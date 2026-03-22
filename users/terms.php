<?php
session_start();
$user_name = $_SESSION['user_name'] ?? 'User';
$initial = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; }

        [data-theme="dark"] {
            --bg: #0b0e14; --surface: #141920; --surface2: #1a2030;
            --border: rgba(255,255,255,0.06); --border2: rgba(255,255,255,0.1);
            --text: #f0f2f8; --text2: #8892a4; --text3: #44505e;
            --accent: #00d4ff; --accent2: #0090ff; --accentglow: rgba(0,212,255,0.18);
            --shadow: 0 4px 24px rgba(0,0,0,0.4);
            --sbg: #0a0d12; --sborder: rgba(255,255,255,0.05);
        }
        [data-theme="light"] {
            --bg: #f0f4f8; --surface: #ffffff; --surface2: #f5f8fc;
            --border: rgba(0,0,0,0.07); --border2: rgba(0,0,0,0.12);
            --text: #0f1923; --text2: #4a5568; --text3: #94a3b8;
            --accent: #0077cc; --accent2: #0055aa; --accentglow: rgba(0,119,204,0.16);
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --sbg: #1c2b3a; --sborder: rgba(255,255,255,0.06);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            transition: background 0.35s, color 0.35s;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 3px; }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--sbg);
            border-right: 1px solid var(--sborder);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
            transition: background 0.35s;
        }
        .sb-brand {
            padding: 26px 22px 18px;
            border-bottom: 1px solid var(--sborder);
        }
        .sb-brand a {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sb-logo {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            color: #fff;
            box-shadow: 0 0 14px var(--accentglow);
            flex-shrink: 0;
        }
        .sb-brand-text {
            font-size: 1.1rem;
            font-weight: 800;
            color: #e8edf5;
            letter-spacing: -0.02em;
        }
        .sb-brand-text span { color: var(--accent); }
        .sb-section {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(232,237,245,0.22);
            padding: 20px 22px 6px;
        }
        .sb-nav { list-style: none; padding: 6px 10px; }
        .sb-nav li { margin-bottom: 2px; }
        .sb-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 9px;
            font-size: 0.85rem;
            font-weight: 500;
            color: rgba(232,237,245,0.45);
            text-decoration: none;
            transition: all 0.2s;
        }
        .sb-nav a i { width: 16px; text-align: center; font-size: 0.82rem; }
        .sb-nav a:hover { background: rgba(0,212,255,0.07); color: rgba(232,237,245,0.85); }
        .sb-nav a.active {
            background: linear-gradient(90deg,rgba(0,212,255,0.15),rgba(0,212,255,0.04));
            color: var(--accent);
            font-weight: 600;
            box-shadow: inset 3px 0 0 var(--accent);
        }
        .sb-nav a.logout { color: rgba(255,79,79,0.6); }
        .sb-nav a.logout:hover { background: rgba(255,79,79,0.08); color: #ff4f4f; }
        .sb-divider { height: 1px; background: var(--sborder); margin: 10px 2px; }
        .sb-user-card {
            margin: 10px;
            padding: 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .sb-user-card .uav {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
            box-shadow: 0 0 12px var(--accentglow);
        }
        .sb-user-card .uname { font-size: 0.82rem; font-weight: 700; color: var(--text); }
        .sb-user-card .urole { font-size: 0.68rem; color: var(--text3); margin-top: 2px; }

        .main {
            margin-left: 240px;
            width: calc(100% - 240px);
            min-height: 100vh;
        }

        .top-bar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--bg);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.35s;
        }
        .tb-left h2 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.02em;
        }
        .tb-left p { font-size: 0.72rem; color: var(--text2); margin-top: 1px; }
        .tb-right { display: flex; align-items: center; gap: 10px; }
        .theme-btn {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            border: 1px solid var(--border2);
            background: var(--surface);
            color: var(--text2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .theme-btn:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 10px var(--accentglow); }

        .content {
            padding: 32px;
            max-width: 900px;
        }

        .page-header {
            margin-bottom: 32px;
            animation: fadeUp 0.5s ease;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .page-header h1 i {
            color: var(--accent);
            font-size: 1.8rem;
        }
        .page-header .subtitle {
            font-size: 0.85rem;
            color: var(--text2);
            margin-top: 8px;
        }

        .terms-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            animation: fadeUp 0.5s ease 0.1s both;
        }

        .toc {
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            padding: 20px 28px;
        }
        .toc-title {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text3);
            margin-bottom: 12px;
        }
        .toc-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .toc-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 0.78rem;
            color: var(--text2);
            text-decoration: none;
            transition: all 0.2s;
        }
        .toc-item:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(0,212,255,0.05);
        }
        .toc-item i { font-size: 0.65rem; color: var(--accent); }

        .terms-content {
            padding: 32px 28px;
        }

        .section {
            margin-bottom: 32px;
            animation: fadeUp 0.5s ease both;
        }
        .section:nth-child(1) { animation-delay: 0.1s; }
        .section:nth-child(2) { animation-delay: 0.15s; }
        .section:nth-child(3) { animation-delay: 0.2s; }
        .section:nth-child(4) { animation-delay: 0.25s; }
        .section:nth-child(5) { animation-delay: 0.3s; }
        .section:nth-child(6) { animation-delay: 0.35s; }
        .section:nth-child(7) { animation-delay: 0.4s; }
        .section:nth-child(8) { animation-delay: 0.45s; }

        .section h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .section h2 i {
            color: var(--accent);
            font-size: 0.9rem;
        }

        .section p {
            font-size: 0.88rem;
            color: var(--text2);
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .section ul li {
            font-size: 0.88rem;
            color: var(--text2);
            line-height: 1.6;
            padding: 6px 0;
            padding-left: 20px;
            position: relative;
        }
        .section ul li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 14px;
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
        }

        .highlight-box {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-left: 3px solid var(--accent);
            border-radius: 0 10px 10px 0;
            padding: 16px 20px;
            margin: 16px 0;
        }
        .highlight-box.warning {
            border-left-color: #fbbf24;
            background: rgba(251,191,36,0.05);
        }
        .highlight-box.warning h4 { color: #fbbf24; }
        .highlight-box.danger {
            border-left-color: #ff4f4f;
            background: rgba(255,79,79,0.05);
        }
        .highlight-box.danger h4 { color: #ff4f4f; }
        .highlight-box.success {
            border-left-color: #00e676;
            background: rgba(0,230,118,0.05);
        }
        .highlight-box.success h4 { color: #00e676; }
        .highlight-box h4 {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .highlight-box p {
            font-size: 0.82rem;
            margin-bottom: 0;
        }

        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .price-table th,
        .price-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        .price-table th {
            background: var(--surface2);
            font-weight: 700;
            color: var(--text);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .price-table td { color: var(--text2); }
        .price-table tr:hover td { background: var(--surface2); }

        .footer-note {
            background: var(--surface2);
            border-top: 1px solid var(--border);
            padding: 20px 28px;
            text-align: center;
        }
        .footer-note p {
            font-size: 0.78rem;
            color: var(--text3);
            margin-bottom: 8px;
        }
        .footer-note .last-updated {
            font-size: 0.72rem;
            color: var(--text3);
            font-style: italic;
        }

        @media(max-width: 768px) {
            .sidebar { display: none; }
            .main { margin-left: 0; width: 100%; }
            .content { padding: 20px 16px; }
            .toc { padding: 16px; }
            .terms-content { padding: 20px 16px; }
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
        <li><a href="booking.php"><i class="fa fa-car"></i> Book a Car</a></li>
        <li><a href="user_booking.php"><i class="fa fa-calendar-check"></i> My Bookings</a></li>
        <li><a href="profile.php"><i class="fa fa-user"></i> Profile</a></li>
        <li class="sb-divider"></li>
        <li><a href="../index.php"><i class="fa fa-arrow-left"></i> Back to Site</a></li>
        <li><a href="logout.php" class="logout"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
    <div style="flex:1;"></div>
    <div style="padding: 12px;">
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
            <h2>Terms & Conditions</h2>
            <p>Car Rental Agreement</p>
        </div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn" title="Toggle theme">
                <i class="fa fa-moon" id="themeIcon"></i>
            </button>
        </div>
    </div>

    <div class="content">
        <div class="page-header">
            <h1><i class="fa fa-file-contract"></i> Terms & Conditions</h1>
            <p class="subtitle">Please read these terms carefully before booking a vehicle</p>
        </div>

        <div class="terms-container">
            <div class="toc">
                <div class="toc-title">Quick Navigation</div>
                <div class="toc-list">
                    <a href="#eligibility" class="toc-item"><i class="fa fa-chevron-right"></i> Eligibility</a>
                    <a href="#booking" class="toc-item"><i class="fa fa-chevron-right"></i> Booking</a>
                    <a href="#payment" class="toc-item"><i class="fa fa-chevron-right"></i> Payment</a>
                    <a href="#vehicle" class="toc-item"><i class="fa fa-chevron-right"></i> Vehicle Use</a>
                    <a href="#insurance" class="toc-item"><i class="fa fa-chevron-right"></i> Insurance</a>
                    <a href="#cancellation" class="toc-item"><i class="fa fa-chevron-right"></i> Cancellation</a>
                    <a href="#damage" class="toc-item"><i class="fa fa-chevron-right"></i> Damage Policy</a>
                    <a href="#privacy" class="toc-item"><i class="fa fa-chevron-right"></i> Privacy</a>
                </div>
            </div>

            <div class="terms-content">
                <!-- 1. Eligibility -->
                <div class="section" id="eligibility">
                    <h2><i class="fa fa-user-check"></i> 1. Eligibility Requirements</h2>
                    <p>By using CarForYou rental services, you confirm that you meet the following requirements:</p>
                    <ul>
                        <li>You must be at least 21 years of age to rent a vehicle</li>
                        <li>A valid Sri Lankan driving license or international driving permit is required</li>
                        <li>You must provide a valid government-issued ID (NIC/Passport)</li>
                        <li>Credit card in the renter's name is required for security deposit</li>
                        <li>Only the person who made the booking may drive the vehicle unless otherwise approved</li>
                    </ul>
                    <div class="highlight-box warning">
                        <h4><i class="fa fa-exclamation-triangle"></i> Important</h4>
                        <p>Additional drivers must be registered and meet all eligibility criteria. Unregistered drivers are not covered by insurance.</p>
                    </div>
                </div>

                <!-- 2. Booking -->
                <div class="section" id="booking">
                    <h2><i class="fa fa-calendar-check"></i> 2. Booking Process</h2>
                    <p>All bookings are subject to availability and confirmation:</p>
                    <ul>
                        <li>Bookings can be made online through our website or at our offices</li>
                        <li>A booking confirmation will be sent via email upon successful reservation</li>
                        <li>Bookings are not confirmed until payment is received in full</li>
                        <li>Rental period begins from the selected pick-up date and time</li>
                        <li>Vehicle will be held for 2 hours past the scheduled pick-up time</li>
                        <li>Late collection without notice may result in cancellation</li>
                    </ul>
                </div>

                <!-- 3. Payment -->
                <div class="section" id="payment">
                    <h2><i class="fa fa-credit-card"></i> 3. Payment Terms</h2>
                    <p>Payment must be made in Sri Lankan Rupees (LKR) via the following methods:</p>
                    <table class="price-table">
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Credit/Debit Card (Visa, Mastercard)</td><td><span style="color:#00e676;">✓ Accepted</span></td></tr>
                            <tr><td>Bank Transfer</td><td><span style="color:#00e676;">✓ Accepted</span></td></tr>
                            <tr><td>Cash Payment</td><td><span style="color:#00e676;">✓ Accepted</span></td></tr>
                        </tbody>
                    </table>
                    <div class="highlight-box">
                        <h4><i class="fa fa-info-circle"></i> Security Deposit</h4>
                        <p>A refundable security deposit of LKR 25,000 to LKR 50,000 (depending on vehicle type) will be held on your card. This will be released within 7 business days after vehicle return.</p>
                    </div>
                </div>

                <!-- 4. Vehicle Use -->
                <div class="section" id="vehicle">
                    <h2><i class="fa fa-car"></i> 4. Vehicle Use Policy</h2>
                    <p>The rented vehicle must be used responsibly and lawfully:</p>
                    <ul>
                        <li>Vehicles must be driven only on recognized roads in Sri Lanka</li>
                        <li>Off-road driving is strictly prohibited unless pre-approved</li>
                        <li>Smoking, eating, or drinking inside the vehicle is not permitted</li>
                        <li>Transportation of hazardous materials is forbidden</li>
                        <li>Vehicles must be returned with the same fuel level as at pick-up</li>
                        <li>Unauthorized modifications to the vehicle are not allowed</li>
                    </ul>
                    <div class="highlight-box danger">
                        <h4><i class="fa fa-ban"></i> Prohibited Uses</h4>
                        <p>Racing, reckless driving, towing, subletting, or using the vehicle for illegal activities will result in immediate contract termination and potential legal action.</p>
                    </div>
                </div>

                <!-- 5. Insurance -->
                <div class="section" id="insurance">
                    <h2><i class="fa fa-shield-halved"></i> 5. Insurance Coverage</h2>
                    <p>All rentals include basic insurance coverage:</p>
                    <ul>
                        <li><strong>Basic Insurance:</strong> Covers third-party damages (included in rental price)</li>
                        <li><strong>Collision Damage Waiver (CDW):</strong> Reduces your liability for vehicle damage</li>
                        <li><strong>Personal Accident Insurance:</strong> Covers driver and passengers</li>
                        <li><strong>Theft Protection:</strong> Covers vehicle theft (police report required)</li>
                    </ul>
                    <div class="highlight-box warning">
                        <h4><i class="fa fa-exclamation-circle"></i> Excess Liability</h4>
                        <p>Without CDW, renter is liable for up to LKR 100,000 in case of accident damage. CDW reduces this to LKR 10,000-25,000 depending on vehicle class.</p>
                    </div>
                </div>

                <!-- 6. Cancellation -->
                <div class="section" id="cancellation">
                    <h2><i class="fa fa-xmark-circle"></i> 6. Cancellation Policy</h2>
                    <p>Cancellation fees apply based on timing:</p>
                    <table class="price-table">
                        <thead>
                            <tr>
                                <th>Cancellation Time</th>
                                <th>Refund</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>More than 7 days before pick-up</td><td><span style="color:#00e676;">100% Refund</span></td></tr>
                            <tr><td>3-7 days before pick-up</td><td><span style="color:#fbbf24;">50% Refund</span></td></tr>
                            <tr><td>Less than 3 days before pick-up</td><td><span style="color:#ff4f4f;">No Refund</span></td></tr>
                            <tr><td>No-show / Same day cancellation</td><td><span style="color:#ff4f4f;">No Refund</span></td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- 7. Damage Policy -->
                <div class="section" id="damage">
                    <h2><i class="fa fa-car-crash"></i> 7. Damage & Liability</h2>
                    <p>In the event of vehicle damage:</p>
                    <ul>
                        <li>Report all accidents or damage immediately to CarForYou</li>
                        <li>Obtain police report for any accident regardless of severity</li>
                        <li>Do not move the vehicle until authorized (unless unsafe)</li>
                        <li>Renter is responsible for all damage costs up to the excess amount</li>
                        <li>Damage assessment will be conducted upon vehicle return</li>
                        <li>Repair costs will be deducted from security deposit</li>
                    </ul>
                    <div class="highlight-box success">
                        <h4><i class="fa fa-check-circle"></i> What's Covered</h4>
                        <p>Normal wear and tear is expected and not chargeable. This includes minor scratches, tire wear, and interior stains from normal use.</p>
                    </div>
                </div>

                <!-- 8. Privacy -->
                <div class="section" id="privacy">
                    <h2><i class="fa fa-lock"></i> 8. Privacy & Data Protection</h2>
                    <p>Your privacy is important to us:</p>
                    <ul>
                        <li>Personal information collected is used solely for rental services</li>
                        <li>Driving license and ID copies are retained during rental period only</li>
                        <li>Location tracking data is used only with explicit consent</li>
                        <li>Your data will not be shared with third parties without consent</li>
                        <li>You may request deletion of your data after rental completion</li>
                    </ul>
                    <p>For any questions regarding your data, contact us at <strong style="color:var(--accent);">privacy@carforyou.lk</strong></p>
                </div>
            </div>

            <div class="footer-note">
                <p>By booking a vehicle with CarForYou, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.</p>
                <p class="last-updated">Last Updated: March 2026</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Theme Toggle
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function() {
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        syncIcon();
    });
    function syncIcon() {
        document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
    }

    // Smooth scroll for TOC
    document.querySelectorAll('.toc-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('href').substring(1);
            var target = document.getElementById(targetId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>
</body>
</html>
