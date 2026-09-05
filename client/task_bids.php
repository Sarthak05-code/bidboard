<?php
// Client: view all bids on one of their tasks and accept/reject

require_once "../includes/auth_client.php";
require_once "../includes/db.php";

$client_id = $_SESSION["client_id"];
$task_id = (int) ($_GET["id"] ?? 0);

if ($task_id <= 0) {
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

// Fetch the task — verify it belongs to this client
$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND client_id = ?");
$stmt->bind_param("ii", $task_id, $client_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    // Task not found or doesn't belong to this client
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

// Fetch all bids for this task, accepted bid first
$bids_stmt = $conn->prepare(
    "SELECT * FROM bids WHERE task_id = ?
     ORDER BY FIELD(status, 'accepted', 'pending', 'rejected'), proposed_price ASC",
);
$bids_stmt->bind_param("i", $task_id);
$bids_stmt->execute();
$bids = $bids_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$bids_stmt->close();

// Flash message (from accept/reject action)
$flash = $_SESSION["flash"] ?? "";
unset($_SESSION["flash"]); // clear after reading

$status_labels = [
    "open" => "Open",
    "in_progress" => "In Progress",
    "completed" => "Completed",
];
$status_badges = [
    "open" => "badge-open",
    "in_progress" => "badge-progress",
    "completed" => "badge-done",
];

$page_title = "Bids — " . $task["title"];
$nav_context = "client";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container">

        <!-- Back link -->
        <a href="/bidboard/client/dashboard.php" class="text-sm text-muted"
            style="text-decoration:none; display:inline-block; margin-bottom:1rem;">
            &larr; Back to dashboard
        </a>

        <!-- Task summary header -->
        <div class="flex items-center justify-between mb-2" style="flex-wrap:wrap; gap:1rem;">
            <div>
                <div class="flex items-center gap-1">
                    <h1 style="font-size:1.3rem; font-weight:700;"><?= htmlspecialchars(
                        $task["title"],
                    ) ?></h1>
                    <span class="badge <?= $status_badges[$task["status"]] ??
                        "badge-pending" ?>">
                        <?= $status_labels[$task["status"]] ??
                            $task["status"] ?>
                    </span>
                </div>
                <p class="text-sm text-muted mt-1">
                    Budget: Rs. <?= number_format(
                        $task["budget"],
                        2,
                    ) ?> &nbsp;&middot;&nbsp;
                    Deadline: <?= date(
                        "M j, Y",
                        strtotime($task["deadline"]),
                    ) ?> &nbsp;&middot;&nbsp;
                    <?= count($bids) ?> bid<?= count($bids) != 1 ? "s" : "" ?>
                </p>
            </div>

            <!-- Mark completed button (only when in_progress) -->
            <?php if ($task["status"] === "in_progress"): ?>
                <form method="POST" action="/bidboard/actions/update_task_status.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                        generate_csrf_token(),
                    ) ?>">
                    <input type="hidden" name="task_id" value="<?= $task[
                        "id"
                    ] ?>">
                    <input type="hidden" name="status" value="completed">
                    <input type="hidden" name="redirect" value="bids">
                    <button type="submit" class="btn btn-success">Mark as completed</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Flash message -->
        <?php if ($flash): ?>
            <div class="alert alert-success"><?= htmlspecialchars(
                $flash,
            ) ?></div>
        <?php endif; ?>

        <!-- Notice when task is no longer open -->
        <?php if ($task["status"] !== "open"): ?>
            <div class="alert alert-info">
                This task is <strong><?= $status_labels[
                    $task["status"]
                ] ?></strong> — bidding is closed.
            </div>
        <?php endif; ?>

        <!-- Bid list -->
        <?php if (empty($bids)): ?>
            <div class="empty-state">
                <h3>No bids yet</h3>
                <p>Share your task link so freelancers can find and bid on it.</p>
                <p class="text-sm text-muted" style="margin-top:0.5rem;">
                    Public link:
                    <a href="/bidboard/task.php?id=<?= $task[
                        "id"
                    ] ?>" style="color:var(--accent);">
                        /bidboard/task.php?id=<?= $task["id"] ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($bids as $bid): ?>
                <!-- Highlight accepted bid -->
                <div class="bid-row <?= $bid["status"] === "accepted"
                    ? "accepted"
                    : "" ?>">
                    <div style="flex:1;">
                        <!-- Freelancer name + email -->
                        <div class="flex items-center gap-1" style="flex-wrap:wrap;">
                            <span class="font-bold"><?= htmlspecialchars(
                                $bid["freelancer_name"],
                            ) ?></span>
                            <span class="text-sm text-muted">&lt;<?= htmlspecialchars(
                                $bid["freelancer_email"],
                            ) ?>&gt;</span>

                            <!-- Bid status badge -->
                            <?php $bid_badges = [
                                "pending" => "badge-pending",
                                "accepted" => "badge-accepted",
                                "rejected" => "badge-rejected",
                            ]; ?>
                            <span class="badge <?= $bid_badges[
                                $bid["status"]
                            ] ?? "badge-pending" ?>">
                                <?= ucfirst($bid["status"]) ?>
                            </span>
                        </div>

                        <!-- Bid price + date -->
                        <div class="bid-meta" style="margin-top:0.3rem;">
                            Bid: <strong style="color:var(--success);">Rs. <?= number_format(
                                $bid["proposed_price"],
                                2,
                            ) ?></strong>
                            &nbsp;&middot;&nbsp;
                            <?= date(
                                "M j, Y",
                                strtotime($bid["submitted_at"]),
                            ) ?>
                        </div>

                        <!-- Pitch text -->
                        <p style="margin-top:0.5rem; font-size:0.9rem; line-height:1.6;">
                            <?= nl2br(htmlspecialchars($bid["pitch"])) ?>
                        </p>
                        <div style="margin-top:0.5rem;">
                            <a href="/bidboard/report.php?type=bid&id=<?= $bid[
                                "id"
                            ] ?>&task=<?= $task_id ?>" class="text-sm" style="color:var(--danger); text-decoration:none;">⚠️ Report this bid</a>
                        </div>
                    </div>

                    <!-- Action buttons — only shown when task is open and bid is pending -->
                    <?php if (
                        $task["status"] === "open" &&
                        $bid["status"] === "pending"
                    ): ?>
                        <div style="display:flex; flex-direction:column; gap:0.4rem; flex-shrink:0;">
                            <!-- Accept bid -->
                            <form method="POST" action="/bidboard/actions/accept_bid.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                    generate_csrf_token(),
                                ) ?>">
                                <input type="hidden" name="bid_id" value="<?= $bid[
                                    "id"
                                ] ?>">
                                <input type="hidden" name="task_id" value="<?= $task[
                                    "id"
                                ] ?>">
                                <button type="submit" class="btn btn-success btn-sm" style="width:100%;">Accept</button>
                            </form>

                            <!-- Reject bid -->
                            <form method="POST" action="/bidboard/actions/reject_bid.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                    generate_csrf_token(),
                                ) ?>">
                                <input type="hidden" name="bid_id" value="<?= $bid[
                                    "id"
                                ] ?>">
                                <input type="hidden" name="task_id" value="<?= $task[
                                    "id"
                                ] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" style="width:100%;">Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>


<?php require_once "../includes/footer.php"; ?>
