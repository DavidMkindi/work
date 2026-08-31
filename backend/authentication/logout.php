<?php
require_once "../auth.php";
authStartSession();
authLogout();
header("Location: ../../auth-basic-login.php");
exit();
