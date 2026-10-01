<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\IContaService;

require dirname(__DIR__) . '/bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Procesador de cola iConta
|--------------------------------------------------------------------------
|
| Procesa únicamente documentos PENDIENTES que todavía no tengan
| identificador remoto.
|
| La generación de la factura y sus validaciones permanecen dentro
| de IContaService::processInvoice().
|
| Este worker NO envía documentos al SRI.
|
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde CLI.\n");
}

/*
|--------------------------------------------------------------------------
| Bloqueo de ejecución
|--------------------------------------------------------------------------
|
| Evita que dos procesos del worker iConta se ejecuten al mismo tiempo.
| Esto es especialmente importante cuando el script se ejecuta mediante
| cron y una ejecución anterior todavía no ha terminado.
|
*/

$lockFile = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'tuhuellavet_iconta_queue.lock';

$lockHandle = fopen(
    $lockFile,
    'c'
);

if ($lockHandle === false) {
    fwrite(
        STDERR,
        "No se pudo crear el archivo de bloqueo del worker iConta.\n"
    );

    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo '['
        . date('Y-m-d H:i:s')
        . "] Ya existe otro worker iConta en ejecución.\n";

    fclose($lockHandle);

    exit(0);
}

/*
 * El bloqueo permanecerá activo mientras este proceso mantenga
 * abierto $lockHandle. Al finalizar PHP, el sistema lo libera.
 */

$db = Database::connection();
$iConta = new IContaService();

$limit = 10;

echo '[' . date('Y-m-d H:i:s') . "] Procesando cola iConta...\n";

$stmt = $db->prepare(
    '
    SELECT
        id,
        documento_fiscal_id,
        estado,
        intentos

    FROM iconta_documentos

    WHERE estado = "PENDIENTE"
      AND (
          iconta_id_documento IS NULL
          OR TRIM(iconta_id_documento) = ""
      )

    ORDER BY id ASC

    LIMIT :limite
    '
);

$stmt->bindValue(
    ':limite',
    $limit,
    PDO::PARAM_INT
);

$stmt->execute();

$documents = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

if ($documents === []) {
    echo "No existen documentos pendientes.\n";
    exit(0);
}

$processed = 0;
$successful = 0;
$failed = 0;

foreach ($documents as $document) {
    $id = (int) (
        $document['id']
        ?? 0
    );

    if ($id <= 0) {
        continue;
    }

    $processed++;

    echo PHP_EOL;
    echo 'Documento iConta #' . $id . PHP_EOL;

    try {
        $result = $iConta->processInvoice(
            $id
        );

        $successful++;

        echo '  OK' . PHP_EOL;
        echo '  Estado: '
            . ($result['estado'] ?? 'GENERADA')
            . PHP_EOL;

        echo '  Factura iConta: '
            . ($result['id_factura'] ?? '—')
            . PHP_EOL;

        echo '  Comprobante: '
            . ($result['numero_comprobante'] ?? '—')
            . PHP_EOL;
    } catch (Throwable $e) {
        $failed++;

        echo '  ERROR: '
            . $e->getMessage()
            . PHP_EOL;
    }
}

echo PHP_EOL;
echo "----------------------------------------\n";
echo 'Procesados: ' . $processed . PHP_EOL;
echo 'Correctos:  ' . $successful . PHP_EOL;
echo 'Errores:    ' . $failed . PHP_EOL;
echo "----------------------------------------\n";

exit($failed > 0
    ? 1
    : 0);
