<?php
require_once __DIR__ . '/../db.php';
require_admin();

$pdo = db();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS media_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        media_type VARCHAR(40) NOT NULL,
        title VARCHAR(180) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        status VARCHAR(20) DEFAULT "active",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

$editing = null;
if (isset($_GET['edit'])) {
    $editing = fetch_one('SELECT * FROM media_uploads WHERE id = :id', [':id' => (int)$_GET['edit']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        redirect_to('admin/media.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $mediaType = clean($_POST['media_type'] ?? 'gallery');
    $title = clean($_POST['title'] ?? 'Media Item');

    $uploadFolder = 'uploads/media/' . strtolower($mediaType);
    $uploaded = upload_image($_FILES['media_file'] ?? [], $uploadFolder);

    if ($id > 0) {
        $existing = fetch_one('SELECT file_path FROM media_uploads WHERE id = :id', [':id' => $id]);
        execute_query('UPDATE media_uploads SET media_type = :media_type, title = :title, file_path = :file_path WHERE id = :id', [
            ':media_type' => $mediaType,
            ':title' => $title,
            ':file_path' => $uploaded ?: ($existing['file_path'] ?? ''),
            ':id' => $id,
        ]);
    } else {
        if ($uploaded !== null) {
            execute_query('INSERT INTO media_uploads (media_type, title, file_path) VALUES (:media_type, :title, :file_path)', [
                ':media_type' => $mediaType,
                ':title' => $title,
                ':file_path' => $uploaded,
            ]);
        }
    }

    redirect_to('admin/media.php');
}

if (isset($_GET['delete'])) {
    if (verify_csrf($_GET['token'] ?? null)) {
        execute_query('DELETE FROM media_uploads WHERE id = :id', [':id' => (int)$_GET['delete']]);
    }
    redirect_to('admin/media.php');
}

$mediaItems = fetch_all('SELECT * FROM media_uploads ORDER BY created_at DESC');

$pageTitle = 'Media Manager';
$activeMenu = 'media';
require_once __DIR__ . '/_top.php';
?>

<section class="admin-card" style="margin-bottom: 12px;">
    <h4><?= $editing ? 'Edit Media Item' : 'Upload Media'; ?></h4>
    <form method="post" enctype="multipart/form-data" class="row g-3 mt-1">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="id" value="<?= esc((string)($editing['id'] ?? '0')); ?>">

        <div class="col-md-3">
            <label class="form-label">Media Type</label>
            <select class="form-select" name="media_type">
                <?php $selectedType = (string)($editing['media_type'] ?? 'gallery'); ?>
                <option value="slider" <?= $selectedType === 'slider' ? 'selected' : ''; ?>>Slider Images</option>
                <option value="hero" <?= $selectedType === 'hero' ? 'selected' : ''; ?>>Hero Images</option>
                <option value="gallery" <?= $selectedType === 'gallery' ? 'selected' : ''; ?>>Gallery</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Title</label>
            <input class="form-control" type="text" name="title" required value="<?= esc((string)($editing['title'] ?? '')); ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">Upload Image</label>
            <input class="form-control" type="file" name="media_file" accept="image/jpeg,image/png,image/webp" <?= $editing ? '' : 'required'; ?>>
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Save</button>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle" data-admin-datatable>
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mediaItems as $item): ?>
                    <tr>
                        <td><img class="avatar-mini" src="<?= esc(base_url((string)$item['file_path'])); ?>" alt="Media preview"></td>
                        <td><?= esc(ucfirst((string)$item['media_type'])); ?></td>
                        <td><?= esc((string)$item['title']); ?></td>
                        <td><?= esc(date('d M Y', strtotime((string)$item['created_at']))); ?></td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-sm btn-outline-info" href="?edit=<?= (int)$item['id']; ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-danger" data-confirm-delete href="?delete=<?= (int)$item['id']; ?>&token=<?= esc(csrf_token()); ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/_bottom.php'; ?>
