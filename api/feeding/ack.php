<?php
/**
 * ESP32 reports the outcome of a feeding command.
 * Body (JSON): { "command_id": 12, "status": "dispensed", "food_grams": 1240 }
 *   status: command_sent | dispensed | failed
 *   food_grams (optional): fresh load-cell weight taken AFTER dispensing, so
 *   the dashboard's remaining grams / percentage update right away.
 */
require_once __DIR__ . '/../_helpers.php';
require_method('POST');
require_device();
touch_device();

$body = request_body();
$id     = (int) ($body['command_id'] ?? 0);
$status = $body['status'] ?? '';
$allowed = ['command_sent', 'dispensed', 'failed'];

if ($id <= 0 || !in_array($status, $allowed, true)) {
    json_error('Provide command_id and status (' . implode('|', $allowed) . ').');
}

$rec = db()->prepare('SELECT id, grams FROM feeding_records WHERE id = ?');
$rec->execute([$id]);
$row = $rec->fetch();
if (!$row) {
    json_error('Unknown command_id.', 404);
}

$dispensedAt = $status === 'dispensed' ? date('Y-m-d H:i:s') : null;
db()->prepare('UPDATE feeding_records SET status = ?, dispensed_at = ? WHERE id = ?')
    ->execute([$status, $dispensedAt, $id]);

if (isset($body['food_grams']) && is_numeric($body['food_grams'])) {
    $grams = max(0, min(65535, (int) round($body['food_grams'])));
    set_food_level(food_grams_to_percent($grams), 'sensor', $grams);
}

if ($status === 'dispensed') {
    add_alert('feed_completed', 'info', 'Feeding completed',
        'ESP32 confirmed ' . (int) $row['grams'] . 'g dispensed (command #' . $id . ').');
} elseif ($status === 'failed') {
    add_alert('hardware', 'critical', 'Feeding failed',
        'ESP32 reported command #' . $id . ' failed. Check the servo / hopper.');
}

json_out(['ok' => true, 'command_id' => $id, 'status' => $status]);
