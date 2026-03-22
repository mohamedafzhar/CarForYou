<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_SESSION['admin_reg_pending'])) {
    header("Location: verify_admin_otp.php");
    exit();
}

$error = '';
$success = '';
$cancelled = isset($_GET['cancelled']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check existing username
        $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            // Check existing email
            $stmt = $conn->prepare("SELECT id FROM admin WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                // Generate OTP
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Store in session
                $_SESSION['admin_reg_pending'] = [
                    'username' => $username,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'full_name' => $full_name,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $_SESSION['admin_otp_code'] = $code;
                $_SESSION['admin_otp_expires'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                // Send OTP email
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
                    $mail->addAddress($email, $full_name);
                    $mail->isHTML(true);
                    $mail->Subject = 'CarForYou Admin - Verification Code';
                    $mail->Body = "
                        <div style='font-family:Outfit,sans-serif;max-width:480px;margin:auto;background:#0f1319;color:#f0f2f8;padding:36px;border-radius:16px;border:1px solid rgba(255,255,255,0.08)'>
                            <div style='text-align:center;margin-bottom:24px'>
                                <h1 style='font-size:22px;font-weight:800;margin:0'>Car<span style='color:#00d4ff'>ForYou</span> Admin</h1>
                            </div>
                            <h2 style='font-size:18px;font-weight:700;margin-bottom:8px'>Admin Registration - Verify Your Email</h2>
                            <p style='color:#8892a4;font-size:14px;line-height:1.6;margin-bottom:20px'>
                                Hi <strong style='color:#f0f2f8'>$full_name</strong>,<br><br>
                                Your admin registration verification code is:
                            </p>
                            <div style='text-align:center;padding:24px;background:#1a2030;border-radius:12px;margin:20px 0;'>
                                <span style='font-size:36px;font-weight:800;letter-spacing:12px;color:#00d4ff;'>$code</span>
                            </div>
                            <div style='background:rgba(255,79,79,0.1);border:1px solid rgba(255,79,79,0.3);border-radius:8px;padding:12px;margin:16px 0;'>
                                <p style='color:#ff4f4f;font-size:12px;margin:0;'>
                                    <strong>Important:</strong> This code expires in 10 minutes. You must verify your email to complete admin registration.
                                </p>
                            </div>
                            <p style='color:#44505e;font-size:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:16px'>
                                If you didn't request this registration, please ignore this email immediately.
                            </p>
                        </div>";
                    $mail->send();
                    $success = "Verification code sent! Check your email.";
                } catch (Exception $e) {
                    $error = "Failed to send verification code.";
                }
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
    <title>Admin Registration | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        [data-theme="dark"] {
            --bg: #0b0e14; --surface: #141920; --surface2: #1a2030;
            --border: rgba(255,255,255,0.08); --border2: rgba(255,255,255,0.12);
            --text: #f0f2f8; --text2: #8892a4; --text3: #44505e;
            --accent: #00d4ff; --accent2: #0090ff;
            --green: #00e676; --greenbg: rgba(0,230,118,0.1);
            --red: #ff4f4f; --redbg: rgba(255,79,79,0.1);
            --warning: #fbbf24; --warningbg: rgba(251,191,36,0.1);
            --shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        [data-theme="light"] {
            --bg: #f0f4f8; --surface: #ffffff; --surface2: #f5f8fc;
            --border: rgba(0,0,0,0.08); --border2: rgba(0,0,0,0.12);
            --text: #0f1923; --text2: #4a5568; --text3: #94a3b8;
            --accent: #0077cc; --accent2: #0055aa;
            --green: #059669; --greenbg: rgba(5,150,105,0.1);
            --red: #dc2626; --redbg: rgba(220,38,38,0.1);
            --warning: #d97706; --warningbg: rgba(217,119,6,0.1);
            --shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 20px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: var(--shadow);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.5rem;
            color: #fff;
            box-shadow: 0 4px 20px rgba(0, 212, 255, 0.3);
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .subtitle {
            color: var(--text2);
            font-size: 0.9rem;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: var(--greenbg); color: var(--green); border: 1px solid rgba(0,230,118,0.2); }
        .alert-error { background: var(--redbg); color: var(--red); border: 1px solid rgba(255,79,79,0.2); }
        .warning-box {
            background: var(--warningbg);
            border: 1px solid rgba(251,191,36,0.3);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .warning-box i {
            color: var(--warning);
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .warning-box p {
            color: var(--warning);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text3);
            margin-bottom: 8px;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 0.85rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.15);
        }
        .btn {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border: none;
            color: #fff;
            box-shadow: 0 4px 16px rgba(0, 212, 255, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(0, 212, 255, 0.4);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text3);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--accent);
        }
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--surface);
            border: 1px solid var(--border2);
            color: var(--text2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .theme-toggle:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">
        <i class="fa fa-moon" id="themeIcon"></i>
    </button>

    <div class="card">
        <div class="header">
            <div class="logo"><i class="fa fa-user-shield"></i></div>
            <h1>Admin Registration</h1>
            <p class="subtitle">Create your admin account</p>
        </div>

        <?php if ($cancelled): ?>
            <div class="alert alert-error">
                <i class="fa fa-circle-xmark"></i> Registration cancelled. You can start again.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <i class="fa fa-shield-halved"></i>
            <p>
                <strong>Admin Registration Requires Verification</strong><br>
                A verification code will be sent to your email. You must verify your email to complete admin registration.
            </p>
        </div>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Full Name</label>
                <div class="input-wrap">
                    <i class="fa fa-user"></i>
                    <input type="text" name="full_name" class="form-control" placeholder="Admin Name" required>
                </div>
            </div>

            <div class="form-group">
                <label>Username</label>
                <div class="input-wrap">
                    <i class="fa fa-at"></i>
                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <div class="input-wrap">
                    <i class="fa fa-envelope"></i>
                    <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa fa-paper-plane"></i> Register & Send Verification Code
            </button>
        </form>

        <a href="index.php" class="back-link">
            <i class="fa fa-arrow-left"></i> Back to Admin Login
        </a>
    </div>

    <script>
        var theme = localStorage.getItem('cfyTheme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
        
        function toggleTheme() {
            theme = theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('cfyTheme', theme);
            document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
        }
    </script>
</body>
</html>
