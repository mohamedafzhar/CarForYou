<?php
session_start();
include 'config.php';
adminAuth();

date_default_timezone_set('Asia/Colombo');

$success_msg = '';
$error_msg   = '';

// ── PROCESS RETURN ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'process_return') {
        $booking_id       = intval($_POST['booking_id']);
        $car_id           = intval($_POST['car_id']);
        $actual_return    = $_POST['actual_return_date'];
        $car_condition    = $_POST['car_condition'];
        $damage_notes     = trim($_POST['damage_notes'] ?? '');
        $penalty_per_day  = intval($_POST['penalty_per_day'] ?? 0);

        // Get booking to_date for late penalty calc
        $stmt = $conn->prepare("SELECT to_date FROM booking WHERE id=?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $bk = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $penalty = 0;
        if ($bk && $actual_return > $bk['to_date']) {
            $late_days = (int)((strtotime($actual_return) - strtotime($bk['to_date'])) / 86400);
            $penalty   = $late_days * $penalty_per_day;
        }

        // Handle photo uploads (optional)
        $uploaded_photos = [];
        if (!empty($_FILES['return_photos']['name'][0])) {
            $upload_dir = 'uploads/return_photos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $allowed = ['image/jpeg','image/png','image/jpg','image/webp'];
            $count   = min(count($_FILES['return_photos']['name']), 4);

            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['return_photos']['error'][$i] === 0
                    && in_array($_FILES['return_photos']['type'][$i], $allowed)
                    && $_FILES['return_photos']['size'][$i] <= 5 * 1024 * 1024) {

                    $ext      = pathinfo($_FILES['return_photos']['name'][$i], PATHINFO_EXTENSION);
                    $filename = 'ret_' . $booking_id . '_' . time() . '_' . $i . '.' . $ext;
                    if (move_uploaded_file($_FILES['return_photos']['tmp_name'][$i], $upload_dir . $filename)) {
                        $uploaded_photos[] = $filename;
                    }
                }
            }
        }
        $photos_json = !empty($uploaded_photos) ? json_encode($uploaded_photos) : null;

        // Update booking
        $stmt2 = $conn->prepare("UPDATE booking SET
            return_status='returned',
            actual_return_date=?,
            car_condition=?,
            damage_notes=?,
            penalty_amount=?,
            return_photos=?
            WHERE id=?");
        $stmt2->bind_param("sssisi", $actual_return, $car_condition, $damage_notes, $penalty, $photos_json, $booking_id);
        $stmt2->execute();
        $stmt2->close();

        // Set car back to Available
        $stmt3 = $conn->prepare("UPDATE cars SET status='Available' WHERE id=?");
        $stmt3->bind_param("i", $car_id);
        $stmt3->execute();
        $stmt3->close();

        $success_msg = "Car return processed successfully." . ($penalty > 0 ? " Late penalty: <strong>Rs ".number_format($penalty)."</strong>" : "");
    }

    if ($_POST['action'] === 'undo_return') {
        $booking_id = intval($_POST['booking_id']);
        $car_id     = intval($_POST['car_id']);

        $stmt = $conn->prepare("UPDATE booking SET return_status='not_returned', actual_return_date=NULL, car_condition='good', damage_notes=NULL, penalty_amount=0 WHERE id=?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("UPDATE cars SET status='Booked' WHERE id=?");
        $stmt2->bind_param("i", $car_id);
        $stmt2->execute();
        $stmt2->close();

        $success_msg = "Return undone. Car status set back to Booked.";
    }
}

// ── FILTERS ───────────────────────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where  = "WHERE b.status IN (1, 'confirmed', 'Confirmed')"; // only confirmed bookings
$params = []; $types = "";

