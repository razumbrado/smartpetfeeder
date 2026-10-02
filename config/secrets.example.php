<?php
/**
 * TEMPLATE for the private settings file.
 *
 * Setup: copy this file to  config/secrets.php  and fill in your own values.
 * config/secrets.php is in .gitignore, so your real passwords never go to GitHub.
 */

// ---- Database (XAMPP defaults) ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'smartpetfeeder');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- ESP32 device API key (sent by the device as the X-Device-Key header) ----
// Use a long random string, and put the same value in the ESP32 firmware.
define('DEVICE_API_KEY', 'CHANGE-ME-ESP32-SECRET-KEY');

// ---- Email (SMTP) for the Forgot Password OTP ----
// Gmail: turn on 2-Step Verification, create an App Password
// (Google Account -> Security -> App passwords) and paste it below.
define('SMTP_USERNAME', '');            // e.g. yourname@gmail.com
define('SMTP_PASSWORD', '');            // 16-character Gmail App Password
define('SMTP_FROM_EMAIL', '');          // usually the same as SMTP_USERNAME
