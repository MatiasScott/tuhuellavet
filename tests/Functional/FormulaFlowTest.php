<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Models\Formula;
use App\Services\ConsultationService;
use App\Services\FormulaService;
use RuntimeException;

class FormulaFlowTest extends ClinicalTestCase
{
    /**
     * Obtiene el ID de un catálogo utilizado
     * específicamente por el módulo de fórmulas.
     */
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


    /**
     * Crea una fórmula de prueba y su primera versión.
     */
    private function createFormula(
        int $environmentId,
        string $expression = 'peso * factor',
        string $state = 'PUBLICADA',
        int $versionNumber = 1
    ): array {
        $categoryId = $this->formulaCatalogId(
            'categorias_formula',
            'MEDICAMENTO'
        );

        $stateId = $this->formulaCatalogId(
            'estados_formula_version',
            $state
        );

        $code =
            'QA_FORMULA_' .
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
            'nombre' => 'Fórmula QA',
            'descripcion' => 'Fixture temporal de PHPUnit',
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
                :numero,
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
            'numero' => $versionNumber,
            'expresion' => $expression,
            'notas' => 'Versión generada por QA',
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


    /**
     * Crea una versión adicional.
     */
    private function createVersion(
        int $formulaId,
        int $number,
        string $expression,
        string $state = 'PUBLICADA'
    ): int {
        $stateId = $this->formulaCatalogId(
            'estados_formula_version',
            $state
        );

        $published =
            $state === 'PUBLICADA';

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
                :numero,
                :expresion,
                :notas,
                :estado,
                :creada_por,
                :publicada_por,
                :publicada_at
            )
            '
        );

        $stmt->execute([
            'formula' => $formulaId,
            'numero' => $number,
            'expresion' => $expression,
            'notas' => 'Versión QA ' . $number,
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

        return (int) $this->db()->lastInsertId();
    }


    /**
     * Crea una variable para una versión.
     */
    private function createVariable(
        int $versionId,
        string $code,
        string $origin,
        string $type = 'DECIMAL',
        bool $required = true,
        ?float $minimum = null,
        ?float $maximum = null,
        ?float $default = null,
        int $order = 0
    ): int {
        $typeId = $this->formulaCatalogId(
            'tipos_variable_formula',
            $type
        );

        $originId = $this->formulaCatalogId(
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
                :minimo,
                :maximo,
                :default_value,
                :orden
            )
            '
        );

        $stmt->execute([
            'version' => $versionId,
            'tipo' => $typeId,
            'origen' => $originId,
            'codigo' => $code,
            'etiqueta' => ucfirst($code),
            'descripcion' => 'Variable QA',
            'obligatorio' =>
                $required ? 1 : 0,
            'minimo' => $minimum,
            'maximo' => $maximum,
            'default_value' => $default,
            'orden' => $order,
        ]);

        return (int) $this->db()->lastInsertId();
    }


