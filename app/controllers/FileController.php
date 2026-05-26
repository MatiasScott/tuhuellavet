<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use RuntimeException;

final class FileController extends Controller
{
    public function viewPdf(Request $request, Response $response): never
    {
        try {
            $file = $this->resolvePdfPath((string) $request->input('path', ''));
            $this->outputFile($file, 'inline');
        } catch (\Throwable $exception) {
            http_response_code(404);
            echo htmlspecialchars($exception->getMessage());
            exit;
        }
    }

    public function downloadPdf(Request $request, Response $response): never
    {
        try {
            $file = $this->resolvePdfPath((string) $request->input('path', ''));
            $this->outputFile($file, 'attachment');
        } catch (\Throwable $exception) {
            http_response_code(404);
            echo htmlspecialchars($exception->getMessage());
            exit;
        }
    }

    private function resolvePdfPath(string $storedPath): string
    {
        $normalized = str_replace('\\', '/', trim($storedPath));
        $normalized = ltrim($normalized, '/');

        if ($normalized === '' || str_contains($normalized, '..')) {
            throw new RuntimeException('Ruta de PDF invalida.');
        }

        $storageRoot = rtrim((string) (config('files.storage_root', BASE_PATH . '/storage/uploads') ?? (BASE_PATH . '/storage/uploads')), '/\\');
        $relativeRoot = trim((string) (config('files.relative_root', 'storage/uploads') ?? 'storage/uploads'), '/');

        if (str_starts_with($normalized, $relativeRoot . '/')) {
            $relativeFile = substr($normalized, strlen($relativeRoot . '/'));
            $absolute = $storageRoot . '/' . $relativeFile;
        } elseif (str_starts_with($normalized, 'uploads/')) {
            $absolute = BASE_PATH . '/' . $normalized;
        } else {
            $absolute = $storageRoot . '/' . basename($normalized);
        }

        $realStorage = realpath($storageRoot);
        $realFile = realpath($absolute);

        if (!is_string($realStorage) || !is_string($realFile) || !str_starts_with($realFile, $realStorage)) {
            throw new RuntimeException('Acceso de archivo denegado.');
        }

        $ext = strtolower((string) pathinfo($realFile, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new RuntimeException('Solo se permite visualizar PDF.');
        }

        if (!is_file($realFile) || !is_readable($realFile)) {
            throw new RuntimeException('Archivo PDF no encontrado.');
        }

        return $realFile;
    }

    private function outputFile(string $absolutePath, string $disposition): never
    {
        $filename = basename($absolutePath);

        header('Content-Type: application/pdf');
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
        header('X-Content-Type-Options: nosniff');

        readfile($absolutePath);
        exit;
    }
}
