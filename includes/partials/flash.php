<?php
defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

$flashMessages = flashes();
if ($flashMessages === []) {
    return;
}
foreach ($flashMessages as $flashItem):
    $type = in_array($flashItem['type'], ['success', 'danger', 'warning', 'info'], true) ? $flashItem['type'] : 'info';
    $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';
    $auto = in_array($type, ['success', 'info'], true) ? ' alert-auto' : '';
    ?>
    <div class="alert alert-<?= e($type) ?><?= $auto ?>" role="<?= e($role) ?>">
        <?= icon(alert_icon($type)) ?>
        <div class="flex-grow-1"><?= e($flashItem['message']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
    </div>
<?php endforeach; ?>
