<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\FormulaService;
use App\Services\HospitalizationService;
use App\Services\TreatmentService;
use RuntimeException;

class HospitalizationAdvancedFlowTest extends ClinicalTestCase
{
    private function createHospitalization(
        ?int $patientId = null,
        ?int $environmentId = null,
        array $extra = []
    ): int {
        $environmentId ??= $this->vetEnvironment;
        $patientId ??= $this->createPatient($environmentId);

        return (new HospitalizationService())->create(
            array_merge(
                [
                    'animal_id' => $patientId,
                    'fecha_ingreso' => '2026-09-14 08:00:00',
                    'motivo_ingreso' => 'Hospitalización avanzada QA',
                ],
                $extra
            ),
            $environmentId,
            $this->superAdminId
        );
    }

    private function formulaCatalogId(
        string $table,
        string $code
    ): int {
        $allowed = [
            'categorias_formula',
            'estados_formula_version',
            'tipos_variable_formula',
            'origenes_variable_formula',
        ];

        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Catálogo no permitido.');
        }

        $stmt = $this->db()->prepare(
            "SELECT id FROM {$table} WHERE codigo = :codigo LIMIT 1"
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("No existe {$table}.{$code}");
        }

        return (int) $id;
    }

    private function createPublishedFormula(
        int $environmentId,
        string $expression = 'peso'
    ): array {
        $categoryId = $this->formulaCatalogId(
            'categorias_formula',
            'FLUIDOTERAPIA'
        );

        $publishedId = $this->formulaCatalogId(
            'estados_formula_version',
            'PUBLICADA'
        );

        $code = 'QA_HOSP_' . strtoupper(
            str_replace('.', '', uniqid('', true))
        );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO formulas
            (
                categoria_formula_id,
                codigo,
                nombre,
                descripcion,
                creada_por,
                activo
            )
            VALUES
            (
                :categoria,
                :codigo,
                :nombre,
                :descripcion,
                :usuario,
                1
            )
            '
        );

        $stmt->execute([
            'categoria' => $categoryId,
            'codigo' => $code,
            'nombre' => 'Fórmula hospitalización QA',
            'descripcion' => 'Fixture QA',
            'usuario' => $this->superAdminId,
        ]);

        $formulaId = (int) $this->db()->lastInsertId();

        $this->db()->prepare(
            '
            INSERT INTO formula_entornos
            (
                formula_id,
                entorno_id
            )
            VALUES
            (
                :formula,
                :entorno
            )
            '
        )->execute([
            'formula' => $formulaId,
            'entorno' => $environmentId,
        ]);

        $stmt = $this->db()->prepare(
            '
    INSERT INTO formula_versiones
    (
        formula_id,
        numero_version,
        expresion,
        estado_id,
        creada_por,
        publicada_por,
        publicada_at
    )
    VALUES
    (
        :formula,
        1,
        :expresion,
        :estado,
        :creada_por,
        :publicada_por,
        NOW()
    )
    '
        );

        $stmt->execute([
            'formula' => $formulaId,
            'expresion' => $expression,
            'estado' => $publishedId,
            'creada_por' => $this->superAdminId,
            'publicada_por' => $this->superAdminId,
        ]);

        $versionId = (int) $this->db()->lastInsertId();

        $typeId = $this->formulaCatalogId(
            'tipos_variable_formula',
            'DECIMAL'
        );

        $originId = $this->formulaCatalogId(
            'origenes_variable_formula',
            'PESO_ACTUAL'
        );

        $this->db()->prepare(
            '
            INSERT INTO formula_variables
            (
                formula_version_id,
                tipo_variable_id,
                origen_variable_id,
                codigo,
                etiqueta,
                obligatorio,
                orden
            )
            VALUES
            (
                :version,
                :tipo,
                :origen,
                "peso",
                "Peso",
                1,
                1
            )
            '
        )->execute([
            'version' => $versionId,
            'tipo' => $typeId,
            'origen' => $originId,
        ]);

        return [
            'formula_id' => $formulaId,
            'version_id' => $versionId,
        ];
    }

    private function createTreatment(
        int $eventId
    ): int {
        $typeId = (int) $this->scalar(
            '
            SELECT id
            FROM tipos_tratamiento
            WHERE codigo = "CLINICO"
            LIMIT 1
            '
        );

        return (new TreatmentService())->create(
            $eventId,
            [
                'tipo_tratamiento_id' => $typeId,
                'fecha_inicio' => '2026-09-14 08:30:00',
                'instrucciones_generales' => 'Tratamiento QA',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testFluidTherapyCanUseValidFormulaExecution(): void
    {
        $patientId = $this->createPatient();

        $this->db()->prepare(
            '
            INSERT INTO animales_pesos
            (
                animal_id,
                peso_kg,
                registrado_por,
                origen,
                fecha_registro
            )
            VALUES
            (
                :animal,
                20,
                :usuario,
                "QA",
                NOW()
            )
            '
        )->execute([
            'animal' => $patientId,
            'usuario' => $this->superAdminId,
        ]);

        $eventId = $this->createHospitalization(
            $patientId
        );

        $formula = $this->createPublishedFormula(
            $this->vetEnvironment
        );

        $execution = (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            $eventId,
            [],
            $this->vetEnvironment,
            $this->superAdminId,
            false,
            'FLUIDOTERAPIA'
        );

        (new HospitalizationService())->addFluid(
            $eventId,
            [
                'formula_ejecucion_id' => $execution['execution_id'],
                'volumen_total_ml' => 500,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                formula_id,
                formula_ejecucion_id
            FROM hospitalizacion_fluidoterapias
            WHERE hospitalizacion_evento_id = :evento
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
            $formula['formula_id'],
            (int) $row['formula_id']
        );

        $this->assertSame(
            $execution['execution_id'],
            (int) $row['formula_ejecucion_id']
        );
    }

    public function testFluidTherapyRejectsFormulaFromAnotherPatient(): void
    {
        $patientA = $this->createPatient();
        $patientB = $this->createPatient();

        $this->db()->prepare(
            '
            INSERT INTO animales_pesos
            (
                animal_id,
                peso_kg,
                registrado_por,
                origen,
                fecha_registro
            )
            VALUES
            (:animal, 15, :usuario, "QA", NOW())
            '
        )->execute([
            'animal' => $patientB,
            'usuario' => $this->superAdminId,
        ]);

        $eventA = $this->createHospitalization(
            $patientA
        );

        $eventB = $this->createHospitalization(
            $patientB
        );

        $formula = $this->createPublishedFormula(
            $this->vetEnvironment
        );

        $execution = (new FormulaService())->calculate(
            $formula['version_id'],
            $patientB,
            $eventB,
            [],
            $this->vetEnvironment,
            $this->superAdminId,
            false,
            'FLUIDOTERAPIA'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'no corresponde a esta hospitalización'
        );

        (new HospitalizationService())->addFluid(
            $eventA,
            [
                'formula_ejecucion_id' =>
                $execution['execution_id'],
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testClosingHospitalizationClosesOpenTreatment(): void
    {
        $eventId = $this->createHospitalization();

        $treatmentId = $this->createTreatment(
            $eventId
        );

        $before = $this->scalar(
            '
            SELECT fecha_fin
            FROM tratamientos
            WHERE id = :id
            ',
            [
                'id' => $treatmentId,
            ]
        );

        $this->assertNull($before);

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'ALTA',
                'fecha_salida' => '2026-09-14 12:00:00',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $after = $this->scalar(
            '
            SELECT fecha_fin
            FROM tratamientos
            WHERE id = :id
            ',
            [
                'id' => $treatmentId,
            ]
        );

        $this->assertSame(
            '2026-09-14 12:00:00',
            $after
        );
    }

    public function testClosingHospitalizationClosesOpenFluidTherapy(): void
    {
        $eventId = $this->createHospitalization();

        (new HospitalizationService())->addFluid(
            $eventId,
            [
                'volumen_total_ml' => 500,
                'fecha_inicio' => '2026-09-14 08:30:00',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $fluidId = (int) $this->scalar(
            '
            SELECT id
            FROM hospitalizacion_fluidoterapias
            WHERE hospitalizacion_evento_id = :evento
            ORDER BY id DESC
            LIMIT 1
            ',
            [
                'evento' => $eventId,
            ]
        );

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'ALTA',
                'fecha_salida' => '2026-09-14 12:00:00',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $end = $this->scalar(
            '
            SELECT fecha_fin
            FROM hospitalizacion_fluidoterapias
            WHERE id = :id
            ',
            [
                'id' => $fluidId,
            ]
        );

        $this->assertSame(
            '2026-09-14 12:00:00',
            $end
        );
    }

    public function testDischargeStatusCanBeTransfer(): void
    {
        $eventId = $this->createHospitalization();

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'TRASLADO',
                'fecha_salida' => '2026-09-14 12:00:00',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $status = $this->scalar(
            '
            SELECT eh.codigo
            FROM hospitalizaciones h
            JOIN estados_hospitalizacion eh
              ON eh.id = h.estado_hospitalizacion_id
            WHERE h.evento_clinico_id = :evento
            ',
            [
                'evento' => $eventId,
            ]
        );

        $this->assertSame(
            'TRASLADO',
            $status
        );
    }

    public function testDischargeStatusCanBeDeceased(): void
    {
        $eventId = $this->createHospitalization();

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'FALLECIDO',
                'fecha_salida' => '2026-09-14 12:00:00',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $status = $this->scalar(
            '
            SELECT eh.codigo
            FROM hospitalizaciones h
            JOIN estados_hospitalizacion eh
              ON eh.id = h.estado_hospitalizacion_id
            WHERE h.evento_clinico_id = :evento
            ',
            [
                'evento' => $eventId,
            ]
        );

        $this->assertSame(
            'FALLECIDO',
            $status
        );
    }

    public function testDischargeStatusCanBeCancelled(): void
    {
        $eventId = $this->createHospitalization();

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'CANCELADA',
                'fecha_salida' => '2026-09-14 12:00:00',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $status = $this->scalar(
            '
            SELECT eh.codigo
            FROM hospitalizaciones h
            JOIN estados_hospitalizacion eh
              ON eh.id = h.estado_hospitalizacion_id
            WHERE h.evento_clinico_id = :evento
            ',
            [
                'evento' => $eventId,
            ]
        );

        $this->assertSame(
            'CANCELADA',
            $status
        );
    }

    public function testClosedHospitalizationRejectsSigns(): void
    {
        $eventId = $this->createHospitalization();

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'ALTA',
                'fecha_salida' => '2026-09-14 12:00:00',
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

        (new HospitalizationService())->addSigns(
            $eventId,
            [
                'temperatura_c' => 38.5,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testClosedHospitalizationRejectsFluidTherapy(): void
    {
        $eventId = $this->createHospitalization();

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'ALTA',
                'fecha_salida' => '2026-09-14 12:00:00',
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

        (new HospitalizationService())->addFluid(
            $eventId,
            [
                'volumen_total_ml' => 100,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testCloseWritesAudit(): void
    {
        $eventId = $this->createHospitalization();

        (new HospitalizationService())->close(
            $eventId,
            [
                'estado_codigo' => 'ALTA',
                'fecha_salida' => '2026-09-14 12:00:00',
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
              AND modulo = "HOSPITALIZACION"
              AND accion = "CERRAR"
              AND tabla_afectada = "hospitalizaciones"
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
}
