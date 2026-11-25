<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class FiscalSyncService
{
    /**
     * Obtiene las facturas elegibles para sincronización.
     *
     * No realiza consultas a iConta ni modifica registros.
     */
    public function pendingDocuments(
        int $limit = 20
    ): array {

        $limit = max(1, min(100, $limit));

        $db = Database::connection();

        $stmt = $db->prepare(
            "
            SELECT
                idoc.id,
                idoc.documento_fiscal_id,
                idoc.iconta_id_documento,
                idoc.estado,
                idoc.ultima_consulta_sri_at,
                idoc.proxima_consulta_sri_at,
                idoc.errores_consulta_sri,
                df.archivo_pdf_id,
                df.archivo_xml_id,
                v.id AS venta_id,
                v.numero AS venta_numero

            FROM iconta_documentos idoc

            INNER JOIN documentos_fiscales df
                ON df.id = idoc.documento_fiscal_id

            INNER JOIN ventas v
                ON v.id = df.venta_id

            INNER JOIN entornos e
                ON e.id = v.entorno_id

            WHERE df.tipo_documento = 'FACTURA'

              AND v.es_simulada = 0

              AND e.permite_facturacion_real = 1

              AND idoc.iconta_id_documento IS NOT NULL

              AND TRIM(idoc.iconta_id_documento)
                  REGEXP '^[0-9]+$'

              AND TRIM(idoc.iconta_id_documento)
                  REGEXP '[1-9]'

              AND df.estado <> 'ANULADO'

              AND idoc.estado IN (
                  'GENERADA',
                  'POR_FIRMAR',
                  'ESPERA_ENVIO',
                  'ENVIADA_SRI',
                  'ESPERA_AUTORIZACION',
                  'NEGADO',
                  'AUTORIZADO'
              )

              AND (
                  idoc.proxima_consulta_sri_at IS NULL
                  OR idoc.proxima_consulta_sri_at <= NOW()
              )

            ORDER BY
                COALESCE(
                    idoc.proxima_consulta_sri_at,
                    idoc.created_at
                ) ASC,
                idoc.id ASC

            LIMIT :limite
            "
        );

        $stmt->bindValue(
            ':limite',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function assertTransition(string $fiscal, string $remoteLocal, string $incoming): void
    {
        $allowed = ['GENERADA', 'POR_FIRMAR', 'ESPERA_ENVIO', 'ENVIADA_SRI', 'ESPERA_AUTORIZACION', 'NEGADO', 'AUTORIZADO', 'ANULADO'];
        $incoming = strtoupper(trim($incoming));
        if (!in_array($incoming, $allowed, true)) {
            throw new \RuntimeException('Estado fiscal remoto no reconocido.');
        }
        foreach ([$fiscal, $remoteLocal] as $previous) {
            $previous = strtoupper(trim($previous));
            if (!in_array($previous, $allowed, true)) {
                throw new \RuntimeException('Estado fiscal local requiere conciliación.');
            }
            if (($previous === 'ANULADO' && $incoming !== 'ANULADO')
                || ($previous === 'AUTORIZADO' && !in_array($incoming, ['AUTORIZADO', 'ANULADO'], true))) {
                throw new \RuntimeException('Transición fiscal bloqueada: ' . $previous . ' -> ' . $incoming);
            }
        }
    }

    /**
     * Intervalo normal entre consultas satisfactorias.
     */
    public static function nextIntervalMinutes(
        string $status,
        ?int $pdfId,
        ?int $xmlId
    ): ?int {

        $status = strtoupper(trim($status));

        return match ($status) {
            'GENERADA',
            'POR_FIRMAR',
            'ESPERA_ENVIO',
            'ENVIADA_SRI',
            'ESPERA_AUTORIZACION' => 5,

            'NEGADO' => 60,

            'AUTORIZADO' => (
                $pdfId !== null
                && $pdfId > 0
                && $xmlId !== null
                && $xmlId > 0
            ) ? 1440 : 5,

            'ANULADO' => null,

            default => throw new \RuntimeException(
                'Estado fiscal no reconocido: ' . $status
            ),
        };
    }

    /**
     * Reintentos progresivos ante errores de consulta.
     */
    public static function retryIntervalMinutes(
        int $consecutiveErrors
    ): int {

        return match (true) {
            $consecutiveErrors <= 1 => 5,
            $consecutiveErrors === 2 => 10,
            $consecutiveErrors === 3 => 20,
            $consecutiveErrors === 4 => 40,
            $consecutiveErrors === 5 => 60,
            default => 180,
        };
    }
}