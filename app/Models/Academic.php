<?php
namespace App\Models;use App\Core\Model;
class Academic extends Model
{
 public function courses(int $env):array{$s=$this->db->prepare('SELECT c.*,a.codigo AS asignatura_codigo,a.nombre AS asignatura,p.nombre AS periodo FROM cursos_academicos c JOIN asignaturas a ON a.id=c.asignatura_id JOIN periodos_academicos p ON p.id=c.periodo_academico_id WHERE c.entorno_id=:e AND c.activo=1 ORDER BY p.fecha_inicio DESC,a.nombre');$s->execute(['e'=>$env]);return$s->fetchAll();}
 public function periods():array{return$this->db->query('SELECT * FROM periodos_academicos WHERE activo=1 ORDER BY fecha_inicio DESC')->fetchAll();}
 public function subjects():array{return$this->db->query('SELECT * FROM asignaturas WHERE activo=1 ORDER BY nombre')->fetchAll();}
 public function cases(int $env):array{$s=$this->db->prepare('SELECT cc.*,a.nombre AS asignatura,c.codigo_seccion,CONCAT(u.nombres," ",u.apellidos) AS creado_por_nombre FROM casos_clinicos_academicos cc JOIN cursos_academicos c ON c.id=cc.curso_id JOIN asignaturas a ON a.id=c.asignatura_id JOIN usuarios u ON u.id=cc.creado_por WHERE c.entorno_id=:e AND cc.activo=1 ORDER BY cc.created_at DESC');$s->execute(['e'=>$env]);return$s->fetchAll();}
 public function assignments(int $user):array{$s=$this->db->prepare('SELECT ea.*,aa.nombre AS actividad,cc.titulo AS caso FROM entregas_academicas ea JOIN actividades_academicas aa ON aa.id=ea.actividad_id JOIN casos_clinicos_academicos cc ON cc.id=aa.caso_clinico_id WHERE ea.estudiante_usuario_id=:u ORDER BY ea.id DESC');$s->execute(['u'=>$user]);return$s->fetchAll();}
}
