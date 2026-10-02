<?php
/** Change the logged-in user's password. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/dashboard.php');
}
csrf_check();

$user    = current_user();
$current = (string) ($_POST['current_password'] ?? '');
$new     = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');
$back    = $_POST['back'] ?? 'pages/dashboard.php';

$row = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
$row->execute([$user['id']]);
$hash = $row->fetchColumn();

if (!$hash || !password_verify($current, $hash)) {
    respond_error($back, 'Current password is incorrect.');
}
if (!is_password_strong_enough($new)) {
    respond_error($back, PASSWORD_RULE_MESSAGE);
}
if ($new !== $confirm) {
    respond_error($back, 'New password and confirmation do not match.');
}

$stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

respond($back, ['success' => true]);
