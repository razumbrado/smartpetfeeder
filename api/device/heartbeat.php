<?php
/**
 * ESP32 -> server check-in. Call every ~30-60s from the firmware.
 * Body (JSON): { "firmware": "1.0.0", "food_grams": 1440 }
 * food_grams (load cell + HX711 weight) is optional; "food_level" (percent) is
 * still accepted as a fallback.
 */
require_once __DIR__ . '/../_helpers.php';
require_method('POST');
require_device();

$body = request_body();
touch_device([
    'firmware' => isset($body['firmware']) ? substr((string) $body['firmware'], 0, 40) : null,
]);

// Optional: piggyback a food-level reading on the heartbeat.
if (isset($body['food_grams']) && is_numeric($body['food_grams'])) {
    $grams = max(0, min(65535, (int) round($body['food_grams'])));
    set_food_level(food_grams_to_percent($grams), 'sensor', $grams);
} elseif (isset($body['food_level']) && is_numeric($body['food_level'])) {
    set_food_level((int) $body['food_level'], 'sensor');
}

$pending = (int) db()->query(
    "SELECT COUNT(*) FROM feeding_records WHERE status = 'pending_hardware'"
)->fetchColumn();

json_out([
    'ok' => true,
    'message' => 'Heartbeat received.',
    'server_time' => date('c'),
    'pending_commands' => $pending,
    'hint' => $pending > 0 ? 'Call GET /api/feeding/pending.php to fetch them.' : 'Nothing to dispense.',
]);
