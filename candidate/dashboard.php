<?php
// dashboard.php - nội dung trang tổng quan cho ứng viên
// Các biến đã có: $pdo, $candidateId, $user, $profile

// Thống kê ứng tuyển
$appStats = ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
$appStmt = $pdo->prepare('SELECT status, COUNT(*) AS cnt FROM applications WHERE candidate_id = :cid GROUP BY status');
$appStmt->execute([':cid' => $candidateId]);
foreach ($appStmt->fetchAll() as $row) {
    $st = $row['status'];
    $appStats['total'] += (int)$row['cnt'];
    if (isset($appStats[$st])) $appStats[$st] = (int)$row['cnt'];
}
// Lịch phỏng vấn sắp tới
$upcomingInterviews = [];
$ivStmt = $pdo->prepare('
    SELECT i.interview_date, i.location AS iv_location, i.note,
           j.title AS job_title, c.name AS company_name
    FROM interviews i
    JOIN applications a ON a.id = i.application_id
    JOIN jobs j ON j.id = a.job_id
    JOIN companies c ON c.id = j.company_id
    WHERE a.candidate_id = :cid AND i.interview_date > NOW()
    ORDER BY i.interview_date ASC
    LIMIT 3
');
$ivStmt->execute([':cid' => $candidateId]);
$upcomingInterviews = $ivStmt->fetchAll();

// Gợi ý việc làm dựa trên danh mục đã ứng tuyển
$suggestedJobs = [];
$prevCatStmt = $pdo->prepare('
    SELECT DISTINCT jc.category_id
    FROM applications a
    JOIN job_categories jc ON jc.job_id = a.job_id
    WHERE a.candidate_id = :cid
    LIMIT 5
');
$prevCatStmt->execute([':cid' => $candidateId]);
$prevCatIds = array_column($prevCatStmt->fetchAll(), 'category_id');
if (!empty($prevCatIds)) {
    $inList = implode(',', array_map('intval', $prevCatIds));
    $sugStmt = $pdo->prepare("
        SELECT DISTINCT j.*, c.id AS company_id_val, c.name AS company_name,
               c.logo AS company_logo, c.address AS company_address,
               cb.province, cb.address_detail, cb.full_address,
               COALESCE(NULLIF(cb.full_address,''), NULLIF(cb.address_detail,''), NULLIF(cb.province,''), NULLIF(c.address,'')) AS location_label
        FROM jobs j
        JOIN companies c ON c.id = j.company_id
        LEFT JOIN company_branches cb ON cb.id = j.branch_id
        JOIN job_categories jc ON jc.job_id = j.id
        WHERE j.status = 'approved'
          AND jc.category_id IN ($inList)
          AND j.id NOT IN (SELECT job_id FROM applications WHERE candidate_id = :cid)
        ORDER BY j.id DESC
        LIMIT 6
    ");
    $sugStmt->execute([':cid' => $candidateId]);
    $suggestedJobs = $sugStmt->fetchAll();
}
// fallback nếu không có gợi ý
if (empty($suggestedJobs)) {
    $suggestedJobs = $pdo->query('
        SELECT j.*, c.id AS company_id_val, c.name AS company_name, c.logo AS company_logo, c.address AS company_address,
               cb.province, cb.address_detail, cb.full_address,
               COALESCE(NULLIF(cb.full_address,""), NULLIF(cb.address_detail,""), NULLIF(cb.province,""), NULLIF(c.address,"")) AS location_label
        FROM jobs j
        JOIN companies c ON c.id = j.company_id
        LEFT JOIN company_branches cb ON cb.id = j.branch_id
        WHERE j.status = "approved"
        ORDER BY j.id DESC LIMIT 6
    ')->fetchAll();
}

render_header('Trang ứng viên');
?>

<div class="row g-4">
    <div class="col-lg-3">
        <!-- Sidebar (giống như cũ) -->
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
                    <div class="fw-bold"><?= e((string)($profile['name'] ?? '')) ?></div>
                    <div class="muted small">Ứng viên</div>
                </div>
            </div>
            <div class="list-group list-group-flush mt-3">
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=dashboard" class="list-group-item list-group-item-action <?= $page === 'dashboard' ? 'active' : '' ?>">Tổng quan</a>
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
        <!-- Nội dung dashboard -->
        <div class="app-card p-4 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div style="width:48px;height:48px;background:rgba(16,185,129,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-hand-wave" style="color:#059669;font-size:1.3rem;"></i>
                </div>
                <div>
                    <h4 class="mb-0">Xin chào, <?= e((string)($profile['name'] ?? 'bạn')) ?>!</h4>
                    <div class="muted small">Đây là bảng theo dõi ứng tuyển của bạn.</div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="app-card p-3 soft-border text-center bg-white">
                        <div class="fs-3 fw-bold"><?= $appStats['total'] ?></div>
                        <div class="muted small">Đã ứng tuyển</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card p-3 soft-border text-center bg-white">
                        <div class="fs-3 fw-bold text-warning"><?= $appStats['pending'] ?></div>
                        <div class="muted small">Đang chờ</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card p-3 soft-border text-center bg-white">
                        <div class="fs-3 fw-bold text-success"><?= $appStats['accepted'] ?></div>
                        <div class="muted small">Phỏng vấn</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card p-3 soft-border text-center bg-white">
                        <div class="fs-3 fw-bold text-danger"><?= $appStats['rejected'] ?></div>
                        <div class="muted small">Bị từ chối</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="app-card p-4">
                    <h5 class="mb-3"><i class="fa-solid fa-wand-magic-sparkles me-2 text-success"></i>Gợi ý việc làm</h5>
                    <div class="row g-3">
                        <?php foreach ($suggestedJobs as $job): ?>
                        <div class="col-md-12">
                            <div class="app-card p-3 soft-border h-100">
                                <div class="d-flex gap-2 align-items-start">
                                    <a href="<?= e(BASE_URL) ?>/?page=company&id=<?= (int)($job['company_id_val'] ?? 0) ?>">
                                        <img src="<?= e(BASE_URL) ?>/<?= e((string)$job['company_logo']) ?>" style="width:42px;height:42px;object-fit:contain;" class="soft-border rounded-14 bg-white p-1" alt="Logo">
                                    </a>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold" style="font-size:.9rem"><?= e((string)$job['title']) ?></div>
                                        <div class="muted" style="font-size:.78rem"><i class="fa-solid fa-building me-1"></i><?= e((string)$job['company_name']) ?></div>
                                        <div class="muted" style="font-size:.78rem"><i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($job)) ?></div>
                                    </div>
                                </div>
                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <span class="badge bg-success" style="font-size:.75rem">💰 <?= e(job_salary_label($job)) ?></span>
                                    <a class="btn btn-outline-success btn-sm" href="<?= e(BASE_URL) ?>/?page=job&id=<?= (int)$job['id'] ?>">Xem</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-success mt-3 btn-sm">Xem tất cả việc làm</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="app-card p-4 mb-3">
                    <h5 class="mb-3"><i class="fa-solid fa-calendar-check me-2 text-success"></i>Lịch phỏng vấn sắp tới</h5>
                    <?php if (empty($upcomingInterviews)): ?>
                        <div class="muted small">Chưa có lịch phỏng vấn nào.</div>
                    <?php else: ?>
                        <?php foreach ($upcomingInterviews as $iv): ?>
                        <div class="app-card p-3 soft-border mb-2" style="border-left:3px solid #059669!important;">
                            <div class="fw-bold"><?= e((string)$iv['job_title']) ?></div>
                            <div class="muted small"><i class="fa-solid fa-building me-1"></i><?= e((string)$iv['company_name']) ?></div>
                            <div class="muted small mt-1"><i class="fa-solid fa-clock me-1 text-success"></i> <?= e(date('d/m/Y H:i', strtotime((string)$iv['interview_date']))) ?></div>
                            <?php if (!empty($iv['iv_location'])): ?>
                                <div class="muted small"><i class="fa-solid fa-location-dot me-1"></i><?= e((string)$iv['iv_location']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="app-card p-4">
                    <h5 class="mb-3"><i class="fa-solid fa-bolt me-2"></i>Truy cập nhanh</h5>
                    <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=profile" class="btn btn-success w-100 mb-2">Hồ sơ của tôi</a>
                    <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="btn btn-outline-success w-100 mb-2">Đơn ứng tuyển</a>
                    <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-outline-secondary w-100">Tìm việc làm</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>