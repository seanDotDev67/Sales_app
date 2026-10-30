<?php
require_once 'auth.php';

// Only customers can add to cart
// (Admin = 1, Customer = 2, Seller = 3)
if (!$isCustomer) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Only customers can use the cart.'];
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity  = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$productId || !$quantity || $quantity < 1) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid product or quantity.'];
    header("Location: index.php");
    exit();
}

$transactionActive = false;

try {
    $conn->begin_transaction();
    $transactionActive = true;

    // 1. Get product
    $stmt = $conn->prepare("SELECT id, name, stock FROM products WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $userStatus = $stmt->get_result()->fetch_assoc();
    if (!$userStatus || ($userStatus['status'] ?? 'approved') !== 'approved') {
        throw new RuntimeException('Your account is not approved for shopping.');
    }

    if (!$product) {
        throw new RuntimeException('Product not found.');
    }

    // 2. Get existing cart ID
    $stmt = $conn->prepare("SELECT cart_id FROM cart_master WHERE user_id = ? ORDER BY cart_id DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    
    // Equivalent to PDO's fetchColumn()
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $cartId = $row ? (int)$row[0] : null;

    // 3. Create cart if it doesn't exist
    if (!$cartId) {
        $stmt = $conn->prepare("INSERT INTO cart_master (user_id) VALUES (?)");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $cartId = (int)$conn->insert_id; // Equivalent to PDO's lastInsertId()
    }

    // 4. Check if item is already in cart
    $stmt = $conn->prepare("SELECT cqty FROM cart_details WHERE cart_id = ? AND prod_id = ?");
    $stmt->bind_param('ii', $cartId, $productId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $inCart = $row ? (int)$row[0] : 0;

    // 5. Validate stock
    if ($inCart + $quantity > (int)$product['stock']) {
        throw new RuntimeException(
            "Only {$product['stock']} of \"{$product['name']}\" in stock (you already have {$inCart} in your cart)."
        );
    }

    // 6. Insert or update cart details
    $stmt = $conn->prepare("
        INSERT INTO cart_details (cart_id, prod_id, cqty)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE cqty = cqty + VALUES(cqty)
    ");
    $stmt->bind_param('iii', $cartId, $productId, $quantity);
    $stmt->execute();

    $conn->commit();
    $transactionActive = false;
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => "\"{$product['name']}\" added to cart."];

} catch (Throwable $e) {
    if ($transactionActive) {
        $conn->rollback();
    }
    $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
}

header("Location: index.php");
exit();
?>