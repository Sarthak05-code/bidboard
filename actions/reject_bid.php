<?php
// Action: reject a single bid

session_name("bidboard_client");
session_start();

if (!isset($_SESSION["client_id"])) {
    header("Location: /bidboard/auth/client_login.php");
    exit();
}

require_once "../includes/db.php";
require_once "../includes/mailer.php";
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

// Verify task belongs to this client
$check = $conn->prepare(
    "SELECT id FROM tasks WHERE id = ? AND client_id = ? AND status = 'open'",
);
$check->bind_param("ii", $task_id, $client_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
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

    $_SESSION["flash"] = "That bid is no longer available.";

    header("Location: /bidboard/client/task_bids.php?id=" . $task_id);
    exit();
}

$verify->close();

// Update only that bid's status to rejected
$stmt = $conn->prepare(
    "UPDATE bids SET status = 'rejected' WHERE id = ? AND task_id = ?",
);
$stmt->bind_param("ii", $bid_id, $task_id);
$stmt->execute();
$stmt->close();

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
        "rejected",
    );
}

$_SESSION["flash"] = "Bid rejected.";

header("Location: /bidboard/client/task_bids.php?id=" . $task_id);
exit();
