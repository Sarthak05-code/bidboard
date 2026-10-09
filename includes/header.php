<?php
// Shared page header — included at the top of every page
// Ensure session is started securely before reading $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        "cookie_httponly" => true,
        "cookie_samesite" => "Lax",
        "cookie_secure" => isset($_SERVER["HTTPS"]), // Enable in production over HTTPS
    ]);
}

// ---------------------------------------------------------------------
// Modern Security Headers
// ---------------------------------------------------------------------
// 1. Prevent MIME sniffing
header("X-Content-Type-Options: nosniff");

// 2. Control information sent via the Referer header
header("Referrer-Policy: strict-origin-when-cross-origin");

// 3. Disable unwanted browser features (camera, microphone, geolocation)
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

// 4. Content Security Policy
// Allows self-hosted assets, Google Fonts, and inline scripts/styles for UI components
$csp = implode("; ", [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'", // Added 'unsafe-inline' to allow inline JS scripts
    "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'",
    "font-src 'self' https://fonts.gstatic.com",
    "img-src 'self' data:",
    "frame-ancestors 'none'", // Prevents clickjacking
    "form-action 'self'",
    "base-uri 'self'",
]);
header("Content-Security-Policy: {$csp}");

// Options setup with default fallbacks
$page_title = $page_title ?? "BidBoard";
$nav_context = $nav_context ?? "public";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(
        $page_title,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    ) ?> — BidBoard</title>

    <!-- Preconnect & Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/bidboard/css/style.css">
</head>

<body>

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="/bidboard/index.php" class="navbar-brand">Bid<span>Board</span></a>

            <ul class="navbar-links">
                <?php if ($nav_context === "public"): ?>
                    <li><a href="/bidboard/index.php">Browse Tasks</a></li>
                    <li><a href="/bidboard/auth/client_login.php">Client Login</a></li>
                    <li><a href="/bidboard/auth/admin_login.php" class="text-muted text-sm">Admin</a></li>
                    <li><a href="/bidboard/bid_status.php">My Bids</a></li>

                <?php elseif ($nav_context === "client"): ?>
                    <li><a href="/bidboard/client/dashboard.php">Dashboard</a></li>
                    <li><a href="/bidboard/client/post_task.php">Post Task</a></li>
                    <li>
                        <a href="/bidboard/client/edit_profile.php" class="nav-profile-link">
                            <?= htmlspecialchars(
                                $_SESSION["client_name"] ?? "",
                                ENT_QUOTES,
                                "UTF-8",
                            ) ?>
                        </a>
                    </li>
                    <li><a href="/bidboard/auth/logout.php?role=client">Logout</a></li>

                <?php elseif ($nav_context === "admin"): ?>
                    <li><a href="/bidboard/admin/dashboard.php">Dashboard</a></li>
                    <li><a href="/bidboard/admin/tasks.php">Tasks</a></li>
                    <li><a href="/bidboard/admin/bids.php">Bids</a></li>
                    <li><a href="/bidboard/admin/clients.php">Clients</a></li>
                    <li><a href="/bidboard/admin/reports.php">Reports</a></li>
                    <li><a href="/bidboard/auth/logout.php?role=admin">Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <?php if ($nav_context === "client"): ?>
        <div class="banner banner-client">
            Client Dashboard — <?= htmlspecialchars(
                $_SESSION["client_name"] ?? "",
                ENT_QUOTES,
                "UTF-8",
            ) ?>
        </div>
    <?php elseif ($nav_context === "admin"): ?>
        <div class="banner banner-admin">
            Admin Panel — <?= htmlspecialchars(
                $_SESSION["admin_name"] ?? "",
                ENT_QUOTES,
                "UTF-8",
            ) ?>
        </div>
    <?php elseif ($nav_context === "public"): ?>
        <div class="banner banner-public">
            Browsing as Guest — <a href="/bidboard/auth/client_login.php">Sign in as Client</a>
        </div>
    <?php endif; ?>
