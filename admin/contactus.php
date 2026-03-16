<?php
session_start();
include 'config.php';

if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
    header('Location: index.php');
    exit();
}

$msg = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    $sql = "INSERT INTO contact_us (first_name, last_name, email, subject, message) VALUES ('$first_name','$last_name','$email','$subject','$message')";
    if (mysqli_query($conn, $sql)) {
        $msg = "Message sent successfully.";
    } else {
        $error = "Error sending message. Please try again.";
    }
}

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $stmt = $conn->prepare("DELETE FROM contact_us WHERE id=?");
    $stmt->bind_param("i", $id);
    $msg = $stmt->execute() ? "Contact message deleted successfully." : "";
    $error = (!$stmt->execute()) ? "Error: Could not delete the message." : "";
}

$res = $conn->query("SELECT * FROM contact_us ORDER BY created_at DESC");
$total = $res ? $res->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Queries | CarForYou Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap"
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

        /* ── TOPBAR ── */
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

        .admin-pill .aname {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text);
        }

        .admin-pill .arole {
            font-size: 0.68rem;
            color: var(--text2);
        }

        /* ── BODY ── */
        .body {
            padding: 26px 36px;
            flex: 1;
        }

        /* ── ALERTS ── */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 0.86rem;
            font-weight: 500;
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeUp 0.4s ease forwards;
        }

        .alert i {
            font-size: 0.95rem;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* ── CARD ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 24px;
            transition: background var(--tr), border-color var(--tr);
            opacity: 0;
            animation: fadeUp 0.5s ease 0.08s forwards;
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border);
            gap: 16px;
            flex-wrap: wrap;
        }

        .card-head-left h3 {
            font-family: 'Syne', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .card-head-left h3 i {
            color: var(--accent);
            font-size: 0.85rem;
        }

        .card-head-left p {
            font-size: 0.77rem;
            color: var(--text3);
            margin-top: 5px;
            padding-left: 26px;
        }

        .count-pill {
            font-size: 0.72rem;
            font-weight: 700;
            background: var(--glow);
            color: var(--accent);
            padding: 3px 10px;
            border-radius: 20px;
            white-space: nowrap;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ── TABLE ── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 820px;
        }

        th {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.13em;
            text-transform: uppercase;
            color: var(--text3);
            padding: 0 14px 11px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 13px 14px;
            font-size: 0.855rem;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(79, 142, 247, 0.04);
        }

        td strong {
            color: var(--text);
            font-weight: 600;
        }

        .row-num {
            font-family: 'Syne', sans-serif;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text3);
        }

        /* Name + email combined cell */
        .sender-cell .name {
            font-weight: 600;
            color: var(--text);
            font-size: 0.88rem;
        }

        .sender-cell .email {
            font-size: 0.77rem;
            color: var(--text3);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .sender-cell .email i {
            font-size: 0.7rem;
        }

        /* Subject pill */
        .subject-tag {
            display: inline-block;
            background: var(--glow);
            color: var(--accent);
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Message preview */
        .msg-cell {
            max-width: 260px;
            font-size: 0.82rem;
            color: var(--text2);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Date */
        .date-cell {
            font-size: 0.78rem;
            color: var(--text3);
            white-space: nowrap;
        }

        /* Delete button */
        .abt-delete {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 7px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.07);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }

        .abt-delete:hover {
            background: #ef4444;
            color: #fff;
            box-shadow: 0 2px 10px rgba(239, 68, 68, 0.3);
        }

        .empty-row td {
            text-align: center;
            padding: 48px;
            color: var(--text3);
            font-size: 0.85rem;
        }

        .empty-row td i {
            display: block;
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.25;
        }

        /* ── ANIMATION ── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
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
            <li><a href="testimonials.php"><i class="fa fa-comments"></i> Testimonials</a></li>
            <li class="active"><a href="contactus.php"><i class="fa fa-envelope"></i> Contact Queries</a></li>
            <li class="sb-divider"></li>
            <li><a href="logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
        </ul>
    </div>

    <!-- MAIN -->
    <div class="main">

        <div class="top-bar">
            <div class="tb-left">
                <h2>Contact Queries</h2>
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

            <?php if ($msg): ?>
                <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-head">
                    <div class="card-head-left">
                        <h3><i class="fa fa-envelope-open-text"></i> Contact Form Queries</h3>
                        <p>Messages submitted by users via the Contact Us form</p>
                    </div>
                    <span class="count-pill"><?php echo $total; ?> message<?php echo $total != 1 ? 's' : ''; ?></span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Sender</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res && $res->num_rows > 0):
                                $cnt = 1;
                                while ($row = $res->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><span class="row-num"><?php echo $cnt; ?></span></td>
                                        <td>
                                            <div class="sender-cell">
                                                <div class="name">
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . ($row['last_name'] ?? '')); ?>
                                                </div>
                                                <div class="email"><i
                                                        class="fa fa-at"></i><?php echo htmlspecialchars($row['email']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="subject-tag"
                                                title="<?php echo htmlspecialchars($row['subject']); ?>"><?php echo htmlspecialchars($row['subject']); ?></span>
                                        </td>
                                        <td>
                                            <div class="msg-cell"><?php echo htmlspecialchars($row['message'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <div class="date-cell">
                                                <?php echo date('d M Y', strtotime($row['created_at'])); ?><br><?php echo date('H:i', strtotime($row['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="contactus.php?del=<?php echo $row['id']; ?>" class="abt-delete"
                                                onclick="return confirm('Delete this message permanently?')">
                                                <i class="fa fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php $cnt++; endwhile;
                            else: ?>
                                <tr class="empty-row">
                                    <td colspan="6">
                                        <i class="fa fa-envelope-open"></i>
                                        No contact messages found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Live date
        (function () {
            var d = new Date(), days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                mo = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            document.getElementById('dateLabel').textContent = days[d.getDay()] + ', ' + d.getDate() + ' ' + mo[d.getMonth()] + ' ' + d.getFullYear();
        })();

        // Theme toggle
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

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.alert').forEach(function (el) {
                setTimeout(function () {
                    el.style.transition = 'opacity 0.5s ease';
                    el.style.opacity = '0';
                    setTimeout(function () { el.style.display = 'none'; }, 500);
                }, 2500);
            });
        });
    </script>
</body>

</html>