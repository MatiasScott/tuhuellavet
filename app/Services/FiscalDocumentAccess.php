<?php
namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class FiscalDocumentAccess
{
    public static function canSync(array $d): bool
    {
        $states = ['GENERADA','POR_FIRMAR','ESPERA_ENVIO','ENVIADA_SRI','ESPERA_AUTORIZACION','NEGADO','AUTORIZADO'];
        $remote = trim((string) ($d['iconta_id_documento'] ?? ''));
        return ($d['tipo_documento'] ?? '') === 'FACTURA'
            && (int) ($d['es_simulada'] ?? 1) === 0
            && (int) ($d['permite_facturacion_real'] ?? 0) === 1
            && (int) ($d['iconta_documento_id'] ?? 0) > 0
            && preg_match('/^[0-9]+$/D', $remote) && (int) $remote > 0
            && in_array($d['estado'] ?? '', $states, true)
            && in_array($d['iconta_estado'] ?? '', $states, true);
    }

    public static function document(int $id, int $env): ?array
    {
        $s = Database::connection()->prepare('SELECT df.*, v.es_simulada, e.permite_facturacion_real, idoc.id AS iconta_documento_id, idoc.iconta_id_documento, idoc.estado AS iconta_estado FROM documentos_fiscales df JOIN ventas v ON v.id=df.venta_id JOIN entornos e ON e.id=v.entorno_id LEFT JOIN iconta_documentos idoc ON idoc.documento_fiscal_id=df.id WHERE df.id=:id AND v.entorno_id=:env AND df.tipo_documento=\'FACTURA\' AND e.activo=1 AND e.deleted_at IS NULL');
        $s->execute(['id'=>$id,'env'=>$env]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function file(array $d, string $kind): ?array
    {
        if (!in_array($kind, ['ride','xml'], true)) return null;
        $id = (int) ($d[$kind === 'ride' ? 'archivo_pdf_id' : 'archivo_xml_id'] ?? 0);
        if ($id <= 0) return null;
        $s = Database::connection()->prepare('SELECT ruta_storage, mime_type, hash_sha256 FROM archivos WHERE id=:id AND deleted_at IS NULL');
        $s->execute(['id'=>$id]);
        $f = $s->fetch(\PDO::FETCH_ASSOC);
        if (!$f) return null;
        $root = realpath(STORAGE_PATH . '/uploads');
        $folder = realpath(STORAGE_PATH . '/uploads/fiscal/' . $kind);
        $path = realpath(STORAGE_PATH . '/uploads/' . ltrim(str_replace('\\','/', $f['ruta_storage']), '/'));
        if (!$root || !$folder || !$path || !str_starts_with($folder, $root . DIRECTORY_SEPARATOR) || !str_starts_with($path, $folder . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) return null;
        $allowed = $kind === 'ride' ? ['application/pdf'] : ['application/xml','text/xml'];
        if (!in_array($f['mime_type'], $allowed, true) || !in_array((new \finfo(FILEINFO_MIME_TYPE))->file($path), $allowed, true)) return null;
        if (!empty($f['hash_sha256']) && !hash_equals($f['hash_sha256'], (string) hash_file('sha256',$path))) return null;
        return ['path'=>$path,'mime'=>$kind === 'ride' ? 'application/pdf' : 'application/xml','name'=>'factura-' . (int)$d['id'] . ($kind === 'ride' ? '.pdf' : '.xml')];
    }
}
