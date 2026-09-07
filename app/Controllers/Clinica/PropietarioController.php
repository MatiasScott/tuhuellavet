<?php
namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Owner;

class PropietarioController extends Controller
{
    public function index(Request $request): void
    {
        $search = trim((string)$request->input('q',''));
        $this->view('propietarios/index',[
            'title'=>'Propietarios',
            'owners'=>(new Owner())->listByEnvironment(active_environment_id(),$search),
            'search'=>$search,
        ]);
    }
}
