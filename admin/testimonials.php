<?php
session_start();
include 'config.php';

if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
    header('Location: index.php'); exit();
}

$msg = $error = "";

// ── APPROVE ───────────────────────────────────────────────────────────────────
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $s  = $conn->prepare("UPDATE testimonials SET status=1 WHERE id=?");
    $s->bind_param("i", $id);
    $msg = $s->execute() ? "Testimonial approved and published." : "";
    if (!$msg) $error = "Error updating.";
}

// ── REJECT ────────────────────────────────────────────────────────────────────
if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $s  = $conn->prepare("UPDATE testimonials SET status=2 WHERE id=?");
    $s->bind_param("i", $id);
    $msg = $s->execute() ? "Testimonial rejected." : "";
    if (!$msg) $error = "Error updating.";
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $s  = $conn->prepare("DELETE FROM testimonials WHERE id=?");
    $s->bind_param("i", $id);
    $msg = $s->execute() ? "Testimonial deleted." : "";
    if (!$msg) $error = "Error deleting.";
}

// ── FETCH ALL ─────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$where  = $filter === 'pending'  ? "WHERE t.status = 0" :
          ($filter === 'approved' ? "WHERE t.status = 1" :
          ($filter === 'rejected' ? "WHERE t.status = 2" : ""));

