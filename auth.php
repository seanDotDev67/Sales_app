<?php
// auth.php — include this at the top of EVERY page
session_start();
include 'connection.php';

// 1. Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

// 2. Get user + role from database (fresh every request)
$stmt = $conn->prepare("SELECT role_id, username, status FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

// 3. User no longer exists or is not approved → force logout
if (!$userData) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

if (($userData['status'] ?? 'approved') !== 'approved') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// 4. Save role in session
$_SESSION['role_id']  = (int)$userData['role_id'];
$_SESSION['username'] = $userData['username'];

// ===== ROLES (matches your roles table) =====
// 1 = Admin    → full control (manage users + products)
// 2 = Customer → shop, cart, orders
// 3 = Seller   → manage items (add / edit products)

$isSuperAdmin      = ($_SESSION['role_id'] === 1);   // Admin
$isCustomer        = ($_SESSION['role_id'] === 2);   // Customer
$isSeller          = ($_SESSION['role_id'] === 3);   // Seller
$canManageProducts = ($isSuperAdmin || $isSeller);   // Admin + Seller