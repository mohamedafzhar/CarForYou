<?php
session_start();
require_once('../includes/config.php');
date_default_timezone_set('Asia/Colombo');

date_default_timezone_set('Asia/Colombo');
mysqli_query($conn, "SET time_zone = '+05:30'");

if (isset($_SESSION['user_id'])) {
    header("Location: car_dashboard.php"); exit();
}

$token      = trim($_GET['token'] ?? '');
$valid      = false;
$user_email = '';
$success    = false;
$error_msg  = '';

// Validate token
if ($token) {
    $safe  = mysqli_real_escape_string($conn, $token);

    $res = mysqli_query($conn, "SELECT * FROM password_resets WHERE token='$safe' AND expires_at > NOW() LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $valid      = true;
        $user_email = $row['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $error_msg = "Password must be at least 6 characters.";
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = "Passwords do not match.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $safe_email = mysqli_real_escape_string($conn, $user_email);
        mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE email='$safe_email'");
        mysqli_query($conn, "DELETE FROM password_resets WHERE email='$safe_email'");
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    [data-theme="dark"] {
        --bg:#0b0e14; --surface:#141920; --surface2:#1a2030; --surface3:#1f2638;
        --border:rgba(255,255,255,0.06); --border2:rgba(255,255,255,0.1);
        --text:#f0f2f8; --text2:#8892a4; --text3:#44505e;
        --accent:#00d4ff; --accent2:#0090ff;
        --accentglow:rgba(0,212,255,0.18); --accentglow2:rgba(0,212,255,0.06);
        --green:#00e676; --greenbg:rgba(0,230,118,0.08); --greenglow:rgba(0,230,118,0.18);
        --red:#ff4f4f; --redbg:rgba(255,79,79,0.08);
        --shadow:0 0 0 1px rgba(0,212,255,0.08), 0 24px 60px rgba(0,0,0,0.6);
        --spot1:rgba(0,144,255,0.09); --spot2:rgba(0,212,255,0.07); --spot3:rgba(0,230,118,0.05);
        --grid-color:rgba(0,212,255,0.03);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --surface:#ffffff; --surface2:#f5f8fc; --surface3:#eaf0f8;
        --border:rgba(0,0,0,0.07); --border2:rgba(0,0,0,0.12);
        --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
        --accent:#0077cc; --accent2:#0055aa;
        --accentglow:rgba(0,119,204,0.18); --accentglow2:rgba(0,119,204,0.08);
        --green:#059669; --greenbg:rgba(5,150,105,0.08); --greenglow:rgba(5,150,105,0.18);
        --red:#dc2626; --redbg:rgba(220,38,38,0.07);
        --shadow:0 0 0 1px rgba(0,119,204,0.1), 0 24px 60px rgba(0,0,0,0.1);
        --spot1:rgba(0,119,204,0.06); --spot2:rgba(0,180,255,0.05); --spot3:rgba(5,150,105,0.04);
        --grid-color:rgba(0,119,204,0.04);
    }
    body {
        font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text);
        min-height:100vh; display:flex; align-items:center; justify-content:center;
        padding:44px 16px; position:relative; overflow-x:hidden;
        transition:background 0.35s, color 0.35s;
    }
    body::before {
        content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
        background:
            radial-gradient(ellipse 55% 45% at 20% 15%, var(--spot1) 0%, transparent 65%),
            radial-gradient(ellipse 50% 40% at 80% 85%, var(--spot2) 0%, transparent 60%),
            radial-gradient(ellipse 35% 30% at 75% 10%, var(--spot3) 0%, transparent 55%);
    }
    body::after {
        content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
        background-image: linear-gradient(var(--grid-color) 1px, transparent 1px), linear-gradient(90deg, var(--grid-color) 1px, transparent 1px);
        background-size:48px 48px;
        mask-image:radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
    }
    .theme-corner { position:fixed; top:18px; right:18px; z-index:200; }
    .theme-toggle {
        width:40px; height:40px; border-radius:11px; background:var(--surface2);
        border:1px solid var(--border2); color:var(--text2); cursor:pointer;
        display:flex; align-items:center; justify-content:center; font-size:0.92rem;
        transition:all 0.22s; box-shadow:0 2px 12px rgba(0,0,0,0.2);
    }
    .theme-toggle:hover { border-color:var(--accent); color:var(--accent); box-shadow:0 0 14px var(--accentglow); }
    .wrap { position:relative; z-index:10; width:100%; max-width:440px; }
    .brand-bar { text-align:center; margin-bottom:28px; animation:fadeDown 0.5s ease both; }
    .brand-bar a { text-decoration:none; display:inline-block; }
    .brand-logo {
        display:inline-flex; align-items:center; justify-content:center;
        width:54px; height:54px;
        background:linear-gradient(135deg, rgba(0,212,255,0.15), rgba(0,144,255,0.25));
        border:1px solid rgba(0,212,255,0.25); border-radius:16px; font-size:1.35rem;
        color:var(--accent); margin-bottom:12px;
        box-shadow:0 0 30px var(--accentglow), inset 0 1px 0 rgba(255,255,255,0.08);
    }
    .brand-bar h1 { font-size:1.65rem; font-weight:800; color:var(--text); letter-spacing:-0.03em; }
    .brand-bar h1 span { background:linear-gradient(90deg,var(--accent),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .brand-bar p { font-size:0.72rem; color:var(--text3); letter-spacing:0.14em; margin-top:4px; font-weight:500; }
    @keyframes fadeDown { from{opacity:0;transform:translateY(-12px)} to{opacity:1;transform:translateY(0)} }
    .card {
        background:var(--surface); border:1px solid var(--border2);
        border-radius:20px; box-shadow:var(--shadow); overflow:hidden;
        animation:cardUp 0.55s cubic-bezier(0.22,1,0.36,1) 0.08s both; position:relative;
    }
    .card::before {
        content:''; position:absolute; top:0; left:0; right:0; height:1px;
        background:linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent); opacity:0.6;
    }
    @keyframes cardUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
    .card-header {
        border-bottom:1px solid var(--border); padding:28px 28px 22px; text-align:center;
    }
    .header-icon {
        width:56px; height:56px; border-radius:16px; margin:0 auto 16px;
        display:flex; align-items:center; justify-content:center; font-size:1.3rem;
    }
    .card-header h2 { font-size:1.4rem; font-weight:800; color:var(--text); letter-spacing:-0.02em; margin-bottom:6px; }
    .card-header p  { font-size:0.82rem; color:var(--text2); line-height:1.5; }
    .card-body { padding:26px 28px 30px; }

    .alert {
        display:flex; align-items:flex-start; gap:9px;
        padding:12px 14px; border-radius:10px;
        font-size:0.82rem; font-weight:500; line-height:1.5;
        margin-bottom:18px; animation:alertIn 0.3s ease;
    }
    .alert i { flex-shrink:0; margin-top:1px; }
    @keyframes alertIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:translateY(0)} }
    .alert-error { background:var(--redbg); color:var(--red); border:1px solid rgba(255,79,79,0.2); }

    .form-group { margin-bottom:16px; }
    .form-group label {
        display:block; font-size:0.68rem; font-weight:700;
        letter-spacing:0.12em; text-transform:uppercase; color:var(--text3); margin-bottom:7px;
    }
    .input-wrap { position:relative; display:flex; align-items:center; }
    .input-wrap .fi { position:absolute; left:13px; color:var(--text3); font-size:0.78rem; pointer-events:none; z-index:2; transition:color 0.22s; }
    .input-wrap:focus-within .fi { color:var(--accent); }
    .form-control {
        width:100%; padding:13px 42px 13px 36px;
        background:var(--surface2); border:1px solid var(--border2);
        border-radius:10px; color:var(--text);
        font-family:'Outfit',sans-serif; font-size:0.875rem; outline:none;
        transition:border-color 0.22s, box-shadow 0.22s, background 0.22s;
    }
    .form-control::placeholder { color:var(--text3); }
    .form-control:focus {
        border-color:rgba(0,212,255,0.45); background:var(--surface3);
        box-shadow:0 0 0 3px var(--accentglow2), 0 0 12px rgba(0,212,255,0.08);
    }
    .eye-btn {
        position:absolute; right:0; width:38px; height:100%;
        display:flex; align-items:center; justify-content:center;
        color:var(--text3); background:none; border:none;
        border-radius:0 10px 10px 0; font-size:0.78rem;
        cursor:pointer; z-index:2; transition:color 0.2s;
    }
    .eye-btn:hover { color:var(--accent); }

    /* Strength bar */
    .strength-bar { display:flex; gap:4px; margin-top:8px; height:4px; }
    .strength-bar span { flex:1; border-radius:2px; background:var(--border2); transition:background 0.3s; }
    .strength-label { font-size:0.68rem; color:var(--text3); margin-top:5px; }

    .btn-submit {
        width:100%; padding:13px;
        background:linear-gradient(135deg, rgba(0,212,255,0.15), rgba(0,144,255,0.2));
        border:1px solid rgba(0,212,255,0.3); border-radius:12px; color:var(--accent);
        font-family:'Outfit',sans-serif; font-size:0.9rem; font-weight:700;
        letter-spacing:0.05em; text-transform:uppercase; cursor:pointer; margin-top:8px;
        display:flex; align-items:center; justify-content:center; gap:9px;
        transition:all 0.22s;
        box-shadow:0 0 20px var(--accentglow), inset 0 1px 0 rgba(255,255,255,0.06);
    }
    .btn-submit:hover { transform:translateY(-2px); box-shadow:0 0 35px var(--accentglow), 0 8px 24px rgba(0,0,0,0.3); border-color:rgba(0,212,255,0.55); }
    .btn-submit:active { transform:translateY(0); }
    .back-link-row { text-align:center; margin-top:18px; font-size:0.8rem; color:var(--text3); }
    .back-link-row a { color:var(--accent); font-weight:600; text-decoration:none; }
    .back-link-row a:hover { opacity:0.7; text-decoration:underline; text-underline-offset:3px; }
    .back-home { text-align:center; margin-top:18px; animation:fadeDown 0.5s ease 0.3s both; opacity:0; }
    .back-home a { display:inline-flex; align-items:center; gap:7px; font-size:0.76rem; color:var(--text3); text-decoration:none; font-weight:500; transition:color 0.2s; }
    .back-home a:hover { color:var(--accent); }

    /* Invalid token state */
    .invalid-state { text-align:center; padding:10px 0; }
    .invalid-icon {
        width:64px; height:64px; border-radius:50%; margin:0 auto 18px;
        background:var(--redbg); border:2px solid rgba(255,79,79,0.25);
        display:flex; align-items:center; justify-content:center;
        font-size:1.6rem; color:var(--red);
        box-shadow:0 0 24px rgba(255,79,79,0.15);
    }
    .invalid-state h3 { font-size:1.1rem; font-weight:800; color:var(--text); margin-bottom:8px; }
    .invalid-state p  { font-size:0.82rem; color:var(--text2); line-height:1.6; }

    /* Success state */
    .success-state { text-align:center; padding:10px 0 6px; }
    .success-icon {
        width:64px; height:64px; border-radius:50%; margin:0 auto 18px;
        background:var(--greenbg); border:2px solid rgba(0,230,118,0.25);
        display:flex; align-items:center; justify-content:center;
        font-size:1.6rem; color:var(--green);
        box-shadow:0 0 24px var(--greenglow);
        animation:popIn 0.4s cubic-bezier(0.22,1,0.36,1);
    }
    @keyframes popIn { from{transform:scale(0.5);opacity:0} to{transform:scale(1);opacity:1} }
    .success-state h3 { font-size:1.1rem; font-weight:800; color:var(--text); margin-bottom:8px; }
    .success-state p  { font-size:0.82rem; color:var(--text2); line-height:1.6; }
    .btn-login {
        display:inline-flex; align-items:center; gap:8px;
        margin-top:20px; padding:11px 24px; border-radius:10px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        color:#fff; font-family:'Outfit',sans-serif; font-size:0.82rem;
        font-weight:700; letter-spacing:0.04em; text-transform:uppercase;
        text-decoration:none; transition:all 0.22s;
        box-shadow:0 4px 16px var(--accentglow);
    }
    .btn-login:hover { opacity:0.88; transform:translateY(-1px); }
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

        <?php if ($success): ?>
        <!-- ── SUCCESS ── -->
        <div class="card-header" style="background:linear-gradient(135deg, rgba(0,230,118,0.06), transparent);">
            <div class="header-icon" style="background:var(--greenbg);border:1px solid rgba(0,230,118,0.22);color:var(--green);box-shadow:0 0 24px var(--greenglow);">
                <i class="fa fa-shield-halved"></i>
            </div>
            <h2>Password Updated</h2>
            <p>Your account is secured with your new password.</p>
        </div>
        <div class="card-body">
            <div class="success-state">
                <div class="success-icon"><i class="fa fa-check"></i></div>
                <h3>All Done!</h3>
                <p>Your password has been reset successfully. You can now sign in with your new password.</p>
                <a href="login.php" class="btn-login"><i class="fa fa-arrow-right-to-bracket"></i> Go to Sign In</a>
            </div>
        </div>

        <?php elseif (!$valid): ?>
        <!-- ── INVALID / EXPIRED TOKEN ── -->
        <div class="card-header">
            <div class="header-icon" style="background:var(--redbg);border:1px solid rgba(255,79,79,0.22);color:var(--red);box-shadow:0 0 24px rgba(255,79,79,0.15);">
                <i class="fa fa-triangle-exclamation"></i>
            </div>
            <h2>Link Expired</h2>
            <p>This reset link is invalid or has expired.</p>
        </div>
        <div class="card-body">
            <div class="invalid-state">
                <div class="invalid-icon"><i class="fa fa-clock-rotate-left"></i></div>
                <h3>Link No Longer Valid</h3>
                <p>Reset links expire after 1 hour for security. Please request a new one.</p>
                <a href="forgot_password.php" class="btn-login" style="background:linear-gradient(135deg,var(--red),#cc0000);box-shadow:0 4px 16px rgba(255,79,79,0.2);">
                    <i class="fa fa-rotate-right"></i> Request New Link
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- ── RESET FORM ── -->
        <div class="card-header" style="background:linear-gradient(135deg, rgba(0,212,255,0.06), transparent);">
            <div class="header-icon" style="background:linear-gradient(135deg,rgba(0,212,255,0.12),rgba(0,144,255,0.2));border:1px solid rgba(0,212,255,0.22);color:var(--accent);box-shadow:0 0 24px var(--accentglow);">
                <i class="fa fa-lock-open"></i>
            </div>
            <h2>Set New Password</h2>
            <p>Choose a strong password for <strong style="color:var(--accent)"><?php echo htmlspecialchars($user_email); ?></strong></p>
        </div>
        <div class="card-body">
            <?php if ($error_msg): ?>
            <div class="alert alert-error"><i class="fa fa-circle-xmark"></i><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-wrap">
                        <i class="fa fa-lock fi"></i>
                        <input type="password" name="new_password" id="newPwd" class="form-control"
                               placeholder="Min 6 characters" minlength="6" required oninput="checkStrength(this.value)">
                        <button type="button" class="eye-btn" id="eyeNew"><i class="fa fa-eye"></i></button>
                    </div>
                    <div class="strength-bar">
                        <span id="s1"></span><span id="s2"></span><span id="s3"></span><span id="s4"></span>
                    </div>
                    <div class="strength-label" id="strengthLabel">Enter a password</div>
                </div>
                <div class="form-group" style="margin-bottom:24px;">
                    <label>Confirm New Password</label>
                    <div class="input-wrap">
                        <i class="fa fa-check-double fi"></i>
                        <input type="password" name="confirm_password" id="confPwd" class="form-control"
                               placeholder="Repeat new password" minlength="6" required>
                        <button type="button" class="eye-btn" id="eyeConf"><i class="fa fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fa fa-shield-halved"></i> Reset Password
                </button>
            </form>
            <div class="back-link-row" style="margin-top:18px;">
                <a href="login.php"><i class="fa fa-arrow-left" style="margin-right:5px;"></i>Back to Sign In</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="back-home">
        <a href="../index.php"><i class="fa fa-arrow-left"></i> Back to homepage</a>
    </div>
</div>

<script>
    // Theme
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeToggle').addEventListener('click', function(){
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun'; }

    // Hold-to-reveal eye buttons
    function bindHold(btnId, inputId){
        var btn=document.getElementById(btnId), inp=document.getElementById(inputId);
        if(!btn||!inp) return;
        var icon=btn.querySelector('i');
        function show(){ inp.type='text'; icon.className='fa fa-eye-slash'; }
        function hide(){ inp.type='password'; icon.className='fa fa-eye'; }
        btn.addEventListener('mousedown',  function(e){e.preventDefault();show();});
        btn.addEventListener('mouseup',    hide);
        btn.addEventListener('mouseleave', hide);
        btn.addEventListener('touchstart', function(e){e.preventDefault();show();},{passive:false});
        btn.addEventListener('touchend',   hide);
    }
    bindHold('eyeNew',  'newPwd');
    bindHold('eyeConf', 'confPwd');

    // Password strength meter
    function checkStrength(v){
        var bars   = [document.getElementById('s1'),document.getElementById('s2'),document.getElementById('s3'),document.getElementById('s4')];
        var label  = document.getElementById('strengthLabel');
        var colors = ['#ff4f4f','#fbbf24','#00d4ff','#00e676'];
        var labels = ['Too short','Weak','Good','Strong'];
        var score  = 0;
        if(v.length >= 6)  score++;
        if(v.length >= 10) score++;
        if(/[A-Z]/.test(v) && /[0-9]/.test(v)) score++;
        if(/[^A-Za-z0-9]/.test(v)) score++;
        bars.forEach(function(b,i){ b.style.background = i < score ? colors[score-1] : 'var(--border2)'; });
        label.textContent  = v.length === 0 ? 'Enter a password' : labels[score-1] || labels[0];
        label.style.color  = v.length === 0 ? 'var(--text3)' : colors[score-1] || colors[0];
    }

    // Auto-redirect after success
    <?php if($success): ?>
    setTimeout(function(){ window.location.href = 'login.php'; }, 4000);
    <?php endif; ?>
</script>
</body>
</html>