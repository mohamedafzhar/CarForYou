<?php
session_start();
include 'config.php';

// ✅ FIX: use statements MUST be at top — never inside if blocks
require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Already logged in
if (isset($_SESSION['alogin'])) {
    header('Location: admin_dashboard.php'); exit();
}

// ── 2FA VERIFICATION ────────────────────────────────────────
if (isset($_SESSION['2fa_pending'])) {
    $show_2fa = true;
    $pending_user = $_SESSION['2fa_pending'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
        $code = trim($_POST['2fa_code'] ?? '');
        
        if (empty($_SESSION['2fa_code']) || $code !== $_SESSION['2fa_code']) {
            $error = "Invalid verification code. Please try again.";
        } elseif (strtotime($_SESSION['2fa_expires']) < time()) {
            $error = "Verification code expired. Please login again.";
            unset($_SESSION['2fa_pending'], $_SESSION['2fa_code'], $_SESSION['2fa_expires']);
            $show_2fa = false;
        } else {
            // 2FA successful
            session_regenerate_id(true);
            $_SESSION['alogin'] = $pending_user['username'];
            $_SESSION['admin_id'] = $pending_user['id'];
            unset($_SESSION['2fa_pending'], $_SESSION['2fa_code'], $_SESSION['2fa_expires']);
            header('Location: admin_dashboard.php'); exit();
        }
    }
    
    if (!isset($_SESSION['2fa_code']) && isset($pending_user)) {
        // Generate new 2FA code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['2fa_code'] = $code;
        $_SESSION['2fa_expires'] = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Send code via email
        $stmt = $conn->prepare("SELECT email FROM admin WHERE username = ?");
        $stmt->bind_param("s", $pending_user['username']);
        $stmt->execute();
        $admin_email = $stmt->get_result()->fetch_assoc()['email'] ?? '';
        
        if ($admin_email) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USERNAME') ?: '';
                $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
                $mail->setFrom(getenv('MAIL_FROM') ?: 'noreply@carforyou.com', 'CarForYou Admin');
                $mail->addAddress($admin_email);
                $mail->isHTML(true);
                $mail->Subject = 'CarForYou Admin - Verification Code';
                $mail->Body = "
                    <div style='font-family:sans-serif;max-width:400px;margin:auto;padding:30px;background:#1e2738;color:#e8edf5;border-radius:12px;'>
                        <h2 style='margin:0 0 20px;'>Verification Code</h2>
                        <p style='margin:0 0 20px;'>Your verification code is:</p>
                        <div style='font-size:32px;font-weight:bold;letter-spacing:8px;color:#4f8ef7;text-align:center;padding:20px;background:#0d1117;border-radius:8px;margin:20px 0;'>$code</div>
                        <p style='margin:0;font-size:12px;color:#7a93b0;'>This code expires in 5 minutes. If you didn't request this, please ignore this email.</p>
                    </div>";
                $mail->send();
                $msg = "Verification code sent to your email.";
            } catch (Exception $e) {
                $error = "Failed to send verification code.";
            }
        }
    }
} else {
    $show_2fa = false;
}

// ── CSRF TOKEN ────────────────────────────────────────────────
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$msg   = '';

