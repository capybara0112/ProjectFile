<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/layout.php';
require_once dirname(__DIR__) . '/lib/upload.php';

require_login('candidate');

$pdo = db();
$user = current_user();
$candidateId = (int)$user['id'];

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'profile', 'skills', 'certificates', 'cv', 'applications', 'notifications'];

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Xử lý các action POST chung (upload avatar, update profile, upload cv, save skills, add certificate, remove skill, add new skill)
// Các action này sẽ được xử lý tại đây để không trùng lặp.
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        verify_csrf();

        if ($action === 'upload_avatar') {
            if (!empty($_FILES['avatar']['name']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $path = upload_image('avatar', 'avatar');
                $stmt = $pdo->prepare('UPDATE users SET avatar = :avatar WHERE id = :id');
                $stmt->execute([':avatar' => $path, ':id' => $candidateId]);
                $_SESSION['user']['avatar'] = $path;
                flash('Cập nhật ảnh đại diện thành công.', 'success');
            } else {
                flash('Bạn chưa chọn ảnh đại diện.', 'danger');
            }
            redirect('/candidate/index.php?page=profile');
        }

        if ($action === 'update_profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $experience = trim((string)($_POST['experience'] ?? ''));
            $education = trim((string)($_POST['education'] ?? ''));

            $stmt = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
            $stmt->execute([':name' => $name, ':id' => $candidateId]);

            $stmt = $pdo->prepare('
                UPDATE candidate_profiles
                SET phone = :phone, address = :address, experience = :experience, education = :education
                WHERE user_id = :user_id
            ');
            $stmt->execute([
                ':phone' => $phone,
                ':address' => $address,
                ':experience' => $experience,
                ':education' => $education,
                ':user_id' => $candidateId
            ]);

            flash('Cập nhật hồ sơ thành công.', 'success');
            redirect('/candidate/index.php?page=profile');
        }

        if ($action === 'upload_cv') {
            if (!empty($_FILES['cv']['name']) && ($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $path = upload_pdf('cv', 'cv');
                $stmt = $pdo->prepare('INSERT INTO cvs (user_id, file_path) VALUES (:user_id, :file_path)');
                $stmt->execute([':user_id' => $candidateId, ':file_path' => $path]);
                flash('Tải CV lên thành công.', 'success');
            } else {
                flash('Vui lòng chọn tập CV dạng PDF.', 'danger');
            }
            redirect('/candidate/index.php?page=cv');
        }

        if ($action === 'save_skills') {
            $skills = $_POST['skills'] ?? [];
            $skills = is_array($skills) ? $skills : [];

            $pdo->prepare('DELETE FROM user_skills WHERE user_id = :uid')->execute([':uid' => $candidateId]);
            $ins = $pdo->prepare('INSERT INTO user_skills (user_id, skill_id) VALUES (:uid, :skill_id)');
            foreach ($skills as $skillId) {
                $skillId = (int)$skillId;
                if ($skillId <= 0) continue;
                $ins->execute([':uid' => $candidateId, ':skill_id' => $skillId]);
            }
            flash('Cập nhật kỹ năng thành công.', 'success');
            redirect('/candidate/index.php?page=skills');
        }

        if ($action === 'add_certificate') {
            $name = trim((string)($_POST['name'] ?? ''));
            $organization = trim((string)($_POST['organization'] ?? ''));
            $issueDate = trim((string)($_POST['issue_date'] ?? ''));

            if ($name === '' || $organization === '' || $issueDate === '') {
                throw new RuntimeException('Tên chứng chỉ/tổ chức/ngày cấp là bắt buộc.');
            }
            if (empty($_FILES['image']['name']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Ảnh chứng chỉ là bắt buộc.');
            }
            $imagePath = upload_image('image', 'cert');

            $stmt = $pdo->prepare('
                INSERT INTO certificates (user_id, name, organization, issue_date, image)
                VALUES (:user_id, :name, :organization, :issue_date, :image)
            ');
            $stmt->execute([
                ':user_id' => $candidateId,
                ':name' => $name,
                ':organization' => $organization,
                ':issue_date' => $issueDate,
                ':image' => $imagePath
            ]);
            flash('Thêm chứng chỉ thành công.', 'success');
            redirect('/candidate/index.php?page=certificates');
        }

        if ($action === 'add_new_skill') {
            $skillName = trim((string)($_POST['skill_name'] ?? ''));
            if ($skillName === '') {
                throw new RuntimeException('Tên kỹ năng không được để trống.');
            }
            // Kiểm tra kỹ năng đã tồn tại chưa
            $stmt = $pdo->prepare('SELECT id FROM skills WHERE name = :name');
            $stmt->execute([':name' => $skillName]);
            $existing = $stmt->fetch();
            if ($existing) {
                $skillId = (int)$existing['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO skills (name) VALUES (:name)');
                $stmt->execute([':name' => $skillName]);
                $skillId = (int)$pdo->lastInsertId();
            }
            // Thêm kỹ năng cho user nếu chưa có
            $stmt = $pdo->prepare('SELECT id FROM user_skills WHERE user_id = :uid AND skill_id = :sid');
            $stmt->execute([':uid' => $candidateId, ':sid' => $skillId]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare('INSERT INTO user_skills (user_id, skill_id) VALUES (:uid, :sid)');
                $stmt->execute([':uid' => $candidateId, ':sid' => $skillId]);
            }
            flash('Đã thêm kỹ năng mới.', 'success');
            redirect('/candidate/index.php?page=skills');
        }

        if ($action === 'remove_skill') {
            $skillId = (int)($_POST['skill_id'] ?? 0);
            if ($skillId > 0) {
                $pdo->prepare('DELETE FROM user_skills WHERE user_id = :uid AND skill_id = :sid')
                    ->execute([':uid' => $candidateId, ':sid' => $skillId]);
                flash('Đã xóa kỹ năng.', 'success');
            }
            redirect('/candidate/index.php?page=skills');
        }
    }
} catch (Throwable $e) {
    flash('Thao tác thất bại: ' . $e->getMessage(), 'danger');
    redirect('/candidate/index.php');
}

// Load các dữ liệu dùng chung cho sidebar và các page
$profileStmt = $pdo->prepare('
    SELECT u.name, u.avatar, cp.phone, cp.address, cp.experience, cp.education
    FROM users u
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    WHERE u.id = :id
');
$profileStmt->execute([':id' => $candidateId]);
$profile = $profileStmt->fetch();

// Include file con tương ứng
$pageFile = __DIR__ . '/' . $page . '.php';
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    // fallback dashboard
    include __DIR__ . '/dashboard.php';
}