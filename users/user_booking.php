<?php
session_start();
require_once('../includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=Please login first");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$initial   = strtoupper(substr($user_name, 0, 1));

$stmt = mysqli_prepare($conn, "SELECT email FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_email = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['email'] ?? '';
if (!$user_email) die("User email not found.");

$sql = "SELECT b.*, c.car_name, c.car_model, c.Vimage1 AS car_image, c.price_per_day
        FROM booking b
        JOIN cars c ON b.car_id = c.id
        WHERE b.user_email = ?
        ORDER BY b.posting_date DESC";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) die("Prepare failed: " . $conn->error);
mysqli_stmt_bind_param($stmt, "s", $user_email);
mysqli_stmt_execute($stmt);
$bookings_result = mysqli_stmt_get_result($stmt);
$total_rows = mysqli_num_rows($bookings_result);

// Fetch all booking IDs that already have a testimonial submitted
// (only runs if the testimonials table exists — safe before running migration)
$reviewed_ids = [];
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'testimonials'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $rev_stmt = mysqli_prepare($conn, "SELECT booking_id FROM testimonials WHERE user_id = ?");
    mysqli_stmt_bind_param($rev_stmt, "i", $user_id);
    mysqli_stmt_execute($rev_stmt);
    $rev_result = mysqli_stmt_get_result($rev_stmt);
    while ($rv = mysqli_fetch_assoc($rev_result)) {
        $reviewed_ids[] = intval($rv['booking_id']);
    }
}

$confirmed = $pending = $cancelled = $returned = 0;
$all_bookings = [];
while ($r = mysqli_fetch_assoc($bookings_result)) {
    $all_bookings[] = $r;
    $st = $r['status'];
    $rs = $r['return_status'] ?? 'not_returned';
    if ($rs === 'returned')          $returned++;
    elseif ($st == 1 || $st === 'confirmed') $confirmed++;
    elseif ($st == 2 || $st === 'cancelled') $cancelled++;
    else                             $pending++;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | CarForYou</title>
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
        --amber:#fbbf24; --amberbg:rgba(251,191,36,0.08);
        --red:#ff4f4f; --redbg:rgba(255,79,79,0.08);
        --shadow:0 4px 24px rgba(0,0,0,0.4); --sbg:#0a0d12; --sborder:rgba(255,255,255,0.05); --hbg:rgba(11,14,20,0.88);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --bg2:#e6ecf3; --surface:#ffffff; --surface2:#f5f8fc;
        --border:rgba(0,0,0,0.07); --border2:rgba(0,0,0,0.12);
        --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
        --accent:#0077cc; --accent2:#0055aa; --accentglow:rgba(0,119,204,0.16); --accentbg:rgba(0,119,204,0.07);
        --green:#059669; --greenbg:rgba(5,150,105,0.08);
        --amber:#d97706; --amberbg:rgba(217,119,6,0.08);
        --red:#dc2626; --redbg:rgba(220,38,38,0.07);
        --shadow:0 4px 20px rgba(0,0,0,0.08); --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06); --hbg:rgba(240,244,248,0.9);
    }
    body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; transition:background 0.35s, color 0.35s; }
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
    .body{padding:26px 32px;flex:1;}
    .flash{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:0.84rem;font-weight:600;margin-bottom:18px;opacity:0;animation:fadeUp 0.4s ease forwards;}
    .flash-success{background:var(--greenbg);color:var(--green);border:1px solid rgba(0,230,118,0.2);}
    .flash-error{background:var(--redbg);color:var(--red);border:1px solid rgba(255,79,79,0.2);}
    .stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px;}
    .sc{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:14px;opacity:0;animation:fadeUp 0.5s ease forwards;transition:transform 0.22s,box-shadow 0.22s;position:relative;overflow:hidden;cursor:default;}
    .sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),var(--accent2));transform:scaleX(0);transform-origin:left;transition:transform 0.3s;}
    .sc:hover{transform:translateY(-3px);box-shadow:var(--shadow);}
    .sc:hover::before{transform:scaleX(1);}
    .sc:nth-child(1){animation-delay:0.05s}.sc:nth-child(2){animation-delay:0.1s}.sc:nth-child(3){animation-delay:0.15s}.sc:nth-child(4){animation-delay:0.2s}
    .sc-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;transition:transform 0.22s;}
    .sc:hover .sc-icon{transform:scale(1.1);}
    .sc-num{font-size:1.7rem;font-weight:800;color:var(--text);letter-spacing:-0.03em;line-height:1;}
    .sc-lbl{font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:var(--text3);margin-top:3px;}
    .filter-row{display:flex;align-items:center;gap:8px;margin-bottom:20px;opacity:0;animation:fadeUp 0.5s ease 0.25s forwards;}
    .ftab{padding:7px 16px;border-radius:20px;font-size:0.74rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;border:1px solid var(--border2);background:transparent;color:var(--text3);cursor:pointer;transition:all 0.2s;font-family:'Outfit',sans-serif;display:inline-flex;align-items:center;gap:6px;}
    .ftab:hover{border-color:var(--accent);color:var(--accent);background:var(--accentbg);}
    .ftab.active{background:var(--accent);color:#fff;border-color:var(--accent);box-shadow:0 3px 10px var(--accentglow);}
    .ftab .cbadge{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:9px;padding:0 4px;font-size:0.6rem;font-weight:800;background:rgba(255,255,255,0.2);}
    .booking-list{display:flex;flex-direction:column;gap:14px;}
    .bk-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;display:flex;align-items:stretch;opacity:0;animation:fadeUp 0.5s ease forwards;transition:transform 0.22s,box-shadow 0.22s,border-color 0.22s;position:relative;}
    .bk-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:var(--border2);}
    .bk-card.unpaid-pending{border-color:rgba(251,191,36,0.3);}
    .bk-card.unpaid-pending::before{content:'⚡';position:absolute;top:12px;right:12px;background:var(--amberbg);color:var(--amber);padding:4px 8px;border-radius:6px;font-size:0.7rem;z-index:1;animation:pulse 2s infinite;}
    @keyframes pulse{0%,100%{opacity:1;}50%{opacity:0.6;}}
    .bk-card::after{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px;}
    .bk-confirmed::after{background:var(--green);}
    .bk-pending::after{background:var(--amber);}
    .bk-cancelled::after{background:var(--red);}
    .bk-img{width:190px;flex-shrink:0;overflow:hidden;position:relative;}
    .bk-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.5s;}
    .bk-card:hover .bk-img img{transform:scale(1.06);}
    .bk-img::after{content:'';position:absolute;inset:0;background:linear-gradient(to right,rgba(0,0,0,0.3),transparent);pointer-events:none;}
    .bk-body{flex:1;padding:20px 24px;display:flex;flex-direction:column;justify-content:space-between;min-width:0;}
    .bk-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:16px;}
    .bk-name{font-size:1rem;font-weight:800;color:var(--text);letter-spacing:-0.01em;}
    .bk-id{font-size:0.7rem;color:var(--text3);margin-top:3px;font-weight:600;letter-spacing:0.04em;display:flex;align-items:center;gap:4px;}
    .sbadge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:0.66rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;white-space:nowrap;flex-shrink:0;}
    .sbadge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
    .sbadge-confirmed{background:var(--greenbg);color:var(--green);}
    .sbadge-pending{background:var(--amberbg);color:var(--amber);}
    .sbadge-cancelled{background:var(--redbg);color:var(--red);}
    .bk-details{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
    .bk-dl{font-size:0.6rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text3);margin-bottom:5px;}
    .bk-dv{font-size:0.86rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:5px;}
    .bk-dv i{color:var(--accent);font-size:0.7rem;}
    .bk-dv.total{color:var(--accent);}
    .bk-actions{padding:20px 20px 20px 8px;display:flex;flex-direction:column;justify-content:center;gap:8px;flex-shrink:0;}
    .act-pay{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;font-family:'Outfit',sans-serif;font-size:0.82rem;font-weight:700;letter-spacing:0.03em;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;box-shadow:0 4px 14px rgba(0,212,255,0.3);}
    .act-pay:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,212,255,0.4);}
    .act-cancel{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;font-family:'Outfit',sans-serif;font-size:0.74rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;background:var(--redbg);color:var(--red);border:1px solid rgba(255,79,79,0.18);cursor:pointer;transition:all 0.2s;text-decoration:none;white-space:nowrap;}
    .act-cancel:hover{background:var(--red);color:#fff;}
    .act-review{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;font-family:'Outfit',sans-serif;font-size:0.74rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;background:rgba(251,191,36,0.1);color:var(--amber);border:1px solid rgba(251,191,36,0.25);cursor:pointer;transition:all 0.2s;text-decoration:none;white-space:nowrap;}
    .act-review:hover{background:var(--amber);color:#000;}
    .act-pay{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;font-family:'Outfit',sans-serif;font-size:0.74rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;white-space:nowrap;box-shadow:0 4px 14px rgba(0,212,255,0.3);}
    .act-pay:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,212,255,0.4);}
    .act-reviewed{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;font-size:0.74rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;background:var(--greenbg);color:var(--green);border:1px solid rgba(0,230,118,0.2);white-space:nowrap;}
    .sbadge-returned{background:rgba(139,92,246,0.1);color:#a78bfa;}
    .bk-returned::after{background:#a78bfa;}
    .empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:80px 40px;text-align:center;background:var(--surface);border:1px dashed var(--border2);border-radius:16px;opacity:0;animation:fadeUp 0.5s ease 0.3s forwards;}
    .empty-icon{width:76px;height:76px;border-radius:20px;background:var(--accentbg);border:1px solid var(--border2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--text3);margin-bottom:20px;}
    .empty-state h3{font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:8px;}
    .empty-state p{font-size:0.85rem;color:var(--text3);margin-bottom:24px;}
    .explore-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-family:'Outfit',sans-serif;font-size:0.82rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;text-decoration:none;transition:all 0.22s;box-shadow:0 4px 14px var(--accentglow);}
    .explore-btn:hover{opacity:0.88;transform:translateY(-1px);}
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}
    @media(max-width:960px){.stats-row{grid-template-columns:1fr 1fr;}.bk-img{width:140px;}.bk-details{grid-template-columns:1fr 1fr;}}
    @media(max-width:640px){.main{margin-left:0;width:100%;}.sidebar{display:none;}.bk-card{flex-direction:column;}.bk-img{width:100%;height:180px;}.bk-actions{padding:0 18px 18px;flex-direction:row;}.stats-row{grid-template-columns:1fr 1fr;}}
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
        <li><a href="user_booking.php" class="active"><i class="fa fa-calendar-check"></i> My Bookings</a></li>
        <li><a href="profile.php"><i class="fa fa-user"></i> Profile</a></li>
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
        <div class="tb-left"><h2>My Bookings</h2><p id="dateLabel"></p></div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn" title="Toggle theme"><i class="fa fa-moon" id="themeIcon"></i></button>
            <a href="profile.php" class="tb-avatar"><?php echo $initial; ?></a>
        </div>
    </div>
    <div class="body">
        <?php if (isset($_GET['cancelled'])): ?>
        <div class="flash flash-success"><i class="fa fa-circle-check"></i> Booking cancelled successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="flash flash-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <div class="stats-row">
            <div class="sc"><div class="sc-icon" style="background:var(--accentbg);color:var(--accent);"><i class="fa fa-receipt"></i></div><div><div class="sc-num"><?php echo $total_rows; ?></div><div class="sc-lbl">Total</div></div></div>
            <div class="sc"><div class="sc-icon" style="background:var(--greenbg);color:var(--green);"><i class="fa fa-circle-check"></i></div><div><div class="sc-num"><?php echo $confirmed; ?></div><div class="sc-lbl">Confirmed</div></div></div>
            <div class="sc"><div class="sc-icon" style="background:var(--amberbg);color:var(--amber);"><i class="fa fa-clock"></i></div><div><div class="sc-num"><?php echo $pending; ?></div><div class="sc-lbl">Pending</div></div></div>
            <div class="sc"><div class="sc-icon" style="background:var(--redbg);color:var(--red);"><i class="fa fa-ban"></i></div><div><div class="sc-num"><?php echo $cancelled; ?></div><div class="sc-lbl">Cancelled</div></div></div>
            <div class="sc"><div class="sc-icon" style="background:rgba(139,92,246,0.1);color:#a78bfa;"><i class="fa fa-rotate-left"></i></div><div><div class="sc-num"><?php echo $returned; ?></div><div class="sc-lbl">Returned</div></div></div>
        </div>
        <div class="filter-row">
            <button class="ftab active" onclick="filterBookings('all',this)">All <span class="cbadge"><?php echo $total_rows; ?></span></button>
            <button class="ftab" onclick="filterBookings('confirmed',this)">Confirmed <span class="cbadge"><?php echo $confirmed; ?></span></button>
            <button class="ftab" onclick="filterBookings('pending',this)">Pending <span class="cbadge"><?php echo $pending; ?></span></button>
            <button class="ftab" onclick="filterBookings('cancelled',this)">Cancelled <span class="cbadge"><?php echo $cancelled; ?></span></button>
            <button class="ftab" onclick="filterBookings('returned',this)">Returned <span class="cbadge"><?php echo $returned; ?></span></button>
        </div>
        <div class="booking-list" id="bookingList">
        <?php if (count($all_bookings) > 0):
            $delay = 0.3;
            foreach ($all_bookings as $row):
                $st = $row['status'];
                $rs = $row['return_status'] ?? 'not_returned';
                $is_returned = ($rs === 'returned');

                if ($is_returned)                                      {$sc='returned'; $st_label='Returned'; $bc='sbadge-returned';}
                elseif ($st == 1 || $st === 'confirmed')               {$sc='confirmed';$st_label='Confirmed';$bc='sbadge-confirmed';}
                elseif ($st == 2 || $st === 'cancelled')               {$sc='cancelled';$st_label='Cancelled';$bc='sbadge-cancelled';}
                else                                                   {$sc='pending';  $st_label='Pending';  $bc='sbadge-pending';}

                $days  = max(1, ceil((strtotime($row['to_date']) - strtotime($row['from_date'])) / 86400));
                $total = $days * $row['price_per_day'];
                $img   = !empty($row['car_image']) ? "../admin/img/vehicleimages/" . htmlspecialchars($row['car_image']) : "https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400";
                $already_reviewed = in_array(intval($row['id']), $reviewed_ids);
                $is_unpaid = ($row['payment_status'] ?? '') === 'unpaid' && ($st == 1 || $st === 'confirmed');
        ?>
        <div class="bk-card bk-<?php echo $sc; ?><?php echo $is_unpaid ? ' unpaid-pending' : ''; ?>" data-status="<?php echo $sc; ?>" style="animation-delay:<?php echo $delay; ?>s;">
            <div class="bk-img"><img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($row['car_name']); ?>" onerror="this.src='https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400'"></div>
            <div class="bk-body">
                <div class="bk-top">
                    <div>
                        <div class="bk-name"><?php echo htmlspecialchars($row['car_name'] . ' ' . $row['car_model']); ?></div>
                        <div class="bk-id"><i class="fa fa-hashtag"></i> BK-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <span class="sbadge <?php echo $bc; ?>"><?php echo $st_label; ?></span>
                </div>
                <div class="bk-details">
                    <div><div class="bk-dl">Pick-up</div><div class="bk-dv"><i class="fa fa-calendar"></i><?php echo date('d M Y', strtotime($row['from_date'])); ?></div></div>
                    <div><div class="bk-dl">Return</div><div class="bk-dv"><i class="fa fa-calendar-check"></i><?php echo date('d M Y', strtotime($row['to_date'])); ?></div></div>
                    <div><div class="bk-dl">Duration</div><div class="bk-dv"><i class="fa fa-clock"></i><?php echo $days; ?> day<?php echo $days!=1?'s':''; ?></div></div>
                    <div><div class="bk-dl">Total Cost</div><div class="bk-dv total"><i class="fa fa-tag"></i>LKR <?php echo number_format($total); ?></div></div>
                </div>
                <?php if ($is_unpaid): ?>
                <a href="payment.php?booking_id=<?php echo $row['id']; ?>" style="display:block;margin-top:10px;padding:10px 12px;background:var(--amberbg);border-radius:8px;border:1px solid rgba(251,191,36,0.3);text-decoration:none;transition:all 0.2s;" onmouseover="this.style.borderColor='var(--amber)'" onmouseout="this.style.borderColor='rgba(251,191,36,0.3)'">
                    <span style="font-size:0.8rem;color:var(--amber);font-weight:600;display:flex;align-items:center;gap:8px;">
                        <i class="fa fa-credit-card"></i> Payment Required — Click to Pay Now <i class="fa fa-arrow-right" style="margin-left:auto;"></i>
                    </span>
                </a>
                <?php endif; ?>
            </div>
            <div class="bk-actions">
                <?php if ($is_unpaid): ?>
                <a href="payment.php?booking_id=<?php echo $row['id']; ?>" class="act-pay" style="width:100%;justify-content:center;"><i class="fa fa-credit-card"></i> Pay Now</a>
                <?php elseif ($is_returned): ?>
                    <?php if ($already_reviewed): ?>
                    <span class="act-reviewed"><i class="fa fa-star"></i> Reviewed</span>
                    <?php else: ?>
                    <a href="testimonial.php?booking_id=<?php echo $row['id']; ?>" class="act-review"><i class="fa fa-star"></i> Write Review</a>
                    <?php endif; ?>
                <?php elseif ($st == 0 || $st === 'pending'): ?>
                <a href="cancel_booking.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Cancel booking #BK-<?php echo str_pad($row['id'],5,'0',STR_PAD_LEFT); ?>?')" class="act-cancel"><i class="fa fa-xmark"></i> Cancel</a>
                <?php endif; ?>
            </div>
        </div>
        <?php $delay += 0.06; endforeach; ?>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa fa-calendar-xmark"></i></div>
            <h3>No Bookings Yet</h3>
            <p>You haven't made any bookings. Start exploring our fleet!</p>
            <a href="../index.php" class="explore-btn"><i class="fa fa-car-side"></i> Explore Cars</a>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>
<script>
    (function(){var d=new Date(),days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear();})();
    var theme=localStorage.getItem('cfyTheme')||'dark';
    document.documentElement.setAttribute('data-theme',theme);syncIcon();
    document.getElementById('themeBtn').addEventListener('click',function(){theme=theme==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',theme);localStorage.setItem('cfyTheme',theme);syncIcon();});
    function syncIcon(){document.getElementById('themeIcon').className=theme==='dark'?'fa fa-moon':'fa fa-sun';}
    function filterBookings(filter,btn){document.querySelectorAll('.ftab').forEach(function(t){t.classList.remove('active');});btn.classList.add('active');document.querySelectorAll('.bk-card').forEach(function(card){card.style.display=(filter==='all'||card.dataset.status===filter)?'flex':'none';});}
    document.querySelectorAll('.flash').forEach(function(el){setTimeout(function(){el.style.transition='opacity 0.5s';el.style.opacity='0';setTimeout(function(){el.style.display='none';},500);},3000);});
</script>
</body>
</html>