<?php
require_once __DIR__ . '/../db.php';
$leaders = fetch_all("SELECT * FROM members WHERE status = 'approved' ORDER BY created_at DESC LIMIT 12");
require_once __DIR__ . '/../header.php';
?>

<section class="container page-hero fade-in">
    <h1>Leadership Team</h1>
    <p>District coordinators and committee members steering APCSNSC's public campaigns and welfare efforts.</p>
</section>

<section class="section">
    <div class="container grid-3">
        <?php foreach ($leaders as $leader): ?>
            <article class="card fade-in">
                <img src="<?= esc(!empty($leader['photo']) ? base_url($leader['photo']) : 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=700&q=80'); ?>" alt="<?= esc($leader['name']); ?>" style="height:220px; object-fit:cover; border-radius:12px;">
                <h3><?= esc($leader['name']); ?></h3>
                <p><strong>Role:</strong> <?= esc($leader['role']); ?></p>
                <p><strong>District:</strong> <?= esc($leader['district']); ?></p>
                <p class="muted"><strong>Experience:</strong> <?= esc((string)$leader['experience']); ?> years</p>
            </article>
        <?php endforeach; ?>

        <?php if (!$leaders): ?>
            <article class="card fade-in">
                <h3>Leadership Data Pending</h3>
                <p>Approved leadership members will appear here once records are updated from admin panel.</p>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../footer.php'; ?>
