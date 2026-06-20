<?php
// =============================================================================
// FILE: candidate/saved_jobs.php
// NEW FILE — implements the "Việc làm đã lưu" (saved jobs) page.
//
// Integration steps:
//   1. Add 'saved_jobs' to $allowedPages in candidate/index.php
//   2. Add the toggle_save_job POST handler to candidate/index.php (see below)
//   3. Add sidebar link in candidate/dashboard.php and all other candidate pages
//   4. Add "Save / Unsave" button to job cards in index.php (public)
// =============================================================================
//
// POST HANDLER to add to candidate/index.php  (inside the try { if POST … } block):
//
//   if ($action === 'toggle_save_job') {
//       $jobId = (int)($_POST['job_id'] ?? 0);
//       if ($jobId > 0) {
//           // Try to delete first (unsave); if nothing deleted, insert (save)
//           $del = $pdo->prepare(
//               'DELETE FROM saved_jobs WHERE user_id = :uid AND job_id = :jid'
//           );
//           $del->execute([':uid' => $candidateId, ':jid' => $jobId]);
//           if ($del->rowCount() === 0) {
//               // Was not saved → save it now (INSERT IGNORE handles race conditions)
//               $pdo->prepare(
//                   'INSERT IGNORE INTO saved_jobs (user_id, job_id) VALUES (:uid, :jid)'
//               )->execute([':uid' => $candidateId, ':jid' => $jobId]);
//               flash('Đã lưu việc làm.', 'success');
//           } else {
//               flash('Đã bỏ lưu việc làm.', 'info');
//           }
//       }
//       $redirect = $_POST['redirect'] ?? '/candidate/index.php?page=saved_jobs';
//       redirect($redirect);
//   }
//
// ─────────────────────────────────────────────────────────────────────────────

// Fetched by candidate/index.php before including this file:
//   $pdo, $candidateId, $user, $profile

$perPage = 10;
$pageNum = max(1, (int)($_GET['p'] ?? 1));

// Count total saved jobs for this user
$countStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM   saved_jobs sj
     JOIN   jobs j ON j.id = sj.job_id
     WHERE  sj.user_id = :uid
       AND  j.status = "approved"'
);
$countStmt->execute([':uid' => $candidateId]);
$total      = (int)$countStmt->fetchColumn();
$pagination = pagination_meta($total, $perPage, $pageNum);
$pageNum    = $pagination['page'];
$totalPages = $pagination['total_pages'];
$offset     = $pagination['offset'];

