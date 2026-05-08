<?php
/**
 * Media Showcase - Dynamic Sections (Admin Managed)
 * Renders a modern responsive media gallery with tabs and video cards.
 */

execute_query('CREATE TABLE IF NOT EXISTS `showcase_sections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_type` VARCHAR(50) NOT NULL,
  `section_name` VARCHAR(255) NOT NULL,
  `section_icon` VARCHAR(100) DEFAULT "fa-film",
  `section_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (section_type),
  INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

execute_query('CREATE TABLE IF NOT EXISTS `showcase_section_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_id` INT NOT NULL,
  `item_title` VARCHAR(255) NOT NULL,
  `item_description` TEXT,
  `item_file` VARCHAR(255),
  `item_thumbnail` VARCHAR(255),
  `item_category` VARCHAR(100),
  `item_date` DATE,
  `item_order` INT DEFAULT 0,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES showcase_sections(id) ON DELETE CASCADE,
  INDEX (section_id),
  INDEX (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$safeFetchSections = static function (): array {
    try {
        return fetch_all('SELECT * FROM showcase_sections WHERE is_active = 1 ORDER BY section_order ASC, id DESC') ?: [];
    } catch (Throwable $e) {
        return [];
    }
};

$safeFetchItems = static function (int $sectionId): array {
    try {
        return fetch_all(
            'SELECT * FROM showcase_section_items WHERE section_id = ? AND is_active = 1 ORDER BY is_featured DESC, item_order ASC, id DESC',
            [$sectionId]
        ) ?: [];
    } catch (Throwable $e) {
        return [];
    }
};

$sections = $safeFetchSections();
if (!$sections) {
    try {
        execute_query('INSERT IGNORE INTO showcase_sections (section_type, section_name, section_icon, section_order, is_active) VALUES (?, ?, ?, ?, 1)', ['videos', 'Latest Videos', 'fa-solid fa-video', 1]);
        execute_query('INSERT IGNORE INTO showcase_sections (section_type, section_name, section_icon, section_order, is_active) VALUES (?, ?, ?, ?, 1)', ['media', 'Media Library', 'fa-solid fa-photo-film', 2]);
        execute_query('INSERT IGNORE INTO showcase_sections (section_type, section_name, section_icon, section_order, is_active) VALUES (?, ?, ?, ?, 1)', ['epaper', 'ePublications', 'fa-regular fa-newspaper', 3]);
        execute_query('INSERT IGNORE INTO showcase_sections (section_type, section_name, section_icon, section_order, is_active) VALUES (?, ?, ?, ?, 1)', ['poster', 'Posters & Banners', 'fa-regular fa-image', 4]);
        $sections = $safeFetchSections();
    } catch (Throwable $e) {
        $sections = [];
    }
}

$parseYoutubeId = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $value) === 1) {
        return $value;
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return '';
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    if (strpos($host, 'youtu.be') !== false) {
        return trim((string)($parts['path'] ?? ''), '/');
    }

    if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        if (!empty($query['v']) && preg_match('/^[a-zA-Z0-9_-]{11}$/', (string)$query['v']) === 1) {
            return (string)$query['v'];
        }
    }

    if (!empty($parts['path']) && strpos((string)$parts['path'], '/embed/') !== false) {
        $chunks = explode('/embed/', (string)$parts['path']);
        return trim((string)($chunks[1] ?? ''), '/');
    }

    return '';
};

$formatDate = static function (?string $date): string {
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : '';
};

$videoThumb = static function (array $item) use ($parseYoutubeId): string {
    $customThumb = trim((string)($item['item_thumbnail'] ?? ''));
    if ($customThumb !== '') {
        return $customThumb;
    }

    $file = trim((string)($item['item_file'] ?? ''));
    $videoId = $parseYoutubeId($file);
    if ($videoId !== '') {
        return 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
    }

    return 'https://via.placeholder.com/640x360?text=Video';
};

$videoIdFromItem = static function (array $item) use ($parseYoutubeId): string {
    $id = $parseYoutubeId((string)($item['item_file'] ?? ''));
    if ($id !== '') {
        return $id;
    }

    return $parseYoutubeId((string)($item['item_thumbnail'] ?? ''));
};
?>

<section class="ms-section section" id="media-showcase-dynamic" aria-labelledby="ms-title">
  <div class="ms-shell container home-shell">
    <header class="ms-header section-title" style="display:block;">
      <h2 id="ms-title">Latest Media &amp; Updates</h2>
      <p>Stay updated with our latest activities, campaigns &amp; publications</p>
    </header>

    <?php if ($sections): ?>
      <nav class="ms-tabs" role="tablist" aria-label="Media showcase tabs">
        <?php foreach ($sections as $index => $section): ?>
          <button
            type="button"
            role="tab"
            class="ms-tab <?= $index === 0 ? 'is-active' : '' ?>"
            data-tab-id="section-<?= (int)$section['id'] ?>"
            aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
          >
            <i class="<?= htmlspecialchars((string)($section['section_icon'] ?? 'fa-solid fa-film')) ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars((string)$section['section_name']) ?></span>
          </button>
        <?php endforeach; ?>
      </nav>

      <div class="ms-panels">
        <?php foreach ($sections as $index => $section): ?>
          <?php
          $sectionType = strtolower((string)$section['section_type']);
          $items = $safeFetchItems((int)$section['id']);
          ?>
          <section class="ms-tab-panel <?= $index === 0 ? 'is-active' : '' ?>" data-tab-panel="section-<?= (int)$section['id'] ?>" role="tabpanel">
            <?php if ($sectionType === 'videos'): ?>
              <?php
              $videos = [];
              foreach ($items as $item) {
                  $id = $videoIdFromItem($item);
                  if ($id === '') {
                      continue;
                  }
                  $item['video_id'] = $id;
                  $item['thumb'] = $videoThumb($item);
                  $videos[] = $item;
              }

              $featured = $videos[0] ?? null;
              foreach ($videos as $video) {
                  if ((int)($video['is_featured'] ?? 0) === 1) {
                      $featured = $video;
                      break;
                  }
              }

              $sideVideos = [];
              foreach ($videos as $video) {
                  if ($featured && (int)$video['id'] === (int)$featured['id']) {
                      continue;
                  }
                  $sideVideos[] = $video;
              }
              ?>

              <?php if ($featured): ?>
                <div class="ms-video-layout">
                  <article
                    class="ms-video-card ms-featured-card"
                    data-video-id="<?= htmlspecialchars((string)$featured['video_id']) ?>"
                    data-video-title="<?= htmlspecialchars((string)$featured['item_title']) ?>"
                    data-video-date="<?= htmlspecialchars($formatDate((string)($featured['item_date'] ?? ''))) ?>"
                  >
                    <div class="ms-card-media">
                      <img src="<?= htmlspecialchars((string)$featured['thumb']) ?>" alt="<?= htmlspecialchars((string)$featured['item_title']) ?>" loading="lazy">
                      <span class="ms-play-badge" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                      <div class="ms-watch-overlay">▶ Watch Now</div>
                    </div>
                    <div class="ms-card-content">
                      <h3 class="ms-card-title"><?= htmlspecialchars((string)$featured['item_title']) ?></h3>
                      <div class="ms-card-meta">
                        <span class="ms-card-date"><i class="fa-regular fa-calendar"></i> <?= htmlspecialchars($formatDate((string)($featured['item_date'] ?? ''))) ?></span>
                        <span><i class="fa-solid fa-tag"></i> Latest</span>
                      </div>
                      <div class="ms-share-bar" data-share-bar>
                        <span class="ms-share-label"><i class="fa-solid fa-share-nodes"></i> Share</span>
                        <div class="ms-share-btns">
                          <a class="ms-share-btn ms-share-whatsapp" data-share="whatsapp" href="#" target="_blank" rel="noopener" aria-label="Share on WhatsApp" title="WhatsApp">
                            <i class="fa-brands fa-whatsapp"></i>
                          </a>
                          <a class="ms-share-btn ms-share-facebook" data-share="facebook" href="#" target="_blank" rel="noopener" aria-label="Share on Facebook" title="Facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                          </a>
                          <a class="ms-share-btn ms-share-twitter" data-share="twitter" href="#" target="_blank" rel="noopener" aria-label="Share on X (Twitter)" title="X / Twitter">
                            <i class="fa-brands fa-x-twitter"></i>
                          </a>
                          <a class="ms-share-btn ms-share-telegram" data-share="telegram" href="#" target="_blank" rel="noopener" aria-label="Share on Telegram" title="Telegram">
                            <i class="fa-brands fa-telegram"></i>
                          </a>
                          <a class="ms-share-btn ms-share-instagram" data-share="instagram" href="#" target="_blank" rel="noopener" aria-label="Share on Instagram" title="Instagram">
                            <i class="fa-brands fa-instagram"></i>
                          </a>
                          <button class="ms-share-btn ms-share-copy" data-share="copy" type="button" aria-label="Copy link" title="Copy link">
                            <i class="fa-solid fa-link"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </article>

                  <div class="ms-playlist">
                    <header class="ms-playlist-header">
                      <span><i class="fa-solid fa-list" aria-hidden="true"></i> Playlist</span>
                      <a href="#" class="ms-view-all-link">View All &rarr;</a>
                    </header>
                    <div class="ms-playlist-track">
                      <?php foreach ($sideVideos as $video): ?>
                        <?php
                        $duration = trim((string)($video['item_description'] ?? ''));
                        if ($duration === '' || strlen($duration) > 8) $duration = '';
                        $videoDateStr = htmlspecialchars($formatDate((string)($video['item_date'] ?? '')));
                        ?>
                        <article
                          class="ms-video-card ms-mini-card"
                          data-video-id="<?= htmlspecialchars((string)$video['video_id']) ?>"
                          data-video-title="<?= htmlspecialchars((string)$video['item_title']) ?>"
                          data-video-date="<?= $videoDateStr ?>"
                        >
                          <div class="ms-card-media">
                            <img src="<?= htmlspecialchars((string)$video['thumb']) ?>" alt="<?= htmlspecialchars((string)$video['item_title']) ?>" loading="lazy">
                            <?php if ($duration !== ''): ?>
                              <span class="ms-mini-duration"><?= htmlspecialchars($duration) ?></span>
                            <?php endif; ?>
                          </div>
                          <div class="ms-card-content">
                            <h4 class="ms-card-title"><?= htmlspecialchars((string)$video['item_title']) ?></h4>
                            <?php if ($videoDateStr !== ''): ?>
                              <span class="ms-card-date"><?= $videoDateStr ?></span>
                            <?php endif; ?>
                          </div>
                        </article>
                      <?php endforeach; ?>
                      <?php if (!$sideVideos): ?>
                        <p class="ms-playlist-empty">No other videos in playlist.</p>
                      <?php endif; ?>
                    </div>
                    <div class="ms-playlist-scroll-btns">
                      <button class="ms-scroll-btn scroll-up" aria-label="Scroll Up" type="button"><i class="fa-solid fa-chevron-up"></i></button>
                      <button class="ms-scroll-btn scroll-down" aria-label="Scroll Down" type="button"><i class="fa-solid fa-chevron-down"></i></button>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="alert alert-info">No videos available in this section yet.</div>
              <?php endif; ?>
            <?php else: ?>
              <div class="ms-content-grid">
                <?php foreach ($items as $item): ?>
                  <?php
                  $thumb = trim((string)($item['item_thumbnail'] ?? ''));
                  $file = trim((string)($item['item_file'] ?? ''));
                  if ($thumb === '') {
                      $thumb = $file;
                  }
                  if ($thumb === '') {
                      $thumb = 'https://via.placeholder.com/640x360?text=Media';
                  }
                  $fullImage = $file !== '' ? $file : $thumb;
                  ?>
                  <article class="ms-media-card">
                    <a href="<?= htmlspecialchars($fullImage) ?>" class="ms-card-media" data-fancybox="gallery-<?= (int)$section['id'] ?>" data-caption="<?= htmlspecialchars((string)$item['item_title']) ?>" style="display: block;">
                      <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars((string)$item['item_title']) ?>" loading="lazy">
                      <div class="ms-watch-overlay">View Item</div>
                    </a>
                    <div class="ms-card-content">
                      <h4 class="ms-card-title"><?= htmlspecialchars((string)$item['item_title']) ?></h4>
                      <p class="ms-card-date"><?= htmlspecialchars($formatDate((string)($item['item_date'] ?? ''))) ?></p>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
              <?php if (!$items): ?>
                <div class="alert alert-info">No items available in this section yet.</div>
              <?php endif; ?>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-warning">
        No showcase sections configured. Please add sections from the admin panel.
      </div>
    <?php endif; ?>

    <div class="ms-footer-actions">
      <a class="ms-view-all" href="<?= htmlspecialchars(base_url('pages/news.php')) ?>">View All &rarr;</a>
    </div>
  </div>
</section>

<!-- Cinematic Video Modal -->
<div class="ms-video-modal" id="ms-cinematic-modal" aria-hidden="true" role="dialog">
  <div class="ms-modal-backdrop" data-modal-close></div>
  <div class="ms-modal-dialog">
    <button class="ms-modal-close" data-modal-close aria-label="Close video" type="button"><i class="fa-solid fa-xmark"></i></button>
    <div class="ms-modal-body">
      <iframe class="ms-modal-iframe" id="ms-modal-iframe" src="" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
  </div>
</div>

<!-- JS handled by assets/js/media-showcase-optionb.js -->