<?php
require_once __DIR__ . '/../db.php';
require_admin();

$pdo = db();

// Ensure table exists with all required columns
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS homepage_districts (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(150) NOT NULL,
        image       VARCHAR(255) DEFAULT NULL,
        description VARCHAR(255) DEFAULT NULL,
        sort_order  INT NOT NULL DEFAULT 0,
        is_active   TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// Add missing columns to existing installs
foreach ([
    "ALTER TABLE homepage_districts ADD COLUMN description VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE homepage_districts ADD COLUMN sort_order INT NOT NULL DEFAULT 0",
    "ALTER TABLE homepage_districts ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
] as $alter) {
    try { $pdo->exec($alter); } catch (PDOException) {}
}

// Fix sort_order = 0 for rows that predate this page
$pdo->exec('UPDATE homepage_districts SET sort_order = id WHERE sort_order = 0');

// Seed all 26 AP districts if the table is empty
$existing = (int)fetch_one('SELECT COUNT(*) AS cnt FROM homepage_districts')['cnt'];
if ($existing === 0) {
    $apDistricts = [
        ['Srikakulam', 1], ['Vizianagaram', 2], ['Visakhapatnam', 3],
        ['Anakapalli', 4], ['Alluri Sitharama Raju', 5], ['Kakinada', 6],
        ['Konaseema', 7], ['Eluru', 8], ['West Godavari', 9],
        ['Krishna', 10], ['NTR (Vijayawada)', 11], ['Palnadu', 12],
        ['Guntur', 13], ['Bapatla', 14], ['Prakasam', 15],
        ['Nellore (SPSR)', 16], ['Tirupati', 17], ['Annamayya', 18],
        ['YSR Kadapa', 19], ['Kurnool', 20], ['Nandyal', 21],
        ['Sri Sathya Sai', 22], ['Anantapur', 23], ['Chittoor', 24],
        ['Sri Balaji', 25], ['Guntur (Rural)', 26],
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO homepage_districts (name, sort_order) VALUES (?, ?)');
    foreach ($apDistricts as [$distName, $order]) {
        $stmt->execute([$distName, $order]);
    }
}

// ---------- Handle POST (Add / Edit) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect_to('admin/district_committees.php');
    }

    $id          = (int)($_POST['id'] ?? 0);
    $name        = clean($_POST['name'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        set_flash('error', 'District name is required.');
        redirect_to('admin/district_committees.php' . ($id ? "?edit={$id}" : ''));
    }

    $uploaded = upload_image($_FILES['district_image'] ?? [], 'uploads/districts');

    if ($id > 0) {
        $existing = fetch_one('SELECT image FROM homepage_districts WHERE id = :id', [':id' => $id]);
        $imagePath = $uploaded ?? ($existing['image'] ?? null);
        execute_query(
            'UPDATE homepage_districts SET name=:name, description=:desc, sort_order=:so, is_active=:ia, image=:img WHERE id=:id',
            [':name' => $name, ':desc' => $description ?: null, ':so' => $sortOrder, ':ia' => $isActive, ':img' => $imagePath, ':id' => $id]
        );
        set_flash('success', "District \"{$name}\" updated.");
    } else {
        execute_query(
            'INSERT INTO homepage_districts (name, description, sort_order, is_active, image) VALUES (:name,:desc,:so,:ia,:img)',
            [':name' => $name, ':desc' => $description ?: null, ':so' => $sortOrder, ':ia' => $isActive, ':img' => $uploaded]
        );
        set_flash('success', "District \"{$name}\" added.");
    }

    redirect_to('admin/district_committees.php');
}

// ---------- Handle DELETE ----------
if (isset($_GET['delete'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        execute_query('DELETE FROM homepage_districts WHERE id = :id', [':id' => (int)$_GET['delete']]);
        set_flash('success', 'District removed.');
    }
    redirect_to('admin/district_committees.php');
}

// ---------- Handle quick active toggle ----------
if (isset($_GET['toggle'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        execute_query(
            'UPDATE homepage_districts SET is_active = 1 - is_active WHERE id = :id',
            [':id' => (int)$_GET['toggle']]
        );
    }
    redirect_to('admin/district_committees.php');
}

// ---------- Load editing row ----------
$editing = null;
if (isset($_GET['edit'])) {
    $editing = fetch_one('SELECT * FROM homepage_districts WHERE id = :id', [':id' => (int)$_GET['edit']]);
}

$allDistricts = fetch_all('SELECT * FROM homepage_districts ORDER BY sort_order ASC, name ASC');

$pageTitle  = 'District Committees';
$activeMenu = 'district-committees';
require_once __DIR__ . '/_top.php';
?>

<?php if ($success = get_flash('success')): ?>
    <div class="alert alert-success"><?= esc($success); ?></div>
<?php endif; ?>
<?php if ($error = get_flash('error')): ?>
    <div class="alert alert-danger"><?= esc($error); ?></div>
<?php endif; ?>

<div class="admin-card mb-3" style="border-left:4px solid var(--ap-primary,#1a73e8);padding:14px 20px;">
    <strong><i class="fa-solid fa-circle-info"></i> What this page controls:</strong>
    <span class="text-muted ms-2">The "District Committees" block on the homepage. Upload a photo for each district, set display order, and show/hide individual districts.</span>
</div>

<!-- Add / Edit Form -->
<section class="admin-card mb-3">
    <h4><?= $editing ? 'Edit District' : 'Add District'; ?></h4>
    <form method="post" enctype="multipart/form-data" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="id" value="<?= esc((string)($editing['id'] ?? '0')); ?>">

        <div class="col-md-4">
            <label class="form-label">District Name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" required
                   value="<?= esc((string)($editing['name'] ?? '')); ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Short Description</label>
            <input class="form-control" name="description" placeholder="Optional tagline"
                   value="<?= esc((string)($editing['description'] ?? '')); ?>">
        </div>

        <div class="col-md-2">
            <label class="form-label">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" min="0"
                   value="<?= esc((string)($editing['sort_order'] ?? '0')); ?>">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                    <?= !isset($editing) || (int)($editing['is_active'] ?? 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_active">Show on Homepage</label>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label">District Photo <small class="text-muted">(JPG / PNG / WebP, max 10 MB)</small></label>
            <?php if (!empty($editing['image'])): ?>
                <div class="mb-2">
                    <img src="<?= esc(base_url($editing['image'])); ?>" alt="Current photo"
                         style="height:64px;border-radius:6px;object-fit:cover;border:1px solid #dee2e6;">
                    <small class="text-muted ms-2">Current photo — upload a new one to replace</small>
                </div>
            <?php endif; ?>
            <input class="form-control" type="file" name="district_image" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="col-md-6 d-flex align-items-end gap-2">
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-floppy-disk"></i> <?= $editing ? 'Update District' : 'Add District'; ?>
            </button>
            <?php if ($editing): ?>
                <a class="btn btn-outline-secondary" href="?">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- District list -->
<section class="admin-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">All Districts
            <span class="badge bg-secondary ms-2"><?= count($allDistricts); ?></span>
        </h4>
        <small class="text-muted">Active ones display on homepage &bull; Click the eye icon to toggle visibility</small>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead>
                <tr>
                    <th style="width:60px">Photo</th>
                    <th>District Name</th>
                    <th>Description</th>
                    <th style="width:90px">Order</th>
                    <th style="width:90px">Status</th>
                    <th style="width:130px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allDistricts as $d): ?>
                    <tr class="<?= !(int)$d['is_active'] ? 'table-secondary opacity-50' : ''; ?>">
                        <td>
                            <?php if (!empty($d['image'])): ?>
                                <img src="<?= esc(base_url($d['image'])); ?>" alt="<?= esc($d['name']); ?>"
                                     style="width:48px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;">
                            <?php else: ?>
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:36px;background:#f3f4f6;border-radius:4px;border:1px dashed #ced4da;color:#9ca3af;font-size:11px;">No img</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= esc($d['name']); ?></strong></td>
                        <td class="text-muted small"><?= esc($d['description'] ?? '—'); ?></td>
                        <td class="text-center"><?= (int)$d['sort_order']; ?></td>
                        <td>
                            <a href="?toggle=<?= (int)$d['id']; ?>&token=<?= esc(csrf_token()); ?>"
                               class="badge text-decoration-none <?= (int)$d['is_active'] ? 'bg-success' : 'bg-secondary'; ?>"
                               title="Click to toggle">
                                <i class="fa-solid <?= (int)$d['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                <?= (int)$d['is_active'] ? 'Active' : 'Hidden'; ?>
                            </a>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-outline-info" href="?edit=<?= (int)$d['id']; ?>">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <a class="btn btn-sm btn-outline-danger" data-confirm-delete
                                   href="?delete=<?= (int)$d['id']; ?>&token=<?= esc(csrf_token()); ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>