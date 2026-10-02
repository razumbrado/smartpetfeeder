<?php
/**
 * Smart Pet Feeder - Core configuration
 * ------------------------------------------------------------------
 * Database connection, app constants and session bootstrap.
 *
 * Passwords and keys (database, ESP32 API key, Gmail SMTP) live in
 * config/secrets.php, which is NOT committed to Git. To set up a fresh copy,
 * copy config/secrets.example.php to config/secrets.php and fill it in.
 */

// ---- Private settings (database, device key, SMTP) ----
if (!is_file(__DIR__ . '/secrets.php')) {
    http_response_code(500);
    die('Missing <code>config/secrets.php</code>. Copy <code>config/secrets.example.php</code> '
        . 'to <code>config/secrets.php</code> and fill in your own values.');
}
require_once __DIR__ . '/secrets.php';

define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
// Public base path (folder inside htdocs). Change if you rename the folder.
define('BASE_URL', '/smartpetfeeder');
define('APP_NAME', 'Smart Pet Feeder');

// DEVICE_API_KEY (shared secret used ONLY by the ESP32 when calling the API)
// is defined in config/secrets.php.

// The hardware is not built yet. While false, the system never claims the
// feeder is physically online and feed commands stay "Pending Hardware".
define('HARDWARE_CONNECTED', false);

// ---- Portion model ----
// 1 portion = 40 grams. Used across the Feed page and schedules.
define('GRAMS_PER_PORTION', 40);
// Upper limit for a single Feed Now command (25 x 40 g = 1000 g).
define('MAX_FEED_PORTIONS', 25);

// ---- Food-level sensor: load cell + HX711 ----
// The ESP32 reads the net weight of food in the hopper (tare the HX711 with
// the EMPTY hopper on the scale) and sends it in grams. The server turns it
// into a percentage of this capacity. Set it to the weight of a FULL hopper.
define('HOPPER_CAPACITY_GRAMS', 2000);
// Once HARDWARE_CONNECTED is true, Feed Now refuses to queue a command unless
// a load-cell reading newer than this many seconds exists.
define('FOOD_READING_MAX_AGE', 120);

// Food level at or below this percent raises a low-food alert.
define('LOW_FOOD_THRESHOLD', 20);

// ---- Email (SMTP) - used to send the Forgot Password OTP ----
// The account, App Password and sender address are in config/secrets.php.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_FROM_NAME', APP_NAME);

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- PDO connection (singleton) ----
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(
            'Database connection failed. Make sure MySQL is running in XAMPP and that '
            . 'you imported <code>database/smartpetfeeder.sql</code>.<br><br>'
            . '<small>' . htmlspecialchars($e->getMessage()) . '</small>'
        );
    }
    return $pdo;
}

// ---- Timezone ----
date_default_timezone_set('Asia/Manila');
