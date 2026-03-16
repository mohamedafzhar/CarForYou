<?php
session_start();
require_once('../includes/config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=Please login first");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Fetch user data
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    session_destroy();
    header("Location: login.php?error=Account no longer exists");
    exit();
}

// Correct column mapping based on your table
$columns = [];
$columns['name']  = isset($user['full_name']) ? 'full_name' : null;
$columns['email'] = isset($user['email']) ? 'email' : null;
$columns['phone'] = isset($user['contact_no']) ? 'contact_no' : null;

// Handle form submission
if (isset($_POST['update_profile'])) {
    $params = [];
    $types = "";
    $set_parts = [];

    if ($columns['name']) {
        $params[] = mysqli_real_escape_string($conn, $_POST['name']);
        $types .= "s";
        $set_parts[] = $columns['name']." = ?";
    }
    if ($columns['email']) {
        $params[] = mysqli_real_escape_string($conn, $_POST['email']);
        $types .= "s";
        $set_parts[] = $columns['email']." = ?";
    }
    if ($columns['phone']) {
        $params[] = mysqli_real_escape_string($conn, $_POST['phone']);
        $types .= "s";
        $set_parts[] = $columns['phone']." = ?";
    }

    // Add user_id for WHERE clause
    $params[] = $user_id;
    $types .= "i";

    $update_query = "UPDATE users SET ".implode(", ", $set_parts)." WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "Profile updated successfully!";
        // Refresh user data
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
    } else {
        $error_msg = "Update failed: " . mysqli_error($conn);
    }
}

// Prepare values for the form
$user_name  = $columns['name']  ? $user[$columns['name']] : '';
$user_email = $columns['email'] ? $user[$columns['email']] : '';
$user_phone = $columns['phone'] ? $user[$columns['phone']] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | CarForYou</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
    </style>
</head>
<body class="flex min-h-screen">

    <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col">
        <div class="p-6">
            <h1 class="text-2xl font-bold text-blue-600 flex items-center gap-2">
                <i class="fa fa-car-side"></i> CarForYou
            </h1>
        </div>
        <nav class="flex-1 px-4 space-y-2">
            <a href="car_dashboard.php" class="flex items-center gap-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
                <i class="fa fa-th-large"></i> Dashboard
            </a>
            <a href="profile.php" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 text-blue-600 font-bold">
                <i class="fa fa-user-circle"></i> Profile
            </a>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col">
        <header class="bg-white border-b p-4 flex justify-between items-center px-8">
            <h2 class="text-xl font-bold text-gray-800">Edit Settings</h2>
            <a href="profile.php" class="text-sm font-bold text-blue-600 hover:text-blue-800">
                <i class="fa fa-arrow-left mr-1"></i> Back to Profile
            </a>
        </header>

        <div class="p-8 max-w-2xl mx-auto w-full">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold text-gray-900">Update Information</h2>
                <p class="text-gray-500">Keep your contact details up to date</p>
            </div>

            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-3">
                    <i class="fa fa-check-circle"></i>
                    <p class="font-medium"><?php echo $success_msg; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-center gap-3">
                    <i class="fa fa-exclamation-circle"></i>
                    <p class="font-medium"><?php echo $error_msg; ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <form method="POST" class="p-8 space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Full Name</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="fa fa-user"></i>
                                </span>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($user_name); ?>" 
                                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="fa fa-envelope"></i>
                                </span>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" 
                                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                    <i class="fa fa-phone"></i>
                                </span>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>" 
                                    class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" required>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex flex-col sm:flex-row gap-4">
                        <button type="submit" name="update_profile" 
                            class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                            Save Changes
                        </button>
                        <a href="profile.php" 
                            class="flex-1 text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>