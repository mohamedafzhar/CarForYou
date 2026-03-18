<?php
/**
 * users/testimonial.php
 * User submits a star rating + review for a returned booking.
 * Only accessible if booking status = 3 (Returned) and no review yet submitted.
 */
session_start();
require_once('../includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? $_SESSION['fname'] ?? 'User';
$initial   = strtoupper(substr($user_name, 0, 1));

// ── Validate booking_id param ─────────────────────────────────────────────────
$booking_id = intval($_GET['booking_id'] ?? 0);
if (!$booking_id) { header("Location: user_booking.php"); exit(); }

// ── Fetch booking — must be Returned (status=3) and belong to this user ───────
// First get user's email
$email_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$email_stmt->bind_param("i", $user_id);
$email_stmt->execute();
$user_email = $email_stmt->get_result()->fetch_assoc()['email'] ?? '';

if (!$user_email) {
    header("Location: user_booking.php"); exit();
}

$stmt = $conn->prepare("
    SELECT b.id, b.car_id, b.from_date, b.to_date, b.status, b.return_status,
           c.car_name, c.car_type, c.Vimage1
    FROM booking b
    JOIN cars c ON c.id = b.car_id
    WHERE b.id = ? AND b.user_email = ? AND b.return_status = 'returned'
");
$stmt->bind_param("is", $booking_id, $user_email);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    // Not eligible — redirect back
    header("Location: user_booking.php?error=Review not available for this booking.");
    exit();
}

// ── Check if review already submitted for this booking ────────────────────────
$check = $conn->prepare("SELECT id FROM testimonials WHERE booking_id = ?");
$check->bind_param("i", $booking_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header("Location: user_booking.php?info=You already reviewed this booking.");
    exit();
}

$msg   = '';
$error = '';

// ── Handle submission ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a star rating.";
    } elseif (strlen($review) < 10) {
        $error = "Please write at least 10 characters in your review.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO testimonials
                (booking_id, user_id, user_name, car_id, car_name, rating, review, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->bind_param(
            "iisisis",
            $booking_id, $user_id, $user_name,
            $booking['car_id'], $booking['car_name'],
            $rating, $review
        );
        if ($stmt->execute()) {
            $msg = "submitted";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

$car_img = !empty($booking['Vimage1'])
    ? "../admin/img/vehicleimages/" . htmlspecialchars($booking['Vimage1'])
    : "https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=400&q=60";

$days = max(1, ceil((strtotime($booking['to_date']) - strtotime($booking['from_date'])) / 86400));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write a Review | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    [data-theme="dark"] {
        --bg:#0b0e14; --surface:#141920; --surface2:#1a2030; --surface3:#1f2638;
        --border:rgba(255,255,255,0.06); --border2:rgba(255,255,255,0.1);
        --text:#f0f2f8; --text2:#8892a4; --text3:#44505e;
        --accent:#00d4ff; --accent2:#0090ff;
        --accentglow:rgba(0,212,255,0.18); --accentbg:rgba(0,212,255,0.06);
        --green:#00e676; --greenbg:rgba(0,230,118,0.08);
        --red:#ff4f4f; --redbg:rgba(255,79,79,0.08);
        --amber:#fbbf24;
        --sbg:#0a0d12; --sborder:rgba(255,255,255,0.05); --hbg:rgba(11,14,20,0.88);
    }
    [data-theme="light"] {
        --bg:#f0f4f8; --surface:#ffffff; --surface2:#f5f8fc; --surface3:#eaf0f8;
        --border:rgba(0,0,0,0.07); --border2:rgba(0,0,0,0.12);
        --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
        --accent:#0077cc; --accent2:#0055aa;
        --accentglow:rgba(0,119,204,0.16); --accentbg:rgba(0,119,204,0.07);
        --green:#059669; --greenbg:rgba(5,150,105,0.08);
        --red:#dc2626; --redbg:rgba(220,38,38,0.07);
        --amber:#d97706;
        --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06); --hbg:rgba(240,244,248,0.9);
    }

    body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; transition:background 0.35s,color 0.35s; }
    ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:var(--bg);} ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px;}
    a{text-decoration:none;color:inherit;}

    /* SIDEBAR */
    .sidebar{width:240px;min-height:100vh;background:var(--sbg);border-right:1px solid var(--sborder);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
    .sb-brand{padding:26px 22px 18px;border-bottom:1px solid var(--sborder);}
    .sb-brand a{display:flex;align-items:center;gap:10px;text-decoration:none;}
    .sb-logo{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.88rem;color:#fff;box-shadow:0 0 14px var(--accentglow);flex-shrink:0;}
    .sb-brand-text{font-size:1.1rem;font-weight:800;color:#e8edf5;letter-spacing:-0.02em;}
    .sb-brand-text span{color:var(--accent);}
    .sb-section{font-size:0.6rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:rgba(232,237,245,0.22);padding:20px 22px 6px;}
    .sb-nav{list-style:none;padding:6px 10px;}
    .sb-nav li{margin-bottom:2px;}
    .sb-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;font-size:0.85rem;font-weight:500;color:rgba(232,237,245,0.45);transition:all 0.2s;}
    .sb-nav a i{width:16px;text-align:center;font-size:0.82rem;}
    .sb-nav a:hover{background:rgba(0,212,255,0.07);color:rgba(232,237,245,0.85);}
    .sb-nav a.active{background:linear-gradient(90deg,rgba(0,212,255,0.15),rgba(0,212,255,0.04));color:var(--accent);font-weight:600;box-shadow:inset 3px 0 0 var(--accent);}
    .sb-nav a.logout{color:rgba(255,79,79,0.6);}
    .sb-nav a.logout:hover{background:rgba(255,79,79,0.08);color:#ff4f4f;}
    .sb-divider{height:1px;background:var(--sborder);margin:10px 2px;}
    .sb-user-card{margin:10px;padding:14px;background:var(--surface);border:1px solid var(--border);border-radius:12px;}
    .sb-user-card .uav{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.9rem;font-weight:800;color:#fff;margin-bottom:10px;box-shadow:0 0 12px var(--accentglow);}
    .sb-user-card .uname{font-size:0.82rem;font-weight:700;color:var(--text);}
    .sb-user-card .urole{font-size:0.68rem;color:var(--text3);margin-top:2px;}

    /* MAIN */
    .main{margin-left:240px;width:calc(100% - 240px);min-height:100vh;display:flex;flex-direction:column;}
    .top-bar{position:sticky;top:0;z-index:50;background:var(--hbg);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;}
    .tb-left h2{font-size:1.05rem;font-weight:700;color:var(--text);}
    .tb-left p{font-size:0.72rem;color:var(--text2);margin-top:1px;}
    .tb-right{display:flex;align-items:center;gap:10px;}
    .theme-btn{width:36px;height:36px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.85rem;transition:all 0.2s;}
    .theme-btn:hover{border-color:var(--accent);color:var(--accent);}
    .tb-avatar{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.82rem;font-weight:800;color:#fff;text-decoration:none;}
    .back-btn{display:inline-flex;align-items:center;gap:7px;font-size:0.78rem;font-weight:600;color:var(--text2);border:1px solid var(--border2);border-radius:8px;padding:7px 14px;text-decoration:none;transition:all 0.2s;}
    .back-btn:hover{border-color:var(--accent);color:var(--accent);}

    /* BODY */
    .body{padding:26px 32px;flex:1;display:flex;flex-direction:column;align-items:center;}

    /* SUCCESS STATE */
    .success-wrap{display:flex;flex-direction:column;align-items:center;text-align:center;padding:60px 20px;max-width:480px;opacity:0;animation:fadeUp 0.5s ease forwards;}
    .success-icon{width:80px;height:80px;border-radius:50%;background:var(--greenbg);border:2px solid rgba(0,230,118,0.25);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--green);margin-bottom:22px;box-shadow:0 0 30px rgba(0,230,118,0.15);}
    .success-wrap h2{font-size:1.4rem;font-weight:800;color:var(--text);margin-bottom:10px;}
    .success-wrap p{font-size:0.88rem;color:var(--text2);line-height:1.7;margin-bottom:28px;}
    .btn-back{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-family:'Outfit',sans-serif;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 4px 14px var(--accentglow);transition:all 0.22s;}
    .btn-back:hover{opacity:0.88;transform:translateY(-1px);}

    /* REVIEW FORM */
    .review-wrap{width:100%;max-width:620px;opacity:0;animation:fadeUp 0.5s ease 0.05s forwards;}

    /* Page header */
    .page-header{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:22px;position:relative;overflow:hidden;}
    .page-header::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--accent),var(--accent2),transparent);opacity:0.5;}
    .page-header::after{content:'';position:absolute;right:-30px;top:-30px;width:160px;height:160px;background:radial-gradient(circle,var(--accentglow),transparent 70%);pointer-events:none;}
    .ph-text h2{font-size:1.3rem;font-weight:800;color:var(--text);}
    .ph-text h2 span{background:linear-gradient(90deg,var(--accent),var(--accent2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
    .ph-text p{font-size:0.82rem;color:var(--text2);margin-top:4px;}

    /* Car summary card */
    .car-summary{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;display:flex;gap:0;margin-bottom:20px;}
    .cs-img{width:160px;flex-shrink:0;}
    .cs-img img{width:100%;height:100%;object-fit:cover;display:block;}
    .cs-info{padding:18px 20px;flex:1;}
    .cs-name{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:4px;}
    .cs-type{font-size:0.72rem;color:var(--text3);margin-bottom:12px;}
    .cs-chips{display:flex;gap:8px;flex-wrap:wrap;}
    .cs-chip{font-size:0.68rem;font-weight:600;color:var(--text2);background:var(--surface2);border:1px solid var(--border);padding:3px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:5px;}
    .cs-chip i{color:var(--accent);font-size:0.62rem;}

    /* Alert */
    .alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:0.84rem;font-weight:500;}
    .alert-error{background:var(--redbg);border:1px solid rgba(255,79,79,0.2);color:var(--red);}

    /* Form card */
    .form-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
    .form-card-head{padding:16px 22px;border-bottom:1px solid var(--border);font-size:0.88rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
    .form-card-head i{color:var(--accent);}
    .form-body{padding:24px;}

    /* Star rating */
    .star-label{font-size:0.65rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text3);margin-bottom:12px;display:block;}
    .stars-wrap{display:flex;gap:8px;margin-bottom:24px;}
    .star-btn{background:none;border:none;cursor:pointer;padding:4px;transition:transform 0.15s;}
    .star-btn:hover{transform:scale(1.15);}
    .star-btn svg{width:36px;height:36px;fill:var(--surface3);transition:fill 0.15s;}
    .star-btn.active svg, .star-btn.hover svg{fill:var(--amber);}
    .star-hint{font-size:0.78rem;color:var(--text3);margin-left:8px;align-self:center;transition:color 0.2s;}

    /* Review textarea */
    .field{margin-bottom:20px;}
    .field label{font-size:0.65rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text3);display:block;margin-bottom:8px;}
    .field label i{color:var(--accent);margin-right:4px;}
    .field textarea{width:100%;padding:13px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-family:'Outfit',sans-serif;font-size:0.875rem;resize:vertical;min-height:120px;outline:none;transition:all 0.2s;}
    .field textarea::placeholder{color:var(--text3);}
    .field textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accentbg);background:var(--surface3);}
    .char-count{font-size:0.7rem;color:var(--text3);text-align:right;margin-top:4px;}

    /* Submit */
    .submit-btn{width:100%;padding:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;border-radius:11px;color:#fff;font-family:'Outfit',sans-serif;font-size:0.88rem;font-weight:800;letter-spacing:0.04em;cursor:pointer;transition:all 0.22s;box-shadow:0 4px 16px var(--accentglow);display:flex;align-items:center;justify-content:center;gap:8px;}
    .submit-btn:hover{opacity:0.88;transform:translateY(-1px);}

    @keyframes fadeUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}
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
        <li><a href="user_booking.php" class="active"><i class="fa fa-calendar-check"></i> My Bookings</a></li>
        <li><a href="profile.php"><i class="fa fa-user"></i> Profile</a></li>
        <li class="sb-divider"></li>
        <li><a href="../index.php"><i class="fa fa-arrow-left"></i> Back to Site</a></li>
        <li><a href="logout.php" class="logout"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
    <div style="flex:1;"></div>
    <div style="padding:12px;">
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
            <h2>Write a Review</h2>
            <p id="dateLabel"></p>
        </div>
        <div class="tb-right">
            <a href="user_booking.php" class="back-btn"><i class="fa fa-arrow-left"></i> My Bookings</a>
            <button class="theme-btn" id="themeBtn"><i class="fa fa-moon" id="themeIcon"></i></button>
            <a href="profile.php" class="tb-avatar"><?php echo $initial; ?></a>
        </div>
    </div>

    <div class="body">

    <?php if ($msg === 'submitted'): ?>
        <!-- SUCCESS STATE -->
        <div class="success-wrap">
            <div class="success-icon"><i class="fa fa-star"></i></div>
            <h2>Thank You for Your Review!</h2>
            <p>Your review for <strong><?php echo htmlspecialchars($booking['car_name']); ?></strong> has been submitted and is pending approval. It will appear on our website once reviewed by our team.</p>
            <a href="user_booking.php" class="btn-back"><i class="fa fa-arrow-left"></i> Back to My Bookings</a>
        </div>

    <?php else: ?>
        <div class="review-wrap">

            <!-- Page header -->
            <div class="page-header">
                <div class="ph-text">
                    <h2>Share Your <span>Experience</span></h2>
                    <p>Your honest review helps other customers and helps us improve our service.</p>
                </div>
            </div>

            <!-- Car summary -->
            <div class="car-summary">
                <div class="cs-img">
                    <img src="<?php echo $car_img; ?>" alt="<?php echo htmlspecialchars($booking['car_name']); ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=400&q=60'">
                </div>
                <div class="cs-info">
                    <div class="cs-name"><?php echo htmlspecialchars($booking['car_name']); ?></div>
                    <div class="cs-type"><?php echo htmlspecialchars($booking['car_type']); ?></div>
                    <div class="cs-chips">
                        <span class="cs-chip"><i class="fa fa-calendar"></i><?php echo date('d M Y', strtotime($booking['from_date'])); ?></span>
                        <span class="cs-chip"><i class="fa fa-calendar-check"></i><?php echo date('d M Y', strtotime($booking['to_date'])); ?></span>
                        <span class="cs-chip"><i class="fa fa-clock"></i><?php echo $days; ?> day<?php echo $days!=1?'s':''; ?></span>
                        <span class="cs-chip" style="color:var(--green);border-color:rgba(0,230,118,0.2);background:var(--greenbg);"><i class="fa fa-circle-check" style="color:var(--green);"></i>Returned</span>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Review form -->
            <div class="form-card">
                <div class="form-card-head"><i class="fa fa-pen-to-square"></i> Your Review</div>
                <form method="POST" class="form-body" id="reviewForm">
                    <input type="hidden" name="rating" id="ratingInput" value="0">

                    <!-- Star selector -->
                    <span class="star-label">Your Rating <span style="color:var(--red);">*</span></span>
                    <div style="display:flex;align-items:center;margin-bottom:24px;">
                        <div class="stars-wrap" id="starsWrap">
                            <?php for ($i=1;$i<=5;$i++): ?>
                            <button type="button" class="star-btn" data-val="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            </button>
                            <?php endfor; ?>
                        </div>
                        <span class="star-hint" id="starHint">Click to rate</span>
                    </div>

                    <!-- Review text -->
                    <div class="field">
                        <label><i class="fa fa-comment-dots"></i> Your Review <span style="color:var(--red);">*</span></label>
                        <textarea name="review" id="reviewText" maxlength="600"
                            placeholder="Tell us about your experience — the car condition, pick-up process, overall satisfaction..."
                            oninput="updateCount()"><?php echo htmlspecialchars($_POST['review'] ?? ''); ?></textarea>
                        <div class="char-count"><span id="charCount">0</span> / 600 characters</div>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fa fa-paper-plane"></i> Submit Review
                    </button>
                </form>
            </div>

        </div>
    <?php endif; ?>

    </div>
