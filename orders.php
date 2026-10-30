<?php
require_once 'auth.php';

if (!$isCustomer) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("SELECT order_id, total, status, created_at FROM order_master WHERE user_id = ? ORDER BY order_id DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$linesByOrder = [];
if ($orders) {
    $stmt = $conn->prepare("
        SELECT od.order_id, od.qty, od.price, p.name, u.username AS owner_name
        FROM order_details od
        JOIN products p ON p.id = od.prod_id
        JOIN order_master om ON om.order_id = od.order_id
        LEFT JOIN users u ON u.id = p.owner_id
        WHERE om.user_id = ?
        ORDER BY od.order_detail_id
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $line) {
        $linesByOrder[(int)$line['order_id']][] = $line;
    }
}

$pageTitle = 'My Orders';
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

        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="bi bi-bag"></i>You haven't placed any orders yet.
                    <div class="mt-3"><a href="index.php" class="btn btn-primary">Browse products</a></div>
                </div>
            </div>
        <?php else: ?>
            <div class="reveal">
            <?php foreach ($orders as $o): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            Order #<?= (int)$o['order_id'] ?>
                            <span class="text-muted fw-normal small ms-2"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($o['created_at']))) ?></span>
                        </div>
                        <span class="badge text-bg-<?= $o['status'] === 'completed' ? 'success' : ($o['status'] === 'cancelled' ? 'danger' : 'primary') ?>">
                            <?= htmlspecialchars(ucfirst($o['status'])) ?>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead><tr><th>Product</th><th>Seller</th><th>Price</th><th>Qty</th><th class="text-end">Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($linesByOrder[(int)$o['order_id']] ?? [] as $l): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($l['name']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($l['owner_name'] ?? '—') ?></td>
                                    <td>$<?= number_format((float)$l['price'], 2) ?></td>
                                    <td><?= (int)$l['qty'] ?></td>
                                    <td class="text-end">$<?= number_format($l['price'] * $l['qty'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="4" class="text-end">Total</td>
                                    <td class="text-end">$<?= number_format((float)$o['total'], 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
