<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Models\Laboratory;
use App\Services\LaboratoryService;
use RuntimeException;

class LaboratoryFlowTest extends ClinicalTestCase
{
    private function createExamType(
        string $name = 'Hemograma QA'
    ): int {
        $name .= ' ' . uniqid();

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
            'nombre' => $name,
            'descripcion' => 'Tipo de examen temporal para QA',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function createLaboratoryExam(
        ?int $patientId = null,
        ?int $environmentId = null,
        array $extra = []
    ): int {
        $environmentId ??= $this->vetEnvironment;
        $patientId ??= $this->createPatient($environmentId);

        $typeId = $this->createExamType();

        return (new LaboratoryService())->create(
            array_merge(
                [
                    'animal_id' => $patientId,
                    'tipo_examen_id' => $typeId,
                    'fecha_solicitud' => '2026-09-14 09:00:00',
                    'resultado_resumen' => 'Resultado QA',
                    'observaciones' => 'Observación QA',
                ],
                $extra
            ),
            [],
            $environmentId,
            $this->superAdminId
        );
    }

    public function testLaboratoryExamCanBeCreated(): void
    {
        $patientId = $this->createPatient();
        $typeId = $this->createExamType();

        $examId = (new LaboratoryService())->create(
            [
                'animal_id' => $patientId,
                'tipo_examen_id' => $typeId,
                'fecha_solicitud' => '2026-09-14 09:00:00',
                'resultado_resumen' => 'Hemograma normal',
                'observaciones' => 'Sin novedades',
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                el.*,
                ec.animal_id
            FROM examenes_laboratorio el
            INNER JOIN eventos_clinicos ec
                ON ec.id = el.evento_clinico_id
            WHERE el.id = :id
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $examId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame($examId, (int) $row['id']);
        $this->assertSame($patientId, (int) $row['animal_id']);
        $this->assertSame($typeId, (int) $row['tipo_examen_id']);
        $this->assertSame(
            $this->superAdminId,
            (int) $row['solicitado_por']
        );
        $this->assertSame(
            '2026-09-14 09:00:00',
            $row['fecha_solicitud']
        );
        $this->assertSame(
            'Hemograma normal',
            $row['resultado_resumen']
        );
        $this->assertSame(
            'Sin novedades',
            $row['observaciones']
        );
    }

    public function testLaboratoryCreatesClinicalEvent(): void
    {
        $examId = $this->createLaboratoryExam();

        $stmt = $this->db()->prepare(
            '
            SELECT
                ec.id,
                ec.animal_id,
                ec.responsable_id,
                ec.titulo,
                tec.codigo
            FROM examenes_laboratorio el
            INNER JOIN eventos_clinicos ec
                ON ec.id = el.evento_clinico_id
            INNER JOIN tipos_evento_clinico tec
                ON tec.id = ec.tipo_evento_id
            WHERE el.id = :id
            '
        );

        $stmt->execute([
            'id' => $examId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('LABORATORIO', $row['codigo']);
        $this->assertSame(
            'Examen de laboratorio',
            $row['titulo']
        );
        $this->assertSame(
            $this->superAdminId,
            (int) $row['responsable_id']
        );
    }

    public function testPatientIsRequired(): void
    {
        $typeId = $this->createExamType();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente y tipo de examen son obligatorios.'
        );

        (new LaboratoryService())->create(
            [
                'animal_id' => 0,
                'tipo_examen_id' => $typeId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testExamTypeIsRequired(): void
    {
        $patientId = $this->createPatient();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente y tipo de examen son obligatorios.'
        );

        (new LaboratoryService())->create(
            [
                'animal_id' => $patientId,
                'tipo_examen_id' => 0,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testPatientFromAnotherEnvironmentIsRejected(): void
    {
        $patientId = $this->createPatient(
            $this->farmEnvironment
        );

        $typeId = $this->createExamType();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente no válido.'
        );

        (new LaboratoryService())->create(
            [
                'animal_id' => $patientId,
                'tipo_examen_id' => $typeId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testNonExistingPatientIsRejected(): void
    {
        $typeId = $this->createExamType();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente no válido.'
        );

        (new LaboratoryService())->create(
            [
                'animal_id' => 999999999,
                'tipo_examen_id' => $typeId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testResultDateCanBeStored(): void
    {
        $examId = $this->createLaboratoryExam(
            null,
            null,
            [
                'fecha_resultado' => '2026-09-14 11:30:00',
            ]
        );

        $date = $this->scalar(
            '
            SELECT fecha_resultado
            FROM examenes_laboratorio
            WHERE id = :id
            ',
            [
                'id' => $examId,
            ]
        );

        $this->assertSame(
            '2026-09-14 11:30:00',
            $date
        );
    }

    public function testResultDateCanBeNull(): void
    {
        $patientId = $this->createPatient();
        $typeId = $this->createExamType();

        $examId = (new LaboratoryService())->create(
            [
                'animal_id' => $patientId,
                'tipo_examen_id' => $typeId,
                'fecha_solicitud' => '2026-09-14 09:00:00',
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $result = $this->scalar(
            '
            SELECT fecha_resultado
            FROM examenes_laboratorio
            WHERE id = :id
            ',
            [
                'id' => $examId,
            ]
        );

        $this->assertNull($result);
    }

    public function testInvalidExamTypeDoesNotLeaveClinicalEvent(): void
    {
        $patientId = $this->createPatient();

        $beforeEvents = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM eventos_clinicos
            WHERE animal_id = :animal
            ',
            [
                'animal' => $patientId,
            ]
        );

        try {
            (new LaboratoryService())->create(
                [
                    'animal_id' => $patientId,
                    'tipo_examen_id' => 999999999,
                    'fecha_solicitud' => '2026-09-14 09:00:00',
                ],
                [],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba que un tipo de examen inexistente fuera rechazado.'
            );
        } catch (\Throwable $e) {
            $afterEvents = (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM eventos_clinicos
                WHERE animal_id = :animal
                ',
                [
                    'animal' => $patientId,
                ]
            );

            $this->assertSame(
                $beforeEvents,
                $afterEvents,
                'El evento clínico debe revertirse si falla la creación del examen.'
            );
        }
    }

    public function testLaboratoryCreationWritesAudit(): void
    {
        $examId = $this->createLaboratoryExam();

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM auditoria
            WHERE usuario_id = :usuario
              AND entorno_id = :entorno
              AND modulo = "LABORATORIO"
              AND accion = "CREAR"
              AND tabla_afectada = "examenes_laboratorio"
              AND registro_id = :registro
            ',
            [
                'usuario' => $this->superAdminId,
                'entorno' => $this->vetEnvironment,
                'registro' => $examId,
            ]
        );

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testLaboratoryModelListsExamInCorrectEnvironment(): void
    {
        $examId = $this->createLaboratoryExam();

        $rows = (new Laboratory())->list(
            $this->vetEnvironment
        );

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        );

        $this->assertContains(
            $examId,
            $ids
        );
    }

    public function testLaboratoryModelDoesNotExposeExamInAnotherEnvironment(): void
    {
        $examId = $this->createLaboratoryExam(
            null,
            $this->vetEnvironment
        );

        $rows = (new Laboratory())->list(
            $this->farmEnvironment
        );

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        );

        $this->assertNotContains(
            $examId,
            $ids
        );
    }

    public function testSearchCanFindLaboratoryExamByType(): void
    {
        $patientId = $this->createPatient();

        $typeId = $this->createExamType(
            'HEMOGRAMA_BUSQUEDA_QA'
        );

        $examId = (new LaboratoryService())->create(
            [
                'animal_id' => $patientId,
                'tipo_examen_id' => $typeId,
                'fecha_solicitud' => '2026-09-14 09:00:00',
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $rows = (new Laboratory())->list(
            $this->vetEnvironment,
            'HEMOGRAMA_BUSQUEDA_QA'
        );

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        );

        $this->assertContains(
            $examId,
            $ids
        );
    }
}