<?php
// Client: edit an existing task (only allowed while status is 'open')

require_once "../includes/auth_client.php";
require_once "../includes/db.php";

$client_id = $_SESSION["client_id"];
$task_id = (int) ($_GET["id"] ?? 0);

if ($task_id <= 0) {
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

// Fetch task — verify it belongs to this client and is still open
$stmt = $conn->prepare(
    "SELECT * FROM tasks WHERE id = ? AND client_id = ? AND status = 'open'",
);
$stmt->bind_param("ii", $task_id, $client_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    // Either doesn't exist, not theirs, or no longer open
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

$error = "";
$success = "";

$categories = [
    "Web Development",
    "Mobile Development",
    "Design / UI-UX",
    "Writing / Content",
    "Data Entry",
    "Digital Marketing",
    "Video / Animation",
    "Translation",
    "Accounting / Finance",
    "Other",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        die("Invalid CSRF token. Please go back and try again.");
    }
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $budget = trim($_POST["budget"] ?? "");
    $deadline = trim($_POST["deadline"] ?? "");

    // Validation
    if (
        $title === "" ||
        $description === "" ||
        $category === "" ||
        $budget === "" ||
        $deadline === ""
    ) {
        $error = "All fields are required.";
    } elseif (!in_array($category, $categories, true)) {
        $error = "Invalid Category Entry";
    } elseif (!is_numeric($budget) || $budget <= 0) {
        $error = "Enter a valid budget amount.";
    } elseif (!strtotime($deadline) || strtotime($deadline) <= time()) {
        $error = "Deadline must be a future date.";
    } else {
        // Update the task
        $upd = $conn->prepare(
            "UPDATE tasks SET title = ?, description = ?, category = ?, budget = ?, deadline = ?
     WHERE id = ? AND client_id = ? AND status = 'open'",
        );
        $budget_f = (float) $budget;
        $upd->bind_param(
            "sssdsii",
            $title,
            $description,
            $category,
            $budget_f,
            $deadline,
            $task_id,
            $client_id,
        );

        if ($upd->execute()) {
            $success = "Task updated successfully.";
            // Refresh local task var so form reflects saved values
            $task["title"] = $title;
            $task["description"] = $description;
            $task["category"] = $category;
            $task["budget"] = $budget;
            $task["deadline"] = $deadline;
        } else {
            $error = "Update failed. Please try again.";
        }
        $upd->close();
    }
}

$page_title = "Edit Task";
$nav_context = "client";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container" style="max-width:680px;">

        <div class="page-header">
            <h1>Edit task</h1>
            <p>You can only edit tasks that are still open.</p>
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

                    <div class="form-group">
                        <label class="form-label" for="title">Task title</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            class="form-control"
                            value="<?= htmlspecialchars($task["title"]) ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            style="min-height:140px;"
                            required><?= htmlspecialchars(
                                $task["description"],
                            ) ?></textarea>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label" for="category">Category</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option
                                        value="<?= htmlspecialchars($cat) ?>"
                                        <?= $task["category"] === $cat
                                            ? "selected"
                                            : "" ?>>
                                        <?= htmlspecialchars($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="budget">Budget (Rs. )</label>
                            <input
                                type="number"
                                id="budget"
                                name="budget"
                                class="form-control"
                                min="1"
                                step="0.01"
                                value="<?= htmlspecialchars($task["budget"]) ?>"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="deadline">Deadline</label>
                        <input
                            type="date"
                            id="deadline"
                            name="deadline"
                            class="form-control"
                            min="<?= date("Y-m-d", strtotime("+1 day")) ?>"
                            value="<?= htmlspecialchars($task["deadline"]) ?>"
                            required>
                    </div>

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
// Real-time budget validation
const budgetInput = document.getElementById('budget');
const budgetError = document.createElement('p');
budgetError.className = 'form-hint';
budgetError.style.color = 'var(--danger)';
budgetError.style.display = 'none';
budgetInput.insertAdjacentElement('afterend', budgetError);

budgetInput.addEventListener('input', () => {
    const value = parseFloat(budgetInput.value);
    if (budgetInput.value.length > 0 && (isNaN(value) || value <= 0)) {
        budgetError.textContent = 'Enter a valid budget greater than 0.';
        budgetError.style.display = 'block';
        budgetInput.style.borderColor = 'var(--danger)';
    } else {
        budgetError.style.display = 'none';
        budgetInput.style.borderColor = '';
    }
});

const budgetInputEl = document.getElementById('budget');
if (budgetInputEl) {
    budgetInputEl.addEventListener('keydown', (e) => {
        if (e.key === '-' || e.key === 'Subtract') {
            e.preventDefault();
        }
    });

    budgetInputEl.addEventListener('input', () => {
        if (budgetInputEl.value.includes('-')) {
            budgetInputEl.value = budgetInputEl.value.replace(/-/g, '');
        }
    });
}
</script>

<?php require_once "../includes/footer.php"; ?>
