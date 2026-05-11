<?php
require_once __DIR__ . '/db.php';

function get_setting($key) {
    $db = get_db();
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : null;
}

function generate_tracking_number() {
    $db = get_db();
    $date = date('Ymd');
    do {
        $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
        $tracking = 'CAPSU-' . $date . '-' . $rand;
        $stmt = $db->prepare('SELECT id FROM requests WHERE tracking_number = ?');
        $stmt->execute([$tracking]);
    } while ($stmt->fetch());
    return $tracking;
}

function send_system_email($to, $to_name, $subject, $body) {
    $from_name = get_setting('university_name') ?: 'CapSU Request System';
    $from_email = get_setting('admin_email') ?: DEFAULT_ADMIN_EMAIL;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $full_body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;">';
    $full_body .= '<div style="background:#1a2a4a;padding:20px;text-align:center;">';
    $full_body .= '<h2 style="color:#c9a84c;margin:0;">' . htmlspecialchars($from_name) . '</h2>';
    $full_body .= '</div>';
    $full_body .= '<div style="padding:20px;border:1px solid #ddd;">' . $body . '</div>';
    $full_body .= '<div style="background:#f5f5f5;padding:10px;text-align:center;font-size:12px;color:#777;">';
    $full_body .= 'This is an automated message. Please do not reply directly to this email.</div>';
    $full_body .= '</body></html>';

    $to_header = $to_name ? "=?UTF-8?B?" . base64_encode($to_name) . "?= <{$to}>" : $to;
    return @mail($to_header, '=?UTF-8?B?' . base64_encode($subject) . '?=', $full_body, $headers);
}

function get_all_request_types() {
    $db = get_db();
    $stmt = $db->query('SELECT * FROM request_types WHERE is_active = 1 ORDER BY id ASC');
    return $stmt->fetchAll();
}

function get_request_by_tracking($tracking) {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT r.*, rt.name AS type_name, rt.code AS type_code
         FROM requests r
         JOIN request_types rt ON r.request_type_id = rt.id
         WHERE r.tracking_number = ?'
    );
    $stmt->execute([strtoupper(trim($tracking))]);
    return $stmt->fetch();
}

function get_request_types_with_templates() {
    $db = get_db();
    $stmt = $db->query(
        'SELECT rt.*, dt.id AS template_id, dt.template_content,
                dt.header_html, dt.footer_html, dt.layout_json,
                dt.template_docx_path
         FROM request_types rt
         LEFT JOIN document_templates dt ON rt.id = dt.request_type_id
         ORDER BY rt.id ASC'
    );
    return $stmt->fetchAll();
}

function format_status_badge($status) {
    $map = [
        'pending'    => ['bg' => '#ffc107', 'text' => '#000', 'label' => 'Pending'],
        'processing' => ['bg' => '#0d6efd', 'text' => '#fff', 'label' => 'Processing'],
        'approved'   => ['bg' => '#198754', 'text' => '#fff', 'label' => 'Approved'],
        'rejected'   => ['bg' => '#dc3545', 'text' => '#fff', 'label' => 'Rejected'],
        'completed'  => ['bg' => '#1a3a6b', 'text' => '#fff', 'label' => 'Completed'],
    ];
    $s = $map[$status] ?? ['bg' => '#6c757d', 'text' => '#fff', 'label' => ucfirst($status)];
    return '<span style="background:' . $s['bg'] . ';color:' . $s['text'] . ';padding:3px 10px;border-radius:12px;font-size:0.82em;font-weight:600;">'
        . htmlspecialchars($s['label']) . '</span>';
}

function format_date($date) {
    if (!$date) return '—';
    return date('F d, Y g:i A', strtotime($date));
}

function get_stats() {
    $db = get_db();
    $stmt = $db->query('SELECT status, COUNT(*) AS cnt FROM requests GROUP BY status');
    $rows = $stmt->fetchAll();
    $stats = ['pending' => 0, 'processing' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0, 'total' => 0];
    foreach ($rows as $row) {
        $stats[$row['status']] = (int)$row['cnt'];
        $stats['total'] += (int)$row['cnt'];
    }
    return $stats;
}

