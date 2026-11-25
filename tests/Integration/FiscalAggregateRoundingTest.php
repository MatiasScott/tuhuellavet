<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Services\IContaService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
final class FiscalAggregateRoundingTest extends TestCase
{
    private function build(array $rows): array
    {
        $key='ICONTA_EXPERIMENTAL_AGGREGATE_ROUNDING';
        $old=$_ENV[$key]??null;
        try {
            $_ENV[$key]='true';
            $details=[];
            foreach($rows as $i=>$r) {
                $details[]=array_combine(['cantidad','precio_unitario','precio_incluye_impuesto','subtotal','descuento','base_imponible','impuesto','total','impuesto_porcentaje'],$r)+['codigo_item'=>'QA-'.$i,'descripcion'=>'QA','impuesto_codigo'=>'IVA'];
            }
            return (new ReflectionMethod(IContaService::class,'buildInvoiceDetails'))->invoke(new IContaService(),$details);
        } finally { if($old===null) unset($_ENV[$key]); else $_ENV[$key]=$old; }
    }
    public function testRealSaleModel(): void
    {
        $p=$this->build([[1,1.5,1,1.5,0,1.30,.20,1.50,15],[1,1.75,1,1.75,.19,1.36,.20,1.56,15],[2,.63,0,1.26,0,1.26,.19,1.45,15],[2,.54,1,1.08,0,.94,.14,1.08,15],[2,.40,1,.80,0,.70,.10,.80,15]]);
        $base=0;
        foreach($p as $d){$gross=round((float)$d['cantidad']*(float)$d['valor_unitario'],2);$base+=$gross-round($gross*(float)$d['porcentaje_descuento']/100,2);}
        self::assertEqualsWithDelta(5.56,$base,.00001);
        self::assertEqualsWithDelta(6.39,$base+round($base*.15,2),.00001);
        self::assertSame('10.857143',$p[1]['porcentaje_descuento']);
    }
    public function testZeroTaxFractionalQuantity(): void
    {
        $p=$this->build([[1.5,2,0,3,.30,2.70,0,2.70,0]]);
        self::assertSame('0',$p[0]['iva_aplicado']);
        self::assertSame('1.50',$p[0]['cantidad']);
    }
    public function testIncompatibleAggregateFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->build([[2,.40,1,.80,0,.70,.10,.80,15]]);
    }
}
