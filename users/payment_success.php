<?php
session_start();
require_once('../includes/config.php');
require_once('../includes/payhere.class.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$initial   = strtoupper(substr($user_name, 0, 1));

$payment = null;
$booking = null;
$error_msg = '';
$is_advance = false;
$advance_amount = 10000;

// Handle PayHere return parameters
$status_code = intval($_GET['status_code'] ?? $_POST['status_code'] ?? 0);
$order_id = $_GET['order_id'] ?? $_POST['order_id'] ?? '';
$payment_type = $_GET['type'] ?? 'full';
$is_advance = ($payment_type === 'advance');

// Handle test/dummy payment success
$is_test = isset($_GET['type']) && $_GET['type'] === 'test';
if ($is_test && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $test_payment_type = $_GET['payment_type'] ?? 'advance';
    $test_amount = floatval($_GET['amount'] ?? 10000);
    $is_advance = ($test_payment_type === 'advance');
    
    $stmt = $conn->prepare("
        SELECT b.*, c.car_name, c.car_model, c.Vimage1, c.price_per_day
        FROM booking b 
        JOIN cars c ON c.id = b.car_id 
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($booking) {
        $now = date('Y-m-d H:i:s');
        $tx_id = 'TEST' . strtoupper(substr(md5($booking_id . time()), 0, 8));
        $email = $_SESSION['user_email'] ?? $booking['user_email'];
        
        // Record test payment
        $stmt = $conn->prepare("
            INSERT INTO payments (booking_id, user_email, car_id, amount, payment_date, payment_method, transaction_id, status)
            VALUES (?, ?, ?, ?, ?, 'Test Payment', ?, 'completed')
        ");
        $stmt->bind_param("isiiss", $booking_id, $email, $booking['car_id'], $test_amount, $now, $tx_id);
        $stmt->execute();
        $stmt->close();
        
        if ($is_advance) {
            // Generate pickup reference number
            $pickup_ref = 'CFY-' . strtoupper(substr(md5($booking_id . time()), 0, 6));
            
            // Update booking to confirmed with pickup reference
            $stmt = $conn->prepare("UPDATE booking SET status = 'confirmed', payment_status = 'advance_paid', payment_date = ?, confirmed_at = ?, pickup_ref = ? WHERE id = ?");
            $stmt->bind_param("sssi", $now, $now, $pickup_ref, $booking_id);
            $stmt->execute();
            $stmt->close();
            
            // Send confirmation email with pickup reference
            sendBookingConfirmedEmail($conn, $booking_id);
            
            $_SESSION['success_msg'] = "Test payment successful! Your booking is now confirmed. Pickup Ref: {$pickup_ref}";
        } else {
            $stmt = $conn->prepare("UPDATE booking SET payment_status = 'paid', payment_date = ? WHERE id = ?");
            $stmt->bind_param("si", $now, $booking_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success_msg'] = "Test payment successful!";
        }
        
        $payment = [
            'id' => 0,
            'booking_id' => $booking_id,
            'amount' => $test_amount,
            'payment_date' => $now,
            'payment_method' => 'Test Payment (Dummy)',
            'transaction_id' => $tx_id,
            'status' => 'completed'
        ];
    }
} elseif ($status_code === 2 && !empty($order_id)) {
    // Get booking info - try session first, then fallback to booking_id param
    $booking_id = 0;
    if (isset($_SESSION['payhere_order']) && $_SESSION['payhere_order']['order_id'] === $order_id) {
        $booking_id = $_SESSION['payhere_order']['booking_id'];
    } elseif (isset($_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
    }
    
    if ($booking_id > 0) {
        // Get booking details
        $stmt = $conn->prepare("
            SELECT b.*, c.car_name, c.car_model, c.Vimage1, c.price_per_day
            FROM booking b 
            JOIN cars c ON c.id = b.car_id 
            WHERE b.id = ?
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($booking) {
            $paid_amount = $_GET['payhere_amount'] ?? $_SESSION['payhere_order']['amount'] ?? 0;
            $receipt_no = 'RCP' . strtoupper(substr(md5($booking_id . time()), 0, 8));
            $now = date('Y-m-d H:i:s');
            
            // Check if payment already recorded
            $check_stmt = $conn->prepare("SELECT id FROM payments WHERE transaction_id = ?");
            $check_stmt->bind_param("s", $order_id);
            $check_stmt->execute();
            $existing = $check_stmt->get_result();
            $check_stmt->close();
            
            if (!$existing || $existing->num_rows === 0) {
                // Record payment in payments table
                $stmt = $conn->prepare("
                    INSERT INTO payments (booking_id, user_email, car_id, amount, payment_date, payment_method, transaction_id, status)
                    VALUES (?, ?, ?, ?, ?, 'PayHere', ?, 'completed')
                ");
                $tx_id = $order_id;
                $email = $_SESSION['user_email'] ?? $booking['user_email'];
                $stmt->bind_param("isiisss", $booking_id, $email, $booking['car_id'], $paid_amount, $now, $tx_id);
                $stmt->execute();
                $stmt->close();
            }
            
            if ($is_advance) {
                // Generate pickup reference
                $pickup_ref = 'CFY-' . strtoupper(substr(md5($booking_id . time()), 0, 6));
                
                // Update booking to confirmed status (use string 'confirmed')
                $stmt = $conn->prepare("UPDATE booking SET status = 'confirmed', payment_status = 'advance_paid', payment_date = ?, confirmed_at = ?, pickup_ref = ? WHERE id = ?");
                $stmt->bind_param("sssi", $now, $now, $pickup_ref, $booking_id);
                $stmt->execute();
                $stmt->close();
                
                // Send confirmation email
                sendBookingConfirmedEmail($conn, $booking_id);
                
                $_SESSION['success_msg'] = "Advance payment received! Your booking is now confirmed. Pickup Ref: {$pickup_ref}";
            } else {
                // Full payment - mark as paid
                $stmt = $conn->prepare("UPDATE booking SET payment_status = 'paid', payment_date = ? WHERE id = ?");
                $stmt->bind_param("si", $now, $booking_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Get the payment record
            $stmt = $conn->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        // Clear session
        unset($_SESSION['payhere_order']);
    }
} elseif ($status_code === 0) {
    $error_msg = "Payment is pending. You will receive a confirmation once processed.";
} elseif ($status_code === 1) {
    $error_msg = "Payment was canceled.";
    header("Location: payment.php?booking_id=" . intval($_GET['booking_id'] ?? 0) . "&type=" . $payment_type);
    exit();
} elseif ($status_code === -1) {
    $error_msg = "Payment failed. Please try again.";
}

// Handle direct access with booking_id
$booking_id = intval($_GET['booking_id'] ?? 0);
if (!$payment && $booking_id) {
    $stmt = $conn->prepare("
        SELECT p.*, b.from_date, b.to_date, c.car_name, c.car_model, c.Vimage1
        FROM payments p
        JOIN booking b ON b.id = p.booking_id
        JOIN cars c ON c.id = b.car_id
        WHERE p.booking_id = ? AND b.user_id = ?
        ORDER BY p.id DESC LIMIT 1
    ");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($payment) {
        $stmt = $conn->prepare("SELECT * FROM booking WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$payment) {
    $_SESSION['error_msg'] = "Payment record not found.";
    header("Location: user_booking.php");
    exit();
}

// Get user email
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_email = $stmt->get_result()->fetch_assoc()['email'] ?? '';
$stmt->close();

$days   = max(1, (int)((strtotime($booking['to_date']) - strtotime($booking['from_date'])) / 86400));

// Calculate totals
$total_amount = $days * intval($booking['price_per_day'] ?? 0);
$remaining = $total_amount - $advance_amount;
?>

<?php
// Email function for booking confirmation
function sendBookingConfirmedEmail($conn, $booking_id) {
    $stmt = $conn->prepare("
        SELECT b.*, u.full_name, u.email, c.car_name, c.car_model, c.car_type, c.price_per_day
        FROM booking b
        JOIN users u ON u.email = b.user_email
        JOIN cars c ON c.id = b.car_id
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();
    if (!$d) return false;

    $days = max(1, (int)(new DateTime($d['from_date']))->diff(new DateTime($d['to_date']))->days);
    $total = $days * intval($d['price_per_day']);
    $remaining = $total - 10000; // Advance paid is always 10,000
    $totalFormatted = number_format($total);
    $remainingFormatted = number_format($remaining);

    require_once __DIR__ . '/../admin/mailer_config.php';
    try {
        $mail = getMailer();
        $mail->addAddress($d['email'], $d['full_name']);
        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmed! — {$d['car_name']} | CarForYou #BKG-{$booking_id}";
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Confirmed! | CarForYou</title>
</head>
<body style="margin:0;padding:0;background:#0d1117;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;padding:40px 20px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0d1117 0%,#111b2a 60%);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;border:1px solid rgba(79,142,247,0.15);border-bottom:none;">
            <h1 style="margin:0;font-size:2rem;font-weight:300;color:#e8edf5;letter-spacing:-0.02em;">Car<span style="color:#4f8ef7;font-style:italic;font-weight:600;">ForYou</span></h1>
          </td>
        </tr>
        <tr>
          <td style="background:#1e2738;border-left:1px solid rgba(79,142,247,0.15);border-right:1px solid rgba(79,142,247,0.15);padding:32px 40px;text-align:center;">
            <span style="display:inline-flex;align-items:center;gap:8px;background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);border-radius:30px;padding:8px 20px;font-size:0.82rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#22c55e;margin-bottom:16px;">
              ✓ &nbsp; BOOKING CONFIRMED
            </span>
            <p style="margin:0;font-size:1rem;color:#e8edf5;">Hi <strong style="color:#4f8ef7;">{$d['full_name']}</strong>,</p>
            <p style="margin:16px 0 0;font-size:0.9rem;color:#7a93b0;">Great! Your advance payment has been received. Your booking is now confirmed!</p>
          </td>
        </tr>
        <tr>
          <td style="background:#131c2e;border-left:1px solid rgba(79,142,247,0.15);border-right:1px solid rgba(79,142,247,0.15);padding:16px 40px;text-align:center;">
            <div style="background:linear-gradient(135deg,rgba(0,212,255,0.15),rgba(0,144,255,0.08));border:1px solid rgba(0,212,255,0.3);border-radius:12px;padding:20px;margin:0;">
              <p style="margin:0 0 8px;font-size:0.78rem;color:#7a93b0;text-transform:uppercase;letter-spacing:0.1em;">Your Pick-up Reference</p>
              <p style="margin:0;font-size:2rem;font-weight:800;color:#00d4ff;letter-spacing:4px;">{$d['pickup_ref']}</p>
              <p style="margin:12px 0 0;font-size:0.78rem;color:#7a93b0;">Present this code at pick-up along with your NIC and Driving License</p>
            </div>
          </td>
        </tr>
        <tr>
          <td style="background:#131c2e;border-left:1px solid rgba(79,142,247,0.15);border-right:1px solid rgba(79,142,247,0.15);padding:24px 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#1e2738;border-radius:12px;overflow:hidden;border:1px solid rgba(79,142,247,0.1);">
              <tr>
                <td colspan="2" style="padding:16px 20px;border-bottom:1px solid rgba(79,142,247,0.1);">
                  <strong style="font-size:16px;color:#e8edf5;">{$d['car_name']}</strong><br>
                  <span style="font-size:13px;color:#7a93b0;">{$d['car_model']} | {$d['car_type']}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:12px 20px;border-bottom:1px solid rgba(79,142,247,0.1);width:50%;">
                  <div style="font-size:11px;color:#7a93b0;text-transform:uppercase;margin-bottom:4px;">Pick-up</div>
                  <strong style="font-size:14px;color:#e8edf5;">{$d['from_date']}</strong>
                </td>
                <td style="padding:12px 20px;border-bottom:1px solid rgba(79,142,247,0.1);width:50%;">
                  <div style="font-size:11px;color:#7a93b0;text-transform:uppercase;margin-bottom:4px;">Return</div>
                  <strong style="font-size:14px;color:#e8edf5;">{$d['to_date']}</strong>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding:16px 20px;background:linear-gradient(135deg,rgba(34,197,94,0.15),rgba(34,197,94,0.05));">
                  <table width="100%">
                    <tr>
                      <td style="font-size:14px;color:#e8edf5;">Total Amount</td>
                      <td style="font-size:16px;font-weight:bold;color:#e8edf5;text-align:right;">LKR {$totalFormatted}</td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#7a93b0;padding-top:8px;">Advance Paid</td>
                      <td style="font-size:12px;color:#22c55e;text-align:right;padding-top:8px;">- LKR 10,000</td>
                    </tr>
                    <tr>
                      <td style="font-size:14px;color:#e8edf5;padding-top:8px;">Balance to Pay</td>
                      <td style="font-size:16px;font-weight:bold;color:#fbbf24;text-align:right;padding-top:8px;">LKR {$remainingFormatted}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="background:#131920;border:1px solid rgba(79,142,247,0.15);border-top:none;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 8px;font-size:0.85rem;color:#7a93b0;">Please pay the remaining balance of <strong style="color:#fbbf24;">LKR {$remainingFormatted}</strong> upon vehicle pick-up.</p>
            <p style="margin:12px 0 0;font-size:0.85rem;color:#7a93b0;">Questions? Contact us at amafzhar@gmail.com | +94 75 45 57 624</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

        $mail->Body = $html;
        $mail->AltBody = "Hi {$d['full_name']}, your booking for {$d['car_name']} is confirmed! Total: LKR {$totalFormatted}. Balance: LKR {$remainingFormatted}";
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('Confirmation email error: ' . $e->getMessage());
        return false;
    }
}
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
            --amber:#fbbf24; --amberbg:rgba(251,191,36,0.1);
        }
        [data-theme="light"] {
            --bg:#f0f4f8; --surface:#ffffff; --surface2:#f5f8fc;
            --border:rgba(0,0,0,0.08); --border2:rgba(0,0,0,0.14);
            --text:#0f1923; --text2:#4a5568; --text3:#94a3b8;
            --accent:#0077cc; --accent2:#0055aa;
            --green:#059669; --greenbg:rgba(5,150,105,0.1);
            --amber:#d97706; --amberbg:rgba(217,119,6,0.1);
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
        
        <?php if ($is_advance): ?>
        .balance-section { display:flex; justify-content:space-between; align-items:center; padding:16px; background:var(--amberbg); border:1px solid rgba(251,191,36,0.3); border-radius:10px; margin-top:12px; }
        .balance-section .label { font-size:0.88rem; font-weight:600; color:var(--amber); }
        .balance-section .amount { font-size:1.2rem; font-weight:800; color:var(--amber); }
        .balance-note { font-size:0.78rem; color:var(--text2); text-align:center; margin-top:12px; }
        <?php endif; ?>
        
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
        <h1><?php echo $is_advance ? 'Booking Confirmed!' : 'Payment Successful!'; ?></h1>
        <p><?php echo $is_advance ? 'Your booking is now confirmed. See you soon!' : 'Your payment has been processed successfully.'; ?></p>
    </div>
    
    <div class="receipt-section">
        <div class="receipt-box">
            <div class="receipt-header">
                <div class="receipt-logo">Car<span>ForYou</span></div>
                <div class="receipt-no">
                    Receipt No
                    <strong><?php echo htmlspecialchars($payment['receipt_no'] ?? $payment['transaction_id'] ?? 'N/A'); ?></strong>
                </div>
            </div>
            
            <div class="car-preview">
                <img src="<?php echo !empty($payment['Vimage1']) ? '../admin/img/vehicleimages/'.htmlspecialchars($payment['Vimage1']) : 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=70&q=60'; ?>" 
                     class="car-img" alt="Car" onerror="this.src='https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=70&q=60'">
                <div class="car-info">
                    <h4><?php echo htmlspecialchars($payment['car_name'] ?? 'Car Rental'); ?></h4>
                    <p><?php echo htmlspecialchars($payment['car_model'] ?? ''); ?></p>
                </div>
            </div>
            
            <div class="receipt-row">
                <span class="label">Booking ID</span>
                <span class="value">#<?php echo $payment['booking_id']; ?></span>
            </div>
            <?php if (!empty($booking['pickup_ref'])): ?>
            <div class="receipt-row" style="background:rgba(0,212,255,0.08);padding:12px 16px;border-radius:8px;margin:10px 0;">
                <span class="label" style="color:var(--accent);">Pick-up Reference</span>
                <span class="value" style="color:var(--accent);font-weight:800;font-size:1.1rem;"><?php echo htmlspecialchars($booking['pickup_ref']); ?></span>
            </div>
            <div class="receipt-row" style="font-size:0.75rem;color:var(--text3);">Present this code at pick-up with NIC & License</div>
            <?php endif; ?>
            <div class="receipt-row">
                <span class="label">Pick-up Date</span>
                <span class="value"><?php echo date('d M Y', strtotime($booking['from_date'])); ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Return Date</span>
                <span class="value"><?php echo date('d M Y', strtotime($booking['to_date'])); ?></span>
            </div>
            <div class="receipt-row">
                <span class="label">Duration</span>
                <span class="value"><?php echo $days; ?> day<?php echo $days>1?'s':''; ?></span>
            </div>
            
            <div class="total-section">
                <span class="label">Amount Paid</span>
                <span class="amount">Rs <?php echo number_format($payment['amount']); ?></span>
            </div>
            
            <?php if ($is_advance): ?>
            <div class="balance-section">
                <span class="label">Balance to Pay on Pick-up</span>
                <span class="amount">Rs <?php echo number_format($remaining); ?></span>
            </div>
            <p class="balance-note">Please pay the remaining balance when you pick up the vehicle.</p>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="user_booking.php" class="btn btn-secondary">
                <i class="fa fa-calendar-check"></i> My Bookings
            </a>
            <a href="car_dashboard.php" class="btn btn-primary">
                <i class="fa fa-home"></i> Dashboard
            </a>
        </div>
        
        <div class="footer-note">
            <i class="fa fa-envelope"></i> A confirmation email has been sent to <?php echo htmlspecialchars($user_email); ?>
        </div>
    </div>
</div>

<script>
    var theme = localStorage.getItem('cfyTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
</script>
</body>
</html>
