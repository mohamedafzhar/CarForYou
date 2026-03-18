<?php
session_start();
require_once('../includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=Please login first");
    exit();
}

$user_id     = $_SESSION['user_id'];
$user_name_s = $_SESSION['user_name'] ?? $_SESSION['fname'] ?? 'User';
$initial     = strtoupper(substr($user_name_s, 0, 1));
$success_msg = "";
$error_msg   = "";

// Fetch user data
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);

if (!$user) {
    session_destroy();
    header("Location: login.php?error=Account no longer exists");
    exit();
}

$columns = [];
$columns['name']  = isset($user['full_name'])  ? 'full_name'  : null;
$columns['email'] = isset($user['email'])      ? 'email'      : null;
$columns['phone'] = isset($user['contact_no']) ? 'contact_no' : null;

// Handle form submission
if (isset($_POST['update_profile'])) {
    $params     = [];
    $types      = "";
    $set_parts  = [];

    if ($columns['name'])  { $params[] = mysqli_real_escape_string($conn, $_POST['name']);  $types .= "s"; $set_parts[] = $columns['name']." = ?"; }
    if ($columns['email']) { $params[] = mysqli_real_escape_string($conn, $_POST['email']); $types .= "s"; $set_parts[] = $columns['email']." = ?"; }
    if ($columns['phone']) { $params[] = mysqli_real_escape_string($conn, $_POST['phone']); $types .= "s"; $set_parts[] = $columns['phone']." = ?"; }

    $params[] = $user_id;
    $types   .= "i";

    $update_query = "UPDATE users SET ".implode(", ", $set_parts)." WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "Profile updated successfully!";
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
    } else {
        $error_msg = "Update failed: " . mysqli_error($conn);
    }
}

