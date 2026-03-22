<?php
session_start();
include 'config.php';

require_once '../includes/PHPMailer/src/Exception.php';
require_once '../includes/PHPMailer/src/PHPMailer.php';
require_once '../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg   = "";
$error = "";

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
    if (!$isAjax) {
        adminAuth();
    }
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

// ── AJAX: Check username uniqueness ─────────────────────────────────────────────
if (isset($_GET['check_username'])) {
    header('Content-Type: application/json');
    $username = trim($_GET['check_username']);
    if (strlen($username) < 3) {
        echo json_encode(['available' => false, 'message' => 'Username too short']);
    } else {
        $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode(['available' => $result->num_rows === 0, 'message' => $result->num_rows === 0 ? 'Username available' : 'Username already taken']);
        $stmt->close();
    }
    exit();
}

// ── AJAX: Send OTP for admin registration ──────────────────────────────────────
if (isset($_POST['send_admin_otp'])) {
    header('Content-Type: application/json');
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    
    $response = ['success' => false, 'message' => ''];
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
    } elseif (strlen($username) < 3) {
        $response['message'] = 'Username must be at least 3 characters.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $response['message'] = 'Password does not meet complexity requirements.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $response['message'] = 'Username already exists.';
        } else {
            $stmt->close();
            $stmt = $conn->prepare("SELECT id FROM admin WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $response['message'] = 'Email already registered to another admin.';
            } else {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['admin_reg_otp'] = $otp;
                $_SESSION['admin_reg_data'] = [
                    'username' => $username,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'expires' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
                ];
                
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int)SMTP_PORT;
                    $mail->setFrom(MAIL_FROM, 'CarForYou Admin');
                    $mail->addAddress($email, $username);
                    $mail->isHTML(true);
                    $mail->Subject = 'CarForYou Admin - Email Verification OTP';
                    $mail->Body = "
                        <div style='font-family:sans-serif;max-width:400px;margin:auto;padding:30px;background:#1e2738;color:#e8edf5;border-radius:12px;'>
                            <h2 style='margin:0 0 20px;text-align:center;'>Verify Your Email</h2>
                            <p style='margin:0 0 20px;'>Your verification code is:</p>
                            <div style='font-size:32px;font-weight:bold;letter-spacing:8px;color:#4f8ef7;text-align:center;padding:20px;background:#0d1117;border-radius:8px;margin:20px 0;'>$otp</div>
                            <p style='margin:0;font-size:12px;color:#7a93b0;'>This code expires in 10 minutes.</p>
                        </div>";
                    $mail->send();
                    $response['success'] = true;
                    $response['message'] = 'OTP sent to your email.';
                } catch (Exception $e) {
                    $response['message'] = 'Failed to send OTP: ' . $e->getMessage();
                    error_log('PHPMailer Error: ' . $e->getMessage());
                }
            }
        }
    }
    echo json_encode($response);
    $stmt->close();
    exit();
}

// ── AJAX: Resend OTP ──────────────────────────────────────────────────────────
if (isset($_POST['resend_admin_otp'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['admin_reg_data'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit();
    }
    
    $email    = $_SESSION['admin_reg_data']['email'];
    $username = $_SESSION['admin_reg_data']['username'];
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['admin_reg_otp'] = $otp;
    $_SESSION['admin_reg_data']['expires'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)SMTP_PORT;
        $mail->setFrom(MAIL_FROM, 'CarForYou Admin');
        $mail->addAddress($email, $username);
        $mail->isHTML(true);
        $mail->Subject = 'CarForYou Admin - New Verification OTP';
        $mail->Body = "
            <div style='font-family:sans-serif;max-width:400px;margin:auto;padding:30px;background:#1e2738;color:#e8edf5;border-radius:12px;'>
                <h2 style='margin:0 0 20px;text-align:center;'>New Verification Code</h2>
                <p style='margin:0 0 20px;'>Your new verification code is:</p>
                <div style='font-size:32px;font-weight:bold;letter-spacing:8px;color:#4f8ef7;text-align:center;padding:20px;background:#0d1117;border-radius:8px;margin:20px 0;'>$otp</div>
                <p style='margin:0;font-size:12px;color:#7a93b0;'>This code expires in 10 minutes.</p>
            </div>";
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'New OTP sent to your email.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
    }
    exit();
}

// ── Verify OTP and Create Admin ────────────────────────────────────────────────
if (isset($_POST['verify_admin_otp'])) {
    header('Content-Type: application/json');
    $entered_otp = trim($_POST['otp_code'] ?? '');
    
    if (!isset($_SESSION['admin_reg_otp']) || !isset($_SESSION['admin_reg_data'])) {
        echo json_encode(['success' => false, 'message' => 'Registration session expired. Please try again.']);
        exit();
    } elseif ($entered_otp !== $_SESSION['admin_reg_otp']) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        exit();
    } elseif (strtotime($_SESSION['admin_reg_data']['expires']) < time()) {
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
        unset($_SESSION['admin_reg_otp'], $_SESSION['admin_reg_data']);
        exit();
    } else {
        $data = $_SESSION['admin_reg_data'];
        $stmt = $conn->prepare("INSERT INTO admin (username, password, email, updation_date) VALUES (?,?,?,NOW())");
        $stmt->bind_param("sss", $data['username'], $data['password'], $data['email']);
        if ($stmt->execute()) {
            unset($_SESSION['admin_reg_otp'], $_SESSION['admin_reg_data']);
            $_SESSION['admin_success'] = 'Admin account "' . htmlspecialchars($data['username']) . '" created successfully!';
            echo json_encode(['success' => true, 'message' => 'Admin account created successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating admin: ' . $stmt->error]);
        }
        $stmt->close();
        exit();
    }
}

// ── DELETE USER ──────────────────────────────────────────────────────────────
if (isset($_GET['deluser'])) {
    $id   = intval($_GET['deluser']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $_SESSION[$stmt->execute() ? 'flash_msg' : 'flash_err'] = $stmt->execute() ? "User deleted successfully." : "Error deleting user.";
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) { $_SESSION['flash_msg'] = "User deleted successfully."; }
    else                  { $_SESSION['flash_err'] = "Error deleting user."; }
    header("Location: reg-users.php"); exit();
}

