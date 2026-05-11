<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$type_id = filter_input(INPUT_GET, 'type_id', FILTER_VALIDATE_INT);
if (!$type_id) {
    header('Location: templates');
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT * FROM request_types WHERE id = ?');
$stmt->execute([$type_id]);
$request_type = $stmt->fetch();
if (!$request_type) {
    header('Location: templates');
    exit;
}

$stmt2 = $db->prepare('SELECT * FROM document_templates WHERE request_type_id = ?');
$stmt2->execute([$type_id]);
$template = $stmt2->fetch();

$success_msg = '';
$error_msg   = '';

// Resolve messages from redirects (set by upload_template_docx.php)
$redirect_success = $_GET['success'] ?? '';
$redirect_error   = $_GET['error'] ?? '';
if ($redirect_success === 'docx_uploaded') {
    $success_msg = 'Word template uploaded successfully. It will now be used when generating DOCX files.';
} elseif ($redirect_success === 'docx_cleared') {
    $success_msg = 'Word template removed. The HTML editor template will be used instead.';
} elseif ($redirect_error === 'invalid_type') {
    $error_msg = 'Invalid file type. Please upload a .docx file.';
} elseif ($redirect_error === 'invalid_docx') {
    $error_msg = 'The uploaded file is not a valid Word document (.docx). Please try again.';
} elseif ($redirect_error === 'upload_failed') {
    $error_msg = 'File upload failed. Please check your server configuration and try again.';
} elseif ($redirect_error === 'save_failed') {
    $error_msg = 'Could not save the uploaded file. Please check directory permissions.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_content = $_POST['template_content'] ?? '';

    if ($template) {
        $stmt3 = $db->prepare(
            'UPDATE document_templates SET template_content=?, header_html=?, footer_html=?, layout_json=? WHERE request_type_id=?'
        );
        $stmt3->execute([$template_content, '', '', null, $type_id]);
    } else {
        $stmt3 = $db->prepare(
            'INSERT INTO document_templates (request_type_id, template_content, header_html, footer_html, layout_json) VALUES (?,?,?,?,?)'
        );
        $stmt3->execute([$type_id, $template_content, '', '', null]);
    }

    // Reload template
    $stmt2->execute([$type_id]);
    $template = $stmt2->fetch();
    $success_msg = 'HTML template saved successfully.';
}

$letterhead    = get_setting('letterhead_html') ?: '';
$footer_default = get_setting('footer_html') ?: '';
$form_fields   = json_decode($request_type['form_fields'] ?? '[]', true) ?: [];

// Build a default starter template for new/empty templates
$starter_template = $template['template_content'] ?? '';
if ($starter_template === '') {
    $uni_name    = htmlspecialchars(get_setting('university_name') ?: 'Capiz State University');
    $uni_address = htmlspecialchars(get_setting('university_address') ?: 'Fuentes Drive, Roxas City, Capiz');
    $uni_phone   = htmlspecialchars(get_setting('university_phone') ?: '(036) 620-0367');
    $type_name   = htmlspecialchars($request_type['name']);
    $starter_template = <<<HTML
<div style="text-align:center;margin-bottom:20pt;">
  <h2 style="color:#1a3a6b;font-size:16pt;margin:0;">{$uni_name}</h2>
  <p style="font-size:10pt;color:#555;margin:2pt 0;">{$uni_address}</p>
  <p style="font-size:10pt;color:#555;margin:2pt 0;">Tel: {$uni_phone}</p>
  <hr style="border-top:2px solid #1a3a6b;margin-top:8pt;">
</div>
<h3 style="text-align:center;font-size:14pt;text-transform:uppercase;text-decoration:underline;margin:20pt 0;">{$type_name}</h3>
<p style="text-align:right;margin-bottom:16pt;">{{ current_date }}</p>
<p>TO WHOM IT MAY CONCERN:</p>
<p style="text-indent:36pt;">This is to certify that <strong>{{ requester_name }}</strong>, <em>{{ requester_position }}</em> of the <em>{{ requester_department }}</em>, has requested this document for the purpose of <strong>{{ purpose }}</strong>.</p>
<p style="text-indent:36pt;">This certification is being issued upon the request of the above-named individual for whatever legal purpose it may serve.</p>
HTML;
}
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
    <style>
        .placeholder-tag {
            background: var(--light-bg);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            display: block;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
            font-family: monospace;
        }
        .placeholder-tag:hover {
            background: var(--primary-navy);
            color: #fff;
            border-color: var(--primary-navy);
        }
        .placeholder-tag.field {
            background: #fff3cd;
            border-color: #ffc107;
        }
        .placeholder-tag.field:hover {
            background: #c9a84c;
            color: #1a2a4a;
            border-color: #c9a84c;
        }
        .tox-tinymce {
            border-radius: 0 0 8px 8px !important;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-content">

    <div class="page-header-bar">
        <h4><i class="bi bi-pencil-square"></i> Template Editor</h4>
        <div class="d-flex gap-2">
            <a href="templates" class="btn-admin-primary btn-admin-sm" style="background:var(--text-muted);">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <button type="button" id="previewBtn" class="btn-admin-primary btn-admin-sm" onclick="previewTemplate()">
                <i class="bi bi-eye"></i> Preview HTML
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

    <?php
    $has_docx = !empty($template['template_docx_path']);
    $docx_file = $has_docx ? __DIR__ . '/docx_templates/' . basename($template['template_docx_path']) : '';
    $docx_exists = $has_docx && file_exists($docx_file);
    // Default tab: show Word upload tab if a docx already exists, else HTML editor
    $default_tab = $docx_exists ? 'word' : 'html';
    if (isset($_GET['tab'])) {
        $default_tab = $_GET['tab'] === 'word' ? 'word' : 'html';
    }
    ?>

    <!-- Tab navigation -->
    <ul class="nav nav-tabs mb-3" id="templateTabs" role="tablist" style="border-bottom:2px solid var(--border-color);">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $default_tab === 'word' ? 'active' : '' ?>" id="tab-word-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-word" type="button" role="tab">
                <i class="bi bi-file-word me-1"></i> Upload Word Template
                <?php if ($docx_exists): ?>
                <span class="badge bg-success ms-1" style="font-size:0.7rem;">Active</span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $default_tab === 'html' ? 'active' : '' ?>" id="tab-html-btn"
                    data-bs-toggle="tab" data-bs-target="#tab-html" type="button" role="tab">
                <i class="bi bi-code-slash me-1"></i> HTML Editor
                <?php if ($docx_exists): ?>
                <span class="badge ms-1" style="font-size:0.7rem;background:#6c757d;">Fallback</span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="templateTabContent">

        <!-- ══ TAB 1: Upload Word Template ══════════════════════════════════ -->
        <div class="tab-pane fade <?= $default_tab === 'word' ? 'show active' : '' ?>" id="tab-word" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-9">

                    <!-- Current .docx status -->
                    <?php if ($docx_exists): ?>
                    <div class="admin-card mb-3" style="border-left:4px solid #198754;">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-file-word" style="font-size:2rem;color:#198754;"></i>
                                    <div>
                                        <div style="font-weight:600;color:#198754;"><i class="bi bi-check-circle me-1"></i>Word Template Active</div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars(basename($template['template_docx_path'])) ?>
                                            &nbsp;·&nbsp;
                                            <?= number_format(filesize($docx_file) / 1024, 1) ?> KB
                                        </div>
                                        <div class="small mt-1" style="color:#555;">
                                            This Word file is used when generating DOCX documents for this request type.
                                            The HTML editor below is the fallback when no Word template is uploaded.
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <a href="download_template_file?type_id=<?= $type_id ?>"
                                       class="btn-admin-primary btn-admin-sm">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    <form method="POST" action="upload_template_docx"
                                          onsubmit="return confirm('Remove the uploaded Word template?');">
                                        <input type="hidden" name="type_id" value="<?= $type_id ?>">
                                        <input type="hidden" name="action" value="clear">
                                        <button type="submit" class="btn-admin-primary btn-admin-sm"
                                                style="background:#dc3545;border-color:#dc3545;">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="admin-card mb-3" style="border-left:4px solid #ffc107;">
                        <div class="card-body py-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle" style="color:#856404;"></i>
                                <span class="small" style="color:#856404;">
                                    No Word template uploaded yet. Upload a <code>.docx</code> file below to use it for
                                    document generation. The HTML editor is used as the fallback.
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upload form -->
                    <div class="admin-card mb-3">
                        <div class="card-header">
                            <h5><i class="bi bi-cloud-upload"></i> <?= $docx_exists ? 'Replace' : 'Upload' ?> Word Template</h5>
                            <span class="text-muted small">Upload a <code>.docx</code> file designed in Microsoft Word or LibreOffice Writer</span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="upload_template_docx" enctype="multipart/form-data" id="docxUploadForm">
                                <input type="hidden" name="type_id" value="<?= $type_id ?>">
                                <input type="hidden" name="action" value="upload">
                                <div class="mb-3">
                                    <label class="admin-form-label" for="template_docx">Choose .docx file</label>
                                    <input type="file" name="template_docx" id="template_docx"
                                           class="form-control" accept=".docx" required>
                                    <div class="form-text">Only <strong>.docx</strong> files (Word 2007 or later) are accepted. Maximum upload size is determined by your server's <code>upload_max_filesize</code> setting.</div>
                                </div>
                                <button type="submit" class="btn-admin-gold">
                                    <i class="bi bi-cloud-upload"></i> Upload Template
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- How-to guide -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h5><i class="bi bi-book"></i> How to Create a Word Template</h5>
                        </div>
                        <div class="card-body">
                            <ol class="small mb-0" style="line-height:2;">
                                <li>Open <strong>Microsoft Word</strong> or <strong>LibreOffice Writer</strong> and design your document layout (letterhead, fonts, tables, signatures, etc.).</li>
                                <li>Place <strong>placeholder tags</strong> exactly where you want the request data to appear — for example, type <code>{{requester_name}}</code> where the employee's name should go. See the placeholder list on the right.</li>
                                <li><strong>Important:</strong> type each placeholder tag as a single continuous word — do not break it across lines or apply mixed formatting mid-tag. The tag must begin with <code>{{</code> and end with <code>}}</code> with no line breaks inside.</li>
                                <li>Save the file as <strong>Word Document (.docx)</strong>.</li>
                                <li>Upload it using the form above. When an admin downloads a DOCX for any request of this type, the placeholders will be automatically replaced with the requester's actual data.</li>
                            </ol>
                        </div>
                    </div>

                </div>

                <!-- Right: Placeholder reference for Word -->
                <div class="col-lg-3">
                    <div class="admin-card" style="position:sticky;top:80px;">
                        <div class="card-header">
                            <h5><i class="bi bi-braces"></i> Placeholders</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Type these exactly in your Word document. Click to copy.</p>
                            <?php
                            $common_placeholders = [
                                'requester_name'       => 'Full name (composed)',
                                'requester_firstname'  => 'First name',
                                'requester_middlename' => 'Middle initial (e.g. A.)',
                                'requester_lastname'   => 'Last name',
                                'requester_salutation' => 'Salutation (Mr or Ms)',
                                'requester_email'      => 'Email address',
                                'requester_phone'      => 'Phone number',
                                'requester_department' => 'Department',
                                'requester_position'   => 'Position / Designation',
                                'purpose'              => 'Purpose of request',
                                'tracking_number'      => 'Tracking number',
                                'current_date'         => 'Current date',
                                'submitted_at'         => 'Submission date',
                                'type_name'            => 'Request type name',
                            ];
                            foreach ($common_placeholders as $key => $label): ?>
                            <div class="mb-1">
                                <span class="placeholder-tag" onclick="copyToClipboard(<?= htmlspecialchars(json_encode('{{' . $key . '}}'), ENT_QUOTES) ?>)" title="Click to copy — <?= htmlspecialchars($label) ?>">
                                    {{<?= htmlspecialchars($key) ?>}}
                                </span>
                            </div>
                            <?php endforeach; ?>

                            <?php if (!empty($form_fields)): ?>
                            <hr>
                            <p class="text-muted small mb-2">Type-specific fields:</p>
                            <?php foreach ($form_fields as $ff): ?>
                            <div class="mb-1">
                                <span class="placeholder-tag field" onclick="copyToClipboard(<?= htmlspecialchars(json_encode('{{' . $ff['name'] . '}}'), ENT_QUOTES) ?>)" title="Click to copy — <?= htmlspecialchars($ff['label']) ?>">
                                    {{<?= htmlspecialchars($ff['name']) ?>}}
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /tab-word -->

        <!-- ══ TAB 2: HTML Editor ═══════════════════════════════════════════ -->
        <div class="tab-pane fade <?= $default_tab === 'html' ? 'show active' : '' ?>" id="tab-html" role="tabpanel">

            <?php if ($docx_exists): ?>
            <div class="alert alert-warning py-2 small mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                A <strong>Word (.docx) template is active</strong> for this request type. The HTML editor below is used
                only as a fallback (e.g. for Print view) when no Word template is uploaded.
                To use this HTML template for DOCX generation, first remove the uploaded Word template on the
                <strong>Upload Word Template</strong> tab.
            </div>
            <?php endif; ?>

            <form method="POST" id="templateForm">

                <div class="row g-3">
                    <div class="col-lg-9">

                        <!-- Document Editor -->
                        <div class="admin-card mb-3">
                            <div class="card-header">
                                <h5><i class="bi bi-file-earmark-word"></i> HTML Document Template</h5>
                                <span class="text-muted small">Edit your full document — add headers, images, tables, and use <code>{{ placeholder }}</code> variables</span>
                            </div>
                            <div class="card-body p-0">
                                <textarea id="template_content" name="template_content"><?= htmlspecialchars($starter_template) ?></textarea>
                            </div>
                        </div>

                    </div>

                    <!-- Right: Placeholders (HTML editor) -->
                    <div class="col-lg-3">
                        <div class="admin-card" style="position:sticky;top:80px;">
                            <div class="card-header">
                                <h5><i class="bi bi-braces"></i> Placeholders</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">Click to insert into the document at the cursor position.</p>
                                <?php foreach ($common_placeholders as $key => $label): ?>
                                <div class="mb-1">
                                    <span class="placeholder-tag" onclick="insertPlaceholder(<?= htmlspecialchars(json_encode('{{ ' . $key . ' }}'), ENT_QUOTES) ?>)" title="<?= htmlspecialchars($label) ?>">
                                        {{ <?= htmlspecialchars($key) ?> }}
                                    </span>
                                </div>
                                <?php endforeach; ?>

                                <?php if (!empty($form_fields)): ?>
                                <hr>
                                <p class="text-muted small mb-2">Type-specific fields:</p>
                                <?php foreach ($form_fields as $ff): ?>
                                <div class="mb-1">
                                    <span class="placeholder-tag field" onclick="insertPlaceholder(<?= htmlspecialchars(json_encode('{{ ' . $ff['name'] . ' }}'), ENT_QUOTES) ?>)" title="<?= htmlspecialchars($ff['label']) ?>">
                                        {{ <?= htmlspecialchars($ff['name']) ?> }}
                                    </span>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="card-body" style="border-top:1px solid var(--border-color);padding-top:16px;">
                                <button type="submit" class="btn-admin-gold w-100 justify-content-center">
                                    <i class="bi bi-save"></i> Save HTML Template
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </form>

        </div><!-- /tab-html -->

    </div><!-- /tab-content -->

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background:#f5f5f5;padding:32px;">
                    <div id="previewContent" style="background:#fff;padding:1in 1in 0.75in;width:8.5in;margin:0 auto;font-family:'Times New Roman',serif;font-size:12pt;min-height:11in;box-shadow:0 2px 20px rgba(0,0,0,0.15);"></div>
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
<script>
// Shared font configuration
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

// Word-like document editor
tinymce.init({
    selector: '#template_content',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks visualchars code fullscreen insertdatetime media table pagebreak nonbreaking directionality charmap wordcount quickbars help',
    menubar: 'file edit view insert format table tools help',
    toolbar: [
        'undo redo | fontfamily fontsize blocks | bold italic underline strikethrough | forecolor backcolor | removeformat',
        'alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent | blockquote | subscript superscript',
        'table link image charmap | pagebreak nonbreaking hr | searchreplace | code fullscreen'
    ],
    toolbar_mode: 'wrap',
    height: 700,
    min_height: 500,
    resize: true,
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
    // Enable resize handles on selected images (click-and-drag corners to resize)
    object_resizing: true,
    // Allow dragging and dropping images into the editor
    paste_data_images: true,
    images_upload_handler: function (blobInfo, progress) {
        return new Promise(function (resolve) {
            // Convert to base64 data URL for inline embedding
            const reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.readAsDataURL(blobInfo.blob());
        });
    },
    // Advanced tab in image dialog: float/alignment, dimensions, spacing
    image_advtab: true,
    image_title: true,
    // Named positioning classes available in the image dialog Style dropdown
    image_class_list: [
        { title: 'None',         value: '' },
        { title: 'Float Left',   value: 'img-float-left' },
        { title: 'Float Right',  value: 'img-float-right' },
        { title: 'Centered',     value: 'img-center' },
    ],
    // Quick inline toolbar shown when an image is selected
    quickbars_selection_toolbar: 'alignleft aligncenter alignright | rotateleft rotateright | imageoptions',
    quickbars_insert_toolbar: false,
    // Show a full document page in the editing area
    content_style: [
        'body {',
        '  font-family: "Times New Roman", serif;',
        '  font-size: 12pt;',
        '  line-height: 1.8;',
        '  color: #000;',
        '  background: #e8e8e8;',
        '  padding: 32px;',
        '}',
        '.mce-content-body {',
        '  background: #fff;',
        '  max-width: 816px;',
        '  margin: 0 auto;',
        '  padding: 96px 96px 72px;',
        '  box-shadow: 0 2px 20px rgba(0,0,0,0.2);',
        '  min-height: 1056px;',
        '}',
        'table, td, th { border: 1px solid #999; padding: 4px 8px; }',
        'img { max-width: 100%; height: auto; }',
        'img.img-float-left { float: left; margin: 0 16px 8px 0; }',
        'img.img-float-right { float: right; margin: 0 0 8px 16px; }',
        'img.img-center { display: block; margin: 8px auto; float: none; }',
        'p { margin: 0 0 10pt; }',
    ].join(''),
    setup: function(editor) {
        editor.on('init', function() {
            // Apply page styles to the body element inside the iframe
            const body = editor.getBody();
            body.style.background = '#fff';
            body.style.maxWidth = '816px';
            body.style.margin = '0 auto';
            body.style.padding = '96px 96px 72px';
            body.style.boxShadow = '0 2px 20px rgba(0,0,0,0.2)';
            body.style.minHeight = '1056px';
            editor.getDoc().body.parentElement.style.background = '#e8e8e8';
        });
    },
});

// Insert placeholder at the current cursor position in TinyMCE
function insertPlaceholder(text) {
    const editor = tinymce.get('template_content');
    if (editor) {
        editor.focus();
        editor.insertContent(text);
    } else {
        copyToClipboard(text);
    }
}

// Copy text to clipboard and show a toast confirmation
function copyToClipboard(text) {
    const fallback = () => {
        const el = document.createElement('input');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).catch(fallback);
    } else {
        fallback();
    }
    showToast('Copied: ' + text);
}

function showToast(msg) {
    const toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#1a3a6b;color:#fff;padding:10px 18px;border-radius:8px;font-size:0.85rem;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2200);
}

// Preview: show filled content in modal (HTML editor only)
function previewTemplate() {
    const editor = tinymce.get('template_content');
    let content = editor ? editor.getContent() : document.getElementById('template_content').value;

    // Replace sample placeholder values for preview
    content = content
        .replace(/\{\{\s*requester_name\s*\}\}/g, 'Juan Dela Cruz')
        .replace(/\{\{\s*requester_position\s*\}\}/g, 'Assistant Professor II')
        .replace(/\{\{\s*requester_department\s*\}\}/g, 'College of Engineering')
        .replace(/\{\{\s*purpose\s*\}\}/g, 'For loan application')
        .replace(/\{\{\s*tracking_number\s*\}\}/g, 'CAPSU-20240101-XXXXX')
        .replace(/\{\{\s*current_date\s*\}\}/g, new Date().toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'}))
        .replace(/\{\{\s*[^}]+?\s*\}\}/g, '[Sample Data]');

    document.getElementById('previewContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

// Sync TinyMCE content to the textarea before form submit
document.getElementById('templateForm').addEventListener('submit', function() {
    tinymce.triggerSave();
});

// Show/hide the "Preview HTML" button based on active tab
document.getElementById('tab-html-btn').addEventListener('shown.bs.tab', function() {
    document.getElementById('previewBtn').style.display = '';
});
document.getElementById('tab-word-btn').addEventListener('shown.bs.tab', function() {
    document.getElementById('previewBtn').style.display = 'none';
});
// Initial state
(function() {
    const defaultTab = <?= json_encode($default_tab) ?>;
    if (defaultTab !== 'html') {
        document.getElementById('previewBtn').style.display = 'none';
    }
})();
</script>
</body>
</html>
