<?php
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "carrental";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

define('MAIL_FROM', 'amafzhar@gmail.com');
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_USERNAME', 'a52b1c001@smtp-brevo.com');
define('SMTP_PASSWORD', 'YOUR_SMTP_KEY_HERE');
define('SMTP_PORT', 587);

define('PAYHERE_MODE', 'sandbox');
define('PAYHERE_MERCHANT_ID', '1223456');
define('PAYHERE_MERCHANT_SECRET', 'your_merchant_secret_here');

function adminAuth() {
    if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
        header('Location: index.php');
        exit();
    }
}
?>