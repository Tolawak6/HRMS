<?php
defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/**
 * Shared employee create/edit form.
 *
 * Expects: $values (array), $errors (array), $departments (array),
 * $positions (array), $isEdit (bool), $formAction (string).
 */
$v = static function (string $key, string $default = '') use ($values): string {
    return (string) ($values[$key] ?? $default);
};
$err = static function (string $key) use ($errors): void {
    if (field_error($errors, $key) !== '') {
        echo '<div class="invalid-feedback d-block">' . e(field_error($errors, $key)) . '</div>';
    }
};
$cls = static function (string $key) use ($errors): string {
    return 'form-control' . invalid_class($errors, $key);
};
?>
<form method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>

    <section class="form-section" aria-labelledby="personalHeading">
        <div class="form-section-title"><?= icon('person-vcard') ?> Personal information</div>
        <div class="form-grid">
            <div>
                <label class="form-label" for="first_name">First name <span class="req">*</span></label>
                <input class="<?= e($cls('first_name')) ?>" type="text" id="first_name" name="first_name"
                       value="<?= e($v('first_name')) ?>" maxlength="80" required autocomplete="given-name">
                <?php $err('first_name'); ?>
            </div>
            <div>
                <label class="form-label" for="middle_name">Middle name</label>
                <input class="<?= e($cls('middle_name')) ?>" type="text" id="middle_name" name="middle_name"
                       value="<?= e($v('middle_name')) ?>" maxlength="80" autocomplete="additional-name">
                <?php $err('middle_name'); ?>
            </div>
            <div>
                <label class="form-label" for="last_name">Last name <span class="req">*</span></label>
                <input class="<?= e($cls('last_name')) ?>" type="text" id="last_name" name="last_name"
                       value="<?= e($v('last_name')) ?>" maxlength="80" required autocomplete="family-name">
                <?php $err('last_name'); ?>
            </div>
            <div>
                <label class="form-label" for="sex">Sex <span class="req">*</span></label>
                <select class="<?= e($cls('sex')) ?>" id="sex" name="sex" required>
                    <option value="">Select…</option>
                    <?php foreach (sex_options() as $option): ?>
                        <option value="<?= e($option) ?>" <?= selected($v('sex'), $option) ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $err('sex'); ?>
            </div>
            <div>
                <label class="form-label" for="date_of_birth">Date of birth</label>
                <input class="<?= e($cls('date_of_birth')) ?>" type="date" id="date_of_birth" name="date_of_birth"
                       value="<?= e($v('date_of_birth')) ?>" max="<?= e(today()) ?>">
                <?php $err('date_of_birth'); ?>
            </div>
            <div>
                <label class="form-label" for="national_id">National ID</label>
                <input class="<?= e($cls('national_id')) ?>" type="text" id="national_id" name="national_id"
                       value="<?= e($v('national_id')) ?>" maxlength="40" autocomplete="off">
                <?php $err('national_id'); ?>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="employmentHeading">
        <div class="form-section-title"><?= icon('briefcase') ?> Employment details</div>
        <div class="form-grid">
            <div>
                <label class="form-label" for="employee_number">Employee number <span class="req">*</span></label>
                <input class="<?= e($cls('employee_number')) ?>" type="text" id="employee_number" name="employee_number"
                       value="<?= e($v('employee_number')) ?>" maxlength="30" required autocomplete="off">
                <div class="form-text">Letters, numbers, dot, dash or slash. Must be unique.</div>
                <?php $err('employee_number'); ?>
            </div>
            <div>
                <label class="form-label" for="department_id">Department <span class="req">*</span></label>
                <select class="<?= e($cls('department_id')) ?>" id="department_id" name="department_id" required
                        data-department-select>
                    <option value="">Select department…</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= selected($v('department_id'), (string) $department['id']) ?>
                                <?= (int) $department['is_active'] === 0 ? 'data-inactive="1"' : '' ?>>
                            <?= e((string) $department['name']) ?><?= (int) $department['is_active'] === 0 ? ' (inactive)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php $err('department_id'); ?>
            </div>
            <div>
                <label class="form-label" for="position_id">Position <span class="req">*</span></label>
                <select class="<?= e($cls('position_id')) ?>" id="position_id" name="position_id" required
                        data-position-select>
                    <option value="">Select position…</option>
                    <?php foreach ($positions as $position): ?>
                        <option value="<?= (int) $position['id'] ?>" data-department="<?= (int) $position['department_id'] ?>"
                                <?= selected($v('position_id'), (string) $position['id']) ?>>
                            <?= e((string) $position['title']) ?><?= (int) $position['is_active'] === 0 ? ' (inactive)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Available positions follow the selected department.</div>
                <?php $err('position_id'); ?>
            </div>
            <div>
                <label class="form-label" for="employment_type">Employment type <span class="req">*</span></label>
                <select class="<?= e($cls('employment_type')) ?>" id="employment_type" name="employment_type" required>
                    <?php foreach (employment_types() as $option): ?>
                        <option value="<?= e($option) ?>" <?= selected($v('employment_type'), $option) ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $err('employment_type'); ?>
            </div>
            <div>
                <label class="form-label" for="employment_status">Employment status <span class="req">*</span></label>
                <select class="<?= e($cls('employment_status')) ?>" id="employment_status" name="employment_status" required>
                    <?php foreach (employment_statuses() as $option): ?>
                        <option value="<?= e($option) ?>" <?= selected($v('employment_status'), $option) ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $err('employment_status'); ?>
            </div>
            <div>
                <label class="form-label" for="hire_date">Hire date <span class="req">*</span></label>
                <input class="<?= e($cls('hire_date')) ?>" type="date" id="hire_date" name="hire_date"
                       value="<?= e($v('hire_date')) ?>" max="<?= e(today()) ?>" required>
                <?php $err('hire_date'); ?>
            </div>
            <div>
                <label class="form-label" for="highest_qualification">Highest qualification</label>
                <input class="<?= e($cls('highest_qualification')) ?>" type="text" id="highest_qualification"
                       name="highest_qualification" value="<?= e($v('highest_qualification')) ?>" maxlength="120">
                <?php $err('highest_qualification'); ?>
            </div>
            <div>
                <label class="form-label" for="work_location">Work location</label>
                <input class="<?= e($cls('work_location')) ?>" type="text" id="work_location" name="work_location"
                       value="<?= e($v('work_location')) ?>" maxlength="120" placeholder="e.g. Head Office, Addis Ababa">
                <?php $err('work_location'); ?>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="contactHeading">
        <div class="form-section-title"><?= icon('telephone') ?> Contact details</div>
        <div class="form-grid">
            <div>
                <label class="form-label" for="phone_number">Phone number</label>
                <input class="<?= e($cls('phone_number')) ?>" type="tel" id="phone_number" name="phone_number"
                       value="<?= e($v('phone_number')) ?>" maxlength="30" autocomplete="tel">
                <?php $err('phone_number'); ?>
            </div>
            <div>
                <label class="form-label" for="email">Email address</label>
                <input class="<?= e($cls('email')) ?>" type="email" id="email" name="email"
                       value="<?= e($v('email')) ?>" maxlength="150" autocomplete="email">
                <?php $err('email'); ?>
            </div>
            <div class="span-2">
                <label class="form-label" for="address">Address</label>
                <input class="<?= e($cls('address')) ?>" type="text" id="address" name="address"
                       value="<?= e($v('address')) ?>" maxlength="255" autocomplete="street-address">
                <?php $err('address'); ?>
            </div>
            <div>
                <label class="form-label" for="emergency_contact_name">Emergency contact</label>
                <input class="<?= e($cls('emergency_contact_name')) ?>" type="text" id="emergency_contact_name"
                       name="emergency_contact_name" value="<?= e($v('emergency_contact_name')) ?>" maxlength="120">
                <?php $err('emergency_contact_name'); ?>
            </div>
            <div>
                <label class="form-label" for="emergency_contact_phone">Emergency contact phone</label>
                <input class="<?= e($cls('emergency_contact_phone')) ?>" type="tel" id="emergency_contact_phone"
                       name="emergency_contact_phone" value="<?= e($v('emergency_contact_phone')) ?>" maxlength="30">
                <?php $err('emergency_contact_phone'); ?>
            </div>
            <div>
                <label class="form-label" for="room_number">Room number</label>
                <input class="<?= e($cls('room_number')) ?>" type="text" id="room_number" name="room_number"
                       value="<?= e($v('room_number')) ?>" maxlength="30">
                <?php $err('room_number'); ?>
            </div>
            <div>
                <label class="form-label" for="shelf_number">Shelf number</label>
                <input class="<?= e($cls('shelf_number')) ?>" type="text" id="shelf_number" name="shelf_number"
                       value="<?= e($v('shelf_number')) ?>" maxlength="30">
                <?php $err('shelf_number'); ?>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="accountHeading">
        <div class="form-section-title"><?= icon('key') ?> Self-service account</div>
        <div class="form-grid">
            <div>
                <label class="form-label" for="username">Username <span class="req">*</span></label>
                <input class="<?= e($cls('username')) ?>" type="text" id="username" name="username"
                       value="<?= e($v('username')) ?>" maxlength="60" required autocomplete="off"
                       autocapitalize="none" spellcheck="false">
                <?php $err('username'); ?>
            </div>
            <div>
                <label class="form-label" for="password">
                    <?= $isEdit ? 'New temporary password' : 'Temporary password' ?>
                    <?php if (!$isEdit): ?><span class="req">*</span><?php endif; ?>
                </label>
                <div class="position-relative">
                    <input class="<?= e($cls('password')) ?> pe-5" type="password" id="password" name="password"
                           autocomplete="new-password" <?= $isEdit ? '' : 'required' ?> minlength="10">
                    <button class="password-toggle" type="button" data-toggle-password="password"
                            aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
                </div>
                <div class="form-text">
                    <?= $isEdit
                        ? 'Leave blank to keep the current password. Setting a new password forces a change at next sign-in.'
                        : 'At least 10 characters with upper case, lower case, a number and a symbol. The employee must change it at first sign-in.' ?>
                </div>
                <?php $err('password'); ?>
            </div>
            <?php if ($isEdit): ?>
                <div class="span-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="account_active" name="account_active" value="1"
                               <?= checked((int) ($values['account_active'] ?? 1) === 1) ?>>
                        <label class="form-check-label" for="account_active">
                            Self-service sign-in enabled
                            <span class="d-block form-text mt-0">Disable to block this employee from signing in without archiving the record.</span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="<?= e(url($isEdit ? 'employees/view.php?id=' . (int) ($values['id'] ?? 0) : 'employees/index.php')) ?>">Cancel</a>
        <span class="spacer"></span>
        <button class="btn btn-primary" type="submit">
            <?= icon($isEdit ? 'check2' : 'person-plus') ?>
            <?= $isEdit ? 'Save changes' : 'Create employee' ?>
        </button>
    </div>
</form>
