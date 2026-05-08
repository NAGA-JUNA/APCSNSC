<?php
require_once __DIR__ . '/../db.php';
$districts = fetch_all('SELECT d.*, (SELECT COUNT(*) FROM members m WHERE m.district = d.name) AS total_members FROM homepage_districts d ORDER BY d.name ASC');
require_once __DIR__ . '/../header.php';
?>

<section class="container page-hero fade-in">
    <h1>District Chapters</h1>
    <p>APCSNSC reaches nurses in every district through volunteer teams and local organizing units.</p>
</section>

<section class="section">
    <div class="container grid-3">
        <?php foreach ($districts as $district): ?>
            <article class="card district-card fade-in">
                <img src="<?= esc(!empty($district['image']) ? base_url($district['image']) : 'https://images.unsplash.com/photo-1566054757965-8c4085344f7f?auto=format&fit=crop&w=900&q=80'); ?>" alt="<?= esc($district['name']); ?>">
                <h3><?= esc($district['name']); ?></h3>
                <p class="muted">Registered Members: <?= esc((string)$district['total_members']); ?></p>
            </article>
        <?php endforeach; ?>

        <?php if (!$districts): ?>
            <article class="card fade-in">
                <h3>No District Data</h3>
                <p>Add districts from admin panel to display district chapters here.</p>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../footer.php'; ?>
