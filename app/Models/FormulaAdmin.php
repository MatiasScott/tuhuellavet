<?php
namespace App\Models;use App\Core\Model;
class FormulaAdmin extends Model
{
 public function all(int $env):array{$s=$this->db->prepare('SELECT f.*,cf.nombre AS categoria,um.simbolo AS unidad,(SELECT MAX(numero_version) FROM formula_versiones WHERE formula_id=f.id) AS ultima_version,(SELECT efv.codigo FROM formula_versiones fv JOIN estados_formula_version efv ON efv.id=fv.estado_id WHERE fv.formula_id=f.id ORDER BY fv.numero_version DESC LIMIT 1) AS ultimo_estado FROM formulas f JOIN formula_entornos fe ON fe.formula_id=f.id JOIN categorias_formula cf ON cf.id=f.categoria_formula_id LEFT JOIN unidades_medida um ON um.id=f.unidad_resultado_id WHERE fe.entorno_id=:e AND f.deleted_at IS NULL ORDER BY f.nombre');$s->execute(['e'=>$env]);return$s->fetchAll();}
 public function categories():array{return$this->db->query('SELECT id,codigo,nombre FROM categorias_formula WHERE activo=1 ORDER BY nombre')->fetchAll();}
 public function variableTypes():array{return$this->db->query('SELECT id,codigo,nombre FROM tipos_variable_formula ORDER BY id')->fetchAll();}
 public function variableOrigins():array{return$this->db->query('SELECT id,codigo,nombre FROM origenes_variable_formula ORDER BY id')->fetchAll();}
 public function versions(int $formula):array{$s=$this->db->prepare('SELECT fv.*,efv.codigo AS estado_codigo,efv.nombre AS estado_nombre FROM formula_versiones fv JOIN estados_formula_version efv ON efv.id=fv.estado_id WHERE fv.formula_id=:f ORDER BY fv.numero_version DESC');$s->execute(['f'=>$formula]);return$s->fetchAll();}
}
