<?php
require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define("SMTP_EMAIL", "your_actual_gmail@gmail.com");
define("SMTP_APP_PASSWORD", "your_16_char_app_password");

function send_bid_notification(
    string $to_email,
    string $to_name,
    string $task_title,
    string $status,
): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_EMAIL;
        $mail->Password = SMTP_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom(SMTP_EMAIL, "BidBoard");
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);

        if ($status === "accepted") {
            $mail->Subject = "Your bid was accepted! — BidBoard";
            $mail->Body = "
                <p>Hi {$to_name},</p>
                <p>Great news — your bid on <strong>\"{$task_title}\"</strong> has been <strong style='color:#16a34a;'>accepted</strong>!</p>
                <p>The client will be in touch with you soon.</p>
                <p>— BidBoard</p>
            ";
        } else {
            $mail->Subject = "Update on your bid — BidBoard";
            $mail->Body = "
                <p>Hi {$to_name},</p>
                <p>Thank you for bidding on <strong>\"{$task_title}\"</strong>. Unfortunately, the client chose a different bid this time.</p>
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
?>
