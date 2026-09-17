<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\FileService;
use RuntimeException;

class FileUploadFlowTest extends ClinicalTestCase
{
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function createTempPng(
        string $filename = 'imagen.png'
    ): array {
        $tmp = tempnam(
            sys_get_temp_dir(),
            'qa_upload_'
        );

        if ($tmp === false) {
            throw new RuntimeException(
                'No fue posible crear archivo temporal.'
            );
        }

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII='
        );

        file_put_contents(
            $tmp,
            $png
        );

        $this->createdFiles[] = $tmp;

        return [
            'name' => $filename,
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    private function createTempPdf(
        string $filename = 'documento.pdf'
    ): array {
        $tmp = tempnam(
            sys_get_temp_dir(),
            'qa_upload_'
        );

        if ($tmp === false) {
            throw new RuntimeException(
                'No fue posible crear archivo temporal.'
            );
        }

        file_put_contents(
            $tmp,
            "%PDF-1.4\n"
                . "1 0 obj\n"
                . "<< /Type /Catalog >>\n"
                . "endobj\n"
                . "trailer\n"
                . "<< /Root 1 0 R >>\n"
                . "%%EOF"
        );

        $this->createdFiles[] = $tmp;

        return [
            'name' => $filename,
            'type' => 'application/pdf',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    private function createTempText(
        string $filename = 'archivo.txt'
    ): array {
        $tmp = tempnam(
            sys_get_temp_dir(),
            'qa_upload_'
        );

        if ($tmp === false) {
            throw new RuntimeException(
                'No fue posible crear archivo temporal.'
            );
        }

        file_put_contents(
            $tmp,
            'contenido no permitido'
        );

        $this->createdFiles[] = $tmp;

        return [
            'name' => $filename,
            'type' => 'text/plain',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    private function testFileService(): FileService
    {
        return new FileService(
            static function (
                string $source,
                string $destination
            ): bool {
                return copy(
                    $source,
                    $destination
                );
            }
        );
    }

    private function storedPath(
        int $fileId
    ): string {
        $relative = $this->scalar(
            '
            SELECT ruta_storage
            FROM archivos
            WHERE id = :id
            ',
            [
                'id' => $fileId,
            ]
        );

        return STORAGE_PATH
            . '/uploads/'
            . ltrim(
                (string) $relative,
                '/'
            );
    }

    public function testValidPngCanBeStored(): void
    {
        $file = $this->createTempPng();

        $id = $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM archivos
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame(
            'imagen.png',
            $row['nombre_original']
        );
        $this->assertSame(
            'png',
            $row['extension']
        );
        $this->assertSame(
            'image/png',
            $row['mime_type']
        );
        $this->assertSame(
            $this->superAdminId,
            (int) $row['subido_por']
        );

        $path = $this->storedPath($id);

        $this->assertFileExists($path);

        $this->createdFiles[] = $path;
    }

    public function testValidPdfCanBeStored(): void
    {
        $file = $this->createTempPdf();

        $id = $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                extension,
                mime_type
            FROM archivos
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame(
            'pdf',
            $row['extension']
        );
        $this->assertSame(
            'application/pdf',
            $row['mime_type']
        );

        $path = $this->storedPath($id);

        $this->assertFileExists($path);

        $this->createdFiles[] = $path;
    }

    public function testOriginalDangerousExtensionIsNotUsed(): void
    {
        $file = $this->createTempPng(
            'imagen.php'
        );

        $id = $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                nombre_original,
                nombre_almacenado,
                extension
            FROM archivos
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        $this->assertSame(
            'imagen.php',
            $row['nombre_original']
        );

        $this->assertSame(
            'png',
            $row['extension']
        );

        $this->assertStringEndsWith(
            '.png',
            $row['nombre_almacenado']
        );

        $this->assertStringNotContainsString(
            '.php',
            $row['nombre_almacenado']
        );

        $path = $this->storedPath($id);

        $this->assertFileExists($path);

        $this->createdFiles[] = $path;
    }

    public function testTextFileIsRejected(): void
    {
        $file = $this->createTempText();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Tipo de archivo no permitido.'
        );

        $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );
    }

    public function testUploadErrorIsRejected(): void
    {
        $file = $this->createTempPng();

        $file['error'] =
            UPLOAD_ERR_PARTIAL;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No se recibió un archivo válido.'
        );

        $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );
    }

    public function testMissingTemporaryFileIsRejected(): void
    {
        $file = [
            'name' => 'imagen.png',
            'type' => 'image/png',
            'tmp_name' =>
            sys_get_temp_dir()
                . '/archivo_inexistente_'
                . uniqid(),
            'error' => UPLOAD_ERR_OK,
            'size' => 10,
        ];

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El archivo temporal no es válido.'
        );

        $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );
    }

    public function testPathTraversalFolderIsRejected(): void
    {
        $file = $this->createTempPng();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Directorio de almacenamiento inválido.'
        );

        $this->testFileService()->store(
            $file,
            $this->superAdminId,
            '../public'
        );
    }

    public function testWindowsPathTraversalFolderIsRejected(): void
    {
        $file = $this->createTempPng();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Directorio de almacenamiento inválido.'
        );

        $this->testFileService()->store(
            $file,
            $this->superAdminId,
            '..\\public'
        );
    }

    public function testStoredHashMatchesPhysicalFile(): void
    {
        $file = $this->createTempPng();

        $id = $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                hash_sha256,
                tamano_bytes
            FROM archivos
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        $path = $this->storedPath($id);

        $this->assertSame(
            hash_file('sha256', $path),
            $row['hash_sha256']
        );

        $this->assertSame(
            filesize($path),
            (int) $row['tamano_bytes']
        );

        $this->createdFiles[] = $path;
    }

    public function testStoredNameIsRandomAndUnique(): void
    {
        $fileA = $this->createTempPng(
            'misma.png'
        );

        $fileB = $this->createTempPng(
            'misma.png'
        );

        $service = $this->testFileService();

        $idA = $service->store(
            $fileA,
            $this->superAdminId,
            'qa'
        );

        $idB = $service->store(
            $fileB,
            $this->superAdminId,
            'qa'
        );

        $nameA = $this->scalar(
            '
            SELECT nombre_almacenado
            FROM archivos
            WHERE id = :id
            ',
            [
                'id' => $idA,
            ]
        );

        $nameB = $this->scalar(
            '
            SELECT nombre_almacenado
            FROM archivos
            WHERE id = :id
            ',
            [
                'id' => $idB,
            ]
        );

        $this->assertNotSame(
            $nameA,
            $nameB
        );

        $pathA = $this->storedPath($idA);
        $pathB = $this->storedPath($idB);

        $this->assertFileExists($pathA);
        $this->assertFileExists($pathB);

        $this->createdFiles[] = $pathA;
        $this->createdFiles[] = $pathB;
    }

    public function testCleanupPreservesFileAndRecordInsideTransaction(): void
    {
        $service = $this->testFileService();

        $id = $service->store(
            $this->createTempPng(),
            $this->superAdminId,
            'qa'
        );

        $path = $this->storedPath($id);
        $this->createdFiles[] = $path;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'La eliminación definitiva requiere una transacción confirmada.'
        );

        try {
            $service->cleanup($id);
        } finally {
            $this->assertFileExists($path);

            $this->assertSame(
                1,
                (int) $this->scalar(
                    'SELECT COUNT(*) FROM archivos WHERE id = :id',
                    ['id' => $id]
                )
            );
        }
    }

    public function testFilenamePathComponentsAreRemoved(): void
    {
        $file = $this->createTempPng(
            '../../malicioso.png'
        );

        $id = $this->testFileService()->store(
            $file,
            $this->superAdminId,
            'qa'
        );

        $original = $this->scalar(
            '
            SELECT nombre_original
            FROM archivos
            WHERE id = :id
            ',
            [
                'id' => $id,
            ]
        );

        $this->assertSame(
            'malicioso.png',
            $original
        );

        $path = $this->storedPath($id);

        $this->createdFiles[] = $path;
    }
    public function testFailedMoverRemovesPartialPhysicalFile(): void
    {
        $destination = null;

        $fileService = new FileService(
            static function (
                string $source,
                string $target
            ) use (&$destination): bool {
                $destination = $target;

                // Simulamos un movimiento que escribe parte
                // del archivo, pero finalmente informa un fallo.
                file_put_contents(
                    $target,
                    'ARCHIVO_PARCIAL_QA'
                );

                return false;
            }
        );

        $tmp = tempnam(
            sys_get_temp_dir(),
            'qa_upload_'
        );

        if ($tmp === false) {
            $this->fail(
                'No fue posible crear el archivo temporal.'
            );
        }

        // PNG mínimo válido para que finfo lo reconozca.
        file_put_contents(
            $tmp,
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB'
                    . 'CAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwM'
                    . 'CAO+/lXcAAAAASUVORK5CYII='
            )
        );

        $storedPath = null;

        $before = (int) $this->scalar(
            'SELECT COUNT(*) FROM archivos'
        );

        try {
            try {
                $fileService->store(
                    [
                        'name' => 'parcial.png',
                        'tmp_name' => $tmp,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($tmp),
                    ],
                    $this->superAdminId,
                    'qa_partial',
                    ['image/png'],
                    $storedPath
                );

                $this->fail(
                    'El movimiento parcial debía provocar una excepción.'
                );
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'No fue posible almacenar el archivo.',
                    $exception->getMessage()
                );
            }

            $this->assertNotNull(
                $destination,
                'El mover debía ejecutarse.'
            );

            $this->assertFileDoesNotExist(
                $destination,
                'El movimiento fallido dejó un archivo parcial.'
            );

            $this->assertNull(
                $storedPath,
                'No debe exponerse una ruta como almacenamiento exitoso.'
            );

            $this->assertSame(
                $before,
                (int) $this->scalar(
                    'SELECT COUNT(*) FROM archivos'
                )
            );
        } finally {
            if ($destination !== null) {
                $this->createdFiles[] = $destination;
            }

            if (is_file($tmp)) {
                unlink($tmp);
            }
        }

        $this->assertNotNull(
            $destination,
            'El mover debía ejecutarse.'
        );

        try {
            $this->assertFileDoesNotExist(
                $destination,
                'El movimiento fallido dejó un archivo parcial.'
            );
        } finally {
            if ($destination !== null && is_file($destination)) {
                unlink($destination);
            }
        }

        $this->assertNull(
            $storedPath,
            'No debe exponerse una ruta como almacenamiento exitoso.'
        );

        $this->assertSame(
            $before,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM archivos'
            )
        );
    }
    public function testCleanupRejectsTransactionBeforeInspectingInvalidPath(): void
    {
        $service = $this->testFileService();

        $id = $service->store(
            $this->createTempPng(),
            $this->superAdminId,
            'qa'
        );

        $originalPath = $this->storedPath($id);

        // Garantiza la limpieza del archivo real en tearDown().
        $this->createdFiles[] = $originalPath;

        $this->assertFileExists($originalPath);

        // Modificamos únicamente el registro de prueba.
        // La transacción de ClinicalTestCase revertirá este UPDATE.
        $invalidRelativePath =
            'qa_directorio_inexistente_'
            . bin2hex(random_bytes(8))
            . '/archivo.png';

        $stmt = $this->db()->prepare(
            '
        UPDATE archivos
        SET ruta_storage = :ruta
        WHERE id = :id
        '
        );

        $stmt->execute([
            'ruta' => $invalidRelativePath,
            'id' => $id,
        ]);

        try {
            $service->cleanup($id);

            $this->fail(
                'cleanup() debía rechazar la ruta física inválida.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'La eliminación definitiva requiere una transacción confirmada.',
                $exception->getMessage()
            );
        }

        $count = (int) $this->scalar(
            '
        SELECT COUNT(*)
        FROM archivos
        WHERE id = :id
        ',
            ['id' => $id]
        );

        $this->assertSame(
            1,
            $count,
            'El registro debe conservarse si falla la limpieza física.'
        );

        $this->assertFileExists(
            $originalPath,
            'La limpieza fallida no debe eliminar el archivo original.'
        );
    }
    public function testFailedCompensationPreservesPhysicalPath(): void
    {
        $destination = null;
        $storedPath = null;

        $service = new class(
            static function (
                string $source,
                string $target
            ) use (&$destination): bool {
                $destination = $target;

                return copy($source, $target);
            }
        ) extends FileService {
            public function compensatePhysicalFile(
                string $path
            ): void {
                throw new RuntimeException(
                    'Fallo simulado de eliminación.'
                );
            }
        };

        $file = $this->createTempPng();

        // nombre_original admite como máximo 255 caracteres.
        // Usamos un nombre más largo para provocar el error SQL.
        $file['name'] = str_repeat('a', 260) . '.png';

        $before = (int) $this->scalar(
            'SELECT COUNT(*) FROM archivos'
        );

        try {
            try {
                $service->store(
                    $file,
                    $this->superAdminId,
                    'qa_compensation',
                    ['image/png'],
                    $storedPath
                );

                $this->fail(
                    'El INSERT debía fallar por longitud del nombre.'
                );
            } catch (\PDOException $exception) {
                $this->assertNotSame(
                    '',
                    $exception->getMessage()
                );
            }

            $this->assertNotNull(
                $destination,
                'El movimiento debía ejecutarse.'
            );

            $this->assertSame(
                $destination,
                $storedPath,
                'La ruta debe conservarse si falla la compensación.'
            );

            $this->assertFileExists(
                $destination,
                'El archivo debe permanecer para demostrar el fallo simulado.'
            );

            $this->assertSame(
                $before,
                (int) $this->scalar(
                    'SELECT COUNT(*) FROM archivos'
                ),
                'No debe quedar un registro de archivo creado.'
            );
        } finally {
            // La limpieza de la prueba no utiliza el método simulado.
            if ($destination !== null && is_file($destination)) {
                $this->createdFiles[] = $destination;
            }
        }
    }

    public function testCleanupRejectsSqlFailureInsideActiveTransaction(): void
    {
        $service = new class(
            static function (
                string $source,
                string $destination
            ): bool {
                return copy($source, $destination);
            }
        ) extends FileService {
            protected function deleteDatabaseRecord(
                int $fileId
            ): void {
                throw new RuntimeException(
                    'Fallo SQL simulado.'
                );
            }
        };

        $id = $service->store(
            $this->createTempPng(),
            $this->superAdminId,
            'qa'
        );

        $path = $this->storedPath($id);

        // El archivo es exclusivo de esta prueba.
        // tearDown() podrá limpiarlo si continúa existiendo.
        $this->createdFiles[] = $path;

        $this->assertFileExists($path);

        try {
            $service->cleanup($id);

            $this->fail(
                'cleanup() debía rechazar la transacción activa.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'La eliminación definitiva requiere una transacción confirmada.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            1,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM archivos WHERE id = :id',
                ['id' => $id]
            ),
            'El registro SQL permanece al fallar su eliminación.'
        );

        $this->assertFileExists(
            $path,
            'El rechazo transaccional debe conservar el archivo físico.'
        );
    }
    public function testCleanupRejectsFileReferencedByPatient(): void
    {
        $service = $this->testFileService();

        $fileId = $service->store(
            $this->createTempPng(),
            $this->superAdminId,
            'qa'
        );

        $path = $this->storedPath($fileId);
        $this->createdFiles[] = $path;

        $patientId = $this->createPatient();

        $stmt = $this->db()->prepare(
            '
        INSERT INTO animal_archivos
            (animal_id, archivo_id)
        VALUES
            (:animal_id, :archivo_id)
        '
        );

        $stmt->execute([
            'animal_id' => $patientId,
            'archivo_id' => $fileId,
        ]);

        try {
            $service->cleanup($fileId);

            $this->fail(
                'cleanup() debía rechazar el archivo referenciado.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'La eliminación definitiva requiere una transacción confirmada.',
                $exception->getMessage()
            );
        }

        $this->assertFileExists($path);

        $this->assertSame(
            1,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM archivos WHERE id = :id',
                ['id' => $fileId]
            )
        );

        $this->assertSame(
            1,
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM animal_archivos
            WHERE archivo_id = :id
            ',
                ['id' => $fileId]
            )
        );
    }

    public function testCleanupRejectsActiveTransactionWithoutDeletingFile(): void
    {
        $service = $this->testFileService();

        $fileId = $service->store(
            $this->createTempPng(),
            $this->superAdminId,
            'qa'
        );

        $path = $this->storedPath($fileId);

        $this->createdFiles[] = $path;

        $this->assertTrue(
            $this->db()->inTransaction()
        );

        $this->assertFileExists($path);

        try {
            $service->cleanup($fileId);

            $this->fail(
                'cleanup() debía rechazar la transacción activa.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'La eliminación definitiva requiere una transacción confirmada.',
                $exception->getMessage()
            );
        }

        $this->assertFileExists($path);

        $this->assertSame(
            1,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM archivos WHERE id = :id',
                ['id' => $fileId]
            )
        );
    }
}
