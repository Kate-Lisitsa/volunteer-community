<?php
// pages/logout.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

logoutUser();
redirect(APP_URL . '/index.php');
?>