<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\FileService;
use RuntimeException;
use Tests\TestCase;

final class FileCleanupFlowTest extends TestCase
{
    private array $fileIds = [];
    private array $paths = [];
    private array $animalIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (
            ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing'
            || $this->db()->query('SELECT DATABASE()')->fetchColumn()
            !== 'tuhuellavet_qa'
            || STORAGE_PATH !== BASE_PATH . '/storage/qa'
        ) {
            throw new RuntimeException(
                'BLOQUEADO: las pruebas requieren base y almacenamiento QA.'
            );
        }

        if ($this->db()->inTransaction()) {
            throw new RuntimeException(
                'BLOQUEADO: existe una transacción activa.'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->db()->inTransaction()) {
                $this->db()->rollBack();
            }

            // Retirar exclusivamente los registros creados por esta prueba.
            foreach ($this->fileIds as $id) {
                $stmt = $this->db()->prepare(
                    'DELETE FROM archivos WHERE id = :id'
                );

                $stmt->execute(['id' => $id]);
            }

            // Retirar exclusivamente las rutas sintéticas registradas.
            foreach ($this->paths as $path) {
                if (is_file($path)) {
                    if (!unlink($path)) {
                        throw new RuntimeException(
                            'No se pudo retirar el archivo QA: ' . $path
                        );
                    }
                }
            }

            foreach ($this->fileIds as $id) {
                $stmt = $this->db()->prepare(
                    'DELETE FROM animal_archivos WHERE archivo_id = :id'
                );
                $stmt->execute(['id' => $id]);
            }

            foreach ($this->animalIds as $id) {
                $stmt = $this->db()->prepare(
                    'DELETE FROM animales WHERE id = :id'
                );
                $stmt->execute(['id' => $id]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createFixture(): array
    {
        $directory = STORAGE_PATH
            . '/uploads/qa_cleanup_tests';

        if (
            !is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'No se pudo crear el directorio QA.'
            );
        }

        $name = 'fixture_' . bin2hex(random_bytes(16)) . '.png';

        $relative = 'qa_cleanup_tests/' . $name;
        $path = $directory . '/' . $name;

        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII=',
            true
        );

        if (
            $content === false
            || file_put_contents($path, $content) === false
        ) {
            throw new RuntimeException(
                'No se pudo crear el archivo sintético.'
            );
        }

        $this->paths[] = $path;

        $user = $this->db()->query(
            "SELECT id
             FROM usuarios
             WHERE email = 'qa-automatizado@example.invalid'
               AND activo = 1
             LIMIT 1"
        )->fetchColumn();

        if ($user === false) {
            throw new RuntimeException(
                'Falta el superadministrador sintético QA.'
            );
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO archivos (
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
            )'
        );

        $stmt->execute([
            'original' => 'fixture.png',
            'stored' => $name,
            'path' => $relative,
            'extension' => 'png',
            'mime' => 'image/png',
            'size' => strlen($content),
            'hash' => hash('sha256', $content),
            'user' => (int) $user,
        ]);

        $id = (int) $this->db()->lastInsertId();

        $this->fileIds[] = $id;

        return [$id, $path, $content];
    }

    private function recordExists(int $id): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM archivos WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() === 1;
    }

    public function testCleanupDeletesFileAndDatabaseRecord(): void
    {
        [$id, $path] = $this->createFixture();

        self::assertTrue($this->recordExists($id));
        self::assertFileExists($path);
        self::assertFalse($this->db()->inTransaction());

        (new FileService())->cleanup($id);

        self::assertFalse($this->recordExists($id));
        self::assertFileDoesNotExist($path);
        self::assertFalse($this->db()->inTransaction());
    }

    public function testSqlFailureRestoresOriginalFileAndRecord(): void
    {
        [$id, $path, $content] = $this->createFixture();

        $service = new class extends FileService {
            protected function deleteDatabaseRecord(
                int $fileId
            ): void {
                throw new RuntimeException(
                    'Fallo SQL simulado QA.'
                );
            }
        };

        try {
            $service->cleanup($id);

            self::fail(
                'cleanup() debía propagar el fallo SQL simulado.'
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Fallo SQL simulado QA.',
                $exception->getMessage()
            );
        }

        self::assertTrue($this->recordExists($id));
        self::assertFileExists($path);
        self::assertSame($content, file_get_contents($path));
        self::assertFalse($this->db()->inTransaction());
    }

    public function testCleanupRejectsReferencedFile(): void
    {
        [$fileId, $path, $content] = $this->createFixture();

        $entornoId = $this->db()->query(
            "SELECT e.id
         FROM entornos e
         JOIN tipos_entorno te ON te.id = e.tipo_entorno_id
         WHERE te.codigo = 'VETERINARIA'
         LIMIT 1"
        )->fetchColumn();

        $especieId = $this->db()->query(
            "SELECT id
         FROM especies
         WHERE codigo = 'PERRO'
         LIMIT 1"
        )->fetchColumn();

        if ($entornoId === false || $especieId === false) {
            throw new RuntimeException(
                'Faltan los catálogos sintéticos necesarios.'
            );
        }

        $stmt = $this->db()->prepare(
            'INSERT INTO animales (
            entorno_id,
            especie_id,
            nombre
        ) VALUES (
            :entorno,
            :especie,
            :nombre
        )'
        );

