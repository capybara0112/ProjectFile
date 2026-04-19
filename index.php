<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/upload.php';

// —————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————
// Routes công khai:
//   /           → trang chủ
//   /?page=jobs → danh sách việc làm (filter + phân trang)
//   /?page=job&id=X   → chi tiết 1 việc làm + ứng tuyển
//   /?page=company&id=X → trang hồ sơ công ty + danh sách job của công ty
// -------------------------------------------------------------

$pdo = db();

$page    = $_GET['page'] ?? 'home';
$perPage = 8;
$pageNum = max(1, (int)($_GET['p'] ?? 1));

$keyword    = trim((string)($_GET['keyword']  ?? ''));
$categoryId = (int)($_GET['category']         ?? 0);
$location   = trim((string)($_GET['location'] ?? ''));

// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
//  TRANG HỒ SƠ CÔNG TY  (?page=company&id=X)
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
if ($page === 'company' && isset($_GET['id'])) {
    $companyId = (int)$_GET['id'];

    // Tải thông tin công ty
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id');
    $stmt->execute([':id' => $companyId]);
    $co = $stmt->fetch();

    if (!$co) {
        render_header('Không tìm thấy công ty');
        echo '<div class="app-card p-4"><h4>công ty không tồn tại.</h4>'
           . '<a href="' . e(BASE_URL) . '/?page=jobs" class="btn btn-success mt-3">← Quay lại việc làm</a></div>';
        render_footer();
        exit;
    }

    // chi nhánh công ty (báº£ng company_branches)
    $branchStmt = $pdo->prepare('SELECT id, branch_name, province, address_detail, full_address, COALESCE(NULLIF(full_address, ""), NULLIF(address_detail, ""), NULLIF(province, ""), NULLIF(branch_name, "")) AS location_label FROM company_branches WHERE company_id = :cid ORDER BY is_headquarter DESC, id ASC');
    $branchStmt->execute([':cid' => $companyId]);
    $branches = $branchStmt->fetchAll();

    // ------------------------------------------------------------- bộ lọc job của công ty này -------------------------------------------------------------
    $fSalaryMin = strlen(trim((string)($_GET['salary_min'] ?? ''))) > 0 ? (float)$_GET['salary_min'] : null;
    $fSalaryMax = strlen(trim((string)($_GET['salary_max'] ?? ''))) > 0 ? (float)$_GET['salary_max'] : null;
    $fLocation  = trim((string)($_GET['co_location'] ?? ''));   // địa điểm / chi nhánh
    $fCategory  = (int)($_GET['co_category'] ?? 0);

    // Xây WHERE clause
    $coJobWhere  = 'WHERE j.company_id = :cid AND j.status = "approved"';
    $coJobParams = [':cid' => $companyId];

    if ($fSalaryMin !== null) {
        // Lọc theo salary_min của job nếu cột tồn tại, fallback qua text salary
        $coJobWhere .= ' AND j.salary_min >= :smin';
        $coJobParams[':smin']      = $fSalaryMin;

    }
    if ($fSalaryMax !== null) {
        $coJobWhere .= ' AND j.salary_max <= :smax';
        $coJobParams[':smax'] = $fSalaryMax;
    }
    if ($fLocation !== '') {
        $coJobWhere .= ' AND COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) LIKE :cloc';
        $coJobParams[':cloc'] = '%' . $fLocation . '%';
    }
    if ($fCategory > 0) {
        $coJobWhere .= ' AND EXISTS (SELECT 1 FROM job_categories jc WHERE jc.job_id = j.id AND jc.category_id = :ccat)';
        $coJobParams[':ccat'] = $fCategory;
    }

    // Phân trang job công ty
    $coCountStmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM jobs j
        JOIN companies c ON c.id = j.company_id
        LEFT JOIN company_branches cb ON cb.id = j.branch_id
        ' . $coJobWhere . '
    ');
    $coCountStmt->execute($coJobParams);
    $coTotal = (int)$coCountStmt->fetchColumn();

    $coTotalPages = max(1, (int)ceil($coTotal / $perPage));
    $coPage       = max(1, min((int)($_GET['p'] ?? 1), $coTotalPages));
    $coOffset     = ($coPage - 1) * $perPage;

    $coJobsStmt = $pdo->prepare('
        SELECT j.*, c.name AS company_name, c.logo AS company_logo, c.address AS company_address,
               cb.province, cb.address_detail, cb.full_address,
               COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) AS location_label
        FROM jobs j
        JOIN companies c ON c.id = j.company_id
        LEFT JOIN company_branches cb ON cb.id = j.branch_id
        ' . $coJobWhere . '
        ORDER BY j.id DESC
        LIMIT :lim OFFSET :off
    ');
    foreach ($coJobParams as $k => $v) $coJobsStmt->bindValue($k, $v);
    $coJobsStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $coJobsStmt->bindValue(':off', $coOffset, PDO::PARAM_INT);
    $coJobsStmt->execute();
    $coJobs = $coJobsStmt->fetchAll();

    // Danh mục để filter
    $allCats = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

    render_header(($co['name'] ?? 'công ty') . ' — Trang tuyển dụng');
    ?>

    <!-- ——————————————————— Header công ty ——————————————————— -->
    <div class="app-card p-4 mb-4">
        <div class="d-flex gap-4 align-items-start flex-wrap">
            <?php if (!empty($co['logo'])): ?>
                <img src="<?= e(BASE_URL) ?>/<?= e((string)$co['logo']) ?>"
                     style="width:96px;height:96px;object-fit:contain;"
                     class="rounded-14 soft-border bg-white p-2 flex-shrink-0" alt="Logo">
            <?php else: ?>
                <div class="rounded-14 soft-border bg-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:96px;height:96px;font-size:2.5rem;">💼</div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <h3 class="mb-1"><?= e((string)$co['name']) ?></h3>
                <?php if (!empty($co['address'])): ?>
                    <div class="muted small mb-1">
                        <i class="fa-solid fa-location-dot me-1"></i><?= e((string)$co['address']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($co['website'])): ?>
                    <a href="<?= e((string)$co['website']) ?>" target="_blank" class="muted small me-3">
                        <i class="fa-solid fa-globe me-1"></i><?= e((string)$co['website']) ?>
                    </a>
                <?php endif; ?>
                <!-- Mạng xã hội -->
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <?php if (!empty($co['facebook'])): ?>
                        <a href="<?= e((string)$co['facebook']) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="fa-brands fa-facebook me-1"></i>Facebook
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($co['linkedin'])): ?>
                        <a href="<?= e((string)$co['linkedin']) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="fa-brands fa-linkedin me-1"></i>LinkedIn
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($co['twitter'])): ?>
                        <a href="<?= e((string)$co['twitter']) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="fa-brands fa-twitter me-1"></i>Twitter
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Thống kê nhanh -->
            <div class="text-center">
                <div class="fw-bold fs-4 text-success"><?= (int)$coTotal ?></div>
                <div class="muted small">việc làm đang tuyển</div>
            </div>
        </div>

        <?php if (!empty($co['description'])): ?>
            <hr class="my-3">
            <p class="muted mb-0"><?= nl2br(e((string)$co['description'])) ?></p>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- ── Sidebar: bộ lọc + chi nhánh ───────────────────────────────────────────────────────────────────────────── -->
        <div class="col-lg-3">

            <!-- bộ lọc job -->
            <div class="app-card p-4 mb-3">
                <h5 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Lọc việc làm</h5>
                <form method="GET">
                    <input type="hidden" name="page" value="company">
                    <input type="hidden" name="id"   value="<?= (int)$companyId ?>">

                    <!-- lương tối thiểu -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.85rem">lương từ (VNĐ)</label>
                        <input class="form-control form-control-sm" type="number"
                               name="salary_min" step="500000"
                               placeholder="VD: 10000000"
                               value="<?= $fSalaryMin !== null ? e((string)(int)$fSalaryMin) : '' ?>">
                    </div>

                    <!-- lương tối đa -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.85rem">lương đến (VNĐ)</label>
                        <input class="form-control form-control-sm" type="number"
                               name="salary_max" step="500000"
                               placeholder="VD: 25000000"
                               value="<?= $fSalaryMax !== null ? e((string)(int)$fSalaryMax) : '' ?>">
                    </div>

                    <!-- Địa điểm / chi nhánh -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.85rem">Địa điểm / chi nhánh</label>
                        <?php if (!empty($branches)): ?>
                            <select class="form-select form-select-sm" name="co_location">
                                <option value="">Tất cả địa điểm</option>
                                <?php foreach ($branches as $br): ?>
                                    <option value="<?= e(branch_location_label($br)) ?>"
                                        <?= $fLocation === branch_location_label($br) ? 'selected' : '' ?>>
                                        <?= e(branch_location_label($br)) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="<?= e((string)($co['address'] ?? '')) ?>"
                                    <?= $fLocation === ($co['address'] ?? '') ? 'selected' : '' ?>>
                                    <?= e((string)($co['address'] ?? 'Trụ sở chính')) ?>
                                </option>
                            </select>
                        <?php else: ?>
                            <input class="form-control form-control-sm" type="text"
                                   name="co_location" placeholder="Hà Nội, HCM..."
                                   value="<?= e($fLocation) ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Danh mục -->
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.85rem">Danh mục</label>
                        <select class="form-select form-select-sm" name="co_category">
                            <option value="0">Tất cả danh mục</option>
                            <?php foreach ($allCats as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"
                                    <?= $fCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="btn btn-success w-100 btn-sm mb-2">
                        <i class="fa-solid fa-filter me-1"></i>Áp dụng bộ lọc
                    </button>
                    <?php if ($fSalaryMin || $fSalaryMax || $fLocation || $fCategory): ?>
                        <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$companyId ?>"
                           class="btn btn-outline-secondary w-100 btn-sm">
                            <i class="fa-solid fa-xmark me-1"></i>Xóa bộ lọc
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- danh sách chi nhánh -->
            <?php if (!empty($branches)): ?>
                <div class="app-card p-3">
                    <h6 class="mb-2"><i class="fa-solid fa-map-location-dot me-2"></i>chi nhánh</h6>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($branches as $br): ?>
                            <li class="py-1 border-bottom border-light">
                                <i class="fa-solid fa-location-dot text-success me-1"></i>
                                <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$companyId ?>&co_location=<?= urlencode(branch_location_label($br)) ?>"
                                   class="muted small" style="text-decoration:none">
                                    <?= e(branch_location_label($br)) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── danh sách job của công ty ───────────────────────────────────────────────────────────────────── -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0">Việc làm tại <?= e((string)$co['name']) ?></h5>
                    <div class="muted small">
                        <?= (int)$coTotal ?> vị trí
                        <?php if ($fSalaryMin || $fSalaryMax || $fLocation || $fCategory): ?>
                            <span class="badge bg-warning text-dark ms-1">Đang lọc</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-outline-success btn-sm">
                    ← Tất cả việc làm
                </a>
            </div>

            <?php if (empty($coJobs)): ?>
                <div class="app-card p-4 text-center muted">
                    Không tìm thấy việc làm phù hợp với bộ lọc.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($coJobs as $job): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <div class="d-flex gap-3">
                                    <!-- Logo — click vào cũng về trang công ty -->
                                    <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$companyId ?>">
                                        <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['company_logo']) ?>"
                                             style="width:48px;height:48px;object-fit:contain;"
                                             class="soft-border rounded-14 bg-white p-1" alt="Logo">
                                    </a>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= e((string)$job['title']) ?></div>
                                        <div class="muted small">
                                            <i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($job)) ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($job['image'])): ?>
                                    <div class="mt-2">
                                        <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['image']) ?>"
                                             class="job-img" alt="Ảnh việc làm" style="height:110px;">
                                    </div>
                                <?php endif; ?>

                                <!-- lương -->
                                <div class="mt-2">
                                    <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                                        <span class="badge bg-success" style="font-size:.8rem">
                                            💰
                                            <?php if (!empty($job['salary_min'])): ?>
                                                <?= number_format((float)$job['salary_min'] / 1000000, 1) ?>tr
                                            <?php endif; ?>
                                                    <?php if (!empty($job['salary_min']) && !empty($job['salary_max'])): ?>
                                                —
                                            <?php endif; ?>
                                            <?php if (!empty($job['salary_max'])): ?>
                                                <?= number_format((float)$job['salary_max'] / 1000000, 1) ?>tr
                                            <?php endif; ?>
                                            /tháng
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-light soft-border" style="font-size:.8rem">
                                            💰 <?= e(job_salary_label($job)) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-3 text-end">
                                    <a class="btn btn-success btn-sm"
                                       href="<?= e(BASE_URL) ?>/?page=job&id=<?= (int)$job['id'] ?>">
                                        Xem chi tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Phân trang -->
                <?php if ($coTotalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php
                            $coBase = [
                                'page'       => 'company',
                                'id'         => $companyId,
                                'salary_min' => $fSalaryMin ?? '',
                                'salary_max' => $fSalaryMax ?? '',
                                'co_location'=> $fLocation,
                                'co_category'=> $fCategory,
                            ];
                            $buildCoLink = static fn(int $p) =>
                                rtrim(BASE_URL, '/') . '/?' . http_build_query(array_filter($coBase + ['p' => $p], static fn($v) => $v !== '' && $v !== null && $v !== 0));
                            ?>
                            <li class="page-item <?= $coPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $coPage <= 1 ? '#' : e($buildCoLink($coPage - 1)) ?>">Trước</a>
                            </li>
                            <?php for ($i = 1; $i <= $coTotalPages; $i++): ?>
                                <li class="page-item <?= $i === $coPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= e($buildCoLink($i)) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $coPage >= $coTotalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $coPage >= $coTotalPages ? '#' : e($buildCoLink($coPage + 1)) ?>">Sau</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php
    render_footer();
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
//  CHI TIẾT VIỆC LÀM  (?page=job&id=X)
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
if ($page === 'job' && isset($_GET['id'])) {
    $jobId = (int)$_GET['id'];

    // Xử lý ứng tuyển
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_job') {
        verify_csrf();
        require_login('candidate');
        $user        = current_user();
        $candidateId = (int)$user['id'];

        $cvId = (int)($_POST['cv_id'] ?? 0);
        if ($cvId <= 0) {
            flash('Vui lòng chọn CV.', 'danger');
            redirect('/?page=job&id=' . $jobId);
        }

        $stmt = $pdo->prepare('SELECT id FROM cvs WHERE id=:cv_id AND user_id=:uid');
        $stmt->execute([':cv_id' => $cvId, ':uid' => $candidateId]);
        if (!$stmt->fetch()) {
            flash('CV đã chọn không hợp lệ.', 'danger');
            redirect('/?page=job&id=' . $jobId);
        }

        $stmt = $pdo->prepare('SELECT id, company_id FROM jobs WHERE id=:jid AND status="approved"');
        $stmt->execute([':jid' => $jobId]);
        $job = $stmt->fetch();
        if (!$job) {
            flash('Tin tuyển dụng không tồn tại hoặc chưa được duyệt.', 'danger');
            redirect('/?page=jobs');
        }

        $stmt = $pdo->prepare('SELECT id FROM applications WHERE job_id=:jid AND candidate_id=:cid');
        $stmt->execute([':jid' => $jobId, ':cid' => $candidateId]);
        if ($stmt->fetch()) {
            flash('Bạn đã ứng tuyển công việc này rồi.', 'info');
            redirect('/?page=job&id=' . $jobId);
        }

        $pdo->prepare('INSERT INTO applications (job_id, candidate_id, cv_id, status) VALUES (:jid, :cid, :cvid, "pending")')
            ->execute([':jid' => $jobId, ':cid' => $candidateId, ':cvid' => $cvId]);

        // Thông báo cho employer
        $emStmt = $pdo->prepare('SELECT user_id FROM employers WHERE company_id=:cid LIMIT 1');
        $emStmt->execute([':cid' => (int)$job['company_id']]);
        $em = $emStmt->fetch();
        if ($em) {
            $pdo->prepare('INSERT INTO notifications (user_id, content) VALUES (:uid, :content)')
                ->execute([':uid' => (int)$em['user_id'],
                           ':content' => sprintf('Ứng viên %s đã ứng tuyển vào vị trí của bạn.', (string)($user['name'] ?? ''))]);
        }
        // Thông báo cho candidate
        $pdo->prepare('INSERT INTO notifications (user_id, content) VALUES (:uid, :content)')
            ->execute([':uid' => $candidateId, ':content' => 'Bạn đã ứng tuyển thành công!']);

        flash('Ứng tuyển thành công. Vui lòng kiểm tra trang quản lý.', 'success');
        redirect('/candidate/index.php');
    }

    // Tải dữ liệu job
    $stmt = $pdo->prepare('
        SELECT j.*, c.id AS company_id_val, c.name AS company_name,
               c.description AS company_description,
               c.address AS company_address, c.logo AS company_logo,
               cb.province, cb.address_detail, cb.full_address,
               COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) AS location_label
        FROM jobs j
        JOIN companies c ON c.id = j.company_id
        LEFT JOIN company_branches cb ON cb.id = j.branch_id
        WHERE j.id = :jid AND j.status = "approved"
    ');
    $stmt->execute([':jid' => $jobId]);
    $job = $stmt->fetch();

    if (!$job) {
        render_header('Không tìm thấy việc làm');
        echo '<div class="app-card p-4"><h4>Không tìm thấy việc làm</h4>'
           . '<p class="muted">Tin tuyển dụng có thể chưa được duyệt hoặc không tồn tại.</p>'
           . '<a class="btn btn-success" href="' . e(BASE_URL) . '/?page=jobs">Quay lại</a></div>';
        render_footer();
        exit;
    }

    $catStmt = $pdo->prepare('SELECT cat.name FROM job_categories jc JOIN categories cat ON cat.id=jc.category_id WHERE jc.job_id=:jid');
    $catStmt->execute([':jid' => $jobId]);
    $categories = $catStmt->fetchAll();

    $imgStmt = $pdo->prepare('SELECT image_path FROM job_images WHERE job_id=:jid');
    $imgStmt->execute([':jid' => $jobId]);
    $images = $imgStmt->fetchAll();

    $candidate = current_user();
    $applied   = false;
    $cvList    = [];
    if ($candidate && ($candidate['role'] ?? '') === 'candidate') {
        $appliedStmt = $pdo->prepare('SELECT id FROM applications WHERE job_id=:jid AND candidate_id=:cid');
        $appliedStmt->execute([':jid' => $jobId, ':cid' => (int)$candidate['id']]);
        $applied = (bool)$appliedStmt->fetch();

        $cvStmt = $pdo->prepare('SELECT id, file_path FROM cvs WHERE user_id=:uid');
        $cvStmt->execute([':uid' => (int)$candidate['id']]);
        $cvList = $cvStmt->fetchAll();
    }

    render_header($job['title'] . ' — ' . $job['company_name']);
    ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="app-card p-4">
                <!-- Header: logo và tên công ty là LINK đến trang công ty -->
                <div class="d-flex gap-3 align-items-start">
                    <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$job['company_id'] ?>"
                       title="Xem hồ sơ công ty">
                        <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['company_logo']) ?>"
                             alt="Logo <?= e((string)$job['company_name']) ?>"
                             style="width:60px;height:60px;object-fit:contain;"
                             class="soft-border rounded-14 bg-white p-2">
                    </a>
                    <div class="flex-grow-1">
                        <h3 class="mb-1"><?= e((string)$job['title']) ?></h3>
                        <div class="muted small">
                            <!-- Tên công ty cũng là link -->
                            <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$job['company_id'] ?>"
                               class="fw-bold text-success">
                                <i class="fa-solid fa-building me-1"></i><?= e((string)$job['company_name']) ?>
                            </a>
                            <span class="mx-2">|</span>
                            <i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($job)) ?>
                            <span class="mx-2">|</span>
                            <i class="fa-solid fa-money-bill-wave me-1"></i><?= e(job_salary_label($job)) ?>
                        </div>
                        <?php if (!empty($job['website']) || !empty($job['facebook']) || !empty($job['linkedin'])): ?>
                            <div class="mt-2">
                                <?php if (!empty($job['website'])): ?>
                                    <a href="<?= e((string)$job['website']) ?>" class="me-2" target="_blank">Website</a>
                                <?php endif; ?>
                                <?php if (!empty($job['facebook'])): ?>
                                    <a href="<?= e((string)$job['facebook']) ?>" class="me-2" target="_blank">Facebook</a>
                                <?php endif; ?>
                                <?php if (!empty($job['linkedin'])): ?>
                                    <a href="<?= e((string)$job['linkedin']) ?>" class="me-2" target="_blank">LinkedIn</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <!-- lương cụ thể nếu có -->
                        <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                            <div class="mt-1">
                                <span class="badge bg-success">
                                    ₫
                                    <?php if (!empty($job['salary_min'])): ?>
                                        <?= number_format((float)$job['salary_min'] / 1000000, 1) ?>tr
                                    <?php endif; ?>
                                    <?php if (!empty($job['salary_min']) && !empty($job['salary_max'])): ?> — <?php endif; ?>
                                    <?php if (!empty($job['salary_max'])): ?>
                                        <?= number_format((float)$job['salary_max'] / 1000000, 1) ?>tr
                                    <?php endif; ?>
                                    /tháng
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-outline-success btn-sm favorite-btn"
                            data-job-id="<?= (int)$jobId ?>" title="Yêu thích">
                        <i class="fa-regular fa-heart"></i>
                    </button>
                </div>

                <!-- Danh mục -->
                <div class="mt-3">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($categories as $cat): ?>
                            <span class="badge text-bg-light soft-border rounded-14 px-3 py-2">
                                <i class="fa-solid fa-tag me-1"></i><?= e((string)$cat['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Hình ảnh -->
                <?php if (!empty($images)): ?>
                    <div class="mt-4 row g-3">
                        <?php foreach ($images as $img): ?>
                            <div class="col-md-6">
                                <img src="<?= e(BASE_URL) ?>/<?= e((string)$img['image_path']) ?>" class="job-img" alt="Ảnh">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (!empty($job['image'])): ?>
                    <div class="mt-4">
                        <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['image']) ?>" class="job-img" alt="Ảnh">
                    </div>
                <?php endif; ?>

                <h5 class="mt-4"><i class="fa-solid fa-circle-info me-2"></i>Mô tả công việc</h5>
                <p class="muted"><?= nl2br(e((string)$job['description'])) ?></p>

                <h5 class="mt-4"><i class="fa-solid fa-list-check me-2"></i>Yêu cầu</h5>
                <p class="muted"><?= nl2br(e((string)$job['requirement'])) ?></p>
            </div>

            <!-- Thông tin công ty ngắn bên dưới job -->
            <div class="app-card p-3 mt-3">
                <div class="d-flex gap-3 align-items-center">
                    <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$job['company_id'] ?>">
                        <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['company_logo']) ?>"
                             style="width:44px;height:44px;object-fit:contain;"
                             class="soft-border rounded-14 bg-white p-1" alt="Logo">
                    </a>
                    <div class="flex-grow-1">
                        <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$job['company_id'] ?>"
                           class="fw-bold text-success">
                            <?= e((string)$job['company_name']) ?>
                        </a>
                        <div class="muted small"><?= e((string)$job['company_address']) ?></div>
                    </div>
                    <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)$job['company_id'] ?>"
                       class="btn btn-outline-success btn-sm">
                        Xem trang công ty
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar ứng tuyển -->
        <div class="col-lg-4">
            <div class="app-card p-4">
                <h5 class="mb-3"><i class="fa-solid fa-paper-plane me-2"></i>Ứng tuyển</h5>

                <?php if (!$candidate): ?>
                    <div class="muted mb-3">Đăng nhập để ứng tuyển.</div>
                    <a href="<?= e(BASE_URL) ?>/login.php" class="btn btn-success w-100">
                        <i class="fa-solid fa-right-to-bracket me-1"></i>Đăng nhập
                    </a>
                <?php elseif (($candidate['role'] ?? '') !== 'candidate'): ?>
                    <div class="muted">Chỉ tài khoản ứng viên mới có thể ứng tuyển.</div>
                <?php elseif ($applied): ?>
                    <div class="alert alert-info">✓ Bạn đã ứng tuyển công việc này.</div>
                <?php elseif (count($cvList) === 0): ?>
                    <div class="alert alert-warning">Bạn cần có ít nhất 1 CV để ứng tuyển.</div>
                    <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv" class="btn btn-outline-success w-100 mt-2">
                        <i class="fa-solid fa-file-arrow-up me-1"></i>Tải lên CV
                    </a>
                <?php else: ?>
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="apply_job">
                        <div class="mb-3">
                            <label class="form-label">Chọn CV</label>
                            <select name="cv_id" class="form-select" required>
                                <?php foreach ($cvList as $cv): ?>
                                    <option value="<?= (int)$cv['id'] ?>">CV #<?= (int)$cv['id'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-success w-100">
                            <i class="fa-solid fa-paper-plane me-1"></i>Ứng tuyển ngay
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const jobId = <?= (int)$jobId ?>;
        const key = 'fav_jobs';
        let fav = [];
        try { fav = JSON.parse(localStorage.getItem(key) || '[]'); } catch(e) {}
        const btn = document.querySelector('.favorite-btn[data-job-id="' + jobId + '"]');
        if (!btn) return;
        btn.innerHTML = fav.includes(jobId) ? '<i class="fa-solid fa-heart"></i>' : '<i class="fa-regular fa-heart"></i>';
        btn.addEventListener('click', function() {
            if (fav.includes(jobId)) {
                fav = fav.filter(x => x !== jobId);
            } else {
                fav.push(jobId);
            }
            localStorage.setItem(key, JSON.stringify(fav));
            btn.innerHTML = fav.includes(jobId) ? '<i class="fa-solid fa-heart"></i>' : '<i class="fa-regular fa-heart"></i>';
        });
    })();
    </script>

    <?php
    render_footer();
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
//  DANH SÁCH VIỆC LÀM  (?page=jobs)
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
if ($page === 'jobs') {
    // ... bên trong if ($page === 'jobs')
$params = [];
$where = 'WHERE j.status = "approved"';

if ($keyword !== '') {
    $where .= ' AND (j.title LIKE :kw OR j.description LIKE :kw OR j.requirement LIKE :kw OR COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) LIKE :kw)';
    $params[':kw'] = '%' . $keyword . '%';
}
if ($location !== '') {
    $where .= ' AND COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) LIKE :loc';
    $params[':loc'] = '%' . $location . '%';
}
if ($categoryId > 0) {
    $where .= ' AND EXISTS (SELECT 1 FROM job_categories jc WHERE jc.job_id = j.id AND jc.category_id = :cat)';
    $params[':cat'] = $categoryId;
}

// Thay thế đoạn từ "COUNT(*)" đến hết phần xử lý phân trang
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id = j.company_id LEFT JOIN company_branches cb ON cb.id = j.branch_id ' . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$sql = 'SELECT j.*, c.id AS company_id_val, c.name AS company_name, c.logo AS company_logo, c.address AS company_address,
               cb.province, cb.address_detail, cb.full_address,
               COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) AS location_label
        FROM jobs j JOIN companies c ON c.id = j.company_id
        LEFT JOIN company_branches cb ON cb.id = j.branch_id
        ' . $where . ' ORDER BY j.id DESC LIMIT :lim OFFSET :off';
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll();

    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

    render_header('Việc làm');
    ?>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="app-card p-4">
                <h5 class="mb-3"><i class="fa-solid fa-filter me-2"></i>tìm kiếm</h5>
                <form method="GET">
                    <input type="hidden" name="page" value="jobs">
                    <div class="mb-2">
                        <label class="form-label">từ khóa</label>
                        <input class="form-control" name="keyword" value="<?= e($keyword) ?>" placeholder="PHP, UI, HR...">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Địa điểm</label>
                        <input class="form-control" name="location" value="<?= e($location) ?>" placeholder="Hà Nội, HCM...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Danh mục</label>
                        <select class="form-select" name="category">
                            <option value="0">Tất cả</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"
                                    <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= e((string)$cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-success w-100">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Áp dụng
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-0">Danh sách việc làm</h4>
                    <div class="muted small"><?= (int)$total ?> kết quả</div>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($jobs as $job): ?>
                    <div class="col-md-6">
                        <div class="app-card p-3">
                            <div class="d-flex gap-3">
                                <!-- Logo là link tới trang công ty -->
                                <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)($job['company_id_val'] ?? $job['company_id'] ?? 0) ?>"
                                   title="Xem hồ sơ công ty">
                                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['company_logo']) ?>"
                                         style="width:52px;height:52px;object-fit:contain;"
                                         class="soft-border rounded-14 bg-white p-2" alt="Logo">
                                </a>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= e((string)$job['title']) ?></div>
                                    <!-- Tên công ty là link -->
                                    <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)($job['company_id_val'] ?? $job['company_id'] ?? 0) ?>"
                                       class="muted small" style="text-decoration:none">
                                        <i class="fa-solid fa-building me-1"></i><?= e((string)$job['company_name']) ?>
                                    </a>
                                </div>
                                <button class="btn btn-outline-success btn-sm favorite-btn"
                                        data-job-id="<?= (int)$job['id'] ?>" title="Yêu thích">
                                    <i class="fa-regular fa-heart"></i>
                                </button>
                            </div>

                            <?php if (!empty($job['image'])): ?>
                                <div class="mt-3">
                                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['image']) ?>"
                                         class="job-img" alt="Ảnh" style="height:130px;">
                                </div>
                            <?php endif; ?>

                            <div class="mt-3 d-flex justify-content-between align-items-center">
                                <div class="muted small">
                                    <i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($job)) ?>
                                    <span class="mx-2">|</span>
                                    <i class="fa-solid fa-money-bill-wave me-1"></i><?= e(job_salary_label($job)) ?>
                                </div>
                                <a class="btn btn-success btn-sm"
                                   href="<?= e(BASE_URL) ?>/?page=job&id=<?= (int)$job['id'] ?>">Xem</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($jobs)): ?>
                <div class="app-card p-4 mt-3 text-center muted">Không tìm thấy việc làm phù hợp.</div>
            <?php endif; ?>

            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $baseParams  = ['page' => 'jobs', 'keyword' => $keyword, 'location' => $location, 'category' => $categoryId];
                    $buildLink   = static fn(int $p) => rtrim(BASE_URL, '/') . '/?' . http_build_query($baseParams + ['p' => $p]);
                    $prev = max(1, $pageNum - 1);
                    $next = min($totalPages, $pageNum + 1);
                    ?>
                    <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $pageNum <= 1 ? '#' : e($buildLink($prev)) ?>">Trước</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === 1 || $i === $totalPages || abs($i - $pageNum) <= 2): ?>
                            <li class="page-item <?= $i === $pageNum ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e($buildLink($i)) ?>"><?= $i ?></a>
                            </li>
                        <?php elseif ($i === 2 || $i === $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $pageNum >= $totalPages ? '#' : e($buildLink($next)) ?>">Sau</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <script>
    (function() {
        const key = 'fav_jobs';
        let fav = [];
        try { fav = JSON.parse(localStorage.getItem(key) || '[]'); } catch(e) {}
        document.querySelectorAll('.favorite-btn').forEach(function(btn) {
            const jobId = parseInt(btn.getAttribute('data-job-id'), 10);
            btn.innerHTML = fav.includes(jobId) ? '<i class="fa-solid fa-heart"></i>' : '<i class="fa-regular fa-heart"></i>';
            btn.addEventListener('click', function() {
                if (fav.includes(jobId)) {
                    fav = fav.filter(x => x !== jobId);
                } else {
                    fav.push(jobId);
                }
                localStorage.setItem(key, JSON.stringify(fav));
                btn.innerHTML = fav.includes(jobId) ? '<i class="fa-solid fa-heart"></i>' : '<i class="fa-regular fa-heart"></i>';
            });
        });
    })();
    </script>

    <?php
    render_footer();
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
//  TRANG CHỦ
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$jobs       = $pdo->query('
    SELECT j.*, c.id AS company_id_val, c.name AS company_name, c.logo AS company_logo, c.address AS company_address,
           cb.province, cb.address_detail, cb.full_address,
           COALESCE(NULLIF(cb.full_address, ""), NULLIF(cb.address_detail, ""), NULLIF(cb.province, ""), NULLIF(c.address, "")) AS location_label
    FROM jobs j JOIN companies c ON c.id = j.company_id
    LEFT JOIN company_branches cb ON cb.id = j.branch_id
    WHERE j.status = "approved"
    ORDER BY j.id DESC LIMIT 6
')->fetchAll();

render_header(SITE_NAME);
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="app-card p-4">
            <div class="d-flex align-items-center gap-3">
                <div style="width:54px;height:54px;background:rgba(16,185,129,.16);display:flex;align-items:center;justify-content:center;border-radius:16px;">
                    <i class="fa-solid fa-rocket" style="color:#059669;"></i>
                </div>
                <div>
                    <h3 class="mb-1">Tìm việc nhanh hơn mỗi ngày</h3>
                    <div class="muted">Nền tảng tuyển dụng đơn giản bằng PHP + MySQL.</div>
                </div>
            </div>
            <div class="mt-4">
                <form class="row g-2" method="GET" action="<?= e(BASE_URL) ?>/">
                    <input type="hidden" name="page" value="jobs">
                    <div class="col-md-6">
                        <input class="form-control" name="keyword" placeholder="Tìm theo vị trí/kỹ năng...">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="category">
                            <option value="0">Tất cả danh mục</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e((string)$cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success w-100"><i class="fa-solid fa-magnifying-glass"></i> Tìm</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="app-card p-4 mt-4">
            <h4 class="mb-3"><i class="fa-solid fa-list me-2"></i>Việc làm nổi bật</h4>
            <div class="row g-3">
                <?php foreach ($jobs as $job): ?>
                    <div class="col-md-6">
                        <div class="app-card p-3 soft-border h-100">
                            <div class="d-flex gap-3 align-items-start">
                                <!-- Logo = link tới trang công ty -->
                                <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)($job['company_id_val'] ?? 0) ?>">
                                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['company_logo']) ?>"
                                         style="width:44px;height:44px;object-fit:contain;"
                                         class="soft-border rounded-14 bg-white p-1" alt="Logo">
                                </a>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= e((string)$job['title']) ?></div>
                                    <!-- Tên công ty = link -->
                                    <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)($job['company_id_val'] ?? 0) ?>"
                                       class="muted small" style="text-decoration:none">
                                        <i class="fa-solid fa-building me-1"></i><?= e((string)$job['company_name']) ?>
                                    </a>
                                </div>
                                <a class="btn btn-outline-success btn-sm"
                                   href="<?= e(BASE_URL) ?>/?page=job&id=<?= (int)$job['id'] ?>">Xem</a>
                            </div>
                            <div class="mt-2 muted small">
                                <i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($job)) ?>
                                <span class="mx-2">|</span>
                                <i class="fa-solid fa-money-bill-wave me-1"></i><?= e(job_salary_label($job)) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-success mt-3">
                <i class="fa-solid fa-bolt me-1"></i>Xem tất cả việc làm
            </a>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="app-card p-4">
            <h4 class="mb-3"><i class="fa-solid fa-tags me-2"></i>Danh mục</h4>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($categories as $cat): ?>
                    <a class="btn btn-outline-success btn-sm rounded-14"
                       href="<?= e(BASE_URL) ?>/?page=jobs&category=<?= (int)$cat['id'] ?>">
                        <?= e((string)$cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="app-card p-4 mt-4">
            <h4 class="mb-3"><i class="fa-solid fa-user-tie me-2"></i>Truy cập nhanh</h4>
            <?php $user = current_user(); ?>
            <?php if (!$user): ?>
                <a href="<?= e(BASE_URL) ?>/login.php"    class="btn btn-success w-100 mb-2"><i class="fa-solid fa-right-to-bracket me-1"></i>Đăng nhập</a>
                <a href="<?= e(BASE_URL) ?>/register.php" class="btn btn-outline-success w-100 mb-2"><i class="fa-solid fa-user-plus me-1"></i>Đăng ký</a>
            <?php elseif (($user['role'] ?? '') === 'candidate'): ?>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php" class="btn btn-success w-100 mb-2"><i class="fa-solid fa-user me-1"></i>Trang ứng viên</a>
            <?php elseif (($user['role'] ?? '') === 'employer'): ?>
                <a href="<?= e(BASE_URL) ?>/employer/index.php" class="btn btn-success w-100 mb-2"><i class="fa-solid fa-building me-1"></i>Trang nhà tuyển dụng</a>
            <?php elseif (($user['role'] ?? '') === 'admin'): ?>
                <a href="<?= e(BASE_URL) ?>/admin/index.php" class="btn btn-success w-100 mb-2"><i class="fa-solid fa-shield-halved me-1"></i>Trang quản trị</a>
            <?php endif; ?>
            <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-outline-success w-100">
                <i class="fa-solid fa-magnifying-glass me-1"></i>Duyệt việc làm
            </a>
        </div>
    </div>
</div>

<?php render_footer(); ?>

