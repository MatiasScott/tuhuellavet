<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Closure;
use RuntimeException;
use Throwable;

class FileService
{
    private Closure $mover;

    public function __construct(
        ?callable $mover = null
    ) {
        $this->mover = $mover !== null
            ? Closure::fromCallable($mover)
            : static fn(
                string $source,
                string $destination
            ): bool => move_uploaded_file(
                $source,
                $destination
            );
    }

    public function store(
        array $file,
        int $userId,
        string $folder = 'documents',
        array $allowed = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        ?string &$storedPhysicalPath = null
    ): int {
        $storedPhysicalPath = null;
        if (
            ($file['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException(
                'No se recibió un archivo válido.'
            );
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if (
            $tmp === ''
            || !is_file($tmp)
            || !is_readable($tmp)
        ) {
            throw new RuntimeException(
                'El archivo temporal no es válido.'
            );
        }

        $folder = $this->sanitizeFolder($folder);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))
            ->file($tmp) ?: '';

        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException(
                'Tipo de archivo no permitido.'
            );
        }

        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime])) {
            throw new RuntimeException(
                'No se pudo determinar una extensión segura.'
            );
        }

        $realSize = filesize($tmp);

        if ($realSize === false) {
            throw new RuntimeException(
                'No fue posible determinar el tamaño del archivo.'
            );
        }

        $max = (int) (
            $_ENV['UPLOAD_MAX_BYTES']
            ?? 10485760
        );

        if ($realSize > $max) {
            throw new RuntimeException(
                'El archivo supera el tamaño permitido.'
            );
        }

        $extension = $extensions[$mime];

        $safeName =
            bin2hex(random_bytes(18))
            . '.'
            . $extension;

        $relativePath =
            $folder
            . '/'
            . $safeName;

        $directory =
            STORAGE_PATH
            . '/uploads/'
            . $folder;

        if (
            !is_dir($directory)
            && !mkdir(
                $directory,
                0775,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'No fue posible crear el directorio de almacenamiento.'
            );
        }

        $destination =
            STORAGE_PATH
            . '/uploads/'
            . $relativePath;

        // La ruta queda disponible para compensación
        // incluso si posteriormente falla la consulta SQL.
        $storedPhysicalPath = $destination;

        $mover = $this->mover;

        try {
            $moved = $mover($tmp, $destination);
        } catch (Throwable $e) {
            $this->compensatePhysicalFile($destination);

            $storedPhysicalPath = null;

            throw $e;
        }

        if (!$moved) {
            $this->compensatePhysicalFile($destination);

            $storedPhysicalPath = null;

            throw new RuntimeException(
                'No fue posible almacenar el archivo.'
            );
        }

        try {
            $hash = hash_file(
                'sha256',
                $destination
            );

            if ($hash === false) {
                throw new RuntimeException(
                    'No fue posible calcular el hash del archivo.'
                );
            }

            $db = Database::connection();

            $stmt = $db->prepare(
                '
                INSERT INTO archivos
                (
                    nombre_original,
                    nombre_almacenado,
                    ruta_storage,
                    extension,
                    mime_type,
                    tamano_bytes,
                    hash_sha256,
                    subido_por
                )
                VALUES
                (
                    :original,
                    :almacenado,
                    :ruta,
                    :extension,
                    :mime,
                    :tamano,
                    :hash,
                    :usuario
                )
                '
            );

            $stmt->execute([
                'original' =>
                basename(
                    (string) ($file['name'] ?? 'archivo')
                ),
                'almacenado' => $safeName,
                'ruta' => $relativePath,
                'extension' => $extension,
                'mime' => $mime,
                'tamano' => $realSize,
                'hash' => $hash,
                'usuario' => $userId,
            ]);

            return (int) $db->lastInsertId();
        } catch (Throwable $e) {
            try {
                $this->compensatePhysicalFile($destination);

                // Solo descartamos la referencia si la limpieza
                // terminó correctamente.
                $storedPhysicalPath = null;
            } catch (Throwable $cleanupException) {
                error_log(
                    'No fue posible compensar el archivo '
                        . $destination
                        . ': '
                        . $cleanupException->getMessage()
                );

                // Conservamos $storedPhysicalPath para que el
                // servicio llamador pueda reintentar la limpieza.
            }

            throw $e;
        }
    }

    public function storedPath(int $fileId): string
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            '
        SELECT ruta_storage
        FROM archivos
        WHERE id = :id
        LIMIT 1
        '
        );

        $stmt->execute([
            'id' => $fileId,
        ]);

        $relative = $stmt->fetchColumn();

        if ($relative === false) {
            throw new RuntimeException(
                'Archivo almacenado no encontrado.'
            );
        }

        return STORAGE_PATH
            . '/uploads/'
            . ltrim((string) $relative, '/');
    }

    public function compensatePhysicalFile(string $path): void
    {
        $uploadsRoot = realpath(
            STORAGE_PATH . '/uploads'
        );

        $directory = realpath(dirname($path));

        if ($uploadsRoot === false || $directory === false) {
            throw new RuntimeException(
                'No fue posible validar la ruta del archivo.'
            );
        }

        $normalizedRoot = rtrim(
            str_replace('\\', '/', $uploadsRoot),
            '/'
        );

        $normalizedDirectory = str_replace(
            '\\',
            '/',
            $directory
        );

        if (
            !str_starts_with(
                $normalizedDirectory . '/',
                $normalizedRoot . '/'
            )
        ) {
            throw new RuntimeException(
                'La ruta del archivo está fuera del almacenamiento.'
            );
        }

        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException(
                'No fue posible eliminar el archivo físico.'
            );
        }
    }

    public function cleanup(int $fileId): void
    {
        $db = Database::connection();

        if ($db->inTransaction()) {
            throw new RuntimeException(
                'La eliminación definitiva requiere una transacción confirmada.'
            );
        }

        $db->beginTransaction();

        $originalPath = null;
        $quarantinePath = null;
        $committed = false;
        $journalPath = null;
        $journalHandle = null;

        try {
            $stmt = $db->prepare(
                '
            SELECT ruta_storage
            FROM archivos
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
            '
            );

            $stmt->execute([
                'id' => $fileId,
            ]);

            $relative = $stmt->fetchColumn();

            if ($relative === false) {
                $db->commit();
                return;
            }

            $this->assertFileHasNoReferences($fileId);

            $originalPath = STORAGE_PATH
                . '/uploads/'
                . ltrim((string) $relative, '/');

            $uploadsRoot = realpath(
                STORAGE_PATH . '/uploads'
            );

            $directory = realpath(
                dirname($originalPath)
            );

            if (
                $uploadsRoot === false
                || $directory === false
            ) {
                throw new RuntimeException(
                    'No fue posible validar la ruta del archivo.'
                );
            }

            $normalizedRoot = rtrim(
                str_replace('\\', '/', $uploadsRoot),
                '/'
            );

            $normalizedDirectory = str_replace(
                '\\',
                '/',
                $directory
            );

            if (
                !str_starts_with(
                    $normalizedDirectory . '/',
                    $normalizedRoot . '/'
                )
            ) {
                throw new RuntimeException(
                    'La ruta del archivo está fuera del almacenamiento.'
                );
            }

            if (
                is_link($originalPath)
                || !is_file($originalPath)
            ) {
                throw new RuntimeException(
                    'El archivo físico no existe o no es un archivo regular.'
                );
            }

            $quarantinePath = $directory
                . '/.qa_cleanup_'
                . bin2hex(random_bytes(16));

            // Journal independiente de la transacción SQL: sobrevive a un rollback.
            $journalPath = $directory . '/.qa_journal_' . bin2hex(random_bytes(16)) . '.json';
            $journalHandle = fopen($journalPath, 'x+b');
            if ($journalHandle === false || !flock($journalHandle, LOCK_EX)) {
                throw new RuntimeException('No fue posible crear o bloquear el diario de eliminación.');
            }
            $payload = json_encode([
                'file_id' => $fileId,
                'relative' => (string) $relative,
                'quarantine' => basename($quarantinePath),
            ], JSON_THROW_ON_ERROR);
            if (fwrite($journalHandle, $payload) !== strlen($payload) || !fflush($journalHandle)) {
                throw new RuntimeException('No fue posible persistir el diario de eliminación.');
            }

            if (!rename($originalPath, $quarantinePath)) {
                throw new RuntimeException(
                    'No fue posible mover el archivo a cuarentena.'
                );
            }

            $this->deleteDatabaseRecord($fileId);

            $db->commit();
            $committed = true;

            if (!$this->deleteQuarantinedFile($quarantinePath)) {
                error_log(
                    'Archivo pendiente de limpieza en cuarentena: '
                        . $quarantinePath
                );

                throw new RuntimeException(
                    'El registro fue eliminado, pero el archivo permanece en cuarentena.'
                );
            }
            unlink($journalPath);
        } catch (\Throwable $exception) {
            if (!$committed) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                if (
                    $quarantinePath !== null
                    && $originalPath !== null
                    && is_file($quarantinePath)
                ) {
                    if (!rename(
                        $quarantinePath,
                        $originalPath
                    )) {
                        error_log(
                            'ERROR CRÍTICO: no fue posible restaurar '
                                . $quarantinePath
                                . ' a '
                                . $originalPath
                        );
                    }
                }
            }

            if (
                !$committed && $journalPath !== null && is_file($journalPath)
                && ($quarantinePath === null || !is_file($quarantinePath))
                && ($originalPath === null || is_file($originalPath))
            ) {
                unlink($journalPath);
            }
            throw $exception;
        } finally {
            if (is_resource($journalHandle)) {
                flock($journalHandle, LOCK_UN);
                fclose($journalHandle);
            }
        }
    }

    /**
     * Recuperación conservadora. Ejecutar con la aplicación detenida, desde CLI.
     * Devuelve incidencias; nunca borra un archivo si su registro sigue presente.
     */
    public function recoverPendingDeletions(): array
    {
        $db = Database::connection();
        if ($db->inTransaction()) {
            throw new RuntimeException('La recuperación requiere ausencia de transacciones activas.');
        }
        $root = realpath(STORAGE_PATH . '/uploads');
        if ($root === false) {
            throw new RuntimeException('No existe el directorio de archivos.');
        }
        $issues = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            if (
                $entry->isLink() || !$entry->isFile()
                || !preg_match('/^\.qa_journal_[a-f0-9]{32}\.json$/D', $entry->getFilename())
            ) {
                continue;
            }
            $journal = $entry->getPathname();
            $handle = fopen($journal, 'rb');
            if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
                $issues[] = 'Diario ocupado o ilegible: ' . $journal;
                if (is_resource($handle)) fclose($handle);
                continue;
            }
            try {
                $data = json_decode(stream_get_contents($handle), true, 512, JSON_THROW_ON_ERROR);
                $id = $data['file_id'] ?? null;
                $relative = $data['relative'] ?? null;
                $name = $data['quarantine'] ?? null;
                if (
                    !is_int($id) || $id < 1 || !is_string($relative)
                    || !is_string($name)
                    || !preg_match('/^\.qa_cleanup_[a-f0-9]{32}$/D', $name)
                ) {
                    throw new RuntimeException('Diario inválido.');
                }
                $original = $root . '/' . ltrim($relative, '/');
                $dir = realpath(dirname($original));
                if (
                    $dir === false || $dir !== $entry->getPath()
                    || !str_starts_with(
                        str_replace('\\', '/', $dir) . '/',
                        rtrim(str_replace('\\', '/', $root), '/') . '/'
                    )
                ) {
                    throw new RuntimeException('Ruta del diario fuera de almacenamiento.');
                }
                $quarantine = $dir . '/' . $name;
                if (is_link($original) || is_link($quarantine)) {
                    throw new RuntimeException('Enlace simbólico: requiere inspección manual.');
                }
                $stmt = $db->prepare('SELECT ruta_storage FROM archivos WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $dbRelative = $stmt->fetchColumn();
                if ($dbRelative !== false) {
                    if ($dbRelative !== $relative || is_file($original) || !is_file($quarantine)) {
                        throw new RuntimeException('Estado ambiguo: revisar manualmente.');
                    }
                    if (!rename($quarantine, $original)) {
                        throw new RuntimeException('No se pudo restaurar.');
                    }
                } else {
                    if (is_file($original)) {
                        throw new RuntimeException('Archivo original sin registro: revisar manualmente.');
                    }
                    if (is_file($quarantine) && !$this->deleteQuarantinedFile($quarantine)) {
                        throw new RuntimeException('No se pudo completar la eliminación.');
                    }
                }
                if (!unlink($journal)) {
                    throw new RuntimeException('No se pudo retirar el diario.');
                }
            } catch (\Throwable $e) {
                $issues[] = $journal . ': ' . $e->getMessage();
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
        return $issues;
    }

    protected function deleteQuarantinedFile(string $path): bool
    {
        return unlink($path);
    }

    private function assertFileHasNoReferences(
        int $fileId
    ): void {
        $db = Database::connection();

        $stmt = $db->prepare(
            '
        SELECT
            (
                SELECT COUNT(*)
                FROM animal_archivos
                WHERE archivo_id = :animal_id
            )
            +
            (
                SELECT COUNT(*)
                FROM cirugia_archivos
                WHERE archivo_id = :cirugia_id
            )
            +
            (
                SELECT COUNT(*)
                FROM examen_laboratorio_archivos
                WHERE archivo_id = :laboratorio_id
            )
            +
            (
                SELECT COUNT(*)
                FROM documentos_fiscales
                WHERE archivo_pdf_id = :pdf_id
                   OR archivo_xml_id = :xml_id
            ) AS total_referencias
        '
        );

        $stmt->execute([
            'animal_id' => $fileId,
            'cirugia_id' => $fileId,
            'laboratorio_id' => $fileId,
            'pdf_id' => $fileId,
            'xml_id' => $fileId,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'No se puede eliminar un archivo que tiene referencias clínicas o fiscales.'
            );
        }
    }

    protected function deleteDatabaseRecord(int $fileId): void
    {
        $db = Database::connection();

        $db->prepare(
            '
        DELETE FROM archivos
        WHERE id = :id
        '
        )->execute([
            'id' => $fileId,
        ]);
    }

    private function sanitizeFolder(
        string $folder
    ): string {
        $folder = trim(
            str_replace('\\', '/', $folder),
            '/'
        );

        if (
            $folder === ''
            || str_contains($folder, '..')
            || !preg_match(
                '/^[a-zA-Z0-9_\/-]+$/',
                $folder
            )
        ) {
            throw new RuntimeException(
                'Directorio de almacenamiento inválido.'
            );
        }

        return $folder;
    }
}
