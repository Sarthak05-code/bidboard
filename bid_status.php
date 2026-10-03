<?php
// Public page — freelancer enters their email to check all their bids
// No account needed, just email lookup

require_once "includes/db.php";

// Switched to GET so freelancers can bookmark their history page
$email = trim($_GET["email"] ?? "");
$bids = [];
$searched = false;

if ($email !== "") {
    $searched = true;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } else {
        // Fetch all bids by this email with task details
        $stmt = $conn->prepare(
            "SELECT b.*, t.title AS task_title, t.id AS task_id,
                    t.budget AS task_budget, t.status AS task_status,
                    t.deadline AS task_deadline, c.name AS client_name
             FROM bids b
             JOIN tasks t   ON b.task_id    = t.id
             JOIN clients c ON t.client_id  = c.id
             WHERE b.freelancer_email = ?
             ORDER BY b.submitted_at DESC",
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Calculate summary metrics if bids are found
$total_bids = count($bids);
$accepted_bids = 0;
$total_value = 0.0;

foreach ($bids as $b) {
    if ($b["status"] === "accepted") {
        $accepted_bids++;
        $total_value += (float) $b["proposed_price"];
    }
}

$page_title = "My Bid History";
$nav_context = "public";
require_once "includes/header.php";
?>

<div class="page-wrap">
    <div class="container container-narrow">

        <div class="page-header">
            <h1>Check your bids</h1>
            <p>Enter the email you used when bidding to see all your submissions.</p>
        </div>

        <!-- Email lookup form (GET method allows bookmarking search results) -->
        <form method="GET" action="" class="email-search-form">
            <input
                type="email"
                name="email"
                class="form-control email-search-input"
                placeholder="you@example.com"
                value="<?= htmlspecialchars($email) ?>"
                required>
            <button type="submit" class="btn btn-primary">Check bids</button>
        </form>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>

        <?php
            // Map bid status to badge + message

            // Map task status to badge
            // Map bid status to badge + message
            // Map task status to badge
            elseif ($searched && empty($bids)): ?>
            <div class="empty-state">
                <h3>No bids found</h3>
                <p>No bids were submitted with <strong><?= htmlspecialchars(
                    $email,
                ) ?></strong>.</p>
                <p class="text-sm text-muted mt-1">
                    Double check your email address or <a href="/bidboard/index.php" class="text-accent">browse open tasks</a> to start bidding.
                </p>
            </div>

        <?php elseif (!empty($bids)): ?>

            <!-- Summary Stats Bar -->
            <div class="bid-stats-bar">
                <div class="bid-stat-item">
                    <span class="text-sm text-muted">Total Bids</span>
                    <span class="bid-stat-val"><?= $total_bids ?></span>
                </div>
                <div class="bid-stat-item">
                    <span class="text-sm text-muted">Accepted</span>
                    <span class="bid-stat-val text-success"><?= $accepted_bids ?></span>
                </div>
                <div class="bid-stat-item">
                    <span class="text-sm text-muted">Accepted Value</span>
                    <span class="bid-stat-val text-success">Rs. <?= number_format(
                        $total_value,
                        2,
                    ) ?></span>
                </div>
            </div>

            <?php foreach ($bids as $bid):

                $bid_badge = [
                    "pending" => [
                        "badge-pending",
                        "Pending",
                        "The client has not reviewed your bid yet.",
                    ],
                    "accepted" => [
                        "badge-accepted",
                        "Accepted",
                        "Congratulations! The client accepted your bid.",
                    ],
                    "rejected" => [
                        "badge-rejected",
                        "Rejected",
                        "The client went with a different bid.",
                    ],
                ];
                [$bc, $bl, $msg] = $bid_badge[$bid["status"]] ?? [
                    "badge-pending",
                    "Pending",
                    "",
                ];

                $task_badges = [
                    "open" => ["badge-open", "Open"],
                    "in_progress" => ["badge-progress", "In Progress"],
                    "completed" => ["badge-done", "Completed"],
                ];
                [$tbc, $tbl] = $task_badges[$bid["task_status"]] ?? [
                    "badge-pending",
                    $bid["task_status"],
                ];
                ?>
                <div class="card mb-2">
                    <div class="card-body">

                        <!-- Task title + status -->
                        <div class="flex items-center gap-1 flex-wrap mb-1">
                            <a href="/bidboard/task.php?id=<?= $bid[
                                "task_id"
                            ] ?>" class="bid-title-link">
                                <?= htmlspecialchars($bid["task_title"]) ?>
                            </a>
                            <span class="badge <?= $tbc ?>"><?= $tbl ?></span>
                        </div>

                        <!-- Client + deadline + budget -->
                        <p class="text-sm text-muted mb-2">
                            Posted by <strong><?= htmlspecialchars(
                                $bid["client_name"],
                            ) ?></strong>
                            &nbsp;&middot;&nbsp;
                            Deadline: <?= date(
                                "M j, Y",
                                strtotime($bid["task_deadline"]),
                            ) ?>
                            &nbsp;&middot;&nbsp;
                            Task budget: Rs. <?= number_format(
                                $bid["task_budget"],
                                2,
                            ) ?>
                        </p>

                        <!-- Divider -->
                        <div class="card-divider"></div>

                        <!-- Bid details + status -->
                        <div class="flex items-center justify-between flex-wrap gap-1">
                            <div>
                                <div class="flex items-center gap-1 mb-1">
                                    <span class="text-sm font-bold">Your bid:</span>
                                    <span class="text-success font-bold">
                                        Rs. <?= number_format(
                                            $bid["proposed_price"],
                                            2,
                                        ) ?>
                                    </span>
                                    <span class="badge <?= $bc ?>"><?= $bl ?></span>
                                </div>
                                <!-- Status message -->
                                <p class="text-sm text-muted"><?= $msg ?></p>
                            </div>
                            <span class="text-sm text-muted">
                                Submitted <?= date(
                                    "M j, Y",
                                    strtotime($bid["submitted_at"]),
                                ) ?>
                            </span>
                        </div>

                        <!-- Show pitch -->
                        <div class="pitch-box">
                            <span class="font-bold text-dark">Your pitch: </span>
                            <?= nl2br(htmlspecialchars($bid["pitch"])) ?>
                        </div>

                    </div>
                </div>
            <?php
            endforeach; ?>

        <?php endif; ?>

    </div>
</div>

<?php require_once "includes/footer.php"; ?>
