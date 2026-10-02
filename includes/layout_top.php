<?php
/**
 * Shared page shell: <head>, sidebar, topbar.
 * A page sets $PAGE_TITLE and $ACTIVE before including this file.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();

$ACTIVE       = $ACTIVE ?? '';
$PAGE_TITLE   = $PAGE_TITLE ?? APP_NAME;
$PAGE_SUBTITLE = $PAGE_SUBTITLE ?? '';
$user        = current_user();
$unread      = unread_alert_count();
$flash       = flash();

// Where to return to after a profile/password form submits from the modals below.
$backPath = ltrim(substr($_SERVER['REQUEST_URI'], strlen(BASE_URL)), '/');
if ($backPath === '' || str_starts_with($_SERVER['REQUEST_URI'], BASE_URL) === false) {
    $backPath = 'pages/dashboard.php';
}

$nav = [
    'dashboard' => ['label' => 'Dashboard',  'icon' => 'dashboard',     'href' => url('pages/dashboard.php')],
    'feed'      => ['label' => 'Feed Now',   'icon' => 'feed_icon',     'href' => url('pages/feed.php')],
    'schedule'  => ['label' => 'Schedule',   'icon' => 'schedule',      'href' => url('pages/schedule.php')],
    'alerts'    => ['label' => 'Alerts',     'icon' => 'notifications', 'href' => url('pages/alerts.php')],
    'history'   => ['label' => 'Feeding History', 'icon' => 'history',  'href' => url('pages/history.php')],
    'important' => ['label' => 'Important',  'icon' => 'info',          'href' => url('pages/important.php')],
];

$flashIcon = [
    'success' => 'check_circle',
    'warning' => 'warning',
    'error'   => 'error',
    'info'    => 'info',
][$flash['type'] ?? ''] ?? 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($PAGE_TITLE) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<script>
(function () {
    try {
        if (localStorage.getItem('spf-theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        if (localStorage.getItem('spf-sidebar-collapsed') === '1') {
            document.body.classList.add('sidebar-collapsed');
        }
        if (localStorage.getItem('spf-alerts-badge') === '0') {
            document.body.classList.add('alerts-badge-off');
        }
    } catch (e) {}
})();
</script>
<div class="layout">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <aside class="sidebar">
        <div class="sidebar__brand">
            <span class="sidebar__logo"><?= icon('pets') ?></span>
            <span class="sidebar__name">Smart Pet Feeder</span>
            <button type="button" class="sidebar__toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                <?= icon('menu') ?>
            </button>
        </div>

        <nav class="sidebar__nav">
            <?php foreach ($nav as $key => $item): ?>
                <a class="navlink <?= $ACTIVE === $key ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>">
                    <span class="navlink__icon"><?= $item['icon'] === 'feed_icon' ? feed_icon() : icon($item['icon']) ?></span>
                    <span class="navlink__label"><?= e($item['label']) ?></span>
                    <?php if ($key === 'alerts' && $unread > 0): ?>
                        <span class="navlink__badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

    </aside>

    <main class="content">
        <header class="topbar">
            <div class="topbar__lead">
                <button type="button" class="mobile-menu-btn" id="mobile-menu-toggle" aria-label="Open menu">
                    <?= icon('menu') ?>
                </button>
                <div>
                    <h1 class="topbar__title"><a href="<?= url($backPath) ?>"><?= e($PAGE_TITLE) ?></a></h1>
                    <?php if ($PAGE_SUBTITLE !== ''): ?>
                        <p class="topbar__sub"><?= e($PAGE_SUBTITLE) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="topbar__actions">
                <a href="<?= url('pages/alerts.php') ?>" class="topbar-bell" aria-label="Alerts">
                    <?= icon('notifications') ?>
                    <?php if ($unread > 0): ?>
                        <span class="topbar-bell__badge"><?= $unread ?></span>
                    <?php endif; ?>
                </a>

                <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                    <?= icon('dark_mode', 'theme-toggle__dark') ?>
                    <?= icon('light_mode', 'theme-toggle__light') ?>
                </button>

                <div class="user-menu" data-user-menu>
                    <button type="button" class="topbar__user user-menu__trigger" data-user-menu-trigger>
                        <div class="avatar"><?= avatar_inner($user) ?></div>
                        <div>
                            <strong data-user-fullname><?= e(full_name($user)) ?></strong>
                            <small><?= e(role_label($user['role'])) ?></small>
                        </div>
                        <span class="material-symbols-outlined user-menu__chevron">expand_more</span>
                    </button>

                    <div class="user-menu__dropdown" data-user-menu-dropdown hidden>
                        <a class="user-menu__item" href="<?= url('pages/account.php') ?>">
                            <?= icon('account_circle') ?> My Account
                        </a>
                        <a class="user-menu__item" href="<?= url('auth/logout.php') ?>">
                            <?= icon('logout') ?> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash && $flash['type'] !== 'success'): ?>
            <div class="flash flash--<?= e($flash['type']) ?>"><?= icon($flashIcon) ?> <?= e($flash['msg']) ?></div>
        <?php endif; ?>

        <div class="page-body <?= e($PAGE_BODY_CLASS ?? '') ?>">