function fill_template($template_content, $request_data, $additional_data = []) {
    $sex = $request_data['requester_sex'] ?? '';
    $salutation = ($sex === 'Male') ? 'MR.' : (($sex === 'Female') ? 'Ms' : '');
    $replacements = [
        'requester_name'       => htmlspecialchars($request_data['requester_name'] ?? ''),
        'requester_firstname'  => htmlspecialchars($request_data['requester_firstname'] ?? ''),
        'requester_middlename' => htmlspecialchars($request_data['requester_middlename'] ?? ''),
        'requester_lastname'   => htmlspecialchars($request_data['requester_lastname'] ?? ''),
        'requester_salutation' => htmlspecialchars($salutation),
        'requester_email'      => htmlspecialchars($request_data['requester_email'] ?? ''),
        'requester_phone'      => htmlspecialchars($request_data['requester_phone'] ?? ''),
        'requester_department' => htmlspecialchars($request_data['requester_department'] ?? ''),
        'requester_position'   => htmlspecialchars($request_data['requester_position'] ?? ''),
        'purpose'              => htmlspecialchars($request_data['purpose'] ?? ''),
        'tracking_number'      => htmlspecialchars($request_data['tracking_number'] ?? ''),
        'submitted_at'         => format_date($request_data['submitted_at'] ?? ''),
        'current_date'         => date('j<\s\u\p>S</\s\u\p> \d\a\y \o\f F, Y'),
        'type_name'            => htmlspecialchars($request_data['type_name'] ?? ''),
    ];

    if (is_string($additional_data)) {
        $additional_data = json_decode($additional_data, true) ?: [];
    }

    foreach ((array)$additional_data as $key => $value) {
        $replacements[trim($key)] = htmlspecialchars((string)$value);
    }

    // Support both {{ name }} (with spaces) and {{name}} (without spaces)
    return preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/', function ($m) use ($replacements) {
        $key = trim($m[1]);
        return array_key_exists($key, $replacements) ? $replacements[$key] : $m[0];
    }, $template_content);
}

function get_request_with_type($id) {
    $db = get_db();
    $stmt = $db->prepare(
        'SELECT r.*, rt.name AS type_name, rt.code AS type_code, rt.form_fields
         FROM requests r
         JOIN request_types rt ON r.request_type_id = rt.id
         WHERE r.id = ?'
    );
    $stmt->execute([(int)$id]);
    return $stmt->fetch();
}

