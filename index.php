<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
redirect(is_logged_in() ? 'pages/dashboard.php' : 'auth/login.php');
