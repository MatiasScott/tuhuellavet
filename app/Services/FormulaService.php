<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Formula;

use PDO;
use RuntimeException;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class FormulaService
{
    public function calculate(
        int $versionId,
        int $patientId,
        ?int $eventId,
        array $manualValues,
        int $environmentId,
        int $executedBy,
        bool $simulation = false,
        string $context = 'TRATAMIENTO'
    ): array {
        return Database::transaction(
            function (PDO $db) use (
                $versionId,
                $patientId,
                $eventId,
                $manualValues,
                $environmentId,
                $executedBy,
                $simulation,
                $context
            ) {
                $formulaModel
                    = new Formula();

                $version
                    = $formulaModel
                    ->version(
                        $versionId,
                        $environmentId
                    );

                if (!$version) {
                    throw new RuntimeException(
                        'La Fórmula no existe, no está publicada o no pertenece al entorno.'
                    );
                }

                $patient
                    = $this->getPatient(
                        $db,
                        $patientId,
                        $environmentId
                    );

                if ($eventId !== null) {
                    $this->validateClinicalEvent(
                        $db,
                        $eventId,
                        $patientId,
                        $environmentId
                    );
                }

                $this->validateSpecies(
                    $db,
                    (int)
                    $version['formula_id'],
                    (int)
                    $patient['especie_id']
                );

                $variables
                    = $formulaModel
                    ->variables(
                        $versionId
                    );

                $expressionValues = [];

                $savedValues = [];

                foreach (
                    $variables
                    as $variable
                ) {
                    $value = $this
                        ->resolveVariable(
                            $db,
                            $variable,
                            $patient,
                            $manualValues
                        );

                    $this
                        ->validateVariableValue(
                            $variable,
                            $value
                        );

                    /*
                     * Symfony ExpressionLanguage
                     * utilizará el código como nombre
                     * de variable.
                     */
                    $expressionValues[$variable['codigo']] = $value;

                    $savedValues[] = [
                        'variable_id'
                        => (int)
                        $variable['id'],

                        'value'
                        => $value,

                        'automatic'
                        => $variable['origen_codigo'] !== 'MANUAL',
                    ];
                }

                try {
                    $language
                        = new ExpressionLanguage();

                    $result = $language
                        ->evaluate(
                            $version['expresion'],
                            $expressionValues
                        );
                } catch (\Throwable $e) {
                    throw new RuntimeException(
                        'No fue posible calcular la Fórmula: '
                            . $e->getMessage()
                    );
                }

                if (
                    !is_numeric($result)
                ) {
                    throw new RuntimeException(
                        'La Fórmula no produjo un resultado numérico.'
                    );
                }

                $result
                    = (float) $result;

                if (
                    !is_finite($result)
                ) {
                    throw new RuntimeException(
                        'El resultado de la Fórmula no es válido.'
                    );
                }

                $allowedContexts = ['TRATAMIENTO', 'FLUIDOTERAPIA', 'ANESTESIA', 'ACADEMICO', 'SIMULACION'];
                $context = strtoupper(trim($context));
                if (!in_array($context, $allowedContexts, true)) {
                    $context = 'TRATAMIENTO';
                }
                if ($simulation) {
                    $context = 'SIMULACION';
                }

                /*
                 * Guardamos TODA ejecución.
                 * Incluso simulaciones.
                 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO formula_ejecuciones
                    (
                        formula_version_id,
                        animal_id,
                        evento_clinico_id,
                        ejecutado_por,
                        contexto,
                        resultado,
                        unidad_resultado_id,
                        es_simulacion,
                        observaciones
                    )
                    VALUES
                    (
                        :version,
                        :animal,
                        :evento,
                        :usuario,
                        :contexto,
                        :resultado,
                        :unidad,
                        :simulacion,
                        NULL
                    )
                    '
                );

                $stmt->execute([
                    'version'
                    => $versionId,

                    'animal'
                    => $patientId,

                    'evento'
                    => $eventId,

                    'usuario'
                    => $executedBy,

                    'contexto'
                    => $context,

                    'resultado'
                    => $result,

                    'unidad'
                    => $version['unidad_resultado_id'],

                    'simulacion'
                    => $simulation
                        ? 1
                        : 0,
                ]);

                $executionId
                    = (int)
                    $db
                        ->lastInsertId();

                foreach (
                    $savedValues
                    as $saved
                ) {
                    $stmt = $db->prepare(
                        '
                        INSERT INTO
                            formula_ejecucion_valores
                        (
                            formula_ejecucion_id,
                            formula_variable_id,
                            formula_version_id,
                            valor,
                            fue_automatico
                        )
                        VALUES
                        (
                            :ejecucion,
                            :variable,
                            :version,
                            :valor,
                            :automatico
                        )
                        '
                    );

                    $stmt->execute([
                        'ejecucion'
                        => $executionId,

                        'variable'
                        => $saved['variable_id'],

                        'version'
                        => $versionId,

                        'valor'
                        => $saved['value'],

                        'automatico'
                        => $saved['automatic']
                            ? 1
                            : 0,
                    ]);
                }

                (new AuditService())
                    ->log(
                        $executedBy,
                        $environmentId,
                        'FORMULAS',
                        'CALCULAR',
                        'formula_ejecuciones',
                        $executionId,
                        null,
                        [
                            'formula_version_id'
                            => $versionId,

                            'animal_id'
                            => $patientId,

                            'resultado'
                            => $result,

                            'simulacion'
                            => $simulation,
                        ]
                    );

                return [
                    'execution_id'
                    => $executionId,

                    'result'
                    => $result,

                    'unit'
                    => $version['unidad_resultado_simbolo']
                        ?: $version['unidad_resultado'],

                    'formula'
                    => $version['formula_nombre'],
                ];
            }
        );
    }


    private function resolveVariable(
        PDO $db,
        array $variable,
        array $patient,
        array $manualValues
    ): float {
        return match ($variable['origen_codigo']) {
            'PESO_ACTUAL'
            => $this
                ->currentWeight(
                    $db,
                    (int)
                    $patient['id']
                ),

            'EDAD_DIAS'
            => $this
                ->ageInDays(
                    $patient['fecha_nacimiento']
                ),

            'EDAD_MESES'
            => $this
                ->ageInMonths(
                    $patient['fecha_nacimiento']
                ),

            'EDAD_ANIOS'
            => $this
                ->ageInYears(
                    $patient['fecha_nacimiento']
                ),

            'MANUAL'
            => $this
                ->manualValue(
                    $variable,
                    $manualValues
                ),

            default
            => throw new RuntimeException(
                'Origen de variable no soportado: '
                    . $variable['origen_codigo']
            ),
        };
    }


    private function manualValue(
        array $variable,
        array $manualValues
    ): float {
        $code = (string) $variable['codigo'];

        /*
     * array_key_exists() es intencional:
     * 0 y "0" son valores válidos y no deben
     * confundirse con una variable ausente.
     */
        $value = array_key_exists(
            $code,
            $manualValues
        )
            ? $manualValues[$code]
            : null;

        /*
     * Normalizamos cadenas únicamente para
     * detectar correctamente valores vacíos.
     */
        if (is_string($value)) {
            $value = trim($value);
        }

        /*
     * Si no se proporcionó valor, utilizamos
     * el valor por defecto cuando exista.
     */
        if (
            ($value === null || $value === '')
            && $variable['valor_default'] !== null
        ) {
            $value = $variable['valor_default'];

            if (is_string($value)) {
                $value = trim($value);
            }
        }

        /*
     * Una variable obligatoria no puede quedar
     * vacía después de aplicar el default.
     */
        if (
            ($value === null || $value === '')
            && !empty($variable['obligatorio'])
        ) {
            throw new RuntimeException(
                'Debes ingresar la variable: '
                    . $variable['etiqueta']
            );
        }

        /*
     * Conservamos el comportamiento histórico
     * de variables opcionales sin valor/default:
     * se resuelven como 0.
     *
     * Lo importante es que un valor REALMENTE
     * ingresado pero inválido nunca se convierta
     * silenciosamente en cero.
     */
        if ($value === null || $value === '') {
            return 0.0;
        }

        /*
     * Validar ANTES del cast.
     */
        if (!is_numeric($value)) {
            throw new RuntimeException(
                $variable['etiqueta']
                    . ' debe contener un valor numérico válido.'
            );
        }

        $numericValue = (float) $value;

        /*
     * Evita INF, -INF y cualquier número
     * no finito.
     */
        if (!is_finite($numericValue)) {
            throw new RuntimeException(
                $variable['etiqueta']
                    . ' debe contener un valor numérico finito.'
            );
        }

        return $numericValue;
    }


    private function currentWeight(
        PDO $db,
        int $patientId
    ): float {
        $stmt = $db->prepare(
            '
            SELECT peso_kg

            FROM animales_pesos

            WHERE animal_id = :animal

            ORDER BY
                fecha_registro DESC,
                id DESC

            LIMIT 1
            '
        );

        $stmt->execute([
            'animal'
            => $patientId,
        ]);

        $weight
            = $stmt->fetchColumn();

        if (
            $weight === false
            || $weight === null
        ) {
            throw new RuntimeException(
                'El paciente no tiene un peso registrado.'
            );
        }

        return (float)
        $weight;
    }


    private function ageInDays(
        ?string $birthDate
    ): float {
        if (!$birthDate) {
            throw new RuntimeException(
                'El paciente no tiene fecha de nacimiento.'
            );
        }

        $birth
            = new \DateTimeImmutable(
                $birthDate
            );

        $today
            = new \DateTimeImmutable();

        return (float)
        $birth
            ->diff($today)
            ->days;
    }


    private function ageInMonths(
        ?string $birthDate
    ): float {
        if (!$birthDate) {
            throw new RuntimeException(
                'El paciente no tiene fecha de nacimiento.'
            );
        }

        $birth
            = new \DateTimeImmutable(
                $birthDate
            );

        $today
            = new \DateTimeImmutable();

        $diff
            = $birth->diff(
                $today
            );

        return (float) (
            $diff->y * 12
            + $diff->m
        );
    }


    private function ageInYears(
        ?string $birthDate
    ): float {
        if (!$birthDate) {
            throw new RuntimeException(
                'El paciente no tiene fecha de nacimiento.'
            );
        }

        return (float)
        (
            new \DateTimeImmutable(
                $birthDate
            )
        )
            ->diff(
                new \DateTimeImmutable()
            )
            ->y;
    }


    private function validateVariableValue(
        array $variable,
        float $value
    ): void {
        $type =
            strtoupper(
                (string) (
                    $variable['tipo_codigo']
                    ?? ''
                )
            );

        switch ($type) {
            case 'ENTERO':
                if (floor($value) !== $value) {
                    throw new RuntimeException(
                        $variable['etiqueta']
                            . ' debe ser un número entero.'
                    );
                }
                break;

            case 'BOOLEANO':
                if (
                    $value !== 0.0
                    && $value !== 1.0
                ) {
                    throw new RuntimeException(
                        $variable['etiqueta']
                            . ' debe ser 0 o 1.'
                    );
                }
                break;

            case 'DECIMAL':
                /*
             * Cualquier valor numérico finito
             * es válido como decimal.
             */
                break;

            default:
                throw new RuntimeException(
                    'Tipo de variable no soportado: '
                        . $type
                );
        }

        if (
            $variable['valor_minimo'] !== null
            && $value
            <
            (float) $variable['valor_minimo']
        ) {
            throw new RuntimeException(
                $variable['etiqueta']
                    . ' no puede ser menor a '
                    . $variable['valor_minimo']
            );
        }

        if (
            $variable['valor_maximo'] !== null
            && $value
            >
            (float) $variable['valor_maximo']
        ) {
            throw new RuntimeException(
                $variable['etiqueta']
                    . ' no puede ser mayor a '
                    . $variable['valor_maximo']
            );
        }
    }


    private function getPatient(
        PDO $db,
        int $patientId,
        int $environmentId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                id,
                especie_id,
                fecha_nacimiento

            FROM animales

            WHERE id = :animal

              AND entorno_id
                = :entorno

              AND activo = 1

              AND deleted_at
                IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'animal'
            => $patientId,

            'entorno'
            => $environmentId,
        ]);

        $patient
            = $stmt->fetch();

        if (!$patient) {
            throw new RuntimeException(
                'Paciente no encontrado.'
            );
        }

        return $patient;
    }


    private function validateSpecies(
        PDO $db,
        int $formulaId,
        int $speciesId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM formula_especies

            WHERE formula_id = :formula
            '
        );

        $stmt->execute([
            'formula'
            => $formulaId,
        ]);

        $restricted
            = (int)
            $stmt->fetchColumn();

        if ($restricted === 0) {
            return;
        }

        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM formula_especies

            WHERE formula_id = :formula
              AND especie_id = :especie
            '
        );

        $stmt->execute([
            'formula'
            => $formulaId,

            'especie'
            => $speciesId,
        ]);

        if (
            (int)
            $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'Esta Fórmula no está habilitada para la especie del paciente.'
            );
        }
    }

    private function validateClinicalEvent(
        PDO $db,
        int $eventId,
        int $patientId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
        SELECT
            ec.id

        FROM eventos_clinicos ec

        INNER JOIN animales a
            ON a.id = ec.animal_id

        WHERE ec.id = :evento
          AND ec.animal_id = :animal
          AND a.entorno_id = :entorno
          AND a.activo = 1
          AND a.deleted_at IS NULL

        LIMIT 1
        '
        );

        $stmt->execute([
            'evento' => $eventId,
            'animal' => $patientId,
            'entorno' => $environmentId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'El evento clínico no pertenece al paciente o al entorno actual.'
            );
        }
    }
}
