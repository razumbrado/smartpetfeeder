<?php
/** Read-only: completed (dispensed) feeding count for a date range, for the Dashboard chart. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    json_response(['success' => false, 'message' => 'Invalid date.'], 422);
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$count = get_completed_feedings_count($from, $to);
$max   = max($count, get_max_daily_completed_count(), 1);

json_response([
    'success' => true,
    'count'   => $count,
    'percent' => (int) round(($count / $max) * 100),
    'from'    => $from,
    'to'      => $to,
]);
