<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$user = require_auth();
$isEmployee = (string) $user['role'] === 'ess_staff';
$errors = [];
$values = [
    'phone_number' => (string) ($user['phone_number'] ?? ''),
    'address' => (string) ($user['address'] ?? ''),
    'emergency_contact_name' => (string) ($user['emergency_contact_name'] ?? ''),
    'emergency_contact_phone' => (string) ($user['emergency_contact_phone'] ?? ''),
];

if (is_post()) {
    $action = post_str('action');
    if ($action === 'contact') {
        $values = [
            'phone_number' => post_str('phone_number'),
            'address' => post_str('address'),
            'emergency_contact_name' => post_str('emergency_contact_name'),
            'emergency_contact_phone' => post_str('emergency_contact_phone'),
        ];
        if ($values['phone_number'] !== '' && !validate_phone_number($values['phone_number'])) {
            $errors['phone_number'] = 'Enter a valid phone number (7–15 digits).';
        }
        if (mb_strlen($values['address']) > 255) {
            $errors['address'] = 'Address must be 255 characters or fewer.';
        }
        if (mb_strlen($values['emergency_contact_name']) > 120) {
            $errors['emergency_contact_name'] = 'Emergency contact name must be 120 characters or fewer.';
        }
        if ($values['emergency_contact_phone'] !== '' && !validate_phone_number($values['emergency_contact_phone'])) {
            $errors['emergency_contact_phone'] = 'Enter a valid emergency contact number.';
        }
        if ($errors === []) {
            db()->execute(
                'UPDATE users SET phone_number = ?, address = ?, emergency_contact_name = ?,
                    emergency_contact_phone = ?, updated_at = NOW() WHERE id = ?',
                [
                    $values['phone_number'] !== '' ? $values['phone_number'] : null,
                    $values['address'] !== '' ? $values['address'] : null,
                    $values['emergency_contact_name'] !== '' ? $values['emergency_contact_name'] : null,
                    $values['emergency_contact_phone'] !== '' ? $values['emergency_contact_phone'] : null,
                    (int) $user['id'],
                ]
            );
            audit_log('profile.update', 'user', (int) $user['id'], 'Contact details updated by account holder');
            flash('success', 'Your contact details have been updated.');
            redirect('profile.php');
        }
    } elseif ($action === 'photo' && $isEmployee) {
        $outcome = save_employee_photo((int) $user['id'], $_FILES['photo'] ?? [], $user);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Profile photo updated.' : (string) ($outcome['error'] ?? 'The photo could not be uploaded.'));
        redirect('profile.php');
    }
}

$photo = employee_photo_url($user);
$hasPhoto = !str_ends_with($photo, 'avatar.svg');
$year = current_year();
$balances = $isEmployee ? leave_balance_summary((int) $user['id'], $year) : [];
$documentCount = $isEmployee ? (int) db()->value(
    'SELECT COUNT(*) FROM file_attachments WHERE ess_staff_id = ?',
    [(int) $user['id']],
    0
) : 0;

$pageTitle = 'My profile';
$topbarTitle = 'My account';
$activeNav = 'profile';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">My profile</h1>
        <p class="page-subtitle">Employment details are maintained by Human Resources. You can keep your contact information current.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url('account/password.php')) ?>"><?= icon('key') ?> Account security</a>
    </div>
</div>

