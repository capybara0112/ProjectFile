<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$pdo = db();

$companyId = (int)($_GET['company_id'] ?? 0);
if ($companyId <= 0) {
    flash('Công ty không tồn tại.', 'danger');
    redirect('/');
}

// --- Thông tin công ty ---
$coStmt = $pdo->prepare('
    SELECT co.id, co.name, co.description, co.address, co.logo, co.cover_image,
           co.company_size, co.website, co.facebook, co.linkedin, co.twitter,
           co.tagline, co.brand_color, co.created_at,
           u.created_at AS employer_joined
    FROM companies co
    LEFT JOIN employers em ON em.company_id = co.id
    LEFT JOIN users u ON u.id = em.user_id
    WHERE co.id = :id
    LIMIT 1
');
$coStmt->execute([':id' => $companyId]);
$company = $coStmt->fetch();

if (!$company) {
    flash('Công ty không tồn tại.', 'danger');
    redirect('/');
}

// --- Chi nhánh ---
$branchStmt = $pdo->prepare('
    SELECT cb.id, cb.branch_name, cb.address_detail, cb.full_address,
           cb.is_headquarter,
           COALESCE(p.name, cb.legacy_province, cb.full_address) AS province_display
    FROM company_branches cb
    LEFT JOIN provinces p ON p.id = cb.province_id
    WHERE cb.company_id = :cid
    ORDER BY cb.is_headquarter DESC, cb.id ASC
');
$branchStmt->execute([':cid' => $companyId]);
$branches = $branchStmt->fetchAll();

// --- Tin tuyển dụng đang tuyển ---
$jobsStmt = $pdo->prepare('
    SELECT j.id, j.title, j.salary_min, j.salary_max, j.salary_type, j.created_at, j.image,
           COALESCE(p.name, cb.legacy_province, cb.full_address) AS location,
           GROUP_CONCAT(DISTINCT cat.name ORDER BY cat.name SEPARATOR ", ") AS categories
    FROM jobs j
    LEFT JOIN company_branches cb ON cb.id = j.branch_id
    LEFT JOIN provinces p ON p.id = cb.province_id
    LEFT JOIN job_categories jc ON jc.job_id = j.id
    LEFT JOIN categories cat ON cat.id = jc.category_id
    WHERE j.company_id = :cid AND j.status = "approved"
    GROUP BY j.id, j.title, j.salary_min, j.salary_max, j.salary_type, j.created_at, j.image, location
    ORDER BY j.created_at DESC
    LIMIT 20
');
$jobsStmt->execute([':cid' => $companyId]);
$jobs = $jobsStmt->fetchAll();

// --- Thống kê ---
$statsStmt = $pdo->prepare('
    SELECT
        (SELECT COUNT(*) FROM jobs WHERE company_id = :cid) AS total_jobs,
        (SELECT COUNT(*) FROM jobs WHERE company_id = :cid2 AND status = "approved") AS active_jobs,
        (SELECT COUNT(DISTINCT a.candidate_id) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.company_id = :cid3) AS total_candidates
');
$statsStmt->execute([':cid' => $companyId, ':cid2' => $companyId, ':cid3' => $companyId]);
$stats = $statsStmt->fetch();

// --- Core values ---
$valuesStmt = $pdo->prepare('
    SELECT title, description FROM company_core_values WHERE company_id = :cid ORDER BY id ASC LIMIT 6
');
$valuesStmt->execute([':cid' => $companyId]);
$coreValues = $valuesStmt->fetchAll();

// --- Gallery ---
$galleryStmt = $pdo->prepare('
    SELECT image_path FROM company_gallery WHERE company_id = :cid ORDER BY id ASC LIMIT 8
');
$galleryStmt->execute([':cid' => $companyId]);
$gallery = $galleryStmt->fetchAll();

$brandColor = $company['brand_color'] ?: '#0d6efd';

render_header('Giới thiệu: ' . $company['name'], [
    'extra_head' => '
<style>
.co-banner{position:relative;height:280px;background:linear-gradient(135deg,' . e($brandColor) . ' 0%,' . e($brandColor) . 'cc 100%);overflow:hidden}
.co-banner img{width:100%;height:100%;object-fit:cover;opacity:.35}
.co-banner-overlay{position:absolute;inset:0;display:flex;align-items:flex-end;padding:28px 32px}
.co-logo-wrap{width:90px;height:90px;border-radius:12px;background:#fff;overflow:hidden;flex-shrink:0;box-shadow:0 2px 12px rgba(0,0,0,.18)}
.co-logo-wrap img{width:100%;height:100%;object-fit:contain;padding:6px}
.co-stat-card{text-align:center;padding:20px;border-radius:12px;background:#f8f9fa;border:1px solid #e9ecef}
.co-stat-card .stat-num{font-size:1.8rem;font-weight:700;color:' . e($brandColor) . '}
.job-card-co{border:1px solid #e9ecef;border-radius:12px;padding:16px;transition:.2s;display:block;color:inherit;text-decoration:none}
.job-card-co:hover{border-color:' . e($brandColor) . ';box-shadow:0 2px 12px rgba(0,0,0,.08);transform:translateY(-2px)}
.branch-item{border-left:3px solid ' . e($brandColor) . ';padding:12px 16px;background:#fafafa;border-radius:0 8px 8px 0;margin-bottom:10px}
.value-card{border-radius:12px;padding:20px;background:#fff;border:1px solid #e9ecef;height:100%}
</style>'
]);
?>

<!-- BANNER -->
<div class="co-banner mb-0" style="margin-left:-12px;margin-right:-12px">
    <?php if ($company['cover_image']): ?>
    <img src="<?= e(BASE_URL . '/' . $company['cover_image']) ?>" alt="">
    <?php else: ?>
    <div style="background:<?= e($brandColor) ?>;width:100%;height:100%"></div>
    <?php endif; ?>
    <div class="co-banner-overlay">
        <div class="co-logo-wrap me-3">
            <?php if ($company['logo']): ?>
            <img src="<?= e(BASE_URL . '/' . $company['logo']) ?>" alt="<?= e($company['name']) ?>">
            <?php else: ?>
            <div class="d-flex align-items-center justify-content-center h-100 text-secondary">
                <i class="fa-solid fa-building fa-2x"></i>
            </div>
            <?php endif; ?>
        </div>
        <div class="text-white">
            <h2 class="fw-bold mb-1" style="text-shadow:0 1px 4px rgba(0,0,0,.4)"><?= e($company['name']) ?></h2>
            <?php if ($company['tagline']): ?>
            <div class="opacity-90"><?= e($company['tagline']) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4 mt-3">

    <!-- Cột trái: Thông tin & chi nhánh -->
    <div class="col-lg-4">

        <!-- Thông tin chung -->
        <div class="app-card mb-4">
            <div class="fw-semibold border-bottom pb-2 mb-3">
                <i class="fa-solid fa-circle-info me-2 text-primary"></i>Thông tin công ty
            </div>
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                <?php if ($company['company_size']): ?>
                <tr>
                    <td class="text-muted" style="width:100px"><i class="fa-solid fa-users me-1"></i>Quy mô</td>
                    <td><?= number_format((int)$company['company_size']) ?> nhân viên</td>
                </tr>
                <?php endif; ?>
                <?php if ($company['address']): ?>
                <tr>
                    <td class="text-muted"><i class="fa-solid fa-location-dot me-1"></i>Địa chỉ</td>
                    <td><?= e($company['address']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($company['website']): ?>
                <tr>
                    <td class="text-muted"><i class="fa-solid fa-globe me-1"></i>Website</td>
                    <td><a href="<?= e($company['website']) ?>" target="_blank" class="text-primary"><?= e($company['website']) ?></a></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="text-muted"><i class="fa-solid fa-calendar me-1"></i>Tham gia</td>
                    <td><?= date('d/m/Y', strtotime($company['created_at'])) ?></td>
                </tr>
                </tbody>
            </table>

            <!-- Mạng xã hội -->
            <?php if ($company['facebook'] || $company['linkedin'] || $company['twitter']): ?>
            <div class="d-flex gap-2 mt-3">
                <?php if ($company['facebook']): ?>
                <a href="<?= e($company['facebook']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fa-brands fa-facebook"></i>
                </a>
                <?php endif; ?>
                <?php if ($company['linkedin']): ?>
                <a href="<?= e($company['linkedin']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fa-brands fa-linkedin"></i>
                </a>
                <?php endif; ?>
                <?php if ($company['twitter']): ?>
                <a href="<?= e($company['twitter']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                    <i class="fa-brands fa-twitter"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Chi nhánh -->
        <?php if (!empty($branches)): ?>
        <div class="app-card mb-4">
            <div class="fw-semibold border-bottom pb-2 mb-3">
                <i class="fa-solid fa-map-location-dot me-2 text-danger"></i>Chi nhánh (<?= count($branches) ?>)
            </div>
            <?php foreach ($branches as $br): ?>
            <div class="branch-item">
                <div class="fw-semibold small">
                    <?= e($br['branch_name'] ?: $br['province_display']) ?>
                    <?php if ($br['is_headquarter']): ?>
                    <span class="badge bg-warning text-dark ms-1" style="font-size:.68rem">Trụ sở</span>
                    <?php endif; ?>
                </div>
                <?php $addr = $br['full_address'] ?: $br['address_detail']; ?>
                <?php if ($addr): ?>
                <div class="text-muted small"><?= e($addr) ?></div>
                <?php endif; ?>
                <?php if ($br['province_display'] && $addr): ?>
                <div class="text-muted small"><i class="fa-solid fa-location-dot me-1"></i><?= e($br['province_display']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Thống kê -->
        <div class="app-card mb-4">
            <div class="fw-semibold border-bottom pb-2 mb-3">
                <i class="fa-solid fa-chart-bar me-2 text-info"></i>Thống kê
            </div>
            <div class="row g-2">
                <div class="col-6">
                    <div class="co-stat-card">
                        <div class="stat-num"><?= (int)$stats['active_jobs'] ?></div>
                        <div class="small text-muted">Tin đang tuyển</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="co-stat-card">
                        <div class="stat-num"><?= (int)$stats['total_jobs'] ?></div>
                        <div class="small text-muted">Tổng tin</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="co-stat-card">
                        <div class="stat-num"><?= (int)$stats['total_candidates'] ?></div>
                        <div class="small text-muted">Ứng viên đã nộp hồ sơ</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cột phải: Mô tả & jobs -->
    <div class="col-lg-8">

        <!-- Mô tả công ty -->
        <?php if ($company['description']): ?>
        <div class="app-card mb-4">
            <div class="fw-semibold border-bottom pb-2 mb-3">
                <i class="fa-solid fa-align-left me-2 text-success"></i>Giới thiệu về <?= e($company['name']) ?>
            </div>
            <div style="white-space:pre-line;line-height:1.8;color:#444"><?= e($company['description']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Giá trị cốt lõi -->
        <?php if (!empty($coreValues)): ?>
        <div class="app-card mb-4">
            <div class="fw-semibold border-bottom pb-2 mb-3">
                <i class="fa-solid fa-star me-2 text-warning"></i>Giá trị cốt lõi
            </div>
            <div class="row g-3">
                <?php foreach ($coreValues as $val): ?>
                <div class="col-md-6">
                    <div class="value-card">
                        <div class="fw-semibold mb-1"><?= e($val['title']) ?></div>
                        <div class="text-muted small"><?= e($val['description']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Gallery -->
        <?php if (!empty($gallery)): ?>
        <div class="app-card mb-4">
            <div class="fw-semibold border-bottom pb-2 mb-3">
                <i class="fa-solid fa-images me-2 text-purple"></i>Hình ảnh công ty
            </div>
            <div class="row g-2">
                <?php foreach ($gallery as $img): ?>
                <div class="col-6 col-md-3">
                    <img src="<?= e(BASE_URL . '/' . $img['image_path']) ?>"
                         class="img-fluid rounded-8 w-100" style="height:100px;object-fit:cover"
                         alt="">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Việc làm đang tuyển -->
        <div class="app-card">
            <div class="fw-semibold border-bottom pb-2 mb-3">
                <i class="fa-solid fa-briefcase me-2" style="color:<?= e($brandColor) ?>"></i>
                Việc làm đang tuyển (<?= count($jobs) ?>)
            </div>
            <?php if (empty($jobs)): ?>
                <p class="text-muted">Hiện công ty chưa có tin tuyển dụng nào được duyệt.</p>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($jobs as $job): ?>
                <div class="col-md-6">
                    <a href="<?= e(BASE_URL) ?>/?page=jobs&id=<?= $job['id'] ?>" class="job-card-co">
                        <div class="fw-semibold mb-1"><?= e($job['title']) ?></div>
                        <?php if ($job['categories']): ?>
                        <div class="text-muted small mb-1"><?= e($job['categories']) ?></div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="text-success small fw-semibold">
                                <?= job_salary_label($job) ?>
                            </span>
                            <?php if ($job['location']): ?>
                            <span class="text-muted small">
                                <i class="fa-solid fa-location-dot me-1"></i><?= e($job['location']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.75rem">
                            <i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y', strtotime($job['created_at'])) ?>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php render_footer(); ?>