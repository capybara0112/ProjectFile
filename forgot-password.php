<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$locked    = isset($_GET['locked']) && $_GET['locked'] === '1';
$flash     = get_flash();
$pageTitle = 'Đặt lại mật khẩu';
$loadRecaptcha = false;

require __DIR__ . '/includes/auth-head.php';
?>

<div class="auth-standalone">
    <div class="auth-standalone-card">
        <a href="<?= e(BASE_URL) ?>/login.php" class="auth-brand mb-3">
            <i class="fa-solid fa-arrow-left"></i> Quay lại đăng nhập
        </a>
        <h2 class="fw-bold mb-2">Đặt lại mật khẩu</h2>

        <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> auth-alert small" role="alert">
            <?= e($flash['message'] ?? '') ?>
        </div>
        <?php elseif ($locked): ?>
        <div class="alert alert-warning auth-alert-warning auth-alert small" role="alert">
            Bạn đã nhập sai quá nhiều lần. Vui lòng đặt lại mật khẩu để tiếp tục sử dụng hệ thống.
        </div>
        <?php else: ?>
        <p class="auth-subtitle mb-3">Nhập email đăng ký để nhận hướng dẫn đặt lại mật khẩu.</p>
        <?php endif; ?>

        <form method="POST" action="#" onsubmit="alert('Chức năng gửi email đặt lại mật khẩu đang được triển khai.'); return false;">
            <div class="auth-input-wrap">
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="Email đăng ký" required>
                <i class="fa-solid fa-envelope input-icon" aria-hidden="true"></i>
            </div>
            <button type="submit" class="btn btn-auth-submit">Gửi yêu cầu</button>
        </form>
    </div>
</div>

<?php render_auth_footer(); ?>