<section class="panel">
    <div class="panel-body">
        <div class="profile-header">
            <div class="profile-identity">
                <span class="avatar avatar-xl">
                    <img src="<?= e($photo) ?>" alt="Profile photo of <?= e(person_name($user)) ?>">
                </span>
                <div>
                    <h2 class="page-title mb-1"><?= e(person_name($user)) ?></h2>
                    <div class="text-muted">
                        <?= e((string) ($user['position_title'] ?? role_label((string) $user['role']))) ?>
                        <?php if (!empty($user['department_name'])): ?> · <?= e((string) $user['department_name']) ?><?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge badge-soft-<?= e(status_badge_class((string) $user['employment_status'])) ?>"><?= e((string) $user['employment_status']) ?></span>
                        <?php if ($isEmployee): ?><span class="chip"><?= icon('person-badge') ?><?= e((string) ($user['employee_number'] ?? '—')) ?></span><?php endif; ?>
                        <span class="badge badge-role <?= e((string) $user['role']) ?>"><?= e(role_label((string) $user['role'])) ?></span>
                    </div>
                </div>
            </div>
            <?php if ($isEmployee): ?>
                <div class="page-actions">
                    <a class="btn btn-outline-secondary" href="<?= e(url('documents/index.php')) ?>"><?= icon('folder2-open') ?> My documents (<?= $documentCount ?>)</a>
                    <a class="btn btn-primary" href="<?= e(url('leave/request.php')) ?>"><?= icon('calendar-plus') ?> Request leave</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="content-grid content-grid-main">
    <section class="panel" aria-labelledby="contactHeading">
        <div class="panel-header">
            <div><h2 id="contactHeading">Contact information</h2><div class="meta">Visible to Human Resources</div></div>
        </div>
        <div class="panel-body">
            <?php include BASE_PATH . '/includes/partials/errors.php'; ?>
            <form method="post" action="<?= e(url('profile.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="contact">
                <div class="form-grid">
                    <div>
                        <label class="form-label" for="phone_number">Phone number</label>
                        <input class="form-control<?= invalid_class($errors, 'phone_number') ?>" type="tel" id="phone_number"
                               name="phone_number" value="<?= e($values['phone_number']) ?>" maxlength="30" autocomplete="tel">
                        <?php if (field_error($errors, 'phone_number') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'phone_number')) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label" for="email_display">Email (managed by HR)</label>
                        <input class="form-control" type="email" id="email_display" value="<?= e((string) ($user['email'] ?? '')) ?>" disabled>
                    </div>
                    <div class="span-2">
                        <label class="form-label" for="address">Address</label>
                        <input class="form-control<?= invalid_class($errors, 'address') ?>" type="text" id="address" name="address"
                               value="<?= e($values['address']) ?>" maxlength="255" autocomplete="street-address">
                        <?php if (field_error($errors, 'address') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'address')) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label" for="emergency_contact_name">Emergency contact</label>
                        <input class="form-control<?= invalid_class($errors, 'emergency_contact_name') ?>" type="text"
                               id="emergency_contact_name" name="emergency_contact_name"
                               value="<?= e($values['emergency_contact_name']) ?>" maxlength="120">
                        <?php if (field_error($errors, 'emergency_contact_name') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'emergency_contact_name')) ?></div><?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label" for="emergency_contact_phone">Emergency contact phone</label>
                        <input class="form-control<?= invalid_class($errors, 'emergency_contact_phone') ?>" type="tel"
                               id="emergency_contact_phone" name="emergency_contact_phone"
                               value="<?= e($values['emergency_contact_phone']) ?>" maxlength="30">
                        <?php if (field_error($errors, 'emergency_contact_phone') !== ''): ?><div class="invalid-feedback d-block"><?= e(field_error($errors, 'emergency_contact_phone')) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <button class="btn btn-primary" type="submit"><?= icon('check2') ?> Save contact details</button>
                </div>
            </form>

            <?php if ($isEmployee): ?>
                <div class="divider"></div>
                <form method="post" action="<?= e(url('profile.php')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="photo">
                    <label class="form-label" for="photo">Profile photo</label>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <input class="form-control" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required style="max-width: 320px;">
                        <button class="btn btn-outline-secondary" type="submit"><?= icon('camera') ?> Upload</button>
                    </div>
                    <div class="form-text">JPG, PNG or WebP · maximum 2 MB. <?= $hasPhoto ? 'Uploading replaces your current photo.' : '' ?></div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel" aria-labelledby="employmentHeading">
        <div class="panel-header">
            <div><h2 id="employmentHeading">Employment record</h2><div class="meta">Read-only · contact HR to correct</div></div>
        </div>
        <div class="panel-body">
            <div class="mini-list">
                <div class="row-line"><span>Employee number</span><span><?= e((string) ($user['employee_number'] ?? '—')) ?></span></div>
                <div class="row-line"><span>Department</span><span><?= e((string) ($user['department_name'] ?? '—')) ?></span></div>
                <div class="row-line"><span>Position</span><span><?= e((string) ($user['position_title'] ?? '—')) ?></span></div>
                <div class="row-line"><span>Employment type</span><span><?= e((string) ($user['employment_type'] ?? '—')) ?></span></div>
                <div class="row-line"><span>Hire date</span><span><?= e(format_date($user['hire_date'] ?? null)) ?></span></div>
                <div class="row-line"><span>Work location</span><span><?= e((string) ($user['work_location'] ?? '—')) ?></span></div>
                <div class="row-line"><span>Last sign-in</span><span><?= e(format_datetime($user['last_login_at'] ?? null)) ?></span></div>
            </div>
        </div>
    </section>
</div>

<?php if ($isEmployee): ?>
<section class="panel" aria-labelledby="leaveHeading">
    <div class="panel-header">
        <div><h2 id="leaveHeading">My leave balances <?= (int) $year ?></h2><div class="meta">Working days</div></div>
        <a class="btn btn-sm btn-quiet" href="<?= e(url('leave/index.php')) ?>">Leave history</a>
    </div>
    <?php if ($balances === []): ?>
        <div class="panel-body"><p class="text-muted mb-0">No leave types are configured.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table-app">
                <thead><tr><th scope="col">Leave type</th><th scope="col" class="text-end">Entitled</th><th scope="col" class="text-end">Approved</th><th scope="col" class="text-end">Pending</th><th scope="col" class="text-end">Remaining</th></tr></thead>
                <tbody>
                <?php foreach ($balances as $balance): ?>
                    <tr>
                        <td><?= e((string) $balance['type']['name']) ?></td>
                        <td class="text-end"><?= e(format_days((float) $balance['entitlement'])) ?></td>
                        <td class="text-end"><?= e(format_days((float) $balance['approved'])) ?></td>
                        <td class="text-end"><?= e(format_days((float) $balance['pending'])) ?></td>
                        <td class="text-end"><strong><?= e(format_days((float) $balance['remaining'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
