<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__).'/bootstrap/app.php';
if ($argc !== 6 || $argv[1] !== '--execute-confirmed' || !ctype_digit($argv[2]) || !ctype_digit($argv[3]) || !ctype_digit($argv[4])) {
    fwrite(STDERR, "Uso: php scripts/recover_iconta_rejected_invoice.php --execute-confirmed ID_ICONTA ID_FISCAL ENTORNO REFERENCIA_CONFIRMACION_PROVEEDOR\n"); exit(1);
}
foreach (["ICONTA_MANUAL_RECOVERY_ENABLED", "ICONTA_EXPERIMENTAL_AGGREGATE_ROUNDING"] as $key) { $_ENV[$key] = getenv($key) ?: "false"; }
try {
    $result = (new \App\Services\IContaService())->processInvoice((int)$argv[2], [
        'confirmed'=>true, 'fiscal_id'=>(int)$argv[3], 'environment_id'=>(int)$argv[4], 'reference'=>$argv[5]
    ]);
    echo json_encode(array_intersect_key($result,array_flip(['iconta_documento_id','documento_fiscal_id','id_factura','numero_comprobante','estado'])), JSON_THROW_ON_ERROR).PHP_EOL;
} catch (\Throwable $e) { fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1); }
