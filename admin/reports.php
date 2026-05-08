<?php
require_once __DIR__ . '/../db.php';
require_admin();

$memberStatusSummary = fetch_all('SELECT status, COUNT(*) AS total FROM members GROUP BY status');
$districtSummary = fetch_all('SELECT district, COUNT(*) AS total FROM members GROUP BY district ORDER BY total DESC LIMIT 12');
$complaintSummary = fetch_all('SELECT status, COUNT(*) AS total FROM complaints GROUP BY status');

$pageTitle = 'Reports';
$activeMenu = 'reports';
require_once __DIR__ . '/_top.php';
?>

<section class="admin-layout-3" style="margin-bottom: 12px;">
    <article class="admin-card">
        <h5>Member Status Summary</h5>
        <ul class="list-group list-group-flush">
            <?php foreach ($memberStatusSummary as $row): ?>
                <li class="list-group-item d-flex justify-content-between px-0" style="background: transparent;">
                    <span><?= esc(ucfirst((string)$row['status'])); ?></span>
                    <strong><?= esc((string)$row['total']); ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>

    <article class="admin-card">
        <h5>Top District Membership</h5>
        <ul class="list-group list-group-flush">
            <?php foreach ($districtSummary as $row): ?>
                <li class="list-group-item d-flex justify-content-between px-0" style="background: transparent;">
                    <span><?= esc((string)$row['district']); ?></span>
                    <strong><?= esc((string)$row['total']); ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>

    <article class="admin-card">
        <h5>Complaint Status Summary</h5>
        <ul class="list-group list-group-flush">
            <?php foreach ($complaintSummary as $row): ?>
                <li class="list-group-item d-flex justify-content-between px-0" style="background: transparent;">
                    <span><?= esc(ucfirst((string)$row['status'])); ?></span>
                    <strong><?= esc((string)$row['total']); ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>
</section>

<section class="admin-card">
    <h4>Data Export Center</h4>
    <p class="text-secondary">Use table-level export buttons on Members and Complaints pages for PDF/Excel. This page provides high-level analytics quick view.</p>
    <a class="btn btn-outline-primary me-2" href="<?= esc(base_url('admin/members.php')); ?>">Open Member Exports</a>
    <a class="btn btn-outline-primary" href="<?= esc(base_url('admin/complaints.php')); ?>">Open Complaint Exports</a>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>
