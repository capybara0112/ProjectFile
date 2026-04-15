<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/upload.php';

require_login('candidate');

$pdo = db();
$user = current_user();
$candidateId = (int)$user['id'];
$page = $_GET['page'] ?? 'dashboard';

// -----------------------------
// Candidate actions
// -----------------------------

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        verify_csrf();

        if ($action === 'update_profile') {
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $experience = trim((string)($_POST['experience'] ?? ''));
            $education = trim((string)($_POST['education'] ?? ''));

            $stmt = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
            $stmt->execute([':name' => $name, ':id' => $candidateId]);

            // Candidate profile row should exist (created on register).
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

            // Replace candidate skills.
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
    }
} catch (Throwable $e) {
    flash('Thao tác thất bại: ' . $e->getMessage(), 'danger');
    redirect('/candidate/index.php');
}

// -----------------------------
// Load data for UI
// -----------------------------

$profileStmt = $pdo->prepare('
    SELECT u.name, u.avatar, cp.phone, cp.address, cp.experience, cp.education
    FROM users u
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    WHERE u.id = :id
');
$profileStmt->execute([':id' => $candidateId]);
$profile = $profileStmt->fetch();

$cvs = $pdo->prepare('SELECT id, file_path FROM cvs WHERE user_id = :uid ORDER BY id DESC');
$cvs->execute([':uid' => $candidateId]);
$cvList = $cvs->fetchAll();

$allSkills = $pdo->query('SELECT id, name FROM skills ORDER BY name ASC')->fetchAll();
$userSkillsStmt = $pdo->prepare('SELECT skill_id FROM user_skills WHERE user_id = :uid');
$userSkillsStmt->execute([':uid' => $candidateId]);
$selectedSkillIds = array_map(static fn($r) => (int)$r['skill_id'], $userSkillsStmt->fetchAll());

$certStmt = $pdo->prepare('SELECT id, name, organization, issue_date, image FROM certificates WHERE user_id = :uid ORDER BY id DESC');
$certStmt->execute([':uid' => $candidateId]);
$certificates = $certStmt->fetchAll();

$appsStmt = $pdo->prepare('
    SELECT 
        a.id AS application_id,
        a.status AS application_status,
        a.rejection_reason,
        j.title,
        j.salary_min,
        j.salary_max,
        j.salary_type,
        j.image,
        c.name AS company_name,
        c.address AS company_address,
        cb.province,
        cb.address_detail,
        cb.full_address,
        COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) AS location_label,
        i.interview_date,
        i.location AS interview_location,
        i.note AS interview_note
    FROM applications a
    JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = j.company_id
    LEFT JOIN company_branches cb ON cb.id = j.branch_id
    LEFT JOIN interviews i ON i.application_id = a.id
    WHERE a.candidate_id = :uid
    ORDER BY a.id DESC
');
$appsStmt->execute([':uid' => $candidateId]);
$applications = $appsStmt->fetchAll();

$notifStmt = $pdo->prepare('SELECT id, content FROM notifications WHERE user_id = :uid ORDER BY id DESC');
$notifStmt->execute([':uid' => $candidateId]);
$notifications = $notifStmt->fetchAll();

render_header('Trang ứng viên');
?>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="app-card p-3">
            <div class="d-flex align-items-center gap-3 p-3 soft-border rounded-14 bg-white">
                <?php if (!empty($profile['avatar'])): ?>
                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$profile['avatar']) ?>" alt="Avatar" style="width:56px;height:56px;object-fit:cover;" class="rounded-14 soft-border bg-white p-1">
                <?php else: ?>
                    <div class="soft-border rounded-14 bg-light d-flex align-items-center justify-content-center" style="width:56px;height:56px;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="fw-bold"><?= e((string)$profile['name']) ?></div>
                    <div class="muted small">Ứng viên</div>
                </div>
            </div>

            <div class="list-group list-group-flush mt-3">
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=profile" class="list-group-item list-group-item-action <?= $page === 'profile' ? 'active' : '' ?>">Hồ sơ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=skills" class="list-group-item list-group-item-action <?= $page === 'skills' ? 'active' : '' ?>">Kỹ năng</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=certificates" class="list-group-item list-group-item-action <?= $page === 'certificates' ? 'active' : '' ?>">Chứng chỉ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv" class="list-group-item list-group-item-action <?= $page === 'cv' ? 'active' : '' ?>">CV</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="list-group-item list-group-item-action <?= $page === 'applications' ? 'active' : '' ?>">Việc đã ứng tuyển</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications" class="list-group-item list-group-item-action <?= $page === 'notifications' ? 'active' : '' ?>">Thông báo</a>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <?php if ($page === 'profile' || $page === 'dashboard'): ?>
            <div class="app-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0"><i class="fa-solid fa-id-card me-2"></i>Hồ sơ ứng viên</h4>
                    <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-success btn-sm"><i class="fa-solid fa-bolt me-1"></i>Duyệt việc làm</a>
                </div>

                <form method="POST" class="row g-3" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="col-md-12">
                        <label class="form-label">Tải ảnh đại diện</label>
                        <input class="form-control" type="file" name="avatar" accept="image/*">
                        <button class="btn btn-outline-success mt-2" type="submit"><i class="fa-solid fa-image me-2"></i>Cập nhật ảnh đại diện</button>
                    </div>
                </form>

                <hr class="my-4">

                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Họ tên</label>
                            <input class="form-control" name="name" value="<?= e((string)($profile['name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input class="form-control" name="phone" value="<?= e((string)($profile['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Địa chỉ</label>
                            <input class="form-control" name="address" value="<?= e((string)($profile['address'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kinh nghiệm</label>
                            <input class="form-control" name="experience" value="<?= e((string)($profile['experience'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Học vấn</label>
                            <input class="form-control" name="education" value="<?= e((string)($profile['education'] ?? '')) ?>">
                        </div>
                        <div class="col-md-12">
                            <button class="btn btn-success"><i class="fa-solid fa-pen me-2"></i>Lưu hồ sơ</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php elseif ($page === 'skills'): ?>
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-wrench me-2"></i>Kỹ năng</h4>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_skills">
                    <div class="row g-3">
                        <?php foreach ($allSkills as $sk): ?>
                            <?php $checked = in_array((int)$sk['id'], $selectedSkillIds, true); ?>
                            <div class="col-md-6">
                                <div class="soft-border rounded-14 p-3 bg-white">
                                    <label class="d-flex gap-2 align-items-center">
                                        <input type="checkbox" name="skills[]" value="<?= (int)$sk['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                        <span class="fw-bold"><?= e((string)$sk['name']) ?></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn btn-success mt-3"><i class="fa-solid fa-floppy-disk me-2"></i>Lưu kỹ năng</button>
                </form>
            </div>
        <?php elseif ($page === 'certificates'): ?>
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-award me-2"></i>Chứng chỉ</h4>

                <form method="POST" enctype="multipart/form-data" class="app-card p-3 soft-border bg-white">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_certificate">

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Tên chứng chỉ</label>
                            <input class="form-control" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tổ chức cấp</label>
                            <input class="form-control" name="organization" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ngày cấp</label>
                            <input class="form-control" type="date" name="issue_date" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Hình ảnh</label>
                            <input class="form-control" type="file" name="image" accept="image/*" required>
                        </div>
                        <div class="col-md-12">
                            <button class="btn btn-success"><i class="fa-solid fa-plus me-2"></i>Thêm chứng chỉ</button>
                        </div>
                    </div>
                </form>

                <hr class="my-4">

                <div class="row g-3">
                    <?php foreach ($certificates as $cert): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <div class="fw-bold"><?= e((string)$cert['name']) ?></div>
                                <div class="muted small"><?= e((string)$cert['organization']) ?> • <?= e((string)$cert['issue_date']) ?></div>
                                <div class="mt-2">
                                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$cert['image']) ?>" style="width:100%;max-height:220px;object-fit:cover;" class="rounded-14 soft-border bg-light" alt="Certificate image">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($certificates)): ?>
                        <div class="muted">Chưa có chứng chỉ nào.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($page === 'cv'): ?>
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-file-arrow-up me-2"></i>CV của bạn</h4>

                <form method="POST" enctype="multipart/form-data" class="app-card p-3 soft-border bg-white">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload_cv">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Tải CV dạng PDF</label>
                            <input class="form-control" type="file" name="cv" accept="application/pdf" required>
                            <div class="muted small mt-1">Max size: <?= (int)(UPLOAD_MAX_BYTES / 1024 / 1024) ?>MB</div>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-success w-100"><i class="fa-solid fa-upload me-2"></i>Tải lên</button>
                        </div>
                    </div>
                </form>

                <hr class="my-4">

                <div class="row g-3">
                    <?php foreach ($cvList as $cv): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <div class="fw-bold">CV #<?= (int)$cv['id'] ?></div>
                                <div class="muted small mb-2"><?= e((string)$cv['file_path']) ?></div>
                                <a class="btn btn-outline-success btn-sm w-100" href="<?= e(BASE_URL) ?>/<?= e((string)$cv['file_path']) ?>" target="_blank">
                                    <i class="fa-solid fa-file-pdf me-2"></i>Mở PDF
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($cvList)): ?>
                        <div class="muted">Bạn chưa tải CV nào.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($page === 'applications'): ?>
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-clipboard-check me-2"></i>Việc đã ứng tuyển</h4>
                <div class="row g-3">
                    <?php foreach ($applications as $app): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-bold"><?= e((string)$app['title']) ?></div>
                                        <div class="muted small"><i class="fa-solid fa-building"></i> <?= e((string)$app['company_name']) ?></div>
                                    </div>
                                    <span class="badge text-bg-light soft-border"><?= e((string)$app['application_status']) ?></span>
                                </div>
                                <div class="muted small mt-2">
                                    <i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($app)) ?>
                                    <span class="mx-2">|</span>
                                    <i class="fa-solid fa-money-bill-wave me-1"></i><?= e(job_salary_label($app)) ?>
                                </div>
                                <div class="mt-3">
                                    <?php if ($app['application_status'] === 'rejected'): ?>
                                        <div class="alert alert-danger py-2 mb-0">
                                            <div class="fw-bold"><i class="fa-solid fa-ban me-1"></i>Đơn ứng tuyển đã bị từ chối</div>
                                            <div class="small mt-2"><strong>Lý do:</strong></div>
                                            <div class="small"><?= e((string)($app['rejection_reason'] ?? 'Không có lý do')) ?></div>
                                        </div>
                                    <?php elseif (!empty($app['interview_date'])): ?>
                                        <div class="alert alert-success py-2 mb-0">
                                            <div class="fw-bold"><i class="fa-solid fa-calendar me-1"></i>Đã lên lịch phỏng vấn</div>
                                            <div class="small">Ngày: <?= e((string)$app['interview_date']) ?></div>
                                            <div class="small">Địa điểm: <?= e((string)$app['interview_location']) ?></div>
                                            <?php if (!empty($app['interview_note'])): ?>
                                                <div class="small muted mt-1"><?= e((string)$app['interview_note']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-secondary py-2 mb-0">Chưa có lịch phỏng vấn.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($applications)): ?>
                        <div class="muted">Bạn chưa ứng tuyển công việc nào.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($page === 'notifications'): ?>
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-bell me-2"></i>Thông báo</h4>
                <div class="row g-3">
                    <?php foreach ($notifications as $n): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <div class="small muted">#<?= (int)$n['id'] ?></div>
                                <div class="mt-1"><?= e((string)$n['content']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($notifications)): ?>
                        <div class="muted">Chưa có thông báo.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
render_footer();