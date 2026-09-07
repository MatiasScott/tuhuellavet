<?php
namespace App\Core;
abstract class Controller{protected function view(string $v,array $d=[],string $l='layouts/admin'):void{View::render($v,$d,$l);}protected function redirect(string $p):never{header('Location: '.url($p));exit;}}
