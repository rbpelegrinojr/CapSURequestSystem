<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_admin_logged_in()) {
    header('Location: dashboard');
    exit;
}

$db = get_db();
$error        = '';
$error_link   = ''; // URL for "try again" link appended to $error
$error_link_text = '';
$success      = '';
$uni_name  = get_setting('university_name') ?: 'Capiz State University';
$admin_id  = isset($_SESSION['otp_admin_id']) ? (int)$_SESSION['otp_admin_id'] : 0;
$has_session = $admin_id > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp          = trim($_POST['otp'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!$has_session) {
        $error           = 'Session expired. Please request a new OTP.';
        $error_link      = 'forgot_password';
        $error_link_text = 'Request a new OTP';
    } elseif (empty($otp) || empty($new_password) || empty($confirm_pass)) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^\d{6}$/', $otp)) {
        $error = 'OTP must be a 6-digit number.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_pass) {
        $error = 'Passwords do not match.';
    } else {
        // Validate OTP
        $stmt = $db->prepare(
            'SELECT * FROM admin_password_resets
             WHERE admin_id = ? AND otp = ? AND used = 0 AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$admin_id, $otp]);
        $reset_row = $stmt->fetch();

        if (!$reset_row) {
            $error           = 'Invalid or expired OTP.';
            $error_link      = 'forgot_password';
            $error_link_text = 'Request a new one';
        } else {
            // Mark OTP as used
            $stmt = $db->prepare('UPDATE admin_password_resets SET used = 1 WHERE id = ?');
            $stmt->execute([$reset_row['id']]);

            // Update the admin password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $stmt->execute([$hashed, $admin_id]);

            // Clear session flag
            unset($_SESSION['otp_admin_id']);

            $success = 'Your password has been reset successfully. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= htmlspecialchars($uni_name) ?></title>
    <?php $uni_favicon = get_setting('university_logo'); if (!empty($uni_favicon)): ?><link rel="icon" href="<?= htmlspecialchars('../' . $uni_favicon) ?>"><?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h4><?= htmlspecialchars($uni_name) ?></h4>
        <p class="login-sub">Reset Password — Admin Portal</p>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
            <?php if ($error_link): ?>
            <a href="<?= htmlspecialchars($error_link) ?>" class="alert-link"><?= htmlspecialchars($error_link_text) ?></a>.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success py-2 small" role="alert">
            <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
        </div>
        <div class="text-center mt-3">
            <a href="index" class="btn-admin-primary w-100 justify-content-center" style="padding:11px;display:flex;">
                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
            </a>
        </div>
        <?php else: ?>
        <?php if (!$has_session): ?>
        <div class="alert alert-warning py-2 small" role="alert">
            <i class="bi bi-info-circle me-1"></i>
            No active OTP session. Please <a href="forgot_password" class="alert-link">request an OTP first</a>.
        </div>
        <?php else: ?>
        <p class="small text-muted mb-3">Enter the 6-digit OTP sent to your email and choose a new password.</p>
        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="admin-form-label" for="otp">One-Time Password (OTP)</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--light-bg);border:1.5px solid var(--border-color);border-right:none;">
                        <i class="bi bi-123" style="color:var(--primary-navy);"></i>
                    </span>
                    <input type="text" class="admin-form-control" id="otp" name="otp"
                           placeholder="6-digit OTP" required maxlength="6" pattern="\d{6}"
                           inputmode="numeric" autocomplete="one-time-code"
                           value="<?= htmlspecialchars($_POST['otp'] ?? '') ?>"
                           style="border-left:none;border-radius:0 8px 8px 0;letter-spacing:6px;font-size:1.2rem;text-align:center;">
                </div>
            </div>
            <div class="mb-3">
                <label class="admin-form-label" for="new_password">New Password</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--light-bg);border:1.5px solid var(--border-color);border-right:none;">
                        <i class="bi bi-lock" style="color:var(--primary-navy);"></i>
                    </span>
                    <input type="password" class="admin-form-control" id="new_password" name="new_password"
                           placeholder="At least 8 characters" required minlength="8"
                           autocomplete="new-password"
                           style="border-left:none;border-radius:0 8px 8px 0;">
                </div>
            </div>
            <div class="mb-4">
                <label class="admin-form-label" for="confirm_password">Confirm New Password</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--light-bg);border:1.5px solid var(--border-color);border-right:none;">
                        <i class="bi bi-lock-fill" style="color:var(--primary-navy);"></i>
                    </span>
                    <input type="password" class="admin-form-control" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter new password" required minlength="8"
                           autocomplete="new-password"
                           style="border-left:none;border-radius:0 8px 8px 0;">
                </div>
            </div>
            <button type="submit" class="btn-admin-primary w-100 justify-content-center" style="padding:11px;">
                <i class="bi bi-check-lg me-2"></i>Reset Password
            </button>
        </form>
        <?php endif; ?>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="forgot_password" class="text-muted small text-decoration-none me-3">
                <i class="bi bi-arrow-repeat me-1"></i>Resend OTP
            </a>
            <a href="index" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
