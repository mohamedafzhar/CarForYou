<?php
session_start();
include 'config.php';
adminAuth();

$msg   = "";
$error = "";

// ── DELETE ────────────────────────────────────────────────────────────────────
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $img_stmt = $conn->prepare("SELECT Vimage1, Vimage2, Vimage3, Vimage4 FROM cars WHERE id=?");
    $img_stmt->bind_param("i", $id);
    $img_stmt->execute();
    if ($img_row = $img_stmt->get_result()->fetch_assoc()) {
        foreach (['Vimage1','Vimage2','Vimage3','Vimage4'] as $col) {
            if (!empty($img_row[$col])) {
                $fp = "img/vehicleimages/" . $img_row[$col];
                if (file_exists($fp)) unlink($fp);
            }
        }
    }
    $stmt = $conn->prepare("DELETE FROM cars WHERE id=?");
    $stmt->bind_param("i", $id);
    $msg   = $stmt->execute() ? "Car deleted successfully." : "";
    $error = (!$msg) ? "Error deleting record." : "";
}

// ── Helper: upload one image slot ────────────────────────────────────────────
function uploadCarImage(string $field, ?string $old = ''): string {
    $old = $old ?? '';
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return $old;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return $old;
    $allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
    if (!in_array($_FILES[$field]['type'], $allowed)) return $old;
    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) return $old;
    $dir  = "img/vehicleimages/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext  = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $name = md5($_FILES[$field]['name'] . time() . $field) . '.' . $ext;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $name)) {
        if ($old && file_exists($dir . $old)) @unlink($dir . $old);
        return $name;
    }
    return $old;
}

// ── CREATE / UPDATE ───────────────────────────────────────────────────────────
if (isset($_POST['submit_form'])) {
    $car_id          = trim($_POST['car_id'] ?? '');
    $vtitle          = trim($_POST['cartitle'] ?? '');
    $vbrand          = trim($_POST['brand'] ?? '');
    $voverview       = trim($_POST['caroverview'] ?? '');
    $priceperday     = intval($_POST['priceperday'] ?? 0);
    $fueltype        = trim($_POST['fueltype'] ?? '');
    $modelyear       = trim($_POST['modelyear'] ?? '');
    $seatingcapacity = intval($_POST['seatingcapacity'] ?? 4);

    // Fetch existing images if editing
    $old = ['Vimage1'=>'','Vimage2'=>'','Vimage3'=>'','Vimage4'=>''];
    if (!empty($car_id)) {
        $os = $conn->prepare("SELECT Vimage1,Vimage2,Vimage3,Vimage4 FROM cars WHERE id=?");
        $os->bind_param("i", $car_id);
        $os->execute();
        $old = array_merge($old, $os->get_result()->fetch_assoc() ?? []);
    }

    // Upload each slot
    $img1 = uploadCarImage('img1', $old['Vimage1']);
    $img2 = uploadCarImage('img2', $old['Vimage2']);
    $img3 = uploadCarImage('img3', $old['Vimage3']);
    $img4 = uploadCarImage('img4', $old['Vimage4']);

    // Handle remove checkboxes
    $dir = "img/vehicleimages/";
    foreach ([1=>$img1,2=>$img2,3=>$img3,4=>$img4] as $n=>$fn) {
        if (!empty($_POST["remove_img{$n}"]) && $fn) {
            if (file_exists($dir . $fn)) @unlink($dir . $fn);
            $${"img{$n}"} = '';
        }
    }

    if (!empty($car_id)) {
        $sql  = "UPDATE cars SET car_name=?,car_brand=?,car_model=?,car_type=?,price_per_day=?,car_overview=?,seating_capacity=?,Vimage1=?,Vimage2=?,Vimage3=?,Vimage4=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssissssssi", $vtitle,$vbrand,$modelyear,$fueltype,$priceperday,$voverview,$seatingcapacity,$img1,$img2,$img3,$img4,$car_id);
        $msg = "Car updated successfully!";
    } else {
        $sql  = "INSERT INTO cars(car_name,car_brand,car_model,car_type,price_per_day,car_overview,seating_capacity,Vimage1,Vimage2,Vimage3,Vimage4,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,'Available',NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssissssss", $vtitle,$vbrand,$modelyear,$fueltype,$priceperday,$voverview,$seatingcapacity,$img1,$img2,$img3,$img4);
        $msg = "Car published successfully!";
    }

    if (!$stmt->execute()) {
        $error = "Database error: " . $stmt->error;
        $msg   = "";
    }
}

