<?php
$jsFile = __DIR__ . '/assets/js/main.js';
$jsVer = file_exists($jsFile) ? (string)filemtime($jsFile) : (string)time();
$translations = get_translations();

$logoCandidates = [
    'uploads/logo.png',
    'uploads/logo.jpg',
    'uploads/logo.jpeg',
    'uploads/APCSNSC-logo.png',
    'uploads/APCSNSC-logo.jpg',
    'uploads/APCSNSC-logo.jpeg',
    'uploads/site-logo.png',
    'uploads/site-logo.jpg',
    'uploads/site-logo.jpeg',
];
$siteLogo = null;
foreach ($logoCandidates as $candidate) {
    if (file_exists(__DIR__ . '/' . $candidate)) {
        $siteLogo = base_url($candidate);
        break;
    }
}
?>
</main>

<footer class="site-footer">

    <!-- ===================== DESKTOP FOOTER ===================== -->
    <div class="footer-desktop">

        <!-- Main grid -->
        <div class="container footer-d-grid">

            <!-- Brand column -->
            <div class="footer-d-brand">
                <div class="footer-d-brand-top">
                    <?php if ($siteLogo !== null): ?>
                        <img src="<?= esc($siteLogo); ?>" alt="APCSNSC logo" class="footer-d-logo">
                    <?php else: ?>
                        <span class="footer-d-logo-placeholder"><i class="fa-solid fa-shield-halved"></i></span>
                    <?php endif; ?>
                    <div class="footer-d-brand-name">
                        <strong>APCSNSC</strong>
                        <span>Andhra Pradesh Contract<br>Staff Nurses' Sangam</span>
                    </div>
                </div>
                <p class="footer-d-brand-desc"><?= t('footer_apcsnsc_description', $translations); ?></p>
                <hr class="footer-d-divider">
                <p class="footer-d-follow-label">Follow Us</p>
                <div class="footer-d-socials">
                    <a href="#" aria-label="Facebook" class="footer-d-social footer-d-social-fb"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="X" class="footer-d-social footer-d-social-x"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#" aria-label="YouTube" class="footer-d-social footer-d-social-yt"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#" aria-label="Instagram" class="footer-d-social footer-d-social-ig"><i class="fa-brands fa-instagram"></i></a>
                </div>
            </div>

            <!-- Quick Links column -->
            <div class="footer-d-col">
                <h4 class="footer-d-col-heading">
                    <span class="footer-d-col-icon footer-d-col-icon-green"><i class="fa-solid fa-link"></i></span>
                    <?= t('quick_links', $translations); ?>
                </h4>
                <ul class="footer-d-link-list">
                    <li>
                        <a href="<?= esc(base_url('index.php')); ?>">
                            <?= t('home', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= esc(base_url('pages/about.php')); ?>">
                            <?= t('about', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= esc(base_url('pages/districts.php')); ?>">
                            <?= t('districts', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= esc(base_url('pages/news.php')); ?>">
                            <?= t('news_and_updates', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Support column -->
            <div class="footer-d-col">
                <h4 class="footer-d-col-heading">
                    <span class="footer-d-col-icon footer-d-col-icon-teal"><i class="fa-solid fa-headset"></i></span>
                    <?= t('support', $translations); ?>
                </h4>
                <ul class="footer-d-link-list">
                    <li>
                        <a href="<?= esc(base_url('pages/contact.php')); ?>">
                            <i class="fa-solid fa-pen-to-square footer-d-link-icon"></i>
                            <?= t('raise_complaint', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= esc(base_url('register.php')); ?>">
                            <i class="fa-solid fa-user-plus footer-d-link-icon"></i>
                            <?= t('member_registration', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= esc(base_url('member_dashboard.php')); ?>">
                            <i class="fa-solid fa-id-card footer-d-link-icon"></i>
                            <?= t('download_id_card', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= esc(base_url('admin/login.php')); ?>">
                            <i class="fa-solid fa-lock footer-d-link-icon"></i>
                            <?= t('admin_login', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fa-solid fa-circle-question footer-d-link-icon"></i>
                            <?= t('faqs', $translations); ?>
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Contact Info column -->
            <div class="footer-d-col">
                <h4 class="footer-d-col-heading">
                    <span class="footer-d-col-icon footer-d-col-icon-blue"><i class="fa-solid fa-phone"></i></span>
                    <?= t('contact_info', $translations); ?>
                </h4>
                <ul class="footer-d-contact-list">
                    <li>
                        <span class="footer-d-contact-icon footer-d-ci-loc"><i class="fa-solid fa-location-dot"></i></span>
                        <span><?= t('address_line_1', $translations); ?></span>
                    </li>
                    <li>
                        <span class="footer-d-contact-icon footer-d-ci-email"><i class="fa-solid fa-envelope"></i></span>
                        <span>support@APCSNSC.org</span>
                    </li>
                    <li>
                        <span class="footer-d-contact-icon footer-d-ci-phone"><i class="fa-solid fa-phone"></i></span>
                        <span>+91 99999 99999</span>
                    </li>
                    <li>
                        <span class="footer-d-contact-icon footer-d-ci-clock"><i class="fa-solid fa-clock"></i></span>
                        <span>Mon &ndash; Sat: 9:00 AM &ndash; 6:00 PM</span>
                    </li>
                </ul>
            </div>

        </div><!-- /footer-d-grid -->

        <!-- Bottom bar -->
        <div class="footer-d-bottom">
            <div class="container footer-d-bottom-inner">
                <span class="footer-d-bottom-shield"><i class="fa-solid fa-shield-halved"></i></span>
                <div class="footer-d-bottom-copy">
                    <span>&copy; <?= date('Y'); ?> APCSNSC. <?= t('copyright', $translations); ?></span>
                    <span class="footer-d-bottom-links">
                        <a href="#">Privacy Policy</a>
                        <span class="footer-d-sep">|</span>
                        <a href="#">Terms &amp; Conditions</a>
                    </span>
                </div>
                <span class="footer-d-bottom-deco"><i class="fa-solid fa-handshake-angle"></i></span>
            </div>
        </div>

    </div><!-- /footer-desktop -->

    <!-- ===================== MOBILE FOOTER ===================== -->
    <div class="footer-mobile">

        <!-- Brand block -->
        <div class="footer-m-brand">
            <div class="footer-m-brand-left">
                <?php if ($siteLogo !== null): ?>
                    <img src="<?= esc($siteLogo); ?>" alt="APCSNSC logo" class="footer-m-logo">
                <?php else: ?>
                    <span class="footer-m-logo-placeholder">A</span>
                <?php endif; ?>
                <div class="footer-m-brand-text">
                    <strong>APCSNSC</strong>
                    <span><?= t('footer_apcsnsc_description', $translations); ?></span>
                </div>
            </div>
            <div class="footer-m-socials">
                <a href="#" aria-label="Facebook" class="footer-social-btn footer-social-fb"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" aria-label="X" class="footer-social-btn footer-social-x"><i class="fa-brands fa-x-twitter"></i></a>
                <a href="#" aria-label="YouTube" class="footer-social-btn footer-social-yt"><i class="fa-brands fa-youtube"></i></a>
                <a href="#" aria-label="Instagram" class="footer-social-btn footer-social-ig"><i class="fa-brands fa-instagram"></i></a>
            </div>
        </div>

        <!-- Quick Links accordion -->
        <div class="footer-m-accordion" data-accordion>
            <button class="footer-m-acc-header" data-acc-toggle aria-expanded="false">
                <span class="footer-m-acc-icon-wrap footer-acc-green"><i class="fa-solid fa-link"></i></span>
                <span class="footer-m-acc-title"><?= t('quick_links', $translations); ?></span>
                <i class="fa-solid fa-chevron-down footer-m-acc-chevron"></i>
            </button>
            <div class="footer-m-acc-body">
                <div class="footer-m-icon-grid">
                    <a href="<?= esc(base_url('index.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-house"></i></span>
                        <span><?= t('home', $translations); ?></span>
                    </a>
                    <a href="<?= esc(base_url('pages/about.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-circle-info"></i></span>
                        <span><?= t('about', $translations); ?></span>
                    </a>
                    <a href="<?= esc(base_url('pages/districts.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-landmark"></i></span>
                        <span><?= t('districts', $translations); ?></span>
                    </a>
                    <a href="<?= esc(base_url('pages/news.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-newspaper"></i></span>
                        <span><?= t('news', $translations); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Support accordion -->
        <div class="footer-m-accordion" data-accordion>
            <button class="footer-m-acc-header" data-acc-toggle aria-expanded="false">
                <span class="footer-m-acc-icon-wrap footer-acc-teal"><i class="fa-solid fa-headset"></i></span>
                <span class="footer-m-acc-title"><?= t('support', $translations); ?></span>
                <i class="fa-solid fa-chevron-down footer-m-acc-chevron"></i>
            </button>
            <div class="footer-m-acc-body">
                <div class="footer-m-icon-grid footer-m-icon-grid-5">
                    <a href="<?= esc(base_url('pages/contact.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-pen-to-square"></i></span>
                        <span><?= t('raise_complaint', $translations); ?></span>
                    </a>
                    <a href="<?= esc(base_url('register.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-user-plus"></i></span>
                        <span><?= t('register', $translations); ?></span>
                    </a>
                    <a href="<?= esc(base_url('member_dashboard.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-id-card"></i></span>
                        <span><?= t('id_card', $translations); ?></span>
                    </a>
                    <a href="<?= esc(base_url('admin/login.php')); ?>" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-lock"></i></span>
                        <span><?= t('admin', $translations); ?></span>
                    </a>
                    <a href="#" class="footer-m-icon-item">
                        <span class="footer-m-item-icon"><i class="fa-solid fa-circle-question"></i></span>
                        <span><?= t('faqs', $translations); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Contact Info accordion -->
        <div class="footer-m-accordion" data-accordion>
            <button class="footer-m-acc-header" data-acc-toggle aria-expanded="false">
                <span class="footer-m-acc-icon-wrap footer-acc-blue"><i class="fa-solid fa-phone"></i></span>
                <span class="footer-m-acc-title"><?= t('contact_info', $translations); ?></span>
                <i class="fa-solid fa-chevron-down footer-m-acc-chevron"></i>
            </button>
            <div class="footer-m-acc-body">
                <ul class="footer-m-contact-list">
                    <li><i class="fa-solid fa-location-dot"></i><span><?= t('address_line_1', $translations); ?></span></li>
                    <li><i class="fa-solid fa-envelope"></i><span>support@APCSNSC.org</span></li>
                    <li><i class="fa-solid fa-phone"></i><span>+91 99999 99999</span></li>
                </ul>
            </div>
        </div>

    </div><!-- /footer-mobile -->

    <!-- shared mobile copyright (mobile only) -->
    <p class="copyright footer-copyright-mobile">&copy; <?= date('Y'); ?> APCSNSC. <?= t('copyright', $translations); ?></p>

</footer>

<script src="<?= esc(base_url('assets/js/main.js?v=' . $jsVer)); ?>"></script>
<?php
// Add media showcase JS
$showcaseOptBJs = __DIR__ . '/assets/js/media-showcase-optionb.js';
$showcaseOptBJsVer = file_exists($showcaseOptBJs) ? (string)filemtime($showcaseOptBJs) : (string)time();
?>
<script src="<?= esc(base_url('assets/js/media-showcase-optionb.js?v=' . $showcaseOptBJsVer)); ?>"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Fancybox !== "undefined") {
            Fancybox.bind('[data-fancybox]', {
                // Automatically enables swipe, zoom, and gallery modes
            });
        }
    });
</script>
</body>
</html>