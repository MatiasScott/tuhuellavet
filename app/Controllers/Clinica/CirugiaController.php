<?php

declare(strict_types=1);

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Catalog;
use App\Models\Patient;
use App\Models\Surgery;
use App\Services\SurgeryService;
use Throwable;

class CirugiaController extends Controller
{
    public function index(
        Request $request
    ): void {
        $search = trim(
            (string) $request->input('q', '')
        );

        $catalog = new Catalog();

        $this->view(
            'cirugias/index',
            [
                'title' => 'Cirugías',

                'surgeries' => (new Surgery())->list(
                    active_environment_id(),
                    $search
                ),

                'patients' => (new Patient())->allByEnvironment(
                    active_environment_id()
                ),

                'procedures' =>
                $catalog->surgeryProcedures(),

                'anesthesiaTypes' =>
                $catalog->anesthesiaTypes(),

                'search' => $search,

                'success' =>
                Session::pullFlash(
                    'success'
                ),

                'error' =>
                Session::pullFlash(
                    'error'
                ),
            ]
        );
    }

    public function show(
        Request $request,
        string $id
    ): void {
        $eventId = (int) $id;

        $model = new Surgery();

        $surgery = $model->find(
            $eventId,
            active_environment_id()
        );

        if (!$surgery) {
            Session::flash(
                'error',
                'Cirugía no encontrada.'
            );

            $this->redirect('/cirugias');
        }

        $catalog = new Catalog();

        $this->view(
            'cirugias/show',
            [
                'title' => 'Detalle de cirugía',

                'surgery' => $surgery,

                'anesthesia' =>
                $model->anesthesia(
                    $eventId
                ),

                'team' =>
                $model->team(
                    $eventId
                ),

                'evolutions' =>
                $model->evolutions(
                    $eventId
                ),

                'files' =>
                $model->files(
                    $eventId
                ),

                'users' =>
                $catalog->users(),

                'teamFunctions' =>
                $catalog
                    ->surgicalTeamFunctions(),

                'success' =>
                Session::pullFlash(
                    'success'
                ),

                'error' =>
                Session::pullFlash(
                    'error'
                ),
            ]
        );
    }

    public function store(
        Request $request
    ): void {
        if (
            !Session::validateCsrf(
                $request->input('_token')
            )
        ) {
            http_response_code(419);
            return;
        }

        try {
            (new SurgeryService())->create(
                $request->all(),
                $request->files(),
                active_environment_id(),
                auth_id()
            );

            Session::flash(
                'success',
                'Cirugía registrada.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect('/cirugias');
    }

    public function team(
        Request $request,
        string $id
    ): void {
        $this->handle(
            $request,
            function () use (
                $request,
                $id
            ): void {
                (new SurgeryService())
                    ->addTeamMember(
                        (int) $id,
                        $request->all(),
                        active_environment_id(),
                        auth_id()
                    );
            },
            '/cirugias/' . (int) $id,
            'Integrante agregado al equipo quirúrgico.'
        );
    }

    public function removeTeam(
        Request $request,
        string $id
    ): void {

        $this->handle(
            $request,

            function () use (
                $request,
                $id
            ): void {

                (new SurgeryService())
                    ->removeTeamMember(
                        (int) $id,

                        (int) $request->input(
                            'integrante_id',
                            0
                        ),

                        active_environment_id(),

                        auth_id()
                    );
            },

            '/cirugias/' . (int) $id,

            'Integrante retirado del equipo quirúrgico.'
        );
    }

    public function evolution(
        Request $request,
        string $id
    ): void {
        $this->handle(
            $request,
            function () use (
                $request,
                $id
            ): void {
                (new SurgeryService())
                    ->addEvolution(
                        (int) $id,
                        $request->all(),
                        active_environment_id(),
                        auth_id()
                    );
            },
            '/cirugias/'
                . (int) $id
                . '#evoluciones',
            'Evolución registrada.'
        );
    }

    private function handle(
        Request $request,
        callable $callback,
        string $redirect,
        string $success
    ): void {
        if (
            !Session::validateCsrf(
                $request->input('_token')
            )
        ) {
            http_response_code(419);
            return;
        }

        try {
            $callback();

            Session::flash(
                'success',
                $success
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            $redirect
        );
    }

    public function viewFile(
        Request $request
    ): void {

        /*
     * Obtener identificador del archivo.
     */
        $fileId = (int) $request->input(
            'id',
            0
        );

        if ($fileId <= 0) {
            http_response_code(400);
            echo 'Identificador de archivo no válido.';
            return;
        }

        /*
     * Validar que el documento pertenece
     * al entorno activo.
     */
        $file = (new Surgery())->findFile(
            $fileId,
            active_environment_id()
        );

        if (!$file) {
            http_response_code(404);
            echo 'Documento no encontrado.';
            return;
        }

        /*
     * Validar ruta relativa.
     *
     * FileService::store() genera nombres
     * aleatorios de 36 caracteres hexadecimales.
     */
        $relativePath = str_replace(
            '\\',
            '/',
            (string) $file['ruta_storage']
        );

        if (
            !preg_match(
                '~^[a-zA-Z0-9_-]+/[a-f0-9]{36}\.(pdf|jpg|png|webp)$~D',
                $relativePath
            )
        ) {
            http_response_code(403);
            echo 'Ruta del documento no permitida.';
            return;
        }

        /*
     * Resolver ubicación física.
     */
        $uploadsRoot = realpath(
            STORAGE_PATH . '/uploads'
        );

        if ($uploadsRoot === false) {
            http_response_code(404);
            echo 'Almacenamiento no disponible.';
            return;
        }

        $physicalPath = realpath(
            $uploadsRoot . '/' . $relativePath
        );

        $normalizedRoot = rtrim(
            str_replace('\\', '/', $uploadsRoot),
            '/'
        );

        $normalizedPath = $physicalPath !== false
            ? str_replace('\\', '/', $physicalPath)
            : '';

        /*
     * Impedir acceso fuera de uploads.
     */
        if (
            $physicalPath === false
            || !str_starts_with(
                $normalizedPath,
                $normalizedRoot . '/'
            )
            || !is_file($physicalPath)
            || !is_readable($physicalPath)
        ) {
            http_response_code(404);
            echo 'Archivo físico no encontrado.';
            return;
        }

        /*
     * Validar MIME registrado.
     */
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
     * Comprobar MIME real.
     */
        $realMime = (new \finfo(FILEINFO_MIME_TYPE))
            ->file($physicalPath);

        if ($realMime !== $mime) {
            http_response_code(415);
            echo 'El tipo del archivo no coincide.';
            return;
        }

        /*
     * Entregar documento al navegador.
     */
        $size = filesize($physicalPath);

        if ($size === false) {
            http_response_code(500);
            echo 'No fue posible obtener el tamaño del archivo.';
            return;
        }

        header('Content-Type: ' . $mime);

        header(
            'Content-Disposition: inline; filename="documento.' .
                match ($mime) {
                    'application/pdf' => 'pdf',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                } . '"'
        );

        header('Content-Length: ' . $size);

        header(
            'Cache-Control: private, no-store, max-age=0'
        );

        header('X-Content-Type-Options: nosniff');

        header('X-Frame-Options: SAMEORIGIN');

        readfile($physicalPath);

        exit;
    }
}
