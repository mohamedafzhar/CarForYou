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

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $fp_email = trim($_POST['email'] ?? '');
    if (empty($fp_email) || !filter_var($fp_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, username FROM admin WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $fp_email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($admin) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $name = $admin['username'];
            $del = $conn->prepare("DELETE FROM admin_password_resets WHERE email = ?");
            $del->bind_param("s", $fp_email);
            $del->execute();
            $ins = $conn->prepare("INSERT INTO admin_password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $fp_email, $token, $expires_at);
            $ins->execute();
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/carrental/admin/reset_password.php?token=" . $token;
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int)SMTP_PORT;
                $mail->setFrom(MAIL_FROM, 'CarForYou Admin');
                $mail->addAddress($fp_email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'CarForYou Admin — Reset Your Password';
                $mail->Body = "<div style='font-family:sans-serif;max-width:520px;margin:auto;background:#0f1319;color:#f0f2f8;padding:36px;border-radius:16px;border:1px solid rgba(255,255,255,0.08)'><h1 style='font-size:22px;font-weight:800;margin:0'>Car<span style='color:#4f8ef7'>ForYou</span> Admin</h1><p style='color:#8892a4;font-size:14px;'>Hi <strong>$name</strong>, Reset your password: <a href='$reset_link' style='color:#4f8ef7'>Reset Now</a></p></div>";
                $mail->send();
                $msg = "Password reset link sent! Check your email inbox.";
            } catch (Exception $e) {
                $error = "Failed to send email.";
            }
        } else {
            $error = "This email is not registered as an admin.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['admin_csrf'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'];
        $window = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $chk = $conn->prepare("SELECT COUNT(*) AS attempts FROM admin_login_attempts WHERE username = ? AND ip_address = ? AND attempted_at > ? AND success = 0");
        $chk->bind_param("sss", $username, $ip, $window);
        $chk->execute();
        $attempts = $chk->get_result()->fetch_assoc()['attempts'];
        $chk->close();
        if ($attempts >= 5) {
            $error = "Too many failed attempts. Please wait 10 minutes.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $valid = $admin && password_verify($password, $admin['password']);
            if ($valid) {
                session_regenerate_id(true);
                $_SESSION['alogin'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                $log = $conn->prepare("INSERT INTO admin_login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, 1, NOW())");
                $log->bind_param("ss", $username, $ip);
                $log->execute();
                $clr = $conn->prepare("DELETE FROM admin_login_attempts WHERE username = ? AND success = 0");
                $clr->bind_param("s", $username);
                $clr->execute();
                $upd = $conn->prepare("UPDATE admin SET last_login = NOW() WHERE id = ?");
                $upd->bind_param("i", $admin['id']);
                $upd->execute();
                header('Location: admin_dashboard.php'); exit();
            } else {
                $log = $conn->prepare("INSERT INTO admin_login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, 0, NOW())");
                $log->bind_param("ss", $username, $ip);
                $log->execute();
                $remaining = max(0, 5 - ($attempts + 1));
                $error = $remaining > 0 ? "Invalid credentials. {$remaining} attempt" . ($remaining == 1 ? '' : 's') . " remaining." : "Too many failed attempts.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | CarForYou</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; }

        [data-theme="dark"] {
            --bg: #050810;
            --surface: #0d1421;
            --surface2: #131c2e;
            --surface3: #1a2540;
            --border: rgba(79, 142, 247, 0.08);
            --border2: rgba(79, 142, 247, 0.15);
            --text: #e8edf5;
            --text2: #7a93b0;
            --text3: #3d5570;
            --accent: #4f8ef7;
            --accent2: #7db0fb;
            --accentglow: rgba(79, 142, 247, 0.3);
            --accentglow2: rgba(79, 142, 247, 0.1);
            --green: #22c55e;
            --red: #ef4444;
            --shadow: 0 25px 80px rgba(0, 0, 0, 0.8);
            --grid-color: rgba(79, 142, 247, 0.03);
        }
        [data-theme="light"] {
            --bg: #e8edf8;
            --surface: #ffffff;
            --surface2: #f5f8fc;
            --surface3: #eaf0f8;
            --border: rgba(37, 99, 235, 0.08);
            --border2: rgba(37, 99, 235, 0.18);
            --text: #1c2b3a;
            --text2: #4a607a;
            --text3: #8fa3bb;
            --accent: #2563eb;
            --accent2: #3b82f6;
            --accentglow: rgba(37, 99, 235, 0.25);
            --accentglow2: rgba(37, 99, 235, 0.08);
            --green: #16a34a;
            --red: #dc2626;
            --shadow: 0 25px 80px rgba(0, 0, 0, 0.12);
            --grid-color: rgba(37, 99, 235, 0.04);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
            perspective: 1000px;
        }

        /* 3D Background Effects */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }
        .bg-gradient {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 50% 50% at 20% 20%, rgba(79, 142, 247, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 50% at 80% 80%, rgba(125, 176, 251, 0.08) 0%, transparent 60%),
                radial-gradient(ellipse 80% 60% at 50% 50%, rgba(79, 142, 247, 0.04) 0%, transparent 70%);
            animation: gradientPulse 8s ease-in-out infinite;
        }
        @keyframes gradientPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridFloat 15s linear infinite;
            transform-style: preserve-3d;
        }
        @keyframes gridFloat {
            from { transform: perspective(500px) rotateX(60deg) translateY(0); }
            to { transform: perspective(500px) rotateX(60deg) translateY(50px); }
        }
        .bg-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 600px;
            height: 600px;
            transform: translate(-50%, -50%);
            background: radial-gradient(circle, var(--accentglow) 0%, transparent 70%);
            filter: blur(80px);
            animation: glowPulse 4s ease-in-out infinite;
        }
        @keyframes glowPulse {
            0%, 100% { opacity: 0.5; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 0.8; transform: translate(-50%, -50%) scale(1.1); }
        }

        /* Floating Shapes */
        .floating-shapes {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }
        .shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            opacity: 0.1;
            filter: blur(1px);
            animation: float3D 20s ease-in-out infinite;
        }
        .shape:nth-child(1) { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
        .shape:nth-child(2) { width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: -5s; }
        .shape:nth-child(3) { width: 150px; height: 150px; top: 50%; left: 10%; animation-delay: -10s; }
        .shape:nth-child(4) { width: 100px; height: 100px; bottom: 20%; right: 15%; animation-delay: -15s; }
        @keyframes float3D {
            0%, 100% { transform: translateY(0) rotateX(0) rotateY(0) scale(1); }
            25% { transform: translateY(-30px) rotateX(15deg) rotateY(10deg) scale(1.05); }
            50% { transform: translateY(-20px) rotateX(-10deg) rotateY(-15deg) scale(0.95); }
            75% { transform: translateY(-40px) rotateX(20deg) rotateY(5deg) scale(1.02); }
        }

        /* Theme Toggle */
        .theme-corner {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100;
        }
        .theme-btn {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--surface);
            border: 1px solid var(--border2);
            color: var(--text2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .theme-btn:hover {
            transform: scale(1.15) rotate(15deg);
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: 0 0 30px var(--accentglow);
        }

        /* Main Container */
        .login-wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            transform-style: preserve-3d;
            animation: containerFloat 6s ease-in-out infinite;
        }
        @keyframes containerFloat {
            0%, 100% { transform: translateY(0) rotateX(0); }
            50% { transform: translateY(-10px) rotateX(1deg); }
        }

        /* 3D Card */
        .login-card {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 28px;
            padding: 48px 40px;
            box-shadow: var(--shadow);
            position: relative;
            transform-style: preserve-3d;
            animation: card3D 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes card3D {
            0% { opacity: 0; transform: translateY(80px) rotateX(15deg) scale(0.9); }
            100% { opacity: 1; transform: translateY(0) rotateX(0) scale(1); }
        }
        .login-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 28px;
            padding: 2px;
            background: linear-gradient(135deg, var(--accent), var(--accent2), transparent, var(--accent));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0.5;
            animation: borderGlow 3s linear infinite;
        }
        @keyframes borderGlow {
            0% { opacity: 0.3; }
            50% { opacity: 0.6; }
            100% { opacity: 0.3; }
        }
        .login-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 120px;
            background: linear-gradient(180deg, var(--accentglow2) 0%, transparent 100%);
            border-radius: 28px 28px 0 0;
            pointer-events: none;
        }

        /* Brand */
        .brand {
            text-align: center;
            margin-bottom: 36px;
            animation: brandFade 0.8s ease 0.3s both;
        }
        @keyframes brandFade {
            from { opacity: 0; transform: translateY(-20px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 20px;
            font-size: 1.8rem;
            color: #fff;
            margin-bottom: 18px;
            box-shadow:
                0 15px 40px var(--accentglow),
                0 0 80px var(--accentglow2),
                inset 0 2px 0 rgba(255, 255, 255, 0.2),
                inset 0 -2px 0 rgba(0, 0, 0, 0.1);
            transform: perspective(500px) rotateX(10deg);
            transition: transform 0.5s ease;
            animation: logoFloat 3s ease-in-out infinite;
        }
        @keyframes logoFloat {
            0%, 100% { transform: perspective(500px) rotateX(10deg) translateY(0); }
            50% { transform: perspective(500px) rotateX(5deg) translateY(-5px); }
        }
        .brand-logo:hover { transform: perspective(500px) rotateX(0) scale(1.05); }
        .brand h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        .brand h1 span { color: var(--accent); }
        .brand p {
            font-size: 0.72rem;
            color: var(--text3);
            letter-spacing: 0.16em;
            margin-top: 8px;
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border2), transparent);
            margin-bottom: 32px;
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1.5;
            margin-bottom: 20px;
            animation: alertPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes alertPop {
            from { opacity: 0; transform: scale(0.8) translateX(-20px); }
            to { opacity: 1; transform: scale(1) translateX(0); }
        }
        .alert i { flex-shrink: 0; margin-top: 2px; font-size: 1rem; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: var(--red); border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-success { background: rgba(34, 197, 94, 0.1); color: var(--green); border: 1px solid rgba(34, 197, 94, 0.2); }

        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text3);
        }
        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrap .field-icon {
            position: absolute;
            left: 16px;
            color: var(--text3);
            font-size: 0.9rem;
            pointer-events: none;
            z-index: 2;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .input-wrap:focus-within .field-icon {
            color: var(--accent);
            transform: scale(1.1) translateX(2px);
        }
        .form-control {
            width: 100%;
            padding: 16px 48px 16px 46px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 14px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .form-control::placeholder { color: var(--text3); }
        .form-control:focus {
            border-color: var(--accent);
            background: var(--surface3);
            box-shadow: 0 0 0 4px var(--accentglow2), 0 10px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(-3px);
        }
        .pw-toggle {
            position: absolute;
            right: 0;
            width: 48px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text3);
            background: none;
            border: none;
            font-size: 0.9rem;
            cursor: pointer;
            z-index: 2;
            transition: all 0.4s ease;
        }
        .pw-toggle:hover { color: var(--accent); transform: scale(1.1); }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none;
            border-radius: 16px;
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow:
                0 10px 40px var(--accentglow),
                inset 0 2px 0 rgba(255, 255, 255, 0.2),
                inset 0 -2px 0 rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            animation: btnPulse 3s ease-in-out infinite;
        }
        @keyframes btnPulse {
            0%, 100% { box-shadow: 0 10px 40px var(--accentglow), inset 0 2px 0 rgba(255, 255, 255, 0.2); }
            50% { box-shadow: 0 15px 50px var(--accentglow), inset 0 2px 0 rgba(255, 255, 255, 0.2); }
        }
        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.3), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .btn-login:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 20px 60px var(--accentglow);
        }
        .btn-login:hover::before { opacity: 1; }
        .btn-login:active { transform: translateY(-2px) scale(0.98); }

        /* Forgot Link */
        .forgot-wrap {
            text-align: center;
            margin-top: 24px;
            animation: fadeIn 0.6s ease 0.5s both;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .forgot-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text3);
            text-decoration: none;
            transition: all 0.4s ease;
        }
        .forgot-link:hover { color: var(--accent); transform: translateX(5px); }

        .login-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 0.72rem;
            color: var(--text3);
            letter-spacing: 0.04em;
            animation: fadeIn 0.6s ease 0.6s both;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            z-index: 500;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 24px;
            padding: 36px;
            width: 100%;
            max-width: 420px;
            position: relative;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
            animation: modalPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.8) translateY(30px) rotateX(15deg); }
            to { opacity: 1; transform: scale(1) translateY(0) rotateX(0); }
        }
        .modal h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal h3 i { color: var(--accent); }
        .modal-sub { font-size: 0.84rem; color: var(--text2); margin-bottom: 24px; line-height: 1.5; }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .modal-close:hover { background: var(--accent); color: #fff; transform: rotate(90deg); }
        .btn-reset {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none;
            border-radius: 14px;
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 30px var(--accentglow);
        }
        .btn-reset:hover { transform: translateY(-3px); box-shadow: 0 15px 50px var(--accentglow); }
        .modal-success { text-align: center; padding: 12px 0; }
        .modal-success-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(34, 197, 94, 0.1);
            border: 2px solid rgba(34, 197, 94, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--green);
            margin: 0 auto 18px;
            animation: iconPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes iconPop {
            from { transform: scale(0) rotate(-180deg); }
            to { transform: scale(1) rotate(0); }
        }
        .modal-success h4 { font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 8px; }
        .modal-success p { font-size: 0.84rem; color: var(--text2); line-height: 1.6; }
        .inbox-tips { display: flex; gap: 10px; margin-top: 20px; }
        .tip-card {
            flex: 1;
            padding: 12px 10px;
            border-radius: 12px;
            text-align: center;
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1.4;
        }
        .tip-inbox { background: rgba(79, 142, 247, 0.1); border: 1px solid rgba(79, 142, 247, 0.2); color: var(--accent); }
        .tip-spam { background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .tip-card i { display: block; font-size: 1.2rem; margin-bottom: 6px; }
        .tip-divider { height: 1px; background: var(--border); margin: 16px 0; }
        .tip-note { font-size: 0.72rem; color: var(--text3); text-align: center; line-height: 1.5; }
    </style>
</head>
<body>
    <!-- Background Effects -->
    <div class="bg-layer">
        <div class="bg-gradient"></div>
        <div class="bg-grid"></div>
        <div class="bg-glow"></div>
    </div>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="theme-corner">
        <button class="theme-btn" id="themeBtn" title="Toggle Theme">
            <i class="fa fa-moon" id="themeIcon"></i>
        </button>
    </div>

    <div class="login-wrap">
        <div class="login-card">
            <div class="brand">
                <div class="brand-logo"><i class="fa fa-car-side"></i></div>
                <h1>Car<span>ForYou</span></h1>
                <p>ADMIN CONSOLE · SIGN IN TO CONTINUE</p>
            </div>

            <div class="divider"></div>

            <?php if (isset($_SESSION['admin_success'])): ?>
                <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($_SESSION['admin_success']); unset($_SESSION['admin_success']); ?></div>
            <?php endif; ?>

            <?php if ($error && !isset($_POST['forgot_password'])): ?>
                <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrap">
                        <i class="fa fa-user field-icon"></i>
                        <input type="text" name="username" class="form-control" placeholder="Enter your username" required autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i class="fa fa-lock field-icon"></i>
                        <input type="password" name="password" id="loginPwd" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" id="eyeLoginPwd" tabindex="-1">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" name="login" class="btn-login">
                    <i class="fa fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="forgot-wrap">
                <a href="#" class="forgot-link" onclick="openForgotModal(); return false;">
                    <i class="fa fa-key"></i> Forgot Password?
                </a>
            </div>

            <div class="login-footer">Secure admin area — unauthorised access is prohibited</div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal-overlay" id="forgotModal">
        <div class="modal">
            <button class="modal-close" onclick="closeForgotModal()"><i class="fa fa-xmark"></i></button>

            <?php if ($msg && isset($_POST['forgot_password'])): ?>
                <div class="modal-success">
                    <div class="modal-success-icon"><i class="fa fa-envelope-circle-check"></i></div>
                    <h4>Reset Link Sent!</h4>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                    <div class="inbox-tips">
                        <div class="tip-card tip-inbox"><i class="fa fa-inbox"></i> Check your<br><strong>Inbox</strong></div>
                        <div class="tip-card tip-spam"><i class="fa fa-triangle-exclamation"></i> Not there?<br><strong>Check Spam</strong></div>
                    </div>
                    <div class="tip-divider"></div>
                    <p class="tip-note"><i class="fa fa-clock" style="color:var(--accent);margin-right:4px;"></i> Link expires in <strong style="color:var(--accent)">1 hour</strong></p>
                </div>
            <?php elseif ($error && isset($_POST['forgot_password'])): ?>
                <h3><i class="fa fa-lock-open"></i> Reset Admin Password</h3>
                <p class="modal-sub">Enter your registered email and we'll send you a secure reset link.</p>
                <div class="alert alert-error" style="margin-bottom:20px;"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
                <form method="POST">
                    <div class="form-group">
                        <label>Registered Email</label>
                        <div class="input-wrap">
                            <i class="fa fa-envelope field-icon"></i>
                            <input type="email" name="email" class="form-control" placeholder="admin@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                        </div>
                    </div>
                    <button type="submit" name="forgot_password" class="btn-reset"><i class="fa fa-paper-plane"></i> Send Reset Link</button>
                </form>
            <?php else: ?>
                <h3><i class="fa fa-lock-open"></i> Reset Admin Password</h3>
                <p class="modal-sub">Enter your registered email and we'll send you a secure reset link.</p>
                <form method="POST">
                    <div class="form-group">
                        <label>Registered Email</label>
                        <div class="input-wrap">
                            <i class="fa fa-envelope field-icon"></i>
                            <input type="email" name="email" class="form-control" placeholder="admin@example.com" required autofocus>
                        </div>
                    </div>
                    <button type="submit" name="forgot_password" class="btn-reset"><i class="fa fa-paper-plane"></i> Send Reset Link</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme
        var theme = localStorage.getItem('adminTheme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        syncIcon();
        document.getElementById('themeBtn').addEventListener('click', function() {
            theme = theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('adminTheme', theme);
            syncIcon();
        });
        function syncIcon() { document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun'; }

        // Modal
        function openForgotModal() { document.getElementById('forgotModal').classList.add('open'); }
        function closeForgotModal() { document.getElementById('forgotModal').classList.remove('open'); }
        document.getElementById('forgotModal').addEventListener('click', function(e) { if (e.target === this) closeForgotModal(); });

        <?php if (isset($_POST['forgot_password'])): ?>
        openForgotModal();
        <?php endif; ?>

        // Password toggle
        function bindHoldPwd(btnId, inputId) {
            var btn = document.getElementById(btnId), inp = document.getElementById(inputId);
            if (!btn || !inp) return;
            var icon = btn.querySelector('i');
            function show() { inp.type = 'text'; icon.className = 'fa fa-eye-slash'; }
            function hide() { inp.type = 'password'; icon.className = 'fa fa-eye'; }
            btn.addEventListener('mousedown', function(e) { e.preventDefault(); show(); });
            btn.addEventListener('mouseup', hide);
            btn.addEventListener('mouseleave', hide);
            btn.addEventListener('touchstart', function(e) { e.preventDefault(); show(); }, { passive: false });
            btn.addEventListener('touchend', hide);
            btn.addEventListener('touchcancel', hide);
        }
        bindHoldPwd('eyeLoginPwd', 'loginPwd');
    </script>
</body>
</html>
