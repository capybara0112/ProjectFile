<?php
// applications.php
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

render_header('Việc đã ứng tuyển');
?>
<div class="row g-4">
    <div class="col-lg-3">
        <!-- Sidebar -->
        <div class="app-card p-3">
            <div class="d-flex align-items-center gap-3 p-3 soft-border rounded-14 bg-white">
                <?php if (!empty($profile['avatar'])): ?>
                    <img src="<?= e(BASE_URL) ?>/<?= e((string)$profile['avatar']) ?>" alt="Avatar" style="width:56px;height:56px;object-fit:cover;" class="rounded-14 soft-border bg-white p-1">
                <?php else: ?>
                    <div class="soft-border rounded-14 bg-light d-flex align-items-center justify-content-center" style="width:56px;height:56px;"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
                <div><div class="fw-bold"><?= e((string)($profile['name'] ?? '')) ?></div><div class="muted small">Ứng viên</div></div>
            </div>
            <div class="list-group list-group-flush mt-3">
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=dashboard" class="list-group-item list-group-item-action">Tổng quan</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=profile" class="list-group-item list-group-item-action">Hồ sơ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=skills" class="list-group-item list-group-item-action">Kỹ năng</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=certificates" class="list-group-item list-group-item-action">Chứng chỉ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv" class="list-group-item list-group-item-action">CV</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="list-group-item list-group-item-action active">Việc đã ứng tuyển</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications" class="list-group-item list-group-item-action">Thông báo</a>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="app-card p-4">
            <h4 class="mb-3"><i class="fa-solid fa-clipboard-check me-2"></i>Việc đã ứng tuyển</h4>
            <div class="row g-3">
                <?php foreach ($applications as $app): ?>
                <div class="col-md-6">
                    <div class="app-card p-3 soft-border bg-white h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div><div class="fw-bold"><?= e((string)$app['title']) ?></div><div class="muted small"><i class="fa-solid fa-building"></i> <?= e((string)$app['company_name']) ?></div></div>
                            <span class="badge text-bg-light soft-border"><?= e((string)$app['application_status']) ?></span>
                        </div>
                        <div class="muted small mt-2"><i class="fa-solid fa-location-dot me-1"></i><?= e(job_location_label($app)) ?> <span class="mx-2">|</span> <i class="fa-solid fa-money-bill-wave me-1"></i><?= e(job_salary_label($app)) ?></div>
                        <div class="mt-3">
                            <?php if ($app['application_status'] === 'rejected'): ?>
                                <div class="alert alert-danger py-2 mb-0"><div class="fw-bold"><i class="fa-solid fa-ban me-1"></i>Đã từ chối</div><div class="small">Lý do: <?= e((string)($app['rejection_reason'] ?? 'Không có')) ?></div></div>
                            <?php elseif (!empty($app['interview_date'])): ?>
                                <div class="alert alert-success py-2 mb-0"><div class="fw-bold"><i class="fa-solid fa-calendar me-1"></i>Đã lên lịch phỏng vấn</div><div class="small">Ngày: <?= e((string)$app['interview_date']) ?></div><div class="small">Địa điểm: <?= e((string)($app['interview_location'] ?? '')) ?></div><?php if (!empty($app['interview_note'])): ?><div class="small muted mt-1"><?= e((string)$app['interview_note']) ?></div><?php endif; ?></div>
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
    </div>
</div>
<?php render_footer(); ?>