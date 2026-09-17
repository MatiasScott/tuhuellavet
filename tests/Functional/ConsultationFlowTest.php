<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\ConsultationService;
use RuntimeException;

class ConsultationFlowTest extends ClinicalTestCase
{
    public function testConsultationCreatesClinicalEvent(): void
    {
        $patientId = $this->createPatient();

        $eventId =
            (new ConsultationService())->create(
                [
                    'animal_id' => $patientId,
                    'fecha_evento' => '2026-09-14 10:00:00',
                    'titulo' => 'Consulta QA',
                    'motivo_consulta' => 'Control general',
                    'anamnesis' => 'Paciente estable',
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
                ec.animal_id,
                ec.responsable_id,
                tec.codigo AS tipo_codigo,
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

        $event = $stmt->fetch();

        $this->assertNotFalse($event);

        $this->assertSame(
            $patientId,
            (int) $event['animal_id']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $event['responsable_id']
        );

        $this->assertSame(
            'CONSULTA_EXTERNA',
            $event['tipo_codigo']
        );

        $this->assertSame(
            'Consulta QA',
            $event['titulo']
        );
    }

    public function testConsultationCreatesExternalConsultationRecord(): void
    {
        $patientId =
            $this->createPatient();

        $eventId =
            (new ConsultationService())->create(
                [
                    'animal_id' => $patientId,
                    'motivo_consulta' => 'Tos ocasional',
                    'anamnesis' => 'Tres días de evolución',
                    'antecedentes' => 'Sin antecedentes',
                    'recomendaciones' => 'Control en 7 días',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM consultas_externas
            WHERE evento_clinico_id = :evento
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $consultation = $stmt->fetch();

        $this->assertNotFalse(
            $consultation
        );

        $this->assertSame(
            'Tos ocasional',
            $consultation['motivo_consulta']
        );

        $this->assertSame(
            'Tres días de evolución',
            $consultation['anamnesis']
        );

        $this->assertSame(
            'Sin antecedentes',
            $consultation['antecedentes']
        );

        $this->assertSame(
            'Control en 7 días',
            $consultation['recomendaciones']
        );
    }

    public function testConsultationCreatesClinicalExam(): void
    {
        $patientId =
            $this->createPatient();

        $eventId =
            (new ConsultationService())->create(
                [
                    'animal_id' => $patientId,
                    'alimentacion' => 'Balanceado',
                    'historial_reproductivo' => 'Sin novedad',
                    'frecuencia_cardiaca' => 90,
                    'frecuencia_respiratoria' => 25,
                    'temperatura_c' => 38.7,
                    'tiempo_llenado_capilar_seg' => 1.5,
                    'ganglios_linfaticos' => 'Normales',
                    'condicion_corporal' => '3/5',
                    'vomitos' => 1,
                    'diarrea' => 0,
                    'tos' => 1,
                    'examen_observaciones' => 'Paciente cooperativo',
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
            90,
            (float) $exam['frecuencia_cardiaca']
        );

        $this->assertEquals(
            25,
            (float) $exam['frecuencia_respiratoria']
        );

        $this->assertEquals(
            38.7,
            (float) $exam['temperatura_c']
        );

        $this->assertSame(
            1,
            (int) $exam['vomitos']
        );

        $this->assertSame(
            0,
            (int) $exam['diarrea']
        );

        $this->assertSame(
            1,
            (int) $exam['tos']
        );
    }

    public function testConsultationCreatesThreeDiagnosisTypes(): void
    {
        $patientId =
            $this->createPatient();

        $eventId =
            (new ConsultationService())->create(
                [
                    'animal_id' => $patientId,

                    'diagnostico_diferencial'
                        => 'Alergia',

                    'diagnostico_presuntivo'
                        => 'Dermatitis',

                    'diagnostico_definitivo'
                        => 'Dermatitis atópica',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                td.codigo,
                dc.descripcion,
                dc.ingresado_por
            FROM diagnosticos_clinicos dc
            INNER JOIN tipos_diagnostico td
                ON td.id = dc.tipo_diagnostico_id
            WHERE dc.evento_clinico_id = :evento
            ORDER BY td.id
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        $diagnoses = $stmt->fetchAll();

        $this->assertCount(
            3,
            $diagnoses
        );

        $codes = array_column(
            $diagnoses,
            'codigo'
        );

        $this->assertSame(
            [
                'DIFERENCIAL',
                'PRESUNTIVO',
                'DEFINITIVO',
            ],
            $codes
        );

        foreach ($diagnoses as $diagnosis) {
            $this->assertSame(
                $this->superAdminId,
                (int) $diagnosis['ingresado_por']
            );
        }
    }

    public function testConsultationWeightCreatesHistoryWithoutOverwritingPreviousWeight(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment,
                10.500
            );

        (new ConsultationService())->create(
            [
                'animal_id' => $patientId,
                'peso_kg' => 11.250,
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
            11.250,
            (float) $weights[1]['peso_kg']
        );

        $this->assertSame(
            'CONSULTA_EXTERNA',
            $weights[1]['origen']
        );
    }

    public function testConsultationWritesAudit(): void
    {
        $patientId =
            $this->createPatient();

        $eventId =
            (new ConsultationService())->create(
                [
                    'animal_id' => $patientId,
                    'motivo_consulta' => 'Auditoría QA',
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
              AND modulo = "CONSULTAS"
              AND accion = "CREAR"
              AND tabla_afectada = "eventos_clinicos"
              AND registro_id = :registro
            ',
            [
                'usuario' => $this->superAdminId,
                'entorno' => $this->vetEnvironment,
                'registro' => $eventId,
            ]
        );

        $this->assertSame(
            1,
            $count
        );
    }

    public function testConsultationCannotUsePatientFromAnotherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->farmEnvironment
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El paciente no pertenece al entorno actual.'
        );

        (new ConsultationService())->create(
            [
                'animal_id' => $patientId,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidWeightRollsBackEntireConsultation(): void
    {
        $patientId =
            $this->createPatient();

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
            (new ConsultationService())->create(
                [
                    'animal_id' => $patientId,
                    'motivo_consulta' => 'Debe hacer rollback',
                    'peso_kg' => 0,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba RuntimeException por peso inválido.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'El peso debe ser mayor a cero.',
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
            'Una consulta fallida dejó un evento clínico huérfano.'
        );
    }
}