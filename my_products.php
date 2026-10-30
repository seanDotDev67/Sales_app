<?php
require_once 'auth.php';

if (!$canManageProducts) {
    header("Location: index.php");
    exit();
}

// Owners only see their own products; the admin sees everything.
$where  = $isSuperAdmin ? '' : 'WHERE p.owner_id = ?';
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.description, p.price, p.stock, p.image, p.owner_id,
           u.username AS owner_name,
           (SELECT COALESCE(SUM(od.qty), 0) FROM order_details od WHERE od.prod_id = p.id) AS sold
    FROM products p
    LEFT JOIN users u ON u.id = p.owner_id
    $where
    ORDER BY p.id DESC
");
if (!$isSuperAdmin) {
    $stmt->bind_param('i', $userId);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$owners = [];
if ($isSuperAdmin) {
    $owners = $conn->query("SELECT id, username, role_id FROM users WHERE role_id IN (1,3) AND status = 'approved' ORDER BY username")
                   ->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = $isSuperAdmin ? 'All Products' : 'My Products';
$pageCrumb = $isSuperAdmin ? 'Administration' : 'My Business';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include 'head.php'; ?></head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <?php include 'topbar.php'; ?>

    <div class="page-body">
        <?php include 'flash.php'; ?>

        <div class="section-head">
            <div>
                <h2><?= $isSuperAdmin ? 'Product catalog' : 'Your products' ?></h2>
                <p><?= count($products) ?> item<?= count($products) === 1 ? '' : 's' ?><?= $isSuperAdmin ? ' across all owners' : ' listed under your account' ?></p>
            </div>
            <div class="d-flex gap-2">
                <input type="search" id="filter" class="form-control" placeholder="Search products…" style="width: 220px;">
                <button class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus-lg me-1"></i>Add product
                </button>
            </div>
        </div>

        <div class="card">
            <?php if (!$products): ?>
                <div class="empty-state"><i class="bi bi-box-seam"></i>No products yet. Use <strong>Add product</strong> to create your first one.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="productTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <?php if ($isSuperAdmin): ?><th>Owner</th><?php endif; ?>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Sold</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img class="thumb-sm" src="<?= htmlspecialchars($p['image'] ?: 'uploads/placeholder.svg') ?>"
                                             onerror="this.onerror=null; this.src='uploads/placeholder.svg';" alt="">
                                        <div>
                                            <div class="fw-semibold pname"><?= htmlspecialchars($p['name']) ?></div>
                                            <div class="text-muted small text-truncate" style="max-width: 280px;"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <?php if ($isSuperAdmin): ?>
                                    <td><?= $p['owner_name'] ? htmlspecialchars($p['owner_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                                <?php endif; ?>
                                <td>$<?= number_format((float)$p['price'], 2) ?></td>
                                <td>
                                    <?php $s = (int)$p['stock']; ?>
                                    <span class="badge text-bg-<?= $s === 0 ? 'danger' : ($s <= 5 ? 'warning' : 'success') ?>"><?= $s ?></span>
                                </td>
                                <td><?= (int)$p['sold'] ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                                    <form action="delete_product.php" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="add_product.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Add product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product name</label>
                            <input type="text" class="form-control" name="name" required maxlength="100">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Stock</label>
                                <input type="number" class="form-control" name="stock" min="0" value="0" required>
                            </div>
                        </div>
                        <?php if ($isSuperAdmin): ?>
                            <div class="mb-3">
                                <label class="form-label">Owner</label>
                                <select name="owner_id" class="form-select">
                                    <?php foreach ($owners as $o): ?>
                                        <option value="<?= (int)$o['id'] ?>" <?= (int)$o['id'] === $userId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($o['username']) ?> (<?= (int)$o['role_id'] === 1 ? 'Admin' : 'Owner' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="500"></textarea>
                        </div>
                        <div>
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="image" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPG, PNG or WebP, up to 2 MB.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
if (new URLSearchParams(location.search).has('add')) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('addProductModal')).show();
}
document.getElementById('filter').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#productTable tbody tr').forEach(function (tr) {
        tr.style.display = tr.querySelector('.pname').textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>
