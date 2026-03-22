<?php
session_start();
require_once('../includes/config.php');
userAuth('login.php?error=Please login first');

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) { session_destroy(); header("Location: login.php?error=Account no longer exists"); exit(); }

$columns = [];
$columns['name']  = isset($user['full_name'])  ? 'full_name'  : null;
$columns['email'] = isset($user['email'])       ? 'email'      : null;
$columns['phone'] = isset($user['contact_no'])  ? 'contact_no' : null;

$user_name  = $columns['name']  ? $user[$columns['name']]  : 'User';
$user_email = $columns['email'] ? $user[$columns['email']] : 'Not provided';
$user_phone = $columns['phone'] ? $user[$columns['phone']] : 'Not provided';
$user_role  = $user['role']     ?? 'Member';
$reg_date   = isset($user['reg_date']) ? date('M d, Y', strtotime($user['reg_date'])) : 'Unknown';
$initial    = strtoupper(substr($user_name, 0, 1));

$user_city    = $user['city']    ?? '';
$user_country = $user['country'] ?? '';
$user_address = $user['address'] ?? '';
$user_dob     = !empty($user['dob']) ? date('d M Y', strtotime($user['dob'])) : 'Not provided';
$location     = trim($user_city . ($user_city && $user_country ? ', ' : '') . $user_country);
$profile_pic  = $user['profile_picture'] ?? '';
$has_pic      = !empty($profile_pic);
$profile_pic_path = $has_pic ? '../' . $profile_pic : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { font-size:16px; }
    [data-theme="dark"] {
        --bg:#0b0e14; --bg2:#0f1319; --surface:#141920; --surface2:#1a2030;
        --border:rgba(255,255,255,0.06); --border2:rgba(255,255,255,0.1);
        --text:#f0f2f8; --text2:#8892a4; --text3:#44505e;
        --accent:#00d4ff; --accent2:#0090ff; --accentglow:rgba(0,212,255,0.18); --accentbg:rgba(0,212,255,0.06);
        --green:#00e676; --greenbg:rgba(0,230,118,0.08);
        --red:#ff4f4f; --redbg:rgba(255,79,79,0.08);
        --shadow:0 4px 24px rgba(0,0,0,0.4); --sbg:#0a0d12; --sborder:rgba(255,255,255,0.05); --hbg:rgba(11,14,20,0.88);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --bg2:#e6ecf3; --surface:#ffffff; --surface2:#f5f8fc;
        --border:rgba(0,0,0,0.07); --border2:rgba(0,0,0,0.12);
        --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
        --accent:#0077cc; --accent2:#0055aa; --accentglow:rgba(0,119,204,0.16); --accentbg:rgba(0,119,204,0.07);
        --green:#059669; --greenbg:rgba(5,150,105,0.08);
        --red:#dc2626; --redbg:rgba(220,38,38,0.07);
        --shadow:0 4px 20px rgba(0,0,0,0.08); --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06); --hbg:rgba(240,244,248,0.9);
    }
    body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background 0.35s,color 0.35s;}
    ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:var(--bg);} ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px;}
    a{text-decoration:none;color:inherit;}
    .sidebar{width:240px;min-height:100vh;background:var(--sbg);border-right:1px solid var(--sborder);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;z-index:100;overflow-y:auto;transition:background 0.35s;}
    .sb-brand{padding:26px 22px 18px;border-bottom:1px solid var(--sborder);}
    .sb-brand a{text-decoration:none;display:flex;align-items:center;gap:10px;}
    .sb-logo{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.88rem;color:#fff;box-shadow:0 0 14px var(--accentglow);flex-shrink:0;}
    .sb-brand-text{font-size:1.1rem;font-weight:800;color:#e8edf5;letter-spacing:-0.02em;}
    .sb-brand-text span{color:var(--accent);}
    .sb-section{font-size:0.6rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:rgba(232,237,245,0.22);padding:20px 22px 6px;}
    .sb-nav{list-style:none;padding:6px 10px;}
    .sb-nav li{margin-bottom:2px;}
    .sb-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-size:0.85rem;font-weight:500;color:rgba(232,237,245,0.45);transition:all 0.2s;}
    .sb-nav a i{width:16px;text-align:center;font-size:0.82rem;}
    .sb-nav a:hover{background:rgba(0,212,255,0.07);color:rgba(232,237,245,0.85);}
    .sb-nav a.active{background:linear-gradient(90deg,rgba(0,212,255,0.15),rgba(0,212,255,0.04));color:var(--accent);font-weight:600;box-shadow:inset 3px 0 0 var(--accent);}
    .sb-nav a.logout{color:rgba(255,79,79,0.6);}
    .sb-nav a.logout:hover{background:rgba(255,79,79,0.08);color:#ff4f4f;}
    .sb-divider{height:1px;background:var(--sborder);margin:10px 2px;}
    .sb-user-card{margin:10px;padding:14px;background:var(--surface);border:1px solid var(--border);border-radius:12px;}
    .sb-user-card .uav{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.9rem;font-weight:800;color:#fff;margin-bottom:10px;box-shadow:0 0 12px var(--accentglow);}
    .sb-user-card .uname{font-size:0.82rem;font-weight:700;color:var(--text);}
    .sb-user-card .urole{font-size:0.68rem;color:var(--text3);margin-top:2px;}
    .main{margin-left:240px;width:calc(100% - 240px);min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:50;background:var(--hbg);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;transition:background 0.35s;}
    .tb-left h2{font-size:1.05rem;font-weight:700;color:var(--text);letter-spacing:-0.02em;}
    .tb-left p{font-size:0.72rem;color:var(--text2);margin-top:1px;}
    .tb-right{display:flex;align-items:center;gap:10px;}
    .theme-btn{width:36px;height:36px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.85rem;transition:all 0.2s;}
    .theme-btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 10px var(--accentglow);}
    .tb-avatar{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.82rem;font-weight:800;color:#fff;text-decoration:none;box-shadow:0 0 12px var(--accentglow);}
    .body{padding:28px 32px;flex:1;}
    .profile-grid{display:grid;grid-template-columns:280px 1fr;gap:22px;align-items:start;opacity:0;animation:fadeUp 0.5s ease 0.05s forwards;}
    .profile-summary{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;position:sticky;top:82px;}
    .ps-header{padding:32px 24px 20px;background:linear-gradient(160deg,var(--accentbg),transparent);border-bottom:1px solid var(--border);text-align:center;position:relative;}
    .ps-header::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--accent),var(--accent2));}
    .ps-avatar{width:80px;height:80px;margin:0 auto 14px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;box-shadow:0 0 0 4px var(--surface),0 0 0 6px var(--border2),0 0 24px var(--accentglow);position:relative;overflow:hidden;}
    .ps-avatar .online-ring{position:absolute;bottom:3px;right:3px;width:14px;height:14px;border-radius:50%;background:var(--green);border:2px solid var(--surface);}
    .ps-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .ps-avatar-edit{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.7);color:#fff;font-size:0.6rem;padding:4px;text-align:center;cursor:pointer;opacity:0;transition:opacity 0.2s;}
    .ps-avatar:hover .ps-avatar-edit{opacity:1;}
    .ps-name{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:4px;}
    .ps-email{font-size:0.75rem;color:var(--text3);margin-bottom:12px;}
    .ps-role-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:0.64rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;background:var(--greenbg);color:var(--green);border:1px solid rgba(0,230,118,0.18);}
    .ps-role-badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
    .ps-stats{padding:18px 24px;}
    .ps-stat-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);}
    .ps-stat-row:last-child{border-bottom:none;}
    .ps-stat-label{font-size:0.72rem;color:var(--text3);display:flex;align-items:center;gap:8px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;}
    .ps-stat-label i{color:var(--accent);width:14px;font-size:0.7rem;}
    .ps-stat-val{font-size:0.82rem;font-weight:700;color:var(--text);}
    .ps-actions{padding:0 16px 16px;display:flex;flex-direction:column;gap:8px;}
    .ps-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border-radius:10px;font-family:'Outfit',sans-serif;font-size:0.78rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;text-decoration:none;transition:all 0.22s;cursor:pointer;border:none;}
    .ps-btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;box-shadow:0 3px 12px var(--accentglow);}
    .ps-btn-primary:hover{opacity:0.88;transform:translateY(-1px);}
    .ps-btn-outline{background:transparent;color:var(--text2);border:1px solid var(--border2);}
    .ps-btn-outline:hover{border-color:var(--accent);color:var(--accent);background:var(--accentbg);}
    .ps-btn-danger{background:var(--redbg);color:var(--red);border:1px solid rgba(255,79,79,0.15);}
    .ps-btn-danger:hover{background:var(--red);color:#fff;}
    .info-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;}
    .card-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .card-head h3{font-size:0.9rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
    .card-head h3 i{color:var(--accent);font-size:0.82rem;}
    .edit-link{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:8px;font-size:0.74rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;border:1px solid var(--border2);color:var(--text2);transition:all 0.2s;}
    .edit-link:hover{border-color:var(--accent);color:var(--accent);background:var(--accentbg);}
    .info-body{padding:24px;}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;}
    .info-field{display:flex;flex-direction:column;gap:6px;}
    .info-field.full{grid-column:span 2;}
    .field-label{font-size:0.62rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text3);}
    .field-value{display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:0.875rem;font-weight:600;color:var(--text);transition:border-color 0.2s;}
    .field-value:hover{border-color:var(--border2);}
    .field-value i{color:var(--accent);font-size:0.78rem;width:14px;flex-shrink:0;}
    .field-value span{color:var(--text2);font-weight:400;}
    .danger-zone{margin-top:20px;padding:20px 24px;background:var(--redbg);border:1px solid rgba(255,79,79,0.15);border-radius:14px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
    .dz-text h4{font-size:0.88rem;font-weight:700;color:var(--red);margin-bottom:3px;}
    .dz-text p{font-size:0.76rem;color:var(--text3);}
    .dz-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:9px;font-family:'Outfit',sans-serif;font-size:0.74rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;background:var(--redbg);color:var(--red);border:1px solid rgba(255,79,79,0.25);text-decoration:none;transition:all 0.2s;white-space:nowrap;flex-shrink:0;}
    .dz-btn:hover{background:var(--red);color:#fff;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}
    @media(max-width:960px){.profile-grid{grid-template-columns:1fr;}.profile-summary{position:static;}.info-grid{grid-template-columns:1fr;}.info-field.full{grid-column:span 1;}}
    @media(max-width:640px){.main{margin-left:0;width:100%;}.sidebar{display:none;}.body{padding:20px;}.danger-zone{flex-direction:column;align-items:flex-start;}}
    </style>
</head>
<body>
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
        <li><a href="profile.php" class="active"><i class="fa fa-user"></i> Profile</a></li>
        <li class="sb-divider"></li>
        <li><a href="../index.php"><i class="fa fa-arrow-left"></i> Back to Site</a></li>
        <li><a href="logout.php" class="logout"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
    <div style="flex:1;"></div>
    <div style="padding:12px;">
        <div class="sb-user-card">
            <div class="uav"><?php echo $initial; ?></div>
            <div class="uname"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="urole">MEMBER</div>
        </div>
    </div>
</aside>
<div class="main">
    <div class="top-bar">
        <div class="tb-left"><h2>Account Settings</h2><p id="dateLabel"></p></div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn" title="Toggle theme"><i class="fa fa-moon" id="themeIcon"></i></button>
            <a href="profile.php" class="tb-avatar"><?php echo $initial; ?></a>
        </div>
    </div>
    <div class="body">
        <div class="profile-grid">
            <!-- LEFT: Summary -->
            <div class="profile-summary">
                <div class="ps-header">
                    <div class="ps-avatar" id="psAvatar">
                        <?php if ($has_pic): ?>
                            <img src="<?php echo htmlspecialchars($profile_pic_path); ?>?t=<?php echo time(); ?>" alt="Profile Picture" id="avatarImg">
                        <?php else: ?>
                            <span id="avatarInitial"><?php echo $initial; ?></span>
                        <?php endif; ?>
                        <span class="online-ring"></span>
                        <span class="ps-avatar-edit" onclick="document.getElementById('profilePicInput').click()">
                            <i class="fa fa-camera"></i> Change
                        </span>
                    </div>
                    <div class="ps-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="ps-email"><?php echo htmlspecialchars($user_email); ?></div>
                    <span class="ps-role-badge"><?php echo htmlspecialchars($user_role); ?></span>
                </div>
                <div class="ps-stats">
                    <div class="ps-stat-row">
                        <span class="ps-stat-label"><i class="fa fa-calendar-plus"></i> Member Since</span>
                        <span class="ps-stat-val"><?php echo $reg_date; ?></span>
                    </div>
                    <?php if ($location): ?>
                    <div class="ps-stat-row">
                        <span class="ps-stat-label"><i class="fa fa-location-dot"></i> Location</span>
                        <span class="ps-stat-val"><?php echo htmlspecialchars($location); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="ps-stat-row">
                        <span class="ps-stat-label"><i class="fa fa-shield-halved"></i> Status</span>
                        <span class="ps-stat-val" style="color:var(--green);">Active</span>
                    </div>
                </div>
                <div class="ps-actions">
                    <a href="edit_profile.php" class="ps-btn ps-btn-primary"><i class="fa fa-pen"></i> Edit Profile</a>
                    <a href="change_password.php" class="ps-btn ps-btn-outline"><i class="fa fa-key"></i> Change Password</a>
                    <a href="logout.php" class="ps-btn ps-btn-danger"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a>
                </div>
            </div>
            <!-- RIGHT: Info -->
            <div>
                <div class="info-card">
                    <div class="card-head">
                        <h3><i class="fa fa-id-card"></i> Personal Information</h3>
                        <a href="edit_profile.php" class="edit-link"><i class="fa fa-pen"></i> Edit</a>
                    </div>
                    <div class="info-body">
                        <div class="info-grid">
                            <div class="info-field">
                                <div class="field-label">Full Name</div>
                                <div class="field-value"><i class="fa fa-user"></i><?php echo $user_name !== 'User' ? htmlspecialchars($user_name) : '<span>Not provided</span>'; ?></div>
                            </div>
                            <div class="info-field">
                                <div class="field-label">Phone Number</div>
                                <div class="field-value"><i class="fa fa-phone"></i><?php echo $user_phone !== 'Not provided' ? htmlspecialchars($user_phone) : '<span>Not provided</span>'; ?></div>
                            </div>
                            <div class="info-field full">
                                <div class="field-label">Email Address</div>
                                <div class="field-value"><i class="fa fa-envelope"></i><?php echo htmlspecialchars($user_email); ?></div>
                            </div>
                            <?php if ($user_dob !== 'Not provided'): ?>
                            <div class="info-field">
                                <div class="field-label">Date of Birth</div>
                                <div class="field-value"><i class="fa fa-cake-candles"></i><?php echo htmlspecialchars($user_dob); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($location): ?>
                            <div class="info-field">
                                <div class="field-label">Location</div>
                                <div class="field-value"><i class="fa fa-location-dot"></i><?php echo htmlspecialchars($location); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($user_address): ?>
                            <div class="info-field full">
                                <div class="field-label">Address</div>
                                <div class="field-value"><i class="fa fa-map-marker-alt"></i><?php echo htmlspecialchars($user_address); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;padding-top:20px;border-top:1px solid var(--border);">
                            <a href="edit_profile.php" class="ps-btn ps-btn-primary" style="flex:1;min-width:140px;"><i class="fa fa-pen-to-square"></i> Update Profile</a>
                            <a href="change_password.php" class="ps-btn ps-btn-outline" style="flex:1;min-width:140px;"><i class="fa fa-lock"></i> Security Settings</a>
                        </div>
                    </div>
                </div>
                <div class="danger-zone">
                    <div class="dz-text">
                        <h4>Privacy &amp; Security</h4>
                        <p>Need to log out of all devices or close your account?</p>
                    </div>
                    <a href="logout.php" class="dz-btn"><i class="fa fa-arrow-right-from-bracket"></i> Logout Now</a>
                </div>
            </div>
        </div>
    </div>
</div>
<input type="file" id="profilePicInput" accept="image/*" style="display:none;">
<script>
    (function(){var d=new Date(),days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear();})();
    var theme=localStorage.getItem('cfyTheme')||'dark';
    document.documentElement.setAttribute('data-theme',theme);syncIcon();
    document.getElementById('themeBtn').addEventListener('click',function(){theme=theme==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',theme);localStorage.setItem('cfyTheme',theme);syncIcon();});
    function syncIcon(){document.getElementById('themeIcon').className=theme==='dark'?'fa fa-moon':'fa fa-sun';}
    
    // Profile Picture Upload
    document.getElementById('profilePicInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('profile_image', file);
        
        fetch('../upload_profile_image.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const avatar = document.getElementById('psAvatar');
                const initial = document.getElementById('avatarInitial');
                const img = document.getElementById('avatarImg');
                
                if (initial) initial.remove();
                const newSrc = '../' + data.path + '?t=' + Date.now();
                if (!img) {
                    const newImg = document.createElement('img');
                    newImg.id = 'avatarImg';
                    newImg.src = newSrc;
                    avatar.insertBefore(newImg, avatar.firstChild);
                } else {
                    img.src = newSrc;
                }
            } else {
                alert('Upload failed: ' + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Upload failed');
        });
    });
</script>
</body>
</html>