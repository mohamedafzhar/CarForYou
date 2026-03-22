<?php
session_start();
require_once '../includes/config.php';
userAuth();

$user_id    = intval($_SESSION['user_id']);
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    header('Location: user_booking.php?error=invalid+booking+ID');
    exit();
}

// USE STORED PROCEDURE with error handling
$stmt = $conn->prepare("CALL cancel_booking(?, ?)");
$stmt->bind_param("ii", $booking_id, $user_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['result'] === 'SUCCESS') {
            header('Location: user_booking.php?cancelled=1');
            exit();
        } else {
            $error_msg = str_replace('ERROR: ', '', $row['result']);
            header('Location: user_booking.php?error=' . urlencode($error_msg));
            exit();
        }
    }
    if ($result) mysqli_free_result($result);
} else {
    header('Location: user_booking.php?error=cancellation+failed');
    exit();
}

$stmt->close();
?>
