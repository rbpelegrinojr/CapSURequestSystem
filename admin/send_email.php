<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requests');
    exit;
}

$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$subject    = trim($_POST['subject'] ?? '');
$message    = trim($_POST['message'] ?? '');

if (!$request_id || empty($subject) || empty($message)) {
    header('Location: requests');
    exit;
}

$request = get_request_with_type($request_id);
if (!$request) {
    header('Location: requests');
    exit;
}

$uni_name = get_setting('university_name') ?: 'Capiz State University';

$body = "
<p>Dear <strong>" . htmlspecialchars($request['requester_name']) . "</strong>,</p>
" . nl2br(htmlspecialchars($message)) . "
<hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
<p style='font-size:0.85rem;color:#777;'>
    <strong>Reference:</strong> Tracking No. " . htmlspecialchars($request['tracking_number']) . "<br>
    <strong>Request Type:</strong> " . htmlspecialchars($request['type_name']) . "
</p>
<p>Thank you,<br><strong>" . htmlspecialchars($uni_name) . "</strong></p>
";

$sent = send_system_email(
    $request['requester_email'],
    $request['requester_name'],
    $subject,
    $body
);
log_email($request_id, $request['requester_email'], $subject, $sent ? 'sent' : 'failed');

if ($sent) {
    header('Location: view_request?id=' . $request_id . '&success=' . urlencode('Email sent successfully.'));
} else {
    header('Location: view_request?id=' . $request_id . '&error=' . urlencode('Email could not be sent. Check your mail configuration.'));
}
exit;
