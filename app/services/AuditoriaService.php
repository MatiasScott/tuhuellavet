<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Auditoria;

final class AuditoriaService
{
    public function __construct(private readonly Auditoria $model = new Auditoria())
    {
    }

    public function data(array $filters): array
    {
        $rows = $this->model->list($filters, 400);

        foreach ($rows as &$row) {
            $row['datos_anteriores_decoded'] = $this->decodeJson((string) ($row['datos_anteriores'] ?? ''));
            $row['datos_nuevos_decoded'] = $this->decodeJson((string) ($row['datos_nuevos'] ?? ''));
        }
        unset($row);

        return [
            'rows' => $rows,
            'usuarios' => $this->model->usuarios(),
            'empresas' => $this->model->empresas(),
            'modulos' => $this->model->modulos(),
            'acciones' => $this->model->acciones(),
        ];
    }

    private function decodeJson(string $value): mixed
    {
        if (trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
