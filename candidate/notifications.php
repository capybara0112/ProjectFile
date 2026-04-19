<?php
// notifications.php
$notifStmt = $pdo->prepare('SELECT id, content FROM notifications WHERE user_id = :uid ORDER BY id DESC');
$notifStmt->execute([':uid' => $candidateId]);
$notifications = $notifStmt->fetchAll();

render_header('Thông báo');
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
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="list-group-item list-group-item-action">Việc đã ứng tuyển</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications" class="list-group-item list-group-item-action active">Thông báo</a>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="app-card p-4">
            <h4 class="mb-3"><i class="fa-solid fa-bell me-2"></i>Thông báo</h4>
            <div class="row g-3">
                <?php foreach ($notifications as $n): ?>
                <div class="col-md-6">
                    <div class="app-card p-3 soft-border bg-white h-100">
                        <div class="small muted">#<?= (int)$n['id'] ?></div>
                        <div class="mt-1"><?= e((string)$n['content']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($notifications)): ?>
                    <div class="muted">Chưa có thông báo.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php render_footer(); ?>