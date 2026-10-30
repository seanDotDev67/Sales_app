<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sidebarCurrentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$sidebarRoleId = (int)($_SESSION['role_id'] ?? 2);
$sidebarUser   = $_SESSION['username'] ?? 'User';
$sidebarRoleName = [1 => 'Administrator', 2 => 'Customer', 3 => 'Owner'][$sidebarRoleId] ?? 'User';

if (!function_exists('sidebarLinkClass')) {
    function sidebarLinkClass(string $page): string
    {
        global $sidebarCurrentPage;
        return $page === $sidebarCurrentPage ? 'nav-link active' : 'nav-link';
    }
}

if (!function_exists('sidebarItem')) {
    function sidebarItem(string $href, string $icon, string $label): void
    {
        echo '<li class="nav-item"><a href="' . $href . '" class="' . sidebarLinkClass($href) . '">'
           . '<i class="bi ' . $icon . '"></i><span>' . $label . '</span></a></li>';
    }
}

include __DIR__ . '/welcome_splash.php';
?>

<aside class="sidebar">

    <a href="index.php" class="brand">
        <span class="brand-mark"><i class="bi bi-bar-chart-line"></i></span>
        <span>
            <span class="brand-name d-block">Sales Manager</span>
            <span class="brand-sub">Operations</span>
        </span>
    </a>

    <button class="menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sideMenu" aria-label="Toggle menu">
        <i class="bi bi-list"></i>
    </button>

    <div class="collapse d-md-flex flex-column flex-grow-1 w-100" id="sideMenu">
    <ul class="nav flex-column">
        <?php if ($sidebarRoleId === 2): ?>
            <li class="nav-label">Shop</li>
            <?php
            sidebarItem('index.php', 'bi-grid', 'Products');
            sidebarItem('cart.php', 'bi-cart3', 'My Cart');
            sidebarItem('orders.php', 'bi-receipt', 'My Orders');
            ?>
        <?php else: ?>
            <li class="nav-label"><?= $sidebarRoleId === 1 ? 'Administration' : 'My Business' ?></li>
            <?php
            sidebarItem('index.php', 'bi-speedometer2', 'Dashboard');
            sidebarItem('my_products.php', 'bi-box-seam', $sidebarRoleId === 1 ? 'All Products' : 'My Products');
            sidebarItem('admin_orders.php', 'bi-list-check', $sidebarRoleId === 1 ? 'All Orders' : 'Orders');
            if ($sidebarRoleId === 1) {
                sidebarItem('manage_users.php', 'bi-people', 'Users &amp; Approvals');
            }
            ?>
            <li class="nav-label">Storefront</li>
            <?php sidebarItem('shop.php', 'bi-grid', 'Browse Catalog'); ?>
        <?php endif; ?>
    </ul>

    <div class="user-box">
        <div class="user-chip">
            <span class="avatar"><?= htmlspecialchars(substr($sidebarUser, 0, 1)) ?></span>
            <span>
                <span class="user-name d-block"><?= htmlspecialchars($sidebarUser) ?></span>
                <span class="user-role"><?= htmlspecialchars($sidebarRoleName) ?></span>
            </span>
        </div>
        <a href="logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i>Sign out</a>
    </div>
    </div>

</aside>
