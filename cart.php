<?php
    session_start();
    include 'connection.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $userId = (int)$_SESSION['user_id'];

    // Verify the user still exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Get cart items
    $stmt = $conn->prepare("
        SELECT cd.cart_detail_id, cd.prod_id, cd.cqty, p.name, p.price, p.stock, p.image
        FROM cart_master cm
        JOIN cart_details cd ON cd.cart_id = cm.cart_id
        JOIN products p ON p.id = cd.prod_id
        WHERE cm.user_id = ?
        ORDER BY cd.prod_id
    ");

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $total     = 0;
    $cartCount = 0;
    foreach ($items as $it) {
        $total     += $it['price'] * $it['cqty'];
        $cartCount += (int)$it['cqty'];
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>My Cart</title>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-speedometer2 me-2"></i>Sales System</a>
            <div class="d-flex align-items-center">
                <a href="cart.php" class="btn btn-outline-light btn-sm me-3 position-relative">
                    <i class="bi bi-cart3 me-1"></i>Cart
                    <?php if ($cartCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $cartCount > 99 ? '99+' : $cartCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h2 class="mb-4"><i class="bi bi-cart3 me-2"></i>My Cart</h2>

        <?php if (empty($items)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart-x fs-1 d-block mb-2"></i>
                Your cart is empty.
                <div class="mt-3">
                    <a href="index.php" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i>Continue Shopping</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Items table -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>C_ID</th>
                                        <th>Product ID</th> 
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th style="width: 180px;">Quantity</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td><?= (int)$it['cart_detail_id'] ?></td>
                                            <td><?= (int)$it['prod_id'] ?></td> 
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= htmlspecialchars($it['image'] ?: 'uploads/placeholder.svg') ?>"
                                                         onerror="this.onerror=null; this.src='uploads/placeholder.svg';"
                                                         class="rounded" style="width: 50px; height: 50px; object-fit: cover;" alt="">
                                                    <div>
                                                        <?= htmlspecialchars($it['name']) ?>
                                                        <?php if ((int)$it['cqty'] > (int)$it['stock']): ?>
                                                            <div class="text-danger small">Only <?= (int)$it['stock'] ?> left in stock!</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>$<?= number_format((float)$it['price'], 2) ?></td>
                                            <td>
                                                <form action="update_cart.php" method="POST" class="d-flex gap-1">
                                                    <input type="hidden" name="product_id" value="<?= (int)$it['prod_id'] ?>">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="number" name="quantity" value="<?= (int)$it['cqty'] ?>"
                                                           min="1" max="<?= (int)$it['stock'] ?>"
                                                           class="form-control form-control-sm" style="width: 70px;">
                                                    <button class="btn btn-outline-primary btn-sm" title="Update">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="fw-bold">$<?= number_format($it['price'] * $it['cqty'], 2) ?></td>
                                            <td>
                                                <form action="update_cart.php" method="POST">
                                                    <input type="hidden" name="product_id" value="<?= (int)$it['prod_id'] ?>">
                                                    <input type="hidden" name="action" value="remove">
                                                    <button class="btn btn-outline-danger btn-sm" title="Remove">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Order summary -->
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <i class="bi bi-receipt me-2"></i>Order Summary
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Items</span><span><?= $cartCount ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
                                <span>Total</span><span>$<?= number_format($total, 2) ?></span>
                            </div>
                            <form action="checkout.php" method="POST"
                                  onsubmit="return confirm('Place this order for $<?= number_format($total, 2) ?>?');">
                                <button type="submit" class="btn btn-success w-100 mb-2">
                                    <i class="bi bi-bag-check me-1"></i>Place Order
                                </button>
                            </form>
                            <a href="index.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-arrow-left me-1"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>