// Fetch saved jobs — explicit columns, no SELECT *, provinces JOIN avoids N+1
$savedStmt = $pdo->prepare(
    'SELECT j.id        AS job_id,
            j.title,
            j.image,
            j.salary_min,
            j.salary_max,
            j.salary_type,
            j.status    AS job_status,
            j.created_at,
            sj.created_at AS saved_at,
            c.id   AS company_id,
            c.name AS company_name,
            c.logo AS company_logo,
            COALESCE(p.name, cb.legacy_province, \'\') AS province_display,
            COALESCE(
                NULLIF(cb.full_address, \'\'),
                NULLIF(cb.address_detail, \'\'),
                NULLIF(p.name, \'\'),
                NULLIF(cb.legacy_province, \'\'),
                NULLIF(c.address, \'\')
            ) AS location_label
     FROM   saved_jobs sj
     JOIN   jobs j              ON j.id  = sj.job_id
     JOIN   companies c         ON c.id  = j.company_id
     LEFT JOIN company_branches cb ON cb.id = j.branch_id
     LEFT JOIN provinces p         ON p.id  = cb.province_id
     WHERE  sj.user_id = :uid
       AND  j.status = "approved"
     ORDER BY sj.created_at DESC
     LIMIT  :lim OFFSET :off'
);
$savedStmt->bindValue(':uid', $candidateId, PDO::PARAM_INT);
$savedStmt->bindValue(':lim', $perPage,     PDO::PARAM_INT);
$savedStmt->bindValue(':off', $offset,      PDO::PARAM_INT);
$savedStmt->execute();
$savedJobs = $savedStmt->fetchAll(PDO::FETCH_ASSOC);

render_header('Việc làm đã lưu');
?>

<div class="row g-4">
    <!-- ── Sidebar (same structure as other candidate pages) ──────────────── -->
    <div class="col-lg-3">
        <div class="app-card p-3">
            <?php if (!empty($profile['avatar'])): ?>
                <img src="<?= e(BASE_URL) ?>/<?= e($profile['avatar']) ?>"
                     class="rounded-circle mb-2" style="width:64px;height:64px;object-fit:cover;" alt="Avatar">
            <?php endif; ?>
            <div class="fw-bold mb-3"><?= e($profile['name'] ?? '') ?></div>
            <div class="list-group list-group-flush">
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=dashboard"
                   class="list-group-item list-group-item-action">Tổng quan</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=profile"
                   class="list-group-item list-group-item-action">Hồ sơ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=skills"
                   class="list-group-item list-group-item-action">Kỹ năng</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=certificates"
                   class="list-group-item list-group-item-action">Chứng chỉ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv"
                   class="list-group-item list-group-item-action">CV</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications"
                   class="list-group-item list-group-item-action">Ứng tuyển</a>
                <!-- NEW: saved jobs link -->
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=saved_jobs"
                   class="list-group-item list-group-item-action active">Việc làm đã lưu</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications"
                   class="list-group-item list-group-item-action">Thông báo</a>
            </div>
        </div>
    </div>

    <!-- ── Main content ──────────────────────────────────────────────────── -->
    <div class="col-lg-9">
        <div class="app-card p-4">
            <h4 class="mb-4">
                <i class="fa-solid fa-bookmark me-2 text-success"></i>
                Việc làm đã lưu
                <span class="badge bg-success ms-2"><?= (int)$total ?></span>
            </h4>

            <?php if (empty($savedJobs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa-regular fa-bookmark fa-3x mb-3"></i>
                    <p>Bạn chưa lưu việc làm nào.<br>
                        <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-success mt-2">
                            Tìm việc làm ngay
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($savedJobs as $sj): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 soft-border bg-white h-100">
                                <!-- Job image -->
                                <?php if (!empty($sj['image'])): ?>
                                    <img src="<?= e(BASE_URL) ?>/<?= e($sj['image']) ?>"
                                         class="rounded mb-2"
                                         style="width:100%;height:120px;object-fit:cover;" alt="">
                                <?php endif; ?>

                                <!-- Company logo + name -->
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?php if (!empty($sj['company_logo'])): ?>
                                        <img src="<?= e(BASE_URL) ?>/<?= e($sj['company_logo']) ?>"
                                             style="width:32px;height:32px;object-fit:contain;" alt="">
                                    <?php endif; ?>
                                    <small class="text-muted"><?= e($sj['company_name']) ?></small>
                                </div>

                                <!-- Title -->
                                <div class="fw-bold mb-1">
                                    <a href="<?= e(BASE_URL) ?>/?page=job&id=<?= (int)$sj['job_id'] ?>"
                                       class="text-dark text-decoration-none">
                                        <?= e($sj['title']) ?>
                                    </a>
                                </div>

                                <!-- Salary -->
                                <div class="small text-success mb-1">
                                    <i class="fa-solid fa-dollar-sign me-1"></i>
                                    <?= e(job_salary_label($sj)) ?>
                                </div>

                                <!-- Location -->
                                <div class="small text-muted mb-2">
                                    <i class="fa-solid fa-location-dot me-1"></i>
                                    <?= e(job_location_label($sj)) ?>
                                </div>

                                <!-- Saved at -->
                                <div class="small text-muted mb-3">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    Đã lưu: <?= e(date('d/m/Y', strtotime($sj['saved_at']))) ?>
                                </div>

                                <!-- Actions -->
                                <div class="d-flex gap-2">
                                    <a href="<?= e(BASE_URL) ?>/?page=job&id=<?= (int)$sj['job_id'] ?>"
                                       class="btn btn-success btn-sm flex-grow-1">
                                        Xem chi tiết
                                    </a>
                                    <!-- Unsave button -->
                                    <form method="POST"
                                          action="<?= e(BASE_URL) ?>/candidate/index.php?page=saved_jobs">
                                        <input type="hidden" name="csrf"     value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action"   value="toggle_save_job">
                                        <input type="hidden" name="job_id"   value="<?= (int)$sj['job_id'] ?>">
                                        <input type="hidden" name="redirect" value="/candidate/index.php?page=saved_jobs">
                                        <button class="btn btn-outline-danger btn-sm"
                                                title="Bỏ lưu"
                                                onclick="return confirm('Bỏ lưu việc làm này?')">
                                            <i class="fa-solid fa-bookmark-slash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
                                <li class="page-item <?= $pg === $pageNum ? 'active' : '' ?>">
                                    <a class="page-link"
                                       href="?page=saved_jobs&p=<?= $pg ?>">
                                        <?= $pg ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php render_footer(); ?>