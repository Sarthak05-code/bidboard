<?php
// Admin: view and manage all registered clients

require_once "../includes/auth_admin.php";
require_once "../includes/db.php";
require_once "../includes/mailer.php";

$flash = $_SESSION["flash"] ?? "";
unset($_SESSION["flash"]);

// Handle toggle active/inactive for a client
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["toggle_client_id"])
) {
    if (!verify_csrf_token($_POST["csrf_token"] ?? "")) {
        die("Invalid CSRF token.");
    }
    $cid = (int) $_POST["toggle_client_id"];
    $new_val = (int) $_POST["new_active"]; // 1 or 0

    // Don't let admin deactivate and then immediately crash — just update
    $stmt = $conn->prepare("UPDATE clients SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_val, $cid);
    $stmt->execute();
    $stmt->close();

    $client_stmt = $conn->prepare(
        "SELECT name, email FROM clients WHERE id = ?",
    );
    $client_stmt->bind_param("i", $cid);
    $client_stmt->execute();
    $client_data = $client_stmt->get_result()->fetch_assoc();
    $client_stmt->close();

    if ($client_data) {
        send_account_status_notification(
            $client_data["email"],
            $client_data["name"],
            $new_val === 1,
        );
    }

    $_SESSION["flash"] = $new_val
        ? "Client reactivated."
        : "Client deactivated.";
    header("Location: /bidboard/admin/clients.php");
    exit();
}

// Fetch all clients with their task + bid counts
$clients = $conn
    ->query(
        "
    SELECT c.*,
           COUNT(DISTINCT t.id)  AS task_count,
           COUNT(DISTINCT b.id)  AS bid_count_received
    FROM clients c
    LEFT JOIN tasks t ON t.client_id = c.id
    LEFT JOIN bids  b ON b.task_id   = t.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
",
    )
    ->fetch_all(MYSQLI_ASSOC);

$page_title = "Clients";
$nav_context = "admin";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container">

        <div class="page-header">
            <h1>Clients</h1>
            <p><?= count($clients) ?> registered client<?= count($clients) != 1
     ? "s"
     : "" ?></p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-success"><?= htmlspecialchars(
                $flash,
            ) ?></div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($clients)): ?>
                <div class="empty-state"><h3>No clients registered yet</h3></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Tasks posted</th>
                                <th>Bids received</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td class="text-sm text-muted"><?= $client[
                                        "id"
                                    ] ?></td>
                                    <td class="font-bold"><?= htmlspecialchars(
                                        $client["name"],
                                    ) ?></td>
                                    <td class="text-sm text-muted"><?= htmlspecialchars(
                                        $client["email"],
                                    ) ?></td>
                                    <td class="text-sm"><?= $client[
                                        "task_count"
                                    ] ?></td>
                                    <td class="text-sm"><?= $client[
                                        "bid_count_received"
                                    ] ?></td>
                                    <td class="text-sm text-muted" title="<?= htmlspecialchars(
                                        date(
                                            "M j, Y",
                                            strtotime($client["created_at"]),
                                        ),
                                    ) ?>">
                                        <?= htmlspecialchars(
                                            format_relative_time(
                                                $client["created_at"],
                                            ),
                                        ) ?>
                                    </td>
                                    <td>
                                        <?php if ($client["is_active"]): ?>
                                            <span class="badge badge-accepted">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Toggle active/inactive -->
                                        <form method="POST" action=""
                                              onsubmit="return confirm('<?= $client[
                                                  "is_active"
                                              ]
                                                  ? "Deactivate"
                                                  : "Reactivate" ?> this client?')">

                                            <input type="hidden" name="toggle_client_id" value="<?= $client[
                                                "id"
                                            ] ?>">

                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                $_SESSION["csrf_token"],
                                            ) ?>">

                                            <input type="hidden" name="new_active" value="<?= $client[
                                                "is_active"
                                            ]
                                                ? 0
                                                : 1 ?>">

                                            <button type="submit"
                                                    class="btn btn-sm <?= $client[
                                                        "is_active"
                                                    ]
                                                        ? "btn-danger"
                                                        : "btn-success" ?>">
                                                <?= $client["is_active"]
                                                    ? "Deactivate"
                                                    : "Reactivate" ?>
                                            </button>
                                        </form>
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
