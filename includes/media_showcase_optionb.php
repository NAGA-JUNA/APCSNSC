<?php
/**
 * Media Showcase - Option B
 * Modern card-based dynamic layout with 70/30 grid, tabs, and responsive design
 * 
 * Usage: <?php include __DIR__ . '/includes/media_showcase_optionb.php'; ?>
 */

// ============ DATA LAYER ============
// Replace these with DB queries for production

$videos = [
    [
        'id' => 1,
        'video_id' => 'dQw4w9WgXcQ',
        'title' => 'APCSNSC: Welcome Message',
        'date' => '2026-04-10',
        'thumb' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
        'featured' => true,
    ],
    [
        'id' => 2,
        'video_id' => '9bZkp7q19f0',
        'title' => 'Event Highlights: District Meet',
        'date' => '2026-03-22',
        'thumb' => 'https://img.youtube.com/vi/9bZkp7q19f0/mqdefault.jpg',
        'featured' => false,
    ],
    [
        'id' => 3,
        'video_id' => '3JZ_D3ELwOQ',
        'title' => 'Community Outreach Program',
        'date' => '2026-02-14',
        'thumb' => 'https://img.youtube.com/vi/3JZ_D3ELwOQ/mqdefault.jpg',
        'featured' => false,
    ],
    [
        'id' => 4,
        'video_id' => 'V-_O7nl0Ii0',
        'title' => 'Training Workshop Recap',
        'date' => '2026-01-05',
        'thumb' => 'https://img.youtube.com/vi/V-_O7nl0Ii0/mqdefault.jpg',
        'featured' => false,
    ],
];