// ── FORGOT PASSWORD ───────────────────────────────────────────
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

            $mail_sent = false;
            $mail_error = '';
            if ($admin) {
                $token      = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $name       = $admin['username'];

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
                    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('SMTP_USERNAME') ?: '';
                    $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
                    $mail->setFrom(getenv('MAIL_FROM') ?: 'noreply@carforyou.com', 'CarForYou Admin');
                    $mail->addAddress($fp_email, $name);
                    $mail->isHTML(true);
                    $mail->Subject = 'CarForYou Admin — Reset Your Password';
                    $mail->Body    = "
<div style='font-family:sans-serif;max-width:520px;margin:auto;background:#0f1319;color:#f0f2f8;padding:36px;border-radius:16px;border:1px solid rgba(255,255,255,0.08)'>
    <div style='text-align:center;margin-bottom:24px'>
        <h1 style='font-size:22px;font-weight:800;margin:0'>Car<span style='color:#4f8ef7'>ForYou</span> <span style='font-size:13px;color:#7a93b0;font-weight:400;letter-spacing:0.1em'>ADMIN</span></h1>
    </div>
    <h2 style='font-size:18px;font-weight:700;margin-bottom:8px'>Admin Password Reset</h2>
    <p style='color:#8892a4;font-size:14px;line-height:1.6;margin-bottom:20px'>
        Hi <strong style='color:#f0f2f8'>$name</strong>,<br><br>
        A password reset was requested for your admin account.
        This link expires in <strong style='color:#fbbf24'>1 hour</strong>.
    </p>
    <div style='text-align:center;margin:28px 0'>
        <a href='$reset_link'
           style='display:inline-block;padding:13px 28px;background:linear-gradient(135deg,#4f8ef7,#7db0fb);color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;letter-spacing:0.05em'>
            Reset Admin Password
        </a>
    </div>
    <p style='color:#44505e;font-size:12px;line-height:1.6;border-top:1px solid rgba(255,255,255,0.06);padding-top:16px;margin-top:8px'>
        If the button doesn't work, copy this link into your browser:<br>
        <span style='color:#4f8ef7;word-break:break-all'>$reset_link</span><br><br>
        If you didn't request this, your account may be at risk. Change your password immediately.
    </p>
</div>";
                    $mail->AltBody = "Hi $name,\n\nReset your admin password (valid 1 hour):\n$reset_link\n\nIf you didn't request this, change your password immediately.";
                    $mail->send();
                    $mail_sent = true;
                } catch (Exception $e) {
                    $mail_error = $e->getMessage();
                    error_log("Password Reset Mail Error: " . $mail_error);
                }
            }

            if ($admin) {
                $msg = "Password reset link sent! Check your email inbox.";
            } else {
                $error = "This email is not registered as an admin.";
            }
        }
}

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    if (!isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['admin_csrf'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'];

        $window = date('Y-m-d H:i:s', strtotime('-10 minutes'));
        $chk    = $conn->prepare(
            "SELECT COUNT(*) AS attempts FROM admin_login_attempts
             WHERE username = ? AND ip_address = ? AND attempted_at > ? AND success = 0"
        );
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

            $valid = false;
            if ($admin) {
                $valid = password_verify($password, $admin['password']);
            }

            if ($valid) {
                // Password verified - now require 2FA
                $log = $conn->prepare("INSERT INTO admin_login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, 1, NOW())");
                $log->bind_param("ss", $username, $ip);
                $log->execute();

                $clr = $conn->prepare("DELETE FROM admin_login_attempts WHERE username = ? AND success = 0");
                $clr->bind_param("s", $username);
                $clr->execute();

                $upd = $conn->prepare("UPDATE admin SET last_login = NOW() WHERE id = ?");
                $upd->bind_param("i", $admin['id']);
                $upd->execute();

                // Set 2FA pending session
                $_SESSION['2fa_pending'] = ['id' => $admin['id'], 'username' => $admin['username']];
                header('Location: index.php'); exit();

            } else {
                $log = $conn->prepare("INSERT INTO admin_login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, 0, NOW())");
                $log->bind_param("ss", $username, $ip);
                $log->execute();

                $remaining = max(0, 5 - ($attempts + 1));
                $error     = $remaining > 0
                    ? "Invalid username or password. {$remaining} attempt" . ($remaining == 1 ? '' : 's') . " remaining."
                    : "Too many failed attempts. Please wait 10 minutes.";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    :root { --tr:0.35s cubic-bezier(0.4,0,0.2,1); }
    [data-theme="dark"] {
        --bg:#0d1117; --surface:#1e2738; --surface2:#253044;
        --border:rgba(99,155,255,0.1); --border2:rgba(99,155,255,0.18);
        --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
        --accent:#4f8ef7; --accent2:#7db0fb; --glow:rgba(79,142,247,0.25);
        --input-bg:#253044; --input-border:rgba(99,155,255,0.18);
        --overlay:rgba(0,0,0,0.75); --grid-line:rgba(79,142,247,0.04);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --surface:#ffffff; --surface2:#f5f7fa;
        --border:rgba(99,120,155,0.14); --border2:rgba(99,120,155,0.24);
        --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
        --accent:#2563eb; --accent2:#3b82f6; --glow:rgba(37,99,235,0.2);
        --input-bg:#ffffff; --input-border:rgba(99,120,155,0.3);
        --overlay:rgba(13,17,23,0.65); --grid-line:rgba(37,99,235,0.04);
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html{font-size:16px;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;transition:background var(--tr),color var(--tr);position:relative;overflow:hidden;}
    body::before{content:'';position:fixed;inset:0;z-index:0;background-image:linear-gradient(var(--grid-line) 1px,transparent 1px),linear-gradient(90deg,var(--grid-line) 1px,transparent 1px);background-size:40px 40px;animation:gridDrift 20s linear infinite;}
    @keyframes gridDrift{from{background-position:0 0}to{background-position:40px 40px}}
    body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse 60% 50% at 50% 50%,var(--glow),transparent 70%);}
    .theme-corner{position:fixed;top:20px;right:20px;z-index:100;}
    .theme-btn{width:38px;height:38px;border-radius:10px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.9rem;transition:all 0.2s;}
    .theme-btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 12px var(--glow);}
    .login-wrap{position:relative;z-index:10;width:100%;max-width:420px;padding:20px;}
    .login-card{background:var(--surface);border:1px solid var(--border2);border-radius:18px;padding:40px 36px;box-shadow:0 24px 60px rgba(0,0,0,0.3),0 0 0 1px var(--border);animation:cardIn 0.55s cubic-bezier(0.34,1.2,0.64,1) forwards;opacity:0;}
    @keyframes cardIn{from{opacity:0;transform:translateY(30px) scale(0.96)}to{opacity:1;transform:translateY(0) scale(1)}}
    .brand{text-align:center;margin-bottom:32px;}
    .brand-logo{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:14px;font-size:1.4rem;color:#fff;margin-bottom:14px;box-shadow:0 6px 20px var(--glow);}
    .brand h1{font-family:'Syne',sans-serif;font-size:1.55rem;font-weight:800;color:var(--text);letter-spacing:-0.01em;}
    .brand h1 span{color:var(--accent);}
    .brand p{font-size:0.8rem;color:var(--text3);margin-top:5px;letter-spacing:0.04em;}
    .divider{height:1px;background:var(--border);margin-bottom:28px;}
    .alert{display:flex;align-items:center;gap:9px;padding:12px 14px;border-radius:10px;font-size:0.83rem;font-weight:500;margin-bottom:20px;animation:fadeIn 0.3s ease;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
    .alert-error{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.22);}
    .alert-success{background:rgba(34,197,94,0.1);color:#22c55e;border:1px solid rgba(34,197,94,0.22);}
    .form-group{margin-bottom:16px;}
    .form-group label{display:block;margin-bottom:6px;font-size:0.72rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text3);}
    .input-wrap{position:relative;display:flex;align-items:center;}
    .input-wrap .field-icon{position:absolute;left:13px;color:var(--text3);font-size:0.85rem;pointer-events:none;transition:color 0.2s;z-index:2;}
    .input-wrap:focus-within .field-icon{color:var(--accent);}
    .form-control{width:100%;padding:11px 42px 11px 38px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.88rem;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
    .form-control::placeholder{color:var(--text3);}
    .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--glow);}
    .pw-toggle{position:absolute;right:0;width:42px;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text3);cursor:pointer;font-size:0.85rem;transition:color 0.2s;background:none;border:none;border-radius:0 9px 9px 0;z-index:2;}
    .pw-toggle:hover{color:var(--accent);}
    .btn-login{width:100%;padding:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;margin-top:6px;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.22s;box-shadow:0 4px 16px var(--glow);}
    .btn-login:hover{opacity:0.88;transform:translateY(-2px);box-shadow:0 8px 24px var(--glow);}
    .btn-login:active{transform:translateY(0);}
    .forgot-wrap{text-align:center;margin-top:18px;}
    .forgot-link{display:inline-flex;align-items:center;gap:6px;font-size:0.8rem;color:var(--text3);cursor:pointer;transition:color 0.2s;background:none;border:none;font-family:'DM Sans',sans-serif;}
    .forgot-link:hover{color:var(--accent);}
    .login-footer{text-align:center;margin-top:20px;font-size:0.72rem;color:var(--text3);letter-spacing:0.04em;}
    .modal-overlay{display:none;position:fixed;inset:0;background:var(--overlay);backdrop-filter:blur(6px);z-index:500;align-items:center;justify-content:center;padding:20px;}
    .modal-overlay.open{display:flex;}
    .modal{background:var(--surface);border:1px solid var(--border2);border-radius:16px;padding:30px;width:100%;max-width:400px;position:relative;box-shadow:0 24px 60px rgba(0,0,0,0.4);animation:modalIn 0.3s cubic-bezier(0.34,1.4,0.64,1) forwards;}
    @keyframes modalIn{from{opacity:0;transform:scale(0.92) translateY(16px)}to{opacity:1;transform:scale(1) translateY(0)}}
    .modal h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--text);margin-bottom:6px;display:flex;align-items:center;gap:9px;}
    .modal h3 i{color:var(--accent);font-size:0.88rem;}
    .modal-sub{font-size:0.8rem;color:var(--text2);margin-bottom:20px;line-height:1.5;}
    .modal-close{position:absolute;top:14px;right:14px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:0.85rem;color:var(--text2);display:flex;align-items:center;justify-content:center;transition:all 0.2s;}
    .modal-close:hover{background:var(--border2);color:var(--text);}
    .btn-reset{width:100%;padding:11px;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:9px;color:#fff;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:8px;transition:all 0.2s;box-shadow:0 3px 12px var(--glow);}
    .btn-reset:hover{opacity:0.88;transform:translateY(-1px);}
    .modal-success{text-align:center;padding:8px 0;}
    .modal-success-icon{width:56px;height:56px;border-radius:50%;background:rgba(34,197,94,0.1);border:2px solid rgba(34,197,94,0.25);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#22c55e;margin:0 auto 14px;animation:popIn 0.35s cubic-bezier(0.34,1.56,0.64,1);}
    @keyframes popIn{from{transform:scale(0.5);opacity:0}to{transform:scale(1);opacity:1}}
    .modal-success h4{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--text);margin-bottom:6px;}
    .modal-success p{font-size:0.8rem;color:var(--text2);line-height:1.6;}
    .modal-error{text-align:center;padding:8px 0;}
    .modal-error-icon{width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.1);border:2px solid rgba(239,68,68,0.25);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#ef4444;margin:0 auto 14px;animation:popIn 0.35s cubic-bezier(0.34,1.56,0.64,1);}
    .modal-error h4{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--text);margin-bottom:6px;}
    .modal-error p{font-size:0.8rem;color:var(--text2);line-height:1.6;}

    /* ✅ NEW: inbox/spam tip cards */
    .inbox-tips{display:flex;gap:8px;margin-top:16px;}
    .tip-card{flex:1;padding:10px 8px;border-radius:10px;text-align:center;font-size:0.72rem;font-weight:600;line-height:1.4;}
    .tip-inbox{background:rgba(79,142,247,0.1);border:1px solid rgba(79,142,247,0.22);color:var(--accent);}
    .tip-spam{background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.22);color:#fbbf24;}
    .tip-card i{display:block;font-size:1.1rem;margin-bottom:5px;}
    .tip-divider{height:1px;background:var(--border);margin:14px 0;}
    .tip-note{font-size:0.72rem;color:var(--text3);text-align:center;line-height:1.5;}
    </style>
