<?php
// Admin login page
// Uses a separate session name to avoid conflicts with client session

session_name("bidboard_admin"); // unique session for admin
session_start();

// If already logged in, go straight to dashboard
if (isset($_SESSION["admin_id"])) {
    header("Location: /bidboard/admin/dashboard.php");
    exit();
}

require_once "../includes/db.php";

$error = ""; // holds any login error message

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        die("Invalid CSRF token. Please go back and try again.");
    }

    if (
        is_rate_limited("admin_login", 5, 60) ||
        is_ip_rate_limited("admin_login", 10, 300)
    ) {
        $error = "Too many login attempt, Please wait a miniute and try again";
    } else {
        $username = trim($_POST["username"] ?? ""); // get submitted username
        // remove trim in password , as user may intentionally want a password with space.
        $password = $_POST["password"] ?? ""; // get submitted password

        if ($username === "" || $password === "") {
            $error = "Please fill in both fields.";
        } else {
            // Look up admin by username
            $stmt = $conn->prepare(
                "SELECT id, username, password FROM admins WHERE username = ?",
            );
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();

            // Verify password hash
            if ($admin && password_verify($password, $admin["password"])) {
                unset($_SESSION["rate_limit"]["admin_login"]);
                session_regenerate_id(true); // added a fresh session id after login
                // Store admin info in session
                $_SESSION["admin_id"] = $admin["id"];
                $_SESSION["admin_name"] = $admin["username"];
                header("Location: /bidboard/admin/dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        }
    }
}

$page_title = "Admin Login";
$nav_context = "public";
require_once "../includes/header.php";
?>

<div class="auth-wrap">
    <div class="auth-card">
        <h2>Admin Login</h2>
        <p class="auth-sub">BidBoard administration panel</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                generate_csrf_token(),
            ) ?>">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="admin"
                    value="<?= htmlspecialchars($_POST["username"] ?? "") ?>"
                    autocomplete="username"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required>
                <p class="form-hint">Default credentials: admin / admin123</p>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.5rem;">
                Sign in as Admin
            </button>
        </form>
    </div>
</div>



<?php require_once "../includes/footer.php"; ?>
