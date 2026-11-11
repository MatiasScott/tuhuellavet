<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class PhysiologicalExam
{
    public const FIELDS = [
        'sonido' => 'Sonido',
        'ritmo_cardiaco' => 'Ritmo cardiaco',
        'mucosas' => 'Mucosas',
        'llenado_capilar' => 'Llenado capilar (descripcion)',
        'retorno_pliegue_cutaneo' => 'Retorno del pliegue cutaneo',
        'deshidratacion' => 'Deshidratacion',
        'presion_arterial' => 'Presion arterial',
        'saturacion_oxigeno' => 'Saturacion de oxigeno',
    ];

    public static function values(array $data): array
    {
        $values = [];
        foreach (self::FIELDS as $field => $label) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (!is_scalar($data[$field]) && $data[$field] !== null) {
                throw new RuntimeException('Valor no valido para ' . $label . '.');
            }
            $value = trim((string) $data[$field]);
            $max = in_array($field, ['sonido', 'ritmo_cardiaco', 'mucosas'], true) ? 100 : 10000;
            if (mb_strlen($value) > $max) {
                throw new RuntimeException('El campo ' . $label . ' excede ' . $max . ' caracteres.');
            }
            $values[$field] = $value === '' ? null : $value;
        }
        return $values;
    }
}
