<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/**
 * Baseline schema for the original two tables.
 *
 * This migration is intentionally non-destructive: if the tables already
 * exist (an existing ESS HRMS installation), only missing columns and indexes
 * are added. Existing rows are never modified or deleted.
 */
return static function (Database $db): void {
    if (!Schema::tableExists('users')) {
        $db->execute(
            "CREATE TABLE users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(60) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'ess_staff',
                first_name VARCHAR(80) NULL,
                middle_name VARCHAR(80) NULL,
                last_name VARCHAR(80) NULL,
                sex VARCHAR(10) NULL,
                image VARCHAR(255) NULL,
                phone_number VARCHAR(30) NULL,
                email VARCHAR(150) NULL,
                address VARCHAR(255) NULL,
                room_number VARCHAR(30) NULL,
                shelf_number VARCHAR(30) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_username (username),
                KEY idx_users_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        $legacyColumns = [
            'username'     => "VARCHAR(60) NULL",
            'password'     => "VARCHAR(255) NULL",
            'role'         => "VARCHAR(20) NOT NULL DEFAULT 'ess_staff'",
            'first_name'   => "VARCHAR(80) NULL",
            'middle_name'  => "VARCHAR(80) NULL",
            'last_name'    => "VARCHAR(80) NULL",
            'sex'          => "VARCHAR(10) NULL",
            'image'        => "VARCHAR(255) NULL",
            'phone_number' => "VARCHAR(30) NULL",
            'email'        => "VARCHAR(150) NULL",
            'address'      => "VARCHAR(255) NULL",
            'room_number'  => "VARCHAR(30) NULL",
            'shelf_number' => "VARCHAR(30) NULL",
        ];
        foreach ($legacyColumns as $column => $definition) {
            Schema::addColumn('users', $column, $definition);
        }
        Schema::note('Existing users table detected; data was preserved.');
    }

    // Bcrypt hashes need 60 characters; older schemas may be narrower.
    Schema::widenVarchar('users', 'username', 60);
    Schema::widenVarchar('users', 'password', 255);
    Schema::widenVarchar('users', 'email', 150);
    Schema::widenVarchar('users', 'first_name', 80);
    Schema::widenVarchar('users', 'middle_name', 80);
    Schema::widenVarchar('users', 'last_name', 80);
    Schema::widenVarchar('users', 'phone_number', 30);
    Schema::widenVarchar('users', 'address', 255);

    if (Schema::duplicateGroups('users', 'username') > 0) {
        Schema::note(
            'Duplicate usernames exist. A UNIQUE index was not added; resolve duplicates before enabling account creation.'
        );
    } else {
        Schema::addIndex('users', 'uq_users_username', ['username'], true);
    }
    if (!Schema::hasIndexOn('users', ['username'])) {
        Schema::addIndex('users', 'idx_users_username', ['username']);
    }
    Schema::addIndex('users', 'idx_users_role', ['role']);

    if (!Schema::tableExists('file_attachments')) {
        $db->execute(
            "CREATE TABLE file_attachments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ess_staff_id INT UNSIGNED NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_file_attachments_staff (ess_staff_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        Schema::addColumn('file_attachments', 'ess_staff_id', 'INT UNSIGNED NULL');
        Schema::addColumn('file_attachments', 'file_name', 'VARCHAR(255) NULL');
        Schema::addColumn('file_attachments', 'file_path', 'VARCHAR(500) NULL');
        Schema::addColumn('file_attachments', 'uploaded_at', 'DATETIME NULL');
        if (!Schema::hasIndexOn('file_attachments', ['ess_staff_id'])) {
            Schema::addIndex('file_attachments', 'idx_file_attachments_staff', ['ess_staff_id']);
        }
    }
};
