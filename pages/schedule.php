<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$schedules = get_schedules();

$PAGE_TITLE = 'Feeding Schedule';
$PAGE_SUBTITLE = 'Set up automatic feedings and manage when they run.';
$ACTIVE = 'schedule';
require __DIR__ . '/../includes/layout_top.php';
?>

<div class="card">
  <div class="card__title" style="display:flex;align-items:center;justify-content:space-between;">
    All Schedules
    <button type="button" class="btn" data-schedule-add><?= icon('add_circle') ?> Add Schedule</button>
  </div>
  <div class="schedule-list" id="schedule-list">
    <?php if (!$schedules): ?>
      <p class="muted" data-schedule-empty>No schedules yet. Click "Add Schedule" to create one.</p>
    <?php endif; ?>
    <?php foreach ($schedules as $s): ?>
      <div class="schedule-item" data-schedule-id="<?= (int)$s['id'] ?>" data-feed-time="<?= e(substr($s['feed_time'], 0, 5)) ?>"
          data-feed-date="<?= e($s['feed_date']) ?>" data-portions="<?= (int)$s['portions'] ?>">
        <?php [$statusLabel, $statusClass] = schedule_status($s['feed_date'], $s['feed_time']); ?>
        <div class="schedule-item__head">
          <div class="schedule-item__icon"><?= icon('schedule') ?></div>
          <div class="schedule-item__main">
            <strong><?= e(time_label($s['feed_time'])) ?></strong>
            <span class="muted"><?= e(date_label($s['feed_date'])) ?></span>
          </div>
        </div>
        <div class="schedule-item__stats">
          <span><?= icon('restaurant') ?> <?= (int)$s['portions'] ?> portion<?= $s['portions'] > 1 ? 's' : '' ?></span>
          <span><?= icon('scale') ?> <?= (int)$s['grams'] ?> g</span>
          <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
        </div>
        <div class="schedule-item__actions">
          <button type="button" class="btn btn--ghost btn--sm" data-schedule-edit><?= icon('edit') ?> Edit</button>
          <form method="post" action="<?= url('actions/schedule_delete.php') ?>"
                data-action="schedule-delete" data-confirm="Delete this feeding schedule?">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn--danger btn--sm" type="submit"><?= icon('delete') ?> Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal-overlay" id="schedule-modal" data-modal hidden>
  <div class="modal">
    <div class="modal__header">
      <h2><span class="material-symbols-outlined" data-modal-icon>add_circle</span> <span data-modal-title>Add Feeding Schedule</span></h2>
      <button type="button" class="modal__close" data-modal-close>&times;</button>
    </div>
    <div class="modal__body">
      <form id="schedule-form" method="post" action="<?= url('actions/schedule_save.php') ?>" data-action="schedule-save">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="" data-field="id">

        <div class="form-grid-2">
          <div class="form-row">
            <label class="field-label" for="feed_date">Feeding date</label>
            <input type="date" id="feed_date" name="feed_date" min="<?= date('Y-m-d') ?>" required>
          </div>

          <div class="form-row">
            <label class="field-label" for="feed_time">Feeding time</label>
            <input type="time" id="feed_time" name="feed_time" required>
          </div>
        </div>

        <div class="form-row">
          <label class="field-label" for="portions">Portion size</label>
          <input type="text" id="portions" name="portions" inputmode="numeric" autocomplete="off"
                 placeholder="Enter number of portions (e.g. 2)"
                 data-grams-per-portion="<?= GRAMS_PER_PORTION ?>" data-max-portions="<?= MAX_FEED_PORTIONS ?>">
          <p class="portion-error" id="schedule-portions-error" hidden></p>
          <p class="muted" style="margin-top:8px;">
            Total: <strong id="schedule-portions-total">&mdash;</strong>
          </p>
        </div>

      </form>
    </div>
    <div class="modal__footer">
      <button type="button" class="btn btn--ghost" data-modal-close><?= icon('close') ?> Cancel</button>
      <button type="submit" form="schedule-form" class="btn">
        <span class="material-symbols-outlined" data-submit-icon>add_circle</span> <span data-submit-text>Add Schedule</span>
      </button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
