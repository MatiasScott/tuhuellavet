<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;

class MediaController extends Controller
{
    public function patientPhoto(
        Request $request,
        string $filename
    ): void {
        if (
            !preg_match(
                '/^[a-f0-9]{32}\.(jpg|png|webp)$/',
                $filename
            )
        ) {
            http_response_code(404);

            return;
        }

        $path
            = STORAGE_PATH
            . '/uploads/pacientes/'
            . $filename;

        if (
            !is_file($path)
        ) {
            http_response_code(404);

            return;
        }

        $mime
            = mime_content_type(
                $path
            );

        header(
            'Content-Type: '
            . $mime
        );

        header(
            'Content-Length: '
            . filesize($path)
        );

        header(
            'Cache-Control: private, max-age=86400'
        );

        readfile($path);

        exit;
    }
}