<?php
/** Upload / replace the logged-in user's profile picture. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/account.php');
}
csrf_check();

$user = current_user();
$back = $_POST['back'] ?? 'pages/account.php';

$file = $_FILES['avatar'] ?? null;
if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    respond_error($back, 'Please choose an image to upload.');
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    respond_error($back, 'Upload failed. Please try again.');
}
if ($file['size'] > 3 * 1024 * 1024) {
    respond_error($back, 'Image must be 3MB or smaller.');
}

$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$mime = mime_content_type($file['tmp_name']);
if (!isset($mimeToExt[$mime])) {
    respond_error($back, 'Please upload a JPG, PNG or WEBP image.');
}

$dir = __DIR__ . '/../uploads/avatars';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$filename = 'user_' . $user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $mimeToExt[$mime];
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
    respond_error($back, 'Could not save the image. Please try again.');
}

$old = $user['avatar'];
$stmt = db()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
$stmt->execute([$filename, $user['id']]);

if ($old && $old !== $filename && is_file($dir . '/' . $old)) {
    unlink($dir . '/' . $old);
}

respond($back, [
    'success'    => true,
    'avatar_url' => avatar_url($filename),
]);
