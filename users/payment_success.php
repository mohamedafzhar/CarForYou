<?php
session_start();
require_once('../includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$receipt_no  = trim($_GET['receipt'] ?? '');
$booking_id  = intval($_GET['booking_id'] ?? 0);
$user_id     = $_SESSION['user_id'];
$user_name   = $_SESSION['user_name'] ?? 'User';
$initial     = strtoupper(substr($user_name, 0, 1));

if (!$receipt_no || !$booking_id) {
    header("Location: user_booking.php");
    exit();
}

// Get user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_email = $stmt->get_result()->fetch_assoc()['email'] ?? '';

// Get payment and booking details
$stmt = $conn->prepare("
    SELECT cp.*, b.from_date, b.to_date, c.car_name, c.car_model, c.Vimage1
    FROM card_payments cp
    JOIN booking b ON b.id = cp.booking_id
    JOIN cars c ON c.id = b.car_id
    WHERE cp.receipt_no = ? AND cp.booking_id = ? AND cp.user_email = ?
");
$stmt->bind_param("sis", $receipt_no, $booking_id, $user_email);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    header("Location: user_booking.php");
    exit();
}

$days   = max(1, (int)((strtotime($payment['to_date']) - strtotime($payment['from_date'])) / 86400));
$amount = $days * intval($payment['amount'] / $days); // Calculate base amount
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | CarForYou</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        [data-theme="dark"] {
            --bg:#0b0e14; --surface:#141920; --surface2:#1a2030;
            --border:rgba(255,255,255,0.08); --border2:rgba(255,255,255,0.12);
            --text:#f0f2f8; --text2:#8892a4; --text3:#44505e;
            --accent:#00d4ff; --accent2:#0090ff;
            --green:#00e676; --greenbg:rgba(0,230,118,0.1);
        }
        [data-theme="light"] {
            --bg:#f0f4f8; --surface:#ffffff; --surface2:#f5f8fc;
            --border:rgba(0,0,0,0.08); --border2:rgba(0,0,0,0.14);
            --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
            --accent:#0077cc; --accent2:#0055aa;
            --green:#059669; --greenbg:rgba(5,150,105,0.1);
        }
        body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:40px 20px; transition:background 0.3s; }
        
        .success-card { background:var(--surface); border:1px solid var(--border2); border-radius:20px; width:100%; max-width:520px; overflow:hidden; animation:slideUp 0.5s ease; }
        @keyframes slideUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        
        .success-header { text-align:center; padding:40px 30px 30px; background:linear-gradient(135deg, rgba(0,230,118,0.1), rgba(0,212,255,0.05)); border-bottom:1px solid var(--border); }
        .success-icon { width:80px; height:80px; background:linear-gradient(135deg,var(--green),#00b85a); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; animation:popIn 0.4s ease 0.2s both; }
        @keyframes popIn { from { transform:scale(0); opacity:0; } to { transform:scale(1); opacity:1; } }
        .success-icon i { font-size:2.2rem; color:#fff; }
        .success-header h1 { font-size:1.6rem; font-weight:800; color:var(--text); margin-bottom:8px; }
        .success-header p { color:var(--text2); font-size:0.9rem; }
        
        .receipt-section { padding:24px 30px; }
        .receipt-box { background:var(--surface2); border:1px dashed var(--border2); border-radius:12px; padding:20px; }
        .receipt-header { display:flex; justify-content:space-between; align-items:center; padding-bottom:16px; border-bottom:1px dashed var(--border2); margin-bottom:16px; }
        .receipt-logo { font-weight:800; font-size:1.1rem; }
        .receipt-logo span { color:var(--accent); }
        .receipt-no { font-size:0.78rem; color:var(--text3); }
        .receipt-no strong { color:var(--accent); display:block; font-size:0.9rem; margin-top:2px; }
        
        .receipt-row { display:flex; justify-content:space-between; padding:8px 0; font-size:0.88rem; border-bottom:1px solid var(--border); }
        .receipt-row:last-child { border-bottom:none; }
        .receipt-row .label { color:var(--text2); }
        .receipt-row .value { font-weight:600; }
        
        .car-preview { display:flex; gap:14px; align-items:center; padding:14px; background:var(--surface2); border-radius:10px; margin-top:16px; }
        .car-img { width:70px; height:48px; border-radius:8px; object-fit:cover; }
        .car-info h4 { font-size:0.95rem; font-weight:700; }
        .car-info p { font-size:0.78rem; color:var(--text3); margin-top:2px; }
        
        .total-section { display:flex; justify-content:space-between; align-items:center; padding:18px; background:linear-gradient(135deg, rgba(0,212,255,0.08), rgba(0,144,255,0.04)); border-radius:10px; margin-top:16px; }
        .total-section .label { font-size:0.9rem; font-weight:700; }
        .total-section .amount { font-size:1.5rem; font-weight:800; color:var(--accent); }
        
        .action-buttons { display:flex; gap:12px; margin-top:20px; }
        .btn { flex:1; padding:14px; border-radius:10px; font-family:'Outfit',sans-serif; font-size:0.9rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all 0.2s; text-decoration:none; }
        .btn-primary { background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; border:none; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,212,255,0.3); }
        .btn-secondary { background:var(--surface2); color:var(--text2); border:1px solid var(--border2); }
        .btn-secondary:hover { border-color:var(--accent); color:var(--accent); }
        
        .footer-note { text-align:center; padding:16px 30px 24px; font-size:0.78rem; color:var(--text3); }
        .footer-note i { color:var(--green); margin-right:4px; }
    </style>
</head>
<body>

<div class="success-card">
    <div class="success-header">
        <div class="success-icon">
            <i class="fa fa-check"></i>
        </div>
        <h1>Payment Successful!</h1>
        <p>Thank you for your payment. Your booking is confirmed.</p>
    </div>
    
    <div class="receipt-section">
        <div class="receipt-box">
            <div class="receipt-header">
                <div class="receipt-logo">Car<span>ForYou</span></div>
                <div class="receipt-no">
                    Receipt No
                    <strong><?php echo htmlspecialchars($payment['receipt_no']); ?></strong>
                </div>
            </div>
            
            <div class="car-preview">
                <img src="<?php echo !empty($payment['Vimage1']) ? '../img/vehicleimages/'.htmlspecialchars($payment['Vimage1']) : 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=70&q=60'; ?>" 
                     class="car-img" alt="Car">
                <div class="car-info">
                    <h4><?php echo htmlspecialchars($payment['car_name']); ?></h4>
                    <p><?php echo htmlspecialchars($payment['car_model']); ?></p>
                </div>
            </div>
            
            <div class="receipt-row">
                <span class="label">Booking ID</span>
                <span class="value">#<?php echo $payment['booking_id']; ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Pick-up Date</span>
                <span class="value"><?php echo date('d M Y', strtotime($payment['from_date'])); ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Return Date</span>
                <span class="value"><?php echo date('d M Y', strtotime($payment['to_date'])); ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Duration</span>
                <span class="value"><?php echo $days; ?> day<?php echo $days>1?'s':''; ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Payment Method</span>
                <span class="value"><i class="fa fa-<?php echo strtolower($payment['card_type']); ?>"></i> <?php echo $payment['card_type']; ?> ending <?php echo $payment['card_last4']; ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Payment Date</span>
                <span class="value"><?php echo date('d M Y, H:i', strtotime($payment['payment_date'])); ?></span>
            </div>
            
            <div class="total-section">
                <span class="label">Total Paid</span>
                <span class="amount">Rs <?php echo number_format($payment['amount']); ?></span>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="user_booking.php" class="btn btn-secondary">
                <i class="fa fa-calendar-check"></i> My Bookings
            </a>
            <a href="car_dashboard.php" class="btn btn-primary">
                <i class="fa fa-home"></i> Dashboard
            </a>
        </div>
    </div>
    
    <div class="footer-note">
        <i class="fa fa-envelope"></i> A confirmation email has been sent to <?php echo htmlspecialchars($user_email); ?>
    </div>
</div>

<script>
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
</script>
</body>
</html>
