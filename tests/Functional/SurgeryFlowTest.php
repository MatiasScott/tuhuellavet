<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Models\Surgery;
use App\Services\SurgeryService;
use RuntimeException;

class SurgeryFlowTest extends ClinicalTestCase
{
    private function createProcedure(
        string $name = 'Cirugía QA'
    ): int {
        $name .= ' ' . uniqid();

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
            'nombre' => $name,
            'descripcion' => 'Procedimiento temporal QA',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function anesthesiaTypeId(
        string $code = 'GENERAL'
    ): int {
        $stmt = $this->db()->prepare(
            '
            SELECT id
            FROM tipos_anestesia
            WHERE codigo = :codigo
              AND activo = 1
            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'No existe tipo de anestesia: ' . $code
            );
        }

        return (int) $id;
    }

    private function createSurgery(
        ?int $patientId = null,
        ?int $environmentId = null,
        array $extra = []
    ): int {
        $environmentId ??= $this->vetEnvironment;
        $patientId ??= $this->createPatient($environmentId);

        $procedureId = $this->createProcedure();

        return (new SurgeryService())->create(
            array_merge(
                [
                    'animal_id' => $patientId,
                    'procedimiento_quirurgico_id' => $procedureId,
                    'fecha_inicio' => '2026-09-14 09:00:00',
                    'diagnostico_preoperatorio' => 'Diagnóstico QA',
                    'descripcion_procedimiento' => 'Procedimiento QA',
                    'hallazgos' => 'Sin hallazgos relevantes',
                    'complicaciones' => 'Ninguna',
                    'indicaciones_postoperatorias' => 'Reposo',
                    'observaciones' => 'Cirugía QA',
                ],
                $extra
            ),
            [],
            $environmentId,
            $this->superAdminId
        );
    }

