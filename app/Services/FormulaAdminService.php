<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class FormulaAdminService
{
    public function create(array $d, int $env, int $by): int
    {
        return Database::transaction(function (PDO $db) use ($d, $env, $by) {
            $code = strtoupper(trim((string)($d['codigo'] ?? '')));
            $name = trim((string)($d['nombre'] ?? ''));
            if (!$code || !$name)
                throw new RuntimeException('Código y nombre son obligatorios.');
            $cat = (int)($d['categoria_formula_id'] ?? 0);
            if (!$cat)
                throw new RuntimeException('Selecciona categoría.');
            $s = $db->prepare(
                '
                INSERT INTO formulas(categoria_formula_id,codigo,nombre,descripcion,unidad_resultado_id,creada_por,activo) 
                VALUES(:c,:co,:n,:d,:u,:by,1)'
            );
            $s->execute([
                'c' => $cat,
                'co' => $code,
                'n' => $name,
                'd' => trim((string)($d['descripcion'] ?? '')) ?: null,
                'u' => !empty($d['unidad_resultado_id']) ? (int)$d['unidad_resultado_id'] : null,
                'by' => $by
            ]);
            $id = (int)$db->lastInsertId();
            $db->prepare(
                '
                INSERT INTO formula_entornos(formula_id,entorno_id) 
                VALUES(:f,:e)'
            )->execute(['f' => $id, 'e' => $env]);
            $this->newVersion($db, $id, $d, $by);
            if (!empty($d['especie_ids']) && is_array($d['especie_ids']))
                foreach ($d['especie_ids'] as $es) {
                    $db->prepare('INSERT IGNORE INTO formula_especies(formula_id,especie_id) VALUES(:f,:e)')->execute(['f' => $id, 'e' => (int)$es]);
                }
            (new AuditService())->log($by, $env, 'FORMULAS', 'CREAR', 'formulas', $id);
            return $id;
        });
    }
    public function addVersion(
        int $formula,
        array $d,
        int $env,
        int $by
    ): int {

        return Database::transaction(
            function (PDO $db) use (
                $formula,
                $d,
                $env,
                $by
            ) {

                /*
             * 1. Bloquear la fórmula durante
             * la creación de la versión.
             *
             * Esto evita que dos solicitudes
             * generen simultáneamente el
             * mismo número de versión.
             */

                $stmt = $db->prepare(
                    '
                SELECT f.id

                FROM formulas f

                INNER JOIN formula_entornos fe
                    ON fe.formula_id = f.id

                WHERE f.id = :formula

                  AND fe.entorno_id = :entorno

                  AND f.activo = 1

                  AND f.deleted_at IS NULL

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'formula' => $formula,
                    'entorno' => $env,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Fórmula no disponible en este entorno.'
                    );
                }

                /*
             * 2. Obtener la última versión.
             */

                $stmt = $db->prepare(
                    '
                SELECT
                    id,
                    numero_version,
                    expresion

                FROM formula_versiones

                WHERE formula_id = :formula

                ORDER BY numero_version DESC

                LIMIT 1
                '
                );

                $stmt->execute([
                    'formula' => $formula,
                ]);

                $previous = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

                if (!$previous) {
                    throw new RuntimeException(
                        'La fórmula no tiene una versión anterior.'
                    );
                }

                /*
             * 3. Si no llega una expresión,
             * copiar la anterior.
             */

                $expression = mb_strtoupper(
                    trim((string)($d['expresion'] ?? '')),
                    'UTF-8'
                );

                if ($expression === '') {
                    $expression =
                        $previous['expresion'];
                }

                $d['expresion'] = $expression;

                /*
             * 4. Crear la nueva versión.
             *
             * No aceptamos variables enviadas
             * desde este formulario: siempre
             * se copian las de la versión anterior.
             * Su edición se realizará después
             * en el borrador.
             */

                $d['variables'] = [];

                $versionId = $this->newVersion(
                    $db,
                    $formula,
                    $d,
                    $by
                );

                /*
             * 5. Copiar las variables.
             */

                $stmt = $db->prepare(
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

                SELECT
                    :nueva_version,
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

                FROM formula_variables

                WHERE formula_version_id =
                    :version_anterior
                '
                );

                $stmt->execute([
                    'nueva_version' => $versionId,

                    'version_anterior'
                    => (int)$previous['id'],
                ]);

                /*
             * 6. Auditoría.
             */

                (new AuditService())->log(
                    $by,
                    $env,
                    'FORMULAS',
                    'NUEVA_VERSION',
                    'formula_versiones',
                    $versionId,
                    null,
                    [
                        'formula_id' => $formula,

                        'version_origen_id'
                        => (int)$previous['id'],

                        'numero_version'
                        => (int)$previous['numero_version'] + 1,
                    ]
                );

                return $versionId;
            }
        );
    }
    public function publish(
        int $version,
        int $env,
        int $by
    ): void {

        Database::transaction(
            function (PDO $db) use (
                $version,
                $env,
                $by
            ) {

                /*
             * 1. Obtener y bloquear la versión.
             */

                $stmt = $db->prepare(
                    '
                SELECT
                    fv.id,
                    fv.formula_id,
                    fv.numero_version,
                    fv.expresion,
                    efv.codigo AS estado

                FROM formula_versiones fv

                INNER JOIN formulas f
                    ON f.id = fv.formula_id

                INNER JOIN formula_entornos fe
                    ON fe.formula_id = f.id

                INNER JOIN estados_formula_version efv
                    ON efv.id = fv.estado_id

                WHERE fv.id = :version
                  AND fe.entorno_id = :entorno
                  AND f.activo = 1
                  AND f.deleted_at IS NULL

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'version' => $version,
                    'entorno' => $env
                ]);

                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    throw new RuntimeException(
                        'Versión no encontrada.'
                    );
                }

                /*
             * 2. Validar estado.
             */

                if ($row['estado'] !== 'BORRADOR') {
                    throw new RuntimeException(
                        'Solo puedes publicar versiones en borrador.'
                    );
                }

                /*
             * 3. Validar fórmula completa.
             */

                (new FormulaPublicationValidator())
                    ->validate(
                        $db,
                        $version
                    );

                /*
             * 4. Obtener estado PUBLICADA.
             */

                $stmt = $db->prepare(
                    '
                SELECT id
                FROM estados_formula_version
                WHERE codigo = :codigo
                LIMIT 1
                '
                );

                $stmt->execute([
                    'codigo' => 'PUBLICADA'
                ]);

                $publishedStateId =
                    (int)$stmt->fetchColumn();

                if (!$publishedStateId) {
                    throw new RuntimeException(
                        'No existe el estado PUBLICADA.'
                    );
                }

                /*
             * 5. Publicar.
             */

                $stmt = $db->prepare(
                    '
                UPDATE formula_versiones

                SET
                    estado_id = :estado,
                    publicada_por = :usuario,
                    publicada_at = NOW()

                WHERE id = :version
                '
                );

                $stmt->execute([
                    'estado' => $publishedStateId,
                    'usuario' => $by,
                    'version' => $version
                ]);

                /*
             * 6. Auditoría.
             */

                (new AuditService())->log(
                    $by,
                    $env,
                    'FORMULAS',
                    'PUBLICAR',
                    'formula_versiones',
                    $version,
                    [
                        'estado' => 'BORRADOR'
                    ],
                    [
                        'estado' => 'PUBLICADA',
                        'formula_id' =>
                        (int)$row['formula_id'],
                        'numero_version' =>
                        (int)$row['numero_version']
                    ]
                );
            }
        );
    }
    private function newVersion(PDO $db, int $formula, array $d, int $by): int
    {
        $expr = mb_strtoupper(
            trim((string)($d['expresion'] ?? '')),
            'UTF-8'
        );
        if ($expr === '')
            throw new RuntimeException('La expresión es obligatoria.');
        $draft = (int)$db->query(
            "SELECT id FROM estados_formula_version 
            WHERE codigo='BORRADOR'"
        )->fetchColumn();
        $s = $db->prepare(
            'SELECT COALESCE(MAX(numero_version),0)+1 
            FROM formula_versiones 
            WHERE formula_id=:f'
        );
        $s->execute(['f' => $formula]);
        $n = (int)$s->fetchColumn();
        $db->prepare(
            'INSERT INTO formula_versiones(formula_id,numero_version,expresion,notas_version,estado_id,creada_por) VALUES(:f,:n,:e,:no,:s,:u)'
        )
            ->execute(['f' => $formula, 'n' => $n, 'e' => $expr, 'no'
            => trim((string)($d['notas_version'] ?? '')) ?: null, 's'
            => $draft, 'u' => $by]);
        $vid = (int)$db->lastInsertId();
        $vars = $d['variables'] ?? [];
        if (is_array($vars)) foreach ($vars as $v) {
            if (empty($v['codigo']) || empty($v['etiqueta'])) continue;
            $db->prepare(
                'INSERT INTO formula_variables(formula_version_id,tipo_variable_id,origen_variable_id,unidad_medida_id,codigo,etiqueta,descripcion,obligatorio,valor_minimo,valor_maximo,valor_default,orden) 
                    VALUES(:fv,:t,:o,:u,:c,:e,:d,:ob,:min,:max,:def,:ord)'
            )
                ->execute([
                    'fv' => $vid,
                    't' => (int)($v['tipo_variable_id'] ?? 1),
                    'o' => (int)($v['origen_variable_id'] ?? 1),
                    'u' => !empty($v['unidad_medida_id']) ? (int)$v['unidad_medida_id'] : null,
                    'c' => strtoupper(trim($v['codigo'])),
                    'e' => trim($v['etiqueta']),
                    'd' => trim((string)($v['descripcion'] ?? '')) ?: null,
                    'ob' => !empty($v['obligatorio']) ? 1 : 0,
                    'min' => ($v['valor_minimo'] ?? '') !== '' ? (float)$v['valor_minimo'] : null,
                    'max' => ($v['valor_maximo'] ?? '') !== '' ? (float)$v['valor_maximo'] : null,
                    'def' => ($v['valor_default'] ?? '') !== '' ? (float)$v['valor_default'] : null,
                    'ord' => (int)($v['orden'] ?? 0)
                ]);
        }
        return $vid;
    }

    public function updateDraft(
        int $versionId,
        array $data,
        int $environmentId,
        int $updatedBy
    ): void {

        Database::transaction(
            function (PDO $db) use (
                $versionId,
                $data,
                $environmentId,
                $updatedBy
            ) {

                /*
 * 1. Obtener y bloquear la versión.
 */

                $stmt = $db->prepare(
                    '
    SELECT
        fv.id,
        fv.formula_id,
        fv.expresion,
        efv.codigo AS estado

    FROM formula_versiones fv

    INNER JOIN formulas f
        ON f.id = fv.formula_id

    INNER JOIN formula_entornos fe
        ON fe.formula_id = f.id

    INNER JOIN estados_formula_version efv
        ON efv.id = fv.estado_id

    WHERE fv.id = :version
      AND fe.entorno_id = :entorno
      AND f.activo = 1
      AND f.deleted_at IS NULL

    FOR UPDATE
    '
                );

                $stmt->execute([
                    'version' => $versionId,
                    'entorno' => $environmentId,
                ]);

                $version = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$version) {
                    throw new RuntimeException(
                        'Versión no encontrada o no disponible en este entorno.'
                    );
                }

                if ($version['estado'] !== 'BORRADOR') {
                    throw new RuntimeException(
                        'Solo puedes editar versiones en borrador.'
                    );
                }

                /*
 * 1.1. Comprobar ejecuciones clínicas.
 */

                $clinicalStmt = $db->prepare(
                    '
    SELECT COUNT(*)
    FROM formula_ejecuciones
    WHERE formula_version_id = :version
    '
                );

                $clinicalStmt->execute([
                    'version' => $versionId,
                ]);

                if ((int)$clinicalStmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'Esta versión tiene ejecuciones clínicas. '
                            . 'Crea una nueva versión para modificarla.'
                    );
                }

                /*
 * 1.2. Comprobar ejercicios académicos.
 */

                $academicStmt = $db->prepare(
                    '
    SELECT COUNT(*)

    FROM ejercicio_formula_valores efv

    INNER JOIN formula_variables fv
        ON fv.id = efv.formula_variable_id

    WHERE fv.formula_version_id = :version
    '
                );

                $academicStmt->execute([
                    'version' => $versionId,
                ]);

                if ((int)$academicStmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'Esta versión tiene ejercicios académicos asociados. '
                            . 'Crea una nueva versión para modificarla.'
                    );
                }

                /*
             * 2. Validar la expresión.
             */

                $expression = mb_strtoupper(
                    trim((string)($data['expresion'] ?? '')),
                    'UTF-8'
                );

                if ($expression === '') {
                    throw new RuntimeException(
                        'La expresión es obligatoria.'
                    );
                }

                /*
             * 3. Validar las variables recibidas.
             */

                $variables = $data['variables'] ?? [];

                if (!is_array($variables)) {
                    throw new RuntimeException(
                        'Las variables no son válidas.'
                    );
                }

                $normalized = [];
                $codes = [];

                foreach ($variables as $variable) {

                    $code = strtoupper(
                        trim(
                            (string)($variable['codigo'] ?? '')
                        )
                    );

                    $label = trim(
                        (string)($variable['etiqueta'] ?? '')
                    );

                    if ($code === '' && $label === '') {
                        continue;
                    }

                    if ($code === '' || $label === '') {
                        throw new RuntimeException(
                            'Cada variable debe tener código y etiqueta.'
                        );
                    }

                    if (
                        !preg_match(
                            '/^[A-Z][A-Z0-9_]*$/',
                            $code
                        )
                    ) {
                        throw new RuntimeException(
                            'Código de variable inválido: ' . $code
                        );
                    }

                    if (isset($codes[$code])) {
                        throw new RuntimeException(
                            'Código de variable duplicado: ' . $code
                        );
                    }

                    $codes[$code] = true;

                    $min = $variable['valor_minimo'] ?? null;
                    $max = $variable['valor_maximo'] ?? null;
                    $default = $variable['valor_default'] ?? null;

                    foreach (['min', 'max', 'default'] as $field) {

                        if (
                            $$field === ''
                            || $$field === null
                        ) {
                            $$field = null;
                            continue;
                        }

                        if (!is_numeric($$field)) {
                            throw new RuntimeException(
                                'Valor numérico inválido en ' . $code
                            );
                        }

                        $$field = (float)$$field;

                        if (!is_finite($$field)) {
                            throw new RuntimeException(
                                'Valor no finito en ' . $code
                            );
                        }
                    }

                    if (
                        $min !== null
                        && $max !== null
                        && $min > $max
                    ) {
                        throw new RuntimeException(
                            'El mínimo supera al máximo en ' . $code
                        );
                    }

                    if (
                        $default !== null
                        && (
                            ($min !== null && $default < $min)
                            ||
                            ($max !== null && $default > $max)
                        )
                    ) {
                        throw new RuntimeException(
                            'El valor predeterminado está fuera del rango en '
                                . $code
                        );
                    }

                    $normalized[] = [
                        'id' => (int)($variable['id'] ?? 0),
                        'codigo' => $code,
                        'etiqueta' => $label,

                        'descripcion' => trim(
                            (string)($variable['descripcion'] ?? '')
                        ) ?: null,

                        'tipo_variable_id' =>
                        (int)($variable['tipo_variable_id'] ?? 0),

                        'origen_variable_id' =>
                        (int)($variable['origen_variable_id'] ?? 0),

                        'unidad_medida_id' =>
                        !empty($variable['unidad_medida_id'])
                            ? (int)$variable['unidad_medida_id']
                            : null,

                        'obligatorio' =>
                        !empty($variable['obligatorio']) ? 1 : 0,

                        'valor_minimo' => $min,
                        'valor_maximo' => $max,
                        'valor_default' => $default,
                    ];
                }

                /*
             * 4. Actualizar la expresión.
             */

                $stmt = $db->prepare(
                    '
                UPDATE formula_versiones

                SET
                    expresion = :expresion,
                    notas_version = :notas

                WHERE id = :version
                '
                );

                $stmt->execute([
                    'expresion' => $expression,
                    'notas' => trim(
                        (string)($data['notas_version'] ?? '')
                    ) ?: null,
                    'version' => $versionId,
                ]);

                /*
 * 5. Sincronizar variables sin destruir sus IDs.
 */

                $existingStmt = $db->prepare(
                    '
    SELECT id
    FROM formula_variables
    WHERE formula_version_id = :version
    FOR UPDATE
    '
                );

                $existingStmt->execute([
                    'version' => $versionId
                ]);

                $existingIds = array_map(
                    'intval',
                    $existingStmt->fetchAll(PDO::FETCH_COLUMN)
                );

                $existingLookup = array_fill_keys(
                    $existingIds,
                    true
                );

                $receivedIds = [];

                $update = $db->prepare(
                    'UPDATE formula_variables
     SET tipo_variable_id = :tipo,
         origen_variable_id = :origen,
         unidad_medida_id = :unidad,
         codigo = :codigo,
         etiqueta = :etiqueta,
         descripcion = :descripcion,
         obligatorio = :obligatorio,
         valor_minimo = :minimo,
         valor_maximo = :maximo,
         valor_default = :predeterminado,
         orden = :orden
     WHERE id = :id
       AND formula_version_id = :version'
                );

                $insert = $db->prepare(
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
        :unidad,
        :codigo,
        :etiqueta,
        :descripcion,
        :obligatorio,
        :minimo,
        :maximo,
        :predeterminado,
        :orden
    )
    '
                );

                foreach ($normalized as $index => $variable) {

                    $id = (int)$variable['id'];

                    $params = [
                        'version' => $versionId,
                        'tipo' => $variable['tipo_variable_id'],
                        'origen' => $variable['origen_variable_id'],
                        'unidad' => $variable['unidad_medida_id'],
                        'codigo' => $variable['codigo'],
                        'etiqueta' => $variable['etiqueta'],
                        'descripcion' => $variable['descripcion'],
                        'obligatorio' => $variable['obligatorio'],
                        'minimo' => $variable['valor_minimo'],
                        'maximo' => $variable['valor_maximo'],
                        'predeterminado' => $variable['valor_default'],
                        'orden' => $index,
                    ];

                    if ($id > 0) {

                        /*
         * Impedir modificar una variable
         * perteneciente a otra versión.
         */

                        if (!isset($existingLookup[$id])) {
                            throw new RuntimeException(
                                'La variable no pertenece a esta versión.'
                            );
                        }

                        /*
         * Impedir IDs duplicados en la solicitud.
         */

                        if (isset($receivedIds[$id])) {
                            throw new RuntimeException(
                                'Se recibió una variable duplicada.'
                            );
                        }

                        $receivedIds[$id] = true;

                        try {

                            $update->execute([
                                'id' => $id,
                                'version' => $versionId,
                                'tipo' => $variable['tipo_variable_id'],
                                'origen' => $variable['origen_variable_id'],
                                'unidad' => $variable['unidad_medida_id'],
                                'codigo' => $variable['codigo'],
                                'etiqueta' => $variable['etiqueta'],
                                'descripcion' => $variable['descripcion'],
                                'obligatorio' => $variable['obligatorio'],
                                'minimo' => $variable['valor_minimo'],
                                'maximo' => $variable['valor_maximo'],
                                'predeterminado' => $variable['valor_default'],
                                'orden' => $index,
                            ]);
                        } catch (\PDOException $e) {

                            error_log(
                                '[FORMULAS][UPDATE_VARIABLE] '
                                    . $e->getMessage()
                            );

                            throw new \RuntimeException(
                                'Error al actualizar la variable.'
                            );
                        }
                    } else {

                        /*
         * Variable nueva.
         */

                        $insert->execute($params);
                    }
                }

                /*
 * 6. Identificar variables eliminadas
 * desde el formulario.
 */

                $removedIds = array_diff(
                    $existingIds,
                    array_keys($receivedIds)
                );

                $checkClinical = $db->prepare(
                    '
    SELECT COUNT(*)
    FROM formula_ejecucion_valores
    WHERE formula_variable_id = :id
    '
                );

                $checkAcademic = $db->prepare(
                    '
    SELECT COUNT(*)
    FROM ejercicio_formula_valores
    WHERE formula_variable_id = :id
    '
                );

                $delete = $db->prepare(
                    '
    DELETE FROM formula_variables
    WHERE id = :id
      AND formula_version_id = :version
    '
                );

                foreach ($removedIds as $removedId) {

                    $checkClinical->execute([
                        'id' => $removedId
                    ]);

                    $clinicalReferences =
                        (int)$checkClinical->fetchColumn();

                    $checkAcademic->execute([
                        'id' => $removedId
                    ]);

                    $academicReferences =
                        (int)$checkAcademic->fetchColumn();

                    if (
                        $clinicalReferences > 0
                        || $academicReferences > 0
                    ) {
                        throw new RuntimeException(
                            'No se puede eliminar la variable '
                                . $removedId
                                . ' porque tiene registros asociados.'
                        );
                    }

                    $delete->execute([
                        'id' => $removedId,
                        'version' => $versionId,
                    ]);
                }

                /*
             * 6. Auditoría.
             */

                (new AuditService())->log(
                    $updatedBy,
                    $environmentId,
                    'FORMULAS',
                    'EDITAR_BORRADOR',
                    'formula_versiones',
                    $versionId,
                    [
                        'expresion' => $version['expresion']
                    ],
                    [
                        'expresion' => $expression,
                        'variables' => $normalized
                    ]
                );
            }
        );
    }
}
