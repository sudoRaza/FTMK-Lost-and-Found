<?php
// ============================================================
// Cara guna real database dekat MySQL dekat XAMPP:
//   1. Bukak phpMyAdmin (http://localhost/phpmyadmin)
//   2. Create database nama ftmk_lostfound
//   3. runkan code sql dekat sql section dalam php admin
// ============================================================

define('USE_MOCK_DB', false);   

define('DB_HOST', 'localhost');
define('DB_NAME', 'ftmk_lostfound');
define('DB_USER', 'root');      
define('DB_PASS', '');          
define('DB_CHARSET', 'utf8mb4');


function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data);
    exit;
}

function getRequestBody(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