$user_name  = $columns['name']  ? $user[$columns['name']]  : '';
$user_email = $columns['email'] ? $user[$columns['email']] : '';
$user_phone = $columns['phone'] ? $user[$columns['phone']] : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { font-size:16px; }

    [data-theme="dark"] {
        --bg:         #0b0e14;
        --surface:    #141920;
        --surface2:   #1a2030;
        --surface3:   #1f2638;
        --border:     rgba(255,255,255,0.06);
        --border2:    rgba(255,255,255,0.1);
        --text:       #f0f2f8;
        --text2:      #8892a4;
        --text3:      #44505e;
        --accent:     #00d4ff;
        --accent2:    #0090ff;
        --accentglow: rgba(0,212,255,0.18);
        --accentbg:   rgba(0,212,255,0.06);
        --green:      #00e676;
        --greenbg:    rgba(0,230,118,0.08);
        --red:        #ff4f4f;
        --redbg:      rgba(255,79,79,0.08);
        --sbg:        #0a0d12;
        --sborder:    rgba(255,255,255,0.05);
        --hbg:        rgba(11,14,20,0.88);
    }
    [data-theme="light"] {
        --bg:         #f0f4f8;
        --surface:    #ffffff;
        --surface2:   #f5f8fc;
        --surface3:   #eaf0f8;
        --border:     rgba(0,0,0,0.07);
        --border2:    rgba(0,0,0,0.12);
        --text:       #0f1923;
        --text2:      #4a5568;
        --text3:      #94a3b8;
        --accent:     #0077cc;
        --accent2:    #0055aa;
        --accentglow: rgba(0,119,204,0.16);
        --accentbg:   rgba(0,119,204,0.07);
        --green:      #059669;
        --greenbg:    rgba(5,150,105,0.08);
        --red:        #dc2626;
        --redbg:      rgba(220,38,38,0.07);
        --sbg:        #1c2b3a;
        --sborder:    rgba(255,255,255,0.06);
        --hbg:        rgba(240,244,248,0.9);
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

    /* ── SIDEBAR ── */
    .sidebar {
        width:240px; min-height:100vh; background:var(--sbg);
        border-right:1px solid var(--sborder);
        position:fixed; top:0; left:0; bottom:0;
        display:flex; flex-direction:column;
        z-index:100; overflow-y:auto; transition:background 0.35s;
    }
    .sb-brand { padding:26px 22px 18px; border-bottom:1px solid var(--sborder); }
    .sb-brand a { text-decoration:none; display:flex; align-items:center; gap:10px; }
    .sb-logo {
        width:34px; height:34px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:9px; display:flex; align-items:center; justify-content:center;
        font-size:0.88rem; color:#fff; box-shadow:0 0 14px var(--accentglow); flex-shrink:0;
    }
    .sb-brand-text { font-size:1.1rem; font-weight:800; color:#e8edf5; letter-spacing:-0.02em; }
    .sb-brand-text span { color:var(--accent); }
    .sb-section {
        font-size:0.6rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase;
        color:rgba(232,237,245,0.22); padding:20px 22px 6px;
    }
    .sb-nav { list-style:none; padding:6px 10px; }
    .sb-nav li { margin-bottom:2px; }
    .sb-nav a {
        display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:9px;
        font-size:0.85rem; font-weight:500; color:rgba(232,237,245,0.45);
        text-decoration:none; transition:all 0.2s;
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
        margin:10px; padding:14px; background:var(--surface);
        border:1px solid var(--border); border-radius:12px;
    }
    .sb-user-card .uav {
        width:36px; height:36px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:9px; display:flex; align-items:center; justify-content:center;
        font-size:0.9rem; font-weight:800; color:#fff; margin-bottom:10px;
        box-shadow:0 0 12px var(--accentglow);
    }
    .sb-user-card .uname { font-size:0.82rem; font-weight:700; color:var(--text); }
    .sb-user-card .urole { font-size:0.68rem; color:var(--text3); margin-top:2px; letter-spacing:0.04em; }

    /* ── MAIN ── */
    .main { margin-left:240px; width:calc(100% - 240px); min-height:100vh; display:flex; flex-direction:column; }

    /* ── TOPBAR ── */
    .top-bar {
        position:sticky; top:0; z-index:50;
        background:var(--hbg); backdrop-filter:blur(16px);
        border-bottom:1px solid var(--border); padding:0 32px; height:64px;
        display:flex; align-items:center; justify-content:space-between;
        transition:background 0.35s;
    }
    .tb-left h2 { font-size:1.05rem; font-weight:700; color:var(--text); letter-spacing:-0.02em; }
    .tb-left p  { font-size:0.72rem; color:var(--text2); margin-top:1px; }
    .tb-right   { display:flex; align-items:center; gap:10px; }
    .theme-btn {
        width:36px; height:36px; border-radius:9px;
        border:1px solid var(--border2); background:var(--surface); color:var(--text2);
        cursor:pointer; display:flex; align-items:center; justify-content:center;
        font-size:0.85rem; transition:all 0.2s;
    }
    .theme-btn:hover { border-color:var(--accent); color:var(--accent); box-shadow:0 0 10px var(--accentglow); }
    .tb-avatar {
        width:34px; height:34px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:9px; display:flex; align-items:center; justify-content:center;
        font-size:0.82rem; font-weight:800; color:#fff; text-decoration:none;
        box-shadow:0 0 12px var(--accentglow);
    }
    .back-btn {
        display:inline-flex; align-items:center; gap:7px;
        font-size:0.78rem; font-weight:600; color:var(--text2);
        border:1px solid var(--border2); border-radius:8px; padding:7px 14px;
        text-decoration:none; transition:all 0.2s;
    }
    .back-btn:hover { border-color:var(--accent); color:var(--accent); }

    /* ── BODY ── */
    .body { padding:26px 32px; flex:1; }

    /* ── PAGE HEADER ── */
    .page-header {
        background:var(--surface); border:1px solid var(--border); border-radius:16px;
        padding:22px 26px; margin-bottom:22px;
        display:flex; align-items:center; justify-content:space-between;
        position:relative; overflow:hidden;
        opacity:0; animation:fadeUp 0.5s ease 0.05s forwards;
    }
    .page-header::before {
        content:''; position:absolute; top:0; left:0; right:0; height:2px;
        background:linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent);
        opacity:0.5;
    }
    .page-header::after {
        content:''; position:absolute; right:-30px; top:-30px; width:160px; height:160px;
        background:radial-gradient(circle, var(--accentglow), transparent 70%); pointer-events:none;
    }
    .ph-text h2 { font-size:1.3rem; font-weight:800; color:var(--text); letter-spacing:-0.02em; }
    .ph-text h2 span {
        background:linear-gradient(90deg,var(--accent),var(--accent2));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }
    .ph-text p { font-size:0.82rem; color:var(--text2); margin-top:4px; }

    /* ── ALERTS ── */
    .alert {
        display:flex; align-items:center; gap:10px;
        padding:13px 16px; border-radius:10px; margin-bottom:20px;
        font-size:0.85rem; font-weight:500;
        opacity:0; animation:fadeUp 0.4s ease forwards;
    }
    .alert i { font-size:1rem; flex-shrink:0; }
    .alert-success { background:var(--greenbg); border:1px solid rgba(0,230,118,0.2); color:var(--green); }
    .alert-error   { background:var(--redbg);   border:1px solid rgba(255,79,79,0.2);   color:var(--red); }

    /* ── FORM CARD ── */
    .form-card {
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        overflow:hidden; max-width:600px;
        opacity:0; animation:fadeUp 0.5s ease 0.1s forwards;
    }
    .form-card-head {
        padding:18px 24px; border-bottom:1px solid var(--border);
        display:flex; align-items:center; gap:9px;
        font-size:0.9rem; font-weight:700; color:var(--text);
    }
    .form-card-head i { color:var(--accent); font-size:0.85rem; }
    .form-body { padding:24px; }

    /* ── AVATAR BLOCK ── */
    .avatar-block {
        display:flex; align-items:center; gap:18px;
        padding:20px 24px; background:var(--surface2);
        border-bottom:1px solid var(--border);
    }
    .avatar-circle {
        width:64px; height:64px; border-radius:16px; flex-shrink:0;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        display:flex; align-items:center; justify-content:center;
        font-size:1.6rem; font-weight:800; color:#fff;
        box-shadow:0 0 20px var(--accentglow);
    }
    .avatar-info h3 { font-size:1rem; font-weight:700; color:var(--text); }
    .avatar-info p  { font-size:0.75rem; color:var(--text3); margin-top:3px; }

    /* ── FIELDS ── */
    .field { margin-bottom:18px; }
    .field:last-of-type { margin-bottom:0; }
    .field label {
        display:block; font-size:0.65rem; font-weight:700;
        letter-spacing:0.12em; text-transform:uppercase;
        color:var(--text3); margin-bottom:8px;
    }
    .field label i { color:var(--accent); margin-right:5px; }
    .input-wrap { position:relative; }
    .input-wrap .fi {
        position:absolute; left:13px; top:50%; transform:translateY(-50%);
        color:var(--text3); font-size:0.8rem; pointer-events:none;
        transition:color 0.2s; z-index:1;
    }
    .input-wrap:focus-within .fi { color:var(--accent); }
    .field input {
        width:100%; padding:11px 14px 11px 36px;
        background:var(--surface2); border:1px solid var(--border2);
        border-radius:10px; color:var(--text);
        font-family:'Outfit',sans-serif; font-size:0.875rem;
        outline:none; transition:all 0.2s;
    }
    .field input::placeholder { color:var(--text3); }
    .field input:focus {
        border-color:var(--accent);
        box-shadow:0 0 0 3px var(--accentglow);
        background:var(--surface3);
    }

    /* ── ACTIONS ── */
    .form-actions {
        display:flex; gap:12px; padding-top:6px; margin-top:24px;
        border-top:1px solid var(--border);
    }
    .btn-save {
        flex:1; padding:13px; border:none; border-radius:10px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        color:#fff; font-family:'Outfit',sans-serif;
        font-size:0.88rem; font-weight:800; letter-spacing:0.04em;
        cursor:pointer; transition:all 0.22s; box-shadow:0 4px 16px var(--accentglow);
        display:flex; align-items:center; justify-content:center; gap:8px;
    }
    .btn-save:hover { opacity:0.88; transform:translateY(-1px); box-shadow:0 6px 22px var(--accentglow); }
    .btn-save:active { transform:scale(0.98); }
    .btn-cancel {
        flex:1; padding:13px; border-radius:10px;
        background:var(--surface2); border:1px solid var(--border2);
        color:var(--text2); font-family:'Outfit',sans-serif;
        font-size:0.88rem; font-weight:600; cursor:pointer;
        text-align:center; transition:all 0.2s; text-decoration:none;
        display:flex; align-items:center; justify-content:center; gap:7px;
    }
    .btn-cancel:hover { border-color:var(--red); color:var(--red); }

    @keyframes fadeUp { from{opacity:0;transform:translateY(14px);} to{opacity:1;transform:translateY(0);} }
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
        <li><a href="booking.php"><i class="fa fa-car"></i> Book a Car</a></li>
        <li><a href="user_booking.php"><i class="fa fa-calendar-check"></i> My Bookings</a></li>
        <li><a href="profile.php" class="active"><i class="fa fa-user"></i> Profile</a></li>
        <li class="sb-divider"></li>
        <li><a href="../index.php"><i class="fa fa-arrow-left"></i> Back to Site</a></li>
        <li><a href="logout.php" class="logout"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
    <div style="flex:1;"></div>
    <div style="padding:12px;">
        <div class="sb-user-card">
            <div class="uav"><?php echo $initial; ?></div>
            <div class="uname"><?php echo htmlspecialchars($user_name_s); ?></div>
            <div class="urole">MEMBER</div>
        </div>
    </div>
</aside>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="top-bar">
        <div class="tb-left">
            <h2>Edit Profile</h2>
            <p id="dateLabel"></p>
        </div>
        <div class="tb-right">
            <a href="profile.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Profile</a>
            <button class="theme-btn" id="themeBtn" title="Toggle theme">
                <i class="fa fa-moon" id="themeIcon"></i>
            </button>
            <a href="profile.php" class="tb-avatar"><?php echo $initial; ?></a>
        </div>
    </div>

    <div class="body">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="ph-text">
                <h2>Update Your <span>Information</span></h2>
                <p>Keep your contact details accurate and up to date.</p>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-error">
            <i class="fa fa-circle-exclamation"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
        <?php endif; ?>

        <!-- FORM CARD -->
        <div class="form-card">
            <div class="form-card-head">
                <i class="fa fa-user-pen"></i> Personal Details
            </div>

            <!-- Avatar preview -->
            <div class="avatar-block">
                <div class="avatar-circle"><?php echo $initial; ?></div>
                <div class="avatar-info">
                    <h3><?php echo htmlspecialchars($user_name); ?></h3>
                    <p><?php echo htmlspecialchars($user_email); ?></p>
                </div>
            </div>

            <form method="POST" class="form-body">

                <div class="field">
                    <label><i class="fa fa-user"></i> Full Name</label>
                    <div class="input-wrap">
                        <i class="fa fa-user fi"></i>
                        <input type="text" name="name"
                               value="<?php echo htmlspecialchars($user_name); ?>"
                               placeholder="Your full name" required>
                    </div>
                </div>

                <div class="field">
                    <label><i class="fa fa-envelope"></i> Email Address</label>
                    <div class="input-wrap">
                        <i class="fa fa-envelope fi"></i>
                        <input type="email" name="email"
                               value="<?php echo htmlspecialchars($user_email); ?>"
                               placeholder="you@example.com" required>
                    </div>
                </div>

                <div class="field">
                    <label><i class="fa fa-phone"></i> Phone Number</label>
                    <div class="input-wrap">
                        <i class="fa fa-phone fi"></i>
                        <input type="text" name="phone"
                               value="<?php echo htmlspecialchars($user_phone); ?>"
                               placeholder="+94 xx xxx xxxx" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="fa fa-floppy-disk"></i> Save Changes
                    </button>
                    <a href="profile.php" class="btn-cancel">
                        <i class="fa fa-xmark"></i> Cancel
                    </a>
                </div>

            </form>
        </div>

    </div>
</div>

<script>
    // Live date
    (function(){
        var d=new Date(), D=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('dateLabel').textContent = D[d.getDay()]+', '+d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear();
    })();

    // Theme
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }
</script>
</body>
</html>