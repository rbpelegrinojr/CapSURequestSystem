<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requests');
    exit;
}

$action     = $_POST['action'] ?? '';
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

if (!$request_id) {
    header('Location: requests');
    exit;
}

$db = get_db();
$request = get_request_with_type($request_id);

if (!$request) {
    header('Location: requests');
    exit;
}

if ($action === 'update_status') {
    $valid_statuses = ['pending', 'processing', 'approved', 'rejected', 'completed'];
    $new_status     = $_POST['status'] ?? '';
    $admin_notes    = trim($_POST['admin_notes'] ?? '');
    $notify         = !empty($_POST['notify_requester']);

    if (!in_array($new_status, $valid_statuses)) {
        header('Location: view_request?id=' . $request_id . '&error=' . urlencode('Invalid status.'));
        exit;
    }

    $stmt = $db->prepare('UPDATE requests SET status = ?, admin_notes = ? WHERE id = ?');
    $stmt->execute([$new_status, $admin_notes, $request_id]);

    if ($notify) {
        $uni_name = get_setting('university_name') ?: 'Capiz State University';

        $status_labels = [
            'pending'    => 'Pending Review',
            'processing' => 'Being Processed',
            'approved'   => 'Approved',
            'rejected'   => 'Rejected',
            'completed'  => 'Completed',
        ];

        $status_colors = [
            'pending'    => '#856404',
            'processing' => '#084298',
            'approved'   => '#0f5132',
            'rejected'   => '#842029',
            'completed'  => '#1a3a6b',
        ];

        $status_bg = [
            'pending'    => '#fff3cd',
            'processing' => '#cfe2ff',
            'approved'   => '#d1e7dd',
            'rejected'   => '#f8d7da',
            'completed'  => '#d0d9ef',
        ];

        $label  = $status_labels[$new_status] ?? ucfirst($new_status);
        $color  = $status_colors[$new_status] ?? '#333';
        $bg     = $status_bg[$new_status] ?? '#f4f6fb';

        $subject = "Request Update [{$request['tracking_number']}] — Status: {$label}";
        $body = "
<p>Dear <strong>" . htmlspecialchars($request['requester_name']) . "</strong>,</p>
<p>Your document request has been updated. Here are the details:</p>
<table style='border-collapse:collapse;width:100%;font-size:0.9rem;margin:16px 0;'>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;width:160px;'>Tracking No.</td>
        <td style='padding:8px 12px;border-bottom:1px solid #eee;font-family:Courier New,monospace;font-weight:700;color:#1a3a6b;'>" . htmlspecialchars($request['tracking_number']) . "</td></tr>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>Request Type</td>
        <td style='padding:8px 12px;border-bottom:1px solid #eee;'>" . htmlspecialchars($request['type_name']) . "</td></tr>
    <tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>New Status</td>
        <td style='padding:8px 12px;border-bottom:1px solid #eee;'>
            <span style='background:{$bg};color:{$color};padding:3px 12px;border-radius:12px;font-weight:700;font-size:0.85rem;'>{$label}</span>
        </td></tr>
    " . ($admin_notes ? "<tr><td style='padding:8px 12px;background:#f4f6fb;font-weight:700;'>Remarks</td>
        <td style='padding:8px 12px;'>" . htmlspecialchars($admin_notes) . "</td></tr>" : '') . "
</table>
<p>You may track your request at any time using your tracking number.</p>
<p>If you have any questions, please contact the Human Resource Management Office.</p>
<p>Thank you,<br><strong>" . htmlspecialchars($uni_name) . "</strong></p>
";
        $sent = send_system_email(
            $request['requester_email'],
            $request['requester_name'],
            $subject,
            $body
        );
        log_email($request_id, $request['requester_email'], $subject, $sent ? 'sent' : 'failed');
    }

    header('Location: view_request?id=' . $request_id . '&success=' . urlencode('Status updated successfully.'));
    exit;
}

header('Location: view_request?id=' . $request_id);
exit;
