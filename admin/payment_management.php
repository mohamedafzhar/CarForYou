<?php
session_start();
include 'config.php';

if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
    header('Location: index.php'); exit();
}

date_default_timezone_set('Asia/Colombo');

$success_msg = '';
$error_msg   = '';

// ── MARK AS PAID ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'mark_paid') {
        $booking_id = intval($_POST['booking_id']);
        $stmt = $conn->prepare("SELECT b.*, c.price_per_day, c.car_name FROM booking b JOIN cars c ON c.id = b.car_id WHERE b.id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $bk = $stmt->get_result()->fetch_assoc();

        if ($bk) {
            $days    = max(1, (int)((strtotime($bk['to_date']) - strtotime($bk['from_date'])) / 86400));
            $amount  = $days * $bk['price_per_day'];
            $penalty = intval($bk['penalty_amount'] ?? 0);
            $total   = $amount + $penalty;
            $now     = date('Y-m-d H:i:s');
            $receipt = 'RCP' . strtoupper(substr(md5($booking_id . time()), 0, 8));

            $stmt2 = $conn->prepare("UPDATE booking SET payment_status='paid', payment_date=?, total_amount=? WHERE id=?");
            $stmt2->bind_param("sii", $now, $total, $booking_id);
            $stmt2->execute();

            $stmt3 = $conn->prepare("INSERT INTO payments (booking_id, user_email, car_id, amount, penalty, total, payment_status, payment_date, receipt_no) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, ?)");
            $stmt3->bind_param("isiiiiss", $booking_id, $bk['user_email'], $bk['car_id'], $amount, $penalty, $total, $now, $receipt);
            $stmt3->execute();

            $success_msg = "Payment marked as paid &mdash; Receipt No: <strong>$receipt</strong>";
        }
    }

    if ($_POST['action'] === 'mark_unpaid') {
        $booking_id = intval($_POST['booking_id']);
        $stmt = $conn->prepare("UPDATE booking SET payment_status='unpaid', payment_date=NULL, total_amount=NULL WHERE id=?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $success_msg = "Payment marked as unpaid.";
    }
}

// ── FILTERS ───────────────────────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$where  = "WHERE 1=1";
$params = []; $types = "";

if ($filter === 'paid')   $where .= " AND b.payment_status='paid'";
if ($filter === 'unpaid') $where .= " AND b.payment_status='unpaid'";
if ($search !== '') {
    $like = "%$search%";
    $where .= " AND (b.user_email LIKE ? OR c.car_name LIKE ? OR u.full_name LIKE ?)";
    $params = [$like, $like, $like]; $types = "sss";
}

$sql = "SELECT b.id, b.user_email, b.from_date, b.to_date, b.status,
               b.payment_status, b.payment_date, b.total_amount, b.penalty_amount,
               c.car_name, c.car_model, c.price_per_day, c.Vimage1,
               u.full_name
        FROM booking b
        JOIN cars c ON c.id = b.car_id
        LEFT JOIN users u ON u.email = b.user_email
        $where ORDER BY b.id DESC";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();

// ── STATS ─────────────────────────────────────────────────────
$s = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN payment_status='paid'   THEN 1 ELSE 0 END) AS paid,
    SUM(CASE WHEN payment_status='unpaid' THEN 1 ELSE 0 END) AS unpaid,
    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END),0) AS revenue
    FROM booking")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Management | CarForYou Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root { --sw:268px; --tr:0.35s cubic-bezier(0.4,0,0.2,1); }
