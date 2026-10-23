<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (is_post()) {
    logout_user();
    redirect('login.php');
}

// A direct visit without a POST is treated as a normal sign-out request.
if (current_user() !== null) {
    logout_user();
}
redirect('login.php');
