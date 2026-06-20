<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/layout.php';

require_login('employer');

$pdo            = db();
$user           = current_user();
$employerUserId = (int)$user['id'];

// Lấy company_id của employer
$empStmt = $pdo->prepare('SELECT company_id FROM employers WHERE user_id = :uid LIMIT 1');
$empStmt->execute([':uid' => $employerUserId]);
$empRow    = $empStmt->fetch();
$companyId = $empRow ? (int)$empRow['company_id'] : 0;

// Lấy candidate_id từ query string
$candidateId = (int)($_GET['id'] ?? 0);
if ($candidateId <= 0) {
    flash('ID ứng viên không hợp lệ.', 'danger');
    redirect('/employer/index.php?page=applications');
}

// Xác minh: ứng viên này đã từng ứng tuyển vào công ty của employer
if ($companyId > 0) {
    $verifyStmt = $pdo->prepare('
        SELECT COUNT(*) AS cnt
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        WHERE a.candidate_id = :cid AND j.company_id = :co
    ');
    $verifyStmt->execute([':cid' => $candidateId, ':co' => $companyId]);
    $verifyRow = $verifyStmt->fetch();
    if ((int)$verifyRow['cnt'] === 0) {
        flash('Bạn không có quyền xem hồ sơ ứng viên này.', 'danger');
        redirect('/employer/index.php?page=applications');
    }
}

// --- Thông tin cơ bản ---
$userStmt = $pdo->prepare('
    SELECT u.id, u.name, u.email, u.avatar, u.created_at,
           cp.phone, cp.address, cp.dob, cp.experience, cp.education,
           cp.career_goal, cp.expected_salary, cp.experience_years, cp.legacy_skills
    FROM users u
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    WHERE u.id = :id AND u.role = "candidate"
    LIMIT 1
');
$userStmt->execute([':id' => $candidateId]);
$candidate = $userStmt->fetch();

if (!$candidate) {
    flash('Không tìm thấy ứng viên.', 'danger');
    redirect('/employer/index.php?page=applications');
}

// --- Kỹ năng ---
$skillsStmt = $pdo->prepare('
    SELECT s.id, s.name
    FROM user_skills us
    JOIN skills s ON s.id = us.skill_id
    WHERE us.user_id = :uid
    ORDER BY s.name ASC
');
$skillsStmt->execute([':uid' => $candidateId]);
$skills = $skillsStmt->fetchAll();

// --- Chứng chỉ ---
$certStmt = $pdo->prepare('
    SELECT id, name, organization, issue_date, image
    FROM certificates
    WHERE user_id = :uid
    ORDER BY issue_date DESC
');
$certStmt->execute([':uid' => $candidateId]);
$certificates = $certStmt->fetchAll();

// --- CVs ---
$cvStmt = $pdo->prepare('
    SELECT id, file_path, created_at
    FROM cvs
    WHERE user_id = :uid
    ORDER BY created_at DESC
');
$cvStmt->execute([':uid' => $candidateId]);
$cvs = $cvStmt->fetchAll();

// --- Lịch sử ứng tuyển (thuộc công ty này) ---
$appHistStmt = $pdo->prepare('
    SELECT a.id, a.status, a.apply_date, a.rejection_reason, j.title AS job_title
    FROM applications a
    JOIN jobs j ON j.id = a.job_id
    WHERE a.candidate_id = :cid AND j.company_id = :co
    ORDER BY a.apply_date DESC
');
$appHistStmt->execute([':cid' => $candidateId, ':co' => $companyId]);
$appHistory = $appHistStmt->fetchAll();

// --- Thống kê nhanh ---
$totalAppsStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM applications WHERE candidate_id = :cid');
$totalAppsStmt->execute([':cid' => $candidateId]);
$totalApps = (int)$totalAppsStmt->fetch()['cnt'];

$totalSkills = count($skills);
$totalCerts  = count($certificates);

// --- Kiểm tra đã có conversation chưa (để hiện nút Chat) ---
$convCheckStmt = $pdo->prepare('
    SELECT c.id FROM conversations c
    JOIN applications a ON a.id = c.application_id
    JOIN jobs j ON j.id = a.job_id
    WHERE c.candidate_id = :cid AND c.employer_id = :eid AND j.company_id = :co
    LIMIT 1
');
$convCheckStmt->execute([':cid' => $candidateId, ':eid' => $employerUserId, ':co' => $companyId]);
$existingConv = $convCheckStmt->fetch();

// --- Lấy application_id đầu tiên để tạo conversation ---
$firstAppStmt = $pdo->prepare('
    SELECT a.id FROM applications a
    JOIN jobs j ON j.id = a.job_id
    WHERE a.candidate_id = :cid AND j.company_id = :co
    ORDER BY a.apply_date ASC LIMIT 1
');
$firstAppStmt->execute([':cid' => $candidateId, ':co' => $companyId]);
$firstApp = $firstAppStmt->fetch();

$statusLabel = [
    'pending'  => ['Đang xét duyệt', 'warning'],
    'accepted' => ['Đã chấp nhận',   'success'],
    'rejected' => ['Đã từ chối',      'danger'],
];

render_header('Hồ sơ ứng viên: ' . $candidate['name']);
?>

<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/employer/index.php?page=applications" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Quay lại
    </a>
</div>

<!-- ============================================================ -->
<!-- BANNER / HEADER PROFILE -->
<!-- ============================================================ -->
<div class="app-card mb-4" style="background:linear-gradient(135deg,#1a73e8 0%,#0d6efd 100%);color:#fff;border-radius:16px;padding:32px">
    <div class="d-flex align-items-center gap-4 flex-wrap">
        <img src="<?= e($candidate['avatar'] ? BASE_URL . '/' . $candidate['avatar'] : BASE_URL . '/assets/images/default-avatar.png') ?>"
             width="100" height="100"
             class="rounded-circle border border-3 border-white object-fit-cover"
             style="flex-shrink:0">
        <div class="flex-grow-1">
            <h3 class="fw-bold mb-1"><?= e($candidate['name']) ?></h3>
            <div class="opacity-85 mb-2">
                <i class="fa-solid fa-envelope me-2"></i><?= e($candidate['email']) ?>
                <?php if ($candidate['phone']): ?>
                    &nbsp;&nbsp;<i class="fa-solid fa-phone me-2"></i><?= e($candidate['phone']) ?>
                <?php endif; ?>
            </div>
            <?php if ($candidate['address']): ?>
            <div class="opacity-75 small"><i class="fa-solid fa-location-dot me-1"></i><?= e($candidate['address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($existingConv): ?>
                <a href="<?= e(BASE_URL) ?>/employer/chat.php?conv=<?= (int)$existingConv['id'] ?>"
                   class="btn btn-light btn-sm">
                    <i class="fa-solid fa-comments me-1"></i>Nhắn tin
                </a>
            <?php elseif ($firstApp): ?>
                <form method="post" action="<?= e(BASE_URL) ?>/employer/chat.php" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="start_conversation">
                    <input type="hidden" name="application_id" value="<?= (int)$firstApp['id'] ?>">
                    <button type="submit" class="btn btn-light btn-sm">
                        <i class="fa-solid fa-comments me-1"></i>Bắt đầu chat
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Thống kê nhanh -->
    <div class="row g-3 mt-3">
        <div class="col-4 col-md-3">
            <div class="text-center p-3 rounded-12" style="background:rgba(255,255,255,.18)">
                <div class="fw-bold fs-4"><?= $totalSkills ?></div>
                <div class="small opacity-85">Kỹ năng</div>
            </div>
        </div>
        <div class="col-4 col-md-3">
            <div class="text-center p-3 rounded-12" style="background:rgba(255,255,255,.18)">
                <div class="fw-bold fs-4"><?= $totalCerts ?></div>
                <div class="small opacity-85">Chứng chỉ</div>
            </div>
        </div>
        <div class="col-4 col-md-3">
            <div class="text-center p-3 rounded-12" style="background:rgba(255,255,255,.18)">
                <div class="fw-bold fs-4"><?= $totalApps ?></div>
                <div class="small opacity-85">Lần ứng tuyển</div>
            </div>
        </div>
        <div class="col-4 col-md-3">
            <div class="text-center p-3 rounded-12" style="background:rgba(255,255,255,.18)">
                <div class="fw-bold fs-4"><?= (int)($candidate['experience_years'] ?? 0) ?></div>
                <div class="small opacity-85">Năm KN</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Cột trái -->
    <div class="col-lg-4">

        <!-- Thông tin cá nhân -->
        <div class="app-card mb-4">
            <div class="fw-semibold mb-3 border-bottom pb-2">
                <i class="fa-solid fa-user me-2 text-primary"></i>Thông tin cá nhân
            </div>
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                <?php if ($candidate['dob']): ?>
                <tr><td class="text-muted" style="width:110px">Ngày sinh</td>
                    <td><?= e(date('d/m/Y', strtotime($candidate['dob']))) ?></td></tr>
                <?php endif; ?>
                <?php if ($candidate['address']): ?>
                <tr><td class="text-muted">Địa chỉ</td>
                    <td><?= e($candidate['address']) ?></td></tr>
                <?php endif; ?>
                <?php if ($candidate['education']): ?>
                <tr><td class="text-muted">Học vấn</td>
                    <td><?= e($candidate['education']) ?></td></tr>
                <?php endif; ?>
                <?php if ($candidate['experience']): ?>
                <tr><td class="text-muted">Kinh nghiệm</td>
                    <td><?= e($candidate['experience']) ?></td></tr>
                <?php endif; ?>
                <?php if ($candidate['expected_salary']): ?>
                <tr><td class="text-muted">Lương kỳ vọng</td>
                    <td><?= format_money_vnd((float)$candidate['expected_salary']) ?>/tháng</td></tr>
                <?php endif; ?>
                <tr><td class="text-muted">Tham gia</td>
                    <td><?= e(date('d/m/Y', strtotime($candidate['created_at']))) ?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Kỹ năng -->
        <div class="app-card mb-4">
            <div class="fw-semibold mb-3 border-bottom pb-2">
                <i class="fa-solid fa-wrench me-2 text-warning"></i>Kỹ năng (<?= $totalSkills ?>)
            </div>
            <?php if (empty($skills)): ?>
                <p class="text-muted small">Chưa cập nhật kỹ năng.</p>
            <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($skills as $sk): ?>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2"><?= e($sk['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($candidate['legacy_skills']): ?>
            <div class="mt-2 text-muted small">
                <em>Kỹ năng khác: <?= e($candidate['legacy_skills']) ?></em>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mục tiêu nghề nghiệp -->
        <?php if ($candidate['career_goal']): ?>
        <div class="app-card mb-4">
            <div class="fw-semibold mb-2 border-bottom pb-2">
                <i class="fa-solid fa-bullseye me-2 text-success"></i>Mục tiêu nghề nghiệp
            </div>
            <p class="mb-0 small" style="white-space:pre-line"><?= e($candidate['career_goal']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Cột phải -->
    <div class="col-lg-8">

        <!-- CV -->
        <div class="app-card mb-4">
            <div class="fw-semibold mb-3 border-bottom pb-2">
                <i class="fa-solid fa-file-pdf me-2 text-danger"></i>CV đã tải lên (<?= count($cvs) ?>)
            </div>
            <?php if (empty($cvs)): ?>
                <p class="text-muted small">Ứng viên chưa tải CV lên hệ thống.</p>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($cvs as $i => $cv): ?>
                <div class="col-md-6">
                    <div class="border rounded-10 p-3 d-flex align-items-center gap-3">
                        <i class="fa-solid fa-file-pdf fa-2x text-danger"></i>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold small text-truncate">CV <?= $i + 1 ?></div>
                            <div class="text-muted" style="font-size:.75rem">
                                <?= date('d/m/Y', strtotime($cv['created_at'])) ?>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="<?= e(BASE_URL . '/' . $cv['file_path']) ?>" target="_blank"
                               class="btn btn-sm btn-outline-primary" title="Xem trực tiếp">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="<?= e(BASE_URL . '/' . $cv['file_path']) ?>" download
                               class="btn btn-sm btn-outline-success" title="Tải về">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Preview CV đầu tiên -->
            <?php if (!empty($cvs[0]['file_path'])): ?>
            <div class="mt-3">
                <div class="text-muted small mb-1">Preview CV mới nhất:</div>
                <iframe src="<?= e(BASE_URL . '/' . $cvs[0]['file_path']) ?>"
                        width="100%" height="500" style="border:1px solid #dee2e6;border-radius:8px">
                </iframe>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Chứng chỉ -->
        <div class="app-card mb-4">
            <div class="fw-semibold mb-3 border-bottom pb-2">
                <i class="fa-solid fa-certificate me-2 text-warning"></i>Chứng chỉ (<?= $totalCerts ?>)
            </div>
            <?php if (empty($certificates)): ?>
                <p class="text-muted small">Ứng viên chưa cập nhật chứng chỉ.</p>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($certificates as $cert): ?>
                <div class="col-md-6">
                    <div class="border rounded-10 p-3 d-flex gap-3 align-items-start">
                        <div class="bg-warning-subtle rounded-circle p-2 text-warning" style="flex-shrink:0">
                            <i class="fa-solid fa-medal"></i>
                        </div>
                        <div>
                            <div class="fw-semibold small"><?= e($cert['name']) ?></div>
                            <div class="text-muted small"><?= e($cert['organization']) ?></div>
                            <?php if ($cert['issue_date']): ?>
                            <div class="text-muted" style="font-size:.75rem">
                                Cấp ngày: <?= date('d/m/Y', strtotime($cert['issue_date'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lịch sử ứng tuyển tại công ty này -->
        <div class="app-card">
            <div class="fw-semibold mb-3 border-bottom pb-2">
                <i class="fa-solid fa-clock-rotate-left me-2 text-info"></i>Lịch sử ứng tuyển tại công ty
            </div>
            <?php if (empty($appHistory)): ?>
                <p class="text-muted small">Chưa có lịch sử.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vị trí</th>
                            <th>Ngày ứng tuyển</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appHistory as $ah): ?>
                    <?php [$label, $color] = $statusLabel[$ah['status']] ?? ['Không rõ', 'secondary']; ?>
                    <tr>
                        <td><?= e($ah['job_title']) ?></td>
                        <td><?= date('d/m/Y', strtotime($ah['apply_date'])) ?></td>
                        <td><span class="badge bg-<?= $color ?>"><?= $label ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php render_footer(); ?>