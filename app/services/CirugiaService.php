<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cirugia;
use RuntimeException;

final class CirugiaService
{
    public function __construct(
        private readonly Cirugia $model = new Cirugia(),
        private readonly FileStorageService $files = new FileStorageService()
    ) {
    }

    public function data(int $empresaId): array
    {
        return [
            'animales' => $this->model->animalesByEmpresa($empresaId),
            'consultas' => $this->model->consultasByEmpresa($empresaId),
            'formulas' => $this->model->formulasByEmpresa($empresaId),
            'rows' => $this->model->listByEmpresa($empresaId),
        ];
    }

    public function create(int $empresaId, int $usuarioId, array $input, ?array $pdf): int
    {
        $animalId = (int) ($input['animal_id'] ?? 0);
        if ($animalId <= 0) {
            throw new RuntimeException('Debes seleccionar un paciente.');
        }

        $procedimiento = trim((string) ($input['procedimiento_quirurgico'] ?? ''));
        if ($procedimiento === '') {
            throw new RuntimeException('El procedimiento quirurgico es obligatorio.');
        }

        $fecha = $this->normalizeDateTime((string) ($input['fecha'] ?? ''));

        $id = $this->model->create([
            'empresa_id' => $empresaId,
            'animal_id' => $animalId,
            'consulta_id' => $this->toNullableInt($input['consulta_id'] ?? null),
            'procedimiento_quirurgico' => $procedimiento,
            'medico_responsable' => trim((string) ($input['medico_responsable'] ?? '')),
            'anestesia' => trim((string) ($input['anestesia'] ?? '')),
            'formula_medica' => trim((string) ($input['formula_medica'] ?? '')),
            'formula_id' => $this->toNullableInt($input['formula_id'] ?? null),
            'archivo_pdf' => null,
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'fecha' => $fecha,
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
        $stmt = $pdo->prepare('UPDATE cirugias SET archivo_pdf = :archivo_pdf, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');
        $stmt->execute([
            ':archivo_pdf' => $path,
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
    }

    private function normalizeDateTime(string $value): string
    {
        $dateTime = trim($value);
        if ($dateTime === '') {
            return date('Y-m-d H:i:s');
        }

        $dateTime = str_replace('T', ' ', $dateTime);
        if (strlen($dateTime) === 16) {
            $dateTime .= ':00';
        }

        return $dateTime;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
