<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

$user = current_user();
if ($user === null) {
    redirect('login.php');
}

$pageTitle = $pageTitle ?? 'Dashboard';
$topbarTitle = $topbarTitle ?? 'Workspace';
$activeNav = $activeNav ?? '';
$bodyClass = $bodyClass ?? '';
$unreadCount = unread_notification_count((int) $user['id']);
$pendingLeave = can_manage_leave() ? leave_pending_count() : 0;
$role = (string) $user['role'];
$isStaff = in_array($role, ['admin', 'hr_staff'], true);

$photoUrl = employee_photo_url($user);
$hasPhoto = !str_ends_with($photoUrl, 'avatar.svg');
$displayName = person_name($user);
$navigation = [];

if ($isStaff) {
    $navigation[] = ['label' => 'Workspace', 'items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'speedometer2'],
        ['key' => 'employees', 'label' => 'Employees', 'url' => 'employees/index.php', 'icon' => 'people'],
        ['key' => 'departments', 'label' => 'Departments', 'url' => 'organization/departments.php', 'icon' => 'building'],
        ['key' => 'positions', 'label' => 'Positions', 'url' => 'organization/positions.php', 'icon' => 'briefcase'],
    ]];
    $navigation[] = ['label' => 'HR operations', 'items' => [
        ['key' => 'leave-requests', 'label' => 'Leave requests', 'url' => 'leave/requests.php', 'icon' => 'calendar2-week', 'count' => $pendingLeave],
        ['key' => 'documents', 'label' => 'Documents', 'url' => 'documents/index.php', 'icon' => 'folder2-open'],
        ['key' => 'reports', 'label' => 'Reports', 'url' => 'reports/index.php', 'icon' => 'bar-chart'],
    ]];
    if ($role === 'admin') {
        $navigation[] = ['label' => 'Administration', 'items' => [
            ['key' => 'staff-accounts', 'label' => 'Staff accounts', 'url' => 'admin/users.php', 'icon' => 'shield-lock'],
            ['key' => 'leave-types', 'label' => 'Leave types', 'url' => 'leave/types.php', 'icon' => 'sliders'],
            ['key' => 'audit', 'label' => 'Audit log', 'url' => 'admin/audit.php', 'icon' => 'journal-text'],
        ]];
    }
} else {
    $navigation[] = ['label' => 'My workspace', 'items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'speedometer2'],
        ['key' => 'profile', 'label' => 'My profile', 'url' => 'profile.php', 'icon' => 'person-vcard'],
        ['key' => 'leave', 'label' => 'My leave', 'url' => 'leave/index.php', 'icon' => 'calendar2-week'],
        ['key' => 'documents', 'label' => 'My documents', 'url' => 'documents/index.php', 'icon' => 'folder2-open'],
        ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'notifications.php', 'icon' => 'bell', 'count' => $unreadCount],
    ]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ESS HRMS</title>
<link rel="icon" href="<?= e(url('images/logo.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<div class="app-shell">
    <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="sidebarTitle">
        <div class="offcanvas-header sidebar-header">
            <img class="sidebar-logo" src="<?= e(url('images/logo.png')) ?>" alt="">
            <div class="sidebar-brand-text">
                <p class="sidebar-title" id="sidebarTitle">ESS HRMS</p>
                <p class="sidebar-subtitle">Ethiopian Statistical Service</p>
            </div>
            <button type="button" class="btn-close btn-close-white sidebar-close d-lg-none"
                    data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close navigation"></button>
        </div>
        <div class="offcanvas-body sidebar-body">
            <nav aria-label="Primary navigation">
                <?php foreach ($navigation as $section): ?>
                    <div class="sidebar-section">
                        <div class="sidebar-label"><?= e($section['label']) ?></div>
                        <div class="sidebar-nav">
                            <?php foreach ($section['items'] as $item): ?>
                                <a class="sidebar-link<?= $activeNav === $item['key'] ? ' is-active' : '' ?>"
                                   href="<?= e(url($item['url'])) ?>"
                                   <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
                                    <?= icon($item['icon']) ?>
                                    <span><?= e($item['label']) ?></span>
                                    <?php if (!empty($item['count'])): ?>
                                        <span class="nav-count"><?= (int) $item['count'] ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="sidebar-footer d-none d-lg-block">
            <a href="<?= e(url('home.php')) ?>">Public website</a> ·
            <a href="<?= e(url('about_us.php')) ?>">About ESS</a>
            <div class="mt-1">Internal HR system · v2.0</div>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <button class="topbar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                    aria-controls="appSidebar" aria-label="Open navigation">
                <?= icon('list') ?>
            </button>
            <div class="topbar-heading">
                <div class="eyebrow"><?= e($topbarTitle) ?></div>
                <div class="title"><?= e($pageTitle) ?></div>
            </div>
            <div class="topbar-spacer"></div>
            <div class="topbar-actions">
                <?php if ($isStaff): ?>
                    <form class="topbar-search position-relative" method="get" action="<?= e(url('employees/index.php')) ?>" role="search">
                        <label class="visually-hidden" for="globalSearch">Search employees</label>
                        <?= icon('search', 'search-icon') ?>
                        <input class="form-control" type="search" id="globalSearch" name="q" placeholder="Search employees…"
                               value="<?= e(current_route() === 'employees/index.php' ? get_str('q') : '') ?>">
                    </form>
                <?php endif; ?>
                <a class="icon-button" href="<?= e(url('notifications.php')) ?>"
                   aria-label="Notifications<?= $unreadCount > 0 ? ', ' . $unreadCount . ' unread' : '' ?>">
                    <?= icon('bell') ?>
                    <?php if ($unreadCount > 0): ?><span class="count"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
                </a>
                <div class="dropdown">
                    <button class="user-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar">
                            <?php if ($hasPhoto): ?>
                                <img src="<?= e($photoUrl) ?>" alt="">
                            <?php else: ?>
                                <?= e(initials($user['first_name'] ?? '', $user['last_name'] ?? '')) ?>
                            <?php endif; ?>
                        </span>
                        <span class="user-meta">
                            <span class="name d-block"><?= e($displayName) ?></span>
                            <span class="role"><?= e(role_label($role)) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2">
                            <div class="fw-semibold small"><?= e($displayName) ?></div>
                            <div class="tiny text-muted-2">@<?= e($user['username']) ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($role === 'ess_staff'): ?>
                            <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>"><?= icon('person-vcard') ?>My profile</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= e(url('account/password.php')) ?>"><?= icon('key') ?>Account security</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('notifications.php')) ?>"><?= icon('bell') ?>Notifications<?= $unreadCount > 0 ? ' (' . $unreadCount . ')' : '' ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(url('logout.php')) ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item" type="submit"><?= icon('box-arrow-right') ?>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content" id="main-content">
            <?php include BASE_PATH . '/includes/partials/flash.php'; ?>
