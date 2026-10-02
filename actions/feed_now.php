<?php
/**
 * Handles the "Feed Now" / "Quick Feed" form.
 * NOTE: no motor is driven here. The command is only RECORDED in MySQL with
 * status "Pending Hardware" until the ESP32 is connected.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/feed.php');
}
csrf_check();

$user     = current_user();
$source   = in_array($_POST['source'] ?? '', ['manual', 'quick'], true) ? $_POST['source'] : 'manual';
$rawPortions = trim((string) ($_POST['portions'] ?? ''));

// Portions must be a whole positive number (digits only). Grams are never
// taken from the client: they are always portions x GRAMS_PER_PORTION.
if ($rawPortions === '' || !ctype_digit($rawPortions)) {
    respond_error('pages/feed.php', 'Please select a portion or enter a valid custom portion (whole number).');
}
$portions = (int) $rawPortions;
if ($portions < 1 || $portions > MAX_FEED_PORTIONS) {
    respond_error('pages/feed.php', 'Portions must be between 1 and ' . MAX_FEED_PORTIONS . '.');
}
$grams = portions_to_grams($portions);

// Food availability check — done here, immediately before the command is
// queued for the ESP32, against the LATEST food reading. Nothing is recorded
// (so the servo can never be triggered) when there isn't enough food.
$food = get_available_food();
if (!$food['is_fresh']) {
    respond_error('pages/feed.php',
        'No recent food-weight reading from the load cell. Check the feeder connection and try again.',
        ['code' => 'stale_reading', 'food' => food_level_payload($food)]);
}
if ($food['available_grams'] < $grams) {
    respond_error('pages/feed.php',
        'There is not enough food in the dispenser for the selected portion. Available: '
        . $food['available_grams'] . ' g, required: ' . $grams . ' g.',
        [
            'code'            => 'insufficient_food',
            'available_grams' => $food['available_grams'],
            'required_grams'  => $grams,
            'food'            => food_level_payload($food),
        ]);
}

$id  = record_feeding($portions, $grams, $source, null, $user['id']);
$row = db()->prepare('SELECT * FROM feeding_records WHERE id = ?');
$row->execute([$id]);
$record = $row->fetch();

respond('pages/feed.php', [
    'success'      => true,
    'unread_count' => unread_alert_count(),
    'food'         => food_level_payload(),
    'record'       => [
        'id'           => (int) $record['id'],
        'when'         => date('M j, g:i A', strtotime($record['created_at'])),
        'source'       => ucfirst($record['source']),
        'grams'        => (int) $record['grams'],
        'portions'     => (int) $record['portions'],
        'status_label' => feed_status_label($record['status']),
        'status_class' => feed_status_class($record['status']),
    ],
]);
