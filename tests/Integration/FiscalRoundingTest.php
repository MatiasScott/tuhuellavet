<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Services\IContaService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;

final class FiscalRoundingTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'VET-176' => [0.40, 2, 0.80, 0.70, 0.10, '0.347826'],
            'VET-177' => [0.63, 2, 1.26, 1.10, 0.16, '0.547826'],
            'existing compatible' => [0.63, 1, 0.63, 0.55, 0.08, '0.547826'],
        ];
    }

    #[DataProvider('cases')]
    public function testExperimentalPayload(float $price, int $qty, float $total, float $base, float $tax, string $expected): void
    {
        $detail = self::detail($price, $qty, $total, $base, $tax);
        $payload = $this->build($detail, true);
        self::assertSame($expected, $payload['valor_unitario']);
        self::assertSame('0.000000', $payload['porcentaje_descuento']);
        self::assertSame('15', $payload['iva_aplicado']);
        self::assertSame(number_format($qty, 2, '.', ''), $payload['cantidad']);
    }

    public function testDisabledByDefault(): void
    {
        $this->expectException(RuntimeException::class);
        $this->build(self::detail(.40, 2, .80, .70, .10), false);
    }

    public function testInconsistentSnapshotRemainsBlocked(): void
    {
        $this->expectException(RuntimeException::class);
        $this->build(self::detail(.40, 2, .80, .69, .11), true);
    }

    private static function detail(float $price, int $qty, float $total, float $base, float $tax): array
    {
        return ['codigo_item'=>'VET-QA', 'descripcion'=>'QA', 'cantidad'=>$qty,
            'precio_unitario'=>$price, 'precio_incluye_impuesto'=>1, 'impuesto_codigo'=>'IVA',
            'impuesto_porcentaje'=>15, 'subtotal'=>$total, 'descuento'=>0,
            'base_imponible'=>$base, 'impuesto'=>$tax, 'total'=>$total];
    }

    private function build(array $detail, bool $enabled): array
    {
        $key = 'ICONTA_EXPERIMENTAL_PRECISE_ROUNDING';
        $previous = $_ENV[$key] ?? null;
        try {
            $_ENV[$key] = $enabled ? 'true' : 'false';
            $method = new ReflectionMethod(IContaService::class, 'buildInvoiceDetails');
            return $method->invoke(new IContaService(), [$detail])[0];
        } finally {
            if ($previous === null) unset($_ENV[$key]); else $_ENV[$key] = $previous;
        }
    }
}
