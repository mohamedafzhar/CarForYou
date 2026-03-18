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
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarForYou | Premium Car Rental Service</title>
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
    @keyframes fadeInUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }

    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 32px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; cursor: pointer; border: none; transition: all 0.25s ease; position: relative; overflow: hidden; }
    .btn::after { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,0.1); transform: translateX(-100%); transition: transform 0.3s ease; }
    .btn:hover::after { transform: translateX(0); }
    .btn-primary { background: var(--accent); color: #0a0a0b; box-shadow: 0 4px 20px var(--accent-glow); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px var(--accent-glow); }
    .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border2); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); background: var(--gold-dim); }
    .btn.btn:not(.btn-primary) { background: transparent; color: var(--text); border: 1px solid var(--border2); }
    .btn:not(.btn-primary):hover { border-color: var(--accent); color: var(--accent); }

    .section-padding { padding: 100px 0; }
    .section-header { text-align: center; margin-bottom: 60px; }
    .section-label { display: inline-block; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.2em; text-transform: uppercase; color: var(--accent); margin-bottom: 16px; }
    .section-header h2 { font-family: 'Cormorant Garamond', serif; font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 400; color: var(--text); line-height: 1.2; letter-spacing: -0.01em; }
    .section-header h2 span { color: var(--accent); font-style: italic; }
    .section-header p { margin-top: 16px; font-size: 1rem; color: var(--text2); max-width: 520px; margin-left: auto; margin-right: auto; line-height: 1.7; }
    .section-divider { height: 1px; background: linear-gradient(90deg, transparent, var(--border2), transparent); margin: 0; }

    .features-section { background: var(--bg2); }
    .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; background: var(--border); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
    .feature-card { background: var(--surface); padding: 48px 40px; position: relative; transition: background var(--transition); overflow: hidden; }
    .feature-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--accent), transparent); transform: scaleX(0); transition: transform 0.4s ease; }
    .feature-card:hover::before { transform: scaleX(1); }
    .feature-card:hover { background: var(--surface2); }
    .feature-icon { width: 60px; height: 60px; border-radius: 14px; background: var(--gold-dim); border: 1px solid rgba(201,168,76,0.2); display: flex; align-items: center; justify-content: center; margin-bottom: 28px; font-size: 1.4rem; color: var(--accent); transition: all 0.3s ease; }
    .feature-card:hover .feature-icon { background: var(--accent); color: #0a0a0b; box-shadow: 0 4px 20px var(--accent-glow); }
    .feature-card h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.4rem; font-weight: 600; color: var(--text); margin-bottom: 12px; }
    .feature-card p { font-size: 0.9rem; color: var(--text2); line-height: 1.7; }

    .testimonial-bg { background: var(--bg); }
    .testimonial-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .testimonial-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 40px; position: relative; transition: all 0.3s ease; overflow: hidden; }
    .testimonial-card::before { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--accent), var(--red)); transform: scaleX(0); transform-origin: left; transition: transform 0.4s ease; }
    .testimonial-card:hover { transform: translateY(-6px); box-shadow: var(--shadow); }
    .testimonial-card:hover::before { transform: scaleX(1); }
    .quote-icon { font-size: 2.5rem; color: var(--accent); opacity: 0.3; margin-bottom: 20px; display: block; }
    .testimonial-card > p { font-size: 0.92rem; color: var(--text2); line-height: 1.8; margin-bottom: 28px; font-style: italic; }
    .user-info { display: flex; align-items: center; gap: 14px; }
    .user-img { width: 46px; height: 46px; border-radius: 50%; background-size: cover; background-position: center; border: 2px solid var(--border2); flex-shrink: 0; }
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

    .listing-section { background: var(--bg2); }
    .filter-bar { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px 24px; margin-bottom: 40px; }
    .filter-bar select { padding: 10px 16px; background: var(--surface2); border: 1px solid var(--border2); border-radius: 8px; color: var(--text); font-size: 0.85rem; font-family: 'DM Sans', sans-serif; outline: none; cursor: pointer; transition: border-color 0.2s; min-width: 160px; }
    .filter-bar select:focus { border-color: var(--accent); }
    .car-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; }
    .car-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: all 0.35s ease; position: relative; }
    .car-card:hover { transform: translateY(-8px); box-shadow: var(--shadow); border-color: var(--border2); }
    .car-img-box { position: relative; aspect-ratio: 16/10; overflow: hidden; }
    .car-img-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; display: block; }
    .car-card:hover .car-img-box img { transform: scale(1.06); }
    .car-img-box:hover .img-view-hint { opacity: 1 !important; }
    .car-img-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 50%); }
    .car-features { position: absolute; bottom: 14px; left: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
    .car-features span { background: rgba(10,10,11,0.75); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.9); font-size: 0.7rem; font-weight: 500; letter-spacing: 0.05em; padding: 5px 10px; border-radius: 20px; display: flex; align-items: center; gap: 5px; }
    .car-type-badge { position: absolute; top: 14px; right: 14px; background: var(--accent); color: #0a0a0b; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 5px 12px; border-radius: 20px; }
    .car-body { padding: 24px; }
    .car-title { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
    .car-title h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 600; color: var(--text); line-height: 1.2; }
    .price { font-size: 0.82rem; font-weight: 600; color: var(--accent); white-space: nowrap; background: var(--gold-dim); border: 1px solid rgba(201,168,76,0.2); padding: 5px 12px; border-radius: 20px; }
    .car-body > p { font-size: 0.85rem; color: var(--text2); line-height: 1.6; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .car-body .btn { width: 100%; justify-content: center; }

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

    #chatBtn { position: fixed; bottom: 28px; right: 28px; z-index: 9999; width: 58px; height: 58px; border-radius: 50%; background: linear-gradient(135deg, var(--gold), #a8722a); border: none; cursor: pointer; box-shadow: 0 4px 24px var(--accent-glow), 0 0 0 0 var(--accent-glow); display: flex; align-items: center; justify-content: center; transition: transform 0.2s, box-shadow 0.2s; animation: chatPulse 3s ease infinite; }
    @keyframes chatPulse { 0%,100% { box-shadow: 0 4px 24px var(--accent-glow), 0 0 0 0 var(--accent-glow); } 50% { box-shadow: 0 4px 24px var(--accent-glow), 0 0 0 12px rgba(201,168,76,0); } }
    #chatBtn:hover { transform: scale(1.1); animation: none; box-shadow: 0 8px 32px var(--accent-glow); }
    #chatBtn i { color: #0a0a0b; font-size: 1.3rem; }
    #chatBtn .badge { position: absolute; top: -4px; right: -4px; background: var(--red); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.6rem; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid var(--bg); }
    #chatWindow { position: fixed; bottom: 100px; right: 28px; z-index: 9998; width: 380px; max-height: 560px; background: var(--surface); border: 1px solid var(--border2); border-radius: 20px; box-shadow: 0 30px 80px rgba(0,0,0,0.4); display: none; flex-direction: column; overflow: hidden; animation: slideUp 0.25s ease; }
    #chatWindow.open { display: flex; }
    @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
    .chat-header { background: linear-gradient(135deg, #18120a, #2a1f0d); padding: 18px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(201,168,76,0.15); }
    .chat-avatar { width: 40px; height: 40px; background: var(--gold-dim); border: 1px solid rgba(201,168,76,0.4); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: var(--gold); flex-shrink: 0; }
    .chat-header-info h4 { color: #f0ede8; font-size: 0.92rem; font-weight: 600; }
    .chat-header-info span { color: rgba(240,237,232,0.5); font-size: 0.72rem; }
    .online-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #22c55e; margin-right: 5px; animation: blink 2s ease infinite; }
    .chat-close { background: none; border: none; color: rgba(240,237,232,0.5); cursor: pointer; font-size: 1rem; padding: 4px; margin-left: auto; transition: color 0.2s; }
    .chat-close:hover { color: #f0ede8; }
    #chatMessages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; background: var(--bg2); }
    #chatMessages::-webkit-scrollbar { width: 3px; } #chatMessages::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
    .msg { display: flex; gap: 8px; align-items: flex-end; max-width: 90%; }
    .msg.bot { align-self: flex-start; } .msg.user { align-self: flex-end; flex-direction: row-reverse; }
    .msg-avatar { width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; }
    .msg.bot .msg-avatar { background: var(--gold-dim); color: var(--gold); } .msg.user .msg-avatar { background: var(--surface2); color: var(--text2); }
    .msg-bubble { padding: 10px 14px; border-radius: 14px; font-size: 0.845rem; line-height: 1.55; max-width: 260px; word-wrap: break-word; }
    .msg.bot .msg-bubble { background: var(--surface); color: var(--text); border-bottom-left-radius: 4px; border: 1px solid var(--border); }
    .msg.user .msg-bubble { background: linear-gradient(135deg, var(--gold), #a8722a); color: #0a0a0b; border-bottom-right-radius: 4px; font-weight: 500; }
    .typing .msg-bubble { padding: 12px 16px; }
    .typing-dots { display: flex; gap: 4px; align-items: center; }
    .typing-dots span { width: 6px; height: 6px; background: var(--text3); border-radius: 50%; animation: bounce 1.2s infinite; }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; } .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce { 0%,60%,100% { transform:translateY(0); } 30% { transform:translateY(-6px); } }
    .quick-replies { display: flex; flex-wrap: wrap; gap: 6px; padding: 10px 14px 6px; background: var(--bg2); border-top: 1px solid var(--border); }
    .qr-btn { background: var(--surface); border: 1px solid var(--border2); border-radius: 20px; padding: 6px 12px; font-size: 0.75rem; color: var(--text2); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-family: 'DM Sans', sans-serif; }
    .qr-btn:hover { background: var(--gold-dim); border-color: var(--accent); color: var(--accent); }
    .chat-input-area { padding: 12px 14px; border-top: 1px solid var(--border); display: flex; gap: 8px; align-items: center; background: var(--surface); }
    #chatInput { flex: 1; border: 1px solid var(--border2); border-radius: 22px; padding: 9px 14px; font-size: 0.875rem; outline: none; transition: border-color 0.2s; background: var(--bg2); color: var(--text); font-family: 'DM Sans', sans-serif; }
    #chatInput:focus { border-color: var(--accent); } #chatInput::placeholder { color: var(--text3); }
    #chatSend { width: 38px; height: 38px; background: linear-gradient(135deg, var(--gold), #a8722a); border: none; border-radius: 50%; color: #0a0a0b; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; }
    #chatSend:hover { transform: scale(1.1); box-shadow: 0 4px 16px var(--accent-glow); }
    #chatSend:disabled { background: var(--surface2); color: var(--text3); cursor: not-allowed; transform: none; }

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
                <div class="hero-badge"><span>Premium Fleet &bull; Sri Lanka</span></div>
                <h1>Find Your<br><em>Perfect Ride</em></h1>
                <p>From luxury sedans to rugged SUVs, find the best deals on car rentals for your next adventure. No hidden charges &mdash; ever.</p>
                <div class="hero-btns">
                    <button class="btn btn-primary" onclick="showPage('listing')"><i class="fa fa-car"></i> Browse Fleet</button>
                    <button class="btn" onclick="showPage('about')">Learn More <i class="fa fa-arrow-right"></i></button>
                </div>
                <div class="hero-stats">
                    <div class="stat-item"><h3><span class="counter" data-target="40">0</span>+</h3><p>Years Experience</p></div>
                    <div class="stat-item"><h3><span class="counter" data-target="1200">0</span>+</h3><p>Active Fleet</p></div>
                    <div class="stat-item"><h3><span class="counter" data-target="24">0</span>/7</h3><p>Support</p></div>
                </div>
            </div>
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
    <section class="section-padding" style="padding-top:120px;">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">Our Story</div>
                <h2>About <span>Our Company</span></h2>
                <p>Delivering excellence since 1984</p>
            </div>
            <div class="about-wrap">
                <div class="about-img reveal">
                    <img src="https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=800" alt="Fleet">
                </div>
                <div class="about-text reveal reveal-delay-1">
                    <h3>Your Trusted Partner for Every Journey</h3>
                    <p>Welcome to CarForYou. We specialize in providing high-quality vehicle rentals that cater to both luxury enthusiasts and budget-conscious travelers.</p>
                    <p>With over four decades of experience, we've learned that customer satisfaction is built on reliability, transparency, and a passion for cars.</p>
                    <div class="about-stats">
                        <div class="about-stat"><h4>40+</h4><p>Years of Experience</p></div>
                        <div class="about-stat"><h4>1200+</h4><p>Active Fleet</p></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- LISTING PAGE -->
<div id="page-listing" class="page-view">
    <section class="section-padding listing-section" style="padding-top:120px;">
        <div class="container">
            <div class="section-header reveal">
                <div class="section-label">Our Fleet</div>
                <h2>Our <span>Car Listing</span></h2>
                <p>Choose from our wide variety of premium vehicles.</p>
            </div>
            <div class="filter-bar reveal">
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
                <button class="btn btn-primary" style="padding:10px 20px;" onclick="applyFilters()">
                    <i class="fa fa-sliders-h"></i> Apply
                </button>
            </div>
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
                <div class="car-card reveal" data-type="<?php echo strtolower($car_type); ?>" data-price="<?php echo $raw_price; ?>">
                    <a href="car_detail.php?id=<?php echo $car_id; ?>" class="car-img-box" style="display:block;text-decoration:none;cursor:zoom-in;" title="View <?php echo $car_name; ?> details">
                        <img src="<?php echo $img_src; ?>" alt="<?php echo $car_name; ?>">
                        <div class="car-img-overlay"></div>
                        <span class="car-type-badge"><?php echo $car_type; ?></span>
                        <div class="car-features">
                            <span><i class="fa <?php echo $fuel_icon; ?>"></i> <?php echo $car_type; ?></span>
                            <span><i class="fa fa-user"></i> <?php echo $seats; ?> Seats</span>
                        </div>
                        <div class="img-view-hint" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.25s;background:rgba(0,0,0,0.28);">
                            <span style="background:rgba(10,10,20,0.75);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,0.15);color:#fff;font-size:0.72rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;padding:8px 16px;border-radius:20px;display:flex;align-items:center;gap:7px;">
                                <i class="fa fa-expand-alt"></i> View Details
                            </span>
                        </div>
                    </a>
                    <div class="car-body">
                        <div class="car-title">
                            <h3><?php echo $car_name; ?></h3>
                            <span class="price">LKR <?php echo $price; ?> /Day</span>
                        </div>
                        <p><?php echo $overview ?: 'Premium vehicle available for rental.'; ?></p>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="users/booking.php?car_id=<?php echo $car_id; ?>" class="btn btn-primary" style="width:100%;justify-content:center;">
                                Book Now <i class="fa fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <a href="users/login.php?redirect=index.php%3Fcar_id%3D<?php echo $car_id; ?>" class="btn btn-primary" style="width:100%;justify-content:center;">
                                Book Now <i class="fa fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                        endwhile;
                    else:
                        echo '<p style="text-align:center;padding:60px;color:var(--text2);">No cars available at the moment.</p>';
                    endif;
                else:
                    echo '<p style="text-align:center;padding:60px;color:var(--red);">Database not connected.</p>';
                endif;
                ?>
            </div>
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
            card.style.display = (typeValue === 'all' || card.getAttribute('data-type') === typeValue.toLowerCase()) ? '' : 'none';
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

<!-- CHATBOT -->
<button id="chatBtn" onclick="toggleChat()" title="Chat with AI Assistant">
    <i class="fa fa-robot"></i>
    <span class="badge">AI</span>
</button>
<div id="chatWindow">
    <div class="chat-header">
        <div class="chat-avatar"><i class="fa fa-robot"></i></div>
        <div class="chat-header-info">
            <h4>CarForYou Assistant</h4>
            <span><span class="online-dot"></span>Online &mdash; Powered by AI</span>
        </div>
        <button class="chat-close" onclick="toggleChat()"><i class="fa fa-times"></i></button>
    </div>
    <div id="chatMessages"></div>
    <div class="quick-replies" id="quickReplies">
        <button class="qr-btn" onclick="sendQuick('Show me all available cars')">&#x1F697; All Cars</button>
        <button class="qr-btn" onclick="sendQuick('What are your premium cars?')">&#x2B50; Premium</button>
        <button class="qr-btn" onclick="sendQuick('What is the cheapest car?')">&#x1F4B0; Budget</button>
        <button class="qr-btn" onclick="sendQuick('Show electric or hybrid cars')">&#x26A1; Eco</button>
        <button class="qr-btn" onclick="sendQuick('How do I book a car?')">&#x1F4CB; How to Book</button>
    </div>
    <div class="chat-input-area">
        <input type="text" id="chatInput" placeholder="Ask about our cars..." onkeydown="if(event.key==='Enter') sendMessage()">
        <button id="chatSend" onclick="sendMessage()"><i class="fa fa-paper-plane"></i></button>
    </div>
</div>

<script>
const CAR_DATA = <?php echo $cars_json; ?>;
let chatOpened = false;
const prices   = () => CAR_DATA.map(c => Number(c.price_per_day));
const maxPrice = () => Math.max(...prices());
const minPrice = () => Math.min(...prices());
const premium  = () => CAR_DATA.filter(c => Number(c.price_per_day) >= maxPrice()*0.65).sort((a,b)=>b.price_per_day-a.price_per_day);
const budget   = () => CAR_DATA.filter(c => Number(c.price_per_day) <= minPrice()*1.8).sort((a,b)=>a.price_per_day-b.price_per_day);
const fmt      = n  => 'LKR ' + Number(n).toLocaleString();
function carLine(car){ return `&#x1F697; <strong>${car.car_name}</strong> (${car.car_model||''}) &mdash; ${fmt(car.price_per_day)}/day | ${car.car_type} | ${car.seating_capacity||4} seats`; }

function getBotReply(msg) {
    const t = msg.toLowerCase().trim();
    if (/^(hi|hello|hey|good (morning|evening|afternoon)|howdy)/.test(t)) return "&#x1F44B; Hi there! Welcome to CarForYou. I can help you find a car, check prices, or compare vehicles. What are you looking for?";
    if (/thank|thanks|thank you/.test(t)) return "&#x1F60A; You're welcome! Feel free to ask if you need anything else. Happy driving!";
    if (/how.*(book|rent|reserve|hire)|booking process|steps to/.test(t)) return "&#x1F4CB; <strong>How to book:</strong><br>1. Go to <strong>Car Listing</strong> from the top menu<br>2. Browse and click <strong>Book Now</strong> on your chosen car<br>3. Login if you haven't already<br>4. Pick your dates and confirm!<br><br>Need help choosing a car?";
    if (/all car|full list|show.*car|available car|what car|fleet|every car|list.*car/.test(t)) {
        if (CAR_DATA.length === 0) return "&#x1F614; No cars are available right now. Please check back later.";
        let reply = `&#x1F698; We have <strong>${CAR_DATA.length} cars</strong> available:<br><br>`;
        CAR_DATA.forEach(c => { reply += carLine(c) + '<br>'; });
        reply += '<br>&#x1F449; Go to <strong>Car Listing</strong> to Book Now!';
        return reply;
    }
    if (/premium|luxury|best|top|expensive|high.?end|vip|fancy/.test(t)) {
        const p = premium(); if (p.length === 0) return "No premium cars found at the moment.";
        let reply = `&#x2B50; <strong>Our Premium Cars:</strong><br><br>`; p.slice(0,4).forEach(c => { reply += carLine(c) + '<br>'; });
        reply += `<br>These are our top-tier vehicles. Visit <strong>Car Listing</strong> to book!`; return reply;
    }
    if (/cheap|budget|affordable|low.?price|cheapest|economical|least expensive|low cost/.test(t)) {
        const b = budget(); if (b.length === 0) return "No budget cars found at the moment.";
        let reply = `&#x1F4B0; <strong>Budget-Friendly Cars:</strong><br><br>`; b.slice(0,4).forEach(c => { reply += carLine(c) + '<br>'; });
        reply += `<br>Great value options! Head to <strong>Car Listing</strong> to book.`; return reply;
    }
    if (/electric|ev|zero emission/.test(t)) {
        const ev = CAR_DATA.filter(c => /electric/i.test(c.car_type)); if (ev.length === 0) return "&#x26A1; We don't have any electric cars available right now. Check back soon!";
        let reply = `&#x26A1; <strong>Electric Cars:</strong><br><br>`; ev.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
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
        const p = CAR_DATA.filter(c => /petrol/i.test(c.car_type)); if (p.length === 0) return "No petrol cars available right now.";
        let reply = `&#x26FD; <strong>Petrol Cars:</strong><br><br>`; p.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
    }
    if (/diesel/.test(t)) {
        const d = CAR_DATA.filter(c => /diesel/i.test(c.car_type)); if (d.length === 0) return "No diesel cars available right now.";
        let reply = `&#x1F6E2;&#xFE0F; <strong>Diesel Cars:</strong><br><br>`; d.forEach(c => { reply += carLine(c) + '<br>'; }); return reply;
    }
    if (/price|cost|rate|how much|per day|daily rate|charges/.test(t)) {
        if (CAR_DATA.length === 0) return "No pricing info available right now.";
        let reply = `&#x1F4B5; <strong>Our Price Range:</strong><br><br>&bull; Lowest: <strong>${fmt(minPrice())}/day</strong><br>&bull; Highest: <strong>${fmt(maxPrice())}/day</strong><br><br><strong>All Cars by Price:</strong><br>`;
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
        let reply = `&#x1F60A; Happy to help! Here's a quick recommendation:<br><br>`;
        if (p.length > 0) reply += `&#x2B50; <strong>Premium pick:</strong> ${p[0].car_name} &mdash; ${fmt(p[0].price_per_day)}/day<br>`;
        if (b.length > 0) reply += `&#x1F4B0; <strong>Budget pick:</strong> ${b[0].car_name} &mdash; ${fmt(b[0].price_per_day)}/day<br>`;
        reply += `<br>Tell me your budget or purpose (trip, wedding, family) and I'll narrow it down!`; return reply;
    }
    if (/contact|phone|call|email|address|location|office|reach/.test(t)) return `&#x1F4DE; <strong>Contact CarForYou:</strong><br><br>&#x1F4F1; Phone: +94 75 45 57 624<br>&#x2709;&#xFE0F; Email: amafzhar@gmail.com<br>&#x1F4CD; 37 Kinniya, Trincomalee, Sri Lanka<br><br>Or use the <strong>Contact Us</strong> page from the menu!`;
    if (/about|company|who are you|carforyou|history|experience/.test(t)) return `&#x1F3E2; <strong>About CarForYou:</strong><br><br>We are a premium car rental company in Sri Lanka with <strong>40+ years</strong> of experience and a fleet of <strong>1200+ vehicles</strong>.<br><br>We offer fully insured cars, 24/7 support, and the best prices &mdash; no hidden charges!`;
    if (/open|hours|timing|when|available|24/.test(t)) return `&#x1F550; We offer <strong>24/7 support</strong> for our customers. You can book a car anytime through our website!`;
    if (/bye|goodbye|see you|later|take care/.test(t)) return "&#x1F44B; Goodbye! Have a great drive. Come back anytime &mdash; CarForYou is always here for you! &#x1F697;";
    return `&#x1F914; I'm not sure about that, but I can help you with:<br><br>&#x1F697; <strong>All Cars</strong> &mdash; type "show all cars"<br>&#x2B50; <strong>Premium Cars</strong> &mdash; type "premium cars"<br>&#x1F4B0; <strong>Budget Cars</strong> &mdash; type "cheap cars"<br>&#x1F4B5; <strong>Prices</strong> &mdash; type "what are your prices"<br>&#x1F4CB; <strong>Booking</strong> &mdash; type "how to book"<br>&#x1F4DE; <strong>Contact</strong> &mdash; type "contact info"<br><br>Or browse our <strong>Car Listing</strong> page directly!`;
}

function toggleChat() {
    const win = document.getElementById('chatWindow');
    win.classList.toggle('open');
    if (win.classList.contains('open') && !chatOpened) {
        chatOpened = true;
        setTimeout(() => addMessage('bot', "&#x1F44B; Hi! I'm the CarForYou assistant. I can help you find cars, check prices, and answer questions &mdash; all for free! What are you looking for today?"), 300);
    }
    if (win.classList.contains('open')) document.getElementById('chatInput').focus();
}
function addMessage(role, text) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    div.innerHTML = '<div class="msg-avatar">' + (role === 'bot' ? '&#x1F916;' : '&#x1F464;') + '</div><div class="msg-bubble">' + text + '</div>';
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}
function showTyping() {
    const c = document.getElementById('chatMessages'), d = document.createElement('div');
    d.className = 'msg bot typing'; d.id = 'typingIndicator';
    d.innerHTML = '<div class="msg-avatar">&#x1F916;</div><div class="msg-bubble"><div class="typing-dots"><span></span><span></span><span></span></div></div>';
    c.appendChild(d); c.scrollTop = c.scrollHeight;
}
function removeTyping() { const el = document.getElementById('typingIndicator'); if (el) el.remove(); }
function sendQuick(text) { document.getElementById('chatInput').value = text; document.getElementById('quickReplies').style.display = 'none'; sendMessage(); }
function sendMessage() {
    const input = document.getElementById('chatInput'), text = input.value.trim();
    if (!text) return;
    document.getElementById('quickReplies').style.display = 'none';
    addMessage('user', text); input.value = ''; input.disabled = true; document.getElementById('chatSend').disabled = true;
    showTyping();
    setTimeout(() => { removeTyping(); addMessage('bot', getBotReply(text)); input.disabled = false; document.getElementById('chatSend').disabled = false; input.focus(); }, 600);
}
</script>

</body>
</html>