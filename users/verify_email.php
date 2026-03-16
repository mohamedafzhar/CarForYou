<?php
session_start();
require_once('../includes/config.php');

$token = trim($_GET['token'] ?? '');
$msg   = '';
$ok    = false;

if ($token) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE verification_token = ? AND email_verified = 0 LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $upd = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
        $upd->bind_param("i", $user['id']);
        $upd->execute();
        $ok  = true;
        $msg = "Email verified! You can now sign in.";
    } else {
        $msg = "Invalid or already used verification link.";
    }
}

$_SESSION['success_msg'] = $ok ? $msg : null;
$_SESSION['error_msg']   = !$ok ? $msg : null;
header("Location: login.php"); exit();