<?php
session_start();
require_once 'includes/auth.php';

// Destroy session and redirect
session_destroy();
header('Location: index.php');
exit();
?>
