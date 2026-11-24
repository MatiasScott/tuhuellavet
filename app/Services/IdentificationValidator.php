<?php

declare(strict_types=1);

namespace App\Services;

final class IdentificationValidator
{
    public static function validateCedula(string $value): bool
    {
        $value = preg_replace('/[\s.\-]+/u', '', trim($value));

        if (!preg_match('/^\d{10}$/', $value)) {
            return false;
        }

        if (self::isAuthorizedException($value, 'CEDULA')) {
            return true;
        }

        $province = (int) substr($value, 0, 2);

        if ($province < 1 || ($province > 24 && $province !== 30)) {
            return false;
        }

        if ((int) $value[2] >= 6) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $value[$i];

            if ($i % 2 === 0) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $checkDigit === (int) $value[9];
    }

    public static function isAuthorizedException(
        string $value,
        string $type
    ): bool {
        $path = dirname(__DIR__) .
            '/Config/identification_exceptions.php';

        if (!is_file($path)) {
            return false;
        }

        $exceptions = require $path;

        if (!is_array($exceptions)) {
            return false;
        }

        $exception = $exceptions[$value] ?? null;

        return is_array($exception)
            && ($exception['tipo'] ?? '') === $type
            && !empty($exception['motivo'])
            && !empty($exception['autorizado_por'])
            && !empty($exception['fecha']);
    }
}
