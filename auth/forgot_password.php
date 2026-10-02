<?php
/** Forgot password, step 1: email -> send a 6-digit OTP. */
require_once __DIR__ . '/../includes/password_reset.php';
require_once __DIR__ . '/../includes/mailer.php';

$input = password_reset_request();
$email = trim((string) ($input['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$user = find_user_by_email($email);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found with that email address.']);
    exit;
}

$cooldown = password_reset_resend_cooldown();
if ($cooldown > 0) {
    echo json_encode([
        'success'  => false,
        'message'  => "Please wait {$cooldown}s before requesting a new code.",
        'cooldown' => $cooldown,
    ]);
    exit;
}

if (!mailer_is_configured()) {
    echo json_encode(['success' => false, 'message' => 'Email sending is not set up yet. Please contact the administrator.']);
    exit;
}

$otp = create_password_reset_otp($user['email']);
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

try {
    send_password_reset_otp_email($user['email'], $fullName, $otp);
} catch (Throwable $e) {
    clear_password_reset();
    error_log('OTP email failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to send the verification code. Please try again later.']);
    exit;
}

echo json_encode([
    'success'    => true,
    'message'    => 'A verification code has been sent to your email.',
    'expires_in' => PASSWORD_RESET_OTP_TTL,
]);
