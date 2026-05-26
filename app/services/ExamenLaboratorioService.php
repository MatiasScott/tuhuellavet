<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenLaboratorio;
use RuntimeException;

final class ExamenLaboratorioService
{
    public function __construct(
        private readonly ExamenLaboratorio $model = new ExamenLaboratorio(),
        private readonly FileStorageService $files = new FileStorageService()
    ) {
    }

    public function data(int $empresaId): array
    {
        return [
            'animales' => $this->model->animalesByEmpresa($empresaId),
            'consultas' => $this->model->consultasByEmpresa($empresaId),
            'rows' => $this->model->listByEmpresa($empresaId),
        ];
    }

    public function create(int $empresaId, int $usuarioId, array $input, ?array $pdf): int
    {
        $animalId = (int) ($input['animal_id'] ?? 0);
        if ($animalId <= 0) {
            throw new RuntimeException('Debes seleccionar un paciente.');
        }

        $tipoExamen = trim((string) ($input['tipo_examen'] ?? ''));
        if ($tipoExamen === '') {
            throw new RuntimeException('El tipo de examen es obligatorio.');
        }

        $id = $this->model->create([
            'empresa_id' => $empresaId,
            'animal_id' => $animalId,
            'consulta_id' => $this->toNullableInt($input['consulta_id'] ?? null),
            'tipo_examen' => $tipoExamen,
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'resultado' => trim((string) ($input['resultado'] ?? '')),
            'archivo_pdf' => null,
            'usuario_id' => $usuarioId,
        ]);

        if (is_array($pdf) && (int) ($pdf['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->uploadDocument($pdf, 'documentos', $id, [
                'allowed_extensions' => ['pdf'],
                'allowed_mimes' => ['application/pdf', 'application/x-pdf'],
            ]);

            $this->updatePdf($id, $empresaId, $stored['path']);
        }

        return $id;
    }

    private function updatePdf(int $id, int $empresaId, string $path): void
    {
        $pdo = \App\Core\Database::connection((array) config('database'));
        $stmt = $pdo->prepare('UPDATE examenes_laboratorio SET archivo_pdf = :archivo_pdf, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');
        $stmt->execute([
            ':archivo_pdf' => $path,
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
