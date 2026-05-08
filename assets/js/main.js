const hamburger = document.getElementById('hamburger');
const navWrap = document.getElementById('navWrap');
const navOverlay = document.getElementById('navOverlay');

let _scrollY = 0;

function closeNav() {
    navWrap && navWrap.classList.remove('open');
    hamburger && hamburger.classList.remove('open');
    hamburger && hamburger.setAttribute('aria-expanded', 'false');
    navOverlay && navOverlay.classList.remove('open');
    document.documentElement.classList.remove('menu-open');
    window.scrollTo(0, _scrollY);
}

function openNav() {
    _scrollY = window.scrollY;
    navWrap && navWrap.classList.add('open');
    hamburger && hamburger.classList.add('open');
    hamburger && hamburger.setAttribute('aria-expanded', 'true');
    navOverlay && navOverlay.classList.add('open');
    document.documentElement.classList.add('menu-open');
}

if (hamburger && navWrap) {
    hamburger.addEventListener('click', () => {
        navWrap.classList.contains('open') ? closeNav() : openNav();
    });

    navWrap.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeNav);
    });

    navOverlay && navOverlay.addEventListener('click', closeNav);

    const navCloseBtn = document.getElementById('navCloseBtn');
    navCloseBtn && navCloseBtn.addEventListener('click', closeNav);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeNav();
    });
}

/* Fixed header scroll shadow */
const siteHeader = document.querySelector('.site-header');
if (siteHeader) {
    const onScroll = () => {
        siteHeader.classList.toggle('scrolled', window.scrollY > 10);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

/* Push page content down by header height only when header is fixed */
(function pushBodyDown() {
    const header = document.querySelector('.site-header');
    const main = document.querySelector('main');
    if (!header || !main) return;
    const update = () => {
        const isFixed = window.getComputedStyle(header).position === 'fixed';
        main.style.paddingTop = isFixed ? header.offsetHeight + 'px' : '';
    };
    update();
    window.addEventListener('resize', update, { passive: true });
})();

/* Mobile footer accordion */
document.querySelectorAll('[data-accordion]').forEach((panel) => {
    const toggle = panel.querySelector('[data-acc-toggle]');
    if (!toggle) return;
    toggle.addEventListener('click', () => {
        const isOpen = panel.classList.contains('open');
        /* close all siblings first */
        const siblings = panel.parentElement ? panel.parentElement.querySelectorAll('[data-accordion]') : [];
        siblings.forEach((sib) => {
            sib.classList.remove('open');
            const t = sib.querySelector('[data-acc-toggle]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
        if (!isOpen) {
            panel.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
        }
    });
});

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('show');
            observer.unobserve(entry.target);
        }
    });
}, {
    threshold: 0.12,
});

document.querySelectorAll('.fade-in').forEach((el) => {
    observer.observe(el);
});

document.querySelectorAll('.hero-slider').forEach((slider) => {
    const slides = Array.from(slider.querySelectorAll('.hero-slide'));
    if (slides.length <= 1) {
        return;
    }

    const prevBtn = slider.querySelector('[data-slide-prev]');
    const nextBtn = slider.querySelector('[data-slide-next]');
    const dots = Array.from(slider.querySelectorAll('.hero-slider-dot'));
    let activeIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
    let autoplayId = null;
    let touchStartX = 0;
    let touchEndX = 0;

    if (activeIndex < 0) {
        activeIndex = 0;
    }

    const setActive = (index) => {
        activeIndex = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === activeIndex);
        });
        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === activeIndex);
        });
    };

    const startAutoplay = () => {
        if (autoplayId !== null) {
            window.clearInterval(autoplayId);
        }
        autoplayId = window.setInterval(() => {
            setActive(activeIndex + 1);
        }, 4200);
    };

    const stopAutoplay = () => {
        if (autoplayId !== null) {
            window.clearInterval(autoplayId);
            autoplayId = null;
        }
    };

    prevBtn?.addEventListener('click', () => {
        setActive(activeIndex - 1);
        startAutoplay();
    });

    nextBtn?.addEventListener('click', () => {
        setActive(activeIndex + 1);
        startAutoplay();
    });

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            setActive(index);
            startAutoplay();
        });
    });

    slider.addEventListener('mouseenter', stopAutoplay);
    slider.addEventListener('mouseleave', startAutoplay);
    slider.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0].clientX;
    }, { passive: true });
    slider.addEventListener('touchend', (event) => {
        touchEndX = event.changedTouches[0].clientX;
        const delta = touchEndX - touchStartX;
        if (Math.abs(delta) > 30) {
            if (delta < 0) {
                setActive(activeIndex + 1);
            } else {
                setActive(activeIndex - 1);
            }
            startAutoplay();
        }
    }, { passive: true });

    setActive(activeIndex);
    startAutoplay();
});

