<?php
require_once __DIR__ . '/../db.php';
require_admin();

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS districts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        district_name VARCHAR(160) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        redirect_to('admin/districts.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $name = clean($_POST['district_name'] ?? '');

    if ($name !== '') {
        if ($id > 0) {
            execute_query('UPDATE districts SET district_name = :district_name WHERE id = :id', [
                ':district_name' => $name,
                ':id' => $id,
            ]);
        } else {
            execute_query('INSERT INTO districts (district_name) VALUES (:district_name)', [':district_name' => $name]);
        }
    }

    redirect_to('admin/districts.php');
}

if (isset($_GET['delete'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        execute_query('DELETE FROM districts WHERE id = :id', [':id' => (int)$_GET['delete']]);
    }
    redirect_to('admin/districts.php');
}

$editing = null;
if (isset($_GET['edit'])) {
    $editing = fetch_one('SELECT * FROM districts WHERE id = :id', [':id' => (int)$_GET['edit']]);
}

$districts = fetch_all(
    'SELECT d.id, d.district_name,
        (SELECT COUNT(*) FROM members m WHERE m.district = d.district_name) AS member_count,
        (SELECT COUNT(*) FROM members m WHERE m.district = d.district_name AND m.status IN ("approved", "active")) AS active_count
     FROM districts d
     ORDER BY d.district_name ASC'
);

$pageTitle = 'District Management';
$activeMenu = 'districts';
require_once __DIR__ . '/_top.php';
?>

<section class="admin-card" style="margin-bottom: 12px;">
    <h4><?= $editing ? 'Edit District' : 'Add District'; ?></h4>
    <form method="post" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="id" value="<?= esc((string)($editing['id'] ?? '0')); ?>">
        <div class="col-md-8">
            <label class="form-label">District Name</label>
            <input class="form-control" name="district_name" required value="<?= esc((string)($editing['district_name'] ?? '')); ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">Save District</button>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead>
                <tr>
                    <th>District Name</th>
                    <th>Member Count</th>
                    <th>Active Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($districts as $district): ?>
                    <tr>
                        <td><?= esc((string)$district['district_name']); ?></td>
                        <td><?= esc((string)$district['member_count']); ?></td>
                        <td><?= esc((string)$district['active_count']); ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-info" href="?edit=<?= (int)$district['id']; ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-danger" data-confirm-delete href="?delete=<?= (int)$district['id']; ?>&token=<?= esc(csrf_token()); ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>
