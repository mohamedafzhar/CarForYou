<?php
session_start();
include 'config.php';

if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
    header('Location: index.php');
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

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

$user_count = $conn->query("SELECT id FROM users")->num_rows;
$cars_count = $conn->query("SELECT id FROM cars")->num_rows;
$booking_count = $conn->query("SELECT id FROM booking")->num_rows;
$testimonial_count = $conn->query("SELECT id FROM testimonials")->num_rows;
$query_count = $conn->query("SELECT id FROM contact_us")->num_rows;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sw: 268px;
            --tr: 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg: #0d1117;
            --bg2: #131920;
            --surface: #1e2738;
            --surface2: #253044;
            --border: rgba(99, 155, 255, 0.08);
            --border2: rgba(99, 155, 255, 0.16);
            --text: #e8edf5;
            --text2: #7a93b0;
            --text3: #3d5570;
            --accent: #4f8ef7;
            --accent2: #7db0fb;
            --glow: rgba(79, 142, 247, 0.22);
            --sbg: #0a1020;
            --sborder: rgba(79, 142, 247, 0.1);
            --cshadow: 0 4px 24px rgba(0, 0, 0, 0.35);
            --hbg: rgba(13, 17, 23, 0.9);
        }

        [data-theme="light"] {
            --bg: #f0f4f8;
            --bg2: #e8edf3;
            --surface: #ffffff;
            --surface2: #f5f7fa;
            --border: rgba(99, 120, 155, 0.12);
            --border2: rgba(99, 120, 155, 0.22);
            --text: #1c2b3a;
            --text2: #4a607a;
            --text3: #8fa3bb;
            --accent: #2563eb;
            --accent2: #3b82f6;
            --glow: rgba(37, 99, 235, 0.16);
            --sbg: #1c2b3a;
            --sborder: rgba(255, 255, 255, 0.06);
            --cshadow: 0 4px 20px rgba(28, 43, 58, 0.08);
            --hbg: rgba(240, 244, 248, 0.92);
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            transition: background var(--tr), color var(--tr);
        }

        ::-webkit-scrollbar {
            width: 4px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sw);
            min-height: 100vh;
            background: var(--sbg);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--sborder);
            z-index: 100;
            overflow-y: auto;
            transition: background var(--tr);
        }

        .sb-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid var(--sborder);
        }

        .sb-brand h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: #e8edf5;
            letter-spacing: 0.01em;
        }

        .sb-brand h2 span {
            color: var(--accent);
        }

        .sb-brand p {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(232, 237, 245, 0.3);
            margin-top: 4px;
        }

        .sb-section {
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(232, 237, 245, 0.25);
            padding: 22px 24px 6px;
        }

        .sb-menu {
            list-style: none;
            padding: 6px 12px;
        }

        .sb-menu li {
            margin-bottom: 2px;
        }

        .sb-menu li a {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: 9px;
            font-size: 0.86rem;
            font-weight: 500;
            color: rgba(232, 237, 245, 0.5);
            transition: all 0.2s;
        }

        .sb-menu li a i {
            width: 18px;
            text-align: center;
            font-size: 0.85rem;
        }

        .sb-menu li:hover a {
            background: rgba(79, 142, 247, 0.09);
            color: rgba(232, 237, 245, 0.88);
        }

        .sb-menu li.active a {
            background: linear-gradient(90deg, rgba(79, 142, 247, 0.2), rgba(79, 142, 247, 0.05));
            color: var(--accent);
            font-weight: 600;
            box-shadow: inset 3px 0 0 var(--accent);
        }

        .sb-menu li.active a i {
            color: var(--accent);
        }

        .sb-divider {
            height: 1px;
            background: var(--sborder);
            margin: 10px 0;
        }

        /* ── MAIN ── */
        .main {
            margin-left: var(--sw);
            width: calc(100% - var(--sw));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── HEADER ── */
        .top-bar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--hbg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 0 36px;
            height: 66px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background var(--tr);
        }

        .tb-left h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.01em;
        }

        .tb-left p {
            font-size: 0.73rem;
            color: var(--text2);
            margin-top: 1px;
        }

        .tb-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .theme-btn {
            width: 37px;
            height: 37px;
            border-radius: 9px;
            border: 1px solid var(--border2);
            background: var(--surface);
            color: var(--text2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            transition: all 0.2s;
        }

        .theme-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: 0 0 10px var(--glow);
        }

        .admin-pill {
            display: flex;
            align-items: center;
            gap: 9px;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 9px;
            padding: 6px 13px;
        }

        .av {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 800;
            color: #fff;
        }

        .admin-pill .name {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text);
        }

        .admin-pill .role {
            font-size: 0.68rem;
            color: var(--text2);
        }

        /* ── PAGE BODY ── */
        .body {
            padding: 26px 36px;
            flex: 1;
        }

        /* ── STAT CARDS ── */
        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .sc {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
        }

        .sc::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.35s ease;
        }

        .sc:hover {
            transform: translateY(-4px);
            box-shadow: var(--cshadow), 0 0 0 1px var(--border2);
        }

        .sc:hover::before {
            transform: scaleX(1);
        }

        .sc-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .sc-num {
            font-family: 'Syne', sans-serif;
            font-size: 1.95rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .sc-lbl {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--text3);
            margin-top: 3px;
        }

        .sc-icon {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            background: var(--glow);
            color: var(--accent);
            transition: all 0.25s;
        }

        .sc:hover .sc-icon {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 4px 14px var(--glow);
        }

        /* card animation stagger */
        .sc:nth-child(1) {
            animation-delay: 0.05s
        }

        .sc:nth-child(2) {
            animation-delay: 0.10s
        }

        .sc:nth-child(3) {
            animation-delay: 0.15s
        }

        .sc:nth-child(4) {
            animation-delay: 0.20s
        }

        .sc:nth-child(5) {
            animation-delay: 0.25s
        }

        /* ── SECTION CARDS ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 22px;
            margin-bottom: 18px;
            transition: background var(--tr), border-color var(--tr);
            opacity: 0;
            animation: fadeUp 0.55s ease forwards;
        }

        .card:nth-of-type(1) {
            animation-delay: 0.3s
        }

        .card:nth-of-type(2) {
            animation-delay: 0.4s
        }

        .card:nth-of-type(3) {
            animation-delay: 0.5s
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .card-head h3 {
            font-family: 'Syne', sans-serif;
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-head h3 i {
            color: var(--accent);
            font-size: 0.82rem;
        }

        .view-all {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.2s;
        }

        .view-all:hover {
            gap: 9px;
        }

        /* ── TABLE ── */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.13em;
            text-transform: uppercase;
            color: var(--text3);
            padding: 0 13px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 13px;
            font-size: 0.855rem;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(79, 142, 247, 0.04);
            color: var(--text);
        }

        td strong {
            color: var(--text);
            font-weight: 600;
        }

        /* badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.67rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        .badge.confirmed {
            background: rgba(34, 197, 94, 0.12);
            color: #22c55e;
        }

        .badge.pending {
            background: rgba(245, 158, 11, 0.12);
            color: #f59e0b;
        }

        .badge.approved {
            background: rgba(79, 142, 247, 0.12);
            color: var(--accent);
        }

        /* action btns */
        .abt {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 7px;
            font-size: 0.74rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .abt-ok {
            color: #22c55e;
            border-color: rgba(34, 197, 94, 0.3);
            background: rgba(34, 197, 94, 0.07);
        }

        .abt-ok:hover {
            background: #22c55e;
            color: #fff;
        }

        .abt-un {
            color: #f59e0b;
            border-color: rgba(245, 158, 11, 0.3);
            background: rgba(245, 158, 11, 0.07);
        }

        .abt-un:hover {
            background: #f59e0b;
            color: #fff;
        }

        .empty td {
            text-align: center;
            padding: 36px;
            color: var(--text3);
            font-size: 0.84rem;
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            <li class="active"><a href="admin_dashboard.php"><i class="fa fa-table-columns"></i> Dashboard</a></li>
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
                <h2>Dashboard Overview</h2>
                <p id="dateLabel"></p>
            </div>
            <div class="tb-right">
                <button class="theme-btn" id="themeBtn" title="Toggle Theme">
                    <i class="fa fa-moon" id="themeIcon"></i>
                </button>
                <div class="admin-pill">
                    <div class="av"><?php echo strtoupper(substr($_SESSION['alogin'] ?? 'A', 0, 1)); ?></div>
                    <div>
                        <div class="name"><?php echo htmlspecialchars($_SESSION['alogin'] ?? 'Admin'); ?></div>
                        <div class="role">Administrator</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="body">

            <!-- Stat Cards -->
            <div class="stats">
                <a href="reg-users.php" style="text-decoration:none;">
                    <div class="sc">
                        <div class="sc-row">
                            <div>
                                <div class="sc-num"><?php echo $user_count; ?></div>
                                <div class="sc-lbl">Users</div>
                            </div>
                            <div class="sc-icon"><i class="fa fa-users"></i></div>
                        </div>
                    </div>
                </a>
                <a href="car.php" style="text-decoration:none;">
                    <div class="sc">
                        <div class="sc-row">
                            <div>
                                <div class="sc-num"><?php echo $cars_count; ?></div>
                                <div class="sc-lbl">Cars</div>
                            </div>
                            <div class="sc-icon"><i class="fa fa-car"></i></div>
                        </div>
                    </div>
                </a>
                <a href="bookings.php" style="text-decoration:none;">
                    <div class="sc">
                        <div class="sc-row">
                            <div>
                                <div class="sc-num"><?php echo $booking_count; ?></div>
                                <div class="sc-lbl">Bookings</div>
                            </div>
                            <div class="sc-icon"><i class="fa fa-calendar-check"></i></div>
                        </div>
                    </div>
                </a>
                <a href="testimonials.php" style="text-decoration:none;">
                    <div class="sc">
                        <div class="sc-row">
                            <div>
                                <div class="sc-num"><?php echo $testimonial_count; ?></div>
                                <div class="sc-lbl">Testimonials</div>
                            </div>
                            <div class="sc-icon"><i class="fa fa-star"></i></div>
                        </div>
                    </div>
                </a>
                <a href="contactus.php" style="text-decoration:none;">
                    <div class="sc">
                        <div class="sc-row">
                            <div>
                                <div class="sc-num"><?php echo $query_count; ?></div>
                                <div class="sc-lbl">Queries</div>
                            </div>
                            <div class="sc-icon"><i class="fa fa-envelope"></i></div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Recent Bookings -->
            <div class="card">
                <div class="card-head">
                    <h3><i class="fa fa-calendar-check"></i> Recent Bookings</h3>
                    <a href="bookings.php" class="view-all">View All <i class="fa fa-arrow-right"></i></a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Car</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $b = $conn->query("SELECT * FROM booking ORDER BY id DESC LIMIT 5");
                        if ($b && $b->num_rows > 0):
                            while ($r = $b->fetch_assoc()):
                                $c = ($r['status'] == 1) ? 'confirmed' : 'pending';
                                $l = ($r['status'] == 1) ? 'Confirmed' : 'Pending';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['user_email']); ?></td>
                                    <td><strong>#<?php echo $r['car_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['from_date']); ?></td>
                                    <td><?php echo htmlspecialchars($r['to_date']); ?></td>
                                    <td><span class="badge <?php echo $c; ?>"><?php echo $l; ?></span></td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr class="empty">
                                <td colspan="5">No bookings found.</td>
                            </tr><?php endif; ?>
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
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Date</th>
                        </tr>
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
                            <tr class="empty">
                                <td colspan="4">No queries found.</td>
                            </tr><?php endif; ?>
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
                        <tr>
                            <th>Email</th>
                            <th>Preview</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $t = $conn->query("SELECT * FROM testimonials ORDER BY status ASC, posting_date DESC LIMIT 5");
                        if ($t && $t->num_rows > 0):
                            while ($tr = $t->fetch_assoc()):
                                $tc = ($tr['status'] == 1) ? 'approved' : 'pending';
                                $tl = ($tr['status'] == 1) ? 'Approved' : 'Pending';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tr['user_email']); ?></td>
                                    <td><?php echo substr(htmlspecialchars($tr['testimonial']), 0, 55); ?>…</td>
                                    <td><span class="badge <?php echo $tc; ?>"><?php echo $tl; ?></span></td>
                                    <td>
                                        <?php if ($tr['status'] == 0): ?>
                                            <a href="admin_dashboard.php?eid=<?php echo $tr['id']; ?>" class="abt abt-ok"
                                                onclick="return confirm('Approve?')"><i class="fa fa-check"></i> Approve</a>
                                        <?php else: ?>
                                            <a href="admin_dashboard.php?aeid=<?php echo $tr['id']; ?>" class="abt abt-un"
                                                onclick="return confirm('Unapprove?')"><i class="fa fa-ban"></i> Unapprove</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                            <tr class="empty">
                                <td colspan="4">No testimonials found.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        history.pushState(null, null, location.href);
        window.addEventListener('popstate', function () { window.location.replace('index.php'); });

        // Live date
        (function () {
            var d = new Date(), days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            document.getElementById('dateLabel').textContent = days[d.getDay()] + ', ' + d.getDate() + ' ' + mo[d.getMonth()] + ' ' + d.getFullYear();
        })();

        // Theme
        var theme = localStorage.getItem('adminTheme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        syncIcon();

        document.getElementById('themeBtn').addEventListener('click', function () {
            theme = theme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('adminTheme', theme);
            syncIcon();
        });

        function syncIcon() {
            document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
        }
    </script>
</body>

</html>