<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardData;

final class DashboardService
{
    public function __construct(private readonly DashboardData $model = new DashboardData())
    {
    }

    public function buildData(int $userId, int $empresaId): array
    {
        $roleContext = $this->model->roleAndContext($userId, $empresaId);

        $rolId = (int) ($roleContext['rol_id'] ?? 0);
        $rolNombre = (string) ($roleContext['rol_nombre'] ?? 'Sin rol');
        $empresaTipo = (string) ($roleContext['empresa_tipo'] ?? 'global');
        $roleCode = $this->normalizeRoleCode($rolNombre);

        $widgets = $rolId > 0
            ? $this->model->widgetsByRoleAndContext($rolId, $empresaTipo)
            : [];

        if ($roleCode === 'cliente') {
            $clientContext = $this->model->clientContext($userId, $empresaId);
            $propietarioId = (int) ($clientContext['id'] ?? 0);
            $mascotas = $propietarioId > 0 ? $this->model->clientMascotas($propietarioId, $empresaId) : [];
            $consultas = $propietarioId > 0 ? $this->model->clientConsultas($propietarioId, $empresaId) : [];
            $vacunas = $propietarioId > 0 ? $this->model->clientVacunas($propietarioId, $empresaId) : [];
            $desparasitaciones = $propietarioId > 0 ? $this->model->clientDesparasitaciones($propietarioId, $empresaId) : [];
            $cirugias = $propietarioId > 0 ? $this->model->clientCirugias($propietarioId, $empresaId) : [];
            $examenes = $propietarioId > 0 ? $this->model->clientExamenes($propietarioId, $empresaId) : [];
            $timeline = $propietarioId > 0 ? $this->model->clientTimeline($propietarioId, $empresaId) : [];
            $documents = $propietarioId > 0 ? $this->model->clientDocuments($propietarioId, $empresaId) : [];

            $animals = [];
            foreach ($mascotas as $mascota) {
                if (!is_array($mascota)) {
                    continue;
                }

                $animalId = (int) ($mascota['id'] ?? 0);
                $animalName = trim((string) ($mascota['nombre'] ?? 'Mascota'));

                $animals[] = [
                    'id' => $animalId,
                    'nombre' => $animalName,
                    'datos' => $mascota,
                    'consultas' => $this->filterByAnimalName($consultas, $animalName),
                    'vacunas' => $this->filterByAnimalName($vacunas, $animalName),
                    'desparasitaciones' => $this->filterByAnimalName($desparasitaciones, $animalName),
                    'cirugias' => $this->filterByAnimalName($cirugias, $animalName),
                    'examenes' => $this->filterByAnimalName($examenes, $animalName),
                    'timeline' => $this->filterByAnimalName($timeline, $animalName),
                    'documents' => $this->filterByAnimalName($documents, $animalName),
                ];
            }

            return [
                'roleCode' => $roleCode,
                'rolNombre' => $rolNombre,
                'empresaTipo' => $empresaTipo,
                'widgets' => [],
                'client' => [
                    'propietario' => $clientContext,
                    'mascotas' => $mascotas,
                    'consultas' => $consultas,
                    'vacunas' => $vacunas,
                    'desparasitaciones' => $desparasitaciones,
                    'cirugias' => $cirugias,
                    'examenes' => $examenes,
                    'timeline' => $timeline,
                    'documents' => $documents,
                    'animals' => $animals,
                ],
            ];
        }

        return [
            'roleCode' => $roleCode,
            'rolNombre' => $rolNombre,
            'empresaTipo' => $empresaTipo,
            'widgets' => $widgets,
            'metrics' => [
                'animales' => $this->model->totalAnimales($empresaId),
                'propietarios' => $this->model->totalPropietarios($empresaId),
                'consultas_hoy' => $this->model->consultasHoy($empresaId),
                'hospitalizaciones_activas' => $this->model->hospitalizacionesActivas($empresaId),
                'vacunas_proximas_7_dias' => $this->model->vacunasProximas7Dias($empresaId),
                'examenes_mes' => $this->model->examenesMes($empresaId),
                'cirugias_mes' => $this->model->cirugiasMes($empresaId),
                'eventos_timeline_7_dias' => $this->model->eventosTimeline7Dias($empresaId),
            ],
            'datasets' => [
                'pacientes_recientes' => $this->model->pacientesRecientes($empresaId),
                'vacunas_proximas' => $this->model->vacunasProximasDetalle($empresaId),
                'hospitalizaciones_activas' => $this->model->hospitalizacionesActivasDetalle($empresaId),
                'cirugias_recientes' => $this->model->cirugiasRecientes($empresaId),
                'timeline_reciente' => $this->model->timelineReciente($empresaId),
            ],
        ];
    }

    public function clientProfile(int $userId, int $empresaId): ?array
    {
        return $this->model->clientProfile($userId, $empresaId);
    }

    public function clientAnimal(int $animalId, int $empresaId): ?array
    {
        return $this->model->clientAnimal($animalId, $empresaId);
    }

    public function clientAnimalDetail(int $userId, int $empresaId, int $animalId): ?array
    {
        $profile = $this->model->clientProfile($userId, $empresaId);
        if (!is_array($profile)) {
            return null;
        }

        $animal = $this->model->clientAnimal($animalId, $empresaId);
        if (!is_array($animal) || (int) ($animal['propietario_id'] ?? 0) !== (int) ($profile['id'] ?? 0)) {
            return null;
        }

        $animalName = trim((string) ($animal['nombre'] ?? 'Mascota'));
        $consultas = $this->filterByAnimalName($this->model->clientConsultas((int) $profile['id'], $empresaId), $animalName);
        $vacunas = $this->filterByAnimalName($this->model->clientVacunas((int) $profile['id'], $empresaId), $animalName);
        $desparasitaciones = $this->filterByAnimalName($this->model->clientDesparasitaciones((int) $profile['id'], $empresaId), $animalName);
        $cirugias = $this->filterByAnimalName($this->model->clientCirugias((int) $profile['id'], $empresaId), $animalName);
        $examenes = $this->filterByAnimalName($this->model->clientExamenes((int) $profile['id'], $empresaId), $animalName);
        $timeline = $this->filterByAnimalName($this->model->clientTimeline((int) $profile['id'], $empresaId), $animalName);
        $documents = $this->filterByAnimalName($this->model->clientDocuments((int) $profile['id'], $empresaId), $animalName);

        return [
            'profile' => $profile,
            'animal' => $animal,
            'consultas' => $consultas,
            'vacunas' => $vacunas,
            'desparasitaciones' => $desparasitaciones,
            'cirugias' => $cirugias,
            'examenes' => $examenes,
            'timeline' => $timeline,
            'documents' => $documents,
        ];
    }

    private function filterByAnimalName(array $records, string $animalName): array
    {
        $filtered = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $recordAnimal = trim((string) ($record['animal'] ?? ''));
            if ($recordAnimal !== '' && strcasecmp($recordAnimal, $animalName) === 0) {
                $filtered[] = $record;
            }
        }

        return $filtered;
    }

    private function normalizeRoleCode(string $rolNombre): string
    {
        $normalized = strtolower(trim($rolNombre));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'super_administrador' => 'super_administrador',
            'administrador' => 'administrador',
            'cliente', 'clientes' => 'cliente',
            default => 'invitado',
        };
    }
}
