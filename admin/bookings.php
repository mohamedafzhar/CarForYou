<?php
session_start();
include 'config.php';
adminAuth();

$msg   = "";
$error = "";

// ── Helper: send advance payment request email ─────────────────────────────────
function sendAdvancePaymentEmail($conn, int $booking_id): bool {
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
    $advance = 10000;

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Approved - Advance Payment Required | CarForYou</title>
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
            <span style="display:inline-flex;align-items:center;gap:8px;background:rgba(79,142,247,0.12);border:1px solid rgba(79,142,247,0.3);border-radius:30px;padding:8px 20px;font-size:0.82rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#4f8ef7;margin-bottom:16px;">
              ✓ &nbsp; Booking Approved
            </span>
            <p style="margin:0;font-size:1rem;color:#e8edf5;">Hi <strong style="color:#4f8ef7;">{$d['full_name']}</strong>,</p>
            <p style="margin:16px 0 0;font-size:0.9rem;color:#7a93b0;">Great news! Your booking for <strong style="color:#e8edf5;">{$d['car_name']}</strong> has been approved by our team.</p>
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
                <td style="padding:12px 20px;border-bottom:1px solid rgba(79,142,247,0.1);">
                  <div style="font-size:11px;color:#7a93b0;text-transform:uppercase;margin-bottom:4px;">Duration</div>
                  <strong style="font-size:14px;color:#e8edf5;">{$days} day(s)</strong>
                </td>
                <td style="padding:12px 20px;border-bottom:1px solid rgba(79,142,247,0.1);">
                  <div style="font-size:11px;color:#7a93b0;text-transform:uppercase;margin-bottom:4px;">Booking Ref</div>
                  <strong style="font-size:14px;color:#4f8ef7;">#BKG-{$booking_id}</strong>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding:16px 20px;background:linear-gradient(135deg,rgba(79,142,247,0.15),rgba(79,142,247,0.05));">
                  <table width="100%">
                    <tr>
                      <td style="font-size:14px;color:#e8edf5;">Total Amount</td>
                      <td style="font-size:16px;font-weight:bold;color:#e8edf5;text-align:right;">LKR {$total}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="background:#131c2e;border-left:1px solid rgba(79,142,247,0.15);border-right:1px solid rgba(79,142,247,0.15);padding:24px 40px;text-align:center;">
            <div style="background:linear-gradient(135deg,rgba(251,191,36,0.15),rgba(251,191,36,0.05));border:1px solid rgba(251,191,36,0.3);border-radius:12px;padding:24px;margin-bottom:20px;">
              <p style="margin:0 0 12px;font-size:1rem;color:#fbbf24;font-weight:700;">⚡ Advance Payment Required</p>
              <p style="margin:0 0 16px;font-size:0.9rem;color:#7a93b0;">To confirm your booking, please pay an advance of:</p>
              <p style="margin:0;font-size:2rem;font-weight:800;color:#fbbf24;">LKR 10,000</p>
              <p style="margin:12px 0 0;font-size:0.8rem;color:#7a93b0;">This will be deducted from your total rental amount.</p>
            </div>
            <a href="http://{$_SERVER['HTTP_HOST']}/carrental/users/payment.php?booking_id={$booking_id}&type=advance" style="display:inline-block;background:linear-gradient(135deg,#4f8ef7,#7db0fb);color:#ffffff;padding:16px 40px;border-radius:10px;font-size:1rem;font-weight:700;text-decoration:none;box-shadow:0 4px 20px rgba(79,142,247,0.3);">Pay Advance Now</a>
            <p style="margin:16px 0 0;font-size:0.8rem;color:#3d5570;">⏰ Please complete payment within <strong style="color:#fbbf24;">2 hours</strong> to confirm your booking.</p>
          </td>
        </tr>
        <tr>
          <td style="background:#131920;border:1px solid rgba(79,142,247,0.15);border-top:none;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 8px;font-size:0.85rem;color:#7a93b0;">Questions? Contact us anytime.</p>
            <p style="margin:0;font-size:0.8rem;color:#3d5570;">Email: amafzhar@gmail.com | Phone: +94 75 45 57 624</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

    require_once __DIR__ . '/mailer_config.php';
    try {
        $mail = getMailer();
        $mail->addAddress($d['email'], $d['full_name']);
        $mail->isHTML(true);
        $mail->Subject = "Booking Approved — Pay LKR 10,000 Advance | CarForYou #BKG-{$booking_id}";
        $mail->Body = $html;
        $mail->AltBody = "Hi {$d['full_name']}, your booking for {$d['car_name']} has been approved! Please pay LKR 10,000 advance within 2 hours to confirm. Payment link: http://{$_SERVER['HTTP_HOST']}/carrental/users/payment.php?booking_id={$booking_id}&type=advance";
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('Advance payment email error: ' . $e->getMessage());
        return false;
    }
}

