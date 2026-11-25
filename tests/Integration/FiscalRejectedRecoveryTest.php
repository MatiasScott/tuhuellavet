<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Services\IContaService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
final class FiscalRejectedRecoveryTest extends TestCase {
    public function testRecoveryGate(): void {
        $old=$_ENV;
        try {
            $_ENV['APP_ENV']='production';
            $_ENV['ICONTA_MANUAL_RECOVERY_ENABLED']='true';
            $_ENV['ICONTA_EXPERIMENTAL_AGGREGATE_ROUNDING']='true';
            $d=['intentos'=>1,'iconta_estado'=>'ERROR','documento_estado'=>'ERROR','venta_estado'=>'FINALIZADA','tipo_documento'=>'FACTURA','es_simulada'=>0,'permite_facturacion_real'=>1,'documento_fiscal_id'=>95,'venta_entorno_id'=>1,'ultimo_error'=>'iConta reportó un error: Las formas de cobro superan el valor total de la venta. Total Pagos:6.39 Total Factura: 6.38','request_payload'=>'{}'];
            foreach(['iconta_id_documento','enviado_at','response_payload','numero_documento','clave_acceso','autorizacion','archivo_pdf_id','archivo_xml_id'] as $k) $d[$k]=null;
            $a=['confirmed'=>true,'reference'=>'QA only','fiscal_id'=>95,'environment_id'=>1];
            IContaService::assertRejectedRecovery($d,$a); self::assertTrue(true);
            foreach(['intentos'=>2,'iconta_id_documento'=>'123','enviado_at'=>'2026-11-25','ultimo_error'=>'timeout','es_simulada'=>1,'permite_facturacion_real'=>0,'numero_documento'=>'001','documento_estado'=>'AUTORIZADO','venta_entorno_id'=>2] as $k=>$v) {
                try { IContaService::assertRejectedRecovery(array_replace($d,[$k=>$v]),$a); self::fail('Accepted '.$k); } catch(RuntimeException $e) { self::assertNotEmpty($e->getMessage()); }
            }
            $_ENV['ICONTA_MANUAL_RECOVERY_ENABLED']='false';
            $this->expectException(RuntimeException::class); IContaService::assertRejectedRecovery($d,$a);
        } finally { $_ENV=$old; }
    }
}
