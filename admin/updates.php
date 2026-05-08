﻿<?php
require_once __DIR__ . '/../db.php';
require_admin();

$columnRows = fetch_all("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'homepage_updates'");
$availableColumns = [];
foreach ($columnRows as $row) {
    $name = strtolower((string)($row['COLUMN_NAME'] ?? ''));
    if ($name !== '') {
        $availableColumns[$name] = true;
    }
}

$hasCreatedAt = isset($availableColumns['created_at']);
$hasUpdatedAt = isset($availableColumns['updated_at']);
$hasCategory = isset($availableColumns['category']);
$hasStatus = isset($availableColumns['status']);
$hasPublishAt = isset($availableColumns['publish_at']);
$hasShortDescription = isset($availableColumns['short_description']);
$hasFullDescription = isset($availableColumns['full_description']);
$hasViews = isset($availableColumns['views']);
$hasFeatured = isset($availableColumns['is_featured']);
$hasImagesJson = isset($availableColumns['images_json']);

$allowedCategories = ['Protest', 'News', 'Meeting', 'Notice', 'Recruitment'];
$allowedStatuses = ['draft', 'published', 'scheduled'];

$editing = null;
if (isset($_GET['edit'])) {
    $editing = fetch_one('SELECT * FROM homepage_updates WHERE id = :id', [':id' => (int)$_GET['edit']]);
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRows = fetch_all('SELECT * FROM homepage_updates ORDER BY created_at DESC, id DESC');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=apcsn-updates-' . date('Ymd-His') . '.csv');

    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fputcsv($out, ['ID', 'Title', 'Category', 'Status', 'Publish Date', 'Featured', 'Views', 'Created At']);
        foreach ($exportRows as $row) {
            $category = (string)($row['category'] ?? 'Notice');
            if (!in_array($category, $allowedCategories, true)) {
                $category = 'Notice';
            }

            $status = strtolower((string)($row['status'] ?? 'published'));
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'published';
            }

            $publishDate = (string)($row['publish_at'] ?? $row['created_at'] ?? '');
            $featured = (int)($row['is_featured'] ?? 0) === 1 ? 'Yes' : 'No';
            $views = (int)($row['views'] ?? 0);
            fputcsv($out, [
                (int)($row['id'] ?? 0),
                (string)($row['title'] ?? ''),
                $category,
                $status,
                $publishDate,
                $featured,
                $views,
                (string)($row['created_at'] ?? ''),
            ]);
        }
        fclose($out);
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        redirect_to('admin/updates.php');
    }

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'toggle_feature') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($hasFeatured) {
                $row = fetch_one('SELECT is_featured FROM homepage_updates WHERE id = :id', [':id' => $id]);
                if ($row) {
                    $next = (int)($row['is_featured'] ?? 0) === 1 ? 0 : 1;
                    execute_query('UPDATE homepage_updates SET is_featured = :is_featured WHERE id = :id', [
                        ':is_featured' => $next,
                        ':id' => $id,
                    ]);
                    set_flash('success', $next === 1 ? 'Update marked as featured.' : 'Update removed from featured list.');
                }
            } else {
                set_flash('error', 'Feature toggle is unavailable. Please run latest database update.');
            }
        }

        redirect_to('admin/updates.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $title = clean((string)($_POST['title'] ?? ''));
    $shortDescription = clean((string)($_POST['short_description'] ?? ''));
    $fullDescription = trim((string)($_POST['full_description'] ?? ''));
    $description = $shortDescription !== '' ? $shortDescription : clean($fullDescription);
    $category = clean((string)($_POST['category'] ?? 'Notice'));
    if (!in_array($category, $allowedCategories, true)) {
        $category = 'Notice';
    }

    $status = strtolower(clean((string)($_POST['status'] ?? 'published')));
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'published';
    }

    $publishAtInput = trim((string)($_POST['publish_at'] ?? ''));
    $publishAt = $publishAtInput !== '' && strtotime($publishAtInput) !== false
        ? date('Y-m-d H:i:s', strtotime($publishAtInput))
        : null;
    $isFeatured = (int)($_POST['is_featured'] ?? 0) === 1 ? 1 : 0;
    $uploadedImages = upload_multiple_images($_FILES['images'] ?? [], 'uploads');
    if (!$uploadedImages && !empty($_FILES['image'])) {
        $single = upload_image($_FILES['image'], 'uploads');
        if ($single !== null) {
            $uploadedImages[] = $single;
        }
    }

    $hasFileUploaded = false;
    if (!empty($_FILES['images']['name'][0])) {
        $hasFileUploaded = true;
    } elseif (!empty($_FILES['image']['name'])) {
        $hasFileUploaded = true;
    }

    if ($hasFileUploaded && empty($uploadedImages)) {
        set_flash('error', 'Image upload failed. Please ensure the file is JPG, PNG, or WEBP and is under 10MB in size.');
        redirect_to('admin/updates.php');
    }

    if ($title === '') {
        set_flash('error', 'Title is required.');
        redirect_to('admin/updates.php');
    }

    if ($id > 0) {
        $old = fetch_one('SELECT * FROM homepage_updates WHERE id = :id', [':id' => $id]);
        
        if (!empty($uploadedImages)) {
            $mergedImages = $uploadedImages;
        } else {
            $mergedImages = get_update_images($old ?? []);
        }
        
        $image = $mergedImages[0] ?? ($old['image'] ?? null);
        $imagesJson = $hasImagesJson ? encode_update_images($mergedImages) : null;

        $setParts = [
            'title = :title',
            'description = :description',
            'image = :image',
        ];

        $params = [
            ':title' => $title,
            ':description' => $description,
            ':image' => $image,
            ':id' => $id,
        ];

        if ($hasImagesJson) {
            $setParts[] = 'images_json = :images_json';
            $params[':images_json'] = $imagesJson;
        }

        if ($hasShortDescription) {
            $setParts[] = 'short_description = :short_description';
            $params[':short_description'] = $shortDescription;
        }

        if ($hasFullDescription) {
            $setParts[] = 'full_description = :full_description';
            $params[':full_description'] = $fullDescription;
        }

        if ($hasCategory) {
            $setParts[] = 'category = :category';
            $params[':category'] = $category;
        }

        if ($hasStatus) {
            $setParts[] = 'status = :status';
            $params[':status'] = $status;
        }

        if ($hasPublishAt) {
            $setParts[] = 'publish_at = :publish_at';
            $params[':publish_at'] = $publishAt;
        }

        if ($hasFeatured) {
            $setParts[] = 'is_featured = :is_featured';
            $params[':is_featured'] = $isFeatured;
        }

        if ($hasUpdatedAt) {
            $setParts[] = 'updated_at = NOW()';
        }

        execute_query('UPDATE homepage_updates SET ' . implode(', ', $setParts) . ' WHERE id = :id', $params);
        set_flash('success', 'Update edited successfully.');
    } else {
        $imagesJson = $hasImagesJson ? encode_update_images($uploadedImages) : null;
        $image = $uploadedImages[0] ?? null;
        $insertColumns = ['title', 'description', 'image'];
        $insertValues = [':title', ':description', ':image'];
        $params = [
            ':title' => $title,
            ':description' => $description,
            ':image' => $image,
        ];

        if ($hasImagesJson) {
            $insertColumns[] = 'images_json';
            $insertValues[] = ':images_json';
            $params[':images_json'] = $imagesJson;
        }

        if ($hasCreatedAt) {
            $insertColumns[] = 'created_at';
            $insertValues[] = 'NOW()';
        }

        if ($hasShortDescription) {
            $insertColumns[] = 'short_description';
            $insertValues[] = ':short_description';
            $params[':short_description'] = $shortDescription;
        }

        if ($hasFullDescription) {
            $insertColumns[] = 'full_description';
            $insertValues[] = ':full_description';
            $params[':full_description'] = $fullDescription;
        }

        if ($hasCategory) {
            $insertColumns[] = 'category';
            $insertValues[] = ':category';
            $params[':category'] = $category;
        }

        if ($hasStatus) {
            $insertColumns[] = 'status';
            $insertValues[] = ':status';
            $params[':status'] = $status;
        }

        if ($hasPublishAt) {
            $insertColumns[] = 'publish_at';
            $insertValues[] = ':publish_at';
            $params[':publish_at'] = $publishAt;
        }

        if ($hasFeatured) {
            $insertColumns[] = 'is_featured';
            $insertValues[] = ':is_featured';
            $params[':is_featured'] = $isFeatured;
        }

        execute_query(
            'INSERT INTO homepage_updates (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')',
            $params
        );
        set_flash('success', 'Update created successfully.');
    }

    redirect_to('admin/updates.php');
}

