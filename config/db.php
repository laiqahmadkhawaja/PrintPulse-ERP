<?php
/**
 * Database Connection Helper (PDO)
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $host = '127.0.0.1';
        $port = '3306';
        $dbname = 'pshop_db';
        $user = 'root';
        $pass = '';

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Database connection error: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}
