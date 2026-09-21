<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Laboratory;
use App\Models\Patient;
use App\Models\Catalog;
use App\Services\LaboratoryService;
use Throwable;

class LaboratorioController extends Controller
{
    public function index(Request $r): void
    {
        $q = trim((string)$r->input('q', ''));
        $this->view('laboratorio/index', ['title' => 'Laboratorio clínico', 'exams' => (new Laboratory())->list(active_environment_id(), $q), 'patients' => (new Patient())->allByEnvironment(active_environment_id()), 'types' => (new Catalog())->labExamTypes(), 'search' => $q, 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
    }
    public function store(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }
        try {
            (new LaboratoryService())->create($r->all(), $r->files(), active_environment_id(), auth_id());
            Session::flash('success', 'Examen de laboratorio registrado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/laboratorio');
    }

    public function delete(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        try {

            $examId = (int) $r->input('examen_id', 0);

            $reason = trim(
                (string) $r->input('motivo_anulacion', '')
            );

            (new LaboratoryService())->delete(
                $examId,
                active_environment_id(),
                auth_id(),
                $reason
            );

            Session::flash(
                'success',
                'Examen anulado correctamente.'
            );
        } catch (Throwable $e) {

            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect('/laboratorio');
    }

    public function update(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        try {

            $examId = (int) $r->input(
                'examen_id',
                0
            );

            (new LaboratoryService())->update(
                $examId,
                $r->all(),
                $r->files(),
                active_environment_id(),
                auth_id()
            );

            Session::flash(
                'success',
                'Examen de laboratorio actualizado correctamente.'
            );
        } catch (Throwable $e) {

            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect('/laboratorio');
    }

    public function viewFile(Request $r): void
    {
        $fileId = (int) $r->input('id', 0);

        if ($fileId <= 0) {
            http_response_code(400);
            echo 'Archivo no válido.';
            return;
        }

        $file = (new Laboratory())->findFile(
            $fileId,
            active_environment_id()
        );

        if (!$file) {
            http_response_code(404);
            echo 'Documento no encontrado.';
            return;
        }

        /*
     * Solo permitimos archivos almacenados
     * dentro de la carpeta de laboratorio.
     */
        $relativePath = str_replace(
            '\\',
            '/',
            (string) $file['ruta_storage']
        );

        if (
            !preg_match(
                '~^laboratorio/[a-f0-9]{36}\.(pdf|jpg|png|webp)$~D',
                $relativePath
            )
        ) {
            http_response_code(403);
            echo 'Ruta de archivo no permitida.';
            return;
        }

        $uploadsRoot = realpath(
            STORAGE_PATH . '/uploads'
        );

        if ($uploadsRoot === false) {
            http_response_code(404);
            echo 'Almacenamiento no disponible.';
            return;
        }

        $path = realpath(
            $uploadsRoot . '/' . $relativePath
        );

        if (
            $path === false
            || !str_starts_with(
                str_replace('\\', '/', $path),
                rtrim(
                    str_replace('\\', '/', $uploadsRoot),
                    '/'
                ) . '/'
            )
            || !is_file($path)
            || !is_readable($path)
        ) {
            http_response_code(404);
            echo 'Archivo físico no encontrado.';
            return;
        }

        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        $mime = (string) $file['mime_type'];

        if (!in_array($mime, $allowedMimeTypes, true)) {
            http_response_code(415);
            echo 'Tipo de documento no permitido.';
            return;
        }

        /*
     * Comprobar el tipo real del archivo.
     */
        $realMime = (new \finfo(FILEINFO_MIME_TYPE))
            ->file($path);

        if ($realMime !== $mime) {
            http_response_code(415);
            echo 'El tipo del archivo no coincide.';
            return;
        }

        /*
     * Evitar que el documento quede almacenado
     * en cachés compartidas.
     */
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: sandbox');
        header('X-Frame-Options: SAMEORIGIN');

        readfile($path);
        exit;
    }
}
