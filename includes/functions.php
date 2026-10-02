<?php
/**
 * Shared domain helpers: food level, dashboard stats, alerts, schedules.
 */
require_once __DIR__ . '/../config/config.php';

/* ----------------------------------------------------------------------
 *  Small utilities
 * -------------------------------------------------------------------- */

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Render a Google Material Symbols icon, e.g. <?= icon('dashboard') ?> */
function icon(string $name, string $class = ''): string
{
    return '<span class="material-symbols-outlined' . ($class ? ' ' . e($class) : '') . '">'
        . e($name) . '</span>';
}

/** Inline pet-food-can icon (Material Symbols has no equivalent). */
function feed_icon(string $class = ''): string
{
    return '<svg class="feed-ico' . ($class ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" '
        . 'fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M5 7v10c0 1.4 3.1 2.3 7 2.3s7-.9 7-2.3V7"/>'
        . '<ellipse cx="12" cy="7" rx="7" ry="2.3"/>'
        . '<path d="M12 15.6c-1.5 0-2.7-1-2.7-2.1 0-.9 1.1-1.3 2.7-1.3s2.7.4 2.7 1.3c0 1.1-1.2 2.1-2.7 2.1Z"/>'
        . '<circle cx="9" cy="11.3" r=".85"/>'
        . '<circle cx="12" cy="10.7" r=".85"/>'
        . '<circle cx="15" cy="11.3" r=".85"/>'
        . '</svg>';
}

/** "First Last" from a user row (array with first_name/last_name). */
function full_name(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

function role_label(string $role): string
{
    return [
        'admin' => 'Admin',
        'owner' => 'Pet Owner',
    ][$role] ?? ucfirst($role);
}

/** Public URL for a stored avatar filename (users.avatar). */
function avatar_url(string $filename): string
{
    return url('uploads/avatars/' . $filename);
}

/**
 * Inner markup for a .avatar-style circle: an <img> shown when the user has
 * uploaded a photo, plus a letter fallback. Both stay in the DOM so main.js
 * can flip which one is visible after a profile update or photo upload
 * without re-rendering the page.
 */
function avatar_inner(array $user): string
{
    $hasPhoto = !empty($user['avatar']);
    $initial  = e(strtoupper(substr($user['first_name'], 0, 1)));
    $src      = $hasPhoto ? e(avatar_url($user['avatar'])) : '';
    return '<img data-user-avatar-img src="' . $src . '" alt=""' . ($hasPhoto ? '' : ' hidden') . '>'
        . '<span data-user-avatar-initial' . ($hasPhoto ? ' hidden' : '') . '>' . $initial . '</span>';
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** True when the current request came from the app's own fetch()-based JS, not a plain form post. */
function is_ajax_request(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Action endpoints call this on success: JS callers get JSON to patch the
 * DOM directly, while a plain form post (progressive-enhancement fallback)
 * still gets the classic redirect.
 */
function respond(string $redirectPath, array $json = ['success' => true]): void
{
    if (is_ajax_request()) {
        json_response($json);
    }
    redirect($redirectPath);
}

/** Action endpoints call this on validation failure. */
function respond_error(string $redirectPath, string $message, array $extra = []): void
{
    if (is_ajax_request()) {
        json_response(array_merge(['success' => false, 'message' => $message], $extra), 422);
    }
    flash($message, 'error');
    redirect($redirectPath);
}

/** One-time flash message stored in the session. */
function flash(string $msg = null, string $type = 'success')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* ----------------------------------------------------------------------
 *  Portions <-> grams
 * -------------------------------------------------------------------- */

function portions_to_grams(int $portions): int
{
    return $portions * GRAMS_PER_PORTION;
}

/* ----------------------------------------------------------------------
 *  Feeding status labels
 * -------------------------------------------------------------------- */

function feed_status_label(string $status): string
{
    return [
        'pending_hardware' => 'Pending Hardware',
        'command_sent'     => 'Command Sent',
        'dispensed'        => 'Dispensed',
        'failed'           => 'Failed',
    ][$status] ?? ucfirst($status);
}

function feed_status_class(string $status): string
{
    return [
        'pending_hardware' => 'badge badge--warning',
        'command_sent'     => 'badge badge--info',
        'dispensed'        => 'badge badge--success',
        'failed'           => 'badge badge--danger',
    ][$status] ?? 'badge';
}

/* ----------------------------------------------------------------------
 *  Food level
 * -------------------------------------------------------------------- */

/** Latest food level row (or a safe default). */
function get_food_level(): array
{
    $row = db()->query(
        'SELECT level_percent, food_grams, source, created_at
           FROM food_level ORDER BY created_at DESC, id DESC LIMIT 1'
    )->fetch();

    if (!$row) {
        $row = ['level_percent' => 75, 'food_grams' => null, 'source' => 'sample', 'created_at' => date('Y-m-d H:i:s')];
    }
    $row['level_percent'] = (int) $row['level_percent'];
    // Only load-cell readings carry a weight; manual/sample values are percent only.
    $row['food_grams'] = $row['food_grams'] === null ? null : (int) $row['food_grams'];
    $row['label'] = food_level_label($row['level_percent']);
    $row['is_low'] = $row['level_percent'] <= LOW_FOOD_THRESHOLD;
    return $row;
}

/** Human label such as "100% Full", "75% Remaining", "Low Food". */
function food_level_label(int $percent): string
{
    if ($percent >= 100) return '100% Full';
    if ($percent <= LOW_FOOD_THRESHOLD) return 'Low Food';
    return $percent . '% Remaining';
}

function food_level_state(int $percent): string
{
    if ($percent <= LOW_FOOD_THRESHOLD) return 'danger';
    if ($percent <= 40) return 'warning';
    return 'ok';
}

/** Weight from the load cell (grams of food in the hopper) -> 0-100 % of HOPPER_CAPACITY_GRAMS. */
function food_grams_to_percent(float $grams): int
{
    return max(0, min(100, (int) round($grams / HOPPER_CAPACITY_GRAMS * 100)));
}

/**
 * Remaining food in the hopper, in grams, from the LATEST food_level row.
 * Load-cell readings carry their own weight; manual/sample readings (no
 * hardware yet) are converted from percent x HOPPER_CAPACITY_GRAMS.
 *
 * 'is_fresh' is false when hardware is connected but the newest load-cell
 * reading is missing or older than FOOD_READING_MAX_AGE: feeding must then
 * be refused, because the stored weight can't be trusted.
 */
function get_available_food(): array
{
    $food = get_food_level();
    $fromLoadCell = $food['food_grams'] !== null;
    $grams = $fromLoadCell
        ? $food['food_grams']
        : (int) round($food['level_percent'] / 100 * HOPPER_CAPACITY_GRAMS);

    $isFresh = true;
    if (HARDWARE_CONNECTED) {
        $age = time() - strtotime($food['created_at']);
        $isFresh = $fromLoadCell && $age <= FOOD_READING_MAX_AGE;
    }

    return $food + [
        'available_grams' => $grams,
        'from_load_cell'  => $fromLoadCell,
        'is_fresh'        => $isFresh,
    ];
}

/** Snapshot of the food level for JSON responses / live UI refresh. */
function food_level_payload(?array $food = null): array
{
    $food = $food ?? get_available_food();
    return [
        'percent'         => $food['level_percent'],
        'label'           => $food['label'],
        'state'           => food_level_state($food['level_percent']),
        'is_low'          => $food['is_low'],
        'source'          => $food['source'],
        'available_grams' => $food['available_grams'],
        'capacity_grams'  => HOPPER_CAPACITY_GRAMS,
        'from_load_cell'  => $food['from_load_cell'],
        'is_fresh'        => $food['is_fresh'],
        'updated_at'      => $food['created_at'],
        'updated_label'   => time_ago($food['created_at']),
    ];
}

/**
 * Store a new food-level reading (used by the manual control and the ESP32 API).
 * $grams is the load-cell weight when the reading came from the sensor.
 */
function set_food_level(int $percent, string $source = 'manual', ?int $grams = null): void
{
    $percent = max(0, min(100, $percent));
    $stmt = db()->prepare(
        'INSERT INTO food_level (device_id, level_percent, food_grams, source) VALUES (1, ?, ?, ?)'
    );
    $stmt->execute([$percent, $grams, $source]);

    if ($percent <= LOW_FOOD_THRESHOLD) {
        add_alert('low_food', 'critical', 'Low food level',
            'Food level is at ' . $percent . '%. Please refill the feeder hopper.');
    }
}

/* ----------------------------------------------------------------------
 *  Device status
 * -------------------------------------------------------------------- */

function get_device_status(): array
{
    $row = db()->query('SELECT * FROM device_status ORDER BY id LIMIT 1')->fetch();
    if (!$row) {
        $row = ['device_name' => 'ESP32 Feeder #1', 'status' => 'not_connected', 'last_seen' => null];
    }
    // Safety net: if hardware flag is off, never report "online".
    if (!HARDWARE_CONNECTED && $row['status'] === 'online') {
        $row['status'] = 'not_connected';
    }
    $row['is_online'] = ($row['status'] === 'online');
    $row['label'] = [
        'not_connected' => 'Not Connected',
        'online'        => 'Online',
        'offline'       => 'Offline',
    ][$row['status']] ?? 'Unknown';
    return $row;
}

/* ----------------------------------------------------------------------
 *  Alerts
 * -------------------------------------------------------------------- */

function add_alert(string $type, string $severity, string $title, string $message): void
{
    // Avoid piling up duplicate unread alerts of the same title.
    $dup = db()->prepare(
        'SELECT id FROM alerts WHERE title = ? AND is_read = 0
           AND created_at > DATE_SUB(NOW(), INTERVAL 12 HOUR) LIMIT 1'
    );
    $dup->execute([$title]);
    if ($dup->fetch()) {
        return;
    }
    $stmt = db()->prepare(
        'INSERT INTO alerts (type, severity, title, message) VALUES (?,?,?,?)'
    );
    $stmt->execute([$type, $severity, $title, $message]);
}

function get_alerts(int $limit = 50): array
{
    $stmt = db()->prepare('SELECT * FROM alerts ORDER BY is_read ASC, created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function unread_alert_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM alerts WHERE is_read = 0')->fetchColumn();
}

/**
 * Recompute the "live" alerts that depend on current state.
 * Called on dashboard / alerts page load so the list stays meaningful
 * even without hardware.
 */
function refresh_dynamic_alerts(): void
{
    $device = get_device_status();
    if (!$device['is_online']) {
        add_alert('hardware', 'warning', 'Hardware not connected',
            'ESP32 is not connected. Feeding commands are recorded but not physically dispensed.');
    }

    $food = get_food_level();
    if ($food['is_low']) {
        add_alert('low_food', 'critical', 'Low food level',
            'Food level is at ' . $food['level_percent'] . '%. Please refill the feeder hopper.');
    }

    // Pending feed commands (waiting for the ESP32).
    $pending = (int) db()->query(
        "SELECT COUNT(*) FROM feeding_records WHERE status = 'pending_hardware'"
    )->fetchColumn();
    if ($pending > 0) {
        add_alert('feed_pending', 'info', 'Feeding command pending',
            $pending . ' feeding command(s) are queued and waiting for the ESP32.');
    }

    // Next scheduled meal reminder (within the next 30 minutes).
    $next = get_next_scheduled_meal();
    if ($next && $next['minutes_away'] !== null && $next['minutes_away'] >= 0 && $next['minutes_away'] <= 30) {
        add_alert('schedule_reminder', 'info', 'Feeding schedule reminder',
            'Next scheduled meal at ' . $next['time_label'] . ' (' . $next['portions'] . ' portions).');
    }
}

/* ----------------------------------------------------------------------
 *  Schedules
 * -------------------------------------------------------------------- */

function get_schedules(): array
{
    return db()->query(
        'SELECT * FROM feeding_schedules ORDER BY id DESC'
    )->fetchAll();
}

/** e.g. "Saturday, Oct 3, 2026" */
function date_label(string $date): string
{
    return date('l, M j, Y', strtotime($date));
}

/**
 * "Completed" once the schedule's date + time has passed, otherwise "Upcoming".
 * Returns [label, badge class].
 */
function schedule_status(string $date, string $time): array
{
    return strtotime($date . ' ' . $time) <= time()
        ? ['Completed', 'badge--success']
        : ['Upcoming', 'badge--info'];
}

function time_label(string $time): string
{
    return date('g:i A', strtotime($time));
}

/**
 * Work out the next upcoming scheduled meal from the enabled schedules.
 * Returns null when no enabled schedule is still in the future.
 */
function get_next_scheduled_meal(): ?array
{
    $schedules = array_filter(get_schedules(), fn($s) => (int) $s['enabled'] === 1);
    if (!$schedules) {
        return null;
    }

    $now = new DateTime();
    $best = null;
    $bestDiff = PHP_INT_MAX;

    foreach ($schedules as $s) {
        $cand = new DateTime($s['feed_date'] . ' ' . $s['feed_time']);
        $diff = $cand->getTimestamp() - $now->getTimestamp();
        if ($diff < 0 || $diff >= $bestDiff) {
            continue;
        }
        $bestDiff = $diff;
        $best = [
            'schedule_id'  => (int) $s['id'],
            'datetime'     => $cand,
            'time_label'   => time_label($s['feed_time']),
            'date'         => $cand->format('Y-m-d'),
            'date_label'   => $cand->format('M j, Y'),
            'portions'     => (int) $s['portions'],
            'grams'        => (int) $s['grams'],
            'minutes_away' => (int) round($diff / 60),
        ];
    }
    return $best;
}

/* ----------------------------------------------------------------------
 *  Feeding records + dashboard stats
 * -------------------------------------------------------------------- */

/**
 * Record a feeding command. Because there is no hardware yet the status is
 * ALWAYS 'pending_hardware'. When the ESP32 is connected, the API will move
 * the record to 'command_sent' and then 'dispensed'.
 */
function record_feeding(int $portions, int $grams, string $source = 'manual', ?int $scheduleId = null, ?int $userId = null): int
{
    $status = HARDWARE_CONNECTED ? 'command_sent' : 'pending_hardware';
    $note   = HARDWARE_CONNECTED ? null : 'Awaiting ESP32 - not physically dispensed';

    $stmt = db()->prepare(
        'INSERT INTO feeding_records (user_id, schedule_id, portions, grams, source, status, note)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([$userId, $scheduleId, $portions, $grams, $source, $status, $note]);
    $id = (int) db()->lastInsertId();

    add_alert('feed_pending', 'info', 'Feeding command pending',
        'Feed of ' . $grams . 'g recorded. Status: Pending Hardware (waiting for ESP32).');

    return $id;
}

function get_recent_feedings(int $limit = 8): array
{
    $stmt = db()->prepare(
        'SELECT r.*, s.feed_time
           FROM feeding_records r
           LEFT JOIN feeding_schedules s ON s.id = r.schedule_id
          ORDER BY r.created_at DESC, r.id DESC
          LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_dashboard_stats(): array
{
    $pdo = db();

    // Food dispensed today = sum of grams of today's records.
    // NOTE: these are recorded commands. Until hardware confirms, treat as "commanded".
    $today = $pdo->query(
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(grams),0) AS grams
           FROM feeding_records
          WHERE DATE(created_at) = CURDATE()"
    )->fetch();

    $last = $pdo->query(
        'SELECT created_at, grams, portions, status, source
           FROM feeding_records ORDER BY created_at DESC, id DESC LIMIT 1'
    )->fetch();

    return [
        'today_count'  => (int) $today['cnt'],
        'today_grams'  => (int) $today['grams'],
        'last_fed'     => $last ?: null,
        'food_level'   => get_food_level(),
        'device'       => get_device_status(),
        'next_meal'    => get_next_scheduled_meal(),
    ];
}

/** Count of feedings the hardware has actually confirmed dispensing within a date range (inclusive). */
function get_completed_feedings_count(string $from, string $to): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM feeding_records
          WHERE status = 'dispensed' AND DATE(created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    return (int) $stmt->fetchColumn();
}

/** The busiest single calendar day for completed feedings, used to scale the chart bar. */
function get_max_daily_completed_count(): int
{
    $row = db()->query(
        "SELECT COALESCE(MAX(c), 0) AS m FROM (
            SELECT COUNT(*) AS c FROM feeding_records WHERE status = 'dispensed' GROUP BY DATE(created_at)
         ) t"
    )->fetch();
    return (int) ($row['m'] ?? 0);
}

function time_ago(string $datetime): string
{
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'yesterday';
    return date('M j, g:i A', $ts);
}
