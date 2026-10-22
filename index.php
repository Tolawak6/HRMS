<?php
declare(strict_types=1);

/*
 * Legacy entry point. The public site is home.php; staff sign in through
 * login.php. Keep this file so old bookmarks and links continue to work.
 */
require __DIR__ . '/app/bootstrap.php';

redirect(current_user() !== null ? 'dashboard.php' : 'login.php');
