<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\BillingService;
use App\Services\IContaService;
use DOMDocument;
use DOMXPath;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

class InvoiceRetryTest extends TestCase
{
    private static function document(): array
    {
        return [
            'tipo_documento' => 'FACTURA',
            'estado' => 'ERROR',
            'iconta_estado' => 'ERROR',
            'venta_estado' => 'FINALIZADA',
            'permite_facturacion_real' => 1,
            'iconta_intentos' => 0,
            'iconta_id_documento' => null,
            'iconta_enviado_at' => null,
            'iconta_documento_id' => 1,
            'iconta_ultimo_error' => 'Persona.CodProvincia es obligatorio.',
        ];
    }

    #[DataProvider('blockedDocuments')]
    public function testUnsafeDocumentsCannotBeRetried(array $changes): void
    {
        $this->assertFalse(
            BillingService::canRetryInvoice(
                array_replace(self::document(), $changes)
            )
        );
    }

    public static function blockedDocuments(): array
    {
        return [
            'otro tipo' => [['tipo_documento' => 'NOTA_CREDITO']],
            'generada' => [['estado' => 'GENERADA']],
            'autorizada' => [['iconta_estado' => 'AUTORIZADO']],
            'anulada' => [['estado' => 'ANULADO']],
            'pendiente' => [['iconta_estado' => 'PENDIENTE']],
            'venta anulada' => [['venta_estado' => 'ANULADA']],
            'entorno academico' => [['permite_facturacion_real' => 0]],
            'entorno desconocido' => [['permite_facturacion_real' => null]],
            'generacion iniciada' => [['iconta_intentos' => 1]],
            'intentos desconocidos' => [['iconta_intentos' => null]],
            'id remoto' => [['iconta_id_documento' => '123']],
            'envio previo' => [['iconta_enviado_at' => '2026-10-21 10:00:00']],
            'sin integracion' => [['iconta_documento_id' => null]],
        ];
    }

    #[DataProvider('locationErrors')]
    public function testExistingLocationErrorsRemainRetryable(string $field): void
    {
        $document = self::document();
        $document['iconta_ultimo_error'] = $field . ' es obligatorio.';

        $this->assertTrue(BillingService::canRetryInvoice($document));
    }

    public static function locationErrors(): array
    {
        return [
            ['Persona.CodProvincia'],
            ['Persona.CodPais'],
            ['Persona.CodCanton'],
            ['Persona.CodParroquia'],
        ];
    }

    #[DataProvider('preGenerationErrors')]
    public function testAnyPreGenerationErrorCanBeRetried(string $error): void
    {
        $document = self::document();
        $document['iconta_ultimo_error'] = $error;
        $this->assertTrue(BillingService::canRetryInvoice($document));

        $document['iconta_intentos'] = 1;
        $this->assertFalse(BillingService::canRetryInvoice($document));
    }

    public static function preGenerationErrors(): array
    {
        return [
            'identificacion' => ['La identificación fiscal del cliente está vacía.'],
            'ubicacion' => ['El propietario no tiene configurado el campo país.'],
            'tipo de persona' => ['Debe indicar si el propietario es una persona natural o jurídica.'],
            'datos fiscales inactivos' => ['Los datos fiscales seleccionados están inactivos.'],
            'configuracion' => ['El método de pago no tiene un código configurado.'],
            'sincronizacion del cliente' => ['Error de conexión.'],
            'timeout antes de generar' => ['Tiempo de espera agotado.'],
            'error nuevo' => ['Nueva validación no conocida previamente.'],
            'sin mensaje' => [''],
        ];
    }

