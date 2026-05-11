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
        $type_id     = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $form_fields_raw = trim($_POST['form_fields_json'] ?? '[]');

        if (empty($code) || empty($name)) {
            $error_msg = 'Code and Name are required.';
        } elseif (!preg_match('/^[A-Z0-9_\-]{1,20}$/', $code)) {
            $error_msg = 'Code must be 1–20 uppercase letters, numbers, underscores, or hyphens.';
        } else {
            // Validate form_fields JSON
            $decoded = json_decode($form_fields_raw, true);
            if (!is_array($decoded)) {
                $form_fields_raw = '[]';
            }

            if ($action === 'add') {
                // Check code uniqueness
                $chk = $db->prepare('SELECT id FROM request_types WHERE code = ?');
                $chk->execute([$code]);
                if ($chk->fetch()) {
                    $error_msg = 'A request type with this code already exists.';
                } else {
                    $stmt = $db->prepare('INSERT INTO request_types (code, name, description, form_fields, is_active) VALUES (?,?,?,?,?)');
                    $stmt->execute([$code, $name, $description, $form_fields_raw, $is_active]);
                    $success_msg = 'Request type added successfully.';
                }
            } elseif ($action === 'edit' && $type_id) {
                // Check code uniqueness (excluding self)
                $chk = $db->prepare('SELECT id FROM request_types WHERE code = ? AND id != ?');
                $chk->execute([$code, $type_id]);
                if ($chk->fetch()) {
                    $error_msg = 'A request type with this code already exists.';
                } else {
                    $stmt = $db->prepare('UPDATE request_types SET code=?, name=?, description=?, form_fields=?, is_active=? WHERE id=?');
                    $stmt->execute([$code, $name, $description, $form_fields_raw, $is_active, $type_id]);
                    $success_msg = 'Request type updated successfully.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
        if ($type_id) {
            // Safety check: cannot delete if requests exist
            $chk = $db->prepare('SELECT COUNT(*) FROM requests WHERE request_type_id = ?');
            $chk->execute([$type_id]);
            $count = (int)$chk->fetchColumn();
            if ($count > 0) {
                $error_msg = "Cannot delete: {$count} request(s) already exist for this type. Deactivate it instead.";
            } else {
                $db->prepare('DELETE FROM document_templates WHERE request_type_id = ?')->execute([$type_id]);
                $db->prepare('DELETE FROM request_types WHERE id = ?')->execute([$type_id]);
                $success_msg = 'Request type deleted.';
            }
        }
    }
}

// Get all request types with template info
$types = $db->query(
    'SELECT rt.*, dt.id AS template_id, dt.template_content, dt.template_docx_path,
            (SELECT COUNT(*) FROM requests r WHERE r.request_type_id = rt.id) AS request_count
     FROM request_types rt
     LEFT JOIN document_templates dt ON rt.id = dt.request_type_id
     ORDER BY rt.id ASC'
)->fetchAll();

// Get single for edit
$edit_type = null;
$edit_id   = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($edit_id) {
    $stmt = $db->prepare('SELECT * FROM request_types WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_type = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Types — CapSU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .field-row {
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            position: relative;
        }
        .field-row .remove-field {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 0.78rem;
            cursor: pointer;
            line-height: 1.6;
        }
        .field-row .remove-field:hover { background: #b02a37; }
        .options-row { display: none; }
        .options-row.visible { display: block; }
        .type-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 600;
            background: var(--light-bg);
            color: var(--primary-navy);
            border: 1px solid var(--border-color);
        }
        .inactive-badge {
            background: #fff3cd;
            color: #856404;
            border-color: #ffc107;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-content">

    <div class="page-header-bar">
        <h4><i class="bi bi-list-task"></i> Request Types</h4>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Request Types List -->
        <div class="col-lg-7">
            <div class="admin-card">
                <div class="card-header">
                    <h5><i class="bi bi-collection"></i> All Request Types</h5>
                    <span class="text-muted small">Manage available document request categories</span>
                </div>
                <div class="card-body p-0">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Code</th>
                                <th>Template</th>
                                <th>Requests</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($types)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No request types yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($types as $t): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($t['name']) ?></strong>
                                    <?php if ($t['description']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($t['description'], 0, 60, '…')) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="type-badge"><?= htmlspecialchars($t['code']) ?></span></td>
                                <td>
                                    <?php if ($t['template_content'] || $t['template_docx_path']): ?>
                                    <span style="color:#198754;font-size:0.82rem;font-weight:600;"><i class="bi bi-check-circle me-1"></i>Set</span>
                                    <?php else: ?>
                                    <span style="color:#dc3545;font-size:0.82rem;font-weight:600;"><i class="bi bi-x-circle me-1"></i>None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= (int)$t['request_count'] ?></span>
                                </td>
                                <td>
                                    <?php if ($t['is_active']): ?>
                                    <span style="color:#198754;font-size:0.82rem;font-weight:600;">Active</span>
                                    <?php else: ?>
                                    <span class="type-badge inactive-badge">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="?edit=<?= $t['id'] ?>" class="btn-admin-primary btn-admin-sm">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="template_editor.php?type_id=<?= $t['id'] ?>" class="btn-admin-gold btn-admin-sm">
                                            <i class="bi bi-file-earmark-text"></i> Template
                                        </a>
                                        <?php if ((int)$t['request_count'] === 0): ?>
                                        <form method="POST" onsubmit="return confirm('Delete this request type? This cannot be undone.');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="type_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-admin-primary btn-admin-sm" style="background:#dc3545;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span title="Cannot delete: has existing requests" style="cursor:not-allowed;opacity:0.4;" class="btn-admin-primary btn-admin-sm" style="background:#dc3545;">
                                            <i class="bi bi-trash"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add / Edit Form -->
        <div class="col-lg-5">
            <div class="admin-card" style="position:sticky;top:80px;">
                <div class="card-header">
                    <h5><i class="bi bi-<?= $edit_type ? 'pencil-square' : 'plus-circle' ?>"></i>
                        <?= $edit_type ? 'Edit Request Type' : 'Add Request Type' ?>
                    </h5>
                    <?php if ($edit_type): ?>
                    <a href="request_types.php" class="btn-admin-primary btn-admin-sm" style="background:var(--text-muted);">
                        <i class="bi bi-x"></i> Cancel
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" id="typeForm">
                        <input type="hidden" name="action" value="<?= $edit_type ? 'edit' : 'add' ?>">
                        <?php if ($edit_type): ?>
                        <input type="hidden" name="type_id" value="<?= $edit_type['id'] ?>">
                        <?php endif; ?>
                        <input type="hidden" name="form_fields_json" id="form_fields_json"
                               value="<?= htmlspecialchars($edit_type['form_fields'] ?? '[]') ?>">

                        <div class="mb-3">
                            <label class="admin-form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" class="admin-form-control" name="code" id="codeInput"
                                   value="<?= htmlspecialchars($edit_type['code'] ?? '') ?>"
                                   placeholder="e.g. COE, SR, CA" required maxlength="20"
                                   style="text-transform:uppercase;"
                                   <?= ($edit_type && (int)($edit_type['is_active'] ?? 1) !== -99) ? '' : '' ?>>
                            <div class="form-text text-muted small">Uppercase letters/numbers only, up to 20 chars.</div>
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="admin-form-control" name="name"
                                   value="<?= htmlspecialchars($edit_type['name'] ?? '') ?>"
                                   placeholder="e.g. Certificate of Employment" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label">Description</label>
                            <textarea class="admin-form-control" name="description" rows="2"
                                      placeholder="Brief description of this document type" maxlength="500"><?= htmlspecialchars($edit_type['description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                                       <?= (!$edit_type || $edit_type['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="isActive">Active (visible to users)</label>
                            </div>
                        </div>

                        <!-- Form Fields Builder -->
                        <div class="mb-3">
                            <label class="admin-form-label">Additional Form Fields</label>
                            <p class="text-muted small mb-2">These are extra fields shown on the request form for this type.</p>
                            <div id="fieldsBuilder"></div>
                            <button type="button" class="btn-admin-primary btn-admin-sm mt-2" onclick="addField()">
                                <i class="bi bi-plus-lg"></i> Add Field
                            </button>
                        </div>

                        <button type="submit" class="btn-admin-gold w-100 justify-content-center" onclick="serializeFields()">
                            <i class="bi bi-<?= $edit_type ? 'save' : 'plus-lg' ?>"></i>
                            <?= $edit_type ? 'Update Request Type' : 'Add Request Type' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---- Form Fields Builder ----
let fieldCounter = 0;

const existingFields = <?= json_encode(
    json_decode($edit_type['form_fields'] ?? '[]', true) ?: [],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

function addField(data) {
    fieldCounter++;
    const idx = fieldCounter;
    data = data || {};
    const type = data.type || 'text';
    const optionsVal = data.options ? data.options.join('\n') : '';

    const row = document.createElement('div');
    row.className = 'field-row';
    row.dataset.idx = idx;
    row.innerHTML = `
        <button type="button" class="remove-field" onclick="removeField(${idx})"><i class="bi bi-x"></i></button>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="admin-form-label" style="font-size:0.78rem;">Field Name (key)</label>
                <input type="text" class="admin-form-control field-name" style="font-size:0.82rem;"
                       placeholder="e.g. period_of_service" value="${escHtml(data.name||'')}" maxlength="60">
            </div>
            <div class="col-6">
                <label class="admin-form-label" style="font-size:0.78rem;">Label</label>
                <input type="text" class="admin-form-control field-label" style="font-size:0.82rem;"
                       placeholder="e.g. Period of Service" value="${escHtml(data.label||'')}" maxlength="100">
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="admin-form-label" style="font-size:0.78rem;">Field Type</label>
                <select class="admin-form-control field-type" style="font-size:0.82rem;" onchange="toggleOptions(${idx}, this.value)">
                    <option value="text"     ${type==='text'     ?'selected':''}>Text</option>
                    <option value="textarea" ${type==='textarea' ?'selected':''}>Textarea</option>
                    <option value="select"   ${type==='select'   ?'selected':''}>Dropdown (select)</option>
                </select>
            </div>
            <div class="col-6">
                <label class="admin-form-label" style="font-size:0.78rem;">Placeholder</label>
                <input type="text" class="admin-form-control field-placeholder" style="font-size:0.82rem;"
                       placeholder="(optional)" value="${escHtml(data.placeholder||'')}" maxlength="150">
            </div>
        </div>
        <div class="row g-2 mb-1 options-row${type==='select'?' visible':''}" id="opts-${idx}">
            <div class="col-12">
                <label class="admin-form-label" style="font-size:0.78rem;">Options <span class="text-muted">(one per line)</span></label>
                <textarea class="admin-form-control field-options" rows="3" style="font-size:0.82rem;"
                          placeholder="Option 1&#10;Option 2&#10;Option 3">${escHtml(optionsVal)}</textarea>
            </div>
        </div>
        <div class="form-check mt-1">
            <input class="form-check-input field-required" type="checkbox" id="req-${idx}" ${data.required?'checked':''}>
            <label class="form-check-label small" for="req-${idx}">Required</label>
        </div>
    `;
    document.getElementById('fieldsBuilder').appendChild(row);
}

function removeField(idx) {
    const row = document.querySelector('.field-row[data-idx="'+idx+'"]');
    if (row) row.remove();
}

function toggleOptions(idx, val) {
    const opts = document.getElementById('opts-'+idx);
    if (opts) opts.classList.toggle('visible', val === 'select');
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function serializeFields() {
    const rows = document.querySelectorAll('#fieldsBuilder .field-row');
    const fields = [];
    rows.forEach(function(row) {
        const name  = row.querySelector('.field-name').value.trim().replace(/\s+/g,'_');
        const label = row.querySelector('.field-label').value.trim();
        if (!name || !label) return;
        const type        = row.querySelector('.field-type').value;
        const placeholder = row.querySelector('.field-placeholder').value.trim();
        const required    = row.querySelector('.field-required').checked;
        const obj = { name, label, type, placeholder, required };
        if (type === 'select') {
            const rawOpts = row.querySelector('.field-options').value;
            obj.options = rawOpts.split('\n').map(o=>o.trim()).filter(o=>o.length>0);
        }
        fields.push(obj);
    });
    document.getElementById('form_fields_json').value = JSON.stringify(fields);
}

// Force uppercase on code input
document.getElementById('codeInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Submit hook
document.getElementById('typeForm').addEventListener('submit', function() {
    serializeFields();
});

// Load existing fields on page load
existingFields.forEach(addField);
</script>
</body>
</html>
