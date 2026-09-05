<?php
// Database credentials — change if your XAMPP MySQL uses a different password
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", ""); // XAMPP default is empty password
define("DB_NAME", "bidboard");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Start a session if one isn't already active (needed for CSRF tokens)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create a MySQLi connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Stop everything if connection fails
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for full unicode support
$conn->set_charset("utf8mb4");

function generate_csrf_token()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function verify_csrf_token($token)
{
    return isset($_SESSION["csrf_token"]) &&
        hash_equals($_SESSION["csrf_token"], $token ?? "");
}

function is_rate_limited(
    string $action_key,
    int $max_attempt = 5,
    int $windows_seconds = 60,
): bool {
    $now = time();

    if (!isset($_SESSION["rate_limit"][$action_key])) {
        $_SESSION["rate_limit"][$action_key] = [];
    }

    $_SESSION["rate_limit"][$action_key] = array_filter(
        $_SESSION["rate_limit"][$action_key],
        fn($timestamp) => $now - $timestamp < $windows_seconds,
    );

    if (count($_SESSION["rate_limit"][$action_key]) >= $max_attempt) {
        return true; // rate limited
    }
    $_SESSION["rate_limit"][$action_key][] = $now;
    return false;
}

function is_ip_rate_limited(
    string $action_key,
    int $max_attempt = 10,
    int $window_seconds = 300,
): bool {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    $key = $action_key . "_" . $ip;
    $now = time();

    if (!isset($_SESSION["ip_rate_limit"][$key])) {
        $_SESSION["ip_rate_limit"][$key] = [];
    }

    $_SESSION["ip_rate_limit"][$key] = array_filter(
        $_SESSION["ip_rate_limit"][$key],
        fn($timestamp) => $now - $timestamp < $window_seconds,
    );

    if (count($_SESSION["ip_rate_limit"][$key]) >= $max_attempt) {
        return true;
    }

    $_SESSION["ip_rate_limit"][$key][] = $now;
    return false;
}

?>
