<?php

function stockGetRow(mysqli $connect, string $item, string $location): array {
    $stmt = $connect->prepare('SELECT * FROM stock WHERE item = ? AND location = ?');
    $stmt->bind_param('ss', $item, $location);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        $stmt->close();
        $ins = $connect->prepare('INSERT INTO stock (item, location, quantity) VALUES (?, ?, 0)');
        $ins->bind_param('ss', $item, $location);
        $ins->execute();
        $ins->close();

        $stmt = $connect->prepare('SELECT * FROM stock WHERE item = ? AND location = ?');
        $stmt->bind_param('ss', $item, $location);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
    }
    $stmt->close();
    return $row;
}

function stockLogMovement(mysqli $connect, string $type, string $item, int $qty, string $from, string $to, string $user): void {
    $stmt = $connect->prepare(
        'INSERT INTO stock_movements (transaction_type, item, quantity, from_location, to_location, created_by) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssisss', $type, $item, $qty, $from, $to, $user);
    $stmt->execute();
    $stmt->close();
}

function stockSetQuantity(mysqli $connect, string $item, string $location, int $newQty): void {
    stockGetRow($connect, $item, $location);
    $stmt = $connect->prepare('UPDATE stock SET quantity = ? WHERE item = ? AND location = ?');
    $stmt->bind_param('iss', $newQty, $item, $location);
    $stmt->execute();
    $stmt->close();
}

function stockAdjust(mysqli $connect, string $item, string $location, int $targetQty, string $user): array {
    $row = stockGetRow($connect, $item, $location);
    $current = (int) $row['quantity'];
    $changed = $targetQty !== $current;
    
    stockSetQuantity($connect, $item, $location, $targetQty);

    if ($changed) {
        stockLogMovement($connect, 'Stock Adjustment', $item, $targetQty, $location, '', $user);
    }

    return ['changed' => $changed, 'current' => $current];
}

function stockCount(mysqli $connect, string $item, string $location, int $countedQty, string $user): array {
    $row = stockGetRow($connect, $item, $location);
    $current = (int) $row['quantity'];
    $changed = $countedQty !== $current;

    stockSetQuantity($connect, $item, $location, $countedQty);

    stockLogMovement($connect, 'Stock Count', $item, $countedQty, $location, '', $user);

    return ['changed' => $changed, 'current' => $current];
}

function stockIn(mysqli $connect, string $item, string $location, int $qty, string $user): void {
    $row = stockGetRow($connect, $item, $location);
    $newQty = (int) $row['quantity'] + $qty;

    stockSetQuantity($connect, $item, $location, $newQty);
    stockLogMovement($connect, 'Stock In', $item, $qty, '', $location, $user);
}

function stockOut(mysqli $connect, string $item, string $location, int $qty, string $user): bool {
    $row = stockGetRow($connect, $item, $location);
    $current = (int) $row['quantity'];

    if ($qty > $current) {
        return false;
    }

    stockSetQuantity($connect, $item, $location, $current - $qty);
    stockLogMovement($connect, 'Stock Out', $item, -$qty, $location, '', $user);
    return true;
}

function stockDelete(mysqli $connect, string $item, string $location, string $user): bool {
    $stmt = $connect->prepare('DELETE FROM stock WHERE item = ? AND location = ?');
    $stmt->bind_param('ss', $item, $location);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        stockLogMovement($connect, 'Stock Out', $item, 0, $location, '', $user);
    }
    return $affected > 0;
}

function stockFindStoreManagerIds(mysqli $connect): array {
    $ids = [];
    $res = $connect->query("SELECT id FROM role WHERE LOWER(TRIM(role)) = 'store manager'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
    }
    return $ids;
}

function stockNotifyLowStock(mysqli $connect, int $threshold = 10): void {
    $stmt = $connect->prepare('SELECT item, location, quantity FROM stock WHERE quantity < ? ORDER BY quantity ASC');
    $stmt->bind_param('i', $threshold);
    $stmt->execute();
    $rows = $stmt->get_result();
    $lowItems = [];
    while ($row = $rows->fetch_assoc()) {
        $lowItems[] = $row;
    }
    $stmt->close();

    if (empty($lowItems)) {
        return;
    }

    $managerIds = stockFindStoreManagerIds($connect);
    $adminIds = [];
    $res = $connect->query("SELECT id FROM role WHERE LOWER(TRIM(role)) IN ('administrator','admin')");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $adminIds[] = (int) $row['id'];
        }
    }
    $managerIds = array_values(array_unique(array_merge($managerIds, $adminIds)));
    if (empty($managerIds)) {
        return;
    }

    $lines = [];
    foreach ($lowItems as $r) {
        $lines[] = $r['item'] . ' @ ' . $r['location'] . ': ' . (int) $r['quantity'];
    }
    $first = implode(', ', array_slice($lines, 0, 5));
    $more = count($lines) > 5 ? ' (+' . (count($lines) - 5) . ' more)' : '';
    $message = 'Low stock alert: ' . $first . $more . '. Please restock to avoid shortages.';

    $ins = $connect->prepare(
        'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
    );
    $type = 'low_stock';
    $title = 'Low Stock Alert';
    $link = 'stock-management.php';
    foreach ($managerIds as $uid) {
        $dup = $connect->prepare(
            'SELECT id FROM notifications WHERE user_id = ? AND type = ? AND is_read = 0 ORDER BY id DESC LIMIT 1'
        );
        $dup->bind_param('is', $uid, $type);
        $dup->execute();
        $hasUnread = $dup->get_result()->fetch_row() !== null;
        $dup->close();
        if ($hasUnread) {
            continue;
        }
        $ins->bind_param('issss', $uid, $type, $title, $message, $link);
        $ins->execute();
    }
    $ins->close();
}

function stockTransfer(mysqli $connect, string $item, string $fromLocation, string $toLocation, int $qty, string $user): bool {
    $row = stockGetRow($connect, $item, $fromLocation);
    $current = (int) $row['quantity'];

    if ($fromLocation === $toLocation) {
        return false;
    }

    if ($qty > $current) {
        return false;
    }

    stockSetQuantity($connect, $item, $fromLocation, $current - $qty);

    $target = stockGetRow($connect, $item, $toLocation);
    stockSetQuantity($connect, $item, $toLocation, (int) $target['quantity'] + $qty);

    stockLogMovement($connect, 'Stock Transfer', $item, $qty, $fromLocation, $toLocation, $user);
    return true;
}
