<?php
/**
 * Helpers for the production job request workflow.
 */

if (!function_exists('jobGenerateNumber')) {
    /**
     * Generate a sequential job number like JOB_000123.
     */
    function jobGenerateNumber(mysqli $connect): string {
        $result = $connect->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM production_jobs');
        $row = $result ? $result->fetch_assoc() : null;
        $next = (int) ($row['max_id'] ?? 0) + 1;
        return 'JOB_' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('jobFindUsersByRole')) {
    /**
     * Return user ids whose role matches any of the given roles.
     */
    function jobFindUsersByRole(mysqli $connect, array $roles): array {
        $ids = [];
        if (empty($roles)) {
            return $ids;
        }
        $in = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $connect->prepare("SELECT id FROM role WHERE LOWER(TRIM(role)) IN ($in)");
        $types = str_repeat('s', count($roles));
        $stmt->bind_param($types, ...$roles);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $stmt->close();
        return $ids;
    }
}

if (!function_exists('jobFindManagers')) {
    /**
     * Return user ids whose role matches the manager roles (production + store).
     */
    function jobFindManagers(mysqli $connect, array $roles = ['production manager', 'store manager']): array {
        return jobFindUsersByRole($connect, $roles);
    }
}

if (!function_exists('jobFindAdmins')) {
    /**
     * Return user ids whose role is administrator/admin. Admins always
     * receive a copy of every notification in the system.
     */
    function jobFindAdmins(mysqli $connect): array {
        return jobFindUsersByRole($connect, ['administrator', 'admin']);
    }
}

if (!function_exists('jobFindSupervisors')) {
    /**
     * Return user ids who should request raw materials for an approved job.
     * Uses the production supervisor role, falling back to production managers.
     */
    function jobFindSupervisors(mysqli $connect, array $roles = ['production supervisor', 'production manager']): array {
        $ids = jobFindUsersByRole($connect, $roles);
        return array_values(array_unique($ids));
    }
}

if (!function_exists('jobNotifyManagers')) {
    /**
     * Insert a notification row for each given manager user id.
     * Message format example: "New production job created - JOB_000123".
     */
    function jobNotifyManagers(mysqli $connect, array $managerIds, string $title, string $message, string $link = ''): void {
        $notifyIds = array_values(array_unique(array_merge($managerIds, jobFindAdmins($connect))));
        if (empty($notifyIds)) {
            return;
        }
        $stmt = $connect->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
        );
        $type = 'production_job';
        foreach ($notifyIds as $uid) {
            $stmt->bind_param('issss', $uid, $type, $title, $message, $link);
            $stmt->execute();
        }
        $stmt->close();
    }
}

