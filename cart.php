<?php
require_once 'auth.php';

if (!$isCustomer) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT cd.cart_detail_id, cd.prod_id, cd.cqty, p.name, p.price, p.stock, p.image, u.username AS owner_name
    FROM cart_master cm
    JOIN cart_details cd ON cd.cart_id = cm.cart_id
    JOIN products p ON p.id = cd.prod_id
    LEFT JOIN users u ON u.id = p.owner_id
    WHERE cm.user_id = ?
    ORDER BY cd.prod_id
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = 0;
$cartCount = 0;
foreach ($items as $it) {
    $total += $it['price'] * $it['cqty'];
    $cartCount += (int)$it['cqty'];
}

$pageTitle = 'My Cart';
$pageCrumb = 'Shop';
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

        <?php if (empty($items)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="bi bi-cart-x"></i>
                    Your cart is empty.
                    <div class="mt-3"><a href="index.php" class="btn btn-primary">Browse products</a></div>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll" checked title="Select all"></th>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th style="width: 170px;">Quantity</th>
                                        <th class="text-end">Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $it): ?>
                                    <?php $short = (int)$it['cqty'] > (int)$it['stock']; ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input item-check" form="checkoutForm" name="selected[]"
                                                   value="<?= (int)$it['prod_id'] ?>"
                                                   data-subtotal="<?= number_format($it['price'] * $it['cqty'], 2, '.', '') ?>"
                                                   data-qty="<?= (int)$it['cqty'] ?>" <?= $short ? 'disabled' : 'checked' ?>>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img class="thumb-sm" src="<?= htmlspecialchars($it['image'] ?: 'uploads/placeholder.svg') ?>"
                                                     onerror="this.onerror=null; this.src='uploads/placeholder.svg';" alt="">
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($it['name']) ?></div>
                                                    <div class="text-muted small">Sold by <?= htmlspecialchars($it['owner_name'] ?? 'Unknown') ?></div>
                                                    <?php if ($short): ?><div class="text-danger small">Only <?= (int)$it['stock'] ?> left in stock</div><?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>$<?= number_format((float)$it['price'], 2) ?></td>
                                        <td>
                                            <form action="update_cart.php" method="POST" class="d-flex gap-1">
                                                <input type="hidden" name="product_id" value="<?= (int)$it['prod_id'] ?>">
                                                <input type="hidden" name="action" value="update">
                                                <input type="number" name="quantity" value="<?= (int)$it['cqty'] ?>" min="1" max="<?= (int)$it['stock'] ?>"
                                                       class="form-control form-control-sm" style="width: 76px;">
                                                <button class="btn btn-outline-primary btn-sm" title="Update quantity"><i class="bi bi-arrow-repeat"></i></button>
                                            </form>
                                        </td>
                                        <td class="text-end fw-semibold">$<?= number_format($it['price'] * $it['cqty'], 2) ?></td>
                                        <td class="text-end">
                                            <form action="update_cart.php" method="POST">
                                                <input type="hidden" name="product_id" value="<?= (int)$it['prod_id'] ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <button class="btn btn-outline-danger btn-sm" title="Remove"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card position-sticky" style="top: 90px;">
                        <div class="card-header">Order summary</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2 text-muted">
                                <span>Selected items</span><span id="sumQty"><?= $cartCount ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
                                <span>Total</span><span id="sumTotal">$<?= number_format($total, 2) ?></span>
                            </div>
                            <form action="checkout.php" method="POST" id="checkoutForm"
                                  onsubmit="return confirm('Buy the selected products for ' + document.getElementById('sumTotal').textContent + '?');">
                                <button type="submit" id="buyBtn" class="btn btn-primary w-100 mb-2">
                                    <i class="bi bi-bag-check me-1"></i>Buy selected
                                </button>
                            </form>
                            <a href="index.php" class="btn btn-outline-secondary w-100">Continue shopping</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var checks = Array.prototype.slice.call(document.querySelectorAll('.item-check'));
    var all = document.getElementById('selectAll');
    if (!all) return;
    function recalc() {
        var qty = 0, total = 0, n = 0;
        checks.forEach(function (c) {
            if (c.checked) { qty += +c.dataset.qty; total += +c.dataset.subtotal; n++; }
        });
        document.getElementById('sumQty').textContent = qty;
        document.getElementById('sumTotal').textContent = '$' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('buyBtn').disabled = n === 0;
        all.checked = n > 0 && n === checks.filter(function (c) { return !c.disabled; }).length;
    }
    all.addEventListener('change', function () {
        checks.forEach(function (c) { if (!c.disabled) c.checked = all.checked; });
        recalc();
    });
    checks.forEach(function (c) { c.addEventListener('change', recalc); });
    recalc();
})();
</script>
</body>
</html>
