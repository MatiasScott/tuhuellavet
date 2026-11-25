<?php

declare(strict_types=1);

use App\Services\FiscalSyncService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FiscalStatusPolicyTest extends TestCase
{
    public static function transitions(): iterable
    {
        $states = ['GENERADA', 'POR_FIRMAR', 'ESPERA_ENVIO', 'ENVIADA_SRI', 'ESPERA_AUTORIZACION', 'NEGADO', 'AUTORIZADO', 'ANULADO'];
        foreach ($states as $fiscal) {
            foreach ($states as $local) {
                foreach ($states as $incoming) {
                    $valid = true;
                    foreach ([$fiscal, $local] as $previous) {
                        if ($previous === 'ANULADO' && $incoming !== 'ANULADO') {
                            $valid = false;
                        }
                        if ($previous === 'AUTORIZADO' && !in_array($incoming, ['AUTORIZADO', 'ANULADO'], true)) {
                            $valid = false;
                        }
                    }
                    yield "$fiscal/$local/$incoming" => [$fiscal, $local, $incoming, $valid];
                }
            }
        }
        yield 'normalized' => [' autoriz ado ', 'GENERADA', 'AUTORIZADO', false];
        yield 'case and whitespace' => [' autorizAdo ', 'generada', ' anulado ', true];
        foreach (['', 'DESCONOCIDO', 'ERROR', 'PENDIENTE'] as $unknown) {
            yield "unknown fiscal [$unknown]" => [$unknown, 'GENERADA', 'AUTORIZADO', false];
            yield "unknown local [$unknown]" => ['GENERADA', $unknown, 'AUTORIZADO', false];
            yield "unknown incoming [$unknown]" => ['GENERADA', 'GENERADA', $unknown, false];
        }
    }

    #[DataProvider('transitions')]
    public function testTransitionPolicy(string $fiscal, string $local, string $incoming, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(RuntimeException::class);
        }
        FiscalSyncService::assertTransition($fiscal, $local, $incoming);
        self::assertTrue($valid, 'La transición prohibida no lanzó una excepción.');
    }
}
