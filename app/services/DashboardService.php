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

        $widgets = $rolId > 0
            ? $this->model->widgetsByRoleAndContext($rolId, $empresaTipo)
            : [];

        return [
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
}
