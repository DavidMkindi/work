<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin', 'store manager']);require_once 'config.php';
require_once 'production_helpers.php';
require_once 'stock_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../material-requests.php');
    exit();
}

if (!$connect || $connect->connect_error) {
    $_SESSION['error_message'] = 'Unable to connect to the database.';
    header('Location: ../material-requests.php');
    exit();
}

$mrId          = (int) ($_POST['mr_id'] ?? 0);
$action        = $_POST['action'] ?? '';
$approvalNotes = trim($_POST['approval_notes'] ?? '');
$approverId    = (int) ($_SESSION['user_id'] ?? 0);

// ---- Verify approver role (store manager or admin) -------------------------
$currentRole = authCurrentUserRole();
$isAdminUser = in_array($currentRole, ['administrator', 'admin'], true);
$storeManagers = mrFindStoreManagers($connect);
if (!$isAdminUser && !in_array($approverId, $storeManagers, true)) {
    $_SESSION['error_message'] = 'Only store managers can review material requests.';
    header('Location: ../material-requests.php');
    exit();
}

if ($action === 'approve') {
    $newStatus = 'Approved';
} elseif ($action === 'reject') {
    $newStatus = 'Rejected';
} else {
    $newStatus = null;
}

if ($mrId <= 0 || $newStatus === null) {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../material-requests.php');
    exit();
}

// ---- Fetch current status to allow only submitted/pending approval ----------
$stmt = $connect->prepare(
    'SELECT mr_number, requested_by FROM material_requests
     WHERE id = ? AND status IN (\'Submitted\', \'Pending Approval\')'
);
$stmt->bind_param('i', $mrId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['error_message'] = 'Material request not found or is not awaiting approval.';
    header('Location: ../material-requests.php');
    exit();
}
$mrNumber   = $row['mr_number'];
$requesterId = (int) $row['requested_by'];

// Notify the requester plus all production managers about the outcome.
$notifyIds = array_values(array_unique(array_merge([$requesterId], jobFindUsersByRole($connect, ['production manager']))));

// ---- Load line items --------------------------------------------------------
$items = [];
$stmt = $connect->prepare('SELECT id, item_name, quantity FROM material_request_items WHERE material_request_id = ?');
$stmt->bind_param('i', $mrId);
$stmt->execute();
$result = $stmt->get_result();
while ($r = $result->fetch_assoc()) {
    $items[] = [
        'id'          => (int) $r['id'],
        'item_name'   => (string) $r['item_name'],
        'quantity'    => (float) $r['quantity'],
        'approved_qty'=> 0.0,
    ];
}
$stmt->close();

// ---- REJECT -----------------------------------------------------------------
if ($newStatus === 'Rejected') {
    if ($approvalNotes === '') {
        $_SESSION['error_message'] = 'Please provide a rejection reason.';
        header('Location: ../material-requests.php');
        exit();
    }

    $stmt = $connect->prepare(
        'UPDATE material_requests
            SET status = ?, approved_by = ?, approved_at = NOW(), approval_notes = ?
          WHERE id = ?'
    );
    $stmt->bind_param('sisi', $newStatus, $approverId, $approvalNotes, $mrId);
    $ok = $stmt->execute() && $stmt->affected_rows >= 0;
    $stmt->close();

    if (!$ok) {
        $_SESSION['error_message'] = 'Unable to update the material request.';
        header('Location: ../material-requests.php');
        exit();
    }

    jobAuditLog($connect, $approverId, 'reject', 'material_requests', $mrId, ('Rejected ' . $mrNumber . ' - ' . $approvalNotes));
jobNotifyUsers(
        $connect,
        $notifyIds,
        'Material Request Rejected',
        'Material request ' . $mrNumber . ' has been rejected. Reason: ' . $approvalNotes,
        'material-requests.php'
    );

    $_SESSION['success_message'] = 'Material request ' . $mrNumber . ' has been rejected.';
    header('Location: ../material-requests.php');
    exit();
}

// ---- APPROVE: approve full requested quantities -------------------------------
foreach ($items as $i => $item) {
    $items[$i]['approved_qty'] = $item['quantity'];
}

// ---- Validate stock availability before making any changes -------------------
$shortages = [];
foreach ($items as $item) {
    $approved = $item['approved_qty'];
    if ($approved <= 0) {
        continue;
    }
    if ($approved > $item['quantity']) {
        $_SESSION['error_message'] = 'Approved quantity for "' . $item['item_name'] . '" cannot exceed the requested quantity.';
        header('Location: ../material-requests.php');
        exit();
    }
    $available = mrGetAvailableStock($connect, $item['item_name']);
    if ($available < $approved) {
        $shortages[] = $item['item_name'] . ' (available ' . number_format($available, 2) . ', requested ' . number_format($approved, 2) . ')';
    }
}

if (!empty($shortages)) {
    $_SESSION['error_message'] = 'Insufficient stock to approve: ' . implode('; ', $shortages);
    header('Location: ../material-requests.php');
    exit();
}

// ---- Reserve stock -----------------------------------------------------------
foreach ($items as $item) {
    if ($item['approved_qty'] > 0) {
        mrReserveStock($connect, $item['item_name'], $item['approved_qty']);
    }
}

if (function_exists('stockNotifyLowStock')) {
    stockNotifyLowStock($connect);
}

// ---- Persist approved quantities per line -------------------------------------
$upd = $connect->prepare('UPDATE material_request_items SET approved_quantity = ? WHERE id = ?');
foreach ($items as $item) {
    if ($item['approved_qty'] > 0) {
        $upd->bind_param('di', $item['approved_qty'], $item['id']);
        $upd->execute();
    }
}
$upd->close();

// ---- Update the material request ------------------------------------------------
$stmt = $connect->prepare(
    'UPDATE material_requests
        SET status = ?, approved_by = ?, approved_at = NOW(), approval_notes = ?
      WHERE id = ?'
);
$stmt->bind_param('sisi', $newStatus, $approverId, $approvalNotes, $mrId);
$ok = $stmt->execute() && $stmt->affected_rows >= 0;
$stmt->close();

if (!$ok) {
    $_SESSION['error_message'] = 'Unable to update the material request.';
    header('Location: ../material-requests.php');
    exit();
}

// ---- Notify the requester and production managers ------------------------------
jobNotifyUsers(
    $connect,
    $notifyIds,
    'Material Request Approved',
    'Material request ' . $mrNumber . ' has been approved',
    'material-requests.php'
);

jobAuditLog($connect, $approverId, strtolower(str_replace(' ', '-', $newStatus)), 'material_requests', $mrId, ($newStatus . ' ' . $mrNumber));

$_SESSION['success_message'] = 'Material request ' . $mrNumber . ' has been ' . strtolower($newStatus) . '.';
header('Location: ../material-requests.php');
exit();
