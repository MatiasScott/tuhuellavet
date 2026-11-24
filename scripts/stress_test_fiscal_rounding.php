<?php

declare(strict_types=1);

/*
 * AXXIS - Prueba masiva de conciliacion fiscal.
 *
 * Sin conexion a base de datos.
 * Sin peticiones HTTP.
 * Sin generacion de facturas.
 */

function money(float $value): float
{
    return round($value, 2, PHP_ROUND_HALF_UP);
}

function fiscalMatches(array $local, array $remote): bool
{
    foreach (['base', 'iva', 'total'] as $key) {
        if (abs($local[$key] - $remote[$key]) >= 0.005) {
            return false;
        }
    }

    return true;
}

function fiscalAmounts(float $base, float $tax): array
{
    return [
        'base' => money($base),
        'iva' => money($tax),
        'total' => money($base + $tax),
    ];
}

$quantities = [
    0.10, 0.25, 0.50, 0.75,
    1.00, 1.25, 1.50, 1.75,
    2.00, 3.00, 4.00, 5.00,
    10.00, 20.00, 50.00,
];

$discountRates = [0, 5, 10, 15, 25];

$taxRates = [0, 15];

$stats = [
    'evaluados' => 0,
    'omitidos' => 0,
    'actual' => 0,
    'precision' => 0,
    'conciliado' => 0,
    'conciliado_base_redondeada' => 0,
];

$failures = [];

$started = microtime(true);

echo "===== AXXIS - STRESS TEST FISCAL =====\n";

for ($cents = 1; $cents <= 1500; $cents++) {

    $price = $cents / 100;

    foreach ($quantities as $quantity) {
        foreach ($discountRates as $discountRate) {
            foreach ($taxRates as $rate) {
                foreach ([false, true] as $includesTax) {

                    $factor = $rate / 100;

                    $subtotal = money(
                        $price * $quantity
                    );

                    $discount = money(
                        $subtotal * $discountRate / 100
                    );

                    $commercial = money(
                        $subtotal - $discount
                    );

                    if ($subtotal <= 0 || $commercial <= 0) {
                        $stats['omitidos']++;
                        continue;
                    }

                    $stats['evaluados']++;

                    // Snapshot fiscal original de AXXIS.

                    if ($includesTax && $rate > 0) {
                        $localBase = money(
                            $commercial / (1 + $factor)
                        );

                        $localTax = money(
                            $commercial - $localBase
                        );
                    } else {
                        $localBase = $commercial;

                        $localTax = money(
                            $localBase * $factor
                        );
                    }

                    $local = fiscalAmounts(
                        $localBase,
                        $localTax
                    );

                    $discountPercentage = round(
                        ($discount / $subtotal) * 100,
                        6
                    );

                    $discountFactor =
                        $discountPercentage / 100;

                    // Algoritmo actual.

                    $unitPrice =
                        $includesTax && $rate > 0
                        ? round($price / (1 + $factor), 6)
                        : round($price, 6);

                    $grossBase = money(
                        $unitPrice * $quantity
                    );

                    $remoteDiscount = money(
                        $grossBase * $discountFactor
                    );

                    $currentBase = money(
                        $grossBase - $remoteDiscount
                    );

                    $currentTax = money(
                        $currentBase * $factor
                    );

                    $current = fiscalAmounts(
                        $currentBase,
                        $currentTax
                    );

                    // Algoritmo con precision decimal.

                    $rawBase = $unitPrice
                        * $quantity
                        * (1 - $discountFactor);

                    $precise = fiscalAmounts(
                        money($rawBase),
                        money($rawBase * $factor)
                    );

                    // Precio neto conciliado.

                    $reconciledUnit =
                        $includesTax && $rate > 0
                        ? round(
                            $subtotal /
                            ($quantity * (1 + $factor)),
                            6
                        )
                        : round(
                            $subtotal / $quantity,
                            6
                        );

                    $reconciledRawBase =
                        $reconciledUnit
                        * $quantity
                        * (1 - $discountFactor);

                    $reconciled = fiscalAmounts(
                        money($reconciledRawBase),
                        money(
                            $reconciledRawBase * $factor
                        )
                    );

                    // Mismo precio conciliado, pero
                    // simulando redondeo intermedio.

                    $roundedGross = money(
                        $reconciledUnit * $quantity
                    );

                    $roundedDiscount = money(
                        $roundedGross * $discountFactor
                    );

                    $roundedBase = money(
                        $roundedGross - $roundedDiscount
                    );

                    $rounded = fiscalAmounts(
                        $roundedBase,
                        money($roundedBase * $factor)
                    );

                    $stats['actual'] += (int)
                        fiscalMatches($local, $current);

                    $stats['precision'] += (int)
                        fiscalMatches($local, $precise);

                    $reconciledOk = fiscalMatches(
                        $local,
                        $reconciled
                    );

                    $stats['conciliado'] += (int)
                        $reconciledOk;

                    $stats['conciliado_base_redondeada'] +=
                        (int) fiscalMatches($local, $rounded);

                    if (
                        !$reconciledOk
                        && count($failures) < 20
                    ) {
                        $failures[] = [
                            'pvp' => $price,
                            'cantidad' => $quantity,
                            'iva_porcentaje' => $rate,
                            'iva_incluido' => $includesTax,
                            'descuento' => $discount,
                            'local' => $local,
                            'conciliado' => $reconciled,
                            'precio_neto' => $reconciledUnit,
                        ];
                    }
                }
            }
        }
    }
}

echo "\n===== RESULTADOS =====\n";

echo 'Casos evaluados: '
    . $stats['evaluados'] . PHP_EOL;

echo 'Casos omitidos: '
    . $stats['omitidos'] . PHP_EOL;

foreach ([
    'actual',
    'precision',
    'conciliado',
    'conciliado_base_redondeada',
] as $algorithm) {

    $success = $stats[$algorithm];

    $failed = $stats['evaluados'] - $success;

    $percentage = $stats['evaluados'] > 0
        ? $success / $stats['evaluados'] * 100
        : 0;

    printf(
        "%-30s OK: %d | Fallos: %d | %.4f%%\n",
        strtoupper($algorithm),
        $success,
        $failed,
        $percentage
    );
}

echo "\n===== MUESTRA DE CASOS NO CONCILIADOS =====\n";

if ($failures === []) {
    echo "Sin diferencias detectadas.\n";
} else {
    echo json_encode(
        $failures,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
}

printf(
    "\nTiempo de ejecucion: %.3f segundos\n",
    microtime(true) - $started
);

echo "Peticiones HTTP: 0\n";
echo "Modificaciones en base de datos: 0\n";
echo "Facturas generadas: 0\n";
