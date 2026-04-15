<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/upload.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $role = trim((string)($_POST['role'] ?? 'candidate'));
    if (!in_array($role, ['candidate', 'employer'], true)) {
        $role = 'candidate';
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        flash('Vui lòng điền đầy đủ thông tin bắt buộc.', 'danger');
        redirect('/register.php');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        flash('Email đã tồn tại.', 'danger');
        redirect('/register.php');
    }

    $avatarPath = null;
    if (!empty($_FILES['avatar']['name']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        try {
            $avatarPath = upload_image('avatar', 'avatar');
        } catch (Throwable $e) {
            $avatarPath = null;
        }
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, avatar) VALUES (:name, :email, :password, :role, :avatar)');
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $passwordHash,
            ':role' => $role,
            ':avatar' => $avatarPath,
        ]);
        $userId = (int)$pdo->lastInsertId();

        if ($role === 'candidate') {
            $cp = $pdo->prepare('INSERT INTO candidate_profiles (user_id, phone, address, experience, education) VALUES (:user_id, "", "", "", "")');
            $cp->execute([':user_id' => $userId]);
        } else {
            $companyName = trim((string)($_POST['company_name'] ?? ''));
            $companyDesc = trim((string)($_POST['company_description'] ?? ''));
            $companyAddress = trim((string)($_POST['company_address'] ?? ''));
            $position = trim((string)($_POST['position'] ?? ''));

            if ($companyName === '' || $position === '') {
                throw new RuntimeException('Vui lòng nhập tên công ty và vị trí khi đăng ký nhà tuyển dụng.');
            }

            $logoPath = null;
            if (!empty($_FILES['company_logo']['name']) && ($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $logoPath = upload_image('company_logo', 'company');
            }

            $stmt = $pdo->prepare('INSERT INTO companies (name, description, address, logo) VALUES (:name, :description, :address, :logo)');
            $stmt->execute([
                ':name' => $companyName,
                ':description' => $companyDesc,
                ':address' => $companyAddress,
                ':logo' => $logoPath,
            ]);
            $companyId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO employers (user_id, company_id, position) VALUES (:user_id, :company_id, :position)');
            $stmt->execute([
                ':user_id' => $userId,
                ':company_id' => $companyId,
                ':position' => $position,
            ]);
        }

        $pdo->commit();

        $_SESSION['user'] = [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'avatar' => $avatarPath,
        ];

        flash('Đăng ký thành công.', 'success');
        redirect($role === 'candidate' ? '/candidate/index.php' : '/employer/index.php');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('Đăng ký thất bại: ' . $e->getMessage(), 'danger');
        redirect('/register.php');
    }
}

render_header('Đăng ký tài khoản');
$token = csrf_token();
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="app-card p-4">
            <h3 class="mb-3"><i class="fa-solid fa-user-plus me-2"></i>Đăng ký</h3>
            <form method="POST" enctype="multipart/form-data" id="registerForm">
                <input type="hidden" name="csrf" value="<?= e($token) ?>">

                <div class="mb-3">
                    <label class="form-label">Bạn đăng ký với vai trò</label>
                    <div class="d-flex gap-3">
                        <label><input type="radio" name="role" value="candidate" checked> Ứng viên</label>
                        <label><input type="radio" name="role" value="employer"> Nhà tuyển dụng</label>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Họ tên</label>
                        <input class="form-control" name="name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mật khẩu</label>
                        <input class="form-control" type="password" name="password" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ảnh đại diện (không bắt buộc)</label>
                        <input class="form-control" type="file" name="avatar" accept="image/*">
                    </div>
                </div>

                <div id="employerFields" class="mt-3" style="display:none;">
                    <hr>
                    <h5 class="mb-3">Thông tin nhà tuyển dụng</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Vị trí</label>
                            <input class="form-control" name="position" id="positionField">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tên công ty</label>
                            <input class="form-control" name="company_name" id="companyNameField">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Địa chỉ công ty</label>
                            <input class="form-control" name="company_address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Logo công ty (không bắt buộc)</label>
                            <input class="form-control" type="file" name="company_logo" accept="image/*">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mô tả công ty</label>
                            <textarea class="form-control" name="company_description" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <button class="btn btn-success w-100 mt-4">
                    <i class="fa-solid fa-user-plus me-2"></i>Tạo tài khoản
                </button>
            </form>

            <div class="mt-3 text-center">
                <span class="muted">Đã có tài khoản?</span>
                <a href="<?= e(BASE_URL) ?>/login.php" class="ms-1">Đăng nhập</a>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const roleInputs = document.querySelectorAll('input[name="role"]');
    const employerFields = document.getElementById('employerFields');
    const positionField = document.getElementById('positionField');
    const companyNameField = document.getElementById('companyNameField');

    function updateRoleUI() {
        const role = document.querySelector('input[name="role"]:checked')?.value || 'candidate';
        const isEmployer = role === 'employer';
        employerFields.style.display = isEmployer ? 'block' : 'none';
        positionField.required = isEmployer;
        companyNameField.required = isEmployer;
    }

    roleInputs.forEach((input) => input.addEventListener('change', updateRoleUI));
    updateRoleUI();
})();
</script>

<?php
render_footer();

