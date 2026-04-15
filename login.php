<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/upload.php';

$pdo = db();

// Dang nhap + dang xuat.

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['action'] ?? '';
    if ($formAction === 'login') {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('Vui lòng nhập email và mật khẩu.', 'danger');
            redirect('/login.php');
        }

        $stmt = $pdo->prepare('SELECT id, name, email, password, role, avatar FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            flash('Email hoặc mật khẩu không đúng.', 'danger');
            redirect('/login.php');
        }

        $stored = (string)$user['password'];
        $ok = false;

        // Support both hashed passwords (new) and plain seed passwords (old data).
        if ($stored !== '' && str_starts_with($stored, '$')) {
            $ok = password_verify($password, $stored);
        } else {
            $ok = hash_equals($stored, $password);
        }

        if (!$ok) {
            flash('Email hoặc mật khẩu không đúng.', 'danger');
            redirect('/login.php');
        }

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => (string)$user['name'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
            'avatar' => $user['avatar'] ?? null,
        ];
        flash('Đăng nhập thành công.', 'success');
        redirect($user['role'] === 'candidate' ? '/candidate/index.php' : ($user['role'] === 'employer' ? '/employer/index.php' : '/admin/index.php'));
    }
}

render_header('Đăng nhập');

?>

<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="app-card p-4">
            <h3 class="mb-3"><i class="fa-solid fa-right-to-bracket me-2"></i>Đăng nhập</h3>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input class="form-control" type="password" name="password" required>
                </div>
                <button class="btn btn-success w-100">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Đăng nhập
                </button>
            </form>
            <div class="mt-3 text-center">
                <span class="muted">Chưa có tài khoản?</span>
                <a href="<?= e(BASE_URL) ?>/register.php" class="ms-1">Đăng ký ngay</a>
            </div>
            <!-- <div class="muted small mt-3">
                Tài khoản mẫu: <code>a@gmail.com</code>, <code>b@gmail.com</code>, <code>c@gmail.com</code>, <code>d@gmail.com</code>, <code>admin@gmail.com</code> (mật khẩu: <code>123456</code>)
            </div> -->
        </div>
    </div>
</div>

<?php
render_footer();