[data-theme="dark"] {
    --bg:#0d1117; --bg2:#131920; --surface:#1e2738; --surface2:#253044;
    --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.16);
    --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
    --accent:#4f8ef7; --accent2:#7db0fb; --glow:rgba(79,142,247,0.22);
    --sbg:#0a1020; --sborder:rgba(79,142,247,0.1);
    --cshadow:0 4px 24px rgba(0,0,0,0.35); --hbg:rgba(13,17,23,0.9);
    --green:#22c55e; --greenbg:rgba(34,197,94,0.1); --greenglow:rgba(34,197,94,0.2);
    --amber:#f59e0b; --amberbg:rgba(245,158,11,0.1);
    --red:#ef4444; --redbg:rgba(239,68,68,0.1);
}
[data-theme="light"] {
    --bg:#f0f4f8; --bg2:#e8edf3; --surface:#ffffff; --surface2:#f5f7fa;
    --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
    --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
    --accent:#2563eb; --accent2:#3b82f6; --glow:rgba(37,99,235,0.16);
    --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06);
    --cshadow:0 4px 20px rgba(28,43,58,0.08); --hbg:rgba(240,244,248,0.92);
    --green:#059669; --greenbg:rgba(5,150,105,0.08); --greenglow:rgba(5,150,105,0.18);
    --amber:#d97706; --amberbg:rgba(217,119,6,0.08);
    --red:#dc2626; --redbg:rgba(220,38,38,0.08);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background var(--tr),color var(--tr);}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--bg)}::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px}
a{text-decoration:none;color:inherit;}

/* SIDEBAR */
.sidebar{width:var(--sw);min-height:100vh;background:var(--sbg);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;border-right:1px solid var(--sborder);z-index:100;overflow-y:auto;transition:background var(--tr);}
.sb-brand{padding:28px 24px 20px;border-bottom:1px solid var(--sborder);}
.sb-brand h2{font-family:'Syne',sans-serif;font-size:1.45rem;font-weight:800;color:#e8edf5;letter-spacing:0.01em;}
.sb-brand h2 span{color:var(--accent);}
.sb-brand p{font-size:0.68rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:rgba(232,237,245,0.3);margin-top:4px;}
.sb-section{font-size:0.62rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:rgba(232,237,245,0.25);padding:22px 24px 6px;}
.sb-menu{list-style:none;padding:6px 12px;}
.sb-menu li{margin-bottom:2px;}
.sb-menu li a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;font-size:0.86rem;font-weight:500;color:rgba(232,237,245,0.5);transition:all 0.2s;}
.sb-menu li a i{width:18px;text-align:center;font-size:0.85rem;}
.sb-menu li:hover a{background:rgba(79,142,247,0.09);color:rgba(232,237,245,0.88);}
.sb-menu li.active a{background:linear-gradient(90deg,rgba(79,142,247,0.2),rgba(79,142,247,0.05));color:var(--accent);font-weight:600;box-shadow:inset 3px 0 0 var(--accent);}
.sb-divider{height:1px;background:var(--sborder);margin:10px 0;}

