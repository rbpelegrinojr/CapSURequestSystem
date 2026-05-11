<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: requests');
    exit;
}

$request = get_request_with_type($id);
if (!$request) {
    header('Location: requests');
    exit;
}

$additional_data = json_decode($request['additional_data'] ?? '{}', true) ?: [];

$db = get_db();
$stmt = $db->prepare('SELECT * FROM document_templates WHERE request_type_id = ?');
$stmt->execute([$request['request_type_id']]);
$template = $stmt->fetch();

$uni_name    = get_setting('university_name') ?: 'Capiz State University';
$uni_address = get_setting('university_address') ?: 'Fuentes Drive, Roxas City, Capiz';
$uni_phone   = get_setting('university_phone') ?: '(036) 620-0367';
$uni_email   = get_setting('university_email') ?: 'info@capsu.edu.ph';
$letterhead  = get_setting('letterhead_html') ?: '';
$global_footer = get_setting('footer_html') ?: '';
$signatories = get_active_signatories();

$tracking  = $request['tracking_number'];
$filename  = 'CapSU_' . str_replace('-', '', $tracking) . '_' . date('Ymd') . '.docx';

// ── PATH A: Use uploaded Word (.docx) template ────────────────────────────
$docx_tpl_path = '';
if (!empty($template['template_docx_path'])) {
    $candidate = __DIR__ . '/docx_templates/' . basename($template['template_docx_path']);
    if (file_exists($candidate)) {
        $docx_tpl_path = $candidate;
    }
}

if ($docx_tpl_path !== '') {
    // Copy template to a temp file so we can edit it without touching the original
    $out_path = sys_get_temp_dir() . '/capsu_filled_' . $tracking . '_' . time() . '.docx';
    if (!copy($docx_tpl_path, $out_path)) {
        die('Could not create temporary DOCX file.');
    }

    $zip = new ZipArchive();
    if ($zip->open($out_path) !== true) {
        @unlink($out_path);
        die('Could not open DOCX template.');
    }

    // Replace placeholders in all relevant XML parts
    $parts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (preg_match('#^word/(document|header\d*|footer\d*|endnotes|footnotes)\.xml$#', $name)) {
            $parts[] = $name;
        }
    }

    foreach ($parts as $part) {
        $xml = $zip->getFromName($part);
        if ($xml !== false) {
            $xml = fill_template_for_xml($xml, $request, $additional_data);
            $zip->addFromString($part, $xml);
        }
    }

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($out_path));
    header('Cache-Control: private, no-cache, no-store');
    header('Pragma: no-cache');
    readfile($out_path);
    @unlink($out_path);
    exit;
}

// ── PATH B: Generate DOCX from HTML template (original behaviour) ─────────

// Fill template
$template_content = $template['template_content'] ?? '';
$filled_content   = fill_template($template_content, $request, $additional_data);

// Strip HTML tags for plain text in DOCX (basic conversion)
function html_to_docx_xml($html) {
    // Convert basic HTML to Word XML-compatible text
    $html = strip_tags($html, '<strong><b><em><i><u><p><br><h1><h2><h3><h4><h5><h6>');
    $html = preg_replace('/<strong[^>]*>(.*?)<\/strong>/is', '<w:b/>$1<w:b/>', $html);
    $html = preg_replace('/<b[^>]*>(.*?)<\/b>/is', '<w:b/>$1<w:b/>', $html);
    $html = preg_replace('/<em[^>]*>(.*?)<\/em>/is', '<w:i/>$1<w:i/>', $html);
    $html = preg_replace('/<i[^>]*>(.*?)<\/i>/is', '<w:i/>$1<w:i/>', $html);
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $html = strip_tags($html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = trim($html);
    return $html;
}

function text_to_docx_paragraphs($text, $indent = true) {
    $xml = '';
    $paragraphs = preg_split('/\n{2,}/', $text);
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if ($para === '') continue;
        $lines = explode("\n", $para);
        $text_content = implode(' ', $lines);
        $text_content = xmlspecialchars($text_content);
        $ind = $indent ? '<w:ind w:firstLineChars="720" w:firstLine="720"/>' : '';
        $xml .= <<<EOX
        <w:p>
          <w:pPr>
            <w:jc w:val="both"/>
            {$ind}
            <w:spacing w:after="120" w:line="360" w:lineRule="auto"/>
          </w:pPr>
          <w:r>
            <w:rPr><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>
            <w:t xml:space="preserve">{$text_content}</w:t>
          </w:r>
        </w:p>
EOX;
    }
    return $xml;
}

