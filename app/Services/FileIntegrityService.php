<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class FileIntegrityService
{
    public function audit(): array
    {
        $root = realpath(STORAGE_PATH . '/uploads');

        if ($root === false || !is_dir($root)) {
            throw new RuntimeException(
                'No existe el directorio uploads.'
            );
        }

        $normalize = static fn(string $path): string =>
        str_replace('\\', '/', $path);

        $normalizedRoot = rtrim(
            $normalize($root),
            '/'
        );

        $insideRoot = static function (
            string $path
        ) use ($normalize, $normalizedRoot): bool {
            return str_starts_with(
                $normalize($path),
                $normalizedRoot . '/'
            );
        };

        $rows = Database::connection()
            ->query(
                '
                SELECT
                    id,
                    ruta_storage,
                    hash_sha256,
                    deleted_at
                FROM archivos
                ORDER BY id
                '
            )
            ->fetchAll(PDO::FETCH_ASSOC);

        $registered = [];
        $missing = [];
        $invalid = [];
        $hashMismatch = [];
        $hashUnavailable = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            $relative = $normalize(
                (string) $row['ruta_storage']
            );

            $segments = explode('/', $relative);

            if (
                $relative === ''
                || str_starts_with($relative, '/')
                || preg_match('/^[a-zA-Z]:/', $relative)
                || in_array('..', $segments, true)
                || in_array('.', $segments, true)
                || in_array('', $segments, true)
            ) {
                $invalid[] = [
                    'id' => $id,
                    'ruta' => $relative,
                    'motivo' => 'Ruta relativa inválida',
                ];

                continue;
            }

            $registered[$relative] = true;

            $path = $root
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $relative
                );

            // Rechazar enlaces simbólicos en cualquier componente de la ruta.
            // No basta con comprobar únicamente el archivo final.
            $currentPath = $root;
            $hasSymlink = false;

            foreach ($segments as $segment) {
                $currentPath .= DIRECTORY_SEPARATOR . $segment;

                if (is_link($currentPath)) {
                    $hasSymlink = true;
                    break;
                }
            }

            if ($hasSymlink) {
                $invalid[] = [
                    'id' => $id,
                    'ruta' => $relative,
                    'motivo' => 'Ruta con enlace simbólico',
                ];

                continue;
            }

            if (!is_file($path)) {
                $missing[] = [
                    'id' => $id,
                    'ruta' => $relative,
                ];

                continue;
            }

            $realPath = realpath($path);

            if (
                $realPath === false
                || !$insideRoot($realPath)
            ) {
                $invalid[] = [
                    'id' => $id,
                    'ruta' => $relative,
                    'motivo' =>
                    'Archivo fuera del almacenamiento',
                ];

                continue;
            }

            $expectedHash = $row['hash_sha256'];

            if (
                $expectedHash !== null
                && $expectedHash !== ''
            ) {
                $actualHash = hash_file(
                    'sha256',
                    $realPath
                );

                if ($actualHash === false) {
                    $hashUnavailable[] = [
                        'id' => $id,
                        'ruta' => $relative,
                    ];
                } elseif (!hash_equals(
                    strtolower((string) $expectedHash),
                    strtolower($actualHash)
                )) {
                    $hashMismatch[] = [
                        'id' => $id,
                        'ruta' => $relative,
                    ];
                }
            }
        }

        $orphans = [];
        $symlinks = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            /*
     * Usamos la ruta que devuelve el iterador para
     * identificar el enlace, sin resolver su destino.
     */
            $path = $file->getPathname();

            $relative = ltrim(
                substr(
                    $normalize($path),
                    strlen($normalizedRoot)
                ),
                '/'
            );

            // Los archivos de mantenimiento no son adjuntos.
            if (basename($relative) === '.gitkeep') {
                continue;
            }

            /*
     * Comprobamos el enlace antes de isFile().
     * Esto permite detectar también enlaces rotos.
     */
            if ($file->isLink()) {
                $symlinks[] = $relative;
                continue;
            }

            if (!$file->isFile()) {
                continue;
            }

            $realPath = $file->getRealPath();

            if (
                $realPath === false
                || !$insideRoot($realPath)
            ) {
                continue;
            }

            if (!isset($registered[$relative])) {
                $orphans[] = $relative;
            }
        }

        sort($orphans);
        sort($symlinks);

        return [
            'fecha' => date(DATE_ATOM),
            'modo' => 'SOLO_LECTURA',
            'registros' => count($rows),
            'archivos_huerfanos' => $orphans,
            'enlaces_simbolicos' => $symlinks,
            'registros_sin_archivo' => $missing,
            'rutas_invalidas' => $invalid,
            'hash_incorrecto' => $hashMismatch,
            'hash_no_verificable' => $hashUnavailable,
        ];
    }
}
