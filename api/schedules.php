<?php
/**
 * Feeding schedules. The ESP32 downloads these and runs them locally (RTC),
 * creating a feeding_record via the normal web flow OR by POSTing acks.
 */
require_once __DIR__ . '/_helpers.php';
require_method('GET');
require_session_or_device();

$rows = get_schedules();
$next = get_next_scheduled_meal();

json_out([
    'ok' => true,
    'count' => count($rows),
    'schedules' => array_map(fn($s) => [
        'id'       => (int) $s['id'],
        'date'     => $s['feed_date'],
        'time'     => substr($s['feed_time'], 0, 5),
        'portions' => (int) $s['portions'],
        'grams'    => (int) $s['grams'],
        'enabled'  => (bool) $s['enabled'],
    ], $rows),
    'next_meal' => $next ? [
        'time'     => $next['time_label'],
        'date'     => $next['date'],
        'portions' => $next['portions'],
        'grams'    => $next['grams'],
        'minutes_away' => $next['minutes_away'],
    ] : null,
]);
