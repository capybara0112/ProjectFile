<?php
declare(strict_types=1);
/** @var string $pageTitle */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
    <script>if(localStorage.getItem('darkMode')==='1'){document.documentElement.classList.add('dm-preload');}</script>
    <style>.dm-preload{background:#0f172a !important;}</style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset_url('css/app.css')) ?>" rel="stylesheet">
    <?php if (!empty($extraHead ?? '')): ?>
    <?= $extraHead ?>
    <?php endif; ?>
</head>
<body class="d-flex flex-column min-vh-100">
