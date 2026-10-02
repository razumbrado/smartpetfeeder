<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$food      = get_available_food();
$next      = get_next_scheduled_meal();

$PAGE_TITLE = 'Feed Now';
$PAGE_SUBTITLE = 'Send a feeding command and check the current food level.';
$ACTIVE = 'feed';
require __DIR__ . '/../includes/layout_top.php';
?>


<div class="grid grid--2">
  <!-- ============ Feed Now ============ -->
  <div class="card feed-card">
    <div class="card__title">Select Portion Size</div>
    <form id="feed-now-form" method="post" action="<?= url('actions/feed_now.php') ?>" data-action="feed-now"
          data-food-endpoint="<?= url('api/food-level.php') ?>"
          data-confirm="Are you sure you want to feed <?= portions_to_grams(1) ?> g (1 portion)?">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="source" value="manual">
      <input type="hidden" name="portions" id="portions-input" value="1">

      <div class="feed-layout">
        <!-- Top: predefined portions (3 columns x 2 rows) -->
        <div class="portion-grid" data-portion-grid data-grams-per-portion="<?= GRAMS_PER_PORTION ?>"
             data-max-portions="<?= MAX_FEED_PORTIONS ?>">
          <?php foreach ([1, 2, 3, 4, 5, 6] as $p): ?>
            <div class="portion-opt<?= $p === 1 ? ' is-selected' : '' ?>" data-portions="<?= $p ?>">
              <strong><?= $p ?> Portion<?= $p === 1 ? '' : 's' ?></strong><small><?= portions_to_grams($p) ?> g</small>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Bottom: custom portion (left) + Feed Now (right) -->
        <div class="feed-layout__bottom">
          <div class="feed-layout__custom">
            <label class="field-label" for="custom-portions-input">Custom portion</label>
            <input type="text" id="custom-portions-input" inputmode="numeric" autocomplete="off"
                   placeholder="e.g. 5">
            <p class="portion-error" id="custom-portions-error" hidden></p>
            <p class="muted feed-layout__total">
              Total: <strong id="portion-grams-total">1 portion = <?= portions_to_grams(1) ?> g</strong>
            </p>
          </div>
          <button type="submit" class="btn btn--block feed-layout__btn" data-busy-text="Recording command..."><?= feed_icon() ?> Feed Now</button>
        </div>
      </div>
    </form>
  </div>

  <!-- ============ Current Food Level ============ -->
  <div class="card">
    <div class="card__title">Current Food Level</div>
    <?php $state = food_level_state($food['level_percent']); ?>
    <div class="foodmeter" id="food-meter">
      <div class="hopper">
        <div class="hopper__fill <?= $state === 'warning' ? 'is-warning' : ($state === 'danger' ? 'is-danger' : '') ?>"
             data-food-fill style="height: <?= (int)$food['level_percent'] ?>%;"></div>
      </div>
      <div>
        <div class="foodmeter__pct" data-food-pct><?= (int)$food['level_percent'] ?>%</div>
        <div class="foodmeter__label" data-food-label><?= e($food['label']) ?></div>
        <p class="badge badge--danger" style="margin-top:10px;" data-food-low <?= $food['is_low'] ? '' : 'hidden' ?>><?= icon('warning') ?> Low food &mdash; please refill</p>
        <div class="foodmeter__meta">
          <div class="foodmeter__row"><?= icon('scale') ?>
            <span>Remaining: <strong data-food-grams><?= number_format($food['available_grams']) ?> g</strong> of <?= number_format(HOPPER_CAPACITY_GRAMS) ?> g</span>
          </div>
          <div class="foodmeter__row"><?= icon('sensors') ?>
            <span>Source: <strong data-food-source><?= e($food['source']) ?></strong></span>
          </div>
          <div class="foodmeter__row"><?= icon('update') ?>
            <span>Updated <span data-food-updated><?= e(time_ago($food['created_at'])) ?></span></span>
          </div>
        </div>
      </div>
    </div>
    <?php if ($next): ?>
      <div class="row-between section-gap next-meal" style="border-top:1px solid var(--line);padding-top:16px;">
        <span class="muted"><?= icon('event') ?> Next scheduled meal</span>
        <strong><?= e($next['time_label']) ?> <span class="next-meal__date">(<?= e($next['date_label']) ?>)</span></strong>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ Insufficient Food modal ============ -->
<div class="modal-overlay" id="insufficient-food-modal" data-modal hidden>
  <div class="modal">
    <div class="modal__header">
      <h2><?= icon('warning') ?> Insufficient Food</h2>
      <button type="button" class="modal__close" data-modal-close>&times;</button>
    </div>
    <div class="modal__body">
      <p>There is not enough food in the dispenser for the selected portion.</p>
      <div class="food-check">
        <div class="row-between"><span class="muted">Available Food:</span> <strong data-insufficient-available>0 g</strong></div>
        <div class="row-between"><span class="muted">Required Food:</span> <strong data-insufficient-required>0 g</strong></div>
      </div>
      <p class="muted">Please refill the food container or select a smaller portion.</p>
    </div>
    <div class="modal__footer">
      <button type="button" class="btn" data-modal-close>OK</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
