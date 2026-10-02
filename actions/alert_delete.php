<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/alerts.php');
}
csrf_check();

$ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
if (!$ids) {
    respond_error('pages/alerts.php', 'No notifications selected.');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
db()->prepare("DELETE FROM alerts WHERE id IN ($placeholders)")->execute($ids);

respond('pages/alerts.php', [
    'success'      => true,
    'ids'          => $ids,
    'unread_count' => unread_alert_count(),
]);
