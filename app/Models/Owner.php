<?php
namespace App\Models;use App\Core\Model;
class Owner extends Model
{
 public function countByEnvironment(int $e):int{$s=$this->db->prepare('SELECT COUNT(*) FROM propietarios_entornos pe JOIN propietarios p ON p.id=pe.propietario_id WHERE pe.entorno_id=:e AND pe.activo=1 AND p.activo=1 AND p.deleted_at IS NULL');$s->execute(['e'=>$e]);return(int)$s->fetchColumn();}
 public function listByEnvironment(int $e,string $q=''):array{$sql='SELECT p.*,pe.id AS propietario_entorno_id,COUNT(a.id) AS animales_count FROM propietarios_entornos pe JOIN propietarios p ON p.id=pe.propietario_id LEFT JOIN animales a ON a.propietario_entorno_id=pe.id AND a.activo=1 AND a.deleted_at IS NULL WHERE pe.entorno_id=:e AND pe.activo=1 AND p.activo=1 AND p.deleted_at IS NULL';$p=['e'=>$e];if($q!==''){$sql.=' AND CONCAT_WS(" ",p.nombres,p.apellidos,p.email,p.identificacion,p.celular) LIKE :q';$p['q']='%'.$q.'%';}$sql.=' GROUP BY p.id,pe.id ORDER BY p.apellidos,p.nombres LIMIT 200';$s=$this->db->prepare($sql);$s->execute($p);return$s->fetchAll();}
 public function options(int $e):array{$s=$this->db->prepare('SELECT pe.id,p.nombres,p.apellidos,p.identificacion FROM propietarios_entornos pe JOIN propietarios p ON p.id=pe.propietario_id WHERE pe.entorno_id=:e AND pe.activo=1 AND p.activo=1 AND p.deleted_at IS NULL ORDER BY p.apellidos,p.nombres');$s->execute(['e'=>$e]);return$s->fetchAll();}
}
