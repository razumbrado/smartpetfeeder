<?php
/**
 * Authentication helpers for the web UI (session based).
 */
require_once __DIR__ . '/../config/config.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare(
            'SELECT id, first_name, last_name, username, email, role, avatar FROM users WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Redirect to the login page when not authenticated. Call at the top of every protected page. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Password rule for every NEW password (register, change, reset) - the same
 * rule the live hints in assets/js/password_validation.js check.
 */
const PASSWORD_RULE_MESSAGE = 'Password must be at least 8 characters and include an uppercase letter, '
    . 'a lowercase letter, a number, and a special character.';

function is_password_strong_enough(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

/** Attempt a login. Returns true on success. */
function attempt_login(string $identifier, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, password_hash FROM users WHERE username = :u OR email = :e LIMIT 1'
    );
    $id = trim($identifier);
    $stmt->execute([':u' => $id, ':e' => $id]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        return true;
    }
    return false;
}

/**
 * Register a new user account. Returns an array of validation errors
 * (empty on success), and logs the user in immediately on success.
 */
function register_user(string $firstName, string $lastName, string $username, string $email, string $password, string $confirm): array
{
    $errors = [];

    $firstName = trim($firstName);
    $lastName  = trim($lastName);
    $username  = trim($username);
    $email     = trim($email);

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'Please enter your first and last name.';
    }
    if ($username === '' || !preg_match('/^[A-Za-z0-9_.]{3,60}$/', $username)) {
        $errors[] = 'Username must be 3-60 characters (letters, numbers, dot, underscore).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!is_password_strong_enough($password)) {
        $errors[] = PASSWORD_RULE_MESSAGE;
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($errors) {
        return $errors;
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetch()) {
        $errors[] = 'That username or email is already registered.';
        return $errors;
    }

    $stmt = db()->prepare(
        'INSERT INTO users (first_name, last_name, username, email, password_hash, role)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([
        $firstName, $lastName, $username, $email,
        password_hash($password, PASSWORD_DEFAULT), 'owner',
    ]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) db()->lastInsertId();

    return [];
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Very small CSRF helper. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        die('Invalid or expired form token. Please go back and try again.');
    }
}
