<?php

declare(strict_types=1);

$layoutRole = auth_role();
$sidebarFile = match ($layoutRole) {
	'cliente' => BASE_PATH . '/app/views/layaouts/sidebar_cliente.php',
	'invitado' => BASE_PATH . '/app/views/layaouts/sidebar_invitado.php',
	default => BASE_PATH . '/app/views/layaouts/sidebar.php',
};

require BASE_PATH . '/app/views/layaouts/header.php';
require $sidebarFile;
?>
<section class="col-12 col-lg-9 col-xl-10">
<?php echo $pageContent ?? ''; ?>
</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