$gallery = [
    ['img' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=500&q=60', 'cat' => 'Events', 'title' => 'March Rally - District Gathering'],
    ['img' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=500&q=60', 'cat' => 'Meetings', 'title' => 'Board Meeting 2026'],
    ['img' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=500&q=60', 'cat' => 'Campaigns', 'title' => 'Awareness Drive'],
    ['img' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=500&q=60', 'cat' => 'Events', 'title' => 'Volunteer Day'],
    ['img' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=500&q=60', 'cat' => 'Meetings', 'title' => 'District Office Visit'],
    ['img' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=500&q=60', 'cat' => 'Campaigns', 'title' => 'Member Training Session'],
];

$news = [
    ['date' => '2026-04-26', 'title' => 'Membership renewals now open for all districts', 'cat' => 'UPDATES', 'color' => 'blue'],
    ['date' => '2026-04-20', 'title' => 'Legal support clinic scheduled for next week', 'cat' => 'LEGAL', 'color' => 'red'],
    ['date' => '2026-04-15', 'title' => 'Campaign kickoff event announced', 'cat' => 'CAMPAIGNS', 'color' => 'green'],
    ['date' => '2026-04-10', 'title' => 'District leaders meet for quarterly review', 'cat' => 'MEETINGS', 'color' => 'purple'],
    ['date' => '2026-04-05', 'title' => 'New ID card design approved', 'cat' => 'UPDATES', 'color' => 'blue'],
    ['date' => '2026-03-28', 'title' => 'Member welfare program expanded', 'cat' => 'PROGRAMS', 'color' => 'orange'],
];

// ============ HELPER FUNCTIONS ============

if (!function_exists('base_url')) {
    function base_url($p = '') { 
        return '/' . ltrim($p, '/'); 
    }
}

$formatDate = function($dateStr) {
    $time = strtotime($dateStr);
    return date('M d, Y', $time);
};

$getFeatured = function($vids) {
    foreach ($vids as $v) {
        if ($v['featured']) return $v;
    }
    return $vids[0] ?? null;
};

$cssPath = base_url('assets/css/media-showcase-optionb.css');
$jsPath = base_url('assets/js/media-showcase-optionb.js');

$featured = $getFeatured($videos);
$cssVer = filemtime(__DIR__ . '/../assets/css/media-showcase-optionb.css') ?? time();
$jsVer = filemtime(__DIR__ . '/../assets/js/media-showcase-optionb.js') ?? time();
?>

<!-- ============ CSS LINK ============ -->
<link rel="stylesheet" href="<?= htmlspecialchars($cssPath) ?>?v=<?= $cssVer ?>">

<!-- ============ MEDIA SHOWCASE SECTION - OPTION B ============ -->
<section class="ms-section" id="media-showcase-optionb" aria-labelledby="ms-title">
  <div class="ms-wrapper">
    
    <!-- Header -->
    <div class="ms-header">
      <span class="ms-badge">MEDIA SHOWCASE</span>
      <h2 id="ms-title">Latest Videos, Gallery &amp; Breaking News</h2>
      <p class="ms-subtitle">Stay updated with our latest videos, event highlights, photo galleries and important updates.</p>
    </div>
    
    <!-- Top Grid: 70% Featured Video (Left) + 30% Video List (Right) -->
    <div class="ms-top-grid">
      
      <!-- LEFT: Featured Video Card (70%) -->
      <div class="ms-featured-card ms-card">
        <div class="ms-video-container">
          <!-- Skeleton loader (shown while iframe loads) -->
          <div class="ms-skeleton-loader" data-skeleton="featured"></div>
          
          <!-- YouTube iframe -->
          <iframe 
            class="ms-video-iframe" 
            id="featured-video"
            src="https://www.youtube.com/embed/<?= htmlspecialchars($featured['video_id'] ?? 'dQw4w9WgXcQ') ?>?rel=0" 
            title="Featured video"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen>
          </iframe>
          
          <!-- Play overlay button -->
          <div class="ms-video-overlay" data-play-overlay>
            <div class="ms-play-btn">
              <i class="fa-solid fa-play"></i>
            </div>
          </div>
        </div>
        
        <!-- Video metadata -->
        <div class="ms-video-meta">
          <h3 class="ms-video-title" id="featured-title"><?= htmlspecialchars($featured['title'] ?? 'Featured Video') ?></h3>
          <time class="ms-video-date" id="featured-date"><?= $formatDate($featured['date'] ?? date('Y-m-d')) ?></time>
          <div class="ms-video-actions">
            <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($featured['video_id'] ?? 'dQw4w9WgXcQ') ?>" 
               target="_blank" 
               rel="noopener"
               class="ms-btn ms-btn-outline">
              <i class="fa-brands fa-youtube"></i> Watch on YouTube
            </a>
          </div>
        </div>
      </div>
      
      <!-- RIGHT: Video Playlist (30%) - 3-4 items -->
      <aside class="ms-playlist-card ms-card">
        <h4 class="ms-widget-title">Latest Videos</h4>
        <div class="ms-playlist" role="list" data-playlist>
          <?php foreach (array_slice($videos, 0, 4) as $i => $v): ?>
          <button 
            class="ms-playlist-item <?= $i === 0 ? 'is-active' : '' ?>" 
            data-video-id="<?= htmlspecialchars($v['video_id']) ?>"
            data-video-title="<?= htmlspecialchars($v['title']) ?>"
            data-video-date="<?= htmlspecialchars($v['date']) ?>"
            aria-label="Play <?= htmlspecialchars($v['title']) ?>">
            <img 
              src="<?= htmlspecialchars($v['thumb']) ?>" 
              alt="<?= htmlspecialchars($v['title']) ?>"
              class="ms-playlist-thumb"
              loading="lazy">
            <div class="ms-playlist-info">
              <strong><?= htmlspecialchars($v['title']) ?></strong>
              <small><?= $formatDate($v['date']) ?></small>
            </div>
          </button>
          <?php endforeach; ?>
        </div>
      </aside>
    </div>
    
    <!-- TABS SECTION -->
    <div class="ms-tabs-section">
      
      <!-- Tab Navigation -->
      <nav class="ms-tabs-nav" role="tablist">
        <button class="ms-tab is-active" role="tab" data-tab-id="videos" aria-selected="true" aria-controls="ms-panel-videos">Videos</button>
        <button class="ms-tab" role="tab" data-tab-id="gallery" aria-selected="false" aria-controls="ms-panel-gallery">Gallery</button>
        <button class="ms-tab" role="tab" data-tab-id="news" aria-selected="false" aria-controls="ms-panel-news">Breaking News</button>
      </nav>
      
      <!-- Tab Panels -->
      <div class="ms-tabs-content">
        
        <!-- VIDEOS TAB -->
        <div class="ms-tab-panel is-active" id="ms-panel-videos" data-tab-panel="videos" role="tabpanel">
          <div class="ms-videos-grid">
            <?php foreach ($videos as $v): ?>
            <article class="ms-video-card ms-card">
              <div class="ms-thumb-wrapper">
                <img 
                  src="<?= htmlspecialchars($v['thumb']) ?>" 
                  alt="<?= htmlspecialchars($v['title']) ?>"
                  class="ms-thumb"
                  loading="lazy">
                <div class="ms-thumb-overlay">
                  <i class="fa-solid fa-play"></i>
                </div>
              </div>
              <div class="ms-card-body">
                <h4><?= htmlspecialchars($v['title']) ?></h4>
                <time><?= $formatDate($v['date']) ?></time>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </div>
        
        <!-- GALLERY TAB -->
        <div class="ms-tab-panel" id="ms-panel-gallery" data-tab-panel="gallery" role="tabpanel">
          <!-- Filter chips -->
          <div class="ms-gallery-filters" data-gallery-filters>
            <button class="ms-filter-chip is-active" data-filter="all">All</button>
            <button class="ms-filter-chip" data-filter="Events">Events</button>
            <button class="ms-filter-chip" data-filter="Meetings">Meetings</button>
            <button class="ms-filter-chip" data-filter="Campaigns">Campaigns</button>
          </div>
          
          <!-- Gallery track (horizontal scroll) -->
          <div class="ms-gallery-viewport">
            <div class="ms-gallery-track" data-gallery-track>
              <?php foreach ($gallery as $g): ?>
              <figure class="ms-gallery-card" data-category="<?= strtolower(htmlspecialchars($g['cat'])) ?>">
                <a href="<?= htmlspecialchars($g['img']) ?>" class="ms-gallery-image-wrap" data-fancybox="gallery" data-caption="<?= htmlspecialchars($g['title']) ?>" style="display: block;">
                  <img 
                    src="<?= htmlspecialchars($g['img']) ?>" 
                    alt="<?= htmlspecialchars($g['title']) ?>"
                    class="ms-gallery-image"
                    loading="lazy">
                  <div class="ms-gallery-overlay">
                    <i class="fa-solid fa-magnifying-glass"></i>
                  </div>
                </a>
                <figcaption><?= htmlspecialchars($g['title']) ?></figcaption>
              </figure>
              <?php endforeach; ?>
            </div>
          </div>
          
          <!-- View All button -->
          <div class="ms-gallery-footer">
            <a href="<?= base_url('pages/news.php') ?>" class="ms-btn ms-btn-outline">View All Gallery</a>
          </div>
        </div>
        
        <!-- BREAKING NEWS TAB - Card-based layout (2-3 columns) -->
        <div class="ms-tab-panel" id="ms-panel-news" data-tab-panel="news" role="tabpanel">
          <div class="ms-news-grid">
            <?php foreach ($news as $n): ?>
            <article class="ms-news-card ms-card" data-category="<?= strtolower(htmlspecialchars($n['cat'])) ?>">
              <div class="ms-news-date-badge"><?= date('d M', strtotime($n['date'])) ?></div>
              <div class="ms-news-body">
                <h4><?= htmlspecialchars($n['title']) ?></h4>
                <div class="ms-news-footer">
                  <span class="ms-news-category ms-cat-<?= strtolower(htmlspecialchars($n['color'])) ?>">
                    <?= htmlspecialchars($n['cat']) ?>
                  </span>
                </div>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
          
          <!-- View All button -->
          <div class="ms-news-actions">
            <a href="<?= base_url('pages/news.php') ?>" class="ms-btn ms-btn-outline">View All News</a>
          </div>
        </div>
      </div>
    </div>
    
  </div>
</section>

<!-- ============ JS LINK ============ -->
<script src="<?= htmlspecialchars($jsPath) ?>?v=<?= $jsVer ?>" defer></script>
