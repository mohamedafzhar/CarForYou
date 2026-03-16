<?php
session_start();
include 'config.php';

$error = ""; $msg = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = md5(trim($_POST['password']));
    $stmt = $conn->prepare("SELECT id, username FROM admin WHERE username=? AND password=?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['alogin'] = $row['username'];
        $_SESSION['id']     = $row['id'];
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}

if (isset($_POST['reset_password'])) {
    $email            = trim($_POST['email']);
    $new_password     = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    if ($new_password !== $confirm_password) { $error = "Passwords do not match."; }
    elseif (strlen($new_password) < 6) { $error = "Password must be at least 6 characters."; }
    else {
        $stmt = $conn->prepare("SELECT id FROM admin WHERE email=?");
        $stmt->bind_param("s", $email); $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $hashed = md5($new_password);
            $u = $conn->prepare("UPDATE admin SET password=? WHERE email=?");
            $u->bind_param("ss", $hashed, $email);
            $msg = $u->execute() ? "Password updated successfully! You can now login." : "Something went wrong.";
        } else { $error = "Email address not found in our records."; }
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
        --bg:       #0d1117;
        --surface:  #1e2738;
        --surface2: #253044;
        --border:   rgba(99,155,255,0.1);
        --border2:  rgba(99,155,255,0.18);
        --text:     #e8edf5;
        --text2:    #7a93b0;
        --text3:    #3d5570;
        --accent:   #4f8ef7;
        --accent2:  #7db0fb;
        --glow:     rgba(79,142,247,0.25);
        --input-bg: #253044;
        --input-border: rgba(99,155,255,0.18);
        --overlay:  rgba(0,0,0,0.75);
        --grid-line:rgba(79,142,247,0.04);
    }
    [data-theme="light"] {
        --bg:       #f0f4f8;
        --surface:  #ffffff;
        --surface2: #f5f7fa;
        --border:   rgba(99,120,155,0.14);
        --border2:  rgba(99,120,155,0.24);
        --text:     #1c2b3a;
        --text2:    #4a607a;
        --text3:    #8fa3bb;
        --accent:   #2563eb;
        --accent2:  #3b82f6;
        --glow:     rgba(37,99,235,0.2);
        --input-bg: #ffffff;
        --input-border: rgba(99,120,155,0.3);
        --overlay:  rgba(13,17,23,0.65);
        --grid-line:rgba(37,99,235,0.04);
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { font-size:16px; }

    body {
        font-family:'DM Sans',sans-serif;
        background:var(--bg);
        color:var(--text);
        min-height:100vh;
        display:flex; align-items:center; justify-content:center;
        transition:background var(--tr), color var(--tr);
        position:relative; overflow:hidden;
    }

    /* Animated grid background */
    body::before {
        content:'';
        position:fixed; inset:0; z-index:0;
        background-image:
            linear-gradient(var(--grid-line) 1px, transparent 1px),
            linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
        background-size:40px 40px;
        animation:gridDrift 20s linear infinite;
    }
    @keyframes gridDrift { from{background-position:0 0} to{background-position:40px 40px} }

    /* Radial glow behind card */
    body::after {
        content:'';
        position:fixed; inset:0; z-index:0;
        background: radial-gradient(ellipse 60% 50% at 50% 50%, var(--glow), transparent 70%);
        pointer-events:none;
    }

    /* Theme toggle — top right */
    .theme-corner {
        position:fixed; top:20px; right:20px; z-index:100;
    }
    .theme-btn {
        width:38px; height:38px; border-radius:10px;
        border:1px solid var(--border2); background:var(--surface);
        color:var(--text2); cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        font-size:0.9rem; transition:all 0.2s;
    }
    .theme-btn:hover { border-color:var(--accent); color:var(--accent); box-shadow:0 0 12px var(--glow); }

    /* LOGIN CARD */
    .login-wrap {
        position:relative; z-index:10;
        width:100%; max-width:420px;
        padding:20px;
    }

    .login-card {
        background:var(--surface);
        border:1px solid var(--border2);
        border-radius:18px;
        padding:40px 36px;
        box-shadow:0 24px 60px rgba(0,0,0,0.3), 0 0 0 1px var(--border);
        animation:cardIn 0.55s cubic-bezier(0.34,1.2,0.64,1) forwards;
        opacity:0;
    }
    @keyframes cardIn { from{opacity:0;transform:translateY(30px) scale(0.96)} to{opacity:1;transform:translateY(0) scale(1)} }

    /* Brand */
    .brand { text-align:center; margin-bottom:32px; }
    .brand-logo {
        display:inline-flex; align-items:center; justify-content:center;
        width:56px; height:56px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:14px;
        font-size:1.4rem; color:#fff;
        margin-bottom:14px;
        box-shadow:0 6px 20px var(--glow);
    }
    .brand h1 {
        font-family:'Syne',sans-serif;
        font-size:1.55rem; font-weight:800;
        color:var(--text); letter-spacing:-0.01em;
    }
    .brand h1 span { color:var(--accent); }
    .brand p { font-size:0.8rem; color:var(--text3); margin-top:5px; letter-spacing:0.04em; }

    /* Divider line */
    .divider { height:1px; background:var(--border); margin-bottom:28px; }

    /* Alerts */
    .alert {
        display:flex; align-items:center; gap:9px;
        padding:12px 14px; border-radius:10px;
        font-size:0.83rem; font-weight:500;
        margin-bottom:20px;
        animation:fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .alert-error   { background:rgba(239,68,68,0.1);  color:#ef4444; border:1px solid rgba(239,68,68,0.22); }
    .alert-success { background:rgba(34,197,94,0.1);  color:#22c55e; border:1px solid rgba(34,197,94,0.22); }

    /* Form */
    .form-group { margin-bottom:16px; }
    .form-group label {
        display:block; margin-bottom:6px;
        font-size:0.72rem; font-weight:700;
        letter-spacing:0.1em; text-transform:uppercase;
        color:var(--text3);
    }

    .input-wrap { position:relative; display:flex; align-items:center; }
    .input-wrap .field-icon {
        position:absolute; left:13px;
        color:var(--text3); font-size:0.85rem; pointer-events:none;
        transition:color 0.2s; z-index:2;
    }
    .input-wrap:focus-within .field-icon { color:var(--accent); }
    .form-control {
        width:100%; padding:11px 42px 11px 38px;
        background:var(--input-bg);
        border:1px solid var(--input-border);
        border-radius:9px;
        color:var(--text);
        font-family:'DM Sans',sans-serif;
        font-size:0.88rem;
        outline:none;
        transition:border-color 0.2s, box-shadow 0.2s;
    }
    .form-control::placeholder { color:var(--text3); }
    .form-control:focus {
        border-color:var(--accent);
        box-shadow:0 0 0 3px var(--glow);
    }

    /* Password eye toggle — sits inside the input on the right */
    .pw-toggle {
        position:absolute; right:0;
        width:42px; height:100%;
        display:flex; align-items:center; justify-content:center;
        color:var(--text3); cursor:pointer; font-size:0.85rem;
        transition:color 0.2s; background:none; border:none;
        border-radius:0 9px 9px 0;
        z-index:2;
    }
    .pw-toggle:hover { color:var(--accent); }

    /* Login button */
    .btn-login {
        width:100%; padding:12px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border:none; border-radius:10px;
        color:#fff; font-family:'DM Sans',sans-serif;
        font-size:0.9rem; font-weight:700;
        cursor:pointer; margin-top:6px;
        display:flex; align-items:center; justify-content:center; gap:8px;
        transition:all 0.22s;
        box-shadow:0 4px 16px var(--glow);
    }
    .btn-login:hover { opacity:0.88; transform:translateY(-2px); box-shadow:0 8px 24px var(--glow); }
    .btn-login:active { transform:translateY(0); }

    /* Forgot link */
    .forgot-wrap { text-align:center; margin-top:18px; }
    .forgot-link {
        display:inline-flex; align-items:center; gap:6px;
        font-size:0.8rem; color:var(--text3);
        cursor:pointer; transition:color 0.2s;
        background:none; border:none; font-family:'DM Sans',sans-serif;
    }
    .forgot-link:hover { color:var(--accent); }

    /* Footer note */
    .login-footer {
        text-align:center; margin-top:20px;
        font-size:0.72rem; color:var(--text3);
        letter-spacing:0.04em;
    }

    /* ── MODAL ── */
    .modal-overlay {
        display:none; position:fixed; inset:0;
        background:var(--overlay);
        backdrop-filter:blur(6px);
        z-index:500; align-items:center; justify-content:center;
        padding:20px;
    }
    .modal-overlay.open { display:flex; }

    .modal {
        background:var(--surface);
        border:1px solid var(--border2);
        border-radius:16px; padding:30px;
        width:100%; max-width:400px;
        position:relative;
        box-shadow:0 24px 60px rgba(0,0,0,0.4);
        animation:modalIn 0.3s cubic-bezier(0.34,1.4,0.64,1) forwards;
    }
    @keyframes modalIn { from{opacity:0;transform:scale(0.92) translateY(16px)} to{opacity:1;transform:scale(1) translateY(0)} }

    .modal h3 {
        font-family:'Syne',sans-serif;
        font-size:1rem; font-weight:700;
        color:var(--text); margin-bottom:20px;
        display:flex; align-items:center; gap:9px;
    }
    .modal h3 i { color:var(--accent); font-size:0.88rem; }

    .modal-close {
        position:absolute; top:14px; right:14px;
        background:var(--surface2); border:1px solid var(--border);
        border-radius:8px; width:30px; height:30px;
        cursor:pointer; font-size:0.85rem; color:var(--text2);
        display:flex; align-items:center; justify-content:center;
        transition:all 0.2s;
    }
    .modal-close:hover { background:var(--border2); color:var(--text); }

    .btn-reset {
        width:100%; padding:11px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border:none; border-radius:9px;
        color:#fff; font-family:'DM Sans',sans-serif;
        font-size:0.88rem; font-weight:700;
        cursor:pointer; margin-top:4px;
        display:flex; align-items:center; justify-content:center; gap:8px;
        transition:all 0.2s;
        box-shadow:0 3px 12px var(--glow);
    }
    .btn-reset:hover { opacity:0.88; transform:translateY(-1px); }
    </style>
</head>
<body>

<!-- Theme toggle -->
<div class="theme-corner">
    <button class="theme-btn" id="themeBtn" title="Toggle Theme">
        <i class="fa fa-moon" id="themeIcon"></i>
    </button>
</div>

<!-- Login Card -->
<div class="login-wrap">
    <div class="login-card">

        <div class="brand">
            <div class="brand-logo"><i class="fa fa-car-side"></i></div>
            <h1>Car<span>ForYou</span></h1>
            <p>ADMIN CONSOLE &nbsp;·&nbsp; SIGN IN TO CONTINUE</p>
        </div>

        <div class="divider"></div>

        <?php if ($error && !isset($_POST['reset_password'])): ?>
            <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
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
                <i class="fa fa-arrow-right-to-bracket"></i> Sign In to Dashboard
            </button>
        </form>

        <div class="forgot-wrap">
            <button class="forgot-link" onclick="document.getElementById('forgotModal').classList.add('open')">
                <i class="fa fa-key"></i> Forgot Password?
            </button>
        </div>

        <div class="login-footer">Secure admin area — unauthorised access is prohibited</div>
    </div>
</div>

<!-- MODAL: Forgot Password -->
<div class="modal-overlay" id="forgotModal">
    <div class="modal">
        <button class="modal-close" onclick="document.getElementById('forgotModal').classList.remove('open')">
            <i class="fa fa-xmark"></i>
        </button>
        <h3><i class="fa fa-lock-open"></i> Reset Admin Password</h3>

        <?php if ($error && isset($_POST['reset_password'])): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Registered Email</label>
                <div class="input-wrap">
                    <i class="fa fa-envelope field-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
                </div>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <div class="input-wrap">
                    <i class="fa fa-lock field-icon"></i>
                    <input type="password" name="new_password" id="resetPwd1" class="form-control" placeholder="Minimum 6 characters" required>
                    <button type="button" class="pw-toggle" id="eyeResetPwd1" tabindex="-1"><i class="fa fa-eye"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <i class="fa fa-lock field-icon"></i>
                    <input type="password" name="confirm_password" id="resetPwd2" class="form-control" placeholder="Repeat password" required>
                    <button type="button" class="pw-toggle" id="eyeResetPwd2" tabindex="-1"><i class="fa fa-eye"></i></button>
                </div>
            </div>
            <div id="pwMatchErr" style="display:none;font-size:0.78rem;color:#ef4444;margin-bottom:10px;">
                <i class="fa fa-triangle-exclamation"></i> Passwords do not match.
            </div>
            <button type="submit" name="reset_password" class="btn-reset" id="resetBtn">
                <i class="fa fa-floppy-disk"></i> Update Password
            </button>
        </form>
    </div>
</div>

<script>
    // Theme
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

    // Password visibility — hold to show, release to hide
    function bindHoldPwd(btnId, inputId) {
        var btn = document.getElementById(btnId);
        var inp = document.getElementById(inputId);
        var icon = btn.querySelector('i');

        function show() { inp.type = 'text';     icon.className = 'fa fa-eye-slash'; }
        function hide() { inp.type = 'password'; icon.className = 'fa fa-eye'; }

        btn.addEventListener('mousedown',   function(e){ e.preventDefault(); show(); });
        btn.addEventListener('mouseup',     hide);
        btn.addEventListener('mouseleave',  hide);
        btn.addEventListener('touchstart',  function(e){ e.preventDefault(); show(); }, {passive:false});
        btn.addEventListener('touchend',    hide);
        btn.addEventListener('touchcancel', hide);
    }

    bindHoldPwd('eyeLoginPwd', 'loginPwd');
    bindHoldPwd('eyeResetPwd1', 'resetPwd1');
    bindHoldPwd('eyeResetPwd2', 'resetPwd2');

    // Password match check on reset form
    document.querySelector('#forgotModal form').addEventListener('submit', function(e){
        var p1 = document.getElementById('resetPwd1').value;
        var p2 = document.getElementById('resetPwd2').value;
        var err = document.getElementById('pwMatchErr');
        if(p1 !== p2){ e.preventDefault(); err.style.display='flex'; err.style.alignItems='center'; err.style.gap='6px'; }
        else { err.style.display='none'; }
    });

    // Close modal on outside click
    document.getElementById('forgotModal').addEventListener('click', function(e){
        if(e.target===this) this.classList.remove('open');
    });

    // Auto-open modal if reset was submitted
    <?php if (isset($_POST['reset_password'])): ?>
    document.getElementById('forgotModal').classList.add('open');
    <?php endif; ?>
</script>
</body>
</html>