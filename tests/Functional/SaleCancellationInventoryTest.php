<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\BillingService;
use RuntimeException;
use Tests\TestCase;

class SaleCancellationInventoryTest extends TestCase
{
    private const TABLES = [
        'ventas', 'pagos', 'documentos_fiscales', 'tipos_movimiento_inventario',
        'movimientos_inventario', 'auditoria',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $definitions = [
            'ventas' => 'id INT PRIMARY KEY, numero VARCHAR(80), entorno_id INT, total DECIMAL(14,2), estado VARCHAR(30), anulada_at DATETIME NULL, anulada_por INT NULL, motivo_anulacion VARCHAR(255) NULL',
            'pagos' => 'id INT PRIMARY KEY, venta_id INT, monto DECIMAL(14,2), estado VARCHAR(30), anulado_at DATETIME NULL',
            'documentos_fiscales' => 'id INT PRIMARY KEY, venta_id INT, estado VARCHAR(30)',
            'tipos_movimiento_inventario' => 'id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(50) UNIQUE, nombre VARCHAR(100), factor SMALLINT',
            'movimientos_inventario' => 'id INT AUTO_INCREMENT PRIMARY KEY, inventario_id INT, producto_id INT, lote_id INT NULL, tipo_movimiento_id INT, cantidad DECIMAL(18,4), costo_unitario DECIMAL(18,6) NULL, realizado_por INT, fecha_movimiento DATETIME, referencia_tipo VARCHAR(60), referencia_id INT, observaciones TEXT NULL, created_at DATETIME',
            'auditoria' => 'id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NULL, entorno_id INT NULL, modulo VARCHAR(50), accion VARCHAR(50), tabla_afectada VARCHAR(100), registro_id INT, datos_anteriores TEXT NULL, datos_nuevos TEXT NULL, ip_address VARCHAR(45) NULL, user_agent TEXT NULL',
        ];

        foreach ($definitions as $table => $definition) {
            $this->db()->exec("CREATE TEMPORARY TABLE {$table} ({$definition}) ENGINE=InnoDB");
        }

        $this->db()->exec("INSERT INTO ventas VALUES (1, 'QA-1', 1, 10, 'FINALIZADA', NULL, NULL, NULL)");
        $this->db()->exec("INSERT INTO tipos_movimiento_inventario (id, codigo, nombre, factor) VALUES (1, 'SALIDA', 'Salida', -1)");
    }

    protected function tearDown(): void
    {
        if ($this->db()->inTransaction()) {
            $this->db()->rollBack();
        }

        foreach (self::TABLES as $table) {
            $this->db()->exec("DROP TEMPORARY TABLE IF EXISTS {$table}");
        }

        parent::tearDown();
    }

    private function addSaleExit(): void
    {
        $this->db()->exec("INSERT INTO movimientos_inventario
            (id, inventario_id, producto_id, lote_id, tipo_movimiento_id, cantidad, costo_unitario, realizado_por, fecha_movimiento, referencia_tipo, referencia_id, created_at)
            VALUES (10, 4, 5, 6, 1, 2.5000, 3.125000, 1, NOW(), 'VENTA', 1, NOW())");
    }

    private function migrate(): void
    {
        $sql = file_get_contents(BASE_PATH . '/scripts/migrations/20261107_devolucion_venta.sql');
        self::assertIsString($sql);
        $this->db()->exec($sql);
    }

    public function testMigrationIsIdempotentAndCancellationRestoresOriginalLot(): void
    {
        $this->migrate();
        $this->migrate();
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM tipos_movimiento_inventario WHERE codigo='DEVOLUCION_VENTA' AND factor=1")->fetchColumn());
        $this->addSaleExit();

        (new BillingService())->cancelSale(1, 'Prueba', 1, 1);

        $row = $this->db()->query("SELECT m.*, t.factor FROM movimientos_inventario m JOIN tipos_movimiento_inventario t ON t.id=m.tipo_movimiento_id WHERE m.referencia_tipo='ANULACION_VENTA'")->fetch();
        self::assertSame(4, (int) $row['inventario_id']);
        self::assertSame(5, (int) $row['producto_id']);
        self::assertSame(6, (int) $row['lote_id']);
        self::assertSame('2.5000', $row['cantidad']);
        self::assertSame('3.125000', $row['costo_unitario']);
        self::assertSame(10, (int) $row['referencia_id']);
        self::assertSame(1, (int) $row['factor']);
        self::assertSame('ANULADA', $this->db()->query('SELECT estado FROM ventas WHERE id=1')->fetchColumn());

        try {
            (new BillingService())->cancelSale(1, 'Otra vez', 1, 1);
            self::fail('Debe impedirse una segunda anulación.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('ya se encuentra anulada', $e->getMessage());
        }
        self::assertSame(1, (int) $this->db()->query("SELECT COUNT(*) FROM movimientos_inventario WHERE referencia_tipo='ANULACION_VENTA'")->fetchColumn());
    }

    public function testServiceOnlySaleDoesNotRequireReturnType(): void
    {
        (new BillingService())->cancelSale(1, 'Prueba', 1, 1);
        self::assertSame('ANULADA', $this->db()->query('SELECT estado FROM ventas WHERE id=1')->fetchColumn());
    }

    public function testMissingOrIncorrectReturnTypeLeavesSaleAndStockUnchanged(): void
    {
        $this->addSaleExit();

        foreach ([null, -1] as $factor) {
            if ($factor !== null) {
                $this->db()->exec("INSERT INTO tipos_movimiento_inventario (codigo, nombre, factor) VALUES ('DEVOLUCION_VENTA', 'Incorrecto', -1)");
                $this->migrate();
            }

            try {
                (new BillingService())->cancelSale(1, 'Prueba', 1, 1);
                self::fail('Debe rechazarse un tipo de devolución ausente o incorrecto.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString(
                    $factor === null ? 'No se encontró el tipo de movimiento DEVOLUCION_VENTA' : 'debe tener factor 1',
                    $e->getMessage()
                );
            }

            self::assertSame('FINALIZADA', $this->db()->query('SELECT estado FROM ventas WHERE id=1')->fetchColumn());
            self::assertSame(1, (int) $this->db()->query('SELECT COUNT(*) FROM movimientos_inventario')->fetchColumn());
        }
    }

    public function testPaymentsAndFiscalDocumentsPreventCancellation(): void
    {
        $this->db()->exec("INSERT INTO pagos VALUES (1, 1, 10, 'REGISTRADO', NULL)");
        try {
            (new BillingService())->cancelSale(1, 'Prueba', 1, 1);
            self::fail('Debe bloquearse la anulación con pagos vigentes.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Anule primero los pagos', $e->getMessage());
        }

        $this->db()->exec('DELETE FROM pagos');
        $this->db()->exec("INSERT INTO documentos_fiscales VALUES (1, 1, 'EMITIDO')");
        try {
            (new BillingService())->cancelSale(1, 'Prueba', 1, 1);
            self::fail('Debe bloquearse la anulación con documento fiscal.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('documento fiscal asociado', $e->getMessage());
        }

        self::assertSame('FINALIZADA', $this->db()->query('SELECT estado FROM ventas WHERE id=1')->fetchColumn());
    }
}
