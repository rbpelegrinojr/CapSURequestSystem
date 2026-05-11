<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$templates = get_request_types_with_templates();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Templates — CapSU Admin</title>
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
        <h4><i class="bi bi-file-earmark-richtext"></i> Document Templates</h4>
    </div>

    <div class="admin-card">
        <div class="card-header">
            <h5><i class="bi bi-collection"></i> All Templates</h5>
            <span class="text-muted small">Click Edit to customize each document template</span>
        </div>
        <div class="card-body p-0">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Request Type</th>
                        <th>Code</th>
                        <th>Template Status</th>
                        <th>Available Placeholders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $tpl): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($tpl['name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($tpl['description'] ?? '') ?></small>
                        </td>
                        <td>
                            <span class="status-badge" style="background:var(--light-bg);color:var(--primary-navy);border:1px solid var(--border-color);">
                                <?= htmlspecialchars($tpl['code']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($tpl['template_content']): ?>
                            <span style="color:#198754;font-size:0.82rem;font-weight:600;"><i class="bi bi-check-circle me-1"></i>Has Template</span>
                            <?php else: ?>
                            <span style="color:#dc3545;font-size:0.82rem;font-weight:600;"><i class="bi bi-x-circle me-1"></i>No Template</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code style="font-size:0.72rem;color:#666;">
                                {{requester_name}}, {{requester_position}},<br>
                                {{requester_department}}, {{purpose}},<br>
                                <?php
                                $fields = json_decode($tpl['form_fields'] ?? '[]', true) ?: [];
                                $ph = array_map(fn($f) => '{{'.$f['name'].'}}', $fields);
                                echo implode(', ', array_map('htmlspecialchars', $ph));
                                ?>
                            </code>
                        </td>
                        <td>
                            <a href="template_editor.php?type_id=<?= $tpl['id'] ?>" class="btn-admin-primary btn-admin-sm">
                                <i class="bi bi-pencil-square"></i> Edit
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="card-header">
            <h5><i class="bi bi-info-circle"></i> Template Placeholders Guide</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Use these placeholders in your templates. They will be replaced with actual data when generating documents.</p>
            <div class="row g-3">
                <?php
                $placeholders = [
                    ['{{requester_name}}',       'Full name of the requester'],
                    ['{{requester_email}}',      'Email address'],
                    ['{{requester_phone}}',      'Phone number'],
                    ['{{requester_department}}', 'Department / College / Office'],
                    ['{{requester_position}}',   'Position / Designation'],
                    ['{{purpose}}',              'Purpose of the request'],
                    ['{{tracking_number}}',      'Unique tracking number'],
                    ['{{current_date}}',         'Current date when document is generated'],
                    ['{{submitted_at}}',         'Request submission date'],
                    ['{{type_name}}',            'Request type full name'],
                ];
                foreach ($placeholders as $ph): ?>
                <div class="col-md-6">
                    <div style="background:var(--light-bg);border-radius:8px;padding:10px 14px;display:flex;gap:10px;align-items:flex-start;">
                        <code style="color:var(--primary-navy);font-size:0.82rem;white-space:nowrap;flex-shrink:0;"><?= $ph[0] ?></code>
                        <span class="text-muted small"><?= $ph[1] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