        $stmt->execute([
            'entorno' => (int) $entornoId,
            'especie' => (int) $especieId,
            'nombre' => 'Animal sintético QA',
        ]);

        $animalId = (int) $this->db()->lastInsertId();

        $this->animalIds[] = $animalId;

        $stmt = $this->db()->prepare(
            'INSERT INTO animal_archivos (
            animal_id,
            archivo_id,
            tipo_documento
        ) VALUES (
            :animal,
            :archivo,
            :tipo
        )'
        );

        $stmt->execute([
            'animal' => $animalId,
            'archivo' => $fileId,
            'tipo' => 'QA',
        ]);

        try {
            (new FileService())->cleanup($fileId);

            self::fail(
                'cleanup() debía rechazar el archivo referenciado.'
            );
        } catch (RuntimeException $exception) {
            self::assertStringContainsString(
                'referencias clínicas o fiscales',
                $exception->getMessage()
            );
        }

        self::assertTrue($this->recordExists($fileId));
        self::assertFileExists($path);
        self::assertSame($content, file_get_contents($path));
        self::assertFalse($this->db()->inTransaction());

        $stmt = $this->db()->prepare(
            'SELECT COUNT(*)
         FROM animal_archivos
         WHERE animal_id = :animal
           AND archivo_id = :archivo'
        );

        $stmt->execute([
            'animal' => $animalId,
            'archivo' => $fileId,
        ]);

        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testPostCommitFailureKeepsFileInQuarantine(): void
    {
        [$fileId, $originalPath] = $this->createFixture();

        $directory = dirname($originalPath);

        $service = new class extends FileService {
            protected function deleteQuarantinedFile(string $path): bool
            {
                return false;
            }
        };

        try {
            $service->cleanup($fileId);

            self::fail(
                'cleanup() debía informar el fallo posterior al COMMIT.'
            );
        } catch (RuntimeException $exception) {
            self::assertSame(
                'El registro fue eliminado, pero el archivo permanece en cuarentena.',
                $exception->getMessage()
            );
        }

        self::assertFalse($this->recordExists($fileId));
        self::assertFileDoesNotExist($originalPath);
        self::assertFalse($this->db()->inTransaction());

        $quarantinedFiles = glob(
            $directory . '/.qa_cleanup_*'
        );

        self::assertIsArray($quarantinedFiles);
        self::assertCount(1, $quarantinedFiles);

        $quarantinePath = $quarantinedFiles[0];

        self::assertFileExists($quarantinePath);

        // Registrar el archivo sintético para su limpieza.
        $this->paths[] = $quarantinePath;

        // Registrar también el diario generado por esta prueba.
        $journals = glob(
            $directory . '/.qa_journal_*.json'
        );

        self::assertIsArray($journals);
        self::assertCount(1, $journals);

        $this->paths[] = $journals[0];
    }

    public function testRecoveryCompletesPostCommitDeletion(): void
    {
        [$id, $original] = $this->createFixture();
        $service = new class extends FileService {
            protected function deleteQuarantinedFile(string $path): bool
            {
                return false;
            }
        };
        try {
            $service->cleanup($id);
            self::fail('Se esperaba fallo de borrado físico.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('permanece en cuarentena', $e->getMessage());
        }
        $journals = glob(dirname($original) . '/.qa_journal_*.json');
        self::assertCount(1, $journals);
        $this->paths[] = $journals[0];
        $quarantined = glob(dirname($original) . '/.qa_cleanup_*');
        self::assertCount(1, $quarantined);
        $this->paths[] = $quarantined[0];

        self::assertSame([], (new FileService())->recoverPendingDeletions());
        self::assertFileDoesNotExist($journals[0]);
        self::assertFileDoesNotExist($quarantined[0]);
        self::assertFalse($this->recordExists($id));
    }

    public function testRecoveryRestoresFileAfterSimulatedCrashBeforeCommit(): void
    {
        [$id, $original, $content] = $this->createFixture();
        $dir = dirname($original);
        $quarantine = $dir . '/.qa_cleanup_' . bin2hex(random_bytes(16));
        $journal = $dir . '/.qa_journal_' . bin2hex(random_bytes(16)) . '.json';
        $this->paths[] = $quarantine;
        $this->paths[] = $journal;
        self::assertNotFalse(file_put_contents($journal, json_encode([
            'file_id' => $id,
            'relative' => 'qa_cleanup_tests/' . basename($original),
            'quarantine' => basename($quarantine),
        ], JSON_THROW_ON_ERROR)));
        self::assertTrue(rename($original, $quarantine));

        self::assertSame([], (new FileService())->recoverPendingDeletions());
        self::assertFileExists($original);
        self::assertSame($content, file_get_contents($original));
        self::assertFileDoesNotExist($quarantine);
        self::assertFileDoesNotExist($journal);
        self::assertTrue($this->recordExists($id));
    }
}