function get_active_signatories() {
    $db = get_db();
    $stmt = $db->query('SELECT * FROM signatories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll();
}

function log_email($request_id, $recipient_email, $subject, $status = 'sent') {
    $db = get_db();
    $stmt = $db->prepare('INSERT INTO email_logs (request_id, recipient_email, subject, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$request_id, $recipient_email, $subject, $status]);
}

function sanitize_html_output($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Build the flat key→value replacements map for a request (no HTML/XML escaping).
 * Used by docx template filling where each caller handles its own escaping.
 */
function build_template_replacements($request_data, $additional_data = []) {
    $sex = $request_data['requester_sex'] ?? '';
    $salutation = ($sex === 'Male') ? 'MR.' : (($sex === 'Female') ? 'Ms' : '');
    $replacements = [
        'requester_name'       => $request_data['requester_name'] ?? '',
        'requester_firstname'  => $request_data['requester_firstname'] ?? '',
        'requester_middlename' => $request_data['requester_middlename'] ?? '',
        'requester_lastname'   => $request_data['requester_lastname'] ?? '',
        'requester_salutation' => $salutation,
        'requester_email'      => $request_data['requester_email'] ?? '',
        'requester_phone'      => $request_data['requester_phone'] ?? '',
        'requester_department' => $request_data['requester_department'] ?? '',
        'requester_position'   => $request_data['requester_position'] ?? '',
        'purpose'              => $request_data['purpose'] ?? '',
        'tracking_number'      => $request_data['tracking_number'] ?? '',
        'submitted_at'         => format_date($request_data['submitted_at'] ?? ''),
        'current_date'         => date('jS \d\a\y \o\f F, Y'),
        'type_name'            => $request_data['type_name'] ?? '',
    ];

    if (is_string($additional_data)) {
        $additional_data = json_decode($additional_data, true) ?: [];
    }

    foreach ((array)$additional_data as $key => $value) {
        $replacements[trim($key)] = (string)$value;
    }

    return $replacements;
}

/**
 * Replace the {{current_date}} placeholder inside a single Word <w:r> run with
 * proper multi-run XML so that the ordinal suffix (st/nd/rd/th) is rendered as a
 * superscript.  Only handles the common case where the placeholder lives entirely
 * within one <w:r>/<w:t> element; the generic replacement in fill_template_for_xml
 * acts as a plain-text fallback for the (rare) split-run case.
 */
function replace_date_placeholder_with_superscript($xml, $date_str) {
    // Match a full <w:r> whose <w:t> contains {{current_date}}, capturing:
    //   $m[1] – optional attributes on <w:r>
    //   $m[2] – full <w:rPr>…</w:rPr> block (empty string when absent)
    //   $m[3] – attributes on <w:t> (e.g. xml:space="preserve")
    //   $m[4] – text before the placeholder (already XML-encoded)
    //   $m[5] – text after the placeholder  (already XML-encoded)
    $pattern = '/<w:r(\s[^>]*)?>(<w:rPr>[\s\S]*?<\/w:rPr>)?\s*<w:t([^>]*?)>(.*?)\{\{current_date\}\}(.*?)<\/w:t>\s*<\/w:r>/s';

    return preg_replace_callback($pattern, function ($m) use ($date_str) {
        $r_attr = $m[1];          // may be empty
        $rPr    = $m[2];          // full <w:rPr>…</w:rPr> block or ''
        $pre    = $m[4];          // already XML-encoded, keep as-is
        $post   = $m[5];          // already XML-encoded, keep as-is

        // Build the run-properties for the superscript run: inherit existing
        // formatting and add <w:vertAlign w:val="superscript"/>.
        if ($rPr !== '') {
            $supRPr = preg_replace('/<\/w:rPr>\s*$/', '<w:vertAlign w:val="superscript"/></w:rPr>', $rPr);
        } else {
            $supRPr = '<w:rPr><w:vertAlign w:val="superscript"/></w:rPr>';
        }

        $open = '<w:r' . $r_attr . '>';

        // Split "11th day of May, 2026" → day="11", ord="th", rest=" day of May, 2026".
        // PHP date('S') always returns lowercase (st/nd/rd/th), so no i flag needed.
        if (preg_match('/^(\d+)(st|nd|rd|th)(.*)$/', $date_str, $dp)) {
            $day  = htmlspecialchars($dp[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $ord  = htmlspecialchars($dp[2], ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $rest = htmlspecialchars($dp[3], ENT_XML1 | ENT_COMPAT, 'UTF-8');

            $out = '';
            if ($pre !== '') {
                $out .= $open . $rPr . '<w:t xml:space="preserve">' . $pre . '</w:t></w:r>';
            }
            $out .= $open . $rPr    . '<w:t xml:space="preserve">' . $day . '</w:t></w:r>';
            // xml:space="preserve" is intentionally omitted on the ordinal run:
            // the ordinal ("th", "st", etc.) contains no leading/trailing whitespace.
            $out .= $open . $supRPr . '<w:t>' . $ord . '</w:t></w:r>';
            $out .= $open . $rPr    . '<w:t xml:space="preserve">' . $rest . $post . '</w:t></w:r>';
            return $out;
        }

        // Fallback: no recognisable ordinal – plain text replacement.
        $escaped = htmlspecialchars($date_str, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        return $open . $rPr . '<w:t xml:space="preserve">' . $pre . $escaped . $post . '</w:t></w:r>';
    }, $xml);
}

/**
 * Fill a Word XML part (document.xml, header*.xml, footer*.xml) with request data.
 * Values are XML-escaped. Handles the common Word behaviour of splitting a
 * {{placeholder}} across multiple <w:r> runs: since XML tag names and attributes
 * cannot contain { or }, the pattern [^{}]* safely spans both plain text and any
 * XML markup that may appear inside the braces.
 */
function fill_template_for_xml($xml_content, $request_data, $additional_data = []) {
    $replacements = build_template_replacements($request_data, $additional_data);

    // Replace {{current_date}} with proper superscript Word XML when the placeholder
    // is within a single <w:r> run (the typical case).  The key is intentionally
    // kept in $replacements so the generic loop below can still handle the (common)
    // case where Word has split the placeholder across multiple runs – in that
    // situation the superscript regex does not match, but the generic fallback will
    // replace the split span with a plain-text date.  There is no double-replacement
    // risk: once replace_date_placeholder_with_superscript succeeds the literal
    // {{current_date}} text is gone from the XML and the generic loop won't find it.
    if (isset($replacements['current_date'])) {
        $xml_content = replace_date_placeholder_with_superscript($xml_content, $replacements['current_date']);
    }

    // Match {{ ... }} even when Word has split the placeholder text across several
    // runs (e.g. {{requester_ in one <w:r> and name}} in the next). Strip any XML
    // tags from the captured inner text to recover the plain-text key, then replace
    // the entire matched span (including embedded XML) with the escaped value.
    return preg_replace_callback('/\{\{([^{}]*)\}\}/s', function ($m) use ($replacements) {
        $key = trim(preg_replace('/<[^>]+>/', '', $m[1]));
        if (array_key_exists($key, $replacements)) {
            return htmlspecialchars($replacements[$key], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }
        return $m[0];
    }, $xml_content);
}
