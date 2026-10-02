<?php
require_once __DIR__ . '/_helpers.php';

json_out([
    'ok'      => true,
    'service' => APP_NAME . ' API',
    'hardware_connected' => HARDWARE_CONNECTED,
    'note'    => 'Web application is fully functional. Hardware endpoints are ready for future ESP32 integration.',
    'auth'    => 'Device endpoints require header  X-Device-Key: <DEVICE_API_KEY>',
    'endpoints' => [
        'GET  /api/device/status.php'   => 'Current device status',
        'POST /api/device/heartbeat.php'=> 'ESP32 check-in (device key required)',
        'GET  /api/feeding/pending.php' => 'Queued feeding commands for the device (device key required)',
        'POST /api/feeding/ack.php'     => 'Device reports feeding result (device key required)',
        'GET  /api/schedules.php'       => 'Feeding schedules',
        'GET  /api/food-level.php'      => 'Latest food level',
        'POST /api/food-level.php'      => 'ESP32 sensor submits a food-level reading (device key required)',
    ],
]);
