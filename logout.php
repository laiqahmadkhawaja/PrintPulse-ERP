<?php
require_once __DIR__ . '/config/init.php';

if (!empty($_SESSION['user']['id'])) {
    log_audit('LOGOUT', 'user', $_SESSION['user']['id'], "User logged out");
}

session_unset();
session_destroy();

header("Location: login.php");
exit;
