<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\IContaService;

if (PHP_SAPI !== 'cli') {
    exit("Este script solo puede ejecutarse desde CLI.\n");
}

require dirname(__DIR__) . '/bootstrap/app.php';

if ($argc !== 2) {
    fwrite(
        STDERR,
        "Uso: php scripts/retry_iconta_invoice.php ID\n"
    );
    exit(1);
}

$id = filter_var(
    $argv[1],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false) {
    fwrite(STDERR, "ID inválido.\n");
    exit(1);
}

$db = Database::connection();

$stmt = $db->prepare(
    '
    SELECT
        id,
        documento_fiscal_id,
        estado,
        iconta_id_documento
    FROM iconta_documentos
    WHERE id = :id
    LIMIT 1
    '
);

$stmt->execute([
    'id' => $id,
]);

$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    fwrite(STDERR, "Documento inexistente.\n");
    exit(1);
}

if ($document['estado'] !== 'ERROR') {
    fwrite(
        STDERR,
        "Solo se permite recuperar documentos en ERROR.\n"
    );
    exit(1);
}

if (
    trim((string) ($document['iconta_id_documento'] ?? '')) !== ''
) {
    fwrite(
        STDERR,
        "El documento ya tiene un ID remoto.\n"
    );
    exit(1);
}

/*
 * Lista temporal de documentos autorizados.
 *
 * No incluir la factura de tarjeta de crédito.
 */
$allowedIds = [11, 12];

if (!in_array($id, $allowedIds, true)) {
    fwrite(
        STDERR,
        "Este documento no está autorizado para recuperación.\n"
    );
    exit(1);
}

echo "Documento: {$id}\n";
echo "Estado: ERROR\n";
echo "Documento fiscal: {$document['documento_fiscal_id']}\n";

echo "\nATENCIÓN: Esta operación puede generar una factura REAL en iConta.\n";
echo "Debe haberse comprobado previamente que no existe una factura remota.\n";
echo "Escriba EMITIR para continuar: ";

$confirmation = trim((string) fgets(STDIN));

if ($confirmation !== 'EMITIR') {
    echo "Operación cancelada.\n";
    exit(0);
}

try {
    $service = new IContaService();

    $result = $service->processInvoice($id);

    echo "\nFactura procesada correctamente.\n";
    echo "ID remoto: " . ($result['id_factura'] ?? '') . "\n";
    echo "Número: " . ($result['numero_comprobante'] ?? '') . "\n";
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "ERROR: " . $e->getMessage() . PHP_EOL
    );
    exit(1);
}
