<?php
session_start();
require_once('admin/config.php');

$car_id = intval($_GET['id'] ?? 0);
if (!$car_id) { header("Location: index.php"); exit(); }

$stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();
if (!$car) { header("Location: index.php"); exit(); }

// Check if car is available for booking
$is_available = ($car['status'] === 'Available');
$is_booked = ($car['status'] === 'Booked');

// Get current booking info if booked
$current_booking = null;
if ($is_booked) {
    $bk_stmt = $conn->prepare("
        SELECT from_date, to_date FROM booking 
        WHERE car_id = ? AND status IN ('confirmed', 'awaiting_payment') 
        AND to_date >= CURDATE() 
        ORDER BY from_date ASC LIMIT 1
    ");
    $bk_stmt->bind_param("i", $car_id);
    $bk_stmt->execute();
    $current_booking = $bk_stmt->get_result()->fetch_assoc();
    $bk_stmt->close();
}

// ── Build image array from all 4 DB slots, fall back to stock only if empty ───
$stock_fallback = "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=1200";

$images = [];
foreach ([1, 2, 3, 4] as $n) {
    $col = "Vimage{$n}";
    if (!empty($car[$col])) {
        $images[] = "admin/img/vehicleimages/" . htmlspecialchars($car[$col]);
    }
}

// If the car has NO images at all, show one stock placeholder
if (empty($images)) {
    $images[] = $stock_fallback;
}

// The rest of the file is UNCHANGED from the original car_detail.php
// Only the $images build block above has changed.

$car_name  = htmlspecialchars($car['car_name']);
$car_type  = htmlspecialchars($car['car_type']);
$car_model = htmlspecialchars($car['car_model'] ?? 'N/A');
$car_brand = htmlspecialchars($car['car_brand'] ?? '');
$price     = number_format($car['price_per_day']);
$overview  = htmlspecialchars($car['car_overview'] ?? '');
$seats     = intval($car['seating_capacity'] ?? 4);

$fuel_icon = ($car_type === 'Electric') ? 'fa-bolt' : ($car_type === 'Hybrid' ? 'fa-leaf' : 'fa-gas-pump');

$sim_res = $conn->prepare("SELECT id, car_name, car_type, price_per_day, Vimage1 FROM cars WHERE status='Available' AND id != ? LIMIT 3");
$sim_res->bind_param("i", $car_id);
$sim_res->execute();
$similar = $sim_res->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $car_name; ?> | CarForYou</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
    /* ═══════════════════════════════════════
       THEME VARIABLES — identical to index.php
    ═══════════════════════════════════════ */
    :root { --transition: 0.4s cubic-bezier(0.4,0,0.2,1); }

    [data-theme="dark"] {
        --bg:#0d1117; --bg2:#131920; --bg3:#1a2230;
        --surface:#1e2738; --surface2:#253044;
        --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.15);
        --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
        --accent:#4f8ef7; --accent2:#7db0fb;
        --accent-glow:rgba(79,142,247,0.25);
        --gold-dim:rgba(79,142,247,0.12);
        --nav-bg:rgba(13,17,23,0.88);
        --card-bg:#1e2738; --glass:rgba(30,39,56,0.85);
        --shadow:0 20px 60px rgba(0,0,0,0.5);
        --hero-overlay:linear-gradient(to right,#0d1117 42%,transparent);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --bg2:#e8edf3; --bg3:#dde3ec;
        --surface:#ffffff; --surface2:#f5f7fa;
        --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
        --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
        --accent:#2563eb; --accent2:#3b82f6;
        --accent-glow:rgba(37,99,235,0.18);
        --gold-dim:rgba(37,99,235,0.1);
        --nav-bg:rgba(240,244,248,0.92);
        --card-bg:#ffffff; --glass:rgba(255,255,255,0.88);
        --shadow:0 12px 40px rgba(28,43,58,0.1);
        --hero-overlay:linear-gradient(to right,#f0f4f8 42%,transparent);
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior:smooth; font-size:16px; }
    body {
        font-family:'DM Sans',sans-serif;
        background:var(--bg); color:var(--text);
        overflow-x:hidden;
        transition:background var(--transition),color var(--transition);
    }
    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--bg); }
    ::-webkit-scrollbar-thumb { background:var(--accent); border-radius:3px; }
    a { text-decoration:none; color:inherit; }
    .container { max-width:1280px; margin:0 auto; padding:0 40px; }

    /* ── NAV ── */
    header {
        position:fixed; top:0; left:0; right:0; z-index:1000;
        background:var(--nav-bg);
        backdrop-filter:blur(20px) saturate(180%);
        border-bottom:1px solid var(--border);
        height:72px; display:flex; align-items:center;
        transition:all var(--transition);
    }
    header.scrolled { height:64px; box-shadow:0 4px 30px rgba(0,0,0,0.3); }
    nav { display:flex; align-items:center; justify-content:space-between; gap:24px; width:100%; }
    .logo h2 {
        font-family:'Cormorant Garamond',serif;
        font-weight:700; font-size:1.75rem; letter-spacing:0.02em; color:var(--text);
    }
    .logo h2 span { color:var(--accent); font-style:italic; }
    .nav-right { display:flex; align-items:center; gap:14px; }
    .back-btn {
        display:inline-flex; align-items:center; gap:8px;
        padding:9px 18px; border-radius:6px;
        border:1px solid var(--border2); color:var(--text2);
        font-size:0.8rem; font-weight:600; letter-spacing:0.06em; text-transform:uppercase;
        transition:all 0.25s;
    }
    .back-btn:hover { border-color:var(--accent); color:var(--accent); background:var(--gold-dim); }
    .btn-login {
        display:inline-flex; align-items:center; gap:7px;
        font-size:0.78rem; font-weight:600;
        letter-spacing:0.06em; text-transform:uppercase;
        padding:9px 20px; border-radius:6px;
        border:1px solid var(--accent); color:var(--accent);
        background:transparent; transition:all 0.25s;
    }
    .btn-login:hover { background:var(--accent); color:#fff; box-shadow:0 0 20px var(--accent-glow); }
    .theme-toggle {
        width:38px; height:38px; border-radius:50%;
        border:1px solid var(--border2); background:var(--surface);
        color:var(--text2); cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        font-size:0.9rem; transition:all 0.25s;
    }
    .theme-toggle:hover { border-color:var(--accent); color:var(--accent); box-shadow:0 0 12px var(--accent-glow); }

    /* ═══════════════════════════════════════
       HERO GALLERY
    ═══════════════════════════════════════ */
    .gallery-hero {
        position:relative; height:100vh; min-height:600px;
        display:flex; align-items:center;
        overflow:hidden; margin-top:72px;
    }
    .gallery-track { position:absolute; inset:0; display:flex; }
    .gallery-slide {
        min-width:100%; height:100%; position:relative;
        transition:transform 0.9s cubic-bezier(0.77,0,0.175,1);
    }
    .gallery-slide img {
        width:100%; height:100%; object-fit:cover; display:block;
        transition:transform 6s ease;
    }
    .gallery-slide.active img { transform:scale(1.06); }
    .gallery-overlay {
        position:absolute; inset:0;
        background:var(--hero-overlay);
        pointer-events:none; z-index:1;
    }
    .gallery-overlay-bottom {
        position:absolute; bottom:0; left:0; right:0; height:260px;
        background:linear-gradient(to top,var(--bg),transparent);
        pointer-events:none; z-index:1;
    }

    /* Thumbnail strip — only shown when more than 1 image */
    .thumb-strip {
        position:absolute; bottom:28px; right:40px; z-index:10;
        display:flex; gap:10px;
    }
    .thumb {
        width:80px; height:54px; border-radius:8px; overflow:hidden; cursor:pointer;
        border:2px solid transparent; transition:all 0.3s; opacity:0.55;
    }
    .thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .thumb.active { border-color:var(--accent); opacity:1; box-shadow:0 0 16px var(--accent-glow); }
    .thumb:hover { opacity:0.85; }

    .slide-counter {
        position:absolute; bottom:36px; left:40px; z-index:10;
        display:flex; align-items:center; gap:10px;
    }
    .slide-dots { display:flex; gap:6px; }
    .dot {
        width:6px; height:6px; border-radius:50%;
        background:var(--border2); cursor:pointer; transition:all 0.3s;
    }
    .dot.active { background:var(--accent); width:20px; border-radius:3px; }

    .gallery-arrow {
        position:absolute; top:50%; transform:translateY(-50%); z-index:10;
        width:44px; height:44px; border-radius:50%;
        background:var(--glass); border:1px solid var(--border2);
        color:var(--text2); cursor:pointer; font-size:0.9rem;
        display:flex; align-items:center; justify-content:center;
        transition:all 0.25s; backdrop-filter:blur(8px);
    }
    .gallery-arrow:hover { background:var(--accent); color:#fff; border-color:var(--accent); box-shadow:0 0 16px var(--accent-glow); }
    .gallery-arrow.prev { left:24px; }
    .gallery-arrow.next { right:24px; }

    /* Hide arrows + thumbs when only 1 image */
    .single-image .gallery-arrow,
    .single-image .thumb-strip,
    .single-image .slide-counter { display:none; }

    .hero-info {
        position:absolute; top:0; left:0; bottom:0; width:50%; z-index:5;
        display:flex; flex-direction:column; justify-content:center;
        padding:0 60px; animation:fadeInLeft 0.8s ease both;
    }
    @keyframes fadeInLeft { from{opacity:0;transform:translateX(-30px);} to{opacity:1;transform:translateX(0);} }
    .hero-badge {
        display:inline-flex; align-items:center; gap:8px;
        background:var(--gold-dim); border:1px solid rgba(79,142,247,0.3); color:var(--accent);
        font-size:0.7rem; font-weight:600; letter-spacing:0.14em; text-transform:uppercase;
        padding:6px 14px; border-radius:20px; margin-bottom:20px; width:fit-content;
    }
    .hero-badge::before { content:''; width:6px; height:6px; border-radius:50%; background:var(--accent); animation:blink 2s ease infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
    .hero-info h1 {
        font-family:'Cormorant Garamond',serif;
        font-size:clamp(2.8rem,5vw,5rem); font-weight:300; line-height:1.05;
        letter-spacing:-0.02em; color:var(--text); margin-bottom:12px;
    }
    .hero-info h1 em { font-style:italic; color:var(--accent); font-weight:400; }
    .hero-meta { display:flex; align-items:center; gap:14px; margin-bottom:28px; flex-wrap:wrap; }
    .meta-chip {
        display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px;
        background:rgba(255,255,255,0.06); border:1px solid var(--border2);
        font-size:0.74rem; font-weight:500; color:var(--text2);
    }
    .meta-chip i { color:var(--accent); font-size:0.72rem; }
    .hero-price {
        font-family:'Cormorant Garamond',serif;
        font-size:3rem; font-weight:600; color:var(--accent); line-height:1; margin-bottom:6px;
    }
    .hero-price span { font-size:1.1rem; color:var(--text3); font-family:'DM Sans',sans-serif; font-weight:400; }
    .hero-btns { display:flex; gap:12px; flex-wrap:wrap; margin-top:28px; }

    /* ── BUTTONS ── */
    .btn {
        display:inline-flex; align-items:center; gap:8px; padding:14px 30px; border-radius:6px;
        font-size:0.82rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase;
        cursor:pointer; border:none; transition:all 0.25s; position:relative; overflow:hidden;
    }
    .btn::after { content:''; position:absolute; inset:0; background:rgba(255,255,255,0.1); transform:translateX(-100%); transition:transform 0.3s; }
    .btn:hover::after { transform:translateX(0); }
    .btn-primary { background:var(--accent); color:#fff; box-shadow:0 4px 20px var(--accent-glow); }
    .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 30px var(--accent-glow); }
    .btn-outline { background:transparent; color:var(--text); border:1px solid var(--border2); }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); background:var(--gold-dim); }
    
    .booked-notice {
        display:flex; align-items:center; gap:14px;
        background:rgba(251,191,36,0.1); border:1px solid rgba(251,191,36,0.25);
        border-radius:8px; padding:14px 20px;
        color:var(--amber);
    }
    .booked-notice i { font-size:1.4rem; }
    .booked-notice div { display:flex; flex-direction:column; gap:2px; }
    .booked-notice strong { font-size:0.88rem; font-weight:700; color:var(--amber); }
    .booked-notice span { font-size:0.78rem; color:var(--text2); }
    
    /* ═══════════════════════════════════════
       DETAILS SECTION
    ═══════════════════════════════════════ */
    .details-section { padding:72px 0; background:var(--bg); }
    .details-grid { display:grid; grid-template-columns:1.1fr 1fr; gap:56px; align-items:start; }
    .section-label { font-size:0.68rem; font-weight:600; letter-spacing:0.2em; text-transform:uppercase; color:var(--accent); display:block; margin-bottom:12px; }
    .specs-title { font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400; color:var(--text); margin-bottom:28px; letter-spacing:-0.01em; }
    .specs-title span { color:var(--accent); font-style:italic; }
    .specs-grid { display:grid; grid-template-columns:1fr 1fr; gap:2px; background:var(--border); border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:28px; }
    .spec-cell { background:var(--surface); padding:20px 22px; transition:background 0.2s; cursor:default; }
    .spec-cell:hover { background:var(--surface2); }
    .spec-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text3); margin-bottom:6px; }
    .spec-val { font-family:'Cormorant Garamond',serif; font-size:1.35rem; font-weight:600; color:var(--text); display:flex; align-items:center; gap:8px; }
    .spec-val i { color:var(--accent); font-size:0.85rem; }
    .overview-box { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:24px 26px; margin-bottom:28px; }
    .overview-box p { font-size:0.93rem; color:var(--text2); line-height:1.8; }

    /* ── Pricing card ── */
    .pricing-card { background:var(--surface); border:1px solid var(--border2); border-radius:16px; overflow:hidden; position:sticky; top:90px; }
    .pricing-card-header { padding:28px 28px 22px; background:linear-gradient(135deg,var(--gold-dim),transparent); border-bottom:1px solid var(--border); position:relative; }
    .pricing-card-header::after { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--accent),var(--accent2)); }
    .pricing-label { font-size:0.68rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--text3); margin-bottom:8px; }
    .pricing-amount { font-family:'Cormorant Garamond',serif; font-size:3.2rem; font-weight:600; color:var(--accent); line-height:1; }
    .pricing-amount span { font-size:1rem; color:var(--text3); font-family:'DM Sans',sans-serif; font-weight:400; }
    .pricing-note { font-size:0.75rem; color:var(--text3); margin-top:6px; }
    .pricing-body { padding:24px 28px; }
    .perk-list { list-style:none; margin-bottom:24px; }
    .perk-list li { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--border); font-size:0.84rem; color:var(--text2); }
    .perk-list li:last-child { border-bottom:none; }
    .perk-list li i { color:var(--accent); width:14px; flex-shrink:0; }
    .perk-list li strong { color:var(--text); }
    .book-btn-full {
        display:flex; align-items:center; justify-content:center; gap:10px;
        width:100%; padding:15px; background:var(--accent); color:#fff;
        border:none; border-radius:10px; font-family:'DM Sans',sans-serif;
        font-size:0.88rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase;
        cursor:pointer; transition:all 0.25s; box-shadow:0 4px 20px var(--accent-glow);
        text-decoration:none; margin-bottom:12px;
    }
    .book-btn-full:hover { transform:translateY(-2px); box-shadow:0 8px 28px var(--accent-glow); opacity:0.92; }
    .login-prompt { text-align:center; font-size:0.78rem; color:var(--text3); padding-top:4px; }
    .login-prompt a { color:var(--accent); font-weight:600; }
    .login-prompt a:hover { text-decoration:underline; }

    /* ═══════════════════════════════════════
       SIMILAR CARS
    ═══════════════════════════════════════ */
    .similar-section { padding:64px 0; background:var(--bg2); }
    .similar-header { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:32px; flex-wrap:wrap; gap:12px; }
    .similar-header h2 { font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400; color:var(--text); }
    .similar-header h2 span { color:var(--accent); font-style:italic; }
    .view-fleet { font-size:0.78rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:var(--accent); padding:7px 16px; border-radius:6px; border:1px solid var(--border2); transition:all 0.2s; }
    .view-fleet:hover { background:var(--gold-dim); }
    .similar-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }
    .sim-card { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; overflow:hidden; transition:all 0.3s; cursor:pointer; }
    .sim-card:hover { transform:translateY(-6px); box-shadow:var(--shadow); border-color:var(--border2); }
    .sim-img { aspect-ratio:16/10; overflow:hidden; position:relative; }
    .sim-img img { width:100%; height:100%; object-fit:cover; display:block; transition:transform 0.5s; }
    .sim-card:hover .sim-img img { transform:scale(1.06); }
    .sim-img-overlay { position:absolute; inset:0; background:linear-gradient(to top,rgba(0,0,0,0.55),transparent 50%); }
    .sim-badge { position:absolute; top:12px; right:12px; background:var(--accent); color:#fff; font-size:0.62rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; padding:4px 10px; border-radius:20px; }
    .sim-body { padding:18px 20px; }
    .sim-body h4 { font-family:'Cormorant Garamond',serif; font-size:1.2rem; font-weight:600; color:var(--text); margin-bottom:8px; }
    .sim-footer { display:flex; justify-content:space-between; align-items:center; }
    .sim-price { font-size:0.82rem; font-weight:600; color:var(--accent); }
    .sim-link { font-size:0.72rem; font-weight:600; color:var(--text3); letter-spacing:0.06em; text-transform:uppercase; display:flex; align-items:center; gap:4px; transition:color 0.2s; }
    .sim-card:hover .sim-link { color:var(--accent); }

    /* ── FOOTER ── */
    footer { background:var(--bg2); border-top:1px solid var(--border); padding:60px 0 32px; }
    .footer-grid { display:grid; grid-template-columns:1.5fr 1fr 1fr; gap:60px; margin-bottom:48px; }
    .footer-col > p { margin-top:16px; font-size:0.88rem; color:var(--text2); line-height:1.7; max-width:280px; }
    .footer-col h4 { font-size:0.72rem; font-weight:600; letter-spacing:0.15em; text-transform:uppercase; color:var(--text3); margin-bottom:20px; }
    .footer-col ul { list-style:none; }
    .footer-col ul li { margin-bottom:12px; }
    .footer-col ul li a { font-size:0.88rem; color:var(--text2); transition:color 0.2s; display:flex; align-items:center; gap:8px; }
    .footer-col ul li a:hover { color:var(--accent); }
    .footer-bottom { border-top:1px solid var(--border); padding-top:28px; display:flex; justify-content:space-between; align-items:center; }
    .footer-bottom p { font-size:0.82rem; color:var(--text3); }

    .reveal { opacity:0; transform:translateY(28px); transition:opacity 0.7s ease,transform 0.7s ease; }
    .reveal.visible { opacity:1; transform:translateY(0); }
    .reveal-d1{transition-delay:0.1s;} .reveal-d2{transition-delay:0.2s;} .reveal-d3{transition-delay:0.3s;}

    @media(max-width:1024px){ .details-grid{grid-template-columns:1fr;} .hero-info{width:65%;padding:0 40px;} .similar-grid{grid-template-columns:1fr 1fr;} .footer-grid{grid-template-columns:1fr 1fr;} }
    @media(max-width:768px){ .container{padding:0 20px;} .gallery-hero{height:70vh;} .hero-info{width:100%;padding:80px 20px 20px;background:linear-gradient(to top,var(--bg),transparent);bottom:0;top:auto;height:60%;justify-content:flex-end;} .hero-info h1{font-size:2.2rem;} .thumb-strip{right:12px;bottom:80px;} .thumb{width:54px;height:38px;} .similar-grid{grid-template-columns:1fr;} .footer-grid{grid-template-columns:1fr;gap:32px;} }
    </style>
</head>
<body>

<!-- ── HEADER ── -->
<header id="siteHeader">
    <div class="container">
        <nav>
            <div class="logo">
                <a href="index.php"><h2>Car<span>ForYou</span></h2></a>
            </div>
            <div class="nav-right">
                <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="users/logout.php" class="btn-login"><i class="fa fa-sign-out"></i> Logout</a>
                <?php else: ?>
                    <a href="users/login.php" class="btn-login"><i class="fa fa-sign-in"></i> Login</a>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle" title="Toggle theme">
                    <i class="fa fa-moon" id="themeIcon"></i>
                </button>
            </div>
        </nav>
    </div>
</header>

<!-- ── HERO GALLERY ── -->
<div class="gallery-hero <?php echo count($images) === 1 ? 'single-image' : ''; ?>" id="galleryHero">

    <div class="gallery-track" id="galleryTrack">
        <?php foreach ($images as $idx => $img): ?>
        <div class="gallery-slide <?php echo $idx===0 ? 'active' : ''; ?>" id="slide-<?php echo $idx; ?>">
            <img src="<?php echo $img; ?>" alt="<?php echo $car_name; ?> view <?php echo $idx+1; ?>"
                 onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=1200'">
        </div>
        <?php endforeach; ?>
    </div>

    <div class="gallery-overlay"></div>
    <div class="gallery-overlay-bottom"></div>

    <div class="hero-info">
        <div class="hero-badge"><?php echo $car_type; ?> Vehicle</div>
        <h1>
            <?php
            $parts = explode(' ', $car_name, 2);
            echo htmlspecialchars($parts[0]);
            if (!empty($parts[1])) echo '<br><em>' . htmlspecialchars($parts[1]) . '</em>';
            ?>
        </h1>
        <div class="hero-meta">
            <?php if ($car_brand): ?>
            <span class="meta-chip"><i class="fa fa-building"></i> <?php echo $car_brand; ?></span>
            <?php endif; ?>
            <span class="meta-chip"><i class="fa fa-calendar"></i> <?php echo $car_model; ?></span>
            <span class="meta-chip"><i class="fa fa-users"></i> <?php echo $seats; ?> Seats</span>
            <span class="meta-chip"><i class="fa <?php echo $fuel_icon; ?>"></i> <?php echo $car_type; ?></span>
        </div>
        <div class="hero-price">LKR <?php echo $price; ?> <span>/ day</span></div>
        <div class="hero-btns">
            <?php if ($is_available): ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="users/booking.php?car_id=<?php echo $car_id; ?>" class="btn btn-primary">
                        <i class="fa fa-calendar-check"></i> Book Now
                    </a>
                <?php else: ?>
                    <a href="users/login.php?redirect=car_detail.php%3Fid%3D<?php echo $car_id; ?>" class="btn btn-primary">
                        <i class="fa fa-calendar-check"></i> Book Now
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="booked-notice">
                    <i class="fa fa-calendar-clock"></i>
                    <div>
                        <strong>Currently Reserved</strong>
                        <?php if ($current_booking): ?>
                        <span>Available after <?php echo date('d M Y', strtotime($current_booking['to_date'])); ?></span>
                        <?php else: ?>
                        <span>Returning soon</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <a href="#details" class="btn btn-outline"><i class="fa fa-info-circle"></i> Full Details</a>
        </div>
    </div>

    <button class="gallery-arrow prev" onclick="slideGallery(-1)"><i class="fa fa-chevron-left"></i></button>
    <button class="gallery-arrow next" onclick="slideGallery(1)"><i class="fa fa-chevron-right"></i></button>

    <div class="thumb-strip" id="thumbStrip">
        <?php foreach ($images as $idx => $img): ?>
        <div class="thumb <?php echo $idx===0?'active':''; ?>" onclick="goToSlide(<?php echo $idx; ?>)">
            <img src="<?php echo $img; ?>" alt="thumb <?php echo $idx+1; ?>"
                 onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=120'">
        </div>
        <?php endforeach; ?>
    </div>

    <div class="slide-counter">
        <div class="slide-dots" id="slideDots">
            <?php for ($i=0;$i<count($images);$i++): ?>
            <div class="dot <?php echo $i===0?'active':''; ?>" onclick="goToSlide(<?php echo $i; ?>)"></div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ── DETAILS SECTION ── -->
<section class="details-section" id="details">
    <div class="container">
        <div class="details-grid">
            <div>
                <span class="section-label reveal">Vehicle Specifications</span>
                <h2 class="specs-title reveal">Full <span>Details</span></h2>
                <div class="specs-grid reveal">
                    <div class="spec-cell"><div class="spec-label">Brand</div><div class="spec-val"><i class="fa fa-building"></i> <?php echo $car_brand ?: 'N/A'; ?></div></div>
                    <div class="spec-cell"><div class="spec-label">Model Year</div><div class="spec-val"><i class="fa fa-calendar"></i> <?php echo $car_model; ?></div></div>
                    <div class="spec-cell"><div class="spec-label">Fuel Type</div><div class="spec-val"><i class="fa <?php echo $fuel_icon; ?>"></i> <?php echo $car_type; ?></div></div>
                    <div class="spec-cell"><div class="spec-label">Seating Capacity</div><div class="spec-val"><i class="fa fa-users"></i> <?php echo $seats; ?> Persons</div></div>
                    <div class="spec-cell"><div class="spec-label">Availability</div><div class="spec-val" style="color:#22c55e;"><i class="fa fa-circle-check" style="color:#22c55e;"></i> Available</div></div>
                    <div class="spec-cell"><div class="spec-label">Price Per Day</div><div class="spec-val"><i class="fa fa-tag"></i> LKR <?php echo $price; ?></div></div>
                </div>
                <?php if ($overview): ?>
                <div class="overview-box reveal">
                    <span class="section-label" style="font-size:0.62rem;margin-bottom:8px;">About This Car</span>
                    <p><?php echo nl2br($overview); ?></p>
                </div>
                <?php endif; ?>
                <div class="overview-box reveal" style="background:var(--surface2);">
                    <span class="section-label" style="font-size:0.62rem;margin-bottom:10px;">What's Included</span>
                    <ul style="list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <li style="display:flex;align-items:center;gap:8px;font-size:0.84rem;color:var(--text2);"><i class="fa fa-shield-alt" style="color:var(--accent);width:14px;"></i> Full Insurance</li>
                        <li style="display:flex;align-items:center;gap:8px;font-size:0.84rem;color:var(--text2);"><i class="fa fa-gas-pump" style="color:var(--accent);width:14px;"></i> Full Tank</li>
                        <li style="display:flex;align-items:center;gap:8px;font-size:0.84rem;color:var(--text2);"><i class="fa fa-headset" style="color:var(--accent);width:14px;"></i> 24/7 Roadside Help</li>
                        <li style="display:flex;align-items:center;gap:8px;font-size:0.84rem;color:var(--text2);"><i class="fa fa-snowflake" style="color:var(--accent);width:14px;"></i> Climate Control</li>
                        <li style="display:flex;align-items:center;gap:8px;font-size:0.84rem;color:var(--text2);"><i class="fa fa-bluetooth" style="color:var(--accent);width:14px;"></i> Bluetooth Audio</li>
                        <li style="display:flex;align-items:center;gap:8px;font-size:0.84rem;color:var(--text2);"><i class="fa fa-ban" style="color:var(--accent);width:14px;"></i> No Hidden Fees</li>
                    </ul>
                </div>
            </div>

            <div class="reveal reveal-d1">
                <div class="pricing-card">
                    <div class="pricing-card-header">
                        <div class="pricing-label">Daily Rental Rate</div>
                        <div class="pricing-amount">LKR <?php echo $price; ?> <span>/ day</span></div>
                        <div class="pricing-note"><i class="fa fa-circle-check" style="color:#22c55e;margin-right:5px;"></i>No hidden charges · Fully insured</div>
                    </div>
                    <div class="pricing-body">
                        <ul class="perk-list">
                            <li><i class="fa fa-shield-halved"></i><span><strong>Fully Insured</strong> — All vehicles covered</span></li>
                            <li><i class="fa fa-clock"></i><span><strong>24/7 Support</strong> — Always available</span></li>
                            <li><i class="fa fa-check"></i><span><strong>Free Cancellation</strong> — Up to 24 hrs</span></li>
                            <li><i class="fa fa-users"></i><span><strong><?php echo $seats; ?> Passengers</strong> — Comfortable for all</span></li>
                            <li><i class="fa <?php echo $fuel_icon; ?>"></i><span><strong><?php echo $car_type; ?></strong> — Full tank included</span></li>
                        </ul>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="users/booking.php?car_id=<?php echo $car_id; ?>" class="book-btn-full">
                                <i class="fa fa-calendar-check"></i> Reserve This Car
                            </a>
                        <?php else: ?>
                            <a href="users/login.php?redirect=car_detail.php%3Fid%3D<?php echo $car_id; ?>" class="book-btn-full">
                                <i class="fa fa-lock"></i> Login to Book
                            </a>
                            <p class="login-prompt">Don't have an account? <a href="users/login.php?tab=register">Sign up free</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── SIMILAR CARS ── -->
<?php if ($similar && $similar->num_rows > 0): ?>
<section class="similar-section">
    <div class="container">
        <div class="similar-header reveal">
            <h2>You Might Also <span>Like</span></h2>
            <a href="index.php" class="view-fleet">View Full Fleet <i class="fa fa-arrow-right" style="font-size:0.7rem;"></i></a>
        </div>
        <div class="similar-grid">
            <?php $di=1; while ($s = $similar->fetch_assoc()):
                $si = !empty($s['Vimage1']) ? "admin/img/vehicleimages/" . htmlspecialchars($s['Vimage1']) : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=600";
            ?>
            <div class="sim-card reveal reveal-d<?php echo $di; ?>" onclick="window.location='car_detail.php?id=<?php echo $s['id']; ?>'">
                <div class="sim-img">
                    <img src="<?php echo $si; ?>" alt="<?php echo htmlspecialchars($s['car_name']); ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=600'">
                    <div class="sim-img-overlay"></div>
                    <span class="sim-badge"><?php echo htmlspecialchars($s['car_type']); ?></span>
                </div>
                <div class="sim-body">
                    <h4><?php echo htmlspecialchars($s['car_name']); ?></h4>
                    <div class="sim-footer">
                        <span class="sim-price">LKR <?php echo number_format($s['price_per_day']); ?>/day</span>
                        <span class="sim-link">View Details <i class="fa fa-arrow-right"></i></span>
                    </div>
                </div>
            </div>
            <?php $di++; endwhile; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── FOOTER ── -->
<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <div class="logo"><h2 style="color:var(--text);">Car<span style="color:var(--accent);font-style:italic;">ForYou</span></h2></div>
                <p>A leading car rental provider dedicated to giving you the best driving experience across the country since 1984.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php">Car Listing</a></li>
                    <li><a href="users/login.php">Login / Register</a></li>
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
            <p style="font-size:0.78rem;">Designed with ♥ in Sri Lanka</p>
        </div>
    </div>
</footer>

<script>
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncThemeIcon();
    document.getElementById('themeToggle').addEventListener('click', function(){
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        syncThemeIcon();
    });
    function syncThemeIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }

    window.addEventListener('scroll', function(){
        document.getElementById('siteHeader').classList.toggle('scrolled', window.scrollY > 40);
    });

    var totalSlides = <?php echo count($images); ?>;
    var currentSlide = 0;
    var autoSlideTimer;

    function goToSlide(idx) {
        document.querySelectorAll('.gallery-slide')[currentSlide].classList.remove('active');
        document.querySelectorAll('.thumb')[currentSlide]?.classList.remove('active');
        document.querySelectorAll('.dot')[currentSlide]?.classList.remove('active');
        currentSlide = (idx + totalSlides) % totalSlides;
        document.getElementById('galleryTrack').style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
        document.getElementById('galleryTrack').style.transition = 'transform 0.9s cubic-bezier(0.77,0,0.175,1)';
        document.querySelectorAll('.gallery-slide')[currentSlide].classList.add('active');
        document.querySelectorAll('.thumb')[currentSlide]?.classList.add('active');
        document.querySelectorAll('.dot')[currentSlide]?.classList.add('active');
        resetTimer();
    }
    function slideGallery(dir) { goToSlide(currentSlide + dir); }
    function startAutoSlide() { if (totalSlides > 1) autoSlideTimer = setInterval(function(){ goToSlide(currentSlide + 1); }, 3500); }
    function resetTimer() { clearInterval(autoSlideTimer); startAutoSlide(); }

    // Init absolute positioning for slides
    document.getElementById('galleryTrack').style.transform = 'translateX(0)';
    document.querySelectorAll('.gallery-slide').forEach(function(sl, i){
        sl.style.position = 'absolute';
        sl.style.left = (i * 100) + '%';
        sl.style.top = '0'; sl.style.width = '100%'; sl.style.height = '100%';
    });
    document.getElementById('galleryTrack').style.position = 'absolute';
    document.getElementById('galleryTrack').style.inset = '0';
    startAutoSlide();

    document.getElementById('galleryHero').addEventListener('mouseenter', function(){ clearInterval(autoSlideTimer); });
    document.getElementById('galleryHero').addEventListener('mouseleave', startAutoSlide);

    var touchStartX = 0;
    document.getElementById('galleryHero').addEventListener('touchstart', function(e){ touchStartX = e.touches[0].clientX; });
    document.getElementById('galleryHero').addEventListener('touchend', function(e){
        var diff = touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) slideGallery(diff > 0 ? 1 : -1);
    });

    function revealCheck(){
        document.querySelectorAll('.reveal:not(.visible)').forEach(function(el){
            if (el.getBoundingClientRect().top < window.innerHeight - 60) el.classList.add('visible');
        });
    }
    window.addEventListener('scroll', revealCheck);
    window.addEventListener('load', revealCheck);
    setTimeout(revealCheck, 200);
</script>
</body>
</html> 