if (!function_exists('mrGenerateNumber')) {
    /**
     * Generate a sequential material request number like MR_000123.
     */
    function mrGenerateNumber(mysqli $connect): string {
        $result = $connect->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM material_requests');
        $row = $result ? $result->fetch_assoc() : null;
        $next = (int) ($row['max_id'] ?? 0) + 1;
        return 'MR_' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('mrFindApprovers')) {
    /**
     * Return user ids whose role can approve material requests
     * (production manager + store manager).
     */
    function mrFindApprovers(mysqli $connect, array $roles = ['production manager', 'store manager']): array {
        return jobFindManagers($connect, $roles);
    }
}

if (!function_exists('mrNotifyApprovers')) {
    /**
     * Notify each approver that a material request needs attention.
     */
    function mrNotifyApprovers(mysqli $connect, array $approverIds, string $title, string $message, string $link = 'material-requests.php'): void {
        jobNotifyManagers($connect, $approverIds, $title, $message, $link);
    }
}

if (!function_exists('mrFindStoreManagers')) {
    /**
     * Return user ids whose role is 'store manager' (material request approvers).
     */
    function mrFindStoreManagers(mysqli $connect): array {
        return jobFindUsersByRole($connect, ['store manager']);
    }
}

if (!function_exists('mrGetAvailableStock')) {
    /**
     * Total available stock for a material item (matched by item name, case-insensitive).
     */
    function mrGetAvailableStock(mysqli $connect, string $itemName): float {
        $stmt = $connect->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM stock WHERE LOWER(TRIM(item)) = LOWER(TRIM(?))');
        $stmt->bind_param('s', $itemName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (float) ($row['total'] ?? 0);
    }
}

if (!function_exists('mrReserveStock')) {
    /**
     * Reserve (deduct) stock for a material item. Takes from warehouses with the
     * most stock first and records the movement. Returns false when stock is
     * insufficient; nothing is changed in that case.
     */
    function mrReserveStock(mysqli $connect, string $itemName, float $qty): bool {
        $qty = (float) $qty;
        if ($qty <= 0) {
            return true;
        }

        $stmt = $connect->prepare(
            'SELECT id, warehouse_id, item_id, quantity
             FROM stock
             WHERE LOWER(TRIM(item)) = LOWER(TRIM(?))
             ORDER BY quantity DESC'
        );
        $stmt->bind_param('s', $itemName);
        $stmt->execute();
        $result = $stmt->get_result();
        $stockRows = [];
        $available = 0.0;
        while ($row = $result->fetch_assoc()) {
            $stockRows[] = $row;
            $available += (float) $row['quantity'];
        }
        $stmt->close();

        if ($available < $qty) {
            return false;
        }

        $remaining = $qty;
        $createdBy = (string) ($_SESSION['user_name'] ?? $_SESSION['user_id'] ?? 'system');
        foreach ($stockRows as $row) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((float) $row['quantity'], $remaining);
            $newQty = (float) $row['quantity'] - $take;

            $upd = $connect->prepare('UPDATE stock SET quantity = ?, updated_at = NOW() WHERE id = ?');
            $upd->bind_param('di', $newQty, $row['id']);
            $upd->execute();
            $upd->close();

            $itemId = $row['item_id'] !== null ? (int) $row['item_id'] : null;
            $warehouseId = $row['warehouse_id'] !== null ? (int) $row['warehouse_id'] : null;
            $takeInt = (int) $take;
            $ins = $connect->prepare(
                'INSERT INTO stock_movements
                    (transaction_type, item, item_id, warehouse_id, quantity, created_by, created_at)
                 VALUES (\'Reserved\', ?, ?, ?, ?, ?, NOW())'
            );
            $ins->bind_param('siiis', $itemName, $itemId, $warehouseId, $takeInt, $createdBy);
            $ins->execute();
            $ins->close();

            $remaining -= $take;
        }
        return true;
    }
}

if (!function_exists('jobGetUserRole')) {
    /**
     * Return the role name for a user (role table uses the same id as users).
     */
    function jobGetUserRole(mysqli $connect, int $userId): string {
        $stmt = $connect->prepare('SELECT role FROM role WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['role'] ?? '';
    }
}

if (!function_exists('jobIsUserRole')) {
    /**
     * Check whether the current user holds one of the given roles (case-insensitive).
     */
    function jobIsUserRole(mysqli $connect, int $userId, array $roles): bool {
        $role = strtolower(trim(jobGetUserRole($connect, $userId)));
        foreach ($roles as $allowed) {
            if ($role === strtolower(trim($allowed))) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('jobNotifyUsers')) {
    /**
     * Insert a notification row for each given user id.
     */
    function jobNotifyUsers(mysqli $connect, array $userIds, string $title, string $message, string $link = ''): void {
        jobNotifyManagers($connect, $userIds, $title, $message, $link);
    }
}

if (!function_exists('jobAuditLog')) {
    /**
     * Record the action in audit_logs.
     */
    function jobAuditLog(mysqli $connect, ?int $userId, string $action, string $entityType, ?int $entityId, string $details = ''): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $connect->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ississ', $userId, $action, $entityType, $entityId, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}
