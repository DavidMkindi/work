<?php
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/config.php';

$id = (int) ($_GET['id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$isAjax = ($_GET['ajax'] ?? '') === '1';
$return = isset($_GET['return']) && is_string($_GET['return']) ? $_GET['return'] : null;

if ($id <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false]);
        exit();
    }
    header('Location: ../notifications.php');
    exit();
}

$link = 'notifications.php';
if ($connect && !$connect->connect_error) {
    // Mark this notification as read ONLY if it belongs to the current user.
    $stmt = $connect->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    $stmt->close();

    // Fetch the target link only if it belongs to the current user.
    $get = $connect->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
    $get->bind_param('ii', $id, $userId);
    $get->execute();
    $get->bind_result($targetLink);
    if ($get->fetch() && is_string($targetLink) && $targetLink !== '') {
        $link = $targetLink;
    }
    $get->close();
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'link' => $link]);
    exit();
}

if ($return) {
    header('Location: ../' . ltrim($return, '/'));
    exit();
}

header('Location: ../' . ltrim($link, '/'));
exit();
