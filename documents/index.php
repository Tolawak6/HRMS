<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_auth();
$canManage = can_manage_employees();
$filters = document_filters();
if (!$canManage) {
    $filters['employee'] = (int) $user['id'];
}

if (is_post() && $canManage) {
    $action = post_str('action');
    if ($action === 'upload') {
        $employeeId = post_int('employee_id');
        $outcome = document_upload($employeeId, $_FILES['document'] ?? [], post_str('description'), $user);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Document uploaded.' : (string) ($outcome['error'] ?? 'The document could not be uploaded.'));
        redirect('documents/index.php?employee=' . $employeeId);
    } elseif ($action === 'delete') {
        $outcome = document_delete(post_int('id'), $user);
        flash($outcome['ok'] ? 'success' : 'danger', $outcome['ok'] ? 'Document deleted.' : (string) ($outcome['error'] ?? 'The document could not be deleted.'));
        redirect('documents/index.php' . ($filters['employee'] > 0 ? '?employee=' . $filters['employee'] : ''));
    }
}

$result = documents_search($filters);
$rows = $result['rows'];
$pagination = $result['pagination'];
$employees = $canManage ? db()->all(
    "SELECT id, first_name, middle_name, last_name, employee_number
     FROM users WHERE role = 'ess_staff' AND archived_at IS NULL
     ORDER BY last_name ASC, first_name ASC LIMIT 1000"
) : [];
$selectedEmployee = $filters['employee'] > 0 ? employee_find((int) $filters['employee']) : null;

$pageTitle = $canManage ? 'Documents' : 'My documents';
$topbarTitle = $canManage ? 'HR operations' : 'Self service';
$activeNav = 'documents';
include BASE_PATH . '/includes/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $canManage ? 'Documents' : 'My documents' ?></h1>
        <p class="page-subtitle">
            <?php if ($canManage): ?>
                Personnel documents stored outside the public web root
            <?php else: ?>
                Documents held in your employee file by Human Resources
            <?php endif; ?>
        </p>
    </div>
    <?php if ($canManage && $selectedEmployee !== null): ?>
        <div class="page-actions">
            <a class="btn btn-outline-secondary" href="<?= e(url('employees/view.php?id=' . (int) $selectedEmployee['id'])) ?>"><?= icon('person-vcard') ?> Open profile</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="content-grid content-grid-main">
