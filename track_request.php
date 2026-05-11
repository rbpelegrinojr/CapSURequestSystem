<?php
require_once __DIR__ . '/includes/functions.php';

$uni_name = get_setting('university_name') ?: 'Capiz State University';
$tracking  = strtoupper(trim($_GET['tracking'] ?? ''));
$is_new    = isset($_GET['new']);
$request   = null;
$not_found = false;

if ($tracking) {
    $request = get_request_by_tracking($tracking);
    if (!$request) $not_found = true;
}

$status_steps = [
    ['key' => 'pending',    'label' => 'Submitted',  'icon' => 'bi-send'],
    ['key' => 'processing', 'label' => 'Processing', 'icon' => 'bi-gear'],
    ['key' => 'approved',   'label' => 'Approved',   'icon' => 'bi-check-circle'],
    ['key' => 'completed',  'label' => 'Completed',  'icon' => 'bi-check2-all'],
];

$step_order = ['pending' => 0, 'processing' => 1, 'approved' => 2, 'completed' => 3, 'rejected' => 1];
$current_step = $request ? ($step_order[$request['status']] ?? 0) : -1;
$is_rejected = $request && $request['status'] === 'rejected';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Request — <?= htmlspecialchars($uni_name) ?></title>
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
                <li class="nav-item"><a class="nav-link active" href="track_request"><i class="bi bi-search me-1"></i>Track Request</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="py-5">
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <?php if ($is_new && $request): ?>
                <div class="alert-success-custom mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                        <div>
                            <strong>Request Submitted Successfully!</strong><br>
                            <span class="small">Your tracking number is <strong><?= htmlspecialchars($request['tracking_number']) ?></strong>. A confirmation email has been sent to <strong><?= htmlspecialchars($request['requester_email']) ?></strong>.</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Search Form -->
                <div class="form-card mb-4">
                    <h4 class="fw-bold mb-1" style="color:var(--primary-navy);">
                        <i class="bi bi-radar me-2" style="color:var(--accent-gold);"></i>Track Your Request
                    </h4>
                    <p class="text-muted small mb-4">Enter your tracking number to check the current status of your request.</p>
                    <form method="GET" action="track_request">
                        <div class="d-flex gap-2">
                            <input type="text" name="tracking" class="form-control"
                                   value="<?= htmlspecialchars($tracking) ?>"
                                   placeholder="e.g. CAPSU-20240511-AB3DE"
                                   style="font-family:'Courier New',monospace;text-transform:uppercase;font-weight:600;"
                                   maxlength="25" required>
                            <button type="submit" class="btn-primary-custom" style="white-space:nowrap;">
                                <i class="bi bi-search me-1"></i> Track
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($not_found): ?>
                <div class="alert-danger-custom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div>
                            <strong>Tracking number not found.</strong>
                            <br><small>Please double-check your tracking number. It should look like: <code>CAPSU-20240511-AB3DE</code></small>
                        </div>
                    </div>
                </div>

                <?php elseif ($request): ?>
                <!-- Request Found -->
                <div class="form-card">

                    <!-- Header -->
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4 pb-3" style="border-bottom:1px solid var(--border-color);">
                        <div>
                            <span class="type-badge mb-2 d-inline-block"><?= htmlspecialchars($request['type_name']) ?></span>
                            <div class="tracking-display"><?= htmlspecialchars($request['tracking_number']) ?></div>
                            <div class="mt-2"><?= format_status_badge($request['status']) ?></div>
                        </div>
                        <div class="text-muted small text-end">
                            <div><strong>Submitted:</strong><br><?= format_date($request['submitted_at']) ?></div>
                            <div class="mt-1"><strong>Last Updated:</strong><br><?= format_date($request['updated_at']) ?></div>
                        </div>
                    </div>

                    <!-- Status Timeline -->
                    <h6 class="fw-bold mb-3" style="color:var(--primary-navy);">Request Progress</h6>

                    <?php if ($is_rejected): ?>
                    <div class="alert-danger-custom mb-4">
                        <i class="bi bi-x-circle-fill me-2"></i>
                        <strong>Your request has been rejected.</strong>
                        <?php if ($request['admin_notes']): ?>
                        <br><small>Reason: <?= htmlspecialchars($request['admin_notes']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-0">
                            <?php foreach ($status_steps as $i => $step):
                                $done   = $current_step > $i;
                                $active = $current_step === $i && !$is_rejected;
                                $rejected_step = $is_rejected && $i === 1;
                            ?>
                            <div class="text-center flex-fill">
                                <div class="d-flex align-items-center">
                                    <?php if ($i > 0): ?>
                                    <div style="height:3px;flex:1;background:<?= $done ? '#198754' : ($i <= $current_step && !$is_rejected ? '#c9a84c' : '#ddd') ?>;transition:background 0.3s;"></div>
                                    <?php endif; ?>
                                    <div style="width:40px;height:40px;border-radius:50%;background:<?= $done ? '#198754' : ($active ? '#c9a84c' : ($rejected_step ? '#dc3545' : '#ddd')) ?>;display:flex;align-items:center;justify-content:center;color:<?= ($done || $active || $rejected_step) ? '#fff' : '#999' ?>;font-size:1rem;flex-shrink:0;transition:all 0.3s;border:3px solid #fff;box-shadow:0 0 0 2px <?= $done ? '#198754' : ($active ? '#c9a84c' : ($rejected_step ? '#dc3545' : '#ddd')) ?>;">
                                        <?php if ($done): ?>
                                        <i class="bi bi-check-lg"></i>
                                        <?php elseif ($rejected_step): ?>
                                        <i class="bi bi-x-lg"></i>
                                        <?php else: ?>
                                        <i class="bi <?= $step['icon'] ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($i < count($status_steps) - 1): ?>
                                    <div style="height:3px;flex:1;background:<?= $done ? '#198754' : '#ddd' ?>;"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2" style="font-size:0.75rem;font-weight:<?= ($done || $active) ? '700' : '400' ?>;color:<?= $done ? '#198754' : ($active ? '#c9a84c' : '#999') ?>;">
                                    <?= $step['label'] ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Details -->
                    <h6 class="fw-bold mb-3" style="color:var(--primary-navy);">Request Details</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value"><?= htmlspecialchars($request['requester_name']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-label">Email</div>
                            <div class="detail-value"><?= htmlspecialchars($request['requester_email']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-label">Department</div>
                            <div class="detail-value"><?= htmlspecialchars($request['requester_department'] ?: '—') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-label">Position</div>
                            <div class="detail-value"><?= htmlspecialchars($request['requester_position'] ?: '—') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-label">Purpose</div>
                            <div class="detail-value"><?= htmlspecialchars($request['purpose'] ?: '—') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value"><?= htmlspecialchars($request['requester_phone'] ?: '—') ?></div>
                        </div>
                    </div>

                    <?php if ($request['admin_notes'] && !$is_rejected): ?>
                    <div class="mt-3 p-3 rounded" style="background:#f4f6fb;border-left:3px solid #1a3a6b;">
                        <div class="detail-label">Admin Notes</div>
                        <div class="detail-value"><?= htmlspecialchars($request['admin_notes']) ?></div>
                    </div>
                    <?php endif; ?>

                </div>

                <?php elseif ($tracking): ?>
                <!-- no result, not found already handled above -->
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="index" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Home
                    </a>
                </div>

            </div>
        </div>
    </div>
</main>

<footer class="site-footer py-4">
    <div class="container-xl text-center">
        <p class="footer-bottom mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($uni_name) ?> — Document Request System</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
