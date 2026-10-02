<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

refresh_dynamic_alerts();

$stats  = get_dashboard_stats();
$food   = $stats['food_level'];
$next   = $stats['next_meal'];
$recent = get_recent_feedings(4);

$cfToday = date('Y-m-d');
$completedCount = get_completed_feedings_count($cfToday, $cfToday);
$completedHistoricalMax = get_max_daily_completed_count();
$completedMax     = max($completedCount, $completedHistoricalMax, 1);
$completedPercent = (int) round(($completedCount / $completedMax) * 100);

$PAGE_TITLE = 'Dashboard';
$PAGE_SUBTITLE = "An overview of your feeder's status and recent activity.";
$ACTIVE = 'dashboard';
require __DIR__ . '/../includes/layout_top.php';
?>


<div class="grid grid--stats">
  <div class="card">
    <div class="stat__icon"><?= feed_icon() ?></div>
    <div class="stat__label">Food Dispensed Today</div>
    <div class="stat__value"><?= (int)$stats['today_grams'] ?> g</div>
    <div class="stat__hint"><?= (int)$stats['today_count'] ?> feeding command(s) recorded</div>
  </div>

  <div class="card">
    <div class="stat__icon"><?= icon('history') ?></div>
    <div class="stat__label">Last Fed</div>
    <?php if ($stats['last_fed']): ?>
      <div class="stat__value"><?= e(time_ago($stats['last_fed']['created_at'])) ?></div>
      <div class="stat__hint">
        <?= (int)$stats['last_fed']['grams'] ?>g &middot;
        <?= e(feed_status_label($stats['last_fed']['status'])) ?>
      </div>
    <?php else: ?>
      <div class="stat__value">&mdash;</div>
      <div class="stat__hint">No feedings yet</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="stat__icon"><?= icon('event') ?></div>
    <div class="stat__label">Next Scheduled Meal</div>
    <?php if ($next): ?>
      <div class="stat__value"><?= e($next['time_label']) ?></div>
      <div class="stat__hint"><?= e($next['date_label']) ?> &middot; <?= (int)$next['portions'] ?> portions (<?= (int)$next['grams'] ?>g)</div>
    <?php else: ?>
      <div class="stat__value">&mdash;</div>
      <div class="stat__hint">No active schedules</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="stat__icon"><?= icon('inventory_2') ?></div>
    <div class="stat__label">Current Food Level</div>
    <div class="stat__value"><?= (int)$food['level_percent'] ?>%</div>
    <div class="stat__hint"><?= e($food['label']) ?> &middot; source: <?= e($food['source']) ?></div>
  </div>
</div>

<div class="grid grid--2 section-gap">
  <div class="card">
    <div class="card__title">Recent Feeding Activity <small>latest 4</small></div>
    <div class="activity">
      <?php if (!$recent): ?>
        <p class="muted">No feeding activity yet. Use <a href="<?= url('pages/feed.php') ?>">Feed Now</a> to record one.</p>
      <?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <div class="activity__row">
          <div class="activity__icon"><?= $r['source'] === 'schedule' ? icon('event') : ($r['source'] === 'quick' ? icon('bolt') : feed_icon()) ?></div>
          <div class="activity__main">
            <strong><?= (int)$r['grams'] ?>g &middot; <?= (int)$r['portions'] ?> portions</strong>
            <small><?= e(ucfirst($r['source'])) ?> feed
              <?php if ($r['feed_time']): ?>&middot; sched <?= e(time_label($r['feed_time'])) ?><?php endif; ?>
            </small>
          </div>
          <div style="text-align:right;">
            <span class="<?= e(feed_status_class($r['status'])) ?>"><?= e(feed_status_label($r['status'])) ?></span>
            <div class="activity__time"><?= e(time_ago($r['created_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card" id="completed-feedings" data-endpoint="<?= url('actions/completed_feedings.php') ?>">
    <div class="card__title">
      Completed Feedings

      <div class="daterange" id="cf-daterange">
        <button type="button" class="daterange__trigger" id="cf-trigger">
          <?= icon('calendar_month') ?>
          <span id="cf-trigger-label"></span>
          <?= icon('expand_more') ?>
        </button>

        <input type="date" id="cf-from" value="<?= e($cfToday) ?>" max="<?= e($cfToday) ?>" hidden>
        <input type="date" id="cf-to" value="<?= e($cfToday) ?>" max="<?= e($cfToday) ?>" hidden>

        <div class="daterange__panel" id="cf-panel" hidden>
        <div class="daterange__fields">
          <div class="daterange__field" id="cf-field-from"></div>
          <span class="daterange__arrow"><?= icon('arrow_forward') ?></span>
          <div class="daterange__field" id="cf-field-to"></div>
        </div>

        <div class="daterange__calhead">
          <button type="button" class="daterange__navbtn" id="cf-prev" aria-label="Previous month"><?= icon('chevron_left') ?></button>
          <div class="daterange__month" id="cf-month"></div>
          <button type="button" class="daterange__navbtn" id="cf-next" aria-label="Next month"><?= icon('chevron_right') ?></button>
        </div>
        <div class="daterange__weekdays">
          <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
        </div>
        <div class="daterange__days" id="cf-days"></div>
        </div>
      </div>
    </div>

    <div class="completedchart">
      <div class="completedchart__track">
        <div class="completedchart__bar" id="cf-bar" style="height: <?= $completedPercent ?>%;"></div>
      </div>
      <div class="completedchart__info">
        <div class="completedchart__count" id="cf-count"><?= $completedCount ?></div>
        <div class="completedchart__label" id="cf-label">Completed Feeding<?= $completedCount === 1 ? '' : 's' ?></div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
