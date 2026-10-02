<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/schedule.php');
}
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    respond_error('pages/schedule.php', 'Invalid schedule.');
}

db()->prepare('DELETE FROM feeding_schedules WHERE id = ?')->execute([$id]);

respond('pages/schedule.php', [
    'success'        => true,
    'id'             => $id,
    'schedule_count' => (int) db()->query('SELECT COUNT(*) FROM feeding_schedules')->fetchColumn(),
]);
