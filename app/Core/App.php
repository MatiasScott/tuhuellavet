<?php
namespace App\Core;
class App{public function __construct(private array $config){} public function run():void{Session::start();$router=new Router();foreach(['web.php','auth.php','admin.php','cliente.php','academico.php'] as $f){$p=APP_PATH.'/Routes/'.$f;if(file_exists($p)) require $p;}try{$router->dispatch(Request::capture());}catch(\Throwable $e){http_response_code(500);if($this->config['debug']){echo '<pre>'.htmlspecialchars((string)$e).'</pre>';return;}View::render('errors/500',['message'=>'Ocurrió un error interno.'],'layouts/auth');}}}
