<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class AnimalController extends Controller
{
    public function index(Request $request, Response $response): never
    {
        $response->view('animales/index', [
            'title' => 'Gestion de animales',
        ]);
    }
}
