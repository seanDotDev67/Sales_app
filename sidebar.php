<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sidebarCurrentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$sidebarRoleId = (int)($_SESSION['role_id'] ?? 2);
$sidebarIsAdmin = ($sidebarRoleId === 1);

if (!function_exists('sidebarLinkClass')) {
    function sidebarLinkClass(string $page): string
    {
        global $sidebarCurrentPage;

        if ($page === $sidebarCurrentPage) {
            return 'nav-link active';
        }

        return 'nav-link text-white';
    }
}
?>

<aside class="sidebar d-flex flex-column p-3 bg-dark text-white">

    <a href="index.php" class="d-flex align-items-center mb-3 text-white text-decoration-none">
        <i class="bi bi-cart3 fs-3 me-2"></i>
        <span class="fs-5 fw-bold">Sales App</span>
    </a>

    <hr>

    <ul class="nav nav-pills flex-column mb-auto">

        <li class="nav-item">
            <a href="index.php" class="<?= sidebarLinkClass('index.php') ?>">
                <i class="bi bi-box-seam me-2"></i>
                Products
            </a>
        </li>

        <li class="nav-item">
            <a href="cart.php" class="<?= sidebarLinkClass('cart.php') ?>">
                <i class="bi bi-cart-check me-2"></i>
                My Cart
            </a>
        </li>

        <li class="nav-item">
            <a href="orders.php" class="<?= sidebarLinkClass('orders.php') ?>">
                <i class="bi bi-receipt me-2"></i>
                My Orders
            </a>
        </li>

        <?php if ($sidebarIsAdmin): ?>

            <li class="mt-3 mb-1 px-3 text-uppercase small text-secondary">
                Admin
            </li>

            <li class="nav-item">
                <a href="index.php?add_product=1" class="nav-link text-white">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add Product
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_products.php" class="<?= sidebarLinkClass('admin_products.php') ?>">
                    <i class="bi bi-gear me-2"></i>
                    Manage Products
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_users.php" class="<?= sidebarLinkClass('admin_users.php') ?>">
                    <i class="bi bi-people me-2"></i>
                    Manage Users
                </a>
            </li>

            <li class="nav-item">
                <a href="admin_orders.php" class="<?= sidebarLinkClass('admin_orders.php') ?>">
                    <i class="bi bi-list-check me-2"></i>
                    All Orders
                </a>
            </li>

        <?php endif; ?>

    </ul>

    <hr>

    <div class="d-grid">
       <a href="logout.php" class="btn btn-outline-danger btn-sm">
             <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>

</aside>