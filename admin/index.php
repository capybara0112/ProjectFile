<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';

require_login('admin');

$pdo  = db();
$page = $_GET['page'] ?? 'dashboard';

// ──────────────────────────────────────────────────────────────────────────────────────────────────
// Admin actions
// ──────────────────────────────────────────────────────────────────────────────────────────────────
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        verify_csrf();

        // ──────────────────────────────────────────────────────────────────────────────────────────────────
        if ($action === 'reset_user_password') {
            $userId  = (int)($_POST['user_id'] ?? 0);
            $newPass = (string)($_POST['new_password'] ?? '');
            if ($userId <= 0 || $newPass === '') {
                throw new RuntimeException('Dữ liệu đầu vào không hợp lệ.');
            }
            $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id')
                ->execute([':pw' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => $userId]);
            flash('Đổi mật khẩu thành công.', 'success');
            redirect('/admin/index.php?page=users');
        }

        // ──────────────────────────────────────────────────────────────────────────────────────────────────
        if ($action === 'approve_job') {
            $jobId  = (int)($_POST['job_id'] ?? 0);
            $status = trim((string)($_POST['status'] ?? ''));

            if ($jobId <= 0) throw new RuntimeException('Mã việc làm không hợp lệ.');
            if (!in_array($status, ['approved', 'rejected'], true)) throw new RuntimeException('Trạng thái không hợp lệ.');

            // Lấy thông tin job
            $stmt = $pdo->prepare('SELECT j.company_id, j.title FROM jobs j WHERE j.id = :id');
            $stmt->execute([':id' => $jobId]);
            $job = $stmt->fetch();
            if (!$job) throw new RuntimeException('Không tìm thấy việc làm.');

            $rejectReason = trim((string)($_POST['reject_reason'] ?? ''));

            // Cập nhật trạng thái + Lưu lý do từ chối vào cột rejection_reason của jobs
            $pdo->prepare('UPDATE jobs SET status = :st, rejection_reason = :reason WHERE id = :id')
                ->execute([
                    ':st'     => $status,
                    ':reason' => ($status === 'rejected' && $rejectReason !== '') ? $rejectReason : null,
                    ':id'     => $jobId,
                ]);

            // Tìm employer để gửi thông báo
            $emStmt = $pdo->prepare('SELECT user_id FROM employers WHERE company_id = :cid LIMIT 1');
            $emStmt->execute([':cid' => (int)$job['company_id']]);
            $em = $emStmt->fetch();

            if ($em) {
                $empUid   = (int)$em['user_id'];
                $jobTitle = (string)$job['title'];

                if ($status === 'approved') {
                    $notif = '✅ Tin tuyển dụng "' . $jobTitle . '" đã được admin duyệt và hiển thị công khai.';
                } else {
                    // Thông báo kèm lý do và hướng dẫn đăng lại
                    $notif = '❌ Tin tuyển dụng "' . $jobTitle . '" đã bị từ chối.';
                    if ($rejectReason !== '') {
                        $notif .= ' Lý do: ' . $rejectReason . '.';
                    }
                    $notif .= ' Bạn có thể chỉnh sửa và đăng lại từ trang Việc làm.';
                }

                $pdo->prepare('INSERT INTO notifications (user_id, content) VALUES (:uid, :content)')
                    ->execute([':uid' => $empUid, ':content' => $notif]);
            }

            flash('Cập nhật việc làm thành công.', 'success');
            redirect('/admin/index.php?page=jobs');
        }

        // ──────────────────────────────────────────────────────────────────────────────────────────────────
        if ($action === 'add_category') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Tên danh mục là bắt buộc.');
            $pdo->prepare('INSERT INTO categories (name) VALUES (:name)')->execute([':name' => $name]);
            flash('Thêm danh mục thành công.', 'success');
            redirect('/admin/index.php?page=categories');
        }

        if ($action === 'edit_category') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $name  = trim((string)($_POST['name'] ?? ''));
            if ($catId <= 0 || $name === '') throw new RuntimeException('Dữ liệu đầu vào không hợp lệ.');
            $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id')->execute([':name' => $name, ':id' => $catId]);
            flash('Cập nhật danh mục thành công.', 'success');
            redirect('/admin/index.php?page=categories');
        }

        if ($action === 'delete_category') {
            $catId = (int)($_POST['category_id'] ?? 0);
            if ($catId <= 0) throw new RuntimeException('Mã danh mục không hợp lệ.');
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM job_categories WHERE category_id = :id')->execute([':id' => $catId]);
                $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $catId]);
                $pdo->commit();
                flash('Xóa danh mục thành công.', 'success');
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            redirect('/admin/index.php?page=categories');
        }
    }
} catch (Throwable $e) {
    flash('Thao tác thất bại: ' . $e->getMessage(), 'danger');
    redirect('/admin/index.php');
}


$pendingJobs = $pdo->query('
    SELECT j.*, c.name AS company_name, c.address AS company_address,
           cb.province, cb.address_detail, cb.full_address,
           COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) AS location_label
    FROM jobs j JOIN companies c ON c.id = j.company_id
    LEFT JOIN company_branches cb ON cb.id = j.branch_id
    WHERE j.status = "pending"
    ORDER BY j.id DESC
