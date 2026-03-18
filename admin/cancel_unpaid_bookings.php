<?php
/**
 * Auto-cancel unpaid bookings after 12 hours
 * 
 * Run via cron job:
 * 0 * * * * php /path/to/cancel_unpaid_bookings.php
 * 
 * Or call via URL (less secure):
 * 0 * * * * curl -s http://yoursite.com/admin/cancel_unpaid_bookings.php
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !isset($_GET['cron'])) {
    die('This script should be run via command line or cron.');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/mailer_config.php';

date_default_timezone_set('Asia/Colombo');

function sendCancellationEmail($booking) {
    try {
        $mail = getMailer();
        $mail->addAddress($booking['email'], $booking['full_name']);
        $mail->isHTML(true);
        $mail->Subject = "Booking Cancelled — CarForYou #BKG-{$booking['id']}";
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Cancelled</title>
</head>
<body style="margin:0;padding:0;background:#0d1117;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;padding:40px 20px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0d1117 0%,#111b2a 60%);
                     border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;
                     border:1px solid rgba(79,142,247,0.15);border-bottom:none;">
            <h1 style="margin:0;font-size:2rem;font-weight:300;color:#e8edf5;letter-spacing:-0.02em;">
              Car<span style="color:#4f8ef7;font-style:italic;font-weight:600;">ForYou</span>
            </h1>
          </td>
        </tr>
        <tr>
          <td style="background:#1e2738;border-left:1px solid rgba(79,142,247,0.15);
                     border-right:1px solid rgba(79,142,247,0.15);padding:32px 40px;text-align:center;">
            <span style="display:inline-flex;align-items:center;gap:8px;
                         background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);
                         border-radius:30px;padding:8px 20px;
                         font-size:0.82rem;font-weight:700;letter-spacing:0.08em;
                         text-transform:uppercase;color:#ef4444;margin-bottom:16px;">
              ✕ &nbsp; Booking Cancelled
            </span>
            <p style="margin:0;font-size:1rem;color:#e8edf5;">
              Hi <strong style="color:#4f8ef7;">{$booking['full_name']}</strong>,
            </p>
            <p style="margin:16px 0 0;font-size:0.9rem;color:#7a93b0;">
              Your booking for <strong style="color:#e8edf5;">{$booking['car_name']}</strong> 
              has been cancelled due to non-payment within the required time.
            </p>
            <p style="margin:16px 0 0;font-size:0.85rem;color:#7a93b0;">
              Booking Reference: <strong style="color:#e8edf5;">#BKG-{$booking['id']}</strong>
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#131920;border:1px solid rgba(79,142,247,0.15);border-top:none;
                     border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 8px;font-size:0.85rem;color:#7a93b0;">
              You can make a new booking anytime at our website.
            </p>
            <p style="margin:0;font-size:0.8rem;color:#3d5570;">
              Questions? Contact us at amafzhar@gmail.com | +94 75 45 57 624
            </p>
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
        $mail->AltBody = "Hi {$booking['full_name']}, your booking #{$booking['id']} for {$booking['car_name']} has been cancelled due to non-payment.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Cancellation email error: ' . $e->getMessage());
        return false;
    }
}

function cancelUnpaidBookings($conn, $hours = 12) {
    $stmt = $conn->prepare("
        SELECT b.id, b.user_email, b.from_date, c.car_name, u.full_name, u.email
        FROM booking b
        JOIN cars c ON c.id = b.car_id
        JOIN users u ON u.email = b.user_email
        WHERE b.status = 1 
        AND b.payment_status = 'unpaid' 
        AND b.posting_date < DATE_SUB(NOW(), INTERVAL ? HOUR)
    ");
    $stmt->bind_param("i", $hours);
    $stmt->execute();
    $bookings = $stmt->get_result();
    $stmt->close();
    
    $cancelled = 0;
    $details = [];
    
    while ($booking = $bookings->fetch_assoc()) {
        $update = $conn->prepare("UPDATE booking SET status = 2 WHERE id = ?");
        $update->bind_param("i", $booking['id']);
        if ($update->execute()) {
            $cancelled++;
            sendCancellationEmail($booking);
            $details[] = [
                'id' => $booking['id'],
                'customer' => $booking['full_name'],
                'email' => $booking['email'],
                'car' => $booking['car_name'],
                'from_date' => $booking['from_date']
            ];
        }
        $update->close();
    }
    
    return ['count' => $cancelled, 'details' => $details];
}

$hours = isset($argv[1]) ? intval($argv[1]) : 12;
$result = cancelUnpaidBookings($conn, $hours);

$timestamp = date('Y-m-d H:i:s');
$log_file = __DIR__ . '/logs/auto_cancel.log';

// Ensure logs directory exists
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

if ($result['count'] > 0) {
    $log = "[$timestamp] Auto-cancelled {$result['count']} unpaid booking(s):\n";
    foreach ($result['details'] as $d) {
        $log .= "  - Booking #{$d['id']}: {$d['customer']} ({$d['email']}) - {$d['car']}\n";
    }
    file_put_contents($log_file, $log, FILE_APPEND);
    echo "[$timestamp] Cancelled {$result['count']} unpaid booking(s)\n";
} else {
    echo "[$timestamp] No unpaid bookings to cancel\n";
}
