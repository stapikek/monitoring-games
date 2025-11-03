<?php
// logout.php - выход из системы

session_start();

if (isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
}

header('Location: /');
exit;
