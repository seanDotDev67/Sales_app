<?php
require_once 'auth.php';

$products = $conn->query("
    SELECT p.id, p.owner_id, p.name, p.description, p.price, p.stock, p.image, u.username AS owner_name
    FROM products p
    LEFT JOIN users u ON u.id = p.owner_id
    ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Products';
$pageCrumb = $isCustomer ? 'Shop' : 'Storefront';
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
                <h2>Browse products</h2>
                <p><?= count($products) ?> item<?= count($products) === 1 ? '' : 's' ?> available</p>
            </div>
            <input type="search" id="filter" class="form-control" placeholder="Search products or sellers…" style="width: 260px;">
        </div>

        <?php if (empty($products)): ?>
            <div class="card"><div class="empty-state"><i class="bi bi-inbox"></i>No products have been listed yet.</div></div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4 reveal" id="grid">
                <?php foreach ($products as $p): ?>
                    <?php $stock = (int)$p['stock']; $mine = ((int)$p['owner_id'] === $userId); ?>
                    <div class="col product" data-search="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['owner_name'])) ?>">
                        <div class="card h-100 product-card">
                            <div class="thumb">
                                <img src="<?= htmlspecialchars($p['image'] ?: 'uploads/placeholder.svg') ?>"
                                     onerror="this.onerror=null; this.src='uploads/placeholder.svg';"
                                     alt="<?= htmlspecialchars($p['name']) ?>">
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                    <h5 class="h6 fw-semibold mb-0"><?= htmlspecialchars($p['name']) ?></h5>
                                    <span class="price">$<?= number_format((float)$p['price'], 2) ?></span>
                                </div>
                                <div class="seller mb-2"><i class="bi bi-shop me-1"></i><?= htmlspecialchars($p['owner_name'] ?? 'Unknown seller') ?></div>
                                <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars($p['description'] ?? '') ?></p>
                                <div class="mb-3">
                                    <?php if ($stock > 0): ?>
                                        <span class="badge text-bg-light border"><?= $stock ?> in stock</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">Out of stock</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isCustomer): ?>
                                    <form action="add_to_cart.php" method="POST" class="d-flex gap-2 mt-auto">
                                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                        <input type="number" name="quantity" value="1" min="1" max="<?= $stock ?>"
                                               class="form-control form-control-sm" style="width: 70px;" <?= $stock === 0 ? 'disabled' : '' ?>>
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1" <?= $stock === 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-cart-plus me-1"></i>Add to cart
                                        </button>
                                    </form>
                                <?php elseif ($isSuperAdmin || $mine): ?>
                                    <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline-primary btn-sm mt-auto">
                                        <i class="bi bi-pencil me-1"></i>Manage
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('filter').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#grid .product').forEach(function (el) {
        el.style.display = el.dataset.search.includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>
