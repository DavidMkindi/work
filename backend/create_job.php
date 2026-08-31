<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin', 'production manager']);require_once 'config.php';
require_once 'production_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../customer-request.php');
    exit();
}

if (!$connect || $connect->connect_error) {
    $_SESSION['error_message'] = 'Unable to connect to the database.';
    header('Location: ../customer-request.php');
    exit();
}

$customerId = (int) ($_POST['customer_id'] ?? 0);
$serviceId  = (int) ($_POST['product_id'] ?? 0);
$quantity   = (int) ($_POST['quantity'] ?? 0);
$dueDate    = trim($_POST['due_date'] ?? '');
$machine    = trim($_POST['machine'] ?? '');
$operator   = trim($_POST['operator'] ?? '');
$priority   = trim($_POST['job_priority'] ?? 'Normal');
$bomId      = (int) ($_POST['bom_id'] ?? 0);
$notes      = trim($_POST['notes'] ?? '');
$action     = $_POST['action'] ?? 'submit';

$allowedPriorities = ['Low', 'Normal', 'High', 'Urgent'];
if (!in_array($priority, $allowedPriorities, true)) {
    $priority = 'Normal';
}

// ---- Validation -------------------------------------------------------
if ($customerId <= 0) {
    $_SESSION['error_message'] = 'Please select a customer.';
    header('Location: ../customer-request.php');
    exit();
}
if ($serviceId <= 0) {
    $_SESSION['error_message'] = 'Please select a service.';
    header('Location: ../customer-request.php');
    exit();
}
if ($quantity <= 0) {
    $_SESSION['error_message'] = 'Quantity must be a positive number.';
    header('Location: ../customer-request.php');
    exit();
}
if ($dueDate === '' || !strtotime($dueDate)) {
    $_SESSION['error_message'] = 'Please provide a valid due date.';
    header('Location: ../customer-request.php');
    exit();
}

// ---- Generate job number & insert the job -----------------------------
$jobNumber = jobGenerateNumber($connect);
$createdBy = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $connect->prepare(
    'INSERT INTO production_jobs
        (job_number, customer_id, service_id, quantity, due_date, machine, operator, job_priority, bom_id, created_by, approved_by, approved_at, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), \'Approved\')'
);
$bomRef = $bomId > 0 ? $bomId : null;
$stmt->bind_param('siiissssiii', $jobNumber, $customerId, $serviceId, $quantity, $dueDate, $machine, $operator, $priority, $bomRef, $createdBy, $createdBy);

if (!$stmt->execute()) {
    $stmt->close();
    $_SESSION['error_message'] = 'Unable to create the production job.';
    header('Location: ../customer-request.php');
    exit();
}
$jobId = $stmt->insert_id;
$stmt->close();

// ---- Notify production manager about the new job ----------------------------
$managers = jobFindUsersByRole($connect, ['production manager']);
$title = 'New Production Job';
$message = 'New production job created and ready for material request - ' . $jobNumber;
$link = 'material-request.php?job_id=' . $jobId;
jobNotifyManagers($connect, $managers, $title, $message, $link);

// ---- Audit trail -------------------------------------------------------
jobAuditLog($connect, $createdBy, 'create', 'production_jobs', $jobId, 'Created ' . $jobNumber . ' with priority ' . $priority . ' (auto-approved)');

$_SESSION['success_message'] = 'Production job ' . $jobNumber . ' created and ready for material request. Notifications sent to the production manager.';
header('Location: ../customer-request.php');
exit();
