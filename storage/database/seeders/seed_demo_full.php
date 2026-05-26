<?php

declare(strict_types=1);

$envPath = dirname(__DIR__, 3) . '/.env';
$env = parse_ini_file($envPath, false, INI_SCANNER_RAW);

if (!is_array($env)) {
    throw new RuntimeException('No se pudo leer el archivo .env');
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        (string) $env['DB_HOST'],
        (string) $env['DB_PORT'],
        (string) $env['DB_DATABASE']
    ),
    (string) $env['DB_USERNAME'],
    (string) $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$adminUserId = (int) $pdo->query('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($adminUserId <= 0) {
    throw new RuntimeException('No existe usuario base. Debes crear al menos 1 usuario en la tabla usuarios.');
}

$tablesToTruncate = [
    'dashboard_widget_roles',
    'dashboard_widgets',
    'fluidoterapia',
    'hospitalizaciones',
    'tamanos_animales',
    'cirugias',
    'examenes_laboratorio',
    'desparasitaciones',
    'consulta_diagnosticos',
    'catalogo_diagnosticos',
    'consulta_examen_general',
    'animal_pesos',
    'archivos',
    'historial_clinico_cambios',
    'notificaciones',
    'citas',
    'movimientos_inventario',
    'productos',
    'categorias_productos',
    'formula_historial',
    'consulta_medicamentos',
    'formula_variables',
    'formulas',
    'medicamentos',
    'animal_vacunas',
    'catalogo_vacunas',
    'vacunas',
    'consultas',
    'animales',
    'razas',
    'especies',
    'categorias_animales',
    'propietarios',
    'rol_permisos',
    'permisos',
    'modulos',
    'usuarios_empresas',
    'roles',
    'empresas',
    'auditoria',
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tablesToTruncate as $table) {
    $pdo->exec('TRUNCATE TABLE ' . $table);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$insert = static function (PDO $pdo, string $table, array $rows): void {
    if ($rows === []) {
        return;
    }

    $columns = array_keys($rows[0]);
    $placeholders = '(' . implode(', ', array_map(static fn ($c) => ':' . $c, $columns)) . ')';
    $sql = sprintf('INSERT INTO %s (%s) VALUES %s', $table, implode(', ', $columns), $placeholders);
    $stmt = $pdo->prepare($sql);

    foreach ($rows as $row) {
        $stmt->execute($row);
    }
};

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'nombre' => $i === 1 ? 'Super Administrador' : 'Rol ' . $i,
        'descripcion' => $i === 1 ? 'Acceso total al sistema' : 'Rol de prueba ' . $i,
    ];
}
$insert($pdo, 'roles', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'nombre' => 'Empresa ' . $i,
        'tipo' => $i % 2 === 0 ? 'hacienda' : 'veterinaria',
        'direccion' => 'Direccion ' . $i,
        'telefono' => '3000000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'email' => 'empresa' . $i . '@demo.local',
        'logo' => 'storage/uploads/empresas/logo_' . $i . '.webp',
        'estado' => 1,
    ];
}
$insert($pdo, 'empresas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'usuario_id' => $adminUserId,
        'empresa_id' => $i,
        'rol_id' => 1,
    ];
}
$insert($pdo, 'usuarios_empresas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'nombre' => 'Modulo ' . $i,
        'slug' => 'modulo_' . $i,
        'descripcion' => 'Modulo de prueba ' . $i,
    ];
}
$insert($pdo, 'modulos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'modulo_id' => $i,
        'nombre' => 'Permiso ' . $i,
        'slug' => 'permiso_' . $i,
    ];
}
$insert($pdo, 'permisos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'rol_id' => 1,
        'permiso_id' => $i,
    ];
}
$insert($pdo, 'rol_permisos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = ['nombre' => 'Categoria Animal ' . $i];
}
$insert($pdo, 'categorias_animales', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'categoria_id' => $i,
        'nombre' => 'Especie ' . $i,
    ];
}
$insert($pdo, 'especies', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'especie_id' => $i,
        'nombre' => 'Raza ' . $i,
    ];
}
$insert($pdo, 'razas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'nombres' => 'Propietario' . $i,
        'apellidos' => 'Apellido' . $i,
        'identificacion' => 'CC' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        'telefono' => '601555' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'celular' => '311555' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
        'email' => 'prop' . $i . '@demo.local',
        'direccion' => 'Barrio Demo ' . $i,
        'usuario_id' => null,
        'foto' => 'storage/uploads/propietarios/prop_' . $i . '.webp',
        'portal_cliente_activo' => 1,
        'estado' => 1,
    ];
}
$insert($pdo, 'propietarios', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'propietario_id' => $i,
        'especie_id' => $i,
        'raza_id' => $i,
        'codigo' => 'ANM' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'nombre' => 'Paciente ' . $i,
        'sexo' => $i % 2 === 0 ? 'hembra' : 'macho',
        'fecha_nacimiento' => date('Y-m-d', strtotime('-' . (200 + $i) . ' days')),
        'peso_actual' => number_format(2.5 + $i, 2, '.', ''),
        'color' => 'Color ' . $i,
        'microchip' => 'MC' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        'foto' => 'storage/uploads/animales/animal_' . $i . '.webp',
        'observaciones' => 'Observacion animal ' . $i,
        'estado' => 1,
    ];
}
$insert($pdo, 'animales', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'animal_id' => $i,
        'veterinario_id' => $adminUserId,
        'fecha_consulta' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
        'motivo_consulta' => 'Motivo consulta ' . $i,
        'anamnesis' => 'Anamnesis ' . $i,
        'antecedentes' => 'Antecedentes ' . $i,
        'diagnostico' => 'Diagnostico textual ' . $i,
        'tratamiento' => 'Tratamiento base ' . $i,
        'recomendaciones' => 'Recomendaciones ' . $i,
        'tratamiento_clinico' => 'Tratamiento clinico ' . $i,
        'tratamiento_casa' => 'Tratamiento casa ' . $i,
        'observaciones' => 'Observaciones ' . $i,
        'peso' => number_format(2.5 + $i, 2, '.', ''),
        'temperatura' => number_format(37.5 + ($i % 4) * 0.3, 2, '.', ''),
        'frecuencia_cardiaca' => number_format(75 + $i, 2, '.', ''),
        'frecuencia_respiratoria' => number_format(22 + $i, 2, '.', ''),
        'estado' => 'abierta',
    ];
}
$insert($pdo, 'consultas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'nombre' => 'Vacuna ' . $i,
        'descripcion' => 'Descripcion vacuna ' . $i,
    ];
}
$insert($pdo, 'vacunas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'nombre' => 'Catalogo Vacuna ' . $i,
        'descripcion' => 'Catalogo de vacuna ' . $i,
        'estado' => 1,
    ];
}
$insert($pdo, 'catalogo_vacunas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'animal_id' => $i,
        'vacuna_id' => $i,
        'catalogo_vacuna_id' => $i,
        'dosis' => '1 ml',
        'laboratorio' => 'Lab ' . $i,
        'lote' => 'LOT' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'consulta_id' => $i,
        'fecha_aplicacion' => date('Y-m-d', strtotime('-' . $i . ' days')),
        'proxima_aplicacion' => date('Y-m-d', strtotime('+' . (20 + $i) . ' days')),
        'observaciones' => 'Aplicacion vacuna ' . $i,
        'usuario_id' => $adminUserId,
    ];
}
$insert($pdo, 'animal_vacunas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'nombre' => 'Medicamento ' . $i,
        'descripcion' => 'Descripcion medicamento ' . $i,
        'foto' => 'storage/uploads/medicamentos/med_' . $i . '.webp',
        'concentracion' => '10mg/ml',
        'unidad' => 'ml',
        'stock_actual' => number_format(40 + $i, 2, '.', ''),
        'estado' => 1,
    ];
}
$insert($pdo, 'medicamentos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'medicamento_id' => $i,
        'nombre' => 'Formula ' . $i,
        'formula' => '(peso * dosis) / concentracion',
        'unidad_resultado' => 'ml',
        'descripcion' => 'Formula demo ' . $i,
        'estado' => 1,
    ];
}
$insert($pdo, 'formulas', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'formula_id' => $i,
        'variable' => 'peso',
        'etiqueta' => 'Peso actual',
        'tipo' => 'decimal',
        'unidad' => 'kg',
        'automatico' => 1,
        'origen' => 'animales.peso_actual',
        'valor_default' => number_format(2.5 + $i, 2, '.', ''),
    ];
}
$insert($pdo, 'formula_variables', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'consulta_id' => $i,
        'medicamento_id' => $i,
        'formula_id' => $i,
        'dosis_calculada' => number_format(0.5 + $i / 10, 2, '.', ''),
        'frecuencia' => 'Cada 12 horas',
        'duracion' => '5 dias',
        'observaciones' => 'Tratamiento de prueba ' . $i,
    ];
}
$insert($pdo, 'consulta_medicamentos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'formula_id' => $i,
        'consulta_medicamento_id' => $i,
        'usuario_id' => $adminUserId,
        'resultado' => number_format(1.1 + $i / 5, 2, '.', ''),
        'datos' => json_encode(['peso' => 3 + $i, 'dosis' => 1.2, 'concentracion' => 10], JSON_UNESCAPED_UNICODE),
    ];
}
$insert($pdo, 'formula_historial', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = ['nombre' => 'Categoria Producto ' . $i];
}
$insert($pdo, 'categorias_productos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'categoria_id' => $i,
        'codigo' => 'PRD' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
        'nombre' => 'Producto ' . $i,
        'descripcion' => 'Producto demo ' . $i,
        'foto' => 'storage/uploads/productos/prod_' . $i . '.webp',
        'unidad' => 'unidad',
        'stock_minimo' => number_format(5 + $i, 2, '.', ''),
        'precio_compra' => number_format(10 + $i, 2, '.', ''),
        'precio_venta' => number_format(15 + $i, 2, '.', ''),
        'estado' => 1,
    ];
}
$insert($pdo, 'productos', $rows);

