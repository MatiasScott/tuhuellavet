<?php

namespace App\Models;

use App\Core\Model;

class Billing extends Model
{
    public function sales(int $e): array
    {
        $s = $this->db->prepare('SELECT v.*,a.nombre AS paciente,p.nombres AS propietario_nombres,p.apellidos AS propietario_apellidos FROM ventas v LEFT JOIN animales a ON a.id=v.animal_id LEFT JOIN propietarios_entornos pe ON pe.id=v.propietario_entorno_id LEFT JOIN propietarios p ON p.id=pe.propietario_id WHERE v.entorno_id=:e ORDER BY v.fecha DESC LIMIT 250');
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }
    public function documents(int $e): array
    {
        $s = $this->db->prepare('SELECT df.*,v.total,cd.contifico_id,cd.estado AS contifico_estado FROM documentos_fiscales df JOIN ventas v ON v.id=df.venta_id LEFT JOIN contifico_documentos cd ON cd.documento_fiscal_id=df.id WHERE v.entorno_id=:e ORDER BY df.id DESC');
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }
}
