<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\InvoiceCustomerRequirements;
use App\Services\BillingService;
use App\Core\Database;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

class InvoiceCustomerRequirementsTest extends TestCase
{
    private static function customer(): array
    {
        return [
            'activo' => 1,
            'identificacion' => '1712345678',
            'razon_social' => 'Cliente de prueba',
            'email' => 'cliente@example.test',
            'tipo_identificacion_codigo' => 'CEDULA',
            'es_persona_natural' => 1,
            'codigo_pais' => 'ECU',
            'codigo_provincia' => '17',
            'codigo_canton' => '1701',
            'codigo_parroquia' => '170102',
        ];
    }

    public function testValidCustomerDoesNotRequireUndocumentedContactFields(): void
    {
        self::assertSame([], InvoiceCustomerRequirements::problems(self::customer()));
    }

    #[DataProvider('invalidFields')]
    public function testIncompleteOrUnsupportedCustomerIsRejected(array $changes, string $expected): void
    {
        $customer = array_replace(self::customer(), $changes);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expected);
        InvoiceCustomerRequirements::assertReady($customer);
    }

    public static function invalidFields(): array
    {
        return [
            'inactivo' => [['activo' => 0], 'activos'],
            'identificacion' => [['identificacion' => ' '], 'identificación fiscal'],
            'razon social' => [['razon_social' => ''], 'razón social fiscal'],
            'correo ausente' => [['email' => ''], 'correo de facturación'],
            'correo invalido' => [['email' => 'incorrecto'], 'correo de facturación no es válido'],
            'pais' => [['codigo_pais' => ''], 'país del propietario'],
            'provincia' => [['codigo_provincia' => ''], 'provincia del propietario'],
            'canton' => [['codigo_canton' => ''], 'cantón del propietario'],
            'parroquia' => [['codigo_parroquia' => ''], 'parroquia del propietario'],
            'persona ausente' => [['es_persona_natural' => null], 'persona natural o jurídica'],
            'cedula juridica' => [['es_persona_natural' => 0], 'requiere una persona natural'],
            'consumidor final' => [['tipo_identificacion_codigo' => 'CONSUMIDOR_FINAL'], 'consumidor final todavía no'],
        ];
    }

    public function testAllMissingFieldsAreReportedTogether(): void
    {
        $message = implode('; ', InvoiceCustomerRequirements::problems(array_replace(
            self::customer(),
            ['email' => '', 'codigo_canton' => '', 'codigo_parroquia' => '']
        )));
        self::assertStringContainsString('correo de facturación', $message);
        self::assertStringContainsString('cantón', $message);
        self::assertStringContainsString('parroquia', $message);
    }

    public function testIncompleteCustomerRollsBackBeforeCreatingFiscalDocument(): void
    {
        $sale = $this->createMock(PDOStatement::class);
        $sale->method('fetch')->willReturn([
            'estado' => 'FINALIZADA', 'datos_fiscales_id' => 3,
            'total' => 10, 'permite_facturacion_real' => 1, 'es_simulada' => 0,
        ]);
        $paid = $this->createMock(PDOStatement::class);
        $paid->method('fetchColumn')->willReturn('10.00');
        $existing = $this->createMock(PDOStatement::class);
        $existing->method('fetchColumn')->willReturn(false);
        $payments = $this->createMock(PDOStatement::class);
        $payments->method('fetchAll')->willReturn([['iconta_codigo' => '01']]);
        $customer = $this->createMock(PDOStatement::class);
        $customer->method('fetch')->willReturn(array_replace(
            self::customer(),
            ['email' => '', 'codigo_parroquia' => '']
        ));

        $db = $this->createMock(PDO::class);
        $db->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $db->expects(self::once())->method('beginTransaction')->willReturn(true);
        $db->expects(self::once())->method('rollBack')->willReturn(true);
        $db->expects(self::exactly(5))->method('prepare')
            ->willReturnOnConsecutiveCalls($sale, $paid, $existing, $payments, $customer);

        $connection = new ReflectionProperty(Database::class, 'connection');
        $previous = $connection->getValue();
        $connection->setValue(null, $db);
        try {
            try {
                (new BillingService())->queueInvoice(1, 1, 1);
                self::fail('Debe rechazar datos incompletos antes de crear el documento.');
            } catch (RuntimeException $error) {
                self::assertStringContainsString('correo de facturación', $error->getMessage());
                self::assertStringContainsString('parroquia', $error->getMessage());
            }
        } finally {
            $connection->setValue(null, $previous);
        }
    }

    public function testInvalidInvoicePayloadRollsBackFiscalDocument(): void
    {
        $sale = $this->createMock(PDOStatement::class);
        $sale->method('fetch')->willReturn([
            'estado' => 'FINALIZADA', 'datos_fiscales_id' => 3,
            'total' => 10, 'permite_facturacion_real' => 1, 'es_simulada' => 0,
        ]);
        $paid = $this->createMock(PDOStatement::class);
        $paid->method('fetchColumn')->willReturn('10.00');
        $existing = $this->createMock(PDOStatement::class);
        $existing->method('fetchColumn')->willReturn(false);
        $payments = $this->createMock(PDOStatement::class);
        $payments->method('fetchAll')->willReturn([['iconta_codigo' => '01']]);
        $customer = $this->createMock(PDOStatement::class);
        $customer->method('fetch')->willReturn(self::customer());
        $insert = $this->createMock(PDOStatement::class);
        $insert->expects(self::once())->method('execute');
        $payloadSale = $this->createMock(PDOStatement::class);
        $payloadSale->method('fetch')->willReturn(false);

        $db = $this->createMock(PDO::class);
        $db->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $db->method('lastInsertId')->willReturn('8');
        $db->expects(self::once())->method('beginTransaction')->willReturn(true);
        $db->expects(self::once())->method('rollBack')->willReturn(true);
        $db->expects(self::exactly(7))->method('prepare')
            ->willReturnOnConsecutiveCalls($sale, $paid, $existing, $payments, $customer, $insert, $payloadSale);

        $connection = new ReflectionProperty(Database::class, 'connection');
        $previous = $connection->getValue();
        $connection->setValue(null, $db);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('La venta no existe.');
            (new BillingService())->queueInvoice(1, 1, 1);
        } finally {
            $connection->setValue(null, $previous);
        }
    }
}
