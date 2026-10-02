<?php
/**
 * Forgot-password flow:  1. email -> OTP sent   2. OTP verified   3. new password
 *
 * The one-time state (hashed code, expiry, attempts, verified flag) lives only
 * in the PHP session - nothing is stored in the database - and is cleared once
 * the password is reset, so a step can't be skipped or reused.
 */
require_once __DIR__ . '/auth.php';   // is_password_strong_enough()

/** The emailed code is valid for 1 minute; a new one can be requested after that. */
const PASSWORD_RESET_OTP_TTL = 60;
/** Once the code is verified, time left to set the new password. */
const PASSWORD_RESET_SESSION_TTL = 600;
const PASSWORD_RESET_MAX_ATTEMPTS = 5;
const PASSWORD_RESET_SESSION_KEY = 'password_reset';

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT id, first_name, last_name, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

/** The pending reset for this email in this browser session, or null. */
function active_password_reset(string $email): ?array
{
    $reset = $_SESSION[PASSWORD_RESET_SESSION_KEY] ?? null;
    if (!is_array($reset) || strcasecmp((string) $reset['email'], $email) !== 0) {
        return null;
    }
    return $reset;
}

function save_password_reset(array $reset): void
{
    $_SESSION[PASSWORD_RESET_SESSION_KEY] = $reset;
}

function clear_password_reset(): void
{
    unset($_SESSION[PASSWORD_RESET_SESSION_KEY]);
}

/** Creates a new 6-digit code for $email (replacing any earlier one) and returns it. */
function create_password_reset_otp(string $email): string
{
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    save_password_reset([
        'email'      => $email,
        'otp_hash'   => password_hash($otp, PASSWORD_DEFAULT),
        'created_at' => time(),
        'expires_at' => time() + PASSWORD_RESET_OTP_TTL,
        'attempts'   => 0,
        'verified'   => false,
    ]);
    return $otp;
}

/** Seconds until another code may be sent (0 = now). */
function password_reset_resend_cooldown(): int
{
    $reset = $_SESSION[PASSWORD_RESET_SESSION_KEY] ?? null;
    if (!is_array($reset) || !empty($reset['verified'])) {
        return 0;
    }
    return max(0, PASSWORD_RESET_OTP_TTL - (time() - (int) $reset['created_at']));
}

function verify_password_reset_otp(string $email, string $otp): array
{
    $reset = active_password_reset($email);
    if ($reset === null) {
        return ['success' => false, 'message' => 'No pending reset request found. Please request a new code.'];
    }
    if ($reset['expires_at'] < time()) {
        return ['success' => false, 'message' => 'This code has expired. Please request a new one.'];
    }
    if ($reset['attempts'] >= PASSWORD_RESET_MAX_ATTEMPTS) {
        return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
    }
    if (!password_verify($otp, $reset['otp_hash'])) {
        $reset['attempts']++;
        save_password_reset($reset);
        return ['success' => false, 'message' => 'Incorrect code. Please try again.'];
    }

    $reset['verified'] = true;
    // The 1-minute limit is only for entering the code; give time for the new password.
    $reset['expires_at'] = time() + PASSWORD_RESET_SESSION_TTL;
    save_password_reset($reset);

    return ['success' => true, 'message' => 'Code verified.'];
}

/** Checks the OTP step passed, then clears the reset so it can't be used twice. */
function consume_verified_password_reset(string $email): array
{
    $reset = active_password_reset($email);
    if ($reset === null || !$reset['verified']) {
        return ['success' => false, 'message' => 'Please verify the OTP code first.', 'restart' => true];
    }
    if ($reset['expires_at'] < time()) {
        clear_password_reset();
        return ['success' => false, 'message' => 'This session has expired. Please request a new code.', 'restart' => true];
    }
    clear_password_reset();
    return ['success' => true, 'message' => 'Verified.'];
}

/** JSON request body + CSRF check for the forgot-password endpoints. */
function password_reset_request(): array
{
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'POST required.']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($input['csrf'] ?? ''))) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Your session expired. Please reload the page and try again.']);
        exit;
    }
    return $input;
}
