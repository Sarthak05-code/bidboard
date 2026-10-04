<?php
// Action: accept a bid
// When a bid is accepted:
//   - that bid's status → 'accepted'
//   - all other pending bids on same task → 'rejected'
//   - task status → 'in_progress'
//   - accepted freelancer gets "accepted" email, all others get "rejected" email

session_name("bidboard_client");
session_start();

// Must be logged in as client
if (!isset($_SESSION["client_id"])) {
    header("Location: /bidboard/auth/client_login.php");
    exit();
}

require_once "../includes/db.php";
require_once "../includes/mailer.php";

// Verify CSRF token before processing
if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
    die("Invalid CSRF token. Please go back and try again.");
}

$client_id = $_SESSION["client_id"];
$bid_id = (int) ($_POST["bid_id"] ?? 0);
$task_id = (int) ($_POST["task_id"] ?? 0);

if ($bid_id <= 0 || $task_id <= 0) {
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

// Verify the task belongs to this client and is still open
// (also fetches the title, which the emails need)
$check = $conn->prepare(
    "SELECT id, title FROM tasks WHERE id = ? AND client_id = ? AND status = 'open'",
);
$check->bind_param("ii", $task_id, $client_id);
$check->execute();
$task_data = $check->get_result()->fetch_assoc();
$check->close();

if (!$task_data) {
    // Task not found, not owned by this client, or already closed
    header("Location: /bidboard/client/dashboard.php");
    exit();
}

// Verify the chosen bid is a pending bid on this task
// (also fetches the winner's name and email for the email)
$verify = $conn->prepare(
    "SELECT id, freelancer_name, freelancer_email
     FROM bids
     WHERE id = ?
       AND task_id = ?
       AND status = 'pending'",
);
$verify->bind_param("ii", $bid_id, $task_id);
$verify->execute();
$bid_data = $verify->get_result()->fetch_assoc();
$verify->close();

if (!$bid_data) {
    $_SESSION["flash"] = "That bid is no longer available";
    header("Location: /bidboard/client/task_bids.php?id=" . $task_id);
    exit();
}

// Fetch the OTHER pending bids NOW, before their status changes to 'rejected'
$others_stmt = $conn->prepare(
    "SELECT id, freelancer_name, freelancer_email
     FROM bids
     WHERE task_id = ? AND id != ? AND status = 'pending'",
);
$others_stmt->bind_param("ii", $task_id, $bid_id);
$others_stmt->execute();
$other_bids = $others_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$others_stmt->close();

// Mark the accepted bid as 'accepted'
$accept = $conn->prepare(
    "UPDATE bids SET status = 'accepted' WHERE id = ? AND task_id = ?",
);
$accept->bind_param("ii", $bid_id, $task_id);
$accept->execute();
$accept->close();

// Reject all other pending bids on this task
$reject = $conn->prepare(
    "UPDATE bids SET status = 'rejected' WHERE task_id = ? AND id != ? AND status = 'pending'",
);
$reject->bind_param("ii", $task_id, $bid_id);
$reject->execute();
$reject->close();

// Move task to in_progress
$progress = $conn->prepare(
    "UPDATE tasks SET status = 'in_progress' WHERE id = ?",
);
$progress->bind_param("i", $task_id);
$progress->execute();
$progress->close();

// Notify the accepted freelancer
$sent = send_bid_notification(
    $bid_data["freelancer_email"],
    $bid_data["freelancer_name"],
    $task_data["title"],
    "accepted",
);
error_log("Notify accepted bid {$bid_id}: " . ($sent ? "sent" : "FAILED"));

// Notify every other freelancer who was rejected
foreach ($other_bids as $b) {
    $sent = send_bid_notification(
        $b["freelancer_email"],
        $b["freelancer_name"],
        $task_data["title"],
        "rejected",
    );
    error_log("Notify rejected bid {$b["id"]}: " . ($sent ? "sent" : "FAILED"));
}

// Flash success message for the bids page
$_SESSION["flash"] =
    "Bid accepted. Task is now in progress. All freelancers have been notified.";

header("Location: /bidboard/client/task_bids.php?id=" . $task_id);
exit();
?>
