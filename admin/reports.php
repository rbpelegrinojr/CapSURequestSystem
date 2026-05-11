<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$db = get_db();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type'] ?? '';

$where  = ['r.submitted_at >= ?', 'r.submitted_at <= ?'];
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

if ($filter_status) {
    $where[] = 'r.status = ?';
    $params[] = $filter_status;
}
if ($filter_type) {
    $where[] = 'rt.code = ?';
    $params[] = $filter_type;
}
$where_sql = implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT r.*, rt.name AS type_name, rt.code AS type_code
     FROM requests r
     JOIN request_types rt ON r.request_type_id = rt.id
     WHERE $where_sql
     ORDER BY r.submitted_at ASC"
);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Summary stats for filtered range
$summary_stmt = $db->prepare(
    "SELECT status, COUNT(*) AS cnt FROM requests r
     JOIN request_types rt ON r.request_type_id = rt.id
     WHERE $where_sql GROUP BY status"
);
$summary_stmt->execute($params);
$summary_rows = $summary_stmt->fetchAll();
$summary = [];
foreach ($summary_rows as $row) {
    $summary[$row['status']] = (int)$row['cnt'];
}

// Stats by type
$type_stmt = $db->prepare(
    "SELECT rt.name, rt.code, COUNT(*) AS cnt FROM requests r
     JOIN request_types rt ON r.request_type_id = rt.id
     WHERE $where_sql GROUP BY rt.id ORDER BY cnt DESC"
);
$type_stmt->execute($params);
$by_type = $type_stmt->fetchAll();

$request_types = get_all_request_types();
$total_in_range = array_sum($summary);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — CapSU Admin</title>
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
        <h4><i class="bi bi-bar-chart-line"></i> Reports</h4>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn-admin-primary btn-admin-sm no-print">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="reports.php" class="filter-bar no-print">
        <div>
            <label class="d-block" style="font-size:0.78rem;font-weight:600;color:var(--text-muted);margin-bottom:4px;">Date From</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div>
            <label class="d-block" style="font-size:0.78rem;font-weight:600;color:var(--text-muted);margin-bottom:4px;">Date To</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div>
            <label class="d-block" style="font-size:0.78rem;font-weight:600;color:var(--text-muted);margin-bottom:4px;">Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending','processing','approved','rejected','completed'] as $s): ?>
                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="d-block" style="font-size:0.78rem;font-weight:600;color:var(--text-muted);margin-bottom:4px;">Request Type</label>
            <select name="type">
                <option value="">All Types</option>
                <?php foreach ($request_types as $rt): ?>
                <option value="<?= htmlspecialchars($rt['code']) ?>" <?= $filter_type === $rt['code'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($rt['code']) ?> — <?= htmlspecialchars($rt['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-2 align-items-end">
            <button type="submit" class="btn-admin-primary">
                <i class="bi bi-funnel"></i> Apply
            </button>
            <a href="reports.php" class="btn-admin-primary" style="background:var(--text-muted);">Clear</a>
        </div>
    </form>

    <!-- Print Header -->
    <div class="print-only" style="display:none;text-align:center;margin-bottom:20px;">
        <h3 style="color:#1a3a6b;"><?= htmlspecialchars(get_setting('university_name') ?: 'Capiz State University') ?></h3>
        <h4>Request Report: <?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?></h4>
        <p>Generated: <?= date('F d, Y g:i A') ?></p>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d0d9ef;"><i class="bi bi-inbox-fill" style="color:#1a3a6b;"></i></div>
                <div><div class="stat-number"><?= number_format($total_in_range) ?></div><div class="stat-label">Total in Range</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff3cd;"><i class="bi bi-hourglass-split" style="color:#856404;"></i></div>
                <div><div class="stat-number"><?= $summary['pending'] ?? 0 ?></div><div class="stat-label">Pending</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d1e7dd;"><i class="bi bi-check-circle-fill" style="color:#0f5132;"></i></div>
                <div><div class="stat-number"><?= $summary['approved'] ?? 0 ?></div><div class="stat-label">Approved</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d0d9ef;"><i class="bi bi-check2-all" style="color:#1a3a6b;"></i></div>
                <div><div class="stat-number"><?= $summary['completed'] ?? 0 ?></div><div class="stat-label">Completed</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- By Type -->
        <div class="col-lg-5">
            <div class="admin-card">
                <div class="card-header"><h5><i class="bi bi-pie-chart"></i> Requests by Type</h5></div>
                <div class="card-body p-0">
                    <table class="admin-table">
                        <thead><tr><th>Type</th><th>Name</th><th>Count</th><th>%</th></tr></thead>
                        <tbody>
                            <?php if (empty($by_type)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No data</td></tr>
                            <?php else: foreach ($by_type as $t): ?>
                            <tr>
                                <td><span class="status-badge" style="background:var(--light-bg);color:var(--primary-navy);border:1px solid var(--border-color);"><?= htmlspecialchars($t['code']) ?></span></td>
                                <td class="small"><?= htmlspecialchars($t['name']) ?></td>
                                <td><strong><?= $t['cnt'] ?></strong></td>
                                <td class="text-muted small"><?= $total_in_range > 0 ? round($t['cnt'] / $total_in_range * 100, 1) : 0 ?>%</td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- By Status -->
        <div class="col-lg-7">
            <div class="admin-card">
                <div class="card-header"><h5><i class="bi bi-bar-chart"></i> Requests by Status</h5></div>
                <div class="card-body">
                    <?php
                    $status_colors = [
                        'pending' => '#ffc107','processing' => '#0d6efd','approved' => '#198754',
                        'rejected' => '#dc3545','completed' => '#1a3a6b',
                    ];
                    foreach (['pending','processing','approved','rejected','completed'] as $s):
                        $cnt = $summary[$s] ?? 0;
                        $pct = $total_in_range > 0 ? round($cnt / $total_in_range * 100, 1) : 0;
                    ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small fw-600"><?= ucfirst($s) ?></span>
                            <span class="small text-muted"><?= $cnt ?> (<?= $pct ?>%)</span>
                        </div>
                        <div style="background:#e9ecef;border-radius:4px;height:10px;">
                            <div style="width:<?= $pct ?>%;background:<?= $status_colors[$s] ?>;border-radius:4px;height:10px;transition:width 0.5s;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="admin-card">
        <div class="card-header">
            <h5><i class="bi bi-table"></i> Request Details (<?= number_format(count($results)) ?> records)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($results)): ?>
            <div class="text-center py-5 text-muted">No requests found in the selected date range.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr><th>#</th><th>Tracking No.</th><th>Type</th><th>Requester</th><th>Department</th><th>Status</th><th>Submitted</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $req): ?>
                        <tr>
                            <td class="text-muted small"><?= $i + 1 ?></td>
                            <td class="tracking-cell"><?= htmlspecialchars($req['tracking_number']) ?></td>
                            <td><span class="status-badge" style="background:var(--light-bg);color:var(--primary-navy);border:1px solid var(--border-color);"><?= htmlspecialchars($req['type_code']) ?></span></td>
                            <td><?= htmlspecialchars($req['requester_name']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($req['requester_department'] ?: '—') ?></td>
                            <td><?= format_status_badge($req['status']) ?></td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($req['submitted_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    .admin-sidebar, .admin-header { display: none !important; }
    .admin-main { margin-left: 0; padding-top: 0; }
}
</style>
</body>
</html>
