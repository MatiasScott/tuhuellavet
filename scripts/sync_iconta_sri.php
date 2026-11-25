<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__) . '/bootstrap/app.php';
use App\Services\FiscalSyncService;
use App\Services\IContaService;
use App\Core\Database;
$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run','--execute'], true) || count($argv)>2) { fwrite(STDERR,"Uso: --dry-run o --execute\n"); exit(1); }
$execute = $mode === '--execute';
if ($execute && (strtolower((string) ($_ENV['APP_ENV'] ?? '')) !== 'production' || strtolower((string) ($_ENV['ICONTA_SRI_SYNC_ENABLED'] ?? 'false')) !== 'true' || strtolower((string) ($_ENV['ICONTA_ENABLED'] ?? 'false')) !== 'true')) { fwrite(STDERR,"Ejecucion bloqueada: requiere produccion y habilitacion explicita.\n"); exit(1); }
$locked=false; $failed=0; $ok=0; $skipped=0;
try {
    if ($execute) {
        $db=Database::connection();
        $locked=(int)$db->query("SELECT GET_LOCK('thv_iconta_sri_worker',0)")->fetchColumn()===1;
        if (!$locked) { echo "Worker ocupado.\n"; exit(0); }
    }
    $rows=(new FiscalSyncService())->pendingDocuments(20);
    echo ($execute ? 'EJECUCION FISCAL' : 'MODO DIAGNOSTICO - SOLO LECTURA')."\nDocumentos elegibles: ".count($rows)."\n";
    foreach ($rows as $row) {
        $id=(int)$row['id'];
        echo "Documento $id | ".$row['venta_numero'].' | '.$row['estado']."\n";
        if (!$execute) continue;
        try {
            $result=(new IContaService())->syncInvoiceSriStatus($id,true);
            if (!empty($result['omitido'])) { ++$skipped; continue; }
            ++$ok; echo 'Estado: '.$result['estado']."\n";
        } catch (Throwable $e) { ++$failed; fwrite(STDERR,"Documento $id: fallo de sincronizacion; revisar auditoria.\n"); }
        sleep(2);
    }
    echo $execute ? "Exitosos: $ok | Fallidos: $failed | Omitidos: $skipped\n" : "Consultas realizadas a iConta: 0\nRegistros modificados: 0\n";
} catch (Throwable $e) { ++$failed; fwrite(STDERR,"Error del worker: ".$e->getMessage()."\n"); }
finally { if ($locked) $db->query("SELECT RELEASE_LOCK('thv_iconta_sri_worker')"); }
exit($failed ? 1 : 0);
