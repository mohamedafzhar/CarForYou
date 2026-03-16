<?php
session_start();
include 'config.php';

if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
    header('Location: index.php');
    exit();
}

$msg = ""; $error = "";

if (isset($_POST['add_testimonial'])) {
    $user_email  = trim($_POST['new_email']);
    $testimonial = trim($_POST['new_testimonial']);
    $rating      = intval($_POST['new_rating']);
    $status      = intval($_POST['new_status']);
    if (empty($user_email) || empty($testimonial)) { $error = "Email and testimonial text are required."; }
    else {
        $stmt = $conn->prepare("INSERT INTO testimonials (user_email,testimonial,rating,status,posting_date) VALUES (?,?,?,?,NOW())");
        $stmt->bind_param("ssii", $user_email, $testimonial, $rating, $status);
        $msg = $stmt->execute() ? "Testimonial added successfully." : "Error: " . $stmt->error;
    }
}

if (isset($_POST['update_testimonial'])) {
    $id = intval($_POST['edit_id']); $user_email = trim($_POST['edit_email']);
    $testimonial = trim($_POST['edit_testimonial']); $rating = intval($_POST['edit_rating']); $status = intval($_POST['edit_status']);
    if (empty($user_email) || empty($testimonial)) { $error = "Email and testimonial text are required."; }
    else {
        $stmt = $conn->prepare("UPDATE testimonials SET user_email=?,testimonial=?,rating=?,status=? WHERE id=?");
        $stmt->bind_param("ssiii", $user_email, $testimonial, $rating, $status, $id);
        $msg = $stmt->execute() ? "Testimonial updated successfully." : "Error: " . $stmt->error;
    }
}

if (isset($_GET['approve']))   { $id=intval($_GET['approve']);   $stmt=$conn->prepare("UPDATE testimonials SET status=1 WHERE id=?"); $stmt->bind_param("i",$id); $msg=$stmt->execute()?"Testimonial approved.":"Error."; }
if (isset($_GET['unapprove'])) { $id=intval($_GET['unapprove']); $stmt=$conn->prepare("UPDATE testimonials SET status=0 WHERE id=?"); $stmt->bind_param("i",$id); $msg=$stmt->execute()?"Testimonial unapproved.":"Error."; }
if (isset($_GET['del']))       { $id=intval($_GET['del']);        $stmt=$conn->prepare("DELETE FROM testimonials WHERE id=?");        $stmt->bind_param("i",$id); $msg=$stmt->execute()?"Testimonial deleted.":"Error."; }

$filter = $_GET['filter'] ?? 'all';
if ($filter==='approved')     $res = $conn->query("SELECT * FROM testimonials WHERE status=1 ORDER BY posting_date DESC");
elseif ($filter==='pending')  $res = $conn->query("SELECT * FROM testimonials WHERE status=0 ORDER BY posting_date DESC");
else                          $res = $conn->query("SELECT * FROM testimonials ORDER BY posting_date DESC");

