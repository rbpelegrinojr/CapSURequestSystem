<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index');
    exit;
}

// Validate CSRF-like: ensure required fields exist
$type_id    = filter_input(INPUT_POST, 'request_type_id', FILTER_VALIDATE_INT);
$type_code  = strtoupper(trim($_POST['type_code'] ?? ''));
$name       = trim($_POST['requester_name'] ?? '');
$email      = trim($_POST['requester_email'] ?? '');
$phone      = trim($_POST['requester_phone'] ?? '');
$department = trim($_POST['requester_department'] ?? '');
$position   = trim($_POST['requester_position'] ?? '');
$purpose    = trim($_POST['purpose'] ?? '');
$extra      = $_POST['extra'] ?? [];

$errors = [];

if (!$type_id) $errors[] = 'Invalid request type.';
if (empty($name)) $errors[] = 'Full name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (empty($department)) $errors[] = 'Department is required.';
if (empty($position)) $errors[] = 'Position is required.';
if (empty($purpose)) $errors[] = 'Purpose is required.';

// Validate type-specific required fields
$db = get_db();
if ($type_id) {
    $stmt = $db->prepare('SELECT * FROM request_types WHERE id = ? AND is_active = 1');
    $stmt->execute([$type_id]);
    $request_type = $stmt->fetch();
    if (!$request_type) {
        $errors[] = 'Invalid request type.';
    } else {
        $form_fields = json_decode($request_type['form_fields'] ?? '[]', true) ?: [];
        foreach ($form_fields as $field) {
            if (!empty($field['required']) && empty($extra[$field['name']])) {
                $errors[] = htmlspecialchars($field['label']) . ' is required.';
            }
        }
    }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = $_POST;
    header('Location: request_form?type=' . urlencode($type_code));
    exit;
}

// Sanitize extra fields
$safe_extra = [];
foreach ($extra as $k => $v) {
    $safe_extra[preg_replace('/[^a-z0-9_]/', '', $k)] = substr(trim($v), 0, 500);
}

$tracking = generate_tracking_number();

$stmt = $db->prepare(
    'INSERT INTO requests (tracking_number, request_type_id, requester_name, requester_email,
     requester_phone, requester_department, requester_position, purpose, additional_data, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $tracking,
    $type_id,
    $name,
    $email,
    $phone,
    $department,
    $position,
    $purpose,
    json_encode($safe_extra),
    'pending',
]);
$request_id = $db->lastInsertId();

$uni_name   = get_setting('university_name') ?: 'Capiz State University';
$admin_email = get_setting('admin_email') ?: DEFAULT_ADMIN_EMAIL;
$type_name  = $request_type['name'] ?? $type_code;

// Email to requester
$req_subject = "Request Received — Tracking No. {$tracking}";
$req_body = "
<p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
<p>We have received your request for a <strong>" . htmlspecialchars($type_name) . "</strong>. Please keep your tracking number for future reference.</p>
<div style='background:#f4f6fb;border-left:4px solid #c9a84c;padding:16px 20px;border-radius:6px;margin:20px 0;'>
    <p style='margin:0 0 4px;font-size:0.85rem;color:#555;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;'>Your Tracking Number</p>
    <p style='margin:0;font-family:Courier New,monospace;font-size:1.4rem;font-weight:700;color:#1a3a6b;letter-spacing:2px;'>{$tracking}</p>
</div>
<p><strong>Request Type:</strong> " . htmlspecialchars($type_name) . "<br>
<strong>Submitted:</strong> " . date('F d, Y g:i A') . "<br>
<strong>Status:</strong> Pending Review</p>
<p>You may track your request at any time by visiting our portal and entering your tracking number.</p>
<p>Processing typically takes <strong>3–5 business days</strong>. You will be notified by email when there are updates.</p>
<p>Thank you for using the " . htmlspecialchars($uni_name) . " Document Request System.</p>
";
$sent = send_system_email($email, $name, $req_subject, $req_body);
log_email($request_id, $email, $req_subject, $sent ? 'sent' : 'failed');

// Email to admin
$admin_subject = "New Request [{$tracking}] — " . htmlspecialchars($type_name);
$admin_body = "
<p>A new document request has been submitted.</p>
<table style='border-collapse:collapse;width:100%;font-size:0.9rem;'>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;width:160px;'>Tracking No.</td><td style='padding:8px 12px;border-bottom:1px solid #eee;font-family:Courier New,monospace;color:#1a3a6b;font-weight:700;'>{$tracking}</td></tr>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>Request Type</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($type_name) . "</td></tr>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>Name</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($name) . "</td></tr>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>Email</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($email) . "</td></tr>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>Department</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($department) . "</td></tr>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>Purpose</td><td style='padding:8px 12px;'>" . htmlspecialchars($purpose) . "</td></tr>
</table>
<p style='margin-top:20px;'><a href='" . htmlspecialchars(APP_URL) . "/admin/view_request?id={$request_id}' style='background:#1a3a6b;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;'>View Request in Admin Panel</a></p>
";
$sent_admin = send_system_email($admin_email, 'Admin', $admin_subject, $admin_body);
log_email($request_id, $admin_email, $admin_subject, $sent_admin ? 'sent' : 'failed');

header('Location: track_request?tracking=' . urlencode($tracking) . '&new=1');
exit;
