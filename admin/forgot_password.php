<?php
session_start();
include 'config.php';

require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_SESSION['alogin'])) {
    header('Location: admin_dashboard.php'); exit();
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Look up admin by email
        $stmt = $conn->prepare(
            "SELECT id, username FROM admin WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res   = $stmt->get_result();
        $admin = $res->fetch_assoc();
        $stmt->close();

        if ($admin) {
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Clear old tokens, save new
            $del = $conn->prepare(
                "DELETE FROM admin_password_resets WHERE email = ?"
            );
            $del->bind_param("s", $email);
            $del->execute();

            $ins = $conn->prepare(
                "INSERT INTO admin_password_resets 
                 (email, token, expires_at) VALUES (?, ?, ?)"
            );
            $ins->bind_param("sss", $email, $token, $expires_at);
            $ins->execute();

            $name       = $admin['username'];
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] 
                        . "/carrental/admin/reset_password.php?token=" . $token;

            // Send via Brevo
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)SMTP_PORT;
                $mail->setFrom(MAIL_FROM, 'CarForYou Admin');
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'CarForYou Admin — Reset Your Password';
                $mail->Body    = "
<div style='font-family:sans-serif;max-width:520px;margin:auto;
            background:#0f1319;color:#f0f2f8;padding:36px;
            border-radius:16px;border:1px solid rgba(255,255,255,0.08)'>
    <h1 style='font-size:22px;font-weight:800;margin:0 0 24px'>
        Car<span style='color:#00d4ff'>ForYou</span> Admin
    </h1>
    <h2 style='font-size:18px;margin-bottom:8px'>Admin Password Reset</h2>
    <p style='color:#8892a4;font-size:14px;line-height:1.6;margin-bottom:20px'>
        Hi <strong style='color:#f0f2f8'>$name</strong>,<br><br>
        A password reset was requested for your admin account. 
        This link expires in <strong style='color:#fbbf24'>1 hour</strong>.
    </p>
    <div style='text-align:center;margin:28px 0'>
        <a href='$reset_link'
           style='padding:13px 28px;background:linear-gradient(135deg,#00d4ff,#0090ff);
                  color:#fff;border-radius:10px;text-decoration:none;
                  font-weight:700;font-size:14px'>
            Reset Admin Password
        </a>
    </div>
    <p style='color:#44505e;font-size:12px;border-top:1px solid rgba(255,255,255,0.06);
              padding-top:16px'>
        Link: <span style='color:#00d4ff;word-break:break-all'>$reset_link</span><br><br>
        If you didn't request this, your account may be at risk — 
        change your password immediately.
    </p>
</div>";
                $mail->send();
            } catch (Exception $e) {
                // Silent fail for security — don't reveal mail errors
            }
        }
        // Always show success (security: don't reveal if email exists)
        $success = true;
    }
}
?>