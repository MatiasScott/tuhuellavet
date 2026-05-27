<?php
ob_start();
?>
<?php

$pageTitle = 'Detalle de mascota';
?>
<?php
$detailSafe = isset($detail) && is_array($detail) ? $detail : [];
$userSafe = isset($user) && is_array($user) ? $user : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$pageStyles = [asset('css/dashboard.css'), asset('css/portal-cliente.css')];

$profileSafe = isset($detailSafe['profile']) && is_array($detailSafe['profile']) ? $detailSafe['profile'] : [];
$animalSafe = isset($detailSafe['animal']) && is_array($detailSafe['animal']) ? $detailSafe['animal'] : [];
$consultasSafe = isset($detailSafe['consultas']) && is_array($detailSafe['consultas']) ? $detailSafe['consultas'] : [];
$vacunasSafe = isset($detailSafe['vacunas']) && is_array($detailSafe['vacunas']) ? $detailSafe['vacunas'] : [];
$desparasitacionesSafe = isset($detailSafe['desparasitaciones']) && is_array($detailSafe['desparasitaciones']) ? $detailSafe['desparasitaciones'] : [];
$cirugiasSafe = isset($detailSafe['cirugias']) && is_array($detailSafe['cirugias']) ? $detailSafe['cirugias'] : [];
$examenesSafe = isset($detailSafe['examenes']) && is_array($detailSafe['examenes']) ? $detailSafe['examenes'] : [];
$timelineSafe = isset($detailSafe['timeline']) && is_array($detailSafe['timeline']) ? $detailSafe['timeline'] : [];
$documentsSafe = isset($detailSafe['documents']) && is_array($detailSafe['documents']) ? $detailSafe['documents'] : [];
$petNavigationSafe = isset($petNavigation) && is_array($petNavigation) ? $petNavigation : [];
$previousPet = isset($petNavigationSafe['previous']) && is_array($petNavigationSafe['previous']) ? $petNavigationSafe['previous'] : null;
$nextPet = isset($petNavigationSafe['next']) && is_array($petNavigationSafe['next']) ? $petNavigationSafe['next'] : null;
$navigationTotal = isset($petNavigationSafe['total']) ? (int) $petNavigationSafe['total'] : 0;
$navigationCurrent = isset($petNavigationSafe['current_index']) ? (int) $petNavigationSafe['current_index'] : null;

$fotoMascota = trim((string) ($animalSafe['foto'] ?? ''));
$fotoMascotaUrl = $fotoMascota !== '' ? url('/imagen/ver?path=' . rawurlencode($fotoMascota)) : '';
?>

