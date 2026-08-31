<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin', 'production manager', 'store manager']);require_once 'config.php';
require_once 'production_helpers.php';

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

$jobId        = (int) ($_POST['job_id'] ?? 0);
$action       = $_POST['action'] ?? '';
$approvalNotes = trim($_POST['approval_notes'] ?? '');
$approverId   = (int) ($_SESSION['user_id'] ?? 0);

// ---- Verify approver role (production/store manager or admin) ---------------
$currentRole = authCurrentUserRole();
$isAdminUser = in_array($currentRole, ['administrator', 'admin'], true);
if (!$isAdminUser && !jobIsUserRole($connect, $approverId, ['production manager', 'store manager'])) {
    $_SESSION['error_message'] = 'You do not have permission to approve production jobs.';
    header('Location: ../production-jobs.php');
    exit();
}

$newStatus = null;
if ($action === 'approve') {
    $newStatus = 'Approved';
} elseif ($action === 'reject') {
    $newStatus = 'Rejected';
}

if ($jobId <= 0 || $newStatus === null) {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../production-jobs.php');
    exit();
}

// ---- Fetch current state to allow only submitted/pending approval ---------
$stmt = $connect->prepare('SELECT job_number, created_by FROM production_jobs WHERE id = ? AND status IN (\'Submitted\', \'Pending Approval\')');
$stmt->bind_param('i', $jobId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['error_message'] = 'Production job not found or is not awaiting approval.';
    header('Location: ../production-jobs.php');
    exit();
}
$jobNumber   = $row['job_number'];
$creatorId   = (int) ($row['created_by'] ?? 0);

// ---- Update the job -------------------------------------------------------
$stmt = $connect->prepare(
    'UPDATE production_jobs
        SET status = ?, approved_by = ?, approved_at = NOW(), approval_notes = ?
      WHERE id = ?'
);
$stmt->bind_param('sisi', $newStatus, $approverId, $approvalNotes, $jobId);

if (!$stmt->execute() || $stmt->affected_rows < 0) {
    $stmt->close();
    $_SESSION['error_message'] = 'Unable to update the production job.';
    header('Location: ../production-jobs.php');
    exit();
}
$stmt->close();

// ---- Notifications --------------------------------------------------------
if ($newStatus === 'Approved') {
    // Tell production supervisors they can now request raw materials.
    $supervisors = jobFindSupervisors($connect);
    jobNotifyUsers(
        $connect,
        $supervisors,
        'Production Job Approved',
        'Production job ' . $jobNumber . ' approved. You can now request the raw materials needed.',
        'material-request.php?job_id=' . $jobId
    );
} else {
    // Tell the person who submitted the job that it was rejected.
    if ($creatorId > 0) {
        jobNotifyUsers(
            $connect,
            [$creatorId],
            'Production Job Rejected',
            'Production job ' . $jobNumber . ' was rejected.' . ($approvalNotes !== '' ? ' Reason: ' . $approvalNotes : ''),
            'production-jobs.php'
        );
    }
}

// ---- Audit trail ----------------------------------------------------------
jobAuditLog($connect, $approverId, strtolower($newStatus), 'production_jobs', $jobId, ($newStatus . ' ' . $jobNumber . ($approvalNotes !== '' ? ' - ' . $approvalNotes : '')));

$_SESSION['success_message'] = 'Production job ' . $jobNumber . ' has been ' . strtolower($newStatus) . '.';
header('Location: ../production-jobs.php');
exit();