<?php
session_start();
include '../includes/config.php';

require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
include '../includes/config.php';

if (!$conn) {
    die("Database connection failed.");
}

// ── CSRF TOKEN GENERATION ─────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$action = $_GET['action'] ?? '';

// ── HELPER: CSRF VERIFY ───────────────────────────────────────────────────────
function verify_csrf($token)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['error_msg'] = "Invalid request. Please try again.";
        header("Location: login.php");
        exit();
    }
}

// ── HELPER: PASSWORD STRENGTH ─────────────────────────────────────────────────
function is_strong_password($pass)
{
    return strlen($pass) >= 8
        && preg_match('/[A-Z]/', $pass)
        && preg_match('/[0-9]/', $pass)
        && preg_match('/[\W_]/', $pass);
}

// ── HELPER: RATE LIMIT CHECK ──────────────────────────────────────────────────
function check_rate_limit($conn, $email)
{
    $max_attempts = 5;
    $lockout_mins = 10;
    $window = date('Y-m-d H:i:s', strtotime("-{$lockout_mins} minutes"));

    $stmt = $conn->prepare("SELECT COUNT(*) AS attempts FROM login_attempts
                            WHERE email = ? AND attempted_at > ? AND success = 0");
    $stmt->bind_param("ss", $email, $window);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row['attempts'] >= $max_attempts) {
        // Find when the lock expires
        $stmt2 = $conn->prepare("SELECT attempted_at FROM login_attempts
                                 WHERE email = ? AND success = 0
                                 ORDER BY attempted_at DESC LIMIT 1");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $last = $stmt2->get_result()->fetch_assoc();
        $unlock = date('H:i', strtotime($last['attempted_at'] . " +{$lockout_mins} minutes"));
        return "Too many failed attempts. Try again after {$unlock}.";
    }
    return false;
}

function log_attempt($conn, $email, $success)
{
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, success, attempted_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("si", $email, $success);
    $stmt->execute();
}

// ── POST HANDLERS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── LOGIN ─────────────────────────────────────────────────────────────────
    if ($action === 'login') {
        verify_csrf($_POST['csrf_token'] ?? '');

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Rate limit check
        $lock_msg = check_rate_limit($conn, $email);
        if ($lock_msg) {
            $_SESSION['error_msg'] = $lock_msg;
            header("Location: login.php");
            exit();
        }

        // Prepared statement — SQL injection safe
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Check email verified
            $is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
            if (!$user['email_verified']) {
                log_attempt($conn, $email, 0);
                $_SESSION['error_msg'] = "Please verify your email before signing in. Check your inbox.";
                header("Location: login.php");
                exit();
            }

            log_attempt($conn, $email, 1);

            // Clear old attempts on success
            $stmt2 = $conn->prepare("DELETE FROM login_attempts WHERE email = ? AND success = 0");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();

            session_regenerate_id(true); // prevent session fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['success_msg'] = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
            header("Location: car_dashboard.php");
            exit();

        } else {
            log_attempt($conn, $email, 0);
            $_SESSION['error_msg'] = "Invalid email or password.";
            header("Location: login.php");
            exit();
        }
    }

    // ── REGISTER ──────────────────────────────────────────────────────────────
    if ($action === 'register') {
        verify_csrf($_POST['csrf_token'] ?? '');

        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $contact_no = trim($_POST['contact_no'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');

        // Password strength
        if (!is_strong_password($password)) {
            $_SESSION['error_msg'] = "Password must be 8+ characters with at least 1 uppercase, 1 number, and 1 symbol.";
            header("Location: login.php?tab=register");
            exit();
        }

        // Check existing email — prepared statement
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['error_msg'] = "Email already registered.";
            header("Location: login.php?tab=register");
            exit();
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $ver_token = bin2hex(random_bytes(32));

        // Auto-verify on localhost
        $is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
        $auto_verified = $is_localhost ? 1 : 0;
        // Insert with email_verified = 0
        $stmt = $conn->prepare("INSERT INTO users
            (full_name, email, password, contact_no, dob, address, city, country, reg_date, email_verified, verification_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?)");
        $stmt->bind_param(
            "sssssssss",
            $full_name,
            $email,
            $hashed,
            $contact_no,
            $dob,
            $address,
            $city,
            $country,
            $ver_token
        );

        if ($stmt->execute()) {
            // Send verification email
            send_verification_email($email, $full_name, $ver_token);
            $_SESSION['success_msg'] = "Account created! Please check your email to verify your account.";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['error_msg'] = "Registration failed. Please try again.";
            header("Location: login.php?tab=register");
            exit();
        }
    }
}

// ── SEND VERIFICATION EMAIL ───────────────────────────────────────────────────
function send_verification_email($email, $name, $token)
{
    $verify_link = "http://" . $_SERVER['HTTP_HOST'] . "/carrental/users/verify_email.php?token=$token";
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your@gmail.com';       // ← your Gmail
        $mail->Password = 'xxxx xxxx xxxx xxxx';  // ← App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('your@gmail.com', 'CarForYou');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'CarForYou — Verify Your Email';
        $mail->Body = "
        <div style='font-family:Outfit,sans-serif;max-width:520px;margin:auto;background:#0f1319;color:#f0f2f8;padding:36px;border-radius:16px;border:1px solid rgba(255,255,255,0.08)'>
            <div style='text-align:center;margin-bottom:24px'>
                <h1 style='font-size:22px;font-weight:800;margin:0'>Car<span style='color:#00d4ff'>ForYou</span></h1>
            </div>
            <h2 style='font-size:18px;font-weight:700;margin-bottom:8px'>Verify Your Email</h2>
            <p style='color:#8892a4;font-size:14px;line-height:1.6;margin-bottom:20px'>
                Hi <strong style='color:#f0f2f8'>$name</strong>,<br><br>
                Thanks for registering! Click below to verify your email and activate your account.
            </p>
            <div style='text-align:center;margin:28px 0'>
                <a href='$verify_link'
                   style='display:inline-block;padding:13px 28px;background:linear-gradient(135deg,#00e676,#00b85a);color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px'>
                    Verify My Email
                </a>
            </div>
            <p style='color:#44505e;font-size:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:16px'>
                Link expires in 24 hours. If you didn't register, ignore this email.
            </p>
        </div>";
        $mail->send();
    } catch (Exception $e) { /* silent fail */
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarForYou — Sign In</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
        }

        [data-theme="dark"] {
            --bg: #0b0e14;
            --bg2: #0f1319;
            --surface: #141920;
            --surface2: #1a2030;
            --surface3: #1f2638;
            --border: rgba(255, 255, 255, 0.06);
            --border2: rgba(255, 255, 255, 0.1);
            --text: #f0f2f8;
            --text2: #8892a4;
            --text3: #44505e;
            --accent: #00d4ff;
            --accent2: #0090ff;
            --accentglow: rgba(0, 212, 255, 0.18);
            --accentglow2: rgba(0, 212, 255, 0.06);
            --green: #00e676;
            --greenglow: rgba(0, 230, 118, 0.18);
            --greenbg: rgba(0, 230, 118, 0.08);
            --amber: #fbbf24;
            --amberbg: rgba(251, 191, 36, 0.08);
            --red: #ff4f4f;
            --redbg: rgba(255, 79, 79, 0.08);
            --shadow: 0 0 0 1px rgba(0, 212, 255, 0.08), 0 24px 60px rgba(0, 0, 0, 0.6);
            --grid-color: rgba(0, 212, 255, 0.03);
            --spot1: rgba(0, 144, 255, 0.09);
            --spot2: rgba(0, 212, 255, 0.07);
            --spot3: rgba(0, 230, 118, 0.05);
        }

        [data-theme="light"] {
            --bg: #f0f4f8;
            --bg2: #e6ecf3;
            --surface: #ffffff;
            --surface2: #f5f8fc;
            --surface3: #eaf0f8;
            --border: rgba(0, 0, 0, 0.07);
            --border2: rgba(0, 0, 0, 0.12);
            --text: #0f1923;
            --text2: #4a5568;
            --text3: #94a3b8;
            --accent: #0077cc;
            --accent2: #0055aa;
            --accentglow: rgba(0, 119, 204, 0.18);
            --accentglow2: rgba(0, 119, 204, 0.08);
            --green: #059669;
            --greenglow: rgba(5, 150, 105, 0.18);
            --greenbg: rgba(5, 150, 105, 0.08);
            --amber: #d97706;
            --amberbg: rgba(217, 119, 6, 0.08);
            --red: #dc2626;
            --redbg: rgba(220, 38, 38, 0.07);
            --shadow: 0 0 0 1px rgba(0, 119, 204, 0.1), 0 24px 60px rgba(0, 0, 0, 0.1);
            --grid-color: rgba(0, 119, 204, 0.04);
            --spot1: rgba(0, 119, 204, 0.06);
            --spot2: rgba(0, 180, 255, 0.05);
            --spot3: rgba(5, 150, 105, 0.04);
        }

        .theme-corner {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 200;
        }

        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            color: var(--text2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.92rem;
            transition: all 0.22s;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        }

        .theme-toggle:hover {
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: 0 0 14px var(--accentglow);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 44px 16px 64px;
            position: relative;
            overflow-x: hidden;
            transition: background 0.35s, color 0.35s;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 55% 45% at 20% 15%, var(--spot1) 0%, transparent 65%),
                radial-gradient(ellipse 50% 40% at 80% 85%, var(--spot2) 0%, transparent 60%),
                radial-gradient(ellipse 35% 30% at 75% 10%, var(--spot3) 0%, transparent 55%);
            transition: background 0.4s;
        }

        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(var(--grid-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
        }

        .wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 520px;
        }

        .brand-bar {
            text-align: center;
            margin-bottom: 28px;
            animation: fadeDown 0.5s ease both;
        }

        .brand-bar a {
            text-decoration: none;
            display: inline-block;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.15), rgba(0, 144, 255, 0.25));
            border: 1px solid rgba(0, 212, 255, 0.25);
            border-radius: 16px;
            font-size: 1.35rem;
            color: var(--accent);
            margin-bottom: 12px;
            box-shadow: 0 0 30px var(--accentglow), inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .brand-bar h1 {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.03em;
        }

        .brand-bar h1 span {
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-bar p {
            font-size: 0.72rem;
            color: var(--text3);
            letter-spacing: 0.14em;
            margin-top: 4px;
            font-weight: 500;
        }

        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: cardUp 0.55s cubic-bezier(0.22, 1, 0.36, 1) 0.08s both;
            position: relative;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent);
            opacity: 0.6;
        }

        @keyframes cardUp {
            from {
                opacity: 0;
                transform: translateY(24px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg2);
            border-bottom: 1px solid var(--border);
        }

        .tab-btn {
            padding: 16px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text3);
            background: none;
            border: none;
            cursor: pointer;
            position: relative;
            transition: all 0.22s;
        }

        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 20%;
            right: 20%;
            height: 2px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 2px 2px 0 0;
            transform: scaleX(0);
            transition: transform 0.25s ease;
            box-shadow: 0 0 8px var(--accentglow);
        }

        .tab-btn:hover {
            color: var(--text2);
            background: rgba(0, 212, 255, 0.03);
        }

        .tab-btn.active {
            color: var(--accent);
        }

        .tab-btn.active::after {
            transform: scaleX(1);
        }

        .alerts {
            padding: 16px 26px 0;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 500;
            line-height: 1.5;
            margin-bottom: 8px;
            animation: alertIn 0.3s ease;
        }

        .alert i {
            flex-shrink: 0;
            margin-top: 1px;
        }

        @keyframes alertIn {
            from {
                opacity: 0;
                transform: translateY(-4px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .alert-success {
            background: var(--greenbg);
            color: var(--green);
            border: 1px solid rgba(0, 230, 118, 0.2);
        }

        .alert-error {
            background: var(--redbg);
            color: var(--red);
            border: 1px solid rgba(255, 79, 79, 0.2);
        }

        .alert-warning {
            background: var(--amberbg);
            color: var(--amber);
            border: 1px solid rgba(251, 191, 36, 0.2);
        }

        .sections {
            padding: 26px 26px 30px;
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
            animation: fadeUp 0.28s ease;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(8px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .sec-head {
            text-align: center;
            margin-bottom: 24px;
        }

        .sec-head h2 {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.03em;
        }

        .sec-head p {
            font-size: 0.82rem;
            color: var(--text2);
            margin-top: 5px;
            font-weight: 400;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 12px;
        }

        .form-group:last-of-type {
            margin-bottom: 0;
        }

        .form-group label {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text3);
        }

        .form-group label span {
            color: var(--red);
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrap .fi {
            position: absolute;
            left: 13px;
            color: var(--text3);
            font-size: 0.78rem;
            pointer-events: none;
            z-index: 2;
            transition: color 0.22s;
        }

        .input-wrap:focus-within .fi {
            color: var(--accent);
        }

        .form-control {
            width: 100%;
            padding: 11px 13px 11px 36px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.875rem;
            font-weight: 400;
            outline: none;
            -webkit-appearance: none;
            transition: border-color 0.22s, box-shadow 0.22s, background 0.22s;
        }

        .form-control.no-icon {
            padding-left: 13px;
        }

        .form-control::placeholder {
            color: var(--text3);
        }

        .form-control:focus {
            border-color: rgba(0, 212, 255, 0.45);
            background: var(--surface3);
            box-shadow: 0 0 0 3px var(--accentglow2), 0 0 12px rgba(0, 212, 255, 0.08);
        }

        select.form-control {
            cursor: pointer;
        }

        select.form-control option {
            background: var(--surface2);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 70px;
        }

        .input-wrap.has-eye .form-control {
            padding-right: 38px;
        }

        .eye-btn {
            position: absolute;
            right: 0;
            width: 38px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text3);
            background: none;
            border: none;
            border-radius: 0 10px 10px 0;
            font-size: 0.78rem;
            cursor: pointer;
            z-index: 2;
            transition: color 0.2s;
        }

        .eye-btn:hover {
            color: var(--accent);
        }

        /* Password strength */
        .pw-strength {
            margin-top: 6px;
        }

        .pw-bars {
            display: flex;
            gap: 4px;
            height: 3px;
            margin-bottom: 4px;
        }

        .pw-bars span {
            flex: 1;
            border-radius: 2px;
            background: var(--border2);
            transition: background 0.3s;
        }

        .pw-hint {
            font-size: 0.68rem;
            color: var(--text3);
        }

        .pw-rules {
            font-size: 0.68rem;
            color: var(--text3);
            margin-top: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .pw-rule {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            background: var(--border);
            transition: all 0.2s;
        }

        .pw-rule.ok {
            background: var(--greenbg);
            color: var(--green);
        }

        /* Forgot link */
        .forgot-row {
            display: flex;
            justify-content: flex-end;
            margin-top: -4px;
            margin-bottom: 14px;
        }

        .forgot-link {
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text3);
            text-decoration: none;
            letter-spacing: 0.02em;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .forgot-link:hover {
            color: var(--accent);
        }

        .btn-signin {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.15), rgba(0, 144, 255, 0.2));
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 12px;
            color: var(--accent);
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: all 0.22s;
            box-shadow: 0 0 20px var(--accentglow), inset 0 1px 0 rgba(255, 255, 255, 0.06);
            position: relative;
            overflow: hidden;
        }

        .btn-signin::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.08), rgba(0, 144, 255, 0.12));
            opacity: 0;
            transition: opacity 0.22s;
        }

        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 35px var(--accentglow), 0 8px 24px rgba(0, 0, 0, 0.3);
            border-color: rgba(0, 212, 255, 0.55);
        }

        .btn-signin:hover::before {
            opacity: 1;
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        .btn-signin:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }

        .btn-register {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, rgba(0, 230, 118, 0.12), rgba(0, 180, 90, 0.18));
            border: 1px solid rgba(0, 230, 118, 0.28);
            border-radius: 12px;
            color: var(--green);
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: all 0.22s;
            box-shadow: 0 0 20px var(--greenglow), inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 35px var(--greenglow), 0 8px 24px rgba(0, 0, 0, 0.3);
            border-color: rgba(0, 230, 118, 0.5);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .btn-register:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
        }

        .switch-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: var(--text3);
        }

        .switch-link button {
            color: var(--accent);
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .switch-link button:hover {
            opacity: 0.7;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .back-home {
            text-align: center;
            margin-top: 18px;
            animation: fadeDown 0.5s ease 0.3s both;
            opacity: 0;
        }

        .back-home a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.76rem;
            color: var(--text3);
            text-decoration: none;
            font-weight: 500;
            letter-spacing: 0.04em;
            transition: color 0.2s;
        }

        .back-home a:hover {
            color: var(--accent);
        }

        .section-divider {
            height: 1px;
            background: var(--border);
            margin: 4px 0 16px;
        }

        @media(max-width:500px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .sections {
                padding: 20px 16px 24px;
            }

            .alerts {
                padding: 12px 16px 0;
            }
        }
    </style>
</head>

<body>

    <div class="theme-corner">
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
            <i class="fa fa-moon" id="themeIcon"></i>
        </button>
    </div>

    <div class="wrap">
        <div class="brand-bar">
            <a href="../index.php">
                <div class="brand-logo"><i class="fa fa-car-side"></i></div>
                <h1>Car<span>ForYou</span></h1>
                <p>YOUR JOURNEY STARTS HERE</p>
            </a>
        </div>

        <div class="card">
            <div class="tabs">
                <button class="tab-btn active" id="loginTab" onclick="showSection('login')">
                    <i class="fa fa-arrow-right-to-bracket" style="margin-right:7px;"></i>Sign In
                </button>
                <button class="tab-btn" id="registerTab" onclick="showSection('register')">
                    <i class="fa fa-user-plus" style="margin-right:7px;"></i>Register
                </button>
            </div>

            <div class="alerts">
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success"><i
                            class="fa fa-circle-check"></i><?php echo htmlspecialchars($_SESSION['success_msg']);
                            unset($_SESSION['success_msg']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-error"><i
                            class="fa fa-circle-xmark"></i><?php echo htmlspecialchars($_SESSION['error_msg']);
                            unset($_SESSION['error_msg']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><i
                            class="fa fa-circle-check"></i><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error"><i
                            class="fa fa-circle-xmark"></i><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
            </div>

            <div class="sections">

                <!-- ── LOGIN ── -->
                <section id="loginSection" class="section active">
                    <div class="sec-head">
                        <h2>Welcome Back</h2>
                        <p>Sign in to browse and book your next ride</p>
                    </div>
                    <form action="?action=login" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-wrap">
                                <i class="fa fa-envelope fi"></i>
                                <input type="email" name="email" class="form-control" placeholder="you@example.com"
                                    required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-wrap has-eye">
                                <i class="fa fa-lock fi"></i>
                                <input type="password" name="password" id="loginPwd" class="form-control"
                                    placeholder="••••••••" required>
                                <button type="button" class="eye-btn" id="eyeLogin"><i class="fa fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="forgot-row">
                            <a href="forgot_password.php" class="forgot-link">
                                <i class="fa fa-circle-question"></i> Forgot password?
                            </a>
                        </div>
                        <button type="submit" class="btn-signin">
                            <i class="fa fa-arrow-right-to-bracket"></i> Sign In
                        </button>
                    </form>
                    <div class="switch-link">
                        Don't have an account? <button onclick="showSection('register')">Create one free</button>
                    </div>
                </section>

                <!-- ── REGISTER ── -->
                <section id="registerSection" class="section">
                    <div class="sec-head">
                        <h2>Create Account</h2>
                        <p>Join CarForYou — it only takes a minute</p>
                    </div>
                    <form action="?action=register" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-grid">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Full Name <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-user fi"></i>
                                    <input type="text" name="full_name" class="form-control" placeholder="John Doe"
                                        required>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Email Address <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-envelope fi"></i>
                                    <input type="email" name="email" class="form-control" placeholder="john@example.com"
                                        required>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Password <span>*</span></label>
                                <div class="input-wrap has-eye">
                                    <i class="fa fa-lock fi"></i>
                                    <input type="password" name="password" id="regPwd" class="form-control"
                                        placeholder="Min 8 chars" required oninput="checkStrength(this.value)">
                                    <button type="button" class="eye-btn" id="eyeReg"><i class="fa fa-eye"></i></button>
                                </div>
                                <!-- Strength meter -->
                                <div class="pw-strength">
                                    <div class="pw-bars">
                                        <span id="b1"></span><span id="b2"></span><span id="b3"></span><span
                                            id="b4"></span>
                                    </div>
                                    <div class="pw-hint" id="pwHint">Enter a password</div>
                                    <div class="pw-rules">
                                        <span class="pw-rule" id="r-len"><i class="fa fa-xmark"></i> 8+ chars</span>
                                        <span class="pw-rule" id="r-upper"><i class="fa fa-xmark"></i> Uppercase</span>
                                        <span class="pw-rule" id="r-num"><i class="fa fa-xmark"></i> Number</span>
                                        <span class="pw-rule" id="r-sym"><i class="fa fa-xmark"></i> Symbol</span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Contact No <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-phone fi"></i>
                                    <input type="text" name="contact_no" class="form-control"
                                        placeholder="+94 77 123 4567" required>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Date of Birth <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-calendar fi"></i>
                                    <input type="date" name="dob" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>City <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-city fi"></i>
                                    <input type="text" name="city" class="form-control" placeholder="Colombo" required>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <div class="form-group">
                            <label>Country <span>*</span></label>
                            <div class="input-wrap">
                                <i class="fa fa-globe fi"></i>
                                <select name="country" class="form-control" required>
                                    <option value="">Select Country</option>
                                    <option value="Sri Lanka">Sri Lanka</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Full Address <span>*</span></label>
                            <textarea name="address" class="form-control no-icon" rows="2"
                                placeholder="Street address, area..." required></textarea>
                        </div>

                        <button type="submit" id="regBtn" class="btn-register" disabled>
                            <i class="fa fa-user-plus"></i> Create My Account
                        </button>
                    </form>
                    <div class="switch-link">
                        Already have an account? <button onclick="showSection('login')">Sign in here</button>
                    </div>
                </section>

            </div>
        </div>

        <div class="back-home">
            <a href="../index.php"><i class="fa fa-arrow-left"></i> Back to homepage</a>
        </div>
    </div>

    <script>
        // Tab switching
        function showSection(s) {
            document.querySelectorAll('.section').forEach(function (el) { el.classList.remove('active'); });
            document.querySelectorAll('.tab-btn').forEach(function (btn) { btn.classList.remove('active'); });
            document.getElementById(s + 'Section').classList.add('active');
            document.getElementById(s + 'Tab').classList.add('active');
        }
        if (new URLSearchParams(window.location.search).get('tab') === 'register') showSection('register');

        // Hold-to-reveal password
        function bindHold(btnId, inputId) {
            var btn = document.getElementById(btnId), inp = document.getElementById(inputId);
            if (!btn || !inp) return;
            var icon = btn.querySelector('i');
            function show() { inp.type = 'text'; icon.className = 'fa fa-eye-slash'; }
            function hide() { inp.type = 'password'; icon.className = 'fa fa-eye'; }
            btn.addEventListener('mousedown', function (e) { e.preventDefault(); show(); });
            btn.addEventListener('mouseup', hide);
            btn.addEventListener('mouseleave', hide);
            btn.addEventListener('touchstart', function (e) { e.preventDefault(); show(); }, { passive: false });
            btn.addEventListener('touchend', hide);
            btn.addEventListener('touchcancel', hide);
        }
        bindHold('eyeLogin', 'loginPwd');
        bindHold('eyeReg', 'regPwd');

        // Password strength meter + rule badges
        function checkStrength(v) {
            var bars = [document.getElementById('b1'), document.getElementById('b2'), document.getElementById('b3'), document.getElementById('b4')];
            var hint = document.getElementById('pwHint');
            var regBtn = document.getElementById('regBtn');
            var colors = ['#ff4f4f', '#fbbf24', '#00d4ff', '#00e676'];
            var labels = ['Too weak', 'Weak', 'Good', 'Strong'];

            var rLen = v.length >= 8;
            var rUpper = /[A-Z]/.test(v);
            var rNum = /[0-9]/.test(v);
            var rSym = /[\W_]/.test(v);
            var score = [rLen, rUpper, rNum, rSym].filter(Boolean).length;

            // Bars
            bars.forEach(function (b, i) { b.style.background = i < score ? colors[score - 1] : 'var(--border2)'; });
            hint.textContent = v.length === 0 ? 'Enter a password' : labels[score - 1] || labels[0];
            hint.style.color = v.length === 0 ? 'var(--text3)' : colors[score - 1] || colors[0];

            // Rule badges
            function setRule(id, ok) {
                var el = document.getElementById(id);
                el.classList.toggle('ok', ok);
                el.querySelector('i').className = ok ? 'fa fa-check' : 'fa fa-xmark';
            }
            setRule('r-len', rLen);
            setRule('r-upper', rUpper);
            setRule('r-num', rNum);
            setRule('r-sym', rSym);

            // Only enable submit if all 4 rules pass
            regBtn.disabled = !(rLen && rUpper && rNum && rSym);
        }

        // Theme
        var theme = localStorage.getItem('cfyTheme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        syncThemeIcon();
        document.getElementById('themeToggle').addEventListener('click', function () {
            theme = theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('cfyTheme', theme);
            syncThemeIcon();
        });
        function syncThemeIcon() { document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun'; }
    </script>
</body>

</html>