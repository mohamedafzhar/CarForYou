<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/payhere.class.php';
userAuth();

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$initial   = strtoupper(substr($user_name, 0, 1));

$stmt = $conn->prepare("SELECT email, full_name, contact_no FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$user_email = $user['email'] ?? '';
$user_fullname = $user['full_name'] ?? '';
$user_phone = $user['contact_no'] ?? '';
$stmt->close();

$msg   = '';
$error = '';

// ── CHECK PAYMENT TYPE ──────────────────────────────────────
$payment_type = $_GET['type'] ?? 'full';
$is_advance = ($payment_type === 'advance');

// ── GET BOOKING ──────────────────────────────────────────────
$booking_id = intval($_GET['booking_id'] ?? 0);
$booking = null;
if ($booking_id) {
    if ($is_advance) {
        // Advance payment - booking must be awaiting_payment status
        $stmt = $conn->prepare("
            SELECT b.*, c.car_name, c.car_model, c.price_per_day, c.Vimage1
            FROM booking b
            JOIN cars c ON c.id = b.car_id
            WHERE b.id = ? AND b.user_email = ? AND b.status = 'awaiting_payment'
        ");
        $stmt->bind_param("is", $booking_id, $user_email);
    } else {
        // Full payment - booking must be confirmed
        $stmt = $conn->prepare("
            SELECT b.*, c.car_name, c.car_model, c.price_per_day, c.Vimage1
            FROM booking b
            JOIN cars c ON c.id = b.car_id
            WHERE b.id = ? AND b.user_email = ? AND b.payment_status = 'unpaid' AND b.status IN (1, 'confirmed', 'Confirmed')
        ");
        $stmt->bind_param("is", $booking_id, $user_email);
    }
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$booking) {
    $_SESSION['error_msg'] = "Booking not found or payment not available.";
    header("Location: user_booking.php");
    exit();
}

$days    = max(1, (int)((strtotime($booking['to_date']) - strtotime($booking['from_date'])) / 86400));
$amount  = $days * $booking['price_per_day'];
$penalty = intval($booking['penalty_amount'] ?? 0);
$total   = $amount + $penalty;

// Advance payment amount
$advance_amount = 10000;
$payment_amount = $is_advance ? $advance_amount : $total;

// ── PAYHERE SETUP ─────────────────────────────────────────────
$payhere = new PayHerePayment();
$order_id = ($is_advance ? 'ADV' : 'BK') . time() . rand(100, 999);

// Customer data
$name_parts = explode(' ', $user_fullname, 2);
$customer = [
    'first_name' => $name_parts[0] ?? $user_fullname,
    'last_name' => $name_parts[1] ?? '',
    'email' => $user_email,
    'phone' => $user_phone,
    'address' => '',
    'city' => 'Trincomalee',
    'country' => 'Sri Lanka'
];

// Item description
$item_desc = $is_advance 
    ? 'Advance Payment - ' . $booking['car_name'] 
    : 'Car Rental - ' . $booking['car_name'];

// Generate payment data
$payment_data = $payhere->createPayment(
    $order_id,
    $payment_amount,
    'LKR',
    $item_desc,
    $customer,
    'http://' . $_SERVER['HTTP_HOST'] . '/carrental/users/payment_notify.php',
    'http://' . $_SERVER['HTTP_HOST'] . '/carrental/users/payment_success.php?order_id=' . $order_id . '&booking_id=' . $booking_id . '&type=' . $payment_type . '&status_code=2',
    'http://' . $_SERVER['HTTP_HOST'] . '/carrental/users/payment.php?booking_id=' . $booking_id . '&type=' . $payment_type
);

// Store order in session for verification
$_SESSION['payhere_order'] = [
    'order_id' => $order_id,
    'booking_id' => $booking_id,
    'amount' => $payment_amount,
    'type' => $payment_type,
    'created' => time()
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment | CarForYou</title>
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

        .theme-corner { position:fixed; top:18px; right:18px; z-index:100; }
        .theme-btn { width:38px; height:38px; border-radius:10px; border:1px solid var(--border2); background:var(--surface); color:var(--text2); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.88rem; transition:all 0.2s; }
        .theme-btn:hover { border-color:var(--accent); color:var(--accent); }

        .page-wrap { max-width:900px; margin:0 auto; padding:40px 20px; }
        .page-header { text-align:center; margin-bottom:40px; }
        .page-header h1 { font-size:1.8rem; font-weight:800; color:var(--text); }
        .page-header p { color:var(--text2); margin-top:6px; font-size:0.9rem; }

        .content-grid { display:grid; grid-template-columns:1fr 380px; gap:30px; align-items:start; }
        @media(max-width:800px) { .content-grid { grid-template-columns:1fr; } }

        .card-section { background:var(--surface); border:1px solid var(--border2); border-radius:16px; overflow:hidden; }
        .card-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
        .card-header h2 { font-size:1rem; font-weight:700; display:flex; align-items:center; gap:8px; }
        .card-header h2 i { color:var(--accent); }
        .card-header .secure-badge { margin-left:auto; display:flex; align-items:center; gap:5px; font-size:0.72rem; color:var(--green); background:var(--greenbg); padding:5px 10px; border-radius:20px; }

        .card-body { padding:24px; }

        .payment-options { display:flex; flex-direction:column; gap:16px; }
        .payment-option { background:var(--surface2); border:2px solid var(--border2); border-radius:12px; padding:20px; cursor:pointer; transition:all 0.25s; }
        .payment-option:hover { border-color:var(--accent); background:rgba(0,212,255,0.05); }
        .payment-option.selected { border-color:var(--accent); background:rgba(0,212,255,0.08); }
        .payment-option input { display:none; }

        .option-header { display:flex; align-items:center; gap:14px; }
        .option-radio { width:22px; height:22px; border:2px solid var(--border2); border-radius:50%; display:flex; align-items:center; justify-content:center; transition:all 0.2s; flex-shrink:0; }
        .payment-option.selected .option-radio { border-color:var(--accent); background:var(--accent); }
        .payment-option.selected .option-radio::after { content:''; width:8px; height:8px; background:#fff; border-radius:50%; }
        .option-icon { width:48px; height:48px; background:var(--surface); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0; }
        .option-info { flex:1; }
        .option-info h3 { font-size:0.95rem; font-weight:700; color:var(--text); margin-bottom:3px; }
        .option-info p { font-size:0.78rem; color:var(--text3); }
        .option-badge { background:var(--greenbg); color:var(--green); font-size:0.65rem; font-weight:700; padding:4px 10px; border-radius:20px; text-transform:uppercase; }

        .option-details { margin-top:16px; padding-top:16px; border-top:1px solid var(--border); display:none; }
        .payment-option.selected .option-details { display:block; }
        .option-details p { font-size:0.8rem; color:var(--text2); display:flex; align-items:center; gap:6px; }
        .option-details p i { color:var(--green); }

        .security-badges { display:flex; align-items:center; gap:20px; justify-content:center; margin-top:24px; padding-top:20px; border-top:1px solid var(--border); flex-wrap:wrap; }
        .security-badge { display:flex; align-items:center; gap:6px; font-size:0.72rem; color:var(--text3); }
        .security-badge i { color:var(--green); font-size:0.85rem; }

        .btn-pay { width:100%; padding:16px; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; border-radius:12px; font-family:'Outfit',sans-serif; font-size:1rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; transition:all 0.25s; box-shadow:0 4px 20px rgba(0,212,255,0.3); margin-top:20px; }
        .btn-pay:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(0,212,255,0.4); }
        .btn-pay:disabled { opacity:0.5; cursor:not-allowed; transform:none; }

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

        .guarantee { display:flex; align-items:center; gap:12px; padding:16px; background:var(--greenbg); border:1px solid rgba(0,230,118,0.2); border-radius:12px; margin-top:20px; }
        .guarantee i { color:var(--green); font-size:1.2rem; }
        .guarantee p { font-size:0.78rem; color:var(--text2); line-height:1.5; }
        .guarantee strong { color:var(--green); }
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
        <h1><i class="fa fa-shield-halved" style="color:var(--accent);margin-right:10px;"></i>Secure Payment</h1>
        <p>Your payment is protected by industry-standard encryption</p>
    </div>

    <div class="content-grid">
        <div class="card-section">
            <div class="card-header">
                <h2><i class="fa fa-lock"></i> Choose Payment Method</h2>
                <span class="secure-badge"><i class="fa fa-shield-halved"></i> Secured by PayHere</span>
            </div>
            <div class="card-body">
                <form id="paymentForm" action="<?php echo $payhere->getCheckoutUrl(); ?>" method="post">
                    <input type="hidden" name="merchant_id" value="<?php echo $payment_data['merchant_id']; ?>">
                    <input type="hidden" name="return_url" value="<?php echo $payment_data['return_url']; ?>">
                    <input type="hidden" name="cancel_url" value="<?php echo $payment_data['cancel_url']; ?>">
                    <input type="hidden" name="notify_url" value="<?php echo $payment_data['notify_url']; ?>">
                    <input type="hidden" name="order_id" value="<?php echo $payment_data['order_id']; ?>">
                    <input type="hidden" name="items" value="<?php echo htmlspecialchars($payment_data['items']); ?>">
                    <input type="hidden" name="currency" value="<?php echo $payment_data['currency']; ?>">
                    <input type="hidden" name="amount" value="<?php echo $payment_data['amount']; ?>">
                    <input type="hidden" name="hash" value="<?php echo $payment_data['hash']; ?>">
                    <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($payment_data['first_name']); ?>">
                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($payment_data['last_name']); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($payment_data['email']); ?>">
                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($payment_data['phone']); ?>">
                    <input type="hidden" name="address" value="<?php echo htmlspecialchars($payment_data['address']); ?>">
                    <input type="hidden" name="city" value="<?php echo htmlspecialchars($payment_data['city']); ?>">
                    <input type="hidden" name="country" value="<?php echo htmlspecialchars($payment_data['country']); ?>">

                    <div class="payment-options">
                        <label class="payment-option selected" onclick="selectOption(this)">
                            <input type="radio" name="payment_method" value="card" checked>
                            <div class="option-header">
                                <div class="option-radio"></div>
                                <div class="option-icon">💳</div>
                                <div class="option-info">
                                    <h3>Credit / Debit Card</h3>
                                    <p>Visa, Mastercard, Amex, UnionPay</p>
                                </div>
                                <span class="option-badge">Popular</span>
                            </div>
                            <div class="option-details">
                                <p><i class="fa fa-lock"></i> Secure OTP verification through your bank</p>
                            </div>
                        </label>

                        <label class="payment-option" onclick="selectOption(this)">
                            <input type="radio" name="payment_method" value="wallet">
                            <div class="option-header">
                                <div class="option-radio"></div>
                                <div class="option-icon">📱</div>
                                <div class="option-info">
                                    <h3>Mobile Wallet</h3>
                                    <p>Dialog, Etisalat, Hutch, Mobitel</p>
                                </div>
                            </div>
                            <div class="option-details">
                                <p><i class="fa fa-mobile-alt"></i> Pay directly from your mobile balance</p>
                            </div>
                        </label>

                        <label class="payment-option" onclick="selectOption(this)">
                            <input type="radio" name="payment_method" value="bank">
                            <div class="option-header">
                                <div class="option-radio"></div>
                                <div class="option-icon">🏦</div>
                                <div class="option-info">
                                    <h3>Bank Transfer</h3>
                                    <p>Commercial Bank, People's Bank, BOC</p>
                                </div>
                            </div>
                            <div class="option-details">
                                <p><i class="fa fa-university"></i> Transfer directly from your bank account</p>
                            </div>
                        </label>

                        <label class="payment-option" onclick="selectOption(this)" id="dummyPaymentOption">
                            <input type="radio" name="payment_method" value="dummy">
                            <div class="option-header">
                                <div class="option-radio"></div>
                                <div class="option-icon">🧪</div>
                                <div class="option-info">
                                    <h3>Test Payment (Dummy)</h3>
                                    <p>Simulate successful payment for testing</p>
                                </div>
                                <span class="option-badge" style="background:rgba(251,191,36,0.15);color:var(--gold);">Testing Only</span>
                            </div>
                            <div class="option-details">
                                <p><i class="fa fa-flask" style="color:var(--gold);"></i> This will simulate a successful payment without any actual transaction</p>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn-pay" id="payBtn">
                        <i class="fa fa-lock"></i>
                        Pay LKR <?php echo number_format($payment_amount); ?> Securely
                    </button>

                    <div class="security-badges">
                        <div class="security-badge"><i class="fa fa-shield-halved"></i><span>256-bit SSL</span></div>
                        <div class="security-badge"><i class="fa fa-key"></i><span>PCI DSS</span></div>
                        <div class="security-badge"><i class="fa fa-mobile-alt"></i><span>OTP Verified</span></div>
                        <div class="security-badge"><i class="fa fa-check-circle"></i><span>PayHere Secured</span></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="summary-section">
            <div class="summary-header">
                <i class="fa fa-receipt"></i>
                <h2>Order Summary</h2>
            </div>
            <div class="summary-body">
                <div class="car-info">
                    <img src="<?php echo !empty($booking['Vimage1']) ? '../admin/img/vehicleimages/'.htmlspecialchars($booking['Vimage1']) : 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=80&q=60'; ?>" 
                         class="car-img" alt="Car" onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=80&q=60'">
                    <div class="car-details">
                        <h3><?php echo htmlspecialchars($booking['car_name']); ?></h3>
                        <p><?php echo htmlspecialchars($booking['car_model']); ?></p>
                        <span class="booking-id">Booking #<?php echo $booking['id']; ?></span>
                    </div>
                </div>

                <div class="summary-row">
                    <span class="label">Pick-up</span>
                    <span class="value"><?php echo date('d M Y', strtotime($booking['from_date'])); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Return</span>
                    <span class="value"><?php echo date('d M Y', strtotime($booking['to_date'])); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Duration</span>
                    <span class="value"><?php echo $days; ?> day<?php echo $days>1?'s':''; ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Rate/Day</span>
                    <span class="value">LKR <?php echo number_format($booking['price_per_day']); ?></span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row">
                    <span class="label">Subtotal</span>
                    <span class="value">LKR <?php echo number_format($amount); ?></span>
                </div>
                <?php if ($penalty > 0): ?>
                <div class="summary-row">
                    <span class="label" style="color:var(--red);">Late Penalty</span>
                    <span class="value" style="color:var(--red);">LKR <?php echo number_format($penalty); ?></span>
                </div>
                <?php endif; ?>

                <div class="summary-total">
                    <span class="label">Total</span>
                    <span class="amount">LKR <?php echo number_format($total); ?></span>
                </div>

                <div class="guarantee">
                    <i class="fa fa-shield-halved"></i>
                    <p><strong>100% Secure Payment</strong><br>Your payment is processed through PayHere's encrypted gateway. Bank OTP verification ensures only you can authorize transactions.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
    function toggleTheme() {
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('cfyTheme', theme);
        document.getElementById('themeIcon').className = theme === 'dark' ? 'fa fa-moon' : 'fa fa-sun';
    }

    function selectOption(element) {
        document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        element.querySelector('input').checked = true;
        
        // Show/hide manual payment form
        const manualForm = document.querySelector('.manual-payment-form');
        const isManual = element.querySelector('input[value="manual"]').checked;
        if (manualForm) {
            manualForm.style.display = isManual ? 'block' : 'none';
        }
        
        // Update button text based on payment method
        const payBtn = document.getElementById('payBtn');
        if (isManual) {
            payBtn.innerHTML = '<i class="fa fa-flask"></i> Test Payment (Dummy)';
            payBtn.style.background = 'linear-gradient(135deg, #fbbf24, #f59e0b)';
        } else {
            payBtn.innerHTML = '<i class="fa fa-lock"></i> Pay LKR <?php echo number_format($payment_amount); ?> Securely';
            payBtn.style.background = 'linear-gradient(135deg,var(--accent),var(--accent2))';
        }
    }

    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
        
        if (selectedMethod === 'dummy') {
            e.preventDefault();
            const payBtn = document.getElementById('payBtn');
            payBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing Test Payment...';
            payBtn.disabled = true;
            
            // Simulate payment processing delay
            setTimeout(function() {
                // Redirect to success page with test parameters
                window.location.href = 'payment_success.php?type=test&booking_id=<?php echo $booking_id; ?>&payment_type=<?php echo $payment_type; ?>&amount=<?php echo $payment_amount; ?>';
            }, 1500);
        } else {
            document.getElementById('payBtn').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Redirecting to PayHere...';
            document.getElementById('payBtn').disabled = true;
        }
    });
</script>
</body>
</html>
