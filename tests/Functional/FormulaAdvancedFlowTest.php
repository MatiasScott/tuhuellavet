<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Models\Formula;
use App\Services\ConsultationService;
use App\Services\FormulaService;
use RuntimeException;

class FormulaAdvancedFlowTest extends ClinicalTestCase
{
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
            throw new RuntimeException(
                'Catálogo no permitido en fixture.'
            );
        }

        $stmt = $this->db()->prepare(
            "
            SELECT id
            FROM {$table}
            WHERE codigo = :codigo
            LIMIT 1
            "
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                "No existe {$table}.{$code}"
            );
        }

        return (int) $id;
    }


    private function createFormula(
        int $environmentId,
        string $expression = 'valor',
        string $state = 'PUBLICADA'
    ): array {
        $categoryId =
            $this->formulaCatalogId(
                'categorias_formula',
                'MEDICAMENTO'
            );

        $stateId =
            $this->formulaCatalogId(
                'estados_formula_version',
                $state
            );

        $code =
            'QA_ADV_' .
            strtoupper(
                str_replace(
                    '.',
                    '',
                    uniqid('', true)
                )
            );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO formulas
            (
                categoria_formula_id,
                codigo,
                nombre,
                descripcion,
                unidad_resultado_id,
                creada_por,
                activo
            )
            VALUES
            (
                :categoria,
                :codigo,
                :nombre,
                :descripcion,
                NULL,
                :usuario,
                1
            )
            '
        );

        $stmt->execute([
            'categoria' => $categoryId,
            'codigo' => $code,
            'nombre' => 'Fórmula avanzada QA',
            'descripcion' => 'Fixture avanzado',
            'usuario' => $this->superAdminId,
        ]);

        $formulaId =
            (int) $this->db()->lastInsertId();

        $stmt = $this->db()->prepare(
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
        );

        $stmt->execute([
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
                notas_version,
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
                :notas,
                :estado,
                :creada_por,
                :publicada_por,
                :publicada_at
            )
            '
        );

        $published =
            $state === 'PUBLICADA';

        $stmt->execute([
            'formula' => $formulaId,
            'expresion' => $expression,
            'notas' => 'Versión avanzada QA',
            'estado' => $stateId,
            'creada_por' => $this->superAdminId,
            'publicada_por' =>
            $published
                ? $this->superAdminId
                : null,
            'publicada_at' =>
            $published
                ? date('Y-m-d H:i:s')
                : null,
        ]);

        return [
            'formula_id' => $formulaId,
            'version_id' =>
            (int) $this->db()->lastInsertId(),
        ];
    }


    private function createVariable(
        int $versionId,
        string $code,
        string $origin,
        string $type = 'DECIMAL',
        bool $required = true
    ): int {
        $typeId =
            $this->formulaCatalogId(
                'tipos_variable_formula',
                $type
            );

        $originId =
            $this->formulaCatalogId(
                'origenes_variable_formula',
                $origin
            );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO formula_variables
            (
                formula_version_id,
                tipo_variable_id,
                origen_variable_id,
                unidad_medida_id,
                codigo,
                etiqueta,
                descripcion,
                obligatorio,
                valor_minimo,
                valor_maximo,
                valor_default,
                orden
            )
            VALUES
            (
                :version,
                :tipo,
                :origen,
                NULL,
                :codigo,
                :etiqueta,
                :descripcion,
                :obligatorio,
                NULL,
                NULL,
                NULL,
                1
            )
            '
        );

        $stmt->execute([
            'version' => $versionId,
            'tipo' => $typeId,
            'origen' => $originId,
            'codigo' => $code,
            'etiqueta' => ucfirst($code),
            'descripcion' => 'Variable avanzada QA',
            'obligatorio' =>
            $required ? 1 : 0,
        ]);

        return (int) $this->db()->lastInsertId();
    }


    private function setBirthDate(
        int $patientId,
        string $date
    ): void {
        $stmt = $this->db()->prepare(
            '
            UPDATE animales
            SET fecha_nacimiento = :fecha
            WHERE id = :id
            '
        );

        $stmt->execute([
            'fecha' => $date,
            'id' => $patientId,
        ]);
    }


    public function testBooleanVariableAcceptsZero(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'activo'
            );

        $this->createVariable(
            $formula['version_id'],
            'activo',
            'MANUAL',
            'BOOLEANO'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'activo' => 0,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertEquals(
            0.0,
            $result['result']
        );
    }


    public function testBooleanVariableAcceptsOne(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'activo'
            );

        $this->createVariable(
            $formula['version_id'],
            'activo',
            'MANUAL',
            'BOOLEANO'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'activo' => 1,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertEquals(
            1.0,
            $result['result']
        );
    }


    public function testBooleanVariableRejectsInvalidValue(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'activo'
            );

        $this->createVariable(
            $formula['version_id'],
            'activo',
            'MANUAL',
            'BOOLEANO'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'debe ser 0 o 1'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'activo' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testAgeInDaysVariableCanBeResolved(): void
    {
        $patientId =
            $this->createPatient();

        $this->setBirthDate(
            $patientId,
            date(
                'Y-m-d',
                strtotime('-10 days')
            )
        );

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'edad'
            );

        $this->createVariable(
            $formula['version_id'],
            'edad',
            'EDAD_DIAS'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThanOrEqual(
            9,
            $result['result']
        );

        $this->assertLessThanOrEqual(
            11,
            $result['result']
        );
    }


    public function testAgeInMonthsVariableCanBeResolved(): void
    {
        $patientId =
            $this->createPatient();

        $this->setBirthDate(
            $patientId,
            date(
                'Y-m-d',
                strtotime('-6 months')
            )
        );

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'edad'
            );

        $this->createVariable(
            $formula['version_id'],
            'edad',
            'EDAD_MESES'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThanOrEqual(
            5,
            $result['result']
        );

        $this->assertLessThanOrEqual(
            6,
            $result['result']
        );
    }


    public function testAgeInYearsVariableCanBeResolved(): void
    {
        $patientId =
            $this->createPatient();

        $this->setBirthDate(
            $patientId,
            date(
                'Y-m-d',
                strtotime('-3 years')
            )
        );

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'edad'
            );

        $this->createVariable(
            $formula['version_id'],
            'edad',
            'EDAD_ANIOS'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            3.0,
            $result['result']
        );
    }


    public function testAgeVariableRequiresBirthDate(): void
    {
        $patientId =
            $this->createPatient();

        $stmt = $this->db()->prepare(
            '
            UPDATE animales
            SET fecha_nacimiento = NULL
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $patientId,
        ]);

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'edad'
            );

        $this->createVariable(
            $formula['version_id'],
            'edad',
            'EDAD_ANIOS'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'no tiene fecha de nacimiento'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testWeightVariableRequiresRegisteredWeight(): void
    {
        $patientId =
            $this->createPatient();

        $stmt = $this->db()->prepare(
            '
            DELETE FROM animales_pesos
            WHERE animal_id = :animal
            '
        );

        $stmt->execute([
            'animal' => $patientId,
        ]);

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'peso'
            );

        $this->createVariable(
            $formula['version_id'],
            'peso',
            'PESO_ACTUAL'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'no tiene un peso registrado'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testFormulaCanLinkValidEventFromSamePatient(): void
    {
        $patientId =
            $this->createPatient();

        $eventId =
            (new ConsultationService())->create(
                [
                    'animal_id' =>
                    $patientId,

                    'motivo_consulta' =>
                    'Consulta para fórmula QA',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor'
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                $eventId,
                [
                    'factor' => 2,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT evento_clinico_id
            FROM formula_ejecuciones
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $result['execution_id'],
        ]);

        $this->assertSame(
            $eventId,
            (int) $stmt->fetchColumn()
        );
    }


    public function testFormulaCannotLinkEventFromDifferentPatientSameEnvironment(): void
    {
        $patientA =
            $this->createPatient(
                $this->vetEnvironment
            );

        $patientB =
            $this->createPatient(
                $this->vetEnvironment
            );

        $eventB =
            (new ConsultationService())->create(
                [
                    'animal_id' =>
                    $patientB,

                    'motivo_consulta' =>
                    'Evento paciente B',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor'
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'evento clínico no pertenece'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientA,
            $eventB,
            [
                'factor' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testPublishedModelFiltersByAllowedSpecies(): void
    {
        $patientId =
            $this->createPatient();

        $speciesId =
            (int) $this->scalar(
                '
                SELECT especie_id
                FROM animales
                WHERE id = :id
                ',
                [
                    'id' => $patientId,
                ]
            );

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                '1'
            );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO formula_especies
            (
                formula_id,
                especie_id
            )
            VALUES
            (
                :formula,
                :especie
            )
            '
        );

        $stmt->execute([
            'formula' =>
            $formula['formula_id'],
            'especie' => $speciesId,
        ]);

        $rows =
            (new Formula())
            ->publishedForEnvironment(
                $this->vetEnvironment,
                $speciesId
            );

        $found = false;

        foreach ($rows as $row) {
            if (
                (int) $row['formula_id']
                ===
                $formula['formula_id']
            ) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }


    public function testPublishedModelExcludesDisallowedSpecies(): void
    {
        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                '1'
            );

        $dogId =
            (int) $this->scalar(
                '
                SELECT id
                FROM especies
                WHERE codigo = "PERRO"
                LIMIT 1
                '
            );

        $catId =
            (int) $this->scalar(
                '
                SELECT id
                FROM especies
                WHERE codigo = "GATO"
                LIMIT 1
                '
            );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO formula_especies
            (
                formula_id,
                especie_id
            )
            VALUES
            (
                :formula,
                :especie
            )
            '
        );

        $stmt->execute([
            'formula' =>
            $formula['formula_id'],
            'especie' => $catId,
        ]);

        $rows =
            (new Formula())
            ->publishedForEnvironment(
                $this->vetEnvironment,
                $dogId
            );

        foreach ($rows as $row) {
            $this->assertNotSame(
                $formula['formula_id'],
                (int) $row['formula_id']
            );
        }

        $this->addToAssertionCount(1);
    }


    public function testPublishedModelCanFilterByDrug(): void
    {
        $drugId =
            $this->createDrug();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                '1'
            );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO farmaco_formulas
            (
                farmaco_id,
                formula_id,
                es_predeterminada
            )
            VALUES
            (
                :farmaco,
                :formula,
                1
            )
            '
        );

        $stmt->execute([
            'farmaco' => $drugId,
            'formula' =>
            $formula['formula_id'],
        ]);

        $rows =
            (new Formula())
            ->publishedForEnvironment(
                $this->vetEnvironment,
                null,
                $drugId
            );

        $found = false;

        foreach ($rows as $row) {
            if (
                (int) $row['formula_id']
                ===
                $formula['formula_id']
            ) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function testDecimalVariableRejectsNonNumericValue(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'valor numérico válido'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'cantidad' => 'abc',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testIntegerVariableRejectsNonNumericValue(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'ENTERO'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'valor numérico válido'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'cantidad' => 'abc',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
    public function testBooleanVariableRejectsTextValue(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'activo'
            );

        $this->createVariable(
            $formula['version_id'],
            'activo',
            'MANUAL',
            'BOOLEANO'
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'valor numérico válido'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'activo' => 'true',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
    public function testRequiredManualVariableRejectsWhitespaceOnly(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL',
            true
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Debes ingresar la variable'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'cantidad' => '     ',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
    public function testManualNumericStringZeroRemainsValid(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'cantidad' => '0',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            0.0,
            $result['result']
        );
    }
    public function testManualDecimalNumericStringIsAccepted(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad * 2'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL'
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'cantidad' => '12.5',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            25.0,
            $result['result']
        );
    }
    private function executionCountForVersion(
        int $versionId
    ): int {
        $stmt = $this->db()->prepare(
            '
        SELECT COUNT(*)
        FROM formula_ejecuciones
        WHERE formula_version_id = :version
        '
        );

        $stmt->execute([
            'version' => $versionId,
        ]);

        return (int) $stmt->fetchColumn();
    }
    private function executionValueCountForVersion(
        int $versionId
    ): int {
        $stmt = $this->db()->prepare(
            '
        SELECT COUNT(*)
        FROM formula_ejecucion_valores
        WHERE formula_version_id = :version
        '
        );

        $stmt->execute([
            'version' => $versionId,
        ]);

        return (int) $stmt->fetchColumn();
    }
    private function formulaCalculationAuditCount(): int
    {
        $stmt = $this->db()->prepare(
            '
        SELECT COUNT(*)
        FROM auditoria
        WHERE modulo = :modulo
          AND accion = :accion
          AND entorno_id = :entorno
        '
        );

        $stmt->execute([
            'modulo' => 'FORMULAS',
            'accion' => 'CALCULAR',
            'entorno' => $this->vetEnvironment,
        ]);

        return (int) $stmt->fetchColumn();
    }
    public function testInvalidManualValueDoesNotPersistExecution(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL'
        );

        $before =
            $this->executionCountForVersion(
                $formula['version_id']
            );

        try {
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'cantidad' => 'abc',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba RuntimeException.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotEmpty(
                $e->getMessage()
            );
        }

        $after =
            $this->executionCountForVersion(
                $formula['version_id']
            );

        $this->assertSame(
            $before,
            $after
        );
    }
    public function testInvalidManualValueDoesNotPersistExecutionValues(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL'
        );

        $before =
            $this->executionValueCountForVersion(
                $formula['version_id']
            );

        try {
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'cantidad' => 'abc',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba RuntimeException.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotEmpty(
                $e->getMessage()
            );
        }

        $after =
            $this->executionValueCountForVersion(
                $formula['version_id']
            );

        $this->assertSame(
            $before,
            $after
        );
    }
    public function testInvalidManualValueDoesNotWriteCalculationAudit(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL'
        );

        $before =
            $this->formulaCalculationAuditCount();

        try {
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'cantidad' => 'abc',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba RuntimeException.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotEmpty(
                $e->getMessage()
            );
        }

        $after =
            $this->formulaCalculationAuditCount();

        $this->assertSame(
            $before,
            $after
        );
    }
    public function testValidCalculationWorksAfterInvalidAttempt(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'cantidad * 2'
            );

        $this->createVariable(
            $formula['version_id'],
            'cantidad',
            'MANUAL',
            'DECIMAL'
        );

        try {
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'cantidad' => 'abc',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba RuntimeException.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotEmpty(
                $e->getMessage()
            );
        }

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'cantidad' => 5,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            10.0,
            $result['result']
        );

        $this->assertSame(
            1,
            $this->executionCountForVersion(
                $formula['version_id']
            )
        );
    }
}
