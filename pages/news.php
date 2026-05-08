<?php
require_once __DIR__ . '/../db.php';
$news = array_values(array_filter(fetch_all('SELECT * FROM homepage_updates ORDER BY created_at DESC'), 'is_public_update'));
require_once __DIR__ . '/../header.php';
?>

<section class="container page-hero fade-in">
    <h1>News & Updates</h1>
    <p>Official statements, district meeting highlights, and struggle committee action updates.</p>
</section>

<section class="section">
    <div class="container grid-3">
        <?php foreach ($news as $item): ?>
            <?php
            $images = get_update_images($item);
            $cover = $images[0] ?? 'https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=900&q=80';
            $excerpt = (string)($item['short_description'] ?? $item['description'] ?? '');
            $excerpt = function_exists('mb_strimwidth')
                ? mb_strimwidth($excerpt, 0, 140, '...')
                : (strlen($excerpt) > 140 ? substr($excerpt, 0, 140) . '...' : $excerpt);
            ?>
            <article class="card news-card fade-in">
                <img src="<?= esc(resolve_image_src($cover, 'https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=900&q=80')); ?>" alt="<?= esc($item['title']); ?>">
                <h3><?= esc($item['title']); ?></h3>
                <p class="muted"><?= esc($excerpt); ?></p>
                <small class="muted"><?= esc(date('d M Y', strtotime((string)$item['created_at']))); ?></small>
                <a class="read-more d-block mt-2" href="<?= esc(get_update_url($item)); ?>">Read More</a>
            </article>
        <?php endforeach; ?>

        <?php if (!$news): ?>
            <article class="card fade-in">
                <h3>No Updates Yet</h3>
                <p>News will appear here once updates are published through the admin dashboard.</p>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../footer.php'; ?>
