<?php
namespace App\Models;
use App\Core\Model;

class Catalog extends Model
{
    private function rows(string $sql, array $params=[]): array { $s=$this->db->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    public function species(): array { return $this->rows('SELECT id,codigo,nombre_comun FROM especies WHERE activo=1 ORDER BY nombre_comun'); }
    public function breeds(?int $speciesId=null): array { $sql='SELECT id,especie_id,nombre FROM razas WHERE activo=1';$p=[]; if($speciesId){$sql.=' AND especie_id=:e';$p['e']=$speciesId;} return $this->rows($sql.' ORDER BY nombre',$p); }
    public function sexes(): array { return $this->rows('SELECT id,codigo,nombre FROM sexos_animales WHERE activo=1 ORDER BY id'); }
    public function identificationTypes(): array { return $this->rows('SELECT id,codigo,nombre FROM tipos_identificacion WHERE activo=1 ORDER BY nombre'); }
    public function vaccines(): array { return $this->rows('SELECT id,nombre FROM vacunas WHERE activo=1 ORDER BY nombre'); }
    public function drugs(): array { return $this->rows('SELECT id,nombre FROM farmacos WHERE activo=1 ORDER BY nombre'); }
    public function drugPresentations(): array { return $this->rows('SELECT fp.id,fp.farmaco_id,fp.nombre_comercial,ff.nombre AS forma FROM farmaco_presentaciones fp LEFT JOIN formas_farmaceuticas ff ON ff.id=fp.forma_farmaceutica_id WHERE fp.activo=1 ORDER BY fp.nombre_comercial'); }
    public function administrationRoutes(): array { return $this->rows('SELECT id,codigo,nombre FROM vias_administracion WHERE activo=1 ORDER BY nombre'); }
    public function administrationFrequencies(): array { return $this->rows('SELECT id,codigo,nombre,intervalo_horas FROM frecuencias_administracion WHERE activo=1 ORDER BY nombre'); }
    public function measurementUnits(): array { return $this->rows('SELECT id,codigo,nombre,simbolo,categoria FROM unidades_medida ORDER BY nombre'); }
    public function timeUnits(): array { return $this->rows('SELECT id,codigo,nombre FROM unidades_tiempo ORDER BY nombre'); }
    public function treatmentTypes(): array { return $this->rows('SELECT id,codigo,nombre FROM tipos_tratamiento ORDER BY id'); }
    public function labExamTypes(): array { return $this->rows('SELECT id,nombre,descripcion FROM tipos_examen_laboratorio WHERE activo=1 ORDER BY nombre'); }
    public function surgeryProcedures(): array { return $this->rows('SELECT id,nombre,descripcion FROM procedimientos_quirurgicos WHERE activo=1 ORDER BY nombre'); }
    public function anesthesiaTypes(): array { return $this->rows('SELECT id,codigo,nombre FROM tipos_anestesia WHERE activo=1 ORDER BY nombre'); }
    public function surgicalFunctions(): array { return $this->rows('SELECT id,codigo,nombre FROM funciones_equipo_quirurgico WHERE activo=1 ORDER BY nombre'); }
    public function appointmentStatuses(): array { return $this->rows('SELECT id,codigo,nombre FROM estados_cita WHERE activo=1 ORDER BY id'); }
    public function inventoryMovementTypes(): array { return $this->rows('SELECT id,codigo,nombre,factor FROM tipos_movimiento_inventario ORDER BY id'); }
    public function productCategories(): array { return $this->rows('SELECT id,nombre FROM categorias_producto WHERE activo=1 ORDER BY nombre'); }
    public function services(): array { return $this->rows('SELECT id,codigo,nombre,precio_base FROM servicios WHERE activo=1 ORDER BY nombre'); }
    public function paymentMethods(): array { return $this->rows('SELECT id,codigo,nombre FROM metodos_pago WHERE activo=1 ORDER BY nombre'); }
    public function users(): array { return $this->rows('SELECT id,nombres,apellidos,email FROM usuarios WHERE activo=1 AND deleted_at IS NULL ORDER BY apellidos,nombres'); }
    public function roles(): array { return $this->rows('SELECT id,codigo,nombre,descripcion,es_global,protegido,activo FROM roles ORDER BY id'); }
}
