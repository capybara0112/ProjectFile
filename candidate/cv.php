<?php
// cv.php
$cvs = $pdo->prepare('SELECT id, file_path FROM cvs WHERE user_id = :uid ORDER BY id DESC');
$cvs->execute([':uid' => $candidateId]);
$cvList = $cvs->fetchAll();

render_header('CV của tôi');
?>
<div class="row g-4">
    <div class="col-lg-3">
        <!-- Sidebar (giống) -->
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
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv" class="list-group-item list-group-item-action active">CV</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="list-group-item list-group-item-action">Việc đã ứng tuyển</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications" class="list-group-item list-group-item-action">Thông báo</a>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="app-card p-4">
            <h4 class="mb-3"><i class="fa-solid fa-file-arrow-up me-2"></i>CV của bạn</h4>
            <form method="POST" enctype="multipart/form-data" class="app-card p-3 soft-border bg-white mb-4">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_cv">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8"><label class="form-label">Tải CV dạng PDF</label><input class="form-control" type="file" name="cv" accept="application/pdf" required><div class="muted small mt-1">Max size: <?= (int)(UPLOAD_MAX_BYTES / 1024 / 1024) ?>MB</div></div>
                    <div class="col-md-4"><button class="btn btn-success w-100"><i class="fa-solid fa-upload me-2"></i>Tải lên</button></div>
                </div>
            </form>
            <div class="row g-3">
                <?php foreach ($cvList as $cv): ?>
                <div class="col-md-6">
                    <div class="app-card p-3 soft-border bg-white h-100">
                        <div class="fw-bold">CV #<?= (int)$cv['id'] ?></div>
                        <div class="muted small mb-2"><?= e((string)$cv['file_path']) ?></div>
                        <a class="btn btn-outline-success btn-sm w-100" href="<?= e(BASE_URL) ?>/<?= e((string)$cv['file_path']) ?>" target="_blank"><i class="fa-solid fa-file-pdf me-2"></i>Mở PDF</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($cvList)): ?>
                    <div class="muted">Bạn chưa tải CV nào.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>