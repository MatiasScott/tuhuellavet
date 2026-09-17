<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\PreventiveCareService;
use RuntimeException;

class VaccinationFlowTest extends ClinicalTestCase
{
    private function createVaccine(): int
    {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO vacunas
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
            'nombre' => 'Vacuna QA ' . uniqid(),
            'descripcion' => 'Fixture temporal PHPUnit',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function testVaccinationCanBeCreated(): void
    {
        $patientId = $this->createPatient();
        $vaccineId = $this->createVaccine();

        $eventId =
            (new PreventiveCareService())->createVaccination(
                [
                    'animal_id' => $patientId,
                    'vacuna_id' => $vaccineId,
                    'fecha_evento' => '2026-09-14 11:00:00',
                    'dosis' => 1.5,
                    'lote' => 'QA-LOT-001',
                    'observaciones' => 'Vacunación QA',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(0, $eventId);

        $stmt = $this->db()->prepare(
            '
            SELECT
                v.*,
                ec.animal_id,
                te.codigo AS tipo_codigo
            FROM vacunaciones v
            INNER JOIN eventos_clinicos ec
                ON ec.id = v.evento_clinico_id
            INNER JOIN tipos_evento_clinico te
                ON te.id = ec.tipo_evento_id
            WHERE v.evento_clinico_id = :evento
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $vaccination = $stmt->fetch();

        $this->assertNotFalse($vaccination);

        $this->assertSame(
            $patientId,
            (int) $vaccination['animal_id']
        );

        $this->assertSame(
            $vaccineId,
            (int) $vaccination['vacuna_id']
        );

        $this->assertSame(
            'VACUNACION',
            $vaccination['tipo_codigo']
        );

        $this->assertEquals(
            1.5,
            (float) $vaccination['dosis']
        );

        $this->assertSame(
            'QA-LOT-001',
            $vaccination['lote']
        );
    }

    public function testVaccinationCreatesClinicalExam(): void
    {
        $patientId = $this->createPatient();
        $vaccineId = $this->createVaccine();

        $eventId =
            (new PreventiveCareService())->createVaccination(
                [
                    'animal_id' => $patientId,
                    'vacuna_id' => $vaccineId,
                    'temperatura_c' => 38.6,
                    'frecuencia_cardiaca' => 88,
                    'frecuencia_respiratoria' => 24,
                    'tos' => 0,
                    'vomitos' => 0,
                    'diarrea' => 0,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM examenes_clinicos_generales
            WHERE evento_clinico_id = :evento
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $exam = $stmt->fetch();

        $this->assertNotFalse($exam);

        $this->assertEquals(
            38.6,
            (float) $exam['temperatura_c']
        );

        $this->assertEquals(
            88,
            (float) $exam['frecuencia_cardiaca']
        );

        $this->assertEquals(
            24,
            (float) $exam['frecuencia_respiratoria']
        );
    }

    public function testVaccinationAddsWeightHistory(): void
    {
        $patientId = $this->createPatient(
            $this->vetEnvironment,
            10.500
        );

        $vaccineId = $this->createVaccine();

        (new PreventiveCareService())->createVaccination(
            [
                'animal_id' => $patientId,
                'vacuna_id' => $vaccineId,
                'peso_kg' => 11.200,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT peso_kg, origen
            FROM animales_pesos
            WHERE animal_id = :animal
            ORDER BY id
            '
        );

        $stmt->execute([
            'animal' => $patientId,
        ]);

        $weights = $stmt->fetchAll();

        $this->assertCount(
            2,
            $weights
        );

        $this->assertEquals(
            10.500,
            (float) $weights[0]['peso_kg']
        );

        $this->assertEquals(
            11.200,
            (float) $weights[1]['peso_kg']
        );

        $this->assertSame(
            'VACUNACION',
            $weights[1]['origen']
        );
    }

    public function testVaccinationWritesAudit(): void
    {
        $patientId = $this->createPatient();
        $vaccineId = $this->createVaccine();

        $eventId =
            (new PreventiveCareService())->createVaccination(
                [
                    'animal_id' => $patientId,
                    'vacuna_id' => $vaccineId,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM auditoria
            WHERE usuario_id = :usuario
              AND entorno_id = :entorno
              AND modulo = "VACUNAS"
              AND accion = "CREAR"
              AND tabla_afectada = "vacunaciones"
            ',
            [
                'usuario' => $this->superAdminId,
                'entorno' => $this->vetEnvironment,
            ]
        );

        $this->assertGreaterThanOrEqual(
            1,
            $count
        );

        $this->assertGreaterThan(
            0,
            $eventId
        );
    }

    public function testVaccinationCannotUsePatientFromAnotherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->farmEnvironment
            );

        $vaccineId =
            $this->createVaccine();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El paciente no pertenece al entorno actual.'
        );

        (new PreventiveCareService())->createVaccination(
            [
                'animal_id' => $patientId,
                'vacuna_id' => $vaccineId,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInactiveVaccineIsRejected(): void
    {
        $patientId = $this->createPatient();
        $vaccineId = $this->createVaccine();

        $this->db()->prepare(
            '
            UPDATE vacunas
            SET activo = 0
            WHERE id = :id
            '
        )->execute([
            'id' => $vaccineId,
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La vacuna seleccionada no'
        );

        (new PreventiveCareService())->createVaccination(
            [
                'animal_id' => $patientId,
                'vacuna_id' => $vaccineId,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testZeroVaccinationDoseRollsBack(): void
    {
        $patientId = $this->createPatient();
        $vaccineId = $this->createVaccine();

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
            (new PreventiveCareService())->createVaccination(
                [
                    'animal_id' => $patientId,
                    'vacuna_id' => $vaccineId,
                    'dosis' => 0,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba RuntimeException por dosis inválida.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'La dosis debe ser mayor a cero.',
                $exception->getMessage()
            );
        }

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
            'La vacunación fallida dejó un evento clínico.'
        );
    }
}