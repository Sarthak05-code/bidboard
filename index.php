<?php
// Public homepage — lists all open tasks, no login required

require_once "includes/db.php";

// Get filter values from URL query string
$search = trim($_GET["search"] ?? ""); // keyword search
$category = trim($_GET["category"] ?? ""); // filter by category

// --- PAGINATION SETUP ---
$page = max(1, (int) ($_GET["page"] ?? 1));
$per_page = 9; // Show 9 tasks per page (ideal for 3-column grid layouts)
$offset = ($page - 1) * $per_page;

// Build common WHERE clause conditions
$where_clauses = ["t.status = 'open'"];
$params = [];
$types = "";

if ($search !== "") {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if ($category !== "") {
    $where_clauses[] = "t.category = ?";
    $params[] = $category;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// 1. Get total count for pagination calculations
$count_sql = "SELECT COUNT(*) AS total
              FROM tasks t
              JOIN clients c ON t.client_id = c.id
              WHERE {$where_sql}";

$stmt = $conn->prepare($count_sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()["total"] ?? 0;
$stmt->close();

$total_pages = max(1, (int) ceil($total_rows / $per_page));
$page = min($page, $total_pages);

// 2. Fetch paginated open tasks
$sql = "SELECT t.*, c.name AS client_name,
               (SELECT COUNT(*) FROM bids b WHERE b.task_id = t.id) AS bid_count
        FROM tasks t
        JOIN clients c ON t.client_id = c.id
        WHERE {$where_sql}
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?";

$fetch_params = $params;
$fetch_types = $types . "ii";
$fetch_params[] = $per_page;
$fetch_params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($fetch_types, ...$fetch_params);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get distinct categories for the filter dropdown
$cats_result = $conn->query(
    "SELECT DISTINCT category FROM tasks WHERE status = 'open' ORDER BY category",
);
$categories = $cats_result->fetch_all(MYSQLI_ASSOC);

// Helper function to maintain filter parameters in pagination links
function build_pagination_url($page_num, $search, $category)
{
    $query = ["page" => $page_num];
    if ($search !== "") {
        $query["search"] = $search;
    }
    if ($category !== "") {
        $query["category"] = $category;
    }
    return "/bidboard/index.php?" . http_build_query($query);
}

$page_title = "Browse Tasks";
$nav_context = "public";
require_once "includes/header.php";
?>

<div class="page-wrap">
    <div class="container">

        <!-- Page heading -->
        <div class="page-header">
            <h1>Open Tasks</h1>
            <p>Browse available projects and submit your bid — no account needed.</p>
        </div>

        <!-- Search and filter bar -->
        <form method="GET" action="" style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.5rem;">
            <!-- Keyword search input -->
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search tasks..."
                value="<?= htmlspecialchars($search) ?>"
                style="flex:1; min-width:200px;">

            <!-- Category dropdown filter -->
            <select name="category" class="form-control" style="width:200px;">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option
                        value="<?= htmlspecialchars($cat["category"]) ?>"
                        <?= $category === $cat["category"] ? "selected" : "" ?>>
                        <?= htmlspecialchars($cat["category"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">Filter</button>

            <!-- Clear filters link -->
            <?php if ($search || $category): ?>
                <a href="/bidboard/index.php" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Task grid or empty state -->
        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <h3>No tasks found</h3>
                <p>Try a different search or check back later.</p>
            </div>
        <?php else: ?>
            <div class="task-grid">
                <?php foreach ($tasks as $task): ?>
                    <!-- Each task is a clickable card -->
                    <a href="/bidboard/task.php?id=<?= $task[
                        "id"
                    ] ?>" class="task-card">
                        <div class="task-card-title"><?= htmlspecialchars(
                            $task["title"],
                        ) ?></div>
                        <div class="task-card-desc"><?= htmlspecialchars(
                            $task["description"],
                        ) ?></div>

                        <div class="task-card-meta">
                            <!-- Category badge -->
                            <span class="badge badge-category"><?= htmlspecialchars(
                                $task["category"],
                            ) ?></span>

                            <!-- Budget display -->
                            <span class="text-sm" style="color:var(--success); font-weight:600;">
                                $<?= number_format($task["budget"], 2) ?>
                            </span>

                            <!-- Bid count -->
                            <span class="text-sm text-muted">
                                <?= $task["bid_count"] ?> bid<?= $task[
     "bid_count"
 ] != 1
     ? "s"
     : "" ?>
                            </span>

                            <!-- Deadline -->
                            <span class="text-sm text-muted" style="margin-left:auto;">
                                Due <?= date(
                                    "M j",
                                    strtotime($task["deadline"]),
                                ) ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Pagination UI -->
            <?php if ($total_pages > 1): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2rem; flex-wrap:wrap; gap:1rem;">
                    <span class="text-sm text-muted">
                        Showing <?= $offset + 1 ?>–<?= min(
    $offset + $per_page,
    $total_rows,
) ?> of <?= $total_rows ?> tasks
                    </span>

                    <div style="display:flex; gap:0.25rem; align-items:center;">
                        <?php if ($page > 1): ?>
                            <a href="<?= build_pagination_url(
                                $page - 1,
                                $search,
                                $category,
                            ) ?>" class="btn btn-sm btn-ghost">« Prev</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?= build_pagination_url(
                                $i,
                                $search,
                                $category,
                            ) ?>"
                               class="btn btn-sm <?= $i === $page
                                   ? "btn-primary"
                                   : "btn-ghost" ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= build_pagination_url(
                                $page + 1,
                                $search,
                                $category,
                            ) ?>" class="btn btn-sm btn-ghost">Next »</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php require_once "includes/footer.php"; ?>
