<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_admin_logged_in()) {
    header('Location: dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (!admin_login($username, $password)) {
        $error = 'Invalid username or password. Please try again.';
    } else {
        header('Location: dashboard');
        exit;
    }
}

$uni_name = get_setting('university_name') ?: 'Capiz State University';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= htmlspecialchars($uni_name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-login-wrap">
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-building"></i>
        </div>
        <h4><?= htmlspecialchars($uni_name) ?></h4>
        <p class="login-sub">Document Request System — Admin Portal</p>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="admin-form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--light-bg);border:1.5px solid var(--border-color);border-right:none;">
                        <i class="bi bi-person" style="color:var(--primary-navy);"></i>
                    </span>
                    <input type="text" class="admin-form-control" id="username" name="username"
                           placeholder="Enter username" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           style="border-left:none;border-radius:0 8px 8px 0;">
                </div>
            </div>
            <div class="mb-4">
                <label class="admin-form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:var(--light-bg);border:1.5px solid var(--border-color);border-right:none;">
                        <i class="bi bi-lock" style="color:var(--primary-navy);"></i>
                    </span>
                    <input type="password" class="admin-form-control" id="password" name="password"
                           placeholder="Enter password" required autocomplete="current-password"
                           style="border-left:none;border-radius:0 8px 8px 0;">
                </div>
            </div>
            <button type="submit" class="btn-admin-primary w-100 justify-content-center" style="padding:11px;">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="../index" class="text-muted small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>Back to User Portal
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