$types = ['entrada', 'salida', 'ajuste'];
$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'producto_id' => $i,
        'tipo' => $types[$i % 3],
        'cantidad' => number_format(2 + $i, 2, '.', ''),
        'observacion' => 'Movimiento ' . $i,
        'usuario_id' => $adminUserId,
    ];
}
$insert($pdo, 'movimientos_inventario', $rows);

$states = ['pendiente', 'confirmada', 'atendida', 'cancelada'];
$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'animal_id' => $i,
        'veterinario_id' => $adminUserId,
        'fecha_inicio' => date('Y-m-d H:i:s', strtotime('+' . $i . ' days')),
        'fecha_fin' => date('Y-m-d H:i:s', strtotime('+' . $i . ' days +1 hour')),
        'motivo' => 'Cita de control ' . $i,
        'estado' => $states[$i % 4],
    ];
}
$insert($pdo, 'citas', $rows);

$notifTypes = ['email', 'sms', 'whatsapp', 'interna'];
$notifStates = ['pendiente', 'enviado', 'error'];
$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'propietario_id' => $i,
        'tipo' => $notifTypes[$i % 4],
        'titulo' => 'Recordatorio ' . $i,
        'mensaje' => 'Mensaje de notificacion ' . $i,
        'fecha_programada' => date('Y-m-d H:i:s', strtotime('+' . $i . ' days')),
        'fecha_envio' => null,
        'estado' => $notifStates[$i % 3],
    ];
}
$insert($pdo, 'notificaciones', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'usuario_id' => $adminUserId,
        'modulo' => 'modulo_' . $i,
        'accion' => 'accion_' . $i,
        'tabla_afectada' => 'tabla_' . $i,
        'registro_id' => $i,
        'datos_anteriores' => json_encode(['estado' => 'anterior'], JSON_UNESCAPED_UNICODE),
        'datos_nuevos' => json_encode(['estado' => 'nuevo'], JSON_UNESCAPED_UNICODE),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Seeder/1.0',
    ];
}
$insert($pdo, 'auditoria', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'consulta_id' => $i,
        'usuario_id' => $adminUserId,
        'campo_modificado' => 'campo_' . $i,
        'valor_anterior' => 'valor viejo ' . $i,
        'valor_nuevo' => 'valor nuevo ' . $i,
    ];
}
$insert($pdo, 'historial_clinico_cambios', $rows);

