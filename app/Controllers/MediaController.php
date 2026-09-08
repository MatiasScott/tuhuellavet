<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Database;

class MediaController extends Controller
{
    public function patientPhoto(Request $request, string $filename): void { $this->serveUpload('patients',$filename,['image/jpeg','image/png','image/webp']); }
    public function document(Request $request, string $id): void
    {
        $s=Database::connection()->prepare('SELECT * FROM archivos WHERE id=:id AND deleted_at IS NULL LIMIT 1');$s->execute(['id'=>(int)$id]);$f=$s->fetch();
        if(!$f){http_response_code(404);return;}
        $path=STORAGE_PATH.'/uploads/'.ltrim((string)$f['ruta_storage'],'/');
        if(!is_file($path)){http_response_code(404);return;}
        header('Content-Type: '.$f['mime_type']);
        header('Content-Disposition: inline; filename="'.rawurlencode($f['nombre_original']).'"');
        header('X-Content-Type-Options: nosniff'); readfile($path); exit;
    }
    private function serveUpload(string $dir,string $filename,array $mimeAllowed): void
    {
        $filename=basename($filename);$path=STORAGE_PATH.'/uploads/'.$dir.'/'.$filename;if(!is_file($path)){http_response_code(404);return;}
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream'; if(!in_array($mime,$mimeAllowed,true)){http_response_code(403);return;}
        header('Content-Type: '.$mime);header('Cache-Control: private, max-age=3600');readfile($path);exit;
    }
}
