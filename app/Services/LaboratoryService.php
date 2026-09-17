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
}
