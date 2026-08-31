<?php
/**
 * Deletes a waste record.
 *
 * Only the Store Manager role is allowed to delete waste records.
 */
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin', 'production manager', 'store manager']);

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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid waste record.';
    header('Location: ../waste-records.php');
    exit();
}

$stmt = $connect->prepare('DELETE FROM waste_records WHERE id = ?');
$stmt->bind_param('i', $id);

if (!$stmt->execute() || $stmt->affected_rows === 0) {
    $stmt->close();
    $_SESSION['error_message'] = 'Unable to delete the waste record.';
    header('Location: ../waste-records.php');
    exit();
}
$stmt->close();

$_SESSION['success_message'] = 'Waste record deleted successfully.';
header('Location: ../waste-records.php');
exit();
