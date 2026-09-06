<?php
define("DB_HOST", getenv("DB_HOST") ?: "127.0.0.1");
define("DB_USER", getenv("DB_USER") ?: "root");
define("DB_PASS", getenv("DB_PASSWORD") ?: "");
define("DB_NAME", getenv("DB_NAME") ?: "bidboard");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

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
