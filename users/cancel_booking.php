<?php
session_start();
include '../admin/config.php';

// Must be logged in as a user
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id    = intval($_SESSION['user_id']);
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header('Location: user_booking.php?error=invalid');
    exit();
}

// Security check: make sure this booking belongs to the logged-in user AND is still Pending (status=0)
// Users should NOT be able to cancel someone else's booking or an already-confirmed one
$stmt = $conn->prepare("
    SELECT b.id, b.status, b.user_email, u.email
    FROM booking b
    JOIN users u ON u.id = ?
    WHERE b.id = ? AND u.email = b.user_email
");
$stmt->bind_param("ii", $user_id, $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

// Booking not found or doesn't belong to this user
if (!$booking) {
    header('Location: user_booking.php?error=notfound');
    exit();
}

// Already confirmed — can't cancel a confirmed booking
if ($booking['status'] == 1) {
    header('Location: user_booking.php?error=confirmed');
    exit();
}

// All good — delete the booking
$del_stmt = $conn->prepare("DELETE FROM booking WHERE id=?");
$del_stmt->bind_param("i", $booking_id);

if ($del_stmt->execute()) {
    header('Location: user_booking.php?cancelled=1');
} else {
    header('Location: user_booking.php?error=failed');
}
exit();
?>