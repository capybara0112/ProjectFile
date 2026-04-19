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
    <!-- Anti-flash: áp dụng dark mode ngay trước khi render để tránh nhấp nháy -->
    <script>if(localStorage.getItem('darkMode')==='1'){document.documentElement.classList.add('dm-preload');}</script>
    <style>.dm-preload{background:#0f1117 !important;}</style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" rel="preload" as="script">
    <style>
        /* ── Light mode (default) ────────────────────────────────────────── */
        :root {
            --bg-page:    #f6f7fb;
            --bg-card:    #ffffff;
            --bg-card2:   #f8f9fa;
            --text-main:  #212529;
            --text-muted: rgba(0,0,0,.6);
            --border:     rgba(0,0,0,.08);
            --input-bg:   #ffffff;
            --input-text: #212529;
            --input-border: #ced4da;
            --table-stripe: rgba(0,0,0,.03);
            --badge-light-bg: #e9ecef;
            --badge-light-color: #495057;
        }

        /* ── Dark mode ───────────────────────────────────────────────────── */
        body.dark-mode {
            --bg-page:    #0f1117;
            --bg-card:    #1a1d27;
            --bg-card2:   #22263a;
            --text-main:  #e8eaf0;
            --text-muted: rgba(220,225,240,.6);
            --border:     rgba(255,255,255,.1);
            --input-bg:   #22263a;
            --input-text: #e8eaf0;
            --input-border: rgba(255,255,255,.15);
            --table-stripe: rgba(255,255,255,.03);
            --badge-light-bg: #2d3147;
            --badge-light-color: #c8cfe0;
        }

        body {
            background: var(--bg-page);
            color: var(--text-main);
            transition: background .25s, color .25s;
        }
        .brand { font-weight: 800; letter-spacing: .2px; }
        .app-card {
            background: var(--bg-card);
            border-radius: 14px;
            box-shadow: 0 6px 22px rgba(0,0,0,.06);
            transition: background .25s;
        }
        .soft-border { border: 1px solid var(--border) !important; }
        .muted { color: var(--text-muted); }
        .job-img { width: 100%; height: 170px; object-fit: cover; border-radius: 12px; }
        .rounded-14 { border-radius: 14px; }

        /* dark mode: forms */
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: var(--input-bg);
            color: var(--input-text);
            border-color: var(--input-border);
        }
        body.dark-mode .form-control::placeholder { color: rgba(200,207,224,.4); }
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            background-color: var(--input-bg);
            color: var(--input-text);
            border-color: #059669;
            box-shadow: 0 0 0 .2rem rgba(5,150,105,.25);
        }

        /* dark mode: tables */
        body.dark-mode .table { color: var(--text-main); }
        body.dark-mode .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: var(--table-stripe);
            color: var(--text-main);
        }
        body.dark-mode .table > :not(caption) > * > * {
            border-color: var(--border);
        }

        /* dark mode: badge text-bg-light / alert */
        body.dark-mode .text-bg-light,
        body.dark-mode .badge.bg-light {
            background-color: var(--badge-light-bg) !important;
            color: var(--badge-light-color) !important;
        }
        body.dark-mode .alert-info    { background: #1a2d40; border-color:#1a6a8c; color:#79d3f5; }
        body.dark-mode .alert-warning { background: #2e2800; border-color:#8a6d00; color:#ffd654; }
        body.dark-mode .alert-danger  { background: #2e0d0d; border-color:#8a2020; color:#f88; }
        body.dark-mode .alert-success { background: #0b2218; border-color:#1a6a45; color:#56d4a0; }

        /* dark mode: list-group */
        body.dark-mode .list-group-item {
            background-color: var(--bg-card);
            color: var(--text-main);
            border-color: var(--border);
        }
        body.dark-mode .list-group-item-action:hover { background: var(--bg-card2); }
        body.dark-mode .list-group-item.active {
            background: #059669;
            border-color: #059669;
        }

        /* dark mode: modal */
        body.dark-mode .modal-content {
            background: var(--bg-card);
            color: var(--text-main);
            border-color: var(--border);
        }
        body.dark-mode .modal-header,
        body.dark-mode .modal-footer { border-color: var(--border); }

        /* dark: bg-white overrides */
        body.dark-mode .bg-white { background-color: var(--bg-card2) !important; }

        /* dark: pagination */
        body.dark-mode .page-link {
            background: var(--bg-card);
            color: var(--text-main);
            border-color: var(--border);
        }
        body.dark-mode .page-item.active .page-link { background: #059669; border-color:#059669; }
        body.dark-mode .page-item.disabled .page-link { background: var(--bg-card); color: var(--text-muted); }

        /* dark: dropdown */
        body.dark-mode .dropdown-menu {
            background: var(--bg-card);
            border-color: var(--border);
        }
        body.dark-mode .dropdown-item { color: var(--text-main); }
        body.dark-mode .dropdown-item:hover { background: var(--bg-card2); }

        /* dark mode toggle button */
        #darkModeToggle {
            background: transparent;
            border: 1px solid rgba(255,255,255,.4);
            color: #fff;
            border-radius: 50%;
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: .9rem;
            transition: background .2s;
        }
        #darkModeToggle:hover { background: rgba(255,255,255,.15); }
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
            <div class="d-flex gap-2 align-items-center">
                <!-- Dark mode toggle -->
                <button id="darkModeToggle" title="Bật/tắt chế độ tối">
                    <i class="fa-solid fa-moon" id="dmIcon"></i>
                </button>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Dark mode ─────────────────────────────────────────────────────────────
(function () {
    const KEY  = 'darkMode';
    const body = document.body;
    const btn  = document.getElementById('darkModeToggle');
    const icon = document.getElementById('dmIcon');

    function apply(dark) {
        if (dark) {
            body.classList.add('dark-mode');
            if (icon) { icon.className = 'fa-solid fa-sun'; }
        } else {
            body.classList.remove('dark-mode');
            if (icon) { icon.className = 'fa-solid fa-moon'; }
        }
    }

    // init from localStorage
    apply(localStorage.getItem(KEY) === '1');
    // Xóa class preload trên html element (chỉ dùng để tránh flash)
    document.documentElement.classList.remove('dm-preload');

    if (btn) {
        btn.addEventListener('click', function () {
            const isDark = body.classList.toggle('dark-mode');
            localStorage.setItem(KEY, isDark ? '1' : '0');
            if (icon) { icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; }
        });
    }
})();
</script>
</body>
</html>
<?php
}