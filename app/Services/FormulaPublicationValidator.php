<?php

namespace App\Services;

use PDO;
use RuntimeException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class FormulaPublicationValidator
{
    public function validate(
        PDO $db,
        int $versionId
    ): void {

        /*
         * 1. Obtener expresión.
         */

        $stmt = $db->prepare(
            '
            SELECT expresion
            FROM formula_versiones
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $versionId
        ]);

        $expression = trim(
            (string)$stmt->fetchColumn()
        );

        if ($expression === '') {
            throw new RuntimeException(
                'La expresión está vacía.'
            );
        }

        /*
         * 2. Obtener variables.
         */

        $stmt = $db->prepare(
            '
            SELECT
                fv.*,
                tv.codigo AS tipo_codigo,
                ov.codigo AS origen_codigo

            FROM formula_variables fv

            LEFT JOIN tipos_variable_formula tv
                ON tv.id = fv.tipo_variable_id

            LEFT JOIN origenes_variable_formula ov
                ON ov.id = fv.origen_variable_id

            WHERE fv.formula_version_id = :version

            ORDER BY fv.orden, fv.id
            '
        );

        $stmt->execute([
            'version' => $versionId
        ]);

        $variables = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (!$variables) {
            throw new RuntimeException(
                'La fórmula no tiene variables.'
            );
        }

        /*
         * 3. Validar variables.
         */

        $codes = [];

        foreach ($variables as $variable) {

            $code = strtoupper(
                trim((string)$variable['codigo'])
            );

            if (
                !preg_match(
                    '/^[A-Z][A-Z0-9_]*$/',
                    $code
                )
            ) {
                throw new RuntimeException(
                    'Código inválido: ' . $code
                );
            }

            if (isset($codes[$code])) {
                throw new RuntimeException(
                    'Variable duplicada: ' . $code
                );
            }

            $codes[$code] = true;

            if (!$variable['tipo_codigo']) {
                throw new RuntimeException(
                    'Tipo inválido en ' . $code
                );
            }

            if (!$variable['origen_codigo']) {
                throw new RuntimeException(
                    'Origen inválido en ' . $code
                );
            }

            $min = $variable['valor_minimo'];
            $max = $variable['valor_maximo'];
            $default = $variable['valor_default'];

            if (
                $min !== null
                && $max !== null
                && (float)$min > (float)$max
            ) {
                throw new RuntimeException(
                    'Rango inválido en ' . $code
                );
            }

            if ($default !== null) {

                if (
                    $min !== null
                    && (float)$default < (float)$min
                ) {
                    throw new RuntimeException(
                        'Valor predeterminado inferior al mínimo en '
                            . $code
                    );
                }

                if (
                    $max !== null
                    && (float)$default > (float)$max
                ) {
                    throw new RuntimeException(
                        'Valor predeterminado superior al máximo en '
                            . $code
                    );
                }
            }
        }

        /*
         * 4. Validar sintaxis e identificadores
         * utilizando el motor matemático real.
         */

        $engine = new ExpressionLanguage();

        try {

            $engine->parse(
                $expression,
                array_keys($codes)
            );
        } catch (\Throwable $e) {

            throw new RuntimeException(
                'La expresión no es válida: '
                    . $e->getMessage()
            );
        }

        /*
         * 5. Comprobar variables no utilizadas.
         *
         * Esta comprobación es complementaria.
         * La sintaxis definitiva la valida
         * ExpressionLanguage.
         */

        foreach (array_keys($codes) as $code) {

            if (
                !preg_match(
                    '/(?<![A-Z0-9_])'
                        . preg_quote($code, '/')
                        . '(?![A-Z0-9_])/',
                    $expression
                )
            ) {

                throw new RuntimeException(
                    'La variable ' . $code
                        . ' no se utiliza en la expresión.'
                );
            }
        }
    }
}
