<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

if (empty($current_business)) {
    echo json_encode([]);
    exit;
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

$bid = (int)$current_business['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT id, description, description_ur, default_rate, line_type, usage_count 
                       FROM service_descriptions 
                       WHERE business_id = ? AND (description LIKE ? OR description_ur LIKE ?) 
                       ORDER BY usage_count DESC, description ASC 
                       LIMIT 15");
$like = "%{$query}%";
$stmt->execute([$bid, $like, $like]);
$results = $stmt->fetchAll();

echo json_encode($results);
