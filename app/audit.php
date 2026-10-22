<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function client_ip(): string
{
    $candidates = [];
    if ((bool) config('security.trust_proxy', false)) {
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $candidates = array_map('trim', explode(',', $forwarded));
        }
    }
    $candidates[] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    foreach ($candidates as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return mb_substr($candidate, 0, 45);
        }
    }
    return 'unknown';
}

function client_user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/**
 * Record a security-relevant action. Never pass passwords, tokens or raw
 * personal data in $description.
 */
function audit_log(
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    string $description = ''
): void {
    try {
        $userId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
        db()->insert(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $userId,
                mb_substr($action, 0, 60),
                $entityType !== null ? mb_substr($entityType, 0, 40) : null,
                $entityId,
                mb_substr($description, 0, 500),
                client_ip(),
                client_user_agent(),
            ]
        );
    } catch (Throwable $exception) {
        app_log('error', 'Audit log write failed', [
            'action' => $action,
            'reason' => $exception->getMessage(),
        ]);
    }
}

function audit_action_label(string $action): string
{
    return match ($action) {
        'auth.login'              => 'Signed in',
        'auth.logout'             => 'Signed out',
        'auth.session_expired'    => 'Session expired',
        'auth.password_upgraded'  => 'Password upgraded',
        'password.change'         => 'Password changed',
        'employee.create'         => 'Employee created',
        'employee.update'         => 'Employee updated',
        'employee.archive'        => 'Employee archived',
        'employee.restore'        => 'Employee restored',
        'employee.photo'          => 'Employee photo updated',
        'account.create'          => 'Account created',
        'account.update'          => 'Account updated',
        'account.status'          => 'Account status changed',
        'account.password_reset'  => 'Password reset',
        'document.upload'         => 'Document uploaded',
        'document.delete'         => 'Document deleted',
        'department.create'       => 'Department created',
        'department.update'       => 'Department updated',
        'department.status'       => 'Department status changed',
        'position.create'         => 'Position created',
        'position.update'         => 'Position updated',
        'position.status'         => 'Position status changed',
        'leave.request'           => 'Leave requested',
        'leave.approve'           => 'Leave approved',
        'leave.reject'            => 'Leave rejected',
        'leave.cancel'            => 'Leave cancelled',
        'leave.type.create'       => 'Leave type created',
        'leave.type.update'       => 'Leave type updated',
        'leave.type.status'       => 'Leave type status changed',
        'leave.balance.set'       => 'Leave entitlement adjusted',
        'report.export'           => 'Report exported',
        'profile.update'          => 'Profile updated',
        default                   => ucwords(str_replace(['.', '_'], ' ', $action)),
    };
}

function audit_entity_label(?string $entityType): string
{
    return match ($entityType) {
        'user'             => 'Employee',
        'account'          => 'Account',
        'document'         => 'Document',
        'department'       => 'Department',
        'position'         => 'Position',
        'leave_request'    => 'Leave request',
        'leave_type'       => 'Leave type',
        default            => $entityType ? ucfirst($entityType) : '—',
    };
}
