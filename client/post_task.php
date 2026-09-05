<?php
// Post a new task — client only

require_once "../includes/auth_client.php";
require_once "../includes/db.php";

$client_id = $_SESSION["client_id"];
$error = "";

// Preset categories for the dropdown
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
        die("Invalid CSRF token, Please go back and try again");
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
        $error = "Invalid category selected";
    } elseif (!is_numeric($budget) || $budget <= 0) {
        $error = "Enter a valid budget amount.";
    } elseif (!strtotime($deadline) || strtotime($deadline) <= time()) {
        $error = "Deadline must be a future date.";
    } else {
        // Insert the new task
        $stmt = $conn->prepare(
            "INSERT INTO tasks (client_id, title, description, category, budget, deadline)
             VALUES (?, ?, ?, ?, ?, ?)",
        );
        $stmt->bind_param(
            "isssds",
            $client_id,
            $title,
            $description,
            $category,
            $budget,
            $deadline,
        );

        if ($stmt->execute()) {
            $new_task_id = $conn->insert_id; // get the new task's ID
            $stmt->close();
            header("Location: /bidboard/task.php?id=" . $new_task_id);
            exit();
        } else {
            $error = "Failed to post task. Please try again.";
            $stmt->close();
        }
    }
}

$page_title = "Post a Task";
$nav_context = "client";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container" style="max-width:680px;">

        <div class="page-header">
            <h1>Post a task</h1>
            <p>Describe what you need done. Freelancers will send their bids.</p>
        </div>

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
                            placeholder="e.g. Build a personal portfolio website"
                            value="<?= htmlspecialchars(
                                $_POST["title"] ?? "",
                            ) ?>"
                            required>
                        <p class="form-hint">Keep it concise — freelancers scan titles first.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            style="min-height:140px;"
                            placeholder="Describe the task in detail — requirements, deliverables, tech stack, etc."
                            required><?= htmlspecialchars(
                                $_POST["description"] ?? "",
                            ) ?></textarea>
                    </div>

                    <!-- Two-column row for category and budget -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="form-group">
                            <label class="form-label" for="category">Category</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option
                                        value="<?= htmlspecialchars($cat) ?>"
                                        <?= ($_POST["category"] ?? "") === $cat
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
                                placeholder="e.g. 500"
                                min="1"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $_POST["budget"] ?? "",
                                ) ?>"
                                required>
                            <p class="form-hint">Your maximum budget.</p>
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
                            value="<?= htmlspecialchars(
                                $_POST["deadline"] ?? "",
                            ) ?>"
                            required>
                    </div>

                    <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
                        <button type="submit" class="btn btn-primary">Post task</button>
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
