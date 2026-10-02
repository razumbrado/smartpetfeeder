<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

refresh_dynamic_alerts();

$alerts = get_alerts(60);

// Facebook-style grouping: unread first regardless of age, then read ones
// split by day. Order within each group is already newest-first (get_alerts
// sorts is_read ASC, created_at DESC), so this only needs to bucket them.
$today = date('Y-m-d');
$sections = ['new' => [], 'today' => [], 'earlier' => []];
foreach ($alerts as $a) {
    if (!$a['is_read']) {
        $sections['new'][] = $a;
    } elseif (substr($a['created_at'], 0, 10) === $today) {
        $sections['today'][] = $a;
    } else {
        $sections['earlier'][] = $a;
    }
}
$sectionLabels = ['new' => 'New', 'today' => 'Today', 'earlier' => 'Earlier'];

// Keep the page from opening with a huge wall of old notifications: only
// the first few "Earlier" ones render up front, the rest stay in the DOM
// (hidden) behind a "See previous notifications" button.
$earlierVisibleCount = 8;
$earlierHiddenCount = max(0, count($sections['earlier']) - $earlierVisibleCount);

$PAGE_TITLE = 'Alerts';
$PAGE_SUBTITLE = 'Notifications about feedings, schedules, and your device.';
$ACTIVE = 'alerts';
require __DIR__ . '/../includes/layout_top.php';
?>

<div class="card">
  <div class="alerts-toolbar">
    <small id="unread-count" class="alerts-toolbar__count"><?= count(array_filter($alerts, fn($a) => !$a['is_read'])) ?> unread</small>
    <div class="alerts-toolbar__actions">
      <label class="btn btn--ghost btn--sm alerts-toolbar__selectall">
        <input type="checkbox" id="alerts-select-all" style="width:16px;height:16px;margin-right:6px;accent-color:var(--gold-deep);vertical-align:-3px;">
        Select All
      </label>

      <div class="alerts-toolbar__more" data-more-menu>
        <button type="button" class="alert-item__more-btn" data-more-trigger aria-label="More actions">
          <?= icon('more_vert') ?>
        </button>
        <div class="alerts-toolbar__more-panel">
          <form method="post" action="<?= url('actions/alert_delete.php') ?>" data-action="alert-delete"
                data-collect-checked=".alert-select-cb" data-confirm="Delete the selected notifications?">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn--danger btn--sm" type="submit" id="alerts-delete-selected" disabled><?= icon('delete') ?> Delete Selected</button>
          </form>
          <form method="post" action="<?= url('actions/alert_read.php') ?>" data-action="alert-read">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="-1">
            <button class="btn btn--ghost btn--sm" type="submit"><?= icon('done_all') ?> Mark all read</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div id="alerts-list">
    <?php if (!$alerts): ?>
      <p class="muted" data-alerts-empty>No alerts. You're all caught up.</p>
    <?php endif; ?>

    <?php foreach ($sections as $sectionKey => $items): ?>
      <?php if (!$items) continue; ?>
      <div class="alerts-section" data-alerts-section="<?= e($sectionKey) ?>">
        <div class="alerts-section__label"><?= e($sectionLabels[$sectionKey]) ?></div>
        <?php foreach ($items as $i => $a): ?>
          <?php $isHiddenExtra = $sectionKey === 'earlier' && $i >= $earlierVisibleCount; ?>
          <div class="alert-item alert-sev--<?= e($a['severity']) ?> <?= $a['is_read'] ? '' : 'is-unread' ?>"
               data-alert-id="<?= (int)$a['id'] ?>" <?= $isHiddenExtra ? 'hidden data-alerts-more' : '' ?>>
            <input type="checkbox" class="alert-select-cb" value="<?= (int)$a['id'] ?>" style="width:18px;height:18px;margin-top:8px;accent-color:var(--gold-deep);">
            <div class="alert-item__body">
              <strong><?= e($a['title']) ?></strong>
              <span class="badge badge--<?= $a['severity'] === 'critical' ? 'danger' : ($a['severity'] === 'warning' ? 'warning' : 'info') ?>" style="margin-left:6px;">
                <?= e(ucfirst($a['severity'])) ?>
              </span>
              <p><?= e($a['message']) ?></p>
              <time><?= e(date('M j, Y g:i A', strtotime($a['created_at']))) ?> &middot; <?= e(time_ago($a['created_at'])) ?></time>
            </div>
            <div class="alert-item__menu" data-more-menu>
              <button type="button" class="alert-item__more-btn" data-more-trigger aria-label="More actions">
                <?= icon('more_vert') ?>
              </button>
              <div class="alert-item__actions">
                <?php if (!$a['is_read']): ?>
                  <form method="post" action="<?= url('actions/alert_read.php') ?>" data-action="alert-read">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn--ghost btn--sm" type="submit"><?= icon('check') ?> Mark read</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= url('actions/alert_delete.php') ?>" data-action="alert-delete" data-confirm="Delete this notification?">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="ids[]" value="<?= (int)$a['id'] ?>">
                  <button class="btn btn--danger btn--sm" type="submit"><?= icon('delete') ?> Delete</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($earlierHiddenCount > 0): ?>
      <button type="button" class="btn btn--ghost btn--block section-gap" id="alerts-see-previous">
        <?= icon('expand_more') ?> See previous notifications
      </button>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
