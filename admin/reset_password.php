<?php
session_start();
include 'config.php';

$token   = trim($_GET['token'] ?? '');
$valid   = false;
$email   = '';
$success = false;
$error   = '';

if ($token) {
    $stmt = $conn->prepare(
        "SELECT email, expires_at FROM admin_password_resets
         WHERE token = ? LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $expires = strtotime($row['expires_at']);
        $now = time();
        $valid = ($expires > $now);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ FIX: read token from hidden input on POST
    $token = trim($_POST['token'] ?? '');

    if ($token) {
        $stmt = $conn->prepare(
            "SELECT email, expires_at FROM admin_password_resets
             WHERE token = ? LIMIT 1"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $expires = strtotime($row['expires_at']);
            $now = time();
            if ($expires > $now) {
                $valid = true;
                $email = $row['email'];
            }
        }
    }

    if (!$valid) {
        $error = "This reset link is invalid or has expired.";
    } else {
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif ($new !== $conf) {
            $error = "Passwords do not match.";
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);

            $upd = $conn->prepare("UPDATE admin SET password = ? WHERE email = ?");
            $upd->bind_param("ss", $hashed, $email);
            $upd->execute();
            $upd->close();

            $del = $conn->prepare("DELETE FROM admin_password_resets WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | CarForYou Admin</title>
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
        --green:#22c55e; --greenbg:rgba(34,197,94,0.1); --greenglow:rgba(34,197,94,0.25);
        --red:#ef4444; --redbg:rgba(239,68,68,0.1);
        --grid-line:rgba(79,142,247,0.04);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --surface:#ffffff; --surface2:#f5f7fa;
        --border:rgba(99,120,155,0.14); --border2:rgba(99,120,155,0.24);
        --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
        --accent:#2563eb; --accent2:#3b82f6; --glow:rgba(37,99,235,0.2);
        --input-bg:#ffffff; --input-border:rgba(99,120,155,0.3);
        --green:#059669; --greenbg:rgba(5,150,105,0.08); --greenglow:rgba(5,150,105,0.2);
        --red:#dc2626; --redbg:rgba(220,38,38,0.07);
        --grid-line:rgba(37,99,235,0.04);
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);
         min-height:100vh;display:flex;align-items:center;justify-content:center;
         transition:background var(--tr),color var(--tr);position:relative;overflow:hidden;}
    body::before{content:'';position:fixed;inset:0;z-index:0;
        background-image:linear-gradient(var(--grid-line) 1px,transparent 1px),
                         linear-gradient(90deg,var(--grid-line) 1px,transparent 1px);
        background-size:40px 40px;animation:gridDrift 20s linear infinite;}
    @keyframes gridDrift{from{background-position:0 0}to{background-position:40px 40px}}
    body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
        background:radial-gradient(ellipse 60% 50% at 50% 50%,var(--glow),transparent 70%);}
    .theme-corner{position:fixed;top:20px;right:20px;z-index:100;}
    .theme-btn{width:38px;height:38px;border-radius:10px;border:1px solid var(--border2);
        background:var(--surface);color:var(--text2);cursor:pointer;display:flex;
        align-items:center;justify-content:center;font-size:0.9rem;transition:all 0.2s;}
    .theme-btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 12px var(--glow);}
    .wrap{position:relative;z-index:10;width:100%;max-width:420px;padding:20px;}
    .card{background:var(--surface);border:1px solid var(--border2);border-radius:18px;
          padding:40px 36px;box-shadow:0 24px 60px rgba(0,0,0,0.3),0 0 0 1px var(--border);
          animation:cardIn 0.55s cubic-bezier(0.34,1.2,0.64,1) forwards;opacity:0;}
    @keyframes cardIn{from{opacity:0;transform:translateY(30px) scale(0.96)}to{opacity:1;transform:translateY(0) scale(1)}}
    .brand{text-align:center;margin-bottom:28px;}
    .brand-logo{display:inline-flex;align-items:center;justify-content:center;
        width:56px;height:56px;background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:14px;font-size:1.4rem;color:#fff;margin-bottom:14px;
        box-shadow:0 6px 20px var(--glow);}
    .brand h1{font-family:'Syne',sans-serif;font-size:1.45rem;font-weight:800;
              color:var(--text);letter-spacing:-0.01em;}
    .brand h1 span{color:var(--accent);}
    .brand p{font-size:0.75rem;color:var(--text3);margin-top:5px;letter-spacing:0.04em;}
    .divider{height:1px;background:var(--border);margin-bottom:26px;}
    .alert{display:flex;align-items:center;gap:9px;padding:12px 14px;border-radius:10px;
           font-size:0.83rem;font-weight:500;margin-bottom:18px;animation:fadeIn 0.3s ease;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
    .alert-error{background:var(--redbg);color:var(--red);border:1px solid rgba(239,68,68,0.22);}
    .form-group{margin-bottom:16px;}
    .form-group label{display:block;margin-bottom:6px;font-size:0.72rem;font-weight:700;
                      letter-spacing:0.1em;text-transform:uppercase;color:var(--text3);}
    .input-wrap{position:relative;display:flex;align-items:center;}
    .input-wrap .field-icon{position:absolute;left:13px;color:var(--text3);font-size:0.85rem;
                            pointer-events:none;transition:color 0.2s;z-index:2;}
    .input-wrap:focus-within .field-icon{color:var(--accent);}
    .form-control{width:100%;padding:11px 42px 11px 38px;background:var(--input-bg);
                  border:1px solid var(--input-border);border-radius:9px;color:var(--text);
                  font-family:'DM Sans',sans-serif;font-size:0.88rem;outline:none;
                  transition:border-color 0.2s,box-shadow 0.2s;}
    .form-control::placeholder{color:var(--text3);}
    .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--glow);}
    .pw-toggle{position:absolute;right:0;width:42px;height:100%;display:flex;
               align-items:center;justify-content:center;color:var(--text3);cursor:pointer;
               font-size:0.85rem;transition:color 0.2s;background:none;border:none;
               border-radius:0 9px 9px 0;z-index:2;}
    .pw-toggle:hover{color:var(--accent);}

    /* password strength bar */
    .strength-bar{height:4px;border-radius:4px;background:var(--border2);margin-top:8px;overflow:hidden;}
    .strength-fill{height:100%;border-radius:4px;width:0;transition:width 0.3s,background 0.3s;}
    .strength-label{font-size:0.7rem;color:var(--text3);margin-top:4px;text-align:right;}

    .btn-submit{width:100%;padding:12px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border:none;border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;
        font-size:0.9rem;font-weight:700;cursor:pointer;margin-top:4px;
        display:flex;align-items:center;justify-content:center;gap:8px;
        transition:all 0.22s;box-shadow:0 4px 16px var(--glow);}
    .btn-submit:hover{opacity:0.88;transform:translateY(-2px);box-shadow:0 8px 24px var(--glow);}
    .btn-submit:active{transform:translateY(0);}

    /* invalid/expired token state */
    .state-box{text-align:center;padding:10px 0;}
    .state-icon{width:60px;height:60px;border-radius:50%;display:flex;
                align-items:center;justify-content:center;font-size:1.5rem;
                margin:0 auto 16px;animation:popIn 0.35s cubic-bezier(0.34,1.56,0.64,1);}
    @keyframes popIn{from{transform:scale(0.5);opacity:0}to{transform:scale(1);opacity:1}}
    .icon-error{background:var(--redbg);border:2px solid rgba(239,68,68,0.25);color:var(--red);}
    .icon-success{background:var(--greenbg);border:2px solid rgba(34,197,94,0.25);color:var(--green);}
    .state-box h3{font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;
                  color:var(--text);margin-bottom:8px;}
    .state-box p{font-size:0.82rem;color:var(--text2);line-height:1.6;}
    .btn-back{display:inline-flex;align-items:center;gap:7px;margin-top:18px;
              padding:10px 20px;background:linear-gradient(135deg,var(--accent),var(--accent2));
              border:none;border-radius:9px;color:#fff;font-family:'DM Sans',sans-serif;
              font-size:0.85rem;font-weight:700;cursor:pointer;text-decoration:none;
              transition:all 0.2s;box-shadow:0 3px 12px var(--glow);}
    .btn-back:hover{opacity:0.88;transform:translateY(-1px);}
    </style>
