<?php
// Admin: view and manage all tasks across all clients

require_once "../includes/auth_admin.php";
require_once "../includes/db.php";

// Read current filters early (needed for redirect after delete)
$page = max(1, (int) ($_GET["page"] ?? 1));
$status_filter = trim($_GET["status"] ?? "");
$search = trim($_GET["search"] ?? "");

// Flash message (after delete)
$flash = $_SESSION["flash"] ?? "";
unset($_SESSION["flash"]);

// Handle delete POST from this page
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_task_id"])) {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        die("Invalid CSRF token");
    }
    $del_id = (int) $_POST["delete_task_id"];
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION["flash"] = "Task deleted.";

    // Redirect back to same page with filters preserved
    $redirect_qs = [];
    if ($page > 1) {
        $redirect_qs["page"] = $page;
    }
    if ($status_filter) {
        $redirect_qs["status"] = $status_filter;
    }
    if ($search) {
        $redirect_qs["search"] = $search;
    }

    $redirect_url = "/bidboard/admin/tasks.php";
    if ($redirect_qs) {
        $redirect_url .= "?" . http_build_query($redirect_qs);
    }

    header("Location: $redirect_url");
    exit();
}

// Pagination configuration
$per_page = 10;
$offset = ($page - 1) * $per_page;

$allowed_statuses = ["open", "in_progress", "completed"];

// Base conditional logic for both COUNT and SELECT queries
$where_sql = "WHERE 1=1";
$params = [];
$types = "";

if (in_array($status_filter, $allowed_statuses)) {
    $where_sql .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search !== "") {
    $like = "%" . $search . "%";
    $where_sql .=
        " AND (t.title LIKE ? OR t.description LIKE ? OR c.name LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

// 1. Get total record count for pagination math
$count_sql = "SELECT COUNT(*) AS total FROM tasks t JOIN clients c ON t.client_id = c.id $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()["total"];
$count_stmt->close();

$total_pages = max(1, ceil($total_rows / $per_page));
$page = min($page, $total_pages);

// 2. Fetch paginated records
$sql = "SELECT t.*, c.name AS client_name,
               (SELECT COUNT(*) FROM bids b WHERE b.task_id = t.id) AS bid_count
        FROM tasks t
        JOIN clients c ON t.client_id = c.id
        $where_sql
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?";

$query_params = $params;
$query_params[] = $per_page;
$query_params[] = $offset;
$query_types = $types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($query_types, ...$query_params);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "All Tasks";
$nav_context = "admin";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container">

        <div class="page-header">
            <h1>All Tasks</h1>
            <p>Manage tasks across all clients (Total: <?= $total_rows ?>)</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-success"><?= htmlspecialchars(
                $flash,
            ) ?></div>
        <?php endif; ?>

        <!-- Search bar -->
        <form method="GET" action="" style="display:flex; gap:0.75rem; margin-bottom:1rem; flex-wrap:wrap;">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars(
                    $status_filter,
                ) ?>">
            <?php endif; ?>
            <input type="text" name="search" class="form-control" placeholder="Search by title, description, or client name..." value="<?= htmlspecialchars(
                $search,
            ) ?>" style="flex:1; min-width:220px;">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search || $status_filter): ?>
                <a href="/bidboard/admin/tasks.php" class="btn btn-ghost">Reset Filters</a>
            <?php endif; ?>
        </form>

        <!-- Status filter tabs -->
        <div style="display:flex; gap:0.4rem; margin-bottom:1.25rem; flex-wrap:wrap;">
            <?php $base_qs = $search ? "&search=" . urlencode($search) : ""; ?>
            <a href="/bidboard/admin/tasks.php<?= $search
                ? "?search=" . urlencode($search)
                : "" ?>" class="btn btn-sm <?= $status_filter === ""
    ? "btn-primary"
    : "btn-ghost" ?>">All</a>
            <a href="/bidboard/admin/tasks.php?status=open<?= $base_qs ?>" class="btn btn-sm <?= $status_filter ===
