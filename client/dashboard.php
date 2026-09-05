<?php
// Client dashboard — overview of their tasks and stats

require_once "../includes/auth_client.php"; // session guard
require_once "../includes/db.php";

$client_id = $_SESSION["client_id"]; // logged-in client's ID

// Count tasks by status for the stat cards
$stats_stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'open')        AS open_count,
        SUM(status = 'in_progress') AS progress_count,
        SUM(status = 'completed')   AS done_count
     FROM tasks WHERE client_id = ?",
);
$stats_stmt->bind_param("i", $client_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Count total bids received on all their tasks
$bids_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total_bids
     FROM bids b
     JOIN tasks t ON b.task_id = t.id
     WHERE t.client_id = ?",
);
$bids_stmt->bind_param("i", $client_id);
$bids_stmt->execute();
$bids_count = $bids_stmt->get_result()->fetch_assoc()["total_bids"];
$bids_stmt->close();

// Fetch all tasks for this client, with bid count per task
$tasks_stmt = $conn->prepare(
    "SELECT t.*,
            (SELECT COUNT(*) FROM bids b WHERE b.task_id = t.id) AS bid_count
     FROM tasks t
     WHERE t.client_id = ?
     ORDER BY t.created_at DESC",
);
$tasks_stmt->bind_param("i", $client_id);
$tasks_stmt->execute();
$tasks = $tasks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tasks_stmt->close();

$page_title = "Dashboard";
$nav_context = "client";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container">

        <!-- Page header with post button -->
        <div class="flex items-center justify-between mb-2">
            <div class="page-header" style="margin-bottom:0;">
                <h1>Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars(
                    $_SESSION["client_name"],
                ) ?></p>
            </div>
            <a href="/bidboard/client/post_task.php" class="btn btn-primary">+ Post a task</a>
        </div>

        <!-- Stat cards row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Total tasks</div>
                <div class="stat-value"><?= $stats["total"] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Open</div>
                <div class="stat-value" style="color:var(--accent);"><?= $stats[
                    "open_count"
                ] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value" style="color:var(--warning);"><?= $stats[
                    "progress_count"
                ] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value" style="color:var(--success);"><?= $stats[
                    "done_count"
                ] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Bids received</div>
                <div class="stat-value"><?= $bids_count ?></div>
            </div>
        </div>

        <!-- Tasks table -->
        <div class="card">
            <div class="card-header">Your tasks</div>
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <h3>No tasks yet</h3>
                    <p>Post your first task to start receiving bids.</p>
                    <a href="/bidboard/client/post_task.php" class="btn btn-primary" style="margin-top:1rem;">
                        Post a task
                    </a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Category</th>
                                <th>Budget</th>
                                <th>Deadline</th>
                                <th>Bids</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td>
                                        <a href="/bidboard/task.php?id=<?= $task[
                                            "id"
                                        ] ?>"
                                            style="color:var(--accent); text-decoration:none; font-weight:600;">
                                            <?= htmlspecialchars(
                                                $task["title"],
                                            ) ?>
                                        </a>
                                    </td>
                                    <td><span class="badge badge-category"><?= htmlspecialchars(
                                        $task["category"],
                                    ) ?></span></td>
                                    <td style="color:var(--success); font-weight:600;">Rs. <?= number_format(
                                        $task["budget"],
                                        2,
                                    ) ?></td>
                                    <td class="text-sm"><?= date(
                                        "M j, Y",
                                        strtotime(
                                            $task["deadline"] . " 12:00:00",
                                        ),
                                    ) ?></td>

                                    <td>
                                        <!-- Link to bids page for this task -->
                                        <a href="/bidboard/client/task_bids.php?id=<?= $task[
                                            "id"
                                        ] ?>"
                                            style="color:var(--accent); text-decoration:none;">
                                            <?= $task[
                                                "bid_count"
                                            ] ?> bid<?= $task["bid_count"] != 1
     ? "s"
     : "" ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                        $badges = [
                                            "open" => ["badge-open", "Open"],
                                            "in_progress" => [
                                                "badge-progress",
                                                "In Progress",
                                            ],
                                            "completed" => [
                                                "badge-done",
                                                "Completed",
                                            ],
                                        ];
                                        [$bc, $bl] = $badges[
                                            $task["status"]
                                        ] ?? ["badge-pending", $task["status"]];
                                        ?>
                                        <span class="badge <?= $bc ?>"><?= $bl ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:0.4rem;">
                                            <!-- View bids -->
                                            <a href="/bidboard/client/task_bids.php?id=<?= $task[
                                                "id"
                                            ] ?>"
                                                class="btn btn-ghost btn-sm">View bids</a>

                                            <!-- Mark completed (only when in_progress) -->
                                            <?php if (
                                                $task["status"] ===
                                                "in_progress"
                                            ): ?>
                                                <form method="POST" action="/bidboard/actions/update_task_status.php" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                        generate_csrf_token(),
                                                    ) ?>">
                                                    <input type="hidden" name="task_id" value="<?= $task[
                                                        "id"
                                                    ] ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <input type="hidden" name="redirect" value="dashboard">
                                                    <button type="submit" class="btn btn-success btn-sm">Mark done</button>
                                                </form>
                                            <?php endif; ?>
                                            <!-- Edit -->
                                            <?php if (
                                                $task["status"] === "open"
                                            ): ?>
                                                <a href="/bidboard/client/edit_task.php?id=<?= $task[
                                                    "id"
                                                ] ?>" class="btn btn-ghost btn-sm">Edit</a>
                                            <?php endif; ?>




                                            <!-- Delete (only when open) -->
                                            <?php if (
                                                $task["status"] === "open"
                                            ): ?>
                                                <form method="POST" action="/bidboard/actions/delete_task.php"
                                                    style="display:inline;"
                                                    onsubmit="return confirm('Delete this task and all its bids?')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                        generate_csrf_token(),
                                                    ) ?>">
                                                    <input type="hidden" name="task_id" value="<?= $task[
                                                        "id"
                                                    ] ?>">
                                                    <input type="hidden" name="redirect" value="dashboard">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
