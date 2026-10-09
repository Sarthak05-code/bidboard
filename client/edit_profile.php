<?php
// Client: edit profile — update name, email, and optionally password

require_once "../includes/auth_client.php";
require_once "../includes/db.php";
require_once "../includes/email_helper.php";

$client_id = $_SESSION["client_id"];

// Fetch current client data
$stmt = $conn->prepare("SELECT id, name, email FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        die("Invalid CSRF token. Please go back and try again.");
    }
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $new_pass = $_POST["new_password"] ?? ""; // optional — blank means don't change
    $confirm = $_POST["confirm"] ?? "";
    $current_raw = trim($_POST["current_password"] ?? ""); // required to confirm identity

    // Basic field validation
    if ($name === "" || $email === "") {
        $error = "Name and email are required.";
    } elseif (!preg_match('/^[A-Za-z][A-Za-z\s]*$/', $name)) {
        $error = "Name must start with a letter and contain only letters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif ($email !== $client["email"] && !is_email_deliverable($email)) {
        $error =
            "This email could not be verified. Please check for typos or enter a new email.";
    } elseif ($current_raw === "") {
        $error = "Enter your current password to save changes.";
    } else {
        // Verify current password before allowing changes
        $pass_stmt = $conn->prepare(
            "SELECT password FROM clients WHERE id = ?",
        );
        $pass_stmt->bind_param("i", $client_id);
        $pass_stmt->execute();
        $row = $pass_stmt->get_result()->fetch_assoc();
        $pass_stmt->close();

        if (!password_verify($current_raw, $row["password"])) {
            $error = "Current password is incorrect.";
        } else {
            // Check if email is taken by another account
            $email_check = $conn->prepare(
                "SELECT id FROM clients WHERE email = ? AND id != ?",
            );
            $email_check->bind_param("si", $email, $client_id);
            $email_check->execute();
            $email_check->store_result();
            $email_taken = $email_check->num_rows > 0;
            $email_check->close();

            if ($email_taken) {
                $error = "That email is already used by another account.";
            } elseif (
                $new_pass !== "" &&
                (strlen($new_pass) < 8 ||
                    !preg_match("/[A-Z]/", $new_pass) ||
                    !preg_match("/[a-z]/", $new_pass) ||
                    !preg_match("/[0-9]/", $new_pass) ||
                    !preg_match("/[^A-Za-z0-9]/", $new_pass))
            ) {
                $error =
                    "New password must be at least 8 characters, with uppercase, lowercase, a number, and a special character.";
            } elseif ($new_pass !== "" && $new_pass !== $confirm) {
                $error = "New passwords do not match.";
            } else {
                // Perform database update
                if ($new_pass !== "") {
                    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                    $upd = $conn->prepare(
                        "UPDATE clients SET name = ?, email = ?, password = ? WHERE id = ?",
                    );
                    $upd->bind_param(
                        "sssi",
                        $name,
                        $email,
                        $hashed,
                        $client_id,
                    );
                } else {
                    $upd = $conn->prepare(
                        "UPDATE clients SET name = ?, email = ? WHERE id = ?",
                    );
                    $upd->bind_param("ssi", $name, $email, $client_id);
                }

                if ($upd->execute()) {
                    $_SESSION["client_name"] = $name;
                    $success = "Profile updated successfully.";
                    $client["name"] = $name;
                    $client["email"] = $email;
                } else {
                    $error = "Update failed. Please try again.";
                }
                $upd->close();
            }
        }
    }
}

