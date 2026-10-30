<?php
require_once 'auth.php';

if (!$canManageProducts) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Access denied.'];
    header("Location: index.php");
    exit();
}

// Only the admin can change an order's status; an order may contain several owners' products.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSuperAdmin) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['completed', 'cancelled'], true)) {
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE order_master SET status = ? WHERE order_id = ? AND status = 'placed'");
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;

        if ($changed && $newStatus === 'cancelled') {
            $stmt = $conn->prepare("
                UPDATE products p JOIN order_details od ON od.prod_id = p.id
                SET p.stock = p.stock + od.qty WHERE od.order_id = ?
            ");
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
        }
        $conn->commit();

        $_SESSION['flash'] = $changed
            ? ['type' => 'success', 'message' => "Order #$orderId marked as $newStatus."]
            : ['type' => 'warning', 'message' => 'Order was not updated.'];
    }
    header("Location: admin_orders.php");
    exit();
}

$ownerClause = $isSuperAdmin ? '' : 'AND p.owner_id = ' . $userId;

$orders = $conn->query("
    SELECT om.order_id, om.status, om.created_at, om.total, u.username, u.f_name, u.l_name,
           SUM(od.qty * od.price) AS visible_total
    FROM order_master om
    JOIN users u ON u.id = om.user_id
    JOIN order_details od ON od.order_id = om.order_id
    JOIN products p ON p.id = od.prod_id
    WHERE 1=1 $ownerClause
    GROUP BY om.order_id, om.status, om.created_at, om.total, u.username, u.f_name, u.l_name
    ORDER BY om.order_id DESC
")->fetch_all(MYSQLI_ASSOC);

$linesByOrder = [];
$lines = $conn->query("
    SELECT od.order_id, od.qty, od.price, p.name, o.username AS owner_name
    FROM order_details od
    JOIN products p ON p.id = od.prod_id
    LEFT JOIN users o ON o.id = p.owner_id
    WHERE 1=1 $ownerClause
    ORDER BY od.order_detail_id
")->fetch_all(MYSQLI_ASSOC);
foreach ($lines as $line) {
    $linesByOrder[(int)$line['order_id']][] = $line;
}

$pageTitle = $isSuperAdmin ? 'All Orders' : 'Orders';
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
                <h2><?= $isSuperAdmin ? 'Customer orders' : 'Orders for your products' ?></h2>
                <p><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?><?= $isSuperAdmin ? '' : ' · only your items are shown' ?></p>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="card"><div class="empty-state"><i class="bi bi-inbox"></i>No orders have been placed yet.</div></div>
        <?php else: ?>
            <div class="reveal">
            <?php foreach ($orders as $o): ?>
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            Order #<?= (int)$o['order_id'] ?>
                            <span class="fw-normal ms-2"><?= htmlspecialchars($o['f_name'] . ' ' . $o['l_name']) ?>
                                <span class="text-muted">(<?= htmlspecialchars($o['username']) ?>)</span></span>
                            <span class="text-muted fw-normal small ms-2"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($o['created_at']))) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-<?= $o['status'] === 'completed' ? 'success' : ($o['status'] === 'cancelled' ? 'danger' : 'primary') ?>">
                                <?= htmlspecialchars(ucfirst($o['status'])) ?>
                            </span>
                            <?php if ($isSuperAdmin && $o['status'] === 'placed'): ?>
                                <form method="POST" class="m-0 d-flex gap-1">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
                                    <button name="status" value="completed" class="btn btn-success btn-sm">Complete</button>
                                    <button name="status" value="cancelled" class="btn btn-outline-danger btn-sm"
                                            onclick="return confirm('Cancel this order?');">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead><tr><th>Product</th><?php if ($isSuperAdmin): ?><th>Owner</th><?php endif; ?><th>Price</th><th>Qty</th><th class="text-end">Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($linesByOrder[(int)$o['order_id']] ?? [] as $l): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($l['name']) ?></td>
                                    <?php if ($isSuperAdmin): ?><td class="text-muted"><?= htmlspecialchars($l['owner_name'] ?? '—') ?></td><?php endif; ?>
                                    <td>$<?= number_format((float)$l['price'], 2) ?></td>
                                    <td><?= (int)$l['qty'] ?></td>
                                    <td class="text-end">$<?= number_format($l['price'] * $l['qty'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="<?= $isSuperAdmin ? 4 : 3 ?>" class="text-end"><?= $isSuperAdmin ? 'Total' : 'Your items total' ?></td>
                                    <td class="text-end">$<?= number_format((float)($isSuperAdmin ? $o['total'] : $o['visible_total']), 2) ?></td>
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
