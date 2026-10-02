<?php
/**
 * Manually set the stored food level (demo / calibration helper).
 * When the ESP32 sensor is connected this becomes unnecessary - the device
 * will POST readings to /api/food-level.php instead.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/important.php');
}
csrf_check();

$percent = (int) ($_POST['level_percent'] ?? -1);
if ($percent < 0 || $percent > 100) {
    respond_error('pages/important.php', 'Enter a food level between 0 and 100.');
}

set_food_level($percent, 'manual');

respond('pages/important.php', [
    'success'       => true,
    'level_percent' => $percent,
    'label'         => food_level_label($percent),
    'is_low'        => $percent <= LOW_FOOD_THRESHOLD,
    'unread_count'  => unread_alert_count(),
]);
