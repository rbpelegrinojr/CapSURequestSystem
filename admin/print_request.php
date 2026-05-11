<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: requests.php');
    exit;
}

$request = get_request_with_type($id);
if (!$request) {
    header('Location: requests.php');
    exit;
}

$additional_data = json_decode($request['additional_data'] ?? '{}', true) ?: [];

// Get template
$db = get_db();
$stmt = $db->prepare('SELECT * FROM document_templates WHERE request_type_id = ?');
$stmt->execute([$request['request_type_id']]);
$template = $stmt->fetch();

$letterhead  = get_setting('letterhead_html') ?: '';
$global_footer = get_setting('footer_html') ?: '';
$uni_name    = get_setting('university_name') ?: 'Capiz State University';
$uni_address = get_setting('university_address') ?: '';

$template_content = $template['template_content'] ?? '';
$header_html      = $template['header_html'] ?? $letterhead;
$footer_html      = $template['footer_html'] ?? $global_footer;
$layout = json_decode($template['layout_json'] ?? '[]', true) ?: ['header','content','signatories','footer'];

// Fill template
$filled_content = fill_template($template_content, $request, $additional_data);
$filled_header  = fill_template($header_html ?: $letterhead, $request, $additional_data);
$filled_footer  = fill_template($footer_html ?: $global_footer, $request, $additional_data);

// Signatories
$signatories = get_active_signatories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($request['type_name']) ?> — <?= htmlspecialchars($request['tracking_number']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            color: #000;
            background: #f5f5f5;
            padding: 20px;
        }
        .page {
            background: #fff;
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            padding: 1in 1in 0.75in;
            box-shadow: 0 2px 20px rgba(0,0,0,0.15);
            position: relative;
        }
        .doc-header {
            text-align: center;
            margin-bottom: 24pt;
        }
        .doc-header h2 {
            color: #1a3a6b;
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 3pt;
        }
        .doc-header p {
            margin: 2pt 0;
            font-size: 10pt;
            color: #555;
        }
        .doc-header .divider {
            border-top: 2px solid #1a3a6b;
            margin: 8pt 0 0;
        }
        .doc-title {
            text-align: center;
            margin: 20pt 0 24pt;
        }
        .doc-title h3 {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #1a2a4a;
            text-decoration: underline;
        }
        .doc-date {
            text-align: right;
            margin-bottom: 20pt;
            font-size: 11pt;
        }
        .doc-salutation {
            font-size: 11pt;
            margin-bottom: 16pt;
        }
        .doc-body {
            font-size: 12pt;
            line-height: 1.8;
            text-align: justify;
        }
        .doc-body p {
            margin-bottom: 12pt;
        }
        .signatory-section {
            margin-top: 40pt;
        }
        .signatory-block {
            display: inline-block;
            margin-right: 40pt;
            text-align: center;
            min-width: 200pt;
        }
        .sig-name-line {
            border-top: 1px solid #000;
            padding-top: 4pt;
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
        }
        .sig-title-text {
            font-size: 10pt;
            color: #333;
        }
        .doc-footer-area {
            margin-top: 32pt;
        }
        .tracking-info {
            margin-top: 40pt;
            padding-top: 8pt;
            border-top: 1px dashed #ccc;
            font-size: 9pt;
            color: #888;
        }
        .no-print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        .no-print-controls button, .no-print-controls a {
            background: #1a3a6b;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: Arial, sans-serif;
        }
        .no-print-controls a.gold { background: #c9a84c; color: #1a2a4a; }

        @media print {
            body { padding: 0; background: #fff; }
            .page { box-shadow: none; width: auto; padding: 0.75in; }
            .no-print-controls { display: none !important; }
        }
    </style>
</head>
<body>

<!-- Print Controls -->
<div class="no-print-controls">
    <button onclick="window.print()">&#128438; Print</button>
    <a href="download_docx.php?id=<?= $id ?>" class="gold">&#128196; Download DOCX</a>
    <a href="view_request.php?id=<?= $id ?>" style="background:#6c757d;">&#8592; Back</a>
</div>

<div class="page">

    <?php foreach ($layout as $section):
        if ($section === 'header'): ?>
        <!-- Header / Letterhead -->
        <div class="doc-header">
            <?php if ($filled_header): ?>
                <?= $filled_header ?>
            <?php else: ?>
                <h2><?= htmlspecialchars($uni_name) ?></h2>
                <p><?= htmlspecialchars($uni_address) ?></p>
                <div class="divider"></div>
            <?php endif; ?>
        </div>

        <?php elseif ($section === 'content'): ?>
        <!-- Document Content -->
        <div class="doc-title">
            <h3><?= htmlspecialchars($request['type_name']) ?></h3>
        </div>

        <div class="doc-date">
            <?= date('F d, Y') ?>
        </div>

        <p class="doc-salutation">TO WHOM IT MAY CONCERN:</p>

        <div class="doc-body">
            <?= $filled_content ?: '<p style="color:#999;text-align:center;">[No template content configured. Please edit the template in the admin panel.]</p>' ?>
        </div>

        <?php elseif ($section === 'signatories' && !empty($signatories)): ?>
        <!-- Signatory Section -->
        <div class="signatory-section">
            <table style="width:100%;margin-top:40pt;">
                <tr>
                    <?php foreach ($signatories as $sig): ?>
                    <td style="text-align:center;width:<?= round(100 / count($signatories)) ?>%;padding:0 10pt;">
                        <div style="margin-top:30pt;">
                            <div style="border-top:1px solid #000;padding-top:6pt;">
                                <strong style="font-size:11pt;"><?= htmlspecialchars($sig['name']) ?></strong><br>
                                <span style="font-size:10pt;"><?= htmlspecialchars($sig['title']) ?></span>
                            </div>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>

        <?php elseif ($section === 'footer'): ?>
        <!-- Footer -->
        <div class="doc-footer-area">
            <?php if ($filled_footer): ?>
                <?= $filled_footer ?>
            <?php endif; ?>
        </div>

        <!-- Tracking Info -->
        <div class="tracking-info">
            Tracking No.: <strong><?= htmlspecialchars($request['tracking_number']) ?></strong>
            &nbsp;&bull;&nbsp; Request Type: <?= htmlspecialchars($request['type_name']) ?>
            &nbsp;&bull;&nbsp; Submitted: <?= format_date($request['submitted_at']) ?>
            &nbsp;&bull;&nbsp; Generated: <?= date('F d, Y g:i A') ?>
        </div>

        <?php endif;
    endforeach; ?>

</div>

<script>
// Delay print to ensure all styles and web fonts have finished rendering
window.addEventListener('load', function() {
    setTimeout(function() {
        window.print();
    }, 800);
});
</script>
</body>
</html>
