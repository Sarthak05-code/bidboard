<?php
// Report a task or bid — public form (task reports) or client-only (bid reports)
// Start a named public session BEFORE anything else for CSRF tokens
session_name("bidboard_public");
session_start();

$type = $_GET["type"] ?? "";

if ($type === "bid") {
    require_once "includes/auth_client.php";
    require_once "includes/db.php";
} else {
    require_once "includes/db.php";
}

$id = (int) ($_GET["id"] ?? 0);
$task_id = (int) ($_GET["task"] ?? 0);

if (!in_array($type, ["task", "bid"]) || $id <= 0) {
    header("Location: /bidboard/index.php");
    exit();
}

// For bid reports, verify client owns the task and retrieve true task_id
if ($type === "bid") {
    $check = $conn->prepare(
        "SELECT b.task_id FROM bids b JOIN tasks t ON b.task_id = t.id WHERE b.id = ? AND t.client_id = ?",
    );
    $check->bind_param("ii", $id, $_SESSION["client_id"]);
    $check->execute();
    $result = $check->get_result();

    if ($row = $result->fetch_assoc()) {
        $task_id = (int) $row["task_id"];
    } else {
        $check->close();
        header("Location: /bidboard/client/dashboard.php");
        exit();
    }
    $check->close();
}

$error = "";
$success = isset($_GET["reported"]) ? "Report submitted. Thank you." : "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        $error = "Invalid CSRF token.";
    } else {
        $name = trim($_POST["reporter_name"] ?? "");
        $email = trim($_POST["reporter_email"] ?? "");
        $reason = trim($_POST["reason"] ?? "");

        if ($name === "" || $email === "" || $reason === "") {
            $error = "All fields are required.";
        } elseif (!preg_match('/^[A-Za-z][A-Za-z\s]*$/', $name)) {
            $error = "Name must start with a letter and contain only letters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } else {
            $task_id_insert =
                $type === "task" ? $id : ($task_id > 0 ? $task_id : null);
            $bid_id_insert = $type === "bid" ? $id : null;

            $stmt = $conn->prepare(
                "INSERT INTO reports (report_type, task_id, bid_id, reporter_name, reporter_email, reason) VALUES (?, ?, ?, ?, ?, ?)",
            );
            $stmt->bind_param(
                "siisss",
                $type,
                $task_id_insert,
                $bid_id_insert,
                $name,
                $email,
                $reason,
            );

            if ($stmt->execute()) {
                $stmt->close();
                $redirect_url = "/bidboard/report.php?type={$type}&id={$id}";
                if ($task_id > 0) {
                    $redirect_url .= "&task={$task_id}";
                }
                $redirect_url .= "&reported=1";
                header("Location: " . $redirect_url);
                exit();
            } else {
                $stmt->close();
                $error = "Failed to submit report. Please try again.";
            }
        }
    }
}

$page_title = "Report " . ucfirst($type);
$nav_context = $type === "bid" ? "client" : "public";
require_once "includes/header.php";
?>

<div class="page-wrap">
    <div class="container" style="max-width:500px;">
        <a href="<?= $type === "task"
            ? "/bidboard/task.php?id=" . $id
            : "/bidboard/client/task_bids.php?id=" .
                $task_id ?>" class="text-sm text-muted" style="text-decoration:none;">&larr; Back</a>

        <div class="card" style="margin-top:1rem;">
            <div class="card-header">Report <?= htmlspecialchars(
                ucfirst($type),
            ) ?></div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars(
                        $success,
                    ) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars(
                        $error,
                    ) ?></div>
                <?php endif; ?>

                <form method="POST" id="reportForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                        generate_csrf_token(),
                    ) ?>">

                    <div class="form-group">
                        <label class="form-label">Your Name</label>
                        <input type="text" id="reporter_name" name="reporter_name" class="form-control" value="<?= htmlspecialchars(
                            $_POST["reporter_name"] ?? "",
                        ) ?>" required>
                        <p id="nameError" class="form-hint" style="color:var(--danger); display:none;">Name must start with a letter and contain only letters.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Your Email</label>
                        <input type="email" id="reporter_email" name="reporter_email" class="form-control" value="<?= htmlspecialchars(
                            $_POST["reporter_email"] ?? "",
                        ) ?>" required>
                        <p id="emailError" class="form-hint" style="color:var(--danger); display:none;">Enter a valid email address.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" required style="min-height:80px;"><?= htmlspecialchars(
                            $_POST["reason"] ?? "",
                        ) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger" style="width:100%;">Submit Report</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('reporter_name');
    const emailInput = document.getElementById('reporter_email');
    const nameError = document.getElementById('nameError');
    const emailError = document.getElementById('emailError');

    if (nameInput) {
        nameInput.addEventListener('input', function() {
            const namePattern = /^[A-Za-z][A-Za-z\s]*$/;
            if (nameInput.value.length > 0 && !namePattern.test(nameInput.value)) {
                nameError.style.display = 'block';
                nameInput.style.borderColor = 'var(--danger)';
            } else {
                nameError.style.display = 'none';
                nameInput.style.borderColor = '';
            }
        });
    }

    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailInput.value.length > 0 && !emailPattern.test(emailInput.value)) {
                emailError.style.display = 'block';
                emailInput.style.borderColor = 'var(--danger)';
            } else {
                emailError.style.display = 'none';
                emailInput.style.borderColor = '';
            }
        });
    }
});
</script>

<?php require_once "includes/footer.php"; ?>
