<?php
/** Forgot password, step 2: check the emailed OTP. */
require_once __DIR__ . '/../includes/password_reset.php';

$input = password_reset_request();
$email = trim((string) ($input['email'] ?? ''));
$otp   = trim((string) ($input['otp'] ?? ''));

if ($email === '' || !preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'message' => 'Please enter the complete 6-digit code.']);
    exit;
}

echo json_encode(verify_password_reset_otp($email, $otp));
