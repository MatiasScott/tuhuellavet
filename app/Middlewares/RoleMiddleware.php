<?php
namespace App\Middlewares;use App\Core\Request;class RoleMiddleware{public function __construct(private ?string $role){}public function handle(Request $request):void{$u=auth_user();if(!$this->role||!in_array($this->role,$u['roles']??[],true)){http_response_code(403);echo 'Rol no autorizado.';exit;}}}
