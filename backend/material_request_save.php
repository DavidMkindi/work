<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin', 'production manager']);require_once 'config.php';
require_once 'production_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../material-request.php');
    exit();
}

if (!$connect || $connect->connect_error) {
    $_SESSION['error_message'] = 'Unable to connect to the database.';
    header('Location: ../material-request.php');
    exit();
}

$jobId        = (int) ($_POST['production_job_id'] ?? 0);
$notes        = trim($_POST['notes'] ?? '');
$action       = $_POST['action'] ?? 'submit';
$requestedBy  = (int) ($_SESSION['user_id'] ?? 0);
$requestedDate = date('Y-m-d');

// ---- Collect line items ------------------------------------------------
$rawNames   = $_POST['item_name']   ?? [];
$rawItems   = $_POST['item_id']     ?? [];
$rawQtys    = $_POST['quantity']    ?? [];
$rawUnits   = $_POST['unit']        ?? [];
$rawNotes   = $_POST['item_notes']  ?? [];

$lines = [];
for ($i = 0, $n = count($rawNames); $i < $n; $i++) {
    $name = trim($rawNames[$i] ?? '');
    $qty  = (int) ($rawQtys[$i] ?? 0);
    if ($name === '' || $qty <= 0) {
        continue;
    }
    $lines[] = [
        'item_id'  => (int) ($rawItems[$i] ?? 0) ?: null,
        'name'     => $name,
        'quantity' => $qty,
        'unit'     => trim($rawUnits[$i] ?? ''),
        'notes'    => trim($rawNotes[$i] ?? ''),
    ];
}

// ---- Validation ---------------------------------------------------------
if ($jobId <= 0) {
    $_SESSION['error_message'] = 'Please select a production job.';
    header('Location: ../material-request.php');
    exit();
}
if (empty($lines)) {
    $_SESSION['error_message'] = 'Add at least one material line with a quantity.';
    header('Location: ../material-request.php?job_id=' . $jobId);
    exit();
}

$status = $action === 'save_draft' ? 'Draft' : 'Submitted';

// ---- Create the request --------------------------------------------------
$mrNumber = mrGenerateNumber($connect);

$stmt = $connect->prepare(
    'INSERT INTO material_requests
        (mr_number, production_job_id, requested_by, requested_date, status, notes)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('siisss', $mrNumber, $jobId, $requestedBy, $requestedDate, $status, $notes);

if (!$stmt->execute()) {
    $stmt->close();
    $_SESSION['error_message'] = 'Unable to save the material request.';
    header('Location: ../material-request.php?job_id=' . $jobId);
    exit();
}
$mrId = $stmt->insert_id;
$stmt->close();

// ---- Insert line items ----------------------------------------------------
$ins = $connect->prepare(
    'INSERT INTO material_request_items
        (material_request_id, item_id, item_name, quantity, unit, notes)
     VALUES (?, ?, ?, ?, ?, ?)'
);
foreach ($lines as $line) {
    // Types must match the columns: request ID and item ID are integers;
    // the material name is a string. Binding it as an integer changed names
    // such as "Paper" to "0", so the store manager could not find its stock.
    $ins->bind_param('iissss', $mrId, $line['item_id'], $line['name'], $line['quantity'], $line['unit'], $line['notes']);
    $ins->execute();
}
$ins->close();

// ---- Notify store managers to review the material request --------------------
if ($status === 'Submitted') {
    $storeManagers = mrFindStoreManagers($connect);
    mrNotifyApprovers(
        $connect,
        $storeManagers,
        'Material Request Submitted',
        'Material request ' . $mrNumber . ' submitted. Please check if the materials are available in the store.',
        'material-requests.php'
    );
}

// ---- Audit trail -----------------------------------------------------------
jobAuditLog($connect, $requestedBy, $status === 'Draft' ? 'draft' : 'submit', 'material_requests', $mrId, ($status === 'Draft' ? 'Drafted ' : 'Submitted ') . $mrNumber);

$_SESSION['success_message'] = 'Material request ' . $mrNumber . ' ' . ($status === 'Draft' ? 'saved as draft.' : 'submitted for approval.');
header('Location: ../material-requests.php');
exit();
