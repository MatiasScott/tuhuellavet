<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class FileStorageService
{
    public function uploadImage(array $file, string $entity, int $entityId, array $options = []): array
    {
        $config = (array) config('files', []);
        $this->assertValidUpload($file);

        $maxSize = (int) ($options['max_size_bytes'] ?? ($config['max_size_bytes'] ?? 5242880));
        $maxWidth = (int) ($options['max_width'] ?? ($config['max_width'] ?? 5000));
        $maxHeight = (int) ($options['max_height'] ?? ($config['max_height'] ?? 5000));

        $size = (int) $file['size'];
        if ($size <= 0 || $size > $maxSize) {
            throw new RuntimeException('Tamano de archivo invalido o excede limite permitido.');
        }

        $tmpFile = (string) $file['tmp_name'];
        if (!is_file($tmpFile)) {
            throw new RuntimeException('Archivo temporal no encontrado.');
        }

        $originalName = (string) ($file['name'] ?? 'archivo');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = (array) ($config['allowed_extensions'] ?? []);

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Extension no permitida.');
        }

        $mime = $this->detectMimeType($tmpFile);
        $allowedMimes = (array) ($config['allowed_mimes'] ?? []);
        $validMimes = (array) ($allowedMimes[$extension] ?? []);

        if (!in_array($mime, $validMimes, true)) {
            throw new RuntimeException('Tipo MIME no permitido para la extension indicada.');
        }

        $imageData = @getimagesize($tmpFile);
        if (!is_array($imageData)) {
            throw new RuntimeException('El archivo no es una imagen valida.');
        }

        $width = (int) ($imageData[0] ?? 0);
        $height = (int) ($imageData[1] ?? 0);

        if ($width <= 0 || $height <= 0 || $width > $maxWidth || $height > $maxHeight) {
            throw new RuntimeException('Dimensiones de imagen fuera de limites permitidos.');
        }

        $folder = $this->resolveEntityFolder($entity, $config);
        $storageRoot = rtrim((string) ($config['storage_root'] ?? (BASE_PATH . '/storage/uploads')), '/\\');
        $relativeRoot = trim((string) ($config['relative_root'] ?? 'storage/uploads'), '/');

        $targetDir = $storageRoot . '/' . $folder;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('No se pudo crear directorio de carga.');
        }

        $suffix = date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $baseName = sprintf('%s_%d_%s', $entity, $entityId, $suffix);

        $convertToWebp = (bool) ($options['convert_to_webp'] ?? ($config['convert_to_webp'] ?? true));
        $targetExt = ($convertToWebp && function_exists('imagewebp')) ? 'webp' : $extension;
        $targetFilename = $baseName . '.' . $targetExt;
        $targetAbsolute = $targetDir . '/' . $targetFilename;

        if ($targetExt === 'webp') {
            $quality = (int) ($options['webp_quality'] ?? ($config['webp_quality'] ?? 82));
            $this->saveAsWebp($tmpFile, $targetAbsolute, $quality);
        } else {
            if (!move_uploaded_file($tmpFile, $targetAbsolute)) {
                if (!rename($tmpFile, $targetAbsolute)) {
                    throw new RuntimeException('No se pudo guardar el archivo cargado.');
                }
            }
        }

        return [
            'filename' => $targetFilename,
            'path' => $relativeRoot . '/' . $folder . '/' . $targetFilename,
            'extension' => $targetExt,
            'mime' => $targetExt === 'webp' ? 'image/webp' : $mime,
            'size_bytes' => (int) filesize($targetAbsolute),
            'width' => $width,
            'height' => $height,
        ];
    }

    public function replaceImage(array $file, string $entity, int $entityId, ?string $oldPath = null, array $options = []): array
    {
        $uploaded = $this->uploadImage($file, $entity, $entityId, $options);

        if (is_string($oldPath) && trim($oldPath) !== '' && $oldPath !== $uploaded['path']) {
            $this->deleteFile($oldPath);
        }

        return $uploaded;
    }

    public function uploadDocument(array $file, string $entity, int $entityId, array $options = []): array
    {
        $config = (array) config('files', []);
        $this->assertValidUpload($file);

        $maxSize = (int) ($options['max_size_bytes'] ?? ($config['max_size_bytes'] ?? 5242880));
        $size = (int) $file['size'];

        if ($size <= 0 || $size > $maxSize) {
            throw new RuntimeException('Tamano de archivo invalido o excede limite permitido.');
        }

        $tmpFile = (string) $file['tmp_name'];
        if (!is_file($tmpFile)) {
            throw new RuntimeException('Archivo temporal no encontrado.');
        }

        $originalName = (string) ($file['name'] ?? 'documento.pdf');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = (array) ($options['allowed_extensions'] ?? ['pdf']);

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Extension de documento no permitida.');
        }

        $mime = $this->detectMimeType($tmpFile);
        $allowedMimes = (array) ($options['allowed_mimes'] ?? ['application/pdf', 'application/x-pdf']);

        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Tipo MIME de documento no permitido.');
        }

        $folder = $this->resolveEntityFolder($entity, $config);
        $storageRoot = rtrim((string) ($config['storage_root'] ?? (BASE_PATH . '/storage/uploads')), '/\\');
        $relativeRoot = trim((string) ($config['relative_root'] ?? 'storage/uploads'), '/');

        $targetDir = $storageRoot . '/' . $folder;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('No se pudo crear directorio de carga de documentos.');
        }

        $suffix = date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $baseName = sprintf('%s_%d_%s', $entity, $entityId, $suffix);
        $targetFilename = $baseName . '.' . $extension;
        $targetAbsolute = $targetDir . '/' . $targetFilename;

        if (!move_uploaded_file($tmpFile, $targetAbsolute)) {
            if (!rename($tmpFile, $targetAbsolute)) {
                throw new RuntimeException('No se pudo guardar el documento cargado.');
            }
        }

        return [
            'filename' => $targetFilename,
            'path' => $relativeRoot . '/' . $folder . '/' . $targetFilename,
            'extension' => $extension,
            'mime' => $mime,
            'size_bytes' => (int) filesize($targetAbsolute),
        ];
    }

    public function replaceDocument(array $file, string $entity, int $entityId, ?string $oldPath = null, array $options = []): array
    {
        $uploaded = $this->uploadDocument($file, $entity, $entityId, $options);

        if (is_string($oldPath) && trim($oldPath) !== '' && $oldPath !== $uploaded['path']) {
            $this->deleteFile($oldPath);
        }

        return $uploaded;
    }

    public function deleteFile(string $storedPath): bool
    {
        $absolute = $this->toAbsolutePath($storedPath);

        if (!file_exists($absolute)) {
            return false;
        }

        return unlink($absolute);
    }

    private function assertValidUpload(array $file): void
    {
        if (!isset($file['error']) || !isset($file['tmp_name']) || !isset($file['name']) || !isset($file['size'])) {
            throw new RuntimeException('Estructura de archivo no valida.');
        }

        $error = (int) $file['error'];
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage($error));
        }
    }

    private function detectMimeType(string $tmpFile): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpFile);

        if (!is_string($mime) || $mime === '') {
            throw new RuntimeException('No se pudo detectar el tipo MIME del archivo.');
        }

        return $mime;
    }

    private function resolveEntityFolder(string $entity, array $config): string
    {
        $entity = strtolower(trim($entity));
        $folders = (array) ($config['folders'] ?? []);

        if (!isset($folders[$entity])) {
            throw new RuntimeException('Entidad de archivo no soportada.');
        }

        return (string) $folders[$entity];
    }

    private function saveAsWebp(string $sourceFile, string $targetFile, int $quality): void
    {
        $binary = file_get_contents($sourceFile);
        if (!is_string($binary) || $binary === '') {
            throw new RuntimeException('No se pudo leer la imagen temporal.');
        }

        $resource = imagecreatefromstring($binary);
        if ($resource === false) {
            throw new RuntimeException('No se pudo procesar la imagen para conversion.');
        }

        $ok = imagewebp($resource, $targetFile, max(1, min(100, $quality)));
        imagedestroy($resource);

        if ($ok !== true) {
            throw new RuntimeException('No se pudo convertir la imagen a formato WEBP.');
        }
    }

    private function toAbsolutePath(string $storedPath): string
    {
        $config = (array) config('files', []);
        $storageRoot = rtrim((string) ($config['storage_root'] ?? (BASE_PATH . '/storage/uploads')), '/\\');
        $relativeRoot = trim((string) ($config['relative_root'] ?? 'storage/uploads'), '/');

        $normalized = str_replace('\\', '/', trim($storedPath));
        $normalized = ltrim($normalized, '/');

        if (str_contains($normalized, '..')) {
            throw new RuntimeException('Ruta de archivo invalida.');
        }

        if (str_starts_with($normalized, $relativeRoot . '/')) {
            $relativeFile = substr($normalized, strlen($relativeRoot . '/'));
            return $storageRoot . '/' . $relativeFile;
        }

        if (str_starts_with($normalized, 'uploads/')) {
            return BASE_PATH . '/' . $normalized;
        }

        return $storageRoot . '/' . basename($normalized);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Archivo excede el tamano maximo permitido.',
            UPLOAD_ERR_PARTIAL => 'La carga del archivo quedo incompleta.',
            UPLOAD_ERR_NO_FILE => 'No se recibio ningun archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'No existe directorio temporal para archivos.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la carga del archivo.',
            default => 'Error desconocido al cargar archivo.',
        };
    }
}
