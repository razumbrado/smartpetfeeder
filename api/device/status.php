<?php
require_once __DIR__ . '/../_helpers.php';
require_method('GET');

$d = get_device_status();
$food = get_food_level();

json_out([
    'ok' => true,
    'device' => [
        'name'      => $d['device_name'],
        'status'    => $d['status'],
        'label'     => $d['label'],
        'is_online' => $d['is_online'],
        'last_seen' => $d['last_seen'],
    ],
    'food_level' => [
        'percent' => $food['level_percent'],
        'label'   => $food['label'],
        'is_low'  => $food['is_low'],
        'source'  => $food['source'],
    ],
    'hardware_connected' => HARDWARE_CONNECTED,
]);
