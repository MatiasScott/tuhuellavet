<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class SurgeryService
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
                    &$storedFilePath
                ): int {
                    $animalId =
                        (int) ($data['animal_id'] ?? 0);

                    $procedureId =
                        (int) (
                            $data['procedimiento_quirurgico_id']
                            ?? 0
                        );

                    if (!$animalId || !$procedureId) {
                        throw new RuntimeException(
                            'Paciente y procedimiento son obligatorios.'
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
                        FROM procedimientos_quirurgicos
                        WHERE id = :id
                          AND activo = 1
                        LIMIT 1
                        '
                    );

                    $stmt->execute([
                        'id' => $procedureId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Procedimiento quirúrgico no válido.'
                        );
                    }

                    $eventTypeId =
                        (int) $db->query(
                            "
                            SELECT id
                            FROM tipos_evento_clinico
                            WHERE codigo = 'CIRUGIA'
                              AND activo = 1
                            LIMIT 1
                            "
                        )->fetchColumn();

                    if (!$eventTypeId) {
                        throw new RuntimeException(
                            'No existe el tipo de evento CIRUGIA.'
                        );
                    }

                    $startDate = $this->dt(
                        $data['fecha_inicio'] ?? null
                    );

                    $endDate =
                        !empty($data['fecha_fin'])
                        ? $this->dt(
                            $data['fecha_fin']
                        )
                        : null;

                    if (
                        $endDate !== null
                        && strtotime($endDate)
                        < strtotime($startDate)
                    ) {
                        throw new RuntimeException(
                            'La fecha de finalización no puede ser anterior al inicio.'
                        );
                    }

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
                            "Cirugía"
                        )
                        '
                    );

                    $stmt->execute([
                        'animal' => $animalId,
                        'tipo' => $eventTypeId,
                        'usuario' => $createdBy,
                        'fecha' => $startDate,
                    ]);

                    $eventId =
                        (int) $db->lastInsertId();

                    $stmt = $db->prepare(
                        '
                        INSERT INTO cirugias
                        (
                            evento_clinico_id,
                            procedimiento_quirurgico_id,
                            medico_responsable_id,
                            fecha_inicio,
                            fecha_fin,
                            diagnostico_preoperatorio,
                            descripcion_procedimiento,
                            hallazgos,
                            complicaciones,
                            indicaciones_postoperatorias,
                            observaciones
                        )
                        VALUES
                        (
                            :evento,
                            :procedimiento,
                            :medico,
                            :fecha_inicio,
                            :fecha_fin,
                            :diagnostico,
                            :descripcion,
                            :hallazgos,
                            :complicaciones,
                            :indicaciones,
                            :observaciones
                        )
                        '
                    );

                    $stmt->execute([
                        'evento' => $eventId,
                        'procedimiento' =>
                        $procedureId,
                        'medico' => $createdBy,
                        'fecha_inicio' =>
                        $startDate,
                        'fecha_fin' =>
                        $endDate,
                        'diagnostico' =>
                        trim(
                            (string) (
                                $data['diagnostico_preoperatorio']
                                ?? ''
                            )
                        ) ?: null,
                        'descripcion' =>
                        trim(
                            (string) (
                                $data['descripcion_procedimiento']
                                ?? ''
                            )
                        ) ?: null,
                        'hallazgos' =>
                        trim(
                            (string) (
                                $data['hallazgos']
                                ?? ''
                            )
                        ) ?: null,
                        'complicaciones' =>
                        trim(
                            (string) (
                                $data['complicaciones']
                                ?? ''
                            )
                        ) ?: null,
                        'indicaciones' =>
                        trim(
                            (string) (
                                $data['indicaciones_postoperatorias']
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
                                'cirugias',
                                [
                                    'application/pdf',
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                ],
                                $storedFilePath
                            );

                        if ($storedFilePath !== null) {
                            $pathForRollback = $storedFilePath;

                            Database::onRollback(
                                function () use ($pathForRollback): void {
                                    $this->fileService->compensatePhysicalFile(
                                        $pathForRollback
                                    );
                                }
                            );
                        }

                        $stmt = $db->prepare(
                            '
                            INSERT INTO cirugia_archivos
                            (
                                cirugia_evento_id,
                                archivo_id,
                                descripcion
                            )
                            VALUES
                            (
                                :cirugia,
                                :archivo,
                                :descripcion
                            )
                            '
                        );

                        $stmt->execute([
                            'cirugia' => $eventId,
                            'archivo' => $storedFileId,
                            'descripcion' =>
                            'Documento de cirugía',
                        ]);
                    }

                    $hasAnesthesiaType =
                        !empty($data['tipo_anestesia_id']);

                    $anesthesiaProtocol =
                        trim(
                            (string) (
                                $data['protocolo_anestesia']
                                ?? ''
                            )
                        );

                    if (
                        $hasAnesthesiaType
                        || $anesthesiaProtocol !== ''
                    ) {
                        $anesthesiaTypeId = null;

                        if ($hasAnesthesiaType) {
                            $anesthesiaTypeId =
                                (int) $data['tipo_anestesia_id'];

                            $stmt = $db->prepare(
                                '
                                SELECT id
                                FROM tipos_anestesia
                                WHERE id = :id
                                  AND activo = 1
                                LIMIT 1
                                '
                            );

                            $stmt->execute([
                                'id' =>
                                $anesthesiaTypeId,
                            ]);

                            if (
                                !$stmt->fetchColumn()
                            ) {
                                throw new RuntimeException(
                                    'Tipo de anestesia no válido.'
                                );
                            }
                        }

                        $stmt = $db->prepare(
                            '
                            INSERT INTO cirugia_anestesias
                            (
                                cirugia_evento_id,
                                responsable_id,
                                tipo_anestesia_id,
                                protocolo,
                                observaciones
                            )
                            VALUES
                            (
                                :cirugia,
                                :responsable,
                                :tipo,
                                :protocolo,
                                :observaciones
                            )
                            '
                        );

                        $stmt->execute([
                            'cirugia' => $eventId,
                            'responsable' =>
                            $createdBy,
                            'tipo' =>
                            $anesthesiaTypeId,
                            'protocolo' =>
                            $anesthesiaProtocol
                                !== ''
                                ? $anesthesiaProtocol
                                : null,
                            'observaciones' => null,
                        ]);
                    }

                    (new AuditService())->log(
                        $createdBy,
                        $environmentId,
                        'CIRUGIAS',
                        'CREAR',
                        'cirugias',
                        $eventId
                    );

                    return $eventId;
                }
            );
        } catch (Throwable $e) {
            if ($storedFilePath !== null) {
                try {
                    $this->fileService->compensatePhysicalFile(
                        $storedFilePath
                    );
                } catch (Throwable) {
                    // Conservamos la excepción original.
                }
            }

            throw $e;
        }
    }

    public function addTeamMember(
        int $eventId,
        array $data,
        int $environmentId,
        int $createdBy
    ): void {

        Database::transaction(
            function (PDO $db) use (
                $eventId,
                $data,
                $environmentId,
                $createdBy
            ): void {

                $this->assertSurgeryEnvironment(
                    $db,
                    $eventId,
                    $environmentId
                );

                /*
             * Tipo de profesional.
             */
                $type = strtoupper(
                    trim(
                        (string) (
                            $data['tipo_profesional']
                            ?? 'INTERNO'
                        )
                    )
                );

                if (!in_array(
                    $type,
                    ['INTERNO', 'EXTERNO'],
                    true
                )) {
                    throw new RuntimeException(
                        'Tipo de profesional no válido.'
                    );
                }

                /*
             * Función quirúrgica.
             */
                $functionId = (int) (
                    $data['funcion_id'] ?? 0
                );

                $stmt = $db->prepare(
                    '
                SELECT id
                FROM funciones_equipo_quirurgico
                WHERE id = :id
                  AND activo = 1
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $functionId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Función quirúrgica no válida.'
                    );
                }

                /*
             * Datos según modalidad.
             */
                $userId = null;
                $externalName = null;
                $professionalRegistration = null;
                $externalInstitution = null;

                if ($type === 'INTERNO') {

                    $userId = (int) (
                        $data['usuario_id'] ?? 0
                    );

                    if ($userId <= 0) {
                        throw new RuntimeException(
                            'Debes seleccionar un profesional.'
                        );
                    }

                    $stmt = $db->prepare(
                        '
                    SELECT 1
                    FROM usuarios
                    WHERE id = :id
                    LIMIT 1
                    '
                    );

                    $stmt->execute([
                        'id' => $userId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Usuario no válido.'
                        );
                    }

                    /*
                 * Evitar duplicados internos.
                 */
                    $stmt = $db->prepare(
                        '
                    SELECT 1
                    FROM cirugia_equipo
                    WHERE cirugia_evento_id = :cirugia
                      AND usuario_id = :usuario
                      AND funcion_id = :funcion
                    LIMIT 1
                    '
                    );

                    $stmt->execute([
                        'cirugia' => $eventId,
                        'usuario' => $userId,
                        'funcion' => $functionId,
                    ]);

                    if ($stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'El integrante ya está registrado con esa función.'
                        );
                    }
                } else {

                    $externalName = trim(
                        (string) (
                            $data['nombre_externo'] ?? ''
                        )
                    );

                    if (
                        $externalName === ''
                        || mb_strlen($externalName) > 200
                    ) {
                        throw new RuntimeException(
                            'Ingresa un nombre válido para el profesional externo.'
                        );
                    }

                    $professionalRegistration = trim(
                        (string) (
                            $data['registro_profesional'] ?? ''
                        )
                    ) ?: null;

                    $externalInstitution = trim(
                        (string) (
                            $data['institucion_externa'] ?? ''
                        )
                    ) ?: null;

                    if (
                        $professionalRegistration !== null
                        && mb_strlen($professionalRegistration) > 100
                    ) {
                        throw new RuntimeException(
                            'El registro profesional es demasiado largo.'
                        );
                    }

                    if (
                        $externalInstitution !== null
                        && mb_strlen($externalInstitution) > 200
                    ) {
                        throw new RuntimeException(
                            'El nombre de la institución es demasiado largo.'
                        );
                    }
                }

                /*
             * Registrar integrante.
             */
                $stmt = $db->prepare(
                    '
                INSERT INTO cirugia_equipo
                (
                    cirugia_evento_id,
                    usuario_id,
                    tipo_profesional,
                    nombre_externo,
                    registro_profesional,
                    institucion_externa,
                    funcion_id,
                    registrado_por
                )
                VALUES
                (
                    :cirugia,
                    :usuario,
                    :tipo,
                    :nombre,
                    :registro,
                    :institucion,
                    :funcion,
                    :registrado_por
                )
                '
                );

                $stmt->execute([
                    'cirugia' => $eventId,
                    'usuario' => $userId,
                    'tipo' => $type,
                    'nombre' => $externalName,
                    'registro' => $professionalRegistration,
                    'institucion' => $externalInstitution,
                    'funcion' => $functionId,
                    'registrado_por' => $createdBy,
                ]);

                $teamMemberId = (int) $db->lastInsertId();

                /*
             * Auditoría.
             */
                (new AuditService())->log(
                    $createdBy,
                    $environmentId,
                    'CIRUGIAS',
                    'AGREGAR_EQUIPO',
                    'cirugia_equipo',
                    $teamMemberId,
                    null,
                    [
                        'cirugia_evento_id' => $eventId,
                        'tipo_profesional' => $type,
                        'usuario_id' => $userId,
                        'nombre_externo' => $externalName,
                        'funcion_id' => $functionId,
                    ]
                );
            }
        );
    }

    public function removeTeamMember(
        int $eventId,
        int $teamMemberId,
        int $environmentId,
        int $deletedBy
    ): void {

        Database::transaction(
            function (PDO $db) use (
                $eventId,
                $teamMemberId,
                $environmentId,
                $deletedBy
            ): void {

                $this->assertSurgeryEnvironment(
                    $db,
                    $eventId,
                    $environmentId
                );

                /*
             * Recuperar integrante.
             */
                $stmt = $db->prepare(
                    '
                SELECT *
                FROM cirugia_equipo
                WHERE id = :id
                  AND cirugia_evento_id = :cirugia
                LIMIT 1
                FOR UPDATE
                '
                );

                $stmt->execute([
                    'id' => $teamMemberId,
                    'cirugia' => $eventId,
                ]);

                $member = $stmt->fetch();

                if (!$member) {
                    throw new RuntimeException(
                        'El integrante del equipo quirúrgico no existe.'
                    );
                }

                /*
             * Eliminar integrante.
             */
                $stmt = $db->prepare(
                    '
                DELETE FROM cirugia_equipo
                WHERE id = :id
                  AND cirugia_evento_id = :cirugia
                '
                );

                $stmt->execute([
                    'id' => $teamMemberId,
                    'cirugia' => $eventId,
                ]);

                /*
             * Auditoría.
             */
                (new AuditService())->log(
                    $deletedBy,
                    $environmentId,
                    'CIRUGIAS',
                    'ELIMINAR_EQUIPO',
                    'cirugia_equipo',
                    $teamMemberId,
                    $member
                );
            }
        );
    }

    public function addEvolution(
        int $eventId,
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $eventId,
                $data,
                $environmentId,
                $createdBy
            ): int {
                $this->assertSurgeryEnvironment(
                    $db,
                    $eventId,
                    $environmentId
                );

                $evolution = trim(
                    (string) ($data['evolucion'] ?? '')
                );

                if ($evolution === '') {
                    throw new RuntimeException(
                        'La evolución es obligatoria.'
                    );
                }

                $stmt = $db->prepare(
                    '
                INSERT INTO cirugia_evoluciones
                (
                    cirugia_evento_id,
                    registrado_por,
                    fecha_hora,
                    evolucion,
                    observaciones
                )
                VALUES
                (
                    :cirugia,
                    :usuario,
                    :fecha,
                    :evolucion,
                    :observaciones
                )
                '
                );

                $stmt->execute([
                    'cirugia' => $eventId,
                    'usuario' => $createdBy,
                    'fecha' => $this->dt(
                        $data['fecha_hora'] ?? null
                    ),
                    'evolucion' => $evolution,
                    'observaciones' =>
                    trim(
                        (string) (
                            $data['observaciones']
                            ?? ''
                        )
                    ) ?: null,
                ]);

                $evolutionId =
                    (int) $db->lastInsertId();

                (new AuditService())->log(
                    $createdBy,
                    $environmentId,
                    'CIRUGIAS',
                    'REGISTRAR_EVOLUCION',
                    'cirugia_evoluciones',
                    $evolutionId
                );

                return $evolutionId;
            }
        );
    }

    private function assertSurgeryEnvironment(
        PDO $db,
        int $eventId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
        SELECT 1
        FROM cirugias c
        INNER JOIN eventos_clinicos ec
            ON ec.id = c.evento_clinico_id
        INNER JOIN animales a
            ON a.id = ec.animal_id
        WHERE c.evento_clinico_id = :cirugia
          AND a.entorno_id = :entorno
          AND a.deleted_at IS NULL
        LIMIT 1
        '
        );

        $stmt->execute([
            'cirugia' => $eventId,
            'entorno' => $environmentId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Cirugía no válida para el entorno actual.'
            );
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
