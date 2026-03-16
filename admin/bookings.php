<?php
session_start();
include 'config.php';

if (!isset($_SESSION['alogin']) || empty($_SESSION['alogin'])) {
    header('Location: index.php');
    exit();
}

$msg   = "";
$error = "";

// ── Helper: build & send confirmation email ───────────────────────────────────
function sendConfirmationEmail($conn, int $booking_id): bool {

    // Fetch full booking + user + car details
    $stmt = $conn->prepare("
        SELECT
            b.id          AS booking_id,
            b.from_date,
            b.to_date,
            b.message     AS special_req,
            b.posting_date,
            u.full_name,
            u.email,
            c.car_name,
            c.car_model,
            c.car_type,
            c.price_per_day,
            c.seating_capacity
        FROM booking b
        JOIN users u ON u.email = b.user_email
        JOIN cars  c ON c.id    = b.car_id
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $d = $stmt->get_result()->fetch_assoc();
    if (!$d) return false;

    // Calculate days & total
    $from  = new DateTime($d['from_date']);
    $to    = new DateTime($d['to_date']);
    $days  = max(1, (int)$from->diff($to)->days);
    $total = $days * intval($d['price_per_day']);

    $car_label = htmlspecialchars($d['car_name']);
    $from_fmt  = date('d M Y', strtotime($d['from_date']));
    $to_fmt    = date('d M Y', strtotime($d['to_date']));
    $booked_on = date('d M Y, h:i A', strtotime($d['posting_date']));
    $price_fmt = 'LKR ' . number_format($d['price_per_day']);
    $total_fmt = 'LKR ' . number_format($total);

    // ── HTML Email Template ────────────────────────────────────────────────────
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Booking Confirmation — CarForYou</title>
</head>
<body style="margin:0;padding:0;background:#0d1117;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;padding:40px 20px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- HEADER -->
        <tr>
          <td style="background:linear-gradient(135deg,#0d1117 0%,#111b2a 60%,#132036 100%);
                     border-radius:16px 16px 0 0;padding:40px 40px 32px;text-align:center;
                     border:1px solid rgba(79,142,247,0.15);border-bottom:none;">
            <div style="display:inline-block;background:rgba(79,142,247,0.1);
                        border:1px solid rgba(79,142,247,0.3);border-radius:50%;
                        width:64px;height:64px;line-height:64px;text-align:center;
                        font-size:28px;margin-bottom:20px;">🚗</div>
            <h1 style="margin:0;font-size:2rem;font-weight:300;color:#e8edf5;letter-spacing:-0.02em;">
              Car<span style="color:#4f8ef7;font-style:italic;font-weight:600;">ForYou</span>
            </h1>
            <p style="margin:8px 0 0;font-size:0.78rem;font-weight:600;letter-spacing:0.2em;
                      text-transform:uppercase;color:rgba(232,237,245,0.4);">
              Booking Confirmation
            </p>
          </td>
        </tr>

        <!-- GREEN BANNER -->
        <tr>
          <td style="background:#0f2318;border-left:1px solid rgba(79,142,247,0.15);
                     border-right:1px solid rgba(79,142,247,0.15);padding:24px 40px;text-align:center;
                     border-bottom:1px solid rgba(34,197,94,0.2);">
            <span style="display:inline-flex;align-items:center;gap:8px;
                         background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);
                         border-radius:30px;padding:8px 20px;
                         font-size:0.82rem;font-weight:700;letter-spacing:0.08em;
                         text-transform:uppercase;color:#22c55e;">
              ✓ &nbsp; Booking Confirmed
            </span>
            <p style="margin:14px 0 0;font-size:1rem;color:#e8edf5;font-weight:400;">
              Hi <strong style="color:#4f8ef7;">{$d['full_name']}</strong>, your reservation is confirmed!
            </p>
            <p style="margin:6px 0 0;font-size:0.82rem;color:#7a93b0;">
              Booking Reference: <strong style="color:#e8edf5;">#BKG-{$booking_id}</strong>
            </p>
          </td>
        </tr>

        <!-- BODY -->
        <tr>
          <td style="background:#1e2738;padding:32px 40px;
                     border:1px solid rgba(79,142,247,0.15);border-top:none;border-bottom:none;">

            <!-- Car name -->
            <h2 style="margin:0 0 24px;font-size:1.4rem;font-weight:600;color:#e8edf5;
                       letter-spacing:-0.01em;padding-bottom:16px;
                       border-bottom:1px solid rgba(99,155,255,0.08);">
              {$car_label}
              <span style="display:block;font-size:0.72rem;font-weight:600;letter-spacing:0.14em;
                           text-transform:uppercase;color:#4f8ef7;margin-top:4px;">
                {$d['car_type']} &nbsp;·&nbsp; {$d['car_model']} &nbsp;·&nbsp; {$d['seating_capacity']} Seats
              </span>
            </h2>

            <!-- Date row -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
              <tr>
                <td width="48%" style="background:#253044;border:1px solid rgba(99,155,255,0.12);
                                       border-radius:10px;padding:16px 18px;vertical-align:top;">
                  <div style="font-size:0.62rem;font-weight:700;letter-spacing:0.14em;
                               text-transform:uppercase;color:#3d5570;margin-bottom:6px;">
                    Pick-up Date
                  </div>
                  <div style="font-size:1.05rem;font-weight:700;color:#e8edf5;">
                    📅 &nbsp;{$from_fmt}
                  </div>
                </td>
                <td width="4%" style="text-align:center;color:#3d5570;font-size:1.2rem;">→</td>
                <td width="48%" style="background:#253044;border:1px solid rgba(99,155,255,0.12);
                                       border-radius:10px;padding:16px 18px;vertical-align:top;">
                  <div style="font-size:0.62rem;font-weight:700;letter-spacing:0.14em;
                               text-transform:uppercase;color:#3d5570;margin-bottom:6px;">
                    Return Date
                  </div>
                  <div style="font-size:1.05rem;font-weight:700;color:#e8edf5;">
                    📅 &nbsp;{$to_fmt}
                  </div>
                </td>
              </tr>
            </table>

            <!-- Bill summary -->
            <div style="background:#253044;border:1px solid rgba(99,155,255,0.12);
                        border-radius:12px;overflow:hidden;margin-bottom:24px;">
              <div style="padding:14px 20px;border-bottom:1px solid rgba(99,155,255,0.08);
                           font-size:0.65rem;font-weight:700;letter-spacing:0.16em;
                           text-transform:uppercase;color:#3d5570;">
                Billing Summary
              </div>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:13px 20px;font-size:0.86rem;color:#7a93b0;
                             border-bottom:1px solid rgba(99,155,255,0.06);">Daily Rate</td>
                  <td style="padding:13px 20px;text-align:right;font-size:0.86rem;
                             color:#e8edf5;font-weight:600;
                             border-bottom:1px solid rgba(99,155,255,0.06);">{$price_fmt}</td>
                </tr>
                <tr>
                  <td style="padding:13px 20px;font-size:0.86rem;color:#7a93b0;
                             border-bottom:1px solid rgba(99,155,255,0.06);">Number of Days</td>
                  <td style="padding:13px 20px;text-align:right;font-size:0.86rem;
                             color:#e8edf5;font-weight:600;
                             border-bottom:1px solid rgba(99,155,255,0.06);">{$days} day(s)</td>
                </tr>
                <tr style="background:rgba(79,142,247,0.06);">
                  <td style="padding:16px 20px;font-size:0.92rem;font-weight:700;color:#e8edf5;">
                    Estimated Total
                  </td>
                  <td style="padding:16px 20px;text-align:right;font-size:1.1rem;
                             font-weight:800;color:#4f8ef7;">{$total_fmt}</td>
                </tr>
              </table>
            </div>

HTML;

    // Special requests block (only if present)
    if (!empty(trim($d['special_req']))) {
        $req = htmlspecialchars($d['special_req']);
        $html .= <<<HTML
            <div style="background:rgba(79,142,247,0.06);border:1px solid rgba(79,142,247,0.15);
                        border-radius:10px;padding:14px 18px;margin-bottom:24px;">
              <div style="font-size:0.62rem;font-weight:700;letter-spacing:0.14em;
                           text-transform:uppercase;color:#4f8ef7;margin-bottom:6px;">
                Special Requests
              </div>
              <div style="font-size:0.86rem;color:#7a93b0;line-height:1.6;">{$req}</div>
            </div>
HTML;
    }

    $html .= <<<HTML
            <!-- What's next -->
            <div style="background:#111b2a;border:1px solid rgba(99,155,255,0.08);
                        border-radius:10px;padding:18px 20px;margin-bottom:8px;">
              <div style="font-size:0.65rem;font-weight:700;letter-spacing:0.14em;
                           text-transform:uppercase;color:#3d5570;margin-bottom:12px;">
                What Happens Next
              </div>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:6px 0;font-size:0.84rem;color:#7a93b0;">
                    <span style="color:#4f8ef7;margin-right:8px;">①</span>
                    Our team will contact you before your pick-up date.
                  </td>
                </tr>
                <tr>
                  <td style="padding:6px 0;font-size:0.84rem;color:#7a93b0;">
                    <span style="color:#4f8ef7;margin-right:8px;">②</span>
                    Please bring a valid driving licence on collection.
                  </td>
                </tr>
                <tr>
                  <td style="padding:6px 0;font-size:0.84rem;color:#7a93b0;">
                    <span style="color:#4f8ef7;margin-right:8px;">③</span>
                    Free cancellation is available up to 24 hours before pick-up.
                  </td>
                </tr>
              </table>
            </div>

          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="background:#131920;border:1px solid rgba(79,142,247,0.15);border-top:none;
                     border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 6px;font-size:0.8rem;color:#3d5570;">
              Need help? Contact us anytime.
            </p>
            <p style="margin:0 0 4px;font-size:0.82rem;color:#7a93b0;">
              📞 +94 75 45 57 624 &nbsp;·&nbsp; ✉️ amafzhar@gmail.com
            </p>
            <p style="margin:16px 0 0;font-size:0.72rem;color:#3d5570;">
              © 2026 CarForYou · 37 Kinniya, Trincomalee, Sri Lanka
            </p>
            <p style="margin:4px 0 0;font-size:0.68rem;color:#3d5570;">
              Booked on {$booked_on} · Ref #BKG-{$booking_id}
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

    // ── Send via PHPMailer (SMTP) ─────────────────────────────────────────────
    require_once __DIR__ . '/mailer_config.php';
    try {
        $mail = getMailer();
        $mail->addAddress($d['email'], $d['full_name']);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmed — {$car_label} | CarForYou #BKG-{$booking_id}";
        $mail->Body    = $html;
        $mail->AltBody = "Hi {$d['full_name']}, your booking for {$car_label} has been confirmed!\n\n"
                       . "Booking Ref: #BKG-{$booking_id}\n"
                       . "Pick-up:     {$from_fmt}\n"
                       . "Return:      {$to_fmt}\n"
                       . "Duration:    {$days} day(s)\n"
                       . "Est. Total:  {$total_fmt}\n\n"
                       . "Contact us: " . MAIL_FROM . " | +94 75 45 57 624";
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('CarForYou mailer error: ' . $e->getMessage());
        return false;
    }
}

