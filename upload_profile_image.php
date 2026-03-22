<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

$is_admin = isset($_SESSION['admin_id']);
$user_id = $is_admin ? $_SESSION['admin_id'] : $_SESSION['user_id'];
$table = $is_admin ? 'admin' : 'users';

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'No file uploaded';
    if (isset($_FILES['profile_image'])) {
        switch ($_FILES['profile_image']['error']) {
            case UPLOAD_ERR_INI_SIZE: $error_msg = 'File too large'; break;
            case UPLOAD_ERR_FORM_SIZE: $error_msg = 'File too large'; break;
            case UPLOAD_ERR_PARTIAL: $error_msg = 'File was only partially uploaded'; break;
            case UPLOAD_ERR_NO_FILE: $error_msg = 'No file was uploaded'; break;
            default: $error_msg = 'Upload error'; break;
        }
    }
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit();
}

$file = $_FILES['profile_image'];
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP']);
    exit();
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File too large. Max size: 5MB']);
    exit();
}

$stmt = $conn->prepare("SELECT profile_picture FROM $table WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();
$stmt->close();

$base_dir = __DIR__;
$upload_dir = $base_dir . '/profile_images/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create directory: " . $upload_dir);
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit();
    }
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$new_filename = ($is_admin ? 'admin_' : 'user_') . $user_id . '_' . time() . '.' . $extension;
$upload_path = $upload_dir . $new_filename;
$relative_path = 'profile_images/' . $new_filename;

// Try move_uploaded_file first, fallback to copy if it fails
$upload_success = false;
if (is_uploaded_file($file['tmp_name'])) {
    $upload_success = move_uploaded_file($file['tmp_name'], $upload_path);
}

if (!$upload_success) {
    // Fallback: try copy (for local testing without HTTP upload)
    if (copy($file['tmp_name'], $upload_path)) {
        $upload_success = true;
    }
}

if (!$upload_success) {
    error_log("Upload failed - tmp_name: " . $file['tmp_name'] . ", upload_path: " . $upload_path);
    echo json_encode(['success' => false, 'error' => 'Failed to save file. Please check directory permissions.']);
    exit();
}

if (!empty($current['profile_picture']) && file_exists($base_dir . '/' . $current['profile_picture'])) {
    @unlink($base_dir . '/' . $current['profile_picture']);
}

$stmt = $conn->prepare("UPDATE $table SET profile_picture = ? WHERE id = ?");
$stmt->bind_param("si", $relative_path, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated',
        'path' => $relative_path,
        'full_path' => 'profile_images/' . $new_filename
    ]);
} else {
    @unlink($upload_path);
    echo json_encode(['success' => false, 'error' => 'Database update failed: ' . mysqli_error($conn)]);
}
$stmt->close();
