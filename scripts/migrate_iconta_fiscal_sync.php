<?php
declare(strict_types=1);
use App\Core\Database;
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$apply = in_array('--apply', $argv, true);
require dirname(__DIR__).'/bootstrap/app.php';
try {
    $db = Database::connection();
    $name = $db->query('SELECT DATABASE()')->fetchColumn();
    $env = $_ENV['APP_ENV'] ?? '';
    $host = $_ENV['DB_HOST'] ?? '';
    if (!in_array($host, ['localhost','127.0.0.1'], true) || ! (($env === 'local' && $name === 'vet_academic_erp') || ($env === 'testing' && $name === 'tuhuellavet_qa'))) {
        throw new RuntimeException('Solo se permite desarrollo local o QA.');
    }
    $lock = 'axxis_fiscal_sync_migration_'.$name;
    $stmt = $db->prepare('SELECT GET_LOCK(?, 10)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) { throw new RuntimeException('Migracion ocupada.'); }
    try {
        $columns = $db->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iconta_documentos'")->fetchAll(PDO::FETCH_ASSOC);
        if (!$columns) { throw new RuntimeException('Tabla iconta_documentos inexistente.'); }
        $existing = array_column($columns, null, 'COLUMN_NAME');
        $spec = [
            'ultima_consulta_sri_at' => ['datetime','YES','DATETIME NULL'],
            'proxima_consulta_sri_at' => ['datetime','YES','DATETIME NULL'],
            'consulta_sri_exitosa_at' => ['datetime','YES','DATETIME NULL'],
            'errores_consulta_sri' => ['smallint','NO','SMALLINT UNSIGNED NOT NULL DEFAULT 0'],
            'ultimo_error_consulta_sri' => ['text','YES','TEXT NULL'],
        ];
        $changes = [];
        foreach ($spec as $column => [$type,$nullable,$ddl]) {
            if (!isset($existing[$column])) { $changes[] = "ADD COLUMN `$column` $ddl"; continue; }
            $c = $existing[$column];
            $default = $c['COLUMN_DEFAULT'];
            $valid = $c['DATA_TYPE'] === $type && $c['IS_NULLABLE'] === $nullable && $c['EXTRA'] === '';
            $valid = $valid && ($type === 'smallint' ? preg_match('/^smallint(?:\(\d+\))? unsigned$/i', $c['COLUMN_TYPE']) === 1 && (string)$default === '0' : ($default === null || strtoupper((string)$default) === 'NULL') && $c['COLUMN_TYPE'] === $type);
            if (!$valid) { throw new RuntimeException('Columna incompatible: '.$column); }
            echo "OK $column\n";
        }
        $idx = $db->query("SELECT COLUMN_NAME, NON_UNIQUE, SUB_PART, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iconta_documentos' AND INDEX_NAME = 'idx_iconta_sync_sri' ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
        if (!$idx) { $changes[] = 'ADD INDEX idx_iconta_sync_sri (estado, proxima_consulta_sri_at, id)'; }
        else {
            if (array_column($idx,'COLUMN_NAME') !== ['estado','proxima_consulta_sri_at','id']) { throw new RuntimeException('Indice incompatible.'); }
            foreach ($idx as $i) { if ((int)$i['NON_UNIQUE'] !== 1 || $i['SUB_PART'] !== null || $i['INDEX_TYPE'] !== 'BTREE') { throw new RuntimeException('Indice incompatible.'); } }
            echo "OK idx_iconta_sync_sri\n";
        }
        if (!$changes) { echo "Migracion completa; sin cambios.\n"; }
        else { $sql = 'ALTER TABLE iconta_documentos '.implode(', ', $changes); echo $sql."\n"; if ($apply) { $db->exec($sql); echo "Migracion aplicada.\n"; } else { echo "Solo diagnostico; use --apply para aplicar.\n"; } }
    } finally { $stmt = $db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); }
