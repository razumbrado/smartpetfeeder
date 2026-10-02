<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();

$PAGE_TITLE = 'My Account';
$PAGE_SUBTITLE = 'Manage your profile, password, and preferences.';
$ACTIVE = '';
require __DIR__ . '/../includes/layout_top.php';
?>

<div class="grid account-grid section-gap">
  <div class="account-hero account-grid__hero">
    <form method="post" action="<?= url('actions/avatar_upload.php') ?>" data-action="avatar-upload" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="back" value="<?= e($backPath) ?>">
      <div class="avatar-upload">
        <div class="account-hero__avatar"><?= avatar_inner($user) ?></div>
        <label class="avatar-upload__btn" for="avatar-file" title="Change photo">
          <?= icon('photo_camera') ?>
        </label>
        <input type="file" id="avatar-file" name="avatar" accept="image/png,image/jpeg,image/webp" data-avatar-input hidden>
      </div>
    </form>

    <div class="account-hero__info">
      <div class="account-hero__name" data-user-fullname><?= e(full_name($user)) ?></div>
      <span class="badge"><?= icon('verified_user') ?> <?= e(role_label($user['role'])) ?></span>
    </div>
  </div>

  <div class="card account-grid__settings">
    <div class="card__title"><?= icon('tune') ?> Settings</div>
    <div class="settings-list">
      <button type="button" class="settings-row" data-modal-open="change-password-modal">
        <span class="settings-row__icon"><?= icon('lock') ?></span>
        <span class="settings-row__text">
          <strong>Change Password</strong>
          <small>Update your account password</small>
        </span>
        <span class="material-symbols-outlined settings-row__chevron">chevron_right</span>
      </button>

      <div class="settings-row">
        <span class="settings-row__icon"><?= icon('dark_mode') ?></span>
        <span class="settings-row__text">
          <strong>Dark Mode</strong>
          <small>Switch between light and dark theme</small>
        </span>
        <label class="switch">
          <input type="checkbox" id="account-theme-switch">
          <span class="switch__track"></span>
        </label>
      </div>

      <div class="settings-row">
        <span class="settings-row__icon"><?= icon('notifications') ?></span>
        <span class="settings-row__text">
          <strong>Alert Notifications</strong>
          <small>Show the unread count badge next to Alerts</small>
        </span>
        <label class="switch">
          <input type="checkbox" id="account-alerts-switch">
          <span class="switch__track"></span>
        </label>
      </div>
    </div>
  </div>

  <div class="card account-grid__profile">
    <div class="card__title"><?= icon('badge') ?> Profile Information</div>
    <div class="account-view">
      <div class="account-view__fields">
        <div class="account-view__row">
          <span class="muted">First Name</span>
          <strong data-user-first-name><?= e($user['first_name']) ?></strong>
        </div>
        <div class="account-view__row">
          <span class="muted">Last Name</span>
          <strong data-user-last-name><?= e($user['last_name']) ?></strong>
        </div>
        <div class="account-view__row">
          <span class="muted">Username</span>
          <strong data-user-username><?= e($user['username']) ?></strong>
        </div>
        <div class="account-view__row">
          <span class="muted">Email</span>
          <strong data-user-email><?= e($user['email']) ?></strong>
        </div>
      </div>
    </div>
    <button type="button" class="btn btn--ghost btn--block section-gap" data-modal-open="edit-profile-modal">
      <?= icon('edit') ?> Edit Profile
    </button>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
