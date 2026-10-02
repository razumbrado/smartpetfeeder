<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/alerts.php');
}
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id === -1) {
    db()->query('UPDATE alerts SET is_read = 1 WHERE is_read = 0');
} elseif ($id > 0) {
    db()->prepare('UPDATE alerts SET is_read = 1 WHERE id = ?')->execute([$id]);
} else {
    respond_error('pages/alerts.php', 'Invalid alert.');
}

respond('pages/alerts.php', [
    'success'      => true,
    'id'           => $id,
    'unread_count' => unread_alert_count(),
]);