if ($filter === 'returned')     $where .= " AND b.return_status='returned'";
if ($filter === 'not_returned') $where .= " AND b.return_status='not_returned'";
if ($filter === 'late')         $where .= " AND b.return_status='not_returned' AND b.to_date < CURDATE()";
if ($search !== '') {
    $like = "%$search%";
    $where .= " AND (b.user_email LIKE ? OR c.car_name LIKE ? OR u.full_name LIKE ?)";
    $params = [$like, $like, $like]; $types = "sss";
}

$sql = "SELECT b.id, b.user_email, b.from_date, b.to_date, b.status,
               b.return_status, b.actual_return_date, b.car_condition,
               b.damage_notes, b.penalty_amount, b.total_amount,
               c.id AS car_id, c.car_name, c.car_model, c.price_per_day, c.Vimage1, c.status AS car_status,
               u.full_name,
               DATEDIFF(CURDATE(), b.to_date) AS days_overdue
        FROM booking b
        JOIN cars c ON c.id = b.car_id
        LEFT JOIN users u ON u.email = b.user_email
        $where ORDER BY
            CASE WHEN b.return_status='not_returned' AND b.to_date < CURDATE() THEN 0
                 WHEN b.return_status='not_returned' THEN 1
                 ELSE 2 END, b.to_date ASC";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$returns = $stmt->get_result();

// ── STATS ─────────────────────────────────────────────────────
$s = $conn->query("SELECT
    SUM(CASE WHEN status IN (1,'confirmed','Confirmed') THEN 1 ELSE 0 END) AS confirmed,
    SUM(CASE WHEN status IN (1,'confirmed','Confirmed') AND return_status='returned' THEN 1 ELSE 0 END) AS returned,
    SUM(CASE WHEN status IN (1,'confirmed','Confirmed') AND return_status='not_returned' THEN 1 ELSE 0 END) AS active,
    SUM(CASE WHEN status IN (1,'confirmed','Confirmed') AND return_status='not_returned' AND to_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
    FROM booking")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Car Returns | CarForYou Admin</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
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
    --purple:#a78bfa; --purplebg:rgba(167,139,250,0.1);
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
    --purple:#7c3aed; --purplebg:rgba(124,58,237,0.08);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background var(--tr),color var(--tr);}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--bg)}::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px}
a{text-decoration:none;color:inherit;}

/* SIDEBAR — identical to admin_dashboard */
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

.alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:0.84rem;font-weight:500;margin-bottom:20px;animation:fadeUp 0.3s ease;}
.alert-success{background:var(--greenbg);color:var(--green);border:1px solid rgba(34,197,94,0.2);}
.alert-error{background:var(--redbg);color:var(--red);border:1px solid rgba(239,68,68,0.2);}

/* Photo upload */
.photo-upload-area{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:20px;background:var(--surface2);border:2px dashed var(--border2);border-radius:10px;cursor:pointer;transition:all 0.2s;text-align:center;}
.photo-upload-area:hover{border-color:var(--accent);background:rgba(79,142,247,0.05);}
.photo-upload-area i{font-size:1.4rem;color:var(--text3);}
.photo-upload-area span{font-size:0.82rem;font-weight:500;color:var(--text2);}
.photo-previews{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:10px;}
.photo-preview-item{position:relative;border-radius:8px;overflow:hidden;border:1px solid var(--border2);aspect-ratio:1;}
.photo-preview-item img{width:100%;height:100%;object-fit:cover;display:block;}
.photo-remove{position:absolute;top:4px;right:4px;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,0.7);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.6rem;transition:background 0.2s;}
.photo-remove:hover{background:var(--red);}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
.sc{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:20px;display:flex;flex-direction:column;gap:14px;position:relative;overflow:hidden;opacity:0;animation:fadeUp 0.5s ease forwards;transition:transform 0.25s,box-shadow 0.25s;}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),var(--accent2));transform:scaleX(0);transform-origin:left;transition:transform 0.35s ease;}
.sc:hover{transform:translateY(-3px);box-shadow:var(--cshadow);}
.sc:hover::before{transform:scaleX(1);}
.sc:nth-child(1){animation-delay:0.05s}.sc:nth-child(2){animation-delay:0.10s}.sc:nth-child(3){animation-delay:0.15s}.sc:nth-child(4){animation-delay:0.20s}
.sc-row{display:flex;justify-content:space-between;align-items:flex-start;}
.sc-num{font-family:'Syne',sans-serif;font-size:1.85rem;font-weight:800;color:var(--text);line-height:1;}
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
.filter-tab.active.red-tab{background:var(--red);}
.filter-tab:hover:not(.active){background:var(--surface);color:var(--text);}
.search-wrap{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:7px 12px;}
.search-wrap i{color:var(--text3);font-size:0.8rem;}
.search-wrap input{background:none;border:none;outline:none;font-family:'DM Sans',sans-serif;font-size:0.84rem;color:var(--text);width:190px;}
.search-wrap input::placeholder{color:var(--text3);}
.search-btn{padding:7px 14px;background:var(--accent);color:#fff;border:none;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:0.78rem;font-weight:600;cursor:pointer;transition:opacity 0.2s;}
.search-btn:hover{opacity:0.85;}

/* TABLE */
.card{background:var(--surface);border:1px solid var(--border);border-radius:13px;overflow:hidden;opacity:0;animation:fadeUp 0.5s ease 0.22s forwards;}
.card-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);}
.card-head h3{font-family:'Syne',sans-serif;font-size:0.92rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.card-head h3 i{color:var(--accent);font-size:0.82rem;}

table{width:100%;border-collapse:collapse;}
th{font-size:0.66rem;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:var(--text3);padding:0 16px 12px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:13px 16px;font-size:0.845rem;color:var(--text2);border-bottom:1px solid var(--border);transition:background 0.15s;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(79,142,247,0.04);}
tr.overdue-row td{background:rgba(239,68,68,0.03);}
tr.overdue-row:hover td{background:rgba(239,68,68,0.06);}

.car-cell{display:flex;align-items:center;gap:10px;}
.car-thumb{width:52px;height:34px;border-radius:7px;object-fit:cover;background:var(--surface2);border:1px solid var(--border);flex-shrink:0;}
.car-name{font-weight:700;color:var(--text);font-size:0.84rem;}
.car-model{font-size:0.7rem;color:var(--text3);margin-top:1px;}

.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.67rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap;}
.badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
.badge-returned{background:var(--greenbg);color:var(--green);}
.badge-active{background:rgba(79,142,247,0.12);color:var(--accent);}
.badge-overdue{background:var(--redbg);color:var(--red);}
.badge-good{background:var(--greenbg);color:var(--green);}
.badge-minor{background:var(--amberbg);color:var(--amber);}
.badge-major{background:var(--redbg);color:var(--red);}

