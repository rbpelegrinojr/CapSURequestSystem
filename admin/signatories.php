<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$db = get_db();
$success_msg = '';
$error_msg   = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $sig_id   = filter_input(INPUT_POST, 'sig_id', FILTER_VALIDATE_INT);
        $name     = trim($_POST['sig_name'] ?? '');
        $title    = trim($_POST['sig_title'] ?? '');
        $role     = trim($_POST['sig_role'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($title)) {
            $error_msg = 'Name and Title are required.';
        } elseif ($action === 'add') {
            $max_order = $db->query('SELECT MAX(sort_order) FROM signatories')->fetchColumn();
            $stmt = $db->prepare('INSERT INTO signatories (name, title, role, is_active, sort_order) VALUES (?,?,?,?,?)');
            $stmt->execute([$name, $title, $role, $is_active, (int)$max_order + 1]);
            $success_msg = 'Signatory added successfully.';
        } elseif ($action === 'edit' && $sig_id) {
            $stmt = $db->prepare('UPDATE signatories SET name=?, title=?, role=?, is_active=? WHERE id=?');
            $stmt->execute([$name, $title, $role, $is_active, $sig_id]);
            $success_msg = 'Signatory updated successfully.';
        }
    } elseif ($action === 'delete') {
        $sig_id = filter_input(INPUT_POST, 'sig_id', FILTER_VALIDATE_INT);
        if ($sig_id) {
            $db->prepare('DELETE FROM signatories WHERE id=?')->execute([$sig_id]);
            $success_msg = 'Signatory deleted.';
        }
    } elseif ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($order)) {
            foreach ($order as $i => $sid) {
                $db->prepare('UPDATE signatories SET sort_order=? WHERE id=?')->execute([$i + 1, (int)$sid]);
            }
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Get all signatories
$signatories = $db->query('SELECT * FROM signatories ORDER BY sort_order ASC, id ASC')->fetchAll();

// Get a single for edit
$edit_sig = null;
$edit_id  = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($edit_id) {
    $stmt = $db->prepare('SELECT * FROM signatories WHERE id=?');
    $stmt->execute([$edit_id]);
    $edit_sig = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signatories — CapSU Admin</title>
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
        <h4><i class="bi bi-pen"></i> Manage Signatories</h4>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Signatory List -->
        <div class="col-lg-7">
            <div class="admin-card">
                <div class="card-header">
                    <h5><i class="bi bi-list-ol"></i> Signatories (drag to reorder)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($signatories)): ?>
                    <div class="text-center text-muted py-4">No signatories yet.</div>
                    <?php else: ?>
                    <div id="signatoryList">
                        <?php foreach ($signatories as $sig): ?>
                        <div class="signatory-item" data-id="<?= $sig['id'] ?>">
                            <i class="bi bi-grip-vertical drag-handle"></i>
                            <div class="sig-info">
                                <div class="sig-name">
                                    <?= htmlspecialchars($sig['name']) ?>
                                    <?php if (!$sig['is_active']): ?>
                                    <span style="color:#dc3545;font-size:0.75rem;font-weight:600;"> (Inactive)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="sig-title"><?= htmlspecialchars($sig['title']) ?>
                                    <?php if ($sig['role']): ?>
                                    <span class="text-muted"> · <?= htmlspecialchars($sig['role']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <a href="?edit=<?= $sig['id'] ?>" class="btn-admin-primary btn-admin-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" onsubmit="return confirm('Delete this signatory?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                    <button type="submit" class="btn-admin-primary btn-admin-sm" style="background:#dc3545;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Drag items to reorder. Changes are saved automatically.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Add/Edit Form -->
        <div class="col-lg-5">
            <div class="admin-card" style="position:sticky;top:80px;">
                <div class="card-header">
                    <h5><i class="bi bi-<?= $edit_sig ? 'pencil-square' : 'plus-circle' ?>"></i>
                        <?= $edit_sig ? 'Edit Signatory' : 'Add Signatory' ?>
                    </h5>
                    <?php if ($edit_sig): ?>
                    <a href="signatories" class="btn-admin-primary btn-admin-sm" style="background:var(--text-muted);">
                        <i class="bi bi-x"></i> Cancel
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $edit_sig ? 'edit' : 'add' ?>">
                        <?php if ($edit_sig): ?>
                        <input type="hidden" name="sig_id" value="<?= $edit_sig['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="admin-form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="admin-form-control" name="sig_name"
                                   value="<?= htmlspecialchars($edit_sig['name'] ?? '') ?>"
                                   placeholder="e.g. DR. MARIA SANTOS" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label">Title / Position <span class="text-danger">*</span></label>
                            <input type="text" class="admin-form-control" name="sig_title"
                                   value="<?= htmlspecialchars($edit_sig['title'] ?? '') ?>"
                                   placeholder="e.g. University President" required maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label">Role / Code</label>
                            <input type="text" class="admin-form-control" name="sig_role"
                                   value="<?= htmlspecialchars($edit_sig['role'] ?? '') ?>"
                                   placeholder="e.g. president, hrmo" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                                       <?= (!$edit_sig || $edit_sig['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="isActive">Active (show in documents)</label>
                            </div>
                        </div>
                        <button type="submit" class="btn-admin-gold w-100 justify-content-center">
                            <i class="bi bi-<?= $edit_sig ? 'save' : 'plus-lg' ?>"></i>
                            <?= $edit_sig ? 'Update Signatory' : 'Add Signatory' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const sigList = document.getElementById('signatoryList');
if (sigList) {
    Sortable.create(sigList, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        onEnd: function() {
            const ids = Array.from(sigList.querySelectorAll('[data-id]')).map(el => el.dataset.id);
            fetch('signatories.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reorder&order=' + encodeURIComponent(JSON.stringify(ids))
            });
        }
    });
}
</script>
</body>
</html>
