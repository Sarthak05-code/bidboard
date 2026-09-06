<?php
// Client login page

session_name("bidboard_client"); // unique session for clients
session_start();

// Already logged in — skip to dashboard
if (isset($_SESSION["client_id"])) {
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

require_once "../includes/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        die("Invalid CSRF token. Please go back and try again.");
    }
    if (is_rate_limited("client_login", 5, 60) || is_ip_rate_limited("client_login" , 10 , 300)) {
        $error = "Too many login attempt. Try again later";
    } else {
        $email = trim($_POST["email"] ?? "");
        // removal of trimmed password.
        $password = $_POST["password"] ?? "";

        if ($email === "" || $password === "") {
            $error = "Please fill in both fields.";
        } else {
            // Find client by email
            $stmt = $conn->prepare(
                "SELECT id, name, email, password, is_active FROM clients WHERE email = ?",
            );
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $client = $result->fetch_assoc();
            $stmt->close();

            if (!$client) {
                $error = "No account found with that email.";
            } elseif (!$client["is_active"]) {
                $error = "Your account has been deactivated. Contact admin.";
            } elseif (!password_verify($password, $client["password"])) {
                $error = "Incorrect password.";
            } else {
                // Login successful — store in session
                unset($_SESSION["rate_limit"]["client_login"]);
                session_regenerate_id(true);
                $_SESSION["client_id"] = $client["id"];
                $_SESSION["client_name"] = $client["name"];

                header("Location: /bidboard/client/dashboard.php");
                exit();
            }
        }
    }
}

$page_title = "Client Login";
$nav_context = "public";
require_once "../includes/header.php";
?>

<div class="auth-wrap">
    <div class="auth-card">
        <h2>Client Login</h2>
        <p class="auth-sub">Post tasks and find the right freelancer</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                generate_csrf_token(),
            ) ?>">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    autocomplete="email"
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
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.5rem;">
                Sign in
            </button>
        </form>

        <!-- Link to registration -->
        <p class="text-sm text-muted" style="text-align:center; margin-top:1.25rem;">
            No account?
            <a href="/bidboard/auth/client_register.php" style="color:var(--accent);">Create one</a>
        </p>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
