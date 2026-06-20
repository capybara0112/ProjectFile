<?php
declare(strict_types=1);

/**
 * Trang kiểm tra nhanh reCAPTCHA + cURL (chạy một lần rồi xóa trên production).
 * Truy cập: http://localhost/dacn/check-recaptcha.php
 */

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/auth.php';

$checks = [
    'curl' => [
        'ok'  => curl_extension_available(),
        'msg' => curl_extension_available()
            ? 'Extension curl đã bật.'
            : 'Chưa có curl — bật extension=curl trong php.ini và restart Apache.',
    ],
    'recaptcha_file' => [
        'ok'  => is_file(__DIR__ . '/config/recaptcha.local.php'),
        'msg' => is_file(__DIR__ . '/config/recaptcha.local.php')
            ? 'File config/recaptcha.local.php tồn tại.'
            : 'Chưa có recaptcha.local.php — copy từ recaptcha.local.example.php.',
    ],
    'recaptcha_keys' => [
        'ok'  => recaptcha_is_configured(),
        'msg' => recaptcha_is_configured()
            ? 'Site Key và Secret Key đã được điền (không còn placeholder).'
            : 'Key chưa hợp lệ — mở config/recaptcha.local.php và dán key từ Google.',
    ],
];

render_header('Kiểm tra reCAPTCHA');
?>

<div class="app-card p-4" style="max-width:640px;margin:0 auto;">
    <h2 class="h4 mb-3">Kiểm tra môi trường đăng nhập</h2>
    <ul class="list-group list-group-flush mb-4">
        <?php foreach ($checks as $id => $c): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <span><?= e($c['msg']) ?></span>
            <?php if ($c['ok']): ?>
                <span class="badge text-bg-success">OK</span>
            <?php else: ?>
                <span class="badge text-bg-danger">Chưa OK</span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="muted small mb-0">
        Sau khi cấu hình xong, thử đăng nhập sai 3 lần để hiện reCAPTCHA, hoặc xóa file này trên server thật.
    </p>
    <a href="<?= e(BASE_URL) ?>/login.php" class="btn btn-success mt-3">Về trang đăng nhập</a>
</div>

<?php render_footer(); ?>
