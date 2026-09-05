<?php
// Admin: view all bids across the entire platform

require_once "../includes/auth_admin.php";
require_once "../includes/db.php";

$flash = $_SESSION["flash"] ?? "";
unset($_SESSION["flash"]);

// Handle single bid delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_bid_id"])) {
    $del_id = (int) $_POST["delete_bid_id"];
    $stmt = $conn->prepare("DELETE FROM bids WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION["flash"] = "Bid deleted.";
    header("Location: /bidboard/admin/bids.php");
    exit();
}

// Filter by bid status and keyword search
$status_filter = trim($_GET["status"] ?? "");
$search = trim($_GET["search"] ?? ""); // keyword search
$allowed = ["pending", "accepted", "rejected"];

$sql = "SELECT b.*, t.title AS task_title, t.id AS task_id, c.name AS client_name
           FROM bids b
           JOIN tasks t   ON b.task_id    = t.id
           JOIN clients c ON t.client_id  = c.id
           WHERE 1=1"; // allows safe AND chaining
$params = [];
$types = "";

// Status filter
if (in_array($status_filter, $allowed)) {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Keyword search: freelancer name, email, or task title
if ($search !== "") {
    $like = "%" . $search . "%";
    $sql .=
        " AND (b.freelancer_name LIKE ? OR b.freelancer_email LIKE ? OR t.title LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$sql .= " ORDER BY b.submitted_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = "All Bids";
$nav_context = "admin";
require_once "../includes/header.php";
?>

<div class="page-wrap">
    <div class="container">

        <div class="page-header">
            <h1>All Bids</h1>
            <p>Every bid submitted across the platform</p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-success"><?= htmlspecialchars(
                $flash,
            ) ?></div>
        <?php endif; ?>

        <!-- Search bar — freelancer name, email, or task title -->
        <form method="GET" action="" style="display:flex; gap:0.75rem; margin-bottom:1rem; flex-wrap:wrap;">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars(
                    $status_filter,
                ) ?>">
            <?php endif; ?>
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search by freelancer name, email, or task title..."
                value="<?= htmlspecialchars($search) ?>"
                style="flex:1; min-width:220px;"
            >
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="/bidboard/admin/bids.php<?= $status_filter
                    ? "?status=" . urlencode($status_filter)
                    : "" ?>"
                   class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Status filter tabs — preserve search term when switching -->
        <div style="display:flex; gap:0.4rem; margin-bottom:1.25rem; flex-wrap:wrap;">
            <?php $sq = $search ? "&search=" . urlencode($search) : ""; ?>
            <a href="/bidboard/admin/bids.php<?= $search
                ? "?search=" . urlencode($search)
                : "" ?>"
               class="btn btn-sm <?= $status_filter === ""
                   ? "btn-primary"
                   : "btn-ghost" ?>">All</a>
            <a href="/bidboard/admin/bids.php?status=pending<?= $sq ?>"
               class="btn btn-sm <?= $status_filter === "pending"
                   ? "btn-primary"
                   : "btn-ghost" ?>">Pending</a>
            <a href="/bidboard/admin/bids.php?status=accepted<?= $sq ?>"
               class="btn btn-sm <?= $status_filter === "accepted"
                   ? "btn-primary"
                   : "btn-ghost" ?>">Accepted</a>
            <a href="/bidboard/admin/bids.php?status=rejected<?= $sq ?>"
               class="btn btn-sm <?= $status_filter === "rejected"
                   ? "btn-primary"
                   : "btn-ghost" ?>">Rejected</a>
        </div>

        <div class="card">
            <?php if (empty($bids)): ?>
                <div class="empty-state"><h3>No bids found</h3></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Freelancer</th>
                                <th>Email</th>
                                <th>Task</th>
                                <th>Client</th>
                                <th>Bid ($)</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bids as $bid):

                                $bid_badges = [
                                    "pending" => "badge-pending",
                                    "accepted" => "badge-accepted",
                                    "rejected" => "badge-rejected",
                                ];
                                $bc =
                                    $bid_badges[$bid["status"]] ??
                                    "badge-pending";
                                ?>
                                <tr>
                                    <td class="text-sm text-muted"><?= $bid[
                                        "id"
                                    ] ?></td>
                                    <td class="font-bold text-sm"><?= htmlspecialchars(
                                        $bid["freelancer_name"],
                                    ) ?></td>
                                    <td class="text-sm text-muted"><?= htmlspecialchars(
                                        $bid["freelancer_email"],
                                    ) ?></td>
                                    <td>
                                        <a href="/bidboard/task.php?id=<?= $bid[
                                            "task_id"
                                        ] ?>"
                                           style="color:var(--accent); text-decoration:none; font-size:0.85rem;">
                                            <?= htmlspecialchars(
                                                $bid["task_title"],
                                            ) ?>
                                        </a>
                                    </td>
                                    <td class="text-sm"><?= htmlspecialchars(
                                        $bid["client_name"],
                                    ) ?></td>
                                    <td class="text-sm" style="color:var(--success); font-weight:600;">
                                        Rs. <?= number_format(
                                            $bid["proposed_price"],
                                            2,
                                        ) ?>
                                    </td>
                                    <td><span class="badge <?= $bc ?>"><?= ucfirst(
    $bid["status"],
) ?></span></td>
                                    <td class="text-sm text-muted"><?= date(
                                        "M j, Y",
                                        strtotime($bid["submitted_at"]),
                                    ) ?></td>
                                    <td>
                                        <form method="POST" action=""
                                              onsubmit="return confirm('Delete this bid?')">
                                            <input type="hidden" name="delete_bid_id" value="<?= $bid[
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
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
