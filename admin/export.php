<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireAdmin();
require_once dirname(__DIR__) . '/config.php';

if (!hash_equals(csrfToken(), (string) ($_GET['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid export request.');
}

$scope = (string) ($_GET['scope'] ?? 'all');
$params = [];
$where = '';
$suffix = 'all';
if ($scope === 'range') {
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    $validDate = static function (string $date): bool {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    };
    if (!$validDate($from) || !$validDate($to) || $from > $to) {
        http_response_code(422);
        exit('Select a valid date range.');
    }
    $where = ' WHERE r.created_at >= :from AND r.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
    $params = ['from' => $from, 'to' => $to];
    $suffix = $from . '_to_' . $to;
} elseif ($scope !== 'all') {
    http_response_code(422);
    exit('Invalid export option.');
}

$statement = database()->prepare('SELECT r.* FROM registrations r' . $where . ' ORDER BY r.created_at DESC, r.id DESC');
$statement->execute($params);
$rows = $statement->fetchAll();

$family = [];
if ($rows !== []) {
    $ids = array_map(static fn(array $row): int => (int) $row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $members = database()->prepare("SELECT registration_id, member_type, name, age, marital_status FROM family_members WHERE registration_id IN ({$placeholders}) ORDER BY id");
    $members->execute($ids);
    foreach ($members->fetchAll() as $member) {
        $family[(int) $member['registration_id']][] = $member;
    }
}

$familyByType = [];
$maxSons = 0;
$maxDaughters = 0;
foreach ($rows as $row) {
    $registrationId = (int) $row['id'];
    $familyByType[$registrationId] = ['Son' => [], 'Daughter' => []];
    foreach ($family[$registrationId] ?? [] as $member) {
        $familyByType[$registrationId][$member['member_type']][] = $member;
    }
    $maxSons = max($maxSons, count($familyByType[$registrationId]['Son']));
    $maxDaughters = max($maxDaughters, count($familyByType[$registrationId]['Daughter']));
}

$headers = ['S.No.', 'Submitted At', 'Name', "Father's Name", 'Mobile', 'Email', 'House Number', 'Locality', 'City', 'State', 'PIN Code', 'Occupation', 'Business Name', 'Business Category', 'Business Address', 'Marital Status', 'Spouse Type', 'Spouse Name'];
for ($number = 1; $number <= $maxSons; $number++) {
    array_push($headers, "Son {$number} Name", "Son {$number} Age", "Son {$number} Marital Status");
}
for ($number = 1; $number <= $maxDaughters; $number++) {
    array_push($headers, "Daughter {$number} Name", "Daughter {$number} Age", "Daughter {$number} Marital Status");
}
$exportRows = [$headers];
foreach ($rows as $index => $row) {
    $spouseType = $row['husband_name'] ? 'Husband' : ($row['wife_name'] ? 'Wife' : '');
    $spouseName = $row['husband_name'] ?: ($row['wife_name'] ?: '');
    $exportRow = [
        $index + 1, $row['created_at'], $row['name'], $row['father_name'], $row['mobile'], $row['email'],
        $row['house_number'], $row['locality'], $row['city'], $row['state'], $row['pin_code'], $row['occupation'],
        $row['business_name'], $row['business_category'], $row['business_address'], $row['marital_status'],
        $spouseType, $spouseName,
    ];
    $sons = $familyByType[(int) $row['id']]['Son'] ?? [];
    $daughters = $familyByType[(int) $row['id']]['Daughter'] ?? [];
    for ($number = 0; $number < $maxSons; $number++) {
        $son = $sons[$number] ?? null;
        array_push($exportRow, $son['name'] ?? '', isset($son) ? (int) $son['age'] : '', $son['marital_status'] ?? '');
    }
    for ($number = 0; $number < $maxDaughters; $number++) {
        $daughter = $daughters[$number] ?? null;
        array_push($exportRow, $daughter['name'] ?? '', isset($daughter) ? (int) $daughter['age'] : '', $daughter['marital_status'] ?? '');
    }
    $exportRows[] = $exportRow;
}

$xmlEscape = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$sheetRows = '';
foreach ($exportRows as $rowIndex => $row) {
    $cells = '';
    foreach ($row as $value) {
        if (is_int($value)) {
            $cells .= '<c><v>' . $value . '</v></c>';
        } else {
            $cells .= '<c t="inlineStr"' . ($rowIndex === 0 ? ' s="1"' : '') . '><is><t xml:space="preserve">' . $xmlEscape((string) $value) . '</t></is></c>';
        }
    }
    $sheetRows .= '<row>' . $cells . '</row>';
}

$excelColumn = static function (int $number): string {
    $name = '';
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }
    return $name;
};
$lastColumn = $excelColumn(count($headers));
$baseWidths = [7, 20, 22, 22, 15, 28, 15, 22, 18, 20, 10, 16, 22, 20, 32, 17, 14, 22];
$columnWidths = $baseWidths;
for ($number = 0; $number < $maxSons + $maxDaughters; $number++) array_push($columnWidths, 22, 10, 20);
$columnsXml = '<cols>';
foreach ($columnWidths as $index => $width) {
    $columnNumber = $index + 1;
    $columnsXml .= '<col min="' . $columnNumber . '" max="' . $columnNumber . '" width="' . $width . '" customWidth="1"/>';
}
$columnsXml .= '</cols>';
$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/>' . $columnsXml . '<sheetData>' . $sheetRows . '</sheetData><autoFilter ref="A1:' . $lastColumn . max(1, count($exportRows)) . '"/></worksheet>';
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Registrations" sheetId="1" r:id="rId1"/></sheets></workbook>';
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

$tempFile = tempnam(sys_get_temp_dir(), 'bsg_export_');
if ($tempFile === false) throw new RuntimeException('Unable to create export.');
$zip = new ZipArchive();
if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create export.');
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
$zip->addFromString('xl/styles.xml', $styles);
$zip->close();

$filename = 'registrations_' . $suffix . '_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
readfile($tempFile);
unlink($tempFile);
