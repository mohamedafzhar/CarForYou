<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/payhere.class.php';

// PayHere IPN (Instant Payment Notification) Handler
// This is called by PayHere when a payment is completed

$payhere = new PayHerePayment();

// Get PayHere response
$merchant_id  = $_POST['merchant_id'] ?? '';
$order_id     = $_POST['order_id'] ?? '';
$payment_id   = $_POST['payment_id'] ?? '';
$amount       = $_POST['amount'] ?? 0;
$currency     = $_POST['currency'] ?? 'LKR';
$status_code = $_POST['status_code'] ?? -1;
$md5sig      = $_POST['md5sig'] ?? '';

// Verify the payment
$verify = $payhere->verifyPayment($_POST);

// Log the payment attempt
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'order_id' => $order_id,
    'payment_id' => $payment_id,
    'amount' => $amount,
    'currency' => $currency,
    'status_code' => $status_code,
    'verified' => $verify['verified'] ? 'YES' : 'NO',
    'success' => $verify['success'] ? 'YES' : 'NO',
    'post_data' => json_encode($_POST)
];

// Log to file
$log_file = __DIR__ . '/payment_log.txt';
$log_entry = implode(' | ', $log_data) . "\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

// If payment is successful and verified
if ($verify['success'] && $verify['verified']) {
    // Get booking from session
    if (isset($_SESSION['payhere_order']) && $_SESSION['payhere_order']['order_id'] === $order_id) {
        $booking_id = $_SESSION['payhere_order']['booking_id'];
        $booking_amount = $_SESSION['payhere_order']['amount'];
        
        // Check if already recorded
        $stmt = $conn->prepare("SELECT id FROM card_payments WHERE booking_id = ? LIMIT 1");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$existing) {
            // Generate receipt
            $receipt_no = 'RCP' . strtoupper(substr(md5($booking_id . time()), 0, 8));
            $now = date('Y-m-d H:i:s');
            
            // Get user email from booking
            $stmt = $conn->prepare("SELECT user_email FROM booking WHERE id = ?");
            $stmt->bind_param("i", $booking_id);
            $stmt->execute();
            $booking_email = $stmt->get_result()->fetch_assoc()['user_email'] ?? '';
            $stmt->close();
            
            // Start transaction
            $conn->begin_transaction();
            try {
                // Save payment record
                $stmt = $conn->prepare("
                    INSERT INTO card_payments (booking_id, user_email, card_type, card_last4, card_holder, expiry_month, expiry_year, amount, payment_date, receipt_no, status, payhere_payment_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
                ");
                $stmt->bind_param("isssssissss", 
                    $booking_id, 
                    $booking_email,
                    'PayHere',
                    '****',
                    'PayHere Customer',
                    '00', '00',
                    $amount,
                    $now,
                    $receipt_no,
                    $payment_id
                );
                $stmt->execute();
                $stmt->close();
                
                // Update booking
                $stmt = $conn->prepare("UPDATE booking SET payment_status = 'paid', payment_date = ?, total_amount = ? WHERE id = ?");
                $stmt->bind_param("sdi", $now, $amount, $booking_id);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("PayHere Payment Error: " . $e->getMessage());
            }
        }
        
        // Clear session
        unset($_SESSION['payhere_order']);
    }
}

// PayHere expects a response
header('Content-Type: text/plain');
echo "OK";