function xmlspecialchars($str) {
    return htmlspecialchars($str, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

// Build document XML
$doc_title = strtoupper($request['type_name']);
$filled_plain = html_to_docx_xml($filled_content);
$date_str = date('F d, Y');

// Signatory XML rows
$sig_xml = '';
if (!empty($signatories)) {
    $col_width = (int)(8640 / count($signatories));
    $sig_cells = '';
    foreach ($signatories as $sig) {
        $sig_name  = xmlspecialchars($sig['name']);
        $sig_title = xmlspecialchars($sig['title']);
        $sig_cells .= <<<EOX
        <w:tc>
          <w:tcPr><w:tcW w:w="{$col_width}" w:type="dxa"/>
            <w:tcBorders><w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/></w:tcBorders>
          </w:tcPr>
          <w:p>
            <w:pPr><w:jc w:val="center"/><w:spacing w:before="0" w:after="40"/></w:pPr>
            <w:r><w:rPr><w:b/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr>
              <w:t>{$sig_name}</w:t>
            </w:r>
          </w:p>
          <w:p>
            <w:pPr><w:jc w:val="center"/><w:spacing w:before="0" w:after="0"/></w:pPr>
            <w:r><w:rPr><w:sz w:val="20"/><w:szCs w:val="20"/><w:color w:val="444444"/></w:rPr>
              <w:t>{$sig_title}</w:t>
            </w:r>
          </w:p>
        </w:tc>
EOX;
    }
    $sig_xml = '<w:tr>' . $sig_cells . '</w:tr>';
}

$content_paragraphs = text_to_docx_paragraphs($filled_plain, true);
$uni_name_xml    = xmlspecialchars($uni_name);
$uni_address_xml = xmlspecialchars($uni_address);
$uni_phone_xml   = xmlspecialchars($uni_phone);
$uni_email_xml   = xmlspecialchars($uni_email);
$date_xml        = xmlspecialchars($date_str);
$tracking_xml    = xmlspecialchars($tracking);
$type_xml        = xmlspecialchars($request['type_name']);
$req_name_xml    = xmlspecialchars($request['requester_name']);

$document_xml = <<<EOX
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"
  xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  mc:Ignorable="w14 wp14">
<w:body>

  <!-- Letterhead -->
  <w:p>
    <w:pPr><w:jc w:val="center"/><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr>
    <w:r><w:rPr><w:b/><w:sz w:val="36"/><w:szCs w:val="36"/><w:color w:val="1a3a6b"/></w:rPr>
      <w:t>{$uni_name_xml}</w:t>
    </w:r>
  </w:p>
  <w:p>
    <w:pPr><w:jc w:val="center"/><w:spacing w:after="0"/></w:pPr>
    <w:r><w:rPr><w:sz w:val="20"/><w:szCs w:val="20"/><w:color w:val="555555"/></w:rPr>
      <w:t>{$uni_address_xml}</w:t>
    </w:r>
  </w:p>
  <w:p>
    <w:pPr><w:jc w:val="center"/><w:spacing w:after="120"/></w:pPr>
    <w:r><w:rPr><w:sz w:val="20"/><w:szCs w:val="20"/><w:color w:val="555555"/></w:rPr>
      <w:t>Tel: {$uni_phone_xml} | Email: {$uni_email_xml}</w:t>
    </w:r>
  </w:p>

  <!-- Title -->
  <w:p>
    <w:pPr><w:jc w:val="center"/><w:spacing w:before="240" w:after="240"/></w:pPr>
    <w:r><w:rPr><w:b/><w:sz w:val="28"/><w:szCs w:val="28"/><w:color w:val="1a2a4a"/><w:u w:val="single"/></w:rPr>
      <w:t>{$doc_title}</w:t>
    </w:r>
  </w:p>

  <!-- Date -->
  <w:p>
    <w:pPr><w:jc w:val="right"/><w:spacing w:after="120"/></w:pPr>
    <w:r><w:rPr><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>
      <w:t>{$date_xml}</w:t>
    </w:r>
  </w:p>

  <!-- Salutation -->
  <w:p>
    <w:pPr><w:spacing w:after="160"/></w:pPr>
    <w:r><w:rPr><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>
      <w:t>TO WHOM IT MAY CONCERN:</w:t>
    </w:r>
  </w:p>

  <!-- Body -->
  {$content_paragraphs}

  <!-- Signatory -->
  <w:p><w:pPr><w:spacing w:before="480" w:after="0"/></w:pPr></w:p>
  <w:tbl>
    <w:tblPr>
      <w:tblW w:w="8640" w:type="dxa"/>
      <w:tblBorders>
        <w:insideH w:val="none"/>
        <w:insideV w:val="none"/>
      </w:tblBorders>
      <w:tblLayout w:type="fixed"/>
    </w:tblPr>
    <w:tblGrid>
      <w:gridCol w:w="8640"/>
    </w:tblGrid>
    {$sig_xml}
  </w:tbl>

  <!-- Footer note -->
  <w:p><w:pPr><w:spacing w:before="480" w:after="0"/></w:pPr></w:p>
  <w:p>
    <w:pPr><w:jc w:val="center"/><w:spacing w:before="240"/><w:pBdr><w:top w:val="single" w:sz="4" w:space="1" w:color="cccccc"/></w:pBdr></w:pPr>
    <w:r><w:rPr><w:sz w:val="18"/><w:szCs w:val="18"/><w:color w:val="888888"/></w:rPr>
      <w:t>Tracking No.: {$tracking_xml} | Request Type: {$type_xml} | Generated: {$date_xml}</w:t>
    </w:r>
  </w:p>

  <w:sectPr>
    <w:pgSz w:w="12240" w:h="15840"/>
    <w:pgMar w:top="1440" w:right="1080" w:bottom="1080" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/>
  </w:sectPr>

</w:body>
</w:document>
EOX;

// Build .docx using ZipArchive
$relationships_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';

$word_relationships_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

$styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>
        <w:sz w:val="24"/>
        <w:szCs w:val="24"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:spacing w:after="200" w:line="360" w:lineRule="auto"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
</w:styles>';

$content_types_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml"   ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>';

// Create docx in memory
$zip_path = sys_get_temp_dir() . '/capsu_' . $tracking . '_' . time() . '.docx';
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Failed to create DOCX file.');
}

$zip->addFromString('[Content_Types].xml',          $content_types_xml);
$zip->addFromString('_rels/.rels',                  $relationships_xml);
$zip->addFromString('word/document.xml',             $document_xml);
$zip->addFromString('word/styles.xml',               $styles_xml);
$zip->addFromString('word/_rels/document.xml.rels',  $word_relationships_xml);
$zip->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($zip_path));
header('Cache-Control: private, no-cache, no-store');
header('Pragma: no-cache');

readfile($zip_path);
@unlink($zip_path);
exit;