</head>
<body>

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
            <p>ADMIN CONSOLE &nbsp;&middot;&nbsp; SIGN IN TO CONTINUE</p>
        </div>

        <div class="divider"></div>

        <?php if (isset($show_2fa) && $show_2fa): ?>
        <!-- 2FA Verification Form -->
        <div class="brand" style="margin-bottom:20px;">
            <div style="width:60px;height:60px;background:rgba(79,142,247,0.15);border:2px solid rgba(79,142,247,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="fa fa-shield-halved" style="font-size:1.5rem;color:#4f8ef7;"></i>
            </div>
            <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;">Two-Factor Authentication</h2>
            <p style="color:var(--text2);margin-top:8px;font-size:0.85rem;">Enter the 6-digit code sent to your email</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
        <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <div class="form-group" style="text-align:center;">
                <label>Verification Code</label>
                <div class="input-wrap" style="max-width:220px;margin:0 auto;">
                    <input type="text" name="2fa_code" class="form-control" 
                           placeholder="000000" maxlength="6" required 
                           autocomplete="one-time-code" style="text-align:center;font-size:1.5rem;letter-spacing:8px;font-weight:700;"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,6)">
                </div>
                <p style="font-size:0.72rem;color:var(--text3);margin-top:8px;">Code expires in 5 minutes</p>
            </div>
            <button type="submit" name="verify_2fa" class="btn-login">
                <i class="fa fa-check"></i> Verify Code
            </button>
        </form>
        
        <div class="forgot-wrap" style="margin-top:16px;">
            <form method="POST" style="display:inline;">
                <button type="submit" name="resend_2fa" class="forgot-link" style="background:none;border:none;color:var(--text3);cursor:pointer;font-family:'DM Sans',sans-serif;">
                    <i class="fa fa-redo"></i> Resend Code
                </button>
            </form>
            <span style="color:var(--text3);margin:0 10px;">|</span>
            <a href="index.php" class="forgot-link" style="color:var(--text3);">
                <i class="fa fa-arrow-left"></i> Back to Login
            </a>
        </div>
        
        <?php else: ?>
        
        <?php if ($error && !isset($_POST['forgot_password'])): ?>
        <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
            <div class="form-group">
                <label>Username</label>
                <div class="input-wrap">
                    <i class="fa fa-user field-icon"></i>
                    <input type="text" name="username" class="form-control"
                           placeholder="Enter your username" required autocomplete="username">
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="fa fa-lock field-icon"></i>
                    <input type="password" name="password" id="loginPwd" class="form-control"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" id="eyeLoginPwd" tabindex="-1">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="login" class="btn-login">
                <i class="fa fa-shield-halved"></i> Sign In (2FA Protected)
            </button>
        </form>

        <div class="forgot-wrap">
            <button class="forgot-link" onclick="openForgotModal()">
                <i class="fa fa-key"></i> Forgot Password?
            </button>
        </div>

        <div class="login-footer">Secure admin area — unauthorised access is prohibited</div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: Forgot Password -->
