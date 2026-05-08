<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$benefits = fetch_all('SELECT * FROM showcase_member_benefits ORDER BY sort_order ASC');
$videos = fetch_all('SELECT * FROM showcase_youtube_videos ORDER BY sort_order ASC');
$gallery_images = fetch_all('SELECT * FROM showcase_media_gallery ORDER BY sort_order ASC');

// Handle POST requests for updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logic for adding/editing/deleting benefits, videos, and images will go here
    // For now, we just refresh the page to keep it simple
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

require_once __DIR__ . '/_top.php';
?>

<div class="container">
    <h1 class="h2 mb-4">Homepage Content Manager</h1>

    <!-- Benefits Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Member Benefits / Highlights</h2>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBenefitModal">Add Benefit</button>
        </div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Icon</th>
                        <th>Title</th>
                        <th>Link</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($benefits as $item): ?>
                    <tr>
                        <td><?= esc($item['sort_order']) ?></td>
                        <td><i class="<?= esc($item['icon']) ?> fa-fw"></i></td>
                        <td><?= esc($item['title']) ?></td>
                        <td><?= esc($item['link']) ?></td>
                        <td><?= $item['is_active'] ? 'Yes' : 'No' ?></td>
                        <td>
                            <button class="btn btn-info btn-sm">Edit</button>
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- YouTube Videos Section -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">YouTube Videos</h2>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVideoModal">Add Video</button>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Video</th>
                        <th>Title</th>
                        <th>Featured</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $item): ?>
                    <tr>
                        <td><?= esc($item['sort_order']) ?></td>
                        <td>
                            <img src="https://img.youtube.com/vi/<?= esc($item['video_id']) ?>/mqdefault.jpg" width="120" alt="<?= esc($item['title']) ?>">
                        </td>
                        <td><?= esc($item['title']) ?></td>
                        <td><?= $item['is_featured'] ? 'Yes' : 'No' ?></td>
                        <td><?= $item['is_active'] ? 'Yes' : 'No' ?></td>
                        <td>
                            <button class="btn btn-info btn-sm">Edit</button>
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gallery Images Section -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Media Gallery</h2>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addImageModal">Add Image</button>
        </div>
        <div class="card-body">
             <div class="row">
                <?php foreach ($gallery_images as $item): ?>
                <div class="col-md-3 mb-3">
                    <div class="card">
                        <img src="<?= esc(base_url($item['image_path'])) ?>" class="card-img-top" alt="<?= esc($item['title']) ?>">
                        <div class="card-body">
                            <p class="card-text"><?= esc($item['title']) ?></p>
                            <button class="btn btn-info btn-sm">Edit</button>
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals for adding content will be added here -->

<?php require_once __DIR__ . '/_bottom.php'; ?>
