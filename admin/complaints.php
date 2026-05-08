<?php
require_once __DIR__ . '/../db.php';
require_admin();

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) DEFAULT "Anonymous",
        district VARCHAR(150) NOT NULL,
        issue VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        status VARCHAR(30) DEFAULT "pending",
        priority VARCHAR(20) DEFAULT "Medium",
        reply_text TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )'
);

$priorityCheck = fetch_one('SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "complaints" AND COLUMN_NAME = "priority"');
if ((int)($priorityCheck['total'] ?? 0) === 0) {
    $pdo->exec('ALTER TABLE complaints ADD COLUMN priority VARCHAR(20) DEFAULT "Medium"');
}

$isDistrictScoped = is_district_president() && admin_district() !== '';
$scopedDistrict = admin_district();

$canAccessComplaint = static function (int $complaintId) use ($isDistrictScoped): bool {
    if (!$isDistrictScoped || $complaintId <= 0) {
        return true;
    }

    $row = fetch_one('SELECT district FROM complaints WHERE id = :id LIMIT 1', [':id' => $complaintId]);
    if (!$row) {
        return false;
    }

    return admin_can_access_district((string)($row['district'] ?? ''));
};

if (isset($_GET['status']) && isset($_GET['id'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        $complaintId = (int)$_GET['id'];
        if ($canAccessComplaint($complaintId)) {
            $allowedStatus = ['pending', 'in-progress', 'resolved', 'closed'];
            $status = strtolower((string)$_GET['status']);
            if (in_array($status, $allowedStatus, true)) {
                execute_query('UPDATE complaints SET status = :status WHERE id = :id', [
                    ':status' => $status,
                    ':id' => $complaintId,
                ]);
                set_flash('success', 'Complaint status updated successfully.');
            }
        } else {
            set_flash('error', 'You can only update complaints from your assigned district.');
        }
    }
    redirect_to('admin/complaints.php');
}

if (isset($_GET['priority']) && isset($_GET['id'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        $complaintId = (int)$_GET['id'];
        if ($canAccessComplaint($complaintId)) {
            $allowedPriority = ['Low', 'Medium', 'High'];
            $priority = ucfirst(strtolower((string)$_GET['priority']));
            if (in_array($priority, $allowedPriority, true)) {
                execute_query('UPDATE complaints SET priority = :priority WHERE id = :id', [
                    ':priority' => $priority,
                    ':id' => $complaintId,
                ]);
                set_flash('success', 'Complaint priority updated successfully.');
            }
        } else {
            set_flash('error', 'You can only update complaints from your assigned district.');
        }
    }
    redirect_to('admin/complaints.php');
}

$search = trim((string)($_GET['search'] ?? ''));
$districtFilter = trim((string)($_GET['district'] ?? ''));
$statusFilter = trim((string)($_GET['status_filter'] ?? ''));
$priorityFilter = trim((string)($_GET['priority_filter'] ?? ''));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE :search OR issue LIKE :search OR description LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($districtFilter !== '') {
    $where[] = 'district = :district';
    $params[':district'] = $districtFilter;
}

if ($statusFilter !== '') {
    $where[] = 'status = :status_filter';
    $params[':status_filter'] = $statusFilter;
}

if ($priorityFilter !== '') {
    $where[] = 'priority = :priority_filter';
    $params[':priority_filter'] = $priorityFilter;
}

if ($isDistrictScoped) {
    $where[] = 'district = :scope_district';
    $params[':scope_district'] = $scopedDistrict;
    $districtFilter = $scopedDistrict;
}

$sql = 'SELECT * FROM complaints';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$complaints = fetch_all($sql, $params);

$districtOptions = fetch_all('SELECT DISTINCT district FROM complaints WHERE district IS NOT NULL AND district <> "" ORDER BY district ASC');

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=APCSNSC_complaints_export.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Complaint ID', 'Member Name', 'District', 'Subject', 'Description', 'Status', 'Priority', 'Date']);

    foreach ($complaints as $item) {
        fputcsv($output, [
            'CMP-' . str_pad((string)$item['id'], 5, '0', STR_PAD_LEFT),
            $item['name'] ?? 'Anonymous',
            $item['district'] ?? '',
            $item['issue'] ?? '',
            $item['description'] ?? '',
            ucfirst((string)($item['status'] ?? 'pending')),
            $item['priority'] ?? 'Medium',
            $item['created_at'] ?? '',
        ]);
    }

    fclose($output);
    exit;
}

$pageTitle = 'Complaints';
$activeMenu = 'complaints';
require_once __DIR__ . '/_top.php';
?>

<?php if ($msg = get_flash('success')): ?>
    <div class="alert alert-success m-3"><?= esc($msg); ?></div>
<?php endif; ?>
<?php if ($msg = get_flash('error')): ?>
    <div class="alert alert-danger m-3"><?= esc($msg); ?></div>
<?php endif; ?>

<section class="admin-card" style="margin-bottom: 12px;">
    <form method="get" class="filters-wrap">
        <input class="form-control" type="text" name="search" placeholder="Search by name, issue..." value="<?= esc($search); ?>">

        <select class="form-select" name="district" <?= $isDistrictScoped ? 'disabled' : ''; ?>>
            <option value="">All Districts</option>
            <?php foreach ($districtOptions as $option): ?>
                <?php $dist = (string)($option['district'] ?? ''); ?>
                <option value="<?= esc($dist); ?>" <?= $districtFilter === $dist ? 'selected' : ''; ?>><?= esc($dist); ?></option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($isDistrictScoped): ?>
            <input type="hidden" name="district" value="<?= esc($scopedDistrict); ?>">
        <?php endif; ?>

        <select class="form-select" name="status_filter">
            <option value="">All Statuses</option>
            <?php foreach (['pending', 'in-progress', 'resolved', 'closed'] as $st): ?>
                <option value="<?= esc($st); ?>" <?= $statusFilter === $st ? 'selected' : ''; ?>><?= esc(ucfirst($st)); ?></option>
            <?php endforeach; ?>
        </select>

        <select class="form-select" name="priority_filter">
            <option value="">All Priorities</option>
            <?php foreach (['Low', 'Medium', 'High'] as $pr): ?>
                <option value="<?= esc($pr); ?>" <?= $priorityFilter === $pr ? 'selected' : ''; ?>><?= esc($pr); ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Filter</button>
        
        <div class="d-flex gap-2">
            <a class="btn btn-outline-success" href="?<?= esc(http_build_query(array_merge($_GET, ['export' => 'excel']))); ?>"><i class="fa-solid fa-file-excel me-1"></i>Export Excel</a>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead>
                <tr>
                    <th>Complaint ID</th>
                    <th>Member Name</th>
                    <th>District</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Date</th>
                    <th>Reply</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $item): ?>
                    <?php
                    $status = strtolower((string)($item['status'] ?? 'pending'));
                    $statusClass = $status === 'resolved' || $status === 'closed' ? 'success' : ($status === 'in-progress' ? 'warning' : 'danger');
                    $priority = (string)($item['priority'] ?? 'Medium');
                    $priorityClass = $priority === 'High' ? 'danger' : ($priority === 'Low' ? 'success' : 'warning');
                    ?>
                    <tr>
                        <td>#CMP-<?= esc(str_pad((string)$item['id'], 5, '0', STR_PAD_LEFT)); ?></td>
                        <td><?= esc((string)($item['name'] ?? 'Anonymous')); ?></td>
                        <td><?= esc((string)$item['district']); ?></td>
                        <td>
                            <strong><?= esc((string)$item['issue']); ?></strong>
                            <?php
                            $desc = (string)$item['description'];
                            $shortDesc = mb_strlen($desc) > 60 ? mb_substr($desc, 0, 60) . '...' : $desc;
                            ?>
                            <p class="mb-0 small text-secondary"><?= esc($shortDesc); ?></p>
                        </td>
                        <td>
                            <span class="badge-soft <?= esc($statusClass); ?>"><?= esc(ucfirst($status)); ?></span>
                            <div class="dropdown d-inline-block ms-1">
                                <button class="btn btn-sm btn-light border" data-bs-toggle="dropdown" type="button">Change</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?id=<?= (int)$item['id']; ?>&status=pending&token=<?= esc(csrf_token()); ?>">Pending</a></li>
                                    <li><a class="dropdown-item" href="?id=<?= (int)$item['id']; ?>&status=in-progress&token=<?= esc(csrf_token()); ?>">In Progress</a></li>
                                    <li><a class="dropdown-item" href="?id=<?= (int)$item['id']; ?>&status=resolved&token=<?= esc(csrf_token()); ?>">Resolved</a></li>
                                    <li><a class="dropdown-item" href="?id=<?= (int)$item['id']; ?>&status=closed&token=<?= esc(csrf_token()); ?>">Closed</a></li>
                                </ul>
                            </div>
                        </td>
                        <td>
                            <span class="badge-soft <?= esc($priorityClass); ?>"><?= esc($priority); ?></span>
                            <div class="dropdown d-inline-block ms-1">
                                <button class="btn btn-sm btn-light border" data-bs-toggle="dropdown" type="button">Set</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?id=<?= (int)$item['id']; ?>&priority=Low&token=<?= esc(csrf_token()); ?>">Low</a></li>
                                    <li><a class="dropdown-item" href="?id=<?= (int)$item['id']; ?>&priority=Medium&token=<?= esc(csrf_token()); ?>">Medium</a></li>
                                    <li><a class="dropdown-item" href="?id=<?= (int)$item['id']; ?>&priority=High&token=<?= esc(csrf_token()); ?>">High</a></li>
                                </ul>
                            </div>
                        </td>
                        <td><?= esc(date('d M Y, h:i A', strtotime((string)$item['created_at']))); ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-info js-view-complaint"
                                    data-bs-toggle="modal"
                                    data-bs-target="#complaintDetailsModal"
                                    data-complaint='<?= esc(json_encode([
                                        'id' => $item['id'],
                                        'name' => $item['name'] ?? 'Anonymous',
                                        'district' => $item['district'] ?? '',
                                        'issue' => $item['issue'] ?? '',
                                        'description' => $item['description'] ?? '',
                                        'status' => ucfirst($status),
                                        'priority' => $priority,
                                        'date' => date('d M Y, h:i A', strtotime((string)$item['created_at']))
                                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>'>View</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="alert('Reply workflow can be integrated with email/SMS API.');">Reply</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="complaintDetailsModal" tabindex="-1" aria-labelledby="complaintDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="complaintDetailsModalLabel">Complaint Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary small">Member Name</p>
                        <strong id="modalComplaintName"></strong>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary small">District</p>
                        <strong id="modalComplaintDistrict"></strong>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary small">Status</p>
                        <strong id="modalComplaintStatus"></strong>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary small">Priority</p>
                        <strong id="modalComplaintPriority"></strong>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1 text-secondary small">Date Filed</p>
                        <strong id="modalComplaintDate"></strong>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <p class="mb-1 text-secondary small">Subject / Issue</p>
                    <h5 id="modalComplaintIssue"></h5>
                </div>
                <div>
                    <p class="mb-1 text-secondary small">Full Description</p>
                    <div class="p-3 bg-light border rounded" id="modalComplaintDescription" style="white-space: pre-wrap; font-size: 0.95rem; color: #334155;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="alert('Reply workflow can be integrated with email/SMS API.');">Reply to Member</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('complaintDetailsModal');
        if (!modal) return;

        var nameEl = document.getElementById('modalComplaintName');
        var districtEl = document.getElementById('modalComplaintDistrict');
        var statusEl = document.getElementById('modalComplaintStatus');
        var priorityEl = document.getElementById('modalComplaintPriority');
        var dateEl = document.getElementById('modalComplaintDate');
        var issueEl = document.getElementById('modalComplaintIssue');
        var descEl = document.getElementById('modalComplaintDescription');
        var titleEl = document.getElementById('complaintDetailsModalLabel');

        document.querySelectorAll('.js-view-complaint').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var raw = btn.getAttribute('data-complaint');
                if (!raw) return;

                try {
                    var data = JSON.parse(raw);
                    titleEl.textContent = 'Complaint #CMP-' + String(data.id).padStart(5, '0');
                    nameEl.textContent = data.name || 'Anonymous';
                    districtEl.textContent = data.district || '-';
                    statusEl.textContent = data.status || '-';
                    priorityEl.textContent = data.priority || '-';
                    dateEl.textContent = data.date || '-';
                    issueEl.textContent = data.issue || '-';
                    descEl.textContent = data.description || '-';
                } catch (e) {
                    console.error('Error parsing complaint data', e);
                }
            });
        });
    })();
</script>

<?php require_once __DIR__ . '/_bottom.php'; ?>
