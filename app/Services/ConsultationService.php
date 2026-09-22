<?php

namespace App\Services;

use App\Core\Database;

use PDO;
use RuntimeException;

class ConsultationService
{
    public function create(
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ) {
                $patientId = (int) (
                    $data['animal_id']
                    ?? 0
                );

                if (!$patientId) {
                    throw new RuntimeException(
                        'Debes seleccionar un paciente.'
                    );
                }

                $this->validatePatient(
                    $db,
                    $patientId,
                    $environmentId
                );

                $eventTypeId = $this
                    ->getEventTypeId(
                        $db,
                        'CONSULTA_EXTERNA'
                    );

                /*
                 * Evento clínico.
                 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO eventos_clinicos
                    (
                        animal_id,
                        tipo_evento_id,
                        responsable_id,
                        fecha_evento,
                        titulo,
                        observaciones
                    )
                    VALUES
                    (
                        :animal,
                        :tipo,
                        :responsable,
                        :fecha,
                        :titulo,
                        :observaciones
                    )
                    '
                );

                $stmt->execute([
                    'animal'
                    => $patientId,

                    'tipo'
                    => $eventTypeId,

                    'responsable'
                    => $createdBy,

                    'fecha'
                    => !empty($data['fecha_evento'])
                        ? $data['fecha_evento']
                        : date(
                            'Y-m-d H:i:s'
                        ),

                    'titulo'
                    => trim(
                        $data['titulo']
                            ?? ''
                    )
                        ?: 'Consulta externa',

                    'observaciones'
                    => trim(
                        $data['observaciones_evento']
                            ?? ''
                    )
                        ?: null,
                ]);

                $eventId = (int)
                $db->lastInsertId();

                /*
                 * Consulta.
                 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO consultas_externas
                    (
                        evento_clinico_id,
                        motivo_consulta,
                        anamnesis,
                        antecedentes,
                        recomendaciones
                    )
                    VALUES
                    (
                        :evento,
                        :motivo,
                        :anamnesis,
                        :antecedentes,
                        :recomendaciones
                    )
                    '
                );

                $stmt->execute([
                    'evento'
                    => $eventId,

                    'motivo'
                    => trim(
                        $data['motivo_consulta']
                            ?? ''
                    )
                        ?: null,

                    'anamnesis'
                    => trim(
                        $data['anamnesis']
                            ?? ''
                    )
                        ?: null,

                    'antecedentes'
                    => trim(
                        $data['antecedentes']
                            ?? ''
                    )
                        ?: null,

                    'recomendaciones'
                    => trim(
                        $data['recomendaciones']
                            ?? ''
                    )
                        ?: null,
                ]);

                /*
                 * Examen clínico general.
                 */
                $this->createClinicalExam(
                    $db,
                    $eventId,
                    $data
                );

                /*
                 * válidaDiagnósticos.
                 */
                $this->createDiagnoses(
                    $db,
                    $eventId,
                    $data,
                    $createdBy
                );

                /*
                 * Peso opcional tomado
                 * durante consulta.
                 */
                if (
                    isset(
                        $data['peso_kg']
                    )
                    && $data['peso_kg'] !== ''
                ) {
                    $weight = (float)
                    $data['peso_kg'];

                    if ($weight <= 0) {
                        throw new RuntimeException(
                            'El peso debe ser mayor a cero.'
                        );
                    }

                    $stmt = $db->prepare(
                        '
                        INSERT INTO animales_pesos
                        (
                            animal_id,
                            peso_kg,
                            registrado_por,
                            origen,
                            fecha_registro,
                            observacion
                        )
                        VALUES
                        (
                            :animal,
                            :peso,
                            :usuario,
                            "CONSULTA_EXTERNA",
                            NOW(),
                            :observacion
                        )
                        '
                    );

                    $stmt->execute([
                        'animal'
                        => $patientId,

                        'peso'
                        => $weight,

                        'usuario'
                        => $createdBy,

                        'observacion'
                        => 'Peso registrado durante consulta externa.',
                    ]);
                }

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'CONSULTAS',
                        'CREAR',
                        'eventos_clinicos',
                        $eventId,
                        null,
                        [
                            'animal_id'
                            => $patientId,

                            'motivo_consulta'
                            => $data['motivo_consulta']
                                ?? null,
                        ]
                    );

