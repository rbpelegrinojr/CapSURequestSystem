<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_admin_logged_in()) {
    header('Location: dashboard');
    exit;
}

$db = get_db();
$error   = '';
$success = '';
$uni_name = get_setting('university_name') ?: 'Capiz State University';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $error = 'Please enter your username or registered email address.';
    } else {
        // Look up admin by username or email
        $stmt = $db->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $admin = $stmt->fetch();

        if (!$admin || empty($admin['email'])) {
            // Show a generic message to avoid user enumeration
            $success = 'If that account exists, an OTP has been sent to its registered email address.';
        } else {
            // Invalidate any previous unused OTPs for this admin
            $stmt = $db->prepare('UPDATE admin_password_resets SET used = 1 WHERE admin_id = ? AND used = 0');
            $stmt->execute([$admin['id']]);

            // Generate a 6-digit OTP
            $otp        = (string)random_int(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', time() + 900); // 15 minutes

            $stmt = $db->prepare('INSERT INTO admin_password_resets (admin_id, otp, expires_at) VALUES (?, ?, ?)');
            $stmt->execute([$admin['id'], $otp, $expires_at]);

            // Send OTP email
            $masked_email = mask_email($admin['email']);
            $body = "
<p>Dear <strong>" . htmlspecialchars($admin['name']) . "</strong>,</p>
<p>A password reset was requested for your admin account.</p>
<p>Your One-Time Password (OTP) is:</p>
<div style='text-align:center;margin:24px 0;'>
    <span style='font-size:2rem;font-weight:900;letter-spacing:12px;color:#1a3a6b;background:#f0f4ff;padding:16px 28px;border-radius:10px;display:inline-block;'>{$otp}</span>
</div>
<p>This OTP is valid for <strong>15 minutes</strong>. Do not share it with anyone.</p>
<p>If you did not request a password reset, please ignore this email or contact your system administrator.</p>
";
            $sent = send_system_email($admin['email'], $admin['name'], 'Admin Password Reset OTP — ' . $uni_name, $body);

            if (!$sent) {
                // Roll back the OTP record so a retry is possible
                $stmt = $db->prepare('DELETE FROM admin_password_resets WHERE id = LAST_INSERT_ID()');
                $stmt->execute();
                $error = 'Could not send the OTP email. Please check the mail configuration and try again.';
            } else {
                // Store admin_id in session so the verify step can use it
                $_SESSION['otp_admin_id'] = $admin['id'];
                $success = 'An OTP has been sent to ' . htmlspecialchars($masked_email) . '. It expires in 15 minutes.';
            }
        }
    }
}

/**
 * Mask an email address for display (e.g. ad***@capsu.edu.ph).
 */
function mask_email($email) {
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) return '***';
    $local  = $parts[0];
    $domain = $parts[1];
    if (strlen($local) <= 2) {
        return str_repeat('*', strlen($local)) . '@' . $domain;
    }
    $visible = substr($local, 0, 2);
    return $visible . str_repeat('*', strlen($local) - 2) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= htmlspecialchars($uni_name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-key"></i>
        </div>
        <h4><?= htmlspecialchars($uni_name) ?></h4>
        <p class="login-sub">Forgot Password — Admin Portal</p>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success py-2 small" role="alert">
            <i class="bi bi-envelope-check me-1"></i><?= htmlspecialchars($success) ?>
        </div>
        <div class="text-center mt-3">
            <a href="reset_password" class="btn-admin-primary w-100 justify-content-center" style="padding:11px;display:flex;">
                <i class="bi bi-shield-lock me-2"></i>Enter OTP &amp; Reset Password
            </a>
        </div>
        <?php else: ?>
        <p class="small text-muted mb-3">Enter your admin username or registered email address. We will send a one-time password (OTP) to your email.</p>
        <form method="POST" autocomplete="off">
            <div class="mb-4">
                <label class="admin-form-label" for="identifier">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--light-bg);border:1.5px solid var(--border-color);border-right:none;">
                        <i class="bi bi-person-badge" style="color:var(--primary-navy);"></i>
                    </span>
                    <input type="text" class="admin-form-control" id="identifier" name="identifier"
                           placeholder="Username or email address" required autocomplete="off"
                           value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                           style="border-left:none;border-radius:0 8px 8px 0;">
                </div>
            </div>
            <button type="submit" class="btn-admin-primary w-100 justify-content-center" style="padding:11px;">
                <i class="bi bi-send me-2"></i>Send OTP
            </button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="index" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
