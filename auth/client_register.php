<?php
// Client registration page

session_name("bidboard_client");
session_start();

if (isset($_SESSION["client_id"])) {
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

require_once "../includes/db.php";
require_once "../includes/email_helper.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        die("Invalid CSRF token. Please go back and try again.");
    }
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");

    // NOTE : to not trim password as users may intentionally want it like that.
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm"] ?? "";

    // Basic validation
    if ($name === "" || $email === "" || $password === "") {
        $error = "All fields are required.";
    } elseif (!preg_match('/^[A-Za-z][A-Za-z\s]*$/', $name)) {
        $error = "Name must start with a letter and contain only letters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (!is_email_deliverable($email)) {
        $error =
            "This email address could not be verified, Please check for typos or use a different email.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $error = "Password must contain at least one uppercase letter. ";
    } elseif (!preg_match("/[a-z]/", $password)) {
        $error = "Password must contain atleast one lowercase letter.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $error = "Password must contain atleast one number";
    } elseif (!preg_match("/[^A-Za-z0-9]/", $password)) {
        $error = "Password must contains atleast one special character.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email is already taken
        $stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "An account with that email already exists.";
            $stmt->close();
        } else {
            $stmt->close();

            // Hash password and insert new client
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $ins = $conn->prepare(
                "INSERT INTO clients (name, email, password) VALUES (?, ?, ?)",
            );
            $ins->bind_param("sss", $name, $email, $hashed);

            if ($ins->execute()) {
                // Auto-login after registration
                $_SESSION["client_id"] = $conn->insert_id;
                $_SESSION["client_name"] = $name;
                $ins->close();
                header("Location: /bidboard/client/dashboard.php");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
                $ins->close();
            }
        }
    }
}

$page_title = "Create Account";
$nav_context = "public";
require_once "../includes/header.php";
?>

<div class="auth-wrap">
    <div class="auth-card">
        <h2>Create account</h2>
        <p class="auth-sub">Start posting tasks and finding talent</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                generate_csrf_token(),
            ) ?>">

            <div class="form-group">
                <label class="form-label" for="name">Full name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    placeholder="Jane Doe"
                    value="<?= htmlspecialchars($_POST["name"] ?? "") ?>"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Create a strong password."
                    required>
                <p class="form-hint">At least 8 characters, including uppercase, lowercase, a number, and a special character.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm">Confirm password</label>
                <input
                    type="password"
                    id="confirm"
                    name="confirm"
                    class="form-control"
                    placeholder="Repeat password"
                    required>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.5rem;">
                Create account
            </button>
        </form>

        <p class="text-sm text-muted" style="text-align:center; margin-top:1.25rem;">
            Already have an account?
            <a href="/bidboard/auth/client_login.php" style="color:var(--accent);">Sign in</a>
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Real-time name validation — must start with a letter, no leading numbers
    const nameInput = document.getElementById('name');
    if (nameInput) {
        const nameError = document.createElement('p');
        nameError.className = 'form-hint';
        nameError.style.color = 'var(--danger)';
        nameError.style.display = 'none';
        nameInput.insertAdjacentElement('afterend', nameError);

        nameInput.addEventListener('input', () => {
            const namePattern = /^[A-Za-z][A-Za-z\s]*$/;
            if (nameInput.value.length > 0 && !namePattern.test(nameInput.value)) {
                nameError.textContent = 'Name must start with a letter and contain only letters.';
                nameError.style.display = 'block';
                nameInput.style.borderColor = 'var(--danger)';
            } else {
                nameError.style.display = 'none';
                nameInput.style.borderColor = '';
            }
        });
    }

    // Real-time email validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        const emailError = document.createElement('p');
        emailError.className = 'form-hint';
        emailError.style.color = 'var(--danger)';
        emailError.style.display = 'none';
        emailInput.insertAdjacentElement('afterend', emailError);

        emailInput.addEventListener('input', () => {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailInput.value.length > 0 && !emailPattern.test(emailInput.value)) {
                emailError.textContent = 'Enter a valid email address.';
                emailError.style.display = 'block';
                emailInput.style.borderColor = 'var(--danger)';
            } else {
                emailError.style.display = 'none';
                emailInput.style.borderColor = '';
            }
        });
    }

    // Real-time strong password validation
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm');

    if (passwordInput && confirmInput) {
        const passwordError = document.createElement('p');
        passwordError.className = 'form-hint';
        passwordError.style.color = 'var(--danger)';
        passwordError.style.display = 'none';
        passwordInput.insertAdjacentElement('afterend', passwordError);

        const confirmError = document.createElement('p');
        confirmError.className = 'form-hint';
        confirmError.style.color = 'var(--danger)';
        confirmError.style.display = 'none';
        confirmInput.insertAdjacentElement('afterend', confirmError);

        function isStrongPassword(password) {
            return password.length >= 8 &&
                /[A-Z]/.test(password) &&
                /[a-z]/.test(password) &&
                /[0-9]/.test(password) &&
                /[^A-Za-z0-9]/.test(password);
        }

        function validatePassword() {
            const password = passwordInput.value;
            const requirements = [];

            if (password.length < 8) requirements.push('at least 8 characters');
            if (!/[A-Z]/.test(password)) requirements.push('an uppercase letter');
            if (!/[a-z]/.test(password)) requirements.push('a lowercase letter');
            if (!/[0-9]/.test(password)) requirements.push('a number');
            if (!/[^A-Za-z0-9]/.test(password)) requirements.push('a special character');

            if (password.length === 0 || isStrongPassword(password)) {
                passwordError.style.display = 'none';
                passwordInput.style.borderColor = '';
            } else {
                passwordError.textContent = 'Password must contain ' + requirements.join(', ') + '.';
                passwordError.style.display = 'block';
                passwordInput.style.borderColor = 'var(--danger)';
            }

            validateConfirmPassword();
        }

        function validateConfirmPassword() {
            if (confirmInput.value.length === 0) {
                confirmError.style.display = 'none';
                confirmInput.style.borderColor = '';
            } else if (confirmInput.value !== passwordInput.value) {
                confirmError.textContent = 'Passwords do not match.';
                confirmError.style.display = 'block';
                confirmInput.style.borderColor = 'var(--danger)';
            } else {
                confirmError.style.display = 'none';
                confirmError.style.borderColor = '';
            }
        }

        passwordInput.addEventListener('input', validatePassword);
        confirmInput.addEventListener('input', validateConfirmPassword);
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>
