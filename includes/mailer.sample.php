<?php
require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load credentials safely from environment or fallback constants
define("SMTP_EMAIL", getenv("SMTP_EMAIL") ?: "paste_your_real_email_here");
define(
    "SMTP_APP_PASSWORD",
    getenv("SMTP_APP_PASSWORD") ?: "paste_your_app_password_here",
);

/**
 * Factory function to instantiate and pre-configure PHPMailer.
 * Prevents repeating SMTP configuration across multiple notification functions.
 */
function create_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_EMAIL;
    $mail->Password = SMTP_APP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = "UTF-8";

    $mail->setFrom(SMTP_EMAIL, "BidBoard");

    return $mail;
}

/**
 * Sends bid status notifications (Accepted / Rejected).
 */
function send_bid_notification(
    string $to_email,
    string $to_name,
    string $task_title,
    string $status,
): bool {
    $safe_name = htmlspecialchars(
        $to_name,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );
    $safe_title = htmlspecialchars(
        $task_title,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );

    try {
        $mail = create_mailer();
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
            $mail->AltBody = "Hi {$to_name},\n\nGreat news — your bid on \"{$task_title}\" has been accepted!\n\nThe client will be in touch with you soon.\n\n— BidBoard";
        } else {
            $mail->Subject = "Update on your bid — BidBoard";
            $mail->Body = "
                <p>Hi {$safe_name},</p>
                <p>Thank you for bidding on <strong>\"{$safe_title}\"</strong>. Unfortunately, the client chose a different bid this time.</p>
                <p>Don't worry — there are plenty of other tasks open on BidBoard. Keep bidding!</p>
                <p>— BidBoard</p>
            ";
            $mail->AltBody = "Hi {$to_name},\n\nThank you for bidding on \"{$task_title}\". Unfortunately, the client chose a different bid this time.\n\nDon't worry — there are plenty of other tasks open on BidBoard. Keep bidding!\n\n— BidBoard";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send failed (bid notification): {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Sends account activation / deactivation notifications.
 */
function send_account_status_notification(
    string $to_email,
    string $to_name,
    bool $is_active,
): bool {
    $safe_name = htmlspecialchars(
        $to_name,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8",
    );

    try {
        $mail = create_mailer();
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);

        if ($is_active) {
            $mail->Subject = "Your BidBoard account has been reactivated";
            $mail->Body = "
                <p>Hi {$safe_name},</p>
                <p>Your account has been <strong style='color:#16a34a;'>reactivated</strong>. You can log in again.</p>
                <p>— BidBoard Team</p>
            ";
            $mail->AltBody = "Hi {$to_name},\n\nYour account has been reactivated. You can log in again.\n\n— BidBoard Team";
        } else {
            $mail->Subject = "Your BidBoard account has been deactivated";
            $mail->Body = "
                <p>Hi {$safe_name},</p>
                <p>Your account has been <strong style='color:#dc2626;'>deactivated</strong> by an administrator, so you can no longer log in.</p>
                <p>If you think this is a mistake, reply to this email to contact the BidBoard team.</p>
                <p>— BidBoard Team</p>
            ";
            $mail->AltBody = "Hi {$to_name},\n\nYour account has been deactivated by an administrator, so you can no longer log in.\n\nIf you think this is a mistake, reply to this email to contact the BidBoard team.\n\n— BidBoard Team";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log(
            "Email send failed (account notification): {$mail->ErrorInfo}",
        );
        return false;
    }
}
