<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

if (empty($current_business)) {
    echo json_encode([]);
    exit;
}

$query = trim($_GET['q'] ?? '');
$bid = (int)$current_business['id'];
$pdo = getDB();

if (strlen($query) > 0) {
    $stmt = $pdo->prepare("SELECT id, name, name_ur, code_or_barcode, unit, selling_price, current_stock, product_type 
                           FROM products 
                           WHERE business_id = ? AND status = 'active' 
                           AND (name LIKE ? OR name_ur LIKE ? OR code_or_barcode LIKE ?) 
                           ORDER BY name ASC LIMIT 20");
    $like = "%{$query}%";
    $stmt->execute([$bid, $like, $like, $like]);
} else {
    $stmt = $pdo->prepare("SELECT id, name, name_ur, code_or_barcode, unit, selling_price, current_stock, product_type 
                           FROM products 
                           WHERE business_id = ? AND status = 'active' 
                           ORDER BY name ASC LIMIT 20");
    $stmt->execute([$bid]);
}

$results = $stmt->fetchAll();
echo json_encode($results);
