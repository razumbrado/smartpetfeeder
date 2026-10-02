<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$device = get_device_status();
$food   = get_food_level();

$PAGE_TITLE = 'Important';
$PAGE_SUBTITLE = 'System status and food level controls.';
$ACTIVE = 'important';
require __DIR__ . '/../includes/layout_top.php';
?>


<div class="grid grid--2">
  <div class="card">
    <div class="card__title">System Status</div>
    <table class="table">
      <colgroup>
        <col style="width:42%">
        <col style="width:58%">
      </colgroup>
      <tbody>
        <tr><td>Web application</td><td><span class="badge badge--success">Running</span></td></tr>
        <tr><td>Database</td><td><span class="badge badge--success">Connected</span></td></tr>
        <tr><td>ESP32 device</td><td><span class="badge badge--warning"><?= e($device['label']) ?></span></td></tr>
        <tr><td>Servo / dispenser</td><td><span class="badge badge--warning">Pending Hardware</span></td></tr>
        <tr><td>Food-level sensor (load cell + HX711)</td><td><span class="badge badge--warning">Not connected (using stored value)</span></td></tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card__title">Food Level (manual / demo)</div>
    <p class="muted" style="margin-bottom:12px;">
      Current: <strong id="food-level-current-pct"><?= (int)$food['level_percent'] ?>%</strong> &mdash; <span id="food-level-current-label"><?= e($food['label']) ?></span>.
      Set a value to preview the dashboard indicator. Later the ESP32 will overwrite this with the
      food weight from the load cell + HX711 (full hopper = <?= number_format(HOPPER_CAPACITY_GRAMS) ?> g).
    </p>
    <form method="post" action="<?= url('actions/food_level_set.php') ?>" data-action="food-level-set">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="form-row">
        <label class="field-label" for="level_percent">Food level %</label>
        <input type="number" id="level_percent" name="level_percent" min="0" max="100" step="1"
               value="<?= (int)$food['level_percent'] ?>">
      </div>
      <button class="btn btn--block" type="submit"><?= icon('save') ?> Update Food Level</button>
      <p class="muted" style="margin-top:8px;">Values at or below <?= LOW_FOOD_THRESHOLD ?>% raise a Low Food alert automatically.</p>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
