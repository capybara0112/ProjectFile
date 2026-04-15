<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/upload.php';

require_login('employer');

$pdo            = db();
$user           = current_user();
$employerUserId = (int)$user['id'];
$page           = $_GET['page'] ?? 'dashboard';

// -----------------------------------------------------------------------------
// Resolve company cho employer đang đăng nhập
// -----------------------------------------------------------------------------
$companyId = null;
$position  = null;
$company   = null;

$empStmt = $pdo->prepare('SELECT company_id, position FROM employers WHERE user_id = :uid LIMIT 1');
$empStmt->execute([':uid' => $employerUserId]);
$empRow = $empStmt->fetch();
if ($empRow) {
    $companyId = (int)$empRow['company_id'];
    $position  = $empRow['position'];

    $coStmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id');
    $coStmt->execute([':id' => $companyId]);
    $company = $coStmt->fetch();
}

// -----------------------------------------------------------------------------
// Xử lý các action POST
// -----------------------------------------------------------------------------
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        verify_csrf();

        if (!$companyId) {
            throw new RuntimeException('Tài khoản nhà tuyển dụng chưa được gắn công ty.');
        }

        // -- Lưu thông tin công ty -------------------------------------------------
        if ($action === 'save_company') {
            $name        = trim((string)($_POST['company_name']        ?? ''));
            $description = trim((string)($_POST['company_description'] ?? ''));
            $address     = trim((string)($_POST['company_address']     ?? ''));

            if ($name === '') throw new RuntimeException('Tên công ty là bắt buộc.');

            $logoPath = $company['logo'] ?? null;
            if (!empty($_FILES['company_logo']['name']) && ($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $logoPath = upload_image('company_logo', 'company');
            }

            $pdo->prepare('UPDATE companies SET name=:name, description=:description, address=:address, logo=:logo WHERE id=:id')
                ->execute([':name' => $name, ':description' => $description, ':address' => $address, ':logo' => $logoPath, ':id' => $companyId]);

            $company = null;
            flash('Lưu thông tin công ty thành công.', 'success');
            redirect('/employer/index.php?page=company');
        }

        // -- Tạo job mới ---------------------------------------------------------
        if ($action === 'create_job') {
            $title       = trim((string)($_POST['title']       ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $requirement = trim((string)($_POST['requirement'] ?? ''));
            $salary      = trim((string)($_POST['salary']      ?? ''));
            $location    = trim((string)($_POST['location']    ?? ''));
            $salaryMin   = strlen(trim((string)($_POST['salary_min'] ?? ''))) > 0 ? (float)$_POST['salary_min'] : null;
            $salaryMax   = strlen(trim((string)($_POST['salary_max'] ?? ''))) > 0 ? (float)$_POST['salary_max'] : null;
            $categoryIds = is_array($_POST['categories'] ?? null) ? $_POST['categories'] : [];

            if ($title === '') throw new RuntimeException('Tiêu đề việc làm là bắt buộc.');
            if (empty($_FILES['image']['name']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Ảnh chính của việc làm là bắt buộc.');
            }
            $mainImagePath = upload_image('image', 'job');

            $pdo->beginTransaction();
            try {
                $pdo->prepare('
                    INSERT INTO jobs (company_id, title, description, requirement, salary, location, image, status, salary_min, salary_max)
                    VALUES (:company_id, :title, :description, :requirement, :salary, :location, :image, "pending", :salary_min, :salary_max)
                ')->execute([
                    ':company_id'  => $companyId,
                    ':title'       => $title,
                    ':description' => $description,
                    ':requirement' => $requirement,
                    ':salary'      => $salary,
                    ':location'    => $location,
                    ':image'       => $mainImagePath,
                    ':salary_min'  => $salaryMin,
                    ':salary_max'  => $salaryMax,
                ]);
                $jobId = (int)$pdo->lastInsertId();

                $ins = $pdo->prepare('INSERT INTO job_categories (job_id, category_id) VALUES (:job_id, :category_id)');
                foreach ($categoryIds as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) $ins->execute([':job_id' => $jobId, ':category_id' => $cid]);
                }

                $paths = upload_images_multi('job_images', 'job');
                $insImg = $pdo->prepare('INSERT INTO job_images (job_id, image_path) VALUES (:job_id, :image_path)');
                foreach ($paths as $path) {
                    $insImg->execute([':job_id' => $jobId, ':image_path' => $path]);
                }

                $pdo->commit();
                flash('Đăng tin thành công! Tin đang chờ admin duyệt.', 'success');
                redirect('/employer/index.php?page=jobs');
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        // -- Cập nhật job --------------------------------------------------------
        if ($action === 'update_job') {
            $jobId = (int)($_POST['job_id'] ?? 0);
            if ($jobId <= 0) throw new RuntimeException('Mã việc làm không hợp lệ.');

            $chk = $pdo->prepare('SELECT id FROM jobs WHERE id=:id AND company_id=:cid');
            $chk->execute([':id' => $jobId, ':cid' => $companyId]);
            if (!$chk->fetch()) throw new RuntimeException('Không tìm thấy việc làm.');

            $title       = trim((string)($_POST['title']       ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $requirement = trim((string)($_POST['requirement'] ?? ''));
            $salary      = trim((string)($_POST['salary']      ?? ''));
            $location    = trim((string)($_POST['location']    ?? ''));
            $salaryMin   = strlen(trim((string)($_POST['salary_min'] ?? ''))) > 0 ? (float)$_POST['salary_min'] : null;
            $salaryMax   = strlen(trim((string)($_POST['salary_max'] ?? ''))) > 0 ? (float)$_POST['salary_max'] : null;
            $categoryIds = is_array($_POST['categories'] ?? null) ? $_POST['categories'] : [];
            // Nếu đây là "đăng lại" thì reset về pending, xóa lý do từ chối
            $isRepost    = ((int)($_POST['repost'] ?? 0)) === 1;

            $updateFields = [
                'title'       => $title,
                'description' => $description,
                'requirement' => $requirement,
                'salary'      => $salary,
                'location'    => $location,
                'salary_min'  => $salaryMin,
                'salary_max'  => $salaryMax,
            ];

            // Nếu đăng lại: reset pending và xóa rejection_reason
            if ($isRepost) {
                $updateFields['status']           = 'pending';
                $updateFields['rejection_reason'] = null;
            }

            if (!empty($_FILES['image']['name']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $updateFields['image'] = upload_image('image', 'job');
            }

            $sqlParts = [];
            $params   = [':id' => $jobId];
            foreach ($updateFields as $k => $v) {
                $sqlParts[] = $k . ' = :' . $k;
                $params[':' . $k] = $v;
            }
            $pdo->prepare('UPDATE jobs SET ' . implode(', ', $sqlParts) . ' WHERE id = :id')->execute($params);

            $pdo->prepare('DELETE FROM job_categories WHERE job_id=:jid')->execute([':jid' => $jobId]);
            $ins = $pdo->prepare('INSERT INTO job_categories (job_id, category_id) VALUES (:job_id, :category_id)');
            foreach ($categoryIds as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) $ins->execute([':job_id' => $jobId, ':category_id' => $cid]);
            }

            $paths = upload_images_multi('job_images', 'job');
            if (!empty($paths)) {
                $insImg = $pdo->prepare('INSERT INTO job_images (job_id, image_path) VALUES (:job_id, :image_path)');
                foreach ($paths as $p) $insImg->execute([':job_id' => $jobId, ':image_path' => $p]);
            }

            $msg = $isRepost
                ? 'Đã cập nhật và gửi lại để admin duyệt!'
                : 'Cập nhật việc làm thành công.';
            flash($msg, 'success');
            redirect('/employer/index.php?page=job_edit&id=' . $jobId);
        }

        // -- Xóa job -------------------------------------------------------------
        if ($action === 'delete_job') {
            $jobId = (int)($_POST['job_id'] ?? 0);
            if ($jobId <= 0) throw new RuntimeException('Mã việc làm không hợp lệ.');

            $chk = $pdo->prepare('SELECT id FROM jobs WHERE id=:id AND company_id=:cid');
            $chk->execute([':id' => $jobId, ':cid' => $companyId]);
            if (!$chk->fetch()) throw new RuntimeException('Không tìm thấy việc làm.');

            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM interviews WHERE application_id IN (SELECT id FROM applications WHERE job_id=:jid)')->execute([':jid' => $jobId]);
                $pdo->prepare('DELETE FROM applications   WHERE job_id=:jid')->execute([':jid' => $jobId]);
                $pdo->prepare('DELETE FROM job_images     WHERE job_id=:jid')->execute([':jid' => $jobId]);
                $pdo->prepare('DELETE FROM job_categories WHERE job_id=:jid')->execute([':jid' => $jobId]);
                $pdo->prepare('DELETE FROM jobs           WHERE id=:jid'    )->execute([':jid' => $jobId]);
                $pdo->commit();
                flash('Xóa việc làm thành công.', 'success');
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            redirect('/employer/index.php?page=jobs');
        }

        // -- Lên lịch phỏng vấn -------------------------------------------------
        if ($action === 'schedule_interview') {
            $applicationId = (int)($_POST['application_id'] ?? 0);
            $interviewDate = trim((string)($_POST['interview_date']  ?? ''));
            $location      = trim((string)($_POST['location']        ?? ''));
            $note          = trim((string)($_POST['note']            ?? ''));

            if ($applicationId <= 0) throw new RuntimeException('Đơn ứng tuyển không hợp lệ.');
            if ($interviewDate === '') throw new RuntimeException('Ngày giờ phỏng vấn là bắt buộc.');

            $chk = $pdo->prepare('SELECT 1 FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=:aid AND j.company_id=:cid');
            $chk->execute([':aid' => $applicationId, ':cid' => $companyId]);
            if (!$chk->fetch()) throw new RuntimeException('Không tìm thấy đơn ứng tuyển thuộc công ty của bạn.');

            $pdo->prepare('DELETE FROM interviews WHERE application_id=:aid')->execute([':aid' => $applicationId]);
            $pdo->prepare('INSERT INTO interviews (application_id, interview_date, location, note) VALUES (:aid, :date, :loc, :note)')
                ->execute([':aid' => $applicationId, ':date' => $interviewDate, ':loc' => $location, ':note' => $note]);

            flash('Đã lên lịch phỏng vấn.', 'success');
            redirect('/employer/index.php?page=job_applicants&id=' . (int)($_POST['job_id'] ?? 0));
        }

        // -- Từ chối ứng viên ---------------------------------------------------
        if ($action === 'reject_application') {
            $applicationId   = (int)($_POST['application_id']  ?? 0);
            $rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));

            if ($applicationId <= 0) throw new RuntimeException('Đơn ứng tuyển không hợp lệ.');
            if ($rejectionReason === '') throw new RuntimeException('Lý do từ chối là bắt buộc.');

            $chk = $pdo->prepare('SELECT a.candidate_id, j.title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=:aid AND j.company_id=:cid');
            $chk->execute([':aid' => $applicationId, ':cid' => $companyId]);
            $appData = $chk->fetch();
            if (!$appData) throw new RuntimeException('Không tìm thấy đơn ứng tuyển thuộc công ty của bạn.');

            $pdo->prepare('UPDATE applications SET status="rejected", rejection_reason=:reason WHERE id=:id')
                ->execute([':reason' => $rejectionReason, ':id' => $applicationId]);

            $msg = 'Đơn ứng tuyển vị trí "' . $appData['title'] . '" đã bị từ chối. Lý do: ' . $rejectionReason;
            $pdo->prepare('INSERT INTO notifications (user_id, content) VALUES (:uid, :content)')
                ->execute([':uid' => (int)$appData['candidate_id'], ':content' => $msg]);

            flash('Đã từ chối ứng viên và gửi thông báo.', 'success');
            redirect('/employer/index.php?page=job_applicants&id=' . (int)($_POST['job_id'] ?? 0));
        }
    }
} catch (Throwable $e) {
    flash('Thao tác thất bại: ' . $e->getMessage(), 'danger');
    redirect('/employer/index.php');
}

// -----------------------------------------------------------------------------
// Reload company nếu cần
// -----------------------------------------------------------------------------
if ($companyId && !$company) {
    $coStmt = $pdo->prepare('SELECT * FROM companies WHERE id=:id');
    $coStmt->execute([':id' => $companyId]);
    $company = $coStmt->fetch();
}

// -----------------------------------------------------------------------------
// Load data cho UI
// -----------------------------------------------------------------------------
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

$jobsStmt = $pdo->prepare('SELECT * FROM jobs WHERE company_id=:cid ORDER BY id DESC');
$jobsStmt->execute([':cid' => $companyId]);
$jobs = $jobsStmt->fetchAll();

// Thống kê nhanh
$jobStats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($jobs as $j) {
    $st = $j['status'] ?? 'pending';
    if (isset($jobStats[$st])) $jobStats[$st]++;
}

// Dữ liệu cho trang edit job
$editJobId         = (int)($_GET['id'] ?? 0);
$editJob           = null;
$editJobCategoryIds = [];
$editJobImages     = [];
if ($page === 'job_edit' && $editJobId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM jobs WHERE id=:id AND company_id=:cid');
    $stmt->execute([':id' => $editJobId, ':cid' => $companyId]);
    $editJob = $stmt->fetch();

    if ($editJob) {
        $stmt = $pdo->prepare('SELECT category_id FROM job_categories WHERE job_id=:jid');
        $stmt->execute([':jid' => $editJobId]);
        $editJobCategoryIds = array_map(static fn($r) => (int)$r['category_id'], $stmt->fetchAll());

        $stmt = $pdo->prepare('SELECT image_path FROM job_images WHERE job_id=:jid');
        $stmt->execute([':jid' => $editJobId]);
        $editJobImages = $stmt->fetchAll();
    }
}

// Danh sách ứng viên
$jobApplicantsJobId = (int)($_GET['id'] ?? 0);
$applicants = [];
if ($page === 'job_applicants' && $jobApplicantsJobId > 0) {
    $chk = $pdo->prepare('SELECT id, title FROM jobs WHERE id=:jid AND company_id=:cid LIMIT 1');
    $chk->execute([':jid' => $jobApplicantsJobId, ':cid' => $companyId]);
    $applicantsJob = $chk->fetch();

    if ($applicantsJob) {
        $stmt = $pdo->prepare('
            SELECT
                a.id AS application_id,
                a.status AS application_status,
                a.rejection_reason,
                j.title AS job_title,
                u.id AS candidate_id,
                u.name AS candidate_name,
                u.avatar AS candidate_avatar,
                cp.phone, cp.address, cp.experience, cp.education,
                a.cv_id,
                cvs.file_path AS cv_file_path,
                i.interview_date,
                i.location AS interview_location,
                i.note AS interview_note
            FROM applications a
            JOIN jobs j ON j.id = a.job_id
            JOIN users u ON u.id = a.candidate_id
            LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
            LEFT JOIN cvs ON cvs.id = a.cv_id
            LEFT JOIN interviews i ON i.application_id = a.id
            WHERE a.job_id = :jid
            ORDER BY a.id DESC
        ');
        $stmt->execute([':jid' => $jobApplicantsJobId]);
        $applicants = $stmt->fetchAll();
    }
}

render_header('Trang nhà tuyển dụng');
?>

<div class="row g-4">
    <!-- -- Sidebar ---------------------------------------------------------- -->
    <div class="col-lg-3">
        <div class="app-card p-3">
            <div class="p-3 soft-border rounded-14 bg-white">
                <div class="muted small">Nhà tuyển dụng</div>
                <div class="fw-bold"><?= e((string)($company['name'] ?? '')) ?></div>
                <div class="muted small mt-1"><i class="fa-solid fa-briefcase me-1"></i><?= e((string)$position) ?></div>
            </div>

            <!-- Badge job bị từ chối để nhắc nhở -->
            <?php if ($jobStats['rejected'] > 0): ?>
                <div class="alert alert-danger py-2 mt-2 mb-0 rounded-14" style="font-size:.85rem">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <strong><?= $jobStats['rejected'] ?></strong> tin bị từ chối
                    <a href="<?= e(BASE_URL) ?>/employer/index.php?page=jobs" class="text-danger fw-bold ms-1">Xem →</a>
                </div>
            <?php endif; ?>

            <div class="list-group list-group-flush mt-3">
                <a href="<?= e(BASE_URL) ?>/employer/index.php?page=company"
                   class="list-group-item list-group-item-action <?= $page === 'company' ? 'active' : '' ?>">Hồ sơ công ty</a>
                <a href="<?= e(BASE_URL) ?>/employer/index.php?page=jobs"
                   class="list-group-item list-group-item-action <?= $page === 'jobs' ? 'active' : '' ?>">
                    Việc làm
                    <?php if ($jobStats['rejected'] > 0): ?>
                        <span class="badge bg-danger float-end"><?= $jobStats['rejected'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= e(BASE_URL) ?>/employer/index.php?page=dashboard"
                   class="list-group-item list-group-item-action <?= $page === 'dashboard' ? 'active' : '' ?>">Tổng quan</a>
            </div>
        </div>
    </div>

    <!-- -- Main content ----------------------------------------------------- -->
    <div class="col-lg-9">
        <?php if (!$companyId): ?>
            <div class="app-card p-4">
                <div class="alert alert-warning mb-0">Tài khoản này chưa được gắn với công ty nào.</div>
            </div>

        <?php elseif ($page === 'dashboard'): ?>
            <!-- -- Tổng quan ------------------------------------------------- -->
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-gauge-high me-2"></i>Tổng quan</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="app-card p-3 soft-border bg-white">
                            <div class="muted small">Chờ duyệt</div>
                            <div class="fs-4 fw-bold text-warning"><?= $jobStats['pending'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-card p-3 soft-border bg-white">
                            <div class="muted small">Đã duyệt</div>
                            <div class="fs-4 fw-bold text-success"><?= $jobStats['approved'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-card p-3 soft-border bg-white">
                            <div class="muted small">Bị từ chối</div>
                            <div class="fs-4 fw-bold text-danger"><?= $jobStats['rejected'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="<?= e(BASE_URL) ?>/employer/index.php?page=jobs&action=create_form" class="btn btn-success">
                        <i class="fa-solid fa-plus me-2"></i>Đăng việc làm mới
                    </a>
                </div>
            </div>

        <?php elseif ($page === 'company'): ?>
            <!-- -- Hồ sơ công ty ---------------------------------------------- -->
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-building me-2"></i>Hồ sơ công ty</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_company">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tên công ty</label>
                            <input class="form-control" name="company_name" value="<?= e((string)($company['name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Địa chỉ công ty</label>
                            <input class="form-control" name="company_address" value="<?= e((string)($company['address'] ?? '')) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="company_description" rows="4"><?= e((string)($company['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Logo</label>
                            <input class="form-control" type="file" name="company_logo" accept="image/*">
                            <?php if (!empty($company['logo'])): ?>
                                <div class="mt-2">
                                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$company['logo']) ?>"
                                         style="width:120px;height:auto;object-fit:contain;"
                                         class="rounded-14 soft-border bg-white p-2" alt="Logo">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-12">
                            <button class="btn btn-success">
                                <i class="fa-solid fa-floppy-disk me-2"></i>Lưu thông tin công ty
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        <?php elseif ($page === 'jobs'): ?>
            <!-- -- Danh sách việc làm ------------------------------------------ -->
            <?php $showCreate = ($_GET['action'] ?? '') === 'create_form'; ?>

            <?php if ($showCreate): ?>
                <!-- Form tạo job mới -->
                <div class="app-card p-4">
                    <h4 class="mb-3"><i class="fa-solid fa-clipboard-plus me-2"></i>Đăng việc làm mới</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create_job">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tiêu đề việc làm *</label>
                                <input class="form-control" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mức lương (hiển thị)</label>
                                <input class="form-control" name="salary" placeholder="VD: 10-15 triệu">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lương tối thiểu (VNĐ)</label>
                                <input class="form-control" type="number" name="salary_min" placeholder="10000000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lương tối đa (VNĐ)</label>
                                <input class="form-control" type="number" name="salary_max" placeholder="15000000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Địa điểm</label>
                                <input class="form-control" name="location" placeholder="Hà Nội, HCM...">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Mô tả</label>
                                <textarea class="form-control" name="description" rows="4"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Yêu cầu</label>
                                <textarea class="form-control" name="requirement" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Ảnh chính *</label>
                                <input class="form-control" type="file" name="image" accept="image/*" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Ảnh phụ (nhiều ảnh)</label>
                                <input class="form-control" type="file" name="job_images[]" accept="image/*" multiple>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Danh mục</label>
                                <div class="row g-2">
                                    <?php foreach ($categories as $cat): ?>
                                        <div class="col-md-4">
                                            <div class="soft-border rounded-14 p-3 bg-white">
                                                <label class="d-flex gap-2 align-items-center">
                                                    <input type="checkbox" name="categories[]" value="<?= (int)$cat['id'] ?>">
                                                    <span class="fw-bold"><?= e((string)$cat['name']) ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button class="btn btn-success">
                                    <i class="fa-solid fa-plus me-2"></i>Tạo việc làm (chờ duyệt)
                                </button>
                                <a href="<?= e(BASE_URL) ?>/employer/index.php?page=jobs" class="btn btn-outline-secondary ms-2">Hủy</a>
                            </div>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Danh sách job -->
                <div class="app-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0"><i class="fa-solid fa-briefcase me-2"></i>Việc làm của bạn</h4>
                        <a href="<?= e(BASE_URL) ?>/employer/index.php?page=jobs&action=create_form"
                           class="btn btn-success btn-sm">
                            <i class="fa-solid fa-plus me-2"></i>Đăng việc làm
                        </a>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($jobs as $j): ?>
                            <div class="col-md-6">
                                <div class="app-card p-3 soft-border bg-white h-100">

                                    <!-- Header card -->
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-bold"><?= e((string)$j['title']) ?></div>
                                            <div class="muted small">
                                                <i class="fa-solid fa-location-dot me-1"></i><?= e((string)$j['location']) ?>
                                            </div>
                                        </div>
                                        <?php
                                        [$badgeCls, $badgeText] = match($j['status'] ?? '') {
                                            'approved' => ['bg-success',          '✅ Đã duyệt'],
                                            'rejected' => ['bg-danger',           '❌ Từ chối'],
                                            default    => ['bg-warning text-dark', '⏳ Chờ duyệt'],
                                        };
                                        ?>
                                        <span class="badge <?= $badgeCls ?>"><?= $badgeText ?></span>
                                    </div>

                                    <!-- Ảnh job -->
                                    <?php if (!empty($j['image'])): ?>
                                        <div class="mt-2">
                                            <img src="<?= e(BASE_URL) ?>/<?= e((string)$j['image']) ?>"
                                                 class="job-img" alt="Ảnh việc làm" style="height:120px;">
                                        </div>
                                    <?php endif; ?>

                                    <!-- ------------------------------------------------------------
                                         THÔNG BÁO BỊ TỪ CHỐI + NÚT ĐĂNG LẠI
                                    ------------------------------------------------------------ -->
                                    <?php if (($j['status'] ?? '') === 'rejected'): ?>
                                        <div class="alert alert-danger py-2 mt-3 mb-0">
                                            <div class="fw-bold mb-1">
                                                <i class="fa-solid fa-circle-xmark me-1"></i>Tin bị từ chối
                                            </div>
                                            <?php if (!empty($j['rejection_reason'])): ?>
                                                <div class="small mb-2">
                                                    <strong>Lý do:</strong> <?= e((string)$j['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Nút "Chỉnh sửa & Đăng lại" -->
                                            <a href="<?= e(BASE_URL) ?>/employer/index.php?page=job_edit&id=<?= (int)$j['id'] ?>&repost=1"
                                               class="btn btn-danger btn-sm mt-1">
                                                <i class="fa-solid fa-rotate-right me-1"></i>Chỉnh sửa & Đăng lại
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Nút hành động -->
                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <a href="<?= e(BASE_URL) ?>/employer/index.php?page=job_edit&id=<?= (int)$j['id'] ?>"
                                           class="btn btn-outline-success btn-sm">
                                            <i class="fa-solid fa-pen me-1"></i>Sửa
                                        </a>
                                        <a href="<?= e(BASE_URL) ?>/employer/index.php?page=job_applicants&id=<?= (int)$j['id'] ?>"
                                           class="btn btn-success btn-sm">
                                            <i class="fa-solid fa-users me-1"></i>Ứng viên
                                        </a>
                                        <form method="POST" style="display:inline-block;"
                                              onsubmit="return confirm('Xóa việc làm này?')">
                                            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_job">
                                            <input type="hidden" name="job_id" value="<?= (int)$j['id'] ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit">
                                                <i class="fa-solid fa-trash me-1"></i>Xóa
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($jobs)): ?>
                            <div class="muted">Chưa có việc làm nào.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($page === 'job_edit'): ?>
            <!-- -- Chỉnh sửa / Đăng lại job ------------------------------------ -->
            <div class="app-card p-4">
                <?php
                // Detect mode: đăng lại hay chỉ sửa thường
                $isRepostMode = ((int)($_GET['repost'] ?? 0)) === 1
                             || (($editJob['status'] ?? '') === 'rejected');
                ?>
                <h4 class="mb-1">
                    <i class="fa-solid fa-pen-to-square me-2"></i>
                    <?= $isRepostMode ? 'Chỉnh sửa & Đăng lại' : 'Chỉnh sửa việc làm' ?>
                </h4>

                <?php if (!$editJob): ?>
                    <div class="alert alert-warning">Không tìm thấy việc làm.</div>
                <?php else: ?>

                    <!-- Banner từ chối nếu đang ở mode repost -->
                    <?php if ($isRepostMode && !empty($editJob['rejection_reason'])): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <strong>Lý do từ chối trước đó:</strong> <?= e((string)$editJob['rejection_reason']) ?>
                            <div class="mt-1 small">Hãy cập nhật nội dung và nhấn <strong>"Lưu & Gửi duyệt lại"</strong>.</div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_job">
                        <input type="hidden" name="job_id" value="<?= (int)$editJob['id'] ?>">
                        <!-- Flag đăng lại: 1 = reset về pending -->
                        <input type="hidden" name="repost" value="<?= $isRepostMode ? '1' : '0' ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tiêu đề việc làm *</label>
                                <input class="form-control" name="title" value="<?= e((string)$editJob['title']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mức lương (hiển thị)</label>
                                <input class="form-control" name="salary" value="<?= e((string)$editJob['salary']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lương tối thiểu (VNĐ)</label>
                                <input class="form-control" type="number" name="salary_min"
                                       value="<?= e((string)($editJob['salary_min'] ?? '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lương tối đa (VNĐ)</label>
                                <input class="form-control" type="number" name="salary_max"
                                       value="<?= e((string)($editJob['salary_max'] ?? '')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Địa điểm</label>
                                <input class="form-control" name="location" value="<?= e((string)$editJob['location']) ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Mô tả</label>
                                <textarea class="form-control" name="description" rows="4"><?= e((string)$editJob['description']) ?></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Yêu cầu</label>
                                <textarea class="form-control" name="requirement" rows="3"><?= e((string)$editJob['requirement']) ?></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Thay ảnh chính (không bắt buộc)</label>
                                <input class="form-control" type="file" name="image" accept="image/*">
                                <?php if (!empty($editJob['image'])): ?>
                                    <div class="mt-2">
                                        <img src="<?= e(BASE_URL) ?>/<?= e((string)$editJob['image']) ?>"
                                             style="width:100%;max-height:200px;object-fit:cover;"
                                             class="rounded-14 soft-border bg-light" alt="Ảnh hiện tại">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Thêm ảnh phụ</label>
                                <input class="form-control" type="file" name="job_images[]" accept="image/*" multiple>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Danh mục</label>
                                <div class="row g-2">
                                    <?php foreach ($categories as $cat): ?>
                                        <?php $checked = in_array((int)$cat['id'], $editJobCategoryIds, true); ?>
                                        <div class="col-md-4">
                                            <div class="soft-border rounded-14 p-3 bg-white">
                                                <label class="d-flex gap-2 align-items-center">
                                                    <input type="checkbox" name="categories[]"
                                                           value="<?= (int)$cat['id'] ?>"
                                                           <?= $checked ? 'checked' : '' ?>>
                                                    <span class="fw-bold"><?= e((string)$cat['name']) ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <?php if ($isRepostMode): ?>
                                    <button class="btn btn-danger">
                                        <i class="fa-solid fa-rotate-right me-2"></i>Lưu & Gửi duyệt lại
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-success">
                                        <i class="fa-solid fa-floppy-disk me-2"></i>Lưu thay đổi
                                    </button>
                                <?php endif; ?>
                                <a href="<?= e(BASE_URL) ?>/employer/index.php?page=jobs" class="btn btn-outline-secondary ms-2">Quay lại</a>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($editJobImages)): ?>
                        <hr class="my-4">
                        <h6 class="mb-2"><i class="fa-solid fa-images me-2"></i>Ảnh phụ hiện có</h6>
                        <div class="row g-3">
                            <?php foreach ($editJobImages as $img): ?>
                                <div class="col-md-6">
                                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$img['image_path']) ?>"
                                         class="job-img" alt="Ảnh phụ">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'job_applicants'): ?>
            <!-- ── Danh sách ứng viên ──────────────────────────────────── -->
            <div class="app-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">
                        <i class="fa-solid fa-users me-2"></i>Ứng viên
                        <?php if (!empty($applicantsJob['title'])): ?>
                            <span class="muted" style="font-size:.9rem">— <?= e((string)$applicantsJob['title']) ?></span>
                        <?php endif; ?>
                    </h4>
                    <a href="<?= e(BASE_URL) ?>/employer/index.php?page=jobs" class="btn btn-outline-secondary btn-sm">
                        ← Quay lại
                    </a>
                </div>

                <?php if ($jobApplicantsJobId <= 0): ?>
                    <div class="alert alert-warning">Chưa chọn việc làm.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($applicants as $app): ?>
                            <div class="col-lg-12">
                                <div class="app-card p-3 soft-border bg-white">
                                    <!-- Thông tin ứng viên -->
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="d-flex gap-3">
                                            <?php if (!empty($app['candidate_avatar'])): ?>
                                                <img src="<?= e(BASE_URL) ?>/<?= e((string)$app['candidate_avatar']) ?>"
                                                     style="width:56px;height:56px;object-fit:cover;"
                                                     class="rounded-14 soft-border bg-white p-1" alt="Avatar">
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold fs-5"><?= e((string)$app['candidate_name']) ?></div>
                                                <div class="muted small">
                                                    <i class="fa-solid fa-phone me-1"></i><?= e((string)($app['phone'] ?? '')) ?>
                                                    <span class="mx-2">|</span>
                                                    <i class="fa-solid fa-location-dot me-1"></i><?= e((string)($app['address'] ?? '')) ?>
                                                </div>
                                                <div class="muted small mt-1"><?= e((string)($app['experience'] ?? '')) ?></div>
                                            </div>
                                        </div>
                                        <?php
                                        [$abadge, $alabel] = match($app['application_status']) {
                                            'accepted' => ['bg-success',          'Chấp nhận'],
                                            'rejected' => ['bg-danger',           'Từ chối'],
                                            default    => ['bg-warning text-dark', 'Chờ xử lý'],
                                        };
                                        ?>
                                        <span class="badge <?= $abadge ?>"><?= $alabel ?></span>
                                    </div>

                                    <!-- CV -->
                                    <div class="mt-3">
                                        <?php if (!empty($app['cv_file_path'])): ?>
                                            <a class="btn btn-outline-success btn-sm"
                                               href="<?= e(BASE_URL) ?>/<?= e((string)$app['cv_file_path']) ?>" target="_blank">
                                                <i class="fa-solid fa-file-pdf me-2"></i>Mở CV
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Trạng thái hiện tại -->
                                    <div class="mt-3">
                                        <?php if ($app['application_status'] === 'rejected'): ?>
                                            <div class="alert alert-danger py-2 mb-2">
                                                <div class="fw-bold"><i class="fa-solid fa-ban me-1"></i>Đã từ chối</div>
                                                <div class="small">Lý do: <?= e((string)($app['rejection_reason'] ?? 'Không có')) ?></div>
                                            </div>
                                        <?php elseif (!empty($app['interview_date'])): ?>
                                            <div class="alert alert-success py-2 mb-2">
                                                <div class="fw-bold"><i class="fa-solid fa-calendar me-1"></i>Đã lên lịch phỏng vấn</div>
                                                <div class="small">Ngày: <?= e((string)$app['interview_date']) ?></div>
                                                <div class="small">Địa điểm: <?= e((string)($app['interview_location'] ?? '')) ?></div>
                                                <?php if (!empty($app['interview_note'])): ?>
                                                    <div class="small muted mt-1"><?= e((string)$app['interview_note']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($app['application_status'] !== 'rejected'): ?>
                                            <div class="row g-2">
                                                <!-- Form lịch phỏng vấn -->
                                                <div class="col-lg-12">
                                                    <form method="POST" class="app-card p-3 soft-border bg-white">
                                                        <input type="hidden" name="csrf"           value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="action"         value="schedule_interview">
                                                        <input type="hidden" name="job_id"         value="<?= (int)$jobApplicantsJobId ?>">
                                                        <input type="hidden" name="application_id" value="<?= (int)$app['application_id'] ?>">
                                                        <div class="row g-3 align-items-end">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Ngày giờ phỏng vấn</label>
                                                                <input class="form-control" type="datetime-local" name="interview_date"
                                                                       value="<?= !empty($app['interview_date']) ? e(str_replace(' ', 'T', (string)$app['interview_date'])) : '' ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Địa điểm</label>
                                                                <input class="form-control" name="location"
                                                                       value="<?= e((string)($app['interview_location'] ?? '')) ?>">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Ghi chú</label>
                                                                <input class="form-control" name="note"
                                                                       value="<?= e((string)($app['interview_note'] ?? '')) ?>">
                                                            </div>
                                                            <div class="col-md-12">
                                                                <button class="btn btn-success btn-sm">
                                                                    <i class="fa-solid fa-calendar-check me-1"></i>Lên lịch
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                                <!-- Form từ chối -->
                                                <div class="col-lg-12">
                                                    <form method="POST" class="app-card p-3 soft-border bg-white">
                                                        <input type="hidden" name="csrf"           value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="action"         value="reject_application">
                                                        <input type="hidden" name="job_id"         value="<?= (int)$jobApplicantsJobId ?>">
                                                        <input type="hidden" name="application_id" value="<?= (int)$app['application_id'] ?>">
                                                        <div class="row g-3">
                                                            <div class="col-md-12">
                                                                <label class="form-label">Lý do từ chối</label>
                                                                <textarea class="form-control" name="rejection_reason" rows="2"
                                                                          placeholder="Ví dụ: Vị trí đã đủ người..." required></textarea>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <button class="btn btn-outline-danger btn-sm" type="submit"
                                                                        onclick="return confirm('Từ chối ứng viên này?')">
                                                                    <i class="fa-solid fa-ban me-1"></i>Từ chối
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($applicants)): ?>
                            <div class="muted">Chưa có ứng viên nào.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
