<?php
// export_pdf.php — Writes JSON payload to temp file, executes Rust binary, streams PDF back

require_once "includes/db.php";

$email = trim($_GET["email"] ?? "");

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid or missing email address.");
}

// 1. Fetch bid history for this email
$stmt = $conn->prepare(
    "SELECT b.proposed_price, b.status, b.submitted_at,
            t.title AS task_title, c.name AS client_name
     FROM bids b
     JOIN tasks t ON b.task_id = t.id
     JOIN clients c ON t.client_id = c.id
     WHERE b.freelancer_email = ?
     ORDER BY b.submitted_at DESC",
);
$stmt->bind_param("s", $email);
$stmt->execute();
$bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($bids)) {
    die("No bids found for this email address.");
}

// 2. Prepare temp directory
$output_dir = str_replace("\\", "/", __DIR__ . "/uploads/temp/");
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0777, true);
}

$file_hash = md5($email . time());
$pdf_path = $output_dir . "bid_history_" . $file_hash . ".pdf";
$json_path = $output_dir . "payload_" . $file_hash . ".json";

// 3. Format payload and write to temporary JSON file
$payload = [
    "email" => $email,
    "output_path" => $pdf_path,
    "bids" => array_map(function ($b) {
        return [
            "task_title" => $b["task_title"],
            "client_name" => $b["client_name"],
            "proposed_price" => number_format($b["proposed_price"], 2),
            "status" => $b["status"],
            "submitted_at" => date("Y-m-d", strtotime($b["submitted_at"])),
        ];
    }, $bids),
];

file_put_contents($json_path, json_encode($payload, JSON_UNESCAPED_SLASHES));

// 4. Invoke Rust executable with JSON file path
$rust_bin = str_replace("\\", "/", __DIR__ . "/bin/bidboard-pdf.exe");

if (!file_exists($rust_bin)) {
    @unlink($json_path);
    die("PDF generator binary not found at: " . htmlspecialchars($rust_bin));
}

$cmd = escapeshellarg($rust_bin) . " " . escapeshellarg($json_path) . " 2>&1";
exec($cmd, $output, $return_code);

// Cleanup JSON temp file
@unlink($json_path);

// 5. Stream PDF to browser
if ($return_code === 0 && file_exists($pdf_path)) {
    $mode = $_GET["mode"] ?? "preview"; // Default to preview mode
    $disposition = $mode === "download" ? "attachment" : "inline";

    header("Content-Type: application/pdf");
    header(
        "Content-Disposition: " .
            $disposition .
            '; filename="BidBoard_History.pdf"',
    );
    header("Content-Length: " . filesize($pdf_path));

    readfile($pdf_path);
    @unlink($pdf_path); // Clean up generated PDF from temp folder
    exit();
} else {
    echo "<h3>Error generating PDF report</h3>";
    echo "<pre style='background:#111; color:#f87171; padding:1rem; border-radius:6px;'>";
    echo htmlspecialchars(implode("\n", $output));
    echo "</pre>";
}
