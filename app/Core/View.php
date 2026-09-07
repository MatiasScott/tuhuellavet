<?php
namespace App\Core;
class View{public static function render(string $v,array $d=[],string $l='layouts/admin'):void{$vf=APP_PATH.'/Views/'.$v.'.php';$lf=APP_PATH.'/Views/'.$l.'.php';if(!file_exists($vf))throw new \RuntimeException('Vista no encontrada: '.$v);extract($d,EXTR_SKIP);ob_start();require $vf;$content=ob_get_clean();if(file_exists($lf)){require $lf;return;}echo $content;}}
