<?php
session_start();
include '../includes/config.php';

require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!$conn) {
    die("Database connection failed.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$action = $_GET['action'] ?? '';

function verify_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['error_msg'] = "Invalid request. Please try again.";
        header("Location: login.php");
        exit();
    }
}

function is_strong_password($pass) {
    return strlen($pass) >= 8 && preg_match('/[A-Z]/', $pass) && preg_match('/[0-9]/', $pass) && preg_match('/[\W_]/', $pass);
}

function check_rate_limit($conn, $email) {
    $max_attempts = 5;
    $lockout_mins = 10;
    $window = date('Y-m-d H:i:s', strtotime("-{$lockout_mins} minutes"));
    $stmt = $conn->prepare("SELECT COUNT(*) AS attempts FROM login_attempts WHERE email = ? AND attempted_at > ? AND success = 0");
    $stmt->bind_param("ss", $email, $window);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row['attempts'] >= $max_attempts) {
        $stmt2 = $conn->prepare("SELECT attempted_at FROM login_attempts WHERE email = ? AND success = 0 ORDER BY attempted_at DESC LIMIT 1");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $last = $stmt2->get_result()->fetch_assoc();
        $unlock = date('H:i', strtotime($last['attempted_at'] . " +{$lockout_mins} minutes"));
        return "Too many failed attempts. Try again after {$unlock}.";
    }
    return false;
}