    public function testSurgeryCanBeCreated(): void
    {
        $patientId = $this->createPatient();
        $procedureId = $this->createProcedure();

        $eventId = (new SurgeryService())->create(
            [
                'animal_id' => $patientId,
                'procedimiento_quirurgico_id' => $procedureId,
                'fecha_inicio' => '2026-09-14 09:00:00',
                'fecha_fin' => '2026-09-14 10:30:00',
                'diagnostico_preoperatorio' => 'Diagnóstico preoperatorio QA',
                'descripcion_procedimiento' => 'Descripción QA',
                'hallazgos' => 'Hallazgos QA',
                'complicaciones' => 'Sin complicaciones',
                'indicaciones_postoperatorias' => 'Control en 48 horas',
                'observaciones' => 'Todo correcto',
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                c.*,
                ec.animal_id
            FROM cirugias c
            INNER JOIN eventos_clinicos ec
                ON ec.id = c.evento_clinico_id
            WHERE c.evento_clinico_id = :id
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame(
            $eventId,
            (int) $row['evento_clinico_id']
        );
        $this->assertSame(
            $patientId,
            (int) $row['animal_id']
        );
        $this->assertSame(
            $procedureId,
            (int) $row['procedimiento_quirurgico_id']
        );
        $this->assertSame(
            $this->superAdminId,
            (int) $row['medico_responsable_id']
        );
        $this->assertSame(
            '2026-09-14 09:00:00',
            $row['fecha_inicio']
        );
        $this->assertSame(
            '2026-09-14 10:30:00',
            $row['fecha_fin']
        );
        $this->assertSame(
            'Diagnóstico preoperatorio QA',
            $row['diagnostico_preoperatorio']
        );
        $this->assertSame(
            'Descripción QA',
            $row['descripcion_procedimiento']
        );
        $this->assertSame(
            'Hallazgos QA',
            $row['hallazgos']
        );
        $this->assertSame(
            'Sin complicaciones',
            $row['complicaciones']
        );
        $this->assertSame(
            'Control en 48 horas',
            $row['indicaciones_postoperatorias']
        );
    }

    public function testSurgeryCreatesClinicalEvent(): void
    {
        $eventId = $this->createSurgery();

        $stmt = $this->db()->prepare(
            '
            SELECT
                tec.codigo,
                ec.responsable_id,
                ec.titulo
            FROM eventos_clinicos ec
            INNER JOIN tipos_evento_clinico tec
                ON tec.id = ec.tipo_evento_id
            WHERE ec.id = :id
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame('CIRUGIA', $row['codigo']);
        $this->assertSame(
            $this->superAdminId,
            (int) $row['responsable_id']
        );
        $this->assertSame(
            'Cirugía',
            $row['titulo']
        );
    }

    public function testPatientIsRequired(): void
    {
        $procedureId = $this->createProcedure();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente y procedimiento son obligatorios.'
        );

        (new SurgeryService())->create(
            [
                'animal_id' => 0,
                'procedimiento_quirurgico_id' => $procedureId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testProcedureIsRequired(): void
    {
        $patientId = $this->createPatient();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente y procedimiento son obligatorios.'
        );

        (new SurgeryService())->create(
            [
                'animal_id' => $patientId,
                'procedimiento_quirurgico_id' => 0,
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

        $procedureId = $this->createProcedure();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente no válido.'
        );

        (new SurgeryService())->create(
            [
                'animal_id' => $patientId,
                'procedimiento_quirurgico_id' => $procedureId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testNonExistingPatientIsRejected(): void
    {
        $procedureId = $this->createProcedure();

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Paciente no válido.'
        );

        (new SurgeryService())->create(
            [
                'animal_id' => 999999999,
                'procedimiento_quirurgico_id' => $procedureId,
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidProcedureRollsBackClinicalEvent(): void
    {
        $patientId = $this->createPatient();

        $before = (int) $this->scalar(
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
            (new SurgeryService())->create(
                [
                    'animal_id' => $patientId,
                    'procedimiento_quirurgico_id' => 999999999,
                    'fecha_inicio' => '2026-09-14 09:00:00',
                ],
                [],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba error por procedimiento inexistente.'
            );
        } catch (\Throwable $e) {
            $after = (int) $this->scalar(
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
                $before,
                $after,
                'La transacción debe revertir el evento clínico.'
            );
        }
    }

    public function testSurgeryCanCreateGeneralAnesthesia(): void
    {
        $anesthesiaTypeId = $this->anesthesiaTypeId(
            'GENERAL'
        );

        $eventId = $this->createSurgery(
            null,
            null,
            [
                'tipo_anestesia_id' => $anesthesiaTypeId,
                'protocolo_anestesia' => 'Propofol + mantenimiento QA',
            ]
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                tipo_anestesia_id,
                protocolo,
                responsable_id
            FROM cirugia_anestesias
            WHERE cirugia_evento_id = :evento
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertSame(
            $anesthesiaTypeId,
            (int) $row['tipo_anestesia_id']
        );

        $this->assertSame(
            'Propofol + mantenimiento QA',
            $row['protocolo']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $row['responsable_id']
        );
    }

    public function testSurgeryCanCreateAnesthesiaWithProtocolOnly(): void
    {
        $eventId = $this->createSurgery(
            null,
            null,
            [
                'protocolo_anestesia' => 'Sedación protocolaria QA',
            ]
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                tipo_anestesia_id,
                protocolo
            FROM cirugia_anestesias
            WHERE cirugia_evento_id = :evento
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertNull($row['tipo_anestesia_id']);
        $this->assertSame(
            'Sedación protocolaria QA',
            $row['protocolo']
        );
    }

    public function testSurgeryWithoutAnesthesiaDoesNotCreateAnesthesiaRow(): void
    {
        $eventId = $this->createSurgery();

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM cirugia_anestesias
            WHERE cirugia_evento_id = :evento
            ',
            [
                'evento' => $eventId,
            ]
        );

        $this->assertSame(0, $count);
    }

    public function testInvalidAnesthesiaTypeRollsBackEntireSurgery(): void
    {
        $patientId = $this->createPatient();
        $procedureId = $this->createProcedure();

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
            (new SurgeryService())->create(
                [
                    'animal_id' => $patientId,
                    'procedimiento_quirurgico_id' => $procedureId,
                    'fecha_inicio' => '2026-09-14 09:00:00',
                    'tipo_anestesia_id' => 999999,
                    'protocolo_anestesia' => 'Protocolo inválido',
                ],
                [],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba error por tipo de anestesia inexistente.'
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
                $afterEvents
            );

            $countSurgeries = (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM cirugias c
                INNER JOIN eventos_clinicos ec
                    ON ec.id = c.evento_clinico_id
                WHERE ec.animal_id = :animal
                ',
                [
                    'animal' => $patientId,
                ]
            );

            $this->assertSame(
                0,
                $countSurgeries
            );
        }
    }

    public function testSurgeryWritesAudit(): void
    {
        $eventId = $this->createSurgery();

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM auditoria
            WHERE usuario_id = :usuario
              AND entorno_id = :entorno
              AND modulo = "CIRUGIAS"
              AND accion = "CREAR"
              AND tabla_afectada = "cirugias"
              AND registro_id = :registro
            ',
            [
                'usuario' => $this->superAdminId,
                'entorno' => $this->vetEnvironment,
                'registro' => $eventId,
            ]
        );

        $this->assertGreaterThanOrEqual(
            1,
            $count
        );
    }

    public function testSurgeryModelListsSurgeryInCorrectEnvironment(): void
    {
        $eventId = $this->createSurgery();

        $rows = (new Surgery())->list(
            $this->vetEnvironment
        );

        $ids = array_map(
            static fn (array $row): int =>
                (int) $row['evento_clinico_id'],
            $rows
        );

        $this->assertContains(
            $eventId,
            $ids
        );
    }

    public function testSurgeryModelDoesNotExposeOtherEnvironment(): void
    {
        $eventId = $this->createSurgery(
            null,
            $this->vetEnvironment
        );

        $rows = (new Surgery())->list(
            $this->farmEnvironment
        );

        $ids = array_map(
            static fn (array $row): int =>
                (int) $row['evento_clinico_id'],
            $rows
        );

        $this->assertNotContains(
            $eventId,
            $ids
        );
    }

    public function testSurgerySearchCanFindProcedure(): void
    {
        $patientId = $this->createPatient();

        $procedureId = $this->createProcedure(
            'OVARIOHISTERECTOMIA_QA'
        );

        $eventId = (new SurgeryService())->create(
            [
                'animal_id' => $patientId,
                'procedimiento_quirurgico_id' => $procedureId,
                'fecha_inicio' => '2026-09-14 09:00:00',
            ],
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $rows = (new Surgery())->list(
            $this->vetEnvironment,
            'OVARIOHISTERECTOMIA_QA'
        );

        $ids = array_map(
            static fn (array $row): int =>
                (int) $row['evento_clinico_id'],
            $rows
        );

        $this->assertContains(
            $eventId,
            $ids
        );
    }
}