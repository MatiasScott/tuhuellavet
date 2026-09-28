<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;

class PublicController extends Controller
{
    public function home(Request $request): void
    {
        $this->view(
            'public/home',
            [],
            'layouts/public'
        );
    }

    public function privacy(Request $request): void
    {
        $this->view(
            'public/privacy',
            [],
            'layouts/public'
        );
    }

    public function terms(Request $request): void
    {
        $this->view(
            'public/terms',
            [],
            'layouts/public'
        );
    }
}
