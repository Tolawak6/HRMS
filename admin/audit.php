<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$actor = require_roles(['admin']);
$q = get_str('q');
$actionFilter = get_str('action');
$page = max(1, get_int('page', 1));
$perPage = per_page_value(25);
$from = get_str('from');
$to = get_str('to');

$where = [];
$params = [];
if ($q !== '') {
    $term = '%' . like_term(mb_substr($q, 0, 80)) . '%';
    $where[] = "(al.description LIKE ? ESCAPE '!' OR al.action LIKE ? ESCAPE '!' OR u.username LIKE ? ESCAPE '!'
                 OR u.first_name LIKE ? ESCAPE '!' OR u.last_name LIKE ? ESCAPE '!')";
    array_push($params, $term, $term, $term, $term, $term);
}
if ($actionFilter !== '') {
    $where[] = 'al.action = ?';
    $params[] = $actionFilter;
}
if (validate_iso_date($from)) {
    $where[] = 'al.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if (validate_iso_date($to)) {
    $where[] = 'al.created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$total = (int) db()->value(
    "SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id $whereSql",
    $params,
    0
);
$pagination = paginate($total, $perPage, $page);
$rows = db()->all(
    "SELECT al.*, u.username, u.first_name, u.last_name, u.role
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     $whereSql
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT " . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
    $params
);
$actions = db()->all('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC');

$pageTitle = 'Audit log';
$topbarTitle = 'Administration';
$activeNav = 'audit';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Audit log</h1>
        <p class="page-subtitle"><?= number_format($total) ?> recorded event<?= $total === 1 ? '' : 's' ?> · append-only history of security-relevant actions</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('admin/users.php')) ?>"><?= icon('shield-lock') ?> Staff accounts</a>
    </div>
</div>

<section class="panel" aria-labelledby="filterHeading">
    <div class="panel-header"><h2 id="filterHeading" class="visually-hidden">Filters</h2></div>
    <div class="panel-body pt-3">
        <form class="toolbar" method="get" action="<?= e(url('admin/audit.php')) ?>" role="search">
            <div class="field field-grow">
                <label for="q">Search</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Actor, action or description">
            </div>
            <div class="field">
                <label for="action">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $option): ?>
                        <option value="<?= e((string) $option['action']) ?>" <?= selected($actionFilter, (string) $option['action']) ?>>
                            <?= e(audit_action_label((string) $option['action'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="from">From</label>
                <input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="field">
                <label for="to">To</label>
                <input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="field">
                <label class="d-none d-sm-block">&nbsp;</label>
                <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
            </div>
        </form>
    </div>
</section>

<section class="panel" aria-labelledby="logHeading">
    <div class="panel-header">
        <div><h2 id="logHeading">Events</h2><div class="meta">Newest first · timestamps in <?= e((string) config('timezone', 'Africa/Addis_Ababa')) ?></div></div>
    </div>
    <?php if ($rows === []): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= icon('journal-text') ?></div>
            <h3>No events match</h3>
            <p>Adjust the filters to widen the search.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Event</th>
                        <th scope="col">Detail</th>
                        <th scope="col">Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-nowrap">
                            <?= e(format_datetime((string) $row['created_at'])) ?>
                            <div class="row-meta"><?= e(time_ago((string) $row['created_at'])) ?></div>
                        </td>
                        <td>
                            <?= e(person_name($row) !== '' ? person_name($row) : 'System') ?>
                            <div class="row-meta"><?= e((string) ($row['role'] ?? '')) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-soft-info"><?= e(audit_action_label((string) $row['action'])) ?></span>
                            <?php if (!empty($row['entity_type'])): ?>
                                <div class="row-meta"><?= e(audit_entity_label((string) $row['entity_type'])) ?><?= $row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '' ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e((string) ($row['description'] ?? '—')) ?></td>
                        <td class="text-nowrap text-muted tiny"><?= e((string) ($row['ip_address'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php include BASE_PATH . '/includes/partials/pagination.php'; ?>
    <?php endif; ?>
</section>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
