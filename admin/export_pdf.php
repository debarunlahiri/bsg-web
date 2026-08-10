<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireAdmin();
require_once dirname(__DIR__) . '/config.php';
require __DIR__ . '/_pdf.php';

if (!hash_equals(csrfToken(), (string) ($_GET['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid export request.');
}

$scope = (string) ($_GET['scope'] ?? 'all');
$params = [];
$where = '';
$label = 'All registrations';
$suffix = 'all';
$validDate = static function (string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
};
if ($scope === 'range') {
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    if (!$validDate($from) || !$validDate($to) || $from > $to) {
        http_response_code(422);
        exit('Select a valid date range.');
    }
    $where = ' WHERE r.created_at >= :from AND r.created_at < DATE_ADD(:to, INTERVAL 1 DAY)';
    $params = ['from' => $from, 'to' => $to];
    $label = 'Registrations from ' . $from . ' to ' . $to;
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
    $members = database()->prepare('SELECT registration_id, member_type, name, age, marital_status FROM family_members WHERE registration_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id');
    $members->execute($ids);
    foreach ($members->fetchAll() as $member) $family[(int) $member['registration_id']][] = $member;
}

$pdf = new SimplePdf();
$subtitle = $label . ' | Generated ' . date('d M Y, h:i A');
$pdf->newPage($subtitle);
if ($rows === []) {
    $pdf->heading('No registrations found');
} else {
    foreach ($rows as $index => $row) {
        $pdf->ensureSpace(250, $subtitle);
        $pdf->heading(($index + 1) . '. ' . $row['name'] . ' - ' . date('d M Y, h:i A', strtotime($row['created_at'])));
        $pdf->field("Father's name", $row['father_name']);
        $pdf->field('Contact', $row['mobile'] . ' | ' . $row['email']);
        $pdf->field('Address', $row['house_number'] . ', ' . $row['locality'] . ', ' . $row['city'] . ', ' . $row['state'] . ' - ' . $row['pin_code']);
        $pdf->field('Occupation', $row['occupation']);
        $business = trim(implode(' | ', array_filter([$row['business_name'], $row['business_category'], $row['business_address']])));
        $pdf->field('Business', $business);
        $spouse = $row['husband_name'] ? 'Husband: ' . $row['husband_name'] : ($row['wife_name'] ? 'Wife: ' . $row['wife_name'] : '');
        $pdf->field('Family status', $row['marital_status'] . ($spouse ? ' | ' . $spouse : ''));
        $sons = $daughters = [];
        foreach ($family[(int) $row['id']] ?? [] as $member) {
            $member['member_type'] === 'Son' ? $sons[] = $member : $daughters[] = $member;
        }
        if ($sons === []) {
            $pdf->field('Sons', 'None');
        } else {
            foreach ($sons as $sonIndex => $son) {
                $pdf->field('Son ' . ($sonIndex + 1), 'Name: ' . $son['name'] . ' | Age: ' . $son['age'] . ' | Marital status: ' . $son['marital_status']);
            }
        }
        if ($daughters === []) {
            $pdf->field('Daughters', 'None');
        } else {
            foreach ($daughters as $daughterIndex => $daughter) {
                $pdf->field('Daughter ' . ($daughterIndex + 1), 'Name: ' . $daughter['name'] . ' | Age: ' . $daughter['age'] . ' | Marital status: ' . $daughter['marital_status']);
            }
        }
        $pdf->separator();
    }
}

$document = $pdf->output();
$filename = 'registrations_' . $suffix . '_' . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($document));
echo $document;