// ── DELETE ADMIN ─────────────────────────────────────────────────────────────
if (isset($_GET['deladmin'])) {
    $id    = intval($_GET['deladmin']);
    $check = $conn->query("SELECT id FROM admin");
    if ($check->num_rows > 1) {
        $stmt = $conn->prepare("DELETE FROM admin WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['flash_msg'] = "Admin account removed.";
    } else {
        $_SESSION['flash_err'] = "Cannot delete the last administrator account.";
    }
    header("Location: reg-users.php"); exit();
}

// Flash messages
if (isset($_SESSION['flash_msg'])) { $msg   = $_SESSION['flash_msg']; unset($_SESSION['flash_msg']); }
if (isset($_SESSION['flash_err'])) { $error = $_SESSION['flash_err']; unset($_SESSION['flash_err']); }
if (isset($_SESSION['admin_success'])) { $msg = $_SESSION['admin_success']; unset($_SESSION['admin_success']); }

// ── UPDATE ADMIN ─────────────────────────────────────────────────────────────
if (isset($_POST['update_admin'])) {
    $id           = intval($_POST['admin_edit_id']);
    $username     = trim($_POST['admin_edit_username']);
    $email        = trim($_POST['admin_edit_email']);
    $new_password = trim($_POST['admin_edit_password']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address for the admin.";
    } else {
        // Check duplicate username
        $chk = $conn->prepare("SELECT id FROM admin WHERE username=? AND id!=?");
        $chk->bind_param("si", $username, $id);
        $chk->execute();
        $chkResult = $chk->get_result();
        $usernameTaken = $chkResult->num_rows > 0;
        $chkResult->free(); $chk->close();

        // Check duplicate email
        $chkE = $conn->prepare("SELECT id FROM admin WHERE email=? AND id!=?");
        $chkE->bind_param("si", $email, $id);
        $chkE->execute();
        $chkEResult = $chkE->get_result();
        $emailTaken = $chkEResult->num_rows > 0;
        $chkEResult->free(); $chkE->close();

        if ($usernameTaken) {
            $error = "That username is already taken.";
        } elseif ($emailTaken) {
            $error = "That email is already used by another admin.";
        } elseif (strlen($username) < 3) {
            $error = "Username must be at least 3 characters.";
        } else {
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters.";
                } else {
                    $hashed = md5($new_password);
                    $stmt   = $conn->prepare("UPDATE admin SET username=?, password=?, email=?, updation_date=NOW() WHERE id=?");
                    $stmt->bind_param("sssi", $username, $hashed, $email, $id);
                    $msg = $stmt->execute() ? "Admin updated successfully." : "Error: " . $stmt->error;
                }
            } else {
                $stmt = $conn->prepare("UPDATE admin SET username=?, email=?, updation_date=NOW() WHERE id=?");
                $stmt->bind_param("ssi", $username, $email, $id);
                $msg = $stmt->execute() ? "Admin updated successfully." : "Error: " . $stmt->error;
            }
        }
    }
}

// ── UPDATE USER ───────────────────────────────────────────────────────────────
if (isset($_POST['update_user'])) {
    $id         = intval($_POST['edit_id']);
    $full_name  = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $contact_no = trim($_POST['contact_no']);
    $city       = trim($_POST['city']);
    $country    = trim($_POST['country']);
    $verified   = isset($_POST['email_verified']) ? 1 : 0;
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $chk->bind_param("si", $email, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "Email already in use by another user.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?,email=?,contact_no=?,city=?,country=?,email_verified=? WHERE id=?");
            $stmt->bind_param("sssssii", $full_name, $email, $contact_no, $city, $country, $verified, $id);
            $msg = $stmt->execute() ? "User updated successfully." : "Error: " . $stmt->error;
        }
    }
}

// ── ADD USER ──────────────────────────────────────────────────────────────────
if (isset($_POST['add_user'])) {
    $full_name  = trim($_POST['new_full_name']);
    $email      = trim($_POST['new_email']);
    $password   = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);
    $contact_no = trim($_POST['new_contact_no']);
    $city       = trim($_POST['new_city']);
    $country    = trim($_POST['new_country']);
    $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $error = "Email already registered.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name,email,password,contact_no,city,country,reg_date) VALUES (?,?,?,?,?,?,NOW())");
        $stmt->bind_param("ssssss", $full_name, $email, $password, $contact_no, $city, $country);
        $msg = $stmt->execute() ? "New user added successfully." : "Error: " . $stmt->error;
    }
}

// ── EDIT / VIEW PREFETCH ──────────────────────────────────────────────────────
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
    $eid  = intval($_GET['edit']);
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

$view_user = null;
if (isset($_GET['view'])) {
    $stmt = $conn->prepare("SELECT u.*, COUNT(b.id) AS total_bookings FROM users u LEFT JOIN booking b ON b.user_email=u.email WHERE u.id=? GROUP BY u.id");
    $vid  = intval($_GET['view']);
    $stmt->bind_param("i", $vid);
    $stmt->execute();
    $view_user = $stmt->get_result()->fetch_assoc();
}

