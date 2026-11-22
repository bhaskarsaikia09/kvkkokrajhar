<?php
require_once __DIR__ . '/app/auth.php';

unset($_SESSION['user_logged_in'], $_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_phone'], $_SESSION['user_role']);
header('Location: login.php');
exit;