/* MAIN */
.main{margin-left:var(--sw);width:calc(100% - var(--sw));min-height:100vh;display:flex;flex-direction:column;}
.top-bar{position:sticky;top:0;z-index:50;background:var(--hbg);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 36px;height:66px;display:flex;align-items:center;justify-content:space-between;transition:background var(--tr);}
.tb-left h2{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:var(--text);letter-spacing:-0.01em;}
.tb-left p{font-size:0.73rem;color:var(--text2);margin-top:1px;}
.tb-right{display:flex;align-items:center;gap:10px;}
.theme-btn{width:37px;height:37px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.88rem;transition:all 0.2s;}
.theme-btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 10px var(--glow);}
.admin-pill{display:flex;align-items:center;gap:9px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:6px 13px;}
.av{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:800;color:#fff;}
.aname{font-size:0.82rem;font-weight:600;color:var(--text);}
.arole{font-size:0.68rem;color:var(--text2);}

.body{padding:26px 36px;flex:1;}

/* ALERTS */
.alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:0.84rem;font-weight:500;margin-bottom:20px;animation:fadeUp 0.3s ease;}
.alert-success{background:var(--greenbg);color:var(--green);border:1px solid rgba(34,197,94,0.2);}
.alert-error{background:var(--redbg);color:var(--red);border:1px solid rgba(239,68,68,0.2);}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.sc{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:20px;display:flex;flex-direction:column;gap:14px;position:relative;overflow:hidden;opacity:0;animation:fadeUp 0.5s ease forwards;transition:transform 0.25s,box-shadow 0.25s;}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),var(--accent2));transform:scaleX(0);transform-origin:left;transition:transform 0.35s ease;}
.sc:hover{transform:translateY(-3px);box-shadow:var(--cshadow);}
.sc:hover::before{transform:scaleX(1);}
.sc:nth-child(1){animation-delay:0.05s}.sc:nth-child(2){animation-delay:0.10s}.sc:nth-child(3){animation-delay:0.15s}.sc:nth-child(4){animation-delay:0.20s}
.sc-row{display:flex;justify-content:space-between;align-items:flex-start;}
.sc-num{font-family:'Syne',sans-serif;font-size:1.75rem;font-weight:800;color:var(--text);line-height:1;}
.sc-lbl{font-size:0.7rem;font-weight:600;letter-spacing:0.07em;text-transform:uppercase;color:var(--text3);margin-top:3px;}
.sc-icon{width:40px;height:40px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.95rem;transition:all 0.25s;}
.sc:hover .sc-icon{color:#fff;box-shadow:0 4px 14px var(--glow);}

/* TOOLBAR */
.toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap;opacity:0;animation:fadeUp 0.5s ease 0.15s forwards;}
.toolbar-left{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.toolbar-left i{color:var(--accent);}
.toolbar-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.filter-tabs{display:flex;background:var(--surface2);border:1px solid var(--border);border-radius:9px;overflow:hidden;}
.filter-tab{padding:7px 14px;font-size:0.78rem;font-weight:600;color:var(--text2);cursor:pointer;border:none;background:none;font-family:'DM Sans',sans-serif;transition:all 0.2s;}
.filter-tab.active{background:var(--accent);color:#fff;}
.filter-tab:hover:not(.active){background:var(--surface);color:var(--text);}
.search-wrap{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:7px 12px;}
.search-wrap i{color:var(--text3);font-size:0.8rem;}
.search-wrap input{background:none;border:none;outline:none;font-family:'DM Sans',sans-serif;font-size:0.84rem;color:var(--text);width:190px;}
.search-wrap input::placeholder{color:var(--text3);}
.search-btn{padding:7px 14px;background:var(--accent);color:#fff;border:none;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:0.78rem;font-weight:600;cursor:pointer;transition:opacity 0.2s;}
.search-btn:hover{opacity:0.85;}

/* TABLE CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:13px;overflow:hidden;opacity:0;animation:fadeUp 0.5s ease 0.22s forwards;}
.card-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);}
.card-head h3{font-family:'Syne',sans-serif;font-size:0.92rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.card-head h3 i{color:var(--accent);font-size:0.82rem;}
.record-count{font-size:0.78rem;color:var(--text3);}

table{width:100%;border-collapse:collapse;}
th{font-size:0.66rem;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:var(--text3);padding:0 16px 12px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:13px 16px;font-size:0.845rem;color:var(--text2);border-bottom:1px solid var(--border);transition:background 0.15s;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(79,142,247,0.04);}

.car-cell{display:flex;align-items:center;gap:10px;}
.car-thumb{width:52px;height:34px;border-radius:7px;object-fit:cover;background:var(--surface2);border:1px solid var(--border);flex-shrink:0;}
.car-name{font-weight:700;color:var(--text);font-size:0.84rem;}
.car-model{font-size:0.7rem;color:var(--text3);margin-top:1px;}

.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap;}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
.badge-paid{background:var(--greenbg);color:var(--green);}
.badge-unpaid{background:var(--amberbg);color:var(--amber);}
.badge-confirmed{background:rgba(79,142,247,0.12);color:var(--accent);}
.badge-pending{background:var(--amberbg);color:var(--amber);}
.badge-cancelled{background:var(--redbg);color:var(--red);}

.amount-num{font-family:'Syne',sans-serif;font-weight:700;color:var(--text);}
.penalty-tag{display:inline-block;font-size:0.68rem;color:var(--red);background:var(--redbg);padding:1px 6px;border-radius:4px;margin-top:2px;}

.btn-action{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;font-size:0.74rem;font-weight:600;border:1px solid transparent;cursor:pointer;transition:all 0.2s;font-family:'DM Sans',sans-serif;}
.btn-pay{color:var(--green);border-color:rgba(34,197,94,0.3);background:var(--greenbg);}
.btn-pay:hover{background:var(--green);color:#fff;}
.btn-unp{color:var(--amber);border-color:rgba(245,158,11,0.3);background:var(--amberbg);}
.btn-unp:hover{background:var(--amber);color:#fff;}
.btn-rec{color:var(--accent);border-color:rgba(79,142,247,0.3);background:rgba(79,142,247,0.08);}
.btn-rec:hover{background:var(--accent);color:#fff;}

.empty-row td{text-align:center;padding:44px;color:var(--text3);font-size:0.84rem;}
.empty-row td i{display:block;font-size:2.2rem;margin-bottom:12px;opacity:0.15;}

/* RECEIPT MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-overlay.open{display:flex;}
.modal{background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:100%;max-width:460px;overflow:hidden;animation:popIn 0.3s cubic-bezier(0.22,1,0.36,1);}
@keyframes popIn{from{transform:scale(0.92);opacity:0}to{transform:scale(1);opacity:1}}
.modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.modal-header h3{font-family:'Syne',sans-serif;font-size:0.95rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.modal-header h3 i{color:var(--accent);}
.modal-close{width:30px;height:30px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.8rem;transition:all 0.2s;}
.modal-close:hover{border-color:var(--red);color:var(--red);}
.modal-body{padding:24px;}
.receipt-top{text-align:center;padding-bottom:20px;border-bottom:1px dashed var(--border2);margin-bottom:20px;}
.receipt-logo{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:var(--text);}
.receipt-logo span{color:var(--accent);}
.receipt-no{font-size:0.72rem;color:var(--text3);letter-spacing:0.1em;margin-top:4px;}
.r-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);font-size:0.84rem;}
.r-row:last-child{border-bottom:none;}
.r-row .rl{color:var(--text2);}
.r-row .rv{font-weight:600;color:var(--text);}
.r-total{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:var(--surface2);border-radius:9px;margin-top:16px;}
.r-total .rl{font-size:0.84rem;color:var(--text2);}
.r-total .rv{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800;color:var(--green);}
.btn-print{width:100%;padding:11px;margin-top:16px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity 0.2s;}
.btn-print:hover{opacity:0.88;}

@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media print{.sidebar,.top-bar,.toolbar,.card-head,.btn-print{display:none!important;}.main{margin-left:0;width:100%;}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sb-brand">
        <h2>Car<span>ForYou</span></h2>
        <p>Admin Console</p>
    </div>
    <div class="sb-section">Main Menu</div>
    <ul class="sb-menu">
        <li><a href="admin_dashboard.php"><i class="fa fa-table-columns"></i> Dashboard</a></li>
        <li><a href="car.php"><i class="fa fa-car"></i> Cars</a></li>
        <li><a href="bookings.php"><i class="fa fa-calendar-check"></i> Bookings</a></li>
        <li><a href="reg-users.php"><i class="fa fa-users"></i> Registered Users</a></li>
        <li class="sb-divider"></li>
    </ul>
    <div class="sb-section">Finance & Operations</div>
    <ul class="sb-menu">
        <li class="active"><a href="payment_management.php"><i class="fa fa-credit-card"></i> Payments</a></li>
        <li><a href="car_returns.php"><i class="fa fa-rotate-left"></i> Car Returns</a></li>
        <li class="sb-divider"></li>
    </ul>
    <div class="sb-section">Content</div>
    <ul class="sb-menu">
        <li><a href="testimonials.php"><i class="fa fa-comments"></i> Testimonials</a></li>
        <li><a href="contactus.php"><i class="fa fa-envelope"></i> Contact Queries</a></li>
        <li class="sb-divider"></li>
        <li><a href="logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<!-- MAIN -->
<div class="main">
    <div class="top-bar">
        <div class="tb-left">
            <h2>Payment Management</h2>
            <p id="dateLabel"></p>
        </div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn"><i class="fa fa-moon" id="themeIcon"></i></button>
            <div class="admin-pill">
                <div class="av"><?php echo strtoupper(substr($_SESSION['alogin'] ?? 'A', 0, 1)); ?></div>
                <div>
                    <div class="aname"><?php echo htmlspecialchars($_SESSION['alogin'] ?? 'Admin'); ?></div>
                    <div class="arole">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <div class="body">

        <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="sc">
                <div class="sc-row">
                    <div><div class="sc-num"><?php echo $s['total']; ?></div><div class="sc-lbl">Total Bookings</div></div>
                    <div class="sc-icon" style="background:rgba(79,142,247,0.12);color:var(--accent)"><i class="fa fa-receipt"></i></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-row">
                    <div><div class="sc-num"><?php echo $s['paid']; ?></div><div class="sc-lbl">Paid</div></div>
                    <div class="sc-icon" style="background:var(--greenbg);color:var(--green)"><i class="fa fa-circle-check"></i></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-row">
                    <div><div class="sc-num"><?php echo $s['unpaid']; ?></div><div class="sc-lbl">Unpaid</div></div>
                    <div class="sc-icon" style="background:var(--amberbg);color:var(--amber)"><i class="fa fa-clock"></i></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-row">
                    <div><div class="sc-num">Rs <?php echo number_format($s['revenue']); ?></div><div class="sc-lbl">Total Revenue</div></div>
                    <div class="sc-icon" style="background:var(--greenbg);color:var(--green)"><i class="fa fa-coins"></i></div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="toolbar-left"><i class="fa fa-credit-card"></i> All Payments</div>
            <div class="toolbar-right">
                <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <div class="filter-tabs">
                        <button type="submit" name="status" value="all"    class="filter-tab <?php echo $filter==='all'   ?'active':'';?>">All</button>
                        <button type="submit" name="status" value="unpaid" class="filter-tab <?php echo $filter==='unpaid'?'active':'';?>">Unpaid</button>
                        <button type="submit" name="status" value="paid"   class="filter-tab <?php echo $filter==='paid'  ?'active':'';?>">Paid</button>
                    </div>
                    <div class="search-wrap">
                        <i class="fa fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Email, car, or name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="search-btn"><i class="fa fa-search"></i> Search</button>
                    <?php if ($search || $filter!=='all'): ?>
                    <a href="payment_management.php" style="font-size:0.78rem;color:var(--text3);"><i class="fa fa-xmark"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-table"></i> Booking Payments</h3>
                <span class="record-count"><?php echo $bookings->num_rows; ?> record(s)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Car</th>
                        <th>Rental Period</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Booking Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($bookings && $bookings->num_rows > 0):
                    while ($b = $bookings->fetch_assoc()):
                        $days    = max(1, (int)((strtotime($b['to_date']) - strtotime($b['from_date'])) / 86400));
                        $amount  = $days * $b['price_per_day'];
                        $penalty = intval($b['penalty_amount'] ?? 0);
                        $total   = $b['total_amount'] ?? ($amount + $penalty);
                        $img     = !empty($b['Vimage1']) ? "img/vehicleimages/".htmlspecialchars($b['Vimage1']) : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=60&q=60";
                        $bstatus = intval($b['status']);
                        if ($bstatus===1){ $bc='badge-confirmed'; $bl='Confirmed'; }
                        elseif($bstatus===2){ $bc='badge-cancelled'; $bl='Cancelled'; }
                        else { $bc='badge-pending'; $bl='Pending'; }
                        $paid = ($b['payment_status'] === 'paid');
                        $rd = [
                            'id'      => $b['id'],
                            'name'    => $b['full_name'] ?? $b['user_email'],
                            'email'   => $b['user_email'],
                            'car'     => $b['car_name'].' '.$b['car_model'],
                            'from'    => date('d M Y', strtotime($b['from_date'])),
                            'to'      => date('d M Y', strtotime($b['to_date'])),
                            'days'    => $days,
                            'ppd'     => $b['price_per_day'],
                            'amount'  => $amount,
                            'penalty' => $penalty,
                            'total'   => $total,
                            'date'    => $b['payment_date'] ? date('d M Y, H:i', strtotime($b['payment_date'])) : '—',
                        ];
                ?>
                <tr>
                    <td style="font-weight:700;color:var(--text);">#<?php echo $b['id']; ?></td>
                    <td>
                        <div style="font-weight:600;color:var(--text);font-size:0.84rem;"><?php echo htmlspecialchars($b['full_name'] ?? '—'); ?></div>
                        <div style="font-size:0.72rem;color:var(--text3);"><?php echo htmlspecialchars($b['user_email']); ?></div>
                    </td>
                    <td>
                        <div class="car-cell">
                            <img src="<?php echo $img; ?>" class="car-thumb" alt="car"
                                 onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=60&q=60'">
                            <div>
                                <div class="car-name"><?php echo htmlspecialchars($b['car_name']); ?></div>
                                <div class="car-model"><?php echo htmlspecialchars($b['car_model']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:0.82rem;color:var(--text);"><?php echo date('d M', strtotime($b['from_date'])); ?> → <?php echo date('d M Y', strtotime($b['to_date'])); ?></div>
                        <div style="font-size:0.72rem;color:var(--text3);"><?php echo $days; ?> day<?php echo $days>1?'s':'';?> × Rs <?php echo number_format($b['price_per_day']); ?></div>
                    </td>
                    <td>
                        <div class="amount-num">Rs <?php echo number_format($total); ?></div>
                        <?php if ($penalty > 0): ?><div class="penalty-tag">+Rs <?php echo number_format($penalty); ?> penalty</div><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo $paid?'badge-paid':'badge-unpaid'; ?>"><?php echo $paid?'Paid':'Unpaid'; ?></span>
                        <?php if ($paid && $b['payment_date']): ?>
                        <div style="font-size:0.7rem;color:var(--text3);margin-top:3px;"><?php echo date('d M Y', strtotime($b['payment_date'])); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $bc; ?>"><?php echo $bl; ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if (!$paid): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Mark booking #<?php echo $b['id']; ?> as paid?')">
                                <input type="hidden" name="action" value="mark_paid">
                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                <button type="submit" class="btn-action btn-pay"><i class="fa fa-check"></i> Mark Paid</button>
                            </form>
                        <?php else: ?>
                            <button class="btn-action btn-rec" onclick='showReceipt(<?php echo json_encode($rd); ?>)'>
                                <i class="fa fa-file-invoice"></i> Receipt
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Mark as unpaid?')">
                                <input type="hidden" name="action" value="mark_unpaid">
                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                <button type="submit" class="btn-action btn-unp"><i class="fa fa-rotate-left"></i> Unpaid</button>
                            </form>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr class="empty-row"><td colspan="8"><i class="fa fa-receipt"></i>No payment records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RECEIPT MODAL -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa fa-file-invoice"></i> Payment Receipt</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="receiptBody"></div>
    </div>
</div>

<script>
    (function(){ var d=new Date(),days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear(); })();

    var theme = localStorage.getItem('adminTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme); syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme==='dark'?'light':'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('adminTheme', theme); syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }

    function showReceipt(d) {
        document.getElementById('receiptBody').innerHTML =
            '<div class="receipt-top">' +
                '<div class="receipt-logo">Car<span>ForYou</span></div>' +
                '<div class="receipt-no">BOOKING #' + d.id + ' &nbsp;|&nbsp; ' + d.date + '</div>' +
            '</div>' +
            '<div class="r-row"><span class="rl">Customer</span><span class="rv">' + d.name + '</span></div>' +
            '<div class="r-row"><span class="rl">Email</span><span class="rv">' + d.email + '</span></div>' +
            '<div class="r-row"><span class="rl">Car</span><span class="rv">' + d.car + '</span></div>' +
            '<div class="r-row"><span class="rl">Period</span><span class="rv">' + d.from + ' → ' + d.to + '</span></div>' +
            '<div class="r-row"><span class="rl">Duration</span><span class="rv">' + d.days + ' day(s) × Rs ' + Number(d.ppd).toLocaleString() + '</span></div>' +
            '<div class="r-row"><span class="rl">Base Amount</span><span class="rv">Rs ' + Number(d.amount).toLocaleString() + '</span></div>' +
            (d.penalty > 0 ? '<div class="r-row"><span class="rl" style="color:var(--red)">Late Penalty</span><span class="rv" style="color:var(--red)">Rs ' + Number(d.penalty).toLocaleString() + '</span></div>' : '') +
            '<div class="r-total"><span class="rl">Total Paid</span><span class="rv">Rs ' + Number(d.total).toLocaleString() + '</span></div>' +
            '<button class="btn-print" onclick="window.print()"><i class="fa fa-print"></i> Print Receipt</button>';
        document.getElementById('receiptModal').classList.add('open');
    }
    function closeModal() { document.getElementById('receiptModal').classList.remove('open'); }
    document.getElementById('receiptModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
</script>
</body>
</html>