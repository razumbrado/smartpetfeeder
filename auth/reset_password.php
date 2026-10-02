<?php
/** Forgot password, step 3: save the new password (only after the OTP was verified). */
require_once __DIR__ . '/../includes/password_reset.php';

$input    = password_reset_request();
$email    = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');
$confirm  = (string) ($input['confirm'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and new password are required.']);
    exit;
}
if (!is_password_strong_enough($password)) {
    echo json_encode(['success' => false, 'message' => PASSWORD_RULE_MESSAGE]);
    exit;
}
if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

$consumed = consume_verified_password_reset($email);
if (!$consumed['success']) {
    echo json_encode($consumed);
    exit;
}

$user = find_user_by_email($email);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found with that email address.']);
    exit;
}

db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);

echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully. You can now log in.']);
