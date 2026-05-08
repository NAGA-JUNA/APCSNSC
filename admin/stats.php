<?php
require_once __DIR__ . '/../db.php';
require_admin();

$editing = null;
if (isset($_GET['edit'])) {
    $editing = fetch_one('SELECT * FROM homepage_stats WHERE id = :id', [':id' => (int)$_GET['edit']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        redirect_to('admin/stats.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $label = clean($_POST['label'] ?? '');
    $value = clean($_POST['value'] ?? '0');
    $icon = clean($_POST['icon'] ?? '•');

    if ($id > 0) {
        execute_query('UPDATE homepage_stats SET label = :label, value = :value, icon = :icon WHERE id = :id', [
            ':label' => $label,
            ':value' => $value,
            ':icon' => $icon,
            ':id' => $id,
        ]);
    } else {
        execute_query('INSERT INTO homepage_stats (label, value, icon) VALUES (:label, :value, :icon)', [
            ':label' => $label,
            ':value' => $value,
            ':icon' => $icon,
        ]);
    }

    redirect_to('admin/stats.php');
}

if (isset($_GET['delete'])) {
    if (!verify_csrf($_GET['token'] ?? null)) {
        redirect_to('admin/stats.php');
    }

    execute_query('DELETE FROM homepage_stats WHERE id = :id', [':id' => (int)$_GET['delete']]);
    redirect_to('admin/stats.php');
}

$records = fetch_all('SELECT * FROM homepage_stats ORDER BY id ASC');

$pageTitle = 'Stats Management';
$activeMenu = 'stats';
require_once __DIR__ . '/_top.php';
?>

<div class="card">
    <h3><?= $editing ? 'Edit Stat' : 'Add Stat'; ?></h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="id" value="<?= esc((string)($editing['id'] ?? '0')); ?>">
        <div class="form-group">
            <label>Label</label>
            <input type="text" name="label" value="<?= esc($editing['label'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label>Value</label>
            <input type="text" name="value" value="<?= esc((string)($editing['value'] ?? '0')); ?>" required>
        </div>
        <div class="form-group">
            <label>Icon</label>
            <input type="text" name="icon" value="<?= esc($editing['icon'] ?? '•'); ?>">
        </div>
        <div class="form-group full">
            <button class="btn btn-primary" type="submit">Save Stat</button>
        </div>
    </form>
</div>

<div class="table-wrap" style="margin-top: 18px;">
    <table>
        <thead><tr><th>Label</th><th>Value</th><th>Icon</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($records as $row): ?>
            <tr>
                <td><?= esc($row['label']); ?></td>
                <td><?= esc((string)$row['value']); ?></td>
                <td><?= esc($row['icon']); ?></td>
                <td>
                    <a href="?edit=<?= (int)$row['id']; ?>">Edit</a> |
                    <a href="?delete=<?= (int)$row['id']; ?>&token=<?= esc(csrf_token()); ?>" onclick="return confirm('Delete this stat?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/_bottom.php'; ?>