$entities = ['animal', 'consulta', 'propietario', 'producto', 'medicamento'];
$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $entity = $entities[$i % count($entities)];
    $rows[] = [
        'empresa_id' => $i,
        'entidad' => $entity,
        'entidad_id' => $i,
        'tipo_archivo' => $i % 2 === 0 ? 'imagen' : 'pdf',
        'nombre_original' => 'archivo_original_' . $i . '.pdf',
        'nombre_archivo' => 'archivo_' . $i . '.pdf',
        'ruta' => 'storage/uploads/documentos/archivo_' . $i . '.pdf',
        'extension' => 'pdf',
        'mime' => 'application/pdf',
        'tamano_bytes' => 102400 + ($i * 100),
        'ancho' => null,
        'alto' => null,
        'metadata' => json_encode(['fuente' => 'seed_demo', 'indice' => $i], JSON_UNESCAPED_UNICODE),
        'subido_por' => $adminUserId,
    ];
}
$insert($pdo, 'archivos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'animal_id' => $i,
        'consulta_id' => $i,
        'peso' => number_format(3.2 + $i, 2, '.', ''),
        'fecha_registro' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
        'usuario_id' => $adminUserId,
        'observacion' => 'Control peso ' . $i,
    ];
}
$insert($pdo, 'animal_pesos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'consulta_id' => $i,
        'alimentacion' => 'Alimentacion ' . $i,
        'historial_reproductivo' => 'Historial reproductivo ' . $i,
        'frecuencia_cardiaca' => number_format(75 + $i, 2, '.', ''),
        'frecuencia_respiratoria' => number_format(20 + $i, 2, '.', ''),
        'temperatura' => number_format(37.5 + ($i % 3) * 0.4, 2, '.', ''),
        'tiempo_llenado_capilar' => '2 seg',
        'ganglios_linfaticos' => 'Sin alteraciones',
        'condicion_corporal' => 'CC ' . (($i % 5) + 1),
        'vomitos' => $i % 2,
        'diarrea' => ($i + 1) % 2,
        'tos' => $i % 3 === 0 ? 1 : 0,
    ];
}
$insert($pdo, 'consulta_examen_general', $rows);

