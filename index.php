<?php
session_start();
include 'connection.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

// No parameters, so a plain query() is fine
$result = $conn->query("SELECT id, name, description, price, stock, image FROM products ORDER BY id DESC");
$products = $result->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(cd.cqty), 0)
    FROM cart_master cm
    JOIN cart_details cd ON cd.cart_id = cm.cart_id
    WHERE cm.user_id = ?
");
$stmt->bind_param('i', $userId);
$stmt->execute();

// Equivalent to PDO's fetchColumn()
$row = $stmt->get_result()->fetch_row();
$cartCount = (int)($row[0] ?? 0);

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
    <title>Dashboard</title>

    <style>
        /* ===== Product Card Hover Effects ===== */
        .product-card {
            transition: transform .3s ease, box-shadow .3s ease;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, .18) !important;
        }

        /* Image zoom on hover */
        .product-card .card-img-top {
            transition: transform .4s ease;
        }

        .product-card:hover .card-img-top {
            transform: scale(1.08);
        }

        /* Title color change on hover */
        .product-card .card-title {
            transition: color .3s ease;
        }

        .product-card:hover .card-title {
            color: #0d6efd;
        }

        /* Price badge pop effect */
        .product-card .badge {
            transition: transform .3s ease;
        }

        .product-card:hover .badge {
            transform: scale(1.1);
        }

        /* Smooth button transitions */
        .product-card .btn {
            transition: all .25s ease;
        }

        .product-card .btn:hover {
            transform: translateY(-2px);
        }

        /* Quantity input focus glow */
        .product-card .form-control:focus {
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .25);
        }
    </style>
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
                <span class="navbar-text text-white me-3 d-none d-sm-inline">
                    Welcome, <strong class="text-primary"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong>
                </span>
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

        <!-- Header + Add Product button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-box-seam me-2"></i>Products</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-lg me-1"></i>Add Product
            </button>
        </div>

        <!-- Product Grid -->
        <?php if (empty($products)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No products yet. Click <strong>Add Product</strong> to create one.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach ($products as $p): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm product-card">
                            <img src="<?= htmlspecialchars($p['image'] ?: 'uploads/placeholder.svg') ?>"
                                 onerror="this.onerror=null; this.src='uploads/placeholder.svg';"
                                 class="card-img-top"
                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                 style="height: 180px; object-fit: cover;">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($p['name']) ?></h5>
                                    <span class="badge text-bg-success">$<?= number_format((float)$p['price'], 2) ?></span>
                                </div>
                                <p class="card-text text-muted small flex-grow-1">
                                    <?= htmlspecialchars($p['description']) ?>
                                </p>
                                <p class="mb-2">
                                    <?php if ((int)$p['stock'] > 0): ?>
                                        <span class="badge text-bg-secondary">
                                            <i class="bi bi-stack me-1"></i><?= (int)$p['stock'] ?> in stock
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">Out of stock</span>
                                    <?php endif; ?>
                                </p>
                                
                                <!-- Edit + Add to Cart -->
                                <div class="mt-auto">
                                    <a href="edit_product.php?id=<?= (int)$p['id'] ?>"
                                       class="btn btn-outline-secondary btn-sm w-100 mb-2">
                                        <i class="bi bi-pencil me-1"></i>Edit Product
                                    </a>
                                    <form action="add_to_cart.php" method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                        <input type="number" name="quantity" value="1" min="1"
                                               max="<?= (int)$p['stock'] ?>"
                                               class="form-control form-control-sm" style="width: 70px;"
                                               <?= (int)$p['stock'] === 0 ? 'disabled' : '' ?>>
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1"
                                                <?= (int)$p['stock'] === 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-cart-plus me-1"></i>Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="add_product.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProductModalLabel">
                            <i class="bi bi-plus-circle me-2"></i>Add New Product
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="productName" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="productName" name="name" required maxlength="100">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="productPrice" class="form-label">Price ($)</label>
                                <input type="number" class="form-control" id="productPrice" name="price"
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="productStock" class="form-label">Stock Qty</label>
                                <input type="number" class="form-control" id="productStock" name="stock"
                                       min="0" value="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="productDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="productDescription" name="description"
                                      rows="3" maxlength="500"></textarea>
                        </div>
                        <div class="mb-1">
                            <label for="productImage" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="productImage" name="image"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPG, PNG or WebP — max 2MB. Leave empty to use the default image.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>