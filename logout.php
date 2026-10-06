<?php
/**
 * logout.php  —  end the session and clear the remember-me cookie.
 * Demonstrates: destroying a SESSION and deleting a COOKIE.
 */
require_once __DIR__ . '/config/functions.php';

$_SESSION = [];              // empty the session array
session_destroy();          // throw the session away

// Delete the remember-me cookie by setting it to expire in the past.
if (isset($_COOKIE['mo_remember'])) {
    setcookie('mo_remember', '', time() - 3600, '/');
}

redirect('login.php');
?>