.overdue-tag{display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;color:var(--red);font-weight:700;margin-top:3px;}

.btn-action{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;font-size:0.74rem;font-weight:600;border:1px solid transparent;cursor:pointer;transition:all 0.2s;font-family:'DM Sans',sans-serif;}
.btn-ret{color:var(--green);border-color:rgba(34,197,94,0.3);background:var(--greenbg);}
.btn-ret:hover{background:var(--green);color:#fff;}
.btn-undo{color:var(--amber);border-color:rgba(245,158,11,0.3);background:var(--amberbg);}
.btn-undo:hover{background:var(--amber);color:#fff;}

.empty-row td{text-align:center;padding:44px;color:var(--text3);font-size:0.84rem;}
.empty-row td i{display:block;font-size:2.2rem;margin-bottom:12px;opacity:0.15;}

/* RETURN MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-overlay.open{display:flex;}
.modal{background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:100%;max-width:500px;overflow:hidden;animation:popIn 0.3s cubic-bezier(0.22,1,0.36,1);max-height:90vh;overflow-y:auto;}
@keyframes popIn{from{transform:scale(0.92);opacity:0}to{transform:scale(1);opacity:1}}
.modal-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface);z-index:1;}
.modal-header h3{font-family:'Syne',sans-serif;font-size:0.95rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
.modal-header h3 i{color:var(--accent);}
.modal-close{width:30px;height:30px;border-radius:7px;border:1px solid var(--border2);background:none;color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.8rem;transition:all 0.2s;}
.modal-close:hover{border-color:var(--red);color:var(--red);}
.modal-body{padding:24px;}

/* FORM */
.info-strip{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:20px;}
.info-strip .irow{display:flex;justify-content:space-between;font-size:0.82rem;padding:4px 0;}
.info-strip .irow .il{color:var(--text2);}
.info-strip .irow .iv{font-weight:600;color:var(--text);}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text3);margin-bottom:7px;}
.form-control{width:100%;padding:11px 13px;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.875rem;outline:none;transition:border-color 0.22s,box-shadow 0.22s;}
.form-control::placeholder{color:var(--text3);}
.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,142,247,0.12);}
select.form-control option{background:var(--surface2);}
textarea.form-control{resize:vertical;min-height:72px;}
.condition-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.cond-option input{display:none;}
.cond-option label{display:block;padding:10px;border:1px solid var(--border2);border-radius:9px;text-align:center;cursor:pointer;font-size:0.78rem;font-weight:600;transition:all 0.2s;color:var(--text2);}
.cond-option input:checked + label.good{border-color:var(--green);background:var(--greenbg);color:var(--green);}
.cond-option input:checked + label.minor{border-color:var(--amber);background:var(--amberbg);color:var(--amber);}
.cond-option input:checked + label.major{border-color:var(--red);background:var(--redbg);color:var(--red);}
.cond-option label:hover{border-color:var(--accent);color:var(--accent);}
.penalty-preview{padding:12px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;margin-top:8px;font-size:0.82rem;color:var(--text2);display:none;}
.penalty-preview.show{display:block;}
.penalty-preview strong{color:var(--red);}
.btn-submit-modal{width:100%;padding:12px;background:linear-gradient(135deg,var(--green),#16a34a);color:#fff;border:none;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity 0.2s;margin-top:4px;}
.btn-submit-modal:hover{opacity:0.88;}

@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,1fr);}}

