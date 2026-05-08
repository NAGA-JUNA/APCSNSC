<?php
require_once __DIR__ . '/../db.php';

$updateId = (int)($_GET['id'] ?? 0);
$update = $updateId > 0 ? fetch_one('SELECT * FROM homepage_updates WHERE id = :id', [':id' => $updateId]) : null;

if (!$update) {
    http_response_code(404);
    require_once __DIR__ . '/../header.php';
    ?>
    <section class="container page-hero fade-in">
        <h1>Update Not Found</h1>
        <p>The requested update is not available.</p>
        <a class="btn btn-primary" href="<?= esc(base_url('pages/news.php')); ?>">Back to Updates</a>
    </section>
    <?php
    require_once __DIR__ . '/../footer.php';
    exit;
}

if (!is_public_update($update)) {
    http_response_code(404);
    require_once __DIR__ . '/../header.php';
    ?>
    <section class="container page-hero fade-in">
        <h1>Update Not Available</h1>
        <p>This update is not published yet.</p>
        <a class="btn btn-primary" href="<?= esc(base_url('pages/news.php')); ?>">Back to Updates</a>
    </section>
    <?php
    require_once __DIR__ . '/../footer.php';
    exit;
}

$hasViewsColumn = true;
try {
    $column = fetch_one("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'homepage_updates' AND COLUMN_NAME = 'views'");
    $hasViewsColumn = (bool)$column;
} catch (Throwable $e) {
    $hasViewsColumn = false;
}

if ($hasViewsColumn) {
    try {
        execute_query('UPDATE homepage_updates SET views = COALESCE(views, 0) + 1 WHERE id = :id', [':id' => $updateId]);
        $update['views'] = (int)($update['views'] ?? 0) + 1;
    } catch (Throwable $e) {
        // Ignore counter failures.
    }
}

$images = get_update_images($update);
$cover = $images[0] ?? (!empty($update['image']) ? (string)$update['image'] : 'https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=1200&q=80');
$detailUrl = get_update_url($update);
$title = (string)($update['title'] ?? 'Update');
$fullDescription = trim((string)($update['full_description'] ?? $update['description'] ?? ''));
$shortDescription = trim((string)($update['short_description'] ?? $update['description'] ?? ''));
$category = (string)($update['category'] ?? 'Notice');
$status = strtolower((string)($update['status'] ?? 'published'));
$publishAt = !empty($update['publish_at']) ? date('d M Y, h:i A', strtotime((string)$update['publish_at'])) : date('d M Y', strtotime((string)($update['created_at'] ?? 'now')));
$views = (int)($update['views'] ?? 0);

require_once __DIR__ . '/../header.php';
?>

