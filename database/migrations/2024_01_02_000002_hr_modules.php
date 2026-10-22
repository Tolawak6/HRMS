<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/**
 * Adds the HR data model: organizational units, employment data, leave,
 * notifications, audit trail and login throttling.
 *
 * Additive only. Existing employee rows are assigned sensible defaults and no
 * records are deleted.
 */
return static function (Database $db): void {
    $userKey = Schema::userKeyType();
    $usersEngine = strtolower(Schema::engine('users'));
    $canUseUserForeignKeys = $usersEngine === 'innodb';

    /* ---------------------------------------------------------------------
     * Organizational units
     * ------------------------------------------------------------------ */
    $db->execute(
        "CREATE TABLE IF NOT EXISTS departments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            code VARCHAR(20) NOT NULL,
            description VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_departments_name (name),
            UNIQUE KEY uq_departments_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->execute(
        "CREATE TABLE IF NOT EXISTS positions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(120) NOT NULL,
            code VARCHAR(30) NOT NULL,
            department_id INT UNSIGNED NULL,
            grade VARCHAR(30) NULL,
            description VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_positions_code (code),
            KEY idx_positions_department (department_id),
            CONSTRAINT fk_positions_department
                FOREIGN KEY (department_id) REFERENCES departments (id)
                ON DELETE SET NULL ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    /* ---------------------------------------------------------------------
     * Employee / employment fields on users
     * ------------------------------------------------------------------ */
    $employeeColumns = [
        'employee_number'       => 'VARCHAR(30) NULL',
        'department_id'         => 'INT UNSIGNED NULL',
        'position_id'           => 'INT UNSIGNED NULL',
        'employment_type'       => "ENUM('Permanent','Contract','Temporary','Intern') NULL",
        'employment_status'     => "ENUM('Active','On Leave','Suspended','Retired','Resigned') NOT NULL DEFAULT 'Active'",
        'hire_date'             => 'DATE NULL',
        'date_of_birth'         => 'DATE NULL',
        'national_id'           => 'VARCHAR(40) NULL',
        'emergency_contact_name'  => 'VARCHAR(120) NULL',
        'emergency_contact_phone' => 'VARCHAR(30) NULL',
        'highest_qualification' => 'VARCHAR(120) NULL',
        'work_location'         => 'VARCHAR(120) NULL',
        'photo_path'            => 'VARCHAR(255) NULL',
        'must_change_password'  => 'TINYINT(1) NOT NULL DEFAULT 0',
        'is_active'             => 'TINYINT(1) NOT NULL DEFAULT 1',
        'archived_at'           => 'DATETIME NULL',
        'archive_reason'        => 'VARCHAR(255) NULL',
        'last_login_at'         => 'DATETIME NULL',
        'created_at'            => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at'            => 'DATETIME NULL DEFAULT NULL',
    ];
    foreach ($employeeColumns as $column => $definition) {
        Schema::addColumn('users', $column, $definition);
    }

    Schema::addIndex('users', 'idx_users_department', ['department_id']);
    Schema::addIndex('users', 'idx_users_position', ['position_id']);
    Schema::addIndex('users', 'idx_users_archived', ['archived_at']);
    Schema::addIndex('users', 'idx_users_name', ['last_name', 'first_name']);
    Schema::addIndex('users', 'idx_users_employee_number', ['employee_number']);

    if (Schema::duplicateGroups('users', 'employee_number') === 0) {
        Schema::addIndex('users', 'uq_users_employee_number', ['employee_number'], true);
    } else {
        Schema::note('Duplicate employee numbers exist; the unique index was not added.');
    }

    if ($canUseUserForeignKeys) {
        try {
            Schema::addForeignKey('users', 'fk_users_department', 'department_id', 'departments', 'SET NULL');
            Schema::addForeignKey('users', 'fk_users_position', 'position_id', 'positions', 'SET NULL');
        } catch (Throwable $exception) {
            Schema::note('Could not add organizational foreign keys: ' . $exception->getMessage());
        }
    } else {
        Schema::note('users table is not InnoDB; foreign keys to users were skipped.');
    }

    /* ---------------------------------------------------------------------
     * Documents metadata
     * ------------------------------------------------------------------ */
    Schema::addColumn('file_attachments', 'uploaded_by', "$userKey NULL");
    Schema::addColumn('file_attachments', 'stored_name', 'VARCHAR(255) NULL');
    Schema::addColumn('file_attachments', 'mime_type', 'VARCHAR(150) NULL');
    Schema::addColumn('file_attachments', 'file_size', 'INT UNSIGNED NULL');
    Schema::addColumn('file_attachments', 'description', 'VARCHAR(255) NULL');

    /* ---------------------------------------------------------------------
     * Leave
     * ------------------------------------------------------------------ */
    $db->execute(
        "CREATE TABLE IF NOT EXISTS leave_types (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            code VARCHAR(20) NOT NULL,
            annual_entitlement DECIMAL(5,1) NOT NULL DEFAULT 0,
            is_paid TINYINT(1) NOT NULL DEFAULT 1,
            description VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_leave_types_name (name),
            UNIQUE KEY uq_leave_types_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->execute(
        "CREATE TABLE IF NOT EXISTS leave_balances (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id $userKey NOT NULL,
            leave_type_id INT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            entitled_days DECIMAL(5,1) NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_leave_balance (user_id, leave_type_id, year),
            KEY idx_leave_balances_type (leave_type_id, year),
            CONSTRAINT fk_leave_balances_user FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE ON UPDATE RESTRICT,
            CONSTRAINT fk_leave_balances_type FOREIGN KEY (leave_type_id) REFERENCES leave_types (id)
                ON DELETE CASCADE ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->execute(
        "CREATE TABLE IF NOT EXISTS leave_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id $userKey NOT NULL,
            leave_type_id INT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            working_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            reason TEXT NULL,
            status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
            reviewed_by $userKey NULL,
            reviewed_at DATETIME NULL,
            review_note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_leave_user_status (user_id, status, start_date),
            KEY idx_leave_status_created (status, created_at),
            KEY idx_leave_type (leave_type_id),
            KEY idx_leave_reviewer (reviewed_by),
            CONSTRAINT fk_leave_requests_user FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE ON UPDATE RESTRICT,
            CONSTRAINT fk_leave_requests_type FOREIGN KEY (leave_type_id) REFERENCES leave_types (id)
                ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_leave_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id)
                ON DELETE SET NULL ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    /* ---------------------------------------------------------------------
     * Notifications, audit and login throttling
     * ------------------------------------------------------------------ */
    $db->execute(
        "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id $userKey NOT NULL,
            type VARCHAR(40) NOT NULL,
            title VARCHAR(120) NOT NULL,
            message VARCHAR(500) NOT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_user_read (user_id, is_read),
            KEY idx_notifications_created (created_at),
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->execute(
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id $userKey NULL,
            action VARCHAR(60) NOT NULL,
            entity_type VARCHAR(40) NULL,
            entity_id INT UNSIGNED NULL,
            description VARCHAR(500) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_audit_created (created_at),
            KEY idx_audit_user (user_id),
            KEY idx_audit_entity (entity_type, entity_id),
            KEY idx_audit_action (action),
            CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE SET NULL ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $db->execute(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(60) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            successful TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_login_username (username, attempted_at),
            KEY idx_login_ip (ip_address, attempted_at),
            KEY idx_login_attempted (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    Schema::note('HR module tables are ready. Configure departments, positions and leave types before production use.');
};
