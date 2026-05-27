<?php
ob_start();
?>
<?php

$pageTitle = 'Portal Cliente';
?>
<?php
$userSafe = isset($user) && is_array($user) ? $user : [];
$empresaIdSafe = isset($empresaId) ? (int) $empresaId : 0;
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$rolNombreSafe = isset($rolNombre) ? (string) $rolNombre : 'Cliente';
$empresaTipoSafe = isset($empresaTipo) ? (string) $empresaTipo : 'portal';
$clientSafe = isset($client) && is_array($client) ? $client : [];
$pageStyles = [asset('css/dashboard.css'), asset('css/portal-cliente.css')];

$propietarioSafe = isset($clientSafe['propietario']) && is_array($clientSafe['propietario']) ? $clientSafe['propietario'] : [];
$mascotasSafe = isset($clientSafe['mascotas']) && is_array($clientSafe['mascotas']) ? $clientSafe['mascotas'] : [];
$consultasSafe = isset($clientSafe['consultas']) && is_array($clientSafe['consultas']) ? $clientSafe['consultas'] : [];
$vacunasSafe = isset($clientSafe['vacunas']) && is_array($clientSafe['vacunas']) ? $clientSafe['vacunas'] : [];
$desparasitacionesSafe = isset($clientSafe['desparasitaciones']) && is_array($clientSafe['desparasitaciones']) ? $clientSafe['desparasitaciones'] : [];
$cirugiasSafe = isset($clientSafe['cirugias']) && is_array($clientSafe['cirugias']) ? $clientSafe['cirugias'] : [];
$examenesSafe = isset($clientSafe['examenes']) && is_array($clientSafe['examenes']) ? $clientSafe['examenes'] : [];
$timelineSafe = isset($clientSafe['timeline']) && is_array($clientSafe['timeline']) ? $clientSafe['timeline'] : [];
$documentsSafe = isset($clientSafe['documents']) && is_array($clientSafe['documents']) ? $clientSafe['documents'] : [];
$animalsSafe = isset($clientSafe['animals']) && is_array($clientSafe['animals']) ? $clientSafe['animals'] : [];

$nombreCliente = trim((string) ($propietarioSafe['nombres'] ?? ''));
$apellidoCliente = trim((string) ($propietarioSafe['apellidos'] ?? ''));
$fotoCliente = trim((string) ($propietarioSafe['foto'] ?? ''));
$fotoClienteUrl = $fotoCliente !== '' ? url('/imagen/ver?path=' . rawurlencode($fotoCliente)) : '';
$mascotaPortada = isset($mascotasSafe[0]) && is_array($mascotasSafe[0]) ? $mascotasSafe[0] : [];
?>

