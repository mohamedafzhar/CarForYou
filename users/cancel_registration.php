<?php
session_start();

// Clear all registration session data
unset($_SESSION['reg_pending']);
unset($_SESSION['otp_code']);
unset($_SESSION['otp_expires']);

$_SESSION['success_msg'] = "Registration cancelled. You can start again anytime.";
header("Location: login.php?tab=register");
exit();
