<?php
require_once 'auth.php';

if (!$canManageProducts || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

$stmt = $conn->prepare("SELECT id, name, image, owner_id FROM products WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product || (!$isSuperAdmin && (int)$product['owner_id'] !== $userId)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Product not found.'];
    header("Location: my_products.php");
    exit();
}

// Products that were already ordered are kept for the order history.
$stmt = $conn->prepare("SELECT COUNT(*) FROM order_details WHERE prod_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
if ((int)$stmt->get_result()->fetch_row()[0] > 0) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => "\"{$product['name']}\" has been ordered before and can't be deleted. Set its stock to 0 instead."];
    header("Location: my_products.php");
    exit();
}

$conn->begin_transaction();
$stmt = $conn->prepare("DELETE FROM cart_details WHERE prod_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$conn->query("DELETE FROM cart_master WHERE cart_id NOT IN (SELECT cart_id FROM cart_details)");
$conn->commit();

if ($product['image'] && strpos($product['image'], 'uploads/') === 0 && is_file(__DIR__ . '/' . $product['image'])) {
    unlink(__DIR__ . '/' . $product['image']);
}

$_SESSION['flash'] = ['type' => 'success', 'message' => "Product \"{$product['name']}\" deleted."];
header("Location: my_products.php");
exit();
