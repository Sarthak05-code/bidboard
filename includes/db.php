
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

/**
 * Used in:
 * - auth/admin_login.php
 * - auth/client_login.php
 * - auth/client_register.php
 * - client/post_task.php
 * - client/edit_task.php
 * - client/edit_profile.php
 * - task.php
 * - client/dashboard.php (Mark done, Delete forms)
 * - client/task_bids.php (Accept, Reject, Mark as completed forms)
 * - admin/tasks.php (Delete form)
 * - admin/bids.php (Delete form)
 * - admin/clients.php (Activate/Deactivate form)
 */
function generate_csrf_token()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

/**
 * Used in:
 * - auth/admin_login.php
 * - auth/client_login.php
 * - auth/client_register.php
 * - client/post_task.php
 * - client/edit_task.php
 * - client/edit_profile.php
 * - task.php
 * - actions/accept_bid.php
 * - actions/reject_bid.php
 * - actions/update_task_status.php
 * - actions/delete_task.php
 * - admin/tasks.php (Delete handler)
 * - admin/bids.php (Delete handler)
 * - admin/clients.php (Toggle handler)
 */
function verify_csrf_token($token)
{
    return isset($_SESSION["csrf_token"]) &&
        hash_equals($_SESSION["csrf_token"], $token ?? "");
}

/**
 * Used in:
 * - auth/admin_login.php
 * - auth/client_login.php
 */
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
        return true; // Rate limited
    }

    $_SESSION["rate_limit"][$action_key][] = $now;

    return false;
}

/**
 * Used in:
 * - auth/admin_login.php
 * - auth/client_login.php
 */
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
