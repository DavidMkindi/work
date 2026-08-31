<?php
/**
 * Saves a new waste record tied to a production job.
 *
 * Flow: pick a production job -> pick one of its ordered materials ->
 * enter the wasted quantity (cannot exceed ordered minus already recorded).
 *
 * Only the Production Manager role is allowed to submit waste records.
 */
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin', 'production manager']);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../waste-records.php');
    exit();
}

if (!$connect || $connect->connect_error) {
    $_SESSION['error_message'] = 'Unable to connect to the database.';
    header('Location: ../waste-records.php');
    exit();
}

$jobId      = (int) ($_POST['production_job_id'] ?? 0);
$wasteType  = trim($_POST['waste_type'] ?? '');
$quantity   = (int) ($_POST['quantity'] ?? 0);
$reason     = trim($_POST['reason'] ?? '');
$employee   = trim($_SESSION['user_name'] ?? '');
$recordDate = trim($_POST['record_date'] ?? '');

// ---- Validation ---------------------------------------------------------
if ($jobId <= 0) {
    $_SESSION['error_message'] = 'Please select a production job.';
    header('Location: ../waste-records.php');
    exit();
}
if ($wasteType === '') {
    $_SESSION['error_message'] = 'Please select the material that was wasted.';
    header('Location: ../waste-records.php');
    exit();
}
if ($quantity <= 0) {
    $_SESSION['error_message'] = 'Quantity must be greater than zero.';
    header('Location: ../waste-records.php');
    exit();
}
if ($recordDate === '' || !strtotime($recordDate)) {
    $_SESSION['error_message'] = 'Please select a valid record date.';
    header('Location: ../waste-records.php');
    exit();
}
if ($employee === '') {
    $_SESSION['error_message'] = 'Unable to identify the current user.';
    header('Location: ../waste-records.php');
    exit();
}

// Make sure the selected job exists and is in Running status.
$stmt = $connect->prepare("SELECT job_number FROM production_jobs WHERE id = ? AND status = 'Running' LIMIT 1");
$stmt->bind_param('i', $jobId);
$stmt->execute();
$jobNumber = null;
$stmt->bind_result($jobNumber);
if (!$stmt->fetch()) {
    $stmt->close();
    $_SESSION['error_message'] = 'The selected production job does not exist or is not currently in Running status.';
    header('Location: ../waste-records.php');
    exit();
}
$stmt->close();

// The wasted material must be one ordered (approved material request) for this job.
$orderedQty = 0.0;
$stmt = $connect->prepare(
    "SELECT COALESCE(SUM(COALESCE(mri.approved_quantity, mri.quantity)), 0)
     FROM material_requests mr
     INNER JOIN material_request_items mri ON mri.material_request_id = mr.id
     WHERE mr.production_job_id = ? AND mr.status = 'Approved' AND mri.item_name = ?"
);
$stmt->bind_param('is', $jobId, $wasteType);
$stmt->execute();
$stmt->bind_result($orderedQty);
$stmt->fetch();
$stmt->close();

if ($orderedQty <= 0) {
    $_SESSION['error_message'] = '"' . $wasteType . '" is not an approved material for job ' . $jobNumber . '.';
    header('Location: ../waste-records.php');
    exit();
}

// How much waste has already been recorded for this job + material.
$alreadyWasted = 0.0;
$stmt = $connect->prepare(
    'SELECT COALESCE(SUM(quantity), 0) FROM waste_records WHERE production_job_id = ? AND waste_type = ?'
);
$stmt->bind_param('is', $jobId, $wasteType);
$stmt->execute();
$stmt->bind_result($alreadyWasted);
$stmt->fetch();
$stmt->close();

$remaining = (float) $orderedQty - (float) $alreadyWasted;
if ($quantity > $remaining) {
    $_SESSION['error_message'] = sprintf(
        'Waste quantity exceeds the remaining amount for "%s" on job %s. Ordered %s, already recorded %s, remaining %s.',
        $wasteType,
        $jobNumber,
        number_format($orderedQty),
        number_format($alreadyWasted),
        number_format(max(0, $remaining))
    );
    header('Location: ../waste-records.php');
    exit();
}

// ---- Insert the record ----------------------------------------------------
$stmt = $connect->prepare(
    'INSERT INTO waste_records (production_job_id, waste_type, quantity, reason, employee, record_date)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('isisss', $jobId, $wasteType, $quantity, $reason, $employee, $recordDate);

if (!$stmt->execute()) {
    $stmt->close();
    $_SESSION['error_message'] = 'Unable to save the waste record.';
    header('Location: ../waste-records.php');
    exit();
}
$stmt->close();

$_SESSION['success_message'] = 'Waste record saved successfully for job ' . $jobNumber . '.';
header('Location: ../waste-records.php');
exit();
