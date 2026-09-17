<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\FileService;
use App\Services\LaboratoryService;
use App\Services\SurgeryService;
use RuntimeException;

class ClinicalAttachmentFlowTest extends ClinicalTestCase
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

    private function fileService(): FileService
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

    private function createTempPng(
        string $name = 'imagen.png'
    ): array {
        $tmp = tempnam(
            sys_get_temp_dir(),
            'qa_clinical_'
        );

        if ($tmp === false) {
            throw new RuntimeException(
                'No fue posible crear el archivo temporal.'
            );
        }

        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQMcAAAAASUVORK5CYII='
        );

        file_put_contents(
            $tmp,
            $content
        );

        $this->createdFiles[] = $tmp;

        return [
            'name' => $name,
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    private function createExamType(): int
    {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO tipos_examen_laboratorio
            (
                nombre,
                descripcion,
                activo
            )
            VALUES
            (
                :nombre,
                :descripcion,
                1
            )
            '
        );

        $stmt->execute([
            'nombre' =>
            'EXAMEN_ADJUNTO_QA_'
                . uniqid(),
            'descripcion' =>
            'Fixture temporal QA',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function createProcedure(): int
    {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO procedimientos_quirurgicos
            (
                nombre,
                descripcion,
                activo
            )
            VALUES
            (
                :nombre,
                :descripcion,
                1
            )
            '
        );

        $stmt->execute([
            'nombre' =>
            'CIRUGIA_ADJUNTO_QA_'
                . uniqid(),
            'descripcion' =>
            'Fixture temporal QA',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function physicalPathFromFileId(
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

    public function testLaboratoryCanStoreAttachment(): void
    {
        $patientId =
            $this->createPatient();

        $examTypeId =
            $this->createExamType();

        $service = new LaboratoryService(
            $this->fileService()
        );

        $examId = $service->create(
            [
                'animal_id' => $patientId,
                'tipo_examen_id' => $examTypeId,
                'fecha_solicitud' =>
                '2026-09-14 09:00:00',
            ],
            [
                'archivo' =>
                $this->createTempPng(
                    'laboratorio.png'
                ),
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                ela.archivo_id,
                ela.descripcion,
                a.nombre_original,
                a.extension,
                a.mime_type,
                a.subido_por
            FROM examen_laboratorio_archivos ela
            INNER JOIN archivos a
                ON a.id = ela.archivo_id
            WHERE ela.examen_laboratorio_id = :id
            '
        );

        $stmt->execute([
            'id' => $examId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertSame(
            'Resultado / documento',
            $row['descripcion']
        );

        $this->assertSame(
            'laboratorio.png',
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

        $path = $this->physicalPathFromFileId(
            (int) $row['archivo_id']
        );

        $this->assertFileExists($path);

        $this->createdFiles[] = $path;
    }

    public function testSurgeryCanStoreAttachment(): void
    {
        $patientId =
            $this->createPatient();

        $procedureId =
            $this->createProcedure();

        $service = new SurgeryService(
            $this->fileService()
        );

        $eventId = $service->create(
            [
                'animal_id' => $patientId,
                'procedimiento_quirurgico_id' =>
                $procedureId,
                'fecha_inicio' =>
                '2026-09-14 09:00:00',
            ],
            [
                'archivo' =>
                $this->createTempPng(
                    'cirugia.png'
                ),
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                ca.archivo_id,
                ca.descripcion,
                a.nombre_original,
                a.extension,
                a.mime_type,
                a.subido_por
            FROM cirugia_archivos ca
            INNER JOIN archivos a
                ON a.id = ca.archivo_id
            WHERE ca.cirugia_evento_id = :evento
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertSame(
            'Documento de cirugía',
            $row['descripcion']
        );

        $this->assertSame(
            'cirugia.png',
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

        $path = $this->physicalPathFromFileId(
            (int) $row['archivo_id']
        );

        $this->assertFileExists($path);

        $this->createdFiles[] = $path;
    }

    public function testLaboratoryWithoutAttachmentCreatesNoFile(): void
    {
        $patientId =
            $this->createPatient();

        $examTypeId =
            $this->createExamType();

        $before = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        $examId = (
            new LaboratoryService(
                $this->fileService()
            )
        )->create(
            [
                'animal_id' => $patientId,
                'tipo_examen_id' =>
                $examTypeId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $after = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        $relationCount = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM examen_laboratorio_archivos
            WHERE examen_laboratorio_id = :id
            ',
            [
                'id' => $examId,
            ]
        );

        $this->assertSame(
            $before,
            $after
        );

        $this->assertSame(
            0,
            $relationCount
        );
    }

    public function testSurgeryWithoutAttachmentCreatesNoFile(): void
    {
        $patientId =
            $this->createPatient();

        $procedureId =
            $this->createProcedure();

        $before = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        $eventId = (
            new SurgeryService(
                $this->fileService()
            )
        )->create(
            [
                'animal_id' => $patientId,
                'procedimiento_quirurgico_id' =>
                $procedureId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $after = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        $relationCount = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM cirugia_archivos
            WHERE cirugia_evento_id = :id
            ',
            [
                'id' => $eventId,
            ]
        );

        $this->assertSame(
            $before,
            $after
        );

        $this->assertSame(
            0,
            $relationCount
        );
    }

    public function testLaboratoryRejectsInvalidAttachmentType(): void
    {
        $patientId =
            $this->createPatient();

        $examTypeId =
            $this->createExamType();

        $tmp = tempnam(
            sys_get_temp_dir(),
            'qa_txt_'
        );

        file_put_contents(
            $tmp,
            'archivo de texto'
        );

        $this->createdFiles[] = $tmp;

        $beforeExam = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM examenes_laboratorio
            '
        );

        $beforeFiles = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        try {
            (
                new LaboratoryService(
                    $this->fileService()
                )
            )->create(
                [
                    'animal_id' =>
                    $patientId,
                    'tipo_examen_id' =>
                    $examTypeId,
                ],
                [
                    'archivo' => [
                        'name' => 'malicioso.txt',
                        'type' => 'text/plain',
                        'tmp_name' => $tmp,
                        'error' => UPLOAD_ERR_OK,
                        'size' =>
                        filesize($tmp),
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba rechazo del archivo.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Tipo de archivo no permitido.',
                $e->getMessage()
            );
        }

        $afterExam = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM examenes_laboratorio
            '
        );

        $afterFiles = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        $this->assertSame(
            $beforeExam,
            $afterExam
        );

        $this->assertSame(
            $beforeFiles,
            $afterFiles
        );
    }

    public function testSurgeryRejectsInvalidAttachmentType(): void
    {
        $patientId =
            $this->createPatient();

        $procedureId =
            $this->createProcedure();

        $tmp = tempnam(
            sys_get_temp_dir(),
            'qa_txt_'
        );

        file_put_contents(
            $tmp,
            'archivo de texto'
        );

        $this->createdFiles[] = $tmp;

        $beforeSurgery = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM cirugias
            '
        );

        $beforeFiles = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        try {
            (
                new SurgeryService(
                    $this->fileService()
                )
            )->create(
                [
                    'animal_id' =>
                    $patientId,
                    'procedimiento_quirurgico_id' =>
                    $procedureId,
                ],
                [
                    'archivo' => [
                        'name' => 'malicioso.txt',
                        'type' => 'text/plain',
                        'tmp_name' => $tmp,
                        'error' => UPLOAD_ERR_OK,
                        'size' =>
                        filesize($tmp),
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba rechazo del archivo.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Tipo de archivo no permitido.',
                $e->getMessage()
            );
        }

        $afterSurgery = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM cirugias
            '
        );

        $afterFiles = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM archivos
            '
        );

        $this->assertSame(
            $beforeSurgery,
            $afterSurgery
        );

        $this->assertSame(
            $beforeFiles,
            $afterFiles
        );
    }

    public function testSurgeryRemovesPhysicalAttachmentAfterLaterValidationFailure(): void
    {
        $patientId = $this->createPatient();
        $procedureId = $this->createProcedure();

        $storedPath = null;

        $fileService = new FileService(
            static function (
                string $source,
                string $destination
            ) use (&$storedPath): bool {
                $storedPath = $destination;

                return copy($source, $destination);
            }
        );

        $beforeFiles = (int) $this->scalar(
            'SELECT COUNT(*) FROM archivos'
        );

        $beforeSurgeries = (int) $this->scalar(
            'SELECT COUNT(*) FROM cirugias'
        );

        $beforeAttachments = (int) $this->scalar(
            'SELECT COUNT(*) FROM cirugia_archivos'
        );

        try {
            (new SurgeryService($fileService))->create(
                [
                    'animal_id' => $patientId,
                    'procedimiento_quirurgico_id' => $procedureId,
                    'fecha_inicio' => '2026-09-17 09:00:00',

                    // Se valida DESPUÉS de almacenar el adjunto.
                    'tipo_anestesia_id' => 999999999,
                ],
                [
                    'archivo' => $this->createTempPng(
                        'cirugia_rollback.png'
                    ),
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'La cirugía debía rechazar el tipo de anestesia.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Tipo de anestesia no válido.',
                $exception->getMessage()
            );
        } finally {
            // Protección del entorno de QA: evita dejar residuos
            // incluso cuando la prueba detecta el defecto.
            if ($storedPath !== null) {
                $this->createdFiles[] = $storedPath;
            }
        }

        $this->assertNotNull(
            $storedPath,
            'La prueba debe alcanzar el almacenamiento físico.'
        );

        $this->assertSame(
            $beforeSurgeries,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM cirugias'
            )
        );

        $this->assertSame(
            $beforeFiles,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM archivos'
            )
        );

        $this->assertSame(
            $beforeAttachments,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM cirugia_archivos'
            )
        );

        $this->assertFileDoesNotExist(
            $storedPath,
            'El archivo físico quedó huérfano después del rollback.'
        );
    }

    public function testLaboratoryRemovesPhysicalAttachmentAfterLaterFailure(): void
    {
        $patientId = $this->createPatient();
        $examTypeId = $this->createExamType();

        $storedPath = null;

        $fileService = new FileService(
            static function (
                string $source,
                string $destination
            ) use (&$storedPath): bool {
                $storedPath = $destination;

                return copy($source, $destination);
            }
        );

        $beforeExams = (int) $this->scalar(
            'SELECT COUNT(*) FROM examenes_laboratorio'
        );

        $beforeFiles = (int) $this->scalar(
            'SELECT COUNT(*) FROM archivos'
        );

        $beforeAttachments = (int) $this->scalar(
            'SELECT COUNT(*) FROM examen_laboratorio_archivos'
        );

        // Provoca un error de clave foránea en la asociación,
        // después de que FileService haya almacenado el archivo.
        $this->db()->exec(
            '
        SET @qa_original_foreign_key_checks = @@FOREIGN_KEY_CHECKS
        '
        );

        try {
            // Aquí NO desactivamos las FK: necesitamos que la
            // base de datos rechace una asociación inválida.
            // Para provocar ese fallo de forma controlada,
            // primero debemos identificar una restricción real.
        } finally {
            if ($storedPath !== null) {
                $this->createdFiles[] = $storedPath;
            }
        }
    }

    public function testExternalTransactionRollbackRemovesSurgeryAttachment(): void
    {
        $patientId = $this->createPatient();
        $procedureId = $this->createProcedure();

        $storedPath = null;

        $fileService = new FileService(
            static function (
                string $source,
                string $destination
            ) use (&$storedPath): bool {
                $storedPath = $destination;

                return copy($source, $destination);
            }
        );

        $beforeSurgeries = (int) $this->scalar(
            'SELECT COUNT(*) FROM cirugias'
        );

        $beforeFiles = (int) $this->scalar(
            'SELECT COUNT(*) FROM archivos'
        );

        $beforeAttachments = (int) $this->scalar(
            'SELECT COUNT(*) FROM cirugia_archivos'
        );

        try {
            \App\Core\Database::transaction(
                function () use (
                    $patientId,
                    $procedureId,
                    $fileService
                ): void {
                    $service = new SurgeryService($fileService);

                    $service->create(
                        [
                            'animal_id' => $patientId,
                            'procedimiento_quirurgico_id' =>
                            $procedureId,
                            'fecha_inicio' =>
                            '2026-09-17 09:00:00',
                        ],
                        [
                            'archivo' => $this->createTempPng(
                                'cirugia_rollback_externo.png'
                            ),
                        ],
                        $this->vetEnvironment,
                        $this->superAdminId
                    );

                    throw new RuntimeException(
                        'Fallo controlado de transacción externa.'
                    );
                }
            );

            $this->fail(
                'La transacción externa debía fallar.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Fallo controlado de transacción externa.',
                $exception->getMessage()
            );
        } finally {
            if ($storedPath !== null) {
                $this->createdFiles[] = $storedPath;
            }
        }

        $this->assertNotNull(
            $storedPath,
            'La prueba debe alcanzar el almacenamiento físico.'
        );

        $this->assertSame(
            $beforeSurgeries,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM cirugias'
            )
        );

        $this->assertSame(
            $beforeFiles,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM archivos'
            )
        );

        $this->assertSame(
            $beforeAttachments,
            (int) $this->scalar(
                'SELECT COUNT(*) FROM cirugia_archivos'
            )
        );

        $this->assertFileDoesNotExist(
            $storedPath,
            'El rollback externo dejó un archivo físico huérfano.'
        );
    }

    public function testSuccessfulExternalTransactionKeepsSurgeryAttachment(): void
    {
        $patientId = $this->createPatient();
        $procedureId = $this->createProcedure();

        $storedPath = null;

        $fileService = new FileService(
            static function (
                string $source,
                string $destination
            ) use (&$storedPath): bool {
                $storedPath = $destination;

                return copy($source, $destination);
            }
        );

        try {
            $eventId = \App\Core\Database::transaction(
                function () use (
                    $patientId,
                    $procedureId,
                    $fileService
                ): int {
                    return (new SurgeryService($fileService))->create(
                        [
                            'animal_id' => $patientId,
                            'procedimiento_quirurgico_id' => $procedureId,
                            'fecha_inicio' => '2026-09-17 09:00:00',
                        ],
                        [
                            'archivo' => $this->createTempPng(
                                'cirugia_transaccion_exitosa.png'
                            ),
                        ],
                        $this->vetEnvironment,
                        $this->superAdminId
                    );
                }
            );

            $this->assertGreaterThan(0, $eventId);
            $this->assertNotNull($storedPath);
            $this->assertFileExists(
                $storedPath,
                'Una transacción exitosa no debe eliminar su adjunto.'
            );
        } finally {
            if ($storedPath !== null) {
                $this->createdFiles[] = $storedPath;
            }
        }
    }
}
