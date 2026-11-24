<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\IContaService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class InvoiceFiscalSyncTest extends TestCase
{
    private function service(bool $enabled = true): IContaService
    {
        $service = new IContaService();
        foreach (['enabled' => $enabled, 'apiUrl' => 'https://iconta.example.test', 'apiKey' => 'test-key'] as $name => $value) {
            (new ReflectionProperty($service, $name))->setValue($service, $value);
        }
        return $service;
    }

    private function withConnection(PDO $db, callable $callback): void
    {
        $property = new ReflectionProperty(Database::class, 'connection');
        $previous = $property->getValue();
        $property->setValue(null, $db);
        try {
            $callback();
        } finally {
            $property->setValue(null, $previous);
        }
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidRequestDoesNotAccessDatabase(bool $enabled, int $id, string $message): void
    {
        $db = $this->createMock(PDO::class);
        $db->expects($this->never())->method('prepare');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $this->withConnection($db, fn() => $this->service($enabled)->syncInvoiceSriStatus($id));
    }

    public static function invalidRequests(): array
    {
        return [
            'disabled' => [false, 42, 'La integración con iConta está deshabilitada.'],
            'zero' => [true, 0, 'El documento de iConta indicado no es válido.'],
            'negative' => [true, -1, 'El documento de iConta indicado no es válido.'],
        ];
    }

    #[DataProvider('unavailableLocks')]
    public function testUnavailableLockStopsProcessing(mixed $result): void
    {
        $lock = $this->createMock(PDOStatement::class);
        $lock->expects($this->once())->method('execute')->with(['lock_name' => 'thv_iconta_invoice_42']);
        $lock->method('fetchColumn')->willReturn($result);
        $db = $this->createMock(PDO::class);
        $db->expects($this->once())->method('prepare')->with('SELECT GET_LOCK(:lock_name, 10)')->willReturn($lock);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El documento fiscal está siendo procesado.');
        $this->withConnection($db, fn() => $this->service()->syncInvoiceSriStatus(42));
    }

    public static function unavailableLocks(): array
    {
        return ['busy' => [0], 'unavailable' => [null]];
    }

    #[DataProvider('invalidDocuments')]
    public function testLockIsReleasedOnValidationFailure(array|false $document, string $message): void
    {
        $this->assertReleaseAfterFailure($document, $message, false);
    }

    public static function invalidDocuments(): array
    {
        return [
            'missing' => [false, 'El documento de iConta no existe.'],
            'wrong type' => [['tipo_documento' => 'NOTA_CREDITO'], 'El documento indicado no corresponde a una factura.'],
            'missing remote id' => [['tipo_documento' => 'FACTURA', 'iconta_id_documento' => null], 'La factura no tiene un identificador válido en iConta.'],
            'invalid remote id' => [['tipo_documento' => 'FACTURA', 'iconta_id_documento' => 'abc'], 'La factura no tiene un identificador válido en iConta.'],
        ];
    }

    public function testLockIsReleasedOnDatabaseException(): void
    {
        $this->assertReleaseAfterFailure(false, 'Fallo simulado de consulta', true);
    }

    private function assertReleaseAfterFailure(array|false $document, string $message, bool $failQuery): void
    {
        $lock = $this->createMock(PDOStatement::class);
        $lock->expects($this->once())->method('execute')->with(['lock_name' => 'thv_iconta_invoice_42']);
        $lock->method('fetchColumn')->willReturn(1);
        $select = $this->createMock(PDOStatement::class);
        $execution = $select->expects($this->once())->method('execute')->with(['id' => 42]);
        if ($failQuery) {
            $execution->willThrowException(new RuntimeException($message));
            $select->expects($this->never())->method('fetch');
        } else {
            $select->expects($this->once())->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($document);
        }
        $release = $this->createMock(PDOStatement::class);
        $release->expects($this->once())->method('execute')->with(['lock_name' => 'thv_iconta_invoice_42']);
        $db = $this->createMock(PDO::class);
        $calls = 0;
        $db->expects($this->exactly(3))->method('prepare')->willReturnCallback(
            function (string $sql) use (&$calls, $lock, $select, $release): PDOStatement {
                return match (++$calls) {
                    1 => $this->checkedStatement('SELECT GET_LOCK(:lock_name, 10)', $sql, $lock),
                    2 => $this->checkedSelect($sql, $select),
                    3 => $this->checkedStatement('SELECT RELEASE_LOCK(:lock_name)', $sql, $release),
                };
            }
        );
        $db->expects($this->never())->method('beginTransaction');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $this->withConnection($db, fn() => $this->service()->syncInvoiceSriStatus(42));
    }

    private function checkedStatement(string $expected, string $actual, PDOStatement $statement): PDOStatement
    {
        self::assertSame($expected, $actual);
        return $statement;
    }

    private function checkedSelect(string $sql, PDOStatement $statement): PDOStatement
    {
        self::assertStringContainsString('FROM iconta_documentos idoc', $sql);
        self::assertStringContainsString('WHERE idoc.id = :id', $sql);
        return $statement;
    }
}
