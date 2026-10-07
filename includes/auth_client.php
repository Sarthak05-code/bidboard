<?php
session_name("bidboard_client");
session_start();

if (!isset($_SESSION["client_id"])) {
    header("Location: /bidboard/auth/client_login.php");
    exit();
}

require_once __DIR__ . "/db.php";

// Re-check the account status on every protected page
$guard = $conn->prepare("SELECT is_active FROM clients WHERE id = ?");
$guard->bind_param("i", $_SESSION["client_id"]);
$guard->execute();
$guard_row = $guard->get_result()->fetch_assoc();
$guard->close();

if (!$guard_row || (int) $guard_row["is_active"] !== 1) {
    // Banned or deleted: reuse the existing logout so the session cookie is cleared properly
    header("Location: /bidboard/auth/logout.php?role=client");
    exit();
}
?>
