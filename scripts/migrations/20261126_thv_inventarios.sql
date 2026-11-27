-- Ejecutar primero en local. No mueve existencias ni modifica inventarios existentes.
INSERT INTO inventarios (entorno_id,nombre,descripcion,activo)
SELECT e.id,n.nombre,'Área operativa Tu Huella Vet',1
FROM entornos e CROSS JOIN (
 SELECT 'Inventario Tu Huella Vet - Farmacia' AS nombre
 UNION ALL SELECT 'Inventario Tu Huella Vet - Bodega'
 UNION ALL SELECT 'Inventario Tu Huella Vet - Veterinaria'
 UNION ALL SELECT 'Inventario Tu Huella Vet - Hospitalización'
) n
WHERE e.codigo='TU_HUELLA_VET' AND e.activo=1 AND e.deleted_at IS NULL
AND e.es_productivo=1 AND e.permite_facturacion_real=1
AND NOT EXISTS (SELECT 1 FROM inventarios i WHERE i.entorno_id=e.id AND i.nombre=n.nombre);
