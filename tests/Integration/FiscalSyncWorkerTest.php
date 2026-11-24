<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Services\FiscalSyncService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
final class FiscalSyncWorkerTest extends TestCase
{
    #[DataProvider('intervals')]
    public function testNormalInterval(string $state, ?int $pdf, ?int $xml, ?int $expected): void
    { self::assertSame($expected, FiscalSyncService::nextIntervalMinutes($state,$pdf,$xml)); }
    public static function intervals(): array
    { return [ ['GENERADA',null,null,5], ['POR_FIRMAR',null,null,5], ['ESPERA_ENVIO',null,null,5], ['ENVIADA_SRI',null,null,5], ['ESPERA_AUTORIZACION',null,null,5], ['NEGADO',null,null,60], ['AUTORIZADO',1,2,1440], ['AUTORIZADO',null,2,5], ['AUTORIZADO',1,null,5], ['AUTORIZADO',0,2,5], ['ANULADO',1,2,null], [' autorizado ',1,2,1440] ]; }
    #[DataProvider('retries')]
    public function testRetryInterval(int $errors,int $expected): void
    { self::assertSame($expected,FiscalSyncService::retryIntervalMinutes($errors)); }
    public static function retries(): array
    { return [[1,5],[2,10],[3,20],[4,40],[5,60],[6,180],[65535,180]]; }
    public function testUnknownStateFails(): void
    { $this->expectException(\RuntimeException::class); FiscalSyncService::nextIntervalMinutes('DESCONOCIDO',null,null); }
}
