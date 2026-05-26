<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seleccionar empresa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
</head>
<body>
<?php
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$companiesSafe = isset($companies) && is_array($companies) ? $companies : [];
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6">
            <section class="card tvg-card p-4">
                <h1 class="h5 mb-4">Selecciona empresa o sede</h1>
                <form action="<?php echo htmlspecialchars(url('/empresa/seleccionar')); ?>" method="post">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="empresa_id">Empresa</label>
                        <select class="form-select" id="empresa_id" name="empresa_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($companiesSafe as $company): ?>
                                <option value="<?php echo (int) $company['id']; ?>"><?php echo htmlspecialchars((string) $company['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-brand" type="submit">Continuar</button>
                </form>
            </section>
        </div>
    </div>
</main>
</body>
</html>