document.querySelectorAll('[data-home-hero-slider]').forEach((slider) => {
    const slides = Array.from(slider.querySelectorAll('[data-home-hero-slide]'));
    if (slides.length <= 1) {
        return;
    }

    const prevBtn = slider.querySelector('[data-home-hero-prev]');
    const nextBtn = slider.querySelector('[data-home-hero-next]');
    const dots = Array.from(slider.querySelectorAll('[data-home-hero-dot]'));
    let activeIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
    let autoplayId = null;
    let touchStartX = 0;
    let touchEndX = 0;

    if (activeIndex < 0) {
        activeIndex = 0;
    }

    const setActive = (index) => {
        activeIndex = (index + slides.length) % slides.length;
        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === activeIndex);
        });
        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === activeIndex);
        });
    };

    const startAutoplay = () => {
        if (autoplayId !== null) {
            window.clearInterval(autoplayId);
        }
        autoplayId = window.setInterval(() => {
            setActive(activeIndex + 1);
        }, 5000);
    };

    const stopAutoplay = () => {
        if (autoplayId !== null) {
            window.clearInterval(autoplayId);
            autoplayId = null;
        }
    };

    prevBtn?.addEventListener('click', () => {
        setActive(activeIndex - 1);
        startAutoplay();
    });

    nextBtn?.addEventListener('click', () => {
        setActive(activeIndex + 1);
        startAutoplay();
    });

    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            setActive(index);
            startAutoplay();
        });
    });

    slider.addEventListener('mouseenter', stopAutoplay);
    slider.addEventListener('mouseleave', startAutoplay);
    slider.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0].clientX;
    }, { passive: true });
    slider.addEventListener('touchend', (event) => {
        touchEndX = event.changedTouches[0].clientX;
        const delta = touchEndX - touchStartX;
        if (Math.abs(delta) > 30) {
            if (delta < 0) {
                setActive(activeIndex + 1);
            } else {
                setActive(activeIndex - 1);
            }
            startAutoplay();
        }
    }, { passive: true });

    setActive(activeIndex);
    startAutoplay();
});

const initSlideDeck = (root, config) => {
    const track = root.querySelector(config.track);
    const slides = Array.from(root.querySelectorAll(config.slide));

    if (!track || slides.length <= 1) {
        return;
    }

    const prevBtn = root.querySelector(config.prev);
    const nextBtn = root.querySelector(config.next);
    const dots = Array.from(root.querySelectorAll(config.dot));
    let activeIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));

    if (activeIndex < 0) {
        activeIndex = 0;
    }

    const setActive = (index) => {
        activeIndex = (index + slides.length) % slides.length;
        track.style.transform = `translateX(-${activeIndex * 100}%)`;
        slides.forEach((slide, slideIndex) => {
            slide.classList.toggle('is-active', slideIndex === activeIndex);
        });
        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === activeIndex);
        });
    };

    prevBtn?.addEventListener('click', () => setActive(activeIndex - 1));
    nextBtn?.addEventListener('click', () => setActive(activeIndex + 1));
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => setActive(index));
    });

    setActive(activeIndex);
};

document.querySelectorAll('[data-updates-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-updates-track]');
    const prevBtn = carousel.querySelector('[data-updates-prev]');
    const nextBtn = carousel.querySelector('[data-updates-next]');

    if (!track || !prevBtn || !nextBtn) {
        return;
    }

    const getStep = () => {
        const card = track.querySelector('.news-card');
        if (!card) {
            return 320;
        }

        const styles = window.getComputedStyle(track);
        const gap = Number.parseFloat(styles.columnGap || styles.gap || '18') || 18;
        return card.getBoundingClientRect().width + gap;
    };

    prevBtn.addEventListener('click', () => {
        track.scrollBy({ left: -getStep() * 1.2, behavior: 'smooth' });
    });

    nextBtn.addEventListener('click', () => {
        track.scrollBy({ left: getStep() * 1.2, behavior: 'smooth' });
    });
});

