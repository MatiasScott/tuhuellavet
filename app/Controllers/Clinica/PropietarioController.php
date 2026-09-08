<?php
namespace App\Controllers\Clinica;use App\Core\Controller;use App\Core\Request;use App\Core\Session;use App\Models\Owner;use App\Models\Catalog;use App\Services\OwnerService;use Throwable;
class PropietarioController extends Controller
{
 public function index(Request $r):void{$q=trim((string)$r->input('q',''));$this->view('propietarios/index',['title'=>'Propietarios','owners'=>(new Owner())->listByEnvironment(active_environment_id(),$q),'identificationTypes'=>(new Catalog())->identificationTypes(),'search'=>$q,'success'=>Session::pullFlash('success'),'error'=>Session::pullFlash('error')]);}
 public function store(Request $r):void{$this->csrf($r);try{$res=(new OwnerService())->create($r->all(),active_environment_id(),auth_id());$m='Propietario creado correctamente.';if($res['temporary_password'])$m.=' Contraseña temporal: '.$res['temporary_password'];Session::flash('success',$m);}catch(Throwable $e){Session::flash('error',$e->getMessage());}$this->redirect('/propietarios');}
 public function update(Request $r,string $id):void{$this->csrf($r);try{(new OwnerService())->update((int)$id,$r->all(),active_environment_id(),auth_id());Session::flash('success','Propietario actualizado.');}catch(Throwable $e){Session::flash('error',$e->getMessage());}$this->redirect('/propietarios');}
 public function destroy(Request $r,string $id):void{$this->csrf($r);try{(new OwnerService())->delete((int)$id,active_environment_id(),auth_id());Session::flash('success','Propietario eliminado.');}catch(Throwable $e){Session::flash('error',$e->getMessage());}$this->redirect('/propietarios');}
 private function csrf(Request $r):void{if(!Session::validateCsrf($r->input('_token'))){http_response_code(419);exit('Sesión expirada.');}}
}
