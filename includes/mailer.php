<?php
/**
 * Outgoing email (PHPMailer over SMTP). Settings live in config/config.php.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function mailer_is_configured(): bool
{
    return SMTP_USERNAME !== '' && SMTP_PASSWORD !== '' && SMTP_FROM_EMAIL !== '';
}

function create_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 30;
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    return $mail;
}

/** Throws RuntimeException when the email can't be sent. */
function send_password_reset_otp_email(string $toEmail, string $toName, string $otp): void
{
    if (!mailer_is_configured()) {
        throw new RuntimeException('SMTP is not configured (see config/config.php).');
    }

    $mail = null;
    $minutes = PASSWORD_RESET_OTP_TTL / 60;
    $expires = $minutes == 1 ? '1 minute' : $minutes . ' minutes';
    try {
        $mail = create_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your ' . APP_NAME . ' Password Reset Code';
        $mail->Body = '
            <div style="font-family: Poppins, Arial, sans-serif; max-width: 480px; margin: 0 auto; color: #3b2a1e;">
                <h2 style="color: #4a2f1d;">Password Reset Request</h2>
                <p>Hi ' . htmlspecialchars($toName) . ',</p>
                <p>Use the verification code below to reset your ' . htmlspecialchars(APP_NAME) . ' password. This code expires in ' . $expires . '.</p>
                <p style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #8a6224; text-align: center; margin: 24px 0;">
                    ' . htmlspecialchars($otp) . '
                </p>
                <p>If you did not request a password reset, you can safely ignore this email.</p>
                <p style="color: #a3907a; font-size: 0.85rem;">&copy; ' . htmlspecialchars(APP_NAME) . '</p>
            </div>';
        $mail->AltBody = "Your password reset code is: {$otp}\nThis code expires in {$expires}.";
        $mail->send();
    } catch (PHPMailerException $e) {
        throw new RuntimeException('Failed to send OTP email: ' . ($mail?->ErrorInfo ?: $e->getMessage()));
    }
}
