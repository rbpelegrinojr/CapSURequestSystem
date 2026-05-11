<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);
$action  = $_POST['action'] ?? 'upload';

if (!$type_id) {
    header('Location: templates.php');
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT id FROM request_types WHERE id = ?');
$stmt->execute([$type_id]);
if (!$stmt->fetch()) {
    header('Location: templates.php');
    exit;
}

$stmt2 = $db->prepare('SELECT id, template_docx_path FROM document_templates WHERE request_type_id = ?');
$stmt2->execute([$type_id]);
$template = $stmt2->fetch();

$redirect = 'template_editor.php?type_id=' . $type_id;

// ── Clear action ────────────────────────────────────────────────────────────
if ($action === 'clear') {
    if ($template && $template['template_docx_path']) {
        $file_path = __DIR__ . '/docx_templates/' . basename($template['template_docx_path']);
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        $db->prepare('UPDATE document_templates SET template_docx_path = NULL WHERE request_type_id = ?')
           ->execute([$type_id]);
    }
    header('Location: ' . $redirect . '&success=docx_cleared');
    exit;
}

// ── Upload action ────────────────────────────────────────────────────────────
if (!isset($_FILES['template_docx']) || $_FILES['template_docx']['error'] !== UPLOAD_ERR_OK) {
    $err = isset($_FILES['template_docx']) ? $_FILES['template_docx']['error'] : 'no_file';
    header('Location: ' . $redirect . '&error=upload_failed&code=' . $err);
    exit;
}

$file = $_FILES['template_docx'];

// Validate extension
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx') {
    header('Location: ' . $redirect . '&error=invalid_type');
    exit;
}

// Validate it is a real DOCX (ZIP containing word/document.xml)
$zip = new ZipArchive();
if ($zip->open($file['tmp_name']) !== true || $zip->locateName('word/document.xml') === false) {
    if ($zip instanceof ZipArchive) {
        @$zip->close();
    }
    header('Location: ' . $redirect . '&error=invalid_docx');
    exit;
}
$zip->close();

// Save to docx_templates/
$upload_dir = __DIR__ . '/docx_templates/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = 'template_' . $type_id . '.docx';
$dest     = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    header('Location: ' . $redirect . '&error=save_failed');
    exit;
}

// Persist path in DB
if ($template) {
    $db->prepare('UPDATE document_templates SET template_docx_path = ? WHERE request_type_id = ?')
       ->execute([$filename, $type_id]);
} else {
    $db->prepare(
        'INSERT INTO document_templates (request_type_id, template_content, template_docx_path) VALUES (?, ?, ?)'
    )->execute([$type_id, '', $filename]);
}

header('Location: ' . $redirect . '&success=docx_uploaded');
exit;
