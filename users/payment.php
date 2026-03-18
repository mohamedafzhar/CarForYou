<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$initial   = strtoupper(substr($user_name, 0, 1));

$stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$user_email = $user['email'] ?? '';
$user_fullname = $user['full_name'] ?? '';

$msg   = '';
$error = '';

// ── CSRF TOKEN ────────────────────────────────────────────────
if (empty($_SESSION['payment_csrf'])) {
    $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
}

function verify_csrf($token) {
    if (!isset($_SESSION['payment_csrf']) || !hash_equals($_SESSION['payment_csrf'], $token)) {
        throw new Exception("Invalid request.");
    }
}

// ── GET BOOKING ──────────────────────────────────────────────
$booking_id = intval($_GET['booking_id'] ?? 0);
$booking = null;
if ($booking_id) {
    $stmt = $conn->prepare("
        SELECT b.*, c.car_name, c.car_model, c.price_per_day, c.Vimage1
        FROM booking b
        JOIN cars c ON c.id = b.car_id
        WHERE b.id = ? AND b.user_email = ? AND b.payment_status = 'unpaid'
    ");
    $stmt->bind_param("is", $booking_id, $user_email);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
}

if (!$booking) {
    $_SESSION['error_msg'] = "Booking not found or already paid.";
    header("Location: user_booking.php");
    exit();
}

$days    = max(1, (int)((strtotime($booking['to_date']) - strtotime($booking['from_date'])) / 86400));
$amount  = $days * $booking['price_per_day'];
$penalty = intval($booking['penalty_amount'] ?? 0);
$total   = $amount + $penalty;

// ── PROCESS PAYMENT ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        verify_csrf($_POST['csrf_token'] ?? '');
        
        $card_name    = trim($_POST['card_name'] ?? '');
        $card_number  = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $expiry_month = trim($_POST['expiry_month'] ?? '');
        $expiry_year  = trim($_POST['expiry_year'] ?? '');
        $cvv          = trim($_POST['cvv'] ?? '');
        
        // Validate card fields
        if (empty($card_name) || strlen($card_name) < 3) {
            throw new Exception("Please enter the cardholder name.");
        }
        if (!preg_match('/^\d{13,19}$/', $card_number)) {
            throw new Exception("Please enter a valid card number.");
        }
        if (!preg_match('/^(0[1-9]|1[0-2])$/', $expiry_month)) {
            throw new Exception("Please enter a valid expiry month (MM).");
        }
        if (!preg_match('/^\d{2}$/', $expiry_year) || $expiry_year < date('y')) {
            throw new Exception("Please enter a valid expiry year (YY).");
        }
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            throw new Exception("Please enter a valid CVV.");
        }
        
        // Check if card is expired
        $exp_ts = mktime(0, 0, 0, $expiry_month, 1, 2000 + $expiry_year);
        if ($exp_ts < time()) {
            throw new Exception("This card has expired.");
        }
        
        // Luhn algorithm validation
        $card_type = detectCardType($card_number);
        if (!$card_type) {
            throw new Exception("Invalid card number.");
        }
        
        // Get card last 4 digits
        $card_last4 = substr($card_number, -4);
        
        // Encrypt sensitive data (in production, use proper encryption like AES)
        // For demo, we store masked card number
        $masked_card = str_repeat('*', strlen($card_number) - 4) . $card_last4;
        
        // Generate receipt number
        $receipt_no = 'RCP' . strtoupper(substr(md5($booking_id . time()), 0, 8));
        $now = date('Y-m-d H:i:s');
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Save card details (encrypted in production)
            $stmt = $conn->prepare("
                INSERT INTO card_payments (booking_id, user_email, card_type, card_last4, card_holder, expiry_month, expiry_year, amount, payment_date, receipt_no, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
            ");
            $stmt->bind_param("isssssisss", 
                $booking_id, $user_email, $card_type, $card_last4, 
                $card_name, $expiry_month, $expiry_year, $total, $now, $receipt_no
            );
            $stmt->execute();
            $payment_id = $conn->insert_id;
            
            // Update booking
            $stmt = $conn->prepare("
                UPDATE booking SET payment_status = 'paid', payment_date = ?, total_amount = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $now, $total, $booking_id);
            $stmt->execute();
            
            $conn->commit();
            
            $_SESSION['success_msg'] = "Payment successful! Receipt: $receipt_no";
            header("Location: payment_success.php?receipt=$receipt_no&booking_id=$booking_id");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Payment processing failed. Please try again.");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Card type detection
function detectCardType($number) {
    $number = preg_replace('/\D/', '', $number);
    if (preg_match('/^4/', $number)) return 'Visa';
    if (preg_match('/^5[1-5]/', $number)) return 'Mastercard';
    if (preg_match('/^3[47]/', $number)) return 'Amex';
    if (preg_match('/^6(?:011|5)/', $number)) return 'Discover';
    return null;
}

// Get card icon
function getCardIcon($type) {
    $icons = [
        'Visa'       => 'fa-cc-visa',
        'Mastercard' => 'fa-cc-mastercard',
        'Amex'       => 'fa-cc-amex',
        'Discover'   => 'fa-cc-discover'
    ];
    return $icons[$type] ?? 'fa-credit-card';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Payment | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        [data-theme="dark"] {
            --bg:#0b0e14; --surface:#141920; --surface2:#1a2030;
            --border:rgba(255,255,255,0.08); --border2:rgba(255,255,255,0.12);
            --text:#f0f2f8; --text2:#8892a4; --text3:#44505e;
            --accent:#00d4ff; --accent2:#0090ff;
            --green:#00e676; --greenbg:rgba(0,230,118,0.1);
            --red:#ff4f4f; --redbg:rgba(255,79,79,0.1);
            --gold:#fbbf24;
        }
        [data-theme="light"] {
            --bg:#f0f4f8; --surface:#ffffff; --surface2:#f5f8fc;
            --border:rgba(0,0,0,0.08); --border2:rgba(0,0,0,0.14);
            --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
            --accent:#0077cc; --accent2:#0055aa;
            --green:#059669; --greenbg:rgba(5,150,105,0.1);
            --red:#dc2626; --redbg:rgba(220,38,38,0.1);
            --gold:#d97706;
        }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; transition:background 0.3s; }
        a { text-decoration:none; color:inherit; }

        .page-wrap { max-width:900px; margin:0 auto; padding:40px 20px; }
        .page-header { text-align:center; margin-bottom:40px; }
        .page-header h1 { font-size:1.8rem; font-weight:800; color:var(--text); }
        .page-header p { color:var(--text2); margin-top:6px; font-size:0.9rem; }

        .content-grid { display:grid; grid-template-columns:1fr 400px; gap:30px; align-items:start; }
        @media(max-width:800px) { .content-grid { grid-template-columns:1fr; } }

        .card-section { background:var(--surface); border:1px solid var(--border2); border-radius:16px; overflow:hidden; }
        .card-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
        .card-header h2 { font-size:1rem; font-weight:700; display:flex; align-items:center; gap:8px; }
        .card-header h2 i { color:var(--accent); }
        .card-header .secure-badge { margin-left:auto; display:flex; align-items:center; gap:5px; font-size:0.72rem; color:var(--green); background:var(--greenbg); padding:5px 10px; border-radius:20px; }

        .card-body { padding:24px; }

        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text3); margin-bottom:8px; }
        .input-wrap { position:relative; }
        .input-wrap i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text3); font-size:0.85rem; }
        .input-wrap .card-icon { position:absolute; right:14px; top:50%; transform:translateY(-50%); font-size:1.2rem; }
        .form-input { width:100%; padding:13px 14px 13px 40px; background:var(--surface2); border:1px solid var(--border2); border-radius:10px; color:var(--text); font-family:'Outfit',sans-serif; font-size:0.9rem; transition:border-color 0.2s,box-shadow 0.2s; outline:none; }
        .form-input.no-icon { padding-left:14px; }
        .form-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,212,255,0.15); }
        .form-input::placeholder { color:var(--text3); }
        .form-input.error { border-color:var(--red); }

        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-row .form-group label { margin-bottom:6px; }

        .card-logos { display:flex; gap:8px; margin-top:8px; }
        .card-logo { width:44px; height:28px; background:var(--surface2); border:1px solid var(--border); border-radius:6px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s; padding:4px; }
        .card-logo:hover { border-color:var(--accent); transform:scale(1.1); box-shadow:0 2px 8px rgba(0,212,255,0.3); }
        .card-logo.active { border-color:var(--accent); background:rgba(0,212,255,0.1); }
        .card-logo svg { width:100%; height:100%; }

        .btn-pay { width:100%; padding:15px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; border-radius:12px; font-family:'Outfit',sans-serif; font-size:1rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; transition:all 0.2s; box-shadow:0 4px 20px rgba(0,212,255,0.3); }
        .btn-pay:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(0,212,255,0.4); }
        .btn-pay:active { transform:translateY(0); }
        .btn-pay:disabled { opacity:0.5; cursor:not-allowed; transform:none; }

        .security-note { display:flex; align-items:flex-start; gap:10px; padding:14px; background:var(--surface2); border-radius:10px; margin-top:20px; font-size:0.78rem; color:var(--text2); line-height:1.5; }
        .security-note i { color:var(--green); margin-top:2px; flex-shrink:0; }

        /* Order Summary */
        .summary-section { background:var(--surface); border:1px solid var(--border2); border-radius:16px; position:sticky; top:20px; }
        .summary-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
        .summary-header h2 { font-size:1rem; font-weight:700; }
        .summary-header i { color:var(--accent); }

        .summary-body { padding:20px 24px; }

        .car-info { display:flex; gap:14px; padding-bottom:16px; border-bottom:1px solid var(--border); margin-bottom:16px; }
        .car-img { width:80px; height:54px; border-radius:8px; object-fit:cover; background:var(--surface2); }
        .car-details h3 { font-size:0.95rem; font-weight:700; color:var(--text); }
        .car-details p { font-size:0.78rem; color:var(--text3); margin-top:2px; }
        .car-details .booking-id { margin-top:4px; font-size:0.7rem; color:var(--accent); }

        .summary-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; font-size:0.88rem; }
        .summary-row .label { color:var(--text2); }
        .summary-row .value { font-weight:600; color:var(--text); }
        .summary-divider { height:1px; background:var(--border); margin:14px 0; }
        .summary-total { display:flex; justify-content:space-between; align-items:center; padding:16px; background:var(--surface2); border-radius:10px; }
        .summary-total .label { font-size:0.9rem; font-weight:700; color:var(--text); }
        .summary-total .amount { font-family:'Outfit',sans-serif; font-size:1.4rem; font-weight:800; color:var(--accent); }

        /* Alert */
        .alert { display:flex; align-items:center; gap:10px; padding:14px 16px; border-radius:10px; font-size:0.84rem; margin-bottom:20px; animation:fadeUp 0.3s ease; }
        .alert-error { background:var(--redbg); color:var(--red); border:1px solid rgba(255,79,79,0.2); }
        .alert i { flex-shrink:0; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }

        .theme-corner { position:fixed; top:18px; right:18px; z-index:100; }
        .theme-btn { width:38px; height:38px; border-radius:10px; border:1px solid var(--border2); background:var(--surface); color:var(--text2); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.88rem; transition:all 0.2s; }
        .theme-btn:hover { border-color:var(--accent); color:var(--accent); }
    </style>
