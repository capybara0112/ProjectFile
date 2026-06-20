<?php
// =============================================================================
// FILE: employer/branches.php
// Quản lý chi nhánh của công ty (thêm, sửa, xóa)
// Đã được cập nhật để sử dụng province_id (khóa ngoại đến provinces)
// và vẫn giữ legacy_province để tương thích ngược
// =============================================================================

// Chỉ được gọi từ employer/index.php với page=branches
// Đã có sẵn $pdo, $companyId, $company, $user

// Lấy danh sách tỉnh/thành phố cho dropdown
$allProvinces = $pdo->query('SELECT id, name FROM provinces ORDER BY name ASC')->fetchAll();

// ─────────────────────────────────────────────────────────────────────────────
// Xử lý POST (thêm, sửa, xóa)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_branch') {
        $branch_name    = trim($_POST['branch_name'] ?? '');
        $province_id    = (int)($_POST['province_id'] ?? 0) ?: null;
        $address_detail = trim($_POST['address_detail'] ?? '');
        $full_address   = trim($_POST['full_address'] ?? '');
        $is_headquarter = isset($_POST['is_headquarter']) ? 1 : 0;

        // Lấy tên tỉnh để lưu vào legacy_province (cho tương thích ngược)
        $legacy_province = '';
        if ($province_id !== null) {
            foreach ($allProvinces as $prov) {
                if ((int)$prov['id'] === $province_id) {
                    $legacy_province = $prov['name'];
                    break;
                }
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO company_branches 
                (company_id, branch_name, province_id, legacy_province, address_detail, full_address, is_headquarter)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $companyId, $branch_name, $province_id,
            $legacy_province, $address_detail, $full_address, $is_headquarter
        ]);
        flash('Thêm chi nhánh thành công.', 'success');
        redirect('/employer/index.php?page=branches');
    }

    if ($action === 'edit_branch') {
        $id             = (int)$_POST['id'];
        $branch_name    = trim($_POST['branch_name'] ?? '');
        $province_id    = (int)($_POST['province_id'] ?? 0) ?: null;
        $address_detail = trim($_POST['address_detail'] ?? '');
        $full_address   = trim($_POST['full_address'] ?? '');
        $is_headquarter = isset($_POST['is_headquarter']) ? 1 : 0;

        // Lấy tên tỉnh để cập nhật legacy_province
        $legacy_province = '';
        if ($province_id !== null) {
            foreach ($allProvinces as $prov) {
                if ((int)$prov['id'] === $province_id) {
                    $legacy_province = $prov['name'];
                    break;
                }
            }
        }

        $stmt = $pdo->prepare('
            UPDATE company_branches 
            SET branch_name=?, province_id=?, legacy_province=?, 
                address_detail=?, full_address=?, is_headquarter=?
            WHERE id=? AND company_id=?
        ');
        $stmt->execute([
            $branch_name, $province_id, $legacy_province,
            $address_detail, $full_address, $is_headquarter,
            $id, $companyId
        ]);
        flash('Cập nhật chi nhánh thành công.', 'success');
        redirect('/employer/index.php?page=branches');
    }

    if ($action === 'delete_branch') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM company_branches WHERE id=? AND company_id=?')
            ->execute([$id, $companyId]);
        flash('Xóa chi nhánh thành công.', 'success');
        redirect('/employer/index.php?page=branches');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Lấy danh sách chi nhánh hiện có (JOIN provinces để lấy tên tỉnh)
// ─────────────────────────────────────────────────────────────────────────────
$branches = [];
$stmt = $pdo->prepare('
    SELECT cb.id, cb.branch_name, 
           cb.province_id,
           COALESCE(p.name, cb.legacy_province, \'\') AS province_display,
           cb.address_detail, cb.full_address, cb.is_headquarter
    FROM   company_branches cb
    LEFT JOIN provinces p ON p.id = cb.province_id
    WHERE  cb.company_id = ?
    ORDER BY cb.is_headquarter DESC, cb.id ASC
');
$stmt->execute([$companyId]);
$branches = $stmt->fetchAll();

// ─────────────────────────────────────────────────────────────────────────────
// Nếu có edit branch, lấy thông tin chi nhánh đó
// ─────────────────────────────────────────────────────────────────────────────
$editBranch = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('
        SELECT cb.*, COALESCE(p.name, cb.legacy_province, \'\') AS province_display
        FROM   company_branches cb
        LEFT JOIN provinces p ON p.id = cb.province_id
        WHERE  cb.id=? AND cb.company_id=?
    ');
    $stmt->execute([$id, $companyId]);
    $editBranch = $stmt->fetch();
}


?>

<div class="app-card p-4">
    <h4 class="mb-3">Quản lý chi nhánh</h4>

    <?php if ($editBranch): ?>
        <!-- Form sửa chi nhánh -->
        <div class="border rounded p-3 mb-4 bg-light">
            <h5>Sửa chi nhánh</h5>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="edit_branch">
                <input type="hidden" name="id" value="<?= (int)$editBranch['id'] ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên chi nhánh</label>
                        <input class="form-control" name="branch_name" value="<?= e($editBranch['branch_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tỉnh/Thành phố</label>
                        <select class="form-select" name="province_id">
                            <option value="">-- Chọn tỉnh/thành --</option>
                            <?php foreach ($allProvinces as $prov): ?>
                                <option value="<?= (int)$prov['id'] ?>"
                                    <?= ((int)($editBranch['province_id'] ?? 0)) === (int)$prov['id'] ? 'selected' : '' ?>>
                                    <?= e($prov['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

    <!-- Form thêm chi nhánh mới -->
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
                    <select class="form-select" name="province_id">
                        <option value="">-- Chọn tỉnh/thành --</option>
                        <?php foreach ($allProvinces as $prov): ?>
                            <option value="<?= (int)$prov['id'] ?>"><?= e($prov['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <td><?= e($br['province_display']) ?></td>
                    <td><?= e($br['full_address'] ?: $br['address_detail']) ?></td>
                    <td><?= $br['is_headquarter'] ? '✅' : '' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/employer/index.php?page=branches&edit=<?= (int)$br['id'] ?>" class="btn btn-sm btn-outline-success">Sửa</a>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Xóa chi nhánh này?')">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="delete_branch">
                            <input type="hidden" name="id" value="<?= (int)$br['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

