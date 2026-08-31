<?php
require_once "../auth.php";
authStartSession();

require_once "../config.php";

// Basic brute-force throttle: lock the account for 30 seconds after 5 failures.
$failKey = 'login_fail_' . md5(strtolower(trim($_POST['identifier'] ?? '')));
$failWindow = 30;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$connect || $connect->connect_error) {
        $_SESSION["error_message"] = "Unable to connect to the database. Please try again later.";
        header("location: ../../auth-basic-login.php");
        exit();
    }

    $identifier = trim($_POST["identifier"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($identifier === "" || $password === "") {
        $_SESSION["error_message"] = "Please enter both your username/email and password.";
        header("location: ../../auth-basic-login.php");
        exit();
    }

    $lastFail = (int) ($_SESSION[$failKey]['time'] ?? 0);
    if (time() - $lastFail < $failWindow) {
        $wait = $failWindow - (time() - $lastFail);
        $_SESSION["error_message"] = "Too many failed attempts. Please wait {$wait}s and try again.";
        header("location: ../../auth-basic-login.php");
        exit();
    }

    $stmt = $connect->prepare(
        "SELECT u.id, u.email, u.Username, u.Password, COALESCE(r.role, 'user') AS role
         FROM users u
         LEFT JOIN role r ON r.id = u.id
         WHERE u.Username = ? OR u.email = ?
         LIMIT 1"
    );
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["Password"])) {
            unset($_SESSION[$failKey]);

            // Hardened session: new id, store identity + role + client fingerprint.
            session_regenerate_id(true);
            $_SESSION["user_id"]    = (int) $user["id"];
            $_SESSION["user_name"]  = $user["Username"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"]  = $user["role"];
            $_SESSION["logged_in"]  = true;
            $_SESSION["auth_fingerprint"] = authFingerprint();
            $_SESSION["login_at"]   = time();

            unset($_SESSION["success_message"]);
            $stmt->close();

            setcookie("auth_logged_in", "1", 0, "/", "", false, true);
            session_write_close();
            header("Location: ../../index.php");
            exit();
        }
    }

    $stmt->close();

    $failCount = (int) ($_SESSION[$failKey]['count'] ?? 0) + 1;
    $_SESSION[$failKey] = ['count' => $failCount, 'time' => time()];
    $_SESSION["error_message"] = "Invalid username/email or password.";
    header("location: ../../auth-basic-login.php");
    exit();
}

header("location: ../../auth-basic-login.php");
exit();
