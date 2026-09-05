<?php
// Admin dashboard — platform-wide stats

require_once "../includes/auth_admin.php";
require_once "../includes/db.php";

// Platform-wide counts
$stats = $conn
    ->query(
        "
    SELECT
        (SELECT COUNT(*) FROM tasks)                          AS total_tasks,
        (SELECT COUNT(*) FROM tasks WHERE status = 'open')   AS open_tasks,
        (SELECT COUNT(*) FROM tasks WHERE status = 'in_progress') AS progress_tasks,
        (SELECT COUNT(*) FROM tasks WHERE status = 'completed')   AS done_tasks,
        (SELECT COUNT(*) FROM bids)                          AS total_bids,
        (SELECT COUNT(*) FROM bids WHERE status = 'pending') AS pending_bids,
        (SELECT COUNT(*) FROM clients)                       AS total_clients,
        (SELECT COUNT(*) FROM clients WHERE is_active = 1)   AS active_clients,
        (SELECT COUNT(*) FROM reports WHERE status = 'pending') AS pending_reports
",
    )
    ->fetch_assoc();

// Most recent 10 tasks across all clients
$recent_tasks = $conn
    ->query(
        "
    SELECT t.*, c.name AS client_name,
           (SELECT COUNT(*) FROM bids b WHERE b.task_id = t.id) AS bid_count
    FROM tasks t
    JOIN clients c ON t.client_id = c.id
    ORDER BY t.created_at DESC
    LIMIT 10
",
    )
    ->fetch_all(MYSQLI_ASSOC);

$page_title = "Admin Dashboard";
$nav_context = "admin";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container">

        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p>Platform-wide overview</p>
        </div>

        <!-- Stats grid -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Total tasks</div>
                <div class="stat-value"><?= $stats["total_tasks"] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Open tasks</div>
                <div class="stat-value" style="color:var(--accent);"><?= $stats[
                    "open_tasks"
                ] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In Progress</div>
                <div class="stat-value" style="color:var(--warning);"><?= $stats[
                    "progress_tasks"
                ] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value" style="color:var(--success);"><?= $stats[
                    "done_tasks"
                ] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total bids</div>
                <div class="stat-value"><?= $stats["total_bids"] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Clients</div>
                <div class="stat-value"><?= $stats["total_clients"] ?></div>
            </div>
        </div>

        <!-- Recent tasks table -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <span>Recent tasks</span>
                <a href="/bidboard/admin/tasks.php" class="btn btn-ghost btn-sm">View all</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Client</th>
                            <th>Category</th>
                            <th>Budget</th>
                            <th>Bids</th>
                            <th>Status</th>
                            <th>Posted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_tasks as $task):

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
                                <td>
                                    <a href="/bidboard/task.php?id=<?= $task[
                                        "id"
                                    ] ?>"
                                       style="color:var(--accent); text-decoration:none; font-weight:600;">
                                        <?= htmlspecialchars($task["title"]) ?>
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
                                <td class="text-sm"><?= $task[
                                    "bid_count"
                                ] ?></td>
                                <td><span class="badge <?= $bc ?>"><?= $bl ?></span></td>
                                <td class="text-sm text-muted"><?= date(
                                    "M j, Y",
                                    strtotime($task["created_at"]),
                                ) ?></td>
                            </tr>
                        <?php
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
