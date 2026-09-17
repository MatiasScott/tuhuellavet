<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\PreventiveCareService;
use RuntimeException;

class DewormingFlowTest extends ClinicalTestCase
{
    public function testDewormingCanBeCreated(): void
    {
        $patientId =
            $this->createPatient();

        $drugId =
            $this->createDrug();

        $eventId =
            (new PreventiveCareService())->createDeworming(
                [
                    'animal_id' => $patientId,
                    'farmaco_id' => $drugId,
                    'fecha_evento'
                        => '2026-09-14 11:30:00',
                    'dosis' => 2.5,
                    'observaciones'
                        => 'Desparasitación QA',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $eventId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                d.*,
                ec.animal_id,
                te.codigo AS tipo_codigo
            FROM desparasitaciones d
            INNER JOIN eventos_clinicos ec
                ON ec.id = d.evento_clinico_id
            INNER JOIN tipos_evento_clinico te
                ON te.id = ec.tipo_evento_id
            WHERE d.evento_clinico_id = :evento
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $deworming =
            $stmt->fetch();

        $this->assertNotFalse(
            $deworming
        );

        $this->assertSame(
            $patientId,
            (int) $deworming['animal_id']
        );

        $this->assertSame(
            $drugId,
            (int) $deworming['farmaco_id']
        );

        $this->assertSame(
            'DESPARASITACION',
            $deworming['tipo_codigo']
        );

        $this->assertEquals(
            2.5,
            (float) $deworming['dosis']
        );
    }

    public function testDewormingAddsWeightHistory(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment,
                9.750
            );

        $drugId =
            $this->createDrug();

        (new PreventiveCareService())->createDeworming(
            [
                'animal_id' => $patientId,
                'farmaco_id' => $drugId,
                'peso_kg' => 10.300,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                peso_kg,
                origen
            FROM animales_pesos
            WHERE animal_id = :animal
            ORDER BY id
            '
        );

        $stmt->execute([
            'animal' => $patientId,
        ]);

        $weights =
            $stmt->fetchAll();

        $this->assertCount(
            2,
            $weights
        );

        $this->assertEquals(
            9.750,
            (float) $weights[0]['peso_kg']
        );

        $this->assertEquals(
            10.300,
            (float) $weights[1]['peso_kg']
        );

        $this->assertSame(
            'DESPARASITACION',
            $weights[1]['origen']
        );
    }

    public function testDewormingWritesAudit(): void
    {
        $patientId =
            $this->createPatient();

        $drugId =
            $this->createDrug();

        (new PreventiveCareService())->createDeworming(
            [
                'animal_id' => $patientId,
                'farmaco_id' => $drugId,
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
              AND modulo = "DESPARASITACION"
              AND accion = "CREAR"
              AND tabla_afectada = "desparasitaciones"
            ',
            [
                'usuario'
                    => $this->superAdminId,

                'entorno'
                    => $this->vetEnvironment,
            ]
        );

        $this->assertGreaterThanOrEqual(
            1,
            $count
        );
    }

    public function testDewormingCannotUsePatientFromAnotherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->farmEnvironment
            );

        $drugId =
            $this->createDrug();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El paciente no pertenece al entorno actual.'
        );

        (new PreventiveCareService())->createDeworming(
            [
                'animal_id' => $patientId,
                'farmaco_id' => $drugId,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInactiveDrugIsRejected(): void
    {
        $patientId =
            $this->createPatient();

        $drugId =
            $this->createDrug();

        $this->db()->prepare(
            '
            UPDATE farmacos
            SET activo = 0
            WHERE id = :id
            '
        )->execute([
            'id' => $drugId,
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El fármaco seleccionado no'
        );

        (new PreventiveCareService())->createDeworming(
            [
                'animal_id' => $patientId,
                'farmaco_id' => $drugId,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testZeroDewormingDoseRollsBack(): void
    {
        $patientId =
            $this->createPatient();

        $drugId =
            $this->createDrug();

        $before =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM eventos_clinicos
                WHERE animal_id = :animal
                ',
                [
                    'animal'
                        => $patientId,
                ]
            );

        try {
            (new PreventiveCareService())->createDeworming(
                [
                    'animal_id'
                        => $patientId,

                    'farmaco_id'
                        => $drugId,

                    'dosis'
                        => 0,
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

        $after =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM eventos_clinicos
                WHERE animal_id = :animal
                ',
                [
                    'animal'
                        => $patientId,
                ]
            );

        $this->assertSame(
            $before,
            $after,
            'La desparasitación fallida dejó un evento clínico.'
        );
    }
}