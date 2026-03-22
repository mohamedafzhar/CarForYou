<?php
session_start();
require_once('../includes/config.php');
userAuth();

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? $_SESSION['fname'] ?? 'User';
$initial   = strtoupper(substr($user_name, 0, 1));

$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_email = $stmt->get_result()->fetch_assoc()['email'] ?? '';

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM booking WHERE user_email = ?");
$stmt->bind_param("s", $user_email); $stmt->execute();
$total_bookings = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS active FROM booking WHERE user_email = ? AND status IN (1, 'confirmed', 'Confirmed')");
$stmt->bind_param("s", $user_email); $stmt->execute();
$active_rentals = $stmt->get_result()->fetch_assoc()['active'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS pending FROM booking WHERE user_email = ? AND status IN (0, 'Pending', 'pending', 'awaiting_payment')");
$stmt->bind_param("s", $user_email); $stmt->execute();
$pending_bookings = $stmt->get_result()->fetch_assoc()['pending'] ?? 0;

$stmt = $conn->prepare("
    SELECT b.id, b.from_date, b.to_date, b.status, b.return_status,
           c.car_name, c.car_type, c.Vimage1
    FROM booking b
    JOIN cars c ON c.id = b.car_id
    WHERE b.user_email = ?
    ORDER BY b.id DESC
    LIMIT 5
");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$bookings_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | CarForYou</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%2300d4ff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%2300d4ff'/><circle cx='70' cy='62' r='6' fill='%2300d4ff'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%2300d4ff' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { font-size:16px; }

    [data-theme="dark"] {
        --bg:#0b0e14; --bg2:#0f1319;
        --surface:#141920; --surface2:#1a2030; --surface3:#1f2638;
        --border:rgba(255,255,255,0.06); --border2:rgba(255,255,255,0.1);
        --text:#f0f2f8; --text2:#8892a4; --text3:#44505e;
        --accent:#00d4ff; --accent2:#0090ff;
        --accentglow:rgba(0,212,255,0.18); --accentbg:rgba(0,212,255,0.06);
        --green:#00e676; --greenbg:rgba(0,230,118,0.08); --greenglow:rgba(0,230,118,0.2);
        --amber:#fbbf24; --amberbg:rgba(251,191,36,0.08);
        --red:#ff4f4f; --redbg:rgba(255,79,79,0.08);
        --shadow:0 4px 24px rgba(0,0,0,0.4);
        --sbg:#0a0d12; --sborder:rgba(255,255,255,0.05); --hbg:rgba(11,14,20,0.88);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --bg2:#e6ecf3;
        --surface:#ffffff; --surface2:#f5f8fc; --surface3:#eaf0f8;
        --border:rgba(0,0,0,0.07); --border2:rgba(0,0,0,0.12);
        --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
        --accent:#0077cc; --accent2:#0055aa;
        --accentglow:rgba(0,119,204,0.16); --accentbg:rgba(0,119,204,0.07);
        --green:#059669; --greenbg:rgba(5,150,105,0.08); --greenglow:rgba(5,150,105,0.18);
        --amber:#d97706; --amberbg:rgba(217,119,6,0.08);
        --red:#dc2626; --redbg:rgba(220,38,38,0.07);
        --shadow:0 4px 20px rgba(0,0,0,0.08);
        --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06); --hbg:rgba(240,244,248,0.9);
    }

    body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; transition:background 0.35s, color 0.35s; }
    ::-webkit-scrollbar { width:4px; }
    ::-webkit-scrollbar-track { background:var(--bg); }
    ::-webkit-scrollbar-thumb { background:var(--accent); border-radius:4px; }

    .sidebar { width:240px; min-height:100vh; background:var(--sbg); border-right:1px solid var(--sborder); position:fixed; top:0; left:0; bottom:0; display:flex; flex-direction:column; z-index:100; transition:background 0.35s; overflow-y:auto; }
    .sb-brand { padding:26px 22px 18px; border-bottom:1px solid var(--sborder); }
    .sb-brand a { text-decoration:none; display:flex; align-items:center; gap:10px; }
    .sb-logo { width:34px; height:34px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.88rem; color:#fff; box-shadow:0 0 14px var(--accentglow); flex-shrink:0; }
    .sb-brand-text { font-size:1.1rem; font-weight:800; color:#e8edf5; letter-spacing:-0.02em; }
    .sb-brand-text span { color:var(--accent); }
    .sb-section { font-size:0.6rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:rgba(232,237,245,0.22); padding:20px 22px 6px; }
    .sb-nav { list-style:none; padding:6px 10px; }
    .sb-nav li { margin-bottom:2px; }
    .sb-nav a { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:9px; font-size:0.85rem; font-weight:500; color:rgba(232,237,245,0.45); text-decoration:none; transition:all 0.2s; }
    .sb-nav a i { width:16px; text-align:center; font-size:0.82rem; }
    .sb-nav a:hover { background:rgba(0,212,255,0.07); color:rgba(232,237,245,0.85); }
    .sb-nav a.active { background:linear-gradient(90deg,rgba(0,212,255,0.15),rgba(0,212,255,0.04)); color:var(--accent); font-weight:600; box-shadow:inset 3px 0 0 var(--accent); }
    .sb-nav a.logout { color:rgba(255,79,79,0.6); }
    .sb-nav a.logout:hover { background:rgba(255,79,79,0.08); color:#ff4f4f; }
    .sb-divider { height:1px; background:var(--sborder); margin:10px 2px; }
    .sb-user-card { margin:10px; padding:14px; background:var(--surface); border:1px solid var(--border); border-radius:12px; }
    .sb-user-card .uav { width:36px; height:36px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; font-weight:800; color:#fff; margin-bottom:10px; box-shadow:0 0 12px var(--accentglow); }
    .sb-user-card .uname { font-size:0.82rem; font-weight:700; color:var(--text); }
    .sb-user-card .urole { font-size:0.68rem; color:var(--text3); margin-top:2px; letter-spacing:0.04em; }

    .main { margin-left:240px; width:calc(100% - 240px); min-height:100vh; display:flex; flex-direction:column; }
    .top-bar { position:sticky; top:0; z-index:50; background:var(--hbg); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; transition:background 0.35s; }
    .tb-left h2 { font-size:1.05rem; font-weight:700; color:var(--text); letter-spacing:-0.02em; }
    .tb-left p { font-size:0.72rem; color:var(--text2); margin-top:1px; }
    .tb-right { display:flex; align-items:center; gap:10px; }
    .theme-btn { width:36px; height:36px; border-radius:9px; border:1px solid var(--border2); background:var(--surface); color:var(--text2); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.85rem; transition:all 0.2s; }
    .theme-btn:hover { border-color:var(--accent); color:var(--accent); box-shadow:0 0 10px var(--accentglow); }
    .tb-avatar { width:34px; height:34px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.82rem; font-weight:800; color:#fff; text-decoration:none; box-shadow:0 0 12px var(--accentglow); }

    .body { padding:26px 32px; flex:1; }

    .welcome-banner { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:22px 26px; margin-bottom:22px; display:flex; align-items:center; justify-content:space-between; position:relative; overflow:hidden; opacity:0; animation:fadeUp 0.5s ease 0.05s forwards; }
    .welcome-banner::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent); opacity:0.5; }
    .welcome-banner::after { content:''; position:absolute; right:-30px; top:-30px; width:160px; height:160px; background:radial-gradient(circle, var(--accentglow), transparent 70%); pointer-events:none; }
    .wb-text h2 { font-size:1.3rem; font-weight:800; color:var(--text); letter-spacing:-0.02em; }
    .wb-text h2 span { background:linear-gradient(90deg,var(--accent),var(--accent2)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .wb-text p { font-size:0.82rem; color:var(--text2); margin-top:4px; }
    .wb-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; font-family:'Outfit',sans-serif; font-size:0.82rem; font-weight:700; letter-spacing:0.03em; text-decoration:none; white-space:nowrap; transition:all 0.22s; box-shadow:0 4px 14px var(--accentglow); position:relative; z-index:1; }
    .wb-btn:hover { opacity:0.88; transform:translateY(-1px); }

    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:22px; }
    .sc-link { text-decoration:none; display:block; }
    .sc { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px; display:flex; align-items:center; gap:16px; position:relative; overflow:hidden; opacity:0; animation:fadeUp 0.5s ease forwards; transition:transform 0.22s, box-shadow 0.22s, border-color 0.22s; cursor:pointer; }
    .sc::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--accent),var(--accent2)); transform:scaleX(0); transform-origin:left; transition:transform 0.3s ease; }
    .sc:hover { transform:translateY(-3px); box-shadow:var(--shadow); border-color:var(--accent); }
    .sc:hover::before { transform:scaleX(1); }
    .sc:nth-child(1){animation-delay:0.1s} .sc:nth-child(2){animation-delay:0.16s} .sc:nth-child(3){animation-delay:0.22s}
    .sc-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; transition:transform 0.22s; }
    .sc:hover .sc-icon { transform:scale(1.1); }
    .sc-num { font-size:1.9rem; font-weight:800; color:var(--text); letter-spacing:-0.03em; line-height:1; }
    .sc-lbl { font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.1em; color:var(--text3); margin-top:4px; }

    .content-grid { display:grid; grid-template-columns:1fr 300px; gap:20px; }
    .card { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; opacity:0; animation:fadeUp 0.5s ease 0.28s forwards; }
    .card-head { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .card-head h3 { font-size:0.9rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; display:flex; align-items:center; gap:8px; }
    .card-head h3 i { color:var(--accent); font-size:0.82rem; }
    .view-all { font-size:0.76rem; font-weight:600; color:var(--accent); text-decoration:none; padding:5px 12px; border-radius:20px; border:1px solid var(--accentglow); transition:all 0.2s; }
    .view-all:hover { background:var(--accentbg); }

    table { width:100%; border-collapse:collapse; }
    th { font-size:0.62rem; font-weight:700; letter-spacing:0.13em; text-transform:uppercase; color:var(--text3); padding:0 18px 11px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
    td { padding:13px 18px; font-size:0.845rem; color:var(--text2); border-bottom:1px solid var(--border); transition:background 0.15s; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(0,212,255,0.03); }
    .car-cell { display:flex; align-items:center; gap:11px; }
    .car-thumb { width:54px; height:36px; border-radius:8px; object-fit:cover; background:var(--surface2); flex-shrink:0; border:1px solid var(--border); }
    .car-name { font-weight:700; color:var(--text); font-size:0.85rem; }
    .car-type { font-size:0.72rem; color:var(--text3); margin-top:2px; }
    .badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    .badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .badge-confirmed { background:var(--greenbg); color:var(--green); }
    .badge-pending   { background:var(--amberbg); color:var(--amber); }
    .badge-cancelled { background:var(--redbg);   color:var(--red); }
    .empty-state { padding:40px 20px; text-align:center; color:var(--text3); }
    .empty-state i { font-size:2.2rem; display:block; margin-bottom:12px; opacity:0.2; }
    .empty-state a { color:var(--accent); text-decoration:none; font-weight:600; }

    .side-cards { display:flex; flex-direction:column; gap:16px; }
    .cta-card { border-radius:14px; padding:24px; background:var(--surface); border:1px solid var(--border); position:relative; overflow:hidden; opacity:0; animation:fadeUp 0.5s ease 0.34s forwards; }
    .cta-card::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg, rgba(0,212,255,0.07), rgba(0,144,255,0.04)); pointer-events:none; }
    .cta-card::after { content:''; position:absolute; right:-20px; bottom:-20px; width:120px; height:120px; background:radial-gradient(circle,var(--accentglow),transparent 70%); pointer-events:none; }
    .cta-card .cta-icon { width:42px; height:42px; border-radius:11px; background:linear-gradient(135deg,rgba(0,212,255,0.15),rgba(0,144,255,0.2)); border:1px solid rgba(0,212,255,0.2); display:flex; align-items:center; justify-content:center; font-size:1rem; color:var(--accent); margin-bottom:14px; box-shadow:0 0 14px var(--accentglow); }
    .cta-card h4 { font-size:0.95rem; font-weight:800; color:var(--text); margin-bottom:6px; letter-spacing:-0.01em; }
    .cta-card p  { font-size:0.78rem; color:var(--text2); line-height:1.55; margin-bottom:16px; }
    .cta-link { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:9px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; font-size:0.78rem; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; text-decoration:none; transition:all 0.22s; box-shadow:0 3px 12px var(--accentglow); position:relative; z-index:1; }
    .cta-link:hover { opacity:0.88; transform:translateY(-1px); }

    .status-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px; opacity:0; animation:fadeUp 0.5s ease 0.4s forwards; }
    .status-card h4 { font-size:0.88rem; font-weight:700; color:var(--text); margin-bottom:14px; }
    .status-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); font-size:0.82rem; }
    .status-row:last-of-type { border-bottom:none; }
    .status-row .slabel { color:var(--text2); display:flex; align-items:center; gap:8px; }
    .status-row .slabel i { color:var(--text3); font-size:0.78rem; width:14px; }
    .sbadge-ok { color:var(--green); font-size:0.95rem; }
    .sbadge-warn { font-size:0.66rem; font-weight:700; color:var(--amber); background:var(--amberbg); padding:3px 9px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em; }
    .update-btn { display:block; width:100%; margin-top:14px; padding:11px; border:1px dashed var(--border2); border-radius:10px; text-align:center; color:var(--text3); font-size:0.78rem; font-weight:600; text-decoration:none; letter-spacing:0.04em; transition:all 0.2s; }
    .update-btn:hover { border-color:var(--accent); color:var(--accent); background:var(--accentbg); }

    @keyframes fadeUp { from{opacity:0;transform:translateY(14px);} to{opacity:1;transform:translateY(0);} }
    @media(max-width:960px) { .content-grid{grid-template-columns:1fr;} .stats-grid{grid-template-columns:1fr 1fr;} }
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
        <li><a href="car_dashboard.php" class="active"><i class="fa fa-table-columns"></i> Dashboard</a></li>
        <li><a href="user_booking.php"><i class="fa fa-calendar-check"></i> My Bookings</a></li>
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
        <div class="tb-left"><h2>My Dashboard</h2><p id="dateLabel"></p></div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn" title="Toggle theme"><i class="fa fa-moon" id="themeIcon"></i></button>
            <a href="profile.php" class="tb-avatar"><?php echo $initial; ?></a>
        </div>
    </div>
    <div class="body">
        <div class="welcome-banner">
            <div class="wb-text">
                <h2>Welcome back, <span><?php echo htmlspecialchars($user_name); ?></span>!</h2>
                <p>Track your rentals and manage all your bookings from here.</p>
            </div>
            <a href="../index.php" class="wb-btn"><i class="fa fa-car-side"></i> Browse Cars</a>
        </div>
        <div class="stats-grid">
            <a href="user_booking.php" class="sc-link"><div class="sc">
                <div class="sc-icon" style="background:var(--accentbg);color:var(--accent);"><i class="fa fa-receipt"></i></div>
                <div><div class="sc-num"><?php echo $total_bookings; ?></div><div class="sc-lbl">Total Bookings</div></div>
            </div></a>
            <a href="user_booking.php?filter=confirmed" class="sc-link"><div class="sc">
                <div class="sc-icon" style="background:var(--greenbg);color:var(--green);"><i class="fa fa-circle-check"></i></div>
                <div><div class="sc-num"><?php echo $active_rentals; ?></div><div class="sc-lbl">Confirmed</div></div>
            </div></a>
            <a href="user_booking.php?filter=pending" class="sc-link"><div class="sc">
                <div class="sc-icon" style="background:var(--amberbg);color:var(--amber);"><i class="fa fa-clock"></i></div>
                <div><div class="sc-num"><?php echo $pending_bookings; ?></div><div class="sc-lbl">Pending</div></div>
            </div></a>
        </div>
        <div class="content-grid">
            <div class="card">
                <div class="card-head">
                    <h3><i class="fa fa-list"></i> Recent Bookings</h3>
                    <a href="user_booking.php" class="view-all">View All</a>
                </div>
                <table>
                    <thead><tr><th>Car</th><th>From</th><th>To</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($bookings_result && $bookings_result->num_rows > 0):
                        while ($b = $bookings_result->fetch_assoc()):
                            $img = !empty($b['Vimage1']) ? "../admin/img/vehicleimages/" . htmlspecialchars($b['Vimage1']) : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=80&q=60";
                            $st = $b['status'];
                            $is_returned = ($b['return_status'] === 'returned');
                            $is_awaiting = ($st === 'awaiting_payment');
                            if ($is_returned){$bc='badge-confirmed';$bt='Returned';}
                            elseif ($is_awaiting){$bc='badge-pending';$bt='Awaiting Payment';}
                            elseif ($st == 1 || $st === 'confirmed' || $st === 'Confirmed'){$bc='badge-confirmed';$bt='Confirmed';}
                            elseif ($st == 2 || $st === 'cancelled' || $st === 'Cancelled'){$bc='badge-cancelled';$bt='Cancelled';}
                            else{$bc='badge-pending';$bt='Pending';}
                    ?>
                    <tr>
                        <td><div class="car-cell"><img src="<?php echo $img; ?>" class="car-thumb" alt="car" onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=80&q=60'"><div><div class="car-name"><?php echo htmlspecialchars($b['car_name']); ?></div><div class="car-type"><?php echo htmlspecialchars($b['car_type']); ?></div></div></div></td>
                        <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($b['from_date'])); ?></td>
                        <td style="white-space:nowrap;"><?php echo date('d M Y', strtotime($b['to_date'])); ?></td>
                        <td><span class="badge <?php echo $bc; ?>"><?php echo $bt; ?></span></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4"><div class="empty-state"><i class="fa fa-car"></i>No bookings yet. <a href="../index.php">Browse cars</a></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="side-cards">
                <div class="cta-card">
                    <div class="cta-icon"><i class="fa fa-headset"></i></div>
                    <h4>Need Help?</h4>
                    <p>Our support team is available 24/7 to assist with your rental queries.</p>
                    <a href="../index.php#contact" class="cta-link"><i class="fa fa-paper-plane"></i> Contact Support</a>
                </div>
                <div class="status-card">
                    <h4>Account Status</h4>
                    <div class="status-row">
                        <span class="slabel"><i class="fa fa-shield-halved"></i> Identity Verified</span>
                        <i class="fa fa-circle-check sbadge-ok"></i>
                    </div>
                    <div class="status-row">
                        <span class="slabel"><i class="fa fa-id-card"></i> Driver's License</span>
                        <span class="sbadge-warn">Expiring</span>
                    </div>
                    <a href="profile.php" class="update-btn"><i class="fa fa-plus" style="margin-right:5px;"></i> Update Documents</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function(){var d=new Date(),days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear();})();
    var theme=localStorage.getItem('cfyTheme')||'dark';
    document.documentElement.setAttribute('data-theme',theme);syncIcon();
    document.getElementById('themeBtn').addEventListener('click',function(){theme=theme==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',theme);localStorage.setItem('cfyTheme',theme);syncIcon();});
    function syncIcon(){document.getElementById('themeIcon').className=theme==='dark'?'fa fa-moon':'fa fa-sun';}
</script>
</body>
</html>