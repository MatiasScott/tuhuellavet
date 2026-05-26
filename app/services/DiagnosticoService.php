<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Diagnostico;
use App\Services\AuditService;
use RuntimeException;

final class DiagnosticoService
{
    public function __construct(
        private readonly Diagnostico $model = new Diagnostico(),
        private readonly AuditService $audit = new AuditService()
    )
    {
    }

    public function data(int $empresaId): array
    {
        return [
            'catalogo' => $this->model->catalogo(),
            'consultas' => $this->model->consultasByEmpresa($empresaId),
            'asignaciones' => $this->model->asignacionesByEmpresa($empresaId),
        ];
    }

    public function createCatalogo(array $input): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del diagnostico es obligatorio.');
        }

        $tipo = (string) ($input['tipo'] ?? 'diferencial');
        if (!in_array($tipo, ['diferencial', 'preventivo', 'definitivo'], true)) {
            throw new RuntimeException('Tipo de diagnostico invalido.');
        }

        return $this->model->createCatalogo([
            'codigo' => trim((string) ($input['codigo'] ?? '')),
            'nombre' => $nombre,
            'tipo' => $tipo,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);
    }

    public function updateCatalogo(int $id, int $usuarioId, int $empresaId, array $input, array $auditMeta = []): void
    {
        $current = $this->model->findCatalogoById($id);
        if (!is_array($current)) {
            throw new RuntimeException('Diagnostico no encontrado.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del diagnostico es obligatorio.');
        }

        $tipo = (string) ($input['tipo'] ?? 'diferencial');
        if (!in_array($tipo, ['diferencial', 'preventivo', 'definitivo'], true)) {
            throw new RuntimeException('Tipo de diagnostico invalido.');
        }

        $payload = [
            'codigo' => trim((string) ($input['codigo'] ?? '')),
            'nombre' => $nombre,
            'tipo' => $tipo,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'estado' => isset($input['estado']) ? 1 : 0,
        ];

        $this->model->updateCatalogo($id, $payload);

        $this->audit->log(array_merge($auditMeta, [
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'modulo' => 'diagnosticos',
            'accion' => 'editar',
            'tabla_afectada' => 'catalogo_diagnosticos',
            'registro_id' => $id,
            'datos_anteriores' => $current,
            'datos_nuevos' => $payload,
        ]));
    }

    public function deleteCatalogo(int $id, int $usuarioId, int $empresaId, array $auditMeta = []): void
    {
        $current = $this->model->findCatalogoById($id);
        if (!is_array($current)) {
            throw new RuntimeException('Diagnostico no encontrado.');
        }

        $this->model->deleteCatalogo($id);

        $this->audit->log(array_merge($auditMeta, [
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'modulo' => 'diagnosticos',
            'accion' => 'eliminar',
            'tabla_afectada' => 'catalogo_diagnosticos',
            'registro_id' => $id,
            'datos_anteriores' => $current,
        ]));
    }

    public function asignar(int $usuarioId, array $input): int
    {
        $consultaId = (int) ($input['consulta_id'] ?? 0);
        $diagnosticoId = (int) ($input['diagnostico_id'] ?? 0);

        if ($consultaId <= 0) {
            throw new RuntimeException('Debes seleccionar una consulta.');
        }

        if ($diagnosticoId <= 0) {
            throw new RuntimeException('Debes seleccionar un diagnostico del catalogo.');
        }

        return $this->model->createAsignacion([
            'consulta_id' => $consultaId,
            'diagnostico_id' => $diagnosticoId,
            'usuario_id' => $usuarioId,
            'observacion' => trim((string) ($input['observacion'] ?? '')),
        ]);
    }
}
