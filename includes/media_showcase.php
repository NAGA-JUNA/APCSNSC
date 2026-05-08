<?php
// Media Showcase include
// Place this file where you want the section to appear, e.g. in index.php:
// <?php include __DIR__ . '/includes/media_showcase.php'; ?>

// Sample data (replace with DB queries as needed)
$videos = [
    ['id' => 'dQw4w9WgXcQ', 'title' => 'APCSNSC: Welcome Message', 'date' => '2026-04-10', 'thumb' => 'assets/images/sample1.jpg'],
    ['id' => '9bZkp7q19f0', 'title' => 'Event Highlights: District Meet', 'date' => '2026-03-22', 'thumb' => 'assets/images/sample2.jpg'],
    ['id' => '3JZ_D3ELwOQ', 'title' => 'Community Outreach', 'date' => '2026-02-14', 'thumb' => 'assets/images/sample3.jpg'],
    ['id' => 'V-_O7nl0Ii0', 'title' => 'Training Workshop Recap', 'date' => '2026-01-05', 'thumb' => 'assets/images/sample4.jpg'],
];

$gallery = [
    ['img' => 'assets/images/gallery1.jpg', 'cat' => 'Events', 'title' => 'March Rally'],
    ['img' => 'assets/images/gallery2.jpg', 'cat' => 'Meetings', 'title' => 'Board Meeting'],
    ['img' => 'assets/images/gallery3.jpg', 'cat' => 'Campaigns', 'title' => 'Awareness Drive'],
    ['img' => 'assets/images/gallery4.jpg', 'cat' => 'Events', 'title' => 'Volunteer Day'],
    ['img' => 'assets/images/gallery5.jpg', 'cat' => 'Meetings', 'title' => 'District Office'],
];

$news = [
    ['date' => '2026-04-26', 'title' => 'Membership renewals open', 'cat' => 'UPDATES'],
    ['date' => '2026-04-20', 'title' => 'Legal support clinic scheduled', 'cat' => 'LEGAL'],
    ['date' => '2026-03-15', 'title' => 'Campaign kickoff', 'cat' => 'CAMPAIGNS'],
];

// Enqueue CSS and JS (safe: avoids duplicate if header system used)
if (!function_exists('base_url')) {
    function base_url($p = '') { return '/' . ltrim($p, '/'); }
}

$cssPath = base_url('assets/css/media-showcase.css');
$jsPath = base_url('assets/js/media-showcase.js');
?>
<link rel="stylesheet" href="<?= htmlspecialchars($cssPath) ?>?v=1">