// ── Helper: send final confirmation email ────────────────────────────────────
function sendFinalConfirmationEmail($conn, int $booking_id): bool {
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
    $balance = $total - 10000;

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
            <p style="margin:16px 0 0;font-size:0.9rem;color:#7a93b0;">Your booking is now confirmed! We look forward to serving you.</p>
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
                <td style="padding:12px 20px;border-bottom:1px solid rgba(79,142,247,0.1);">
                  <div style="font-size:11px;color:#7a93b0;text-transform:uppercase;margin-bottom:4px;">Duration</div>
                  <strong style="font-size:14px;color:#e8edf5;">{$days} day(s)</strong>
                </td>
                <td style="padding:12px 20px;border-bottom:1px solid rgba(79,142,247,0.1);">
                  <div style="font-size:11px;color:#7a93b0;text-transform:uppercase;margin-bottom:4px;">Booking Ref</div>
                  <strong style="font-size:14px;color:#4f8ef7;">#BKG-{$booking_id}</strong>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding:16px 20px;background:linear-gradient(135deg,rgba(34,197,94,0.15),rgba(34,197,94,0.05));">
                  <table width="100%">
                    <tr>
                      <td style="font-size:14px;color:#e8edf5;">Total Amount</td>
                      <td style="font-size:16px;font-weight:bold;color:#22c55e;text-align:right;">LKR {$total}</td>
                    </tr>
                    <tr>
                      <td style="font-size:12px;color:#7a93b0;padding-top:8px;">Advance Paid</td>
                      <td style="font-size:12px;color:#22c55e;text-align:right;padding-top:8px;">- LKR 10,000</td>
                    </tr>
                    <tr>
                      <td style="font-size:14px;color:#e8edf5;padding-top:8px;">Balance to Pay</td>
                      <td style="font-size:16px;font-weight:bold;color:#e8edf5;text-align:right;padding-top:8px;">LKR {$balance}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="background:#131920;border:1px solid rgba(79,142,247,0.15);border-top:none;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 8px;font-size:0.85rem;color:#7a93b0;">Questions? Contact us anytime.</p>
            <p style="margin:0;font-size:0.8rem;color:#3d5570;">Email: amafzhar@gmail.com | Phone: +94 75 45 57 624</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

    require_once __DIR__ . '/mailer_config.php';
    try {
        $mail = getMailer();
        $mail->addAddress($d['email'], $d['full_name']);
        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmed! — {$d['car_name']} | CarForYou #BKG-{$booking_id}";
        $mail->Body = $html;
        $mail->AltBody = "Hi {$d['full_name']}, your booking for {$d['car_name']} is confirmed! Pick-up: {$d['from_date']}, Return: {$d['to_date']}. Total: LKR {$total}. Balance to pay: LKR " . ($total - 10000);
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('Final confirmation email error: ' . $e->getMessage());
        return false;
    }
}