// Fetch all cars
$cars_res   = $conn->query("SELECT * FROM cars ORDER BY id DESC");
$cars_total = $cars_res ? $cars_res->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cars | CarForYou Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root { --sw:268px; --tr:0.35s cubic-bezier(0.4,0,0.2,1); }

        [data-theme="dark"] {
            --bg:#0d1117; --bg2:#131920; --surface:#1e2738; --surface2:#253044;
            --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.16);
            --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
            --accent:#4f8ef7; --accent2:#7db0fb; --glow:rgba(79,142,247,0.22);
            --sbg:#0a1020; --sborder:rgba(79,142,247,0.1);
            --cshadow:0 4px 24px rgba(0,0,0,0.35);
            --hbg:rgba(13,17,23,0.9);
            --input-bg:#253044; --input-border:rgba(99,155,255,0.15);
        }
        [data-theme="light"] {
            --bg:#f0f4f8; --bg2:#e8edf3; --surface:#ffffff; --surface2:#f5f7fa;
            --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
            --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
            --accent:#2563eb; --accent2:#3b82f6; --glow:rgba(37,99,235,0.16);
            --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06);
            --cshadow:0 4px 20px rgba(28,43,58,0.08);
            --hbg:rgba(240,244,248,0.92);
            --input-bg:#ffffff; --input-border:rgba(99,120,155,0.25);
        }

        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        html{font-size:16px;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background var(--tr),color var(--tr);}
        ::-webkit-scrollbar{width:4px;}
        ::-webkit-scrollbar-track{background:var(--bg);}
        ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px;}
        a{text-decoration:none;color:inherit;}

        /* SIDEBAR */
        .sidebar{width:var(--sw);min-height:100vh;background:var(--sbg);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;border-right:1px solid var(--sborder);z-index:100;overflow-y:auto;transition:background var(--tr);}
        .sb-brand{padding:28px 24px 20px;border-bottom:1px solid var(--sborder);}
        .sb-brand h2{font-family:'Syne',sans-serif;font-size:1.45rem;font-weight:800;color:#e8edf5;}
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
        .admin-pill{display:flex;align-items:center;gap:9px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:6px 13px;}
        .av{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:800;color:#fff;}
        .admin-pill .aname{font-size:0.82rem;font-weight:600;color:var(--text);}
        .admin-pill .arole{font-size:0.68rem;color:var(--text2);}

        /* BODY */
        .body{padding:26px 36px;flex:1;}

        /* ALERTS */
        .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:10px;font-size:0.86rem;font-weight:500;margin-bottom:20px;opacity:0;animation:fadeUp 0.4s ease forwards;}
        .alert i{font-size:0.95rem;}
        .alert-success{background:rgba(34,197,94,0.1);color:#22c55e;border:1px solid rgba(34,197,94,0.2);}
        .alert-error{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2);}

        /* CARDS */
        .card{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:24px;margin-bottom:20px;transition:background var(--tr),border-color var(--tr);opacity:0;animation:fadeUp 0.5s ease forwards;}
        .card:nth-of-type(1){animation-delay:0.05s;}
        .card:nth-of-type(2){animation-delay:0.18s;}
        .card-head{display:flex;justify-content:space-between;align-items:center;padding-bottom:16px;margin-bottom:20px;border-bottom:1px solid var(--border);}
        .card-head h3{font-family:'Syne',sans-serif;font-size:0.95rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:9px;}
        .card-head h3 i{color:var(--accent);font-size:0.85rem;}
        .count-pill{font-size:0.72rem;font-weight:700;background:var(--glow);color:var(--accent);padding:3px 10px;border-radius:20px;}

        /* FORM */
        .form-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:22px;}
        .span-3{grid-column:span 3;}
        .span-2{grid-column:span 2;}
        .form-group{display:flex;flex-direction:column;gap:6px;}
        .form-group label{font-size:0.78rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text3);}
        .form-group label .req{color:#ef4444;margin-left:2px;}
        .form-control{padding:10px 13px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:8px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.88rem;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
        .form-control::placeholder{color:var(--text3);}
        .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--glow);}
        select.form-control option{background:var(--surface2);color:var(--text);}
        textarea.form-control{resize:vertical;min-height:90px;}

        /* ── IMAGE UPLOAD GRID ── */
        .img-upload-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
        .img-slot { display:flex; flex-direction:column; gap:7px; }
        .img-slot-label {
            font-size:0.68rem; font-weight:700; letter-spacing:0.1em;
            text-transform:uppercase; color:var(--text3);
            display:flex; align-items:center; gap:6px;
        }
        .main-badge {
            font-size:0.55rem; font-weight:800; letter-spacing:0.06em;
            background:rgba(79,142,247,0.15); border:1px solid rgba(79,142,247,0.3);
            color:var(--accent); padding:1px 6px; border-radius:20px;
        }

        /* Drop zone */
        .drop-zone {
            position:relative; aspect-ratio:4/3;
            border:2px dashed var(--input-border); border-radius:10px;
            background:var(--input-bg); overflow:hidden;
            cursor:pointer; transition:all 0.22s;
            display:flex; flex-direction:column;
            align-items:center; justify-content:center; gap:5px;
        }
        .drop-zone:hover { border-color:var(--accent); background:rgba(79,142,247,0.06); }
        .drop-zone.has-image { border-style:solid; border-color:var(--accent); }
        .drop-zone.dragover { border-color:var(--accent); background:rgba(79,142,247,0.1); transform:scale(1.02); }

        .drop-zone input[type="file"] {
            position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; z-index:2;
        }
        .dz-icon { font-size:1.3rem; color:var(--text3); pointer-events:none; }
        .dz-text { font-size:0.68rem; color:var(--text3); text-align:center; pointer-events:none; line-height:1.4; }
        .dz-text strong { color:var(--accent); display:block; font-size:0.7rem; }

        /* Preview */
        .drop-zone .preview-img {
            position:absolute; inset:0; width:100%; height:100%;
            object-fit:cover; border-radius:8px; display:none; z-index:1;
        }
        .drop-zone.has-image .preview-img { display:block; }
        .drop-zone.has-image .dz-icon,
        .drop-zone.has-image .dz-text { display:none; }

        /* Hover controls */
        .drop-zone .img-controls {
            position:absolute; inset:0; background:rgba(0,0,0,0.55);
            border-radius:8px; display:none; flex-direction:column;
            align-items:center; justify-content:center; gap:7px; z-index:3;
        }
        .drop-zone.has-image:hover .img-controls { display:flex; }
        .ic-btn {
            pointer-events:all; cursor:pointer;
            padding:5px 12px; border-radius:7px; font-size:0.7rem; font-weight:700;
            border:none; font-family:'DM Sans',sans-serif; transition:all 0.15s;
            display:flex; align-items:center; gap:5px;
        }
        .ic-change { background:var(--accent); color:#fff; }
        .ic-remove { background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); color:#ef4444; }
        .ic-change:hover { opacity:0.85; }
        .ic-remove:hover { background:#ef4444; color:#fff; }
        .remove-check { display:none; }

        /* form buttons */
        .form-actions{display:flex;gap:10px;align-items:center;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:600;border:none;cursor:pointer;transition:all 0.22s;}
        .btn-primary{background:var(--accent);color:#fff;box-shadow:0 3px 12px var(--glow);}
        .btn-primary:hover{opacity:0.88;transform:translateY(-1px);box-shadow:0 6px 18px var(--glow);}
        .btn-reset{background:var(--surface2);color:var(--text2);border:1px solid var(--border2);}
        .btn-reset:hover{color:var(--text);border-color:var(--border2);}

        /* TABLE */
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;min-width:760px;}
        th{font-size:0.65rem;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:var(--text3);padding:0 14px 11px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
        td{padding:13px 14px;font-size:0.855rem;color:var(--text2);border-bottom:1px solid var(--border);transition:background 0.15s;vertical-align:middle;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(79,142,247,0.04);color:var(--text);}
        td strong{color:var(--text);font-weight:600;}
        .row-num{font-family:'Syne',sans-serif;font-size:0.78rem;font-weight:700;color:var(--text3);}
        .v-img{width:78px;height:50px;object-fit:cover;border-radius:7px;border:1px solid var(--border2);display:block;}
        .no-img{font-size:0.72rem;color:var(--text3);display:flex;align-items:center;gap:5px;}
        .no-img i{font-size:1.1rem;}
        .price-tag{font-family:'Syne',sans-serif;font-weight:700;font-size:0.88rem;color:var(--text);}
        .price-tag span{font-size:0.7rem;font-weight:400;color:var(--text3);}
        .fuel-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:0.68rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;}
        .fuel-Petrol{background:rgba(251,191,36,0.12);color:#fbbf24;}
        .fuel-Diesel{background:rgba(156,163,175,0.12);color:#9ca3af;}
        .fuel-Hybrid{background:rgba(34,197,94,0.12);color:#22c55e;}
        .fuel-Electric{background:rgba(79,142,247,0.12);color:var(--accent);}
        .acts{display:flex;gap:7px;}
        .abt{display:inline-flex;align-items:center;gap:4px;padding:6px 11px;border-radius:7px;font-size:0.76rem;font-weight:600;border:1px solid transparent;cursor:pointer;transition:all 0.2s;text-decoration:none;}
        .abt-edit{color:var(--accent);border-color:rgba(79,142,247,0.3);background:rgba(79,142,247,0.07);}
        .abt-edit:hover{background:var(--accent);color:#fff;}
        .abt-delete{color:#ef4444;border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.07);}
        .abt-delete:hover{background:#ef4444;color:#fff;}
        .empty-row td{text-align:center;padding:44px;color:var(--text3);font-size:0.85rem;}
        .empty-row td i{display:block;font-size:2rem;margin-bottom:10px;opacity:0.3;}

        /* img count dots in table */
        .img-dots { display:flex; gap:4px; margin-top:4px; }
        .img-dot {
            width:6px; height:6px; border-radius:50%;
            background:var(--border2);
        }
        .img-dot.filled { background:var(--accent); }

        @keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
        .hamburger{display:none;width:38px;height:38px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;font-size:0.95rem;transition:all 0.2s;}
        .hamburger:hover{border-color:var(--accent);color:var(--accent);}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;}
        @media (max-width:768px){
            .hamburger{display:flex;align-items:center;justify-content:center;}
            .sidebar{transform:translateX(-100%);z-index:200;}
            .sidebar.open{transform:translateX(0);}
            .sidebar-overlay.open{display:block;}
            .main{margin-left:0;width:100%;}
            .body{padding:20px 16px;}
            .card{padding:18px 16px;}
            .form-grid{grid-template-columns:1fr;}
            .img-upload-grid{grid-template-columns:1fr 1fr;}
            .top-bar{padding:0 16px;}
            table{font-size:0.8rem;}
            td,th{padding:10px 8px;}
        }
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
        <li class="active"><a href="car.php"><i class="fa fa-car"></i> Cars</a></li>
        <li><a href="bookings.php"><i class="fa fa-calendar-check"></i> Bookings</a></li>
        <li><a href="reg-users.php"><i class="fa fa-users"></i> Registered Users</a></li>
        <div class="sb-section">Finance & Operations</div>
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
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main">

    <div class="top-bar">
        <div class="tb-left" style="display:flex;align-items:center;gap:12px;">
            <button class="hamburger" id="hamburgerBtn"><i class="fa fa-bars"></i></button>
            <div>
                <h2 id="pageTitle">Car Management</h2>
                <p id="dateLabel"></p>
            </div>
        </div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn" title="Toggle Theme">
                <i class="fa fa-moon" id="themeIcon"></i>
            </button>
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

        <?php if ($msg): ?>
        <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ── FORM CARD ── -->
        <div class="card">
            <div class="card-head">
                <h3 id="form-title"><i class="fa fa-plus-circle"></i> Post a New Car</h3>
            </div>

            <form method="POST" enctype="multipart/form-data" id="carForm">
                <input type="hidden" name="car_id" id="car_id">

                <!-- ── IMAGE UPLOAD SECTION ── -->
                <div class="form-group span-3" style="margin-bottom:22px;">
                    <label style="font-size:0.78rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text3);margin-bottom:10px;display:block;">
                        <i class="fa fa-images" style="color:var(--accent);margin-right:5px;"></i>
                        Vehicle Photos
                        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text3);font-size:0.72rem;">(up to 4 — Photo 1 is the main listing image)</span>
                    </label>
                    <div class="img-upload-grid">
                        <?php
                        $slots = [
                            1 => 'Photo 1',
                            2 => 'Photo 2',
                            3 => 'Photo 3',
                            4 => 'Photo 4',
                        ];
                        foreach ($slots as $n => $label):
                        ?>
                        <div class="img-slot">
                            <div class="img-slot-label">
                                <i class="fa fa-image"></i> <?php echo $label; ?>
                                <?php if ($n === 1): ?><span class="main-badge">Main</span><?php endif; ?>
                            </div>
                            <div class="drop-zone" id="zone<?php echo $n; ?>"
                                 ondragover="dzDragOver(event,this)"
                                 ondragleave="dzDragLeave(this)"
                                 ondrop="dzDrop(event,this,<?php echo $n; ?>)">

                                <input type="file" name="img<?php echo $n; ?>" id="file<?php echo $n; ?>"
                                       accept="image/jpeg,image/png,image/webp,image/gif"
                                       onchange="previewImage(this,<?php echo $n; ?>)">

                                <i class="fa fa-cloud-arrow-up dz-icon"></i>
                                <div class="dz-text">
                                    <strong>Click or drag</strong>
                                    JPG, PNG, WebP
                                </div>

                                <img class="preview-img" id="preview<?php echo $n; ?>" src="" alt="">

                                <div class="img-controls">
                                    <button type="button" class="ic-btn ic-change"
                                            onclick="document.getElementById('file<?php echo $n; ?>').click(); event.stopPropagation();">
                                        <i class="fa fa-arrow-up-from-bracket"></i> Change
                                    </button>
                                    <button type="button" class="ic-btn ic-remove"
                                            onclick="removeImage(<?php echo $n; ?>); event.stopPropagation();">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                            <input type="checkbox" name="remove_img<?php echo $n; ?>" id="removeCheck<?php echo $n; ?>" class="remove-check" value="1">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── CAR DETAILS ── -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Car Title <span class="req">*</span></label>
                        <input type="text" name="cartitle" id="cartitle" class="form-control" placeholder="e.g. Toyota Corolla" required>
                    </div>
                    <div class="form-group">
                        <label>Car Brand <span class="req">*</span></label>
                        <input type="text" name="brand" id="brand" class="form-control" placeholder="e.g. Toyota" required>
                    </div>
                    <div class="form-group">
                        <label>Model Year <span class="req">*</span></label>
                        <input type="number" name="modelyear" id="modelyear" class="form-control" placeholder="e.g. 2023" required>
                    </div>

                    <div class="form-group span-3">
                        <label>Car Overview <span class="req">*</span></label>
                        <textarea name="caroverview" id="caroverview" class="form-control"
                            placeholder="Describe the car features, condition, extras…" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Price Per Day (LKR) <span class="req">*</span></label>
                        <input type="number" name="priceperday" id="priceperday" class="form-control" placeholder="e.g. 15000" required>
                    </div>
                    <div class="form-group">
                        <label>Fuel Type <span class="req">*</span></label>
                        <select name="fueltype" id="fueltype" class="form-control" required>
                            <option value="">Select Fuel Type</option>
                            <option value="Petrol">Petrol</option>
                            <option value="Diesel">Diesel</option>
                            <option value="Hybrid">Hybrid</option>
                            <option value="Electric">Electric</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Seating Capacity <span class="req">*</span></label>
                        <input type="number" name="seatingcapacity" id="seatingcapacity" class="form-control" placeholder="e.g. 5" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit_form" id="submit_btn" class="btn btn-primary">
                        <i class="fa fa-upload"></i> Publish Car
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-reset">
                        <i class="fa fa-rotate-left"></i> Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- ── LISTING CARD ── -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-list"></i> Car Listing</h3>
                <span class="count-pill"><?php echo $cars_total; ?> car<?php echo $cars_total != 1 ? 's' : ''; ?></span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Brand</th>
                            <th>Year</th>
                            <th>Price / Day</th>
                            <th>Fuel</th>
                            <th>Seats</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($cars_res && $cars_res->num_rows > 0):
                        $cnt = 1;
                        while ($row = $cars_res->fetch_assoc()):
                            $fuel = htmlspecialchars($row['car_type']);
                            // Count how many images this car has
                            $img_count = 0;
                            foreach (['Vimage1','Vimage2','Vimage3','Vimage4'] as $c) {
                                if (!empty($row[$c])) $img_count++;
                            }
                    ?>
                        <tr>
                            <td><span class="row-num"><?php echo $cnt; ?></span></td>
                            <td>
                                <?php if (!empty($row['Vimage1'])): ?>
                                    <img src="img/vehicleimages/<?php echo htmlspecialchars($row['Vimage1']); ?>" class="v-img" alt="car">
                                    <div class="img-dots" title="<?php echo $img_count; ?>/4 photos">
                                        <?php for ($d=1;$d<=4;$d++): ?>
                                        <div class="img-dot <?php echo $d<=$img_count?'filled':''; ?>"></div>
                                        <?php endfor; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-img"><i class="fa fa-image"></i> No Image</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['car_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['car_brand'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($row['car_model']); ?></td>
                            <td><div class="price-tag">Rs. <?php echo number_format($row['price_per_day']); ?><br><span>/day</span></div></td>
                            <td><span class="fuel-badge fuel-<?php echo $fuel; ?>"><?php echo $fuel; ?></span></td>
                            <td><?php echo htmlspecialchars($row['seating_capacity'] ?? '—'); ?></td>
                            <td>
                                <div class="acts">
                                    <a onclick='populateEdit(<?php echo json_encode($row); ?>)' class="abt abt-edit"><i class="fa fa-pen"></i></a>
                                    <a href="car.php?del=<?php echo $row['id']; ?>" class="abt abt-delete"
                                       onclick="return confirm('Delete this car and all its images permanently?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php $cnt++; endwhile;
                    else: ?>
                        <tr class="empty-row">
                            <td colspan="9"><i class="fa fa-car"></i> No cars found. Add your first listing above.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
// ── Live date ─────────────────────────────────────────────────────────────────
(function(){
    var d=new Date(), D=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
    M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('dateLabel').textContent = D[d.getDay()]+', '+d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear();
})();

// ── Theme ─────────────────────────────────────────────────────────────────────
var theme = localStorage.getItem('adminTheme') || 'dark';
document.documentElement.setAttribute('data-theme', theme);
syncIcon();
document.getElementById('themeBtn').addEventListener('click', function(){
    theme = theme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('adminTheme', theme);
    syncIcon();
});
function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }

// ── Mobile sidebar ────────────────────────────────────────────────────────────
document.getElementById('hamburgerBtn').addEventListener('click', function(){
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
});
document.getElementById('sidebarOverlay').addEventListener('click', function(){
    document.querySelector('.sidebar').classList.remove('open');
    this.classList.remove('open');
});

// ── Image preview ─────────────────────────────────────────────────────────────
function previewImage(input, n) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('preview' + n).src = e.target.result;
        document.getElementById('zone' + n).classList.add('has-image');
        document.getElementById('removeCheck' + n).checked = false;
    };
    reader.readAsDataURL(input.files[0]);
}

// ── Remove image ──────────────────────────────────────────────────────────────
function removeImage(n) {
    document.getElementById('preview' + n).src = '';
    document.getElementById('zone' + n).classList.remove('has-image');
    document.getElementById('file' + n).value = '';
    document.getElementById('removeCheck' + n).checked = true;
}

// ── Drag and drop ─────────────────────────────────────────────────────────────
function dzDragOver(e, zone) { e.preventDefault(); zone.classList.add('dragover'); }
function dzDragLeave(zone)   { zone.classList.remove('dragover'); }
function dzDrop(e, zone, n)  {
    e.preventDefault(); zone.classList.remove('dragover');
    var files = e.dataTransfer.files;
    if (!files || !files[0]) return;
    var input = document.getElementById('file' + n);
    var dt = new DataTransfer(); dt.items.add(files[0]); input.files = dt.files;
    previewImage(input, n);
}

// ── Populate edit form ────────────────────────────────────────────────────────
function populateEdit(data) {
    document.getElementById('form-title').innerHTML = '<i class="fa fa-pen-to-square"></i> Edit Car: ' + data.car_name;
    document.getElementById('submit_btn').innerHTML = '<i class="fa fa-floppy-disk"></i> Update Car';
    document.getElementById('car_id').value          = data.id;
    document.getElementById('cartitle').value        = data.car_name    || '';
    document.getElementById('brand').value           = data.car_brand   || '';
    document.getElementById('modelyear').value       = data.car_model   || '';
    document.getElementById('caroverview').value     = data.car_overview|| '';
    document.getElementById('priceperday').value     = data.price_per_day;
    document.getElementById('fueltype').value        = data.car_type;
    document.getElementById('seatingcapacity').value = data.seating_capacity || '';

    // Load existing images into drop zones
    var base = 'img/vehicleimages/';
    [1,2,3,4].forEach(function(n) {
        var col  = 'Vimage' + n;
        var zone = document.getElementById('zone' + n);
        var prev = document.getElementById('preview' + n);
        var chk  = document.getElementById('removeCheck' + n);
        chk.checked = false;
        document.getElementById('file' + n).value = ''; // clear any pending upload
        if (data[col]) {
            prev.src = base + data[col];
            zone.classList.add('has-image');
        } else {
            prev.src = '';
            zone.classList.remove('has-image');
        }
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Reset form ────────────────────────────────────────────────────────────────
function resetForm() {
    document.getElementById('carForm').reset();
    document.getElementById('car_id').value = '';
    document.getElementById('form-title').innerHTML = '<i class="fa fa-plus-circle"></i> Post a New Car';
    document.getElementById('submit_btn').innerHTML = '<i class="fa fa-upload"></i> Publish Car';
    [1,2,3,4].forEach(function(n) {
        document.getElementById('preview' + n).src = '';
        document.getElementById('zone' + n).classList.remove('has-image');
        document.getElementById('removeCheck' + n).checked = false;
    });
}

// ── Auto-hide alerts ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.alert').forEach(function(el){
        setTimeout(function(){
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity    = '0';
            setTimeout(function(){ el.style.display='none'; }, 500);
        }, 2500);
    });
});
</script>
</body>
</html>