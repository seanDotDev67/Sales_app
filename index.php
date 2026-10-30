<?php
require_once 'auth.php';

// Customers land on the storefront; admins and owners get the dashboard.
if ($isCustomer) {
    require 'shop.php';
    exit();
}

$ownerFilter = $isSuperAdmin ? '' : ' AND p.owner_id = ' . $userId;
$stats = [];

if ($isSuperAdmin) {
    $stats['users']    = (int)$conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
    $stats['pending']  = (int)$conn->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetch_row()[0];
    $stats['products'] = (int)$conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
    $stats['orders']   = (int)$conn->query("SELECT COUNT(*) FROM order_master")->fetch_row()[0];
    $stats['revenue']  = (float)$conn->query("SELECT COALESCE(SUM(total), 0) FROM order_master WHERE status <> 'cancelled'")->fetch_row()[0];

    $pending = $conn->query("
        SELECT id, f_name, l_name, username, email, role_id, created_at
        FROM users WHERE status = 'pending' ORDER BY id DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
} else {
    $stats['products'] = (int)$conn->query("SELECT COUNT(*) FROM products WHERE owner_id = $userId")->fetch_row()[0];
    $stats['low']      = (int)$conn->query("SELECT COUNT(*) FROM products WHERE owner_id = $userId AND stock <= 5")->fetch_row()[0];
    $stats['orders']   = (int)$conn->query("
        SELECT COUNT(DISTINCT od.order_id) FROM order_details od
        JOIN products p ON p.id = od.prod_id WHERE p.owner_id = $userId
    ")->fetch_row()[0];
    $stats['revenue']  = (float)$conn->query("
        SELECT COALESCE(SUM(od.qty * od.price), 0) FROM order_details od
        JOIN products p ON p.id = od.prod_id
        JOIN order_master om ON om.order_id = od.order_id
        WHERE p.owner_id = $userId AND om.status <> 'cancelled'
    ")->fetch_row()[0];
    $pending = [];
}

$recent = $conn->query("
    SELECT om.order_id, om.status, om.created_at, u.username,
           SUM(od.qty * od.price) AS line_total
    FROM order_master om
    JOIN order_details od ON od.order_id = om.order_id
    JOIN products p ON p.id = od.prod_id
    JOIN users u ON u.id = om.user_id
    WHERE 1=1 $ownerFilter
    GROUP BY om.order_id, om.status, om.created_at, u.username
    ORDER BY om.order_id DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$lowStock = $conn->query("
    SELECT p.id, p.name, p.stock FROM products p
    WHERE p.stock <= 5 $ownerFilter ORDER BY p.stock ASC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
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
                <h2><?= $isSuperAdmin ? 'System overview' : 'Business overview' ?></h2>
                <p><?= $isSuperAdmin ? 'Accounts, catalog and sales across the whole system.' : 'Your products and the orders placed for them.' ?></p>
            </div>
            <a href="my_products.php?add=1" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add product</a>
        </div>

        <div class="row g-3 mb-4 reveal">
            <?php if ($isSuperAdmin): ?>
                <div class="col-6 col-xl"><div class="card stat"><div class="icon"><i class="bi bi-people"></i></div>
                    <div><div class="num"><?= $stats['users'] ?></div><div class="lbl">Users</div></div></div></div>
                <div class="col-6 col-xl"><div class="card stat"><div class="icon amber"><i class="bi bi-hourglass-split"></i></div>
                    <div><div class="num"><?= $stats['pending'] ?></div><div class="lbl">Pending</div></div></div></div>
            <?php else: ?>
                <div class="col-6 col-xl"><div class="card stat"><div class="icon amber"><i class="bi bi-exclamation-triangle"></i></div>
                    <div><div class="num"><?= $stats['low'] ?></div><div class="lbl">Low stock</div></div></div></div>
            <?php endif; ?>
            <div class="col-6 col-xl"><div class="card stat"><div class="icon slate"><i class="bi bi-box-seam"></i></div>
                <div><div class="num"><?= $stats['products'] ?></div><div class="lbl">Products</div></div></div></div>
            <div class="col-6 col-xl"><div class="card stat"><div class="icon"><i class="bi bi-receipt"></i></div>
                <div><div class="num"><?= $stats['orders'] ?></div><div class="lbl">Orders</div></div></div></div>
            <div class="col-12 col-xl"><div class="card stat"><div class="icon green"><i class="bi bi-currency-dollar"></i></div>
                <div><div class="num">$<?= number_format($stats['revenue'], 2) ?></div><div class="lbl">Revenue</div></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        Recent orders <a href="admin_orders.php" class="small text-decoration-none">View all</a>
                    </div>
                    <?php if (!$recent): ?>
                        <div class="empty-state"><i class="bi bi-inbox"></i>No orders yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
                                <tbody>
                                <?php foreach ($recent as $o): ?>
                                    <tr>
                                        <td class="fw-semibold">#<?= (int)$o['order_id'] ?></td>
                                        <td><?= htmlspecialchars($o['username']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars(date('M j, g:i A', strtotime($o['created_at']))) ?></td>
                                        <td><span class="badge text-bg-<?= $o['status'] === 'completed' ? 'success' : ($o['status'] === 'cancelled' ? 'danger' : 'primary') ?>"><?= htmlspecialchars(ucfirst($o['status'])) ?></span></td>
                                        <td class="text-end">$<?= number_format((float)$o['line_total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <?php if ($isSuperAdmin): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            Pending approvals <a href="manage_users.php" class="small text-decoration-none">Manage</a>
                        </div>
                        <?php if (!$pending): ?>
                            <div class="empty-state py-4"><i class="bi bi-check2-circle"></i>No accounts waiting.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($pending as $u): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($u['f_name'] . ' ' . $u['l_name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($u['username']) ?> · <?= (int)$u['role_id'] === 3 ? 'Owner' : 'Customer' ?></div>
                                        </div>
                                        <form method="POST" action="manage_users.php" class="d-flex gap-1">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="return" value="dashboard.php">
                                            <button name="status" value="approved" class="btn btn-success btn-sm">Approve</button>
                                            <button name="status" value="denied" class="btn btn-outline-danger btn-sm">Decline</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">Low stock</div>
                    <?php if (!$lowStock): ?>
                        <div class="empty-state py-4"><i class="bi bi-check2-circle"></i>All products are well stocked.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($lowStock as $p): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none text-reset"><?= htmlspecialchars($p['name']) ?></a>
                                    <span class="badge text-bg-<?= (int)$p['stock'] === 0 ? 'danger' : 'warning' ?>"><?= (int)$p['stock'] ?> left</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
