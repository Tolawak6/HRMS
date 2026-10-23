<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

$publicTitle = $publicTitle ?? 'Ethiopian Statistical Service';
$publicActive = $publicActive ?? '';
$publicUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($publicTitle) ?> · ESS HRMS</title>
<link rel="icon" href="<?= e(url('images/logo.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="public-page">
<div class="public-header">
    <div class="container">
        <nav class="navbar navbar-expand-lg p-0" aria-label="Public navigation">
            <a class="public-brand" href="<?= e(url('home.php')) ?>">
                <img src="<?= e(url('images/logo.png')) ?>" alt="Ethiopian Statistical Service emblem">
                <span>
                    <span class="name d-block">Ethiopian Statistical Service</span>
                    <span class="sub d-block">Human Resource Management System</span>
                </span>
            </a>
            <button class="navbar-toggler border-0 ms-auto" type="button" data-bs-toggle="collapse"
                    data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav public-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link<?= $publicActive === 'home' ? ' active' : '' ?>" href="<?= e(url('home.php')) ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link<?= $publicActive === 'about' ? ' active' : '' ?>" href="<?= e(url('about_us.php')) ?>">About ESS</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <?php if ($publicUser !== null): ?>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('dashboard.php')) ?>"><?= icon('grid-1x2') ?> Open dashboard</a>
                        <?php else: ?>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('login.php')) ?>"><?= icon('box-arrow-in-right') ?> Staff sign in</a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</div>
