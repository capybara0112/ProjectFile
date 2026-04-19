<?php
// Chỉ được gọi từ employer/index.php với page=branches
// Đã có sẵn $pdo, $companyId, $company, $user

// Xử lý thêm, sửa, xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_branch') {
        $branch_name = trim($_POST['branch_name'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $address_detail = trim($_POST['address_detail'] ?? '');
        $full_address = trim($_POST['full_address'] ?? '');
        $is_headquarter = isset($_POST['is_headquarter']) ? 1 : 0;
        $stmt = $pdo->prepare('INSERT INTO company_branches (company_id, branch_name, province, address_detail, full_address, is_headquarter) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$companyId, $branch_name, $province, $address_detail, $full_address, $is_headquarter]);
        flash('Thêm chi nhánh thành công.', 'success');
        redirect('/employer/index.php?page=branches');
    }
    if ($action === 'edit_branch') {
        $id = (int)$_POST['id'];
        $branch_name = trim($_POST['branch_name'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $address_detail = trim($_POST['address_detail'] ?? '');
        $full_address = trim($_POST['full_address'] ?? '');
        $is_headquarter = isset($_POST['is_headquarter']) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE company_branches SET branch_name=?, province=?, address_detail=?, full_address=?, is_headquarter=? WHERE id=? AND company_id=?');
        $stmt->execute([$branch_name, $province, $address_detail, $full_address, $is_headquarter, $id, $companyId]);
        flash('Cập nhật chi nhánh thành công.', 'success');
        redirect('/employer/index.php?page=branches');
    }
    if ($action === 'delete_branch') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM company_branches WHERE id=? AND company_id=?')->execute([$id, $companyId]);
        flash('Xóa chi nhánh thành công.', 'success');
        redirect('/employer/index.php?page=branches');
    }
}

// Lấy danh sách chi nhánh hiện có
$branches = [];
$stmt = $pdo->prepare('SELECT * FROM company_branches WHERE company_id = ? ORDER BY is_headquarter DESC, id ASC');
$stmt->execute([$companyId]);
$branches = $stmt->fetchAll();

// Nếu có edit branch
$editBranch = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM company_branches WHERE id=? AND company_id=?');
    $stmt->execute([$id, $companyId]);
    $editBranch = $stmt->fetch();
}

render_header('Quản lý chi nhánh');
?>

<div class="app-card p-4">
    <h4 class="mb-3">Quản lý chi nhánh</h4>

    <?php if ($editBranch): ?>
        <div class="border rounded p-3 mb-4 bg-light">
            <h5>Sửa chi nhánh</h5>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="edit_branch">
                <input type="hidden" name="id" value="<?= $editBranch['id'] ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên chi nhánh</label>
                        <input class="form-control" name="branch_name" value="<?= e($editBranch['branch_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tỉnh/Thành phố</label>
                        <input class="form-control" name="province" value="<?= e($editBranch['province'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Địa chỉ chi tiết</label>
                        <input class="form-control" name="address_detail" value="<?= e($editBranch['address_detail'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Địa chỉ đầy đủ</label>
                        <input class="form-control" name="full_address" value="<?= e($editBranch['full_address'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_headquarter" id="isHQ" <?= $editBranch['is_headquarter'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isHQ">Là trụ sở chính</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <button class="btn btn-success">Cập nhật</button>
                        <a href="<?= BASE_URL ?>/employer/index.php?page=branches" class="btn btn-outline-secondary">Hủy</a>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Form thêm mới -->
    <div class="border rounded p-3 mb-4">
        <h5>Thêm chi nhánh mới</h5>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_branch">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tên chi nhánh</label>
                    <input class="form-control" name="branch_name" placeholder="VD: Chi nhánh Hà Nội">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tỉnh/Thành phố</label>
                    <input class="form-control" name="province" placeholder="Hà Nội">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Địa chỉ chi tiết</label>
                    <input class="form-control" name="address_detail" placeholder="Số 10, đường ...">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Địa chỉ đầy đủ</label>
                    <input class="form-control" name="full_address" placeholder="Số 10, đường ..., Hà Nội">
                </div>
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_headquarter" id="isHQNew">
                        <label class="form-check-label" for="isHQNew">Là trụ sở chính</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-success">Thêm chi nhánh</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Danh sách chi nhánh -->
    <h5>Danh sách chi nhánh</h5>
    <?php if (empty($branches)): ?>
        <p class="text-muted">Chưa có chi nhánh nào.</p>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr><th>Tên chi nhánh</th><th>Tỉnh/TP</th><th>Địa chỉ</th><th>Trụ sở chính</th><th>Hành động</th></tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $br): ?>
                <tr>
                    <td><?= e($br['branch_name']) ?></td>
                    <td><?= e($br['province']) ?></td>
                    <td><?= e($br['full_address'] ?: $br['address_detail']) ?></td>
                    <td><?= $br['is_headquarter'] ? '✅' : '' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/employer/index.php?page=branches&edit=<?= $br['id'] ?>" class="btn btn-sm btn-outline-success">Sửa</a>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Xóa chi nhánh này?')">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="delete_branch">
                            <input type="hidden" name="id" value="<?= $br['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php render_footer(); ?>