<?php
// Expects $pageTitle (string) and optionally $pageCrumb; shows the cart button for customers.
$topbarCart = 0;
if (($_SESSION['role_id'] ?? 0) === 2 && isset($conn, $userId)) {
    $tb = $conn->prepare("
        SELECT COALESCE(SUM(cd.cqty), 0)
        FROM cart_master cm JOIN cart_details cd ON cd.cart_id = cm.cart_id
        WHERE cm.user_id = ?
    ");
    $tb->bind_param('i', $userId);
    $tb->execute();
    $topbarCart = (int)($tb->get_result()->fetch_row()[0] ?? 0);
}
?>
<header class="topbar">
    <div>
        <?php if (!empty($pageCrumb)): ?><div class="crumbs"><?= htmlspecialchars($pageCrumb) ?></div><?php endif; ?>
        <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
    </div>
    <?php if (($_SESSION['role_id'] ?? 0) === 2): ?>
        <a href="cart.php" class="cart-btn">
            <i class="bi bi-cart3"></i>Cart
            <?php if ($topbarCart > 0): ?>
                <span class="badge rounded-pill text-bg-danger"><?= $topbarCart > 99 ? '99+' : $topbarCart ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
</header>
