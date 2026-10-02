<?php
/**
 * Returns feeding commands the device still needs to act on
 * (status = pending_hardware). The ESP32 dispenses each one, then calls
 * /api/feeding/ack.php to update the status.
 *
 * Food check: this is the moment a command is handed to the servo, so each
 * one is re-checked against the LATEST load-cell weight. Commands the hopper
 * can't cover are marked "failed" (Insufficient food) and NOT returned, so the
 * ESP32 never runs the servo for them.
 */
require_once __DIR__ . '/../_helpers.php';
require_method('GET');
require_device();

touch_device();

$rows = db()->query(
    "SELECT id, portions, grams, source, note, created_at
       FROM feeding_records
      WHERE status = 'pending_hardware'
      ORDER BY created_at ASC"
)->fetchAll();

$food = get_available_food();
$remaining = $food['is_fresh'] ? $food['available_grams'] : 0;
$deliver = [];
$reject = db()->prepare(
    "UPDATE feeding_records SET status = 'failed', note = ? WHERE id = ?"
);
foreach ($rows as $r) {
    if (!$food['is_fresh']) {
        // No trustworthy weight: hold the commands, don't fail them.
        break;
    }
    if ($remaining < (int) $r['grams']) {
        $reject->execute(['Insufficient food: ' . $remaining . ' g available, ' . (int) $r['grams'] . ' g required', $r['id']]);
        add_alert('low_food', 'critical', 'Feeding skipped - insufficient food',
            'Command #' . $r['id'] . ' needed ' . (int) $r['grams'] . ' g but only ' . $remaining
            . ' g is in the hopper. Please refill the food container.');
        continue;
    }
    $remaining -= (int) $r['grams'];   // reserve food for commands handed out together
    $deliver[] = $r;
}
$rows = $deliver;

json_out([
    'ok' => true,
    'available_grams' => $food['available_grams'],
    'reading_is_fresh' => $food['is_fresh'],
    'count' => count($rows),
    'commands' => array_map(fn($r) => [
        'command_id' => (int) $r['id'],
        'portions'   => (int) $r['portions'],
        'grams'      => (int) $r['grams'],
        'source'     => $r['source'],
        'created_at' => $r['created_at'],
    ], $rows),
]);
