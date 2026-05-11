<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$db = get_db();

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type'] ?? '';
$search        = trim($_GET['search'] ?? '');

// Pagination
$per_page    = 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($current_page - 1) * $per_page;

// Build query
$where  = ['1=1'];
$params = [];
if ($filter_status) {
    $where[] = 'r.status = ?';
    $params[] = $filter_status;
}
if ($filter_type) {
    $where[] = 'rt.code = ?';
    $params[] = $filter_type;
}
if ($search) {
    $where[] = '(r.tracking_number LIKE ? OR r.requester_name LIKE ? OR r.requester_email LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$where_sql = implode(' AND ', $where);

// Count
$count_stmt = $db->prepare("SELECT COUNT(*) FROM requests r JOIN request_types rt ON r.request_type_id = rt.id WHERE $where_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// Results
$data_params = array_merge($params, [$per_page, $offset]);
$stmt = $db->prepare(
    "SELECT r.*, rt.name AS type_name, rt.code AS type_code
     FROM requests r
     JOIN request_types rt ON r.request_type_id = rt.id
     WHERE $where_sql
     ORDER BY r.submitted_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($data_params);
$requests = $stmt->fetchAll();

$request_types = get_all_request_types();

function build_url($overrides = []) {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'requests.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Requests — CapSU Admin</title>
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
        <h4><i class="bi bi-inbox"></i> All Requests</h4>
        <span class="text-muted small"><?= number_format($total) ?> record(s) found</span>
    </div>

    <!-- Filters -->
    <form method="GET" action="requests" class="filter-bar">
        <div>
            <label class="d-block" style="font-size:0.78rem;font-weight:600;color:var(--text-muted);margin-bottom:4px;">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Name, email, or tracking no." style="min-width:220px;">
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
                <i class="bi bi-search"></i> Filter
            </button>
            <a href="requests" class="btn-admin-primary" style="background:var(--text-muted);">
                <i class="bi bi-x-lg"></i> Clear
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="admin-card">
        <div class="card-body p-0">
            <?php if (empty($requests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                No requests found matching your criteria.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tracking No.</th>
                            <th>Type</th>
                            <th>Requester</th>
                            <th>Department</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $i => $req): ?>
                        <tr>
                            <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                            <td class="tracking-cell"><?= htmlspecialchars($req['tracking_number']) ?></td>
                            <td>
                                <span class="status-badge" style="background:var(--light-bg);color:var(--primary-navy);border:1px solid var(--border-color);">
                                    <?= htmlspecialchars($req['type_code']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($req['requester_name']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($req['requester_department'] ?: '—') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($req['requester_email']) ?></td>
                            <td><?= format_status_badge($req['status']) ?></td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($req['submitted_at'])) ?></td>
                            <td>
                                <a href="view_request?id=<?= $req['id'] ?>" class="btn-admin-primary btn-admin-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-top:1px solid var(--border-color);">
                <span class="text-muted small">
                    Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> of <?= number_format($total) ?>
                </span>
                <div class="admin-pagination">
                    <?php if ($current_page > 1): ?>
                    <a href="<?= htmlspecialchars(build_url(['page' => $current_page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $current_page - 2);
                    $end   = min($total_pages, $current_page + 2);
                    for ($p = $start; $p <= $end; $p++): ?>
                    <?php if ($p === $current_page): ?>
                    <span class="current-page"><?= $p ?></span>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars(build_url(['page' => $p])) ?>"><?= $p ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                    <a href="<?= htmlspecialchars(build_url(['page' => $current_page + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