document.querySelectorAll('[data-district-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-district-track]');
    const prevBtn = carousel.querySelector('[data-district-prev]');
    const nextBtn = carousel.querySelector('[data-district-next]');

    if (!track || !prevBtn || !nextBtn) {
        return;
    }

    const getStep = () => {
        const card = track.querySelector('.district-photo-card');
        if (!card) {
            return 216;
        }
        const styles = window.getComputedStyle(track);
        const gap = Number.parseFloat(styles.columnGap || styles.gap || '16') || 16;
        return card.getBoundingClientRect().width + gap;
    };

    prevBtn.addEventListener('click', () => {
        track.scrollBy({ left: -getStep() * 3, behavior: 'smooth' });
    });

    nextBtn.addEventListener('click', () => {
        track.scrollBy({ left: getStep() * 3, behavior: 'smooth' });
    });
});

document.querySelectorAll('[data-update-card-slider]').forEach((slider) => {
    initSlideDeck(slider, {
        track: '.news-image-slider-track',
        slide: '[data-update-slide]',
        prev: '[data-update-card-prev]',
        next: '[data-update-card-next]',
        dot: '[data-update-card-dot]',
    });
});

document.querySelectorAll('[data-update-gallery]').forEach((slider) => {
    initSlideDeck(slider, {
        track: '.update-detail-gallery-track',
        slide: '[data-update-gallery-slide]',
        prev: '[data-update-gallery-prev]',
        next: '[data-update-gallery-next]',
        dot: '[data-update-gallery-dot]',
    });
});

document.querySelectorAll('[data-copy-link]').forEach((button) => {
    button.addEventListener('click', async () => {
        const link = button.getAttribute('data-copy-link') || '';
        if (!link) {
            return;
        }

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(link);
                window.alert('Link copied. Open Instagram and paste the update link.');
            } else {
                window.prompt('Copy this update link for Instagram sharing:', link);
            }
        } catch (error) {
            window.prompt('Copy this update link for Instagram sharing:', link);
        }
    });
});

document.querySelectorAll('[data-command-center]').forEach((panel) => {
    const activityItems = Array.from(panel.querySelectorAll('[data-command-activity-item]'));
    activityItems.forEach((item, index) => {
        window.setTimeout(() => {
            item.classList.add('is-visible');
        }, 100 + (index * 90));
    });
});

const statsSection = document.querySelector('.section-stats-modern');

if (statsSection) {
    const counterEls = statsSection.querySelectorAll('.counter-value');
    let hasAnimatedStats = false;

    const formatNumber = (value) => new Intl.NumberFormat('en-IN').format(value);

    const animateCounter = (el) => {
        const target = Number.parseInt(el.getAttribute('data-target') || '0', 10);
        const suffix = el.getAttribute('data-suffix') || '';
        const duration = Number.parseInt(el.getAttribute('data-duration') || '1400', 10);

        if (!Number.isFinite(target) || target <= 0) {
            return;
        }

        let startTime = null;
        const step = (timestamp) => {
            if (startTime === null) {
                startTime = timestamp;
            }

            const elapsed = timestamp - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(target * eased);

            el.textContent = `${formatNumber(current)}${suffix}`;

            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };

        el.textContent = '0';
        window.requestAnimationFrame(step);
    };

    if ('IntersectionObserver' in window) {
        statsSection.classList.add('stats-animate');
        const statsObserver = new IntersectionObserver((entries, localObserver) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting || hasAnimatedStats) {
                    return;
                }

                hasAnimatedStats = true;
                statsSection.classList.add('stats-visible');
                counterEls.forEach((el) => animateCounter(el));
                localObserver.unobserve(entry.target);
            });
        }, {
            threshold: 0.3,
        });

        statsObserver.observe(statsSection);
    } else {
        counterEls.forEach((el) => animateCounter(el));
    }
}