<?php
session_start();

if (!isset($_SESSION['admin_reg_pending'])) {
    header("Location: register.php");
    exit();
}

unset($_SESSION['admin_reg_pending'], $_SESSION['admin_otp_code'], $_SESSION['admin_otp_expires']);

header("Location: register.php?cancelled=1");
exit();
