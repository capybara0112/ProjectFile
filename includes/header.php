<?php
declare(strict_types=1);
/** @var ?array $user @var ?string $role @var ?string $userName @var ?array $flash */
$currentPage = $_GET['page'] ?? 'home';
?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= e(BASE_URL ?: '/') ?>">
            <i class="fa-solid fa-briefcase me-2"></i><?= e(SITE_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNav" aria-controls="appNav" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="appNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" href="<?= e(BASE_URL ?: '/') ?>">
                        <i class="fa-solid fa-house me-1"></i>Trang chủ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'jobs' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/?page=jobs">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Việc làm
                    </a>
                </li>
                <?php if ($role === 'candidate'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(BASE_URL) ?>/candidate/index.php">
                        <i class="fa-solid fa-user me-1"></i>Ứng viên
                    </a>
                </li>
                <?php elseif ($role === 'employer'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(BASE_URL) ?>/employer/index.php">
                        <i class="fa-solid fa-building me-1"></i>Nhà tuyển dụng
                    </a>
                </li>
                <?php elseif ($role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(BASE_URL) ?>/admin/index.php">
                        <i class="fa-solid fa-shield-halved me-1"></i>Quản trị
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                <button id="darkModeToggle" type="button" title="Bật/tắt chế độ tối" aria-label="Dark mode">
                    <i class="fa-solid fa-moon" id="dmIcon"></i>
                </button>
                <?php if ($user): ?>
                <span class="text-muted small d-none d-lg-inline me-1"><?= e((string)$userName) ?></span>
                <a class="btn btn-outline-success btn-sm" href="<?= e(BASE_URL) ?>/login.php?action=logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i><span class="d-none d-sm-inline ms-1">Đăng xuất</span>
                </a>
                <?php else: ?>
                <a class="btn btn-outline-success btn-sm" href="<?= e(BASE_URL) ?>/login.php">
                    <i class="fa-solid fa-right-to-bracket"></i><span class="d-none d-sm-inline ms-1">Đăng nhập</span>
                </a>
                <a class="btn btn-success btn-sm" href="<?= e(BASE_URL) ?>/register.php">
                    <i class="fa-solid fa-user-plus"></i><span class="d-none d-sm-inline ms-1">Đăng ký</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="container app-main flex-grow-1">
    <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'success') ?> mb-4" role="alert">
        <?= e($flash['message'] ?? '') ?>
    </div>
    <?php endif; ?>
