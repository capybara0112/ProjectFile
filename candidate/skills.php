<?php
// skills.php
// Lấy danh sách kỹ năng có sẵn và kỹ năng đã chọn
$allSkills = $pdo->query('SELECT id, name FROM skills ORDER BY name ASC')->fetchAll();
$userSkillsStmt = $pdo->prepare('SELECT skill_id FROM user_skills WHERE user_id = :uid');
$userSkillsStmt->execute([':uid' => $candidateId]);
$selectedSkillIds = array_map(fn($r) => (int)$r['skill_id'], $userSkillsStmt->fetchAll());

// Lấy danh sách kỹ năng đã chọn (để hiển thị badge)
$selectedSkills = [];
if (!empty($selectedSkillIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedSkillIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM skills WHERE id IN ($placeholders)");
    $stmt->execute($selectedSkillIds);
    $selectedSkills = $stmt->fetchAll();
}

render_header('Kỹ năng của tôi');
?>
<div class="row g-4">
    <div class="col-lg-3">
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
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=skills" class="list-group-item list-group-item-action active">Kỹ năng</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=certificates" class="list-group-item list-group-item-action">Chứng chỉ</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=cv" class="list-group-item list-group-item-action">CV</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=applications" class="list-group-item list-group-item-action">Việc đã ứng tuyển</a>
                <a href="<?= e(BASE_URL) ?>/candidate/index.php?page=notifications" class="list-group-item list-group-item-action">Thông báo</a>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="app-card p-4">
            <h4 class="mb-3"><i class="fa-solid fa-wrench me-2"></i>Kỹ năng của bạn</h4>
            
            <!-- Hiển thị kỹ năng đã chọn dạng badge -->
            <div class="mb-4">
                <label class="form-label fw-bold">Kỹ năng đã có:</label>
                <div id="selectedSkillsContainer" class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($selectedSkills as $sk): ?>
                        <span class="badge bg-success rounded-pill px-3 py-2" style="font-size:0.9rem;">
                            <?= e($sk['name']) ?>
                            <button type="button" class="btn-close btn-close-white ms-2" style="font-size:0.6rem;" onclick="removeSkill(<?= $sk['id'] ?>)"></button>
                        </span>
                    <?php endforeach; ?>
                    <?php if (empty($selectedSkills)): ?>
                        <span class="muted small">Chưa có kỹ năng nào. Hãy chọn hoặc thêm mới bên dưới.</span>
                    <?php endif; ?>
                </div>
            </div>
            <hr>

            <!-- Form chọn kỹ năng có sẵn -->
            <h5 class="mb-3"><i class="fa-solid fa-list-check me-2"></i>Danh sách kỹ năng có sẵn</h5>
            <form method="POST" id="skillsForm">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_skills">
                <div class="row g-3">
                    <?php foreach ($allSkills as $sk): ?>
                        <?php $checked = in_array((int)$sk['id'], $selectedSkillIds, true); ?>
                        <div class="col-md-4">
                            <div class="soft-border rounded-14 p-3 bg-white">
                                <label class="d-flex gap-2 align-items-center">
                                    <input type="checkbox" name="skills[]" value="<?= (int)$sk['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                    <span class="fw-bold"><?= e((string)$sk['name']) ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-success mt-3" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Lưu kỹ năng đã chọn</button>
            </form>
            <hr class="my-4">

            <!-- Thêm kỹ năng mới -->
            <h5 class="mb-3"><i class="fa-solid fa-plus-circle me-2"></i>Thêm kỹ năng mới (không có trong danh sách)</h5>
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_new_skill">
                <div class="col-md-8">
                    <label class="form-label">Tên kỹ năng</label>
                    <input type="text" class="form-control" name="skill_name" placeholder="VD: Python, React, Lãnh đạo..." required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-success w-100" type="submit"><i class="fa-solid fa-plus me-2"></i>Thêm & gán</button>
                </div>
            </form>
            <div class="muted small mt-2">Kỹ năng mới sẽ được thêm vào danh sách chung và tự động gán cho bạn.</div>
        </div>
    </div>
</div>

<script>
function removeSkill(skillId) {
    if (confirm('Bạn có chắc muốn xóa kỹ năng này?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        var csrfInput = document.createElement('input');
        csrfInput.name = 'csrf';
        csrfInput.value = '<?= e(csrf_token()) ?>';
        var actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'remove_skill';
        var skillIdInput = document.createElement('input');
        skillIdInput.name = 'skill_id';
        skillIdInput.value = skillId;
        form.appendChild(csrfInput);
        form.appendChild(actionInput);
        form.appendChild(skillIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<?php render_footer(); ?>