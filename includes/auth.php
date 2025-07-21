<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn($userType) {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === $userType;
}

function requireLogin($userType) {
    if (!isLoggedIn($userType)) {
        header('Location: ../login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}

function logout() {
    session_destroy();
    header('Location: ../index.php');
    exit();
}

function redirectToDashboard($userType) {
    switch($userType) {
        case 'student':
            header('Location: student/dashboard.php');
            break;
        case 'startup':
            header('Location: startup/dashboard.php');
            break;
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        default:
            header('Location: index.php');
    }
    exit();
}

function sanitizeInput($data) {
    if ($data === null) return '';
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}
?>
