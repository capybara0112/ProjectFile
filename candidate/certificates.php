<?php
// certificates.php
$certStmt = $pdo->prepare('SELECT id, name, organization, issue_date, image FROM certificates WHERE user_id = :uid ORDER BY id DESC');
$certStmt->execute([':uid' => $candidateId]);
$certificates = $certStmt->fetchAll();

render_header('Chứng chỉ của tôi');
?>
<div class="row g-4">
    <div class="col-lg-3">
        <!-- Sidebar giống skills.php, có thể tái sử dụng nhưng tôi viết gọn -->
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
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=certificates" class="list-group-item list-group-item-action active">Chứng chỉ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv" class="list-group-item list-group-item-action">CV</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="list-group-item list-group-item-action">Việc đã ứng tuyển</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications" class="list-group-item list-group-item-action">Thông báo</a>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="app-card p-4">
            <h4 class="mb-3"><i class="fa-solid fa-award me-2"></i>Chứng chỉ</h4>
            <form method="POST" enctype="multipart/form-data" class="app-card p-3 soft-border bg-white mb-4">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_certificate">
                <div class="row g-3">
                    <div class="col-md-5"><label class="form-label">Tên chứng chỉ</label><input class="form-control" name="name" required></div>
                    <div class="col-md-4"><label class="form-label">Tổ chức cấp</label><input class="form-control" name="organization" required></div>
                    <div class="col-md-3"><label class="form-label">Ngày cấp</label><input class="form-control" type="date" name="issue_date" required></div>
                    <div class="col-md-12"><label class="form-label">Hình ảnh</label><input class="form-control" type="file" name="image" accept="image/*" required></div>
                    <div class="col-md-12"><button class="btn btn-success"><i class="fa-solid fa-plus me-2"></i>Thêm chứng chỉ</button></div>
                </div>
            </form>
            <div class="row g-3">
                <?php foreach ($certificates as $cert): ?>
                <div class="col-md-6">
                    <div class="app-card p-3 soft-border bg-white h-100">
                        <div class="fw-bold"><?= e((string)$cert['name']) ?></div>
                        <div class="muted small"><?= e((string)$cert['organization']) ?> • <?= e((string)$cert['issue_date']) ?></div>
                        <div class="mt-2"><img src="<?= e(BASE_URL) ?>/<?= e((string)$cert['image']) ?>" style="width:100%;max-height:220px;object-fit:cover;" class="rounded-14 soft-border bg-light" alt="Certificate"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($certificates)): ?>
                    <div class="muted">Chưa có chứng chỉ nào.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>