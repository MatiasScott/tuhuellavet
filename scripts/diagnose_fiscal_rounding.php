<?php

declare(strict_types=1);

/*
 * Diagnostico fiscal independiente.
 *
 * No carga .env.
 * No conecta a la base de datos.
 * No realiza peticiones HTTP.
 * No genera facturas.
 */

$cases = [
    ['VET-176 x1', 0.40, 1, 15, true, 0],
    ['VET-176 x2', 0.40, 2, 15, true, 0],
    ['VET-176 x3', 0.40, 3, 15, true, 0],

    ['VET-177 x1', 0.63, 1, 15, true, 0],
    ['VET-177 x2', 0.63, 2, 15, true, 0],
    ['VET-177 x3', 0.63, 3, 15, true, 0],

    ['Venta sin IVA incluido', 0.63, 2, 15, false, 0],
    ['Producto 0.54 x2', 0.54, 2, 15, true, 0],
    ['Producto con descuento', 1.50, 1, 15, true, 0.19],
    ['Producto 1.75', 1.75, 1, 15, true, 0],

    ['IVA 0%', 0.40, 2, 0, false, 0],
    ['Cantidad decimal', 0.63, 1.50, 15, true, 0],
];

function matches(array $a, array $b): bool
{
    foreach (['base', 'iva', 'total'] as $field) {
        if (abs($a[$field] - $b[$field]) >= 0.005) {
            return false;
        }
    }

    return true;
}

function amounts(float $base, float $tax): array
{
    return [
        'base' => round($base, 2),
        'iva' => round($tax, 2),
        'total' => round($base + $tax, 2),
    ];
}

$matchesCurrent = 0;
$matchesPrecise = 0;

foreach ($cases as $case) {
    [
        $name,
        $price,
        $quantity,
        $rate,
        $includesTax,
        $discount
    ] = $case;

    $taxFactor = $rate / 100;

    // Algoritmo original de BillingService.
    $subtotal = round($price * $quantity, 2);
    $commercialTotal = round($subtotal - $discount, 2);

    if ($includesTax && $rate > 0) {
        $localBase = round(
            $commercialTotal / (1 + $taxFactor),
            2
        );

        $localTax = round(
            $commercialTotal - $localBase,
            2
        );
    } else {
        $localBase = $commercialTotal;
        $localTax = round($localBase * $taxFactor, 2);
    }

    $local = amounts($localBase, $localTax);

    // Datos enviados actualmente a iConta.
    $unitPrice = $includesTax && $rate > 0
        ? round($price / (1 + $taxFactor), 6)
        : round($price, 6);

    $discountPercentage = $subtotal > 0
        ? round(($discount / $subtotal) * 100, 6)
        : 0.0;

    // Simulacion actual.
    $grossBase = round($unitPrice * $quantity, 2);

    $discountAmount = round(
        $grossBase * ($discountPercentage / 100),
        2
    );

    $currentBase = round(
        $grossBase - $discountAmount,
        2
    );

    $currentTax = round(
        $currentBase * $taxFactor,
        2
    );

    $current = amounts($currentBase, $currentTax);

    // Simulacion alternativa sin redondeo intermedio.
    $rawBase = $unitPrice
        * $quantity
        * (1 - ($discountPercentage / 100));

    $preciseBase = round($rawBase, 2);
    $preciseTax = round($rawBase * $taxFactor, 2);

    $precise = amounts($preciseBase, $preciseTax);

    // Algoritmo experimental basado en el subtotal comercial.

    $reconciledUnitPrice = $includesTax && $rate > 0
        ? round(
            $subtotal / ($quantity * (1 + $taxFactor)),
            6
        )
        : round($subtotal / $quantity, 6);

    $reconciledRawBase = $reconciledUnitPrice
        * $quantity
        * (1 - ($discountPercentage / 100));

    $reconciledBase = round(
        $reconciledRawBase,
        2
    );

    $reconciledTax = round(
        $reconciledRawBase * $taxFactor,
        2
    );

    $reconciled = amounts(
        $reconciledBase,
        $reconciledTax
    );

    $reconciledOk = matches($local, $reconciled);

    $currentOk = matches($local, $current);
    $preciseOk = matches($local, $precise);

    $matchesCurrent += (int) $currentOk;
    $matchesPrecise += (int) $preciseOk;

    echo PHP_EOL;
    echo str_repeat('=', 65) . PHP_EOL;
    echo $name . PHP_EOL;
    echo str_repeat('=', 65) . PHP_EOL;

    printf(
        "PVP: %.4f | Cantidad: %.2f | IVA: %.2f%%\n",
        $price,
        $quantity,
        $rate
    );

    printf(
        "Precio neto enviado: %.6f\n",
        $unitPrice
    );

    printf(
        "Descuento enviado: %.6f%%\n",
        $discountPercentage
    );

    printf(
        "%-20s %10s %10s %10s\n",
        'ALGORITMO',
        'BASE',
        'IVA',
        'TOTAL'
    );

    foreach (
        [
            'AXXIS' => $local,
            'ACTUAL' => $current,
            'PRECISION' => $precise,
            'CONCILIADO' => $reconciled,
        ] as $label => $values
    ) {
        printf(
            "%-20s %10.2f %10.2f %10.2f\n",
            $label,
            $values['base'],
            $values['iva'],
            $values['total']
        );

        printf(
            "Precio neto conciliado: %.6f\n",
            $reconciledUnitPrice
        );

        echo 'Conciliado: '
            . ($reconciledOk ? 'OK' : 'DIFERENCIA')
            . PHP_EOL;
    }

    echo 'Actual: '
        . ($currentOk ? 'OK' : 'DIFERENCIA')
        . PHP_EOL;

    echo 'Precision: '
        . ($preciseOk ? 'OK' : 'DIFERENCIA')
        . PHP_EOL;
}

echo PHP_EOL;
echo "===== RESUMEN GENERAL =====" . PHP_EOL;

echo 'Casos evaluados: ' . count($cases) . PHP_EOL;

echo 'Conciliados algoritmo actual: '
    . $matchesCurrent . PHP_EOL;

echo 'Conciliados algoritmo precision: '
    . $matchesPrecise . PHP_EOL;

echo 'Peticiones HTTP: 0' . PHP_EOL;
echo 'Modificaciones en base de datos: 0' . PHP_EOL;
echo 'Facturas generadas: 0' . PHP_EOL;
