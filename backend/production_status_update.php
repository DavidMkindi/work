<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin', 'production manager', 'project manager']);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/production_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../production-jobs.php');
    exit();
}

if (!$connect || $connect->connect_error) {
    $_SESSION['error_message'] = 'Unable to connect to the database.';
    header('Location: ../production-jobs.php');
    exit();
}

$jobId      = (int) ($_POST['job_id'] ?? 0);
$newStatus  = trim((string) ($_POST['status'] ?? ''));
$actorId    = (int) ($_SESSION['user_id'] ?? 0);

// ---- Permission check: production manager / project manager (or admin) can change status
if (!jobIsUserRole($connect, $actorId, ['production manager', 'project manager', 'administrator', 'admin'])) {
    $_SESSION['error_message'] = 'Only the Production or Project Manager can update job statuses.';
    header('Location: ../production-jobs.php');
    exit();
}

// ---- Whitelist of statuses the production manager may set directly
$allowedStatuses = ['Approved', 'Running', 'Completed', 'Cancelled'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    $_SESSION['error_message'] = 'The selected status is not valid for a production manager.';
    header('Location: ../production-jobs.php');
    exit();
}

if ($jobId <= 0) {
    $_SESSION['error_message'] = 'Invalid production job.';
    header('Location: ../production-jobs.php');
    exit();
}

// ---- Fetch current state --------------------------------------------------
$stmt = $connect->prepare('SELECT job_number, status, created_by FROM production_jobs WHERE id = ?');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['error_message'] = 'Production job not found.';
    header('Location: ../production-jobs.php');
    exit();
}

$jobNumber = $row['job_number'];
$oldStatus = $row['status'];
$creatorId = (int) ($row['created_by'] ?? 0);

// Rejected jobs cannot be revived into the production flow.
if ($oldStatus === 'Rejected' && $newStatus !== 'Cancelled') {
    $_SESSION['error_message'] = 'A rejected production job cannot be started.';
    header('Location: ../production-jobs.php');
    exit();
}

if ($oldStatus === $newStatus) {
    $_SESSION['info_message'] = 'Production job ' . $jobNumber . ' is already ' . $newStatus . '.';
    header('Location: ../production-jobs.php');
    exit();
}

// ---- Update the status ----------------------------------------------------
$stmt = $connect->prepare('UPDATE production_jobs SET status = ? WHERE id = ?');
$stmt->bind_param('si', $newStatus, $jobId);

if (!$stmt->execute() || $stmt->affected_rows < 0) {
    $stmt->close();
    $_SESSION['error_message'] = 'Unable to update the production job status.';
    header('Location: ../production-jobs.php');
    exit();
}
$stmt->close();

// ---- Notifications --------------------------------------------------------
$actorName = (string) ($_SESSION['user_name'] ?? 'Production Manager');
if ($newStatus === 'Completed' && $creatorId > 0) {
    jobNotifyUsers(
        $connect,
        [$creatorId],
        'Production Job Completed',
        'Production job ' . $jobNumber . ' has been marked as Completed.',
        'production-jobs.php'
    );
} else {
    $managers = jobFindUsersByRole($connect, ['production manager']);
    jobNotifyUsers(
        $connect,
        $managers,
        'Production Job Status Updated',
        'Production job ' . $jobNumber . ' status changed from ' . $oldStatus . ' to ' . $newStatus . ' by ' . $actorName . '.',
        'production-jobs.php'
    );
}

// ---- Audit trail ----------------------------------------------------------
jobAuditLog($connect, $actorId, 'update_status', 'production_jobs', $jobId, 'Status changed from ' . $oldStatus . ' to ' . $newStatus . ' for ' . $jobNumber);

$_SESSION['success_message'] = 'Production job ' . $jobNumber . ' status updated to ' . $newStatus . '.';
header('Location: ../production-jobs.php');
exit();