$diagTypes = ['diferencial', 'preventivo', 'definitivo'];
$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'codigo' => 'DX' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'nombre' => 'Diagnostico ' . $i,
        'tipo' => $diagTypes[$i % 3],
        'descripcion' => 'Catalogo diagnostico ' . $i,
        'estado' => 1,
    ];
}
$insert($pdo, 'catalogo_diagnosticos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'consulta_id' => $i,
        'diagnostico_id' => $i,
        'usuario_id' => $adminUserId,
        'observacion' => 'Observacion diagnostico ' . $i,
    ];
}
$insert($pdo, 'consulta_diagnosticos', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'animal_id' => $i,
        'farmaco' => 'Farmaco ' . $i,
        'dosis' => '5 mg/kg',
        'fecha' => date('Y-m-d', strtotime('-' . $i . ' days')),
        'proxima_fecha' => date('Y-m-d', strtotime('+' . (30 + $i) . ' days')),
        'observacion' => 'Desparasitacion ' . $i,
        'peso_actual' => number_format(3 + $i, 2, '.', ''),
        'usuario_id' => $adminUserId,
    ];
}
$insert($pdo, 'desparasitaciones', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $min = number_format(($i - 1) * 2, 2, '.', '');
    $max = number_format($i * 2, 2, '.', '');
    $rows[] = [
        'nombre' => 'Tamano ' . $i,
        'peso_min' => $min,
        'peso_max' => $max,
        'estado' => 1,
    ];
}
$insert($pdo, 'tamanos_animales', $rows);