<section class="media-showcase" aria-labelledby="ms-heading">
  <div class="ms-container">
    <header class="ms-header">
      <div class="ms-kicker">MEDIA SHOWCASE</div>
      <h2 id="ms-heading">Latest Videos, Gallery &amp; Breaking News</h2>
      <p class="ms-sub">Stay updated with our latest videos, event highlights, photo galleries and important updates.</p>
    </header>

    <div class="ms-grid">
      <div class="ms-left">
        <div class="ms-card ms-featured" data-module="featured">
          <div class="ms-media">
            <div class="ms-skeleton ms-video-skel"></div>
            <div class="ms-iframe-wrap">
              <iframe id="ms-featured-iframe" loading="lazy" src="https://www.youtube.com/embed/<?= htmlspecialchars($videos[0]['id']) ?>?rel=0" title="Featured video" frameborder="0" allowfullscreen></iframe>
            </div>
          </div>
          <div class="ms-meta">
            <h3 class="ms-title"><?= htmlspecialchars($videos[0]['title']) ?></h3>
            <time class="ms-date" datetime="<?= htmlspecialchars($videos[0]['date']) ?>"><?= date('F j, Y', strtotime($videos[0]['date'])) ?></time>
            <div class="ms-actions">
              <a class="btn ms-btn-outline" href="https://www.youtube.com/watch?v=<?= htmlspecialchars($videos[0]['id']) ?>" target="_blank" rel="noopener">Watch on YouTube</a>
            </div>
          </div>
        </div>
      </div>

      <aside class="ms-right">
        <div class="ms-card ms-playlist">
          <h4 class="ms-widget-title">Latest Videos</h4>
          <div class="ms-playlist-list" role="list">
            <?php foreach(array_slice($videos, 0, 4) as $i => $v): ?>
            <button class="ms-playlist-item<?= $i===0? ' is-active':'' ?>" data-video-id="<?= htmlspecialchars($v['id']) ?>" data-video-title="<?= htmlspecialchars($v['title']) ?>" data-video-date="<?= htmlspecialchars($v['date']) ?>" aria-label="Play <?= htmlspecialchars($v['title']) ?>">
              <img data-src="<?= htmlspecialchars($v['thumb']) ?>" alt="<?= htmlspecialchars($v['title']) ?>" class="ms-thumb lazy">
              <div class="ms-playlist-copy">
                <strong><?= htmlspecialchars($v['title']) ?></strong>
                <small><?= date('M j, Y', strtotime($v['date'])) ?></small>
              </div>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>
    </div>

    <div class="ms-tabs">
      <nav class="ms-tabs-nav" role="tablist">
        <button class="ms-tab is-active" data-tab="videos">Videos</button>
        <button class="ms-tab" data-tab="gallery">Gallery</button>
        <button class="ms-tab" data-tab="news">Breaking News</button>
      </nav>

      <div class="ms-tabs-panels">
        <div class="ms-panel ms-panel-videos is-open" data-panel="videos">
          <div class="ms-videos-grid">
            <?php foreach($videos as $v): ?>
            <article class="ms-media-card">
              <div class="ms-media-thumb">
                <img data-src="<?= htmlspecialchars($v['thumb']) ?>" alt="<?= htmlspecialchars($v['title']) ?>" class="lazy">
              </div>
              <div class="ms-media-body">
                <strong><?= htmlspecialchars($v['title']) ?></strong>
                <small><?= date('M j, Y', strtotime($v['date'])) ?></small>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="ms-panel" data-panel="gallery">
          <div class="ms-gallery-controls">
            <div class="ms-filters">
              <button class="ms-filter is-active" data-filter="All">All</button>
              <button class="ms-filter" data-filter="Events">Events</button>
              <button class="ms-filter" data-filter="Meetings">Meetings</button>
              <button class="ms-filter" data-filter="Campaigns">Campaigns</button>
            </div>
            <a class="btn ms-btn-link" href="#">View All Gallery</a>
          </div>
          <div class="ms-gallery-viewport">
            <div class="ms-gallery-track">
              <?php foreach($gallery as $g): ?>
              <figure class="ms-gallery-card" data-cat="<?= htmlspecialchars($g['cat']) ?>">
                <a href="<?= htmlspecialchars($g['img']) ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($g['title']) ?>" style="display: block;">
                  <img data-src="<?= htmlspecialchars($g['img']) ?>" alt="<?= htmlspecialchars($g['title']) ?>" class="lazy">
                </a>
                <figcaption>
                  <span><?= htmlspecialchars($g['title']) ?></span>
                </figcaption>
              </figure>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="ms-panel" data-panel="news">
          <div class="ms-news-grid">
            <?php foreach($news as $n): ?>
            <article class="ms-news-card">
              <div class="ms-news-badge"><?= date('d M', strtotime($n['date'])) ?></div>
              <div class="ms-news-body">
                <strong><?= htmlspecialchars($n['title']) ?></strong>
                <div class="ms-news-meta"><span class="ms-news-cat"><?= htmlspecialchars($n['cat']) ?></span></div>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
          <div class="ms-news-actions"><a class="btn ms-btn-link" href="#">View All News</a></div>
        </div>
      </div>
    </div>

  </div>
</section>

<script src="<?= htmlspecialchars($jsPath) ?>?v=1" defer></script>

<?php // End include
