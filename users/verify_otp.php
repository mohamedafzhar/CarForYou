<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['reg_pending'])) {
    header("Location: login.php?tab=register");
    exit();
}

require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pending = $_SESSION['reg_pending'];
$error = '';
$success = '';

// Resend OTP
if (isset($_POST['resend'])) {
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_code'] = $code;
    $_SESSION['otp_expires'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)SMTP_PORT;
        $mail->setFrom(MAIL_FROM, 'CarForYou');
        $mail->addAddress($pending['email'], $pending['full_name']);
        $mail->isHTML(true);
        $mail->Subject = 'CarForYou - Your Verification Code';
        $mail->Body = "
            <div style='font-family:Outfit,sans-serif;max-width:480px;margin:auto;background:#0f1319;color:#f0f2f8;padding:36px;border-radius:16px;border:1px solid rgba(255,255,255,0.08)'>
                <div style='text-align:center;margin-bottom:24px'>
                    <h1 style='font-size:22px;font-weight:800;margin:0'>Car<span style='color:#00d4ff'>ForYou</span></h1>
                </div>
                <h2 style='font-size:18px;font-weight:700;margin-bottom:8px'>Verify Your Email</h2>
                <p style='color:#8892a4;font-size:14px;line-height:1.6;margin-bottom:20px'>
                    Hi <strong style='color:#f0f2f8'>{$pending['full_name']}</strong>,<br><br>
                    Your verification code is:
                </p>
                <div style='text-align:center;padding:24px;background:#1a2030;border-radius:12px;margin:20px 0;'>
                    <span style='font-size:36px;font-weight:800;letter-spacing:12px;color:#00d4ff;'>$code</span>
                </div>
                <p style='color:#44505e;font-size:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:16px'>
                    This code expires in 10 minutes.<br>
                    If you didn't create this account, ignore this email.
                </p>
            </div>";
        $mail->send();
        $success = "New verification code sent to your email!";
    } catch (Exception $e) {
        $error = "Failed to send code. Please try again.";
    }
}

// Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = preg_replace('/[^0-9]/', '', $_POST['otp_code'] ?? '');
    
    if (empty($_SESSION['otp_code']) || empty($_SESSION['otp_expires'])) {
        $error = "Session expired. Please request a new code.";
    } elseif ($entered_otp !== $_SESSION['otp_code']) {
        $error = "Invalid verification code. Please try again.";
    } elseif (strtotime($_SESSION['otp_expires']) < time()) {
        $error = "Verification code expired. Please request a new code.";
    } else {
        // OTP verified - Complete registration
        $hashed = password_hash($pending['password'], PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users 
            (full_name, email, password, contact_no, dob, address, city, country, nic, license_no, reg_date, email_verified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
        $stmt->bind_param("ssssssssss", 
            $pending['full_name'],
            $pending['email'],
            $hashed,
            $pending['contact_no'],
            $pending['dob'],
            $pending['address'],
            $pending['city'],
            $pending['country'],
            $pending['nic'],
            $pending['license_no']
        );
        
        if ($stmt->execute()) {
            // Clear session
            unset($_SESSION['reg_pending'], $_SESSION['otp_code'], $_SESSION['otp_expires']);
            
            $_SESSION['success_msg'] = "Account created successfully! You can now sign in.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}

// Generate OTP if not exists or expired
if (!isset($_SESSION['otp_code']) || (isset($_SESSION['otp_expires']) && strtotime($_SESSION['otp_expires']) < time())) {
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_code'] = $code;
    $_SESSION['otp_expires'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)SMTP_PORT;
        $mail->setFrom(MAIL_FROM, 'CarForYou');
        $mail->addAddress($pending['email'], $pending['full_name']);
        $mail->isHTML(true);
        $mail->Subject = 'CarForYou - Your Verification Code';
        $mail->Body = "
            <div style='font-family:Outfit,sans-serif;max-width:480px;margin:auto;background:#0f1319;color:#f0f2f8;padding:36px;border-radius:16px;border:1px solid rgba(255,255,255,0.08)'>
                <div style='text-align:center;margin-bottom:24px'>
                    <h1 style='font-size:22px;font-weight:800;margin:0'>Car<span style='color:#00d4ff'>ForYou</span></h1>
                </div>
                <h2 style='font-size:18px;font-weight:700;margin-bottom:8px'>Verify Your Email</h2>
                <p style='color:#8892a4;font-size:14px;line-height:1.6;margin-bottom:20px'>
                    Hi <strong style='color:#f0f2f8'>{$pending['full_name']}</strong>,<br><br>
                    Your verification code is:
                </p>
                <div style='text-align:center;padding:24px;background:#1a2030;border-radius:12px;margin:20px 0;'>
                    <span style='font-size:36px;font-weight:800;letter-spacing:12px;color:#00d4ff;'>$code</span>
                </div>
                <p style='color:#44505e;font-size:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:16px'>
                    This code expires in 10 minutes.<br>
                    If you didn't create this account, ignore this email.
                </p>
            </div>";
        $mail->send();
        $success = "Verification code sent to your email!";
    } catch (Exception $e) {
        $error = "Failed to send verification code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | CarForYou</title>
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
            --shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        [data-theme="light"] {
            --bg: #f0f4f8; --surface: #ffffff; --surface2: #f5f8fc;
            --border: rgba(0,0,0,0.08); --border2: rgba(0,0,0,0.12);
            --text: #0f1923; --text2: #4a5568; --text3: #94a3b8;
            --accent: #0077cc; --accent2: #0055aa;
            --green: #059669; --greenbg: rgba(5,150,105,0.1);
            --red: #dc2626; --redbg: rgba(220,38,38,0.1);
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
            max-width: 420px;
            width: 100%;
            box-shadow: var(--shadow);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 1.8rem;
            color: #fff;
            box-shadow: 0 8px 24px rgba(0, 212, 255, 0.3);
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 8px;
        }
        p {
            text-align: center;
            color: var(--text2);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .email-display {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 24px;
            word-break: break-all;
        }
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 24px;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--surface2);
            border: 2px solid var(--border2);
            border-radius: 12px;
            color: var(--text);
            outline: none;
            transition: all 0.2s;
        }
        .otp-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.2);
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
        .btn-secondary {
            background: var(--surface2);
            border: 1px solid var(--border2);
            color: var(--text2);
            margin-top: 12px;
        }
        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
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
        .timer {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text3);
            margin-bottom: 16px;
        }
        .timer span { color: var(--accent); font-weight: 700; }
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
        .theme-toggle:hover { border-color: var(--accent); color: var(--accent); }
        .cancel-link {
            text-align: center;
            margin-top: 20px;
        }
        .cancel-link a {
            color: var(--text3);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        .cancel-link a:hover { color: var(--red); }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">
        <i class="fa fa-moon" id="themeIcon"></i>
    </button>

    <div class="card">
        <div class="icon">
            <i class="fa fa-envelope-check"></i>
        </div>
        
        <h1>Verify Your Email</h1>
        <p>We've sent a verification code to your email address. Enter it below to complete your registration.</p>
        
        <div class="email-display">
            <i class="fa fa-envelope" style="margin-right:6px;"></i>
            <?php echo htmlspecialchars($pending['email']); ?>
        </div>
        
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
        
        <div class="timer">
            Code expires in <span id="countdown">10:00</span>
        </div>
        
        <form method="POST" autocomplete="off">
            <div class="otp-inputs">
                <input type="text" class="otp-input" maxlength="1" data-index="0" autofocus>
                <input type="text" class="otp-input" maxlength="1" data-index="1">
                <input type="text" class="otp-input" maxlength="1" data-index="2">
                <input type="text" class="otp-input" maxlength="1" data-index="3">
                <input type="text" class="otp-input" maxlength="1" data-index="4">
                <input type="text" class="otp-input" maxlength="1" data-index="5">
            </div>
            <input type="hidden" name="otp_code" id="otpCode" value="">
            <button type="submit" name="verify_otp" class="btn btn-primary" id="verifyBtn">
                <i class="fa fa-check"></i> Verify & Complete Registration
            </button>
        </form>
        
        <form method="POST">
            <button type="submit" name="resend" class="btn btn-secondary">
                <i class="fa fa-paper-plane"></i> Resend Code
            </button>
        </form>
        
        <div class="cancel-link">
            <a href="cancel_registration.php"><i class="fa fa-times"></i> Cancel Registration</a>
        </div>
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
        
        // OTP Input Handling
        const inputs = document.querySelectorAll('.otp-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.replace(/[^0-9]/g, '');
                e.target.value = value;
                
                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                
                // Auto-submit when all filled
                const allFilled = Array.from(inputs).every(i => i.value.length === 1);
                if (allFilled) {
                    document.getElementById('otpCode').value = Array.from(inputs).map(i => i.value).join('');
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !input.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                pasted.split('').forEach((char, i) => {
                    if (inputs[i]) inputs[i].value = char;
                });
                if (pasted.length >= 6) {
                    document.getElementById('otpCode').value = pasted;
                    inputs[5].focus();
                }
            });
        });
        
        // Countdown Timer
        const countdown = document.getElementById('countdown');
        
        <?php
        $expiresAt = $_SESSION['otp_expires'] ?? null;
        if ($expiresAt) {
            $expiresTs = strtotime($expiresAt);
            $remaining = max(0, $expiresTs - time());
            echo "let remainingSeconds = {$remaining};";
        } else {
            echo "let remainingSeconds = 0;";
        }
        ?>
        
        function updateCountdown() {
            if (remainingSeconds <= 0) {
                countdown.textContent = 'Expired';
                countdown.style.color = 'var(--red)';
                return;
            }
            
            const mins = Math.floor(remainingSeconds / 60);
            const secs = remainingSeconds % 60;
            countdown.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
            remainingSeconds--;
        }
        
        setInterval(updateCountdown, 1000);
        updateCountdown();
        
        // Auto-submit form
        document.querySelector('form').addEventListener('submit', function(e) {
            const code = Array.from(inputs).map(i => i.value).join('');
            if (code.length === 6) {
                document.getElementById('otpCode').value = code;
            }
        });
    </script>
</body>
</html>
