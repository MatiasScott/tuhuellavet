<?php
namespace App\Middlewares;use App\Core\Request;class EnvironmentMiddleware{public function handle(Request $request):void{if(active_environment_id()===null){header('Location: '.url('/seleccionar-entorno'));exit;}}}
