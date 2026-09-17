<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\FileIntegrityService;

final class FileIntegrityFlowTest extends ClinicalTestCase
{
    public function testDetectsDatabaseRecordWithMissingPhysicalFile(): void
    {
        $storedName = 'qa105_missing_'
            . bin2hex(random_bytes(12))
            . '.png';

        $relativePath = 'qa/' . $storedName;

        $physicalPath = STORAGE_PATH
            . '/uploads/'
            . $relativePath;

        // La prueba no debe eliminar ni reutilizar archivos existentes.
        $this->assertFileDoesNotExist($physicalPath);

        $stmt = $this->db()->prepare(
            '
            INSERT INTO archivos (
                nombre_original,
                nombre_almacenado,
                ruta_storage,
                extension,
                mime_type,
                tamano_bytes,
                hash_sha256,
                subido_por
            ) VALUES (
                :original,
                :stored,
                :path,
                :extension,
                :mime,
                :size,
                :hash,
                :user
            )
            '
        );

        $stmt->execute([
            'original' => 'qa105_missing.png',
            'stored' => $storedName,
            'path' => $relativePath,
            'extension' => 'png',
            'mime' => 'image/png',
            'size' => 128,
            'hash' => null,
            'user' => $this->superAdminId,
        ]);

        $fileId = (int) $this->db()->lastInsertId();

        $this->assertGreaterThan(0, $fileId);

        // El servicio utiliza la misma conexión que la transacción.
        $report = (new FileIntegrityService())->audit();

        $this->assertSame('SOLO_LECTURA', $report['modo']);

        $this->assertContains(
            [
                'id' => $fileId,
                'ruta' => $relativePath,
            ],
            $report['registros_sin_archivo']
        );

        // La auditoría no debe eliminar el registro detectado.
        $check = $this->db()->prepare(
            'SELECT COUNT(*) FROM archivos WHERE id = :id'
        );

        $check->execute([
            'id' => $fileId,
        ]);

        $this->assertSame(
            1,
            (int) $check->fetchColumn()
        );

        $this->assertFileDoesNotExist($physicalPath);
    }

    private array $createdFiles = [];

