<?php
/**
 * Shared helpers for every /api/*.php endpoint.
 *
 * These endpoints are LIVE now (they read/write the same MySQL tables the web
 * UI uses). They are also the contract the future ESP32 firmware will use.
 * No endpoint drives real hardware - the ESP32 will do that and report back.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $code = 400): void
{
    json_out(['ok' => false, 'error' => $message], $code);
}

/** Only allow the given HTTP method(s). */
function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_error('Method not allowed. Expected: ' . implode('/', $methods), 405);
    }
}

/** Read a JSON or form body into an array. */
function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST ?: [];
}

/**
 * Device authentication for the future ESP32.
 * Accepts header  X-Device-Key: <key>  or  ?device_key=<key>.
 * While no real key is configured this still works so you can test with curl.
 */
function require_device(): void
{
    $sent = $_SERVER['HTTP_X_DEVICE_KEY']
        ?? ($_GET['device_key'] ?? ($_POST['device_key'] ?? ''));

    if (!hash_equals(DEVICE_API_KEY, (string) $sent)) {
        json_error('Unauthorized device. Send header X-Device-Key with the configured DEVICE_API_KEY.', 401);
    }
}

/** Allow either a logged-in web session or a valid device key (for GET reads). */
function require_session_or_device(): void
{
    require_once __DIR__ . '/../includes/auth.php';
    if (is_logged_in()) {
        return;
    }
    require_device();
}

/** Mark the device online / update its heartbeat metadata. */
function touch_device(array $meta = []): void
{
    $stmt = db()->prepare(
        "UPDATE device_status
            SET status = 'online',
                last_seen = NOW(),
                firmware = COALESCE(?, firmware),
                ip_address = COALESCE(?, ip_address)
          WHERE id = 1"
    );
    $stmt->execute([
        $meta['firmware'] ?? null,
        $meta['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
    ]);
}
