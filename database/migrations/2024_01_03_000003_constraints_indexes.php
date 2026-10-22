<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/**
 * Hardens data integrity without deleting or rewriting records:
 *  - normalizes blank email values to NULL
 *  - adds a unique index on email when the existing data allows it
 *  - adds supporting indexes for account status and document listings
 *  - adds foreign keys for document ownership when no orphan rows exist
 *
 * If legacy data already contains duplicates or orphans, the migration records
 * a note and leaves the constraint out instead of failing or removing data.
 */
return static function (Database $db): void {
    $db->execute("UPDATE users SET email = NULL WHERE email IS NOT NULL AND TRIM(email) = ''");

    $duplicateEmails = Schema::duplicateGroups('users', 'email');
    if ($duplicateEmails === 0) {
        if (Schema::addIndex('users', 'uq_users_email', ['email'], true)) {
            Schema::note('Added unique index on users.email.');
        }
    } else {
        Schema::addIndex('users', 'idx_users_email', ['email'], false);
        Schema::note(
            'Unique email index skipped: ' . $duplicateEmails
            . ' duplicate email group(s) exist. Resolve them and re-run migrations to enforce uniqueness.'
        );
    }

    Schema::addIndex('users', 'idx_users_status', ['is_active', 'employment_status']);
    Schema::addIndex('file_attachments', 'idx_file_attachments_uploaded', ['uploaded_at']);

    $orphanDocuments = (int) $db->value(
        'SELECT COUNT(*) FROM file_attachments fa
         LEFT JOIN users owner ON owner.id = fa.ess_staff_id
         WHERE owner.id IS NULL',
        [],
        0
    );
    if ($orphanDocuments === 0) {
        if (Schema::addForeignKey('file_attachments', 'fk_file_attachments_owner', 'ess_staff_id', 'users', 'CASCADE')) {
            Schema::note('Added foreign key for document ownership.');
        }
    } else {
        Schema::note(
            'Document ownership foreign key skipped: ' . $orphanDocuments
            . ' attachment row(s) reference a missing employee.'
        );
    }

    $orphanUploaders = (int) $db->value(
        'SELECT COUNT(*) FROM file_attachments fa
         LEFT JOIN users uploader ON uploader.id = fa.uploaded_by
         WHERE fa.uploaded_by IS NOT NULL AND uploader.id IS NULL',
        [],
        0
    );
    if ($orphanUploaders === 0) {
        if (Schema::addForeignKey('file_attachments', 'fk_file_attachments_uploader', 'uploaded_by', 'users', 'SET NULL')) {
            Schema::note('Added foreign key for document uploader.');
        }
    } else {
        Schema::note(
            'Document uploader foreign key skipped: ' . $orphanUploaders
            . ' attachment row(s) reference a missing uploader.'
        );
    }
};
