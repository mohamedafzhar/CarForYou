<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id   = $_SESSION['user_id'];
    $car_id    = mysqli_real_escape_string($conn, $_POST['car_id']);
    $from_date = mysqli_real_escape_string($conn, $_POST['from_date']);
    $to_date   = mysqli_real_escape_string($conn, $_POST['to_date']);

    // Calculate Days
    $start = new DateTime($from_date);
    $end   = new DateTime($to_date);
    $diff  = $start->diff($end);
    $days  = $diff->days;
    if($days <= 0) $days = 1;

    // Get Car Price
    $car_query = mysqli_query($conn, "SELECT price_per_day FROM cars WHERE id = '$car_id'");
    $car_data  = mysqli_fetch_assoc($car_query);
    
    $total_price = ($car_data['price_per_day'] * $days) + 15; // +Service fee

    $sql = "INSERT INTO bookings (user_id, car_id, from_date, to_date, total_price, status, booking_date) 
            VALUES ('$user_id', '$car_id', '$from_date', '$to_date', '$total_price', 'Pending', NOW())";

    if (mysqli_query($conn, $sql)) {
        header("Location: admin_dashboard.php?success=Booking confirmed! Total: $" . $total_price);
    } else {
        header("Location: admin_dashboard.php?error=Booking failed: " . mysqli_error($conn));
    }
}
?>