$total_count    = $conn->query("SELECT id FROM testimonials")->num_rows;
$approved_count = $conn->query("SELECT id FROM testimonials WHERE status=1")->num_rows;
$pending_count  = $conn->query("SELECT id FROM testimonials WHERE status=0")->num_rows;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials | CarForYou Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
    :root { --sw:268px; --tr:0.35s cubic-bezier(0.4,0,0.2,1); }

    [data-theme="dark"] {
        --bg:#0d1117; --bg2:#131920;
        --surface:#1e2738; --surface2:#253044;
        --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.16);
        --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
        --accent:#4f8ef7; --accent2:#7db0fb;
        --glow:rgba(79,142,247,0.22);
        --sbg:#0a1020; --sborder:rgba(79,142,247,0.1);
        --cshadow:0 4px 24px rgba(0,0,0,0.35);
        --hbg:rgba(13,17,23,0.9);
        --modal-bg:#1e2738; --input-bg:#253044; --input-border:rgba(99,155,255,0.18);
        --overlay:rgba(0,0,0,0.7);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --bg2:#e8edf3;
        --surface:#ffffff; --surface2:#f5f7fa;
        --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
        --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
        --accent:#2563eb; --accent2:#3b82f6;
        --glow:rgba(37,99,235,0.16);
        --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06);
        --cshadow:0 4px 20px rgba(28,43,58,0.08);
        --hbg:rgba(240,244,248,0.92);
        --modal-bg:#ffffff; --input-bg:#ffffff; --input-border:rgba(99,120,155,0.28);
        --overlay:rgba(13,17,23,0.6);
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { font-size:16px; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; transition:background var(--tr),color var(--tr); }
    ::-webkit-scrollbar{width:4px} ::-webkit-scrollbar-track{background:var(--bg)} ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px}
    a { text-decoration:none; color:inherit; }

    /* SIDEBAR */
    .sidebar { width:var(--sw); min-height:100vh; background:var(--sbg); position:fixed; top:0; left:0; bottom:0; display:flex; flex-direction:column; border-right:1px solid var(--sborder); z-index:100; overflow-y:auto; transition:background var(--tr); }
    .sb-brand { padding:28px 24px 20px; border-bottom:1px solid var(--sborder); }
    .sb-brand h2 { font-family:'Syne',sans-serif; font-size:1.45rem; font-weight:800; color:#e8edf5; }
    .sb-brand h2 span { color:var(--accent); }
    .sb-brand p { font-size:0.68rem; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:rgba(232,237,245,0.3); margin-top:4px; }
    .sb-section { font-size:0.62rem; font-weight:700; letter-spacing:0.18em; text-transform:uppercase; color:rgba(232,237,245,0.25); padding:22px 24px 6px; }
    .sb-menu { list-style:none; padding:6px 12px; }
    .sb-menu li { margin-bottom:2px; }
    .sb-menu li a { display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:9px; font-size:0.86rem; font-weight:500; color:rgba(232,237,245,0.5); transition:all 0.2s; }
    .sb-menu li a i { width:18px; text-align:center; font-size:0.85rem; }
    .sb-menu li:hover a { background:rgba(79,142,247,0.09); color:rgba(232,237,245,0.88); }
    .sb-menu li.active a { background:linear-gradient(90deg,rgba(79,142,247,0.2),rgba(79,142,247,0.05)); color:var(--accent); font-weight:600; box-shadow:inset 3px 0 0 var(--accent); }
    .sb-menu li.active a i { color:var(--accent); }
    .sb-divider { height:1px; background:var(--sborder); margin:10px 0; }

    /* MAIN */
    .main { margin-left:var(--sw); width:calc(100% - var(--sw)); min-height:100vh; display:flex; flex-direction:column; }

    /* TOPBAR */
    .top-bar { position:sticky; top:0; z-index:50; background:var(--hbg); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); border-bottom:1px solid var(--border); padding:0 36px; height:66px; display:flex; align-items:center; justify-content:space-between; transition:background var(--tr); }
    .tb-left h2 { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
    .tb-left p { font-size:0.73rem; color:var(--text2); margin-top:1px; }
    .tb-right { display:flex; align-items:center; gap:10px; }
    .theme-btn { width:37px; height:37px; border-radius:9px; border:1px solid var(--border2); background:var(--surface); color:var(--text2); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.88rem; transition:all 0.2s; }
    .theme-btn:hover { border-color:var(--accent); color:var(--accent); box-shadow:0 0 10px var(--glow); }
    .admin-pill { display:flex; align-items:center; gap:9px; background:var(--surface); border:1px solid var(--border2); border-radius:9px; padding:6px 13px; }
    .av { width:28px; height:28px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:800; color:#fff; }
    .admin-pill .aname { font-size:0.82rem; font-weight:600; color:var(--text); }
    .admin-pill .arole { font-size:0.68rem; color:var(--text2); }

    /* BODY */
    .body { padding:26px 36px; flex:1; }

    /* ALERTS */
    .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:10px; font-size:0.86rem; font-weight:500; margin-bottom:20px; opacity:0; animation:fadeUp 0.4s ease forwards; }
    .alert i { font-size:0.95rem; }
    .alert-success { background:rgba(34,197,94,0.1); color:#22c55e; border:1px solid rgba(34,197,94,0.2); }
    .alert-error   { background:rgba(239,68,68,0.1);  color:#ef4444; border:1px solid rgba(239,68,68,0.2); }

    /* STAT CARDS */
    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:20px; }
    .sc { background:var(--surface); border:1px solid var(--border); border-radius:13px; padding:20px; display:flex; justify-content:space-between; align-items:center; position:relative; overflow:hidden; cursor:default; opacity:0; animation:fadeUp 0.5s ease forwards; transition:transform 0.25s, box-shadow 0.25s; }
    .sc::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--accent),var(--accent2)); transform:scaleX(0); transform-origin:left; transition:transform 0.35s ease; }
    .sc:hover { transform:translateY(-3px); box-shadow:var(--cshadow); }
    .sc:hover::before { transform:scaleX(1); }
    .sc:nth-child(1){animation-delay:0.05s} .sc:nth-child(2){animation-delay:0.10s} .sc:nth-child(3){animation-delay:0.15s}
    .sc-num { font-family:'Syne',sans-serif; font-size:1.9rem; font-weight:800; color:var(--text); line-height:1; }
    .sc-lbl { font-size:0.72rem; font-weight:600; letter-spacing:0.07em; text-transform:uppercase; color:var(--text3); margin-top:4px; }
    .sc-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; transition:all 0.25s; }

    /* MAIN CARD */
    .card { background:var(--surface); border:1px solid var(--border); border-radius:13px; padding:24px; transition:background var(--tr),border-color var(--tr); opacity:0; animation:fadeUp 0.5s ease 0.2s forwards; }
    .card-head { display:flex; justify-content:space-between; align-items:center; padding-bottom:16px; margin-bottom:16px; border-bottom:1px solid var(--border); gap:12px; flex-wrap:wrap; }
    .card-head h3 { font-family:'Syne',sans-serif; font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:9px; }
    .card-head h3 i { color:var(--accent); font-size:0.85rem; }
    .card-head-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

    /* FILTER TABS */
    .filter-tabs { display:flex; gap:6px; }
    .tab { padding:6px 14px; border-radius:20px; font-size:0.76rem; font-weight:600; border:1px solid var(--border2); color:var(--text2); background:transparent; cursor:pointer; transition:all 0.2s; text-decoration:none; white-space:nowrap; }
    .tab:hover { border-color:var(--accent); color:var(--accent); background:var(--glow); }
    .tab.active { background:var(--accent); color:#fff; border-color:var(--accent); box-shadow:0 2px 10px var(--glow); }

    /* ADD BUTTON */
    .btn-add { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; border-radius:9px; font-family:'DM Sans',sans-serif; font-size:0.82rem; font-weight:600; background:var(--accent); color:#fff; border:none; cursor:pointer; transition:all 0.2s; box-shadow:0 3px 10px var(--glow); white-space:nowrap; }
    .btn-add:hover { opacity:0.88; transform:translateY(-1px); }

    /* TABLE */
    .table-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width:740px; }
    th { font-size:0.65rem; font-weight:700; letter-spacing:0.13em; text-transform:uppercase; color:var(--text3); padding:0 14px 11px; text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
    td { padding:13px 14px; font-size:0.855rem; color:var(--text2); border-bottom:1px solid var(--border); transition:background 0.15s; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(79,142,247,0.04); }
    td strong { color:var(--text); font-weight:600; }
    .row-num { font-family:'Syne',sans-serif; font-size:0.78rem; font-weight:700; color:var(--text3); }

    /* User avatar cell */
    .user-cell { display:flex; align-items:center; gap:9px; }
    .uav { width:32px; height:32px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:800; color:#fff; flex-shrink:0; }
    .user-cell span { font-size:0.82rem; color:var(--text2); }

    /* Stars */
    .stars { color:#f59e0b; font-size:0.78rem; letter-spacing:1px; }
    .stars-dim { color:var(--text3); }

    /* Testimonial preview */
    .t-text { max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:0.82rem; color:var(--text2); }

    /* Badges */
    .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 11px; border-radius:20px; font-size:0.68rem; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; }
    .badge::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .badge-approved { background:rgba(34,197,94,0.12);  color:#22c55e; }
    .badge-pending  { background:rgba(245,158,11,0.12); color:#f59e0b; }

    /* Actions */
    .acts { display:flex; gap:5px; }
    .abt { width:30px; height:30px; border-radius:7px; display:inline-flex; align-items:center; justify-content:center; font-size:0.78rem; border:none; cursor:pointer; transition:all 0.2s; text-decoration:none; }
    .abt-view    { background:rgba(79,142,247,0.1);  color:var(--accent); }
    .abt-view:hover    { background:var(--accent); color:#fff; }
    .abt-edit    { background:rgba(245,158,11,0.1);  color:#f59e0b; }
    .abt-edit:hover    { background:#f59e0b; color:#fff; }
    .abt-approve { background:rgba(34,197,94,0.1);   color:#22c55e; }
    .abt-approve:hover { background:#22c55e; color:#fff; }
    .abt-unprov  { background:rgba(245,158,11,0.1);  color:#f59e0b; }
    .abt-unprov:hover  { background:#f59e0b; color:#fff; }
    .abt-delete  { background:rgba(239,68,68,0.1);   color:#ef4444; }
    .abt-delete:hover  { background:#ef4444; color:#fff; }

    .empty-row td { text-align:center; padding:48px; color:var(--text3); font-size:0.85rem; }
    .empty-row td i { display:block; font-size:2.2rem; margin-bottom:12px; opacity:0.2; }

    /* MODALS */
    .modal-overlay { display:none; position:fixed; inset:0; background:var(--overlay); backdrop-filter:blur(4px); z-index:500; align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--modal-bg); border:1px solid var(--border2); border-radius:16px; padding:30px; width:100%; max-width:500px; position:relative; max-height:90vh; overflow-y:auto; box-shadow:0 24px 60px rgba(0,0,0,0.4); animation:modalIn 0.28s cubic-bezier(0.34,1.56,0.64,1) forwards; }
    @keyframes modalIn { from{opacity:0;transform:scale(0.92) translateY(20px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .modal h3 { font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; color:var(--text); margin-bottom:20px; display:flex; align-items:center; gap:9px; }
    .modal h3 i { font-size:0.88rem; }
    .modal-close { position:absolute; top:14px; right:14px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; width:30px; height:30px; cursor:pointer; font-size:0.85rem; color:var(--text2); display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
    .modal-close:hover { background:var(--border2); color:var(--text); }

    /* Modal form */
    .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:15px; }
    .form-group label { font-size:0.72rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--text3); }
    .form-control { padding:10px 12px; background:var(--input-bg); border:1px solid var(--input-border); border-radius:8px; color:var(--text); font-family:'DM Sans',sans-serif; font-size:0.875rem; outline:none; transition:border-color 0.2s,box-shadow 0.2s; width:100%; }
    .form-control::placeholder { color:var(--text3); }
    .form-control:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--glow); }
    textarea.form-control { resize:vertical; min-height:100px; }
    select.form-control option { background:var(--surface2); color:var(--text); }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .btn-submit { width:100%; padding:11px; border-radius:9px; font-family:'DM Sans',sans-serif; font-size:0.88rem; font-weight:700; border:none; cursor:pointer; margin-top:4px; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:8px; }
    .btn-blue   { background:var(--accent); color:#fff; box-shadow:0 3px 12px var(--glow); }
    .btn-amber  { background:#f59e0b; color:#fff; box-shadow:0 3px 12px rgba(245,158,11,0.3); }
    .btn-submit:hover { opacity:0.88; transform:translateY(-1px); }

    /* Star rating input */
    .star-input { display:flex; gap:5px; }
    .star-input input[type="radio"] { display:none; }
    .star-input label { font-size:1.5rem; color:var(--text3); cursor:pointer; transition:color 0.15s; line-height:1; }
    .star-input label:hover,
    .star-input label.selected { color:#f59e0b; }

    /* View modal */
    .view-head { display:flex; align-items:center; gap:12px; margin-bottom:18px; }
    .view-av { width:46px; height:46px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:800; color:#fff; flex-shrink:0; }
    .view-head-info .vemail { font-weight:700; font-size:0.9rem; color:var(--text); }
    .view-quote { background:var(--surface2); border:1px solid var(--border); border-left:3px solid var(--accent); border-radius:10px; padding:14px 16px; font-size:0.875rem; color:var(--text2); line-height:1.65; margin:16px 0; font-style:italic; }
    .detail-list { border:1px solid var(--border); border-radius:10px; overflow:hidden; margin-bottom:16px; }
    .detail-row { display:flex; justify-content:space-between; align-items:center; padding:11px 14px; border-bottom:1px solid var(--border); }
    .detail-row:last-child { border-bottom:none; }
    .detail-row:hover { background:rgba(79,142,247,0.04); }
    .dl { font-size:0.72rem; color:var(--text3); font-weight:700; text-transform:uppercase; letter-spacing:0.07em; }
    .dv { font-size:0.85rem; color:var(--text); font-weight:600; }
    .modal-actions { display:grid; grid-template-columns:1fr 1fr; gap:9px; }
    .mact { flex:1; padding:10px; border-radius:9px; font-family:'DM Sans',sans-serif; font-weight:700; font-size:0.82rem; border:none; cursor:pointer; transition:all 0.2s; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center; gap:6px; }
    .mact:hover { opacity:0.85; transform:translateY(-1px); }
    .mact-approve { background:rgba(34,197,94,0.12); color:#22c55e; border:1px solid rgba(34,197,94,0.25); }
    .mact-approve:hover { background:#22c55e; color:#fff; }
    .mact-unprov  { background:rgba(245,158,11,0.12); color:#f59e0b; border:1px solid rgba(245,158,11,0.25); }
    .mact-unprov:hover  { background:#f59e0b; color:#fff; }
    .mact-delete  { background:rgba(239,68,68,0.12);  color:#ef4444; border:1px solid rgba(239,68,68,0.25); }
    .mact-delete:hover  { background:#ef4444; color:#fff; }

    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
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
        <div class="sb-section">Finance & Operations</div>
            <li><a href="payment_management.php"><i class="fa fa-credit-card"></i> Payments</a></li>
            <li><a href="car_returns.php"><i class="fa fa-rotate-left"></i> Car Returns</a></li>
            <li class="sb-divider"></li> 
    </ul>
    <div class="sb-section">Content</div>
    <ul class="sb-menu">
        <li class="active"><a href="testimonials.php"><i class="fa fa-comments"></i> Testimonials</a></li>
        <li><a href="contactus.php"><i class="fa fa-envelope"></i> Contact Queries</a></li>
        <li class="sb-divider"></li>
        <li><a href="logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<!-- MAIN -->
<div class="main">

    <div class="top-bar">
        <div class="tb-left">
            <h2>Testimonials</h2>
            <p id="dateLabel"></p>
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

        <?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- STAT CARDS -->
        <div class="stats-grid">
            <div class="sc">
                <div>
                    <div class="sc-num"><?php echo $total_count; ?></div>
                    <div class="sc-lbl">Total</div>
                </div>
                <div class="sc-icon" style="background:var(--glow);color:var(--accent);"><i class="fa fa-comments"></i></div>
            </div>
            <div class="sc">
                <div>
                    <div class="sc-num"><?php echo $approved_count; ?></div>
                    <div class="sc-lbl">Approved</div>
                </div>
                <div class="sc-icon" style="background:rgba(34,197,94,0.1);color:#22c55e;"><i class="fa fa-circle-check"></i></div>
            </div>
            <div class="sc">
                <div>
                    <div class="sc-num"><?php echo $pending_count; ?></div>
                    <div class="sc-lbl">Pending Review</div>
                </div>
                <div class="sc-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="fa fa-clock"></i></div>
            </div>
        </div>

        <!-- TABLE CARD -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-list"></i> All Testimonials</h3>
                <div class="card-head-right">
                    <div class="filter-tabs">
                        <a href="testimonials.php?filter=all"      class="tab <?php echo $filter==='all'      ?'active':''; ?>">All (<?php echo $total_count; ?>)</a>
                        <a href="testimonials.php?filter=approved" class="tab <?php echo $filter==='approved' ?'active':''; ?>">Approved (<?php echo $approved_count; ?>)</a>
                        <a href="testimonials.php?filter=pending"  class="tab <?php echo $filter==='pending'  ?'active':''; ?>">Pending (<?php echo $pending_count; ?>)</a>
                    </div>
                    <button class="btn-add" onclick="openModal('addModal')">
                        <i class="fa fa-plus"></i> Add
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>User</th><th>Testimonial</th><th>Rating</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($res && $res->num_rows > 0):
                        $cnt = 1;
                        while ($row = $res->fetch_assoc()):
                            $approved = ($row['status'] == 1);
                            $rating   = intval($row['rating'] ?? 5);
                    ?>
                    <tr>
                        <td><span class="row-num"><?php echo $cnt++; ?></span></td>
                        <td>
                            <div class="user-cell">
                                <div class="uav"><?php echo strtoupper(substr($row['user_email'],0,1)); ?></div>
                                <span><?php echo htmlspecialchars($row['user_email']); ?></span>
                            </div>
                        </td>
                        <td><div class="t-text"><?php echo htmlspecialchars($row['testimonial']); ?></div></td>
                        <td>
                            <div class="stars">
                                <?php for($i=1;$i<=5;$i++) echo '<i class="fa fa-star'.($i<=$rating?'':' stars-dim').'"></i>'; ?>
                            </div>
                        </td>
                        <td style="font-size:0.78rem;color:var(--text3);white-space:nowrap;"><?php echo date('d M Y', strtotime($row['posting_date'])); ?></td>
                        <td><span class="badge <?php echo $approved?'badge-approved':'badge-pending'; ?>"><?php echo $approved?'Approved':'Pending'; ?></span></td>
                        <td>
                            <div class="acts">
                                <button onclick='openViewModal(<?php echo json_encode($row); ?>)' class="abt abt-view" title="View"><i class="fa fa-eye"></i></button>
                                <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="abt abt-edit" title="Edit"><i class="fa fa-pen"></i></button>
                                <?php if (!$approved): ?>
                                    <a href="testimonials.php?approve=<?php echo $row['id']; ?>&filter=<?php echo $filter; ?>" class="abt abt-approve" title="Approve" onclick="return confirm('Approve?')"><i class="fa fa-check"></i></a>
                                <?php else: ?>
                                    <a href="testimonials.php?unapprove=<?php echo $row['id']; ?>&filter=<?php echo $filter; ?>" class="abt abt-unprov" title="Unapprove" onclick="return confirm('Unapprove?')"><i class="fa fa-ban"></i></a>
                                <?php endif; ?>
                                <a href="testimonials.php?del=<?php echo $row['id']; ?>&filter=<?php echo $filter; ?>" class="abt abt-delete" title="Delete" onclick="return confirm('Delete permanently?')"><i class="fa fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr class="empty-row"><td colspan="7"><i class="fa fa-comments"></i>No testimonials found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- MODAL: ADD -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('addModal')"><i class="fa fa-xmark"></i></button>
        <h3><i class="fa fa-plus-circle" style="color:var(--accent);"></i> Add Testimonial</h3>
        <form method="POST">
            <div class="form-group">
                <label>User Email <span style="color:#ef4444;">*</span></label>
                <input type="email" name="new_email" class="form-control" placeholder="user@example.com" required>
            </div>
            <div class="form-group">
                <label>Testimonial <span style="color:#ef4444;">*</span></label>
                <textarea name="new_testimonial" class="form-control" placeholder="Write testimonial here..." required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Rating</label>
                    <div class="star-input" id="addStars">
                        <?php for($i=1;$i<=5;$i++): ?>
                        <input type="radio" name="new_rating" id="add_star<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo $i==5?'checked':''; ?>>
                        <label for="add_star<?php echo $i; ?>" data-val="<?php echo $i; ?>">&#9733;</label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="new_status" class="form-control">
                        <option value="0">Pending</option>
                        <option value="1">Approved</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_testimonial" class="btn-submit btn-blue"><i class="fa fa-floppy-disk"></i> Save Testimonial</button>
        </form>
    </div>
</div>

<!-- MODAL: EDIT -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('editModal')"><i class="fa fa-xmark"></i></button>
        <h3><i class="fa fa-pen-to-square" style="color:#f59e0b;"></i> Edit Testimonial</h3>
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group">
                <label>User Email <span style="color:#ef4444;">*</span></label>
                <input type="email" name="edit_email" id="edit_email" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Testimonial <span style="color:#ef4444;">*</span></label>
                <textarea name="edit_testimonial" id="edit_testimonial" class="form-control" required></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Rating</label>
                    <div class="star-input" id="editStars">
                        <?php for($i=1;$i<=5;$i++): ?>
                        <input type="radio" name="edit_rating" id="edit_star<?php echo $i; ?>" value="<?php echo $i; ?>">
                        <label for="edit_star<?php echo $i; ?>" data-val="<?php echo $i; ?>">&#9733;</label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="edit_status" id="edit_status" class="form-control">
                        <option value="0">Pending</option>
                        <option value="1">Approved</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="update_testimonial" class="btn-submit btn-amber"><i class="fa fa-floppy-disk"></i> Update Testimonial</button>
        </form>
    </div>
</div>

<!-- MODAL: VIEW -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('viewModal')"><i class="fa fa-xmark"></i></button>
        <h3><i class="fa fa-comment-dots" style="color:var(--accent);"></i> Testimonial Detail</h3>
        <div class="view-head">
            <div class="view-av" id="vInitial"></div>
            <div class="view-head-info">
                <div class="vemail" id="vEmail"></div>
                <div class="stars" id="vStars" style="margin-top:4px;"></div>
            </div>
        </div>
        <div class="view-quote" id="vText"></div>
        <div class="detail-list">
            <div class="detail-row"><span class="dl">Status</span><span class="dv" id="vStatus"></span></div>
            <div class="detail-row"><span class="dl">Submitted</span><span class="dv" id="vDate"></span></div>
        </div>
        <div class="modal-actions" id="vActions"></div>
    </div>
</div>

<script>
    // Live date
    (function(){
        var d=new Date(),days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear();
    })();

    // Theme
    var theme=localStorage.getItem('adminTheme')||'dark';
    document.documentElement.setAttribute('data-theme',theme); syncIcon();
    document.getElementById('themeBtn').addEventListener('click',function(){
        theme=theme==='dark'?'light':'dark';
        document.documentElement.setAttribute('data-theme',theme);
        localStorage.setItem('adminTheme',theme); syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className=theme==='dark'?'fa fa-moon':'fa fa-sun'; }

    // Modals
    function openModal(id){ document.getElementById(id).classList.add('open'); }
    function closeModal(id){ document.getElementById(id).classList.remove('open'); }
    document.querySelectorAll('.modal-overlay').forEach(function(o){
        o.addEventListener('click',function(e){ if(e.target===this) this.classList.remove('open'); });
    });

    // Star rating UI
    function initStars(cid){
        var c=document.getElementById(cid);
        var labels=c.querySelectorAll('label'), inputs=c.querySelectorAll('input[type="radio"]');
        function hl(val){ labels.forEach(function(l){ l.classList.toggle('selected',parseInt(l.dataset.val)<=val); }); }
        inputs.forEach(function(inp){ if(inp.checked) hl(parseInt(inp.value)); inp.addEventListener('change',function(){ hl(parseInt(inp.value)); }); });
        labels.forEach(function(lbl){
            lbl.addEventListener('mouseover',function(){ hl(parseInt(lbl.dataset.val)); });
            lbl.addEventListener('mouseout',function(){ var ch=c.querySelector('input:checked'); hl(ch?parseInt(ch.value):0); });
        });
    }
    initStars('addStars'); initStars('editStars');

    // Edit modal
    function openEditModal(data){
        document.getElementById('edit_id').value          = data.id;
        document.getElementById('edit_email').value       = data.user_email  || '';
        document.getElementById('edit_testimonial').value = data.testimonial || '';
        document.getElementById('edit_status').value      = data.status;
        var r=parseInt(data.rating)||5;
        var radio=document.getElementById('edit_star'+r);
        if(radio){ radio.checked=true; radio.dispatchEvent(new Event('change')); }
        openModal('editModal');
    }

    // View modal
    var currentFilter='<?php echo $filter; ?>';
    function openViewModal(data){
        var approved=data.status==1, rating=parseInt(data.rating)||5;
        document.getElementById('vInitial').textContent = data.user_email.charAt(0).toUpperCase();
        document.getElementById('vEmail').textContent   = data.user_email;
        document.getElementById('vText').textContent    = data.testimonial;
        document.getElementById('vDate').textContent    = data.posting_date||'—';
        var stars='';
        for(var i=1;i<=5;i++) stars+='<i class="fa fa-star'+(i<=rating?'':' stars-dim')+'"></i>';
        document.getElementById('vStars').innerHTML=stars;
        document.getElementById('vStatus').innerHTML=approved
            ?'<span class="badge badge-approved">Approved</span>'
            :'<span class="badge badge-pending">Pending</span>';
        var acts='';
        if(!approved){
            acts+='<a href="testimonials.php?approve='+data.id+'&filter='+currentFilter+'" class="mact mact-approve" onclick="return confirm(\'Approve?\')"><i class="fa fa-check"></i> Approve</a>';
        } else {
            acts+='<a href="testimonials.php?unapprove='+data.id+'&filter='+currentFilter+'" class="mact mact-unprov" onclick="return confirm(\'Unapprove?\')"><i class="fa fa-ban"></i> Unapprove</a>';
        }
        acts+='<a href="testimonials.php?del='+data.id+'&filter='+currentFilter+'" class="mact mact-delete" onclick="return confirm(\'Delete permanently?\')"><i class="fa fa-trash"></i> Delete</a>';
        document.getElementById('vActions').innerHTML=acts;
        openModal('viewModal');
    }

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.alert').forEach(function(el){
            setTimeout(function(){ el.style.transition='opacity 0.5s ease'; el.style.opacity='0'; setTimeout(function(){ el.style.display='none'; },500); },2500);
        });
    });
</script>
</body>
</html>