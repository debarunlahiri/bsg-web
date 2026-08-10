<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireAdmin();
require_once dirname(__DIR__) . '/config.php';

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 30;
$total = (int) database()->query('SELECT COUNT(*) FROM matrimonial_registrations')->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$query = database()->prepare('SELECT * FROM matrimonial_registrations ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
$query->bindValue('limit', $perPage, PDO::PARAM_INT);
$query->bindValue('offset', $offset, PDO::PARAM_INT);
$query->execute();
$registrations = $query->fetchAll();
$imagesByRegistration = [];

if ($registrations !== []) {
    $ids = array_map(static fn(array $row): int => (int) $row['id'], $registrations);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $imageQuery = database()->prepare("SELECT matrimonial_registration_id, file_name, original_name FROM matrimonial_images WHERE matrimonial_registration_id IN ({$placeholders}) ORDER BY id");
    $imageQuery->execute($ids);
    foreach ($imageQuery->fetchAll() as $image) {
        $imagesByRegistration[(int) $image['matrimonial_registration_id']][] = $image;
    }
}

function displayAmount(mixed $amount): string
{
    return $amount === null ? '—' : '₹' . number_format((float) $amount, 2);
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matrimonial Registrations</title>
    <link rel="stylesheet" href="admin.css?v=6">
    <link rel="stylesheet" href="admin-nav.css?v=2">
    <link rel="stylesheet" href="matrimonial-list.css?v=1">
</head>

<body>
    <main class="admin-shell">
        <header>
            <div>
                <p class="eyebrow">Admin dashboard</p>
                <h1>Matrimonial registrations</h1>
                <p class="muted"><?= number_format($total) ?> total matrimonial profiles</p>
            </div>
            <div class="header-actions"><a class="primary-link" href="matrimonial.php">Add matrimonial registration</a>
                <form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="secondary">Sign out</button></form>
            </div>
        </header>
        <nav class="admin-tabs" aria-label="Registration sections"><a href="index.php">Member registrations</a><a class="active" href="matrimonial-list.php">Matrimonial registrations</a></nav>
        <section class="table-card">
            <div class="table-wrap">
                <table class="matrimonial-table">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Date</th>
                            <th>Photographs</th>
                            <th>Profile</th>
                            <th>Candidate</th>
                            <th>Birth details</th>
                            <th>Physical details</th>
                            <th>Education and profession</th>
                            <th>Contact</th>
                            <th>Payment</th>
                            <th>Other details</th>
                        </tr>
                    </thead>
                    <tbody><?php if ($registrations === []): ?><tr>
                                <td colspan="11" class="empty"><strong>No matrimonial registrations yet.</strong><span>Use “Add matrimonial registration” to create the first profile.</span></td>
                            </tr><?php endif; ?>
                        <?php foreach ($registrations as $index => $row): ?><tr>
                                <td class="serial-number"><?= number_format($offset + $index + 1) ?></td>
                                <td><?= e(date('d M Y, h:i A', strtotime($row['created_at']))) ?></td>
                                <td>
                                    <div class="photo-stack"><?php $photos = $imagesByRegistration[(int) $row['id']] ?? []; ?><?php if ($photos === []): ?><span class="no-photo">No photos</span><?php else: ?><?php foreach ($photos as $photoIndex => $photo): ?><a href="../uploads/matrimonial/<?= rawurlencode($photo['file_name']) ?>" target="_blank" rel="noopener" title="<?= e($photo['original_name']) ?>"><img src="../uploads/matrimonial/<?= rawurlencode($photo['file_name']) ?>" alt="Photograph <?= $photoIndex + 1 ?> of <?= e($row['full_name']) ?>" loading="lazy"></a><?php endforeach; ?><?php endif; ?></div>
                                </td>
                                <td><span class="profile-badge <?= strtolower(e($row['profile_type'])) ?>"><?= e($row['profile_type']) ?></span></td>
                                <td><strong><?= e($row['full_name']) ?></strong><span class="cell-detail">Father: <?= e($row['father_name']) ?></span><span class="cell-detail"><?= nl2br(e($row['address'])) ?></span></td>
                                <td><strong><?= e(date('d M Y', strtotime($row['date_of_birth']))) ?></strong><?php if ($row['birth_time']): ?><span class="cell-detail"><?= e(date('h:i A', strtotime($row['birth_time']))) ?></span><?php endif; ?><span class="cell-detail"><?= e($row['birth_place']) ?></span><span class="manglik-badge"><?= e($row['manglik_status']) ?></span></td>
                                <td><strong><?= e(rtrim(rtrim((string) $row['height_cm'], '0'), '.')) ?> cm</strong><span class="cell-detail"><?= e(rtrim(rtrim((string) $row['weight_kg'], '0'), '.')) ?> kg</span></td>
                                <td><strong><?= e($row['occupation']) ?></strong><span class="cell-detail"><?= nl2br(e($row['education'])) ?></span><?php if ($row['professional_qualification']): ?><span class="cell-detail"><?= nl2br(e($row['professional_qualification'])) ?></span><?php endif; ?><span class="cell-detail"><?= displayAmount($row['income_amount']) ?><?= $row['income_period'] ? ' ' . e($row['income_period']) : '' ?></span></td>
                                <td class="contact-cell"><a href="tel:<?= e($row['mobile']) ?>"><?= e($row['mobile']) ?></a><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td>
                                <td><strong><?= displayAmount($row['registration_charge']) ?></strong><span class="cell-detail"><?= e($row['payment_method'] ?: '—') ?></span><?php if ($row['payment_reference']): ?><span class="cell-detail">Ref: <?= e($row['payment_reference']) ?></span><?php endif; ?><?php if ($row['payment_date']): ?><span class="cell-detail"><?= e(date('d M Y', strtotime($row['payment_date']))) ?></span><?php endif; ?></td>
                                <td><?= $row['other_details'] ? nl2br(e($row['other_details'])) : '—' ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?><nav aria-label="Pagination"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?></nav><?php endif; ?>
        </section>
    </main>
</body>

</html>