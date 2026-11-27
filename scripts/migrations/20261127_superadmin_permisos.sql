-- AXXIS v1.57.0
-- Eliminar asignaciones redundantes de permisos de Hacienda
-- al rol global SUPER_ADMINISTRADOR.
-- No elimina permisos del catálogo ni asignaciones de otros roles.

START TRANSACTION;

DELETE rp
FROM rol_permisos rp
INNER JOIN roles r ON r.id = rp.rol_id
INNER JOIN permisos p ON p.id = rp.permiso_id
INNER JOIN modulos m ON m.id = p.modulo_id
WHERE r.codigo = 'SUPER_ADMINISTRADOR'
  AND r.es_global = 1
  AND m.codigo IN (
      'LOTES_ANIMALES',
      'PRODUCCION',
      'GENEALOGIA',
      'ALIMENTACION'
  );

SELECT ROW_COUNT() AS asignaciones_eliminadas;

SELECT COUNT(*) AS asignaciones_superadmin_restantes
FROM rol_permisos rp
INNER JOIN roles r ON r.id = rp.rol_id
WHERE r.codigo = 'SUPER_ADMINISTRADOR';

COMMIT;