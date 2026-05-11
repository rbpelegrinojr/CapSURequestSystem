<?php
require_once __DIR__ . '/includes/functions.php';

$request_types = get_all_request_types();

$icons = [
    'COE'   => 'bi-person-badge',
    'CNPAC' => 'bi-shield-check',
    'CGS'   => 'bi-star-fill',
    'SR'    => 'bi-journal-text',
    'CA'    => 'bi-calendar-check',
    'CC'    => 'bi-award',
    'OTHER' => 'bi-file-earmark-text',
];

$uni_name    = get_setting('university_name') ?: 'Capiz State University';
$uni_address = get_setting('university_address') ?: 'Fuentes Drive, Roxas City, Capiz';
$uni_phone   = get_setting('university_phone') ?: '(036) 620-0367';
$uni_email   = get_setting('university_email') ?: 'info@capsu.edu.ph';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($uni_name) ?> — Request System</title>
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
                <li class="nav-item">
                    <a class="nav-link active" href="index"><i class="bi bi-house-door me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="track_request"><i class="bi bi-search me-1"></i>Track Request</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin/index"><i class="bi bi-person-lock me-1"></i>Admin</a>
                </li>
            </ul>
        </div>
    </nav>
</header>

<!-- HERO -->
<section class="hero-banner">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="hero-badge"><i class="bi bi-shield-check me-1"></i> Official Document System</span>
                <h1>Document Request Portal</h1>
                <p class="hero-subtitle">Submit and track your official document requests from <?= htmlspecialchars($uni_name) ?>. Fast, convenient, and fully digital.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#request-types" class="btn btn-warning fw-bold px-4 py-2">
                        <i class="bi bi-file-earmark-plus me-2"></i>Submit a Request
                    </a>
                    <a href="track_request" class="btn btn-outline-light px-4 py-2">
                        <i class="bi bi-search me-2"></i>Track My Request
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-flex justify-content-center mt-4 mt-lg-0">
                <div class="hero-seal">
                    <i class="bi bi-building"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="py-5 bg-white">
    <div class="container-xl">
        <div class="text-center mb-4">
            <h2 class="section-title">How It Works</h2>
            <div class="section-divider center"></div>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $steps = [
                ['icon' => 'bi-1-circle-fill', 'color' => '#1a3a6b', 'title' => 'Choose Request Type', 'desc' => 'Select the type of document you need from our available request categories.'],
                ['icon' => 'bi-2-circle-fill', 'color' => '#c9a84c', 'title' => 'Fill Out the Form',    'desc' => 'Provide your personal information and details relevant to your request.'],
                ['icon' => 'bi-3-circle-fill', 'color' => '#1a2a4a', 'title' => 'Get Tracking Number', 'desc' => 'Receive a unique tracking number via email to monitor your request status.'],
                ['icon' => 'bi-4-circle-fill', 'color' => '#198754', 'title' => 'Receive Your Document','desc' => 'Once approved, download or receive your official document.'],
            ];
            foreach ($steps as $i => $step): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="text-center p-3">
                    <i class="bi <?= $step['icon'] ?>" style="font-size:2.4rem;color:<?= $step['color'] ?>;"></i>
                    <h6 class="fw-700 mt-3 mb-2" style="color:var(--primary-navy);font-weight:700;"><?= $step['title'] ?></h6>
                    <p class="text-muted small mb-0"><?= $step['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- REQUEST TYPES -->
<section id="request-types" class="py-5" style="background:var(--light-bg);">
    <div class="container-xl">
        <div class="mb-4">
            <h2 class="section-title">Available Request Types</h2>
            <div class="section-divider"></div>
            <p class="text-muted">Select the document you need. Click a card to begin your request.</p>
        </div>
        <div class="row g-3">
            <?php foreach ($request_types as $rt): ?>
            <div class="col-sm-6 col-lg-4">
                <a href="request_form?type=<?= urlencode($rt['code']) ?>" class="request-card">
                    <div class="card-icon">
                        <i class="bi <?= $icons[$rt['code']] ?? 'bi-file-earmark-text' ?>"></i>
                    </div>
                    <div class="card-code"><?= htmlspecialchars($rt['code']) ?></div>
                    <h5><?= htmlspecialchars($rt['name']) ?></h5>
                    <p><?= htmlspecialchars($rt['description']) ?></p>
                    <span class="btn-request">Request Now <i class="bi bi-arrow-right ms-1"></i></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TRACK REQUEST SECTION -->
<section class="track-section">
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <i class="bi bi-radar display-5 text-warning mb-3 d-block"></i>
                <h3>Track Your Request</h3>
                <p>Enter your tracking number below to check the status of your submitted request.</p>
                <form action="track_request" method="GET" class="mt-3">
                    <div class="track-input-group">
                        <input type="text" name="tracking" placeholder="e.g. CAPSU-20240511-AB3DE"
                               maxlength="25" style="text-transform:uppercase;" required>
                        <button type="submit">
                            <i class="bi bi-search me-1"></i> Track
                        </button>
                    </div>
                </form>
                <p class="mt-3" style="font-size:0.82rem;color:rgba(255,255,255,0.5);">
                    <i class="bi bi-info-circle me-1"></i>
                    Tracking numbers are sent to your email after submission.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="container-xl">
        <div class="row g-4">
            <div class="col-lg-4">
                <h6><?= htmlspecialchars($uni_name) ?></h6>
                <p><?= htmlspecialchars($uni_address) ?></p>
                <p><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($uni_phone) ?></p>
                <p><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($uni_email) ?></p>
            </div>
            <div class="col-lg-3">
                <h6>Quick Links</h6>
                <p><a href="index"><i class="bi bi-chevron-right me-1"></i>Home</a></p>
                <p><a href="track_request"><i class="bi bi-chevron-right me-1"></i>Track Request</a></p>
                <p><a href="admin/index"><i class="bi bi-chevron-right me-1"></i>Admin Portal</a></p>
            </div>
            <div class="col-lg-5">
                <h6>Available Documents</h6>
                <?php foreach ($request_types as $rt): ?>
                <p><a href="request_form?type=<?= urlencode($rt['code']) ?>">
                    <i class="bi bi-chevron-right me-1"></i><?= htmlspecialchars($rt['name']) ?>
                </a></p>
                <?php endforeach; ?>
            </div>
        </div>
        <hr class="footer-divider">
        <p class="footer-bottom mb-0">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($uni_name) ?> — Document Request System. All rights reserved.
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Smooth scroll to sections
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>
</body>
</html>
