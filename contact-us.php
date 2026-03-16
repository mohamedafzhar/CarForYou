<?php
// This file should be in your root directory
include 'admin/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // SQL to insert data into the table that the admin reads from
    $sql = "INSERT INTO contact_us (first_name, last_name, email, subject, message) VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $fname, $lname, $email, $subject, $message);

    if ($stmt->execute()) {
        // Success: Redirect back to the main page with a success flag
        echo "<script>alert('Message sent successfully!');</script>";
        echo "<script>window.location.href='index.php';</script>";
    } else {
        // Error
        echo "<script>alert('Error: Could not send message. Please try again.');</script>";
        echo "<script>window.history.back();</script>";
    }
    
    $stmt->close();
    $conn->close();
} else {
    // If accessed directly without POST
    header("Location: index.php");
    exit();
}