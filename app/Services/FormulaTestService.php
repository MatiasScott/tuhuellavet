<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

class FormulaTestService
{
    public function test(
        int $versionId,
        array $values,
        int $environmentId,
        ?float $expected = null,
        float $tolerance = 0.000001
    ): array {

        if ($versionId <= 0) {
            throw new RuntimeException(
                'Selecciona una versión válida.'
            );
        }

        if (
            !is_finite($tolerance)
            || $tolerance < 0
        ) {
            throw new RuntimeException(
                'La tolerancia no es válida.'
            );
        }

        $db = Database::connection();

        /*
         * Consultar la versión sin exigir
         * que esté publicada.
         *
         * Se mantiene la restricción
         * del entorno activo.
         */

        $stmt = $db->prepare(
            '
            SELECT
                fv.id,
                fv.formula_id,
                fv.numero_version,
                fv.expresion,
                f.nombre,
                efv.codigo AS estado,
                um.simbolo AS unidad

            FROM formula_versiones fv

            INNER JOIN formulas f
                ON f.id = fv.formula_id

            INNER JOIN formula_entornos fe
                ON fe.formula_id = f.id

            INNER JOIN estados_formula_version efv
                ON efv.id = fv.estado_id

            LEFT JOIN unidades_medida um
                ON um.id = f.unidad_resultado_id

            WHERE fv.id = :version

              AND fe.entorno_id = :entorno

              AND f.activo = 1

              AND f.deleted_at IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'version' => $versionId,
            'entorno' => $environmentId,
        ]);

        $version = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$version) {
            throw new RuntimeException(
                'La versión no existe en el entorno actual.'
            );
        }

        /*
         * Consultar las variables definidas.
         */

        $stmt = $db->prepare(
            '
            SELECT
                fv.id,
                fv.codigo,
                fv.etiqueta,
                fv.obligatorio,
                fv.valor_minimo,
                fv.valor_maximo,
                fv.valor_default,
                tv.codigo AS tipo

            FROM formula_variables fv

            INNER JOIN tipos_variable_formula tv
                ON tv.id = fv.tipo_variable_id

            WHERE fv.formula_version_id = :version

            ORDER BY fv.orden, fv.id
            '
        );

        $stmt->execute([
            'version' => $versionId,
        ]);

        $variables = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (!$variables) {
            throw new RuntimeException(
                'La versión no tiene variables definidas.'
            );
        }

        $resolved = [];

        foreach ($variables as $variable) {

            $code = $variable['codigo'];

            $value = array_key_exists(
                $code,
                $values
            )
                ? $values[$code]
                : null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if (
                ($value === null || $value === '')
                && $variable['valor_default'] !== null
            ) {
                $value = $variable['valor_default'];
            }

            if ($value === null || $value === '') {

                if ($variable['obligatorio']) {
                    throw new RuntimeException(
                        'Falta la variable: '
                            . $variable['etiqueta']
                    );
                }

                $value = 0;
            }

            if (!is_numeric($value)) {
                throw new RuntimeException(
                    $variable['etiqueta']
                        . ' debe ser numérica.'
                );
            }

            $value = (float) $value;

            if (!is_finite($value)) {
                throw new RuntimeException(
                    'Valor no válido para '
                        . $variable['etiqueta']
                );
            }

            switch ($variable['tipo']) {

                case 'ENTERO':

                    if (floor($value) !== $value) {
                        throw new RuntimeException(
                            $variable['etiqueta']
                                . ' debe ser entero.'
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
                    break;

                default:
                    throw new RuntimeException(
                        'Tipo de variable no soportado.'
                    );
            }

            if (
                $variable['valor_minimo'] !== null
                && $value < (float) $variable['valor_minimo']
            ) {
                throw new RuntimeException(
                    $variable['etiqueta']
                        . ' está por debajo del mínimo.'
                );
            }

            if (
                $variable['valor_maximo'] !== null
                && $value > (float) $variable['valor_maximo']
            ) {
                throw new RuntimeException(
                    $variable['etiqueta']
                        . ' supera el máximo.'
                );
            }

            $resolved[$code] = $value;
        }

        /*
         * Evaluar usando el mismo motor
         * matemático del sistema clínico.
         */

        try {

            $language = new ExpressionLanguage();

            $result = $language->evaluate(
                $version['expresion'],
                $resolved
            );
        } catch (Throwable $e) {

            throw new RuntimeException(
                'Error en la expresión: '
                    . $e->getMessage()
            );
        }

        if (!is_numeric($result)) {
            throw new RuntimeException(
                'La expresión no devolvió un número.'
            );
        }

        $result = (float) $result;

        if (!is_finite($result)) {
            throw new RuntimeException(
                'El resultado no es finito.'
            );
        }

        /*
         * Verificar que el resultado
         * pueda almacenarse en DECIMAL(18,6).
         */

        if (abs($result) >= 1000000000000) {
            throw new RuntimeException(
                'El resultado excede el rango permitido.'
            );
        }

        /*
         * Comparación matemática.
         */

        $difference = null;
        $matches = null;

        if ($expected !== null) {

            if (!is_finite($expected)) {
                throw new RuntimeException(
                    'El resultado esperado no es válido.'
                );
            }

            $difference = abs(
                $result - $expected
            );

            $matches = $difference <= $tolerance;
        }

        return [
            'formula' => $version['nombre'],

            'version' => $version['numero_version'],

            'state' => $version['estado'],

            'expression' => $version['expresion'],

            'variables' => $resolved,

            'result' => $result,

            'unit' => $version['unidad'],

            'expected' => $expected,

            'difference' => $difference,

            'matches' => $matches,

            'tolerance' => $tolerance,

            'simulation' => true,
        ];
    }
}
