<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\ConsultationService;
use App\Services\TreatmentService;
use RuntimeException;

class TreatmentFlowTest extends ClinicalTestCase
{
    private function createConsultation(
        ?int $environmentId = null
    ): int {
        $environmentId ??=
            $this->vetEnvironment;

        $patientId =
            $this->createPatient(
                $environmentId
            );

        return (new ConsultationService())->create(
            [
                'animal_id' => $patientId,
                'motivo_consulta'
                => 'Consulta previa a tratamiento',
            ],
            $environmentId,
            $this->superAdminId
        );
    }

    private function treatmentType(
        string $code = 'CLINICO'
    ): int {
        return $this->catalogId(
            'tipos_tratamiento',
            'codigo',
            $code
        );
    }

    public function testTreatmentCanBeCreatedForClinicalEvent(): void
    {
        $eventId =
            $this->createConsultation();

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'fecha_inicio'
                    => '2026-09-14 11:00:00',

                    'instrucciones_generales'
                    => 'Tratamiento de prueba',

                    'observaciones'
                    => 'Sin novedades',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $treatmentId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM tratamientos
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $treatmentId,
        ]);

        $treatment = $stmt->fetch();

        $this->assertNotFalse(
            $treatment
        );

        $this->assertSame(
            $eventId,
            (int) $treatment['evento_clinico_id']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $treatment['indicado_por']
        );

        $this->assertSame(
            'Tratamiento de prueba',
            $treatment['instrucciones_generales']
        );
    }

    public function testTreatmentCanIncludeDrug(): void
    {
        $eventId =
            $this->createConsultation();

        $drugId =
            $this->createDrug();

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'medicamentos' => [
                        [
                            'farmaco_id' => $drugId,
                            'dosis_cantidad' => 2.5,
                            'frecuencia_texto'
                            => 'Cada 12 horas',
                            'duracion_cantidad' => 5,
                            'instrucciones'
                            => 'Administrar después de comer',
                        ],
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM tratamiento_medicamentos
            WHERE tratamiento_id = :tratamiento
            '
        );

        $stmt->execute([
            'tratamiento' => $treatmentId,
        ]);

        $medication = $stmt->fetch();

        $this->assertNotFalse(
            $medication
        );

        $this->assertSame(
            $drugId,
            (int) $medication['farmaco_id']
        );

        $this->assertEquals(
            2.5,
            (float) $medication['dosis_cantidad']
        );

        $this->assertEquals(
            5,
            (float) $medication['duracion_cantidad']
        );

        $this->assertSame(
            'Cada 12 horas',
            $medication['frecuencia_texto']
        );

        $this->assertSame(
            0,
            (int) $medication['orden']
        );
    }

    public function testApplicationCanBeRecordedForTreatmentMedication(): void
    {
        $eventId =
            $this->createConsultation();

        $drugId =
            $this->createDrug();

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'medicamentos' => [
                        [
                            'farmaco_id' => $drugId,
                            'dosis_cantidad' => 1.5,
                        ],
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $medicationId =
            (int) $this->scalar(
                '
                SELECT id
                FROM tratamiento_medicamentos
                WHERE tratamiento_id = :tratamiento
                LIMIT 1
                ',
                [
                    'tratamiento'
                    => $treatmentId,
                ]
            );

        (new TreatmentService())->addApplication(
            $medicationId,
            1.5,
            null,
            'Aplicación QA',
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM medicamento_aplicaciones
            WHERE tratamiento_medicamento_id = :medicamento
            '
        );

        $stmt->execute([
            'medicamento'
            => $medicationId,
        ]);

        $application = $stmt->fetch();

        $this->assertNotFalse(
            $application
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $application['aplicado_por']
        );

        $this->assertEquals(
            1.5,
            (float) $application['cantidad_aplicada']
        );

        $this->assertSame(
            'Aplicación QA',
            $application['observaciones']
        );
    }

    public function testTreatmentWritesAudit(): void
    {
        $eventId =
            $this->createConsultation();

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),
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
              AND modulo = "TRATAMIENTOS"
              AND accion = "CREAR"
              AND tabla_afectada = "tratamientos"
              AND registro_id = :registro
            ',
            [
                'usuario'
                => $this->superAdminId,

                'entorno'
                => $this->vetEnvironment,

                'registro'
                => $treatmentId,
            ]
        );

        $this->assertSame(
            1,
            $count
        );
    }

    public function testMedicationApplicationWritesAudit(): void
    {
        $eventId =
            $this->createConsultation();

        $drugId =
            $this->createDrug();

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'medicamentos' => [
                        [
                            'farmaco_id'
                            => $drugId,

                            'dosis_cantidad'
                            => 1,
                        ],
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $medicationId =
            (int) $this->scalar(
                '
                SELECT id
                FROM tratamiento_medicamentos
                WHERE tratamiento_id = :tratamiento
                LIMIT 1
                ',
                [
                    'tratamiento'
                    => $treatmentId,
                ]
            );

        (new TreatmentService())->addApplication(
            $medicationId,
            1,
            null,
            null,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM auditoria
            WHERE usuario_id = :usuario
              AND entorno_id = :entorno
              AND modulo = "TRATAMIENTOS"
              AND accion = "APLICAR_MEDICAMENTO"
              AND tabla_afectada = "medicamento_aplicaciones"
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

    public function testTreatmentCannotUseEventFromAnotherEnvironment(): void
    {
        $foreignEvent =
            $this->createConsultation(
                $this->farmEnvironment
            );

        $this->expectException(
            RuntimeException::class
        );

        (new TreatmentService())->create(
            $foreignEvent,
            [
                'tipo_tratamiento_id'
                => $this->treatmentType(),
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testMedicationApplicationCannotCrossEnvironment(): void
    {
        $foreignEvent =
            $this->createConsultation(
                $this->farmEnvironment
            );

        $drugId =
            $this->createDrug();

        $treatmentId =
            (new TreatmentService())->create(
                $foreignEvent,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'medicamentos' => [
                        [
                            'farmaco_id'
                            => $drugId,

                            'dosis_cantidad'
                            => 1,
                        ],
                    ],
                ],
                $this->farmEnvironment,
                $this->superAdminId
            );

        $medicationId =
            (int) $this->scalar(
                '
                SELECT id
                FROM tratamiento_medicamentos
                WHERE tratamiento_id = :tratamiento
                LIMIT 1
                ',
                [
                    'tratamiento'
                    => $treatmentId,
                ]
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Medicamento no encontrado.'
        );

        (new TreatmentService())->addApplication(
            $medicationId,
            1,
            null,
            null,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testZeroApplicationQuantityIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La cantidad aplicada debe ser un número finito mayor a cero.'
        );

        (new TreatmentService())->addApplication(
            999999999,
            0,
            null,
            null,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInactiveOrNonexistentDrugIsRejectedAndTreatmentRollsBack(): void
    {
        $eventId =
            $this->createConsultation();

        $before = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM tratamientos
            WHERE evento_clinico_id = :evento
            ',
            [
                'evento' => $eventId,
            ]
        );

        try {
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'medicamentos' => [
                        [
                            'farmaco_id'
                            => 999999999,

                            'dosis_cantidad'
                            => 1,
                        ],
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba excepción por fármaco inválido.'
            );
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty(
                $exception->getMessage()
            );
        }

        $after = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM tratamientos
            WHERE evento_clinico_id = :evento
            ',
            [
                'evento' => $eventId,
            ]
        );

        $this->assertSame(
            $before,
            $after,
            'El tratamiento no hizo rollback completo.'
        );
    }

    public function testMedicationMayOmitDoseWithoutPhpWarning(): void
    {
        $eventId =
            $this->createConsultation();

        $drugId =
            $this->createDrug();

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'medicamentos' => [
                        [
                            'farmaco_id'
                            => $drugId,
                        ],
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $dose = $this->scalar(
            '
        SELECT dosis_cantidad
        FROM tratamiento_medicamentos
        WHERE tratamiento_id = :tratamiento
        LIMIT 1
        ',
            [
                'tratamiento' =>
                $treatmentId,
            ]
        );

        $this->assertNull(
            $dose
        );
    }

    public function testMedicationRejectsNonNumericDose(): void
    {
        $eventId =
            $this->createConsultation();

        $drugId =
            $this->createDrug();

        $before =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM tratamientos
            WHERE evento_clinico_id = :evento
            ',
                [
                    'evento' => $eventId,
                ]
            );

        try {
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id'
                    => $this->treatmentType(),

                    'medicamentos' => [
                        [
                            'farmaco_id'
                            => $drugId,

                            'dosis_cantidad'
                            => 'abc',
                        ],
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba RuntimeException por dosis inválida.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'valor numérico válido',
                $e->getMessage()
            );
        }

        $after =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM tratamientos
            WHERE evento_clinico_id = :evento
            ',
                [
                    'evento' => $eventId,
                ]
            );

        $this->assertSame(
            $before,
            $after
        );
    }

    public function testMedicationRejectsNegativeDose(): void
    {
        $eventId =
            $this->createConsultation();

        $drugId =
            $this->createDrug();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La dosis debe ser mayor a cero.'
        );

        (new TreatmentService())->create(
            $eventId,
            [
                'tipo_tratamiento_id'
                => $this->treatmentType(),

                'medicamentos' => [
                    [
                        'farmaco_id'
                        => $drugId,

                        'dosis_cantidad'
                        => -1,
                    ],
                ],
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testMedicationRejectsNonNumericDuration(): void
    {
        $eventId =
            $this->createConsultation();

        $drugId =
            $this->createDrug();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La duración debe ser un valor numérico válido.'
        );

        (new TreatmentService())->create(
            $eventId,
            [
                'tipo_tratamiento_id'
                => $this->treatmentType(),

                'medicamentos' => [
                    [
                        'farmaco_id'
                        => $drugId,

                        'dosis_cantidad'
                        => 1,

                        'duracion_cantidad'
                        => 'cinco',
                    ],
                ],
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
    public function testMedicationApplicationRejectsInfiniteQuantity(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'número finito mayor a cero'
        );

        (new TreatmentService())->addApplication(
            999999999,
            INF,
            null,
            null,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
    public function testMedicationApplicationRejectsNanQuantity(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'número finito mayor a cero'
        );

        (new TreatmentService())->addApplication(
            999999999,
            NAN,
            null,
            null,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
}
