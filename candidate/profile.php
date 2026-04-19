<?php
// profile.php
// Các biến đã có: $pdo, $candidateId, $profile

render_header('Hồ sơ ứng viên');
?>
<div class="row g-4">
    <div class="col-lg-3">
        <!-- Sidebar giống dashboard, có thể tách riêng nhưng tạm thời copy -->
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
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=profile" class="list-group-item list-group-item-action active">Hồ sơ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=skills" class="list-group-item list-group-item-action">Kỹ năng</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=certificates" class="list-group-item list-group-item-action">Chứng chỉ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv" class="list-group-item list-group-item-action">CV</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="list-group-item list-group-item-action">Việc đã ứng tuyển</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications" class="list-group-item list-group-item-action">Thông báo</a>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="app-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fa-solid fa-id-card me-2"></i>Hồ sơ ứng viên</h4>
                <a href="<?= e(BASE_URL) ?>/?page=jobs" class="btn btn-success btn-sm"><i class="fa-solid fa-bolt me-1"></i>Duyệt việc làm</a>
            </div>
            <form method="POST" enctype="multipart/form-data" class="mb-4">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_avatar">
                <div class="mb-3">
                    <label class="form-label">Ảnh đại diện</label>
                    <input class="form-control" type="file" name="avatar" accept="image/*">
                    <button class="btn btn-outline-success mt-2" type="submit"><i class="fa-solid fa-image me-2"></i>Cập nhật ảnh đại diện</button>
                </div>
            </form>
            <hr>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Họ tên</label><input class="form-control" name="name" value="<?= e((string)($profile['name'] ?? '')) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Số điện thoại</label><input class="form-control" name="phone" value="<?= e((string)($profile['phone'] ?? '')) ?>"></div>
                    <div class="col-md-12"><label class="form-label">Địa chỉ</label><input class="form-control" name="address" value="<?= e((string)($profile['address'] ?? '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Kinh nghiệm</label><input class="form-control" name="experience" value="<?= e((string)($profile['experience'] ?? '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Học vấn</label><input class="form-control" name="education" value="<?= e((string)($profile['education'] ?? '')) ?>"></div>
                    <div class="col-md-12"><button class="btn btn-success"><i class="fa-solid fa-pen me-2"></i>Lưu hồ sơ</button></div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>