$page_title = "Edit Profile";
$nav_context = "client";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container" style="max-width:600px;">

        <div class="page-header">
            <h1>Edit profile</h1>
            <p>Update your name, email, or password.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars(
                $success,
            ) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                        generate_csrf_token(),
                    ) ?>">

                    <!-- Name -->
                    <div class="form-group">
                        <label class="form-label" for="name">Full name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            class="form-control"
                            value="<?= htmlspecialchars($client["name"]) ?>"
                            required>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($client["email"]) ?>"
                            required>
                    </div>

                    <div style="border-top:1px solid var(--border); margin:1.5rem 0 1.25rem;"></div>
                    <p class="text-sm text-muted" style="margin-bottom:1rem;">
                        Leave the new password fields blank if you don't want to change it.
                    </p>

                    <!-- New password (optional) -->
                    <div class="form-group">
                        <label class="form-label" for="new_password">New password</label>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="form-control"
                            placeholder="Min. 8 characters, upper/lower/number/symbol"
                            autocomplete="new-password">
                    </div>

                    <!-- Confirm new password -->
                    <div class="form-group">
                        <label class="form-label" for="confirm">Confirm new password</label>
                        <input
                            type="password"
                            id="confirm"
                            name="confirm"
                            class="form-control"
                            placeholder="Repeat new password"
                            autocomplete="new-password">
                    </div>

                    <div style="border-top:1px solid var(--border); margin:1.5rem 0 1.25rem;"></div>

                    <!-- Current password -->
                    <div class="form-group">
                        <label class="form-label" for="current_password">Current password</label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="form-control"
                            placeholder="Required to save any changes"
                            autocomplete="current-password"
                            required>
                        <p class="form-hint">We verify your identity before saving changes.</p>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                        <a href="/bidboard/client/dashboard.php" class="btn btn-ghost">Cancel</a>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Real-time name validation
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

    // 2. Real-time email validation
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

    // 3. New password & confirm password validation
    const newPasswordInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm');

    if (newPasswordInput && confirmInput) {
        const newPasswordError = document.createElement('p');
        newPasswordError.className = 'form-hint';
        newPasswordError.style.color = 'var(--danger)';
        newPasswordError.style.display = 'none';
        newPasswordInput.insertAdjacentElement('afterend', newPasswordError);

        const confirmError = document.createElement('p');
        confirmError.className = 'form-hint';
        confirmError.style.color = 'var(--danger)';
        confirmError.style.display = 'none';
        confirmInput.insertAdjacentElement('afterend', confirmError);

        function isStrongPassword(pass) {
            return pass.length >= 8 &&
                /[A-Z]/.test(pass) &&
                /[a-z]/.test(pass) &&
                /[0-9]/.test(pass) &&
                /[^A-Za-z0-9]/.test(pass);
        }

        function validateNewPassword() {
            const pass = newPasswordInput.value;
            const requirements = [];

            if (pass.length < 8) requirements.push('at least 8 characters');
            if (!/[A-Z]/.test(pass)) requirements.push('an uppercase letter');
            if (!/[a-z]/.test(pass)) requirements.push('a lowercase letter');
            if (!/[0-9]/.test(pass)) requirements.push('a number');
            if (!/[^A-Za-z0-9]/.test(pass)) requirements.push('a special character');

            if (pass.length === 0 || isStrongPassword(pass)) {
                newPasswordError.style.display = 'none';
                newPasswordInput.style.borderColor = '';
            } else {
                newPasswordError.textContent = 'New password must contain ' + requirements.join(', ') + '.';
                newPasswordError.style.display = 'block';
                newPasswordInput.style.borderColor = 'var(--danger)';
            }

            validateConfirmPassword();
        }

        function validateConfirmPassword() {
            if (confirmInput.value.length === 0) {
                confirmError.style.display = 'none';
                confirmInput.style.borderColor = '';
            } else if (confirmInput.value !== newPasswordInput.value) {
                confirmError.textContent = 'New passwords do not match.';
                confirmError.style.display = 'block';
                confirmInput.style.borderColor = 'var(--danger)';
            } else {
                confirmError.style.display = 'none';
                confirmError.style.borderColor = '';
            }
        }

        newPasswordInput.addEventListener('input', validateNewPassword);
        confirmInput.addEventListener('input', validateConfirmPassword);
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>
