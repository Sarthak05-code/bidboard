<?php
// Shared page header — included at the top of every page
// $page_title should be set before including this file
// $nav_context should be 'public', 'client', or 'admin'
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

$page_title = $page_title ?? "BidBoard";
$nav_context = $nav_context ?? "public";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — BidBoard</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/bidboard/css/style.css">
</head>

<body>

    <nav class="navbar">
        <div class="navbar-inner">
            <!-- Brand logo text -->
            <a href="/bidboard/index.php" class="navbar-brand">Bid<span>Board</span></a>

            <ul class="navbar-links">
                <?php if ($nav_context === "public"): ?>
                    <!-- Public nav: browse tasks, login options -->
                    <li><a href="/bidboard/index.php">Browse Tasks</a></li>
                    <li><a href="/bidboard/auth/client_login.php">Client Login</a></li>
                    <li><a href="/bidboard/auth/admin_login.php" class="text-muted text-sm">Admin</a></li>
                    <li><a href="/bidboard/bid_status.php">My Bids</a></li>
                <?php elseif ($nav_context === "client"): ?>
                    <!-- Client nav: dashboard, post, logout -->
                    <li><a href="/bidboard/client/dashboard.php">Dashboard</a></li>
                    <li><a href="/bidboard/client/post_task.php">Post Task</a></li>
                    <li>
                        <!-- Client name links to edit profile -->
                        <a href="/bidboard/client/edit_profile.php"
                            style="padding: 0.4rem 0.5rem; color:var(--muted); text-decoration:none; font-size:0.9rem;">
                            <?= htmlspecialchars(
                                $_SESSION["client_name"] ?? "",
                            ) ?>
                        </a>
                    </li>
                    <li><a href="/bidboard/auth/logout.php?role=client">Logout</a></li>

                <?php elseif ($nav_context === "admin"): ?>
                    <!-- Admin nav: all sections -->
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
        <div style="background:var(--accent); color:#fff; text-align:center; padding:0.35rem; font-size:0.78rem; font-weight:600; letter-spacing:0.05em; text-transform:uppercase;">
            Client Dashboard — <?= htmlspecialchars(
                $_SESSION["client_name"] ?? "",
            ) ?>
        </div>
    <?php elseif ($nav_context === "admin"): ?>
        <div style="background:#7c3aed; color:#fff; text-align:center; padding:0.35rem; font-size:0.78rem; font-weight:600; letter-spacing:0.05em; text-transform:uppercase;">
            Admin Panel — <?= htmlspecialchars($_SESSION["admin_name"] ?? "") ?>
        </div>
    <?php elseif ($nav_context === "public"): ?>
        <div style="background:var(--success); color:#fff; text-align:center; padding:0.35rem; font-size:0.78rem; font-weight:600; letter-spacing:0.05em; text-transform:uppercase;">
            Browsing as Guest — <a href="/bidboard/auth/client_login.php" style="color:#fff; text-decoration:underline;">Sign in as Client</a>
        </div>
    <?php endif; ?>
