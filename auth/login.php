<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('pages/dashboard.php');
}

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';

$loginError    = '';
$loginOld      = ['identifier' => ''];
$registerErrors = [];
$registerOld   = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['auth_form'] ?? '') === 'register') {
        $mode = 'register';
        $registerOld['first_name'] = $_POST['first_name'] ?? '';
        $registerOld['last_name']  = $_POST['last_name'] ?? '';
        $registerOld['username']   = $_POST['username'] ?? '';
        $registerOld['email']      = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $registerErrors = register_user(
            $registerOld['first_name'], $registerOld['last_name'], $registerOld['username'], $registerOld['email'],
            $password, $confirm
        );

        if (!$registerErrors) {
            redirect('pages/dashboard.php');
        }
    } else {
        $mode = 'login';
        $loginOld['identifier'] = $_POST['identifier'] ?? '';
        $pw = $_POST['password'] ?? '';
        if ($loginOld['identifier'] === '' || $pw === '') {
            $loginError = 'Please enter your username/email and password.';
        } elseif (attempt_login($loginOld['identifier'], $pw)) {
            redirect('pages/dashboard.php');
        } else {
            $loginError = 'Invalid credentials. Please try again.';
        }
    }
}

$isRegister = $mode === 'register';

$PAGE_TITLE = ($isRegister ? 'Register' : 'Login') . ' &middot; ' . e(APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $PAGE_TITLE ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<script>
(function () {
    try {
        if (localStorage.getItem('spf-theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (e) {}
})();
</script>
<div class="login-wrap">
  <div class="auth-card<?= $isRegister ? ' auth-card--register' : '' ?>" id="auth-card">

    <div class="auth-form-panel auth-form-panel--login" id="panel-login"<?= $isRegister ? ' hidden' : '' ?>>
      <form class="auth-form auth-form--login" method="post" action="<?= url('auth/login.php') ?>">
        <input type="hidden" name="auth_form" value="login">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="login-brand"><span class="sidebar__logo"><?= icon('pets') ?></span> Smart Pet Feeder</div>
        <h1>SIGN IN</h1>
        <p class="sub">Sign in to manage and monitor your feeder.</p>

        <?php if ($loginError): ?><div class="login-error"><?= icon('error') ?> <?= e($loginError) ?></div><?php endif; ?>

        <div class="form-row">
          <label class="field-label" for="identifier">Username or Email</label>
          <input type="text" id="identifier" class="ph-mobile" placeholder="Username or Email" name="identifier" autocomplete="username"
                 value="<?= e($loginOld['identifier']) ?>" required<?= $isRegister ? '' : ' autofocus' ?>>
        </div>

        <div class="form-row">
          <label class="field-label" for="password">Password</label>
          <input type="password" id="password" class="ph-mobile" placeholder="Password" name="password" autocomplete="current-password" required
                 data-pw="required">
        </div>

        <a href="#" class="forgot-link" id="forgot-link">Forgot your password?</a>

        <button type="submit" class="btn btn--block"><?= icon('login') ?> Sign In</button>

        <p class="auth-mobile-switch">Don't have an account?
          <a href="<?= url('auth/login.php?mode=register') ?>" data-switch="register">Register</a>
        </p>
      </form>

      <!-- ============ Forgot password: email -> OTP -> new password ============ -->
      <div class="auth-form auth-form--login" id="forgot-view" hidden
           data-csrf="<?= e(csrf_token()) ?>"
           data-send-url="<?= url('auth/forgot_password.php') ?>"
           data-verify-url="<?= url('auth/verify_reset_otp.php') ?>"
           data-reset-url="<?= url('auth/reset_password.php') ?>">
        <a href="#" class="back-link" id="back-to-login"><?= icon('arrow_back') ?> Back to Login</a>
        <div class="login-brand"><span class="sidebar__logo"><?= icon('pets') ?></span> Smart Pet Feeder</div>

        <div class="reset-step" data-step="email">
          <h1>Forgot Password</h1>
          <p class="sub">Enter your registered email to receive a verification code.</p>
          <div class="auth-alert" data-alert hidden></div>
          <div class="form-row">
            <label class="field-label" for="reset-email">Email</label>
            <input type="email" id="reset-email" class="ph-mobile" autocomplete="email" placeholder="Email">
            <p class="field-error" data-error="email"></p>
          </div>
          <button type="button" class="btn btn--block" id="send-otp-btn"><?= icon('mail') ?> Send Code</button>
        </div>

        <div class="reset-step" data-step="otp" hidden>
          <h1>Enter Verification Code</h1>
          <p class="sub">We sent a 6-digit code to <strong id="otp-email-display"></strong>.</p>
          <div class="auth-alert" data-alert hidden></div>
          <div class="otp-boxes" id="otp-boxes">
            <?php for ($d = 1; $d <= 6; $d++): ?>
              <input type="password" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]*"
                     autocomplete="<?= $d === 1 ? 'one-time-code' : 'off' ?>" aria-label="Code digit <?= $d ?>">
            <?php endfor; ?>
          </div>
          <p class="field-error field-error--center" data-error="otp"></p>
          <div class="otp-actions">
            <button type="button" class="otp-link" id="toggle-otp-btn"><?= icon('visibility') ?> <span>Show Code</span></button>
            <button type="button" class="otp-link" id="resend-otp-btn" disabled>Resend Code</button>
          </div>
          <p class="otp-timer" id="otp-timer" aria-live="polite"></p>
          <button type="button" class="btn btn--block" id="verify-otp-btn"><?= icon('verified') ?> Verify Code</button>
        </div>

        <div class="reset-step" data-step="reset" hidden>
          <h1>Reset Password</h1>
          <p class="sub">Enter your new password.</p>
          <div class="auth-alert" data-alert hidden></div>
          <div class="form-row">
            <label class="field-label" for="new-password">New Password</label>
            <input type="password" id="new-password" class="ph-mobile" placeholder="New Password" autocomplete="new-password">
            <p class="password-hint" id="password-strength-hint"></p>
          </div>
          <div class="form-row">
            <label class="field-label" for="confirm-new-password">Confirm Password</label>
            <input type="password" id="confirm-new-password" class="ph-mobile" placeholder="Confirm Password" autocomplete="new-password">
            <p class="password-hint" id="password-match-hint"></p>
          </div>
          <button type="button" class="btn btn--block" id="reset-password-btn"><?= icon('lock_reset') ?> Reset Password</button>
        </div>

        <div class="reset-step" data-step="done" hidden>
          <div class="reset-done-icon"><?= icon('check_circle') ?></div>
          <h1>Password Reset!</h1>
          <p class="sub">Your password has been changed successfully. You can now log in.</p>
          <button type="button" class="btn btn--block" id="reset-done-btn"><?= icon('login') ?> Back to Login</button>
        </div>
      </div>
    </div>

    <div class="auth-form-panel auth-form-panel--register" id="panel-register"<?= $isRegister ? '' : ' hidden' ?>>
      <form class="auth-form" method="post" action="<?= url('auth/login.php') ?>">
        <input type="hidden" name="auth_form" value="register">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="login-brand"><span class="sidebar__logo"><?= icon('pets') ?></span> Smart Pet Feeder</div>
        <h1>Create Account</h1>
        <p class="sub">Register to manage and monitor your feeder.</p>

        <?php foreach ($registerErrors as $err): ?>
          <div class="login-error"><?= icon('error') ?> <?= e($err) ?></div>
        <?php endforeach; ?>

        <div class="form-grid-2">
          <div class="form-row">
            <label class="field-label" for="first_name">First Name</label>
            <input type="text" id="first_name" class="ph-mobile" placeholder="First Name" name="first_name" autocomplete="given-name"
                   value="<?= e($registerOld['first_name']) ?>" required<?= $isRegister ? ' autofocus' : '' ?>>
          </div>
          <div class="form-row">
            <label class="field-label" for="last_name">Last Name</label>
            <input type="text" id="last_name" class="ph-mobile" placeholder="Last Name" name="last_name" autocomplete="family-name"
                   value="<?= e($registerOld['last_name']) ?>" required>
          </div>
        </div>

        <div class="form-row">
          <label class="field-label" for="username">Username</label>
          <input type="text" id="username" class="ph-mobile" placeholder="Username" name="username" autocomplete="username"
                 value="<?= e($registerOld['username']) ?>" required>
        </div>

        <div class="form-row">
          <label class="field-label" for="email">Email</label>
          <input type="email" id="email" class="ph-mobile" placeholder="Email" name="email" autocomplete="email"
                 value="<?= e($registerOld['email']) ?>" required>
        </div>

        <div class="form-grid-2">
          <div class="form-row">
            <label class="field-label" for="reg_password">Password</label>
            <input type="password" id="reg_password" class="ph-mobile" placeholder="Password" name="password" autocomplete="new-password" required
                   data-pw="strong">
          </div>
          <div class="form-row">
            <label class="field-label" for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" class="ph-mobile" placeholder="Confirm Password" name="confirm_password" autocomplete="new-password" required
                   data-pw-confirm="#reg_password">
          </div>
        </div>

        <button type="submit" class="btn btn--block"><?= icon('person_add') ?> Create Account</button>

        <p class="auth-mobile-switch">Already have an account?
          <a href="<?= url('auth/login.php?mode=login') ?>" data-switch="login">Sign In</a>
        </p>
      </form>
    </div>

    <div class="auth-overlay">
      <div class="auth-overlay__track">
        <div class="auth-overlay__panel auth-overlay__panel--register-prompt">
          <div class="auth-overlay__copy">
            <span class="auth-overlay__eyebrow"><?= icon('pets') ?> Smart Feeding, Happy Pets</span>
            <h2>Everything<br><span class="accent">Your Pet Needs,</span><br>One Smart Feeder.</h2>
            <p>New here? Create an account and start managing your Smart Pet Feeder in minutes.</p>
            <a href="<?= url('auth/login.php?mode=register') ?>" class="btn btn--overlay" data-switch="register">Register Now</a>
          </div>
        </div>
        <div class="auth-overlay__panel auth-overlay__panel--login-prompt">
          <div class="auth-overlay__copy">
            <span class="auth-overlay__eyebrow"><?= icon('pets') ?> Join Smart Pet Feeder</span>
            <h2>Healthy Meals,<br><span class="accent">Right on Time,</span><br>Every Day.</h2>
            <p>Already have an account? Sign in to keep an eye on feedings and schedules.</p>
            <a href="<?= url('auth/login.php?mode=login') ?>" class="btn btn--overlay" data-switch="login">Back to Login</a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  var card = document.getElementById('auth-card');
  var loginPanel = document.getElementById('panel-login');
  var registerPanel = document.getElementById('panel-register');
  if (!card || !loginPanel || !registerPanel) return;

  function setMode(mode) {
    var toRegister = mode === 'register';
    card.classList.toggle('auth-card--register', toRegister);
    loginPanel.hidden = toRegister;
    registerPanel.hidden = !toRegister;
    if (window.history && window.history.replaceState) {
      var url = window.location.pathname + '?mode=' + (toRegister ? 'register' : 'login');
      window.history.replaceState(null, '', url);
    }
    var active = toRegister ? registerPanel : loginPanel;
    var focusEl = active.querySelector('input:not([type=hidden])');
    if (focusEl) { try { focusEl.focus({ preventScroll: true }); } catch (e) {} }
  }

  document.querySelectorAll('[data-switch]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      ev.preventDefault();
      setMode(el.dataset.switch);
    });
  });
})();
</script>
<script src="<?= url('assets/js/password_validation.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/password_validation.js') ?>"></script>
<script src="<?= url('assets/js/forgot_password.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/forgot_password.js') ?>"></script>
</body>
</html>
