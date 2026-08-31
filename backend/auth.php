<?php
/**
 * Session / authentication helpers shared by every protected page.
 *
 * Provides: authStartSession(), authBaseUrl(), authFingerprint(),
 * authCurrentUserRole(), authHasRole(), requireLogin(), requireRole(),
 * requirePageAccess() and authLogout().
 */

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

function authStartSession(): void
{
    if (PHP_SESSION_NONE === session_status()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function authBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    // If the script is executed from within the backend/ folder, go up to app root
    if (substr($dir, -8) === '/backend') {
        $dir = substr($dir, 0, -8);
    }
    return $scheme . '://' . $host . $dir;
}

function authFingerprint(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
}

function authCurrentUserRole(): string
{
    return strtolower(trim((string) ($_SESSION['user_role'] ?? '')));
}

function authHasRole(array $roles): bool
{
    $role = authCurrentUserRole();

    // Administrator / admin can do everything in the system.
    if ($role === 'administrator' || $role === 'admin') {
        return true;
    }

    foreach ($roles as $allowed) {
        if ($role === strtolower(trim((string) $allowed))) {
            return true;
        }
    }
    return false;
}

function requireLogin(): void
{
    $loggedIn = !empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0;
    if ($loggedIn && !empty($_SESSION['auth_fingerprint']) && hash_equals($_SESSION['auth_fingerprint'], authFingerprint())) {
        return;
    }

    header('Location: ' . authBaseUrl() . '/auth-basic-login.php');
    exit();
}

function requireRole(array $roles): void
{
    if (authHasRole($roles)) {
        return;
    }

    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: ' . authBaseUrl() . '/index.php');
    exit();
}

function requirePageAccess(): void
{
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');

    $access = [
        'index.php'          => ['*'],
        'notifications.php'  => ['*'],
        'customers.php'      => ['administrator', 'admin', 'sales officer'],
        'customer-request.php' => ['administrator', 'admin', 'production manager'],
        'view-users.php'     => ['administrator', 'admin'],
        'userregister.php'   => ['administrator', 'admin'],
        'production-jobs.php' => ['administrator', 'admin', 'production manager', 'production supervisor', 'project manager', 'store manager'],
        'services.php'       => ['administrator', 'admin', 'production manager'],
        'material-request.php' => ['administrator', 'admin', 'production manager'],
        'material-requests.php' => ['administrator', 'admin', 'production manager', 'production supervisor', 'store manager'],
        'waste-records.php'  => ['administrator', 'admin', 'production manager', 'production supervisor', 'store manager'],
        'purchase-request.php' => ['administrator', 'admin', 'purchasing officer', 'store manager'],
        'purchase-orders.php' => ['administrator', 'admin', 'purchasing officer', 'store manager'],
        'goods-receiving.php' => ['administrator', 'admin', 'purchasing officer', 'store manager'],
        'stock-management.php' => ['administrator', 'admin', 'store manager'],
        'warehouses.php'     => ['administrator', 'admin', 'store manager'],
        'categories.php'     => ['administrator', 'admin', 'store manager'],
    ];

    $roles = $access[$page] ?? ['*'];

    if (in_array('*', $roles, true) || authHasRole($roles)) {
        return;
    }

    $_SESSION['error_message'] = 'You do not have permission to access this page.';
    header('Location: ' . authBaseUrl() . '/index.php');
    exit();
}

function authLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    setcookie('auth_logged_in', '', time() - 42000, '/', '', false, true);
    session_destroy();
}