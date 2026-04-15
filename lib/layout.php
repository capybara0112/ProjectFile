<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function render_header(string $title): void
{
    $user = current_user();
    $flash = get_flash();

    $role = $user['role'] ?? null;
    $userName = $user['name'] ?? null;

    ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f6f7fb; }
        .brand { font-weight: 800; letter-spacing: .2px; }
        .app-card { background: #fff; border-radius: 14px; box-shadow: 0 6px 22px rgba(0,0,0,.06); }
        .soft-border { border: 1px solid rgba(0,0,0,.08); }
        .muted { color: rgba(0,0,0,.6); }
        .job-img { width: 100%; height: 170px; object-fit: cover; border-radius: 12px; }
        .rounded-14 { border-radius: 14px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(90deg,#16a34a,#059669);">
    <div class="container">
        <a class="navbar-brand brand" href="<?= e(BASE_URL ?: '/') ?>">
            <i class="fa-solid fa-briefcase"></i> <?= e(SITE_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'jobs' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/?page=jobs">
                        <i class="fa-solid fa-magnifying-glass"></i> Việc làm
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'home' ? 'active' : '' ?>" href="<?= e(BASE_URL ?: '/') ?>">
                        <i class="fa-solid fa-house"></i> Trang chủ
                    </a>
                </li>
                <?php if ($role === 'candidate'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'candidate' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/candidate/index.php">
                            <i class="fa-solid fa-user"></i> Ứng viên
                        </a>
                    </li>
                <?php elseif ($role === 'employer'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'employer' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/employer/index.php">
                            <i class="fa-solid fa-building"></i> Nhà tuyển dụng
                        </a>
                    </li>
                <?php elseif ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'admin' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>/admin/index.php">
                            <i class="fa-solid fa-shield-halved"></i> Quản trị
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2">
                <?php if ($user): ?>
                    <span class="navbar-text text-white-50 d-none d-lg-inline"><?= e($userName) ?></span>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(BASE_URL) ?>/login.php?action=logout">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Đăng xuất
                    </a>
                <?php else: ?>
                    <a class="btn btn-outline-light btn-sm" href="<?= e(BASE_URL) ?>/login.php">
                        <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
                    </a>
                    <a class="btn btn-light btn-sm" href="<?= e(BASE_URL) ?>/register.php">
                        <i class="fa-solid fa-user-plus"></i> Đăng ký
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type'] ?? 'success') ?> soft-border rounded-14">
            <?= e($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
</main>

<footer class="py-4">
    <div class="container">
        <div class="text-center muted small">Dự án sinh viên. Xây dựng với PHP + MySQL + Bootstrap.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

