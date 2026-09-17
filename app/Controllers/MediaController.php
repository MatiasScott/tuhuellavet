<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Database;

class MediaController extends Controller
{
    public function patientPhoto(
        Request $request,
        string $id
    ): void {
        $db = Database::connection();

        $userId = auth_id();
        $environmentId = active_environment_id();

        if (!$userId || $environmentId === null) {
            http_response_code(403);
            return;
        }

        $sql = '
        SELECT
            a.id,
            a.foto_principal_path
        FROM animales a
        WHERE a.id = :animal
          AND a.entorno_id = :entorno
          AND a.activo = 1
          AND a.deleted_at IS NULL
          AND a.foto_principal_path IS NOT NULL
    ';

        $params = [
            'animal'  => (int) $id,
            'entorno' => (int) $environmentId,
        ];

        $user = auth_user();

        if (
            in_array(
                'CLIENTE',
                $user['roles'] ?? [],
                true
            )
        ) {
            $sql .= '
            AND EXISTS (
                SELECT 1
                FROM propietarios_entornos pe
                INNER JOIN propietarios p
                    ON p.id = pe.propietario_id
                WHERE pe.id = a.propietario_entorno_id
                  AND pe.entorno_id = :entorno_cliente
                  AND pe.activo = 1
                  AND p.usuario_id = :usuario
                  AND p.activo = 1
                  AND p.deleted_at IS NULL
            )
        ';

            $params['entorno_cliente']
                = (int) $environmentId;

            $params['usuario']
                = (int) $userId;
        } elseif (
            empty($user['is_super_admin'])
            && !can('pacientes.ver')
        ) {
            http_response_code(403);
            return;
        }

        $sql .= ' LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $patient = $stmt->fetch();

        if (!$patient) {
            http_response_code(404);
            return;
        }

        $filename = basename(
            (string) $patient['foto_principal_path']
        );

        $this->serveUpload(
            'patients',
            $filename,
            [
                'image/jpeg',
                'image/png',
                'image/webp'
            ]
        );
    }
    public function document(Request $request, string $id): void
    {
        $db = Database::connection();

        $userId = auth_id();
        $environmentId = active_environment_id();

        if (!$userId || $environmentId === null) {
            http_response_code(403);
            return;
        }

        /*
     * Un archivo puede visualizarse únicamente cuando está asociado
     * a un animal del entorno activo.
     *
     * El acceso del CLIENTE se restringe además a animales que
     * pertenecen a su propietario.
     *
     * Los demás usuarios dependen de sus permisos del entorno.
     */
        $sql = '
        SELECT DISTINCT
            ar.*
        FROM archivos ar
        INNER JOIN animal_archivos aa
            ON aa.archivo_id = ar.id
        INNER JOIN animales a
            ON a.id = aa.animal_id
        WHERE ar.id = :archivo
          AND ar.deleted_at IS NULL
          AND a.entorno_id = :entorno
          AND a.deleted_at IS NULL
          AND a.activo = 1
    ';

        $params = [
            'archivo' => (int) $id,
            'entorno' => (int) $environmentId,
        ];

        $user = auth_user();

        if (
            in_array(
                'CLIENTE',
                $user['roles'] ?? [],
                true
            )
        ) {
            $sql .= '
            AND EXISTS (
                SELECT 1
                FROM propietarios_entornos pe
                INNER JOIN propietarios p
                    ON p.id = pe.propietario_id
                WHERE pe.id = a.propietario_entorno_id
                  AND pe.entorno_id = :entorno_cliente
                  AND pe.activo = 1
                  AND p.usuario_id = :usuario
                  AND p.activo = 1
                  AND p.deleted_at IS NULL
            )
        ';

            $params['entorno_cliente']
                = (int) $environmentId;

            $params['usuario']
                = (int) $userId;
        } elseif (
            empty($user['is_super_admin'])
            && !can('pacientes.ver')
        ) {
            http_response_code(403);
            return;
        }

        $sql .= ' LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $file = $stmt->fetch();

        /*
     * Utilizamos 404 también cuando el archivo existe pero no
     * pertenece al contexto autorizado. De esta manera no revelamos
     * la existencia de recursos de otros usuarios/entornos.
     */
        if (!$file) {
            http_response_code(404);
            return;
        }

        $path = STORAGE_PATH
            . '/uploads/'
            . ltrim(
                (string) $file['ruta_storage'],
                '/'
            );

        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header(
            'Content-Type: '
                . $file['mime_type']
        );

        header(
            'Content-Disposition: inline; filename="'
                . rawurlencode(
                    $file['nombre_original']
                )
                . '"'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        header(
            'Cache-Control: private, no-store'
        );

        readfile($path);
        exit;
    }
    private function serveUpload(string $dir, string $filename, array $mimeAllowed): void
    {
        $filename = basename($filename);
        $path = STORAGE_PATH . '/uploads/' . $dir . '/' . $filename;
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        if (!in_array($mime, $mimeAllowed, true)) {
            http_response_code(403);
            return;
        }
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }
}
