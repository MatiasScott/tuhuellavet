<?php
namespace App\Models;use App\Core\Model;
class AdminRepository extends Model
{
 public function users():array{return$this->db->query('SELECT u.*,GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ", ") AS roles FROM usuarios u LEFT JOIN usuarios_entornos_roles uer ON uer.usuario_id=u.id LEFT JOIN roles r ON r.id=uer.rol_id WHERE u.deleted_at IS NULL GROUP BY u.id ORDER BY u.apellidos,u.nombres')->fetchAll();}
 public function companies():array{return$this->db->query('SELECT e.*,COUNT(en.id) AS entornos_count FROM empresas e LEFT JOIN entornos en ON en.empresa_id=e.id WHERE e.deleted_at IS NULL GROUP BY e.id ORDER BY e.nombre')->fetchAll();}
 public function roles():array{return$this->db->query('SELECT * FROM roles WHERE activo=1 ORDER BY id')->fetchAll();}
 public function permissions():array{return$this->db->query('SELECT p.id,p.codigo,m.nombre AS modulo,a.nombre AS accion FROM permisos p JOIN modulos m ON m.id=p.modulo_id JOIN acciones_permiso a ON a.id=p.accion_id WHERE p.activo=1 ORDER BY m.orden,a.id')->fetchAll();}
 public function rolePermissions(int $role):array{$s=$this->db->prepare('SELECT permiso_id FROM rol_permisos WHERE rol_id=:r');$s->execute(['r'=>$role]);return array_map('intval',array_column($s->fetchAll(),'permiso_id'));}
 public function audit(int $env,int $limit=300):array{$s=$this->db->prepare('SELECT a.*,CONCAT(u.nombres," ",u.apellidos) AS usuario FROM auditoria a LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE a.entorno_id=:e OR a.entorno_id IS NULL ORDER BY a.created_at DESC LIMIT '.$limit);$s->execute(['e'=>$env]);return$s->fetchAll();}
}