<div class="modal-overlay" id="forgotModal">
    <div class="modal">
        <button class="modal-close" onclick="closeForgotModal()">
            <i class="fa fa-xmark"></i>
        </button>

        <?php if ($msg && isset($_POST['forgot_password'])): ?>
        <div class="modal-success">
            <div class="modal-success-icon"><i class="fa fa-envelope-circle-check"></i></div>
            <h4>Reset Link Sent!</h4>
            <p><?php echo htmlspecialchars($msg); ?></p>
            <div class="inbox-tips">
                <div class="tip-card tip-inbox">
                    <i class="fa fa-inbox"></i>
                    Check your<br><strong>Inbox</strong>
                </div>
                <div class="tip-card tip-spam">
                    <i class="fa fa-triangle-exclamation"></i>
                    Not there?<br><strong>Check Spam</strong>
                </div>
            </div>
            <div class="tip-divider"></div>
            <p class="tip-note">
                <i class="fa fa-clock" style="color:var(--accent);margin-right:4px;"></i>
                Link expires in <strong style="color:var(--accent)">1 hour</strong>
            </p>
        </div>

        <?php elseif ($error && isset($_POST['forgot_password'])): ?>
        <h3><i class="fa fa-lock-open"></i> Reset Admin Password</h3>
        <p class="modal-sub">Enter your registered email and we'll send you a secure reset link.</p>
        <div class="alert alert-error" style="margin-bottom:16px;">
            <i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Registered Email</label>
                <div class="input-wrap">
                    <i class="fa fa-envelope field-icon"></i>
                    <input type="email" name="email" class="form-control"
                           placeholder="admin@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required autofocus>
                </div>
            </div>
            <button type="submit" name="forgot_password" class="btn-reset">
                <i class="fa fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <?php else: ?>
        <h3><i class="fa fa-lock-open"></i> Reset Admin Password</h3>
        <p class="modal-sub">Enter your registered email and we'll send you a secure reset link.</p>

        <form method="POST">
            <div class="form-group">
                <label>Registered Email</label>
                <div class="input-wrap">
                    <i class="fa fa-envelope field-icon"></i>
                    <input type="email" name="email" class="form-control"
                           placeholder="admin@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required autofocus>
                </div>
            </div>
            <button type="submit" name="forgot_password" class="btn-reset">
                <i class="fa fa-paper-plane"></i> Send Reset Link
            </button>
        </form>
        <?php endif; ?>

    </div>