    #[DataProvider('previousGenerationEvidence')]
    public function testProcessingRechecksEvidenceUnderExclusiveLock(
        array $evidence
    ): void {
        $document = array_replace([
            'venta_estado' => 'FINALIZADA',
            'documento_estado' => 'ERROR',
            'iconta_estado' => 'ERROR',
            'tipo_documento' => 'FACTURA',
            'iconta_id_documento' => null,
            'intentos' => 0,
            'enviado_at' => null,
        ], $evidence);

        $lock = $this->createMock(PDOStatement::class);
        $lock->expects($this->once())->method('execute')
            ->with(['lock_name' => 'thv_iconta_invoice_1']);
        $lock->method('fetchColumn')->willReturn(1);
        $select = $this->createMock(PDOStatement::class);
        $select->expects($this->once())->method('execute')->with(['id' => 1]);
        $select->method('fetch')->willReturn($document);
        $release = $this->createMock(PDOStatement::class);
        $release->expects($this->once())->method('execute')
            ->with(['lock_name' => 'thv_iconta_invoice_1']);
        $db = $this->createMock(PDO::class);
        $db->expects($this->exactly(3))->method('prepare')
            ->willReturnOnConsecutiveCalls($lock, $select, $release);

        $service = new IContaService();
        (new ReflectionProperty($service, 'enabled'))->setValue($service, true);
        (new ReflectionProperty($service, 'apiUrl'))->setValue(
            $service,
            'https://iconta.example.test'
        );
        (new ReflectionProperty($service, 'apiKey'))->setValue($service, 'test-key');
        $connection = new ReflectionProperty(Database::class, 'connection');
        $previous = $connection->getValue();
        $connection->setValue(null, $db);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'Se requiere conciliación con iConta antes de reenviarla.'
            );
            $service->processInvoice(1);
        } finally {
            $connection->setValue(null, $previous);
        }
    }

    public static function previousGenerationEvidence(): array
    {
        return [
            'intento previo sin id remoto' => [['intentos' => 1]],
            'fecha de envio sin id remoto' => [
                ['enviado_at' => '2026-10-21 10:00:00'],
            ],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function testEmailValidationFailureCanBeRetriedAfterCorrection(
        string $email
    ): void {
        $service = new IContaService();
        $build = new ReflectionMethod($service, 'buildPersonSavePayload');
        $fiscal = [
            'identificacion' => '9999999999',
            'tipo_identificacion_codigo' => 'CEDULA',
            'es_persona_natural' => 1,
            'nombres' => 'Cliente de prueba',
            'email' => $email,
            'codigo_pais' => 'EC',
            'codigo_provincia' => '17',
            'codigo_canton' => '1701',
            'codigo_parroquia' => '170101',
        ];

        try {
            $build->invoke($service, $fiscal);
            $this->fail('El correo incorrecto debe detener la sincronizacion.');
        } catch (RuntimeException $error) {
            $document = self::document();
            $document['iconta_ultimo_error'] = $error->getMessage();
            $this->assertTrue(BillingService::canRetryInvoice($document));

            $document['iconta_intentos'] = 1;
            $this->assertFalse(BillingService::canRetryInvoice($document));
        }

        $fiscal['email'] = 'cliente@example.test';
        $payload = $build->invoke($service, $fiscal);
        $this->assertSame('cliente@example.test', $payload['Persona']['Email']);
    }

    public static function invalidEmails(): array
    {
        return [
            'ausente' => [''],
            'espacios' => ['   '],
            'invalido' => ['correo-invalido'],
        ];
    }

    public function testMissingDocumentCannotBeRetried(): void
    {
        $this->assertFalse(BillingService::canRetryInvoice([]));
    }

    #[DataProvider('viewRetryCases')]
    public function testViewShowsRetryOnlyWhenAllowed(
        array $changes,
        bool $hasPermission,
        bool $expected
    ): void {
        $document = array_replace(self::document(), [
            'id' => 29,
            'venta_id' => 30,
            'venta_numero' => 'V-TEST',
            'total' => 15,
            'iconta_ultimo_error' => 'El cliente no tiene un correo electrónico configurado '
                . 'en sus datos fiscales. Registre un correo válido '
                . 'antes de sincronizar con iConta.',
        ], $changes);
        $session = $_SESSION ?? [];
        $_SESSION['auth_user'] = [
            'permissions' => $hasPermission ? ['facturacion.facturar'] : [],
        ];

        $sales = $owners = $patients = $products = $services = [];
        $fiscalData = $paymentMethods = $payments = [];
        $documents = [$document];
        $success = $error = null;

        ob_start();
        try {
            require BASE_PATH . '/app/Views/facturacion/index.php';
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
            $_SESSION = $session;
        }

        $this->assertIsString($html);
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($dom->loadHTML($html, LIBXML_NONET));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($dom);
        $this->assertCount(
            $expected ? 1 : 0,
            $xpath->query('//form[contains(@action, "/documentos/29/reintentar")]')
        );
        $this->assertCount(
            6,
            $xpath->query('//section[contains(@class, "fiscal-card")]//tbody/tr/td')
        );
        $this->assertStringNotContainsString('Pendiente de reintento', $html);
    }

    public static function viewRetryCases(): array
    {
        return [
            'correo corregible' => [[], true, true],
            'otro dato faltante' => [
                ['iconta_ultimo_error' => 'La identificación fiscal del cliente está vacía.'],
                true,
                true,
            ],
            'error nuevo' => [
                ['iconta_ultimo_error' => 'Nueva validación no conocida previamente.'],
                true,
                true,
            ],
            'sin permiso' => [[], false, false],
            'generacion iniciada' => [['iconta_intentos' => 1], true, false],
            'id remoto' => [['iconta_id_documento' => '123'], true, false],
            'envio previo' => [
                ['iconta_enviado_at' => '2026-10-21 10:00:00'], true, false,
            ],
            'entorno no fiscal' => [['permite_facturacion_real' => 0], true, false],
        ];
    }
}