<main class="cliente-dashboard">
    <section class="card cliente-hero p-4" id="inicio">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-8">
                <span class="cliente-badge mb-3"><i class="bi bi-heart-pulse"></i> Portal cliente activo</span>
                <h1 class="display-6 fw-bold mb-2">Bienvenido <?php echo htmlspecialchars(trim($nombreCliente . ' ' . $apellidoCliente)); ?></h1>
                <p class="mb-3">Aqui tienes el resumen de tus mascotas, sus controles clinicos y los documentos mas recientes en un solo lugar.</p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="cliente-chip"><i class="bi bi-emoji-smile"></i><?php echo count($mascotasSafe); ?> mascotas</span>
                    <span class="cliente-chip"><i class="bi bi-clipboard2-pulse"></i><?php echo count($consultasSafe); ?> consultas</span>
                    <span class="cliente-chip"><i class="bi bi-shield-check"></i><?php echo count($vacunasSafe); ?> vacunas registradas</span>
                </div>
            </div>
            <div class="col-12 col-lg-4 text-lg-end">
                <div class="cliente-card p-3 bg-white text-dark shadow-sm">
                    <p class="cliente-muted mb-1">Empresa activa</p>
                    <h2 class="h5 mb-1"><?php echo htmlspecialchars($empresaTipoSafe); ?></h2>
                    <small class="cliente-muted">Rol: <?php echo htmlspecialchars($rolNombreSafe); ?></small>
                    <?php if ($fotoClienteUrl !== ''): ?>
                        <div class="mt-3">
                            <img src="<?php echo htmlspecialchars($fotoClienteUrl); ?>" alt="Foto de perfil" style="width:88px;height:88px;object-fit:cover;border-radius:20px;border:1px solid rgba(0,0,0,.08);">
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?php echo htmlspecialchars(url('/portal/perfil/foto')); ?>" enctype="multipart/form-data" class="mt-3 text-start">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                        <label class="form-label small mb-1">Actualizar foto de perfil</label>
                        <input class="form-control form-control-sm mb-2" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required>
                        <button class="btn btn-sm btn-light w-100" type="submit">Subir foto</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-6 col-lg-3">
            <div class="cliente-metric">
                <p class="cliente-metric-label">Mascotas</p>
                <p class="cliente-metric-value"><?php echo count($mascotasSafe); ?></p>
                <small class="cliente-muted">Total vinculadas a tu portal</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="cliente-metric">
                <p class="cliente-metric-label">Consultas</p>
                <p class="cliente-metric-value"><?php echo count($consultasSafe); ?></p>
                <small class="cliente-muted">Historial clinico visible</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="cliente-metric">
                <p class="cliente-metric-label">Vacunas</p>
                <p class="cliente-metric-value"><?php echo count($vacunasSafe); ?></p>
                <small class="cliente-muted">Aplicadas y proximas</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="cliente-metric">
                <p class="cliente-metric-label">Documentos</p>
                <p class="cliente-metric-value"><?php echo count($documentsSafe); ?></p>
                <small class="cliente-muted">PDF y resultados recientes</small>
            </div>
        </div>
    </section>

    <section class="row g-3" id="mis-mascotas">
        <div class="col-12">
            <div class="cliente-section-title">
                <h3>Mis mascotas</h3>
                <small class="cliente-muted">Cada mascota tiene su propio historial y acciones</small>
            </div>
        </div>
        <?php foreach ($animalsSafe as $animal): ?>
            <?php
            $mascota = isset($animal['datos']) && is_array($animal['datos']) ? $animal['datos'] : [];
            $fotoMascota = trim((string) ($mascota['foto'] ?? ''));
            $fotoMascotaUrl = $fotoMascota !== '' ? url('/imagen/ver?path=' . rawurlencode($fotoMascota)) : '';
            $consultasAnimal = isset($animal['consultas']) && is_array($animal['consultas']) ? $animal['consultas'] : [];
            $vacunasAnimal = isset($animal['vacunas']) && is_array($animal['vacunas']) ? $animal['vacunas'] : [];
            $desparasitacionesAnimal = isset($animal['desparasitaciones']) && is_array($animal['desparasitaciones']) ? $animal['desparasitaciones'] : [];
            $cirugiasAnimal = isset($animal['cirugias']) && is_array($animal['cirugias']) ? $animal['cirugias'] : [];
            $examenesAnimal = isset($animal['examenes']) && is_array($animal['examenes']) ? $animal['examenes'] : [];
            $timelineAnimal = isset($animal['timeline']) && is_array($animal['timeline']) ? $animal['timeline'] : [];
            $documentsAnimal = isset($animal['documents']) && is_array($animal['documents']) ? $animal['documents'] : [];
            ?>
            <div class="col-12">
                <article class="cliente-card p-3">
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-md-4 col-xl-3">
                            <div class="cliente-pet-card">
                                <?php if ($fotoMascotaUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($fotoMascotaUrl); ?>" alt="Foto de <?php echo htmlspecialchars((string) ($mascota['nombre'] ?? 'Mascota')); ?>">
                                <?php else: ?>
                                    <div style="height:210px;background:linear-gradient(135deg,#dfeaf4,#f8fbfd);"></div>
                                <?php endif; ?>
                                <div class="body">
                                    <h4 class="h6 mb-1"><?php echo htmlspecialchars((string) ($mascota['nombre'] ?? 'Mascota')); ?></h4>
                                    <p class="cliente-muted mb-2"><?php echo htmlspecialchars(trim((string) ($mascota['especie'] ?? '') . ' · ' . (string) ($mascota['raza'] ?? ''))); ?></p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="cliente-chip">Sexo: <?php echo htmlspecialchars((string) ($mascota['sexo'] ?? 'N/D')); ?></span>
                                        <span class="cliente-chip">Peso: <?php echo htmlspecialchars((string) ($mascota['peso_actual'] ?? '0')); ?> kg</span>
                                    </div>
                                    <form method="post" action="<?php echo htmlspecialchars(url('/portal/mascotas/foto')); ?>" enctype="multipart/form-data" class="mt-3">
                                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                        <input type="hidden" name="animal_id" value="<?php echo (int) ($mascota['id'] ?? 0); ?>">
                                        <label class="form-label small mb-1">Actualizar foto</label>
                                        <input class="form-control form-control-sm mb-2" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required>
                                        <button class="btn btn-sm btn-outline-primary w-100" type="submit">Guardar foto</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-8 col-xl-9">
                            <div class="cliente-section-title">
                                <h3>Historial de <?php echo htmlspecialchars((string) ($mascota['nombre'] ?? 'Mascota')); ?></h3>
                                <small class="cliente-muted"><a href="<?php echo htmlspecialchars(url('/portal/mascotas/detalle?id=' . (int) ($mascota['id'] ?? 0))); ?>">Ver detalle completo</a></small>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-xl-4">
                                    <div class="cliente-list">
                                        <strong class="mb-1 d-block">Consultas</strong>
                                        <?php foreach ($consultasAnimal as $consulta): ?>
                                            <div class="cliente-item">
                                                <strong><?php echo htmlspecialchars((string) ($consulta['motivo_consulta'] ?? 'Consulta')); ?></strong>
                                                <small><?php echo htmlspecialchars((string) ($consulta['fecha_consulta'] ?? '')); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($consultasAnimal === []): ?><div class="cliente-item"><small class="cliente-muted">Sin consultas.</small></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-4">
                                    <div class="cliente-list">
                                        <strong class="mb-1 d-block">Vacunas</strong>
                                        <?php foreach ($vacunasAnimal as $vacuna): ?>
                                            <div class="cliente-item">
                                                <strong><?php echo htmlspecialchars((string) ($vacuna['vacuna'] ?? 'Vacuna')); ?></strong>
                                                <small><?php echo htmlspecialchars((string) ($vacuna['fecha_aplicacion'] ?? '')); ?> · <?php echo htmlspecialchars((string) ($vacuna['proxima_aplicacion'] ?? '')); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($vacunasAnimal === []): ?><div class="cliente-item"><small class="cliente-muted">Sin vacunas.</small></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-4">
                                    <div class="cliente-list">
                                        <strong class="mb-1 d-block">Documentos</strong>
                                        <?php foreach ($documentsAnimal as $documento): ?>
                                            <div class="cliente-item">
                                                <strong><?php echo htmlspecialchars((string) ($documento['titulo'] ?? 'Documento')); ?></strong>
                                                <?php if (!empty($documento['archivo_pdf'])): ?>
                                                    <small><a href="<?php echo htmlspecialchars(url('/pdf/ver?path=' . rawurlencode((string) $documento['archivo_pdf']))); ?>" target="_blank" rel="noopener">Ver PDF</a></small>
                                                <?php else: ?>
                                                    <small class="cliente-muted">Sin archivo adjunto.</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($documentsAnimal === []): ?><div class="cliente-item"><small class="cliente-muted">Sin documentos.</small></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-4">
                                    <div class="cliente-list">
                                        <strong class="mb-1 d-block">Desparasitacion</strong>
                                        <?php foreach ($desparasitacionesAnimal as $desparasitacion): ?>
                                            <div class="cliente-item">
                                                <strong><?php echo htmlspecialchars((string) ($desparasitacion['farmaco'] ?? 'Producto')); ?></strong>
                                                <small><?php echo htmlspecialchars((string) ($desparasitacion['fecha'] ?? '')); ?> · <?php echo htmlspecialchars((string) ($desparasitacion['proxima_fecha'] ?? '')); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($desparasitacionesAnimal === []): ?><div class="cliente-item"><small class="cliente-muted">Sin desparasitaciones.</small></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-4">
                                    <div class="cliente-list">
                                        <strong class="mb-1 d-block">Cirugias</strong>
                                        <?php foreach ($cirugiasAnimal as $cirugia): ?>
                                            <div class="cliente-item">
                                                <strong><?php echo htmlspecialchars((string) ($cirugia['procedimiento_quirurgico'] ?? 'Cirugia')); ?></strong>
                                                <small><?php echo htmlspecialchars((string) ($cirugia['fecha'] ?? '')); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($cirugiasAnimal === []): ?><div class="cliente-item"><small class="cliente-muted">Sin cirugias.</small></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-4">
                                    <div class="cliente-list">
                                        <strong class="mb-1 d-block">Timeline</strong>
                                        <?php foreach ($timelineAnimal as $evento): ?>
                                            <div class="cliente-item">
                                                <strong><?php echo htmlspecialchars((string) ($evento['titulo'] ?? 'Evento')); ?></strong>
                                                <small><?php echo htmlspecialchars((string) ($evento['fecha_evento'] ?? '')); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($timelineAnimal === []): ?><div class="cliente-item"><small class="cliente-muted">Sin eventos.</small></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-xl-4">
                                    <div class="cliente-list">
                                        <strong class="mb-1 d-block">Examenes</strong>
                                        <?php foreach ($examenesAnimal as $examen): ?>
                                            <div class="cliente-item">
                                                <strong><?php echo htmlspecialchars((string) ($examen['tipo_examen'] ?? 'Examen')); ?></strong>
                                                <small><?php echo htmlspecialchars((string) ($examen['created_at'] ?? '')); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($examenesAnimal === []): ?><div class="cliente-item"><small class="cliente-muted">Sin examenes.</small></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
        <?php if ($animalsSafe === []): ?>
            <div class="col-12"><div class="cliente-card p-3">Aun no tienes mascotas registradas en el portal.</div></div>
        <?php endif; ?>
    </section>

    <section class="row g-3 mt-1" id="perfil">
        <div class="col-12 col-lg-5">
            <article class="cliente-card p-3 h-100">
                <div class="cliente-section-title"><h3>Perfil</h3><small class="cliente-muted">Datos del portal</small></div>
                <div class="cliente-list">
                    <div class="cliente-item"><strong><?php echo htmlspecialchars(trim($nombreCliente . ' ' . $apellidoCliente)); ?></strong><small><?php echo htmlspecialchars((string) ($propietarioSafe['email'] ?? '')); ?></small></div>
                    <div class="cliente-item"><small class="cliente-muted">Empresa ID: <?php echo $empresaIdSafe; ?></small></div>
                    <div class="cliente-item"><small class="cliente-muted">Usuario: <?php echo htmlspecialchars((string) ($userSafe['email'] ?? '')); ?></small></div>
                </div>
            </article>
        </div>
        <div class="col-12 col-lg-7" id="timeline">
            <article class="cliente-card p-3 h-100">
                <div class="cliente-section-title"><h3>Timeline medico</h3><small class="cliente-muted">Eventos recientes</small></div>
                <div class="cliente-list">
                    <?php foreach ($timelineSafe as $item): ?>
                        <div class="cliente-item">
                            <strong><?php echo htmlspecialchars((string) ($item['titulo'] ?? 'Evento')); ?></strong>
                            <small><?php echo htmlspecialchars((string) ($item['animal'] ?? 'Mascota')); ?> · <?php echo htmlspecialchars((string) ($item['fecha_evento'] ?? '')); ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($timelineSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin eventos recientes.</small></div><?php endif; ?>
                </div>
            </article>
        </div>
    </section>
</main>
<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