"open"
    ? "btn-primary"
    : "btn-ghost" ?>">Open</a>
            <a href="/bidboard/admin/tasks.php?status=in_progress<?= $base_qs ?>" class="btn btn-sm <?= $status_filter ===
"in_progress"
    ? "btn-primary"
    : "btn-ghost" ?>">In Progress</a>
            <a href="/bidboard/admin/tasks.php?status=completed<?= $base_qs ?>" class="btn btn-sm <?= $status_filter ===
"completed"
    ? "btn-primary"
    : "btn-ghost" ?>">Completed</a>
        </div>

        <div class="card">
            <?php if (empty($tasks)): ?>
                <div class="empty-state"><h3>No tasks found</h3></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Client</th>
                                <th>Category</th>
                                <th>Budget</th>
                                <th>Deadline</th>
                                <th>Bids</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task):

                                $badges = [
                                    "open" => ["badge-open", "Open"],
                                    "in_progress" => [
                                        "badge-progress",
                                        "In Progress",
                                    ],
                                    "completed" => ["badge-done", "Completed"],
                                ];
                                [$bc, $bl] = $badges[$task["status"]] ?? [
                                    "badge-pending",
                                    $task["status"],
                                ];
                                ?>
                                <tr>
                                    <td class="text-sm text-muted"><?= $task[
                                        "id"
                                    ] ?></td>
                                    <td>
                                        <a href="/bidboard/task.php?id=<?= $task[
                                            "id"
                                        ] ?>" style="color:var(--accent); text-decoration:none; font-weight:600;">
                                            <?= htmlspecialchars(
                                                $task["title"],
                                            ) ?>
                                        </a>
                                    </td>
                                    <td class="text-sm"><?= htmlspecialchars(
                                        $task["client_name"],
                                    ) ?></td>
                                    <td><span class="badge badge-category"><?= htmlspecialchars(
                                        $task["category"],
                                    ) ?></span></td>
                                    <td class="text-sm" style="color:var(--success);">Rs. <?= number_format(
                                        $task["budget"],
                                        2,
                                    ) ?></td>
                                    <td class="text-sm"><?= date(
                                        "M j, Y",
                                        strtotime($task["deadline"]),
                                    ) ?></td>
                                    <td class="text-sm"><?= $task[
                                        "bid_count"
                                    ] ?></td>
                                    <td><span class="badge <?= $bc ?>"><?= $bl ?></span></td>
                                    <td>
                                        <form method="POST" action="" onsubmit="return confirm('Delete task #<?= $task[
                                            "id"
                                        ] ?> and all its bids?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                generate_csrf_token(),
                                            ) ?>">
                                            <input type="hidden" name="delete_task_id" value="<?= $task[
                                                "id"
                                            ] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php
                            endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding: 1rem; border-top: 1px solid var(--border); flex-wrap:wrap; gap:1rem;">
                        <div class="text-sm text-muted">Page <?= $page ?> of <?= $total_pages ?></div>
                        <div style="display:flex; gap:0.25rem;">
                            <?php
                            $query_params_array = [];
                            if ($status_filter) {
                                $query_params_array["status"] = $status_filter;
                            }
                            if ($search) {
                                $query_params_array["search"] = $search;
                            }
                            ?>

                            <?php if ($page > 1): ?>
                                <?php $query_params_array["page"] =
                                    $page - 1; ?>
                                <a href="?<?= http_build_query(
                                    $query_params_array,
                                ) ?>" class="btn btn-sm btn-ghost">&laquo; Prev</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php $query_params_array["page"] = $i; ?>
                                <a href="?<?= http_build_query(
                                    $query_params_array,
                                ) ?>" class="btn btn-sm <?= $i === $page
    ? "btn-primary"
    : "btn-ghost" ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($total_pages > $page): ?>
                                <?php $query_params_array["page"] =
                                    $page + 1; ?>
                                <a href="?<?= http_build_query(
                                    $query_params_array,
                                ) ?>" class="btn btn-sm btn-ghost">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