/* MOBILE RESPONSIVE */
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);z-index:999;transition:transform 0.3s ease;}
    .sidebar.open{transform:translateX(0);}
    .main{margin-left:0!important;width:100%!important;}
    .top-bar{padding:0 16px;height:56px;}
    .body{padding:16px;}
    .mobile-menu-btn{display:flex!important;}
    .tb-left h2{font-size:0.95rem;}
    table{font-size:0.75rem;}
    th,td{padding:8px 6px;}
    th{font-size:0.6rem;}
    .badge{font-size:0.6rem;padding:2px 6px;}
    .btn-action{font-size:0.65rem;padding:4px 8px;}
    .stats-grid{grid-template-columns:1fr 1fr;}
    .filter-tabs{flex-wrap:wrap;}
    .modal{width:95%!important;max-width:none;margin:10px;}
}
@media(max-width:480px){
    table{font-size:0.7rem;}
    th,td{padding:6px 4px;}
    .car-cell{flex-direction:column;align-items:flex-start;gap:4px;}
    .stats-grid{grid-template-columns:1fr 1fr;}
}
.mobile-menu-btn{
    display:none;width:40px;height:40px;background:var(--surface);
    border:1px solid var(--border2);border-radius:8px;cursor:pointer;
    align-items:center;justify-content:center;color:var(--text2);font-size:1rem;margin-right:12px;
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
        <li><a href="admin_dashboard.php"><i class="fa fa-table-columns"></i> Dashboard</a></li>
        <li><a href="car.php"><i class="fa fa-car"></i> Cars</a></li>
        <li><a href="bookings.php"><i class="fa fa-calendar-check"></i> Bookings</a></li>
        <li><a href="reg-users.php"><i class="fa fa-users"></i> Registered Users</a></li>
        <li class="sb-divider"></li>
    </ul>
    <div class="sb-section">Finance & Operations</div>
    <ul class="sb-menu">
        <li><a href="payment_management.php"><i class="fa fa-credit-card"></i> Payments</a></li>
        <li class="active"><a href="car_returns.php"><i class="fa fa-rotate-left"></i> Car Returns</a></li>
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
            <h2>Car Returns</h2>
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
                    <div><div class="sc-num"><?php echo $s['confirmed']; ?></div><div class="sc-lbl">Confirmed Rentals</div></div>
                    <div class="sc-icon" style="background:rgba(79,142,247,0.12);color:var(--accent)"><i class="fa fa-car-side"></i></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-row">
                    <div><div class="sc-num"><?php echo $s['active']; ?></div><div class="sc-lbl">Active / Out</div></div>
                    <div class="sc-icon" style="background:rgba(79,142,247,0.12);color:var(--accent)"><i class="fa fa-road"></i></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-row">
                    <div><div class="sc-num"><?php echo $s['returned']; ?></div><div class="sc-lbl">Returned</div></div>
                    <div class="sc-icon" style="background:var(--greenbg);color:var(--green)"><i class="fa fa-circle-check"></i></div>
                </div>
            </div>
            <div class="sc">
                <div class="sc-row">
                    <div><div class="sc-num"><?php echo $s['overdue']; ?></div><div class="sc-lbl">Overdue</div></div>
                    <div class="sc-icon" style="background:var(--redbg);color:var(--red)"><i class="fa fa-triangle-exclamation"></i></div>
                </div>
            </div>
        </div>

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="toolbar-left"><i class="fa fa-rotate-left"></i> Return Records</div>
            <div class="toolbar-right">
                <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <div class="filter-tabs">
                        <button type="submit" name="status" value="all"          class="filter-tab <?php echo $filter==='all'         ?'active':'';?>">All</button>
                        <button type="submit" name="status" value="not_returned" class="filter-tab <?php echo $filter==='not_returned'?'active':'';?>">Active</button>
                        <button type="submit" name="status" value="late"         class="filter-tab <?php echo $filter==='late'        ?'active red-tab':'';?>">Overdue</button>
                        <button type="submit" name="status" value="returned"     class="filter-tab <?php echo $filter==='returned'    ?'active':'';?>">Returned</button>
                    </div>
                    <div class="search-wrap">
                        <i class="fa fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Email, car, or name..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="search-btn"><i class="fa fa-search"></i> Search</button>
                    <?php if ($search || $filter!=='all'): ?>
                    <a href="car_returns.php" style="font-size:0.78rem;color:var(--text3);"><i class="fa fa-xmark"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-table"></i> Rental Returns</h3>
                <span style="font-size:0.78rem;color:var(--text3);"><?php echo $returns->num_rows; ?> record(s)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Car</th>
                        <th>Rental Period</th>
                        <th>Return Status</th>
                        <th>Condition</th>
                        <th>Penalty</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($returns && $returns->num_rows > 0):
                    while ($r = $returns->fetch_assoc()):
                        $overdue    = ($r['return_status']==='not_returned' && $r['days_overdue'] > 0);
                        $returned   = ($r['return_status']==='returned');
                        $img        = !empty($r['Vimage1']) ? "img/vehicleimages/".htmlspecialchars($r['Vimage1']) : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=60&q=60";
                        $cond       = $r['car_condition'] ?? 'good';
                        $condClass  = $cond==='good' ? 'badge-good' : ($cond==='minor_damage' ? 'badge-minor' : 'badge-major');
                        $condLabel  = $cond==='good' ? 'Good' : ($cond==='minor_damage' ? 'Minor Damage' : 'Major Damage');
                        $rowClass   = $overdue ? 'overdue-row' : '';
                        $days       = max(1, (int)((strtotime($r['to_date']) - strtotime($r['from_date'])) / 86400));
                        $rd = [
                            'booking_id'  => $r['id'],
                            'car_id'      => $r['car_id'],
                            'name'        => $r['full_name'] ?? $r['user_email'],
                            'email'       => $r['user_email'],
                            'car'         => $r['car_name'].' '.$r['car_model'],
                            'from'        => $r['from_date'],
                            'to'          => $r['to_date'],
                            'days'        => $days,
                            'ppd'         => $r['price_per_day'],
                            'overdue'     => intval($r['days_overdue']),
                        ];
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td style="font-weight:700;color:var(--text);">#<?php echo $r['id']; ?></td>
                    <td>
                        <div style="font-weight:600;color:var(--text);font-size:0.84rem;"><?php echo htmlspecialchars($r['full_name'] ?? '—'); ?></div>
                        <div style="font-size:0.72rem;color:var(--text3);"><?php echo htmlspecialchars($r['user_email']); ?></div>
                    </td>
                    <td>
                        <div class="car-cell">
                            <img src="<?php echo $img; ?>" class="car-thumb" alt="car"
                                 onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=60&q=60'">
                            <div>
                                <div class="car-name"><?php echo htmlspecialchars($r['car_name']); ?></div>
                                <div class="car-model"><?php echo htmlspecialchars($r['car_model']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:0.82rem;color:var(--text);"><?php echo date('d M', strtotime($r['from_date'])); ?> → <?php echo date('d M Y', strtotime($r['to_date'])); ?></div>
                        <?php if ($returned && $r['actual_return_date']): ?>
                        <div style="font-size:0.72rem;color:var(--green);margin-top:2px;"><i class="fa fa-check" style="margin-right:3px;"></i>Returned: <?php echo date('d M Y', strtotime($r['actual_return_date'])); ?></div>
                        <?php elseif ($overdue): ?>
                        <div class="overdue-tag"><i class="fa fa-triangle-exclamation"></i><?php echo $r['days_overdue']; ?> day(s) overdue</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($returned): ?>
                            <span class="badge badge-returned">Returned</span>
                        <?php elseif ($overdue): ?>
                            <span class="badge badge-overdue">Overdue</span>
                        <?php else: ?>
                            <span class="badge badge-active">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($returned): ?>
                            <span class="badge <?php echo $condClass; ?>"><?php echo $condLabel; ?></span>
                            <?php if ($r['damage_notes']): ?>
                            <div style="font-size:0.7rem;color:var(--text3);margin-top:3px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($r['damage_notes']); ?>"><?php echo htmlspecialchars($r['damage_notes']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--text3);font-size:0.78rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['penalty_amount'] > 0): ?>
                            <span style="font-family:'Syne',sans-serif;font-weight:700;color:var(--red);">Rs <?php echo number_format($r['penalty_amount']); ?></span>
                        <?php else: ?>
                            <span style="color:var(--text3);font-size:0.78rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$returned): ?>
                        <button class="btn-action btn-ret" onclick='openReturnModal(<?php echo json_encode($rd); ?>)'>
                            <i class="fa fa-rotate-left"></i> Process Return
                        </button>
                        <?php else: ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Undo this return?')">
                            <input type="hidden" name="action" value="undo_return">
                            <input type="hidden" name="booking_id" value="<?php echo $r['id']; ?>">
                            <input type="hidden" name="car_id" value="<?php echo $r['car_id']; ?>">
                            <button type="submit" class="btn-action btn-undo"><i class="fa fa-rotate-right"></i> Undo</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr class="empty-row"><td colspan="8"><i class="fa fa-car-side"></i>No return records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- RETURN MODAL -->
<div class="modal-overlay" id="returnModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa fa-rotate-left"></i> Process Car Return</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="returnForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="process_return">
                <input type="hidden" name="booking_id" id="m_booking_id">
                <input type="hidden" name="car_id" id="m_car_id">

                <div class="info-strip" id="m_info"></div>

                <div class="form-group">
                    <label>Actual Return Date</label>
                    <input type="date" name="actual_return_date" id="m_return_date" class="form-control" required onchange="calcPenalty()">
                </div>

                <div class="form-group">
                    <label>Late Fee Per Day (Rs) <span style="color:var(--text3);font-weight:400;font-size:0.78rem;">— leave 0 if no late fee</span></label>
                    <input type="number" name="penalty_per_day" id="m_ppd" class="form-control" value="" min="0" onchange="calcPenalty()">
                    <div class="penalty-preview" id="penaltyPreview"></div>
                </div>

                <div class="form-group">
                    <label>Car Condition on Return</label>
                    <div class="condition-grid">
                        <div class="cond-option">
                            <input type="radio" name="car_condition" id="cond_good" value="good" checked>
                            <label for="cond_good" class="good"><i class="fa fa-circle-check" style="display:block;margin-bottom:4px;font-size:1.1rem;"></i>Good</label>
                        </div>
                        <div class="cond-option">
                            <input type="radio" name="car_condition" id="cond_minor" value="minor_damage">
                            <label for="cond_minor" class="minor"><i class="fa fa-triangle-exclamation" style="display:block;margin-bottom:4px;font-size:1.1rem;"></i>Minor Damage</label>
                        </div>
                        <div class="cond-option">
                            <input type="radio" name="car_condition" id="cond_major" value="major_damage">
                            <label for="cond_major" class="major"><i class="fa fa-circle-xmark" style="display:block;margin-bottom:4px;font-size:1.1rem;"></i>Major Damage</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Damage Notes <span style="color:var(--text3);font-weight:400;">(optional)</span></label>
                    <textarea name="damage_notes" id="m_damage" class="form-control" placeholder="Describe any damage, scratches, or issues..."></textarea>
                </div>

                <div class="form-group">
                    <label>Return Photos <span style="color:var(--text3);font-weight:400;">(optional — max 4 photos)</span></label>
                    <div class="photo-upload-area" id="photoUploadArea" onclick="document.getElementById('returnPhotos').click()">
                        <i class="fa fa-camera"></i>
                        <span>Click to upload photos</span>
                        <span style="font-size:0.72rem;color:var(--text3);">JPG, PNG up to 5MB each</span>
                    </div>
                    <input type="file" name="return_photos[]" id="returnPhotos" multiple accept="image/*" style="display:none" onchange="handlePhotoSelect(this)">
                    <div class="photo-previews" id="photoPreviews"></div>
                    <div id="photoError" style="font-size:0.78rem;color:var(--red);margin-top:6px;display:none;"></div>
                </div>

                <button type="submit" class="btn-submit-modal">
                    <i class="fa fa-circle-check"></i> Confirm Return & Update Car Status
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Date
    (function(){ var d=new Date(),days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear(); })();

    // Theme
    var theme = localStorage.getItem('adminTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme); syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme==='dark'?'light':'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('adminTheme', theme); syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }

    // Return modal
    var currentBooking = {};
    function openReturnModal(d) {
        currentBooking = d;
        document.getElementById('m_booking_id').value = d.booking_id;
        document.getElementById('m_car_id').value     = d.car_id;

        // Set today as default return date
        var today = new Date().toISOString().split('T')[0];
        document.getElementById('m_return_date').value = today;
        document.getElementById('m_return_date').min   = d.from;

        // Info strip
        document.getElementById('m_info').innerHTML =
            '<div class="irow"><span class="il">Booking</span><span class="iv">#' + d.booking_id + '</span></div>' +
            '<div class="irow"><span class="il">Customer</span><span class="iv">' + d.name + '</span></div>' +
            '<div class="irow"><span class="il">Car</span><span class="iv">' + d.car + '</span></div>' +
            '<div class="irow"><span class="il">Rental Period</span><span class="iv">' + d.from + ' → ' + d.to + '</span></div>' +
            '<div class="irow"><span class="il">Duration</span><span class="iv">' + d.days + ' day(s)</span></div>';

        // Clear damage notes
        document.getElementById('m_damage').value = '';
        document.getElementById('cond_good').checked = true;

        calcPenalty();
        document.getElementById('returnModal').classList.add('open');
    }

    function calcPenalty() {
        var returnDate = document.getElementById('m_return_date').value;
        var toDate     = currentBooking.to;
        var ppd        = parseInt(document.getElementById('m_ppd').value) || 0;
        var preview    = document.getElementById('penaltyPreview');

        if (!returnDate || !toDate) { preview.classList.remove('show'); return; }

        var ret  = new Date(returnDate);
        var due  = new Date(toDate);
        var diff = Math.floor((ret - due) / 86400000);

        if (diff > 0 && ppd > 0) {
            var pen = diff * ppd;
            preview.innerHTML = '<i class="fa fa-triangle-exclamation" style="color:var(--red);margin-right:5px;"></i>' +
                diff + ' day(s) late &mdash; Late penalty: <strong>Rs ' + pen.toLocaleString() + '</strong>';
            preview.classList.add('show');
        } else if (diff > 0 && ppd === 0) {
            preview.innerHTML = diff + ' day(s) late &mdash; No penalty set.';
            preview.classList.add('show');
        } else if (diff < 0) {
            preview.innerHTML = '<i class="fa fa-circle-check" style="color:var(--green);margin-right:5px;"></i>Returned ' + Math.abs(diff) + ' day(s) early. No penalty.';
            preview.classList.add('show');
        } else {
            preview.innerHTML = '<i class="fa fa-circle-check" style="color:var(--green);margin-right:5px;"></i>Returned on time. No penalty.';
            preview.classList.add('show');
        }
    }

    // Photo upload handler
    var selectedFiles = [];

    function handlePhotoSelect(input) {
        var newFiles = Array.from(input.files);
        var errorDiv = document.getElementById('photoError');
        errorDiv.style.display = 'none';

        // Check total count
        if (selectedFiles.length + newFiles.length > 4) {
            errorDiv.textContent = 'Maximum 4 photos allowed. ' + selectedFiles.length + ' already selected.';
            errorDiv.style.display = 'block';
            input.value = '';
            return;
        }

        // Check file sizes
        for (var i = 0; i < newFiles.length; i++) {
            if (newFiles[i].size > 5 * 1024 * 1024) {
                errorDiv.textContent = newFiles[i].name + ' is too large. Max 5MB per photo.';
                errorDiv.style.display = 'block';
                input.value = '';
                return;
            }
        }

        selectedFiles = selectedFiles.concat(newFiles);
        renderPreviews();
        input.value = ''; // reset so same file can be re-added
    }

    function removePhoto(index) {
        selectedFiles.splice(index, 1);
        renderPreviews();
    }

    function renderPreviews() {
        var container = document.getElementById('photoPreviews');
        var area      = document.getElementById('photoUploadArea');
        container.innerHTML = '';

        selectedFiles.forEach(function(file, i) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var item = document.createElement('div');
                item.className = 'photo-preview-item';
                item.innerHTML =
                    '<img src="' + e.target.result + '" alt="photo">' +
                    '<button type="button" class="photo-remove" onclick="removePhoto(' + i + ')"><i class="fa fa-xmark"></i></button>';
                container.appendChild(item);
            };
            reader.readAsDataURL(file);
        });

        // Update upload area
        if (selectedFiles.length >= 4) {
            area.style.opacity = '0.4';
            area.style.pointerEvents = 'none';
            area.querySelector('span').textContent = '4/4 photos selected';
        } else {
            area.style.opacity = '1';
            area.style.pointerEvents = 'auto';
            area.querySelector('span').textContent = selectedFiles.length > 0
                ? (selectedFiles.length + '/4 photos — click to add more')
                : 'Click to upload photos';
        }
    }

    function closeModal() { document.getElementById('returnModal').classList.remove('open'); }
    document.getElementById('returnModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
</script>
</body>
</html>