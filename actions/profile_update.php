<?php
/** Update the logged-in user's first name, last name, username and email. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/dashboard.php');
}
csrf_check();

$user     = current_user();
$first    = trim($_POST['first_name'] ?? '');
$last     = trim($_POST['last_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$back     = $_POST['back'] ?? 'pages/dashboard.php';

if ($first === '' || $last === '' || $username === '') {
    respond_error($back, 'First name, last name and username are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond_error($back, 'Please enter a valid email address.');
}

$dupUsername = db()->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
$dupUsername->execute([$username, $user['id']]);
if ($dupUsername->fetch()) {
    respond_error($back, 'That username is already taken.');
}

$dupEmail = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
$dupEmail->execute([$email, $user['id']]);
if ($dupEmail->fetch()) {
    respond_error($back, 'That email is already used by another account.');
}

$stmt = db()->prepare('UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ? WHERE id = ?');
$stmt->execute([$first, $last, $username, $email, $user['id']]);

respond($back, [
    'success'    => true,
    'first_name' => $first,
    'last_name'  => $last,
    'username'   => $username,
    'email'      => $email,
]);