    /**
     * Restringe una fórmula a una especie.
     */
    private function restrictFormulaToSpecies(
        int $formulaId,
        int $speciesId
    ): void {
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
            'formula' => $formulaId,
            'especie' => $speciesId,
        ]);
    }


    public function testPublishedFormulaCanBeCalculated(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment,
                10.0
            );

        $formula =
            $this->createFormula(
                $this->vetEnvironment
            );

        $this->createVariable(
            $formula['version_id'],
            'peso',
            'PESO_ACTUAL',
            order: 1
        );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL',
            minimum: 0.1,
            order: 2
        );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'factor' => 2.5,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $result['execution_id']
        );

        $this->assertEquals(
            25.0,
            $result['result']
        );

        $this->assertSame(
            'Fórmula QA',
            $result['formula']
        );
    }


    public function testExecutionAndVariableValuesArePersisted(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment,
                12.0
            );

        $formula =
            $this->createFormula(
                $this->vetEnvironment
            );

        $weightVariableId =
            $this->createVariable(
                $formula['version_id'],
                'peso',
                'PESO_ACTUAL',
                order: 1
            );

        $factorVariableId =
            $this->createVariable(
                $formula['version_id'],
                'factor',
                'MANUAL',
                order: 2
            );

        $result =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'factor' => 3,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM formula_ejecuciones
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $result['execution_id'],
        ]);

        $execution =
            $stmt->fetch();

        $this->assertNotFalse(
            $execution
        );

        $this->assertSame(
            $formula['version_id'],
            (int) $execution[
                'formula_version_id'
            ]
        );

        $this->assertSame(
            $patientId,
            (int) $execution[
                'animal_id'
            ]
        );

        $this->assertEquals(
            36.0,
            (float) $execution[
                'resultado'
            ]
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                formula_variable_id,
                valor,
                fue_automatico

            FROM formula_ejecucion_valores

            WHERE formula_ejecucion_id
                = :ejecucion

            ORDER BY formula_variable_id
            '
        );

        $stmt->execute([
            'ejecucion' =>
                $result['execution_id'],
        ]);

        $values =
            $stmt->fetchAll();

        $this->assertCount(
            2,
            $values
        );

        $indexed = [];

        foreach ($values as $value) {
            $indexed[
                (int) $value[
                    'formula_variable_id'
                ]
            ] = $value;
        }

        $this->assertEquals(
            12.0,
            (float) $indexed[
                $weightVariableId
            ]['valor']
        );

        $this->assertSame(
            1,
            (int) $indexed[
                $weightVariableId
            ]['fue_automatico']
        );

        $this->assertEquals(
            3.0,
            (float) $indexed[
                $factorVariableId
            ]['valor']
        );

        $this->assertSame(
            0,
            (int) $indexed[
                $factorVariableId
            ]['fue_automatico']
        );
    }


    public function testDefaultManualValueIsUsed(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor * 2'
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL',
            default: 4.5
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

        $this->assertEquals(
            9.0,
            $result['result']
        );
    }


    public function testRequiredManualVariableIsRejectedWhenMissing(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor * 2'
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL',
            required: true
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Debes ingresar la variable: Factor'
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


    public function testMinimumValueIsEnforced(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor'
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL',
            minimum: 1.0
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Factor no puede ser menor a 1'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'factor' => 0.5,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testMaximumValueIsEnforced(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor'
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL',
            maximum: 10.0
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Factor no puede ser mayor a 10'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'factor' => 11,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testDraftVersionCannotBeCalculated(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor * 2',
                'BORRADOR'
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
            'no existe, no está publicada'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'factor' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testFormulaCannotBeCalculatedFromAnotherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->farmEnvironment
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

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'factor' => 1,
            ],
            $this->farmEnvironment,
            $this->superAdminId
        );
    }


    public function testFormulaSpeciesRestrictionIsEnforced(): void
    {
        $patientId =
            $this->createPatient();

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

        $stmt = $this->db()->prepare(
            '
            SELECT id
            FROM especies
            WHERE codigo = :codigo
            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => 'GATO',
        ]);

        $catId =
            (int) $stmt->fetchColumn();

        $this->assertGreaterThan(
            0,
            $catId,
            'Debe existir la especie GATO.'
        );

        $this->restrictFormulaToSpecies(
            $formula['formula_id'],
            $catId
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'no está habilitada para la especie'
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'factor' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testSimulationIsPersistedAsSimulation(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor * 2'
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
                null,
                [
                    'factor' => 3,
                ],
                $this->vetEnvironment,
                $this->superAdminId,
                true,
                'ACADEMICO'
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                contexto,
                es_simulacion

            FROM formula_ejecuciones

            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $result[
                'execution_id'
            ],
        ]);

        $execution =
            $stmt->fetch();

        $this->assertNotFalse(
            $execution
        );

        $this->assertSame(
            'SIMULACION',
            $execution[
                'contexto'
            ]
        );

        $this->assertSame(
            1,
            (int) $execution[
                'es_simulacion'
            ]
        );
    }


    public function testInvalidContextFallsBackToTreatment(): void
    {
        $patientId =
            $this->createPatient();

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
                null,
                [
                    'factor' => 2,
                ],
                $this->vetEnvironment,
                $this->superAdminId,
                false,
                'CONTEXTO_INVENTADO'
            );

        $context =
            $this->scalar(
                '
                SELECT contexto
                FROM formula_ejecuciones
                WHERE id = :id
                ',
                [
                    'id' =>
                        $result[
                            'execution_id'
                        ],
                ]
            );

        $this->assertSame(
            'TRATAMIENTO',
            $context
        );
    }


    public function testCalculationWritesAudit(): void
    {
        $patientId =
            $this->createPatient();

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
                null,
                [
                    'factor' => 2,
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
                  AND modulo = "FORMULAS"
                  AND accion = "CALCULAR"
                  AND tabla_afectada =
                      "formula_ejecuciones"
                  AND registro_id = :registro
                ',
                [
                    'usuario' =>
                        $this->superAdminId,

                    'entorno' =>
                        $this->vetEnvironment,

                    'registro' =>
                        $result[
                            'execution_id'
                        ],
                ]
            );

        $this->assertSame(
            1,
            $count
        );
    }


    public function testLatestPublishedVersionIsReturnedByModel(): void
    {
        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                '1',
                'PUBLICADA',
                1
            );

        $version2 =
            $this->createVersion(
                $formula['formula_id'],
                2,
                '2',
                'PUBLICADA'
            );

        $this->createVersion(
            $formula['formula_id'],
            3,
            '3',
            'BORRADOR'
        );

        $rows =
            (new Formula())
                ->publishedForEnvironment(
                    $this->vetEnvironment
                );

        $found = null;

        foreach ($rows as $row) {
            if (
                (int) $row[
                    'formula_id'
                ]
                ===
                $formula[
                    'formula_id'
                ]
            ) {
                $found = $row;
                break;
            }
        }

        $this->assertNotNull(
            $found
        );

        $this->assertSame(
            $version2,
            (int) $found[
                'formula_version_id'
            ]
        );

        $this->assertSame(
            2,
            (int) $found[
                'numero_version'
            ]
        );
    }


    public function testOldExecutionKeepsItsOriginalVersionAndResult(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor * 2',
                'PUBLICADA',
                1
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL'
        );

        $execution =
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'factor' => 5,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $version2 =
            $this->createVersion(
                $formula['formula_id'],
                2,
                'factor * 100',
                'PUBLICADA'
            );

        $this->createVariable(
            $version2,
            'factor',
            'MANUAL'
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                formula_version_id,
                resultado

            FROM formula_ejecuciones

            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' =>
                $execution[
                    'execution_id'
                ],
        ]);

        $saved =
            $stmt->fetch();

        $this->assertNotFalse(
            $saved
        );

        $this->assertSame(
            $formula['version_id'],
            (int) $saved[
                'formula_version_id'
            ]
        );

        $this->assertEquals(
            10.0,
            (float) $saved[
                'resultado'
            ]
        );
    }


    public function testFailedCalculationDoesNotPersistExecution(): void
    {
        $patientId =
            $this->createPatient();

        $formula =
            $this->createFormula(
                $this->vetEnvironment,
                'factor / divisor'
            );

        $this->createVariable(
            $formula['version_id'],
            'factor',
            'MANUAL',
            order: 1
        );

        $this->createVariable(
            $formula['version_id'],
            'divisor',
            'MANUAL',
            order: 2
        );

        $before =
            (int) $this->scalar(
                '
                SELECT COUNT(*)

                FROM formula_ejecuciones

                WHERE formula_version_id
                    = :version
                ',
                [
                    'version' =>
                        $formula[
                            'version_id'
                        ],
                ]
            );

        try {
            (new FormulaService())->calculate(
                $formula['version_id'],
                $patientId,
                null,
                [
                    'factor' => 10,
                    'divisor' => 0,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'Se esperaba error al dividir por cero.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'No fue posible calcular la Fórmula',
                $exception->getMessage()
            );
        }

        $after =
            (int) $this->scalar(
                '
                SELECT COUNT(*)

                FROM formula_ejecuciones

                WHERE formula_version_id
                    = :version
                ',
                [
                    'version' =>
                        $formula[
                            'version_id'
                        ],
                ]
            );

        $this->assertSame(
            $before,
            $after
        );
    }


    /**
     * Seguridad:
     *
     * Un evento clínico no debería poder
     * vincularse a una ejecución de otro
     * paciente o entorno.
     */
    public function testFormulaCannotLinkEventFromAnotherEnvironment(): void
    {
        $vetPatient =
            $this->createPatient(
                $this->vetEnvironment
            );

        $farmPatient =
            $this->createPatient(
                $this->farmEnvironment
            );

        $farmEvent =
            (new ConsultationService())
                ->create(
                    [
                        'animal_id' =>
                            $farmPatient,

                        'motivo_consulta' =>
                            'Evento Hacienda QA',
                    ],
                    $this->farmEnvironment,
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

        (new FormulaService())->calculate(
            $formula['version_id'],
            $vetPatient,
            $farmEvent,
            [
                'factor' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    /**
     * Integridad:
     *
     * Una variable declarada ENTERO
     * no debería aceptar decimales.
     */
    public function testIntegerVariableRejectsDecimalValue(): void
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
            'ENTERO'
        );

        $this->expectException(
            RuntimeException::class
        );

        (new FormulaService())->calculate(
            $formula['version_id'],
            $patientId,
            null,
            [
                'cantidad' => 2.5,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
}