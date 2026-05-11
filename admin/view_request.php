<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: requests');
    exit;
}

$db = get_db();
$request = get_request_with_type($id);
if (!$request) {
    header('Location: requests');
    exit;
}

$additional_data = json_decode($request['additional_data'] ?? '{}', true) ?: [];
$form_fields = json_decode($request['form_fields'] ?? '[]', true) ?: [];

// Email logs
$email_logs = $db->prepare('SELECT * FROM email_logs WHERE request_id = ? ORDER BY sent_at DESC');
$email_logs->execute([$id]);
$email_logs = $email_logs->fetchAll();

$success_msg = $_GET['success'] ?? '';
$error_msg   = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request #<?= $id ?> — CapSU Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-content">

    <!-- Page Header -->
    <div class="page-header-bar">
        <h4><i class="bi bi-file-text"></i> Request Details</h4>
        <div class="d-flex gap-2 flex-wrap">
            <a href="requests" class="btn-admin-primary btn-admin-sm" style="background:var(--text-muted);">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="print_request?id=<?= $id ?>" class="btn-admin-primary btn-admin-sm">
                <i class="bi bi-printer"></i> Print
            </a>
            <a href="download_docx?id=<?= $id ?>" class="btn-admin-gold btn-admin-sm">
                <i class="bi bi-file-word"></i> Download DOCX
            </a>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- Main Details -->
        <div class="col-lg-8">
            <div class="admin-card">
                <div class="card-header">
                    <h5><i class="bi bi-person-lines-fill"></i> Request Information</h5>
                    <div><?= format_status_badge($request['status']) ?></div>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <div class="detail-label">Tracking No.</div>
                        <div class="detail-val"><code style="color:var(--primary-navy);font-weight:700;font-size:1rem;"><?= htmlspecialchars($request['tracking_number']) ?></code></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Request Type</div>
                        <div class="detail-val"><strong><?= htmlspecialchars($request['type_name']) ?></strong> <span class="text-muted small">(<?= htmlspecialchars($request['type_code']) ?>)</span></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Full Name</div>
                        <div class="detail-val"><strong><?= htmlspecialchars($request['requester_name']) ?></strong></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email</div>
                        <div class="detail-val"><a href="mailto:<?= htmlspecialchars($request['requester_email']) ?>"><?= htmlspecialchars($request['requester_email']) ?></a></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone</div>
                        <div class="detail-val"><?= htmlspecialchars($request['requester_phone'] ?: '—') ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Department</div>
                        <div class="detail-val"><?= htmlspecialchars($request['requester_department'] ?: '—') ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Position</div>
                        <div class="detail-val"><?= htmlspecialchars($request['requester_position'] ?: '—') ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Purpose</div>
                        <div class="detail-val"><?= htmlspecialchars($request['purpose'] ?: '—') ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Submitted</div>
                        <div class="detail-val"><?= format_date($request['submitted_at']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Last Updated</div>
                        <div class="detail-val"><?= format_date($request['updated_at']) ?></div>
                    </div>
                    <?php if ($request['admin_notes']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Admin Notes</div>
                        <div class="detail-val"><?= htmlspecialchars($request['admin_notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Extra Fields -->
            <?php if (!empty($additional_data)): ?>
            <div class="admin-card">
                <div class="card-header">
                    <h5><i class="bi bi-list-check"></i> Additional Request Details</h5>
                </div>
                <div class="card-body">
                    <?php
                    $field_labels = [];
                    foreach ($form_fields as $ff) {
                        $field_labels[$ff['name']] = $ff['label'];
                    }
                    foreach ($additional_data as $key => $value): ?>
                    <div class="detail-row">
                        <div class="detail-label"><?= htmlspecialchars($field_labels[$key] ?? ucwords(str_replace('_', ' ', $key))) ?></div>
                        <div class="detail-val"><?= htmlspecialchars($value) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Email Log -->
            <?php if (!empty($email_logs)): ?>
            <div class="admin-card">
                <div class="card-header">
                    <h5><i class="bi bi-envelope"></i> Email Log</h5>
                </div>
                <div class="card-body p-0">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Recipient</th><th>Subject</th><th>Sent At</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($email_logs as $log): ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($log['recipient_email']) ?></td>
                                <td class="small"><?= htmlspecialchars($log['subject']) ?></td>
                                <td class="small text-muted"><?= format_date($log['sent_at']) ?></td>
                                <td>
                                    <?php if ($log['status'] === 'sent'): ?>
                                    <span style="color:#198754;font-size:0.8rem;font-weight:600;"><i class="bi bi-check-circle me-1"></i>Sent</span>
                                    <?php else: ?>
                                    <span style="color:#dc3545;font-size:0.8rem;font-weight:600;"><i class="bi bi-x-circle me-1"></i>Failed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">

            <!-- Update Status -->
            <div class="admin-card mb-3">
                <div class="card-header">
                    <h5><i class="bi bi-arrow-repeat"></i> Update Status</h5>
                </div>
                <div class="card-body">
                    <form action="process_request" method="POST">
                        <input type="hidden" name="request_id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="update_status">
                        <div class="mb-3">
                            <label class="admin-form-label">New Status</label>
                            <select name="status" class="admin-form-control" required>
                                <?php foreach (['pending','processing','approved','rejected','completed'] as $s): ?>
                                <option value="<?= $s ?>" <?= $request['status'] === $s ? 'selected' : '' ?>>
                                    <?= ucfirst($s) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label">Admin Notes / Remarks</label>
                            <textarea name="admin_notes" class="admin-form-control" rows="3"
                                      placeholder="Optional notes visible to requester..."><?= htmlspecialchars($request['admin_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="notify_requester" id="notifyChk" value="1" checked>
                            <label class="form-check-label small" for="notifyChk">Send email notification to requester</label>
                        </div>
                        <button type="submit" class="btn-admin-primary w-100 justify-content-center">
                            <i class="bi bi-check2"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>

            <!-- Send Custom Email -->
            <div class="admin-card">
                <div class="card-header">
                    <h5><i class="bi bi-send"></i> Send Email to Requester</h5>
                </div>
                <div class="card-body">
                    <form action="send_email" method="POST">
                        <input type="hidden" name="request_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="admin-form-label">Subject</label>
                            <input type="text" name="subject" class="admin-form-control"
                                   value="Re: Your Request <?= htmlspecialchars($request['tracking_number']) ?>"
                                   required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="admin-form-label">Message</label>
                            <textarea name="message" class="admin-form-control" rows="5"
                                      placeholder="Type your message here..." required></textarea>
                        </div>
                        <button type="submit" class="btn-admin-gold w-100 justify-content-center">
                            <i class="bi bi-envelope-check"></i> Send Email
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