// ── Helper: send cancellation email ──────────────────────────────────────────
function sendCancellationEmail($booking) {
    require_once __DIR__ . '/mailer_config.php';
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
          <td style="background:linear-gradient(135deg,#0d1117 0%,#111b2a 60%);border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;border:1px solid rgba(79,142,247,0.15);border-bottom:none;">
            <h1 style="margin:0;font-size:2rem;font-weight:300;color:#e8edf5;letter-spacing:-0.02em;">Car<span style="color:#4f8ef7;font-style:italic;font-weight:600;">ForYou</span></h1>
          </td>
        </tr>
        <tr>
          <td style="background:#1e2738;border-left:1px solid rgba(79,142,247,0.15);border-right:1px solid rgba(79,142,247,0.15);padding:32px 40px;text-align:center;">
            <span style="display:inline-flex;align-items:center;gap:8px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:30px;padding:8px 20px;font-size:0.82rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#ef4444;margin-bottom:16px;">
              ✕ &nbsp; Booking Cancelled
            </span>
            <p style="margin:0;font-size:1rem;color:#e8edf5;">Hi <strong style="color:#4f8ef7;">{$booking['full_name']}</strong>,</p>
            <p style="margin:16px 0 0;font-size:0.9rem;color:#7a93b0;">Your booking for <strong style="color:#e8edf5;">{$booking['car_name']}</strong> has been cancelled.</p>
            <p style="margin:16px 0 0;font-size:0.85rem;color:#7a93b0;">Booking Reference: <strong style="color:#e8edf5;">#BKG-{$booking['id']}</strong></p>
          </td>
        </tr>
        <tr>
          <td style="background:#131920;border:1px solid rgba(79,142,247,0.15);border-top:none;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 8px;font-size:0.85rem;color:#7a93b0;">You can make a new booking anytime at our website.</p>
            <p style="margin:0;font-size:0.8rem;color:#3d5570;">Questions? Contact us at amafzhar@gmail.com | +94 75 45 57 624</p>
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
        $mail->AltBody = "Hi {$booking['full_name']}, your booking #{$booking['id']} for {$booking['car_name']} has been cancelled.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Cancellation email error: ' . $e->getMessage());
        return false;
    }
}

// ── Helper: Create admin notification ─────────────────────────────────────────
function createNotification($conn, string $type, string $title, string $message, ?int $reference_id = null, string $reference_type = 'booking'): void {
    $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, reference_id, reference_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $type, $title, $message, $reference_id, $reference_type);
    $stmt->execute();
    $stmt->close();
}