                return $eventId;
            }
        );
    }


    private function createClinicalExam(
        PDO $db,
        int $eventId,
        array $data
    ): void {
        $stmt = $db->prepare(
            '
            INSERT INTO examenes_clinicos_generales
            (
                evento_clinico_id,
                alimentacion,
                historial_reproductivo,
                frecuencia_cardiaca,
                frecuencia_respiratoria,
                temperatura_c,
                tiempo_llenado_capilar_seg,
                ganglios_linfaticos,
                condicion_corporal,
                vomitos,
                diarrea,
                tos,
                observaciones
            )
            VALUES
            (
                :evento,
                :alimentacion,
                :historial_reproductivo,
                :fc,
                :fr,
                :temperatura,
                :tlc,
                :ganglios,
                :condicion,
                :vomitos,
                :diarrea,
                :tos,
                :observaciones
            )
            '
        );

        $stmt->execute([
            'evento'
            => $eventId,

            'alimentacion'
            => trim(
                $data['alimentacion']
                    ?? ''
            )
                ?: null,

            'historial_reproductivo'
            => trim(
                $data['historial_reproductivo']
                    ?? ''
            )
                ?: null,

            'fc'
            => $this->decimalOrNull(
                $data['frecuencia_cardiaca']
                    ?? null
            ),

            'fr'
            => $this->decimalOrNull(
                $data['frecuencia_respiratoria']
                    ?? null
            ),

            'temperatura'
            => $this->decimalOrNull(
                $data['temperatura_c']
                    ?? null
            ),

            'tlc'
            => $this->decimalOrNull(
                $data['tiempo_llenado_capilar_seg']
                    ?? null
            ),

            'ganglios'
            => trim(
                $data['ganglios_linfaticos']
                    ?? ''
            )
                ?: null,

            'condicion'
            => trim(
                $data['condicion_corporal']
                    ?? ''
            )
                ?: null,

            'vomitos'
            => !empty($data['vomitos'])
                ? 1
                : 0,

            'diarrea'
            => !empty($data['diarrea'])
                ? 1
                : 0,

            'tos'
            => !empty($data['tos'])
                ? 1
                : 0,

            'observaciones'
            => trim(
                $data['examen_observaciones']
                    ?? ''
            )
                ?: null,
        ]);
    }


    private function createDiagnoses(
        PDO $db,
        int $eventId,
        array $data,
        int $createdBy
    ): void {
        $map = [
            'diagnostico_diferencial'
            => 'DIFERENCIAL',

            'diagnostico_presuntivo'
            => 'PRESUNTIVO',

            'diagnostico_definitivo'
            => 'DEFINITIVO',
        ];

        foreach (
            $map
            as $field => $typeCode
        ) {
            $value = trim(
                (string) (
                    $data[$field]
                    ?? ''
                )
            );

            if ($value === '') {
                continue;
            }

            $typeId = $this
                ->getDiagnosisTypeId(
                    $db,
                    $typeCode
                );

            $stmt = $db->prepare(
                '
                INSERT INTO diagnosticos_clinicos
                (
                    evento_clinico_id,
                    tipo_diagnostico_id,
                    descripcion,
                    ingresado_por
                )
                VALUES
                (
                    :evento,
                    :tipo,
                    :descripcion,
                    :usuario
                )
                '
            );

            $stmt->execute([
                'evento'
                => $eventId,

                'tipo'
                => $typeId,

                'descripcion'
                => $value,

                'usuario'
                => $createdBy,
            ]);
        }
    }


    private function validatePatient(
        PDO $db,
        int $patientId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM animales

            WHERE id = :animal
              AND entorno_id = :entorno
              AND activo = 1
              AND deleted_at IS NULL
            '
        );

        $stmt->execute([
            'animal'
            => $patientId,

            'entorno'
            => $environmentId,
        ]);

        if (
            (int)
            $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'El paciente no pertenece al entorno actual.'
            );
        }
    }


    private function getEventTypeId(
        PDO $db,
        string $code
    ): int {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM tipos_evento_clinico

            WHERE codigo = :codigo

            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el tipo de evento clínico solicitado.'
            );
        }

        return (int) $id;
    }


    private function getDiagnosisTypeId(
        PDO $db,
        string $code
    ): int {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM tipos_diagnostico

            WHERE codigo = :codigo

            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el tipo de diagnóstico.'
            );
        }

        return (int) $id;
    }


    private function decimalOrNull(
        mixed $value
    ): ?float {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return (float) $value;
    }

    /** Actualiza solo los campos enviados por el modal, sin borrar los demás. */
    public function update(int $eventId, array $data, int $environmentId, int $updatedBy): void
    {
        $allowed = ['motivo_consulta', 'anamnesis', 'antecedentes', 'recomendaciones'];
        $changes = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if (!is_scalar($data[$field]) && $data[$field] !== null) {
                    throw new RuntimeException('Valor inválido para ' . $field . '.');
                }
                $value = trim((string) ($data[$field] ?? ''));
                $changes[$field] = $value === '' ? null : $value;
            }
        }
        if (!$changes) {
            throw new RuntimeException('No se recibieron campos de consulta para actualizar.');
        }

        Database::transaction(function (PDO $db) use ($eventId, $environmentId, $updatedBy, $changes) {
            $this->assertEditableConsultation($db, $eventId, $environmentId);
            $stmt = $db->prepare('SELECT motivo_consulta, anamnesis, antecedentes, recomendaciones FROM consultas_externas WHERE evento_clinico_id = :evento FOR UPDATE');
            $stmt->execute(['evento' => $eventId]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) {
                throw new RuntimeException('No se encontró el detalle de la consulta.');
            }
            $set = [];
            $params = ['evento' => $eventId];
            foreach ($changes as $field => $value) {
                $set[] = $field . ' = :' . $field;
                $params[$field] = $value;
            }
            $stmt = $db->prepare('UPDATE consultas_externas SET ' . implode(', ', $set) . ' WHERE evento_clinico_id = :evento');
            $stmt->execute($params);
            $after = array_merge($before, $changes);
            (new AuditService())->log($updatedBy, $environmentId, 'CONSULTAS', 'EDITAR', 'consultas_externas', $eventId, $before, $after);
        });
    }

    /** Actualiza el examen existente; no crea un examen duplicado. */
    public function updateClinicalExam(int $eventId, array $data, int $environmentId, int $updatedBy): void
    {
        $textFields = ['alimentacion', 'historial_reproductivo', 'ganglios_linfaticos', 'condicion_corporal', 'observaciones'];
        $numberFields = ['frecuencia_cardiaca', 'frecuencia_respiratoria', 'temperatura_c', 'tiempo_llenado_capilar_seg'];
        $booleanFields = ['vomitos', 'diarrea', 'tos'];
        $changes = [];
        foreach ($textFields as $field) {
            $input = $field === 'observaciones' ? 'examen_observaciones' : $field;
            if (array_key_exists($input, $data)) {
                if (!is_scalar($data[$input]) && $data[$input] !== null) {
                    throw new RuntimeException('Valor inválido para ' . $input . '.');
                }
                $value = trim((string) ($data[$input] ?? ''));
                $changes[$field] = $value === '' ? null : $value;
            }
        }
        foreach ($numberFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if ($value !== null && $value !== '' && (!is_scalar($value) || !is_numeric($value))) {
                    throw new RuntimeException('Número inválido para ' . $field . '.');
                }
                $changes[$field] = ($value === null || $value === '') ? null : (float) $value;
            }
        }
        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                if (!in_array((string) $data[$field], ['0', '1'], true)) {
                    throw new RuntimeException('Valor inválido para ' . $field . '.');
                }
                $changes[$field] = (int) $data[$field];
            }
        }
        if (!$changes) {
            throw new RuntimeException('No se recibieron campos del examen.');
        }
        Database::transaction(function (PDO $db) use ($eventId, $environmentId, $updatedBy, $changes) {
            $this->assertEditableConsultation($db, $eventId, $environmentId);
            $stmt = $db->prepare('SELECT * FROM examenes_clinicos_generales WHERE evento_clinico_id = :evento FOR UPDATE');
            $stmt->execute(['evento' => $eventId]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) {
                throw new RuntimeException('No existe un examen clínico para esta consulta.');
            }
            $set = [];
            $params = ['evento' => $eventId];
            foreach ($changes as $field => $value) {
                $set[] = $field . ' = :' . $field;
                $params[$field] = $value;
            }
            $stmt = $db->prepare('UPDATE examenes_clinicos_generales SET ' . implode(', ', $set) . ' WHERE evento_clinico_id = :evento');
            $stmt->execute($params);
            (new AuditService())->log($updatedBy, $environmentId, 'CONSULTAS', 'EDITAR_EXAMEN', 'examenes_clinicos_generales', $eventId, $before, array_merge($before, $changes));
        });
    }

    /** Agrega un diagnóstico nuevo sin modificar los anteriores. */
    public function addDiagnosis(int $eventId, array $data, int $environmentId, int $createdBy): int
    {
        $type = strtoupper(trim((string) ($data['tipo_codigo'] ?? '')));
        if (!in_array($type, ['DIFERENCIAL', 'PRESUNTIVO', 'DEFINITIVO'], true)) {
            throw new RuntimeException('Selecciona un tipo de diagnóstico válido.');
        }
        $description = trim((string) ($data['descripcion'] ?? ''));
        if ($description === '') {
            throw new RuntimeException('La descripción del diagnóstico es obligatoria.');
        }
        return Database::transaction(function (PDO $db) use ($eventId, $environmentId, $createdBy, $type, $description) {
            $this->assertEditableConsultation($db, $eventId, $environmentId);
            $typeId = $this->getDiagnosisTypeId($db, $type);
            $stmt = $db->prepare('INSERT INTO diagnosticos_clinicos (evento_clinico_id, tipo_diagnostico_id, descripcion, ingresado_por) VALUES (:evento, :tipo, :descripcion, :usuario)');
            $stmt->execute(['evento' => $eventId, 'tipo' => $typeId, 'descripcion' => $description, 'usuario' => $createdBy]);
            $id = (int) $db->lastInsertId();
            (new AuditService())->log($createdBy, $environmentId, 'CONSULTAS', 'AGREGAR_DIAGNOSTICO', 'diagnosticos_clinicos', $id, null, ['evento_clinico_id' => $eventId, 'tipo_codigo' => $type, 'descripcion' => $description]);
            return $id;
        });
    }

    private function assertEditableConsultation(PDO $db, int $eventId, int $environmentId): void
    {
        $stmt = $db->prepare('SELECT ec.id FROM eventos_clinicos ec INNER JOIN animales a ON a.id = ec.animal_id INNER JOIN tipos_evento_clinico tec ON tec.id = ec.tipo_evento_id INNER JOIN consultas_externas ce ON ce.evento_clinico_id = ec.id WHERE ec.id = :evento AND a.entorno_id = :entorno AND tec.codigo = "CONSULTA_EXTERNA" AND ec.anulado_at IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute(['evento' => $eventId, 'entorno' => $environmentId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Consulta inexistente, anulada o fuera del entorno activo.');
        }
    }
}
