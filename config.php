<?php
// config.php
// DB + Session bootstrap. Include in all other files (require_once).
// NOTE: Keep this file PHP-only (no HTML output).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$DB_HOST = "localhost";
$DB_USER = "ugfwxemowrehd";
$DB_PASS = "cliigx0v0hca";
$DB_NAME = "dbriviy7ozu1xo";

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    die("DB connection failed: " . $mysqli->connect_error);
}

// Helpers
function json_response($data = [], $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        echo '<script>window.location.href="login.php";</script>';
        exit;
    }
}

function random_user_number($mysqli) {
    // 9-digit random number, unique
    do {
        $n = strval(random_int(100000000, 999999999));
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE user_number=? LIMIT 1");
        $stmt->bind_param("s", $n);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $n;
}
