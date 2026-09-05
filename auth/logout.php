<?php
// Logout handler — works for both admin and client
// Usage: logout.php?role=admin  OR  logout.php?role=client

$role = $_GET["role"] ?? "client";

if ($role === "admin") {
    session_name("bidboard_admin");
    session_start();

    // Explicitly clear the session cookie for this specific session name only
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"],
        );
    }

    session_unset();
    session_destroy();
    header("Location: /bidboard/auth/admin_login.php");
} else {
    session_name("bidboard_client");
    session_start();

    // Explicitly clear the session cookie for this specific session name only
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"],
        );
    }

    session_unset();
    session_destroy();
    header("Location: /bidboard/auth/client_login.php");
}

exit();
?>
