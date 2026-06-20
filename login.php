<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/auth.php';

const LOGIN_FAIL_CAPTCHA = 3;
const LOGIN_FAIL_LOCKOUT = 6;

$pdo = db();

function login_fail_count(): int
{
    ensure_session();
    return (int)($_SESSION['login_fail_count'] ?? 0);
}

function login_captcha_required(): bool
{
    return login_fail_count() >= LOGIN_FAIL_CAPTCHA;
}

function login_record_failure(string $message, string $email = ''): void
{
    ensure_session();
    $_SESSION['login_fail_count'] = login_fail_count() + 1;
    if ($email !== '') {
        $_SESSION['login_last_email'] = $email;
    }

    if ($_SESSION['login_fail_count'] >= LOGIN_FAIL_LOCKOUT) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        ensure_session();
        $_SESSION['flash'] = [
            'message' => $message . ' Tài khoản đã bị tạm khóa đăng nhập. Vui lòng đặt lại mật khẩu.',
            'type'    => 'warning',
        ];
        redirect('/forgot-password.php?locked=1');
    }

    flash($message, 'danger');
    redirect('/login.php');
}

function login_reset_failures(): void
{
    ensure_session();
    unset($_SESSION['login_fail_count'], $_SESSION['login_last_email']);
}

// Xóa đếm lần sai (chỉ localhost — tránh kẹt captcha khi test)
if (isset($_GET['reset_attempts'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($ip, ['127.0.0.1', '::1'], true)) {
        login_reset_failures();
        flash('Đã reset số lần đăng nhập sai.', 'info');
    }
    redirect('/login.php');
}

if (($_GET['action'] ?? '') === 'logout') {
    ensure_session();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    redirect('/');
}

$loggedIn = current_user();
if ($loggedIn && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $role = (string)($loggedIn['role'] ?? '');
    redirect(match ($role) {
        'candidate' => '/candidate/index.php',
        'employer'  => '/employer/index.php',
        'admin'     => '/admin/index.php',
        default     => '/',
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    ensure_session();

    $token = (string)($_POST['csrf'] ?? '');
    if (!$token || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        flash('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.', 'danger');
        redirect('/login.php');
    }

    $email    = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        flash('Vui lòng nhập email và mật khẩu.', 'danger');
        redirect('/login.php');
    }

    if (login_captcha_required()) {
        if (!recaptcha_is_configured()) {
            flash(
                'reCAPTCHA chưa được cấu hình. Sửa file config/recaptcha.local.php (Site Key + Secret Key) rồi thử lại.',
                'danger'
            );
            redirect('/login.php');
        }
        if (!curl_extension_available()) {
            flash('PHP extension curl chưa bật. Bật extension=curl trong C:\\xampp\\php\\php.ini và khởi động lại Apache.', 'danger');
            redirect('/login.php');
        }
        $captchaResponse = trim((string)($_POST['g-recaptcha-response'] ?? ''));
        if (!verify_recaptcha($captchaResponse, (string)RECAPTCHA_SECRET_KEY)) {
            login_record_failure('Vui lòng xác thực reCAPTCHA hoặc thử lại.', $email);
        }
    }

    $user = authenticate_user($pdo, $email, $password);
    if (!$user) {
        login_record_failure('Email hoặc mật khẩu không đúng.', $email);
    }

    login_reset_failures();
    login_establish_session($user);

    flash('Đăng nhập thành công.', 'success');

    $role = (string)$user['role'];
    redirect(match ($role) {
        'candidate' => '/candidate/index.php',
        'employer'  => '/employer/index.php',
        'admin'     => '/admin/index.php',
        default     => '/',
    });
}

ensure_session();
$showCaptcha       = login_captcha_required();
$recaptchaReady    = recaptcha_is_configured();
$curlReady         = curl_extension_available();
$flash             = get_flash();
$csrf              = csrf_token();
$prefillEmail      = trim((string)($_SESSION['login_last_email'] ?? ''));
$pageTitle         = 'Đăng nhập';
$loadRecaptcha     = $showCaptcha && $recaptchaReady;

require __DIR__ . '/includes/auth-head.php';
?>

<div class="container-fluid g-0 auth-shell">
    <div class="row g-0 min-vh-100">
        <div class="col-lg-6 order-lg-2 auth-form-panel">
            <div class="auth-form-wrap">
                <a href="<?= e(BASE_URL ?: '/') ?>" class="auth-brand">
                    <i class="fa-solid fa-briefcase"></i>
                    <?= e(SITE_NAME) ?>
                </a>

                <h2>Chào mừng trở lại</h2>
                <p class="auth-subtitle">Đăng nhập để tiếp tục hành trình nghề nghiệp của bạn.</p>

                <?php if ($flash): ?>
                <div class="alert auth-alert alert-<?= e($flash['type'] === 'danger' ? 'danger' : ($flash['type'] === 'warning' ? 'warning auth-alert-warning' : 'success')) ?>" role="alert">
                    <?= e($flash['message'] ?? '') ?>
                </div>
                <?php endif; ?>

                <?php if ($showCaptcha && !$recaptchaReady): ?>
                <div class="alert alert-warning auth-alert small" role="alert">
                    <strong>reCAPTCHA chưa cấu hình.</strong>
                    Sửa <code>config/recaptcha.local.php</code> — dán Site Key và Secret Key từ
                    <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">Google reCAPTCHA</a>
                    (v2 checkbox). Thêm domain <code>localhost</code>.
                </div>
                <?php elseif ($showCaptcha && !$curlReady): ?>
                <div class="alert alert-danger auth-alert small" role="alert">
                    Extension <code>curl</code> chưa bật. Trong <code>C:\xampp\php\php.ini</code> bật
                    <code>extension=curl</code>, rồi Restart Apache.
                </div>
                <?php elseif ($showCaptcha): ?>
                <div class="auth-hint">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    Bạn đã nhập sai nhiều lần. Vui lòng xác thực reCAPTCHA để tiếp tục.
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= e(BASE_URL) ?>/login.php" id="loginForm" novalidate>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <div class="auth-input-wrap">
                        <input type="email" class="form-control" name="email" id="email"
                               placeholder="Email đăng nhập" value="<?= e($prefillEmail) ?>"
                               required autocomplete="username">
                        <i class="fa-solid fa-user input-icon" aria-hidden="true"></i>
                    </div>

                    <div class="auth-input-wrap">
                        <input type="password" class="form-control" name="password" id="password"
                               placeholder="Mật khẩu" required autocomplete="current-password">
                        <i class="fa-solid fa-lock input-icon" aria-hidden="true"></i>
                    </div>

                    <?php if ($showCaptcha && $recaptchaReady): ?>
                    <div class="auth-captcha-wrap">
                        <p class="small text-muted mb-2">Tích chọn <strong>Tôi không phải là người máy</strong> bên dưới:</p>
                        <div class="g-recaptcha"
                             data-sitekey="<?= e((string)RECAPTCHA_SITE_KEY) ?>"
                             data-theme="light"
                             data-expired-callback="onRecaptchaExpired"></div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-auth-submit">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Đăng nhập
                    </button>
                </form>

                <div class="auth-footer-links">
                    <a href="<?= e(BASE_URL) ?>/forgot-password.php">Quên mật khẩu?</a>
                    <span class="mx-2">·</span>
                    <span>Chưa có tài khoản?</span>
                    <a href="<?= e(BASE_URL) ?>/register.php" class="ms-1">Đăng ký</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6 order-lg-1 auth-hero">
            <div class="auth-hero-content">
                <div class="auth-hero-badge">
                    <i class="fa-solid fa-star"></i> Nền tảng tuyển dụng
                </div>
                <h1>Kết nối đúng người — đúng cơ hội</h1>
                <p>Hàng nghìn vị trí và ứng viên tiềm năng đang chờ bạn. Bắt đầu ngay hôm nay.</p>
            </div>
        </div>
    </div>
</div>

<?php render_auth_footer(); ?>
