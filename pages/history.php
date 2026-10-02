<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$recent = get_recent_feedings(100);

$PAGE_TITLE = 'Feeding History';
$PAGE_SUBTITLE = 'See all your feeding commands, most recent first.';
$ACTIVE = 'history';
require __DIR__ . '/../includes/layout_top.php';
?>

<div class="card">
  <?php if ($recent): ?><div class="history-perpage-slot" id="history-perpage-mobile-slot"></div><?php endif; ?>

  <!-- Desktop/tablet: a real table. -->
  <div class="table-wrap history-table-wrap">
  <table class="table" id="history-table">
    <colgroup>
      <col style="width:26%">
      <col style="width:18%">
      <col style="width:18%">
      <col style="width:16%">
      <col style="width:22%">
    </colgroup>
    <thead><tr><th>When</th><th>Source</th><th>Amount</th><th>Portions</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= e(date('M j, g:i A', strtotime($r['created_at']))) ?></td>
          <td><?= e(ucfirst($r['source'])) ?></td>
          <td><?= (int)$r['grams'] ?> g</td>
          <td><?= (int)$r['portions'] ?></td>
          <td><span class="<?= e(feed_status_class($r['status'])) ?>"><?= e(feed_status_label($r['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="5" class="muted">Nothing yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

  <!-- Phones: the same rows as cards, so nothing scrolls horizontally. -->
  <div class="activity history-list" id="history-list">
    <?php foreach ($recent as $r): ?>
      <div class="activity__row" data-history-row>
        <div class="activity__icon"><?= $r['source'] === 'schedule' ? icon('event') : ($r['source'] === 'quick' ? icon('bolt') : feed_icon()) ?></div>
        <div class="activity__main">
          <strong><?= (int)$r['grams'] ?>g &middot; <?= (int)$r['portions'] ?> portions</strong>
          <small><?= e(ucfirst($r['source'])) ?> feed</small>
        </div>
        <div style="text-align:right;">
          <span class="<?= e(feed_status_class($r['status'])) ?>"><?= e(feed_status_label($r['status'])) ?></span>
          <div class="activity__time"><?= e(date('M j, g:i A', strtotime($r['created_at']))) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$recent): ?><p class="muted">Nothing yet.</p><?php endif; ?>
  </div>

  <?php if ($recent): ?>
    <div class="tablepager" id="history-pager">
      <div class="tablepager__info">
        <span id="history-pager-summary"></span>
        <label class="tablepager__perpage" id="history-perpage-wrap">
          Rows per page:
          <select id="history-pager-size">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </label>
      </div>
      <div class="tablepager__nav">
        <button type="button" class="tablepager__btn" id="history-pager-prev">Previous</button>
        <span class="tablepager__pages" id="history-pager-pages"></span>
        <button type="button" class="tablepager__btn" id="history-pager-next">Next</button>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout_bottom.php'; ?>
