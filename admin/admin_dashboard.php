<?php
session_start();
include 'config.php';
adminAuth();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

$admin_id = $_SESSION['admin_id'] ?? 0;

// Fetch admin profile picture
$admin_pic = '';
if ($admin_id) {
    $stmt = $conn->prepare("SELECT profile_picture FROM admin WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $admin_pic = $row['profile_picture'] ?? '';
    }
    $stmt->close();
}
$has_admin_pic = !empty($admin_pic);
$admin_pic_path = $has_admin_pic ? '../' . $admin_pic : '';

// AJAX: Mark notification as read
if (isset($_GET['mark_read']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $notif_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND admin_id IS NULL");
    $stmt->bind_param("i", $notif_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
    exit();
}

// AJAX: Mark all notifications as read
if (isset($_GET['mark_all_read']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE admin_id IS NULL OR admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    $stmt->close();
    exit();
}

// AJAX: Get unread count
if (isset($_GET['get_unread_count']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0 AND (admin_id IS NULL OR admin_id = ?)");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode(['count' => $result['cnt'] ?? 0]);
    $stmt->close();
    exit();
}

// AJAX: Get notifications
if (isset($_GET['get_notifications']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE admin_id IS NULL OR admin_id = ?
        ORDER BY is_read ASC, created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifs = [];
    while ($row = $result->fetch_assoc()) {
        $notifs[] = $row;
    }
    echo json_encode($notifs);
    $stmt->close();
    exit();
}

if (isset($_GET['eid'])) {
    $eid = intval($_GET['eid']);
    $status = 1;
    $stmt = $conn->prepare("UPDATE testimonials SET status=? WHERE id=?");
    $stmt->bind_param("ii", $status, $eid);
    $stmt->execute();
}
if (isset($_GET['aeid'])) {
    $aeid = intval($_GET['aeid']);
    $status = 0;
    $stmt = $conn->prepare("UPDATE testimonials SET status=? WHERE id=?");
    $stmt->bind_param("ii", $status, $aeid);
    $stmt->execute();
}

$user_count        = $conn->query("SELECT id FROM users")->num_rows;
$cars_count        = $conn->query("SELECT id FROM cars")->num_rows;
$booking_count     = $conn->query("SELECT id FROM booking")->num_rows;
$testimonial_count = $conn->query("SELECT id FROM testimonials")->num_rows;
$query_count       = $conn->query("SELECT id FROM contact_us")->num_rows;

// Payments count
$payment_res   = $conn->query("SHOW TABLES LIKE 'payment_management'");
$payment_count = 0;
if ($payment_res && $payment_res->num_rows > 0) {
    $payment_count = $conn->query("SELECT id FROM payment_management")->num_rows;
} else {
    $pr = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE payment_status='paid'");
    if ($pr) $payment_count = $pr->fetch_assoc()['c'] ?? 0;
}

// Car returns count
$returns_res   = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE return_status='returned'");
$returns_count = $returns_res ? $returns_res->fetch_assoc()['c'] : 0;

// Booking status counts
$pending_count = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE status = 0")->fetch_assoc()['c'] ?? 0;
$awaiting_count = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE status = 'awaiting_payment'")->fetch_assoc()['c'] ?? 0;
$confirmed_count = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE status = 'confirmed'")->fetch_assoc()['c'] ?? 0;
$cancelled_count = $conn->query("SELECT COUNT(*) AS c FROM booking WHERE status = 'cancelled'")->fetch_assoc()['c'] ?? 0;

// Total unread notifications
$unread_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0 AND (admin_id IS NULL OR admin_id = ?)");
$unread_stmt->bind_param("i", $admin_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$unread_stmt->close();

// GET REVENUE REPORT using Stored Procedure with Cursor
$revenue_report = null;
$result = $conn->query("CALL generate_revenue_report()");
if ($result) {
    $revenue_report = $result->fetch_assoc();
    mysqli_free_result($result);
    while ($conn->more_results()) {
        $conn->next_result();
        $extraResult = $conn->use_result();
        if ($extraResult) {
            $extraResult->free();
        }
    }
}

// GET TOP CUSTOMERS using Subquery
$top_customers = [];
$sql = "SELECT 
    u.id, u.full_name, u.email,
    COUNT(b.id) AS total_bookings,
    COALESCE(SUM(b.total_amount), 0) AS total_spent
FROM users u
LEFT JOIN booking b ON u.id = b.user_id
GROUP BY u.id, u.full_name, u.email
HAVING total_bookings > 0
ORDER BY total_spent DESC
LIMIT 5";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $top_customers[] = $row;
    }
}

// GET MOST POPULAR CAR using Subquery
$popular_car = null;
$sql = "SELECT c.car_name, c.car_model, COUNT(b.id) AS booking_count
FROM cars c
JOIN booking b ON c.id = b.car_id
GROUP BY c.id, c.car_name, c.car_model
ORDER BY booking_count DESC
LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    $popular_car = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Admin Dashboard | CarForYou</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root { --sw: 268px; --tr: 0.35s cubic-bezier(0.4,0,0.2,1); }

        [data-theme="dark"] {
            --bg:#0d1117; --bg2:#131920; --surface:#1e2738; --surface2:#253044;
            --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.16);
            --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
            --accent:#4f8ef7; --accent2:#7db0fb; --glow:rgba(79,142,247,0.22);
            --sbg:#0a1020; --sborder:rgba(79,142,247,0.1);
            --cshadow:0 4px 24px rgba(0,0,0,0.35); --hbg:rgba(13,17,23,0.9);
        }
        [data-theme="light"] {
            --bg:#f0f4f8; --bg2:#e8edf3; --surface:#ffffff; --surface2:#f5f7fa;
            --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
            --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
            --accent:#2563eb; --accent2:#3b82f6; --glow:rgba(37,99,235,0.16);
            --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06);
            --cshadow:0 4px 20px rgba(28,43,58,0.08); --hbg:rgba(240,244,248,0.92);
        }

        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        html{font-size:16px;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background var(--tr),color var(--tr);}
        ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:var(--bg);} ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px;}
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
        .sb-menu li.active a i{color:var(--accent);}
        .sb-divider{height:1px;background:var(--sborder);margin:10px 0;}

        /* MAIN */
        .main{margin-left:var(--sw);width:calc(100% - var(--sw));min-height:100vh;display:flex;flex-direction:column;}

        /* TOPBAR */
        .top-bar{position:sticky;top:0;z-index:50;background:var(--hbg);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 36px;height:66px;display:flex;align-items:center;justify-content:space-between;transition:background var(--tr);}
        .tb-left h2{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:var(--text);letter-spacing:-0.01em;}
        .tb-left p{font-size:0.73rem;color:var(--text2);margin-top:1px;}
        .tb-right{display:flex;align-items:center;gap:10px;}
        .theme-btn{width:37px;height:37px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.88rem;transition:all 0.2s;}
        .theme-btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 10px var(--glow);}
        .admin-pill{display:flex;align-items:center;gap:9px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:6px 13px;cursor:pointer;position:relative;}
        .av{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:800;color:#fff;overflow:hidden;}
        .av img{width:100%;height:100%;object-fit:cover;border-radius:7px;}
        .admin-pill .name{font-size:0.82rem;font-weight:600;color:var(--text);}
        .admin-pill .role{font-size:0.68rem;color:var(--text2);}
        .admin-pic-overlay{position:absolute;top:0;left:0;right:0;bottom:0;border-radius:9px;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s;color:#fff;font-size:0.7rem;}
        .admin-pill:hover .admin-pic-overlay{opacity:1;}

        /* NOTIFICATION BELL */
        .notif-btn{position:relative;width:37px;height:37px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.88rem;transition:all 0.2s;}
        .notif-btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 10px var(--glow);}
        .notif-btn.has-notif{color:var(--accent);}
        .notif-badge{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;background:#ef4444;border-radius:9px;font-size:0.65rem;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;padding:0 4px;animation:badgePulse 2s infinite;}
        @keyframes badgePulse{0%,100%{transform:scale(1);}50%{transform:scale(1.1);}}
        .notif-dropdown{position:absolute;top:54px;right:0;width:360px;background:var(--surface);border:1px solid var(--border2);border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,0.4);display:none;z-index:200;overflow:hidden;}
        .notif-dropdown.open{display:block;animation:dropdownIn 0.3s ease;}
        @keyframes dropdownIn{from{opacity:0;transform:translateY(-10px) scale(0.95);}to{opacity:1;transform:translateY(0) scale(1);}}
        .notif-header{padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--surface),var(--surface2));}
        .notif-header h4{font-family:'Syne',sans-serif;font-size:0.9rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
        .notif-header h4 i{color:var(--accent);}
        .notif-mark-all{font-size:0.72rem;color:var(--accent);cursor:pointer;transition:opacity 0.2s;border:none;background:none;font-family:'DM Sans',sans-serif;font-weight:600;}
        .notif-mark-all:hover{opacity:0.7;}
        .notif-list{max-height:380px;overflow-y:auto;}
        .notif-item{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);transition:all 0.2s;cursor:pointer;text-decoration:none;color:inherit;}
        .notif-item:last-child{border-bottom:none;}
        .notif-item:hover{background:rgba(79,142,247,0.04);}
        .notif-item.unread{background:rgba(79,142,247,0.06);border-left:3px solid var(--accent);}
        .notif-item.unread:hover{background:rgba(79,142,247,0.1);}
        .notif-icon-wrap{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0;}
        .notif-icon-booking{background:rgba(79,142,247,0.12);color:var(--accent);}
        .notif-icon-payment{background:rgba(34,197,94,0.12);color:#22c55e;}
        .notif-icon-warning{background:rgba(245,158,11,0.12);color:#f59e0b;}
        .notif-icon-error{background:rgba(239,68,68,0.12);color:#ef4444;}
        .notif-content{flex:1;min-width:0;}
        .notif-title{font-size:0.82rem;font-weight:600;color:var(--text);margin-bottom:3px;line-height:1.3;}
        .notif-message{font-size:0.75rem;color:var(--text2);line-height:1.4;margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .notif-meta{display:flex;align-items:center;gap:8px;}
        .notif-time{font-size:0.68rem;color:var(--text3);}
        .notif-status{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
        .notif-status.unread{background:var(--accent);}
        .notif-status.read{background:var(--border2);}
        .notif-empty{padding:40px 20px;text-align:center;}
        .notif-empty i{font-size:2.5rem;color:var(--text3);display:block;margin-bottom:12px;opacity:0.4;}
        .notif-empty p{font-size:0.85rem;color:var(--text3);}
        .notif-footer{padding:12px 18px;border-top:1px solid var(--border);text-align:center;}
        .notif-footer a{display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:var(--accent);transition:gap 0.2s;}
        .notif-footer a:hover{gap:10px;}

        /* BODY */
        .body{padding:26px 36px;flex:1;}

        /* STAT CARDS — 7 columns now */
        .stats{display:grid;grid-template-columns:repeat(7,1fr);gap:12px;margin-bottom:24px;}

        .sc{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:18px;display:flex;flex-direction:column;gap:12px;position:relative;overflow:hidden;cursor:pointer;transition:transform 0.25s,box-shadow 0.25s,border-color 0.25s;opacity:0;animation:fadeUp 0.5s ease forwards;}
        .sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),var(--accent2));transform:scaleX(0);transform-origin:left;transition:transform 0.35s ease;}
        .sc:hover{transform:translateY(-4px);box-shadow:var(--cshadow),0 0 0 1px var(--border2);}
        .sc:hover::before{transform:scaleX(1);}
        .sc-row{display:flex;justify-content:space-between;align-items:flex-start;}
        .sc-num{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;color:var(--text);line-height:1;}
        .sc-lbl{font-size:0.68rem;font-weight:600;letter-spacing:0.07em;text-transform:uppercase;color:var(--text3);margin-top:3px;}
        .sc-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.88rem;background:var(--glow);color:var(--accent);transition:all 0.25s;}
        .sc:hover .sc-icon{background:var(--accent);color:#fff;box-shadow:0 4px 14px var(--glow);}

        /* Custom icon colours per card */
        .sc-green .sc-icon{background:rgba(34,197,94,0.12);color:#22c55e;}
        .sc:hover .sc-green .sc-icon,.sc-green:hover .sc-icon{background:#22c55e;color:#fff;box-shadow:0 4px 14px rgba(34,197,94,0.3);}
        .sc-amber .sc-icon{background:rgba(245,158,11,0.12);color:#f59e0b;}
        .sc:hover .sc-amber .sc-icon,.sc-amber:hover .sc-icon{background:#f59e0b;color:#fff;box-shadow:0 4px 14px rgba(245,158,11,0.3);}
        .sc-purple .sc-icon{background:rgba(167,139,250,0.12);color:#a78bfa;}
        .sc:hover .sc-purple .sc-icon,.sc-purple:hover .sc-icon{background:#a78bfa;color:#fff;box-shadow:0 4px 14px rgba(167,139,250,0.3);}
        .sc-rose .sc-icon{background:rgba(251,113,133,0.12);color:#fb7185;}
        .sc:hover .sc-rose .sc-icon,.sc-rose:hover .sc-icon{background:#fb7185;color:#fff;box-shadow:0 4px 14px rgba(251,113,133,0.3);}

        /* stagger */
        .sc:nth-child(1){animation-delay:0.04s} .sc:nth-child(2){animation-delay:0.08s}
        .sc:nth-child(3){animation-delay:0.12s} .sc:nth-child(4){animation-delay:0.16s}
        .sc:nth-child(5){animation-delay:0.20s} .sc:nth-child(6){animation-delay:0.24s}
        .sc:nth-child(7){animation-delay:0.28s}

        /* SECTION CARDS */
        .card{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:22px;margin-bottom:18px;transition:background var(--tr),border-color var(--tr);opacity:0;animation:fadeUp 0.55s ease forwards;}
        .card:nth-of-type(1){animation-delay:0.3s} .card:nth-of-type(2){animation-delay:0.4s} .card:nth-of-type(3){animation-delay:0.5s}
        .card-head{display:flex;justify-content:space-between;align-items:center;padding-bottom:14px;margin-bottom:14px;border-bottom:1px solid var(--border);}
        .card-head h3{font-family:'Syne',sans-serif;font-size:0.92rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
        .card-head h3 i{color:var(--accent);font-size:0.82rem;}
        .view-all{font-size:0.72rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--accent);display:flex;align-items:center;gap:5px;transition:gap 0.2s;}
        .view-all:hover{gap:9px;}

        /* TABLE */
        table{width:100%;border-collapse:collapse;}
        th{font-size:0.66rem;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:var(--text3);padding:0 13px 10px;text-align:left;border-bottom:1px solid var(--border);}
        td{padding:12px 13px;font-size:0.855rem;color:var(--text2);border-bottom:1px solid var(--border);transition:background 0.15s;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(79,142,247,0.04);color:var(--text);}
        td strong{color:var(--text);font-weight:600;}
        .badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.67rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;}
        .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
        .badge.confirmed{background:rgba(34,197,94,0.12);color:#22c55e;}
        .badge.pending{background:rgba(245,158,11,0.12);color:#f59e0b;}
        .badge.approved{background:rgba(79,142,247,0.12);color:var(--accent);}
        .badge.cancelled{background:rgba(239,68,68,0.12);color:#ef4444;}
        .badge.awaiting_payment{background:rgba(167,139,250,0.12);color:#a78bfa;}
        .abt{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;font-size:0.74rem;font-weight:600;border:1px solid transparent;cursor:pointer;transition:all 0.2s;text-decoration:none;}
        .abt-ok{color:#22c55e;border-color:rgba(34,197,94,0.3);background:rgba(34,197,94,0.07);}
        .abt-ok:hover{background:#22c55e;color:#fff;}
        .abt-un{color:#f59e0b;border-color:rgba(245,158,11,0.3);background:rgba(245,158,11,0.07);}
        .abt-un:hover{background:#f59e0b;color:#fff;}
        .empty td{text-align:center;padding:36px;color:var(--text3);font-size:0.84rem;}

        @keyframes fadeUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}

        @media(max-width:1200px){ .stats{grid-template-columns:repeat(4,1fr);} }
        @media(max-width:900px){ .stats{grid-template-columns:repeat(2,1fr);} }
        
        /* MOBILE RESPONSIVE */
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);z-index:999;transition:transform 0.3s ease;}
            .sidebar.open{transform:translateX(0);}
            .main{margin-left:0!important;width:100%!important;}
            .top-bar{padding:0 16px;height:56px;}
            .tb-left h2{font-size:0.95rem;}
            .body{padding:16px;}
            .stats{grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px;}
            .sc{padding:14px;}
            .sc-num{font-size:1.3rem;}
            .sc-lbl{font-size:0.6rem;}
            .card-body,.card-body2{max-height:none;overflow:visible;}
            .card-body table,.card-body2 table{font-size:0.75rem;}
            .card-body td,.card-body2 td{padding:8px 6px;}
            .filter-bar{flex-wrap:wrap;gap:8px;}
            .filter-bar select,.filter-bar input{flex:1 1 100%;min-width:0;}
            .mobile-menu-btn{display:flex!important;}
            .tb-right .theme-btn,.tb-right .notif-btn{display:none;}
            .tb-left p{display:none;}
            .notification-panel{width:300px;right:-10px;}
            .profile-dropdown{right:-10px;}
        }
        @media(max-width:480px){
            .stats{grid-template-columns:1fr 1fr;}
            .sc{padding:12px;}
            .sc-num{font-size:1.1rem;}
            .tb-right{gap:6px;}
            .card-body table,.card-body2 table{font-size:0.7rem;}
            th{font-size:0.55rem;padding:0 4px 8px;}
            td{padding:6px 4px;}
            .filter-bar select,.filter-bar input,.filter-bar button{padding:8px 12px;font-size:0.8rem;}
        }
        .mobile-menu-btn{
            display:none;width:40px;height:40px;background:var(--surface);
            border:1px solid var(--border2);border-radius:8px;cursor:pointer;
            align-items:center;justify-content:center;color:var(--text2);font-size:1rem;
        }
        .mobile-menu-btn:hover{border-color:var(--accent);color:var(--accent);}
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
        <li class="active"><a href="admin_dashboard.php"><i class="fa fa-table-columns"></i> Dashboard</a></li>
        <li><a href="car.php"><i class="fa fa-car"></i> Cars</a></li>
        <li><a href="bookings.php"><i class="fa fa-calendar-check"></i> Bookings</a></li>
        <li><a href="reg-users.php"><i class="fa fa-users"></i> Registered Users</a></li>
        <div class="sb-section">Finance &amp; Operations</div>
        <li><a href="payment_management.php"><i class="fa fa-credit-card"></i> Payments</a></li>
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
            <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
            <h2>Dashboard Overview</h2>
            <p id="dateLabel"></p>
        </div>
        <div class="tb-right">
            <!-- Notification Bell -->
            <div style="position:relative;">
                <button class="notif-btn <?php echo $unread_count > 0 ? 'has-notif' : ''; ?>" id="notifBtn" onclick="toggleNotif()">
                    <i class="fa fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                    <span class="notif-badge" id="notifBadgeCount"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <h4><i class="fa fa-bell"></i> Notifications</h4>
                        <button class="notif-mark-all" id="markAllRead" onclick="markAllRead(event)">Mark all read</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-empty" id="notifEmpty">
                            <i class="fa fa-bell-slash"></i>
                            <p>No notifications yet</p>
                        </div>
                    </div>
                    <div class="notif-footer">
                        <a href="bookings.php">View All Bookings <i class="fa fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <button class="theme-btn" id="themeBtn" title="Toggle Theme">
                <i class="fa fa-moon" id="themeIcon"></i>
            </button>
            <div class="admin-pill" id="adminPill" onclick="document.getElementById('adminPicInput').click()">
                <div class="av">
                    <?php if ($has_admin_pic): ?>
                        <img src="<?php echo htmlspecialchars($admin_pic_path); ?>?t=<?php echo time(); ?>" alt="Profile" id="adminAvatarImg">
                    <?php else: ?>
                        <span id="adminAvatarInitial"><?php echo strtoupper(substr($_SESSION['alogin'] ?? 'A', 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="name"><?php echo htmlspecialchars($_SESSION['alogin'] ?? 'Admin'); ?></div>
                    <div class="role">Administrator</div>
                </div>
                <div class="admin-pic-overlay"><i class="fa fa-camera"></i> Change</div>
            </div>
        </div>
    </div>
    <input type="file" id="adminPicInput" accept="image/*" style="display:none;">

    <div class="body">

        <!-- ── STAT CARDS — 7 items ── -->
        <div class="stats">

            <a href="reg-users.php" style="text-decoration:none;">
                <div class="sc">
                    <div class="sc-row">
                        <div><div class="sc-num"><?php echo $user_count; ?></div><div class="sc-lbl">Users</div></div>
                        <div class="sc-icon"><i class="fa fa-users"></i></div>
                    </div>
                </div>
            </a>

            <a href="car.php" style="text-decoration:none;">
                <div class="sc sc-green">
                    <div class="sc-row">
                        <div><div class="sc-num"><?php echo $cars_count; ?></div><div class="sc-lbl">Cars</div></div>
                        <div class="sc-icon"><i class="fa fa-car"></i></div>
                    </div>
                </div>
            </a>

            <a href="bookings.php" style="text-decoration:none;">
                <div class="sc sc-amber">
                    <div class="sc-row">
                        <div><div class="sc-num"><?php echo $booking_count; ?></div><div class="sc-lbl">Bookings</div></div>
                        <div class="sc-icon"><i class="fa fa-calendar-check"></i></div>
                    </div>
                </div>
            </a>

            <!-- ── NEW: Payments ── -->
            <a href="payment_management.php" style="text-decoration:none;">
                <div class="sc sc-green">
                    <div class="sc-row">
                        <div><div class="sc-num"><?php echo $payment_count; ?></div><div class="sc-lbl">Payments</div></div>
                        <div class="sc-icon"><i class="fa fa-credit-card"></i></div>
                    </div>
                </div>
            </a>

            <!-- ── NEW: Car Returns ── -->
            <a href="car_returns.php" style="text-decoration:none;">
                <div class="sc sc-purple">
                    <div class="sc-row">
                        <div><div class="sc-num"><?php echo $returns_count; ?></div><div class="sc-lbl">Returns</div></div>
                        <div class="sc-icon"><i class="fa fa-rotate-left"></i></div>
                    </div>
                </div>
            </a>

            <a href="testimonials.php" style="text-decoration:none;">
                <div class="sc sc-rose">
                    <div class="sc-row">
                        <div><div class="sc-num"><?php echo $testimonial_count; ?></div><div class="sc-lbl">Reviews</div></div>
                        <div class="sc-icon"><i class="fa fa-star"></i></div>
                    </div>
                </div>
            </a>

            <a href="contactus.php" style="text-decoration:none;">
                <div class="sc">
                    <div class="sc-row">
                        <div><div class="sc-num"><?php echo $query_count; ?></div><div class="sc-lbl">Queries</div></div>
                        <div class="sc-icon"><i class="fa fa-envelope"></i></div>
                    </div>
                </div>
            </a>

        </div>

        <!-- ── REVENUE REPORT (Generated using Cursor Procedure) ── -->
        <?php if ($revenue_report): ?>
        <div class="card" style="margin-bottom:24px; border-left:3px solid var(--accent);">
            <div class="card-head">
                <h3><i class="fa fa-chart-line"></i> Revenue Report (Generated via Stored Procedure + Cursor)</h3>
                <span style="font-size:0.7rem;color:var(--text3);">Database Features: Stored Procedure | Cursor | Error Handling</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;padding:20px;">
                <div style="background:var(--surface2);border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text3);letter-spacing:0.1em;margin-bottom:8px;">Total Revenue</div>
                    <div style="font-size:1.6rem;font-weight:800;color:var(--accent);">Rs <?php echo number_format($revenue_report['total_revenue_lkr'] ?? 0); ?></div>
                </div>
                <div style="background:var(--surface2);border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text3);letter-spacing:0.1em;margin-bottom:8px;">Confirmed Bookings</div>
                    <div style="font-size:1.6rem;font-weight:800;color:#22c55e;"><?php echo number_format($revenue_report['confirmed_bookings'] ?? 0); ?></div>
                </div>
                <div style="background:var(--surface2);border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text3);letter-spacing:0.1em;margin-bottom:8px;">Pending Bookings</div>
                    <div style="font-size:1.6rem;font-weight:800;color:#f59e0b;"><?php echo number_format($revenue_report['pending_bookings'] ?? 0); ?></div>
                </div>
                <div style="background:var(--surface2);border-radius:12px;padding:18px;text-align:center;">
                    <div style="font-size:0.7rem;text-transform:uppercase;color:var(--text3);letter-spacing:0.1em;margin-bottom:8px;">Avg Booking Value</div>
                    <div style="font-size:1.6rem;font-weight:800;color:var(--text);">Rs <?php echo number_format($revenue_report['average_booking_value'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TOP CUSTOMERS using Subquery -->
        <?php if (count($top_customers) > 0): ?>
        <div class="card" style="margin-bottom:24px; border-left:3px solid #22c55e;">
            <div class="card-head">
                <h3><i class="fa fa-trophy"></i> Top Customers (Generated via Subquery)</h3>
                <span style="font-size:0.7rem;color:var(--text3);">Database Features: Subquery | JOIN | GROUP BY</span>
            </div>
            <table>
                <thead>
                    <tr><th>Customer</th><th>Email</th><th>Total Bookings</th><th>Total Spent</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($top_customers as $customer): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($customer['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><span class="badge confirmed"><?php echo $customer['total_bookings']; ?></span></td>
                        <td><strong style="color:var(--accent);">Rs <?php echo number_format($customer['total_spent']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-calendar-check"></i> Recent Bookings</h3>
                <a href="bookings.php" class="view-all">View All <i class="fa fa-arrow-right"></i></a>
            </div>
            <table>
                <thead>
                    <tr><th>Email</th><th>Car</th><th>From</th><th>To</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php
                    $b = $conn->query("
                        SELECT b.*, c.car_name 
                        FROM booking b 
                        LEFT JOIN cars c ON c.id = b.car_id 
                        ORDER BY b.id DESC LIMIT 5
                    ");
                    if ($b && $b->num_rows > 0):
                        while ($r = $b->fetch_assoc()):
                            $status = $r['status'];
                            $carName = $r['car_name'] ?? 'Unknown Car';
                            
                            if ($status === 'confirmed') {
                                $badgeClass = 'confirmed';
                                $label = 'Confirmed';
                            } elseif ($status === 'awaiting_payment') {
                                $badgeClass = 'approved';
                                $label = 'Awaiting Payment';
                            } elseif ($status === 'cancelled') {
                                $badgeClass = 'cancelled';
                                $label = 'Cancelled';
                            } else {
                                $badgeClass = 'pending';
                                $label = 'Pending';
                            }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['user_email']); ?></td>
                        <td><strong><?php echo htmlspecialchars($carName); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['from_date']); ?></td>
                        <td><?php echo htmlspecialchars($r['to_date']); ?></td>
                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $label; ?></span></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr class="empty"><td colspan="5">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Contact Queries -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-envelope"></i> Recent Contact Queries</h3>
                <a href="contactus.php" class="view-all">View All <i class="fa fa-arrow-right"></i></a>
            </div>
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Subject</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php
                    $q = $conn->query("SELECT * FROM contact_us ORDER BY created_at DESC LIMIT 5");
                    if ($q && $q->num_rows > 0):
                        while ($qr = $q->fetch_assoc()):
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($qr['first_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($qr['email']); ?></td>
                        <td><?php echo htmlspecialchars($qr['subject']); ?></td>
                        <td><?php echo date('d M Y', strtotime($qr['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr class="empty"><td colspan="4">No queries found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Testimonials -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-comments"></i> Pending Testimonials</h3>
                <a href="testimonials.php" class="view-all">Manage All <i class="fa fa-arrow-right"></i></a>
            </div>
            <table>
                <thead>
                    <tr><th>User</th><th>Car</th><th>Preview</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php
                    $t = $conn->query("SELECT * FROM testimonials ORDER BY status ASC, created_at DESC LIMIT 5");
                    if ($t && $t->num_rows > 0):
                        while ($tr = $t->fetch_assoc()):
                            $tc = ($tr['status'] == 1) ? 'approved' : 'pending';
                            $tl = ($tr['status'] == 1) ? 'Approved' : 'Pending';
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($tr['user_name'] ?? '—'); ?></strong></td>
                        <td><?php echo htmlspecialchars($tr['car_name'] ?? '—'); ?></td>
                        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php echo htmlspecialchars(substr($tr['review'] ?? '', 0, 55)); ?>…
                        </td>
                        <td><span class="badge <?php echo $tc; ?>"><?php echo $tl; ?></span></td>
                        <td>
                            <?php if ($tr['status'] == 0): ?>
                            <a href="admin_dashboard.php?eid=<?php echo $tr['id']; ?>" class="abt abt-ok"
                               onclick="return confirm('Approve this review?')"><i class="fa fa-check"></i> Approve</a>
                            <?php else: ?>
                            <a href="admin_dashboard.php?aeid=<?php echo $tr['id']; ?>" class="abt abt-un"
                               onclick="return confirm('Unapprove?')"><i class="fa fa-ban"></i> Unapprove</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr class="empty"><td colspan="5">No testimonials found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
    history.pushState(null, null, location.href);
    window.addEventListener('popstate', function(){ window.location.replace('index.php'); });

    (function(){
        var d=new Date(), days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear();
    })();

    var theme = localStorage.getItem('adminTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme==='dark'?'light':'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('adminTheme', theme);
        syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }

    // Notification dropdown
    function toggleNotif() {
        var dropdown = document.getElementById('notifDropdown');
        dropdown.classList.toggle('open');
        event.stopPropagation();
        if (dropdown.classList.contains('open')) {
            loadNotifications();
        }
    }
    
    function loadNotifications() {
        fetch('admin_dashboard.php?get_notifications=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            var list = document.getElementById('notifList');
            var empty = document.getElementById('notifEmpty');
            
            if (!data || data.length === 0) {
                list.innerHTML = '<div class="notif-empty"><i class="fa fa-bell-slash"></i><p>No notifications yet</p></div>';
                return;
            }
            
            list.innerHTML = data.map(function(n) {
                var iconClass = 'notif-icon-booking';
                var icon = 'fa fa-calendar-check';
                
                if (n.type === 'payment') {
                    iconClass = 'notif-icon-payment';
                    icon = 'fa fa-credit-card';
                } else if (n.type === 'warning') {
                    iconClass = 'notif-icon-warning';
                    icon = 'fa fa-exclamation-triangle';
                } else if (n.type === 'error') {
                    iconClass = 'notif-icon-error';
                    icon = 'fa fa-times-circle';
                }
                
                var timeAgo = getTimeAgo(n.created_at);
                var unreadClass = n.is_read == 0 ? 'unread' : '';
                var link = n.reference_type === 'booking' ? 'bookings.php?id=' + n.reference_id : 'bookings.php';
                
                return '<a href="' + link + '" class="notif-item ' + unreadClass + '" onclick="markAsRead(' + n.id + ', event)">' +
                    '<div class="notif-icon-wrap ' + iconClass + '"><i class="' + icon + '"></i></div>' +
                    '<div class="notif-content">' +
                    '<div class="notif-title">' + escapeHtml(n.title) + '</div>' +
                    '<div class="notif-message">' + escapeHtml(n.message) + '</div>' +
                    '<div class="notif-meta"><span class="notif-time">' + timeAgo + '</span></div>' +
                    '</div>' +
                    '<div class="notif-status ' + (n.is_read == 0 ? 'unread' : 'read') + '"></div>' +
                    '</a>';
            }).join('');
        })
        .catch(err => console.error('Error loading notifications:', err));
    }
    
    function getTimeAgo(dateStr) {
        var date = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    }
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function markAsRead(id, event) {
        event.preventDefault();
        event.stopPropagation();
        
        fetch('admin_dashboard.php?mark_read=' + id, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(function() {
            var badge = document.getElementById('notifBadgeCount');
            if (badge) {
                var count = parseInt(badge.textContent) - 1;
                if (count <= 0) {
                    badge.remove();
                    document.getElementById('notifBtn').classList.remove('has-notif');
                } else {
                    badge.textContent = count;
                }
            }
            window.location.href = event.currentTarget.href;
        })
        .catch(err => {
            console.error('Error marking as read:', err);
            window.location.href = event.currentTarget.href;
        });
    }
    
    function markAllRead(event) {
        event.preventDefault();
        event.stopPropagation();
        
        fetch('admin_dashboard.php?mark_all_read=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(function() {
            var badge = document.getElementById('notifBadgeCount');
            if (badge) badge.remove();
            document.getElementById('notifBtn').classList.remove('has-notif');
            loadNotifications();
        })
        .catch(err => console.error('Error:', err));
    }
    
    // Poll for new notifications every 30 seconds
    setInterval(function() {
        fetch('admin_dashboard.php?get_unread_count=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(function(data) {
            var badge = document.getElementById('notifBadgeCount');
            var btn = document.getElementById('notifBtn');
            
            if (data.count > 0) {
                btn.classList.add('has-notif');
                if (badge) {
                    badge.textContent = data.count;
                } else {
                    var newBadge = document.createElement('span');
                    newBadge.className = 'notif-badge';
                    newBadge.id = 'notifBadgeCount';
                    newBadge.textContent = data.count;
                    btn.appendChild(newBadge);
                }
            } else {
                btn.classList.remove('has-notif');
                if (badge) badge.remove();
            }
        })
        .catch(err => {});
    }, 30000);
    
    document.addEventListener('click', function(e) {
        var dropdown = document.getElementById('notifDropdown');
        var btn = document.getElementById('notifBtn');
        if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.remove('open');
        }
        var sidebar = document.querySelector('.sidebar');
        var menuBtn = document.querySelector('.mobile-menu-btn');
        if (sidebar && menuBtn && window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });
    
    // Toggle Sidebar for Mobile
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('open');
    }
    
    // Admin Profile Picture Upload
    document.getElementById('adminPicInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('profile_image', file);
        
        fetch('upload_profile_image.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const av = document.querySelector('.av');
                const initial = document.getElementById('adminAvatarInitial');
                const img = document.getElementById('adminAvatarImg');
                
                if (initial) initial.remove();
                const newSrc = '../' + data.path + '?t=' + Date.now();
                if (!img) {
                    const newImg = document.createElement('img');
                    newImg.id = 'adminAvatarImg';
                    newImg.src = newSrc;
                    av.appendChild(newImg);
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