')->fetchAll();

$allJobs = $pdo->query('
    SELECT j.*, c.name AS company_name, c.address AS company_address,
           cb.province, cb.address_detail, cb.full_address,
           COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) AS location_label
    FROM jobs j JOIN companies c ON c.id = j.company_id
    LEFT JOIN company_branches cb ON cb.id = j.branch_id
    ORDER BY j.id DESC
')->fetchAll();

$users      = $pdo->query('SELECT id, name, email, role, avatar FROM users ORDER BY id DESC')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

$editCategoryId = (int)($_GET['id'] ?? 0);
$editCategory   = null;
if ($page === 'categories' && $editCategoryId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute([':id' => $editCategoryId]);
    $editCategory = $stmt->fetch();
}

render_header('Trang quản trị');
?>

<div class="row g-4">
    <!-- Sidebar -->
    <div class="col-lg-3">
        <div class="app-card p-3">
            <div class="p-3 soft-border rounded-14 bg-white">
                <div class="fw-bold"><i class="fa-solid fa-shield-halved me-2"></i>Quản trị</div>
                <div class="muted small mt-1">Duyệt việc làm và quản lý hệ thống</div>
            </div>
            <div class="list-group list-group-flush mt-3">
                <a href="<?= e(BASE_URL) ?>/admin/index.php?page=jobs"
                   class="list-group-item list-group-item-action <?= $page === 'jobs' ? 'active' : '' ?>">
                    Duyệt việc làm
                    <?php if (count($pendingJobs) > 0): ?>
                        <span class="badge bg-danger float-end"><?= count($pendingJobs) ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= e(BASE_URL) ?>/admin/index.php?page=users"
                   class="list-group-item list-group-item-action <?= $page === 'users' ? 'active' : '' ?>">Quản lý người dùng</a>
                <a href="<?= e(BASE_URL) ?>/admin/index.php?page=categories"
                   class="list-group-item list-group-item-action <?= $page === 'categories' ? 'active' : '' ?>">Danh mục</a>
                <a href="<?= e(BASE_URL) ?>/admin/index.php?page=dashboard"
                   class="list-group-item list-group-item-action <?= $page === 'dashboard' ? 'active' : '' ?>">Tổng quan</a>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="col-lg-9">

        <?php if ($page === 'dashboard'): ?>
            <!-- Tổng quan -->
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-gauge-high me-2"></i>Tổng quan</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="app-card p-3 soft-border bg-white">
                            <div class="muted small">Việc làm chờ duyệt</div>
                            <div class="fs-4 fw-bold <?= count($pendingJobs) > 0 ? 'text-danger' : '' ?>">
                                <?= count($pendingJobs) ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-card p-3 soft-border bg-white">
                            <div class="muted small">Người dùng</div>
                            <div class="fs-4 fw-bold"><?= count($users) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-card p-3 soft-border bg-white">
                            <div class="muted small">Danh mục</div>
                            <div class="fs-4 fw-bold"><?= count($categories) ?></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($page === 'jobs'): ?>
            <!-- Duyệt việc làm -->
            <div class="app-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="fa-solid fa-list-check me-2"></i>Duyệt / từ chối việc làm</h4>
                    <a class="btn btn-outline-success btn-sm"
                       href="<?= e(BASE_URL) ?>/admin/index.php?page=jobs&show=<?= ($_GET['show'] ?? '') === 'all' ? '' : 'all' ?>">
                        <?= ($_GET['show'] ?? '') === 'all' ? '⏳ Chỉ chờ duyệt' : '📋 Tất cả việc làm' ?>
                    </a>
                </div>

                <?php
                $showAll = ($_GET['show'] ?? '') === 'all';
                $list    = $showAll ? $allJobs : $pendingJobs;
                ?>

                <?php if (empty($list)): ?>
                    <div class="alert alert-success">✅ Không có việc làm nào đang chờ duyệt.</div>
                <?php endif; ?>

                <div class="d-flex flex-column gap-3">
                    <?php foreach ($list as $job): ?>
                        <div class="app-card p-4 soft-border bg-white">

                            <!-- Header: tên job + badge trạng thái -->
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-bold fs-5"><?= e((string)$job['title']) ?></div>
                                    <div class="muted small mt-1">
                                        <i class="fa-solid fa-building me-1"></i><?= e((string)$job['company_name']) ?>
                                        <span class="mx-2">·</span>
                                        <i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($job)) ?>
                                        <span class="mx-2">·</span>
                                        <i class="fa-solid fa-money-bill-wave me-1"></i><?= e(job_salary_label($job)) ?>
                                    </div>
                                    <?php if (!empty($job['requirement'])): ?>
                                        <div class="muted small mt-2"><?= e(mb_strimwidth((string)$job['requirement'], 0, 160, '…')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php
                                [$badgeCls, $badgeLabel] = match($job['status'] ?? '') {
                                    'approved' => ['bg-success',        '✅ Đã duyệt'],
                                    'rejected' => ['bg-danger',         '❌ Từ chối'],
                                    default    => ['bg-warning text-dark','⏳ Chờ duyệt'],
                                };
                                ?>
                                <span class="badge <?= $badgeCls ?> px-3 py-2 fs-6 flex-shrink-0"><?= $badgeLabel ?></span>
                            </div>

                            <!-- Lý do từ chối cũ (nếu có) -->
                            <?php if (($job['status'] ?? '') === 'rejected' && !empty($job['rejection_reason'])): ?>
                                <div class="alert alert-warning py-2 mt-3 mb-0">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                    Lý do từ chối trước: <strong><?= e((string)$job['rejection_reason']) ?></strong>
                                </div>
                            <?php endif; ?>

                            <!-- Khu vực action -->
                            <div class="mt-3 d-flex gap-2 align-items-end flex-wrap">

                                <!-- Nút Duyệt -->
                                <form method="POST"
                                      onsubmit="return confirm('Duyệt tin tuyển dụng «<?= e((string)$job['title']) ?>»?')">
                                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="approve_job">
                                    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button class="btn btn-success" type="submit">
                                        <i class="fa-solid fa-check me-1"></i>Duyệt
                                    </button>
                                </form>

                                <!-- Từ chối kèm ô nhập lý do -->
                                <form method="POST" class="d-flex gap-2 align-items-end flex-grow-1"
                                      onsubmit="return confirm('Từ chối tin tuyển dụng này?')">
                                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="approve_job">
                                    <input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <div class="flex-grow-1">
                                        <label class="form-label mb-1" style="font-size:.82rem">
                                            Lý do từ chối
                                            <span class="muted">(không bắt buộc — nhà tuyển dụng sẽ nhận được)</span>
                                        </label>
                                        <input class="form-control form-control-sm"
                                               name="reject_reason"
                                               placeholder="Vi phạm quy định, thông tin không đầy đủ, nội dung không phù hợp..."
                                               value="<?= e((string)($job['rejection_reason'] ?? '')) ?>">
                                    </div>
                                    <button class="btn btn-outline-danger" type="submit">
                                        <i class="fa-solid fa-xmark me-1"></i>Từ chối
                                    </button>
                                </form>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($page === 'users'): ?>
            <!-- Quản lý người dùng -->
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-users me-2"></i>Quản lý người dùng</h4>
                <div class="row g-3">
                    <?php foreach ($users as $u): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-bold"><?= e((string)$u['name']) ?></div>
                                        <div class="muted small"><?= e((string)$u['email']) ?></div>
                                    </div>
                                    <span class="badge text-bg-light soft-border"><?= e((string)$u['role']) ?></span>
                                </div>
                                <div class="mt-3">
                                    <form method="POST" class="row g-2 align-items-end">
                                        <input type="hidden" name="csrf"    value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action"  value="reset_user_password">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <div class="col-7">
                                            <label class="form-label">Mật khẩu mới</label>
                                            <input class="form-control" type="password" name="new_password" value="123456" required>
                                        </div>
                                        <div class="col-5">
                                            <button class="btn btn-success w-100" type="submit">
                                                <i class="fa-solid fa-key me-2"></i>Đặt lại
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($page === 'categories'): ?>
            <!-- Danh mục -->
            <div class="app-card p-4">
                <h4 class="mb-3"><i class="fa-solid fa-tags me-2"></i>Quản lý Danh mục</h4>

                <?php if ($editCategory): ?>
                    <div class="app-card p-3 soft-border bg-white mb-4">
                        <h5 class="mb-3">Sửa Danh mục</h5>
                        <form method="POST">
                            <input type="hidden" name="csrf"        value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action"      value="edit_category">
                            <input type="hidden" name="category_id" value="<?= (int)$editCategory['id'] ?>">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label">Tên</label>
                                    <input class="form-control" name="name" value="<?= e((string)$editCategory['name']) ?>" required>
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button class="btn btn-success w-100" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Lưu</button>
                                    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/admin/index.php?page=categories">Hủy</a>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="app-card p-3 soft-border bg-white mb-4">
                    <h5 class="mb-3">Thêm Danh mục</h5>
                    <form method="POST">
                        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_category">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label">Tên</label>
                                <input class="form-control" name="name" required placeholder="Ví dụ IT, Marketing">
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-success w-100" type="submit"><i class="fa-solid fa-plus me-2"></i>Thêm</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="row g-3">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fw-bold"><?= e((string)$cat['name']) ?></div>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-outline-success btn-sm"
                                           href="<?= e(BASE_URL) ?>/admin/index.php?page=categories&id=<?= (int)$cat['id'] ?>">
                                            <i class="fa-solid fa-pen me-1"></i>Sửa
                                        </a>
                                        <form method="POST" style="display:inline-block;"
                                              onsubmit="return confirm('Xóa Danh mục «<?= e((string)$cat['name']) ?>»?')">
                                            <input type="hidden" name="csrf"        value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action"      value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit">
                                                <i class="fa-solid fa-trash me-1"></i>Xóa
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>