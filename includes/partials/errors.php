<?php
defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

if (empty($errors) || !is_array($errors)) {
    return;
}
$formError = isset($errors['_form']) ? (string) $errors['_form'] : '';
$messages = [];
foreach ($errors as $key => $message) {
    if ($key === '_form' || !is_string($message) || trim($message) === '') {
        continue;
    }
    $messages[] = trim($message);
}
$messages = array_values(array_unique($messages));
if ($formError === '' && $messages === []) {
    return;
}
?>
<div class="alert alert-danger" role="alert">
    <?= icon('exclamation-triangle') ?>
    <div class="flex-grow-1">
        <?php if ($formError !== ''): ?>
            <div class="fw-semibold"><?= e($formError) ?></div>
        <?php endif; ?>
        <?php if ($messages !== []): ?>
            <?php if ($formError === ''): ?><div class="fw-semibold">Please correct the following:</div><?php endif; ?>
            <ul class="mb-0">
                <?php foreach ($messages as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
