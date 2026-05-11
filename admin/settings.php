<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$db = get_db();
$success_msg = '';
$error_msg   = '';
$active_tab  = $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_general') {
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
                $upload_dir = __DIR__ . '/../../assets/uploads/';
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
            $active_tab = 'general';
        }
    } elseif ($action === 'save_letterhead') {
        $letterhead = $_POST['letterhead_html'] ?? '';
        $footer     = $_POST['footer_html'] ?? '';
        $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?');
        $stmt->execute(['letterhead_html', $letterhead, $letterhead]);
        $stmt->execute(['footer_html', $footer, $footer]);
        $success_msg = 'Letterhead settings saved.';
        $active_tab = 'letterhead';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/tinymce/tinymce.min.js"></script>
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

    <!-- Tabs -->
    <div class="admin-tabs">
        <a href="?tab=general" class="tab-link <?= $active_tab === 'general' ? 'active' : '' ?>">
            <i class="bi bi-building"></i> General
        </a>
        <a href="?tab=letterhead" class="tab-link <?= $active_tab === 'letterhead' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-richtext"></i> Letterhead & Footer
        </a>
    </div>

    <?php if ($active_tab === 'general'): ?>
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

    <?php elseif ($active_tab === 'letterhead'): ?>
    <!-- Letterhead Settings -->
    <div class="admin-card">
        <div class="card-header">
            <h5><i class="bi bi-file-earmark-richtext"></i> Letterhead & Footer HTML</h5>
        </div>
        <div class="card-body">
            <form method="POST" id="letterheadForm">
                <input type="hidden" name="action" value="save_letterhead">
                <div class="mb-4">
                    <label class="admin-form-label">Letterhead HTML</label>
                    <p class="text-muted small mb-2">This appears at the top of all generated documents. Use inline styles for PDF compatibility.</p>
                    <textarea id="letterhead_html" name="letterhead_html" rows="8"><?= htmlspecialchars($settings['letterhead_html'] ?? '') ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="admin-form-label">Footer HTML</label>
                    <p class="text-muted small mb-2">This appears at the bottom of all generated documents.</p>
                    <textarea id="footer_html" name="footer_html" rows="5"><?= htmlspecialchars($settings['footer_html'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn-admin-primary" onclick="tinymce.triggerSave()">
                    <i class="bi bi-save"></i> Save Letterhead Settings
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($active_tab === 'letterhead'): ?>
<script>
const RICH_FONTS =
    'Arial=arial,helvetica,sans-serif;' +
    'Calibri=calibri,sans-serif;' +
    'Cambria=cambria,georgia,serif;' +
    'Georgia=georgia,palatino,serif;' +
    'Times New Roman=times new roman,times,serif;' +
    'Verdana=verdana,geneva,sans-serif';
const RICH_SIZES = '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 24pt 28pt 36pt';

tinymce.init({
    selector: '#letterhead_html',
    plugins: 'advlist autolink lists link image charmap code table',
    menubar: 'format table',
    toolbar: 'fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | table image | code',
    toolbar_mode: 'wrap',
    height: 260,
    promotion: false,
    branding: false,
    font_family_formats: RICH_FONTS,
    font_size_formats: RICH_SIZES,
    content_style: 'body { font-family: "Times New Roman", serif; font-size: 12pt; }',
});
tinymce.init({
    selector: '#footer_html',
    plugins: 'advlist autolink lists link image charmap code',
    menubar: 'format',
    toolbar: 'fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | image | code',
    toolbar_mode: 'wrap',
    height: 200,
    promotion: false,
    branding: false,
    font_family_formats: RICH_FONTS,
    font_size_formats: RICH_SIZES,
    content_style: 'body { font-family: "Times New Roman", serif; font-size: 12pt; }',
});
document.getElementById('letterheadForm').addEventListener('submit', function() {
    tinymce.triggerSave();
});
</script>
<?php endif; ?>
</body>
</html>