</head>
<body>

<div class="theme-corner">
    <button class="theme-btn" id="themeBtn" title="Toggle Theme">
        <i class="fa fa-moon" id="themeIcon"></i>
    </button>
</div>

<div class="wrap">
    <div class="card">

        <div class="brand">
            <div class="brand-logo"><i class="fa fa-car-side"></i></div>
            <h1>Car<span>ForYou</span></h1>
            <p>ADMIN CONSOLE &nbsp;&middot;&nbsp; RESET PASSWORD</p>
        </div>

        <div class="divider"></div>

        <?php if ($success): ?>
        <!-- ✅ SUCCESS -->
        <div class="state-box">
            <div class="state-icon icon-success"><i class="fa fa-shield-check"></i></div>
            <h3>Password Updated!</h3>
            <p>Your admin password has been changed successfully.<br>
               You can now sign in with your new password.</p>
            <a href="index.php" class="btn-back">
                <i class="fa fa-arrow-right-to-bracket"></i> Go to Login
            </a>
        </div>

        <?php elseif (!$valid && !$_POST): ?>
        <!-- ❌ INVALID / EXPIRED TOKEN -->
        <div class="state-box">
            <div class="state-icon icon-error"><i class="fa fa-link-slash"></i></div>
            <h3>Link Invalid or Expired</h3>
            <p>This password reset link is no longer valid.<br>
               Links expire after <strong style="color:var(--accent)">1 hour</strong>.<br><br>
               Please request a new reset link.</p>
            <a href="index.php" class="btn-back">
                <i class="fa fa-arrow-left"></i> Back to Login
            </a>
        </div>

        <?php else: ?>
        <!-- 📝 RESET FORM -->
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- ✅ KEY FIX: token passed as hidden input -->
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="form-group">
                <label>New Password</label>
                <div class="input-wrap">
                    <i class="fa fa-lock field-icon"></i>
                    <input type="password" name="new_password" id="newPwd"
                           class="form-control" placeholder="Min. 8 characters"
                           required autocomplete="new-password"
                           oninput="checkStrength(this.value)">
                    <button type="button" class="pw-toggle" id="eyeNew" tabindex="-1">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <i class="fa fa-lock-open field-icon"></i>
                    <input type="password" name="confirm_password" id="confPwd"
                           class="form-control" placeholder="Repeat new password"
                           required autocomplete="new-password">
                    <button type="button" class="pw-toggle" id="eyeConf" tabindex="-1">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa fa-key"></i> Reset Password
            </button>
        </form>
        <?php endif; ?>

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

    // Password strength checker
    function checkStrength(val){
        var fill  = document.getElementById('strengthFill');
        var label = document.getElementById('strengthLabel');
        if(!fill) return;
        var score = 0;
        if(val.length >= 8)  score++;
        if(val.length >= 12) score++;
        if(/[A-Z]/.test(val)) score++;
        if(/[0-9]/.test(val)) score++;
        if(/[^A-Za-z0-9]/.test(val)) score++;
        var levels = [
            {w:'0%',   c:'transparent', t:''},
            {w:'25%',  c:'#ef4444',     t:'Weak'},
            {w:'50%',  c:'#f97316',     t:'Fair'},
            {w:'75%',  c:'#fbbf24',     t:'Good'},
            {w:'100%', c:'#22c55e',     t:'Strong'},
        ];
        var lv = levels[Math.min(score, 4)];
        fill.style.width      = lv.w;
        fill.style.background = lv.c;
        label.textContent     = lv.t;
        label.style.color     = lv.c;
    }

    // Hold-to-reveal
    function bindHold(btnId, inputId){
        var btn = document.getElementById(btnId);
        var inp = document.getElementById(inputId);
        if(!btn||!inp) return;
        var ico = btn.querySelector('i');
        var show = function(){ inp.type='text';     ico.className='fa fa-eye-slash'; };
        var hide = function(){ inp.type='password'; ico.className='fa fa-eye'; };
        btn.addEventListener('mousedown',   function(e){ e.preventDefault(); show(); });
        btn.addEventListener('mouseup',     hide);
        btn.addEventListener('mouseleave',  hide);
        btn.addEventListener('touchstart',  function(e){ e.preventDefault(); show(); },{passive:false});
        btn.addEventListener('touchend',    hide);
        btn.addEventListener('touchcancel', hide);
    }
    bindHold('eyeNew',  'newPwd');
    bindHold('eyeConf', 'confPwd');
</script>
</body>
</html>
