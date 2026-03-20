<?php
session_start();

// Verwijder alle sessie variabelen
$_SESSION = [];

// Verwijder de sessie cookie
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p["path"], $p["domain"], $p["secure"], $p["httponly"]
    );
}

// Vernietigt de sessie
session_destroy();

header('Location: login.php');
exit();
?>