    protected function tearDown(): void
    {
        try {
            foreach ($this->createdFiles as $path) {
                if (is_link($path) || is_file($path)) {
                    if (!unlink($path)) {
                        throw new \RuntimeException(
                            'No fue posible limpiar el archivo QA: '
                                . $path
                        );
                    }
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testDetectsIncorrectSha256Hash(): void
    {
        $storedName = 'qa105_hash_'
            . bin2hex(random_bytes(12))
            . '.txt';

        $relativePath = 'qa/' . $storedName;

        $physicalPath = STORAGE_PATH
            . '/uploads/'
            . $relativePath;

        $this->assertFileDoesNotExist($physicalPath);

        // Registrar la ruta antes de crear el archivo garantiza
        // que tearDown intente limpiarlo incluso si falla una aserción.
        $this->createdFiles[] = $physicalPath;

        $content = 'Contenido exclusivo de QA-10.5E.3';

        $written = file_put_contents(
            $physicalPath,
            $content,
            LOCK_EX
        );

        $this->assertSame(strlen($content), $written);

        $actualHash = hash_file('sha256', $physicalPath);

        $this->assertNotFalse($actualHash);

        // Hash válido en formato, pero deliberadamente diferente.
        $incorrectHash = str_repeat(
            $actualHash[0] === 'a' ? 'b' : 'a',
            64
        );

        $this->assertNotSame($actualHash, $incorrectHash);

        $stmt = $this->db()->prepare(
            '
        INSERT INTO archivos (
            nombre_original,
            nombre_almacenado,
            ruta_storage,
            extension,
            mime_type,
            tamano_bytes,
            hash_sha256,
            subido_por
        ) VALUES (
            :original,
            :stored,
            :path,
            :extension,
            :mime,
            :size,
            :hash,
            :user
        )
        '
        );

        $stmt->execute([
            'original' => 'qa105_hash.txt',
            'stored' => $storedName,
            'path' => $relativePath,
            'extension' => 'txt',
            'mime' => 'text/plain',
            'size' => $written,
            'hash' => $incorrectHash,
            'user' => $this->superAdminId,
        ]);

        $fileId = (int) $this->db()->lastInsertId();

        $this->assertGreaterThan(0, $fileId);

        $report = (new FileIntegrityService())->audit();

        $this->assertContains(
            [
                'id' => $fileId,
                'ruta' => $relativePath,
            ],
            $report['hash_incorrecto']
        );

        $this->assertNotContains(
            $relativePath,
            $report['archivos_huerfanos']
        );

        // El auditor no debe alterar el contenido.
        $this->assertSame(
            $actualHash,
            hash_file('sha256', $physicalPath)
        );
    }
    public function testDetectsPathTraversalWithoutReadingExternalFiles(): void
    {
        $storedName = 'qa105_traversal_'
            . bin2hex(random_bytes(12))
            . '.txt';

        $relativePath = '../' . $storedName;

        $stmt = $this->db()->prepare(
            '
        INSERT INTO archivos (
            nombre_original,
            nombre_almacenado,
            ruta_storage,
            extension,
            mime_type,
            tamano_bytes,
            hash_sha256,
            subido_por
        ) VALUES (
            :original,
            :stored,
            :path,
            :extension,
            :mime,
            :size,
            :hash,
            :user
        )
        '
        );

        $stmt->execute([
            'original' => 'qa105_traversal.txt',
            'stored' => $storedName,
            'path' => $relativePath,
            'extension' => 'txt',
            'mime' => 'text/plain',
            'size' => 10,
            'hash' => null,
            'user' => $this->superAdminId,
        ]);

        $fileId = (int) $this->db()->lastInsertId();

        $report = (new FileIntegrityService())->audit();

        $this->assertContains(
            [
                'id' => $fileId,
                'ruta' => $relativePath,
                'motivo' => 'Ruta relativa inválida',
            ],
            $report['rutas_invalidas']
        );

        $this->assertNotContains(
            [
                'id' => $fileId,
                'ruta' => $relativePath,
            ],
            $report['registros_sin_archivo']
        );
    }
    public function testDetectsSymbolicLinkWithoutHashingTarget(): void
    {
        $suffix = bin2hex(random_bytes(12));

        $targetName = "qa105_target_{$suffix}.txt";
        $linkName = "qa105_link_{$suffix}.txt";

        $directory = STORAGE_PATH . '/uploads/qa';

        $targetPath = $directory . '/' . $targetName;
        $linkPath = $directory . '/' . $linkName;

        $relativePath = 'qa/' . $linkName;

        $this->assertFileDoesNotExist($targetPath);
        $this->assertFalse(is_link($linkPath));

        // Registrar ambas rutas antes de crear los archivos.
        $this->createdFiles[] = $linkPath;
        $this->createdFiles[] = $targetPath;

        $content = 'Contenido temporal QA-10.5E.6';

        $written = file_put_contents(
            $targetPath,
            $content,
            LOCK_EX
        );

        $this->assertSame(strlen($content), $written);

        if (!function_exists('symlink')) {
            $this->markTestSkipped(
                'PHP no dispone de symlink().'
            );
        }

        // Evitar que una advertencia de Windows interrumpa la prueba.
        $created = @symlink($targetPath, $linkPath);

        if (!$created || !is_link($linkPath)) {
            $this->markTestSkipped(
                'El entorno no permite crear enlaces simbólicos.'
            );
        }

        $stmt = $this->db()->prepare(
            '
        INSERT INTO archivos (
            nombre_original,
            nombre_almacenado,
            ruta_storage,
            extension,
            mime_type,
            tamano_bytes,
            hash_sha256,
            subido_por
        ) VALUES (
            :original,
            :stored,
            :path,
            :extension,
            :mime,
            :size,
            :hash,
            :user
        )
        '
        );

        $stmt->execute([
            'original' => 'qa105_link.txt',
            'stored' => $linkName,
            'path' => $relativePath,
            'extension' => 'txt',
            'mime' => 'text/plain',
            'size' => $written,
            'hash' => str_repeat('a', 64),
            'user' => $this->superAdminId,
        ]);

        $fileId = (int) $this->db()->lastInsertId();

        $report = (new FileIntegrityService())->audit();

        $this->assertContains(
            $relativePath,
            $report['enlaces_simbolicos']
        );

        $this->assertContains(
            [
                'id' => $fileId,
                'ruta' => $relativePath,
                'motivo' => 'Ruta con enlace simbólico',
            ],
            $report['rutas_invalidas']
        );

        $this->assertNotContains(
            [
                'id' => $fileId,
                'ruta' => $relativePath,
            ],
            $report['hash_incorrecto']
        );

        $this->assertSame(
            $content,
            file_get_contents($targetPath)
        );
    }
}
