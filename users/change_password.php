<?php
session_start();
require_once('../includes/config.php');
userAuth();

$user_id = $_SESSION['user_id'];

// Fetch full user record (same as profile.php)
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    session_destroy();
    header("Location: login.php?error=Account no longer exists");
    exit();
}

$user_name  = $user['full_name'] ?? ($user['name'] ?? 'User');
$user_email = $user['email'] ?? 'Not provided';
$user_role  = $user['role'] ?? 'Member';
$initial    = strtoupper(substr($user_name, 0, 1));

$success_msg = "";
$error_msg   = "";

if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $error_msg = "New password and Confirm password do not match.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res  = mysqli_stmt_get_result($stmt);
        $row  = mysqli_fetch_assoc($res);

        if ($row) {
            if (password_verify($current_pass, $row['password']) || md5($current_pass) == $row['password']) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $upd    = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, "si", $hashed, $user_id);
                if (mysqli_stmt_execute($upd)) {
                    $success_msg = "Password changed successfully!";
                } else {
                    $error_msg = "Error updating password. Please try again.";
                }
            } else {
                $error_msg = "Current password is incorrect.";
            }
        } else {
            $error_msg = "User session invalid.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    [data-theme="dark"] {
        --bg:#0b0e14; --bg2:#0f1319;
        --surface:#141920; --surface2:#1a2030;
        --border:rgba(255,255,255,0.06); --border2:rgba(255,255,255,0.1);
        --text:#f0f2f8; --text2:#8892a4; --text3:#44505e;
        --accent:#00d4ff; --accent2:#0090ff;
        --accentglow:rgba(0,212,255,0.18); --accentbg:rgba(0,212,255,0.06);
        --green:#00e676; --greenbg:rgba(0,230,118,0.08);
        --red:#ff4f4f; --redbg:rgba(255,79,79,0.08);
        --shadow:0 4px 24px rgba(0,0,0,0.4);
        --sbg:#0a0d12; --sborder:rgba(255,255,255,0.05);
        --hbg:rgba(11,14,20,0.88);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --bg2:#e6ecf3;
        --surface:#ffffff; --surface2:#f5f8fc;
        --border:rgba(0,0,0,0.07); --border2:rgba(0,0,0,0.12);
        --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
        --accent:#0077cc; --accent2:#0055aa;
        --accentglow:rgba(0,119,204,0.16); --accentbg:rgba(0,119,204,0.07);
        --green:#059669; --greenbg:rgba(5,150,105,0.08);
        --red:#dc2626; --redbg:rgba(220,38,38,0.07);
        --shadow:0 4px 20px rgba(0,0,0,0.08);
        --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06);
        --hbg:rgba(240,244,248,0.9);
    }

    body {
        font-family:'Outfit',sans-serif;
        background:var(--bg); color:var(--text);
        display:flex; min-height:100vh;
        transition:background 0.35s, color 0.35s;
    }
    ::-webkit-scrollbar { width:4px; }
    ::-webkit-scrollbar-track { background:var(--bg); }
    ::-webkit-scrollbar-thumb { background:var(--accent); border-radius:4px; }
    a { text-decoration:none; color:inherit; }

    /* SIDEBAR */
    .sidebar {
        width:240px; min-height:100vh; background:var(--sbg);
        border-right:1px solid var(--sborder);
        position:fixed; top:0; left:0; bottom:0;
        display:flex; flex-direction:column;
        z-index:100; overflow-y:auto; transition:background 0.35s;
    }
    .sb-brand { padding:26px 22px 18px; border-bottom:1px solid var(--sborder); }
    .sb-brand a { display:flex; align-items:center; gap:10px; }
    .sb-logo {
        width:34px; height:34px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:9px; display:flex; align-items:center; justify-content:center;
        font-size:0.88rem; color:#fff; box-shadow:0 0 14px var(--accentglow); flex-shrink:0;
    }
    .sb-brand-text { font-size:1.1rem; font-weight:800; color:#e8edf5; letter-spacing:-0.02em; }
    .sb-brand-text span { color:var(--accent); }
    .sb-section { font-size:0.6rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:rgba(232,237,245,0.22); padding:20px 22px 6px; }
    .sb-nav { list-style:none; padding:6px 10px; }
    .sb-nav li { margin-bottom:2px; }
    .sb-nav a {
        display:flex; align-items:center; gap:10px;
        padding:10px 12px; border-radius:9px;
        font-size:0.85rem; font-weight:500;
        color:rgba(232,237,245,0.45); transition:all 0.2s;
    }
    .sb-nav a i { width:16px; text-align:center; font-size:0.82rem; }
    .sb-nav a:hover { background:rgba(0,212,255,0.07); color:rgba(232,237,245,0.85); }
    .sb-nav a.active {
        background:linear-gradient(90deg,rgba(0,212,255,0.15),rgba(0,212,255,0.04));
        color:var(--accent); font-weight:600; box-shadow:inset 3px 0 0 var(--accent);
    }
    .sb-nav a.logout { color:rgba(255,79,79,0.6); }
    .sb-nav a.logout:hover { background:rgba(255,79,79,0.08); color:#ff4f4f; }
    .sb-divider { height:1px; background:var(--sborder); margin:10px 2px; }
    .sb-user-card {
        margin:10px; padding:14px;
        background:var(--surface); border:1px solid var(--border); border-radius:12px;
    }
    .sb-user-card .uav {
        width:36px; height:36px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:9px; display:flex; align-items:center; justify-content:center;
        font-size:0.9rem; font-weight:800; color:#fff; margin-bottom:10px;
        box-shadow:0 0 12px var(--accentglow);
    }
    .sb-user-card .uname { font-size:0.82rem; font-weight:700; color:var(--text); }
    .sb-user-card .urole { font-size:0.68rem; color:var(--text3); margin-top:2px; }

    /* MAIN */
    .main { margin-left:240px; width:calc(100% - 240px); min-height:100vh; display:flex; flex-direction:column; }

    /* TOPBAR */
    .top-bar {
        position:sticky; top:0; z-index:50;
        background:var(--hbg); backdrop-filter:blur(16px);
        border-bottom:1px solid var(--border);
        padding:0 32px; height:64px;
        display:flex; align-items:center; justify-content:space-between;
        transition:background 0.35s;
    }
    .tb-left h2 { font-size:1.05rem; font-weight:700; color:var(--text); letter-spacing:-0.02em; }
    .tb-left p  { font-size:0.72rem; color:var(--text2); margin-top:1px; }
    .tb-right { display:flex; align-items:center; gap:10px; }
    .theme-btn {
        width:36px; height:36px; border-radius:9px;
        border:1px solid var(--border2); background:var(--surface);
        color:var(--text2); cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        font-size:0.85rem; transition:all 0.2s;
    }
    .theme-btn:hover { border-color:var(--accent); color:var(--accent); box-shadow:0 0 10px var(--accentglow); }
    .tb-avatar {
        width:34px; height:34px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:9px; display:flex; align-items:center; justify-content:center;
        font-size:0.82rem; font-weight:800; color:#fff;
        text-decoration:none; box-shadow:0 0 12px var(--accentglow);
        /* Matches profile.php exactly — is an <a> linking to profile */
    }

    /* BODY */
    .body { padding:28px 32px; flex:1; }

    /* PAGE WRAP */
    .page-wrap {
        max-width:520px; margin:0 auto;
        opacity:0; animation:fadeUp 0.5s ease 0.05s forwards;
    }

    /* INFO CARD */
    .info-card {
        background:var(--surface); border:1px solid var(--border);
        border-radius:16px; overflow:hidden;
    }
    .card-head {
        padding:18px 24px; border-bottom:1px solid var(--border);
        display:flex; align-items:center; justify-content:space-between;
        position:relative;
    }
    .card-head::after {
        content:''; position:absolute; top:0; left:0; right:0; height:3px;
        background:linear-gradient(90deg,var(--accent),var(--accent2));
    }
    .card-head h3 {
        font-size:0.9rem; font-weight:700; color:var(--text);
        display:flex; align-items:center; gap:8px;
    }
    .card-head h3 i { color:var(--accent); font-size:0.82rem; }
    .back-link {
        display:inline-flex; align-items:center; gap:6px;
        padding:6px 14px; border-radius:8px;
        font-size:0.74rem; font-weight:700; letter-spacing:0.04em; text-transform:uppercase;
        border:1px solid var(--border2); color:var(--text2); transition:all 0.2s;
    }
    .back-link:hover { border-color:var(--accent); color:var(--accent); background:var(--accentbg); }

    /* FORM */
    .card-body { padding:28px 24px; }
    .form-group { margin-bottom:20px; }
    .field-label {
        font-size:0.62rem; font-weight:700;
        letter-spacing:0.14em; text-transform:uppercase; color:var(--text3);
        display:block; margin-bottom:8px;
    }
    .input-wrap { position:relative; }
    .input-wrap i.icon-left {
        position:absolute; left:14px; top:50%; transform:translateY(-50%);
        color:var(--accent); font-size:0.78rem; width:14px;
    }
    .input-wrap input {
        width:100%; padding:12px 40px 12px 38px;
        background:var(--surface2); border:1px solid var(--border);
        border-radius:10px; font-family:'Outfit',sans-serif;
        font-size:0.875rem; font-weight:500; color:var(--text);
        transition:border-color 0.2s, box-shadow 0.2s; outline:none;
    }
    .input-wrap input::placeholder { color:var(--text3); font-weight:400; }
    .input-wrap input:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accentglow); }
    .input-wrap .toggle-pw {
        position:absolute; right:14px; top:50%; transform:translateY(-50%);
        color:var(--text3); font-size:0.78rem; cursor:pointer; transition:color 0.2s;
    }
    .input-wrap .toggle-pw:hover { color:var(--accent); }

    .divider { height:1px; background:var(--border); margin:24px 0; }

    .submit-btn {
        width:100%; padding:13px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        color:#fff; border:none; border-radius:10px;
        font-family:'Outfit',sans-serif; font-size:0.85rem; font-weight:700;
        letter-spacing:0.06em; text-transform:uppercase;
        cursor:pointer; transition:all 0.22s;
        box-shadow:0 4px 16px var(--accentglow);
        display:flex; align-items:center; justify-content:center; gap:8px;
    }
    .submit-btn:hover { opacity:0.88; transform:translateY(-1px); }
    .submit-btn:active { transform:scale(0.98); }

    /* ALERTS */
    .alert {
        display:flex; align-items:center; gap:12px;
        padding:14px 16px; border-radius:12px;
        margin-bottom:22px; font-size:0.82rem; font-weight:600;
    }
    .alert i { font-size:1rem; flex-shrink:0; }
    .alert-success { background:var(--greenbg); border:1px solid rgba(0,230,118,0.18); color:var(--green); }
    .alert-error   { background:var(--redbg);   border:1px solid rgba(255,79,79,0.18);  color:var(--red); }

    .foot-note { text-align:center; margin-top:20px; font-size:0.76rem; color:var(--text3); }
    .foot-note a { color:var(--accent); font-weight:700; }
    .foot-note a:hover { text-decoration:underline; }

    @keyframes fadeUp {
        from { opacity:0; transform:translateY(14px); }
        to   { opacity:1; transform:translateY(0); }
    }
    @media(max-width:640px) {
        .main { margin-left:0; width:100%; }
        .sidebar { display:none; }
        .body { padding:20px; }
    }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sb-brand">
        <a href="../index.php">
            <div class="sb-logo"><i class="fa fa-car-side"></i></div>
            <span class="sb-brand-text">Car<span>ForYou</span></span>
        </a>
    </div>
    <div class="sb-section">Menu</div>
    <ul class="sb-nav">
        <li><a href="car_dashboard.php"><i class="fa fa-table-columns"></i> Dashboard</a></li>
        <li><a href="user_booking.php"><i class="fa fa-calendar-check"></i> My Bookings</a></li>
        <li><a href="profile.php"><i class="fa fa-user"></i> Profile</a></li>
        <li><a href="change_password.php" class="active"><i class="fa fa-key"></i> Change Password</a></li>
        <li class="sb-divider"></li>
        <li><a href="../index.php"><i class="fa fa-arrow-left"></i> Back to Site</a></li>
        <li><a href="logout.php" class="logout"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
    <div style="flex:1;"></div>
    <div style="padding:12px;">
        <div class="sb-user-card">
            <!-- Real name and initial from DB, same as profile.php -->
            <div class="uav"><?php echo $initial; ?></div>
            <div class="uname"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="urole"><?php echo strtoupper(htmlspecialchars($user_role)); ?></div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<div class="main">
    <div class="top-bar">
        <div class="tb-left">
            <h2>Change Password</h2>
            <p id="dateLabel"></p>
        </div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn" title="Toggle theme">
                <i class="fa fa-moon" id="themeIcon"></i>
            </button>
            <!-- Same as profile.php: <a> linking to profile.php showing real $initial -->
            <a href="profile.php" class="tb-avatar"><?php echo $initial; ?></a>
        </div>
    </div>

    <div class="body">
        <div class="page-wrap">

            <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i>
                <span><?php echo $success_msg; ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <i class="fa fa-exclamation-circle"></i>
                <span><?php echo $error_msg; ?></span>
            </div>
            <?php endif; ?>

            <div class="info-card">
                <div class="card-head">
                    <h3><i class="fa fa-lock"></i> Security Settings</h3>
                    <a href="profile.php" class="back-link"><i class="fa fa-arrow-left"></i> Profile</a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="field-label">Current Password</label>
                            <div class="input-wrap">
                                <i class="fa fa-lock icon-left"></i>
                                <input type="password" name="current_password" placeholder="Enter current password" required>
                                <i class="fa fa-eye toggle-pw" onclick="togglePw(this)"></i>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="form-group">
                            <label class="field-label">New Password</label>
                            <div class="input-wrap">
                                <i class="fa fa-key icon-left"></i>
                                <input type="password" name="new_password" placeholder="Enter new password" minlength="6" required>
                                <i class="fa fa-eye toggle-pw" onclick="togglePw(this)"></i>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:28px;">
                            <label class="field-label">Confirm New Password</label>
                            <div class="input-wrap">
                                <i class="fa fa-check-double icon-left"></i>
                                <input type="password" name="confirm_password" placeholder="Repeat new password" minlength="6" required>
                                <i class="fa fa-eye toggle-pw" onclick="togglePw(this)"></i>
                            </div>
                        </div>

                        <button type="submit" name="change_password" class="submit-btn">
                            <i class="fa fa-shield-halved"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <p class="foot-note">
                Forgot your password? <a href="#">Contact Support</a>
            </p>
        </div>
    </div>
</div>

<script>
    // Live date — identical to profile.php
    (function(){
        var d=new Date(), days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('dateLabel').textContent = days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear();
    })();

    // Theme — same cfyTheme key shared across all pages
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun'; }

    // Toggle password visibility — finds sibling input of the eye icon
    function togglePw(icon) {
        var input = icon.closest('.input-wrap').querySelector('input');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'password' ? 'fa fa-eye toggle-pw' : 'fa fa-eye-slash toggle-pw';
    }
</script>
</body>
</html>