$hStates = ['activa', 'alta', 'traslado', 'cancelada'];
$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'animal_id' => $i,
        'consulta_id' => $i,
        'fecha_ingreso' => date('Y-m-d H:i:s', strtotime('-' . (2 * $i) . ' hours')),
        'fecha_salida' => $i % 2 === 0 ? date('Y-m-d H:i:s', strtotime('-' . $i . ' hours')) : null,
        'motivo' => 'Motivo hospitalizacion ' . $i,
        'estado' => $hStates[$i % 4],
        'observaciones' => 'Observaciones hospitalizacion ' . $i,
        'usuario_id' => $adminUserId,
    ];
}
$insert($pdo, 'hospitalizaciones', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'hospitalizacion_id' => $i,
        'tamano_animal_id' => $i,
        'mantenimiento' => number_format(20 + $i, 2, '.', ''),
        'rehidratacion' => number_format(10 + $i, 2, '.', ''),
        'formula' => 'mantenimiento + rehidratacion',
        'formulas_medicas' => 'Formula medica ' . $i,
        'signos_clinicos' => 'Signos clinicos ' . $i,
        'observaciones' => 'Observacion fluidoterapia ' . $i,
    ];
}
$insert($pdo, 'fluidoterapia', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'animal_id' => $i,
        'consulta_id' => $i,
        'tipo_examen' => 'Examen ' . $i,
        'observaciones' => 'Observacion examen ' . $i,
        'resultado' => 'Resultado examen ' . $i,
        'archivo_pdf' => 'storage/uploads/documentos/examen_' . $i . '.pdf',
        'usuario_id' => $adminUserId,
    ];
}
$insert($pdo, 'examenes_laboratorio', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'empresa_id' => $i,
        'animal_id' => $i,
        'consulta_id' => $i,
        'procedimiento_quirurgico' => 'Procedimiento ' . $i,
        'medico_responsable' => 'Medico ' . $i,
        'anestesia' => 'General',
        'formula_medica' => 'Formula medica pre y post',
        'formula_id' => $i,
        'archivo_pdf' => 'storage/uploads/documentos/cirugia_' . $i . '.pdf',
        'observaciones' => 'Observaciones cirugia ' . $i,
        'fecha' => date('Y-m-d H:i:s', strtotime('-' . (3 * $i) . ' days')),
        'usuario_id' => $adminUserId,
    ];
}
$insert($pdo, 'cirugias', $rows);

$contexts = ['veterinaria', 'hacienda', 'global'];
$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'codigo' => 'widget_' . $i,
        'nombre' => 'Widget ' . $i,
        'contexto' => $contexts[$i % 3],
        'modulo_origen' => 'modulo_' . $i,
        'activo' => 1,
    ];
}
$insert($pdo, 'dashboard_widgets', $rows);

$rows = [];
for ($i = 1; $i <= 20; $i++) {
    $rows[] = [
        'widget_id' => $i,
        'rol_id' => 1,
        'orden' => $i,
        'visible' => 1,
    ];
}
$insert($pdo, 'dashboard_widget_roles', $rows);

$tablesToCheck = [
    'roles',
    'empresas',
    'usuarios_empresas',
    'modulos',
    'permisos',
    'rol_permisos',
    'categorias_animales',
    'especies',
    'razas',
    'propietarios',
    'animales',
    'consultas',
    'vacunas',
    'catalogo_vacunas',
    'animal_vacunas',
    'medicamentos',
    'formulas',
    'formula_variables',
    'consulta_medicamentos',
    'formula_historial',
    'categorias_productos',
    'productos',
    'movimientos_inventario',
    'citas',
    'notificaciones',
    'auditoria',
    'historial_clinico_cambios',
    'archivos',
    'animal_pesos',
    'consulta_examen_general',
    'catalogo_diagnosticos',
    'consulta_diagnosticos',
    'desparasitaciones',
    'tamanos_animales',
    'hospitalizaciones',
    'fluidoterapia',
    'examenes_laboratorio',
    'cirugias',
    'dashboard_widgets',
    'dashboard_widget_roles',
];

foreach ($tablesToCheck as $table) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    echo $table . '=' . $count . PHP_EOL;
}

echo 'seed_demo_full=OK' . PHP_EOL;
