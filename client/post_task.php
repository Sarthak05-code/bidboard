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

    // Count Unicode letters for meaningful content checks
    preg_match_all("/\p{L}/u", $title, $title_letter_matches);
    $title_letter_count = count($title_letter_matches[0]);

    preg_match_all("/\p{L}/u", $description, $desc_letter_matches);
    $desc_letter_count = count($desc_letter_matches[0]);

    // Server-Side Validation
    if (
        $title === "" ||
        $description === "" ||
        $category === "" ||
        $budget === "" ||
        $deadline === ""
    ) {
        $error = "All fields are required.";
    } elseif (!preg_match("/^\p{L}/u", $title)) {
        $error =
            "Task title must start with a letter (cannot start with numbers or symbols).";
    } elseif (mb_strlen($title) < 10) {
        $error = "Task title must be at least 10 characters long.";
    } elseif ($title_letter_count < 6) {
        $error = "Task title must contain at least 6 letters.";
    } elseif (mb_strlen($description) < 30) {
        $error = "Description must be at least 30 characters long.";
    } elseif ($desc_letter_count < 20) {
        $error =
            "Please provide a meaningful description with at least 20 letters.";
    } elseif (!in_array($category, $categories, true)) {
        $error = "Invalid category selected.";
    } elseif (!is_numeric($budget) || $budget <= 0) {
        $error = "Enter a valid budget amount greater than 0.";
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
            $new_task_id = $conn->insert_id;
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
    <div class="container container-narrow">

        <div class="page-header">
            <h1>Post a task</h1>
            <p>Describe what you need done. Freelancers will send their bids.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form id="post-task-form" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                        generate_csrf_token(),
                    ) ?>">

                    <!-- Task Title -->
                    <div class="form-group">
                        <label class="form-label" for="title">Task title (*)</label>
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
                        <div class="counter-wrap">
                            <p id="titleError" class="form-hint form-error-inline"></p>
                            <span id="titleCounter" class="text-sm text-muted pitch-counter">0 chars | 0 letters</span>
                        </div>
                    </div>

                    <!-- Task Description -->
                    <div class="form-group">
                        <label class="form-label" for="description">Description (*)</label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-control textarea-lg"
                            placeholder="Describe the task in detail — requirements, deliverables, tech stack, etc."
                            required><?= htmlspecialchars(
                                $_POST["description"] ?? "",
                            ) ?></textarea>
                        <div class="counter-wrap">
                            <p id="descError" class="form-hint form-error-inline"></p>
                            <span id="descCounter" class="text-sm text-muted pitch-counter">0 chars | 0 letters</span>
                        </div>
                    </div>

                    <!-- Category & Budget -->
                    <div class="form-row-2col">
                        <div class="form-group">
                            <label class="form-label" for="category">Category (*)</label>
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
                            <label class="form-label" for="budget">Budget (Rs. ) (*)</label>
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
                            <p id="budgetError" class="form-hint form-error-inline"></p>
                        </div>
                    </div>

                    <!-- Deadline -->
                    <div class="form-group">
                        <label class="form-label" for="deadline">Deadline (*)</label>
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

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Post task</button>
                        <a href="/bidboard/client/dashboard.php" class="btn btn-ghost">Cancel</a>
                    </div>

                    <div class="mandatory">
                        (*) needs to be filled mandatorily
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<script>
// Helper to count Unicode letters
function countLetters(str) {
    const matches = str.match(/[\p{L}]/gu);
    return matches ? matches.length : 0;
}

// 1. Title Validation
const titleInput = document.getElementById('title');
const titleCounter = document.getElementById('titleCounter');
const titleError = document.getElementById('titleError');

function validateTitle() {
    const val = titleInput.value;
    const trimmed = val.trim();
    const chars = val.length;
    const letters = countLetters(val);

    titleCounter.textContent = `${chars} chars | ${letters} letters`;

    if (trimmed.length === 0) {
        titleError.textContent = '';
        titleInput.classList.remove('input-invalid');
        return true;
    }

    if (!/^[\p{L}]/u.test(trimmed)) {
        titleError.textContent = 'Title must start with a letter.';
        titleInput.classList.add('input-invalid');
        return false;
    }

    if (trimmed.length < 10) {
        titleError.textContent = 'Title must be at least 10 characters.';
        titleInput.classList.add('input-invalid');
        return false;
    }

    if (letters < 6) {
        titleError.textContent = 'Title must contain at least 6 letters.';
        titleInput.classList.add('input-invalid');
        return false;
    }

    titleError.textContent = '';
    titleInput.classList.remove('input-invalid');
    return true;
}

titleInput.addEventListener('input', validateTitle);

// 2. Description Validation
const descInput = document.getElementById('description');
const descCounter = document.getElementById('descCounter');
const descError = document.getElementById('descError');

function validateDescription() {
    const val = descInput.value;
    const trimmed = val.trim();
    const chars = val.length;
    const letters = countLetters(val);

    descCounter.textContent = `${chars} chars | ${letters} letters`;

    if (trimmed.length === 0) {
        descError.textContent = '';
        descInput.classList.remove('input-invalid');
        return true;
    }

    if (trimmed.length < 30) {
        descError.textContent = 'Description must be at least 30 characters.';
        descInput.classList.add('input-invalid');
        return false;
    }

    if (letters < 20) {
        descError.textContent = 'Please enter at least 20 letters.';
        descInput.classList.add('input-invalid');
        return false;
    }

    descError.textContent = '';
    descInput.classList.remove('input-invalid');
    return true;
}

descInput.addEventListener('input', validateDescription);

// 3. Budget Validation
const budgetInput = document.getElementById('budget');
const budgetError = document.getElementById('budgetError');

budgetInput.addEventListener('keydown', (e) => {
    if (e.key === '-' || e.key === 'Subtract') {
        e.preventDefault();
    }
});

budgetInput.addEventListener('input', () => {
    if (budgetInput.value.includes('-')) {
        budgetInput.value = budgetInput.value.replace(/-/g, '');
    }

    const value = parseFloat(budgetInput.value);
    if (budgetInput.value.length > 0 && (isNaN(value) || value <= 0)) {
        budgetError.textContent = 'Enter a valid budget greater than 0.';
        budgetInput.classList.add('input-invalid');
    } else {
        budgetError.textContent = '';
        budgetInput.classList.remove('input-invalid');
    }
});

// Run initial counters if fields populated via POST redirect
validateTitle();
validateDescription();
</script>

<?php require_once "../includes/footer.php"; ?>
