<?php

declare(strict_types=1);

require BASE_PATH . '/app/views/layaouts/header.php';
require BASE_PATH . '/app/views/layaouts/sidebar.php';
?>
<section class="col-12 col-lg-9 col-xl-10">
<?php echo $pageContent ?? ''; ?>
</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
