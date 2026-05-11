<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$db = get_db();
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_msg = 'All password fields are required.';
        } elseif (strlen($new_password) < 8) {
            $error_msg = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error_msg = 'New passwords do not match.';
        } else {
            $stmt = $db->prepare('SELECT password FROM admins WHERE id = ?');
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch();
            if (!$admin || !password_verify($current_password, $admin['password'])) {
                $error_msg = 'Current password is incorrect.';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE admins SET password = ? WHERE id = ?');
                $stmt->execute([$hashed, $_SESSION['admin_id']]);
                if ($stmt->rowCount() > 0) {
                    $success_msg = 'Password changed successfully.';
                } else {
                    $error_msg = 'Password could not be updated. Please try again.';
                }
            }
        }
    } elseif ($action === 'save_general') {
        $keys = ['university_name', 'university_address', 'university_phone', 'university_email', 'admin_email'];
        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?');
            $stmt->execute([$key, $val, $val]);
        }
        // Handle logo upload
        if (!empty($_FILES['university_logo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['university_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','svg'])) {
                $upload_dir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['university_logo']['tmp_name'], $upload_dir . $filename)) {
                    $logo_path = 'assets/uploads/' . $filename;
                    $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?');
                    $stmt->execute(['university_logo', $logo_path, $logo_path]);
                }
            } else {
                $error_msg = 'Invalid logo file type. Use JPG, PNG, GIF, or SVG.';
            }
        }
        if (!$error_msg) {
            $success_msg = 'General settings saved.';
        }
    }
}

// Load settings
$settings_rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
$settings = [];
foreach ($settings_rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — CapSU Admin</title>
    <?php $uni_favicon = get_setting('university_logo'); if (!empty($uni_favicon)): ?><link rel="icon" href="<?= htmlspecialchars('../' . $uni_favicon) ?>"><?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-content">

    <div class="page-header-bar">
        <h4><i class="bi bi-gear"></i> System Settings</h4>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- General Settings -->
    <div class="admin-card">
        <div class="card-header">
            <h5><i class="bi bi-building"></i> University Information</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_general">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="admin-form-label">University Name <span class="text-danger">*</span></label>
                        <input type="text" class="admin-form-control" name="university_name"
                               value="<?= htmlspecialchars($settings['university_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="admin-form-label">Admin Email (for notifications) <span class="text-danger">*</span></label>
                        <input type="email" class="admin-form-control" name="admin_email"
                               value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label class="admin-form-label">Address</label>
                        <input type="text" class="admin-form-control" name="university_address"
                               value="<?= htmlspecialchars($settings['university_address'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="admin-form-label">Phone</label>
                        <input type="text" class="admin-form-control" name="university_phone"
                               value="<?= htmlspecialchars($settings['university_phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="admin-form-label">Email</label>
                        <input type="email" class="admin-form-control" name="university_email"
                               value="<?= htmlspecialchars($settings['university_email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="admin-form-label">University Logo</label>
                        <?php if (!empty($settings['university_logo'])): ?>
                        <div class="mb-2">
                            <img src="../<?= htmlspecialchars($settings['university_logo']) ?>" alt="Logo" style="max-height:60px;border:1px solid #ddd;padding:4px;border-radius:6px;">
                        </div>
                        <?php endif; ?>
                        <input type="file" class="admin-form-control" name="university_logo" accept="image/*">
                        <div class="form-text text-muted small">JPG, PNG, SVG. Recommended size: 200×200px</div>
                    </div>
                </div>
                <hr class="my-4">
                <button type="submit" class="btn-admin-primary">
                    <i class="bi bi-save"></i> Save General Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="admin-card mt-4">
        <div class="card-header">
            <h5><i class="bi bi-key"></i> Change Password</h5>
        </div>
        <div class="card-body">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="change_password">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="admin-form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="admin-form-control" name="current_password"
                               placeholder="Enter current password" required autocomplete="current-password">
                    </div>
                    <div class="col-md-4">
                        <label class="admin-form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="admin-form-control" name="new_password"
                               placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="col-md-4">
                        <label class="admin-form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="admin-form-control" name="confirm_password"
                               placeholder="Re-enter new password" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <hr class="my-4">
                <button type="submit" class="btn-admin-primary">
                    <i class="bi bi-shield-lock"></i> Update Password
                </button>
            </form>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
