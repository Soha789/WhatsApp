<?php
// config.php — database connection + common helpers (no output)
// Adjust $DB_HOST if your MySQL host differs.
$DB_HOST = 'localhost';
$DB_NAME = 'dbdpg0oxmtxzf7';
$DB_USER = 'uwhgkspktdrxk';
$DB_PASS = 'sqj7swh1bcio';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db() {
    static $pdo = null;
    if ($pdo === null) {
        global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
        $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $opts);
    }
    return $pdo;
}

function require_login_json() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }
}

function current_user() {
    if (!isset($_SESSION['user_id'])) return null;
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, username, phone_number, created_at, last_seen FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
