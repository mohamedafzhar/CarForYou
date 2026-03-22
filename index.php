<?php
session_start();
include 'admin/config.php';

// ── Fetch approved testimonials (at top, before any HTML) ─────────────────────
$testimonials = [];
$tbl = $conn->query("SHOW TABLES LIKE 'testimonials'");
if ($tbl && $tbl->num_rows > 0) {
    $testi_result = $conn->query("
        SELECT user_name, car_name, rating, review, created_at
        FROM testimonials
        WHERE status = 1
        ORDER BY created_at DESC
        LIMIT 6
    ");
    if ($testi_result && $testi_result->num_rows > 0) {
        while ($t = $testi_result->fetch_assoc()) {
            $testimonials[] = $t;
        }
    }
}

// ── Build car catalogue for AI chatbot context ────────────────────────────────
$chatbot_cars = [];
if ($conn) {
    $car_res = $conn->query("SELECT car_name, car_model, car_type, price_per_day, car_overview, seating_capacity FROM cars WHERE status='Available' ORDER BY price_per_day DESC");
    if ($car_res && $car_res->num_rows > 0) {
        while ($c = $car_res->fetch_assoc()) {
            $chatbot_cars[] = $c;
        }
    }
}
$cars_json = json_encode($chatbot_cars);

// ── Fetch currently booked/unavailable cars ───────────────────────────────────
$booked_cars = [];
if ($conn) {
    // Get cars that are not available (Booked status)
    $booked_res = $conn->query("
        SELECT c.*
        FROM cars c
        WHERE c.status = 'Booked'
        ORDER BY c.id DESC
    ");
    if ($booked_res && $booked_res->num_rows > 0) {
        while ($bc = $booked_res->fetch_assoc()) {
            // Get the most recent active booking for this car
            $bk_query = $conn->query("
                SELECT from_date, to_date, status 
                FROM booking 
                WHERE car_id = " . intval($bc['id']) . " 
                AND status IN ('confirmed', 'awaiting_payment', 'Pending', 'pending')
                ORDER BY from_date DESC 
                LIMIT 1
            ");
            $bk = $bk_query ? $bk_query->fetch_assoc() : null;
            $bc['booking_from'] = $bk['from_date'] ?? 'N/A';
            $bc['booking_to'] = $bk['to_date'] ?? 'N/A';
            $bc['booking_status'] = $bk['status'] ?? 'unknown';
            $booked_cars[] = $bc;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarForYou | Premium Car Rental Service</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
    :root {
        --gold: #c9a84c;
        --gold-light: #e8c97a;
        --gold-dim: rgba(201,168,76,0.15);
        --red: #e63946;
        --red-dim: rgba(230,57,70,0.15);
        --transition: 0.4s cubic-bezier(0.4,0,0.2,1);
    }

    [data-theme="dark"] {
        --bg:#0d1117; --bg2:#131920; --bg3:#1a2230;
        --surface:#1e2738; --surface2:#253044;
        --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.15);
        --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
        --accent:#4f8ef7; --accent-glow:rgba(79,142,247,0.25);
        --hero-bg:linear-gradient(135deg,#0d1117 0%,#111b2a 50%,#132036 100%);
        --card-bg:#1e2738; --glass:rgba(30,39,56,0.85);
        --shadow:0 20px 60px rgba(0,0,0,0.5); --nav-bg:rgba(13,17,23,0.88);
        --gold:#4f8ef7; --gold-light:#7db0fb; --gold-dim:rgba(79,142,247,0.12);
    }
    [data-theme="dark"] .hero::before { background-image: linear-gradient(rgba(79,142,247,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(79,142,247,0.04) 1px, transparent 1px) !important; }
    [data-theme="dark"] .hero::after { background: radial-gradient(circle, rgba(79,142,247,0.07) 0%, transparent 70%) !important; }
    [data-theme="dark"] .contact-info { background: linear-gradient(160deg, #0d1117, #1a2d4a) !important; }
    [data-theme="dark"] .chat-header  { background: linear-gradient(135deg, #0d1117, #1a2d4a) !important; }
    [data-theme="dark"] #chatBtn { background: linear-gradient(135deg, #4f8ef7, #2563eb); box-shadow: 0 4px 24px rgba(79,142,247,0.4); animation: chatPulseDark 3s ease infinite; }
    @keyframes chatPulseDark { 0%,100% { box-shadow: 0 4px 24px rgba(79,142,247,0.4), 0 0 0 0 rgba(79,142,247,0.3); } 50% { box-shadow: 0 4px 24px rgba(79,142,247,0.4), 0 0 0 12px rgba(79,142,247,0); } }
    [data-theme="dark"] #chatSend { background: linear-gradient(135deg, #4f8ef7, #2563eb); color: #fff; }
    [data-theme="dark"] .msg.user .msg-bubble { background: linear-gradient(135deg, #4f8ef7, #2563eb); color: #fff; }
    [data-theme="dark"] .btn-primary { background: #4f8ef7; color: #0d1117; box-shadow: 0 4px 20px rgba(79,142,247,0.3); }
    [data-theme="dark"] .btn-primary:hover { box-shadow: 0 8px 30px rgba(79,142,247,0.45); }
    [data-theme="dark"] .btn-login { border-color: #4f8ef7; color: #4f8ef7; }
    [data-theme="dark"] .btn-login:hover { background: #4f8ef7; color: #0d1117; }
    [data-theme="dark"] .car-type-badge { background: #4f8ef7; color: #0d1117; }
    [data-theme="dark"] .price { background: rgba(79,142,247,0.1); border-color: rgba(79,142,247,0.2); color: #4f8ef7; }
    [data-theme="dark"] .feature-icon { background: rgba(79,142,247,0.1); border-color: rgba(79,142,247,0.2); color: #4f8ef7; }
    [data-theme="dark"] .feature-card:hover .feature-icon { background: #4f8ef7; color: #0d1117; box-shadow: 0 4px 20px rgba(79,142,247,0.3); }
    [data-theme="dark"] .feature-card::before { background: linear-gradient(90deg, transparent, #4f8ef7, transparent); }
    [data-theme="dark"] .testimonial-card::before { background: linear-gradient(90deg, #4f8ef7, #7db0fb); }
    [data-theme="dark"] .about-img::before { background: linear-gradient(135deg, #4f8ef7, transparent 60%); }
    [data-theme="dark"] .about-stat:hover { border-color: #4f8ef7; box-shadow: 0 4px 20px rgba(79,142,247,0.2); }
    [data-theme="dark"] .about-stat h4 { color: #4f8ef7; }
    [data-theme="dark"] .stat-item h3 { color: #4f8ef7; }
    [data-theme="dark"] .nav-links li a::after { background: #4f8ef7; }
    [data-theme="dark"] .section-header h2 span { color: #4f8ef7; }
    [data-theme="dark"] .section-label { color: #4f8ef7; }
    [data-theme="dark"] .logo h2 span { color: #4f8ef7; }
    [data-theme="dark"] .quote-icon { color: #4f8ef7; opacity: 0.4; }
    [data-theme="dark"] .stars { color: #f59e0b; }
    [data-theme="dark"] .hero-badge { background: rgba(79,142,247,0.1); border-color: rgba(79,142,247,0.25); color: #4f8ef7; }
    [data-theme="dark"] .hero-badge::before { background: #4f8ef7; }
    [data-theme="dark"] .info-item i { background: rgba(79,142,247,0.1); border-color: rgba(79,142,247,0.25); color: #4f8ef7; }
    [data-theme="dark"] .social-links a:hover { background: #4f8ef7; border-color: #4f8ef7; color: #0d1117; }
    [data-theme="dark"] .theme-toggle:hover { border-color: #4f8ef7; color: #4f8ef7; box-shadow: 0 0 12px rgba(79,142,247,0.25); }
    [data-theme="dark"] .qr-btn:hover { background: rgba(79,142,247,0.1); border-color: #4f8ef7; color: #4f8ef7; }
    [data-theme="dark"] #chatInput:focus { border-color: #4f8ef7; }
    [data-theme="dark"] .form-group input:focus, [data-theme="dark"] .form-group textarea:focus { border-color: #4f8ef7; box-shadow: 0 0 0 3px rgba(79,142,247,0.12); }
    [data-theme="dark"] .filter-bar select:focus { border-color: #4f8ef7; }
    [data-theme="dark"] ::-webkit-scrollbar-thumb { background: #2d4a72; }

    [data-theme="light"] {
        --bg:#f0f4f8; --bg2:#e8edf3; --bg3:#dde3ec;
        --surface:#ffffff; --surface2:#f5f7fa;
        --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
        --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
        --accent:#2563eb; --accent-glow:rgba(37,99,235,0.18);
        --hero-bg:linear-gradient(135deg,#eef2f7 0%,#e2eaf5 50%,#d8e4f2 100%);
        --card-bg:#ffffff; --glass:rgba(255,255,255,0.88);
        --shadow:0 12px 40px rgba(28,43,58,0.1); --nav-bg:rgba(240,244,248,0.92);
        --gold:#2563eb; --gold-light:#3b82f6; --gold-dim:rgba(37,99,235,0.1);
    }
    [data-theme="light"] .hero::before { background-image: linear-gradient(rgba(37,99,235,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(37,99,235,0.05) 1px, transparent 1px) !important; }
    [data-theme="light"] .hero::after { background: radial-gradient(circle, rgba(37,99,235,0.06) 0%, transparent 70%) !important; }
    [data-theme="light"] .contact-info { background: linear-gradient(160deg, #1c2b3a, #2a3f5a) !important; }
    [data-theme="light"] .chat-header { background: linear-gradient(135deg, #1c2b3a, #2a3f5a) !important; }
    [data-theme="light"] .hero-badge { background: rgba(37,99,235,0.08); border-color: rgba(37,99,235,0.25); color: #2563eb; }
    [data-theme="light"] .hero-badge::before { background: #2563eb; }
    [data-theme="light"] .feature-icon { background: rgba(37,99,235,0.08); border-color: rgba(37,99,235,0.18); color: #2563eb; }
    [data-theme="light"] .feature-card:hover .feature-icon { background: #2563eb; color: #fff; box-shadow: 0 4px 20px rgba(37,99,235,0.3); }
    [data-theme="light"] .price { background: rgba(37,99,235,0.08); border-color: rgba(37,99,235,0.18); color: #2563eb; }
    [data-theme="light"] .about-stat:hover { border-color: #2563eb; box-shadow: 0 4px 20px rgba(37,99,235,0.15); }
    [data-theme="light"] ::-webkit-scrollbar-thumb { background: #93b4d8; }
    [data-theme="light"] .car-type-badge { background: #2563eb; color: #fff; }
    [data-theme="light"] .btn-primary { background: #2563eb; color: #fff; box-shadow: 0 4px 20px rgba(37,99,235,0.25); }
    [data-theme="light"] .btn-primary:hover { box-shadow: 0 8px 30px rgba(37,99,235,0.35); }
    [data-theme="light"] #chatBtn { background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4px 24px rgba(37,99,235,0.35); animation: chatPulseBlue 3s ease infinite; }
    @keyframes chatPulseBlue { 0%,100% { box-shadow: 0 4px 24px rgba(37,99,235,0.35), 0 0 0 0 rgba(37,99,235,0.3); } 50% { box-shadow: 0 4px 24px rgba(37,99,235,0.35), 0 0 0 12px rgba(37,99,235,0); } }
    [data-theme="light"] #chatSend { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; }
    [data-theme="light"] .msg.user .msg-bubble { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; }
    [data-theme="light"] .btn-login { border-color: #2563eb; color: #2563eb; }
    [data-theme="light"] .btn-login:hover { background: #2563eb; color: #fff; box-shadow: 0 4px 20px rgba(37,99,235,0.25); }
    [data-theme="light"] .feature-card::before { background: linear-gradient(90deg, transparent, #2563eb, transparent); }
    [data-theme="light"] .testimonial-card::before { background: linear-gradient(90deg, #2563eb, #60a5fa); }
    [data-theme="light"] .about-img::before { background: linear-gradient(135deg, #2563eb, transparent 60%); }
    [data-theme="light"] .nav-links li a::after { background: #2563eb; }
    [data-theme="light"] .section-header h2 span { color: #2563eb; }
    [data-theme="light"] .stat-item h3 { color: #2563eb; }
    [data-theme="light"] .about-stat h4 { color: #2563eb; }
    [data-theme="light"] .quote-icon { color: #2563eb; }
    [data-theme="light"] .stars { color: #f59e0b; }
    [data-theme="light"] .section-label { color: #2563eb; }
    [data-theme="light"] .logo h2 span { color: #2563eb; }
    [data-theme="light"] .social-links a:hover { background: #2563eb; border-color: #2563eb; color: #fff; }
    [data-theme="light"] .info-item i { background: rgba(37,99,235,0.1); border-color: rgba(37,99,235,0.25); color: #2563eb; }
    [data-theme="light"] .theme-toggle:hover { border-color: #2563eb; color: #2563eb; box-shadow: 0 0 12px rgba(37,99,235,0.2); }
    [data-theme="light"] .qr-btn:hover { background: rgba(37,99,235,0.08); border-color: #2563eb; color: #2563eb; }
    [data-theme="light"] #chatInput:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
    [data-theme="light"] .form-group input:focus, [data-theme="light"] .form-group textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
    [data-theme="light"] .filter-bar select:focus { border-color: #2563eb; }
    
    /* Light Mode Car Listing */
    [data-theme="light"] .listing-section::before {
        background: radial-gradient(circle, rgba(37, 99, 235, 0.15) 0%, transparent 70%);
    }
    [data-theme="light"] .listing-section::after {
        background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
    }
    [data-theme="light"] .car-card {
        background: rgba(255, 255, 255, 0.8);
        border-color: rgba(0, 0, 0, 0.08);
    }
    [data-theme="light"] .car-card:hover {
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 30px 60px rgba(28, 43, 58, 0.15);
    }
    [data-theme="light"] .filter-bar {
        background: rgba(255, 255, 255, 0.9);
        border-color: rgba(0, 0, 0, 0.08);
    }
    [data-theme="light"] .filter-bar select {
        background: rgba(255, 255, 255, 0.5);
        border-color: rgba(0, 0, 0, 0.1);
    }
    [data-theme="light"] .feature-card {
        background: rgba(255, 255, 255, 0.8);
        border-color: rgba(0, 0, 0, 0.06);
    }
    [data-theme="light"] .feature-card:hover {
        background: rgba(255, 255, 255, 0.95);
    }
    [data-theme="light"] .testimonial-card {
        background: rgba(255, 255, 255, 0.8);
        border-color: rgba(0, 0, 0, 0.06);
    }
    [data-theme="light"] .testimonial-card:hover {
        background: rgba(255, 255, 255, 0.95);
    }
    [data-theme="light"] .glass-card {
        background: rgba(255, 255, 255, 0.8);
    }
    [data-theme="light"] .testimonial-card:hover { box-shadow: 0 12px 40px rgba(28,43,58,0.12); }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior: smooth; font-size: 16px; }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); overflow-x: hidden; transition: background var(--transition), color var(--transition); cursor: default; }
    ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: var(--bg); } ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 3px; }
    .page-view { display: none; } .page-view.active { display: block; }
    ::selection { background: var(--gold-dim); color: var(--gold); }
    .container { max-width: 1280px; margin: 0 auto; padding: 0 40px; }
    a { text-decoration: none; color: inherit; }

    header { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: var(--nav-bg); backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); transition: all var(--transition); height: 72px; display: flex; align-items: center; }
    header.scrolled { height: 64px; box-shadow: 0 4px 30px rgba(0,0,0,0.3); }
    header .container { width: 100%; max-width: 1280px; }
    nav { display: flex; align-items: center; justify-content: space-between; gap: 40px; }
    .logo a { display: flex; align-items: center; text-decoration: none; color: inherit; }
    .logo h2 { font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 1.75rem; letter-spacing: 0.02em; color: var(--text); transition: color var(--transition); }
    .logo h2 span { color: var(--accent); font-style: italic; }
    .nav-links { display: flex; list-style: none; align-items: center; gap: 8px; flex: 1; justify-content: center; }
    .nav-links li a { font-size: 0.82rem; font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text2); padding: 8px 16px; border-radius: 6px; position: relative; transition: color var(--transition); }
    .nav-links li a::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 0; height: 2px; background: var(--accent); border-radius: 2px; transition: width 0.3s ease; }
    .nav-links li:hover a, .nav-links li.active a { color: var(--text); }
    .nav-links li:hover a::after, .nav-links li.active a::after { width: 60%; }
    .auth-controls { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .mobile-auth { display: none; }
    .btn-login { display: inline-flex; align-items: center; gap: 7px; font-size: 0.78rem; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; padding: 9px 20px; border-radius: 6px; border: 1px solid var(--accent); color: var(--accent); background: transparent; transition: all 0.25s ease; white-space: nowrap; text-decoration: none; }
    .btn-login:hover { background: var(--accent); color: #fff; box-shadow: 0 0 20px var(--accent-glow); }
    .user-pill { display: inline-flex; align-items: center; gap: 0; background: var(--surface); border: 1px solid var(--border2); border-radius: 40px; padding: 4px 4px 4px 4px; box-shadow: 0 0 0 1px transparent, 0 2px 12px rgba(0,0,0,0.15); transition: box-shadow 0.3s, border-color 0.3s; position: relative; }
    .user-pill::before { content: ''; position: absolute; inset: -1px; border-radius: 40px; background: linear-gradient(135deg, var(--accent), var(--accent2, var(--accent))); opacity: 0; z-index: -1; transition: opacity 0.3s; }
    .user-pill:hover { border-color: var(--accent); box-shadow: 0 0 18px var(--accent-glow), 0 2px 12px rgba(0,0,0,0.15); }
    .user-pill-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #7db0fb); display: flex; align-items: center; justify-content: center; font-family: 'Cormorant Garamond', serif; font-size: 1rem; font-weight: 700; color: #fff; flex-shrink: 0; box-shadow: 0 0 12px var(--accent-glow); letter-spacing: 0; position: relative; }
    .user-pill-avatar::after { content: ''; position: absolute; bottom: 1px; right: 1px; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; border: 2px solid var(--surface); }
    .user-pill-info { display: flex; flex-direction: column; padding: 0 10px 0 8px; line-height: 1; }
    .user-pill-label { font-size: 0.6rem; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text3); }
    .user-pill-name { font-size: 0.88rem; font-weight: 700; color: var(--text); background: linear-gradient(90deg, var(--accent), var(--accent2, var(--accent))); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; white-space: nowrap; max-width: 130px; overflow: hidden; text-overflow: ellipsis; margin-top: 3px; letter-spacing: -0.01em; }
    .user-pill-divider { width: 1px; height: 20px; background: var(--border2); flex-shrink: 0; margin: 0 4px; }
    .user-pill-action { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; color: var(--text3); transition: all 0.2s; text-decoration: none; flex-shrink: 0; }
    .user-pill-action:hover { background: var(--accent); color: #fff; box-shadow: 0 0 10px var(--accent-glow); }
    .user-pill-logout:hover { background: rgba(230,57,70,0.12); color: #e63946; box-shadow: none; }
    .btn-dashboard { display: inline-flex; align-items: center; gap: 7px; font-size: 0.78rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 9px 20px; border-radius: 6px; background: var(--accent); color: #0a0a0b; border: none; white-space: nowrap; transition: all 0.25s ease; box-shadow: 0 4px 16px var(--accent-glow); text-decoration: none; }
    .btn-dashboard:hover { opacity:0.88; transform:translateY(-1px); }
    .btn-logout { display: inline-flex; align-items: center; gap: 7px; font-size: 0.78rem; font-weight: 600; padding: 9px 16px; border-radius: 6px; border: 1px solid var(--border2); color: var(--text2); background: transparent; text-decoration: none; transition: all 0.25s; }
    .btn-logout:hover { border-color: #e63946; color: #e63946; }
    .user-greeting { font-size: 0.8rem; color: var(--text2); font-weight: 400; white-space: nowrap; }
    .user-greeting strong { color: var(--accent); font-weight: 700; }
    .theme-toggle { width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--border2); background: var(--surface); color: var(--text2); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.25s ease; flex-shrink: 0; }
    .theme-toggle:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 12px var(--accent-glow); }
    .mobile-menu-btn { display: none; width: 40px; height: 40px; align-items: center; justify-content: center; cursor: pointer; color: var(--text); font-size: 1.1rem; border: 1px solid var(--border2); border-radius: 8px; }

    .hero { min-height: 100vh; background: var(--hero-bg); display: flex; align-items: center; position: relative; overflow: hidden; padding-top: 72px; perspective: 1000px; }
    .hero::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(201,168,76,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(201,168,76,0.04) 1px, transparent 1px); background-size: 60px 60px; animation: gridMove 20s linear infinite; pointer-events: none; }
    [data-theme="light"] .hero::before { background-image: linear-gradient(rgba(154,120,41,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(154,120,41,0.06) 1px, transparent 1px); }
    @keyframes gridMove { from { transform: translateY(0); } to { transform: translateY(60px); } }
    .hero::after { content: ''; position: absolute; width: 800px; height: 800px; border-radius: 50%; background: radial-gradient(circle, rgba(201,168,76,0.07) 0%, transparent 70%); top: -200px; right: -200px; pointer-events: none; animation: pulse 8s ease-in-out infinite; }
    @keyframes pulse { 0%,100% { transform: scale(1); opacity: 0.7; } 50% { transform: scale(1.1); opacity: 1; } }
    .hero .container { position: relative; z-index: 1; }
    
    /* Parallax 3D Background Elements */
    .parallax-bg { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
    .parallax-layer { position: absolute; width: 100%; height: 100%; }
    .parallax-layer.layer-1 { transform: translateZ(-100px) scale(1.2); }
    .parallax-layer.layer-2 { transform: translateZ(-200px) scale(1.4); }
    .parallax-layer.layer-3 { transform: translateZ(-300px) scale(1.6); }
    
    /* 3D Floating Particles */
    .particle { position: absolute; border-radius: 50%; background: linear-gradient(135deg, var(--accent), transparent); opacity: 0.3; animation: float3d 6s ease-in-out infinite; }
    .particle:nth-child(1) { width: 400px; height: 400px; top: 5%; left: -100px; animation-delay: 0s; filter: blur(60px); }
    .particle:nth-child(2) { width: 300px; height: 300px; top: 40%; right: -80px; animation-delay: 2s; background: linear-gradient(135deg, #a78bfa, transparent); filter: blur(50px); }
    .particle:nth-child(3) { width: 250px; height: 250px; bottom: 10%; left: 30%; animation-delay: 4s; background: linear-gradient(135deg, #22c55e, transparent); filter: blur(40px); }
    .particle:nth-child(4) { width: 200px; height: 200px; top: 20%; right: 20%; animation-delay: 1s; filter: blur(45px); }
    .particle:nth-child(5) { width: 180px; height: 180px; bottom: 30%; right: 10%; animation-delay: 3s; background: linear-gradient(135deg, #f59e0b, transparent); filter: blur(35px); }
    .particle:nth-child(6) { width: 150px; height: 150px; top: 60%; left: 5%; animation-delay: 5s; filter: blur(30px); }
    @keyframes float3d {
        0%, 100% { transform: translate(0, 0) scale(1); }
        25% { transform: translate(20px, -30px) scale(1.05); }
        50% { transform: translate(-10px, -20px) scale(0.95); }
        75% { transform: translate(30px, -10px) scale(1.02); }
    }
    
    /* 3D Depth Lines */
    .depth-lines { position: absolute; inset: 0; overflow: hidden; pointer-events: none; opacity: 0.5; }
    .depth-line {
        position: absolute;
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        animation: lineMove 8s linear infinite;
    }
    .depth-line:nth-child(1) { top: 15%; width: 80%; left: -20%; animation-delay: 0s; }
    .depth-line:nth-child(2) { top: 30%; width: 60%; right: -15%; left: auto; animation-delay: 1.5s; }
    .depth-line:nth-child(3) { top: 45%; width: 70%; left: -25%; animation-delay: 3s; }
    .depth-line:nth-child(4) { top: 60%; width: 50%; right: -10%; left: auto; animation-delay: 4.5s; }
    .depth-line:nth-child(5) { top: 75%; width: 65%; left: -15%; animation-delay: 6s; }
    .depth-line:nth-child(6) { top: 90%; width: 55%; right: -5%; left: auto; animation-delay: 7.5s; }
    @keyframes lineMove {
        0% { transform: translateX(-100%); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateX(200%); opacity: 0; }
    }
    
    /* 3D Glow Orbs */
    .glow-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        animation: orbPulse 6s ease-in-out infinite;
    }
    .glow-orb:nth-child(1) { width: 500px; height: 500px; background: rgba(79, 142, 247, 0.15); top: -100px; right: -100px; animation-delay: 0s; }
    .glow-orb:nth-child(2) { width: 400px; height: 400px; background: rgba(139, 92, 246, 0.1); bottom: -50px; left: -100px; animation-delay: 2s; }
    .glow-orb:nth-child(3) { width: 300px; height: 300px; background: rgba(34, 197, 94, 0.08); top: 50%; left: 50%; animation-delay: 4s; }
    @keyframes orbPulse {
        0%, 100% { transform: scale(1); opacity: 0.5; }
        50% { transform: scale(1.2); opacity: 0.8; }
    }
    
    /* 3D Text Effect */
    .hero-3d-text {
        animation: textFloat 4s ease-in-out infinite;
        transform-style: preserve-3d;
    }
    @keyframes textFloat {
        0%, 100% { transform: translateY(0) rotateX(0deg); }
        50% { transform: translateY(-10px) rotateX(2deg); }
    }
    .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: var(--gold-dim); border: 1px solid rgba(201,168,76,0.3); color: var(--gold); font-size: 0.72rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; padding: 7px 16px; border-radius: 20px; margin-bottom: 28px; animation: fadeInUp 0.6s ease both; }
    .hero-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--gold); animation: blink 2s ease infinite; }
    @keyframes blink { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
    .hero-content h1 { font-family: 'Cormorant Garamond', serif; font-size: clamp(3.2rem, 7vw, 6.5rem); font-weight: 300; line-height: 1.0; letter-spacing: -0.02em; color: var(--text); margin-bottom: 24px; animation: fadeInUp 0.7s 0.1s ease both; }
    .hero-content h1 em { font-style: italic; color: var(--accent); font-weight: 400; }
    .hero-content p { font-size: 1.1rem; font-weight: 300; color: var(--text2); max-width: 520px; line-height: 1.7; margin-bottom: 44px; animation: fadeInUp 0.7s 0.2s ease both; }
    .hero-btns { display: flex; gap: 16px; flex-wrap: wrap; animation: fadeInUp 0.7s 0.3s ease both; }
    .hero-stats { display: flex; gap: 48px; margin-top: 80px; padding-top: 48px; border-top: 1px solid var(--border); animation: fadeInUp 0.7s 0.5s ease both; }
    .stat-item h3 { font-family: 'Cormorant Garamond', serif; font-size: 2.4rem; font-weight: 600; color: var(--accent); line-height: 1; }
    .stat-item p { font-size: 0.78rem; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text3); margin-top: 4px; }
    
    /* Apple-Style Scroll Indicator */
    .scroll-indicator {
        position: absolute;
        bottom: 40px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        animation: fadeInUp 1s 1.2s ease both;
    }
    .scroll-line {
        width: 1px;
        height: 60px;
        background: linear-gradient(to bottom, var(--accent), transparent);
        position: relative;
        overflow: hidden;
    }
    .scroll-line::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 20px;
        background: var(--accent);
        animation: scrollDown 2s ease-in-out infinite;
    }
    @keyframes scrollDown {
        0% { top: -20px; opacity: 1; }
        100% { top: 60px; opacity: 0; }
    }
    .scroll-indicator span {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: var(--text3);
    }
    
    @keyframes fadeInUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }

    /* Apple-Style Buttons */
    .btn { 
        display: inline-flex; 
        align-items: center; 
        gap: 10px; 
        padding: 16px 32px; 
        border-radius: 12px; 
        font-size: 0.88rem; 
        font-weight: 600; 
        letter-spacing: 0.05em; 
        text-transform: uppercase; 
        cursor: pointer; 
        border: none; 
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
        transition: left 0.6s ease;
    }
    .btn:hover::before {
        left: 100%;
    }
    .btn-primary { 
        background: linear-gradient(135deg, var(--accent), #7db0fb);
        color: #fff; 
        box-shadow: 0 8px 30px rgba(79, 142, 247, 0.35);
    }
    .btn-primary:hover { 
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 15px 50px rgba(79, 142, 247, 0.45);
    }
    .btn-primary:active {
        transform: translateY(-1px) scale(0.98);
    }
    .btn-outline { 
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: var(--text); 
    }
    .btn-outline:hover { 
        border-color: var(--accent); 
        color: var(--accent);
        background: rgba(79, 142, 247, 0.1);
    }
    .btn:not(.btn-primary):hover { 
        border-color: var(--accent); 
        color: var(--accent);
        transform: translateY(-2px);
    }

    .section-padding { padding: 120px 0; }
    .section-header { 
        text-align: center; 
        margin-bottom: 60px;
        opacity: 0;
        transform: translateY(40px);
        transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
    }
    .section-header.visible {
        opacity: 1;
        transform: translateY(0);
    }
    .section-label { 
        display: inline-block; 
        font-size: 0.72rem; 
        font-weight: 700; 
        letter-spacing: 0.3em; 
        text-transform: uppercase; 
        color: var(--accent); 
        margin-bottom: 20px;
        position: relative;
    }
    .section-label::before,
    .section-label::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 40px;
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--accent));
    }
    .section-label::before {
        right: calc(100% + 16px);
        background: linear-gradient(90deg, transparent, var(--accent));
    }
    .section-label::after {
        left: calc(100% + 16px);
        background: linear-gradient(270deg, transparent, var(--accent));
    }
    .section-header h2 { 
        font-family: 'Cormorant Garamond', serif; 
        font-size: clamp(2.5rem, 5vw, 4rem); 
        font-weight: 300; 
        color: var(--text); 
        line-height: 1.1; 
        letter-spacing: -0.02em;
        margin-bottom: 16px;
    }
    .section-header h2 span { 
        color: var(--accent); 
        font-style: italic;
        font-weight: 400;
    }
    .section-header p { 
        font-size: 1.1rem; 
        color: var(--text2); 
        max-width: 560px; 
        margin: 0 auto; 
        line-height: 1.7;
        font-weight: 400;
    }
    .section-divider { height: 1px; background: linear-gradient(90deg, transparent, var(--border2), transparent); margin: 0; }

    .features-section { background: var(--bg2); position: relative; overflow: hidden; }
    .features-section::before {
        content: '';
        position: absolute;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(79, 142, 247, 0.1) 0%, transparent 70%);
        border-radius: 50%;
        filter: blur(80px);
        top: -100px;
        left: -100px;
        pointer-events: none;
    }
    .features-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 24px;
    }
    .feature-card { 
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px; 
        padding: 48px 40px; 
        position: relative; 
        transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
        overflow: hidden;
    }
    .feature-card::before { 
        content: ''; 
        position: absolute; 
        top: 0; left: 0; right: 0; height: 2px; 
        background: linear-gradient(90deg, transparent, var(--accent), transparent); 
        transform: scaleX(0); 
        transition: transform 0.5s ease;
    }
    .feature-card:hover::before { transform: scaleX(1); }
    .feature-card:hover { 
        background: rgba(255, 255, 255, 0.06);
        transform: translateY(-12px) scale(1.02);
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        border-color: rgba(79, 142, 247, 0.3);
    }
    .feature-icon { 
        width: 70px; 
        height: 70px; 
        border-radius: 20px; 
        background: linear-gradient(135deg, rgba(79, 142, 247, 0.15), rgba(79, 142, 247, 0.05));
        border: 1px solid rgba(79, 142, 247, 0.2);
        display: flex; 
        align-items: center; 
        justify-content: center; 
        margin-bottom: 28px; 
        font-size: 1.6rem; 
        color: var(--accent); 
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    }
    .feature-card:hover .feature-icon { 
        background: linear-gradient(135deg, var(--accent), #7db0fb);
        color: #fff; 
        box-shadow: 0 10px 40px rgba(79, 142, 247, 0.4);
        transform: scale(1.1) rotate(5deg);
    }
    .feature-card h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; font-weight: 600; color: var(--text); margin-bottom: 12px; }
    .feature-card p { font-size: 0.95rem; color: var(--text2); line-height: 1.8; }

    .testimonial-bg { background: var(--bg); position: relative; overflow: hidden; }
    .testimonial-bg::before {
        content: '';
        position: absolute;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(139, 92, 246, 0.08) 0%, transparent 70%);
        border-radius: 50%;
        filter: blur(80px);
        bottom: -100px;
        right: -100px;
        pointer-events: none;
    }
    .testimonial-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 24px;
    }
    .testimonial-card { 
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px; 
        padding: 40px; 
        position: relative; 
        transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
        overflow: hidden;
    }
    .testimonial-card::before { 
        content: ''; 
        position: absolute; 
        bottom: 0; left: 0; right: 0; height: 3px; 
        background: linear-gradient(90deg, var(--accent), #a855f7); 
        transform: scaleX(0); 
        transform-origin: left; 
        transition: transform 0.5s ease;
    }
    .testimonial-card:hover { 
        transform: translateY(-8px);
        background: rgba(255, 255, 255, 0.06);
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        border-color: rgba(79, 142, 247, 0.2);
    }
    .testimonial-card:hover::before { transform: scaleX(1); }
    .quote-icon { 
        font-size: 3rem; 
        color: var(--accent); 
        opacity: 0.2; 
        margin-bottom: 20px; 
        display: block;
        transition: all 0.4s ease;
    }
    .testimonial-card:hover .quote-icon { 
        opacity: 0.4;
        transform: scale(1.2);
    }
    .testimonial-card > p { 
        font-size: 0.95rem; 
        color: var(--text2); 
        line-height: 1.8; 
        margin-bottom: 28px; 
        font-style: italic; 
    }
    .user-info { display: flex; align-items: center; gap: 14px; }
    .user-img { 
        width: 50px; 
        height: 50px; 
        border-radius: 50%; 
        background-size: cover; 
        background-position: center; 
        border: 2px solid rgba(79, 142, 247, 0.3);
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    .testimonial-card:hover .user-img {
        border-color: var(--accent);
        box-shadow: 0 4px 20px rgba(79, 142, 247, 0.3);
    }
    .user-details h4 { font-size: 0.9rem; font-weight: 600; color: var(--text); }
    .user-details p { font-size: 0.78rem; color: var(--text3); margin-top: 2px; }
    .stars { display: flex; gap: 3px; margin-bottom: 20px; color: var(--accent); font-size: 0.75rem; }

    .about-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
    .about-img { position: relative; border-radius: 16px; overflow: hidden; }
    .about-img::before { content: ''; position: absolute; inset: -2px; background: linear-gradient(135deg, var(--accent), transparent 60%); border-radius: 18px; z-index: -1; }
    .about-img img { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; border-radius: 16px; filter: brightness(0.9) contrast(1.05); transition: transform 0.6s ease; }
    .about-img:hover img { transform: scale(1.03); }
    .about-text h3 { font-family: 'Cormorant Garamond', serif; font-size: 2.2rem; font-weight: 400; color: var(--text); line-height: 1.3; margin-bottom: 20px; }
    .about-text p { color: var(--text2); line-height: 1.8; margin-bottom: 16px; font-size: 0.95rem; }
    .about-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 36px; }
    .about-stat { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; transition: all 0.3s; }
    .about-stat:hover { border-color: var(--accent); box-shadow: 0 4px 20px var(--accent-glow); }
    .about-stat h4 { font-family: 'Cormorant Garamond', serif; font-size: 2rem; font-weight: 600; color: var(--accent); }
    .about-stat p { font-size: 0.8rem; color: var(--text3); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.08em; }

    /* ── ADVANCED ABOUT US SECTION ── */
    .about-hero {
        position: relative;
        min-height: 60vh;
        display: flex;
        align-items: center;
        overflow: hidden;
        padding: 140px 0 80px;
    }
    .about-hero-bg {
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(24,18,10,0.95) 0%, rgba(42,31,13,0.9) 100%);
    }
    .about-hero-bg::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a84c' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        opacity: 0.5;
    }
    .about-hero-particles {
        position: absolute;
        inset: 0;
        overflow: hidden;
    }
    .about-hero-particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: var(--gold);
        border-radius: 50%;
        opacity: 0.2;
        animation: floatUp 15s infinite linear;
    }
    @keyframes floatUp {
        0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
        10% { opacity: 0.2; }
        90% { opacity: 0.2; }
        100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
    }
    .about-hero-content {
        position: relative;
        z-index: 2;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 80px;
        align-items: center;
    }
    .about-hero-text { animation: fadeInLeft 0.8s ease; }
    @keyframes fadeInLeft {
        0% { opacity: 0; transform: translateX(-40px); }
        100% { opacity: 1; transform: translateX(0); }
    }
    .about-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(37,99,235,0.15);
        border: 1px solid rgba(37,99,235,0.3);
        padding: 8px 16px;
        border-radius: 30px;
        font-size: 0.8rem;
        color: #2563eb;
        margin-bottom: 24px;
        font-weight: 500;
    }
    .about-hero-badge::before { content: ''; width: 8px; height: 8px; background: #2563eb; border-radius: 50%; animation: pulse 2s infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
    .about-hero-text h1 { font-family: 'Cormorant Garamond', serif; font-size: 3.5rem; font-weight: 400; color: #f0ede8; line-height: 1.2; margin-bottom: 24px; }
    .about-hero-text h1 span { color: #2563eb; font-style: italic; }
    .about-hero-text p { color: rgba(240,237,232,0.7); font-size: 1.1rem; line-height: 1.8; margin-bottom: 32px; max-width: 500px; }
    .about-hero-stats { display: flex; gap: 40px; }
    .about-hero-stat { text-align: center; }
    .about-hero-stat h3 { font-family: 'Cormorant Garamond', serif; font-size: 2.8rem; font-weight: 600; color: #2563eb; line-height: 1; }
    .about-hero-stat p { font-size: 0.75rem; color: rgba(240,237,232,0.5); text-transform: uppercase; letter-spacing: 0.1em; margin-top: 8px; }
    
    .about-hero-visual {
        position: relative;
        animation: fadeInRight 0.8s ease 0.2s both;
    }
    @keyframes fadeInRight {
        0% { opacity: 0; transform: translateX(40px); }
        100% { opacity: 1; transform: translateX(0); }
    }
    .about-hero-img-main {
        position: relative;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 30px 80px rgba(0,0,0,0.5);
    }
    .about-hero-img-main::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(37,99,235,0.1), transparent 50%);
        z-index: 1;
        pointer-events: none;
    }
    .about-hero-img-main img { width: 100%; display: block; border-radius: 20px; }
    .about-hero-float-card {
        position: absolute;
        background: rgba(26,24,20,0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(37,99,235,0.2);
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    }
    .about-hero-float-card.top-right { top: -20px; right: -20px; animation: floatCard 3s ease-in-out infinite; }
    .about-hero-float-card.bottom-left { bottom: -20px; left: -20px; animation: floatCard 3s ease-in-out infinite 0.5s; }
    @keyframes floatCard { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    .about-hero-float-card .card-icon { width: 50px; height: 50px; background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(37,99,235,0.1)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .about-hero-float-card .card-content h5 { font-size: 0.9rem; color: #f0ede8; margin-bottom: 2px; }
    .about-hero-float-card .card-content p { font-size: 0.7rem; color: rgba(240,237,232,0.5); }

    /* ── MISSION & VALUES ── */
    .about-mission {
        padding: 100px 0;
        background: var(--bg2);
        position: relative;
    }
    .about-mission::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 1px;
        height: 60px;
        background: linear-gradient(to bottom, #2563eb, transparent);
    }
    .mission-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 60px; }
    .mission-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 40px 30px;
        text-align: center;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
    }
    .mission-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(37,99,235,0.15), transparent);
        opacity: 0;
        transition: opacity 0.3s;
    }
    .mission-card:hover { transform: translateY(-10px); border-color: #2563eb; box-shadow: 0 20px 60px rgba(0,0,0,0.3), 0 0 30px rgba(37,99,235,0.2); }
    .mission-card:hover::before { opacity: 0.5; }
    .mission-card > * { position: relative; z-index: 1; }
    .mission-icon { width: 80px; height: 80px; background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(37,99,235,0.1)); border: 1px solid rgba(37,99,235,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2rem; transition: all 0.3s; }
    .mission-card:hover .mission-icon { transform: scale(1.1) rotate(5deg); background: #2563eb; }
    .mission-card:hover .mission-icon::before { filter: brightness(0); }
    .mission-card h4 { font-family: 'Cormorant Garamond', serif; font-size: 1.5rem; color: #f0ede8; margin-bottom: 16px; }
    .mission-card p { color: var(--text2); font-size: 0.9rem; line-height: 1.7; }

    /* ── TIMELINE ── */
    .about-timeline { padding: 100px 0; background: var(--bg); position: relative; overflow: hidden; }
    .timeline-container { position: relative; max-width: 900px; margin: 60px auto 0; }
    .timeline-line { position: absolute; left: 50%; top: 0; bottom: 0; width: 2px; background: linear-gradient(to bottom, #2563eb, rgba(37,99,235,0.2)); transform: translateX(-50%); }
    .timeline-item { display: flex; align-items: center; margin-bottom: 50px; position: relative; }
    .timeline-item:nth-child(even) { flex-direction: row-reverse; }
    .timeline-item:nth-child(even) .timeline-content { text-align: right; padding-right: 60px; padding-left: 0; }
    .timeline-item:nth-child(odd) .timeline-content { text-align: left; padding-left: 60px; }
    .timeline-dot { position: absolute; left: 50%; transform: translateX(-50%); width: 20px; height: 20px; background: #2563eb; border-radius: 50%; border: 4px solid var(--bg); box-shadow: 0 0 20px rgba(37,99,235,0.4); z-index: 2; }
    .timeline-content { flex: 1; }
    .timeline-content h5 { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; color: #2563eb; margin-bottom: 8px; }
    .timeline-content h6 { font-size: 1.1rem; color: #f0ede8; margin-bottom: 10px; }
    .timeline-content p { color: var(--text2); font-size: 0.9rem; line-height: 1.6; }
    .timeline-spacer { flex: 1; }

    /* ── TEAM SECTION ── */
    .about-team { padding: 100px 0; background: var(--bg2); }
    .team-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; margin-top: 60px; }
    .team-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.4s ease;
        text-align: center;
    }
    .team-card:hover { transform: translateY(-10px); border-color: #2563eb; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    .team-img { position: relative; overflow: hidden; height: 220px; background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(37,99,235,0.05)); display: flex; align-items: center; justify-content: center; font-size: 4rem; }
    .team-img::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, var(--surface), transparent); }
    .team-info { padding: 24px; position: relative; z-index: 1; }
    .team-info h5 { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; color: #f0ede8; margin-bottom: 4px; }
    .team-info p { font-size: 0.8rem; color: #2563eb; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 16px; }
    .team-social { display: flex; justify-content: center; gap: 12px; }
    .team-social a { width: 36px; height: 36px; border-radius: 50%; background: var(--bg2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--text2); transition: all 0.3s; font-size: 0.85rem; }
    .team-social a:hover { background: #2563eb; border-color: #2563eb; color: #fff; transform: translateY(-3px); }

    /* ── WHY CHOOSE US ── */
    .about-why { padding: 100px 0; background: var(--bg); }
    .why-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; margin-top: 60px; }
    .why-card {
        display: flex;
        gap: 24px;
        padding: 32px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        transition: all 0.3s;
    }
    .why-card:hover { border-color: #2563eb; transform: translateX(10px); box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .why-icon { width: 70px; height: 70px; background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(37,99,235,0.1)); border: 1px solid rgba(37,99,235,0.3); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; transition: all 0.3s; }
    .why-card:hover .why-icon { background: #2563eb; transform: rotate(5deg); }
    .why-card:hover .why-icon::before { filter: brightness(0); }
    .why-content h5 { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; color: #f0ede8; margin-bottom: 10px; }
    .why-content p { color: var(--text2); font-size: 0.9rem; line-height: 1.6; }

    /* ── CTA SECTION ── */
    .about-cta {
        padding: 100px 0;
        background: linear-gradient(135deg, rgba(24,18,10,0.98), rgba(42,31,13,0.95));
        position: relative;
        overflow: hidden;
    }
    .about-cta::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%232563eb' fill-opacity='0.03' fill-rule='evenodd'/%3E%3C/svg%3E");
    }
    .about-cta-content { text-align: center; max-width: 700px; margin: 0 auto; position: relative; z-index: 1; }
    .about-cta h2 { font-family: 'Cormorant Garamond', serif; font-size: 3rem; font-weight: 400; color: #f0ede8; margin-bottom: 20px; }
    .about-cta h2 span { color: #2563eb; font-style: italic; }
    .about-cta p { color: rgba(240,237,232,0.7); font-size: 1.1rem; margin-bottom: 40px; line-height: 1.7; }
    .about-cta-btns { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
    .btn-blue { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 16px 40px; border-radius: 50px; font-weight: 600; font-size: 0.95rem; border: none; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 10px; text-decoration: none; }
    .btn-blue:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(37,99,235,0.4); }
    .btn-outline { background: transparent; color: #f0ede8; padding: 16px 40px; border-radius: 50px; font-weight: 600; font-size: 0.95rem; border: 2px solid rgba(37,99,235,0.4); cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 10px; text-decoration: none; }
    .btn-outline:hover { background: rgba(37,99,235,0.1); border-color: #2563eb; transform: translateY(-3px); }

    @media (max-width: 1100px) {
        .team-grid { grid-template-columns: repeat(2, 1fr); }
        .about-hero-content { grid-template-columns: 1fr; gap: 50px; text-align: center; }
        .about-hero-text p { max-width: 100%; }
        .about-hero-stats { justify-content: center; }
    }
    @media (max-width: 900px) {
        .about-wrap { grid-template-columns: 1fr; gap: 40px; }
        .mission-grid { grid-template-columns: 1fr; }
        .timeline-container { padding: 0 20px; }
        .timeline-line { left: 20px; }
        .timeline-item, .timeline-item:nth-child(even) { flex-direction: column; align-items: flex-start; padding-left: 50px; }
        .timeline-item:nth-child(even) .timeline-content { text-align: left; padding: 0; }
        .timeline-dot { left: 20px; }
        .timeline-spacer { display: none; }
        .team-grid { grid-template-columns: 1fr; }
        .why-grid { grid-template-columns: 1fr; }
        .about-hero-text h1 { font-size: 2.5rem; }
        .about-cta h2 { font-size: 2rem; }
    }

    /* ═══════════════════════════════════════════════════════════
       APPLE-STYLE 3D CAR LISTING - BUTTER SMOOTH ANIMATIONS
    ═══════════════════════════════════════════════════════════ */
    
    /* Perspective Container for 3D Effects */
    .perspective-container {
        perspective: 2000px;
        perspective-origin: center center;
    }
    
    /* 3D Car Grid */
    .listing-section {
        background: var(--bg2);
        position: relative;
        overflow: hidden;
    }
    
    /* Floating Gradient Orbs Background */
    .listing-section::before,
    .listing-section::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        filter: blur(100px);
        opacity: 0.4;
        pointer-events: none;
        animation: floatOrb 20s ease-in-out infinite;
    }
    .listing-section::before {
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
        top: -200px;
        left: -200px;
        animation-delay: 0s;
    }
    .listing-section::after {
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(139, 92, 246, 0.6) 0%, transparent 70%);
        bottom: -150px;
        right: -150px;
        animation-delay: -10s;
    }
    @keyframes floatOrb {
        0%, 100% { transform: translate(0, 0) scale(1); }
        25% { transform: translate(50px, 30px) scale(1.1); }
        50% { transform: translate(-30px, -20px) scale(0.95); }
        75% { transform: translate(20px, -40px) scale(1.05); }
    }
    
    /* Apple-Style Filter Bar */
    .filter-bar {
        display: flex;
        gap: 14px;
        align-items: center;
        flex-wrap: wrap;
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 60px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }
    .filter-bar select {
        padding: 14px 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: var(--text);
        font-size: 0.9rem;
        font-family: 'DM Sans', sans-serif;
        font-weight: 500;
        outline: none;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-width: 180px;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%238892a4' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        padding-right: 44px;
    }
    .filter-bar select:hover {
        background-color: rgba(255, 255, 255, 0.1);
        border-color: var(--accent);
    }
    .filter-bar select:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(79, 142, 247, 0.15);
    }
    .filter-bar select option {
        background: var(--bg);
        color: var(--text);
        padding: 12px;
    }
    
    /* Apple-Style 3D Car Grid */
    .car-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 32px;
        position: relative;
        z-index: 1;
    }
    
    /* 3D Car Card with Apple Design */
    .car-card {
        background: rgba(255, 255, 255, 0.03);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        overflow: hidden;
        position: relative;
        transform-style: preserve-3d;
        transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
        cursor: pointer;
        perspective: 1000px;
    }
    
    /* Card Glow Effect */
    .car-card::before {
        content: '';
        position: absolute;
        inset: -1px;
        border-radius: 25px;
        background: linear-gradient(135deg, var(--accent), transparent 50%, rgba(139, 92, 246, 0.5));
        opacity: 0;
        transition: opacity 0.5s ease;
        z-index: -1;
    }
    
    /* Card Shine Animation */
    .car-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.8s ease;
        z-index: 1;
        pointer-events: none;
    }
    .car-card:hover::after {
        left: 100%;
    }
    
    .car-card:hover {
        transform: translateY(-16px) rotateX(2deg);
        box-shadow: 
            0 30px 60px rgba(0, 0, 0, 0.4),
            0 0 0 1px rgba(79, 142, 247, 0.2),
            inset 0 1px 0 rgba(255, 255, 255, 0.1);
        border-color: rgba(79, 142, 247, 0.3);
    }
    .car-card:hover::before {
        opacity: 1;
    }
    
    /* 3D Image Box */
    .car-img-box {
        position: relative;
        aspect-ratio: 16/10;
        overflow: hidden;
        transform-style: preserve-3d;
        perspective: 800px;
    }
    .car-img-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        transform: scale(1.05);
    }
    .car-card:hover .car-img-box img {
        transform: scale(1.15) translateZ(30px);
    }
    
    /* Parallax Image Effect */
    .car-img-box::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.2) 50%, transparent 100%);
        z-index: 1;
        transition: opacity 0.5s ease;
    }
    
    /* Floating Badge */
    .car-type-badge {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 8px 16px;
        border-radius: 30px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        z-index: 2;
        transform: translateY(-20px);
        opacity: 0;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .car-card:hover .car-type-badge {
        transform: translateY(0);
        opacity: 1;
    }
    
    /* Floating Features Tags */
    .car-features {
        position: absolute;
        bottom: 20px;
        left: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        z-index: 2;
        transform: translateY(20px);
        opacity: 0;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s;
    }
    .car-card:hover .car-features {
        transform: translateY(0);
        opacity: 1;
    }
    .car-features span {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.95);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        padding: 8px 14px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
    }
    .car-features span:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-3px);
    }
    
    /* View Details Hint */
    .img-view-hint {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        opacity: 0;
        transition: all 0.4s ease;
        z-index: 3;
    }
    .car-img-box:hover .img-view-hint {
        opacity: 1;
    }
    .img-view-hint span {
        background: rgba(255, 255, 255, 0.95);
        color: #0d1117;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 14px 28px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        gap: 10px;
        transform: scale(0.8);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .car-img-box:hover .img-view-hint span {
        transform: scale(1);
    }
    
    /* 3D Card Body */
    .car-body {
        padding: 28px 32px 32px;
        position: relative;
        transform-style: preserve-3d;
    }
    
    /* Card Title with 3D Text Effect */
    .car-title {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 12px;
    }
    .car-title h3 {
        font-family: 'Cormorant Garamond', serif;
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text);
        line-height: 1.2;
        transition: all 0.3s ease;
        transform: translateZ(0);
    }
    .car-card:hover .car-title h3 {
        color: var(--accent);
        text-shadow: 0 0 30px rgba(79, 142, 247, 0.3);
    }
    
    /* Apple-Style Price Tag */
    .price {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--accent);
        white-space: nowrap;
        background: linear-gradient(135deg, rgba(79, 142, 247, 0.15), rgba(79, 142, 247, 0.08));
        border: 1px solid rgba(79, 142, 247, 0.3);
        padding: 8px 16px;
        border-radius: 20px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .price::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(79, 142, 247, 0.2), transparent);
        transition: left 0.6s ease;
    }
    .car-card:hover .price::before {
        left: 100%;
    }
    
    .car-body > p {
        font-size: 0.88rem;
        color: var(--text2);
        line-height: 1.7;
        margin-bottom: 24px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    /* Apple-Style Book Button */
    .car-body .btn {
        width: 100%;
        justify-content: center;
        position: relative;
        overflow: hidden;
        border-radius: 14px;
        font-weight: 700;
        letter-spacing: 0.05em;
    }
    .car-body .btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: all 0.6s ease;
    }
    .car-body .btn:hover::before {
        width: 400px;
        height: 400px;
    }
    .car-body .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 40px rgba(79, 142, 247, 0.4);
    }
    .car-body .btn:active {
        transform: translateY(-1px) scale(0.98);
    }
    
    /* ═══════════════════════════════════════════════════════════
       SCROLL REVEAL ANIMATIONS - APPLE STYLE
    ═══════════════════════════════════════════════════════════ */
    
    /* Reveal Base Styles */
    .reveal {
        opacity: 0;
        transform: translateY(60px);
        transition: all 1s cubic-bezier(0.23, 1, 0.32, 1);
    }
    .reveal.visible {
        opacity: 1;
        transform: translateY(0);
    }
    
    /* Reveal with Scale */
    .reveal-scale {
        opacity: 0;
        transform: scale(0.9) translateY(40px);
        transition: all 1s cubic-bezier(0.23, 1, 0.32, 1);
    }
    .reveal-scale.visible {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
    
    /* Staggered Animation for Grid Items */
    .car-grid .car-card {
        opacity: 0;
        transform: translateY(80px) scale(0.95);
        transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
    }
    .car-grid .car-card.reveal {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    
    /* Currently Booked Section Styles */
    #bookedCarGrid {
        opacity: 0.9;
    }
    #bookedCarGrid .car-card {
        border-color: rgba(251, 191, 36, 0.15);
    }
    #bookedCarGrid .car-card:hover {
        border-color: rgba(251, 191, 36, 0.3);
        transform: translateY(-4px) scale(1.01);
    }
    #bookedCarGrid .car-img-box img {
        filter: grayscale(30%);
        transition: filter 0.3s ease;
    }
    #bookedCarGrid .car-card:hover .car-img-box img {
        filter: grayscale(50%);
    }
    
    /* Hero Parallax Scroll Effect */
    .hero {
        transform: translateZ(0);
        will-change: transform;
    }
    
    /* Smooth Scroll Behavior */
    html {
        scroll-behavior: smooth;
        scrollbar-width: thin;
        scrollbar-color: var(--accent) var(--bg);
    }
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 10px;
    }
    ::-webkit-scrollbar-track {
        background: var(--bg);
    }
    ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, var(--accent), rgba(79, 142, 247, 0.5));
        border-radius: 5px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--accent);
    }
    
    /* ═══════════════════════════════════════════════════════════
       3D FLOATING ELEMENTS
    ═══════════════════════════════════════════════════════════ */
    
    /* Floating Car Icons */
    .floating-car-icon {
        position: absolute;
        font-size: 120px;
        color: rgba(79, 142, 247, 0.05);
        animation: floatCarIcon 15s ease-in-out infinite;
        pointer-events: none;
        z-index: 0;
    }
    @keyframes floatCarIcon {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        25% { transform: translateY(-30px) rotate(5deg); }
        50% { transform: translateY(-15px) rotate(-3deg); }
        75% { transform: translateY(-40px) rotate(2deg); }
    }
    
    /* Glassmorphism Cards */
    .glass-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
    }
    .glass-card:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(79, 142, 247, 0.3);
        transform: translateY(-8px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }
    
    /* Loading Skeleton Animation */
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    .skeleton {
        background: linear-gradient(90deg, var(--surface) 25%, var(--surface2) 50%, var(--surface) 75%);
        background-size: 200% 100%;
        animation: shimmer 2s infinite;
    }
    
    /* ═══════════════════════════════════════════════════════════
       SMOOTH PAGE TRANSITIONS
    ═══════════════════════════════════════════════════════════ */
    
    .page-view {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
    }
    .page-view.active {
        opacity: 1;
        transform: translateY(0);
    }
    
    /* ═══════════════════════════════════════════════════════════
       GLOWING PULSE ANIMATION
    ═══════════════════════════════════════════════════════════ */
    
    @keyframes glowPulse {
        0%, 100% { box-shadow: 0 0 20px rgba(79, 142, 247, 0.3); }
        50% { box-shadow: 0 0 40px rgba(79, 142, 247, 0.6); }
    }
    .glow-pulse {
        animation: glowPulse 3s ease-in-out infinite;
    }
    
    /* ═══════════════════════════════════════════════════════════
       SMOOTH COUNTER ANIMATION
    ═══════════════════════════════════════════════════════════ */
    
    .counter {
        display: inline-block;
        transition: all 0.3s ease;
    }
    
    /* ═══════════════════════════════════════════════════════════
       ADVANCED CAROUSEL STYLES
    ═══════════════════════════════════════════════════════════ */
    
    .testimonial-scroll {
        display: flex;
        gap: 24px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        padding: 20px 0;
        scrollbar-width: none;
    }
    .testimonial-scroll::-webkit-scrollbar {
        display: none;
    }
    .testimonial-card {
        scroll-snap-align: start;
        flex-shrink: 0;
    }
    
    /* ═══════════════════════════════════════════════════════════
       RESPONSIVE 3D EFFECTS
    ═══════════════════════════════════════════════════════════ */
    
    @media (max-width: 1024px) {
        .features-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .testimonial-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .car-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }
    
    @media (max-width: 768px) {
        .car-grid {
            grid-template-columns: 1fr !important;
            gap: 20px;
        }
        .filter-bar {
            padding: 16px 20px;
            border-radius: 16px;
            flex-direction: column;
        }
        .filter-bar select {
            flex: 1;
            min-width: 100%;
        }
        .car-card:hover {
            transform: translateY(-8px);
        }
        .car-card:hover .car-img-box img {
            transform: scale(1.08);
        }
        .car-type-badge,
        .car-features {
            opacity: 1;
            transform: translateY(0);
        }
        .features-grid {
            grid-template-columns: 1fr !important;
        }
        .testimonial-grid {
            grid-template-columns: 1fr !important;
        }
        .section-padding {
            padding: 80px 0;
        }
        .hero-content h1 {
            font-size: 3rem !important;
        }
        .hero-stats {
            flex-direction: column;
            gap: 24px;
            margin-top: 40px;
        }
        .scroll-indicator {
            display: none;
        }
        .section-header h2 {
            font-size: 2rem !important;
        }
    }

    .contact-container { display: grid; grid-template-columns: 1fr 1.6fr; gap: 40px; background: var(--surface); border: 1px solid var(--border); border-radius: 20px; overflow: hidden; }
    .contact-info { background: linear-gradient(160deg, #18120a, #2a1f0d); padding: 56px 44px; position: relative; overflow: hidden; }
    .contact-info::before { content: ''; position: absolute; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%); bottom: -100px; right: -100px; pointer-events: none; }
    .contact-info h3 { font-family: 'Cormorant Garamond', serif; font-size: 2rem; font-weight: 400; color: #f0ede8; margin-bottom: 14px; }
    .contact-info > p { color: rgba(240,237,232,0.6); line-height: 1.7; margin-bottom: 40px; font-size: 0.9rem; }
    .info-item { display: flex; gap: 16px; align-items: flex-start; margin-bottom: 28px; }
    .info-item i { width: 42px; height: 42px; flex-shrink: 0; background: rgba(201,168,76,0.15); border: 1px solid rgba(201,168,76,0.3); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--gold); font-size: 0.9rem; }
    .info-item h4 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(240,237,232,0.5); margin-bottom: 4px; }
    .info-item p { color: #f0ede8; font-size: 0.9rem; }
    .social-links { display: flex; gap: 12px; margin-top: 40px; }
    .social-links a { width: 40px; height: 40px; border-radius: 50%; border: 1px solid rgba(201,168,76,0.3); color: rgba(240,237,232,0.7); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; transition: all 0.25s; }
    .social-links a:hover { background: var(--gold); color: #0a0a0b; border-color: var(--gold); }
    .contact-form { padding: 56px 44px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--text3); margin-bottom: 8px; }
    .form-group input, .form-group textarea { width: 100%; padding: 12px 16px; background: var(--surface2); border: 1px solid var(--border2); border-radius: 8px; color: var(--text); font-size: 0.9rem; font-family: 'DM Sans', sans-serif; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
    .form-group textarea { resize: vertical; min-height: 120px; }
    .contact-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    footer { background: var(--bg2); border-top: 1px solid var(--border); padding: 60px 0 32px; }
    .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 60px; margin-bottom: 48px; }
    .footer-col .logo h2 { font-size: 1.5rem; }
    .footer-col > p { margin-top: 16px; font-size: 0.88rem; color: var(--text2); line-height: 1.7; max-width: 280px; }
    .footer-col h4 { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.15em; text-transform: uppercase; color: var(--text3); margin-bottom: 20px; }
    .footer-col ul { list-style: none; }
    .footer-col ul li { margin-bottom: 12px; }
    .footer-col ul li a { font-size: 0.88rem; color: var(--text2); transition: color 0.2s; display: flex; align-items: center; gap: 8px; }
    .footer-col ul li a::before { content: '\2192'; font-size: 0.7rem; color: var(--accent); opacity: 0; transform: translateX(-6px); transition: all 0.2s; }
    .footer-col ul li a:hover { color: var(--accent); }
    .footer-col ul li a:hover::before { opacity: 1; transform: translateX(0); }
    .footer-bottom { border-top: 1px solid var(--border); padding-top: 28px; display: flex; justify-content: space-between; align-items: center; }
    .footer-bottom p { font-size: 0.82rem; color: var(--text3); }

    /* ═══════════════════════════════════════════════════════════
       ADVANCED CHATBOT STYLES - GLASSMORPHISM DESIGN
    ═══════════════════════════════════════════════════════════ */
    
    /* Floating Particles Background */
    .chat-particles {
        position: absolute;
        inset: 0;
        overflow: hidden;
        pointer-events: none;
        z-index: 0;
    }
    .chat-particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: var(--gold);
        border-radius: 50%;
        opacity: 0.3;
        animation: floatParticle 8s infinite ease-in-out;
    }
    @keyframes floatParticle {
        0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
        25% { transform: translateY(-30px) translateX(10px); opacity: 0.6; }
        50% { transform: translateY(-15px) translateX(-10px); opacity: 0.4; }
        75% { transform: translateY(-40px) translateX(5px); opacity: 0.5; }
    }

    /* Chat Button - Pulsing Glow Effect */
    #chatBtn {
        position: fixed;
        bottom: 28px;
        right: 28px;
        z-index: 9999;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), #a8722a);
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 24px var(--accent-glow), 0 0 0 0 var(--accent-glow);
        animation: chatPulse 3s ease infinite;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: visible;
    }
    #chatBtn::before {
        content: '';
        position: absolute;
        inset: -3px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), #a8722a);
        z-index: -1;
        opacity: 0;
        animation: ringPulse 3s ease infinite;
    }
    @keyframes ringPulse {
        0%, 100% { transform: scale(1); opacity: 0; }
        50% { transform: scale(1.3); opacity: 0.3; }
    }
    @keyframes chatPulse {
        0%, 100% { box-shadow: 0 4px 24px var(--accent-glow), 0 0 0 0 var(--accent-glow); }
        50% { box-shadow: 0 4px 24px var(--accent-glow), 0 0 0 15px rgba(201,168,76,0); }
    }
    #chatBtn:hover {
        transform: scale(1.15) rotate(10deg);
        animation: none;
        box-shadow: 0 8px 40px var(--accent-glow);
    }
    #chatBtn i { color: #0a0a0b; font-size: 1.5rem; transition: transform 0.3s; }
    #chatBtn:hover i { transform: scale(1.1); }
    #chatBtn .badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        font-size: 0.65rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        border: 2px solid var(--bg);
        animation: badgeBounce 2s ease infinite;
    }
    @keyframes badgeBounce {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    #chatBtn.hasMessages .badge { animation: badgeShake 0.5s ease; }
    @keyframes badgeShake {
        0%, 100% { transform: rotate(0); }
        25% { transform: rotate(-10deg); }
        75% { transform: rotate(10deg); }
    }

    /* Chat Window - Glassmorphism */
    #chatWindow {
        position: fixed;
        bottom: 100px;
        right: 28px;
        z-index: 9998;
        width: 390px;
        max-height: 580px;
        background: rgba(26, 24, 20, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(201, 168, 76, 0.2);
        border-radius: 24px;
        box-shadow: 0 25px 80px rgba(0,0,0,0.5), 0 0 40px rgba(201,168,76,0.1), inset 0 1px 0 rgba(255,255,255,0.05);
        display: none;
        flex-direction: column;
        overflow: hidden;
        transform-origin: bottom right;
    }
    #chatWindow.open {
        display: flex;
        animation: chatWindowIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes chatWindowIn {
        0% { opacity: 0; transform: scale(0.8) translateY(20px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }
    #chatWindow.closing {
        animation: chatWindowOut 0.3s ease forwards;
    }
    @keyframes chatWindowOut {
        0% { opacity: 1; transform: scale(1) translateY(0); }
        100% { opacity: 0; transform: scale(0.8) translateY(20px); }
    }

    /* Animated Gradient Border */
    #chatWindow::before {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 26px;
        background: linear-gradient(135deg, var(--gold), transparent, var(--gold), transparent);
        background-size: 300% 300%;
        animation: gradientBorder 4s ease infinite;
        z-index: -1;
        opacity: 0.5;
    }
    @keyframes gradientBorder {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Chat Header */
    .chat-header {
        background: linear-gradient(135deg, rgba(24, 18, 10, 0.95), rgba(42, 31, 13, 0.9));
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid rgba(201,168,76,0.15);
        position: relative;
        z-index: 2;
    }
    .chat-avatar-container { position: relative; }
    .chat-avatar {
        width: 46px;
        height: 46px;
        background: linear-gradient(135deg, var(--gold-dim), rgba(201,168,76,0.2));
        border: 2px solid rgba(201,168,76,0.4);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: var(--gold);
        position: relative;
        overflow: hidden;
    }
    .chat-avatar::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.1));
        border-radius: 50%;
    }
    .chat-avatar .status-ring {
        position: absolute;
        inset: -3px;
        border: 2px solid var(--gold);
        border-radius: 50%;
        animation: statusPulse 2s ease infinite;
    }
    @keyframes statusPulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.15); opacity: 0.5; }
    }
    .chat-header-info { flex: 1; }
    .chat-header-info h4 {
        color: #f0ede8;
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 2px;
    }
    .chat-header-info span {
        color: rgba(240,237,232,0.6);
        font-size: 0.72rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .online-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
        animation: blink 2s ease infinite;
    }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    .typing-indicator-header {
        display: none;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        color: var(--gold);
    }
    .typing-indicator-header.active { display: flex; }
    .typing-indicator-header span {
        width: 4px;
        height: 4px;
        background: var(--gold);
        border-radius: 50%;
        animation: typingBounce 1.4s infinite;
    }
    .typing-indicator-header span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator-header span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typingBounce { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-4px); } }
    
    .chat-actions { display: flex; gap: 8px; margin-left: auto; }
    .chat-action-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        color: rgba(240,237,232,0.6);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        font-size: 0.8rem;
    }
    .chat-action-btn:hover { background: rgba(255,255,255,0.1); color: #f0ede8; transform: scale(1.1); }
    .chat-close {
        background: none;
        border: none;
        color: rgba(240,237,232,0.5);
        cursor: pointer;
        font-size: 1rem;
        padding: 4px;
        margin-left: 4px;
        transition: all 0.2s;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .chat-close:hover { color: #f0ede8; background: rgba(255,255,255,0.1); transform: rotate(90deg); }

    /* Chat Messages Container */
    #chatMessages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        background: linear-gradient(180deg, rgba(15, 14, 12, 0.8) 0%, rgba(20, 18, 15, 0.9) 100%);
        position: relative;
    }
    #chatMessages::-webkit-scrollbar { width: 4px; }
    #chatMessages::-webkit-scrollbar-track { background: transparent; }
    #chatMessages::-webkit-scrollbar-thumb { background: rgba(201,168,76,0.3); border-radius: 4px; }
    #chatMessages::-webkit-scrollbar-thumb:hover { background: rgba(201,168,76,0.5); }

    /* Date Separator */
    .date-separator {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 8px 0;
        color: rgba(240,237,232,0.4);
        font-size: 0.7rem;
    }
    .date-separator::before, .date-separator::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(201,168,76,0.2), transparent);
    }

    /* Message Styles */
    .msg {
        display: flex;
        gap: 10px;
        align-items: flex-end;
        max-width: 85%;
        animation: msgSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes msgSlideIn {
        0% { opacity: 0; transform: translateY(10px) scale(0.95); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }
    .msg.bot { align-self: flex-start; }
    .msg.user { align-self: flex-end; flex-direction: row-reverse; }

    .msg-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        position: relative;
        transition: transform 0.2s;
    }
    .msg-avatar:hover { transform: scale(1.1); }
    .msg.bot .msg-avatar {
        background: linear-gradient(135deg, var(--gold-dim), rgba(201,168,76,0.15));
        color: var(--gold);
        box-shadow: 0 2px 10px rgba(201,168,76,0.2);
    }
    .msg.user .msg-avatar {
        background: linear-gradient(135deg, rgba(100,116,139,0.3), rgba(100,116,139,0.15));
        color: #94a3b8;
    }

    .msg-content { display: flex; flex-direction: column; gap: 4px; }
    .msg.user .msg-content { align-items: flex-end; }
    
    .msg-bubble {
        padding: 12px 16px;
        border-radius: 18px;
        font-size: 0.875rem;
        line-height: 1.5;
        max-width: 260px;
        word-wrap: break-word;
        position: relative;
        transition: all 0.2s;
    }
    .msg.bot .msg-bubble {
        background: linear-gradient(135deg, rgba(40, 35, 28, 0.95), rgba(30, 26, 20, 0.9));
        color: #e8e4dc;
        border-bottom-left-radius: 6px;
        border: 1px solid rgba(201,168,76,0.1);
        box-shadow: 0 2px 12px rgba(0,0,0,0.2);
    }
    .msg.bot .msg-bubble:hover { border-color: rgba(201,168,76,0.2); }
    .msg.user .msg-bubble {
        background: linear-gradient(135deg, var(--gold), #b8862e);
        color: #0a0a0b;
        border-bottom-right-radius: 6px;
        font-weight: 500;
        box-shadow: 0 4px 16px rgba(201,168,76,0.3);
    }
    .msg.user .msg-bubble:hover { box-shadow: 0 6px 20px rgba(201,168,76,0.4); }

    .msg-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.65rem;
        color: rgba(240,237,232,0.4);
        padding: 0 4px;
    }
    .msg.user .msg-meta { flex-direction: row-reverse; }
    .msg-time { }
    .msg-status { font-size: 0.6rem; }
    .msg-status.sent { color: rgba(34,197,94,0.6); }
    .msg-status.delivered { color: rgba(34,197,94,0.8); }
    .msg-status.read { color: #22c55e; }

    /* Car Card in Message */
    .msg-car-card {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(201,168,76,0.15);
        border-radius: 12px;
        padding: 10px;
        margin-top: 8px;
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .msg-car-card .car-icon { font-size: 1.5rem; }
    .msg-car-card .car-info { flex: 1; }
    .msg-car-card .car-name { font-weight: 600; color: var(--gold); font-size: 0.85rem; }
    .msg-car-card .car-price { color: #e8e4dc; font-size: 0.8rem; }
    .msg-car-card .car-badge { 
        background: var(--gold-dim); 
        color: var(--gold); 
        padding: 2px 8px; 
        border-radius: 10px; 
        font-size: 0.65rem;
        font-weight: 600;
    }

    /* Quick Reply Tags */
    .quick-replies { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 8px; 
        padding: 12px 14px; 
        background: rgba(20, 18, 15, 0.8);
        border-top: 1px solid rgba(201,168,76,0.1);
    }
    .quick-replies-label {
        width: 100%;
        font-size: 0.7rem;
        color: rgba(240,237,232,0.4);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .quick-replies-label::before { content: '✨'; }
    .qr-btn {
        background: linear-gradient(135deg, rgba(40,35,28,0.9), rgba(30,26,20,0.8));
        border: 1px solid rgba(201,168,76,0.2);
        border-radius: 20px;
        padding: 8px 14px;
        font-size: 0.78rem;
        color: #e8e4dc;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        white-space: nowrap;
        font-family: 'DM Sans', sans-serif;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .qr-btn:hover {
        background: linear-gradient(135deg, var(--gold-dim), rgba(201,168,76,0.2));
        border-color: var(--gold);
        color: var(--gold);
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 4px 12px rgba(201,168,76,0.2);
    }
    .qr-btn:active { transform: translateY(0) scale(0.98); }
    .qr-btn .qr-icon { font-size: 0.9rem; }

    /* Chat Input Area */
    .chat-input-area {
        padding: 12px 14px;
        border-top: 1px solid rgba(201,168,76,0.1);
        display: flex;
        gap: 10px;
        align-items: center;
        background: rgba(26, 24, 20, 0.95);
        position: relative;
        z-index: 2;
    }
    .chat-input-wrapper {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;
    }
    #chatInput {
        width: 100%;
        border: 1px solid rgba(201,168,76,0.2);
        border-radius: 24px;
        padding: 10px 40px 10px 16px;
        font-size: 0.875rem;
        outline: none;
        transition: all 0.3s;
        background: rgba(20, 18, 15, 0.9);
        color: #e8e4dc;
        font-family: 'DM Sans', sans-serif;
    }
    #chatInput:focus {
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(201,168,76,0.1);
        background: rgba(25, 22, 18, 0.95);
    }
    #chatInput::placeholder { color: rgba(240,237,232,0.35); }
    .input-voice-btn {
        position: absolute;
        right: 8px;
        background: none;
        border: none;
        color: rgba(240,237,232,0.4);
        cursor: pointer;
        padding: 4px;
        transition: all 0.2s;
        border-radius: 50%;
        font-size: 0.9rem;
    }
    .input-voice-btn:hover { color: var(--gold); background: rgba(201,168,76,0.1); }
    .input-voice-btn.recording { color: #ef4444; animation: recordPulse 1s ease infinite; }
    @keyframes recordPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.2); } }
    
    #chatSend {
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, var(--gold), #a8722a);
        border: none;
        border-radius: 50%;
        color: #0a0a0b;
        cursor: pointer;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
    }
    #chatSend::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
        opacity: 0;
        transition: opacity 0.2s;
    }
    #chatSend:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 20px var(--accent-glow);
    }
    #chatSend:hover::before { opacity: 1; }
    #chatSend:active { transform: scale(0.95); }
    #chatSend:disabled {
        background: rgba(100,116,139,0.3);
        color: rgba(240,237,232,0.3);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    #chatSend.sending { animation: sendPulse 0.6s ease; }
    @keyframes sendPulse { 0% { transform: scale(1); } 50% { transform: scale(0.9); } 100% { transform: scale(1); } }

    /* Typing Indicator - 3D Dots */
    .typing .msg-bubble { padding: 14px 18px; }
    .typing-dots { display: flex; gap: 5px; align-items: center; height: 20px; }
    .typing-dots span {
        width: 8px;
        height: 8px;
        background: linear-gradient(135deg, var(--gold), #b8862e);
        border-radius: 50%;
        animation: typingWave 1.4s infinite ease-in-out;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .typing-dots span:nth-child(1) { animation-delay: 0s; }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typingWave {
        0%, 60%, 100% { transform: translateY(0) scale(1); opacity: 0.5; }
        30% { transform: translateY(-8px) scale(1.2); opacity: 1; }
    }

    /* Suggested Actions */
    .suggested-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .action-btn {
        background: linear-gradient(135deg, rgba(201,168,76,0.15), rgba(201,168,76,0.05));
        border: 1px solid rgba(201,168,76,0.3);
        border-radius: 12px;
        padding: 6px 12px;
        font-size: 0.75rem;
        color: var(--gold);
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .action-btn:hover {
        background: var(--gold-dim);
        border-color: var(--gold);
        transform: translateY(-2px);
    }

    /* Welcome Message Animation */
    .welcome-msg {
        text-align: center;
        padding: 20px;
        animation: fadeInUp 0.6s ease;
    }
    @keyframes fadeInUp {
        0% { opacity: 0; transform: translateY(20px); }
        100% { opacity: 1; transform: translateY(0); }
    }
    .welcome-msg .welcome-icon {
        font-size: 3rem;
        margin-bottom: 12px;
        animation: welcomeBounce 2s ease infinite;
    }
    @keyframes welcomeBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    .welcome-msg h3 { color: #f0ede8; font-size: 1rem; margin-bottom: 6px; }
    .welcome-msg p { color: rgba(240,237,232,0.5); font-size: 0.8rem; }

    /* Toast Notification */
    .chat-toast {
        position: fixed;
        bottom: 100px;
        right: 28px;
        background: linear-gradient(135deg, rgba(40,35,28,0.95), rgba(30,26,20,0.95));
        border: 1px solid rgba(201,168,76,0.3);
        border-radius: 12px;
        padding: 12px 16px;
        color: #e8e4dc;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 10000;
        animation: toastIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    }
    .chat-toast.hiding { animation: toastOut 0.3s ease forwards; }
    @keyframes toastIn {
        0% { opacity: 0; transform: translateX(100px); }
        100% { opacity: 1; transform: translateX(0); }
    }
    @keyframes toastOut {
        0% { opacity: 1; transform: translateX(0); }
        100% { opacity: 0; transform: translateX(100px); }
    }

    /* ── FIX: reveal starts visible; JS adds .visible to ensure it ── */
    .reveal { opacity: 1; transform: translateY(0); transition: opacity 0.7s ease, transform 0.7s ease; }
    .reveal.hidden { opacity: 0; transform: translateY(30px); }
    .reveal.visible { opacity: 1 !important; transform: translateY(0) !important; }
    .reveal-delay-1 { transition-delay: 0.1s; }
    .reveal-delay-2 { transition-delay: 0.2s; }
    .reveal-delay-3 { transition-delay: 0.3s; }

    #pageLoader { position: fixed; inset: 0; z-index: 99999; background: #0a0a0b; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 20px; transition: opacity 0.5s ease; }
    #pageLoader.hidden { opacity: 0; pointer-events: none; }
    .loader-logo { font-family: 'Cormorant Garamond', serif; font-size: 2.5rem; font-weight: 300; color: #f0ede8; letter-spacing: 0.05em; }
    .loader-logo span { color: var(--gold); font-style: italic; }
    .loader-bar-wrap { width: 180px; height: 2px; background: rgba(255,255,255,0.08); border-radius: 2px; overflow: hidden; }
    .loader-bar { height: 100%; background: linear-gradient(90deg, var(--gold), var(--gold-light)); border-radius: 2px; animation: loadBar 1.4s ease forwards; }
    @keyframes loadBar { from { width:0; } to { width:100%; } }

    @media (max-width: 900px) {
        .container { padding: 0 20px; }
        .mobile-menu-btn { display: flex; }
        .nav-links { position: fixed; top: 72px; left: 0; right: 0; background: var(--nav-bg); backdrop-filter: blur(20px); flex-direction: column; padding: 20px; gap: 4px; border-bottom: 1px solid var(--border); transform: translateY(-100%); opacity: 0; transition: all 0.35s ease; pointer-events: none; }
        .nav-links.open { transform: translateY(0); opacity: 1; pointer-events: all; }
        .nav-links li a { display: block; padding: 12px 16px; }
        .auth-controls { display: none; } .mobile-auth { display: block; }
        .nav-links li.mobile-auth a { color: var(--accent); font-weight: 600; border: 1px solid var(--accent); border-radius: 6px; margin-top: 8px; text-align: center; justify-content: center; }
        .hero-content h1 { font-size: 3rem; } .hero-stats { gap: 28px; }
        .features-grid, .testimonial-grid { grid-template-columns: 1fr; }
        .about-wrap { grid-template-columns: 1fr; gap: 40px; }
        .contact-container { grid-template-columns: 1fr; }
        .footer-grid { grid-template-columns: 1fr; gap: 36px; }
        .car-grid { grid-template-columns: 1fr; }
        #chatWindow { width: calc(100vw - 20px); right: 10px; bottom: 90px; }
    }
    .gray-bg { background: var(--bg2); }
    .counter { display: inline-block; }
    </style>
</head>
<body>

<div id="pageLoader">
    <div class="loader-logo">Car<span>ForYou</span></div>
    <div class="loader-bar-wrap"><div class="loader-bar"></div></div>
</div>

<header id="siteHeader">
    <div class="container">
        <nav>
            <div class="logo">
                <a href="javascript:void(0)" onclick="showPage('home')" style="cursor:pointer;text-decoration:none;">
                    <h2>Car<span>ForYou</span></h2>
                </a>
            </div>
            <ul class="nav-links" id="navLinks">
                <li><a href="javascript:void(0)" onclick="showPage('home')" id="link-home">Home</a></li>
                <li><a href="javascript:void(0)" onclick="showPage('about')" id="link-about">About Us</a></li>
                <li><a href="javascript:void(0)" onclick="showPage('listing')" id="link-listing">Car Listing</a></li>
                <li><a href="javascript:void(0)" onclick="showPage('contact')" id="link-contact">Contact Us</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="mobile-auth"><a href="users/car_dashboard.php"><i class="fa fa-table-columns"></i> My Dashboard</a></li>
                <li class="mobile-auth"><a href="users/logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
                <?php else: ?>
                <li class="mobile-auth"><a href="users/login.php"><i class="fa fa-sign-in"></i> Login / Register</a></li>
                <?php endif; ?>
            </ul>
            <div class="auth-controls">
                <?php if (isset($_SESSION['user_id'])):
                    $nav_name    = htmlspecialchars($_SESSION['user_name'] ?? 'User');
                    $nav_initial = strtoupper(substr($nav_name, 0, 1));
                ?>
                    <div class="user-pill">
                        <div class="user-pill-avatar"><?php echo $nav_initial; ?></div>
                        <div class="user-pill-info">
                            <span class="user-pill-label">Welcome back</span>
                            <span class="user-pill-name"><?php echo $nav_name; ?></span>
                        </div>
                        <div class="user-pill-divider"></div>
                        <a href="users/car_dashboard.php" class="user-pill-action" title="Dashboard"><i class="fa fa-table-columns"></i></a>
                        <a href="users/logout.php" class="user-pill-action user-pill-logout" title="Logout"><i class="fa fa-arrow-right-from-bracket"></i></a>
                    </div>
                <?php else: ?>
                    <a href="users/login.php" class="btn-login"><i class="fa fa-sign-in"></i> Login / Register</a>
                <?php endif; ?>
            </div>
            <button class="theme-toggle" id="themeToggle" title="Toggle day/night mode">
                <i class="fa fa-moon" id="themeIcon"></i>
            </button>
            <div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fa fa-bars"></i></div>
        </nav>
    </div>
</header>

<!-- HOME PAGE -->
<div id="page-home" class="page-view active">

    <section class="hero">
        <!-- Parallax Background -->
        <div class="parallax-bg">
            <!-- 3D Glow Orbs -->
            <div class="glow-orb"></div>
            <div class="glow-orb"></div>
            <div class="glow-orb"></div>
            
            <!-- 3D Floating Particles -->
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            
            <!-- Depth Lines -->
            <div class="depth-lines">
                <div class="depth-line"></div>
                <div class="depth-line"></div>
                <div class="depth-line"></div>
                <div class="depth-line"></div>
                <div class="depth-line"></div>
                <div class="depth-line"></div>
            </div>
        </div>
        
        <div class="container">
            <div class="hero-content hero-3d-text">
                <div class="hero-badge" style="animation-delay:0.2s;"><span><i class="fa fa-star" style="font-size:0.6rem;"></i> Premium Fleet &bull; Sri Lanka <i class="fa fa-star" style="font-size:0.6rem;"></i></span></div>
                <h1 style="animation-delay:0.4s;">Find Your<br><em style="background:linear-gradient(135deg,var(--accent),#a855f7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Perfect Ride</em></h1>
                <p style="animation-delay:0.6s;">From luxury sedans to rugged SUVs, find the best deals on car rentals for your next adventure. No hidden charges &mdash; ever.</p>
                <div class="hero-btns" style="animation-delay:0.8s;">
                    <button class="btn btn-primary ripple" onclick="showPage('listing')" style="padding:16px 32px;font-size:1rem;">
                        <i class="fa fa-car"></i> Browse Fleet
                    </button>
                    <button class="btn" onclick="showPage('about')" style="border:1px solid rgba(255,255,255,0.2);">
                        Learn More <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
                <div class="hero-stats" style="animation-delay:1s;">
                    <div class="stat-item"><h3><span class="counter" data-target="40">0</span>+</h3><p>Years Experience</p></div>
                    <div class="stat-item"><h3><span class="counter" data-target="1200">0</span>+</h3><p>Active Fleet</p></div>
                    <div class="stat-item"><h3><span class="counter" data-target="24">0</span>/7</h3><p>Support</p></div>
                </div>
            </div>
        </div>
        
        <!-- Apple-Style Scroll Indicator -->
        <div class="scroll-indicator">
            <div class="scroll-line"></div>
            <span>Scroll</span>
        </div>
    </section>

    <section class="section-padding features-section">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">Why Choose Us</div>
                <h2>The <em>CarForYou</em> <span>Difference</span></h2>
                <p>We provide premium services to ensure your journey is safe, comfortable, and memorable.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card reveal reveal-delay-1">
                    <div class="feature-icon"><i class="fa fa-shield-alt"></i></div>
                    <h3>Fully Insured</h3>
                    <p>Every vehicle in our fleet is fully covered, giving you complete peace of mind on every journey.</p>
                </div>
                <div class="feature-card reveal reveal-delay-2">
                    <div class="feature-icon"><i class="fa fa-clock"></i></div>
                    <h3>24/7 Support</h3>
                    <p>Our dedicated support team is always ready to assist you anywhere, at any hour of the day.</p>
                </div>
                <div class="feature-card reveal reveal-delay-3">
                    <div class="feature-icon"><i class="fa fa-tags"></i></div>
                    <h3>Best Prices</h3>
                    <p>Guaranteed best rates in the market with absolutely no hidden charges or surprise fees.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="section-divider"></div>

    <!-- TESTIMONIALS - dynamic from DB -->
    <section class="section-padding testimonial-bg">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">Client Stories</div>
                <h2>What Our <span>Clients Say</span></h2>
            </div>

            <?php if (!empty($testimonials)): ?>
            <div class="testimonial-grid">
                <?php
                $delays = ['reveal-delay-1','reveal-delay-2','reveal-delay-3'];
                foreach ($testimonials as $i => $t):
                    $initial = strtoupper(substr($t['user_name'], 0, 1));
                    $delay   = $delays[$i % 3];
                    $filled  = str_repeat('&#9733;', intval($t['rating']));
                    $empty   = str_repeat('&#9734;', 5 - intval($t['rating']));
                ?>
                <div class="testimonial-card reveal <?php echo $delay; ?>">
                    <div class="stars"><?php echo $filled . $empty; ?></div>
                    <i class="fa fa-quote-right quote-icon"></i>
                    <p>&ldquo;<?php echo htmlspecialchars($t['review']); ?>&rdquo;</p>
                    <div class="user-info">
                        <div style="width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#7db0fb);display:flex;align-items:center;justify-content:center;font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:700;color:#fff;flex-shrink:0;border:2px solid var(--border2);">
                            <?php echo htmlspecialchars($initial); ?>
                        </div>
                        <div class="user-details">
                            <h4><?php echo htmlspecialchars($t['user_name']); ?></h4>
                            <p><?php echo htmlspecialchars($t['car_name']); ?> &nbsp;&middot;&nbsp; <?php echo date('M Y', strtotime($t['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="testimonial-grid">
                <div class="testimonial-card reveal reveal-delay-1">
                    <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <i class="fa fa-quote-right quote-icon"></i>
                    <p>&ldquo;The car was delivered to my doorstep in perfect condition. Incredible service and very transparent pricing. Best in Sri Lanka!&rdquo;</p>
                    <div class="user-info">
                        <div class="user-img" style="background-image:url('https://i.pravatar.cc/150?u=1');"></div>
                        <div class="user-details"><h4>Sahan Perera</h4><p>Business Executive</p></div>
                    </div>
                </div>
                <div class="testimonial-card reveal reveal-delay-2">
                    <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <i class="fa fa-quote-right quote-icon"></i>
                    <p>&ldquo;I rented the Defender for a trip to Nuwara Eliya. The vehicle handled the terrain perfectly. Highly recommend their SUV fleet.&rdquo;</p>
                    <div class="user-info">
                        <div class="user-img" style="background-image:url('https://i.pravatar.cc/150?u=2');"></div>
                        <div class="user-details"><h4>Amanda Silva</h4><p>Travel Vlogger</p></div>
                    </div>
                </div>
                <div class="testimonial-card reveal reveal-delay-3">
                    <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <i class="fa fa-quote-right quote-icon"></i>
                    <p>&ldquo;Affordable luxury. Booking the Porsche was a dream come true for my wedding anniversary. Truly a premium experience.&rdquo;</p>
                    <div class="user-info">
                        <div class="user-img" style="background-image:url('https://i.pravatar.cc/150?u=3');"></div>
                        <div class="user-details"><h4>Kasun Rajapaksha</h4><p>Entrepreneur</p></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

</div><!-- END #page-home -->

<!-- ABOUT PAGE -->
<div id="page-about" class="page-view">
    <!-- Hero Section -->
    <section class="about-hero">
        <div class="about-hero-bg"></div>
        <div class="about-hero-particles" id="aboutParticles"></div>
        <div class="container">
            <div class="about-hero-content">
                <div class="about-hero-text">
                    <div class="about-hero-badge">&#x1F31F; Premium Car Rental Since 1984</div>
                    <h1>Driving Excellence<br>Across <span>Sri Lanka</span></h1>
                    <p>Experience the freedom of the open road with CarForYou. From luxury sedans to family SUVs, we deliver exceptional vehicles and unforgettable journeys for over four decades.</p>
                    <div class="about-hero-stats">
                        <div class="about-hero-stat">
                            <h3>40+</h3>
                            <p>Years of Trust</p>
                        </div>
                        <div class="about-hero-stat">
                            <h3>1200+</h3>
                            <p>Fleet Size</p>
                        </div>
                        <div class="about-hero-stat">
                            <h3>50K+</h3>
                            <p>Happy Customers</p>
                        </div>
                    </div>
                </div>
                <div class="about-hero-visual">
                    <div class="about-hero-img-main">
                        <img src="https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?auto=format&fit=crop&q=80&w=800" alt="CarForYou Fleet">
                    </div>
                    <div class="about-hero-float-card top-right">
                        <div class="card-icon">&#x1F697;</div>
                        <div class="card-content">
                            <h5>Premium Vehicles</h5>
                            <p>Top-tier maintenance</p>
                        </div>
                    </div>
                    <div class="about-hero-float-card bottom-left">
                        <div class="card-icon">&#x1F4DE;</div>
                        <div class="card-content">
                            <h5>24/7 Support</h5>
                            <p>Always here for you</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Values -->
    <section class="about-mission">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">What Drives Us</div>
                <h2>Our <span>Mission & Values</span></h2>
                <p>Committed to delivering excellence in every journey</p>
            </div>
            <div class="mission-grid">
                <div class="mission-card reveal">
                    <div class="mission-icon">&#x1F3CE;</div>
                    <h4>Premium Experience</h4>
                    <p>Every vehicle in our fleet is meticulously maintained to deliver a premium driving experience. We believe luxury should be accessible to everyone.</p>
                </div>
                <div class="mission-card reveal reveal-delay-1">
                    <div class="mission-icon">&#x1F91D;</div>
                    <h4>Customer First</h4>
                    <p>Your satisfaction is our priority. From booking to return, we ensure seamless service with dedicated 24/7 customer support.</p>
                </div>
                <div class="mission-card reveal reveal-delay-2">
                    <div class="mission-icon">&#x1F3AF;</div>
                    <h4>Transparent Pricing</h4>
                    <p>No hidden fees, no surprises. We believe in honest pricing that lets you budget your journey without unexpected costs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Timeline -->
    <section class="about-timeline">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">Our Journey</div>
                <h2>Four Decades of <span>Excellence</span></h2>
                <p>From humble beginnings to Sri Lanka's trusted rental service</p>
            </div>
            <div class="timeline-container">
                <div class="timeline-line"></div>
                <div class="timeline-item reveal">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h5>1984</h5>
                        <h6>Humble Beginnings</h6>
                        <p>Started with just 3 vehicles in Trincomalee, serving local travelers and tourists visiting the beautiful eastern coast.</p>
                    </div>
                    <div class="timeline-spacer"></div>
                </div>
                <div class="timeline-item reveal">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h5>1995</h5>
                        <h6>Fleet Expansion</h6>
                        <p>Grew to 50 vehicles and expanded services across the island, becoming a recognized name in Sri Lankan tourism.</p>
                    </div>
                    <div class="timeline-spacer"></div>
                </div>
                <div class="timeline-item reveal">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h5>2008</h5>
                        <h6>Digital Transformation</h6>
                        <p>Launched online booking system, becoming one of the first Sri Lankan car rental companies with web reservations.</p>
                    </div>
                    <div class="timeline-spacer"></div>
                </div>
                <div class="timeline-item reveal">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h5>2024</h5>
                        <h6>Modern Era</h6>
                        <p>1200+ vehicles, 50,000+ customers, and counting. Now featuring hybrid and electric vehicles for eco-conscious travelers.</p>
                    </div>
                    <div class="timeline-spacer"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team -->
    <section class="about-team">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">The Team</div>
                <h2>Meet Our <span>Leadership</span></h2>
                <p>Dedicated professionals driving your experience</p>
            </div>
            <div class="team-grid">
                <div class="team-card reveal">
                    <div class="team-img">&#x1F464;</div>
                    <div class="team-info">
                        <h5>Mohamed Afzhar</h5>
                        <p>Founder & CEO</p>
                        <div class="team-social">
                            <a href="#"><i class="fa fa-linkedin"></i></a>
                            <a href="#"><i class="fa fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <div class="team-card reveal reveal-delay-1">
                    <div class="team-img">&#x1F464;</div>
                    <div class="team-info">
                        <h5>Ahmad Rishard</h5>
                        <p>Operations Director</p>
                        <div class="team-social">
                            <a href="#"><i class="fa fa-linkedin"></i></a>
                            <a href="#"><i class="fa fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <div class="team-card reveal reveal-delay-2">
                    <div class="team-img">&#x1F464;</div>
                    <div class="team-info">
                        <h5>Fathima Zuhra</h5>
                        <p>Customer Success</p>
                        <div class="team-social">
                            <a href="#"><i class="fa fa-linkedin"></i></a>
                            <a href="#"><i class="fa fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <div class="team-card reveal reveal-delay-3">
                    <div class="team-img">&#x1F464;</div>
                    <div class="team-info">
                        <h5>Ibrahim Nazim</h5>
                        <p>Fleet Manager</p>
                        <div class="team-social">
                            <a href="#"><i class="fa fa-linkedin"></i></a>
                            <a href="#"><i class="fa fa-envelope"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="about-why">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">The Advantage</div>
                <h2>Why <span>Choose Us</span></h2>
                <p>Experience the CarForYou difference</p>
            </div>
            <div class="why-grid">
                <div class="why-card reveal">
                    <div class="why-icon">&#x1F697;</div>
                    <div class="why-content">
                        <h5>Diverse Fleet Selection</h5>
                        <p>From economy cars to luxury SUVs, we have the perfect vehicle for every occasion and budget. All vehicles are regularly serviced and safety-checked.</p>
                    </div>
                </div>
                <div class="why-card reveal reveal-delay-1">
                    <div class="why-icon">&#x1F4CB;</div>
                    <div class="why-content">
                        <h5>Flexible Booking</h5>
                        <p>Book online in minutes or call us anytime. Enjoy flexible pick-up and drop-off locations across Sri Lanka with no extra charges.</p>
                    </div>
                </div>
                <div class="why-card reveal">
                    <div class="why-icon">&#x1F3F7;</div>
                    <div class="why-content">
                        <h5>Full Insurance Coverage</h5>
                        <p>Every rental includes comprehensive insurance. Drive with peace of mind knowing you're protected on every journey.</p>
                    </div>
                </div>
                <div class="why-card reveal reveal-delay-1">
                    <div class="why-icon">&#x1F4F1;</div>
                    <div class="why-content">
                        <h5>24/7 Roadside Assistance</h5>
                        <p>Flat tire? Empty tank? Our support team is available round the clock to assist you anywhere in Sri Lanka.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="about-cta">
        <div class="container">
            <div class="about-cta-content reveal">
                <h2>Ready to Start Your <span>Journey</span>?</h2>
                <p>Join thousands of satisfied customers who trust CarForYou for their travel needs. Browse our fleet and book your perfect vehicle today.</p>
                <div class="about-cta-btns">
                    <a href="javascript:void(0)" onclick="showPage('listing')" class="btn-blue">
                        <i class="fa fa-car"></i> Browse Our Fleet
                    </a>
                    <a href="javascript:void(0)" onclick="showPage('contact')" class="btn-outline">
                        <i class="fa fa-phone"></i> Contact Us
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- LISTING PAGE -->
<div id="page-listing" class="page-view">
    <section class="section-padding listing-section perspective-container" style="padding-top:120px;">
        <!-- Floating Background Elements -->
        <div class="floating-car-icon" style="top:10%;left:5%;"><i class="fa fa-car"></i></div>
        <div class="floating-car-icon" style="top:30%;right:8%;animation-delay:-5s;"><i class="fa fa-car-side"></i></div>
        <div class="floating-car-icon" style="bottom:20%;left:15%;animation-delay:-10s;"><i class="fa fa-van-shuttle"></i></div>
        
        <div class="container">
            <!-- Apple-Style Section Header -->
            <div class="section-header reveal">
                <div class="section-label" style="letter-spacing:0.3em;font-size:0.75rem;">DISCOVER</div>
                <h2 style="font-family:'Cormorant Garamond',serif;font-size:3.5rem;font-weight:300;line-height:1.1;margin:12px 0 16px;">
                    Our <span style="background:linear-gradient(135deg,var(--accent),#a855f7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Premium Fleet</span>
                </h2>
                <p style="font-size:1.1rem;color:var(--text2);max-width:500px;margin:0 auto;">
                    Handpicked vehicles for every journey. Experience luxury, performance, and reliability.
                </p>
            </div>
            
            <!-- Apple-Style Filter Bar -->
            <div class="filter-bar reveal" style="margin-bottom:60px;">
                <select id="typeFilter">
                    <option value="all">All Vehicle Types</option>
                    <option value="Petrol">Petrol</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Hybrid">Hybrid</option>
                    <option value="Electric">Electric</option>
                </select>
                <select id="priceFilter">
                    <option value="all">Sort By Price</option>
                    <option value="low">Lowest First</option>
                    <option value="high">Highest First</option>
                </select>
                <button class="btn btn-primary" style="padding:14px 28px;" onclick="applyFilters()">
                    <i class="fa fa-sliders-h"></i> Apply Filters
                </button>
            </div>
            
            <!-- 3D Car Grid -->
            <div class="car-grid" id="carGrid">
                <?php
                if ($conn):
                    $sql = "SELECT * FROM cars WHERE status='Available' ORDER BY id DESC";
                    $res = $conn->query($sql);
                    if ($res && $res->num_rows > 0):
                        while ($car = $res->fetch_assoc()):
                            $car_id    = $car['id'];
                            $car_name  = htmlspecialchars($car['car_name']);
                            $car_type  = htmlspecialchars($car['car_type']);
                            $price     = number_format($car['price_per_day']);
                            $raw_price = intval($car['price_per_day']);
                            $overview  = htmlspecialchars($car['car_overview'] ?? '');
                            $seats = intval($car['seating_capacity'] ?? $car['seatingcapacity'] ?? $car['seats'] ?? 4);
                            $img_src = !empty($car['Vimage1'])
                                ? "admin/img/vehicleimages/" . htmlspecialchars($car['Vimage1'])
                                : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=600";
                            $fuel_icon = ($car_type === 'Electric') ? 'fa-bolt' : 'fa-gas-pump';
                ?>
                <!-- 3D Apple-Style Car Card -->
                <div class="car-card reveal" data-type="<?php echo $car_type; ?>" data-price="<?php echo $raw_price; ?>">
                    <!-- 3D Image Container -->
                    <a href="car_detail.php?id=<?php echo $car_id; ?>" class="car-img-box" style="display:block;text-decoration:none;cursor:zoom-in;" title="View <?php echo $car_name; ?> details">
                        <img src="<?php echo $img_src; ?>" alt="<?php echo $car_name; ?>" loading="lazy">
                        
                        <!-- Floating Badge -->
                        <span class="car-type-badge">
                            <i class="fa <?php echo $fuel_icon; ?>"></i> <?php echo $car_type; ?>
                        </span>
                        
                        <!-- Floating Features -->
                        <div class="car-features">
                            <span><i class="fa <?php echo $fuel_icon; ?>"></i> <?php echo $car_type; ?></span>
                            <span><i class="fa fa-user"></i> <?php echo $seats; ?> Seats</span>
                        </div>
                        
                        <!-- View Details Overlay -->
                        <div class="img-view-hint">
                            <span>
                                <i class="fa fa-expand-alt"></i> View Details
                            </span>
                        </div>
                    </a>
                    
                    <!-- 3D Card Body -->
                    <div class="car-body">
                        <div class="car-title">
                            <h3><?php echo $car_name; ?></h3>
                            <span class="price">LKR <?php echo $price; ?> /Day</span>
                        </div>
                        <p><?php echo $overview ?: 'Premium vehicle available for rental. Experience comfort and style with our well-maintained fleet.'; ?></p>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="users/booking.php?car_id=<?php echo $car_id; ?>" class="btn btn-primary" style="justify-content:center;display:flex;align-items:center;gap:10px;padding:16px;">
                                <i class="fa fa-calendar-check"></i> Book Now
                            </a>
                        <?php else: ?>
                            <a href="users/login.php?redirect=index.php%3Fcar_id%3D<?php echo $car_id; ?>" class="btn btn-primary" style="justify-content:center;display:flex;align-items:center;gap:10px;padding:16px;">
                                <i class="fa fa-calendar-check"></i> Book Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                        endwhile;
                    else:
                        echo '<div class="glass-card" style="padding:80px;text-align:center;grid-column:1/-1;"><i class="fa fa-car" style="font-size:3rem;color:var(--text3);margin-bottom:20px;"></i><p style="font-size:1.1rem;color:var(--text2);">No cars available at the moment. Check back soon!</p></div>';
                    endif;
                else:
                    echo '<div class="glass-card" style="padding:80px;text-align:center;grid-column:1/-1;background:rgba(230,57,70,0.1);border-color:rgba(230,57,70,0.3);"><i class="fa fa-exclamation-triangle" style="font-size:3rem;color:#e63946;margin-bottom:20px;"></i><p style="font-size:1.1rem;color:#e63946;">Database not connected.</p></div>';
                endif;
                ?>
            </div>

            <!-- Coming Soon / Currently Booked Cars -->
            <?php if (!empty($booked_cars)): ?>
            <div style="margin-top:80px;">
                <div class="section-header reveal" style="margin-bottom:40px;">
                    <div class="section-label" style="color:var(--amber);">Coming Soon</div>
                    <h2>Currently <span>Booked</span></h2>
                    <p>These vehicles are currently reserved. They'll be available after the booking period ends.</p>
                </div>
                <div class="car-grid" id="bookedCarGrid" style="opacity:0.85;">
                    <?php foreach($booked_cars as $car): 
                        $car_id    = $car['id'];
                        $car_name  = htmlspecialchars($car['car_name']);
                        $car_type  = htmlspecialchars($car['car_type']);
                        $price     = number_format($car['price_per_day']);
                        $seats = intval($car['seating_capacity'] ?? 4);
                        $img_src = !empty($car['Vimage1'])
                            ? "admin/img/vehicleimages/" . htmlspecialchars($car['Vimage1'])
                            : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=600";
                        $fuel_icon = ($car_type === 'Electric') ? 'fa-bolt' : 'fa-gas-pump';
                        $booking_from = date('d M Y', strtotime($car['booking_from']));
                        $booking_to = date('d M Y', strtotime($car['booking_to']));
                    ?>
                    <div class="car-card reveal" data-type="<?php echo $car_type; ?>">
                        <div class="car-img-box" style="position:relative;">
                            <img src="<?php echo $img_src; ?>" alt="<?php echo $car_name; ?>" loading="lazy" style="filter:grayscale(30%);">
                            <span class="car-type-badge" style="background:var(--amber);color:#000;">
                                <i class="fa fa-calendar-clock"></i> Reserved
                            </span>
                            <div class="car-features">
                                <span><i class="fa <?php echo $fuel_icon; ?>"></i> <?php echo $car_type; ?></span>
                                <span><i class="fa fa-user"></i> <?php echo $seats; ?> Seats</span>
                            </div>
                        </div>
                        <div class="car-body">
                            <div class="car-title">
                                <h3><?php echo $car_name; ?></h3>
                                <span class="price" style="background:rgba(251,191,36,0.1);border-color:rgba(251,191,36,0.2);color:var(--amber);">LKR <?php echo $price; ?> /Day</span>
                            </div>
                            <div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:10px;padding:12px;margin-bottom:14px;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                                    <i class="fa fa-calendar" style="color:var(--amber);font-size:0.85rem;"></i>
                                    <span style="font-size:0.82rem;color:var(--text2);font-weight:600;">Booking Period</span>
                                </div>
                                <div style="display:flex;gap:16px;">
                                    <div>
                                        <div style="font-size:0.68rem;color:var(--text3);text-transform:uppercase;margin-bottom:2px;">From</div>
                                        <div style="font-size:0.82rem;color:var(--text);font-weight:600;"><?php echo $booking_from; ?></div>
                                    </div>
                                    <div style="color:var(--text3);align-self:center;">→</div>
                                    <div>
                                        <div style="font-size:0.68rem;color:var(--text3);text-transform:uppercase;margin-bottom:2px;">To</div>
                                        <div style="font-size:0.82rem;color:var(--text);font-weight:600;"><?php echo $booking_to; ?></div>
                                    </div>
                                </div>
                            </div>
                            <a href="car_detail.php?id=<?php echo $car_id; ?>" class="btn btn-primary" style="justify-content:center;display:flex;align-items:center;gap:10px;padding:14px;">
                                <i class="fa fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- CONTACT PAGE -->
<div id="page-contact" class="page-view">
    <section class="section-padding" style="padding-top:120px;">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">Reach Out</div>
                <h2>Get In <span>Touch</span></h2>
                <p>Have questions? We are here to help you 24/7.</p>
            </div>
            <div class="contact-container reveal">
                <div class="contact-info">
                    <h3>Let's Talk</h3>
                    <p>Fill out the form and our team will get back to you within 24 hours.</p>
                    <div class="info-item"><i class="fa fa-phone"></i><div><h4>Call Us</h4><p>+94 75 45 57 624</p></div></div>
                    <div class="info-item"><i class="fa fa-envelope"></i><div><h4>Email Us</h4><p>amafzhar@gmail.com</p></div></div>
                    <div class="info-item"><i class="fa fa-location-dot"></i><div><h4>Head Office</h4><p>37 Kinniya, Trincomalee, Sri Lanka</p></div></div>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="contact-form">
                    <form id="rentalContactForm" method="POST" action="contact-us.php">
                        <div class="contact-form-grid">
                            <div class="form-group"><label>First Name</label><input type="text" name="first_name" placeholder="John" required></div>
                            <div class="form-group"><label>Last Name</label><input type="text" name="last_name" placeholder="Doe" required></div>
                        </div>
                        <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="john@example.com" required></div>
                        <div class="form-group"><label>Subject</label><input type="text" name="subject" placeholder="Inquiry about a vehicle" required></div>
                        <div class="form-group"><label>Message</label><textarea name="message" placeholder="Write your message here..." required></textarea></div>
                        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Send Message <i class="fa fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <div class="logo"><h2 style="color:var(--text);">Car<span>ForYou</span></h2></div>
                <p>A leading car rental provider dedicated to giving you the best driving experience across the country since 1984.</p>
            </div>
            <div class="footer-col">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="javascript:void(0)" onclick="showPage('home')">Home</a></li>
                    <li><a href="javascript:void(0)" onclick="showPage('about')">About Us</a></li>
                    <li><a href="javascript:void(0)" onclick="showPage('listing')">Car Listing</a></li>
                    <li><a href="javascript:void(0)" onclick="showPage('contact')">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li><a href="tel:+94754557624"><i class="fa fa-phone" style="width:14px;color:var(--accent);"></i> +94 75 45 57 624</a></li>
                    <li><a href="mailto:amafzhar@gmail.com"><i class="fa fa-envelope" style="width:14px;color:var(--accent);"></i> amafzhar@gmail.com</a></li>
                    <li><a href="#"><i class="fa fa-location-dot" style="width:14px;color:var(--accent);"></i> Trincomalee, Sri Lanka</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 CarForYou Rental Portal. Crafted for premium experiences.</p>
            <p style="color:var(--text3);font-size:0.78rem;">Designed with &hearts; in Sri Lanka</p>
        </div>
    </div>
</footer>

<script>
    var PAGE_ORDER = ['home', 'about', 'listing', 'contact'];

    function renderPage(pageId) {
        document.querySelectorAll('.page-view').forEach(function(page) { page.classList.remove('active'); });
        var target = document.getElementById('page-' + pageId);
        if (target) target.classList.add('active');
        document.querySelectorAll('#navLinks li').forEach(function(li) { li.classList.remove('active'); });
        var activeLink = document.getElementById('link-' + pageId);
        if (activeLink && activeLink.parentElement) activeLink.parentElement.classList.add('active');
        document.getElementById('navLinks').classList.remove('open');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        // FIX: fire reveal 3 times to catch all elements in active page
        triggerReveal();
        setTimeout(triggerReveal, 150);
        setTimeout(triggerReveal, 600);
    }

    function showPage(pageId) {
        if (pageId === 'home') { history.replaceState({ page: 'home' }, '', '#home'); }
        else { history.pushState({ page: pageId }, '', '#' + pageId); }
        renderPage(pageId);
    }

    window.addEventListener('popstate', function(e) {
        renderPage(e.state && e.state.page ? e.state.page : 'home');
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('open');
        });
        var hash = window.location.hash.replace('#', '');
        var startPage = (hash && PAGE_ORDER.indexOf(hash) !== -1) ? hash : 'home';
        history.replaceState({ page: startPage }, '', '#' + startPage);
        renderPage(startPage);
        window.addEventListener('scroll', function() {
            document.getElementById('siteHeader').classList.toggle('scrolled', window.scrollY > 40);
            triggerReveal();
        });
        setTimeout(function() {
            document.getElementById('pageLoader').classList.add('hidden');
            setTimeout(function() { document.getElementById('pageLoader').style.display = 'none'; }, 500);
        }, 1500);
        animateCounters();
    });

    function applyFilters() {
        var typeValue  = document.getElementById('typeFilter').value;
        var priceValue = document.getElementById('priceFilter').value;
        var grid = document.getElementById('carGrid');
        var cards = Array.from(grid.querySelectorAll('.car-card'));
        cards.forEach(function(card) {
            var cardType = (card.getAttribute('data-type') || '').toLowerCase();
            var filterType = typeValue.toLowerCase();
            card.style.display = (typeValue === 'all' || cardType === filterType) ? '' : 'none';
        });
        if (priceValue === 'low' || priceValue === 'high') {
            var visible = cards.filter(function(c) { return c.style.display !== 'none'; });
            visible.sort(function(a, b) {
                var pa = parseInt(a.getAttribute('data-price')), pb = parseInt(b.getAttribute('data-price'));
                return priceValue === 'low' ? pa - pb : pb - pa;
            });
            visible.forEach(function(card) { grid.appendChild(card); });
        }
    }

    var currentTheme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon();
    document.getElementById('themeToggle').addEventListener('click', function() {
        currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', currentTheme);
        localStorage.setItem('cfyTheme', currentTheme);
        updateThemeIcon();
    });
    function updateThemeIcon() {
        var icon = document.getElementById('themeIcon');
        icon.className = currentTheme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
        document.getElementById('themeToggle').title = currentTheme === 'dark' ? 'Switch to Day Mode' : 'Switch to Night Mode';
    }

    function animateCounters() {
        document.querySelectorAll('.counter').forEach(function(el) {
            var target = parseInt(el.getAttribute('data-target')), start = 0, step = target / (1800 / 16);
            var timer = setInterval(function() {
                start += step;
                if (start >= target) { el.textContent = target; clearInterval(timer); }
                else { el.textContent = Math.floor(start); }
            }, 16);
        });
    }

    // FIX: reveal checks if element is in active page, not just scroll position
    function triggerReveal() {
        document.querySelectorAll('.reveal').forEach(function(el) {
            var rect = el.getBoundingClientRect();
            var inViewport = rect.top < window.innerHeight - 40;
            var inActivePage = el.closest('.page-view.active') !== null;
            if (inViewport || inActivePage) {
                el.classList.add('visible');
                el.classList.remove('hidden');
            }
        });
    }
    
    // Parallax Scroll Effect
    window.addEventListener('scroll', function() {
        var scrolled = window.pageYOffset;
        var parallaxBg = document.querySelector('.parallax-bg');
        var glowOrbs = document.querySelectorAll('.glow-orb');
        var heroContent = document.querySelector('.hero-content');
        
        if (parallaxBg) {
            parallaxBg.style.transform = 'translateY(' + (scrolled * 0.4) + 'px)';
        }
        
        if (glowOrbs) {
            glowOrbs.forEach(function(orb, i) {
                var speed = 0.2 + (i * 0.1);
                orb.style.transform = 'translateY(' + (scrolled * speed) + 'px) scale(' + (1 + scrolled * 0.0005) + ')';
            });
        }
        
        if (heroContent && scrolled < 600) {
            heroContent.style.transform = 'translateY(' + (scrolled * 0.3) + 'px)';
            heroContent.style.opacity = 1 - (scrolled / 600);
        }
    });
    
    // Smooth mouse parallax effect on hero
    document.querySelector('.hero').addEventListener('mousemove', function(e) {
        var rect = this.getBoundingClientRect();
        var x = (e.clientX - rect.left) / rect.width - 0.5;
        var y = (e.clientY - rect.top) / rect.height - 0.5;
        
        var particles = document.querySelectorAll('.particle');
        particles.forEach(function(p, i) {
            var speed = 20 + (i * 15);
            var direction = i % 2 === 0 ? 1 : -1;
            p.style.transform = 'translate(' + (x * speed * direction) + 'px, ' + (y * speed) + 'px)';
        });
        
        var glowOrbs = document.querySelectorAll('.glow-orb');
        glowOrbs.forEach(function(orb, i) {
            var speed = 10 + (i * 5);
            orb.style.transform = 'translate(' + (x * speed) + 'px, ' + (y * speed) + 'px)';
        });
    });
    
    // Reset on mouse leave
    document.querySelector('.hero').addEventListener('mouseleave', function() {
        var particles = document.querySelectorAll('.particle');
        particles.forEach(function(p) {
            p.style.transition = 'transform 0.5s ease-out';
            p.style.transform = 'translate(0, 0)';
            setTimeout(function() { p.style.transition = ''; }, 500);
        });
    });
</script>

<!-- ADVANCED CHATBOT -->
<button id="chatBtn" onclick="toggleChat()" title="Chat with AI Assistant">
    <i class="fa fa-comments"></i>
    <span class="badge">AI</span>
</button>
<div id="chatWindow">
    <div class="chat-particles" id="chatParticles"></div>
    <div class="chat-header">
        <div class="chat-avatar-container">
            <div class="chat-avatar">
                <i class="fa fa-robot"></i>
                <span class="status-ring"></span>
            </div>
        </div>
        <div class="chat-header-info">
            <h4>CarForYou Assistant</h4>
            <span>
                <span class="online-dot"></span>
                <span id="headerStatus">Online — Powered by AI</span>
            </span>
        </div>
        <div class="chat-actions">
            <button class="chat-action-btn" onclick="clearChat()" title="Clear Chat"><i class="fa fa-trash-alt"></i></button>
            <button class="chat-close" onclick="toggleChat()"><i class="fa fa-times"></i></button>
        </div>
    </div>
    <div id="chatMessages">
        <div class="welcome-msg">
            <div class="welcome-icon">&#x1F916;</div>
            <h3>Welcome to CarForYou!</h3>
            <p>I'm your AI assistant. Ask me about cars, prices, or bookings.</p>
        </div>
    </div>
    <div class="quick-replies" id="quickReplies">
        <span class="quick-replies-label">Quick actions</span>
        <button class="qr-btn" onclick="sendQuick('Show me all available cars')"><span class="qr-icon">&#x1F697;</span> All Cars</button>
        <button class="qr-btn" onclick="sendQuick('What are your premium cars?')"><span class="qr-icon">&#x2B50;</span> Premium</button>
        <button class="qr-btn" onclick="sendQuick('What is the cheapest car?')"><span class="qr-icon">&#x1F4B0;</span> Budget</button>
        <button class="qr-btn" onclick="sendQuick('Show electric or hybrid cars')"><span class="qr-icon">&#x26A1;</span> Eco</button>
        <button class="qr-btn" onclick="sendQuick('How do I book a car?')"><span class="qr-icon">&#x1F4CB;</span> How to Book</button>
    </div>
    <div class="chat-input-area">
        <div class="chat-input-wrapper">
            <input type="text" id="chatInput" placeholder="Ask about our cars..." onkeydown="if(event.key==='Enter') sendMessage()">
            <button class="input-voice-btn" id="voiceBtn" onclick="toggleVoice()" title="Voice input"><i class="fa fa-microphone"></i></button>
        </div>
        <button id="chatSend" onclick="sendMessage()"><i class="fa fa-paper-plane"></i></button>
    </div>
</div>

<script>
const CAR_DATA = <?php echo $cars_json; ?>;
let chatOpened = false;
let messageCount = 0;
let isRecording = false;
let recognition = null;
const prices   = () => CAR_DATA.map(c => Number(c.price_per_day));
const maxPrice = () => Math.max(...prices());
const minPrice = () => Math.min(...prices());
const premium  = () => CAR_DATA.filter(c => Number(c.price_per_day) >= maxPrice()*0.65).sort((a,b)=>b.price_per_day-a.price_per_day);
const budget   = () => CAR_DATA.filter(c => Number(c.price_per_day) <= minPrice()*1.8).sort((a,b)=>a.price_per_day-b.price_per_day);
const fmt      = n  => 'LKR ' + Number(n).toLocaleString();
function carLine(car){ return `&#x1F697; <strong>${car.car_name}</strong> (${car.car_model||''}) &mdash; ${fmt(car.price_per_day)}/day | ${car.car_type} | ${car.seating_capacity||4} seats`; }
function formatTime(d) { return d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'}); }

function createParticles() {
    const container = document.getElementById('chatParticles');
    for (let i = 0; i < 12; i++) {
        const p = document.createElement('div');
        p.className = 'chat-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.top = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 8 + 's';
        p.style.animationDuration = (6 + Math.random() * 4) + 's';
        container.appendChild(p);
    }
}
createParticles();

function getBotReply(msg) {
    const t = msg.toLowerCase().trim();
    if (/^(hi|hello|hey|good (morning|evening|afternoon)|howdy)/.test(t)) return "&#x1F44B; Hi there! Welcome to <strong>CarForYou</strong>. I'm your AI assistant and I can help you find the perfect car, compare prices, or guide you through the booking process. What are you looking for today?";
    if (/thank|thanks|thank you/.test(t)) return "&#x1F60A; You're welcome! Feel free to ask if you need anything else. Happy driving! &#x1F697;";
    if (/how.*(book|rent|reserve|hire)|booking process|steps to/.test(t)) return "&#x1F4CB; <strong>How to Book:</strong><br><br>&#x1F517; 1. Go to <strong>Car Listing</strong> from the top menu<br>&#x1F50D; 2. Browse and click <strong>Book Now</strong> on your chosen car<br>&#x1F512; 3. Login if you haven't already<br>&#x2705; 4. Pick your dates and confirm!<br><br>&#x1F449; Need help choosing a car? Just ask!";
    if (/all car|full list|show.*car|available car|what car|fleet|every car|list.*car/.test(t)) {
        if (CAR_DATA.length === 0) return "&#x1F614; No cars are available right now. Please check back later.";
        let reply = `&#x1F698; We have <strong>${CAR_DATA.length} cars</strong> in our fleet:<br><br>`;
        CAR_DATA.forEach(c => { reply += carLine(c) + '<br>'; });
        reply += '<br>&#x1F449; <strong>Go to Car Listing</strong> to Book Now!';
        return reply;
    }
    if (/premium|luxury|best|top|expensive|high.?end|vip|fancy/.test(t)) {
        const p = premium(); if (p.length === 0) return "&#x1F614; No premium cars found at the moment.";
        let reply = `&#x2B50; <strong>Our Premium Collection:</strong><br><br>`; p.slice(0,4).forEach(c => { reply += carLine(c) + '<br>'; });
        reply += `<br>&#x1F451; These are our top-tier vehicles with premium features. Visit <strong>Car Listing</strong> to book!`; return reply;
    }
    if (/cheap|budget|affordable|low.?price|cheapest|economical|least expensive|low cost/.test(t)) {
        const b = budget(); if (b.length === 0) return "&#x1F614; No budget cars found at the moment.";
        let reply = `&#x1F4B0; <strong>Budget-Friendly Options:</strong><br><br>`; b.slice(0,4).forEach(c => { reply += carLine(c) + '<br>'; });
        reply += `<br>&#x1F4C8; Great value for money! Head to <strong>Car Listing</strong> to book.`; return reply;
    }
    if (/electric|ev|zero emission/.test(t)) {
        const ev = CAR_DATA.filter(c => /electric/i.test(c.car_type)); if (ev.length === 0) return "&#x26A1; We don't have any electric cars available right now. Check back soon!";
        let reply = `&#x26A1; <strong>Electric Cars (Zero Emissions):</strong><br><br>`; ev.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
    }
    if (/hybrid/.test(t)) {
        const h = CAR_DATA.filter(c => /hybrid/i.test(c.car_type)); if (h.length === 0) return "&#x1F33F; No hybrid cars available right now.";
        let reply = `&#x1F33F; <strong>Hybrid Cars:</strong><br><br>`; h.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
    }
    if (/eco|green|fuel.?efficient|environment/.test(t)) {
        const eco = CAR_DATA.filter(c => /hybrid|electric/i.test(c.car_type)); if (eco.length === 0) return "&#x1F331; No eco-friendly cars available right now.";
        let reply = `&#x1F331; <strong>Eco-Friendly Cars (Hybrid & Electric):</strong><br><br>`; eco.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
    }
    if (/petrol|gasoline/.test(t)) {
        const p = CAR_DATA.filter(c => /petrol/i.test(c.car_type)); if (p.length === 0) return "&#x26FD; No petrol cars available right now.";
        let reply = `&#x26FD; <strong>Petrol Cars:</strong><br><br>`; p.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
    }
    if (/diesel/.test(t)) {
        const d = CAR_DATA.filter(c => /diesel/i.test(c.car_type)); if (d.length === 0) return "&#x1F6E2;&#xFE0F; No diesel cars available right now.";
        let reply = `&#x1F6E2;&#xFE0F; <strong>Diesel Cars:</strong><br><br>`; d.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
    }
    if (/price|cost|rate|how much|per day|daily rate|charges/.test(t)) {
        if (CAR_DATA.length === 0) return "&#x1F614; No pricing info available right now.";
        let reply = `&#x1F4B5; <strong>Our Price Range:</strong><br><br>&#x2B07; <strong>Lowest:</strong> ${fmt(minPrice())}/day<br>&#x2B06; <strong>Highest:</strong> ${fmt(maxPrice())}/day<br><br><strong>All Cars by Price:</strong><br>`;
        [...CAR_DATA].sort((a,b)=>a.price_per_day-b.price_per_day).forEach(c => { reply += `${c.car_name} &mdash; ${fmt(c.price_per_day)}/day<br>`; }); return reply;
    }
    if (/seat|passenger|family|people|group|capacity|how many/.test(t)) {
        let reply = `&#x1F468;&#x200D;&#x1F469;&#x200D;&#x1F467; <strong>Cars by Seating Capacity:</strong><br><br>`;
        [...CAR_DATA].sort((a,b)=>(b.seating_capacity||4)-(a.seating_capacity||4)).forEach(c => { reply += `&#x1F697; <strong>${c.car_name}</strong> &mdash; ${c.seating_capacity||4} seats | ${fmt(c.price_per_day)}/day<br>`; }); return reply;
    }
    const matchedCar = CAR_DATA.find(c => t.includes(c.car_name.toLowerCase()) || (c.car_model && t.includes(c.car_model.toLowerCase())));
    if (matchedCar) return `&#x1F697; <strong>${matchedCar.car_name}</strong><br><br>&#x1F4C5; Year/Model: ${matchedCar.car_model||'N/A'}<br>&#x26FD; Fuel: ${matchedCar.car_type}<br>&#x1F465; Seats: ${matchedCar.seating_capacity||4}<br>&#x1F4B5; Price: <strong>${fmt(matchedCar.price_per_day)}/day</strong><br><br>&#x1F4DD; ${matchedCar.car_overview||'Premium rental vehicle available now.'}<br><br>&#x1F449; Go to <strong>Car Listing</strong> to book this car!`;
    if (/recommend|suggest|which car|best car|good car|what.*should|help.*choose/.test(t)) {
        const p = premium(), b = budget();
        let reply = `&#x1F60A; Happy to help! Here's my recommendation:<br><br>`;
        if (p.length > 0) reply += `&#x2B50; <strong>Premium pick:</strong> ${p[0].car_name} &mdash; ${fmt(p[0].price_per_day)}/day<br>`;
        if (b.length > 0) reply += `&#x1F4B0; <strong>Budget pick:</strong> ${b[0].car_name} &mdash; ${fmt(b[0].price_per_day)}/day<br>`;
        reply += `<br>&#x1F914; Tell me your <strong>budget</strong> or <strong>purpose</strong> (trip, wedding, family) and I'll narrow it down!`; return reply;
    }
    if (/contact|phone|call|email|address|location|office|reach/.test(t)) return `&#x1F4DE; <strong>Contact CarForYou:</strong><br><br>&#x1F4F1; Phone: +94 75 45 57 624<br>&#x2709;&#xFE0F; Email: amafzhar@gmail.com<br>&#x1F4CD; 37 Kinniya, Trincomalee, Sri Lanka<br><br>&#x1F4AC; Or use the <strong>Contact Us</strong> page from the menu!`;
    if (/about|company|who are you|carforyou|history|experience/.test(t)) return `&#x1F3E2; <strong>About CarForYou:</strong><br><br>We are a premium car rental company in Sri Lanka with <strong>40+ years</strong> of experience and a fleet of <strong>1200+ vehicles</strong>.<br><br>&#x2705; Fully insured cars<br>&#x1F504; 24/7 support<br>&#x1F4B0; Best prices, no hidden charges!`;
    if (/open|hours|timing|when|available|24/.test(t)) return `&#x1F550; We offer <strong>24/7 support</strong> for our customers. You can book a car anytime through our website!<br><br>&#x1F4AC; Have questions? I'm here to help!`;
    if (/bye|goodbye|see you|later|take care/.test(t)) return "&#x1F44B; Goodbye! Have a great drive. Come back anytime &mdash; CarForYou is always here for you! &#x1F697;";
    return `&#x1F914; I'm not sure about that, but I can help you with:<br><br>&#x1F697; <strong>All Cars</strong> &mdash; type "show all cars"<br>&#x2B50; <strong>Premium Cars</strong> &mdash; type "premium cars"<br>&#x1F4B0; <strong>Budget Cars</strong> &mdash; type "cheap cars"<br>&#x1F4B5; <strong>Prices</strong> &mdash; type "what are your prices"<br>&#x1F4CB; <strong>Booking</strong> &mdash; type "how to book"<br>&#x1F4DE; <strong>Contact</strong> &mdash; type "contact info"<br><br>&#x1F449; Or browse our <strong>Car Listing</strong> page directly!`;
}

function toggleChat() {
    const win = document.getElementById('chatWindow');
    if (win.classList.contains('open')) {
        win.classList.add('closing');
        setTimeout(() => {
            win.classList.remove('open', 'closing');
        }, 300);
    } else {
        win.classList.add('open');
        if (!chatOpened) {
            chatOpened = true;
            setTimeout(() => addMessage('bot', "&#x1F44B; Hi! I'm the CarForYou AI assistant. I can help you find cars, check prices, compare vehicles, and guide you through the booking process &mdash; all for free!<br><br>&#x1F914; What are you looking for today?"), 400);
        }
        document.getElementById('chatInput').focus();
    }
}

function addMessage(role, text) {
    const container = document.getElementById('chatMessages');
    const now = new Date();
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    
    const timeStr = formatTime(now);
    const statusHtml = role === 'user' ? `<span class="msg-status sent"><i class="fa fa-check"></i></span>` : '';
    
    div.innerHTML = `
        <div class="msg-avatar">${role === 'bot' ? '&#x1F916;' : '&#x1F464;'}</div>
        <div class="msg-content">
            <div class="msg-bubble">${text}</div>
            <div class="msg-meta">
                <span class="msg-time">${timeStr}</span>
                ${statusHtml}
            </div>
        </div>
    `;
    
    container.appendChild(div);
    smoothScrollToBottom();
    messageCount++;
}

function smoothScrollToBottom() {
    const container = document.getElementById('chatMessages');
    const targetScroll = container.scrollHeight - container.clientHeight;
    const startScroll = container.scrollTop;
    const duration = 300;
    const startTime = performance.now();
    
    function animate(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeProgress = 1 - Math.pow(1 - progress, 3);
        container.scrollTop = startScroll + (targetScroll - startScroll) * easeProgress;
        if (progress < 1) requestAnimationFrame(animate);
    }
    requestAnimationFrame(animate);
}

function showTyping() {
    const c = document.getElementById('chatMessages');
    const existing = document.getElementById('typingIndicator');
    if (existing) existing.remove();
    
    const d = document.createElement('div');
    d.className = 'msg bot typing';
    d.id = 'typingIndicator';
    d.innerHTML = `
        <div class="msg-avatar">&#x1F916;</div>
        <div class="msg-content">
            <div class="msg-bubble">
                <div class="typing-dots"><span></span><span></span><span></span></div>
            </div>
        </div>
    `;
    c.appendChild(d);
    smoothScrollToBottom();
    
    document.getElementById('headerStatus').innerHTML = '<span class="typing-indicator-header active"><span></span><span></span><span></span> typing...</span>';
}

function removeTyping() {
    const el = document.getElementById('typingIndicator');
    if (el) el.remove();
    document.getElementById('headerStatus').innerHTML = '<span class="online-dot"></span>Online — Powered by AI';
}

function sendQuick(text) {
    document.getElementById('chatInput').value = text;
    document.getElementById('quickReplies').style.opacity = '0';
    setTimeout(() => { document.getElementById('quickReplies').style.display = 'none'; document.getElementById('quickReplies').style.opacity = '1'; }, 200);
    sendMessage();
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text) return;
    
    document.getElementById('quickReplies').style.display = 'none';
    addMessage('user', text);
    input.value = '';
    input.disabled = true;
    document.getElementById('chatSend').disabled = true;
    document.getElementById('chatSend').classList.add('sending');
    
    showTyping();
    
    const delay = 400 + Math.random() * 600;
    setTimeout(() => {
        removeTyping();
        addMessage('bot', getBotReply(text));
        input.disabled = false;
        document.getElementById('chatSend').disabled = false;
        document.getElementById('chatSend').classList.remove('sending');
        input.focus();
    }, delay);
}

function clearChat() {
    const container = document.getElementById('chatMessages');
    container.innerHTML = `
        <div class="welcome-msg">
            <div class="welcome-icon">&#x1F916;</div>
            <h3>Chat Cleared!</h3>
            <p>Start a new conversation with CarForYou.</p>
        </div>
    `;
    messageCount = 0;
    showToast('Chat cleared', 'fa-check-circle');
}

function showToast(message, icon = 'fa-info-circle') {
    const existing = document.querySelector('.chat-toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'chat-toast';
    toast.innerHTML = `<i class="fa ${icon}"></i> ${message}`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

function toggleVoice() {
    const btn = document.getElementById('voiceBtn');
    
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        showToast('Voice input not supported in this browser', 'fa-exclamation-circle');
        return;
    }
    
    if (isRecording) {
        if (recognition) recognition.stop();
        btn.classList.remove('recording');
        btn.innerHTML = '<i class="fa fa-microphone"></i>';
        isRecording = false;
        return;
    }
    
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.lang = 'en-US';
    
    recognition.onstart = () => {
        isRecording = true;
        btn.classList.add('recording');
        btn.innerHTML = '<i class="fa fa-stop"></i>';
        showToast('Listening...', 'fa-microphone');
    };
    
    recognition.onresult = (event) => {
        const transcript = Array.from(event.results).map(result => result[0].transcript).join('');
        document.getElementById('chatInput').value = transcript;
        if (event.results[0].isFinal) {
            sendMessage();
        }
    };
    
    recognition.onerror = (event) => {
        console.log('Speech recognition error:', event.error);
        isRecording = false;
        btn.classList.remove('recording');
        btn.innerHTML = '<i class="fa fa-microphone"></i>';
        if (event.error !== 'no-speech') {
            showToast('Voice input error: ' + event.error, 'fa-exclamation-circle');
        }
    };
    
    recognition.onend = () => {
        isRecording = false;
        btn.classList.remove('recording');
        btn.innerHTML = '<i class="fa fa-microphone"></i>';
    };
    
    recognition.start();
}

// About Hero Particles
function createAboutParticles() {
    const container = document.getElementById('aboutParticles');
    if (!container) return;
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'about-hero-particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDelay = Math.random() * 15 + 's';
        p.style.animationDuration = (15 + Math.random() * 10) + 's';
        container.appendChild(p);
    }
}
createAboutParticles();

// ═══════════════════════════════════════════════════════════
// APPLE-STYLE SCROLL ANIMATIONS - INTERSECTION OBSERVER
// ═══════════════════════════════════════════════════════════

// Reveal on Scroll - Apple Style
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            // Add staggered delay for grid items
            const delay = entry.target.closest('.car-grid') ? index * 100 : 0;
            setTimeout(() => {
                entry.target.classList.add('visible');
            }, delay);
        }
    });
}, {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
});

// Observe all reveal elements
document.querySelectorAll('.reveal, .reveal-scale, .reveal-3d').forEach(el => {
    revealObserver.observe(el);
});

// ═══════════════════════════════════════════════════════════
// PARALLAX SCROLL EFFECT
// ═══════════════════════════════════════════════════════════

let ticking = false;
window.addEventListener('scroll', () => {
    if (!ticking) {
        window.requestAnimationFrame(() => {
            const scrolled = window.pageYOffset;
            
            // Parallax for floating elements
            document.querySelectorAll('.floating-car-icon').forEach((el, i) => {
                const speed = 0.1 + (i * 0.05);
                el.style.transform = `translateY(${scrolled * speed}px)`;
            });
            
            ticking = false;
        });
        ticking = true;
    }
});

// ═══════════════════════════════════════════════════════════
// SMOOTH COUNTER ANIMATION
// ═══════════════════════════════════════════════════════════

function animateCounter(el) {
    const target = parseInt(el.dataset.target);
    const duration = 2000;
    const step = target / (duration / 16);
    let current = 0;
    
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            el.textContent = target.toLocaleString();
            clearInterval(timer);
        } else {
            el.textContent = Math.floor(current).toLocaleString();
        }
    }, 16);
}

// Counter observer
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('.counter').forEach(el => counterObserver.observe(el));

// ═══════════════════════════════════════════════════════════
// LAZY LOADING IMAGES
// ═══════════════════════════════════════════════════════════

if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
                imageObserver.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}

// ═══════════════════════════════════════════════════════════
// SMOOTH SCROLL TO SECTIONS
// ═══════════════════════════════════════════════════════════

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ═══════════════════════════════════════════════════════════
// PRELOAD CRITICAL RESOURCES
// ═══════════════════════════════════════════════════════════

// Preload critical fonts
if (document.fonts && document.fonts.load) {
    document.fonts.load('400 16px "Cormorant Garamond"');
    document.fonts.load('600 16px "DM Sans"');
}

// ═══════════════════════════════════════════════════════════
// PERFORMANCE: REDUCE MOTION FOR ACCESSIBILITY
// ═══════════════════════════════════════════════════════════

if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.style.setProperty('--transition', 'none');
    document.querySelectorAll('.reveal, .car-card, .floating-car-icon').forEach(el => {
        el.style.transition = 'none';
        el.style.animation = 'none';
    });
}

// ═══════════════════════════════════════════════════════════
// INITIALIZE ON PAGE LOAD
// ═══════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // Trigger initial reveal animations
    setTimeout(() => {
        document.querySelectorAll('.reveal, .reveal-scale').forEach(el => {
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight) {
                el.classList.add('visible');
            }
        });
    }, 100);
});
</script>

</body>
</html>