if (isset($_GET['delete'])) {
    if (!verify_csrf($_GET['token'] ?? null)) {
        redirect_to('admin/updates.php');
    }

    execute_query('DELETE FROM homepage_updates WHERE id = :id', [':id' => (int)$_GET['delete']]);
    set_flash('success', 'Update deleted successfully.');
    redirect_to('admin/updates.php');
}

$orderBy = $hasPublishAt ? 'publish_at DESC, created_at DESC, id DESC' : 'created_at DESC, id DESC';
$records = fetch_all('SELECT * FROM homepage_updates ORDER BY ' . $orderBy);

$normalized = [];
foreach ($records as $row) {
    $status = strtolower((string)($row['status'] ?? 'published'));
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'published';
    }

    $category = (string)($row['category'] ?? 'Notice');
    if (!in_array($category, $allowedCategories, true)) {
        $category = 'Notice';
    }

    $shortDesc = (string)($row['short_description'] ?? $row['description'] ?? '');
    $fullDesc = (string)($row['full_description'] ?? $row['description'] ?? '');
    $publishAt = (string)($row['publish_at'] ?? $row['created_at'] ?? '');

    $normalized[] = [
        'id' => (int)($row['id'] ?? 0),
        'title' => (string)($row['title'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'short_description' => $shortDesc,
        'full_description' => $fullDesc,
        'image' => (string)($row['image'] ?? ''),
        'images' => get_update_images($row),
        'category' => $category,
        'status' => $status,
        'publish_at' => $publishAt,
        'created_at' => (string)($row['created_at'] ?? ''),
        'views' => (int)($row['views'] ?? 0),
        'is_featured' => (int)($row['is_featured'] ?? 0),
    ];
}

$statsTotal = count($normalized);
$statsPublished = 0;
$statsDrafts = 0;
$statsScheduled = 0;

foreach ($normalized as $row) {
    if ($row['status'] === 'published') {
        $statsPublished++;
    } elseif ($row['status'] === 'draft') {
        $statsDrafts++;
    } elseif ($row['status'] === 'scheduled') {
        $statsScheduled++;
    }
}

$editingView = [
    'id' => (int)($editing['id'] ?? 0),
    'title' => (string)($editing['title'] ?? ''),
    'short_description' => (string)($editing['short_description'] ?? $editing['description'] ?? ''),
    'full_description' => (string)($editing['full_description'] ?? $editing['description'] ?? ''),
    'category' => (string)($editing['category'] ?? 'Notice'),
    'status' => strtolower((string)($editing['status'] ?? 'published')),
    'publish_at' => !empty($editing['publish_at']) ? date('Y-m-d\TH:i', strtotime((string)$editing['publish_at'])) : '',
    'is_featured' => (int)($editing['is_featured'] ?? 0),
    'image' => (string)($editing['image'] ?? ''),
    'images' => get_update_images($editing ?? []),
];

if (!in_array($editingView['category'], $allowedCategories, true)) {
    $editingView['category'] = 'Notice';
}

if (!in_array($editingView['status'], $allowedStatuses, true)) {
    $editingView['status'] = 'published';
}

$success = get_flash('success');
$error = get_flash('error');

$pageTitle = 'Updates Management';
$hideAdminPageTitle = true;
$activeMenu = 'updates';
require_once __DIR__ . '/_top.php';

$updatesCssPath = __DIR__ . '/../assets/css/admin-updates.css';
$updatesCssVer = file_exists($updatesCssPath) ? (string)filemtime($updatesCssPath) : (string)time();
?>
<link rel="stylesheet" href="<?= esc(base_url('assets/css/admin-updates.css?v=' . $updatesCssVer)); ?>">

<section class="updates-hero-card mb-3">
    <div>
        <h2>Updates Management</h2>
        <p>APCSNSC Government-grade operational dashboard</p>
    </div>
    <div class="updates-hero-actions">
        <a href="#updateFormCard" class="btn updates-btn-light" id="createUpdateBtn"><i class="fa-solid fa-plus me-1"></i>Create Update</a>
        <button type="button" class="btn updates-btn-light" id="scheduleQuickBtn"><i class="fa-regular fa-clock me-1"></i>Schedule</button>
        <a href="?export=csv" class="btn updates-btn-light"><i class="fa-solid fa-file-export me-1"></i>Export</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert alert-success updates-alert" role="alert"><?= esc($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger updates-alert" role="alert"><?= esc($error); ?></div>
<?php endif; ?>

<section class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="updates-stat-card">
            <div class="stat-icon stat-total"><i class="fa-regular fa-newspaper"></i></div>
            <div>
                <h3><?= esc((string)$statsTotal); ?></h3>
                <p>Total Updates</p>
            </div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="updates-stat-card">
            <div class="stat-icon stat-published"><i class="fa-solid fa-circle-check"></i></div>
            <div>
                <h3><?= esc((string)$statsPublished); ?></h3>
                <p>Published</p>
            </div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="updates-stat-card">
            <div class="stat-icon stat-draft"><i class="fa-regular fa-pen-to-square"></i></div>
            <div>
                <h3><?= esc((string)$statsDrafts); ?></h3>
                <p>Drafts</p>
            </div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="updates-stat-card">
            <div class="stat-icon stat-scheduled"><i class="fa-regular fa-clock"></i></div>
            <div>
                <h3><?= esc((string)$statsScheduled); ?></h3>
                <p>Scheduled</p>
            </div>
        </article>
    </div>
</section>

<section class="updates-form-card mb-3" id="updateFormCard">
    <div class="updates-card-head">
        <h3><?= $editing ? 'Edit Update #' . (int)$editingView['id'] : 'Create New Update'; ?></h3>
        <p>Prepare and publish premium update content with complete metadata.</p>
    </div>

    <form method="post" enctype="multipart/form-data" id="updatesForm">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
        <input type="hidden" name="id" value="<?= esc((string)$editingView['id']); ?>">
        <input type="hidden" name="action" value="save">

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Update Title</label>
                        <input type="text" class="form-control" name="title" maxlength="150" value="<?= esc($editingView['title']); ?>" required>
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Short Description</label>
                            <small class="text-muted"><span id="shortDescCount">0</span>/240</small>
                        </div>
                        <textarea class="form-control" rows="3" maxlength="240" name="short_description" id="shortDescriptionInput"><?= esc($editingView['short_description']); ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Full Description</label>
                            <small class="text-muted"><span id="fullDescCount">0</span>/2000</small>
                        </div>
                        <textarea class="form-control" rows="8" maxlength="2000" name="full_description" id="fullDescriptionInput"><?= esc($editingView['full_description']); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <?php foreach ($allowedCategories as $cat): ?>
                                <option value="<?= esc($cat); ?>" <?= $editingView['category'] === $cat ? 'selected' : ''; ?>><?= esc($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="updates-side-panel">
                    <label class="form-label">Upload Image</label>
                    <label class="updates-upload-box" for="updateImageInput" id="uploadDropZone">
                        <i class="fa-regular fa-image"></i>
                        <strong>Drag and drop image files</strong>
                        <span>or click to browse (JPG, PNG, WEBP) - multiple allowed</span>
                        <input type="file" id="updateImageInput" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="d-none">
                    </label>

                    <div class="updates-image-preview" id="imagePreviewWrap">
                        <?php $previewSrc = !empty($editingView['images'][0]) ? base_url($editingView['images'][0]) : (!empty($editingView['image']) ? base_url($editingView['image']) : ''); ?>
                        <?php if ($previewSrc !== ''): ?>
                            <img id="imagePreview" src="<?= esc($previewSrc); ?>" alt="Update preview">
                            <div class="updates-no-preview" id="noImagePreview" style="display:none;">No image selected</div>
                        <?php else: ?>
                            <img id="imagePreview" src="" alt="Update preview" style="display:none;">
                            <div class="updates-no-preview" id="noImagePreview">No image selected</div>
                        <?php endif; ?>
                    </div>

                    <div class="updates-selected-strip" id="selectedImagesStrip">
                        <?php foreach (get_update_images($editing ?? []) as $imagePath): ?>
                            <img src="<?= esc(base_url($imagePath)); ?>" alt="Selected update image">
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted d-block mt-2">Multiple images will appear as a slider on the front-end post view.</small>

                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label class="form-label">Publish Date</label>
                            <input type="datetime-local" class="form-control" name="publish_at" id="publishAtInput" value="<?= esc($editingView['publish_at']); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="statusInput">
                                <option value="draft" <?= $editingView['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?= $editingView['status'] === 'published' ? 'selected' : ''; ?>>Publish Now</option>
                                <option value="scheduled" <?= $editingView['status'] === 'scheduled' ? 'selected' : ''; ?>>Schedule</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch updates-feature-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="featuredToggle" name="is_featured" value="1" <?= $editingView['is_featured'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="featuredToggle">Feature this update on dashboard</label>
                            </div>
                            <?php if (!$hasFeatured): ?>
                                <small class="text-muted">Feature storage requires latest DB migration.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-md-9">
                <button type="submit" class="btn updates-btn-primary w-100">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Update
                </button>
            </div>
            <div class="col-md-3">
                <button type="reset" class="btn btn-outline-secondary w-100" id="resetFormBtn">Reset</button>
            </div>
        </div>
    </form>
</section>

<section class="updates-table-card">
    <div class="updates-card-head mb-2">
        <h3>Existing Updates</h3>
        <p>Track publication state, visibility and engagement in one place.</p>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-lg-5">
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" class="form-control" id="updatesSearchInput" placeholder="Search title or description...">
            </div>
        </div>
        <div class="col-sm-4 col-lg-2">
            <select id="categoryFilter" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($allowedCategories as $cat): ?>
                    <option value="<?= esc(strtolower($cat)); ?>"><?= esc($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4 col-lg-2">
            <select id="statusFilter" class="form-select">
                <option value="">All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
            </select>
        </div>
        <div class="col-sm-4 col-lg-3">
            <select id="sortFilter" class="form-select">
                <option value="latest">Sort: Latest</option>
                <option value="oldest">Sort: Oldest</option>
                <option value="title_asc">Sort: Title A-Z</option>
                <option value="views_desc">Sort: Most Views</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle updates-table" id="updatesTable">
            <thead>
                <tr>
                    <th>Thumbnail</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Views</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($normalized as $row): ?>
                    <?php
                    $statusLabel = ucfirst($row['status']);
                    $statusClass = 'status-published';
                    if ($row['status'] === 'draft') {
                        $statusClass = 'status-draft';
                    } elseif ($row['status'] === 'scheduled') {
                        $statusClass = 'status-scheduled';
                    }
                    $previewImage = !empty($row['images'][0]) ? base_url($row['images'][0]) : ($row['image'] !== '' ? base_url($row['image']) : '');
                    ?>
                    <tr
                        data-id="<?= (int)$row['id']; ?>"
                        data-title="<?= esc(strtolower($row['title'])); ?>"
                        data-desc="<?= esc(strtolower($row['short_description'])); ?>"
                        data-category="<?= esc(strtolower($row['category'])); ?>"
                        data-status="<?= esc($row['status']); ?>"
                        data-date="<?= esc((string)strtotime($row['publish_at'] !== '' ? $row['publish_at'] : $row['created_at'])); ?>"
                        data-views="<?= esc((string)$row['views']); ?>"
                    >
                        <td>
                            <?php if ($previewImage !== ''): ?>
                                <img src="<?= esc($previewImage); ?>" alt="<?= esc($row['title']); ?>" class="updates-thumb">
                            <?php else: ?>
                                <div class="updates-thumb updates-thumb-empty"><i class="fa-regular fa-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="updates-title-cell">
                                <strong><?= esc($row['title']); ?></strong>
                                <small><?= esc(function_exists('mb_strimwidth') ? mb_strimwidth($row['short_description'], 0, 85, '...') : (strlen($row['short_description']) > 85 ? substr($row['short_description'], 0, 85) . '...' : $row['short_description'])); ?></small>
                            </div>
                        </td>
                        <td><span class="badge text-bg-light"><?= esc($row['category']); ?></span></td>
                        <td><?= esc($row['publish_at'] !== '' ? date('d M Y, h:i A', strtotime($row['publish_at'])) : date('d M Y', strtotime($row['created_at']))); ?></td>
                        <td><span class="updates-status-badge <?= esc($statusClass); ?>"><?= esc($statusLabel); ?></span></td>
                        <td><?= esc(number_format($row['views'])); ?></td>
                        <td>
                            <div class="updates-actions">
                                <a href="?edit=<?= (int)$row['id']; ?>#updateFormCard" class="btn btn-sm btn-outline-primary">Edit</a>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-info preview-btn"
                                    data-title="<?= esc($row['title']); ?>"
                                    data-category="<?= esc($row['category']); ?>"
                                    data-status="<?= esc(ucfirst($row['status'])); ?>"
                                    data-date="<?= esc($row['publish_at'] !== '' ? date('d M Y, h:i A', strtotime($row['publish_at'])) : date('d M Y', strtotime($row['created_at']))); ?>"
                                    data-description="<?= esc($row['full_description']); ?>"
                                    data-image="<?= esc($previewImage); ?>"
                                >Preview</button>
                                <?php if ($hasFeatured): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="toggle_feature">
                                        <input type="hidden" name="id" value="<?= (int)$row['id']; ?>">
                                        <button type="submit" class="btn btn-sm <?= (int)$row['is_featured'] === 1 ? 'btn-warning' : 'btn-outline-warning'; ?>"><?= (int)$row['is_featured'] === 1 ? 'Featured' : 'Feature'; ?></button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" disabled>Feature</button>
                                <?php endif; ?>
                                <a href="?delete=<?= (int)$row['id']; ?>&token=<?= esc(csrf_token()); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this update?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="updatePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content updates-preview-modal">
            <div class="modal-header">
                <h5 class="modal-title">Update Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img id="previewModalImage" src="" alt="Update preview image" style="display:none;">
                <h4 id="previewModalTitle"></h4>
                <div class="preview-meta">
                    <span id="previewModalCategory"></span>
                    <span id="previewModalStatus"></span>
                    <span id="previewModalDate"></span>
                </div>
                <p id="previewModalDescription"></p>
            </div>
        </div>
    </div>
</div>

<?php
$updatesJsPath = __DIR__ . '/../assets/js/admin-updates.js';
$updatesJsVer = file_exists($updatesJsPath) ? (string)filemtime($updatesJsPath) : (string)time();
?>
<script src="<?= esc(base_url('assets/js/admin-updates.js?v=' . $updatesJsVer)); ?>"></script>

<?php require_once __DIR__ . '/_bottom.php'; ?>
