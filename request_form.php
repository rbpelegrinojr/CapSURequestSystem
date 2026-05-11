<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

// Read and immediately clear flash data set by submit_request.php (PRG pattern)
$form_errors = $_SESSION['form_errors'] ?? [];
$old_input   = $_SESSION['form_data']   ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

$type_code = strtoupper(trim($_GET['type'] ?? ''));
if (!$type_code) {
    header('Location: index');
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT * FROM request_types WHERE code = ? AND is_active = 1');
$stmt->execute([$type_code]);
$request_type = $stmt->fetch();

if (!$request_type) {
    header('Location: index');
    exit;
}

$form_fields = json_decode($request_type['form_fields'] ?? '[]', true) ?: [];
$uni_name = get_setting('university_name') ?: 'Capiz State University';

$icons = [
    'COE'   => 'bi-person-badge',
    'CNPAC' => 'bi-shield-check',
    'CGS'   => 'bi-star-fill',
    'SR'    => 'bi-journal-text',
    'CA'    => 'bi-calendar-check',
    'CC'    => 'bi-award',
    'OTHER' => 'bi-file-earmark-text',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request: <?= htmlspecialchars($request_type['name']) ?> — <?= htmlspecialchars($uni_name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- HEADER -->
<header class="site-header">
    <nav class="navbar navbar-expand-lg container-xl">
        <a class="navbar-brand" href="index">
            <div class="brand-logo-circle">CS</div>
            <div class="brand-text">
                <span class="brand-name"><?= htmlspecialchars($uni_name) ?></span>
                <span class="brand-subtitle">Document Request System</span>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="index"><i class="bi bi-house-door me-1"></i>Home</a></li>
                <li class="nav-item"><a class="nav-link" href="track_request"><i class="bi bi-search me-1"></i>Track Request</a></li>
            </ul>
        </div>
    </nav>
</header>

<!-- BREADCRUMB -->
<div style="background:#fff;border-bottom:1px solid #e0e6f0;padding:10px 0;">
    <div class="container-xl">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.85rem;">
                <li class="breadcrumb-item"><a href="index" class="text-decoration-none" style="color:var(--primary-navy);">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($request_type['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="py-5">
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Type Header -->
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div style="width:54px;height:54px;background:linear-gradient(135deg,#1a2a4a,#1a3a6b);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#c9a84c;flex-shrink:0;">
                        <i class="bi <?= $icons[$type_code] ?? 'bi-file-earmark-text' ?>"></i>
                    </div>
                    <div>
                        <span style="font-size:0.7rem;font-weight:700;color:#c9a84c;letter-spacing:1.5px;text-transform:uppercase;"><?= htmlspecialchars($request_type['code']) ?></span>
                        <h1 class="h4 fw-bold mb-0" style="color:var(--primary-navy);"><?= htmlspecialchars($request_type['name']) ?></h1>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($request_type['description']) ?></p>
                    </div>
                </div>

                <div class="form-card">
                    <form action="submit_request" method="POST" id="requestForm" novalidate>

                        <?php if (!empty($form_errors)): ?>
                        <div class="alert alert-danger mb-4" role="alert">
                            <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following errors:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                <?php foreach ($form_errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <input type="hidden" name="request_type_id" value="<?= (int)$request_type['id'] ?>">
                        <input type="hidden" name="type_code" value="<?= htmlspecialchars($request_type['code']) ?>">

                        <h5 class="fw-bold mb-1" style="color:var(--primary-navy);">
                            <i class="bi bi-person-lines-fill me-2" style="color:var(--accent-gold);"></i>Personal Information
                        </h5>
                        <p class="text-muted small mb-4">Please provide your accurate personal details.</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="requester_name"
                                       placeholder="Last Name, First Name M.I."
                                       value="<?= htmlspecialchars($old_input['requester_name'] ?? '') ?>"
                                       required maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="requester_email"
                                       placeholder="your.email@capsu.edu.ph"
                                       value="<?= htmlspecialchars($old_input['requester_email'] ?? '') ?>"
                                       required maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="requester_phone"
                                       placeholder="+63 9XX XXX XXXX"
                                       value="<?= htmlspecialchars($old_input['requester_phone'] ?? '') ?>"
                                       maxlength="30">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department / College / Office <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="requester_department"
                                       placeholder="e.g. College of Engineering"
                                       value="<?= htmlspecialchars($old_input['requester_department'] ?? '') ?>"
                                       required maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position / Designation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="requester_position"
                                       placeholder="e.g. Assistant Professor II"
                                       value="<?= htmlspecialchars($old_input['requester_position'] ?? '') ?>"
                                       required maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Purpose <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="purpose"
                                       placeholder="e.g. For loan application"
                                       value="<?= htmlspecialchars($old_input['purpose'] ?? '') ?>"
                                       required maxlength="255">
                            </div>
                        </div>

                        <?php if (!empty($form_fields)): ?>
                        <hr class="my-4">
                        <h5 class="fw-bold mb-1" style="color:var(--primary-navy);">
                            <i class="bi bi-list-check me-2" style="color:var(--accent-gold);"></i>Request Details
                        </h5>
                        <p class="text-muted small mb-4">Additional information required for this document type.</p>

                        <div class="row g-3">
                            <?php foreach ($form_fields as $field): ?>
                            <div class="col-md-<?= ($field['type'] === 'textarea') ? '12' : '6' ?>">
                                <label class="form-label">
                                    <?= htmlspecialchars($field['label']) ?>
                                    <?php if (!empty($field['required'])): ?><span class="text-danger">*</span><?php endif; ?>
                                </label>

                                <?php if ($field['type'] === 'select'): ?>
                                    <select class="form-select" name="extra[<?= htmlspecialchars($field['name']) ?>]"
                                            <?= !empty($field['required']) ? 'required' : '' ?>>
                                        <option value="">— Select —</option>
                                        <?php foreach ($field['options'] as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"
                                            <?= (($old_input['extra'][$field['name']] ?? '') === $opt) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($opt) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field['type'] === 'textarea'): ?>
                                    <textarea class="form-control" name="extra[<?= htmlspecialchars($field['name']) ?>]"
                                              rows="4" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                                              <?= !empty($field['required']) ? 'required' : '' ?>><?= htmlspecialchars($old_input['extra'][$field['name']] ?? '') ?></textarea>
                                <?php else: ?>
                                    <input type="text" class="form-control"
                                           name="extra[<?= htmlspecialchars($field['name']) ?>]"
                                           placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"
                                           value="<?= htmlspecialchars($old_input['extra'][$field['name']] ?? '') ?>"
                                           <?= !empty($field['required']) ? 'required' : '' ?>>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <hr class="my-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <a href="index" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn-primary-custom">
                                <i class="bi bi-send me-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Info Note -->
                <div class="mt-3 p-3 rounded" style="background:#fff3cd;border-left:4px solid #c9a84c;">
                    <small class="text-dark">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Note:</strong> After submission, you will receive a confirmation email with your tracking number.
                        Processing typically takes <strong>3–5 business days</strong>. Fields marked with <span class="text-danger">*</span> are required.
                    </small>
                </div>

            </div>
        </div>
    </div>
</main>

<!-- FOOTER -->
<footer class="site-footer py-4">
    <div class="container-xl text-center">
        <p class="footer-bottom mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($uni_name) ?> — Document Request System</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('requestForm').addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
});
</script>
</body>
</html>
