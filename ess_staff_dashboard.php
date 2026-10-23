<?php
declare(strict_types=1);

/* Legacy entry point retained for existing bookmarks. */
require __DIR__ . '/app/bootstrap.php';

redirect(current_user() !== null ? 'dashboard.php' : 'login.php');
