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
$stmt = $db->prepare(
    'SELECT dt.template_docx_path, rt.code
     FROM document_templates dt
     JOIN request_types rt ON dt.request_type_id = rt.id
     WHERE dt.request_type_id = ?'
);
$stmt->execute([$type_id]);
$row = $stmt->fetch();

if (!$row || !$row['template_docx_path']) {
    header('Location: template_editor?type_id=' . $type_id . '&error=no_docx');
    exit;
}

$file_path = __DIR__ . '/docx_templates/' . basename($row['template_docx_path']);
if (!file_exists($file_path)) {
    header('Location: template_editor?type_id=' . $type_id . '&error=file_missing');
    exit;
}

$download_name = 'Template_' . preg_replace('/[^A-Z0-9_]/i', '', $row['code']) . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, no-cache, no-store');
header('Pragma: no-cache');
readfile($file_path);
exit;