</div>

<script>
    var theme = localStorage.getItem('adminTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme==='dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('adminTheme', theme);
        syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark' ? 'fa fa-moon' : 'fa fa-sun'; }

    function openForgotModal(){  document.getElementById('forgotModal').classList.add('open'); }
    function closeForgotModal(){ document.getElementById('forgotModal').classList.remove('open'); }
    document.getElementById('forgotModal').addEventListener('click', function(e){
        if(e.target === this) closeForgotModal();
    });

    <?php if (isset($_POST['forgot_password'])): ?>
    openForgotModal();
    <?php endif; ?>

    function bindHoldPwd(btnId, inputId){
        var btn  = document.getElementById(btnId);
        var inp  = document.getElementById(inputId);
        if(!btn || !inp) return;
        var icon = btn.querySelector('i');
        function show(){ inp.type='text';     icon.className='fa fa-eye-slash'; }
        function hide(){ inp.type='password'; icon.className='fa fa-eye'; }
        btn.addEventListener('mousedown',   function(e){ e.preventDefault(); show(); });
        btn.addEventListener('mouseup',     hide);
        btn.addEventListener('mouseleave',  hide);
        btn.addEventListener('touchstart',  function(e){ e.preventDefault(); show(); },{passive:false});
        btn.addEventListener('touchend',    hide);
        btn.addEventListener('touchcancel', hide);
    }
    bindHoldPwd('eyeLoginPwd', 'loginPwd');
</script>
</body>
</html>
