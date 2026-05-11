<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$type_id = filter_input(INPUT_GET, 'type_id', FILTER_VALIDATE_INT);
if (!$type_id) {
    header('Location: templates.php');
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT * FROM request_types WHERE id = ?');
$stmt->execute([$type_id]);
$request_type = $stmt->fetch();
if (!$request_type) {
    header('Location: templates.php');
    exit;
}

$stmt2 = $db->prepare('SELECT * FROM document_templates WHERE request_type_id = ?');
$stmt2->execute([$type_id]);
$template = $stmt2->fetch();

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_content = $_POST['template_content'] ?? '';
    $header_html      = $_POST['header_html'] ?? '';
    $footer_html      = $_POST['footer_html'] ?? '';
    $layout_json_raw  = $_POST['layout_json'] ?? '';

    $layout_json = null;
    if ($layout_json_raw) {
        $decoded = json_decode($layout_json_raw, true);
        $layout_json = $decoded ? $layout_json_raw : null;
    }

    if ($template) {
        $stmt3 = $db->prepare(
            'UPDATE document_templates SET template_content=?, header_html=?, footer_html=?, layout_json=? WHERE request_type_id=?'
        );
        $stmt3->execute([$template_content, $header_html, $footer_html, $layout_json, $type_id]);
    } else {
        $stmt3 = $db->prepare(
            'INSERT INTO document_templates (request_type_id, template_content, header_html, footer_html, layout_json) VALUES (?,?,?,?,?)'
        );
        $stmt3->execute([$type_id, $template_content, $header_html, $footer_html, $layout_json]);
    }

    // Reload template
    $stmt2->execute([$type_id]);
    $template = $stmt2->fetch();
    $success_msg = 'Template saved successfully.';
}

$letterhead = get_setting('letterhead_html') ?: '';
$footer_default = get_setting('footer_html') ?: '';

