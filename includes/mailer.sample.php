<?php
// actions/accept_bid.php — Accepts a bid, auto-rejects all other pending bids, and notifies everyone

require_once "../includes/auth_client.php";
require_once "../includes/db.php";
require_once "../includes/mailer.php";

$client_id = $_SESSION["client_id"];
$bid_id = (int) ($_POST["bid_id"] ?? 0);
$task_id = (int) ($_POST["task_id"] ?? 0);

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !verify_csrf_token($_POST["csrf_token"] ?? "")
) {
    die("Invalid request or CSRF token mismatch.");
}

// 1. Verify task belongs to this logged-in client and is open
$stmt = $conn->prepare(
    "SELECT id, title FROM tasks WHERE id = ? AND client_id = ? AND status = 'open'",
);
$stmt->bind_param("ii", $task_id, $client_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    header(
        "Location: /bidboard/client/dashboard.php?error=" .
            urlencode("Task not found or already closed."),
    );
    exit();
}

// 2. Fetch ALL pending bids on this task before changing statuses
$stmt = $conn->prepare(
    "SELECT id, freelancer_name, freelancer_email FROM bids WHERE task_id = ? AND status = 'pending'",
);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$all_bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($all_bids)) {
    header(
        "Location: /bidboard/client/task_bids.php?id=" .
            $task_id .
            "&error=" .
            urlencode("No pending bids found."),
    );
    exit();
}
// The chosen bid must be one of the pending bids on this task
$pending_ids = array_map(fn($b) => (int) $b["id"], $all_bids);
if (!in_array($bid_id, $pending_ids, true)) {
    header(
        "Location: /bidboard/client/task_bids.php?id=" .
            $task_id .
            "&error=" .
            urlencode("That bid is no longer available."),
    );
    exit();
}

// 3. Mark selected bid as 'accepted'
$stmt = $conn->prepare(
    "UPDATE bids SET status = 'accepted' WHERE id = ? AND task_id = ?",
);
$stmt->bind_param("ii", $bid_id, $task_id);
$stmt->execute();
$stmt->close();

// 4. Mark all other pending bids on this task as 'rejected'
$stmt = $conn->prepare(
    "UPDATE bids SET status = 'rejected' WHERE task_id = ? AND id != ? AND status = 'pending'",
);
$stmt->bind_param("ii", $task_id, $bid_id);
$stmt->execute();
$stmt->close();

// 5. Update task status to 'in_progress'
$stmt = $conn->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$stmt->close();

// 6. Notify EVERY freelancer who submitted a bid on this task
foreach ($all_bids as $b) {
    $is_accepted = (int) $b["id"] === $bid_id;
    $status_text = $is_accepted ? "accepted" : "rejected";

    // Sends "accepted" email to winner, "rejected" email to all others
    send_bid_notification(
        $b["freelancer_email"],
        $b["freelancer_name"],
        $task["title"],
        $status_text,
    );
}

header(
    "Location: /bidboard/client/task_bids.php?id=" .
        $task_id .
        "&msg=" .
        urlencode("Bid accepted! All freelancers have been notified."),
);
exit();
