<?php
$current_admin_name = $_SESSION['admin_name'] ?? 'Admin';
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $current_admin_name))));
$initials = substr($initials, 0, 2) ?: 'AD';
?>
<header class="admin-header">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-lg-none" id="sidebarToggle" style="background:none;border:none;color:var(--primary-navy);font-size:1.2rem;">
            <i class="bi bi-list"></i>
        </button>
        <h5 class="page-title mb-0" id="pageTitle">Dashboard</h5>
    </div>
    <div class="header-right">
        <div class="d-flex align-items-center gap-2">
            <div class="admin-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="d-none d-sm-block">
                <div class="admin-name"><?= htmlspecialchars($current_admin_name) ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted);">Administrator</div>
            </div>
        </div>
    </div>
</header>
