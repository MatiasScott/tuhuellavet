<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Formula;
use RuntimeException;

final class FormulaService
{
    public function __construct(
        private readonly Formula $model = new Formula(),
        private readonly FormulaEngineService $engine = new FormulaEngineService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function list(int $empresaId): array
    {
        return $this->model->listByEmpresa($empresaId);
    }

    public function find(int $id, int $empresaId): ?array
    {
        $formula = $this->model->findById($id, $empresaId);
        if (!is_array($formula)) {
            return null;
        }

        $formula['variables'] = $this->model->variablesByFormula((int) $formula['id']);

        return $formula;
    }

    public function create(int $empresaId, int $usuarioId, array $input, array $auditMeta = []): int
    {
        $payload = $this->normalizePayload($input);
        $formulaId = $this->model->create([
            'empresa_id' => $empresaId,
            'nombre' => $payload['nombre'],
            'descripcion' => $payload['descripcion'],
            'expresion_formula' => $payload['expresion_formula'],
            'formula' => $payload['expresion_formula'],
            'categoria' => $payload['categoria'],
            'estado' => $payload['estado'],
            'created_by' => $usuarioId,
        ]);

        $variables = $this->buildVariablesFromExpression($payload['expresion_formula']);
        $this->model->replaceVariables($formulaId, $variables);

        $this->audit->log(array_merge($auditMeta, [
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'modulo' => 'formulas',
            'accion' => 'crear',
            'tabla_afectada' => 'formulas',
            'registro_id' => $formulaId,
            'datos_nuevos' => [
                'nombre' => $payload['nombre'],
                'categoria' => $payload['categoria'],
                'expresion_formula' => $payload['expresion_formula'],
                'variables' => $variables,
            ],
        ]));

        return $formulaId;
    }

    public function update(int $id, int $empresaId, int $usuarioId, array $input, array $auditMeta = []): void
    {
        $current = $this->find($id, $empresaId);
        if (!is_array($current)) {
            throw new RuntimeException('Formula no encontrada.');
        }

        $payload = $this->normalizePayload($input);
        $this->model->update($id, $empresaId, [
            'nombre' => $payload['nombre'],
            'descripcion' => $payload['descripcion'],
            'expresion_formula' => $payload['expresion_formula'],
            'formula' => $payload['expresion_formula'],
            'categoria' => $payload['categoria'],
            'estado' => $payload['estado'],
            'updated_by' => $usuarioId,
        ]);

        $variables = $this->buildVariablesFromExpression($payload['expresion_formula']);
        $this->model->replaceVariables($id, $variables);

        $this->audit->log(array_merge($auditMeta, [
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'modulo' => 'formulas',
            'accion' => 'editar',
            'tabla_afectada' => 'formulas',
            'registro_id' => $id,
            'datos_anteriores' => $current,
            'datos_nuevos' => [
                'nombre' => $payload['nombre'],
                'categoria' => $payload['categoria'],
                'expresion_formula' => $payload['expresion_formula'],
                'variables' => $variables,
            ],
        ]));
    }

    public function delete(int $id, int $empresaId, int $usuarioId, array $auditMeta = []): void
    {
        $current = $this->find($id, $empresaId);
        if (!is_array($current)) {
            throw new RuntimeException('Formula no encontrada.');
        }

        $this->model->delete($id, $empresaId);

        $this->audit->log(array_merge($auditMeta, [
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'modulo' => 'formulas',
            'accion' => 'eliminar',
            'tabla_afectada' => 'formulas',
            'registro_id' => $id,
            'datos_anteriores' => $current,
        ]));
    }

    public function setEstado(int $id, int $empresaId, int $usuarioId, bool $activo, array $auditMeta = []): void
    {
        $current = $this->find($id, $empresaId);
        if (!is_array($current)) {
            throw new RuntimeException('Formula no encontrada.');
        }

        $estado = $activo ? 1 : 0;
        $this->model->setEstado($id, $empresaId, $estado, $usuarioId);

        $this->audit->log(array_merge($auditMeta, [
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'modulo' => 'formulas',
            'accion' => $activo ? 'activar' : 'desactivar',
            'tabla_afectada' => 'formulas',
            'registro_id' => $id,
            'datos_anteriores' => ['estado' => $current['estado'] ?? null],
            'datos_nuevos' => ['estado' => $estado],
        ]));
    }

    public function detectVariables(string $expression): array
    {
        return $this->buildVariablesFromExpression($expression);
    }

    public function test(string $expression, array $inputValues): array
    {
        $variables = $this->buildVariablesFromExpression($expression);
        $values = [];

        foreach ($variables as $variable) {
            $name = $variable['variable'];
            $rawValue = $inputValues[$name] ?? null;
            if ($rawValue === null || $rawValue === '') {
                throw new RuntimeException('Debes ingresar el valor de ' . $name . '.');
            }

            if (!is_numeric($rawValue)) {
                throw new RuntimeException('El valor de ' . $name . ' debe ser numerico.');
            }

            $values[$name] = (float) $rawValue;
        }

        $result = $this->engine->evaluate($expression, $values);

        return [
            'variables' => $variables,
            'input_values' => $values,
            'resultado' => $result,
        ];
    }

    private function normalizePayload(array $input): array
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $expresion = trim((string) ($input['expresion_formula'] ?? ''));
        $categoria = trim((string) ($input['categoria'] ?? 'general'));

        if ($nombre === '') {
            throw new RuntimeException('El nombre de la formula es obligatorio.');
        }

        if ($expresion === '') {
            throw new RuntimeException('La expresion de la formula es obligatoria.');
        }

        // Valida sintaxis antes de guardar.
        $this->engine->evaluate($expresion, array_fill_keys($this->engine->detectVariables($expresion), 1));

        return [
            'nombre' => $nombre,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'expresion_formula' => $expresion,
            'categoria' => $categoria === '' ? 'general' : $categoria,
            'estado' => isset($input['estado']) ? 1 : 0,
        ];
    }

    private function buildVariablesFromExpression(string $expression): array
    {
        $variables = $this->engine->detectVariables($expression);

        return array_map(static function (string $name): array {
            return [
                'variable' => $name,
                'etiqueta' => ucwords(str_replace('_', ' ', $name)),
                'tipo_input' => 'number',
                'obligatorio' => 1,
                'valor_default' => null,
            ];
        }, $variables);
    }
}