$result = $conn->query("
    SELECT t.*, b.from_date, b.to_date
    FROM testimonials t
    LEFT JOIN booking b ON b.id = t.booking_id
    $where
    ORDER BY t.created_at DESC
");
$total   = $result ? $result->num_rows : 0;
$counts  = [];
foreach (['all'=>'','pending'=>'WHERE status=0','approved'=>'WHERE status=1','rejected'=>'WHERE status=2'] as $k=>$w) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM testimonials $w");
    $counts[$k] = $r ? $r->fetch_assoc()['c'] : 0;
}
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
    :root{--sw:268px;--tr:0.35s cubic-bezier(0.4,0,0.2,1);}
    [data-theme="dark"]{--bg:#0d1117;--surface:#1e2738;--surface2:#253044;--border:rgba(99,155,255,0.08);--border2:rgba(99,155,255,0.16);--text:#e8edf5;--text2:#7a93b0;--text3:#3d5570;--accent:#4f8ef7;--accent2:#7db0fb;--glow:rgba(79,142,247,0.22);--sbg:#0a1020;--sborder:rgba(79,142,247,0.1);--hbg:rgba(13,17,23,0.9);}
    [data-theme="light"]{--bg:#f0f4f8;--surface:#ffffff;--surface2:#f5f7fa;--border:rgba(99,120,155,0.12);--border2:rgba(99,120,155,0.22);--text:#1c2b3a;--text2:#4a607a;--text3:#8fa3bb;--accent:#2563eb;--accent2:#3b82f6;--glow:rgba(37,99,235,0.16);--sbg:#1c2b3a;--sborder:rgba(255,255,255,0.06);--hbg:rgba(240,244,248,0.92);}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background var(--tr),color var(--tr);}
    ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:var(--bg);} ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px;}
    a{text-decoration:none;color:inherit;}
    .sidebar{width:var(--sw);min-height:100vh;background:var(--sbg);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;border-right:1px solid var(--sborder);z-index:100;overflow-y:auto;}
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
    .sb-divider{height:1px;background:var(--sborder);margin:10px 0;}
    .main{margin-left:var(--sw);width:calc(100% - var(--sw));min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:50;background:var(--hbg);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 36px;height:66px;display:flex;align-items:center;justify-content:space-between;}
    .tb-left h2{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:var(--text);}
    .tb-left p{font-size:0.73rem;color:var(--text2);margin-top:1px;}
    .tb-right{display:flex;align-items:center;gap:10px;}
    .theme-btn{width:37px;height:37px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.88rem;transition:all 0.2s;}
    .theme-btn:hover{border-color:var(--accent);color:var(--accent);}
    .admin-pill{display:flex;align-items:center;gap:9px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:6px 13px;}
    .av{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:800;color:#fff;}
    .admin-pill .aname{font-size:0.82rem;font-weight:600;color:var(--text);}
    .admin-pill .arole{font-size:0.68rem;color:var(--text2);}
    .body{padding:26px 36px;flex:1;}
    .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:10px;font-size:0.86rem;font-weight:500;margin-bottom:20px;opacity:0;animation:fadeUp 0.4s ease forwards;}
    .alert-success{background:rgba(34,197,94,0.1);color:#22c55e;border:1px solid rgba(34,197,94,0.2);}
    .alert-error{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2);}

    /* FILTER TABS */
    .filter-bar{display:flex;gap:8px;margin-bottom:22px;flex-wrap:wrap;opacity:0;animation:fadeUp 0.4s ease 0.05s forwards;}
    .ftab{padding:7px 16px;border-radius:20px;font-size:0.74rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;border:1px solid var(--border2);background:transparent;color:var(--text3);cursor:pointer;transition:all 0.2s;font-family:'DM Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;text-decoration:none;}
    .ftab:hover{border-color:var(--accent);color:var(--accent);}
    .ftab.active{background:var(--accent);color:#fff;border-color:var(--accent);box-shadow:0 3px 10px var(--glow);}
    .ftab .n{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:9px;padding:0 4px;font-size:0.6rem;font-weight:800;background:rgba(255,255,255,0.2);}

    /* TESTIMONIAL CARDS */
    .cards-grid{display:flex;flex-direction:column;gap:14px;}
    .t-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 24px;opacity:0;animation:fadeUp 0.5s ease forwards;transition:box-shadow 0.22s,border-color 0.22s;position:relative;overflow:hidden;}
    .t-card:hover{border-color:var(--border2);box-shadow:0 4px 20px rgba(0,0,0,0.2);}
    .t-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px;}
    .t-pending::before{background:#f59e0b;}
    .t-approved::before{background:#22c55e;}
    .t-rejected::before{background:#ef4444;}

    .t-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px;}
    .t-meta{}
    .t-name{font-size:0.95rem;font-weight:700;color:var(--text);}
    .t-car{font-size:0.78rem;color:var(--text3);margin-top:3px;display:flex;align-items:center;gap:5px;}
    .t-car i{color:var(--accent);font-size:0.7rem;}
    .t-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}

    /* Stars display */
    .stars-display{display:flex;gap:2px;}
    .stars-display i{font-size:0.88rem;}
    .star-filled{color:#f59e0b;}
    .star-empty{color:var(--border2);}

    /* Status badge */
    .sbadge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.66rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;}
    .sbadge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
    .sbadge-pending{background:rgba(245,158,11,0.12);color:#f59e0b;}
    .sbadge-approved{background:rgba(34,197,94,0.12);color:#22c55e;}
    .sbadge-rejected{background:rgba(239,68,68,0.12);color:#ef4444;}

    .t-review{font-size:0.88rem;color:var(--text2);line-height:1.65;margin-bottom:16px;padding:14px 16px;background:var(--surface2);border-radius:10px;border-left:3px solid var(--border2);}
    .t-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
    .t-date{font-size:0.72rem;color:var(--text3);}
    .t-actions{display:flex;gap:8px;}
    .abt{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;font-size:0.74rem;font-weight:600;border:1px solid transparent;cursor:pointer;transition:all 0.2s;text-decoration:none;white-space:nowrap;}
    .abt-approve{color:#22c55e;border-color:rgba(34,197,94,0.3);background:rgba(34,197,94,0.07);}
    .abt-approve:hover{background:#22c55e;color:#fff;}
    .abt-reject{color:#f59e0b;border-color:rgba(245,158,11,0.3);background:rgba(245,158,11,0.07);}
    .abt-reject:hover{background:#f59e0b;color:#fff;}
    .abt-delete{color:#ef4444;border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.07);}
    .abt-delete:hover{background:#ef4444;color:#fff;}

    .empty-state{text-align:center;padding:60px 20px;color:var(--text3);}
    .empty-state i{font-size:2.5rem;display:block;margin-bottom:14px;opacity:0.2;}

    @keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
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
            <button class="theme-btn" id="themeBtn"><i class="fa fa-moon" id="themeIcon"></i></button>
            <div class="admin-pill">
                <div class="av"><?php echo strtoupper(substr($_SESSION['alogin']??'A',0,1)); ?></div>
                <div>
                    <div class="aname"><?php echo htmlspecialchars($_SESSION['alogin']??'Admin'); ?></div>
                    <div class="arole">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <div class="body">
        <?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- FILTER TABS -->
        <div class="filter-bar">
            <a href="testimonials.php?filter=all"      class="ftab <?php echo $filter==='all'?'active':''; ?>">All <span class="n"><?php echo $counts['all']; ?></span></a>
            <a href="testimonials.php?filter=pending"  class="ftab <?php echo $filter==='pending'?'active':''; ?>">Pending <span class="n"><?php echo $counts['pending']; ?></span></a>
            <a href="testimonials.php?filter=approved" class="ftab <?php echo $filter==='approved'?'active':''; ?>">Approved <span class="n"><?php echo $counts['approved']; ?></span></a>
            <a href="testimonials.php?filter=rejected" class="ftab <?php echo $filter==='rejected'?'active':''; ?>">Rejected <span class="n"><?php echo $counts['rejected']; ?></span></a>
        </div>

        <div class="cards-grid">
        <?php if ($result && $result->num_rows > 0):
            $delay = 0;
            while ($t = $result->fetch_assoc()):
                $s = intval($t['status']);
                if ($s===1){$sc='approved';$sl='Approved';$bc='sbadge-approved';}
                elseif($s===2){$sc='rejected';$sl='Rejected';$bc='sbadge-rejected';}
                else{$sc='pending';$sl='Pending';$bc='sbadge-pending';}
        ?>
        <div class="t-card t-<?php echo $sc; ?>" style="animation-delay:<?php echo $delay; ?>s;">
            <div class="t-top">
                <div class="t-meta">
                    <div class="t-name"><?php echo htmlspecialchars($t['user_name']); ?></div>
                    <div class="t-car"><i class="fa fa-car"></i><?php echo htmlspecialchars($t['car_name']); ?>
                        <?php if ($t['from_date']): ?>
                        &nbsp;·&nbsp; <?php echo date('d M Y', strtotime($t['from_date'])); ?> → <?php echo date('d M Y', strtotime($t['to_date'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="t-right">
                    <div class="stars-display">
                        <?php for ($i=1;$i<=5;$i++): ?>
                        <i class="fa fa-star <?php echo $i<=$t['rating']?'star-filled':'star-empty'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="sbadge <?php echo $bc; ?>"><?php echo $sl; ?></span>
                </div>
            </div>

            <div class="t-review">"<?php echo htmlspecialchars($t['review']); ?>"</div>

            <div class="t-footer">
                <div class="t-date"><i class="fa fa-clock" style="margin-right:5px;"></i><?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></div>
                <div class="t-actions">
                    <?php if ($s !== 1): ?>
                    <a href="testimonials.php?approve=<?php echo $t['id']; ?>&filter=<?php echo $filter; ?>" class="abt abt-approve"
                       onclick="return confirm('Approve and publish this review?')">
                        <i class="fa fa-check"></i> Approve
                    </a>
                    <?php endif; ?>
                    <?php if ($s !== 2): ?>
                    <a href="testimonials.php?reject=<?php echo $t['id']; ?>&filter=<?php echo $filter; ?>" class="abt abt-reject">
                        <i class="fa fa-xmark"></i> Reject
                    </a>
                    <?php endif; ?>
                    <a href="testimonials.php?delete=<?php echo $t['id']; ?>&filter=<?php echo $filter; ?>" class="abt abt-delete"
                       onclick="return confirm('Permanently delete this review?')">
                        <i class="fa fa-trash"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php $delay += 0.06; endwhile;
        else: ?>
        <div class="empty-state">
            <i class="fa fa-comments"></i>
            <p>No <?php echo $filter !== 'all' ? $filter : ''; ?> testimonials found.</p>
        </div>
        <?php endif; ?>
        </div>

    </div>
</div>

<script>
    (function(){var d=new Date(),D=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];document.getElementById('dateLabel').textContent=D[d.getDay()]+', '+d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear();})();
    var theme=localStorage.getItem('adminTheme')||'dark';
    document.documentElement.setAttribute('data-theme',theme);syncIcon();
    document.getElementById('themeBtn').addEventListener('click',function(){theme=theme==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',theme);localStorage.setItem('adminTheme',theme);syncIcon();});
    function syncIcon(){document.getElementById('themeIcon').className=theme==='dark'?'fa fa-moon':'fa fa-sun';}
    document.querySelectorAll('.alert').forEach(function(el){setTimeout(function(){el.style.transition='opacity 0.5s';el.style.opacity='0';setTimeout(function(){el.style.display='none';},500);},3000);});
</script>
</body>
</html>