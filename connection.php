<?php
$host = '127.0.0.1';
$port = 3307;
$db   = 'sales_app';
$user = 'root';
$pass = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($host, $user, $pass, $db, $port);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>