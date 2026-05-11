<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$stats = get_stats();
$db = get_db();

// Recent requests
$recent = $db->query(
    'SELECT r.*, rt.name AS type_name, rt.code AS type_code
     FROM requests r JOIN request_types rt ON r.request_type_id = rt.id
     ORDER BY r.submitted_at DESC LIMIT 10'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CapSU Admin</title>
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
        <h4><i class="bi bi-speedometer2"></i> Dashboard</h4>
        <div class="d-flex gap-2">
            <a href="requests?status=pending" class="btn-admin-gold btn-admin-sm">
                <i class="bi bi-clock"></i> View Pending
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <?php
        $stat_cards = [
            ['label' => 'Total Requests',  'key' => 'total',      'icon' => 'bi-inbox-fill',         'bg' => '#1a3a6b', 'color' => '#c9a84c'],
            ['label' => 'Pending',         'key' => 'pending',    'icon' => 'bi-hourglass-split',    'bg' => '#fff3cd', 'color' => '#856404'],
            ['label' => 'Processing',      'key' => 'processing', 'icon' => 'bi-gear-fill',          'bg' => '#cfe2ff', 'color' => '#084298'],
            ['label' => 'Approved',        'key' => 'approved',   'icon' => 'bi-check-circle-fill',  'bg' => '#d1e7dd', 'color' => '#0f5132'],
            ['label' => 'Completed',       'key' => 'completed',  'icon' => 'bi-check2-all',         'bg' => '#d0d9ef', 'color' => '#1a3a6b'],
            ['label' => 'Rejected',        'key' => 'rejected',   'icon' => 'bi-x-circle-fill',      'bg' => '#f8d7da', 'color' => '#842029'],
        ];
        foreach ($stat_cards as $card): ?>
        <div class="col-6 col-lg-4 col-xl-2">
            <a href="requests<?= $card['key'] !== 'total' ? '?status='.$card['key'] : '' ?>" class="stat-card">
                <div class="stat-icon" style="background:<?= $card['bg'] ?>;">
                    <i class="bi <?= $card['icon'] ?>" style="color:<?= $card['color'] ?>;"></i>
                </div>
                <div>
                    <div class="stat-number"><?= $stats[$card['key']] ?></div>
                    <div class="stat-label"><?= $card['label'] ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Requests -->
    <div class="admin-card">
        <div class="card-header">
            <h5><i class="bi bi-clock-history"></i> Recent Requests</h5>
            <a href="requests" class="btn-admin-primary btn-admin-sm">View All</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recent)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                No requests yet.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Type</th>
                            <th>Requester</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $req): ?>
                        <tr>
                            <td class="tracking-cell"><?= htmlspecialchars($req['tracking_number']) ?></td>
                            <td><span class="type-badge" style="font-size:0.75rem;"><?= htmlspecialchars($req['type_code']) ?></span></td>
                            <td><?= htmlspecialchars($req['requester_name']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($req['requester_department'] ?: '—') ?></td>
                            <td><?= format_status_badge($req['status']) ?></td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($req['submitted_at'])) ?></td>
                            <td>
                                <a href="view_request?id=<?= $req['id'] ?>" class="btn-admin-primary btn-admin-sm">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.admin-content -->
</div><!-- /.admin-main -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
