<?php
require_once __DIR__ . '/../auth.php';
requireLogin();
requireRole(['administrator', 'admin']);

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: ../../userregister.php');
    exit();
}

if (!isset($connect) || $connect->connect_error) {
    $_SESSION['error_message'] = 'Unable to connect to the database. Please try again later.';
    header('Location: ../../userregister.php');
    exit();
}

$action = strtolower(trim($_POST['action'] ?? 'create'));
$fullName = trim($_POST['fullName'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$roleName = trim($_POST['role'] ?? 'user');
$permissions = $_POST['permissions'] ?? [];
$userIdInput = isset($_POST['userId']) && trim((string) $_POST['userId']) !== '' ? (int) $_POST['userId'] : 0;

// Handle deletion early so the required-field validation below does not block it.
if ($action === 'delete') {
    if ($userIdInput <= 0) {
        $_SESSION['error_message'] = 'Please provide a valid user ID to delete.';
        header('Location: ../../view-users.php');
        exit();
    }

    $checkUser = $connect->prepare('SELECT id FROM users WHERE id = ?');
    $checkUser->bind_param('i', $userIdInput);
    $checkUser->execute();
    $checkResult = $checkUser->get_result();

    if ($checkResult->num_rows === 0) {
        $_SESSION['error_message'] = 'No user was found to delete.';
        $checkUser->close();
        header('Location: ../../view-users.php');
        exit();
    }
    $checkUser->close();

    $deletePermissions = $connect->prepare('DELETE FROM permissions WHERE id = ?');
    $deletePermissions->bind_param('i', $userIdInput);
    $deletePermissions->execute();
    $deletePermissions->close();

    $deleteRole = $connect->prepare('DELETE FROM role WHERE id = ?');
    $deleteRole->bind_param('i', $userIdInput);
    $deleteRole->execute();
    $deleteRole->close();
    
    $deleteUser = $connect->prepare('DELETE FROM users WHERE id = ?');
    $deleteUser->bind_param('i', $userIdInput);

    if (!$deleteUser->execute()) {
        $_SESSION['error_message'] = 'Unable to delete the user.';
        $deleteUser->close();
        header('Location: ../../view-users.php');
        exit();
    }

    $deleteUser->close();
    $_SESSION['success_message'] = 'User has been deleted from the database.';
    header('Location: ../../view-users.php');
    exit();
}

if ($fullName === '' || $email === '') {
    $_SESSION['error_message'] = 'Please fill in all required fields.';
    header('Location: ../../userregister.php');
    exit();
}

if ($action === 'create' && $password === '') {
    $_SESSION['error_message'] = 'Please enter a password for the new user.';
    header('Location: ../../userregister.php');
    exit();
}

if (!is_array($permissions)) {
    $permissions = [$permissions];
}

$permissions = array_values(array_filter(array_map(function ($permission) {
    return strtolower(trim((string) $permission));
}, $permissions)));

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

if ($action === 'delete') {
    if ($userIdInput <= 0) {
        $_SESSION['error_message'] = 'Please provide a valid user ID to delete.';
        header('Location: ../../view-users.php');
        exit();
    }

    $checkUser = $connect->prepare('SELECT id FROM users WHERE id = ?');
    $checkUser->bind_param('i', $userIdInput);
    $checkUser->execute();
    $checkResult = $checkUser->get_result();

    if ($checkResult->num_rows === 0) {
        $_SESSION['error_message'] = 'No user was found to delete.';
        $checkUser->close();
        header('Location: ../../view-users.php');
        exit();
    }
    $checkUser->close();

    $deletePermissions = $connect->prepare('DELETE FROM permissions WHERE id = ?');
    $deletePermissions->bind_param('i', $userIdInput);
    $deletePermissions->execute();
    $deletePermissions->close();

    $deleteRole = $connect->prepare('DELETE FROM role WHERE id = ?');
    $deleteRole->bind_param('i', $userIdInput);
    $deleteRole->execute();
    $deleteRole->close();

    $deleteUser = $connect->prepare('DELETE FROM users WHERE id = ?');
    $deleteUser->bind_param('i', $userIdInput);

    if (!$deleteUser->execute()) {
        $_SESSION['error_message'] = 'Unable to delete the user.';
        $deleteUser->close();
        header('Location: ../../view-users.php');
        exit();
    }

    $deleteUser->close();
    $_SESSION['success_message'] = 'User has been deleted from the database.';
    header('Location: ../../view-users.php');
    exit();
}

if ($action === 'modify') {
    $userId = 0;

    if ($userIdInput > 0) {
        $findUser = $connect->prepare('SELECT id FROM users WHERE id = ?');
        $findUser->bind_param('i', $userIdInput);
    } else {
        $findUser = $connect->prepare('SELECT id FROM users WHERE email = ?');
        $findUser->bind_param('s', $email);
    }

    $findUser->execute();
    $userResult = $findUser->get_result();

    if ($userResult->num_rows > 0) {
        $userRow = $userResult->fetch_assoc();
        $userId = (int) $userRow['id'];
    }

    $findUser->close();

    if ($userId === 0) {
        $_SESSION['error_message'] = 'No matching user was found to update.';
        header('Location: ../../userregister.php');
        exit();
    }

    if ($password !== '') {
        // Only update the password when a new one was provided.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateUser = $connect->prepare('UPDATE users SET email = ?, Username = ?, Password = ? WHERE id = ?');
        $updateUser->bind_param('sssi', $email, $fullName, $hashedPassword, $userId);
    } else {
        $updateUser = $connect->prepare('UPDATE users SET email = ?, Username = ? WHERE id = ?');
        $updateUser->bind_param('ssi', $email, $fullName, $userId);
    }

    if (!$updateUser->execute()) {
        $_SESSION['error_message'] = 'Unable to update the user.';
        $updateUser->close();
        header('Location: ../../userregister.php');
        exit();
    }

    $updateUser->close();

    $updateRole = $connect->prepare('INSERT INTO role (id, role) VALUES (?, ?) ON DUPLICATE KEY UPDATE role = ?');
    $updateRole->bind_param('iss', $userId, $roleName, $roleName);

    if (!$updateRole->execute()) {
        $_SESSION['error_message'] = 'The user was updated, but the role could not be changed.';
        $updateRole->close();
        header('Location: ../../userregister.php');
        exit();
    }

    $updateRole->close();

    $deletePermissions = $connect->prepare('DELETE FROM permissions WHERE id = ?');
    $deletePermissions->bind_param('i', $userId);
    $deletePermissions->execute();
    $deletePermissions->close();

    $moduleName = 'users';
    foreach ($permissions as $permission) {
        $insertPermission = $connect->prepare('INSERT INTO permissions (id, module, action) VALUES (?, ?, ?)');
        $insertPermission->bind_param('iss', $userId, $moduleName, $permission);
        $insertPermission->execute();
        $insertPermission->close();
    }

    $_SESSION['success_message'] = 'User updated successfully.';
    header('Location: ../../userregister.php');
    exit();
}

$existing = $connect->prepare('SELECT id FROM users WHERE email = ? OR Username = ?');
$existing->bind_param('ss', $email, $fullName);
$existing->execute();
$result = $existing->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error_message'] = 'A user with this email or username already exists.';
    $existing->close();
    header('Location: ../../userregister.php');
    exit();
}

$existing->close();

$insertUser = $connect->prepare('INSERT INTO users (email, Username, Password) VALUES (?, ?, ?)');
$insertUser->bind_param('sss', $email, $fullName, $hashedPassword);

if (!$insertUser->execute()) {
    $_SESSION['error_message'] = 'Unable to create the user. Please try again.';
    $insertUser->close();
    header('Location: ../../userregister.php');
    exit();
}

$userId = $connect->insert_id;
$insertUser->close();

$insertRole = $connect->prepare('INSERT INTO role (id, role) VALUES (?, ?)');
$insertRole->bind_param('is', $userId, $roleName);

if (!$insertRole->execute()) {
    $_SESSION['error_message'] = 'The account was created, but the role could not be assigned.';
    $insertRole->close();
    header('Location: ../../userregister.php');
    exit();
}

$insertRole->close();

$moduleName = 'users';
foreach ($permissions as $permission) {
    $insertPermission = $connect->prepare('INSERT INTO permissions (id, module, action) VALUES (?, ?, ?)');
    $insertPermission->bind_param('iss', $userId, $moduleName, $permission);
    $insertPermission->execute();
    $insertPermission->close();
}

$_SESSION['success_message'] = 'User created successfully.';
header('Location: ../../userregister.php');
exit();
