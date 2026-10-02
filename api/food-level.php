<?php
/**
 * GET  -> latest stored food level (used by the web dashboard / any client).
 * POST -> ESP32 submits a load cell + HX711 reading. Body (JSON): { "grams": 1360 }
 *         (net food weight in the hopper; converted to % of HOPPER_CAPACITY_GRAMS).
 *         { "percent": 68 } is still accepted as a fallback.
 *         Requires device key. This is the "Load cell -> HX711 -> ESP32 -> PHP API -> MySQL" step.
 */
require_once __DIR__ . '/_helpers.php';
require_method('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_session_or_device();
    json_out(['ok' => true, 'threshold' => LOW_FOOD_THRESHOLD] + food_level_payload());
}

// POST
require_device();
touch_device();

$body = request_body();
$grams = $body['grams'] ?? null;
$percent = $body['percent'] ?? ($body['food_level'] ?? null);

if (is_numeric($grams)) {
    // Small negative values are HX711 drift around an empty (tared) hopper.
    $grams = max(0, min(65535, (int) round($grams)));
    $percent = food_grams_to_percent($grams);
} elseif (is_numeric($percent)) {
    $grams = null;
    $percent = max(0, min(100, (int) $percent));
} else {
    json_error('Provide "grams" (load-cell weight) or "percent" (0-100).');
}
set_food_level($percent, 'sensor', $grams);

json_out([
    'ok' => true,
    'stored_grams' => $grams,
    'stored_percent' => $percent,
    'label' => food_level_label($percent),
    'is_low' => $percent <= LOW_FOOD_THRESHOLD,
]);
