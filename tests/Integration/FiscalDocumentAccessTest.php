<?php
namespace Tests\Integration;
use App\Services\FiscalDocumentAccess;
use PHPUnit\Framework\TestCase;
final class FiscalDocumentAccessTest extends TestCase
{
    public function testEligibility(): void
    {
        $d=['tipo_documento'=>'FACTURA','es_simulada'=>0,'permite_facturacion_real'=>1,'iconta_documento_id'=>1,'iconta_id_documento'=>'123','estado'=>'AUTORIZADO','iconta_estado'=>'AUTORIZADO'];
        self::assertTrue(FiscalDocumentAccess::canSync($d));
        foreach (['tipo_documento'=>'NOTA','es_simulada'=>1,'permite_facturacion_real'=>0,'iconta_documento_id'=>0,'iconta_id_documento'=>'0','estado'=>'ANULADO','iconta_estado'=>'ERROR'] as $key=>$value) {
            self::assertFalse(FiscalDocumentAccess::canSync(array_replace($d,[$key=>$value])));
        }
        self::assertNull(FiscalDocumentAccess::file($d,'invalid'));
        self::assertNull(FiscalDocumentAccess::file($d,'ride'));
    }
}
