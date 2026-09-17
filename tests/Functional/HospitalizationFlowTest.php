<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\HospitalizationService;
use RuntimeException;

class HospitalizationFlowTest extends ClinicalTestCase
{
    private function hospitalizationStatusId(
        string $code
    ): int {
        $stmt = $this->db()->prepare(
            '
            SELECT id
            FROM estados_hospitalizacion
            WHERE codigo = :codigo
            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'No existe estado de hospitalización: '
                . $code
            );
        }

        return (int) $id;
    }


    private function createHospitalization(
        ?int $patientId = null,
        ?int $environmentId = null,
        array $extra = []
    ): int {
        $environmentId ??=
            $this->vetEnvironment;

        $patientId ??=
            $this->createPatient(
                $environmentId
            );

        $data = array_merge(
            [
                'animal_id' => $patientId,
                'fecha_ingreso' =>
                    date('Y-m-d H:i:s'),
                'motivo_ingreso' =>
                    'Hospitalización QA',
                'impresion_clinica_ingreso' =>
                    'Paciente estable',
                'indicaciones_generales' =>
                    'Monitoreo clínico',
            ],
            $extra
        );

        return
            (new HospitalizationService())
                ->create(
                    $data,
                    $environmentId,
                    $this->superAdminId
                );
    }


    public function testHospitalizationCanBeCreated(): void
    {
        $patientId =
            $this->createPatient();

        $eventId =
            $this->createHospitalization(
                $patientId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                h.evento_clinico_id,
                h.fecha_ingreso,
                h.motivo_ingreso,
                h.impresion_clinica_ingreso,
                h.indicaciones_generales,
                eh.codigo AS estado_codigo,
                ec.animal_id
            FROM hospitalizaciones h
            INNER JOIN estados_hospitalizacion eh
                ON eh.id =
                    h.estado_hospitalizacion_id
            INNER JOIN eventos_clinicos ec
                ON ec.id =
                    h.evento_clinico_id
            WHERE h.evento_clinico_id = :id
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertSame(
            $eventId,
            (int) $row[
                'evento_clinico_id'
            ]
        );

        $this->assertSame(
            $patientId,
            (int) $row['animal_id']
        );

        $this->assertSame(
            'ACTIVA',
            $row['estado_codigo']
        );

        $this->assertSame(
            'Hospitalización QA',
            $row['motivo_ingreso']
        );
    }


    public function testHospitalizationCreatesClinicalEvent(): void
    {
        $eventId =
            $this->createHospitalization();

        $stmt = $this->db()->prepare(
            '
            SELECT
                tec.codigo,
                ec.responsable_id,
                ec.titulo
            FROM eventos_clinicos ec
            INNER JOIN tipos_evento_clinico tec
                ON tec.id =
                    ec.tipo_evento_id
            WHERE ec.id = :id
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertSame(
            'HOSPITALIZACION',
            $row['codigo']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $row['responsable_id']
        );

        $this->assertSame(
            'Hospitalización',
            $row['titulo']
        );
    }


    public function testHospitalizationCanCreateInitialWeight(): void
    {
        $patientId =
            $this->createPatient();

        $before =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM animales_pesos
                WHERE animal_id = :animal
                ',
                [
                    'animal' => $patientId,
                ]
            );

        $this->createHospitalization(
            $patientId,
            $this->vetEnvironment,
            [
                'peso_kg' => 18.75,
            ]
        );

        $after =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM animales_pesos
                WHERE animal_id = :animal
                ',
                [
                    'animal' => $patientId,
                ]
            );

        $this->assertSame(
            $before + 1,
            $after
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                peso_kg,
                origen
            FROM animales_pesos
            WHERE animal_id = :animal
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'animal' => $patientId,
        ]);

        $row = $stmt->fetch();

        $this->assertEquals(
            18.75,
            (float) $row['peso_kg']
        );

        $this->assertSame(
            'HOSPITALIZACION',
            $row['origen']
        );
    }


    public function testInvalidInitialWeightRollsBackHospitalization(): void
    {
        $patientId =
            $this->createPatient();

        $before =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM hospitalizaciones
                '
            );

        $this->expectException(
            RuntimeException::class
        );

        try {
            $this->createHospitalization(
                $patientId,
                $this->vetEnvironment,
                [
                    'peso_kg' => -5,
                ]
            );
        } finally {
            $after =
                (int) $this->scalar(
                    '
                    SELECT COUNT(*)
                    FROM hospitalizaciones
                    '
                );

            $this->assertSame(
                $before,
                $after
            );
        }
    }


    public function testHospitalizationCanCreateInitialSigns(): void
    {
        $eventId =
            $this->createHospitalization(
                null,
                null,
                [
                    'temperatura_c' => 38.5,
                    'frecuencia_cardiaca' =>
                        100,
                    'frecuencia_respiratoria' =>
                        25,
                    'tiempo_llenado_capilar_seg' =>
                        2,
                    'condicion_corporal' =>
                        'Normal',
                    'nivel_dolor' => 'Leve',
                    'apetito' => 'Normal',
                    'hidratacion' => 'Normal',
                    'vomitos' => 1,
                    'diarrea' => 0,
                    'tos' => 1,
                ]
            );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM hospitalizacion_signos
            WHERE hospitalizacion_evento_id = :id
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertEquals(
            38.5,
            (float) $row[
                'temperatura_c'
            ]
        );

        $this->assertEquals(
            100,
            (float) $row[
                'frecuencia_cardiaca'
            ]
        );

        $this->assertSame(
            1,
            (int) $row['vomitos']
        );

        $this->assertSame(
            1,
            (int) $row['tos']
        );
    }


    public function testSignsCanBeAddedToActiveHospitalization(): void
    {
        $eventId =
            $this->createHospitalization();

        (new HospitalizationService())
            ->addSigns(
                $eventId,
                [
                    'temperatura_c' => 39.1,
                    'frecuencia_cardiaca' =>
                        110,
                    'observaciones' =>
                        'Control QA',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                temperatura_c,
                frecuencia_cardiaca,
                observaciones
            FROM hospitalizacion_signos
            WHERE hospitalizacion_evento_id =
                :id
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertEquals(
            39.1,
            (float) $row[
                'temperatura_c'
            ]
        );

        $this->assertEquals(
            110,
            (float) $row[
                'frecuencia_cardiaca'
            ]
        );

        $this->assertSame(
            'Control QA',
            $row['observaciones']
        );
    }


    public function testEvolutionCanBeAdded(): void
    {
        $eventId =
            $this->createHospitalization();

        (new HospitalizationService())
            ->addEvolution(
                $eventId,
                [
                    'evolucion' =>
                        'Paciente presenta mejoría.',
                    'observaciones' =>
                        'Continúa monitoreo.',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                evolucion,
                observaciones,
                registrado_por
            FROM hospitalizacion_evoluciones
            WHERE hospitalizacion_evento_id =
                :id
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertSame(
            'Paciente presenta mejoría.',
            $row['evolucion']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $row[
                'registrado_por'
            ]
        );
    }


    public function testEmptyEvolutionIsRejected(): void
    {
        $eventId =
            $this->createHospitalization();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La evolución es obligatoria.'
        );

        (new HospitalizationService())
            ->addEvolution(
                $eventId,
                [
                    'evolucion' => '   ',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testBasicFluidTherapyCanBeAdded(): void
    {
        $eventId =
            $this->createHospitalization();

        $categoryId =
            (int) $this->scalar(
                '
                SELECT id
                FROM categorias_mantenimiento_fluido
                WHERE activo = 1
                ORDER BY id
                LIMIT 1
                '
            );

        (new HospitalizationService())
            ->addFluid(
                $eventId,
                [
                    'categoria_mantenimiento_id' =>
                        $categoryId,
                    'mantenimiento_ml' => 500,
                    'rehidratacion_ml' => 250,
                    'porcentaje_deshidratacion' =>
                        5,
                    'volumen_total_ml' => 750,
                    'velocidad_ml_hora' => 75,
                    'observaciones' =>
                        'Fluidoterapia QA',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM hospitalizacion_fluidoterapias
            WHERE hospitalizacion_evento_id =
                :id
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertEquals(
            500,
            (float) $row[
                'mantenimiento_ml'
            ]
        );

        $this->assertEquals(
            750,
            (float) $row[
                'volumen_total_ml'
            ]
        );

        $this->assertEquals(
            75,
            (float) $row[
                'velocidad_ml_hora'
            ]
        );
    }


    public function testPatientCannotHaveTwoActiveHospitalizations(): void
    {
        $patientId =
            $this->createPatient();

        $this->createHospitalization(
            $patientId
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'ya tiene una hospitalización activa'
        );

        $this->createHospitalization(
            $patientId
        );
    }


    public function testHospitalizationCannotUsePatientFromAnotherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->farmEnvironment
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->createHospitalization(
            $patientId,
            $this->vetEnvironment
        );
    }


    public function testEvolutionCannotCrossEnvironment(): void
    {
        $eventId =
            $this->createHospitalization(
                null,
                $this->vetEnvironment
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Hospitalización no encontrada.'
        );

        (new HospitalizationService())
            ->addEvolution(
                $eventId,
                [
                    'evolucion' =>
                        'Intento cruzado QA',
                ],
                $this->farmEnvironment,
                $this->superAdminId
            );
    }


    public function testInvalidClosingStatusIsRejected(): void
    {
        $eventId =
            $this->createHospitalization();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Estado de cierre inválido.'
        );

        (new HospitalizationService())
            ->close(
                $eventId,
                [
                    'estado_codigo' =>
                        'INVENTADO',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testExitCannotBeBeforeAdmission(): void
    {
        $eventId =
            $this->createHospitalization(
                null,
                null,
                [
                    'fecha_ingreso' =>
                        '2026-09-14 10:00:00',
                ]
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'salida no puede ser anterior'
        );

        (new HospitalizationService())
            ->close(
                $eventId,
                [
                    'estado_codigo' =>
                        'ALTA',
                    'fecha_salida' =>
                        '2026-09-14 09:00:00',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testHospitalizationCanBeClosedWithDischarge(): void
    {
        $eventId =
            $this->createHospitalization(
                null,
                null,
                [
                    'fecha_ingreso' =>
                        '2026-09-14 08:00:00',
                ]
            );

        (new HospitalizationService())
            ->close(
                $eventId,
                [
                    'estado_codigo' =>
                        'ALTA',
                    'fecha_salida' =>
                        '2026-09-14 12:00:00',
                    'observaciones_alta' =>
                        'Paciente estable.',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                eh.codigo,
                h.fecha_salida,
                h.observaciones_alta,
                h.responsable_alta_id
            FROM hospitalizaciones h
            INNER JOIN estados_hospitalizacion eh
                ON eh.id =
                    h.estado_hospitalizacion_id
            WHERE h.evento_clinico_id = :id
            '
        );

        $stmt->execute([
            'id' => $eventId,
        ]);

        $row = $stmt->fetch();

        $this->assertSame(
            'ALTA',
            $row['codigo']
        );

        $this->assertSame(
            '2026-09-14 12:00:00',
            $row['fecha_salida']
        );

        $this->assertSame(
            'Paciente estable.',
            $row['observaciones_alta']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $row[
                'responsable_alta_id'
            ]
        );
    }


    public function testClosedHospitalizationRejectsNewEvolution(): void
    {
        $eventId =
            $this->createHospitalization(
                null,
                null,
                [
                    'fecha_ingreso' =>
                        '2026-09-14 08:00:00',
                ]
            );

        (new HospitalizationService())
            ->close(
                $eventId,
                [
                    'estado_codigo' => 'ALTA',
                    'fecha_salida' =>
                        '2026-09-14 12:00:00',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'hospitalización ya está cerrada'
        );

        (new HospitalizationService())
            ->addEvolution(
                $eventId,
                [
                    'evolucion' =>
                        'No debería guardarse',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testCreationWritesAudit(): void
    {
        $eventId =
            $this->createHospitalization();

        $count =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM auditoria
                WHERE usuario_id = :usuario
                  AND entorno_id = :entorno
                  AND modulo = "HOSPITALIZACION"
                  AND accion = "CREAR"
                  AND tabla_afectada =
                      "hospitalizaciones"
                  AND registro_id = :registro
                ',
                [
                    'usuario' =>
                        $this->superAdminId,
                    'entorno' =>
                        $this->vetEnvironment,
                    'registro' => $eventId,
                ]
            );

        $this->assertGreaterThanOrEqual(
            1,
            $count
        );
    }


    public function testEvolutionWritesAudit(): void
    {
        $eventId =
            $this->createHospitalization();

        (new HospitalizationService())
            ->addEvolution(
                $eventId,
                [
                    'evolucion' =>
                        'Evolución para auditoría',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $count =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM auditoria
                WHERE usuario_id = :usuario
                  AND entorno_id = :entorno
                  AND modulo = "HOSPITALIZACION"
                  AND accion =
                      "REGISTRAR_EVOLUCION"
                  AND tabla_afectada =
                      "hospitalizacion_evoluciones"
                ',
                [
                    'usuario' =>
                        $this->superAdminId,
                    'entorno' =>
                        $this->vetEnvironment,
                ]
            );

        $this->assertGreaterThanOrEqual(
            1,
            $count
        );
    }
}