// ── Check for new bookings and create notifications ─────────────────────────────
$new_bookings_stmt = $conn->prepare("
    SELECT b.id, b.user_email, c.car_name, u.full_name, b.posting_date
    FROM booking b
    JOIN users u ON u.email = b.user_email
    JOIN cars c ON c.id = b.car_id
    WHERE b.status = 0
    AND b.posting_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND b.id NOT IN (SELECT reference_id FROM notifications WHERE type = 'booking_new' AND reference_type = 'booking')
");
$new_bookings_stmt->execute();
$new_bookings = $new_bookings_stmt->get_result();
$new_bookings_stmt->close();

while ($new_booking = $new_bookings->fetch_assoc()) {
    $notif_title = 'New Booking Request';
    $notif_message = $new_booking['full_name'] . ' booked ' . $new_booking['car_name'] . ' - awaiting your approval';
    createNotification($conn, 'booking_new', $notif_title, $notif_message, $new_booking['id'], 'booking');
}

// ── AUTO-CANCEL: awaiting payment expired (2 hours) ───────────────────────────
function autoCancelExpiredPayments($conn) {
    $stmt = $conn->prepare("
        SELECT b.id, b.user_email, c.car_name, u.full_name, u.email
        FROM booking b
        JOIN cars c ON c.id = b.car_id
        JOIN users u ON u.email = b.user_email
        WHERE b.status = 'awaiting_payment'
        AND b.confirmed_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $stmt->execute();
    $bookings = $stmt->get_result();
    $stmt->close();
    
    $cancelled = 0;
    while ($booking = $bookings->fetch_assoc()) {
        $update = $conn->prepare("UPDATE booking SET status = 2 WHERE id = ?");
        $update->bind_param("i", $booking['id']);
        if ($update->execute()) {
            $cancelled++;
            sendCancellationEmail($booking);
            createNotification($conn, 'warning', 'Payment Expired', $booking['full_name'] . ' - ' . $booking['car_name'] . ' - Payment not received within 2 hours, auto-cancelled', $booking['id'], 'booking');
        }
        $update->close();
    }
    return $cancelled;
}

// Run auto-cancel on page load
$cancelled_count = autoCancelExpiredPayments($conn);

// ── ADMIN: CONFIRM BOOKING (sends advance payment request) ───────────────────
if (isset($_GET['aeid'])) {
    $aeid = intval($_GET['aeid']);
    $stmt = $conn->prepare("UPDATE booking SET status = 'awaiting_payment', confirmed_at = NOW() WHERE id = ? AND status = 0");
    $stmt->bind_param("i", $aeid);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $sent = sendAdvancePaymentEmail($conn, $aeid);
        $msg = $sent
            ? "Booking approved! Email sent to customer requesting LKR 10,000 advance payment."
            : "Booking approved. (Email may have failed — check mail server.)";
        
        // Get booking details for notification
        $detail_stmt = $conn->prepare("SELECT b.*, c.car_name, u.full_name FROM booking b JOIN cars c ON c.id = b.car_id JOIN users u ON u.email = b.user_email WHERE b.id = ?");
        $detail_stmt->bind_param("i", $aeid);
        $detail_stmt->execute();
        $booking_detail = $detail_stmt->get_result()->fetch_assoc();
        $detail_stmt->close();
        
        if ($booking_detail) {
            createNotification($conn, 'payment', 'Payment Request Sent', $booking_detail['full_name'] . ' - ' . $booking_detail['car_name'] . ' - Awaiting LKR 10,000 advance payment', $aeid, 'booking');
        }
    } else {
        $error = "Booking not found or already processed.";
    }
}

// ── ADMIN: CANCEL BOOKING ────────────────────────────────────────────────────
if (isset($_GET['eid'])) {
    $eid = intval($_GET['eid']);
    $stmt_sel = $conn->prepare("SELECT b.*, c.car_name, u.full_name, u.email FROM booking b JOIN cars c ON c.id = b.car_id JOIN users u ON u.email = b.user_email WHERE b.id = ?");
    $stmt_sel->bind_param("i", $eid);
    $stmt_sel->execute();
    $booking = $stmt_sel->get_result()->fetch_assoc();
    $stmt_sel->close();
    
    if ($booking) {
        $stmt = $conn->prepare("UPDATE booking SET status = 2 WHERE id = ?");
        $stmt->bind_param("i", $eid);
        if ($stmt->execute()) {
            sendCancellationEmail($booking);
            createNotification($conn, 'error', 'Booking Cancelled', $booking['full_name'] . ' - ' . $booking['car_name'] . ' has been cancelled', $eid, 'booking');
            $msg = "Booking cancelled and customer notified.";
        } else {
            $error = "Error cancelling booking.";
        }
    } else {
        $error = "Booking not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings | CarForYou Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root { --sw:268px; --tr:0.35s cubic-bezier(0.4,0,0.2,1); }

        [data-theme="dark"] {
            --bg:#0d1117; --bg2:#131920; --surface:#1e2738; --surface2:#253044;
            --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.16);
            --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
            --accent:#4f8ef7; --accent2:#7db0fb; --glow:rgba(79,142,247,0.22);
            --sbg:#0a1020; --sborder:rgba(79,142,247,0.1);
            --cshadow:0 4px 24px rgba(0,0,0,0.35);
            --hbg:rgba(13,17,23,0.9);
        }
        [data-theme="light"] {
            --bg:#f0f4f8; --bg2:#e8edf3; --surface:#ffffff; --surface2:#f5f7fa;
            --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
            --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
            --accent:#2563eb; --accent2:#3b82f6; --glow:rgba(37,99,235,0.16);
            --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06);
            --cshadow:0 4px 20px rgba(28,43,58,0.08);
            --hbg:rgba(240,244,248,0.92);
        }

        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        html{font-size:16px;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background var(--tr),color var(--tr);}
        ::-webkit-scrollbar{width:4px;}
        ::-webkit-scrollbar-track{background:var(--bg);}
        ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px;}
        a{text-decoration:none;color:inherit;}

        /* SIDEBAR */
        .sidebar{width:var(--sw);min-height:100vh;background:var(--sbg);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;border-right:1px solid var(--sborder);z-index:100;overflow-y:auto;transition:background var(--tr);}
        .sb-brand{padding:28px 24px 20px;border-bottom:1px solid var(--sborder);}
        .sb-brand h2{font-family:'Syne',sans-serif;font-size:1.45rem;font-weight:800;color:#e8edf5;letter-spacing:0.01em;}
        .sb-brand h2 span{color:var(--accent);}
        .sb-brand p{font-size:0.68rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:rgba(232,237,245,0.3);margin-top:4px;}
        .sb-section{font-size:0.62rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:rgba(232,237,245,0.25);padding:22px 24px 6px;}
        .sb-menu{list-style:none;padding:6px 12px;}
        .sb-menu li{margin-bottom:2px;}
        .sb-menu li a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;font-size:0.86rem;font-weight:500;color:rgba(232,237,245,0.5);transition:all 0.2s;}
        .sb-menu li a i{width:18px;text-align:center;font-size:0.85rem;}
        .sb-menu li:hover a{background:rgba(79,142,247,0.09);color:rgba(232,237,245,0.88);}
        .sb-menu li.active a{background:linear-gradient(90deg,rgba(79,142,247,0.2),rgba(79,142,247,0.05));color:var(--accent);font-weight:600;box-shadow:inset 3px 0 0 var(--accent);}
        .sb-menu li.active a i{color:var(--accent);}
        .sb-divider{height:1px;background:var(--sborder);margin:10px 0;}

        /* MAIN */
        .main{margin-left:var(--sw);width:calc(100% - var(--sw));min-height:100vh;display:flex;flex-direction:column;}

        /* TOPBAR */
        .top-bar{position:sticky;top:0;z-index:50;background:var(--hbg);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 36px;height:66px;display:flex;align-items:center;justify-content:space-between;transition:background var(--tr);}
        .tb-left h2{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:var(--text);letter-spacing:-0.01em;}
        .tb-left p{font-size:0.73rem;color:var(--text2);margin-top:1px;}
        .tb-right{display:flex;align-items:center;gap:10px;}
        .theme-btn{width:37px;height:37px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.88rem;transition:all 0.2s;}
        .theme-btn:hover{border-color:var(--accent);color:var(--accent);box-shadow:0 0 10px var(--glow);}
        .admin-pill{display:flex;align-items:center;gap:9px;background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:6px 13px;}
        .av{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:800;color:#fff;}
        .admin-pill .aname{font-size:0.82rem;font-weight:600;color:var(--text);}
        .admin-pill .arole{font-size:0.68rem;color:var(--text2);}

        /* BODY */
        .body{padding:26px 36px;flex:1;}

        /* ALERTS */
        .alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:10px;font-size:0.86rem;font-weight:500;margin-bottom:20px;opacity:0;animation:fadeUp 0.4s ease forwards;}
        .alert i{font-size:0.95rem;}
        .alert-success{background:rgba(34,197,94,0.1);color:#22c55e;border:1px solid rgba(34,197,94,0.2);}
        .alert-error{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2);}
        .alert-info{background:rgba(79,142,247,0.1);color:#4f8ef7;border:1px solid rgba(79,142,247,0.2);}
        .alert-warn{background:rgba(245,158,11,0.1);color:#f59e0b;border:1px solid rgba(245,158,11,0.2);}

        /* CARD */
        .card{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:22px;transition:background var(--tr),border-color var(--tr);opacity:0;animation:fadeUp 0.5s ease 0.1s forwards;}
        .card-head{display:flex;justify-content:space-between;align-items:center;padding-bottom:14px;margin-bottom:14px;border-bottom:1px solid var(--border);}
        .card-head h3{font-family:'Syne',sans-serif;font-size:0.95rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;}
        .card-head h3 i{color:var(--accent);font-size:0.85rem;}
        .count-pill{font-size:0.72rem;font-weight:700;background:var(--glow);color:var(--accent);padding:3px 10px;border-radius:20px;letter-spacing:0.04em;}

        /* TABLE */
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;min-width:900px;}
        th{font-size:0.65rem;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:var(--text3);padding:0 14px 11px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
        td{padding:13px 14px;font-size:0.855rem;color:var(--text2);border-bottom:1px solid var(--border);transition:background 0.15s;vertical-align:middle;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(79,142,247,0.04);color:var(--text);}
        td strong{color:var(--text);font-weight:600;}
        .row-num{font-family:'Syne',sans-serif;font-size:0.78rem;font-weight:700;color:var(--text3);}

        /* STATUS BADGES */
        .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:0.68rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;white-space:nowrap;}
        .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
        .badge.pending{background:rgba(245,158,11,0.12);color:#f59e0b;}
        .badge.awaiting{background:rgba(0,212,255,0.12);color:#00d4ff;}
        .badge.confirmed{background:rgba(34,197,94,0.12);color:#22c55e;}
        .badge.cancelled{background:rgba(239,68,68,0.12);color:#ef4444;}
        .badge-paid{background:rgba(34,197,94,0.12);color:#22c55e;}
        .badge-unpaid{background:rgba(245,158,11,0.12);color:#f59e0b;}

        /* ACTION BUTTONS */
        .acts{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
        .abt{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;font-size:0.74rem;font-weight:600;border:1px solid transparent;cursor:pointer;transition:all 0.2s;text-decoration:none;white-space:nowrap;}
        .abt-ok{color:#22c55e;border-color:rgba(34,197,94,0.3);background:rgba(34,197,94,0.07);}
        .abt-ok:hover{background:#22c55e;color:#fff;box-shadow:0 2px 10px rgba(34,197,94,0.3);}
        .abt-cx{color:#ef4444;border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.07);}
        .abt-cx:hover{background:#ef4444;color:#fff;box-shadow:0 2px 10px rgba(239,68,68,0.3);}
        .done-tag{font-size:0.74rem;font-style:italic;color:var(--text3);}

        /* car cell */
        .car-cell{display:flex;align-items:center;gap:10px;}
        .car-thumb{width:58px;height:38px;border-radius:7px;object-fit:cover;border:1px solid var(--border2);flex-shrink:0;background:var(--surface2);}
        .car-thumb-placeholder{width:58px;height:38px;border-radius:7px;border:1px dashed var(--border2);display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:1rem;flex-shrink:0;}
        .car-name-sm{font-size:0.84rem;font-weight:600;color:var(--text);}
        .car-type-sm{font-size:0.7rem;color:var(--text3);margin-top:2px;}

        .msg-preview{max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.82rem;color:var(--text2);}
        .empty-row td{text-align:center;padding:44px;color:var(--text3);font-size:0.85rem;}
        .empty-row td i{display:block;font-size:2rem;margin-bottom:10px;opacity:0.3;}

        /* email sent indicator */
        .email-sent-tag{display:inline-flex;align-items:center;gap:4px;font-size:0.68rem;color:#22c55e;margin-top:3px;}

        @keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
        
        /* MOBILE RESPONSIVE */
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);z-index:999;transition:transform 0.3s ease;}
            .sidebar.open{transform:translateX(0);}
            .main{margin-left:0!important;width:100%!important;}
            .top-bar{padding:0 16px;height:56px;}
            .body{padding:16px;}
            .mobile-menu-btn{display:flex!important;}
            .tb-left h2{font-size:0.95rem;}
            table{font-size:0.75rem;}
            th,td{padding:8px 6px;}
            th{font-size:0.6rem;}
            .badge{font-size:0.6rem;padding:2px 6px;}
            .btn-action{font-size:0.65rem;padding:4px 8px;}
            .filter-bar select,.filter-bar input,.filter-bar button{padding:8px 12px;font-size:0.8rem;}
        }
        @media(max-width:480px){
            .stats-grid{grid-template-columns:1fr 1fr;}
            table{font-size:0.7rem;}
            th,td{padding:6px 4px;}
            .car-cell{flex-direction:column;align-items:flex-start;gap:4px;}
            .car-thumb{width:40px;height:28px;}
        }
        .mobile-menu-btn{
            display:none;width:40px;height:40px;background:var(--surface);
            border:1px solid var(--border2);border-radius:8px;cursor:pointer;
            align-items:center;justify-content:center;color:var(--text2);font-size:1rem;
            margin-right:12px;
        }
        .mobile-menu-btn:hover{border-color:var(--accent);color:var(--accent);}
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sb-brand">
        <h2>Car<span>ForYou</span></h2>
        <p>Admin Console</p>
    </div>
    <div class="sb-section">Main Menu</div>
    <ul class="sb-menu">
        <li><a href="admin_dashboard.php"><i class="fa fa-table-columns"></i> Dashboard</a></li>
        <li><a href="car.php"><i class="fa fa-car"></i> Cars</a></li>
        <li class="active"><a href="bookings.php"><i class="fa fa-calendar-check"></i> Bookings</a></li>
        <li><a href="reg-users.php"><i class="fa fa-users"></i> Registered Users</a></li>
        <div class="sb-section">Finance & Operations</div>
        <li><a href="payment_management.php"><i class="fa fa-credit-card"></i> Payments</a></li>
        <li><a href="car_returns.php"><i class="fa fa-rotate-left"></i> Car Returns</a></li>
        <li class="sb-divider"></li>
    </ul>
    <div class="sb-section">Content</div>
    <ul class="sb-menu">
        <li><a href="testimonials.php"><i class="fa fa-comments"></i> Testimonials</a></li>
        <li><a href="contactus.php"><i class="fa fa-envelope"></i> Contact Queries</a></li>
        <li class="sb-divider"></li>
        <li><a href="logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<!-- MAIN -->
<div class="main">

    <div class="top-bar">
        <div class="tb-left">
            <button class="mobile-menu-btn" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
            <h2>Manage Bookings</h2>
            <p id="dateLabel"></p>
        </div>
        <div class="tb-right">
            <button class="theme-btn" id="themeBtn" title="Toggle Theme">
                <i class="fa fa-moon" id="themeIcon"></i>
            </button>
            <div class="admin-pill">
                <div class="av"><?php echo strtoupper(substr($_SESSION['alogin'] ?? 'A', 0, 1)); ?></div>
                <div>
                    <div class="aname"><?php echo htmlspecialchars($_SESSION['alogin'] ?? 'Admin'); ?></div>
                    <div class="arole">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <div class="body">

        <?php if ($msg): ?>
        <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($cancelled_count > 0): ?>
        <div class="alert alert-info">
            <i class="fa fa-clock"></i> <?php echo $cancelled_count; ?> unpaid booking(s) automatically cancelled (older than 12 hours).
        </div>
        <?php endif; ?>

        <?php
        $result = $conn->query("
            SELECT
                u.full_name, u.email AS user_email,
                b.id, b.car_id, b.from_date, b.to_date,
                b.message, b.status, b.posting_date, b.payment_status,
                c.car_name, c.car_type, c.price_per_day, c.Vimage1
            FROM booking b
            JOIN users u ON u.email = b.user_email
            JOIN cars  c ON c.id    = b.car_id
            ORDER BY b.id DESC
        ");
        $total = $result ? $result->num_rows : 0;
        ?>

        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-calendar-check"></i> Booking Registry</h3>
                <span class="count-pill"><?php echo $total; ?> record<?php echo $total != 1 ? 's' : ''; ?></span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Car</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days / Est. Total</th>
                            <th>Message</th>
                            <th>Booking</th>
                            <th>Payment</th>
                            <th>Booked On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0):
                        $count = 1;
                        while ($row = $result->fetch_assoc()):
                            $st = $row['status'];
                            if ($st == 0 || $st === 'Pending' || $st === 'pending') { $bc='pending'; $bl='Pending'; }
                            elseif ($st === 'awaiting_payment' || $st === 'Awaiting Payment') { $bc='awaiting'; $bl='Awaiting Payment'; }
                            elseif ($st == 1 || $st === 'Confirmed' || $st === 'confirmed') { $bc='confirmed'; $bl='Confirmed'; }
                            else { $bc='cancelled'; $bl='Cancelled'; }

                            $days = max(1, (int)(new DateTime($row['from_date']))->diff(new DateTime($row['to_date']))->days);
                            $est  = 'LKR ' . number_format($days * $row['price_per_day']);
                            $img  = !empty($row['Vimage1']) ? "img/vehicleimages/" . htmlspecialchars($row['Vimage1']) : '';
                    ?>
                        <tr>
                            <td><span class="row-num"><?php echo $count; ?></span></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                <div style="font-size:0.72rem;color:var(--text3);margin-top:2px;">
                                    <?php echo htmlspecialchars($row['user_email']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="car-cell">
                                    <?php if ($img): ?>
                                        <img src="<?php echo $img; ?>" class="car-thumb" alt="car"
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div class="car-thumb-placeholder"><i class="fa fa-car"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="car-name-sm"><?php echo htmlspecialchars($row['car_name']); ?></div>
                                        <div class="car-type-sm"><?php echo htmlspecialchars($row['car_type']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['from_date'])); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['to_date'])); ?></td>
                            <td>
                                <strong><?php echo $days; ?> day<?php echo $days!=1?'s':''; ?></strong>
                                <div style="font-size:0.72rem;color:var(--accent);margin-top:2px;"><?php echo $est; ?></div>
                            </td>
                            <td>
                                <?php if (!empty(trim($row['message']))): ?>
                                <div class="msg-preview" title="<?php echo htmlspecialchars($row['message']); ?>">
                                    <?php echo htmlspecialchars(substr($row['message'], 0, 40)); ?>…
                                </div>
                                <?php else: ?>
                                <span style="font-size:0.75rem;color:var(--text3);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $bc; ?>"><?php echo $bl; ?></span>
                                <?php if ($row['status'] == 1 || $row['status'] === 'confirmed' || $row['status'] === 'Confirmed' || $row['status'] === 'awaiting_payment'): ?>
                                <div class="email-sent-tag"><i class="fa fa-envelope-circle-check"></i> Email sent</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $pstatus = $row['payment_status'] ?? 'unpaid';
                                $pclass = ($pstatus === 'paid') ? 'paid' : 'unpaid';
                                ?>
                                <span class="badge-<?php echo $pclass; ?>"><?php echo ucfirst($pstatus); ?></span>
                            </td>
                            <td style="white-space:nowrap;font-size:0.8rem;">
                                <?php echo date('d M Y', strtotime($row['posting_date'])); ?>
                                <div style="font-size:0.7rem;color:var(--text3);"><?php echo date('h:i A', strtotime($row['posting_date'])); ?></div>
                            </td>
                            <td>
                                <?php if ($st == 0 || $st === 'Pending' || $st === 'pending'): ?>
                                <div class="acts">
                                    <a href="bookings.php?aeid=<?php echo $row['id']; ?>" class="abt abt-ok"
                                       onclick="return confirm('Approve booking and request advance payment from customer?')">
                                        <i class="fa fa-check"></i> Approve
                                    </a>
                                    <a href="bookings.php?eid=<?php echo $row['id']; ?>" class="abt abt-cx"
                                       onclick="return confirm('Cancel this booking?')">
                                        <i class="fa fa-xmark"></i> Cancel
                                    </a>
                                </div>
                                <?php elseif ($st === 'awaiting_payment'): // Awaiting Payment ?>
                                <span class="done-tag" style="color:var(--amber);"><i class="fa fa-clock"></i> Awaiting 2H</span>
                                <?php else: ?>
                                <span class="done-tag">— Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php $count++; endwhile;
                    else: ?>
                        <tr class="empty-row">
                            <td colspan="10"><i class="fa fa-calendar-xmark"></i> No booking records found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    (function(){
        var d=new Date(), D=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        M=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('dateLabel').textContent = D[d.getDay()]+', '+d.getDate()+' '+M[d.getMonth()]+' '+d.getFullYear();
    })();

    var theme = localStorage.getItem('adminTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('adminTheme', theme);
        syncIcon();
    });

    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('open');
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        var sidebar = document.querySelector('.sidebar');
        var menuBtn = document.querySelector('.mobile-menu-btn');
        if (sidebar && menuBtn && window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.alert').forEach(function(el){
            setTimeout(function(){
                el.style.transition='opacity 0.5s ease'; el.style.opacity='0';
                setTimeout(function(){ el.style.display='none'; }, 500);
            }, 4000);
        });
    });
</script>
</body>
</html>