$current_layout = json_decode($template['layout_json'] ?? '[]', true) ?: ['header','content','signatories','footer'];
$form_fields = json_decode($request_type['form_fields'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template Editor — <?= htmlspecialchars($request_type['name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="../assets/tinymce/tinymce.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-content">

    <div class="page-header-bar">
        <h4><i class="bi bi-pencil-square"></i> Template Editor</h4>
        <div class="d-flex gap-2">
            <a href="templates.php" class="btn-admin-primary btn-admin-sm" style="background:var(--text-muted);">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <button type="button" class="btn-admin-primary btn-admin-sm" onclick="previewTemplate()">
                <i class="bi bi-eye"></i> Preview
            </button>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Type Info -->
    <div class="admin-card mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
                <div style="background:var(--primary-navy);color:var(--accent-gold);width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0;">
                    <?= htmlspecialchars($request_type['code']) ?>
                </div>
                <div>
                    <strong><?= htmlspecialchars($request_type['name']) ?></strong>
                    <div class="text-muted small"><?= htmlspecialchars($request_type['description'] ?? '') ?></div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" id="templateForm">

        <div class="row g-3">
            <div class="col-lg-8">

                <!-- Layout Sections Builder -->
                <div class="admin-card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-layout-text-window-reverse"></i> Section Layout</h5>
                        <span class="text-muted small">Drag to reorder sections</span>
                    </div>
                    <div class="card-body">
                        <div id="layoutSections" style="min-height:60px;">
                            <?php
                            $section_labels = [
                                'header'      => ['icon' => 'bi-card-heading',   'label' => 'Header / Letterhead'],
                                'content'     => ['icon' => 'bi-body-text',      'label' => 'Document Content'],
                                'signatories' => ['icon' => 'bi-pen',            'label' => 'Signatory Block'],
                                'footer'      => ['icon' => 'bi-card-footer',    'label' => 'Footer'],
                            ];
                            foreach ($current_layout as $section):
                                $info = $section_labels[$section] ?? ['icon' => 'bi-square', 'label' => ucfirst($section)];
                            ?>
                            <div class="signatory-item" data-section="<?= htmlspecialchars($section) ?>">
                                <i class="bi bi-grip-vertical drag-handle"></i>
                                <div class="sig-info">
                                    <div class="sig-name"><i class="bi <?= $info['icon'] ?> me-2"></i><?= $info['label'] ?></div>
                                </div>
                                <i class="bi bi-arrows-expand text-muted"></i>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="layout_json" id="layoutJson" value='<?= htmlspecialchars(json_encode($current_layout)) ?>'>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="admin-card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-body-text"></i> Document Content</h5>
                    </div>
                    <div class="card-body">
                        <textarea id="template_content" name="template_content" rows="12"><?= htmlspecialchars($template['template_content'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Custom Header -->
                <div class="admin-card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-card-heading"></i> Custom Header HTML</h5>
                        <span class="text-muted small">Leave blank to use global letterhead</span>
                    </div>
                    <div class="card-body">
                        <textarea id="header_html" name="header_html" rows="6"><?= htmlspecialchars($template['header_html'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="admin-card mb-3">
                    <div class="card-header">
                        <h5><i class="bi bi-card-footer"></i> Custom Footer HTML</h5>
                        <span class="text-muted small">Leave blank to use global footer</span>
                    </div>
                    <div class="card-body">
                        <textarea id="footer_html" name="footer_html" rows="4"><?= htmlspecialchars($template['footer_html'] ?? '') ?></textarea>
                    </div>
                </div>

            </div>

            <!-- Right: Placeholders -->
            <div class="col-lg-4">
                <div class="admin-card" style="position:sticky;top:80px;">
                    <div class="card-header">
                        <h5><i class="bi bi-braces"></i> Placeholders</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Click to copy, then paste into the editor.</p>
                        <?php
                        $common_placeholders = [
                            '{{requester_name}}', '{{requester_email}}', '{{requester_phone}}',
                            '{{requester_department}}', '{{requester_position}}', '{{purpose}}',
                            '{{tracking_number}}', '{{current_date}}', '{{submitted_at}}', '{{type_name}}',
                        ];
                        foreach ($common_placeholders as $ph): ?>
                        <div class="mb-1">
                            <code onclick="copyPlaceholder('<?= htmlspecialchars($ph) ?>')"
                                  style="background:var(--light-bg);padding:4px 10px;border-radius:6px;font-size:0.8rem;cursor:pointer;display:block;border:1px solid var(--border-color);transition:all 0.2s;"
                                  title="Click to copy">
                                <?= htmlspecialchars($ph) ?>
                            </code>
                        </div>
                        <?php endforeach; ?>

                        <?php if (!empty($form_fields)): ?>
                        <hr>
                        <p class="text-muted small mb-2">Type-specific fields:</p>
                        <?php foreach ($form_fields as $ff): ?>
                        <div class="mb-1">
                            <code onclick="copyPlaceholder('{{<?= htmlspecialchars($ff['name']) ?>}}')"
                                  style="background:#fff3cd;padding:4px 10px;border-radius:6px;font-size:0.8rem;cursor:pointer;display:block;border:1px solid #ffc107;transition:all 0.2s;"
                                  title="<?= htmlspecialchars($ff['label']) ?>">
                                {{<?= htmlspecialchars($ff['name']) ?>}}
                            </code>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-body" style="border-top:1px solid var(--border-color);padding-top:16px;">
                        <button type="submit" class="btn-admin-gold w-100 justify-content-center">
                            <i class="bi bi-save"></i> Save Template
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent" style="background:#fff;padding:40px;border:1px solid #ddd;font-family:Times New Roman,serif;font-size:12pt;min-height:300px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// Shared TinyMCE font configuration
const EDITOR_FONT_FAMILIES =
    'Arial=arial,helvetica,sans-serif;' +
    'Arial Black=arial black,gadget,sans-serif;' +
    'Book Antiqua=book antiqua,palatino,serif;' +
    'Calibri=calibri,sans-serif;' +
    'Cambria=cambria,georgia,serif;' +
    'Comic Sans MS=comic sans ms,cursive;' +
    'Courier New=courier new,courier,monospace;' +
    'Georgia=georgia,palatino,serif;' +
    'Helvetica=helvetica,sans-serif;' +
    'Impact=impact,charcoal,sans-serif;' +
    'Tahoma=tahoma,arial,helvetica,sans-serif;' +
    'Times New Roman=times new roman,times,serif;' +
    'Trebuchet MS=trebuchet ms,helvetica,sans-serif;' +
    'Verdana=verdana,geneva,sans-serif';
const EDITOR_FONT_SIZES = '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 22pt 24pt 26pt 28pt 36pt 48pt 72pt';
const EDITOR_FONT_SIZES_SMALL = '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 24pt';

// TinyMCE init — Word-like document editor
tinymce.init({
    selector: '#template_content',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks visualchars code fullscreen insertdatetime media table pagebreak nonbreaking directionality charmap wordcount help',
    menubar: 'edit view insert format table tools help',
    toolbar: [
        'undo redo | fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor',
        'alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent | blockquote | subscript superscript',
        'table link image charmap | pagebreak nonbreaking | searchreplace | code fullscreen'
    ],
    toolbar_mode: 'wrap',
    height: 600,
    promotion: false,
    branding: false,
    font_family_formats: EDITOR_FONT_FAMILIES,
    font_size_formats: EDITOR_FONT_SIZES,
    block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6; Preformatted=pre',
    table_default_styles: {
        'width': '100%',
        'border-collapse': 'collapse',
    },
    table_default_attributes: {
        border: '1',
    },
    content_style: [
        'body {',
        '  font-family: "Times New Roman", serif;',
        '  font-size: 12pt;',
        '  line-height: 1.8;',
        '  margin: 40px auto;',
        '  max-width: 816px;',
        '  background: #fff;',
        '  padding: 60px 72px;',
        '  box-shadow: 0 0 0 1px #ddd;',
        '}',
        'table, td, th { border: 1px solid #999; padding: 4px 8px; }',
    ].join(''),
    setup: function(editor) {
        editor.on('init', function() {
            editor.getDoc().body.style.background = '#fff';
        });
    },
});

// Header editor — richer formatting for letterhead
tinymce.init({
    selector: '#header_html',
    plugins: 'advlist autolink lists link image charmap code table',
    menubar: 'format table',
    toolbar: 'fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | table image | code',
    toolbar_mode: 'wrap',
    height: 220,
    promotion: false,
    branding: false,
    font_family_formats: EDITOR_FONT_FAMILIES,
    font_size_formats: EDITOR_FONT_SIZES_SMALL,
    content_style: 'body { font-family: "Times New Roman", serif; font-size: 12pt; }',
});

// Footer editor — richer formatting
tinymce.init({
    selector: '#footer_html',
    plugins: 'advlist autolink lists link image charmap code',
    menubar: 'format',
    toolbar: 'fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | image | code',
    toolbar_mode: 'wrap',
    height: 180,
    promotion: false,
    branding: false,
    font_family_formats: EDITOR_FONT_FAMILIES,
    font_size_formats: EDITOR_FONT_SIZES_SMALL,
    content_style: 'body { font-family: "Times New Roman", serif; font-size: 12pt; }',
});

// SortableJS for layout sections
const sortable = Sortable.create(document.getElementById('layoutSections'), {
    animation: 150,
    handle: '.drag-handle',
    ghostClass: 'sortable-ghost',
    onEnd: function() {
        const items = document.querySelectorAll('#layoutSections [data-section]');
        const order = Array.from(items).map(el => el.dataset.section);
        document.getElementById('layoutJson').value = JSON.stringify(order);
    }
});

// Copy placeholder to clipboard
function copyPlaceholder(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Insert into active TinyMCE editor if available
        const activeEditor = tinymce.activeEditor;
        if (activeEditor && activeEditor.id === 'template_content') {
            activeEditor.insertContent(text);
        }
        showToast('Copied: ' + text);
    }).catch(() => {
        // fallback
        const el = document.createElement('input');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        showToast('Copied: ' + text);
    });
}

function showToast(msg) {
    const toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a3a6b;color:#fff;padding:10px 18px;border-radius:8px;font-size:0.85rem;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2200);
}

// Preview
function previewTemplate() {
    const content = tinymce.get('template_content') ? tinymce.get('template_content').getContent() : document.getElementById('template_content').value;
    const header  = tinymce.get('header_html') ? tinymce.get('header_html').getContent() : document.getElementById('header_html').value;
    const footer  = tinymce.get('footer_html') ? tinymce.get('footer_html').getContent() : document.getElementById('footer_html').value;

    const letterhead = <?= json_encode($letterhead) ?>;
    const globalFooter = <?= json_encode($footer_default) ?>;
    const layout = JSON.parse(document.getElementById('layoutJson').value);

    let html = '';
    layout.forEach(section => {
        if (section === 'header') html += (header || letterhead) + '<br>';
        else if (section === 'content') html += content + '<br>';
        else if (section === 'signatories') html += '<div style="margin-top:60px;"><table style="width:100%;"><tr><td style="text-align:center;width:50%;border-top:1px solid #333;padding-top:6px;font-weight:bold;">[Signatory Name]</td><td></td></tr></table></div>';
        else if (section === 'footer') html += '<br>' + (footer || globalFooter);
    });

    // Replace sample placeholders for preview
    html = html.replace(/\{\{requester_name\}\}/g, 'Juan Dela Cruz')
               .replace(/\{\{requester_position\}\}/g, 'Assistant Professor II')
               .replace(/\{\{requester_department\}\}/g, 'College of Engineering')
               .replace(/\{\{purpose\}\}/g, 'For loan application')
               .replace(/\{\{tracking_number\}\}/g, 'CAPSU-20240101-XXXXX')
               .replace(/\{\{current_date\}\}/g, new Date().toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'}))
               .replace(/\{\{[^}]+\}\}/g, '[Sample Data]');

    document.getElementById('previewContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

// Ensure TinyMCE syncs before form submit
document.getElementById('templateForm').addEventListener('submit', function() {
    tinymce.triggerSave();
});
</script>
</body>
</html>
