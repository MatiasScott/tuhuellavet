<?php
namespace App\Services;
use App\Core\Database;
use RuntimeException;

class FileService
{
    public function store(array $file,int $userId,string $folder='documents',array $allowed=['application/pdf','image/jpeg','image/png','image/webp']): int
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('No se recibió un archivo válido.');
        $tmp=$file['tmp_name'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if(!in_array($mime,$allowed,true)) throw new RuntimeException('Tipo de archivo no permitido.');
        $max=(int)($_ENV['UPLOAD_MAX_BYTES']??10485760); if((int)$file['size']>$max) throw new RuntimeException('El archivo supera el tamaño permitido.');
        $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$safe=bin2hex(random_bytes(18)).($ext?'.'.$ext:'');
        $relative=trim($folder,'/').'/'.$safe;$dir=STORAGE_PATH.'/uploads/'.trim($folder,'/');if(!is_dir($dir))mkdir($dir,0775,true);
        $dest=STORAGE_PATH.'/uploads/'.$relative;if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException('No fue posible almacenar el archivo.');
        $db=Database::connection();$s=$db->prepare('INSERT INTO archivos(nombre_original,nombre_almacenado,ruta_storage,extension,mime_type,tamano_bytes,hash_sha256,subido_por) VALUES(:o,:n,:r,:e,:m,:t,:h,:u)');
        $s->execute(['o'=>$file['name'],'n'=>$safe,'r'=>$relative,'e'=>$ext?:null,'m'=>$mime,'t'=>(int)$file['size'],'h'=>hash_file('sha256',$dest),'u'=>$userId]);return (int)$db->lastInsertId();
    }
}