<?php endif; ?>
    <section class="panel" aria-labelledby="listHeading">
        <div class="panel-header">
            <div>
                <h2 id="listHeading"><?= $canManage ? 'Document register' : 'My files' ?></h2>
                <div class="meta"><?= number_format((int) $pagination['total']) ?> document<?= (int) $pagination['total'] === 1 ? '' : 's' ?></div>
            </div>
        </div>
        <?php if ($canManage): ?>
            <div class="panel-body pt-3">
                <form class="toolbar" method="get" action="<?= e(url('documents/index.php')) ?>" role="search">
                    <div class="field field-grow">
                        <label for="q">Search</label>
                        <input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>"
                               placeholder="File name, description, employee">
                    </div>
                    <div class="field">
                        <label for="employee">Employee</label>
                        <select class="form-select" id="employee" name="employee">
                            <option value="">All employees</option>
                            <?php foreach ($employees as $option): ?>
                                <option value="<?= (int) $option['id'] ?>" <?= selected($filters['employee'], (int) $option['id']) ?>>
                                    <?= e(person_name($option)) ?> · <?= e((string) ($option['employee_number'] ?? '—')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="d-none d-sm-block">&nbsp;</label>
                        <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
                    </div>
                    <?php if ($filters['q'] !== '' || $filters['employee'] > 0): ?>
                        <div class="field">
                            <label class="d-none d-sm-block">&nbsp;</label>
                            <a class="btn btn-quiet" href="<?= e(url('documents/index.php')) ?>"><?= icon('x-lg') ?> Clear</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($rows === []): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('folder2-open') ?></div>
                <h3>No documents found</h3>
                <p><?= $canManage ? 'Upload a document to start building the personnel file.' : 'No documents have been added to your file yet.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-app">
                    <thead>
                        <tr>
                            <th scope="col">File</th>
                            <?php if ($canManage): ?><th scope="col">Employee</th><?php endif; ?>
                            <th scope="col" class="text-end">Size</th>
                            <th scope="col">Uploaded</th>
                            <th scope="col" class="col-actions"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <span class="row-title d-inline-flex align-items-center gap-2">
                                    <?= icon(document_icon((string) $row['file_name'])) ?>
                                    <?= e((string) $row['file_name']) ?>
                                </span>
                                <?php if (!empty($row['description'])): ?><div class="row-meta"><?= e((string) $row['description']) ?></div><?php endif; ?>
                            </td>
                            <?php if ($canManage): ?>
                                <td>
                                    <a class="row-title" href="<?= e(url('employees/view.php?id=' . (int) $row['ess_staff_id'])) ?>"><?= e(person_name($row)) ?></a>
                                    <div class="row-meta"><?= e((string) ($row['employee_number'] ?? '—')) ?></div>
                                </td>
                            <?php endif; ?>
                            <td class="text-end text-nowrap"><?= e(human_file_size((int) ($row['file_size'] ?? 0))) ?></td>
                            <td class="text-nowrap text-muted"><?= e(format_date((string) $row['uploaded_at'])) ?></td>
                            <td class="col-actions">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('documents/download.php?id=' . (int) $row['id'])) ?>"><?= icon('download') ?> Download</a>
                                    <?php if ($canManage): ?>
                                        <form method="post" action="<?= e(url('documents/index.php')) ?>"
                                              data-confirm="Permanently delete this document? This cannot be undone."
                                              data-confirm-title="Delete document" data-confirm-button="Delete">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button class="btn btn-sm btn-quiet" type="submit"><?= icon('trash') ?><span class="visually-hidden">Delete <?= e((string) $row['file_name']) ?></span></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php include BASE_PATH . '/includes/partials/pagination.php'; ?>
        <?php endif; ?>
    </section>

    <?php if ($canManage): ?>
    <aside class="panel" aria-labelledby="uploadHeading">
        <div class="panel-header">
            <div><h2 id="uploadHeading">Upload document</h2><div class="meta">PDF, DOCX, XLSX, JPG, PNG or WebP · maximum 5 MB</div></div>
        </div>
        <div class="panel-body">
            <form method="post" action="<?= e(url('documents/index.php')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <div class="mb-3">
                    <label class="form-label" for="employee_id">Employee <span class="req">*</span></label>
                    <select class="form-select" id="employee_id" name="employee_id" required>
                        <option value="">Select employee…</option>
                        <?php foreach ($employees as $option): ?>
                            <option value="<?= (int) $option['id'] ?>" <?= selected($filters['employee'], (int) $option['id']) ?>>
                                <?= e(person_name($option)) ?> · <?= e((string) ($option['employee_number'] ?? '—')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="document">File <span class="req">*</span></label>
                    <input class="form-control" type="file" id="document" name="document" required
                           accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png,.webp">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="description">Description</label>
                    <input class="form-control" type="text" id="description" name="description" maxlength="255"
                           placeholder="e.g. Employment verification letter">
                </div>
                <div class="form-actions">
                    <span class="spacer"></span>
                    <button class="btn btn-primary" type="submit"><?= icon('upload') ?> Upload</button>
                </div>
            </form>
            <div class="notice mt-3">
                <?= icon('shield-lock') ?>
                <div>Files are stored outside the public web directory and served only to authorized users.</div>
            </div>
        </div>
    </aside>
    <?php endif; ?>
<?php if ($canManage): ?>
</div>
<?php endif; ?>
<?php include BASE_PATH . '/includes/layout/footer.php'; ?>
