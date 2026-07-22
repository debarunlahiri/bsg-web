<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireAdmin();
require_once dirname(__DIR__) . '/config.php';

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 50;
$total = (int) database()->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$query = database()->prepare('SELECT * FROM registrations ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
$query->bindValue('limit', $perPage, PDO::PARAM_INT);
$query->bindValue('offset', $offset, PDO::PARAM_INT);
$query->execute();
$registrations = $query->fetchAll();
$familyByRegistration = [];
if ($registrations !== []) {
    $ids = array_map(static fn(array $row): int => (int) $row['id'], $registrations);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $familyQuery = database()->prepare(
        "SELECT registration_id, member_type, name, age, marital_status FROM family_members WHERE registration_id IN ({$placeholders}) ORDER BY id"
    );
    $familyQuery->execute($ids);
    foreach ($familyQuery->fetchAll() as $member) {
        $familyByRegistration[(int) $member['registration_id']][] = $member;
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registrations</title><link rel="stylesheet" href="admin.css?v=6"><link rel="stylesheet" href="export-ui.css?v=2"></head>
<body><main class="admin-shell">
<header><div><p class="eyebrow">Admin dashboard</p><h1>Registrations</h1><p class="muted"><?= number_format($total) ?> total submissions</p></div>
<form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="secondary">Sign out</button></form></header>
<section class="export-bar" aria-label="Download Excel">
    <div><strong>Download Excel</strong><span>Export all registrations or select a date range.</span></div>
    <div class="export-actions"><a class="export-all" href="export.php?scope=all&amp;csrf_token=<?= e(csrfToken()) ?>">All Excel</a><a class="export-pdf" href="export_pdf.php?scope=all&amp;csrf_token=<?= e(csrfToken()) ?>">All PDF</a></div>
    <form action="export.php" method="get">
        <input type="hidden" name="scope" value="range"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label>From<input type="date" name="from" max="<?= date('Y-m-d') ?>" required></label>
        <label>To<input type="date" name="to" max="<?= date('Y-m-d') ?>" required></label>
        <button type="submit">Range Excel</button><button type="submit" formaction="export_pdf.php">Range PDF</button>
    </form>
</section>
<section class="table-card"><div class="table-wrap"><table>
<thead><tr><th>S.No.</th><th>Date</th><th>Name</th><th>Father's name</th><th>Contact</th><th>Address</th><th>Occupation</th><th>Business</th><th>Marital status</th><th>Spouse</th><th>Sons</th><th>Daughters</th></tr></thead>
<tbody><?php if ($registrations === []): ?><tr><td colspan="12" class="empty">No registrations yet.</td></tr><?php endif; ?>
<?php foreach ($registrations as $index => $row): ?><tr>
<td class="serial-number"><?= number_format($offset + $index + 1) ?></td>
<td><?= e(date('d M Y, h:i A', strtotime($row['created_at']))) ?></td>
<td><strong><?= e($row['name']) ?></strong></td><td><?= e($row['father_name']) ?></td>
<td class="contact-cell"><a href="tel:<?= e($row['mobile']) ?>"><?= e($row['mobile']) ?></a><br><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td>
<td><?= e($row['house_number'] . ', ' . $row['locality']) ?><br><?= e($row['city'] . ', ' . $row['state'] . ' - ' . $row['pin_code']) ?></td>
<td><?= e($row['occupation']) ?></td>
<td><?= e($row['business_name'] ?: '—') ?><?php if ($row['business_category']): ?><br><span class="muted"><?= e($row['business_category']) ?></span><?php endif; ?></td>
<td><span class="status-badge <?= strtolower(e($row['marital_status'])) ?>"><?= e($row['marital_status']) ?></span></td>
<td><?php if ($row['husband_name']): ?><strong>Husband</strong><br><?= e($row['husband_name']) ?><?php elseif ($row['wife_name']): ?><strong>Wife</strong><br><?= e($row['wife_name']) ?><?php else: ?>—<?php endif; ?></td>
<td class="family-cell"><?php $sons = array_filter($familyByRegistration[(int) $row['id']] ?? [], static fn(array $member): bool => $member['member_type'] === 'Son'); ?>
<?php if ($sons === []): ?>—<?php else: ?><?php foreach ($sons as $member): ?><div class="family-member"><strong><?= e($member['name']) ?></strong><span><?= e((string) $member['age']) ?> years · <?= e($member['marital_status']) ?></span></div><?php endforeach; ?><?php endif; ?></td>
<td class="family-cell"><?php $daughters = array_filter($familyByRegistration[(int) $row['id']] ?? [], static fn(array $member): bool => $member['member_type'] === 'Daughter'); ?>
<?php if ($daughters === []): ?>—<?php else: ?><?php foreach ($daughters as $member): ?><div class="family-member"><strong><?= e($member['name']) ?></strong><span><?= e((string) $member['age']) ?> years · <?= e($member['marital_status']) ?></span></div><?php endforeach; ?><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php if ($pages > 1): ?><nav aria-label="Pagination"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?></nav><?php endif; ?>
</section></main></body></html>
