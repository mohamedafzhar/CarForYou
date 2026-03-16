<?php
session_start();
require_once('../includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? $_SESSION['fname'] ?? 'User';
$initial   = strtoupper(substr($user_name, 0, 1));

// Fetch user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_email = $stmt->get_result()->fetch_assoc()['email'] ?? '';
if (!$user_email) die("User email not found.");

$msg   = '';
$error = '';

// ── HANDLE BOOKING SUBMISSION ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_id    = intval($_POST['car_id']);
    $from_date = trim($_POST['from_date']);
    $to_date   = trim($_POST['to_date']);
    $message   = trim($_POST['message'] ?? '');
    $from_ts   = strtotime($from_date);
    $to_ts     = strtotime($to_date);

    $stmt = $conn->prepare("SELECT id FROM cars WHERE id = ? AND status = 'Available'");
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $car_exists = $stmt->get_result()->num_rows > 0;

    if (!$car_id || !$car_exists) {
        $error = "Please select a car.";
    } elseif (!$from_date || !$to_date) {
        $error = "Please select both pick-up and return dates.";
    } elseif ($from_ts < strtotime('today')) {
        $error = "Pick-up date cannot be in the past.";
    } elseif ($to_ts <= $from_ts) {
        $error = "Return date must be after pick-up date.";
    } else {
        // Check overlapping bookings (pending=0 or confirmed=1)
        $check = $conn->prepare("
            SELECT id FROM booking
            WHERE car_id = ? AND status IN (0,1)
            AND NOT (to_date < ? OR from_date > ?)
        ");
        $check->bind_param("iss", $car_id, $from_date, $to_date);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "This car is already booked for the selected dates. Please choose different dates.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO booking (user_email, car_id, from_date, to_date, message, status, posting_date)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->bind_param("sisss", $user_email, $car_id, $from_date, $to_date, $message);
            if ($stmt->execute()) {
                $msg = "Booking submitted! Our team will confirm it shortly.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

// ── PRE-SELECTED CAR: GET param or POST re-render ────────────────────────────
$preselect_car_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['car_id'])) {
    $preselect_car_id = intval($_POST['car_id']);
} elseif (!empty($_GET['car_id'])) {
    $preselect_car_id = intval($_GET['car_id']);
}

// ── FETCH ALL AVAILABLE CARS ──────────────────────────────────────────────────
$cars_result = $conn->query("SELECT * FROM cars WHERE status = 'Available' ORDER BY id DESC");

// ── CACHE PRESELECTED CAR DATA (name + price) so we can use it in the form panel
//    without data_seek conflicts after the card loop consumes the result pointer ─
$preselect_name  = '';
$preselect_price = 0;
if ($preselect_car_id && $cars_result) {
    while ($c = $cars_result->fetch_assoc()) {
        if ((int)$c['id'] === $preselect_car_id) {
            $preselect_name  = $c['car_name'];
            $preselect_price = intval($c['price_per_day']);
            break;
        }
    }
    $cars_result->data_seek(0); // reset so the card loop below works normally
}

// ── FETCH BOOKED DATE RANGES PER CAR (pending + confirmed, future only) ───────
// Shape: { "car_id": [["2026-03-20","2026-03-25"], ...], ... }
$booked_ranges = [];
$bk = $conn->query("
    SELECT car_id, from_date, to_date
    FROM booking
    WHERE status IN (0,1)
    AND to_date >= CURDATE()
    ORDER BY car_id, from_date
");
if ($bk) {
    while ($row = $bk->fetch_assoc()) {
        $booked_ranges[$row['car_id']][] = [$row['from_date'], $row['to_date']];
    }
}
$booked_ranges_json = json_encode($booked_ranges);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Car | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { font-size:16px; }

    [data-theme="dark"] {
        --bg:          #0b0e14;
        --surface:     #141920;
        --surface2:    #1a2030;
        --surface3:    #1f2638;
        --border:      rgba(255,255,255,0.06);
        --border2:     rgba(255,255,255,0.1);
        --text:        #f0f2f8;
        --text2:       #8892a4;
        --text3:       #44505e;
        --accent:      #00d4ff;
        --accent2:     #0090ff;
        --accentglow:  rgba(0,212,255,0.18);
        --accentbg:    rgba(0,212,255,0.06);
        --green:       #00e676;
        --greenbg:     rgba(0,230,118,0.08);
        --amber:       #fbbf24;
        --amberbg:     rgba(251,191,36,0.08);
        --red:         #ff4f4f;
        --redbg:       rgba(255,79,79,0.08);
        --shadow:      0 4px 24px rgba(0,0,0,0.4);
        --sbg:         #0a0d12;
        --sborder:     rgba(255,255,255,0.05);
        --hbg:         rgba(11,14,20,0.88);
    }
    [data-theme="light"] {
        --bg:          #f0f4f8;
        --surface:     #ffffff;
        --surface2:    #f5f8fc;
        --surface3:    #eaf0f8;
        --border:      rgba(0,0,0,0.07);
        --border2:     rgba(0,0,0,0.12);
        --text:        #0f1923;
        --text2:       #4a5568;
        --text3:       #94a3b8;
        --accent:      #0077cc;
        --accent2:     #0055aa;
        --accentglow:  rgba(0,119,204,0.16);
        --accentbg:    rgba(0,119,204,0.07);
        --green:       #059669;
        --greenbg:     rgba(5,150,105,0.08);
        --amber:       #d97706;
        --amberbg:     rgba(217,119,6,0.08);
        --red:         #dc2626;
        --redbg:       rgba(220,38,38,0.07);
        --shadow:      0 4px 20px rgba(0,0,0,0.08);
        --sbg:         #1c2b3a;
        --sborder:     rgba(255,255,255,0.06);
        --hbg:         rgba(240,244,248,0.9);
    }

    body {
        font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text);
        display:flex; min-height:100vh; transition:background 0.35s, color 0.35s;
    }
    ::-webkit-scrollbar { width:4px; }
    ::-webkit-scrollbar-track { background:var(--bg); }
    ::-webkit-scrollbar-thumb { background:var(--accent); border-radius:4px; }

    /* SIDEBAR */
    .sidebar {
        width:240px; min-height:100vh; background:var(--sbg);
        border-right:1px solid var(--sborder);
        position:fixed; top:0; left:0; bottom:0;
        display:flex; flex-direction:column; z-index:100;
        transition:background 0.35s; overflow-y:auto;
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

    /* MAIN */
    .main { margin-left:240px; width:calc(100% - 240px); min-height:100vh; display:flex; flex-direction:column; }

    /* TOPBAR */
    .top-bar {
        position:sticky; top:0; z-index:50;
        background:var(--hbg); backdrop-filter:blur(16px);
        border-bottom:1px solid var(--border); padding:0 32px; height:64px;
        display:flex; align-items:center; justify-content:space-between; transition:background 0.35s;
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

    .body { padding:26px 32px; flex:1; }

    /* PAGE HEADER */
    .page-header {
        background:var(--surface); border:1px solid var(--border); border-radius:16px;
        padding:22px 26px; margin-bottom:22px;
        display:flex; align-items:center; justify-content:space-between;
        position:relative; overflow:hidden;
        opacity:0; animation:fadeUp 0.5s ease 0.05s forwards;
    }
    .page-header::before {
        content:''; position:absolute; top:0; left:0; right:0; height:2px;
        background:linear-gradient(90deg, transparent, var(--accent), var(--accent2), transparent); opacity:0.5;
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
    .ph-badge {
        display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px;
        background:var(--accentbg); border:1px solid rgba(0,212,255,0.15); color:var(--accent);
        font-size:0.82rem; font-weight:700; position:relative; z-index:1; white-space:nowrap;
    }

    /* ALERTS */
    .alert {
        margin-bottom:20px; border-radius:12px; padding:14px 18px;
        display:flex; align-items:center; gap:12px; font-size:0.85rem; font-weight:500;
        opacity:0; animation:fadeUp 0.4s ease forwards;
    }
    .alert i { font-size:1rem; flex-shrink:0; }
    .alert-success { background:var(--greenbg); border:1px solid rgba(0,230,118,0.2); color:var(--green); }
    .alert-error   { background:var(--redbg);   border:1px solid rgba(255,79,79,0.2);   color:var(--red); }
    .alert a { color:inherit; font-weight:700; text-decoration:underline; }

    /* BOOKING GRID */
    .booking-grid {
        display:grid; grid-template-columns:1fr 340px; gap:20px;
        opacity:0; animation:fadeUp 0.5s ease 0.15s forwards;
    }

    /* PANEL */
    .panel { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .panel-head {
        padding:18px 22px; border-bottom:1px solid var(--border);
        display:flex; align-items:center; justify-content:space-between;
    }
    .panel-head h3 { font-size:0.9rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
    .panel-head h3 i { color:var(--accent); font-size:0.82rem; }
    .panel-head .count {
        font-size:0.72rem; font-weight:700; color:var(--text3);
        background:var(--surface2); border:1px solid var(--border); padding:3px 10px; border-radius:20px;
    }
    .panel-body { padding:16px; }

    /* CAR CARD */
    .car-card {
        display:flex; align-items:center; gap:14px; padding:14px 16px; border-radius:12px;
        border:1px solid var(--border); background:var(--surface2);
        cursor:pointer; transition:all 0.22s; margin-bottom:10px; position:relative; overflow:hidden;
    }
    .car-card:last-child { margin-bottom:0; }
    .car-card::before {
        content:''; position:absolute; left:0; top:0; bottom:0; width:3px;
        background:var(--accent); border-radius:3px 0 0 3px; transform:scaleY(0); transition:transform 0.22s;
    }
    .car-card:hover { border-color:rgba(0,212,255,0.25); background:var(--surface3); transform:translateX(3px); }
    .car-card:hover::before { transform:scaleY(1); }
    .car-card.selected {
        border-color:var(--accent); background:var(--accentbg);
        box-shadow:0 0 0 1px var(--accent), 0 4px 20px var(--accentglow);
    }
    .car-card.selected::before { transform:scaleY(1); }

    .radio-dot {
        width:18px; height:18px; border-radius:50%; border:2px solid var(--border2);
        flex-shrink:0; position:relative; transition:all 0.2s; background:var(--surface);
    }
    .car-card.selected .radio-dot { background:var(--accent); border-color:var(--accent); box-shadow:0 0 8px var(--accentglow); }
    .car-card.selected .radio-dot::after {
        content:''; display:block; width:7px; height:7px; background:#fff; border-radius:50%;
        position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
    }

    .car-thumb {
        width:80px; height:52px; border-radius:9px; object-fit:cover; flex-shrink:0;
        border:1px solid var(--border); background:var(--surface3);
    }
    .car-info { flex:1; min-width:0; }
    .car-name-row { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
    .car-name  { font-size:0.88rem; font-weight:700; color:var(--text); }
    .car-model { font-size:0.72rem; color:var(--text3); margin-top:2px; }
    .car-price { font-size:0.88rem; font-weight:800; color:var(--accent); white-space:nowrap; }
    .car-price span { font-size:0.7rem; font-weight:400; color:var(--text3); }
    .car-tags  { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; align-items:center; }
    .tag {
        font-size:0.67rem; font-weight:600; color:var(--text3);
        background:var(--surface); border:1px solid var(--border);
        padding:2px 8px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;
    }
    .tag-booked {
        font-size:0.67rem; font-weight:700; color:var(--red);
        background:var(--redbg); border:1px solid rgba(255,79,79,0.25);
        padding:2px 8px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;
    }
    .tag-free {
        font-size:0.67rem; font-weight:700; color:var(--green);
        background:var(--greenbg); border:1px solid rgba(0,230,118,0.25);
        padding:2px 8px; border-radius:20px; display:inline-flex; align-items:center; gap:4px;
    }

    /* FORM PANEL */
    .form-panel {
        background:var(--surface); border:1px solid var(--border);
        border-radius:14px; overflow:hidden; position:sticky; top:84px; height:fit-content;
    }
    .form-panel-head { padding:18px 22px; border-bottom:1px solid var(--border); }
    .form-panel-head h3 { font-size:0.9rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; }
    .form-panel-head h3 i { color:var(--accent); font-size:0.82rem; }
    .form-panel-head p { font-size:0.75rem; color:var(--text3); margin-top:3px; }
    .form-body { padding:20px; }

    /* Selected preview */
    .selected-preview {
        background:var(--accentbg); border:1px solid rgba(0,212,255,0.15);
        border-radius:10px; padding:14px; margin-bottom:18px; display:none;
    }
    .selected-preview.visible { display:block; }
    .sp-label { font-size:0.62rem; font-weight:700; color:var(--accent); letter-spacing:0.12em; text-transform:uppercase; margin-bottom:6px; }
    .sp-name  { font-size:0.9rem; font-weight:800; color:var(--text); }
    .sp-rate  { font-size:0.75rem; color:var(--text2); margin-top:3px; }

    /* No car hint */
    .no-car-hint {
        background:var(--amberbg); border:1px solid rgba(251,191,36,0.2);
        border-radius:10px; padding:14px; margin-bottom:18px;
        font-size:0.8rem; color:var(--amber); display:flex; gap:8px; align-items:center;
    }
    .no-car-hint.hidden { display:none; }

    /* Booked dates notice */
    .booked-notice {
        background:var(--redbg); border:1px solid rgba(255,79,79,0.2);
        border-radius:10px; padding:12px 14px; margin-bottom:16px;
        font-size:0.78rem; color:var(--red); display:none; gap:8px;
    }
    .booked-notice.visible { display:flex; }
    .booked-notice i { flex-shrink:0; margin-top:2px; }

    /* Date conflict indicator */
    .date-conflict {
        font-size:0.75rem; color:var(--red); margin-top:5px; display:none;
    }
    .date-conflict.visible { display:block; }

    /* Fields */
    .field { margin-bottom:16px; }
    .field label {
        display:block; font-size:0.65rem; font-weight:700;
        letter-spacing:0.12em; text-transform:uppercase; color:var(--text3); margin-bottom:8px;
    }
    .field label i { color:var(--accent); margin-right:5px; }
    .field input, .field textarea {
        width:100%; padding:11px 14px; background:var(--surface2);
        border:1px solid var(--border2); border-radius:10px; color:var(--text);
        font-family:'Outfit',sans-serif; font-size:0.85rem; transition:all 0.2s; outline:none;
    }
    [data-theme="dark"] .field input::-webkit-calendar-picker-indicator { filter:invert(0.7); cursor:pointer; }
    .field input:focus, .field textarea:focus {
        border-color:var(--accent); box-shadow:0 0 0 3px var(--accentglow); background:var(--surface3);
    }
    .field input.invalid { border-color:var(--red) !important; box-shadow:0 0 0 3px var(--redbg) !important; }
    .field input:disabled { opacity:0.35; cursor:not-allowed; }
    .field textarea { resize:none; }

    /* Price Summary */
    .price-summary {
        background:var(--surface2); border:1px solid var(--border);
        border-radius:10px; padding:14px; margin-bottom:16px; display:none;
    }
    .price-summary.visible { display:block; }
    .ps-title { font-size:0.62rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--text3); margin-bottom:12px; }
    .ps-row { display:flex; justify-content:space-between; font-size:0.82rem; color:var(--text2); margin-bottom:8px; }
    .ps-total {
        display:flex; justify-content:space-between; font-size:1rem; font-weight:800; color:var(--text);
        padding-top:10px; border-top:1px solid var(--border); margin-top:4px;
    }
    .ps-total span:last-child {
        background:linear-gradient(90deg,var(--accent),var(--accent2));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }

    .info-box {
        background:var(--accentbg); border:1px solid rgba(0,212,255,0.1);
        border-radius:9px; padding:10px 13px; font-size:0.75rem; color:var(--text2);
        margin-bottom:16px; display:flex; gap:8px; align-items:flex-start;
    }
    .info-box i { color:var(--accent); margin-top:1px; flex-shrink:0; }

    .submit-btn {
        width:100%; padding:14px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border:none; border-radius:11px; color:#fff; font-family:'Outfit',sans-serif;
        font-size:0.88rem; font-weight:800; letter-spacing:0.04em;
        cursor:pointer; transition:all 0.22s; box-shadow:0 4px 16px var(--accentglow);
        display:flex; align-items:center; justify-content:center; gap:8px;
    }
    .submit-btn:hover { opacity:0.88; transform:translateY(-1px); box-shadow:0 6px 22px var(--accentglow); }
    .submit-btn:active { transform:scale(0.98); }
    .submit-btn:disabled { opacity:0.35; cursor:not-allowed; transform:none; background:var(--surface3); box-shadow:none; color:var(--text3); }

    .empty-cars { padding:48px 20px; text-align:center; color:var(--text3); }
    .empty-cars i { font-size:2.5rem; display:block; margin-bottom:14px; opacity:0.15; }

    @keyframes fadeUp { from{opacity:0;transform:translateY(14px);} to{opacity:1;transform:translateY(0);} }

    @media(max-width:960px) {
        .booking-grid { grid-template-columns:1fr; }
        .form-panel { position:static; }
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
        <li><a href="booking.php" class="active"><i class="fa fa-car"></i> Book a Car</a></li>
        <li><a href="user_booking.php"><i class="fa fa-calendar-check"></i> My Bookings</a></li>
        <li><a href="profile.php"><i class="fa fa-user"></i> Profile</a></li>
        <li><a href="change_password.php"><i class="fa fa-key"></i> Change Password</a></li>
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

<!-- MAIN -->
<div class="main">
    <div class="top-bar">
        <div class="tb-left">
            <h2>Book a Car</h2>
            <p id="dateLabel"></p>
        </div>
        <div class="tb-right">
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
                <h2>Reserve Your <span>Vehicle</span></h2>
                <p>Select a car, then pick your dates — already-booked slots are blocked automatically.</p>
            </div>
            <div class="ph-badge"><i class="fa fa-circle-check"></i> Instant Submission</div>
        </div>

        <!-- ALERTS -->
        <?php if ($msg): ?>
        <div class="alert alert-success">
            <i class="fa fa-circle-check"></i>
            <div><?php echo htmlspecialchars($msg); ?> &nbsp;<a href="user_booking.php">View My Bookings →</a></div>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- FORM -->
        <form method="POST" id="bookingForm">
        <div class="booking-grid">

            <!-- LEFT: Car List -->
            <div class="panel">
                <div class="panel-head">
                    <h3><i class="fa fa-car-side"></i> Available Vehicles</h3>
                    <span class="count"><?php echo ($cars_result ? $cars_result->num_rows : 0); ?> listed</span>
                </div>
                <div class="panel-body">
                <?php
                if ($cars_result && $cars_result->num_rows > 0):
                    $cars_result->data_seek(0);
                    while ($car = $cars_result->fetch_assoc()):
                        $cid    = intval($car['id']);
                        $cname  = htmlspecialchars($car['car_name']);
                        $ctype  = htmlspecialchars($car['car_type']);
                        $cmodel = htmlspecialchars($car['car_model'] ?? '');
                        $cprice = intval($car['price_per_day']);
                        $cseats = intval($car['seating_capacity'] ?? 4);
                        $cimg   = !empty($car['Vimage1'])
                            ? "../admin/img/vehicleimages/" . htmlspecialchars($car['Vimage1'])
                            : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=200&q=60";
                        $is_sel       = ($preselect_car_id === $cid);
                        $has_bookings = isset($booked_ranges[$cid]) && count($booked_ranges[$cid]) > 0;
                ?>
                <div class="car-card <?php echo $is_sel ? 'selected' : ''; ?>"
                     data-id="<?php echo $cid; ?>"
                     data-price="<?php echo $cprice; ?>"
                     data-name="<?php echo addslashes($cname); ?>"
                     onclick="selectCar(this)">

                    <input type="radio" name="car_id" value="<?php echo $cid; ?>"
                           <?php echo $is_sel ? 'checked' : ''; ?> style="display:none">

                    <div class="radio-dot"></div>

                    <img src="<?php echo $cimg; ?>" class="car-thumb" alt="<?php echo $cname; ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=200&q=60'">

                    <div class="car-info">
                        <div class="car-name-row">
                            <div>
                                <div class="car-name"><?php echo $cname; ?></div>
                                <div class="car-model"><?php echo $cmodel; ?></div>
                            </div>
                            <div class="car-price">LKR <?php echo number_format($cprice); ?><span>/day</span></div>
                        </div>
                        <div class="car-tags">
                            <span class="tag"><i class="fa fa-gas-pump"></i><?php echo $ctype; ?></span>
                            <span class="tag"><i class="fa fa-user"></i><?php echo $cseats; ?> Seats</span>
                            <?php if ($has_bookings): ?>
                                <span class="tag-booked"><i class="fa fa-calendar-xmark"></i>Has Bookings</span>
                            <?php else: ?>
                                <span class="tag-free"><i class="fa fa-calendar-check"></i>Fully Free</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile;
                else: ?>
                <div class="empty-cars">
                    <i class="fa fa-car"></i>
                    <p>No cars available right now.</p>
                </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Booking Form -->
            <div class="form-panel">
                <div class="form-panel-head">
                    <h3><i class="fa fa-calendar-days"></i> Booking Details</h3>
                    <p>Pick your dates to complete the reservation</p>
                </div>
                <div class="form-body">

                    <!-- No car hint -->
                    <div class="no-car-hint <?php echo $preselect_car_id ? 'hidden' : ''; ?>" id="noCarHint">
                        <i class="fa fa-arrow-left"></i>
                        <span>Select a vehicle from the list to continue.</span>
                    </div>

                    <!-- Selected Car Preview -->
                    <div id="selectedPreview" class="selected-preview <?php echo $preselect_car_id ? 'visible' : ''; ?>">
                        <div class="sp-label">Selected Vehicle</div>
                        <div class="sp-name" id="previewName">
                            <?php echo htmlspecialchars($preselect_name); ?>
                        </div>
                        <div class="sp-rate" id="previewRate">
                            <?php echo $preselect_price ? 'LKR ' . number_format($preselect_price) . ' / day' : ''; ?>
                        </div>
                    </div>

                    <!-- Booked Dates Notice (shown when a car with bookings is selected) -->
                    <div class="booked-notice" id="bookedNotice">
                        <i class="fa fa-triangle-exclamation"></i>
                        <div id="bookedNoticeText"></div>
                    </div>

                    <div class="field">
                        <label><i class="fa fa-calendar"></i> Pick-up Date</label>
                        <input type="date" name="from_date" id="from_date" required
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo htmlspecialchars($_POST['from_date'] ?? ''); ?>"
                               <?php echo !$preselect_car_id ? 'disabled' : ''; ?>>
                        <div class="date-conflict" id="fromConflict">
                            <i class="fa fa-circle-xmark"></i> This date falls within a reserved period.
                        </div>
                    </div>

                    <div class="field">
                        <label><i class="fa fa-calendar-check"></i> Return Date</label>
                        <input type="date" name="to_date" id="to_date" required
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               value="<?php echo htmlspecialchars($_POST['to_date'] ?? ''); ?>"
                               <?php echo !$preselect_car_id ? 'disabled' : ''; ?>>
                        <div class="date-conflict" id="toConflict">
                            <i class="fa fa-circle-xmark"></i> Your selected range overlaps a reserved period.
                        </div>
                    </div>

                    <!-- Price Summary -->
                    <div class="price-summary" id="priceSummary">
                        <div class="ps-title">Cost Estimate</div>
                        <div class="ps-row"><span>Daily Rate</span><span id="summaryRate">—</span></div>
                        <div class="ps-row"><span>Number of Days</span><span id="numDays">—</span></div>
                        <div class="ps-total"><span>Estimated Total</span><span id="totalPrice">—</span></div>
                    </div>

                    <div class="field">
                        <label><i class="fa fa-comment-dots"></i> Special Requests <span style="font-weight:400;text-transform:none;letter-spacing:0;">(Optional)</span></label>
                        <textarea name="message" rows="3"
                            placeholder="Any special requests or notes..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <div class="info-box">
                        <i class="fa fa-circle-info"></i>
                        <span>Booking will be reviewed and confirmed by our team. Dates reserved by others are automatically blocked.</span>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn"
                            <?php echo !$preselect_car_id ? 'disabled' : ''; ?>>
                        <i class="fa fa-check"></i> Confirm Booking
                    </button>
                </div>
            </div>

        </div>
        </form>

    </div>
</div>

<script>
// ── Booked date ranges from DB { car_id: [[from, to], ...] } ─────────────────
const BOOKED = <?php echo $booked_ranges_json; ?>;

// ── State — seeded from PHP so pre-selection is instant ──────────────────────
var selectedPrice = <?php echo $preselect_price ?: 0; ?>;
var selectedCarId = <?php echo $preselect_car_id ?: 0; ?>;

// ── Theme ─────────────────────────────────────────────────────────────────────
var theme = localStorage.getItem('cfyTheme') || 'dark';
document.documentElement.setAttribute('data-theme', theme);
syncIcon();
document.getElementById('themeBtn').addEventListener('click', function(){
    theme = theme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('cfyTheme', theme);
    syncIcon();
});
function syncIcon(){
    document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
}

// ── Live Date ─────────────────────────────────────────────────────────────────
(function(){
    var d=new Date(), D=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
    M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('dateLabel').textContent = D[d.getDay()]+', '+d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear();
})();

// ── Select a car ──────────────────────────────────────────────────────────────
function selectCar(el) {
    document.querySelectorAll('.car-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('input[name="car_id"]').forEach(r => r.checked = false);

    el.classList.add('selected');
    var id    = parseInt(el.dataset.id);
    var price = parseInt(el.dataset.price);
    var name  = el.dataset.name;
    el.querySelector('input[type="radio"]').checked = true;

    selectedPrice = price;
    selectedCarId = id;

    // Update preview panel
    document.getElementById('previewName').textContent = name;
    document.getElementById('previewRate').textContent  = 'LKR ' + price.toLocaleString() + ' / day';
    document.getElementById('selectedPreview').classList.add('visible');
    document.getElementById('noCarHint').classList.add('hidden');
    document.getElementById('summaryRate').textContent  = 'LKR ' + price.toLocaleString();

    // Enable date inputs + submit
    document.getElementById('from_date').disabled = false;
    document.getElementById('to_date').disabled   = false;
    document.getElementById('submitBtn').disabled = false;

    // Clear previous date values when switching cars
    document.getElementById('from_date').value = '';
    document.getElementById('to_date').value   = '';
    clearConflict();
    document.getElementById('priceSummary').classList.remove('visible');

    // Show reserved ranges for this car
    refreshBookedNotice(id);
}

// ── Show reserved date ranges in warning box ──────────────────────────────────
function refreshBookedNotice(carId) {
    var notice = document.getElementById('bookedNotice');
    var text   = document.getElementById('bookedNoticeText');
    var ranges = BOOKED[carId];
    if (ranges && ranges.length > 0) {
        var lines = ranges.map(function(r){
            return '📅 <strong>' + r[0] + '</strong> to <strong>' + r[1] + '</strong>';
        });
        text.innerHTML = '<strong>Already reserved on:</strong><br>' + lines.join('<br>') + '<br><small style="opacity:0.75;">Choose dates outside these periods.</small>';
        notice.classList.add('visible');
    } else {
        notice.classList.remove('visible');
    }
}

// ── Check if chosen range overlaps any booked period ─────────────────────────
function datesOverlap(carId, from, to) {
    var ranges = BOOKED[carId];
    if (!ranges || !ranges.length) return false;
    var f = new Date(from), t = new Date(to);
    for (var i = 0; i < ranges.length; i++) {
        var rf = new Date(ranges[i][0]), rt = new Date(ranges[i][1]);
        if (!(t < rf || f > rt)) return true;   // overlap found
    }
    return false;
}

function clearConflict() {
    document.getElementById('from_date').classList.remove('invalid');
    document.getElementById('to_date').classList.remove('invalid');
    document.getElementById('fromConflict').classList.remove('visible');
    document.getElementById('toConflict').classList.remove('visible');
}

// ── Update price summary + live overlap validation ────────────────────────────
function updateSummary() {
    var from    = document.getElementById('from_date').value;
    var to      = document.getElementById('to_date').value;
    var fromEl  = document.getElementById('from_date');
    var toEl    = document.getElementById('to_date');
    var summary = document.getElementById('priceSummary');
    var btn     = document.getElementById('submitBtn');

    // Keep return date min = pick-up + 1 day
    if (from) {
        var nextDay = new Date(new Date(from).getTime() + 86400000).toISOString().split('T')[0];
        toEl.min = nextDay;
        // If to is now before from, clear it
        if (to && to <= from) {
            toEl.value = '';
            to = '';
        }
    }

    clearConflict();
    summary.classList.remove('visible');

    if (!from || !to) { btn.disabled = false; return; }

    var days = Math.ceil((new Date(to) - new Date(from)) / 86400000);
    if (days <= 0) return;

    // Overlap check
    if (datesOverlap(selectedCarId, from, to)) {
        fromEl.classList.add('invalid');
        toEl.classList.add('invalid');
        document.getElementById('toConflict').classList.add('visible');
        btn.disabled = true;
        return;
    }

    // All good — show summary
    document.getElementById('numDays').textContent    = days + (days === 1 ? ' day' : ' days');
    document.getElementById('totalPrice').textContent = 'LKR ' + (days * selectedPrice).toLocaleString();
    document.getElementById('summaryRate').textContent = 'LKR ' + selectedPrice.toLocaleString();
    summary.classList.add('visible');
    btn.disabled = false;
}

document.getElementById('from_date').addEventListener('change', updateSummary);
document.getElementById('to_date').addEventListener('change',   updateSummary);

// ── Auto-init for pre-selected car (from ?car_id= or POST re-render) ──────────
(function(){
    var preselected = document.querySelector('.car-card.selected');
    if (preselected) {
        selectedPrice = parseInt(preselected.dataset.price);
        selectedCarId = parseInt(preselected.dataset.id);
        document.getElementById('summaryRate').textContent = 'LKR ' + selectedPrice.toLocaleString();
        refreshBookedNotice(selectedCarId);
        updateSummary();  // recalculate if POST re-rendered with dates
    }
})();
</script>
</body>
</html>