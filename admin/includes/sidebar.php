<?php
$current_page = basename($_SERVER['PHP_SELF']);
$uni_name = get_setting('university_name') ?: 'Capiz State University';

$nav_links = [
    ['href' => 'dashboard.php',     'icon' => 'bi-speedometer2',      'label' => 'Dashboard'],
    ['href' => 'requests.php',       'icon' => 'bi-inbox',              'label' => 'All Requests'],
    ['href' => 'templates.php',      'icon' => 'bi-file-earmark-richtext','label' => 'Document Templates'],
    ['href' => 'signatories.php',    'icon' => 'bi-pen',                'label' => 'Signatories'],
    ['href' => 'reports.php',        'icon' => 'bi-bar-chart-line',     'label' => 'Reports'],
    ['href' => 'settings.php',       'icon' => 'bi-gear',               'label' => 'Settings'],
];

// Count pending for badge
$db = get_db();
$pending_count = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM requests WHERE status='pending'");
    $pending_count = (int)$stmt->fetchColumn();
} catch (Exception $e) { /* ignore */ }
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">CS</div>
        <h6><?= htmlspecialchars($uni_name) ?></h6>
        <small>Admin Panel</small>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main Menu</div>
        <ul class="list-unstyled mb-0">
            <?php foreach ($nav_links as $link): ?>
            <li class="nav-item">
                <a href="<?= $link['href'] ?>"
                   class="nav-link <?= ($current_page === $link['href']) ? 'active' : '' ?>">
                    <i class="bi <?= $link['icon'] ?>"></i>
                    <span><?= $link['label'] ?></span>
                    <?php if ($link['href'] === 'requests.php' && $pending_count > 0): ?>
                    <span style="background:#dc3545;color:#fff;font-size:0.7rem;padding:1px 7px;border-radius:10px;margin-left:auto;font-weight:700;">
                        <?= $pending_count ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <div class="nav-section-label mt-2">Quick Access</div>
        <ul class="list-unstyled mb-0">
            <li class="nav-item">
                <a href="../index.php" target="_blank" class="nav-link">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>User Portal</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<script>
// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    // Set page title from nav
    const active = sidebar ? sidebar.querySelector('.nav-link.active') : null;
    const pageTitle = document.getElementById('pageTitle');
    if (active && pageTitle) {
        pageTitle.textContent = active.querySelector('span') ? active.querySelector('span').textContent.trim() : '';
    }
});
</script>
