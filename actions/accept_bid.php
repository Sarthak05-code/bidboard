<?php
// Action: accept a bid
// When a bid is accepted:
//   - that bid's status → 'accepted'
//   - all other bids on same task → 'rejected'
//   - task status → 'in_progress'

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
$check = $conn->prepare(
    "SELECT id FROM tasks WHERE id = ? AND client_id = ? AND status = 'open'",
);
$check->bind_param("ii", $task_id, $client_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    // Task not found, not owned by this client, or already closed
    $check->close();
    header("Location: /bidboard/client/dashboard.php");
    exit();
}
$check->close();

$verify = $conn->prepare(
    "SELECT id
     FROM bids
     WHERE id = ?
       AND task_id = ?
       AND status = 'pending'",
);

$verify->bind_param("ii", $bid_id, $task_id);

$verify->execute();

$verify->store_result();

if ($verify->num_rows === 0) {
    $verify->close();

    $_SESSION["flash"] = "That bid is no longer available";
    header("Location: /bidboard/client/task_bids.php?id=" . $task_id);
    exit();
}
$verify->close();

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
$bid_info = $conn->prepare(
    "SELECT freelancer_name, freelancer_email FROM bids WHERE id = ?",
);
$bid_info->bind_param("i", $bid_id);
$bid_info->execute();
$bid_data = $bid_info->get_result()->fetch_assoc();
$bid_info->close();

$task_info = $conn->prepare("SELECT title FROM tasks WHERE id = ?");
$task_info->bind_param("i", $task_id);
$task_info->execute();
$task_data = $task_info->get_result()->fetch_assoc();
$task_info->close();

if ($bid_data && $task_data) {
    send_bid_notification(
        $bid_data["freelancer_email"],
        $bid_data["freelancer_name"],
        $task_data["title"],
        "accepted",
    );
}

// Flash success message for the bids page
$_SESSION["flash"] = "Bid accepted. Task is now in progress.";

header("Location: /bidboard/client/task_bids.php?id=" . $task_id);
exit();
?>
