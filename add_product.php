<?php
require_once 'auth.php';

if (!$canManageProducts) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Only admins and sellers can add products.'];
    header("Location: my_products.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_products.php");
    exit();
}

$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
$stock       = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

$ownerId = $userId;
if ($isSuperAdmin && isset($_POST['owner_id'])) {
    $candidate = (int)$_POST['owner_id'];
    $chk = $conn->prepare("SELECT id FROM users WHERE id = ? AND role_id IN (1,3) AND status = 'approved'");
    $chk->bind_param('i', $candidate);
    $chk->execute();
    if ($chk->get_result()->fetch_row()) {
        $ownerId = $candidate;
    }
}

$errors = [];

if ($name === '' || mb_strlen($name) > 100) {
    $errors[] = 'Name is required (max 100 chars).';
}

if ($price === false || $price < 0) {
    $errors[] = 'Price must be a non-negative number.';
}

if ($stock === false || $stock < 0) {
    $errors[] = 'Stock must be a non-negative integer.';
}

$imagePath = null;

if (!empty($_FILES['image']['name'])) {
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Image upload failed.';
    } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Image must be under 2MB.';
    } else {
        $mime    = mime_content_type($_FILES['image']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            $errors[] = 'Only JPG, PNG or WebP images are allowed.';
        } else {
            $uploadDir = __DIR__ . '/uploads/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imagePath = 'uploads/' . $filename;
            } else {
                $errors[] = 'Could not save the uploaded image.';
            }
        }
    }
}

if ($errors) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => implode(' ', $errors)
    ];

    header("Location: my_products.php");
    exit();
}

$stmt = $conn->prepare(
    "INSERT INTO products (owner_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?)"
);

/*
    s = string
    s = string
    d = double/float
    i = integer
    s = string
*/
$stmt->bind_param('issdis', $ownerId, $name, $description, $price, $stock, $imagePath);

$stmt->execute();

$_SESSION['flash'] = [
    'type' => 'success',
    'message' => "Product \"{$name}\" added."
];

header("Location: my_products.php");
exit();
?>