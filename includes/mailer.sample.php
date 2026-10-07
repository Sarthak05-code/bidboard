<?php
require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define("SMTP_EMAIL", "paste_your_real_email_here");
define("SMTP_APP_PASSWORD", "paste_your_app_password_here"); // paste your 16-char Gmail App Password here (global only)

/*
actions/accept_bid.php
actions/reject_bid.php
*/
function send_bid_notification(
    string $to_email,
    string $to_name,
    string $task_title,
    string $status,
): bool {
    $mail = new PHPMailer(true);

    // Escape user-supplied values before putting them in HTML
    $safe_name = htmlspecialchars($to_name, ENT_QUOTES, "UTF-8");
    $safe_title = htmlspecialchars($task_title, ENT_QUOTES, "UTF-8");

    try {
        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_EMAIL;
        $mail->Password = SMTP_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = "UTF-8";

        $mail->setFrom(SMTP_EMAIL, "BidBoard");
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);

        if ($status === "accepted") {
            $mail->Subject = "Your bid was accepted! — BidBoard";
            $mail->Body = "
                <p>Hi {$safe_name},</p>
                <p>Great news — your bid on <strong>\"{$safe_title}\"</strong> has been <strong style='color:#16a34a;'>accepted</strong>!</p>
                <p>The client will be in touch with you soon.</p>
                <p>— BidBoard</p>
            ";
        } else {
            $mail->Subject = "Update on your bid — BidBoard";
            $mail->Body = "
                <p>Hi {$safe_name},</p>
                <p>Thank you for bidding on <strong>\"{$safe_title}\"</strong>. Unfortunately, the client chose a different bid this time.</p>
                <p>Don't worry — there are plenty of other tasks open on BidBoard. Keep bidding!</p>
                <p>— BidBoard</p>
            ";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed: {$mail->ErrorInfo}");
        return false;
    }
}

function send_account_disabled_notification(
    string $to_email,
    string $to_name,
    string $status,
) {
    
}
?>
