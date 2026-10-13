<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verify user still exists
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit();
}

$userId    = (int)$_SESSION['user_id'];
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$action    = $_POST['action'] ?? '';

if (!$productId || !in_array($action, ['update', 'remove'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid request.'];
    header("Location: cart.php");
    exit();
}

// Get the user's cart ID
$stmt = $conn->prepare("SELECT cart_id FROM cart_master WHERE user_id = ? ORDER BY cart_id DESC LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();

$row = $stmt->get_result()->fetch_row();
$cartId = $row ? (int)$row[0] : null;

if (!$cartId) {
    header("Location: cart.php");
    exit();
}

if ($action === 'remove') {
    $stmt = $conn->prepare("DELETE FROM cart_details WHERE cart_id = ? AND prod_id = ?");
    $stmt->bind_param('ii', $cartId, $productId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Item removed from cart.'];
} else {
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    if (!$quantity || $quantity < 1) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Quantity must be at least 1.'];
        header("Location: cart.php");
        exit();
    }

    // ✅ FIXED: changed 'id' to 'prod_id' to match the actual products table column
    $stmt = $conn->prepare("SELECT name, stock FROM products WHERE prod_id = ?");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Product not found.'];
    } elseif ($quantity > (int)$product['stock']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => "Only {$product['stock']} of \"{$product['name']}\" in stock."];
    } else {
        $stmt = $conn->prepare("UPDATE cart_details SET cqty = ? WHERE cart_id = ? AND prod_id = ?");
        $stmt->bind_param('iii', $quantity, $cartId, $productId);
        $stmt->execute();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cart updated.'];
    }
}

header("Location: cart.php");
exit();
?>