function log_attempt($conn, $email, $success) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, success, attempted_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("si", $email, $success);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        verify_csrf($_POST['csrf_token'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $lock_msg = check_rate_limit($conn, $email);
        if ($lock_msg) {
            $_SESSION['error_msg'] = $lock_msg;
            header("Location: login.php");
            exit();
        }
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['email_verified']) {
                log_attempt($conn, $email, 0);
                $_SESSION['error_msg'] = "Please verify your email before signing in.";
                header("Location: login.php");
                exit();
            }
            log_attempt($conn, $email, 1);
            $stmt2 = $conn->prepare("DELETE FROM login_attempts WHERE email = ? AND success = 0");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            session_regenerate_id(true);
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
        $nic = trim($_POST['nic'] ?? '');
        $license_no = trim($_POST['license_no'] ?? '');
        if (!is_strong_password($password)) {
            $_SESSION['error_msg'] = "Password must be 8+ characters with at least 1 uppercase, 1 number, and 1 symbol.";
            header("Location: login.php?tab=register");
            exit();
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['error_msg'] = "Email already registered.";
            header("Location: login.php?tab=register");
            exit();
        }
        $_SESSION['reg_pending'] = [
            'full_name' => $full_name, 'email' => $email, 'password' => $password,
            'contact_no' => $contact_no, 'dob' => $dob, 'address' => $address,
            'city' => $city, 'country' => $country, 'nic' => $nic, 'license_no' => $license_no,
            'created_at' => date('Y-m-d H:i:s')
        ];
        header("Location: verify_otp.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarForYou — Sign In</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%2300d4ff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%2300d4ff'/><circle cx='70' cy='62' r='6' fill='%2300d4ff'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%2300d4ff' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; }

        [data-theme="dark"] {
            --bg: #06080d; --surface: #0d1421; --surface2: #131c2e; --surface3: #1a2540;
            --border: rgba(0, 212, 255, 0.08); --border2: rgba(0, 212, 255, 0.15);
            --text: #f0f5ff; --text2: #8a9cc4; --text3: #4a5a7a;
            --accent: #00d4ff; --accent2: #0090ff; --accentglow: rgba(0, 212, 255, 0.25);
            --accentglow2: rgba(0, 212, 255, 0.08);
            --green: #00e676; --greenglow: rgba(0, 230, 118, 0.2);
            --red: #ff4f4f; --redglow: rgba(255, 79, 79, 0.2);
            --shadow: 0 0 60px rgba(0, 0, 0, 0.8);
        }
        [data-theme="light"] {
            --bg: #e8edf8; --surface: #ffffff; --surface2: #f5f8fc; --surface3: #eaf0f8;
            --border: rgba(0, 119, 204, 0.08); --border2: rgba(0, 119, 204, 0.18);
            --text: #0f1923; --text2: #4a5568; --text3: #94a3b8;
            --accent: #0077cc; --accent2: #0055aa; --accentglow: rgba(0, 119, 204, 0.2);
            --accentglow2: rgba(0, 119, 204, 0.06);
            --green: #059669; --greenglow: rgba(5, 150, 105, 0.15);
            --red: #dc2626; --redglow: rgba(220, 38, 38, 0.15);
            --shadow: 0 0 60px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 16px 60px;
            position: relative;
            overflow-x: hidden;
            transition: background 0.5s ease;
        }

        /* Animated Background */
        .bg-animated {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }
        .bg-animated::before {
            content: '';
            position: absolute;
            width: 150%;
            height: 150%;
            top: -25%;
            left: -25%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 212, 255, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(0, 144, 255, 0.06) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(0, 230, 118, 0.04) 0%, transparent 50%);
            animation: bgPulse 12s ease-in-out infinite;
        }
        @keyframes bgPulse {
            0%, 100% { transform: scale(1) rotate(0deg); opacity: 1; }
            50% { transform: scale(1.1) rotate(3deg); opacity: 0.8; }
        }

        /* Floating Particles */
        .particles {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--accent);
            border-radius: 50%;
            opacity: 0;
            animation: floatParticle 8s infinite;
        }
        @keyframes floatParticle {
            0% { opacity: 0; transform: translateY(100vh) scale(0); }
            10% { opacity: 0.6; }
            90% { opacity: 0.6; }
            100% { opacity: 0; transform: translateY(-100vh) scale(1); }
        }

        /* Grid Lines */
        .grid-lines {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: 
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 20s linear infinite;
            opacity: 0.5;
        }
        @keyframes gridMove {
            from { background-position: 0 0; }
            to { background-position: 60px 60px; }
        }

        /* Theme Toggle */
        .theme-corner {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
        }
        .theme-toggle {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--surface);
            border: 1px solid var(--border2);
            color: var(--text2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .theme-toggle:hover {
            transform: scale(1.1) rotate(10deg);
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: 0 0 25px var(--accentglow);
        }

        /* Main Container */
        .wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 540px;
            flex-shrink: 0;
        }

        /* Brand Section */
        .brand-bar {
            text-align: center;
            margin-bottom: 24px;
            animation: fadeInDown 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .brand-bar a { text-decoration: none; display: inline-block; }
        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.2), rgba(0, 144, 255, 0.3));
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 20px;
            font-size: 1.6rem;
            color: var(--accent);
            margin-bottom: 16px;
            box-shadow: 
                0 0 40px var(--accentglow),
                0 0 80px var(--accentglow2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            animation: logoGlow 3s ease-in-out infinite;
        }
        @keyframes logoGlow {
            0%, 100% { box-shadow: 0 0 40px var(--accentglow), 0 0 80px var(--accentglow2), inset 0 1px 0 rgba(255, 255, 255, 0.1); }
            50% { box-shadow: 0 0 60px var(--accentglow), 0 0 100px var(--accentglow2), inset 0 1px 0 rgba(255, 255, 255, 0.1); }
        }
        .brand-bar h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.04em;
        }
        .brand-bar h1 span {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .brand-bar p {
            font-size: 0.7rem;
            color: var(--text3);
            letter-spacing: 0.18em;
            margin-top: 6px;
            font-weight: 600;
        }

        /* Card */
        .card {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 24px;
            box-shadow: var(--shadow);
            animation: cardEntry 1s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
            position: relative;
        }
        @keyframes cardEntry {
            from { opacity: 0; transform: translateY(60px) scale(0.95) rotateX(10deg); }
            to { opacity: 1; transform: translateY(0) scale(1) rotateX(0); }
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent);
            opacity: 0.8;
        }
        .card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 212, 255, 0.02) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Tabs */
        .tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
        }
        .tab-btn {
            padding: 18px 16px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text3);
            background: none;
            border: none;
            cursor: pointer;
            position: relative;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }
        .tab-btn::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            border-radius: 3px 3px 0 0;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform: translateX(-50%);
            box-shadow: 0 0 15px var(--accentglow);
        }
        .tab-btn:hover { color: var(--text2); }
        .tab-btn:hover::before { width: 30%; }
        .tab-btn.active { color: var(--accent); }
        .tab-btn.active::before { width: 80%; }

        /* Alerts */
        .alerts { padding: 20px 28px 0; }
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 0.84rem;
            font-weight: 500;
            line-height: 1.5;
            margin-bottom: 10px;
            animation: alertSlide 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes alertSlide {
            from { opacity: 0; transform: translateX(-20px) scale(0.95); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
        .alert i { flex-shrink: 0; margin-top: 2px; font-size: 1rem; }
        .alert-success { background: rgba(0, 230, 118, 0.1); color: var(--green); border: 1px solid rgba(0, 230, 118, 0.2); }
        .alert-error { background: rgba(255, 79, 79, 0.1); color: var(--red); border: 1px solid rgba(255, 79, 79, 0.2); }

        .info-box {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: rgba(0, 212, 255, 0.06);
            border: 1px solid rgba(0, 212, 255, 0.15);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 22px;
        }
        .info-box i {
            font-size: 1.2rem;
            color: var(--accent);
            flex-shrink: 0;
            margin-top: 2px;
        }
        .info-box strong {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }
        .info-box p {
            font-size: 0.78rem;
            color: var(--text2);
            line-height: 1.5;
            margin: 0;
        }

        /* Sections */
        .sections { padding: 28px 28px 32px; }
        .section { display: none; }
        .section.active { display: block; animation: sectionFade 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        @keyframes sectionFade {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .sec-head { text-align: center; margin-bottom: 28px; }
        .sec-head h2 { font-size: 1.6rem; font-weight: 800; color: var(--text); letter-spacing: -0.03em; }
        .sec-head p { font-size: 0.84rem; color: var(--text2); margin-top: 6px; }

        /* Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .form-group:last-of-type { margin-bottom: 0; }
        .form-group label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text3); }
        .form-group label span { color: var(--red); }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrap .fi {
            position: absolute;
            left: 14px;
            color: var(--text3);
            font-size: 0.82rem;
            pointer-events: none;
            z-index: 2;
            transition: all 0.3s ease;
        }
        .input-wrap:focus-within .fi { color: var(--accent); transform: scale(1.1); }

        .form-control {
            width: 100%;
            padding: 14px 14px 14px 40px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 12px;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .form-control.no-icon { padding-left: 14px; }
        .form-control::placeholder { color: var(--text3); }
        .form-control:focus {
            border-color: var(--accent);
            background: var(--surface3);
            box-shadow: 0 0 0 4px var(--accentglow2), 0 8px 30px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        .input-wrap.has-eye .form-control { padding-right: 44px; }
        .eye-btn {
            position: absolute;
            right: 0;
            width: 44px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text3);
            background: none;
            border: none;
            font-size: 0.85rem;
            cursor: pointer;
            z-index: 2;
            transition: all 0.3s ease;
        }
        .eye-btn:hover { color: var(--accent); transform: scale(1.1); }

        /* Password Strength */
        .pw-strength { margin-top: 8px; }
        .pw-bars { display: flex; gap: 4px; height: 4px; margin-bottom: 6px; }
        .pw-bars span { flex: 1; border-radius: 2px; background: var(--border2); transition: all 0.4s ease; }
        .pw-hint { font-size: 0.7rem; color: var(--text3); margin-bottom: 6px; }
        .pw-rules { display: flex; flex-wrap: wrap; gap: 6px; }
        .pw-rule {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--border);
            font-size: 0.65rem;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .pw-rule.ok { background: rgba(0, 230, 118, 0.15); color: var(--green); transform: scale(1.05); }
        .pw-rule i { font-size: 0.6rem; }

        /* Buttons */
        .btn-signin {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none;
            border-radius: 14px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 30px var(--accentglow);
            position: relative;
            overflow: hidden;
        }
        .btn-signin::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .btn-signin:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 15px 50px var(--accentglow); }
        .btn-signin:hover::before { opacity: 1; }
        .btn-signin:active { transform: translateY(-1px) scale(0.98); }

        .btn-register {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--green), #00b85a);
            border: none;
            border-radius: 14px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 8px 30px var(--greenglow);
        }
        .btn-register:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 15px 50px var(--greenglow); }
        .btn-register:active { transform: translateY(-1px) scale(0.98); }
        .btn-register:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

        .forgot-row { display: flex; justify-content: flex-end; margin: -6px 0 16px; }
        .forgot-link {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text3);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .forgot-link:hover { color: var(--accent); transform: translateX(3px); }

        .switch-link { text-align: center; margin-top: 22px; font-size: 0.82rem; color: var(--text3); }
        .switch-link button {
            color: var(--accent);
            font-weight: 700;
            background: none;
            border: none;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            font-size: 0.82rem;
            transition: all 0.3s ease;
        }
        .switch-link button:hover { opacity: 0.7; text-decoration: underline; text-underline-offset: 3px; }

        .section-divider { height: 1px; background: var(--border); margin: 6px 0 18px; }

        .back-home {
            text-align: center;
            margin-top: 20px;
            padding-bottom: 20px;
            animation: fadeInUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.4s both;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .back-home a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            color: var(--text3);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-home a:hover { color: var(--accent); transform: translateX(-3px); }

        @media(max-width: 520px) {
            body {
                padding: 20px 12px 40px;
                align-items: flex-start;
            }
            .form-grid { grid-template-columns: 1fr; }
            .sections { padding: 20px 18px 28px; }
            .alerts { padding: 16px 18px 0; }
            .brand-bar { margin-bottom: 16px; }
            .brand-bar h1 { font-size: 1.6rem; }
            .brand-logo { width: 52px; height: 52px; font-size: 1.3rem; }
            .sec-head h2 { font-size: 1.3rem; }
            .form-group label { font-size: 0.62rem; }
            .form-control { padding: 12px 12px 12px 38px; }
            .input-wrap .fi { left: 12px; font-size: 0.75rem; }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>
    <div class="grid-lines"></div>
    
    <!-- Floating Particles -->
    <div class="particles" id="particles"></div>

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
                    <i class="fa fa-arrow-right-to-bracket" style="margin-right:8px;"></i>Sign In
                </button>
                <button class="tab-btn" id="registerTab" onclick="showSection('register')">
                    <i class="fa fa-user-plus" style="margin-right:8px;"></i>Register
                </button>
            </div>

            <div class="alerts">
                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success"><i class="fa fa-circle-check"></i><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error_msg'])): ?>
                    <div class="alert alert-error"><i class="fa fa-circle-xmark"></i><?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><i class="fa fa-circle-check"></i><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error"><i class="fa fa-circle-xmark"></i><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
            </div>

            <div class="sections">
                <!-- LOGIN -->
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
                                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-wrap has-eye">
                                <i class="fa fa-lock fi"></i>
                                <input type="password" name="password" id="loginPwd" class="form-control" placeholder="••••••••" required>
                                <button type="button" class="eye-btn" id="eyeLogin"><i class="fa fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="forgot-row">
                            <a href="forgot_password.php" class="forgot-link"><i class="fa fa-circle-question"></i> Forgot password?</a>
                        </div>
                        <button type="submit" class="btn-signin"><i class="fa fa-arrow-right-to-bracket"></i> Sign In</button>
                    </form>
                    <div class="switch-link">Don't have an account? <button onclick="showSection('register')">Create one free</button></div>
                </section>

                <!-- REGISTER -->
                <section id="registerSection" class="section">
                    <div class="sec-head">
                        <h2>Create Account</h2>
                        <p>Join CarForYou — it only takes a minute</p>
                    </div>
                    <div class="info-box">
                        <i class="fa fa-shield-halved"></i>
                        <div>
                            <strong>Your Data is Secure</strong>
                            <p>Your personal information is encrypted and protected. We only collect data necessary for car rental verification and will never share it with third parties.</p>
                        </div>
                    </div>
                    <form action="?action=register" method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-user fi"></i>
                                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email Address <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-envelope fi"></i>
                                    <input type="email" name="email" class="form-control" placeholder="john@example.com" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Password <span>*</span></label>
                                <div class="input-wrap has-eye">
                                    <i class="fa fa-lock fi"></i>
                                    <input type="password" name="password" id="regPwd" class="form-control" placeholder="Min 8 chars" required oninput="checkStrength(this.value)">
                                    <button type="button" class="eye-btn" id="eyeReg"><i class="fa fa-eye"></i></button>
                                </div>
                                <div class="pw-strength">
                                    <div class="pw-bars"><span id="b1"></span><span id="b2"></span><span id="b3"></span><span id="b4"></span></div>
                                    <div class="pw-hint" id="pwHint">Enter a password</div>
                                    <div class="pw-rules">
                                        <span class="pw-rule" id="r-len"><i class="fa fa-xmark"></i> 8+ chars</span>
                                        <span class="pw-rule" id="r-upper"><i class="fa fa-xmark"></i> Uppercase</span>
                                        <span class="pw-rule" id="r-num"><i class="fa fa-xmark"></i> Number</span>
                                        <span class="pw-rule" id="r-sym"><i class="fa fa-xmark"></i> Symbol</span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Contact No <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-phone fi"></i>
                                    <input type="text" name="contact_no" class="form-control" placeholder="+94 77 123 4567" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Date of Birth <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-calendar fi"></i>
                                    <input type="date" name="dob" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>City <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-city fi"></i>
                                    <input type="text" name="city" class="form-control" placeholder="Colombo" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>NIC / National ID <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-id-card fi"></i>
                                    <input type="text" name="nic" class="form-control" placeholder="199012345678" required pattern="[0-9]{9}[VvXx]|[0-9]{12}">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Driver's License No. <span>*</span></label>
                                <div class="input-wrap">
                                    <i class="fa fa-car fi"></i>
                                    <input type="text" name="license_no" class="form-control" placeholder="B1234567" required>
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
                            <textarea name="address" class="form-control no-icon" rows="2" placeholder="Street address, area..." required></textarea>
                        </div>
                        <button type="submit" id="regBtn" class="btn-register" disabled><i class="fa fa-user-plus"></i> Create My Account</button>
                    </form>
                    <div class="switch-link">Already have an account? <button onclick="showSection('login')">Sign in here</button></div>
                </section>
            </div>
        </div>

        <div class="back-home">
            <a href="../index.php"><i class="fa fa-arrow-left"></i> Back to homepage</a>
        </div>
    </div>

    <script>
        // Floating Particles
        function createParticles() {
            const container = document.getElementById('particles');
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 8 + 's';
                particle.style.animationDuration = (6 + Math.random() * 6) + 's';
                particle.style.width = (2 + Math.random() * 4) + 'px';
                particle.style.height = particle.style.width;
                container.appendChild(particle);
            }
        }
        createParticles();

        // Tab switching
        function showSection(s) {
            document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(s + 'Section').classList.add('active');
            document.getElementById(s + 'Tab').classList.add('active');
        }
        if (new URLSearchParams(window.location.search).get('tab') === 'register') showSection('register');

        // Password toggle
        function bindHold(btnId, inputId) {
            const btn = document.getElementById(btnId), inp = document.getElementById(inputId);
            if (!btn || !inp) return;
            const icon = btn.querySelector('i');
            function show() { inp.type = 'text'; icon.className = 'fa fa-eye-slash'; }
            function hide() { inp.type = 'password'; icon.className = 'fa fa-eye'; }
            btn.addEventListener('mousedown', e => { e.preventDefault(); show(); });
            btn.addEventListener('mouseup', hide);
            btn.addEventListener('mouseleave', hide);
            btn.addEventListener('touchstart', e => { e.preventDefault(); show(); }, { passive: false });
            btn.addEventListener('touchend', hide);
            btn.addEventListener('touchcancel', hide);
        }
        bindHold('eyeLogin', 'loginPwd');
        bindHold('eyeReg', 'regPwd');

        // Password strength
        function checkStrength(v) {
            const bars = ['b1','b2','b3','b4'].map(id => document.getElementById(id));
            const hint = document.getElementById('pwHint');
            const regBtn = document.getElementById('regBtn');
            const colors = ['#ff4f4f', '#fbbf24', '#00d4ff', '#00e676'];
            const labels = ['Too weak', 'Weak', 'Good', 'Strong'];
            const rLen = v.length >= 8, rUpper = /[A-Z]/.test(v), rNum = /[0-9]/.test(v), rSym = /[\W_]/.test(v);
            const score = [rLen, rUpper, rNum, rSym].filter(Boolean).length;
            bars.forEach((b, i) => b.style.background = i < score ? colors[score - 1] : 'var(--border2)');
            hint.textContent = v.length === 0 ? 'Enter a password' : labels[score - 1] || labels[0];
            hint.style.color = v.length === 0 ? 'var(--text3)' : colors[score - 1] || colors[0];
            function setRule(id, ok) {
                const el = document.getElementById(id);
                el.classList.toggle('ok', ok);
                el.querySelector('i').className = ok ? 'fa fa-check' : 'fa fa-xmark';
            }
            setRule('r-len', rLen); setRule('r-upper', rUpper); setRule('r-num', rNum); setRule('r-sym', rSym);
            regBtn.disabled = !(rLen && rUpper && rNum && rSym);
        }

        // Theme
        let theme = localStorage.getItem('cfyTheme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        syncThemeIcon();
        document.getElementById('themeToggle').addEventListener('click', () => {
            theme = theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('cfyTheme', theme);
            syncThemeIcon();
        });
        function syncThemeIcon() { document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun'; }

        // Auto-hide success messages after 4 seconds
        document.querySelectorAll('.alert-success').forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(function() { alert.style.display = 'none'; }, 500);
            }, 4000);
        });
    </script>
</body>
</html>