$resAdmin    = $conn->query("SELECT * FROM admin ORDER BY id ASC");
$resUser     = $conn->query("SELECT * FROM users ORDER BY reg_date DESC");
$admin_total = $resAdmin ? $resAdmin->num_rows : 0;
$user_total  = $resUser  ? $resUser->num_rows  : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | CarForYou Admin</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect fill='%230d1117' width='100' height='100' rx='20'/><path d='M20 55 L25 45 L40 40 L60 40 L75 45 L80 55 L80 60 L20 60 Z' fill='none' stroke='%234f8ef7' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/><circle cx='30' cy='62' r='6' fill='%234f8ef7'/><circle cx='70' cy='62' r='6' fill='%234f8ef7'/><path d='M28 50 L30 45 L35 42 L65 42 L70 45 L72 50' fill='none' stroke='%234f8ef7' stroke-width='2' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root { --sw:268px; --tr:0.35s cubic-bezier(0.4,0,0.2,1); }
        .hamburger{display:none;width:38px;height:38px;border-radius:9px;border:1px solid var(--border2);background:var(--surface);color:var(--text2);cursor:pointer;font-size:0.95rem;transition:all 0.2s;}
        .hamburger:hover{border-color:var(--accent);color:var(--accent);}
        .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;}
        @media (max-width:768px){
            .hamburger{display:flex;align-items:center;justify-content:center;}
            .sidebar{transform:translateX(-100%);z-index:200;}
            .sidebar.open{transform:translateX(0);}
            .sidebar-overlay.open{display:block;}
            .main{margin-left:0;width:100%;}
            .body{padding:20px 16px;}
            .card{padding:18px 16px;}
            .form-grid{grid-template-columns:1fr;}
            .form-group.full,.span-2,.span-3{grid-column:span 1;}
            .top-bar{padding:0 16px;}
            table{font-size:0.8rem;}
            td,th{padding:10px 8px;}
        }

        [data-theme="dark"] {
            --bg:#0d1117; --bg2:#131920; --surface:#1e2738; --surface2:#253044;
            --border:rgba(99,155,255,0.08); --border2:rgba(99,155,255,0.16);
            --text:#e8edf5; --text2:#7a93b0; --text3:#3d5570;
            --accent:#4f8ef7; --accent2:#7db0fb; --glow:rgba(79,142,247,0.22);
            --sbg:#0a1020; --sborder:rgba(79,142,247,0.1);
            --cshadow:0 4px 24px rgba(0,0,0,0.35); --hbg:rgba(13,17,23,0.9);
            --modal-bg:#1e2738; --input-bg:#253044; --input-border:rgba(99,155,255,0.18);
            --overlay:rgba(0,0,0,0.7);
        }
        [data-theme="light"] {
            --bg:#f0f4f8; --bg2:#e8edf3; --surface:#ffffff; --surface2:#f5f7fa;
            --border:rgba(99,120,155,0.12); --border2:rgba(99,120,155,0.22);
            --text:#1c2b3a; --text2:#4a607a; --text3:#8fa3bb;
            --accent:#2563eb; --accent2:#3b82f6; --glow:rgba(37,99,235,0.16);
            --sbg:#1c2b3a; --sborder:rgba(255,255,255,0.06);
            --cshadow:0 4px 20px rgba(28,43,58,0.08); --hbg:rgba(240,244,248,0.92);
            --modal-bg:#ffffff; --input-bg:#ffffff; --input-border:rgba(99,120,155,0.28);
            --overlay:rgba(13,17,23,0.6);
        }

        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        html{font-size:16px;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;transition:background var(--tr),color var(--tr);}
        ::-webkit-scrollbar{width:4px;} ::-webkit-scrollbar-track{background:var(--bg);} ::-webkit-scrollbar-thumb{background:var(--accent);border-radius:4px;}
        a{text-decoration:none;color:inherit;}

        /* SIDEBAR */
        .sidebar{width:var(--sw);min-height:100vh;background:var(--sbg);position:fixed;top:0;left:0;bottom:0;display:flex;flex-direction:column;border-right:1px solid var(--sborder);z-index:100;overflow-y:auto;transition:background var(--tr);}
        .sb-brand{padding:28px 24px 20px;border-bottom:1px solid var(--sborder);}
        .sb-brand h2{font-family:'Syne',sans-serif;font-size:1.45rem;font-weight:800;color:#e8edf5;}
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

        /* CARDS */
        .card{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:24px;margin-bottom:20px;transition:background var(--tr),border-color var(--tr);opacity:0;animation:fadeUp 0.5s ease forwards;}
        .card:nth-of-type(1){animation-delay:0.05s} .card:nth-of-type(2){animation-delay:0.15s}
        .card-head{display:flex;justify-content:space-between;align-items:center;padding-bottom:16px;margin-bottom:16px;border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap;}
        .card-head h3{font-family:'Syne',sans-serif;font-size:0.95rem;font-weight:700;color:var(--text);display:flex;align-items:center;gap:9px;}
        .card-head h3 i{color:var(--accent);font-size:0.85rem;}
        .count-pill{font-size:0.72rem;font-weight:700;background:var(--glow);color:var(--accent);padding:3px 10px;border-radius:20px;}

        /* ADD BUTTON */
        .btn-add{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:600;background:var(--accent);color:#fff;border:none;cursor:pointer;transition:all 0.2s;box-shadow:0 3px 10px var(--glow);}
        .btn-add:hover{opacity:0.88;transform:translateY(-1px);}

        /* TABLE */
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;min-width:680px;}
        th{font-size:0.65rem;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:var(--text3);padding:0 14px 11px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
        td{padding:13px 14px;font-size:0.855rem;color:var(--text2);border-bottom:1px solid var(--border);transition:background 0.15s;vertical-align:middle;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(79,142,247,0.04);}
        td strong{color:var(--text);font-weight:600;}
        .row-num{font-family:'Syne',sans-serif;font-size:0.78rem;font-weight:700;color:var(--text3);}
        .user-cell .uname{font-weight:600;color:var(--text);font-size:0.88rem;}
        .user-cell .uemail{font-size:0.75rem;color:var(--text3);margin-top:2px;display:flex;align-items:center;gap:3px;}

        /* EMAIL PILL in admin table */
        .email-pill{display:inline-flex;align-items:center;gap:5px;font-size:0.75rem;color:var(--text2);background:var(--surface2);border:1px solid var(--border2);border-radius:20px;padding:3px 10px;}
        .email-pill i{color:var(--accent);font-size:0.65rem;}
        .email-missing{display:inline-flex;align-items:center;gap:5px;font-size:0.75rem;color:#f59e0b;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.25);border-radius:20px;padding:3px 10px;}
        .email-missing i{font-size:0.65rem;}

        /* BADGES */
        .badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.68rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;}
        .badge::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor;}
        .badge-admin{background:rgba(79,142,247,0.12);color:var(--accent);}
        .badge-user{background:rgba(34,197,94,0.12);color:#22c55e;}

        /* ACTION BUTTONS */
        .acts{display:flex;gap:6px;}
        .abt{width:30px;height:30px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;font-size:0.78rem;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;}
        .abt-view{background:rgba(79,142,247,0.1);color:var(--accent);}
        .abt-view:hover{background:var(--accent);color:#fff;}
        .abt-edit{background:rgba(245,158,11,0.1);color:#f59e0b;}
        .abt-edit:hover{background:#f59e0b;color:#fff;}
        .abt-delete{background:rgba(239,68,68,0.1);color:#ef4444;}
        .abt-delete:hover{background:#ef4444;color:#fff;}
        .empty-row td{text-align:center;padding:40px;color:var(--text3);font-size:0.84rem;}

        /* MODALS */
        .modal-overlay{display:none;position:fixed;inset:0;background:var(--overlay);backdrop-filter:blur(4px);z-index:500;align-items:center;justify-content:center;padding:20px;}
        .modal-overlay.open{display:flex;}
        .modal{background:var(--modal-bg);border:1px solid var(--border2);border-radius:16px;padding:30px;width:100%;max-width:520px;position:relative;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,0.4);animation:modalIn 0.28s cubic-bezier(0.34,1.56,0.64,1) forwards;}
        @keyframes modalIn{from{opacity:0;transform:scale(0.92) translateY(20px);}to{opacity:1;transform:scale(1) translateY(0);}}
        .modal h3{font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;color:var(--text);margin-bottom:22px;display:flex;align-items:center;gap:10px;}
        .modal h3 i{font-size:0.9rem;}
        .modal-close{position:absolute;top:14px;right:14px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:0.85rem;color:var(--text2);display:flex;align-items:center;justify-content:center;transition:all 0.2s;}
        .modal-close:hover{background:var(--border2);color:var(--text);}

        /* EMAIL REQUIRED NOTICE */
        .email-notice{display:flex;align-items:flex-start;gap:9px;background:rgba(79,142,247,0.07);border:1px solid rgba(79,142,247,0.2);border-radius:9px;padding:11px 14px;font-size:0.78rem;color:var(--text2);margin-bottom:16px;line-height:1.5;}
        .email-notice i{color:var(--accent);flex-shrink:0;margin-top:1px;}
        .email-notice strong{color:var(--accent);}

        /* FORM */
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;}
        .form-group{display:flex;flex-direction:column;gap:5px;}
        .form-group.full{grid-column:span 2;}
        .form-group label{font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text3);}
        .form-group label .req{color:#ef4444;margin-left:2px;}
        .form-control{padding:10px 12px;background:var(--input-bg);border:1px solid var(--input-border);border-radius:8px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.875rem;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
        .form-control::placeholder{color:var(--text3);}
        .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--glow);}
        .form-control.required-empty{border-color:#ef4444 !important;box-shadow:0 0 0 3px rgba(239,68,68,0.12) !important;}
        .form-hint{font-size:0.72rem;color:var(--text3);margin-top:2px;}
        .btn-submit{width:100%;padding:11px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;border:none;cursor:pointer;margin-top:4px;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:8px;}
        .btn-submit.blue{background:var(--accent);color:#fff;box-shadow:0 3px 12px var(--glow);}
        .btn-submit.amber{background:#f59e0b;color:#fff;box-shadow:0 3px 12px rgba(245,158,11,0.3);}
        .btn-submit:hover{opacity:0.88;transform:translateY(-1px);}
        .pwd-error{display:none;color:#ef4444;font-size:0.78rem;margin-bottom:10px;gap:6px;align-items:center;}
        .info-box{background:rgba(79,142,247,0.08);border:1px solid rgba(79,142,247,0.18);border-radius:9px;padding:11px 14px;font-size:0.78rem;color:var(--text2);margin-bottom:16px;display:flex;gap:8px;align-items:flex-start;}
        .info-box i{color:var(--accent);margin-top:1px;flex-shrink:0;}

        /* VIEW MODAL */
        .view-avatar{width:60px;height:60px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:#fff;margin:0 auto 12px;}
        .view-name{text-align:center;font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;color:var(--text);}
        .view-email{text-align:center;font-size:0.8rem;color:var(--text3);margin-top:3px;margin-bottom:20px;}
        .view-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;}
        .stat-box{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center;}
        .stat-box .snum{font-family:'Syne',sans-serif;font-size:1.6rem;font-weight:800;color:var(--accent);line-height:1;}
        .stat-box .slbl{font-size:0.7rem;color:var(--text3);margin-top:4px;text-transform:uppercase;letter-spacing:0.07em;font-weight:600;}
        .detail-list{border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:18px;}
        .detail-row{display:flex;justify-content:space-between;align-items:center;padding:11px 14px;border-bottom:1px solid var(--border);}
        .detail-row:last-child{border-bottom:none;}
        .detail-row:hover{background:rgba(79,142,247,0.04);}
        .dl{font-size:0.75rem;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;}
        .dv{font-size:0.85rem;color:var(--text);font-weight:600;}

        @keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
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
        <li><a href="bookings.php"><i class="fa fa-calendar-check"></i> Bookings</a></li>
        <li class="active"><a href="reg-users.php"><i class="fa fa-users"></i> Registered Users</a></li>
        <div class="sb-section">Finance &amp; Operations</div>
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

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN -->
<div class="main">

    <div class="top-bar">
        <div class="tb-left" style="display:flex;align-items:center;gap:12px;">
            <button class="hamburger" id="hamburgerBtn"><i class="fa fa-bars"></i></button>
            <div>
                <h2>User Management</h2>
                <p id="dateLabel"></p>
            </div>
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
        <div class="alert alert-success" id="successMsg" style="display:none;"><i class="fa fa-circle-check"></i> <span></span></div>

        <!-- ADMIN ACCOUNTS -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-shield-halved"></i> Admin Accounts</h3>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="count-pill"><?php echo $admin_total; ?> admin<?php echo $admin_total != 1 ? 's' : ''; ?></span>
                    <button class="btn-add" onclick="openModal('addAdminModal')">
                        <i class="fa fa-user-shield"></i> Add Admin
                    </button>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $cnt = 1; while ($row = $resAdmin->fetch_assoc()): ?>
                        <tr>
                            <td><span class="row-num"><?php echo $cnt++; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                            <td>
                                <?php if (!empty($row['email'])): ?>
                                    <span class="email-pill">
                                        <i class="fa fa-envelope"></i>
                                        <?php echo htmlspecialchars($row['email']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="email-missing">
                                        <i class="fa fa-triangle-exclamation"></i> No email — add one!
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-admin">Administrator</span></td>
                            <td style="font-size:0.78rem;color:var(--text3);"><?php echo $row['updation_date'] ?? '—'; ?></td>
                            <td>
                                <div class="acts">
                                    <button class="abt abt-edit" title="Edit"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>"
                                        data-email="<?php echo htmlspecialchars($row['email'] ?? '', ENT_QUOTES); ?>"
                                        onclick="openEditAdminModal(this)">
                                        <i class="fa fa-pen"></i>
                                    </button>
                                    <a href="reg-users.php?deladmin=<?php echo $row['id']; ?>"
                                       onclick="return confirm('Delete this admin account?')"
                                       class="abt abt-delete" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- REGISTERED USERS -->
        <div class="card">
            <div class="card-head">
                <h3><i class="fa fa-users"></i> Registered Customers</h3>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="count-pill"><?php echo $user_total; ?> user<?php echo $user_total != 1 ? 's' : ''; ?></span>
                    <button class="btn-add" onclick="openModal('addUserModal')">
                        <i class="fa fa-user-plus"></i> Add User
                    </button>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Reg Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resUser && $resUser->num_rows > 0): $cnt = 1;
                        while ($row = $resUser->fetch_assoc()): ?>
                        <tr>
                            <td><span class="row-num"><?php echo $cnt++; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                            <td>
                                <?php 
                                $userEmail = htmlspecialchars($row['email']);
                                $verified = !empty($row['email_verified']);
                                ?>
                                <span style="font-size:0.82rem;color:var(--text2);display:flex;align-items:center;gap:5px;">
                                    <?php echo $userEmail; ?>
                                    <?php if ($verified): ?>
                                        <i class="fa fa-circle-check" style="color:#22c55e;font-size:0.7rem;" title="Verified"></i>
                                    <?php else: ?>
                                        <i class="fa fa-circle-exclamation" style="color:#f59e0b;font-size:0.7rem;" title="Not Verified"></i>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars($row['contact_no'] ?: '—'); ?></td>
                            <td style="font-size:0.82rem;"><?php echo htmlspecialchars(trim($row['city'].', '.$row['country'], ', ')); ?></td>
                            <td style="font-size:0.78rem;color:var(--text3);"><?php echo date('d M Y', strtotime($row['reg_date'])); ?></td>
                            <td>
                                <div class="acts">
                                    <a href="reg-users.php?view=<?php echo $row['id']; ?>" class="abt abt-view" title="View"><i class="fa fa-eye"></i></a>
                                    <button class="abt abt-edit" title="Edit"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-fullname="<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES); ?>"
                                        data-email="<?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?>"
                                        data-email-verified="<?php echo $row['email_verified'] ?? 0; ?>"
                                        data-contact="<?php echo htmlspecialchars($row['contact_no'] ?? '', ENT_QUOTES); ?>"
                                        data-city="<?php echo htmlspecialchars($row['city'] ?? '', ENT_QUOTES); ?>"
                                        data-country="<?php echo htmlspecialchars($row['country'] ?? '', ENT_QUOTES); ?>"
                                        onclick="openEditUserModal(this)">
                                        <i class="fa fa-pen"></i>
                                    </button>
                                    <a href="reg-users.php?deluser=<?php echo $row['id']; ?>"
                                       onclick="return confirm('Delete this customer?')"
                                       class="abt abt-delete" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr class="empty-row"><td colspan="7">No customers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ══ MODAL: EDIT ADMIN ══ -->
<div class="modal-overlay" id="editAdminModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('editAdminModal')"><i class="fa fa-xmark"></i></button>
        <h3><i class="fa fa-pen-to-square" style="color:#f59e0b;"></i> Edit Admin Account</h3>
        <div class="email-notice">
            <i class="fa fa-circle-info"></i>
            <span><strong>Email is required</strong> so this admin can use the Forgot Password feature to recover their account.</span>
        </div>
        <form method="POST">
            <input type="hidden" name="admin_edit_id" id="admin_edit_id">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="admin_edit_username" id="admin_edit_username" class="form-control" minlength="3" required>
                </div>
                <div class="form-group full">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="admin_edit_email" id="admin_edit_email" class="form-control" placeholder="admin@example.com" required>
                    <span class="form-hint">Used for password recovery — must be valid</span>
                </div>
                <div class="form-group full">
                    <label>New Password</label>
                    <input type="password" name="admin_edit_password" id="admin_edit_password" class="form-control" placeholder="Leave blank to keep current">
                    <span class="form-hint">Minimum 6 characters if changing</span>
                </div>
                <div class="form-group full">
                    <label>Confirm New Password</label>
                    <input type="password" id="admin_edit_confirm" class="form-control" placeholder="Repeat new password">
                </div>
            </div>
            <div class="pwd-error" id="adminEditPwdErr"><i class="fa fa-triangle-exclamation"></i> Passwords do not match.</div>
            <button type="submit" name="update_admin" class="btn-submit amber">
                <i class="fa fa-floppy-disk"></i> Save Changes
            </button>
        </form>
    </div>
</div>

<!-- ══ MODAL: ADD ADMIN ══ -->
<div class="modal-overlay" id="addAdminModal">
    <div class="modal">
        <button class="modal-close" onclick="closeAdminModal()"><i class="fa fa-xmark"></i></button>
        <h3><i class="fa fa-user-shield" style="color:var(--accent);"></i> Add New Admin</h3>
        
        <!-- Step 1: Form -->
        <div id="adminFormStep">
            <div class="email-notice">
                <i class="fa fa-circle-info"></i>
                <span>An OTP will be sent to the email for verification before creating the account.</span>
            </div>
            <form id="adminRegForm">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Username <span class="req">*</span></label>
                        <div style="position:relative;">
                            <input type="text" name="admin_username" id="admin_username" class="form-control" placeholder="e.g. admin2" minlength="3" required onblur="checkUsername(this.value)">
                            <span id="usernameStatus" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:0.75rem;"></span>
                        </div>
                        <span class="form-hint" id="usernameHint">At least 3 characters</span>
                    </div>
                    <div class="form-group full">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="admin_email" id="admin_email" class="form-control" placeholder="admin@example.com" required>
                        <span class="form-hint">OTP will be sent to this email</span>
                    </div>
                    <div class="form-group full">
                        <label>Password <span class="req">*</span></label>
                        <input type="password" name="admin_password" id="admin_password" class="form-control" placeholder="Set a strong password" required oninput="validatePasswordStrength(this.value)">
                        <span class="form-hint" id="passwordHint"></span>
                    </div>
                    <div class="form-group full">
                        <label>Confirm Password <span class="req">*</span></label>
                        <input type="password" id="admin_confirm_pwd" class="form-control" placeholder="Repeat password" required>
                    </div>
                </div>
                <div class="pwd-error" id="adminAddPwdErr"><i class="fa fa-triangle-exclamation"></i> Passwords do not match.</div>
                <div class="pwd-error" id="adminAddPwdStrength"><i class="fa fa-triangle-exclamation"></i> Password does not meet requirements.</div>
                <div class="info-box"><i class="fa fa-circle-info"></i> This admin will have full access to the admin panel.</div>
                <div class="alert alert-error" id="adminFormError" style="display:none;margin-bottom:16px;"></div>
                <button type="submit" class="btn-submit blue" id="sendOtpBtn" disabled>
                    <i class="fa fa-paper-plane"></i> Send OTP to Email
                </button>
            </form>
        </div>
        
        <!-- Step 2: OTP Verification -->
        <div id="adminOtpStep" style="display:none;">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="width:56px;height:56px;background:rgba(79,142,247,0.12);border:2px solid rgba(79,142,247,0.25);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <i class="fa fa-envelope-circle-check" style="font-size:1.4rem;color:#4f8ef7;"></i>
                </div>
                <h2 style="font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;margin-bottom:6px;color:var(--text);">Verify Your Email</h2>
                <p style="font-size:0.78rem;color:var(--text2);margin:0;">Enter the 6-digit code sent to</p>
                <p style="font-size:0.82rem;color:var(--accent);font-weight:600;margin:4px 0 0;" id="otpEmailDisplay"></p>
            </div>
            <form id="otpVerifyForm">
                <div style="text-align:center;margin-bottom:16px;">
                    <label style="display:block;font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--text3);margin-bottom:8px;">Verification Code</label>
                    <input type="text" id="otp_code" name="otp_code" class="form-control" 
                           placeholder="000000" maxlength="6" required 
                           autocomplete="one-time-code" 
                           style="display:block;width:180px;margin:0 auto;text-align:center;font-size:1.4rem;letter-spacing:10px;font-weight:700;padding:10px 12px;"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,6)">
                </div>
                <p style="font-size:0.7rem;color:var(--text3);text-align:center;margin-bottom:16px;">Code expires in <strong id="otpTimer" style="color:var(--accent);">10:00</strong></p>
                <div class="alert alert-error" id="otpError" style="display:none;margin-bottom:14px;"></div>
                <button type="submit" class="btn-submit blue" style="margin-bottom:12px;">
                    <i class="fa fa-check"></i> Verify & Create Admin
                </button>
                <div style="text-align:center;font-size:0.78rem;color:var(--text3);">
                    Didn't receive code? 
                    <button type="button" id="resendOtpBtn" onclick="resendOtp()" style="background:none;border:none;color:var(--accent);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:0.78rem;">
                        Resend
                    </button>
                    <span style="margin:0 8px;">|</span>
                    <button type="button" onclick="backToAdminForm()" style="background:none;border:none;color:var(--text3);cursor:pointer;font-family:'DM Sans',sans-serif;font-size:0.78rem;">
                        <i class="fa fa-arrow-left"></i> Back
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL: ADD USER ══ -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('addUserModal')"><i class="fa fa-xmark"></i></button>
        <h3><i class="fa fa-user-plus" style="color:var(--accent);"></i> Add New Customer</h3>
        <form method="POST" id="addUserForm">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="new_full_name" class="form-control" placeholder="e.g. John Silva" required>
                </div>
                <div class="form-group full">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="new_email" id="new_email" class="form-control" placeholder="user@email.com" required>
                    <span class="form-hint">A verification email will be sent to this address</span>
                </div>
                <div class="form-group full">
                    <label>Password <span class="req">*</span></label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Set a password" minlength="6" required>
                    <span class="form-hint">Minimum 6 characters</span>
                </div>
                <div class="form-group full">
                    <label>Confirm Password <span class="req">*</span></label>
                    <input type="password" id="new_confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
                <div class="form-group">
                    <label>Contact No</label>
                    <input type="text" name="new_contact_no" class="form-control" placeholder="+94 77 000 0000">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="new_city" class="form-control" placeholder="Colombo">
                </div>
                <div class="form-group full">
                    <label>Country</label>
                    <input type="text" name="new_country" class="form-control" placeholder="Sri Lanka">
                </div>
            </div>
            <div class="pwd-error" id="addUserPwdErr"><i class="fa fa-triangle-exclamation"></i> Passwords do not match.</div>
            <button type="submit" name="add_user" class="btn-submit blue">
                <i class="fa fa-plus"></i> Create Account
            </button>
        </form>
    </div>
</div>

<!-- ══ MODAL: EDIT USER ══ -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('editUserModal')"><i class="fa fa-xmark"></i></button>
        <h3><i class="fa fa-user-pen" style="color:#f59e0b;"></i> Edit Customer</h3>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                </div>
                <div class="form-group full">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="form-group full">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="email_verified" id="edit_email_verified" style="width:16px;height:16px;accent-color:var(--accent);">
                        <span>Email Verified</span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Contact No</label>
                    <input type="text" name="contact_no" id="edit_contact_no" class="form-control">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" id="edit_city" class="form-control">
                </div>
                <div class="form-group full">
                    <label>Country</label>
                    <input type="text" name="country" id="edit_country" class="form-control">
                </div>
            </div>
            <button type="submit" name="update_user" class="btn-submit amber">
                <i class="fa fa-floppy-disk"></i> Save Changes
            </button>
        </form>
    </div>
</div>

<!-- ══ MODAL: VIEW USER ══ -->
<div class="modal-overlay <?php echo $view_user ? 'open' : ''; ?>" id="viewModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('viewModal')"><i class="fa fa-xmark"></i></button>
        <?php if ($view_user): ?>
        <h3><i class="fa fa-id-card" style="color:var(--accent);"></i> Customer Profile</h3>
        <div class="view-avatar"><?php echo strtoupper(substr($view_user['full_name'], 0, 1)); ?></div>
        <div class="view-name"><?php echo htmlspecialchars($view_user['full_name']); ?></div>
        <div class="view-email">
            <?php echo htmlspecialchars($view_user['email']); ?>
            <?php if (!empty($view_user['email_verified'])): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;font-size:0.7rem;color:#22c55e;background:rgba(34,197,94,0.1);padding:2px 8px;border-radius:10px;">
                    <i class="fa fa-circle-check"></i> Verified
                </span>
            <?php else: ?>
                <span style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;font-size:0.7rem;color:#f59e0b;background:rgba(245,158,11,0.1);padding:2px 8px;border-radius:10px;">
                    <i class="fa fa-circle-exclamation"></i> Not Verified
                </span>
            <?php endif; ?>
        </div>
        <div class="view-stats">
            <div class="stat-box">
                <div class="snum"><?php echo $view_user['total_bookings']; ?></div>
                <div class="slbl">Total Bookings</div>
            </div>
            <div class="stat-box">
                <div class="snum" style="font-size:1rem;padding-top:6px;">
                    <?php echo htmlspecialchars(trim(($view_user['city']??'').', '.($view_user['country']??''), ', ')); ?>
                </div>
                <div class="slbl">Location</div>
            </div>
        </div>
        <div class="detail-list">
            <div class="detail-row"><span class="dl">Contact</span><span class="dv"><?php echo htmlspecialchars($view_user['contact_no'] ?: '—'); ?></span></div>
            <div class="detail-row"><span class="dl">Date of Birth</span><span class="dv"><?php echo !empty($view_user['dob']) ? date('d M Y', strtotime($view_user['dob'])) : '—'; ?></span></div>
            <div class="detail-row"><span class="dl">Address</span><span class="dv"><?php echo htmlspecialchars($view_user['address'] ?: '—'); ?></span></div>
            <div class="detail-row"><span class="dl">Registered</span><span class="dv"><?php echo date('d M Y', strtotime($view_user['reg_date'])); ?></span></div>
        </div>
        <button
            data-id="<?php echo $view_user['id']; ?>"
            data-fullname="<?php echo htmlspecialchars($view_user['full_name'], ENT_QUOTES); ?>"
            data-email="<?php echo htmlspecialchars($view_user['email'], ENT_QUOTES); ?>"
            data-email-verified="<?php echo $view_user['email_verified'] ?? 0; ?>"
            data-contact="<?php echo htmlspecialchars($view_user['contact_no'] ?? '', ENT_QUOTES); ?>"
            data-city="<?php echo htmlspecialchars($view_user['city'] ?? '', ENT_QUOTES); ?>"
            data-country="<?php echo htmlspecialchars($view_user['country'] ?? '', ENT_QUOTES); ?>"
            onclick='openEditUserModal(this); closeModal("viewModal");'
            class="btn-submit amber">
            <i class="fa fa-pen"></i> Edit This Customer
        </button>
        <?php endif; ?>
    </div>
</div>

<script>
    // Live date
    (function(){
        var d=new Date(),days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        document.getElementById('dateLabel').textContent=days[d.getDay()]+', '+d.getDate()+' '+mo[d.getMonth()]+' '+d.getFullYear();
    })();

    // Theme
    var theme = localStorage.getItem('adminTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    syncIcon();
    document.getElementById('themeBtn').addEventListener('click', function(){
        theme = theme==='dark'?'light':'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('adminTheme', theme);
        syncIcon();
    });
    function syncIcon(){ document.getElementById('themeIcon').className = theme==='dark'?'fa fa-moon':'fa fa-sun'; }

    // Mobile sidebar
    document.getElementById('hamburgerBtn').addEventListener('click', function(){
        document.querySelector('.sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    });
    document.getElementById('sidebarOverlay').addEventListener('click', function(){
        document.querySelector('.sidebar').classList.remove('open');
        this.classList.remove('open');
    });

    // Modal helpers
    function openModal(id){ document.getElementById(id).classList.add('open'); }
    function closeModal(id){
        document.getElementById(id).classList.remove('open');
        if(id==='viewModal'||id==='editUserModal') window.history.replaceState({},'','reg-users.php');
    }
    document.querySelectorAll('.modal-overlay').forEach(function(o){
        o.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
    });

    // Open edit admin modal
    function openEditAdminModal(btn){
        document.getElementById('admin_edit_id').value       = btn.dataset.id;
        document.getElementById('admin_edit_username').value = btn.dataset.username || '';
        document.getElementById('admin_edit_email').value    = btn.dataset.email    || '';
        document.getElementById('admin_edit_password').value = '';
        document.getElementById('admin_edit_confirm').value  = '';
        document.getElementById('adminEditPwdErr').style.display = 'none';
        openModal('editAdminModal');
    }

    // Open edit user modal
    function openEditUserModal(btn){
        var d = (btn instanceof HTMLElement) ? {
            id:btn.dataset.id, full_name:btn.dataset.fullname, email:btn.dataset.email,
            contact_no:btn.dataset.contact, city:btn.dataset.city, country:btn.dataset.country,
            email_verified:btn.dataset.emailVerified
        } : btn;
        document.getElementById('edit_id').value           = d.id;
        document.getElementById('edit_full_name').value    = d.full_name  || '';
        document.getElementById('edit_email').value        = d.email      || '';
        document.getElementById('edit_contact_no').value   = d.contact_no || '';
        document.getElementById('edit_city').value         = d.city       || '';
        document.getElementById('edit_country').value      = d.country    || '';
        document.getElementById('edit_email_verified').checked = d.email_verified == '1';
        openModal('editUserModal');
    }

    // Password match — edit admin
    document.querySelector('#editAdminModal form').addEventListener('submit', function(e){
        var p=document.getElementById('admin_edit_password').value;
        var c=document.getElementById('admin_edit_confirm').value;
        var err=document.getElementById('adminEditPwdErr');
        if(p && p!==c){ e.preventDefault(); err.style.display='flex'; }
        else { err.style.display='none'; }
    });

    // ─── ADD ADMIN OTP FLOW ────────────────────────────────────────────────
    var usernameAvailable = false;
    var passwordValid = false;
    var otpTimerInterval = null;
    var otpTimeLeft = 600;

    function checkUsername(username) {
        var status = document.getElementById('usernameStatus');
        var hint = document.getElementById('usernameHint');
        if (username.length < 3) {
            status.innerHTML = '';
            hint.textContent = 'At least 3 characters';
            usernameAvailable = false;
            updateSendBtn();
            return;
        }
        status.innerHTML = '<i class="fa fa-spinner fa-spin" style="color:var(--text3);"></i>';
        fetch('reg-users.php?check_username=' + encodeURIComponent(username))
            .then(r => r.json())
            .then(data => {
                if (data.available) {
                    status.innerHTML = '<i class="fa fa-check-circle" style="color:#22c55e;"></i>';
                    hint.textContent = data.message;
                    hint.style.color = '#22c55e';
                    usernameAvailable = true;
                } else {
                    status.innerHTML = '<i class="fa fa-times-circle" style="color:#ef4444;"></i>';
                    hint.textContent = data.message;
                    hint.style.color = '#ef4444';
                    usernameAvailable = false;
                }
                updateSendBtn();
            });
    }

    function validatePasswordStrength(password) {
        var hint = document.getElementById('passwordHint');
        var strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[@$!%*?&])[A-Za-z\\d@$!%*?&]{8,}$");
        var hasLength = password.length >= 8;
        var hasUpper = /[A-Z]/.test(password);
        var hasLower = /[a-z]/.test(password);
        var hasNumber = /\d/.test(password);
        var hasSpecial = /[@$!%*?&]/.test(password);
        
        if (password.length === 0) {
            hint.textContent = '';
            passwordValid = false;
        } else if (strongRegex.test(password)) {
            hint.textContent = 'Strong password';
            hint.style.color = '#22c55e';
            passwordValid = true;
        } else {
            var issues = [];
            if (!hasLength) issues.push('8+ chars');
            if (!hasUpper) issues.push('uppercase');
            if (!hasLower) issues.push('lowercase');
            if (!hasNumber) issues.push('number');
            if (!hasSpecial) issues.push('special (@$!%*?&)');
            hint.textContent = 'Missing: ' + issues.join(', ');
            hint.style.color = '#ef4444';
            passwordValid = false;
        }
        updateSendBtn();
    }

    function updateSendBtn() {
        var btn = document.getElementById('sendOtpBtn');
        var pwd = document.getElementById('admin_password').value;
        var confirmPwd = document.getElementById('admin_confirm_pwd').value;
        var pwdMatch = pwd === confirmPwd && pwd.length > 0;
        
        if (usernameAvailable && passwordValid && pwdMatch) {
            btn.disabled = false;
        } else {
            btn.disabled = true;
        }
    }

    document.getElementById('admin_password').addEventListener('input', updateSendBtn);
    document.getElementById('admin_confirm_pwd').addEventListener('input', updateSendBtn);

    document.getElementById('adminRegForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var errBox = document.getElementById('adminFormError');
        var pwd = document.getElementById('admin_password').value;
        var confirmPwd = document.getElementById('admin_confirm_pwd').value;
        
        if (!usernameAvailable) {
            errBox.textContent = 'Please choose a unique username.';
            errBox.style.display = 'flex';
            return;
        }
        if (!passwordValid) {
            errBox.textContent = 'Password does not meet complexity requirements.';
            errBox.style.display = 'flex';
            return;
        }
        if (pwd !== confirmPwd) {
            document.getElementById('adminAddPwdErr').style.display = 'flex';
            return;
        }
        
        errBox.style.display = 'none';
        document.getElementById('adminAddPwdErr').style.display = 'none';
        document.getElementById('sendOtpBtn').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
        document.getElementById('sendOtpBtn').disabled = true;
        
        var formData = new FormData();
        formData.append('send_admin_otp', '1');
        formData.append('username', document.getElementById('admin_username').value);
        formData.append('email', document.getElementById('admin_email').value);
        formData.append('password', pwd);
        
        fetch('reg-users.php', { 
            method: 'POST', 
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('otpEmailDisplay').textContent = document.getElementById('admin_email').value;
                    document.getElementById('adminFormStep').style.display = 'none';
                    document.getElementById('adminOtpStep').style.display = 'block';
                    startOtpTimer();
                } else {
                    errBox.textContent = data.message;
                    errBox.style.display = 'flex';
                    document.getElementById('sendOtpBtn').innerHTML = '<i class="fa fa-paper-plane"></i> Send OTP to Email';
                    document.getElementById('sendOtpBtn').disabled = false;
                }
            });
    });

    document.getElementById('otpVerifyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var errBox = document.getElementById('otpError');
        var otpInput = document.getElementById('otp_code');
        var submitBtn = this.querySelector('button[type="submit"]');
        
        var formData = new FormData();
        formData.append('verify_admin_otp', '1');
        formData.append('otp_code', otpInput.value);
        
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Verifying...';
        submitBtn.disabled = true;
        errBox.style.display = 'none';
        
        fetch('reg-users.php', { 
            method: 'POST', 
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeModal('addAdminModal');
                    document.getElementById('successMsg').textContent = 'Admin account created successfully!';
                    document.getElementById('successMsg').style.display = 'flex';
                    setTimeout(function() {
                        var el = document.getElementById('successMsg');
                        el.style.transition = 'opacity 0.5s ease';
                        el.style.opacity = '0';
                        setTimeout(function() { el.style.display = 'none'; }, 500);
                    }, 3000);
                } else {
                    errBox.textContent = data.message;
                    errBox.style.display = 'flex';
                    submitBtn.innerHTML = '<i class="fa fa-check"></i> Verify & Create Admin';
                    submitBtn.disabled = false;
                    otpInput.value = '';
                    otpInput.focus();
                }
            });
    });

    function startOtpTimer() {
        otpTimeLeft = 600;
        clearInterval(otpTimerInterval);
        otpTimerInterval = setInterval(function() {
            otpTimeLeft--;
            var mins = Math.floor(otpTimeLeft / 60);
            var secs = otpTimeLeft % 60;
            document.getElementById('otpTimer').textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
            if (otpTimeLeft <= 0) {
                clearInterval(otpTimerInterval);
                document.getElementById('otpTimer').textContent = '0:00';
            }
        }, 1000);
    }

    function resendOtp() {
        var btn = document.getElementById('resendOtpBtn');
        var errBox = document.getElementById('otpError');
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        errBox.style.display = 'none';
        
        var formData = new FormData();
        formData.append('resend_admin_otp', '1');
        
        fetch('reg-users.php', { 
            method: 'POST', 
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    startOtpTimer();
                    errBox.textContent = 'New OTP sent to your email.';
                    errBox.className = 'alert alert-success';
                    errBox.style.display = 'flex';
                    setTimeout(function() { errBox.style.display = 'none'; }, 3000);
                } else {
                    errBox.textContent = data.message;
                    errBox.className = 'alert alert-error';
                    errBox.style.display = 'flex';
                }
                btn.innerHTML = 'Resend';
                btn.disabled = false;
            });
    }

    function backToAdminForm() {
        document.getElementById('adminFormStep').style.display = 'block';
        document.getElementById('adminOtpStep').style.display = 'none';
        clearInterval(otpTimerInterval);
    }

    function closeAdminModal() {
        closeModal('addAdminModal');
        backToAdminForm();
        document.getElementById('admin_username').value = '';
        document.getElementById('admin_email').value = '';
        document.getElementById('admin_password').value = '';
        document.getElementById('admin_confirm_pwd').value = '';
        document.getElementById('otp_code').value = '';
        document.getElementById('usernameStatus').innerHTML = '';
        document.getElementById('usernameHint').textContent = 'At least 3 characters';
        document.getElementById('usernameHint').style.color = '';
        document.getElementById('passwordHint').textContent = '';
        document.getElementById('adminFormError').style.display = 'none';
        document.getElementById('otpError').style.display = 'none';
        document.getElementById('sendOtpBtn').innerHTML = '<i class="fa fa-paper-plane"></i> Send OTP to Email';
        document.getElementById('sendOtpBtn').disabled = true;
        usernameAvailable = false;
        passwordValid = false;
    }

    // Password match — add user
    document.querySelector('#addUserForm').addEventListener('submit', function(e){
        var p=document.getElementById('new_password').value;
        var c=document.getElementById('new_confirm_password').value;
        var err=document.getElementById('addUserPwdErr');
        if(p!==c){ e.preventDefault(); err.style.display='flex'; }
        else { err.style.display='none'; }
    });

    // Auto-open edit modal via PHP
    <?php if ($edit_user): ?>
    openEditUserModal(<?php echo json_encode($edit_user); ?>);
    <?php endif; ?>

    // Auto-hide alerts
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.alert').forEach(function(el){
            setTimeout(function(){
                el.style.transition='opacity 0.5s ease';
                el.style.opacity='0';
                setTimeout(function(){ el.style.display='none'; },500);
            },2500);
        });
    });
</script>
</body>
</html>