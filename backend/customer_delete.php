<?php
require_once __DIR__ . '/auth.php';
requireLogin();
requireRole(['administrator', 'admin']);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../customers.php');
    exit();
}

if (!$connect || $connect->connect_error) {
    $_SESSION['error_message'] = 'Unable to connect to the database.';
    header('Location: ../customers.php');
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid customer.';
    header('Location: ../customers.php');
    exit();
}

$stmt = $connect->prepare('SELECT company_name FROM customers WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['error_message'] = 'Customer not found.';
    header('Location: ../customers.php');
    exit();
}

$companyName = $row['company_name'];

try {
    $stmt = $connect->prepare('DELETE FROM customers WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $_SESSION['error_message'] = 'Unable to delete the customer.';
        header('Location: ../customers.php');
        exit();
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1451) {
        $_SESSION['error_message'] = 'Cannot delete "' . $companyName . '" because it is linked to existing production jobs or other records.';
    } else {
        $_SESSION['error_message'] = 'Unable to delete the customer.';
    }
    header('Location: ../customers.php');
    exit();
}

$_SESSION['success_message'] = 'Customer "' . $companyName . '" deleted successfully.';
header('Location: ../customers.php');
exit();
