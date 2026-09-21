<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class LaboratoryService
{
    private FileService $fileService;

    public function __construct(
        ?FileService $fileService = null
    ) {
        $this->fileService =
            $fileService ?? new FileService();
    }

    public function create(
        array $data,
        array $files,
        int $environmentId,
        int $createdBy
    ): int {
        $storedFileId = null;
        $storedFilePath = null;

        try {
            return Database::transaction(
                function (PDO $db) use (
                    $data,
                    $files,
                    $environmentId,
                    $createdBy,
                    &$storedFileId,
                    &$storedFilePath,
                ): int {
                    $animalId =
                        (int) ($data['animal_id'] ?? 0);

                    $examTypeId =
                        (int) ($data['tipo_examen_id'] ?? 0);

                    if (!$animalId || !$examTypeId) {
                        throw new RuntimeException(
                            'Paciente y tipo de examen son obligatorios.'
                        );
                    }

                    $stmt = $db->prepare(
                        '
                        SELECT 1
                        FROM animales
                        WHERE id = :animal
                          AND entorno_id = :entorno
                          AND deleted_at IS NULL
                        '
                    );

                    $stmt->execute([
                        'animal' => $animalId,
                        'entorno' => $environmentId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Paciente no válido.'
                        );
                    }

                    $stmt = $db->prepare(
                        '
                        SELECT id
                        FROM tipos_examen_laboratorio
                        WHERE id = :id
                          AND activo = 1
                        LIMIT 1
                        '
                    );

                    $stmt->execute([
                        'id' => $examTypeId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Tipo de examen no válido.'
                        );
                    }

                    $eventTypeId =
                        (int) $db->query(
                            "
                            SELECT id
                            FROM tipos_evento_clinico
                            WHERE codigo = 'LABORATORIO'
                              AND activo = 1
                            LIMIT 1
                            "
                        )->fetchColumn();

                    if (!$eventTypeId) {
                        throw new RuntimeException(
                            'No existe el tipo de evento LABORATORIO.'
                        );
                    }

                    $requestDate = $this->dt(
                        $data['fecha_solicitud'] ?? null
                    );

                    $stmt = $db->prepare(
                        '
                        INSERT INTO eventos_clinicos
                        (
                            animal_id,
                            tipo_evento_id,
                            responsable_id,
                            fecha_evento,
                            titulo
                        )
                        VALUES
                        (
                            :animal,
                            :tipo,
                            :usuario,
                            :fecha,
                            "Examen de laboratorio"
                        )
                        '
                    );

                    $stmt->execute([
                        'animal' => $animalId,
                        'tipo' => $eventTypeId,
                        'usuario' => $createdBy,
                        'fecha' => $requestDate,
                    ]);

                    $eventId =
                        (int) $db->lastInsertId();

                    $stmt = $db->prepare(
                        '
                        INSERT INTO examenes_laboratorio
                        (
                            evento_clinico_id,
                            tipo_examen_id,
                            solicitado_por,
                            fecha_solicitud,
                            fecha_resultado,
                            resultado_resumen,
                            observaciones
                        )
                        VALUES
                        (
                            :evento,
                            :tipo,
                            :usuario,
                            :fecha_solicitud,
                            :fecha_resultado,
                            :resultado,
                            :observaciones
                        )
                        '
                    );

                    $stmt->execute([
                        'evento' => $eventId,
                        'tipo' => $examTypeId,
                        'usuario' => $createdBy,
                        'fecha_solicitud' => $requestDate,
                        'fecha_resultado' =>
                        !empty($data['fecha_resultado'])
                            ? $this->dt(
                                $data['fecha_resultado']
                            )
                            : null,
                        'resultado' =>
                        trim(
                            (string) (
                                $data['resultado_resumen']
                                ?? ''
                            )
                        ) ?: null,
                        'observaciones' =>
                        trim(
                            (string) (
                                $data['observaciones']
                                ?? ''
                            )
                        ) ?: null,
                    ]);

                    $examId =
                        (int) $db->lastInsertId();

                    if (
                        isset($files['archivo'])
                        && (
                            $files['archivo']['error']
                            ?? UPLOAD_ERR_NO_FILE
                        ) === UPLOAD_ERR_OK
                    ) {
                        $storedFileId =
                            $this->fileService->store(
                                $files['archivo'],
                                $createdBy,
                                'laboratorio',
                                [
                                    'application/pdf',
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                ],
                                $storedFilePath
                            );

                        $stmt = $db->prepare(
                            '
                            INSERT INTO examen_laboratorio_archivos
                            (
                                examen_laboratorio_id,
                                archivo_id,
                                descripcion
                            )
                            VALUES
                            (
                                :examen,
                                :archivo,
                                :descripcion
                            )
                            '
                        );

                        $stmt->execute([
                            'examen' => $examId,
                            'archivo' => $storedFileId,
                            'descripcion' =>
                            'Resultado / documento',
                        ]);
                    }

                    (new AuditService())->log(
                        $createdBy,
                        $environmentId,
                        'LABORATORIO',
                        'CREAR',
                        'examenes_laboratorio',
                        $examId
                    );

                    return $examId;
                }
            );
        } catch (Throwable $e) {
            if ($storedFilePath !== null) {
                try {
                    $this->fileService->compensatePhysicalFile(
                        $storedFilePath
                    );
                } catch (Throwable) {
                    // No ocultamos el error original.
                }
            }

            throw $e;
        }
    }

    private function dt(
        mixed $value
    ): string {
        if (!$value) {
            return date('Y-m-d H:i:s');
        }

        $value = str_replace(
            'T',
            ' ',
            (string) $value
        );

        return strlen($value) === 16
            ? $value . ':00'
            : $value;
    }

    public function delete(
        int $examId,
        int $environmentId,
        int $deletedBy,
        string $reason
    ): void {

        $reason = trim($reason);

        if ($examId <= 0) {
            throw new RuntimeException(
                'Examen no válido.'
            );
        }

        if ($reason === '') {
            throw new RuntimeException(
                'Debes indicar el motivo de eliminación.'
            );
        }

        Database::transaction(
            function (PDO $db) use (
                $examId,
                $environmentId,
                $deletedBy,
                $reason
            ): void {

                /*
             * Verificar examen y entorno.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    el.id,
                    el.evento_clinico_id,
                    ec.anulado_at
                FROM examenes_laboratorio el

                INNER JOIN eventos_clinicos ec
                    ON ec.id = el.evento_clinico_id

                INNER JOIN animales a
                    ON a.id = ec.animal_id

                WHERE el.id = :examen
                  AND a.entorno_id = :entorno
                  AND a.deleted_at IS NULL

                LIMIT 1
                FOR UPDATE
                '
                );

                $stmt->execute([
                    'examen' => $examId,
                    'entorno' => $environmentId,
                ]);

                $exam = $stmt->fetch();

                if (!$exam) {
                    throw new RuntimeException(
                        'El examen no existe en el entorno activo.'
                    );
                }

                if ($exam['anulado_at'] !== null) {
                    throw new RuntimeException(
                        'Este examen ya fue anulado.'
                    );
                }

                /*
             * Anulación lógica del evento.
             */
                $stmt = $db->prepare(
                    '
                UPDATE eventos_clinicos
                SET
                    anulado_at = NOW(),
                    anulado_por = :usuario,
                    motivo_anulacion = :motivo

                WHERE id = :evento
                  AND anulado_at IS NULL
                '
                );

                $stmt->execute([
                    'usuario' => $deletedBy,
                    'motivo' => $reason,
                    'evento' => $exam['evento_clinico_id'],
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'No se pudo anular el examen.'
                    );
                }

                /*
             * Auditoría.
             */
                (new AuditService())->log(
                    $deletedBy,
                    $environmentId,
                    'LABORATORIO',
                    'ANULAR',
                    'examenes_laboratorio',
                    $examId,
                    [
                        'evento_clinico_id' =>
                        $exam['evento_clinico_id'],
                    ],
                    [
                        'motivo_anulacion' => $reason,
                        'anulado_por' => $deletedBy,
                    ]
                );
            }
        );
    }

    public function update(
        int $examId,
        array $data,
        array $files,
        int $environmentId,
        int $updatedBy
    ): void {
        $storedFilePath = null;

        try {
            Database::transaction(
                function (PDO $db) use (
                    $examId,
                    $data,
                    $files,
                    $environmentId,
                    $updatedBy,
                    &$storedFilePath
                ): void {

                    if ($examId <= 0) {
                        throw new RuntimeException(
                            'Examen de laboratorio no válido.'
                        );
                    }

                    /*
                 * Verificar que el examen pertenece
                 * al entorno activo y no está anulado.
                 */
                    $stmt = $db->prepare(
                        '
                    SELECT
                        el.*,
                        ec.anulado_at
                    FROM examenes_laboratorio el

                    INNER JOIN eventos_clinicos ec
                        ON ec.id = el.evento_clinico_id

                    INNER JOIN animales a
                        ON a.id = ec.animal_id

                    WHERE el.id = :examen
                      AND a.entorno_id = :entorno
                      AND a.deleted_at IS NULL

                    LIMIT 1
                    FOR UPDATE
                    '
                    );

                    $stmt->execute([
                        'examen' => $examId,
                        'entorno' => $environmentId,
                    ]);

                    $previous = $stmt->fetch();

                    if (!$previous) {
                        throw new RuntimeException(
                            'El examen no existe en el entorno activo.'
                        );
                    }

                    if ($previous['anulado_at'] !== null) {
                        throw new RuntimeException(
                            'No puedes editar un examen anulado.'
                        );
                    }

                    /*
                 * Validar tipo de examen.
                 */
                    $examTypeId = (int) (
                        $data['tipo_examen_id'] ?? 0
                    );

                    $stmt = $db->prepare(
                        '
                    SELECT id
                    FROM tipos_examen_laboratorio
                    WHERE id = :id
                      AND activo = 1
                    LIMIT 1
                    '
                    );

                    $stmt->execute([
                        'id' => $examTypeId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Tipo de examen no válido.'
                        );
                    }

                    /*
                 * Preparar datos.
                 */
                    $requestDate = !empty($data['fecha_solicitud'])
                        ? $this->dt(
                            $data['fecha_solicitud']
                        )
                        : $previous['fecha_solicitud'];

                    $resultDate = !empty($data['fecha_resultado'])
                        ? $this->dt(
                            $data['fecha_resultado']
                        )
                        : null;

                    $resultSummary = trim(
                        (string) (
                            $data['resultado_resumen'] ?? ''
                        )
                    );

                    /*
                 * Actualizar examen.
                 */
                    $stmt = $db->prepare(
                        '
                    UPDATE examenes_laboratorio

                    SET
                        tipo_examen_id = :tipo,
                        fecha_solicitud = :solicitud,
                        fecha_resultado = :resultado_fecha,
                        resultado_resumen = :resumen

                    WHERE id = :examen
                    '
                    );

                    $stmt->execute([
                        'tipo' => $examTypeId,
                        'solicitud' => $requestDate,
                        'resultado_fecha' => $resultDate,
                        'resumen' => $resultSummary !== ''
                            ? $resultSummary
                            : null,
                        'examen' => $examId,
                    ]);

                    /*
                 * Sincronizar fecha del evento clínico.
                 */
                    $stmt = $db->prepare(
                        '
                    UPDATE eventos_clinicos
                    SET fecha_evento = :fecha
                    WHERE id = :evento
                      AND anulado_at IS NULL
                    '
                    );

                    $stmt->execute([
                        'fecha' => $requestDate,
                        'evento' => $previous['evento_clinico_id'],
                    ]);

                    /*
                 * Agregar un documento nuevo,
                 * conservando los anteriores.
                 */
                    if (
                        isset($files['archivo'])
                        && (
                            $files['archivo']['error']
                            ?? UPLOAD_ERR_NO_FILE
                        ) === UPLOAD_ERR_OK
                    ) {

                        $fileId = $this->fileService->store(
                            $files['archivo'],
                            $updatedBy,
                            'laboratorio',
                            [
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ],
                            $storedFilePath
                        );

                        $stmt = $db->prepare(
                            '
                        INSERT INTO examen_laboratorio_archivos
                        (
                            examen_laboratorio_id,
                            archivo_id,
                            descripcion
                        )
                        VALUES
                        (
                            :examen,
                            :archivo,
                            :descripcion
                        )
                        '
                        );

                        $stmt->execute([
                            'examen' => $examId,
                            'archivo' => $fileId,
                            'descripcion' =>
                            'Documento adicional / resultado',
                        ]);
                    } elseif (
                        isset($files['archivo'])
                        && (
                            $files['archivo']['error']
                            ?? UPLOAD_ERR_NO_FILE
                        ) !== UPLOAD_ERR_NO_FILE
                    ) {
                        throw new RuntimeException(
                            'No fue posible recibir el archivo adjunto.'
                        );
                    }

                    /*
                 * Auditoría.
                 */
                    (new AuditService())->log(
                        $updatedBy,
                        $environmentId,
                        'LABORATORIO',
                        'EDITAR',
                        'examenes_laboratorio',
                        $examId
                    );
                }
            );
        } catch (Throwable $e) {

            if ($storedFilePath !== null) {

                try {
                    $this->fileService
                        ->compensatePhysicalFile(
                            $storedFilePath
                        );
                } catch (Throwable $cleanupException) {
                    error_log(
                        'Error al limpiar archivo de laboratorio: '
                            . $cleanupException->getMessage()
                    );
                }
            }

            throw $e;
        }
    }
}