<section class="container page-hero page-hero-updates fade-in">
    <div class="update-detail-hero">
        <div>
            <span class="update-detail-chip"><?= esc($category); ?></span>
            <h1><?= esc($title); ?></h1>
            <p><?= esc($shortDescription !== '' ? $shortDescription : 'Official APCSNSC update and public information bulletin.'); ?></p>
            <div class="update-detail-meta">
                <span><i class="fa-regular fa-calendar me-1"></i><?= esc($publishAt); ?></span>
                <span><i class="fa-regular fa-eye me-1"></i><?= esc(number_format($views)); ?> views</span>
                <span><i class="fa-solid fa-circle-info me-1"></i><?= esc(ucfirst($status)); ?></span>
            </div>
        </div>
        <div class="update-detail-actions">
            <a class="btn btn-primary" href="#updateShareSection">Share</a>
            <a class="btn btn-outline" href="<?= esc(base_url('pages/news.php')); ?>">Back to Updates</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container update-detail-layout">
        <article class="update-detail-card">
            <div class="update-detail-gallery" data-update-gallery>
                <div class="update-detail-gallery-track">
                    <?php if ($images): ?>
                        <?php foreach ($images as $index => $imagePath): ?>
                            <figure class="update-detail-slide <?= $index === 0 ? 'is-active' : ''; ?>" data-update-gallery-slide>
                                <a href="<?= esc(resolve_image_src($imagePath)); ?>" data-fancybox="update-gallery" data-caption="<?= esc($title); ?> - Image <?= esc((string)($index + 1)); ?>">
                                    <img src="<?= esc(resolve_image_src($imagePath)); ?>" alt="<?= esc($title); ?> image <?= esc((string)($index + 1)); ?>">
                                </a>
                            </figure>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <figure class="update-detail-slide is-active" data-update-gallery-slide>
                            <a href="<?= esc(resolve_image_src($cover, $cover)); ?>" data-fancybox="update-gallery" data-caption="<?= esc($title); ?>">
                                <img src="<?= esc(resolve_image_src($cover, $cover)); ?>" alt="<?= esc($title); ?>">
                            </a>
                        </figure>
                    <?php endif; ?>
                </div>

                <?php if (count($images) > 1): ?>
                    <button type="button" class="update-gallery-btn prev" data-update-gallery-prev aria-label="Previous image">‹</button>
                    <button type="button" class="update-gallery-btn next" data-update-gallery-next aria-label="Next image">›</button>
                    <div class="update-gallery-dots">
                        <?php foreach ($images as $index => $imagePath): ?>
                            <button type="button" class="update-gallery-dot <?= $index === 0 ? 'is-active' : ''; ?>" data-update-gallery-dot="<?= esc((string)$index); ?>" aria-label="Image <?= esc((string)($index + 1)); ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="update-detail-content">
                <h2>About this update</h2>
                <p><?= nl2br(esc($fullDescription !== '' ? $fullDescription : $shortDescription)); ?></p>
            </div>
        </article>

        <aside class="update-detail-sidebar" id="updateShareSection">
            <div class="card update-share-card">
                <h3>Share this update</h3>
                <p>Send this post to members through social channels.</p>
                <?php
                $message = rawurlencode($title . ' - ' . $detailUrl);
                $facebookUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($detailUrl);
                $whatsappUrl = 'https://wa.me/?text=' . $message;
                $instagramCopy = $detailUrl;
                ?>
                <div class="update-share-buttons">
                    <a class="btn btn-success" href="<?= esc($whatsappUrl); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp me-1"></i>WhatsApp</a>
                    <a class="btn btn-primary" href="<?= esc($facebookUrl); ?>" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f me-1"></i>Facebook</a>
                    <button type="button" class="btn btn-outline-secondary" data-copy-link="<?= esc($instagramCopy); ?>"><i class="fa-brands fa-instagram me-1"></i>Instagram</button>
                </div>
                <small class="text-muted d-block mt-2">Instagram sharing opens as copy-link helper because browsers do not support direct Instagram URL posting.</small>
            </div>

            <div class="card update-share-card mt-3">
                <h3>Update details</h3>
                <ul class="update-detail-list">
                    <li><strong>Category:</strong> <?= esc($category); ?></li>
                    <li><strong>Status:</strong> <?= esc(ucfirst($status)); ?></li>
                    <li><strong>Published:</strong> <?= esc($publishAt); ?></li>
                    <li><strong>Views:</strong> <?= esc(number_format($views)); ?></li>
                </ul>
            </div>
        </aside>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-title">
            <h2>More Updates</h2>
            <a class="btn btn-outline" href="<?= esc(base_url('pages/news.php')); ?>">View All</a>
        </div>
        <div class="grid-3">
            <?php
            $related = array_values(array_filter(fetch_all('SELECT * FROM homepage_updates WHERE id <> :id ORDER BY created_at DESC', [':id' => $updateId]), 'is_public_update'));
            $related = array_slice($related, 0, 3);
            foreach ($related as $relatedItem):
                $relatedImages = get_update_images($relatedItem);
                $relatedCover = $relatedImages[0] ?? 'https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=900&q=80';
            ?>
                <article class="card news-card fade-in">
                    <img src="<?= esc(resolve_image_src($relatedCover, $relatedCover)); ?>" alt="<?= esc((string)$relatedItem['title']); ?>">
                    <h3><?= esc((string)$relatedItem['title']); ?></h3>
                    <p class="muted"><?= esc(function_exists('mb_strimwidth') ? mb_strimwidth((string)($relatedItem['short_description'] ?? $relatedItem['description'] ?? ''), 0, 120, '...') : substr((string)($relatedItem['short_description'] ?? $relatedItem['description'] ?? ''), 0, 120)); ?></p>
                    <a class="read-more" href="<?= esc(get_update_url($relatedItem)); ?>">Read More</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../footer.php'; ?>