<main class="container py-4 cliente-dashboard">
    <section class="cliente-card p-3 mb-2 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 mb-1">Detalle de <?php echo htmlspecialchars((string) ($animalSafe['nombre'] ?? 'Mascota')); ?></h1>
            <p class="cliente-muted mb-0">Vista individual del paciente dentro del portal cliente.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/portal#mis-mascotas')); ?>">Volver al portal</a>
    </section>

    <section class="cliente-card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong class="d-block">Mascota <?php echo $navigationCurrent !== null ? (int) $navigationCurrent + 1 : 1; ?> de <?php echo $navigationTotal > 0 ? $navigationTotal : 1; ?></strong>
                <small class="cliente-muted">Navega entre tus mascotas sin salir de la historia clínica.</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($previousPet !== null): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/portal/mascotas/detalle?id=' . (int) ($previousPet['id'] ?? 0))); ?>">Anterior: <?php echo htmlspecialchars((string) ($previousPet['nombre'] ?? 'Mascota')); ?></a>
                <?php endif; ?>
                <?php if ($nextPet !== null): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/portal/mascotas/detalle?id=' . (int) ($nextPet['id'] ?? 0))); ?>">Siguiente: <?php echo htmlspecialchars((string) ($nextPet['nombre'] ?? 'Mascota')); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-12 col-lg-4">
            <article class="cliente-pet-card">
                <?php if ($fotoMascotaUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($fotoMascotaUrl); ?>" alt="Foto de <?php echo htmlspecialchars((string) ($animalSafe['nombre'] ?? 'Mascota')); ?>">
                <?php else: ?>
                    <div style="height:240px;background:linear-gradient(135deg,#dfeaf4,#f8fbfd);"></div>
                <?php endif; ?>
                <div class="body">
                    <h2 class="h5 mb-1"><?php echo htmlspecialchars((string) ($animalSafe['nombre'] ?? 'Mascota')); ?></h2>
                    <p class="cliente-muted mb-2"><?php echo htmlspecialchars(trim((string) ($animalSafe['especie'] ?? '') . ' · ' . (string) ($animalSafe['raza'] ?? ''))); ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="cliente-chip">Sexo: <?php echo htmlspecialchars((string) ($animalSafe['sexo'] ?? 'N/D')); ?></span>
                        <span class="cliente-chip">Peso: <?php echo htmlspecialchars((string) ($animalSafe['peso_actual'] ?? '0')); ?> kg</span>
                    </div>
                    <form method="post" action="<?php echo htmlspecialchars(url('/portal/mascotas/foto')); ?>" enctype="multipart/form-data" class="mt-3">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                        <input type="hidden" name="animal_id" value="<?php echo (int) ($animalSafe['id'] ?? 0); ?>">
                        <label class="form-label small mb-1">Actualizar foto de la mascota</label>
                        <input class="form-control form-control-sm mb-2" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required>
                        <button class="btn btn-sm btn-outline-primary w-100" type="submit">Guardar foto</button>
                    </form>
                </div>
            </article>
        </div>
        <div class="col-12 col-lg-8">
            <article class="cliente-card p-3 h-100">
                <div class="cliente-section-title">
                    <h3>Historia completa</h3>
                    <small class="cliente-muted"><?php echo htmlspecialchars((string) ($profileSafe['nombres'] ?? '')); ?></small>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="cliente-list">
                            <strong class="mb-1 d-block">Consultas</strong>
                            <?php foreach ($consultasSafe as $consulta): ?>
                                <div class="cliente-item">
                                    <strong><?php echo htmlspecialchars((string) ($consulta['motivo_consulta'] ?? 'Consulta')); ?></strong>
                                    <small><?php echo htmlspecialchars((string) ($consulta['fecha_consulta'] ?? '')); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($consultasSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin consultas.</small></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cliente-list">
                            <strong class="mb-1 d-block">Vacunas</strong>
                            <?php foreach ($vacunasSafe as $vacuna): ?>
                                <div class="cliente-item">
                                    <strong><?php echo htmlspecialchars((string) ($vacuna['vacuna'] ?? 'Vacuna')); ?></strong>
                                    <small><?php echo htmlspecialchars((string) ($vacuna['fecha_aplicacion'] ?? '')); ?> · <?php echo htmlspecialchars((string) ($vacuna['proxima_aplicacion'] ?? '')); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($vacunasSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin vacunas.</small></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cliente-list">
                            <strong class="mb-1 d-block">Desparasitacion</strong>
                            <?php foreach ($desparasitacionesSafe as $desparasitacion): ?>
                                <div class="cliente-item">
                                    <strong><?php echo htmlspecialchars((string) ($desparasitacion['farmaco'] ?? 'Producto')); ?></strong>
                                    <small><?php echo htmlspecialchars((string) ($desparasitacion['fecha'] ?? '')); ?> · <?php echo htmlspecialchars((string) ($desparasitacion['proxima_fecha'] ?? '')); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($desparasitacionesSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin desparasitaciones.</small></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cliente-list">
                            <strong class="mb-1 d-block">Cirugias</strong>
                            <?php foreach ($cirugiasSafe as $cirugia): ?>
                                <div class="cliente-item">
                                    <strong><?php echo htmlspecialchars((string) ($cirugia['procedimiento_quirurgico'] ?? 'Cirugia')); ?></strong>
                                    <small><?php echo htmlspecialchars((string) ($cirugia['fecha'] ?? '')); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($cirugiasSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin cirugias.</small></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cliente-list">
                            <strong class="mb-1 d-block">Examenes</strong>
                            <?php foreach ($examenesSafe as $examen): ?>
                                <div class="cliente-item">
                                    <strong><?php echo htmlspecialchars((string) ($examen['tipo_examen'] ?? 'Examen')); ?></strong>
                                    <small><?php echo htmlspecialchars((string) ($examen['created_at'] ?? '')); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($examenesSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin examenes.</small></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cliente-list">
                            <strong class="mb-1 d-block">Documentos</strong>
                            <?php foreach ($documentsSafe as $documento): ?>
                                <div class="cliente-item">
                                    <strong><?php echo htmlspecialchars((string) ($documento['titulo'] ?? 'Documento')); ?></strong>
                                    <?php if (!empty($documento['archivo_pdf'])): ?>
                                        <small><a href="<?php echo htmlspecialchars(url('/pdf/ver?path=' . rawurlencode((string) $documento['archivo_pdf']))); ?>" target="_blank" rel="noopener">Ver PDF</a></small>
                                    <?php else: ?>
                                        <small class="cliente-muted">Sin archivo adjunto.</small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($documentsSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin documentos.</small></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="cliente-list">
                            <strong class="mb-1 d-block">Timeline medico</strong>
                            <?php foreach ($timelineSafe as $evento): ?>
                                <div class="cliente-item">
                                    <strong><?php echo htmlspecialchars((string) ($evento['titulo'] ?? 'Evento')); ?></strong>
                                    <small><?php echo htmlspecialchars((string) ($evento['fecha_evento'] ?? '')); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($timelineSafe === []): ?><div class="cliente-item"><small class="cliente-muted">Sin eventos recientes.</small></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</main>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
