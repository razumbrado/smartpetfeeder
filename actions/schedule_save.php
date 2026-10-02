<?php
/** Add or edit a feeding schedule. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/schedule.php');
}
csrf_check();

$user     = current_user();
$id       = (int) ($_POST['id'] ?? 0);
$date     = $_POST['feed_date'] ?? '';
$time     = $_POST['feed_time'] ?? '';
$rawPortions = trim((string) ($_POST['portions'] ?? ''));
$enabled  = 1; // schedules can no longer be disabled from the UI

$parsed = DateTime::createFromFormat('!Y-m-d', $date);
if (!$parsed || $parsed->format('Y-m-d') !== $date) {
    respond_error('pages/schedule.php', 'Please pick a valid feeding date.');
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
    respond_error('pages/schedule.php', 'Please pick a valid feeding time.');
}
if (new DateTime($date . ' ' . $time) <= new DateTime()) {
    respond_error('pages/schedule.php', 'The feeding date and time must be in the future.');
}
if ($rawPortions === '' || !ctype_digit($rawPortions)) {
    respond_error('pages/schedule.php', 'Please enter a valid number of portions (whole number).');
}
$portions = (int) $rawPortions;
if ($portions < 1 || $portions > MAX_FEED_PORTIONS) {
    respond_error('pages/schedule.php', 'Portions must be between 1 and ' . MAX_FEED_PORTIONS . '.');
}
$grams    = portions_to_grams($portions);
$isNew    = $id <= 0;

if (!$isNew) {
    $stmt = db()->prepare(
        'UPDATE feeding_schedules SET feed_date=?, feed_time=?, portions=?, grams=?, enabled=?
         WHERE id=?'
    );
    $stmt->execute([$date, $time . ':00', $portions, $grams, $enabled, $id]);
} else {
    $stmt = db()->prepare(
        'INSERT INTO feeding_schedules (user_id, feed_date, feed_time, portions, grams, enabled)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$user['id'], $date, $time . ':00', $portions, $grams, $enabled]);
    $id = (int) db()->lastInsertId();
}

respond('pages/schedule.php', [
    'success'  => true,
    'is_new'   => $isNew,
    'schedule' => [
        'id'         => $id,
        'feed_date'  => $date,
        'feed_time'  => $time,
        'time_label' => time_label($time . ':00'),
        'date_label' => date_label($date),
        'portions'   => $portions,
        'grams'      => $grams,
        'status'       => schedule_status($date, $time)[0],
        'status_class' => schedule_status($date, $time)[1],
    ],
]);
