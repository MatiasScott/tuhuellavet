<?php

declare(strict_types=1);

namespace App\Helpers;

final class ValidationHelper
{
    public static function required(array $data, array $fields): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $value = $data[$field] ?? null;

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[$field] = 'El campo es obligatorio.';
            }
        }

        return $errors;
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
