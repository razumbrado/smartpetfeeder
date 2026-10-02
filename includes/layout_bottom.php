        </div>
    </main>
</div>

<!-- ============ Edit Profile modal ============ -->
<div class="modal-overlay" id="edit-profile-modal" data-modal hidden>
    <div class="modal">
        <div class="modal__header">
            <h2><?= icon('edit') ?> Edit Profile</h2>
            <button type="button" class="modal__close" data-modal-close>&times;</button>
        </div>
        <form method="post" action="<?= url('actions/profile_update.php') ?>" data-action="profile-update">
            <div class="modal__body">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="back" value="<?= e($backPath) ?>">

                <div class="form-row">
                    <label class="field-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required>
                </div>
                <div class="form-row">
                    <label class="field-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required>
                </div>
                <div class="form-row">
                    <label class="field-label" for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e($user['username']) ?>" required>
                </div>
                <div class="form-row">
                    <label class="field-label" for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
                </div>
            </div>
            <div class="modal__footer">
                <button type="submit" class="btn"><?= icon('save') ?> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Change Password modal ============ -->
<div class="modal-overlay" id="change-password-modal" data-modal hidden>
    <div class="modal">
        <div class="modal__header">
            <h2><?= icon('lock') ?> Change Password</h2>
            <button type="button" class="modal__close" data-modal-close>&times;</button>
        </div>
        <form method="post" action="<?= url('actions/password_change.php') ?>" data-action="password-change">
            <div class="modal__body">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="back" value="<?= e($backPath) ?>">

                <div class="form-row">
                    <label class="field-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required
                           data-pw="required">
                </div>
                <div class="form-row">
                    <label class="field-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required
                           data-pw="strong">
                </div>
                <div class="form-row">
                    <label class="field-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required
                           data-pw-confirm="#new_password">
                </div>
            </div>
            <div class="modal__footer">
                <button type="submit" class="btn"><?= icon('save') ?> Update Password</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ Confirm modal (replaces window.confirm for [data-confirm] forms) ============ -->
<div class="modal-overlay" id="confirm-modal" data-modal hidden>
    <div class="modal">
        <div class="modal__header">
            <h2><?= icon('help') ?> Please Confirm</h2>
            <button type="button" class="modal__close" data-modal-close>&times;</button>
        </div>
        <div class="modal__body">
            <p id="confirm-modal-text"></p>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
            <button type="button" class="btn btn--danger" id="confirm-modal-ok">Confirm</button>
        </div>
    </div>
</div>

<script src="<?= url('assets/js/password_validation.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/password_validation.js') ?>"></script>
<script src="<?= url('assets/js/main.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
