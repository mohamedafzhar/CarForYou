<?php
session_start();
require_once('../includes/config.php');
date_default_timezone_set('Asia/Colombo');

require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: car_dashboard.php");
    exit();
}

$success_msg = "";
$error_msg   = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } else {
        // Check if email exists in DB
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error_msg = "No account found with that email.";
        } else {
            $user    = $result->fetch_assoc();
            $name    = $user['full_name'];

            // Generate secure token
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete old tokens, save new
            $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();

            $ins = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $email, $token, $expires_at);
            $ins->execute();

            // ✅ FIXED PATH — matches your project
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/carrental/users/reset_password.php?token=" . $token;

            // ── BREVO SMTP via PHPMailer ──────────────────────────
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USERNAME') ?: '';
                $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);

                $mail->setFrom(getenv('MAIL_FROM') ?: 'noreply@carforyou.com', 'CarForYou');
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'CarForYou — Reset Your Password';
                $mail->Body    = "
    <div style='font-family:Outfit,sans-serif;max-width:520px;margin:auto;background:#0f1319;color:#f0f2f8;padding:36px;border-radius:16px;border:1px solid rgba(255,255,255,0.08)'>
        <div style='text-align:center;margin-bottom:24px'>
            <h1 style='font-size:22px;font-weight:800;margin:0'>Car<span style='color:#00d4ff'>ForYou</span></h1>
        </div>
        <h2 style='font-size:18px;font-weight:700;margin-bottom:8px'>Password Reset Request</h2>
        <p style='color:#8892a4;font-size:14px;line-height:1.6;margin-bottom:20px'>
            Hi <strong style='color:#f0f2f8'>$name</strong>,<br><br>
            We received a request to reset your CarForYou password.
            Click the button below — this link is valid for <strong style='color:#fbbf24'>1 hour</strong>.
        </p>
        <div style='text-align:center;margin:28px 0'>
            <a href='$reset_link'
               style='display:inline-block;padding:13px 28px;background:linear-gradient(135deg,#00d4ff,#0090ff);
                      color:#fff;border-radius:10px;text-decoration:none;font-weight:700;
                      font-size:14px;letter-spacing:0.05em'>
                Reset My Password
            </a>
        </div>
        <p style='color:#44505e;font-size:12px;line-height:1.6;border-top:1px solid rgba(255,255,255,0.06);padding-top:16px;margin-top:8px'>
            If the button doesn't work, copy this link:<br>
            <span style='color:#00d4ff;word-break:break-all'>$reset_link</span><br><br>
            If you didn't request this, you can safely ignore this email.
        </p>
    </div>";
                $mail->AltBody = "Hi $name,\n\nReset your password (valid 1 hour):\n$reset_link\n\nIgnore if you didn't request this.";

                $mail->send();
                $success_msg = "If that email exists, a reset link has been sent.";

            } catch (Exception $e) {
                $error_msg = "Mail Error: " . $mail->ErrorInfo;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        [data-theme="dark"] {
            --bg: #0b0e14;
            --surface: #141920;
            --surface2: #1a2030;
            --surface3: #1f2638;
            --border: rgba(255,255,255,0.06);
            --border2: rgba(255,255,255,0.1);
            --text: #f0f2f8;
            --text2: #8892a4;
            --text3: #44505e;
            --accent: #00d4ff;
            --accent2: #0090ff;
            --accentglow: rgba(0,212,255,0.18);
            --accentglow2: rgba(0,212,255,0.06);
            --green: #00e676;
            --greenbg: rgba(0,230,118,0.08);
            --greenglow: rgba(0,230,118,0.18);
            --red: #ff4f4f;
            --redbg: rgba(255,79,79,0.08);
            --shadow: 0 0 0 1px rgba(0,212,255,0.08), 0 24px 60px rgba(0,0,0,0.6);
            --spot1: rgba(0,144,255,0.09);
            --spot2: rgba(0,212,255,0.07);
            --spot3: rgba(0,230,118,0.05);
            --grid-color: rgba(0,212,255,0.03);
        }

        [data-theme="light"] {
            --bg: #f0f4f8;
            --surface: #ffffff;
            --surface2: #f5f8fc;
            --surface3: #eaf0f8;
            --border: rgba(0,0,0,0.07);
            --border2: rgba(0,0,0,0.12);
            --text: #0f1923;
            --text2: #4a5568;
            --text3: #94a3b8;
            --accent: #0077cc;
            --accent2: #0055aa;
            --accentglow: rgba(0,119,204,0.18);
            --accentglow2: rgba(0,119,204,0.08);
            --green: #059669;
            --greenbg: rgba(5,150,105,0.08);
            --greenglow: rgba(5,150,105,0.18);
            --red: #dc2626;
            --redbg: rgba(220,38,38,0.07);
            --shadow: 0 0 0 1px rgba(0,119,204,0.1), 0 24px 60px rgba(0,0,0,0.1);
            --spot1: rgba(0,119,204,0.06);
            --spot2: rgba(0,180,255,0.05);
            --spot3: rgba(5,150,105,0.04);
            --grid-color: rgba(0,119,204,0.04);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 44px 16px;
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

        .theme-corner { position: fixed; top: 18px; right: 18px; z-index: 200; }

        .theme-toggle {
            width: 40px; height: 40px;
            border-radius: 11px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            color: var(--text2);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.92rem;
            transition: all 0.22s;
            box-shadow: 0 2px 12px rgba(0,0,0,0.2);
        }

        .theme-toggle:hover {
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: 0 0 14px var(--accentglow);
        }

        .wrap { position: relative; z-index: 10; width: 100%; max-width: 440px; }

        .brand-bar {
            text-align: center;
            margin-bottom: 28px;
            animation: fadeDown 0.5s ease both;
        }

        .brand-bar a { text-decoration: none; display: inline-block; }

        .brand-logo {
            display: inline-flex; align-items: center; justify-content: center;
            width: 54px; height: 54px;
            background: linear-gradient(135deg, rgba(0,212,255,0.15), rgba(0,144,255,0.25));
            border: 1px solid rgba(0,212,255,0.25);
            border-radius: 16px;
            font-size: 1.35rem;
            color: var(--accent);
            margin-bottom: 12px;
            box-shadow: 0 0 30px var(--accentglow), inset 0 1px 0 rgba(255,255,255,0.08);
        }

        .brand-bar h1 {
            font-size: 1.65rem; font-weight: 800;
            color: var(--text); letter-spacing: -0.03em;
        }

        .brand-bar h1 span {
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-bar p {
            font-size: 0.72rem; color: var(--text3);
            letter-spacing: 0.14em; margin-top: 4px; font-weight: 500;
        }

        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-12px) }
            to   { opacity: 1; transform: translateY(0) }
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: cardUp 0.55s cubic-bezier(0.22,1,0.36,1) 0.08s both;
            position: relative;
        }

        .card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent);
            opacity: 0.6;
        }

        @keyframes cardUp {
            from { opacity: 0; transform: translateY(24px) }
            to   { opacity: 1; transform: translateY(0) }
        }

        .card-header {
            background: linear-gradient(135deg, rgba(0,212,255,0.06), transparent);
            border-bottom: 1px solid var(--border);
            padding: 28px 28px 22px;
            text-align: center;
        }

        .header-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, rgba(0,212,255,0.12), rgba(0,144,255,0.2));
            border: 1px solid rgba(0,212,255,0.22);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: var(--accent);
            box-shadow: 0 0 24px var(--accentglow);
        }

        .card-header h2 {
            font-size: 1.4rem; font-weight: 800;
            color: var(--text); letter-spacing: -0.02em; margin-bottom: 6px;
        }

        .card-header p { font-size: 0.82rem; color: var(--text2); line-height: 1.5; }

        .card-body { padding: 26px 28px 30px; }

        .alert {
            display: flex; align-items: flex-start; gap: 9px;
            padding: 12px 14px; border-radius: 10px;
            font-size: 0.82rem; font-weight: 500; line-height: 1.5;
            margin-bottom: 18px;
            animation: alertIn 0.3s ease;
        }

        .alert i { flex-shrink: 0; margin-top: 1px; }

        @keyframes alertIn {
            from { opacity: 0; transform: translateY(-4px) }
            to   { opacity: 1; transform: translateY(0) }
        }

        .alert-success {
            background: var(--greenbg); color: var(--green);
            border: 1px solid rgba(0,230,118,0.2);
        }

        .alert-error {
            background: var(--redbg); color: var(--red);
            border: 1px solid rgba(255,79,79,0.2);
        }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block; font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--text3); margin-bottom: 7px;
        }

        .input-wrap { position: relative; display: flex; align-items: center; }

        .input-wrap .fi {
            position: absolute; left: 13px;
            color: var(--text3); font-size: 0.78rem;
            pointer-events: none; z-index: 2;
            transition: color 0.22s;
        }

        .input-wrap:focus-within .fi { color: var(--accent); }

        .form-control {
            width: 100%;
            padding: 13px 13px 13px 36px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.22s, box-shadow 0.22s, background 0.22s;
        }

        .form-control::placeholder { color: var(--text3); }

        .form-control:focus {
            border-color: rgba(0,212,255,0.45);
            background: var(--surface3);
            box-shadow: 0 0 0 3px var(--accentglow2), 0 0 12px rgba(0,212,255,0.08);
        }

        .btn-submit {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, rgba(0,212,255,0.15), rgba(0,144,255,0.2));
            border: 1px solid rgba(0,212,255,0.3);
            border-radius: 12px;
            color: var(--accent);
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem; font-weight: 700;
            letter-spacing: 0.05em; text-transform: uppercase;
            cursor: pointer; margin-top: 4px;
            display: flex; align-items: center; justify-content: center; gap: 9px;
            transition: all 0.22s;
            box-shadow: 0 0 20px var(--accentglow), inset 0 1px 0 rgba(255,255,255,0.06);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 35px var(--accentglow), 0 8px 24px rgba(0,0,0,0.3);
            border-color: rgba(0,212,255,0.55);
        }

        .btn-submit:active { transform: translateY(0); }

        .back-link-row {
            text-align: center; margin-top: 18px;
            font-size: 0.8rem; color: var(--text3);
        }

        .back-link-row a {
            color: var(--accent); font-weight: 600;
            text-decoration: none; transition: opacity 0.2s;
        }

        .back-link-row a:hover {
            opacity: 0.7;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .back-home {
            text-align: center; margin-top: 18px;
            animation: fadeDown 0.5s ease 0.3s both;
            opacity: 0;
        }

        .back-home a {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 0.76rem; color: var(--text3);
            text-decoration: none; font-weight: 500;
            transition: color 0.2s;
        }

        .back-home a:hover { color: var(--accent); }

        .success-state { text-align: center; padding: 10px 0 6px; }

        .success-icon {
            width: 64px; height: 64px; border-radius: 50%;
            margin: 0 auto 18px;
            background: var(--greenbg);
            border: 2px solid rgba(0,230,118,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: var(--green);
            box-shadow: 0 0 24px var(--greenglow);
            animation: popIn 0.4s cubic-bezier(0.22,1,0.36,1);
        }

        @keyframes popIn {
            from { transform: scale(0.5); opacity: 0 }
            to   { transform: scale(1);   opacity: 1 }
        }

        .success-state h3 {
            font-size: 1.1rem; font-weight: 800;
            color: var(--text); margin-bottom: 8px;
        }

        .success-state p { font-size: 0.82rem; color: var(--text2); line-height: 1.6; }
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
        <div class="card-header">
            <div class="header-icon"><i class="fa fa-key"></i></div>
            <h2>Forgot Password?</h2>
            <p>Enter your registered email and we'll send you a link to reset your password.</p>
        </div>

        <div class="card-body">

            <?php if ($success_msg): ?>
                <!-- ✅ SUCCESS STATE -->
                <div class="success-state">
                    <div class="success-icon"><i class="fa fa-envelope-circle-check"></i></div>
                    <h3>Check Your Email</h3>
                    <p><?php echo htmlspecialchars($success_msg); ?><br><br>
                        The link will expire in <strong style="color:var(--accent)">1 hour</strong>.
                        Check your spam folder if you don't see it.
                    </p>
                </div>
                <div class="back-link-row" style="margin-top:24px;">
                    <a href="login.php"><i class="fa fa-arrow-left" style="margin-right:5px;"></i>Back to Sign In</a>
                </div>

            <?php else: ?>
                <!-- ❌ ERROR (if any) -->
                <?php if ($error_msg): ?>
                    <div class="alert alert-error">
                        <i class="fa fa-circle-xmark"></i>
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <!-- 📧 FORM -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrap">
                            <i class="fa fa-envelope fi"></i>
                            <input type="email" name="email" class="form-control"
                                   placeholder="you@example.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fa fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>

                <div class="back-link-row" style="margin-top:20px;">
                    <a href="login.php"><i class="fa fa-arrow-left" style="margin-right:5px;"></i>Back to Sign In</a>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <div class="back-home">
        <a href="../index.php"><i class="fa fa-arrow-left"></i> Back to homepage</a>
    </div>

</div>

<script>
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeToggle').addEventListener('click', function () {
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        syncIcon();
    });
    function syncIcon() {
        document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
    }
</script>

</body>
</html>