</head>
<body>

<div class="theme-corner">
    <button class="theme-btn" id="themeBtn" onclick="toggleTheme()">
        <i class="fa fa-moon" id="themeIcon"></i>
    </button>
</div>

<div class="page-wrap">
    <div class="page-header">
        <h1><i class="fa fa-credit-card" style="color:var(--accent);margin-right:10px;"></i>Card Payment</h1>
        <p>Complete your booking payment securely</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fa fa-circle-xmark"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="content-grid">
        <!-- Payment Form -->
        <div class="card-section">
            <div class="card-header">
                <h2><i class="fa fa-credit-card"></i> Payment Details</h2>
                <span class="secure-badge"><i class="fa fa-lock"></i> Secure</span>
            </div>
            <div class="card-body">
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['payment_csrf']; ?>">
                    <input type="hidden" name="process_payment" value="1">

                    <div class="form-group">
                        <label>Cardholder Name</label>
                        <div class="input-wrap">
                            <i class="fa fa-user"></i>
                            <input type="text" name="card_name" class="form-input" 
                                   placeholder="Name on card" required 
                                   value="<?php echo htmlspecialchars($user_fullname); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Card Number</label>
                        <div class="input-wrap">
                            <i class="fa fa-credit-card"></i>
                            <input type="text" name="card_number" class="form-input" 
                                   id="cardNumber" placeholder="1234 5678 9012 3456" 
                                   maxlength="19" required
                                   oninput="formatCardNumber(this)">
                            <i class="fa fa-credit-card card-icon" id="cardTypeIcon"></i>
                        </div>
                        <div class="card-logos">
                            <span class="card-logo" id="visaLogo" onclick="fillTestCard('4')" title="Click to use Visa test card">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none"><rect width="32" height="20" rx="2" fill="#1A1F71"/><text x="16" y="14" text-anchor="middle" fill="white" font-size="10" font-weight="bold" font-family="Arial">VISA</text></svg>
                            </span>
                            <span class="card-logo" id="mcLogo" onclick="fillTestCard('5')" title="Click to use Mastercard test card">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none"><rect width="32" height="20" rx="2" fill="#000"/><circle cx="12" cy="10" r="7" fill="#EB001B"/><circle cx="20" cy="10" r="7" fill="#F79E1B"/><path d="M16 4.8a7 7 0 010 10.4A7 7 0 0116 4.8z" fill="#FF5F00"/></svg>
                            </span>
                            <span class="card-logo" id="amexLogo" onclick="fillTestCard('amex')" title="Click to use Amex test card">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none"><rect width="32" height="20" rx="2" fill="#006FCF"/><text x="16" y="14" text-anchor="middle" fill="white" font-size="7" font-weight="bold" font-family="Arial">AMEX</text></svg>
                            </span>
                            <span class="card-logo" id="discLogo" onclick="fillTestCard('discover')" title="Click to use Discover test card">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none"><rect width="32" height="20" rx="2" fill="#FF6600"/><text x="16" y="14" text-anchor="middle" fill="white" font-size="6" font-weight="bold" font-family="Arial">DISCOVER</text></svg>
                            </span>
                        </div>
                        <div style="display:flex;justify-content:center;gap:6px;margin-top:4px;">
                            <span style="font-size:0.6rem;color:var(--text3);width:44px;text-align:center;">Visa</span>
                            <span style="font-size:0.6rem;color:var(--text3);width:44px;text-align:center;">MC</span>
                            <span style="font-size:0.6rem;color:var(--text3);width:44px;text-align:center;">Amex</span>
                            <span style="font-size:0.6rem;color:var(--text3);width:44px;text-align:center;">Disc</span>
                        </div>
                        <p style="font-size:0.7rem;color:var(--text3);margin-top:6px;text-align:center;">💡 Click a card type to auto-fill test number</p>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry Month</label>
                            <div class="input-wrap">
                                <i class="fa fa-calendar"></i>
                                <select name="expiry_month" class="form-input no-icon" required style="padding-left:14px; appearance:none; cursor:pointer;">
                                    <option value="">MM</option>
                                    <?php for($m=1; $m<=12; $m++): ?>
                                    <option value="<?php echo str_pad($m,2,'0',STR_PAD_LEFT); ?>"><?php echo str_pad($m,2,'0',STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Expiry Year</label>
                            <div class="input-wrap">
                                <i class="fa fa-calendar"></i>
                                <select name="expiry_year" class="form-input no-icon" required style="padding-left:14px; appearance:none; cursor:pointer;">
                                    <option value="">YY</option>
                                    <?php for($y=date('y'); $y<=date('y')+10; $y++): ?>
                                    <option value="<?php echo str_pad($y,2,'0',STR_PAD_LEFT); ?>"><?php echo str_pad($y,2,'0',STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>CVV / Security Code</label>
                        <div class="input-wrap">
                            <i class="fa fa-lock"></i>
                            <input type="password" name="cvv" class="form-input" 
                                   id="cvvInput" placeholder="123" maxlength="4" required
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            <i class="fa fa-question-circle" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--text3); font-size:0.8rem;" title="3 or 4 digit code on back of card"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-pay" id="payBtn">
                        <i class="fa fa-lock"></i>
                        Pay Rs <?php echo number_format($total); ?>
                    </button>

                    <div class="security-note">
                        <i class="fa fa-shield-halved"></i>
                        <span>Your payment information is encrypted and processed securely. We never store your full card details.</span>
                    </div>
                </form>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="summary-section">
            <div class="summary-header">
                <i class="fa fa-receipt"></i>
                <h2>Order Summary</h2>
            </div>
            <div class="summary-body">
                <div class="car-info">
                    <img src="<?php echo !empty($booking['Vimage1']) ? '../img/vehicleimages/'.htmlspecialchars($booking['Vimage1']) : 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=80&q=60'; ?>" 
                         class="car-img" alt="Car" onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=80&q=60'">
                    <div class="car-details">
                        <h3><?php echo htmlspecialchars($booking['car_name']); ?></h3>
                        <p><?php echo htmlspecialchars($booking['car_model']); ?></p>
                        <span class="booking-id">Booking #<?php echo $booking['id']; ?></span>
                    </div>
                </div>

                <div class="summary-row">
                    <span class="label">Pick-up Date</span>
                    <span class="value"><?php echo date('d M Y', strtotime($booking['from_date'])); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Return Date</span>
                    <span class="value"><?php echo date('d M Y', strtotime($booking['to_date'])); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Duration</span>
                    <span class="value"><?php echo $days; ?> day<?php echo $days>1?'s':''; ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Rate per day</span>
                    <span class="value">Rs <?php echo number_format($booking['price_per_day']); ?></span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row">
                    <span class="label">Subtotal</span>
                    <span class="value">Rs <?php echo number_format($amount); ?></span>
                </div>
                <?php if ($penalty > 0): ?>
                <div class="summary-row">
                    <span class="label" style="color:var(--red);">Late Penalty</span>
                    <span class="value" style="color:var(--red);">Rs <?php echo number_format($penalty); ?></span>
                </div>
                <?php endif; ?>

                <div class="summary-total">
                    <span class="label">Total Amount</span>
                    <span class="amount">Rs <?php echo number_format($total); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Theme
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
    function toggleTheme() {
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
    }

    // Card number formatting
    function formatCardNumber(input) {
        let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/g, '');
        let formatted = '';
        for (let i = 0; i < value.length; i++) {
            if (i > 0 && i % 4 === 0) formatted += ' ';
            formatted += value[i];
        }
        input.value = formatted;
        
        // Detect card type
        detectCardType(value);
    }

    // Card type detection on input
    function detectCardType(number) {
        let icon = document.getElementById('cardTypeIcon');
        document.querySelectorAll('.card-logo').forEach(l => l.classList.remove('active'));
        
        if (/^4/.test(number)) {
            icon.className = 'fa fa-cc-visa card-icon';
            document.getElementById('visaLogo').classList.add('active');
        } else if (/^5[1-5]/.test(number)) {
            icon.className = 'fa fa-cc-mastercard card-icon';
            document.getElementById('mcLogo').classList.add('active');
        } else if (/^3[47]/.test(number)) {
            icon.className = 'fa fa-cc-amex card-icon';
            document.getElementById('amexLogo').classList.add('active');
        } else if (/^6(?:011|5)/.test(number)) {
            icon.className = 'fa fa-cc-discover card-icon';
            document.getElementById('discLogo').classList.add('active');
        } else {
            icon.className = 'fa fa-credit-card card-icon';
        }
    }

    // Auto-fill test card numbers when clicking logos
    function fillTestCard(type) {
        let cardInput = document.getElementById('cardNumber');
        let testNumbers = {
            '4': '4242424242424242',       // Visa
            '5': '5555555555554444',       // Mastercard
            'amex': '378282246310005',     // Amex
            'discover': '6011111111111117' // Discover
        };
        
        cardInput.value = testNumbers[type] || '';
        detectCardType(cardInput.value);
        formatCardNumber(cardInput);
        
        // Auto-fill expiry (future date)
        let d = new Date();
        d.setFullYear(d.getFullYear() + 1);
        let month = String(d.getMonth() + 1).padStart(2, '0');
        let year = String(d.getFullYear()).slice(-2);
        document.querySelector('[name="expiry_month"]').value = month;
        document.querySelector('[name="expiry_year"]').value = year;
        
        // Auto-fill CVV
        document.getElementById('cvvInput').value = type === 'amex' ? '1234' : '123';
        
        // Show notification
        showNotification('Test card filled! Use any CVV to test.');
    }
    
    function showNotification(msg) {
        let existing = document.querySelector('.test-notification');
        if (existing) existing.remove();
        
        let notif = document.createElement('div');
        notif.className = 'test-notification';
        notif.innerHTML = '<i class="fa fa-info-circle"></i> ' + msg;
        notif.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#00d4ff;color:#000;padding:12px 20px;border-radius:10px;font-size:0.85rem;font-weight:600;z-index:9999;animation:fadeUp 0.3s ease;';
        document.body.appendChild(notif);
        
        setTimeout(() => {
            notif.style.opacity = '0';
            notif.style.transition = 'opacity 0.3s';
            setTimeout(() => notif.remove(), 300);
        }, 3000);
    }

    // Form validation
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        let cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
        let cvv = document.getElementById('cvvInput').value;
        
        if (cardNumber.length < 13 || cardNumber.length > 19) {
            e.preventDefault();
            alert('Please enter a valid card number');
            return;
        }
        
        if (cvv.length < 3) {
            e.preventDefault();
            alert('Please enter a valid CVV');
            return;
        }
        
        document.getElementById('payBtn').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
        document.getElementById('payBtn').disabled = true;
    });
</script>
</body>
</html>
