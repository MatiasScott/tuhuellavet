<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class HaciendaMigration
{
    public function run(): void
    {
        $db = Database::connection();
        $db->exec((string) file_get_contents(BASE_PATH . '/scripts/migrations/20261110_hacienda_tables.sql'));
        foreach (['metodo_identificacion' => 'VARCHAR(150) NULL', 'origen' => 'VARCHAR(150) NULL'] as $name => $definition) {
            $this->column($db, 'animales', $name, $definition);
        }
        foreach ([
            'sonido' => 'VARCHAR(100) NULL',
            'ritmo_cardiaco' => 'VARCHAR(100) NULL',
            'mucosas' => 'VARCHAR(100) NULL',
            'llenado_capilar' => 'TEXT NULL',
            'retorno_pliegue_cutaneo' => 'TEXT NULL',
            'deshidratacion' => 'TEXT NULL',
            'presion_arterial' => 'TEXT NULL',
            'saturacion_oxigeno' => 'TEXT NULL',
        ] as $name => $definition) {
            $this->column($db, 'examenes_clinicos_generales', $name, $definition);
            $this->column($db, 'hospitalizacion_signos', $name, $definition);
        }
        $this->column($db, 'productos', 'entorno_id', 'BIGINT UNSIGNED NULL');
        foreach ([
            'precio_venta' => 'DECIMAL(14,4) NULL',
            'costo_compra' => 'DECIMAL(14,4) NULL',
            'precio_incluye_impuesto' => 'BOOLEAN NOT NULL DEFAULT FALSE',
            'impuesto_tarifa_id' => 'BIGINT UNSIGNED NULL',
        ] as $name => $definition) {
            $this->column($db, 'productos', $name, $definition);
        }
        $this->constraint($db, 'productos', 'fk_producto_entorno', 'FOREIGN KEY (entorno_id) REFERENCES entornos(id)');
        $this->column($db, 'productos', 'catalogo_entorno_id', 'BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(entorno_id,0)) STORED');
        $this->constraint($db, 'productos', 'uq_producto_catalogo_codigo', 'UNIQUE (catalogo_entorno_id,codigo)');
        $oldCodeIndex = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='productos' AND INDEX_NAME='uq_producto_codigo'")->fetchColumn();
        if ((int) $oldCodeIndex > 0) {
            $db->exec('ALTER TABLE productos DROP INDEX uq_producto_codigo');
        }
        $this->column($db, 'eventos_clinicos', 'lote_animal_id', 'BIGINT UNSIGNED NULL');
        $nullable = $db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='eventos_clinicos' AND COLUMN_NAME='animal_id'")->fetchColumn();
        if ($nullable === 'NO') {
            $db->exec('ALTER TABLE eventos_clinicos MODIFY animal_id BIGINT UNSIGNED NULL');
        }
        $this->constraint($db, 'eventos_clinicos', 'fk_evento_lote_animal', 'FOREIGN KEY (lote_animal_id) REFERENCES lotes_animales(id)');
        $this->constraint($db, 'eventos_clinicos', 'chk_evento_destino', 'CHECK ((animal_id IS NULL) <> (lote_animal_id IS NULL))');

        // Existing shared products cannot be reassigned without reviewing other environments.
        $conflicts = $db->query("SELECT COUNT(*) FROM inventario_productos ip
            JOIN inventarios i ON i.id=ip.inventario_id JOIN entornos e ON e.id=i.entorno_id
            JOIN productos p ON p.id=ip.producto_id
            WHERE e.codigo='HACIENDA_AGUSBELLA' AND (p.entorno_id IS NULL OR p.entorno_id<>e.id)")->fetchColumn();
        if ((int) $conflicts > 0) {
            throw new RuntimeException('Hay productos compartidos en inventarios de Hacienda. Revise su separacion antes de habilitar la operacion.');
        }

        $permissions = new PermissionService();
        foreach (['LOTES_ANIMALES' => 'Lotes animales', 'PRODUCCION' => 'Produccion', 'GENEALOGIA' => 'Genealogia', 'ALIMENTACION' => 'Alimentacion'] as $code => $name) {
            $permissions->registerModule($code, $name, ['VER', 'CREAR', 'EDITAR']);
        }
        $db->exec("INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
            SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
            JOIN modulos m ON m.id=p.modulo_id
            WHERE r.codigo IN ('ADMINISTRADOR','SUPER_ADMINISTRADOR')
              AND m.codigo IN ('LOTES_ANIMALES','PRODUCCION','GENEALOGIA','ALIMENTACION')");
    }

    private function column(PDO $db, string $table, string $name, string $definition): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $name]);
        if (!(int) $stmt->fetchColumn()) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
        }
    }

    private function constraint(PDO $db, string $table, string $name, string $definition): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
        $stmt->execute([$table, $name]);
        if (!(int) $stmt->fetchColumn()) {
            $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` $definition");
        }
    }
}