</div>

<script>
    // Date
    (function(){var d=new Date(),D=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];document.getElementById('dateLabel').textContent=D[d.getDay()]+', '+d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear();})();

    // Theme
    var theme=localStorage.getItem('cfyTheme')||'dark';
    document.documentElement.setAttribute('data-theme',theme);syncIcon();
    document.getElementById('themeBtn').addEventListener('click',function(){theme=theme==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',theme);localStorage.setItem('cfyTheme',theme);syncIcon();});
    function syncIcon(){document.getElementById('themeIcon').className=theme==='dark'?'fa fa-moon':'fa fa-sun';}

    // Star rating
    var currentRating = <?php echo intval($_POST['rating'] ?? 0); ?>;
    var hints = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent!'];

    function setRating(val) {
        currentRating = val;
        document.getElementById('ratingInput').value = val;
        document.getElementById('starHint').textContent = hints[val];
        document.getElementById('starHint').style.color = 'var(--amber)';
        renderStars(val, val);
    }

    function renderStars(active, hover) {
        document.querySelectorAll('.star-btn').forEach(function(btn) {
            var v = parseInt(btn.dataset.val);
            btn.classList.toggle('active', v <= active);
            btn.classList.toggle('hover', v <= hover && v > active);
        });
    }

    // Hover effect
    document.querySelectorAll('.star-btn').forEach(function(btn) {
        btn.addEventListener('mouseenter', function() { renderStars(currentRating, parseInt(btn.dataset.val)); });
        btn.addEventListener('mouseleave', function() { renderStars(currentRating, currentRating); });
    });

    // Init if re-rendered after error
    if (currentRating > 0) setRating(currentRating);

    // Char counter
    function updateCount() {
        var len = document.getElementById('reviewText').value.length;
        document.getElementById('charCount').textContent = len;
    }
    updateCount();

    // Form validation before submit
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        if (currentRating < 1) {
            e.preventDefault();
            alert('Please select a star rating before submitting.');
        }
    });
</script>
</body>
</html>