// ── CONFIRM BOOKING ───────────────────────────────────────────────────────────
if (isset($_GET['aeid'])) {
    $aeid   = intval($_GET['aeid']);
    $status = 1;
    $stmt   = $conn->prepare("UPDATE booking SET status=? WHERE id=?");
    $stmt->bind_param("ii", $status, $aeid);
    if ($stmt->execute()) {
        // Send confirmation email
        $sent = sendConfirmationEmail($conn, $aeid);
        $msg  = $sent
            ? "Booking confirmed & confirmation email sent to customer."
            : "Booking confirmed. (Email delivery failed — check mail server config.)";
    } else {
        $error = "Error updating booking.";
    }
}

// ── CANCEL BOOKING ────────────────────────────────────────────────────────────
if (isset($_GET['eid'])) {
    $eid    = intval($_GET['eid']);
    $status = 2;
    $stmt   = $conn->prepare("UPDATE booking SET status=? WHERE id=?");
    $stmt->bind_param("ii", $status, $eid);
    $msg   = $stmt->execute() ? "Booking successfully Cancelled." : "";
    $error = (!$msg) ? "Error updating booking." : "";
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings | CarForYou Admin</title>
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
        .badge.confirmed{background:rgba(34,197,94,0.12);color:#22c55e;}
        .badge.cancelled{background:rgba(239,68,68,0.12);color:#ef4444;}

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

        <?php
        $result = $conn->query("
            SELECT
                u.full_name, u.email AS user_email,
                b.id, b.car_id, b.from_date, b.to_date,
                b.message, b.status, b.posting_date,
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
                            <th>Status</th>
                            <th>Booked On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0):
                        $count = 1;
                        while ($row = $result->fetch_assoc()):
                            if ($row['status'] == 0)     { $bc='pending';   $bl='Pending'; }
                            elseif ($row['status'] == 1) { $bc='confirmed'; $bl='Confirmed'; }
                            else                         { $bc='cancelled'; $bl='Cancelled'; }

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
                                <?php if ($row['status'] == 1): ?>
                                <div class="email-sent-tag"><i class="fa fa-envelope-circle-check"></i> Email sent</div>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap;font-size:0.8rem;">
                                <?php echo date('d M Y', strtotime($row['posting_date'])); ?>
                                <div style="font-size:0.7rem;color:var(--text3);"><?php echo date('h:i A', strtotime($row['posting_date'])); ?></div>
                            </td>
                            <td>
                                <?php if ($row['status'] == 0): ?>
                                <div class="acts">
                                    <a href="bookings.php?aeid=<?php echo $row['id']; ?>" class="abt abt-ok"
                                       onclick="return confirm('Confirm this booking and send email to customer?')">
                                        <i class="fa fa-check"></i> Confirm
                                    </a>
                                    <a href="bookings.php?eid=<?php echo $row['id']; ?>" class="abt abt-cx"
                                       onclick="return confirm('Cancel this booking?')">
                                        <i class="fa fa-xmark"></i> Cancel
                                    </a>
                                </div>
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