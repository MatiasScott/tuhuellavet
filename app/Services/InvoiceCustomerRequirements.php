<?php

namespace App\Services;

use RuntimeException;

final class InvoiceCustomerRequirements
{
    public static function problems(array $fiscal): array
    {
        $problems = [];
        if ((int) ($fiscal['activo'] ?? 0) !== 1) {
            $problems[] = 'los datos fiscales deben estar activos';
        }

        foreach ([
            'identificacion' => 'identificación fiscal',
            'razon_social' => 'razón social fiscal',
            'codigo_pais' => 'país del propietario',
            'codigo_provincia' => 'provincia del propietario',
            'codigo_canton' => 'cantón del propietario',
            'codigo_parroquia' => 'parroquia del propietario',
        ] as $field => $label) {
            if (trim((string) ($fiscal[$field] ?? '')) === '') {
                $problems[] = 'falta ' . $label;
            }
        }

        $email = trim((string) ($fiscal['email'] ?? ''));
        if ($email === '') {
            $problems[] = 'falta correo de facturación';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $problems[] = 'el correo de facturación no es válido';
        }

        $type = strtoupper(trim((string) ($fiscal['tipo_identificacion_codigo'] ?? '')));
        if (!in_array($type, ['CEDULA', 'RUC', 'PASAPORTE'], true)) {
            $problems[] = $type === 'CONSUMIDOR_FINAL'
                ? 'consumidor final todavía no está habilitado para emitir facturas en esta integración'
                : 'el tipo de identificación fiscal no está soportado para facturación';
        }

        $natural = $fiscal['es_persona_natural'] ?? null;
        if ($natural === null || $natural === '') {
            $problems[] = 'falta indicar si el propietario es persona natural o jurídica';
        } elseif (!in_array((string) $natural, ['0', '1'], true)) {
            $problems[] = 'el tipo de persona del propietario no es válido';
        } elseif (in_array($type, ['CEDULA', 'PASAPORTE'], true) && (string) $natural !== '1') {
            $problems[] = 'la cédula o el pasaporte fiscal requiere una persona natural';
        }

        return $problems;
    }

    public static function assertReady(array $fiscal): void
    {
        $problems = self::problems($fiscal);
        if ($problems !== []) {
            throw new RuntimeException(
                'Antes de emitir la factura, complete o corrija: '
                . implode('; ', $problems)
                . '. Revise la ficha del propietario y sus datos fiscales.'
            );
        }
    }
}
