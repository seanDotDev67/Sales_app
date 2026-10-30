<?php
require_once 'auth.php';

if (!$canManageProducts) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Only admins and owners can edit products.'];
    header("Location: index.php");
    exit();
}

// Returns the product if the current user may manage it (admin: any, owner: own only).
function loadManagedProduct(mysqli $conn, int $id, bool $isAdmin, int $userId): ?array
{
    $stmt = $conn->prepare("SELECT id, owner_id, name, description, price, stock, image FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || (!$isAdmin && (int)$row['owner_id'] !== $userId)) {
        return null;
    }
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock       = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    $existing = $id ? loadManagedProduct($conn, $id, $isSuperAdmin, $userId) : null;
    if (!$existing) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Product not found.'];
        header("Location: my_products.php");
        exit();
    }

    if ($name === '' || $price === false || $price < 0 || $stock === false || $stock < 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please fill in all fields correctly.'];
        header("Location: edit_product.php?id=$id");
        exit();
    }

    $ownerId = (int)$existing['owner_id'];
    if ($isSuperAdmin && isset($_POST['owner_id'])) {
        $candidate = (int)$_POST['owner_id'];
        $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND role_id IN (1,3) AND status = 'approved'");
        $chk->bind_param('i', $candidate);
        $chk->execute();
        if ($chk->get_result()->fetch_row()) {
            $ownerId = $candidate;
        }
    }

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $fail = null;
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $fail = 'Image upload failed.';
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $fail = 'Image must be 2MB or smaller.';
        } else {
            $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                $fail = 'Only JPG, PNG or WebP images are allowed.';
            } else {
                if (!is_dir(__DIR__ . '/uploads')) {
                    mkdir(__DIR__ . '/uploads', 0777, true);
                }
                $imagePath = 'uploads/' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                if (!move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/' . $imagePath)) {
                    $fail = 'Could not save the uploaded image.';
                    $imagePath = null;
                }
            }
        }
        if ($fail) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $fail];
            header("Location: edit_product.php?id=$id");
            exit();
        }
    }

    if ($imagePath) {
        $stmt = $conn->prepare("UPDATE products SET owner_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?");
        $stmt->bind_param('issdisi', $ownerId, $name, $description, $price, $stock, $imagePath, $id);
        $stmt->execute();

        $old = $existing['image'];
        if ($old && strpos($old, 'uploads/') === 0 && is_file(__DIR__ . '/' . $old)) {
            unlink(__DIR__ . '/' . $old);
        }
    } else {
        $stmt = $conn->prepare("UPDATE products SET owner_id = ?, name = ?, description = ?, price = ?, stock = ? WHERE id = ?");
        $stmt->bind_param('issdii', $ownerId, $name, $description, $price, $stock, $id);
        $stmt->execute();
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => "Product \"$name\" updated."];
    header("Location: my_products.php");
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product = $id ? loadManagedProduct($conn, $id, $isSuperAdmin, $userId) : null;
if (!$product) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Product not found.'];
    header("Location: my_products.php");
    exit();
}

$owners = [];
if ($isSuperAdmin) {
    $owners = $conn->query("SELECT id, username, role_id FROM users WHERE role_id IN (1,3) AND status = 'approved' ORDER BY username")
                   ->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = 'Edit Product';
$pageCrumb = ($isSuperAdmin ? 'All Products' : 'My Products') . ' / ' . $product['name'];
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

    <div class="page-body" style="max-width: 760px;">
        <?php include 'flash.php'; ?>

        <div class="card">
            <div class="card-header">Product #<?= (int)$product['id'] ?></div>
            <div class="card-body p-4">
                <form action="edit_product.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img src="<?= htmlspecialchars($product['image'] ?: 'uploads/placeholder.svg') ?>"
                             onerror="this.onerror=null; this.src='uploads/placeholder.svg';"
                             class="rounded" style="width: 96px; height: 96px; object-fit: cover;" alt="Current image">
                        <div class="flex-grow-1">
                            <label class="form-label">Replace image</label>
                            <input type="file" class="form-control" name="image" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Leave empty to keep the current image.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Product name</label>
                        <input type="text" class="form-control" name="name" required maxlength="100"
                               value="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Price ($)</label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required
                                   value="<?= htmlspecialchars($product['price']) ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" class="form-control" name="stock" min="0" required
                                   value="<?= (int)$product['stock'] ?>">
                        </div>
                    </div>

                    <?php if ($isSuperAdmin): ?>
                        <div class="mb-3">
                            <label class="form-label">Owner</label>
                            <select name="owner_id" class="form-select">
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?= (int)$o['id'] ?>" <?= (int)$o['id'] === (int)$product['owner_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($o['username']) ?> (<?= (int)$o['role_id'] === 1 ? 'Admin' : 'Owner' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="4" maxlength="500"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="my_products.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
