document.addEventListener('DOMContentLoaded', function () {
    var featuredVideoRoot = document.querySelector('[data-showcase-featured-video]');
    var featuredIframe = featuredVideoRoot ? featuredVideoRoot.querySelector('iframe') : null;
    var videoButtons = Array.prototype.slice.call(document.querySelectorAll('[data-showcase-video-thumbs] .apcs-video-playlist-item'));
    var videoTitle = document.querySelector('[data-apcs-video-title]');
    var videoDescription = document.querySelector('[data-apcs-video-description]');
    var videoDuration = document.querySelector('[data-apcs-video-duration]');
    var videoDate = document.querySelector('[data-apcs-video-date]');
    var videoYoutube = document.querySelector('[data-apcs-video-youtube]');
    var playOverlay = document.querySelector('[data-apcs-video-play]');

    var setFeaturedVideo = function (btn, autoplay) {
        if (!featuredIframe || !btn) {
            return;
        }

        var videoId = (btn.getAttribute('data-video-id') || '').trim();
        if (!videoId) {
            return;
        }

        var videoSrc = 'https://www.youtube.com/embed/' + encodeURIComponent(videoId) + (autoplay ? '?autoplay=1' : '');
        featuredIframe.src = videoSrc;

        var title = btn.getAttribute('data-video-title') || 'Featured Video';
        var description = btn.getAttribute('data-video-description') || 'Latest APCSNSC media coverage.';
        var duration = btn.getAttribute('data-video-duration') || '';
        var date = btn.getAttribute('data-video-date') || '';
        var youtubeUrl = btn.getAttribute('data-video-url') || ('https://www.youtube.com/watch?v=' + videoId);

        if (videoTitle) {
            videoTitle.textContent = title;
        }
        if (videoDescription) {
            videoDescription.textContent = description;
        }
        if (videoDuration) {
            videoDuration.textContent = duration;
            videoDuration.style.display = duration ? 'inline-flex' : 'none';
        }
        if (videoDate) {
            videoDate.textContent = date;
            videoDate.style.display = date ? 'inline-flex' : 'none';
        }
        if (videoYoutube) {
            videoYoutube.href = youtubeUrl;
        }

        videoButtons.forEach(function (item) {
            item.classList.remove('is-active');
        });
        btn.classList.add('is-active');

        if (playOverlay) {
            playOverlay.classList.add('is-hidden');
        }
    };

    videoButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setFeaturedVideo(btn, true);
            manualVideoInteraction = true;
        });
    });

    if (playOverlay) {
        playOverlay.addEventListener('click', function () {
            var activeBtn = document.querySelector('[data-showcase-video-thumbs] .apcs-video-playlist-item.is-active') || videoButtons[0];
            setFeaturedVideo(activeBtn, true);
        });
    }

    var manualVideoInteraction = false;
    if (videoButtons.length > 1) {
        var rotationIndex = 0;
        setInterval(function () {
            if (manualVideoInteraction) {
                return;
            }

            rotationIndex = (rotationIndex + 1) % videoButtons.length;
            setFeaturedVideo(videoButtons[rotationIndex], false);
        }, 8000);
    }

    var videoModal = document.getElementById('apcsVideoModal');
    var openVideoModalBtn = document.querySelector('[data-apcs-open-video-modal]');
    var videoModalFrame = document.querySelector('[data-apcs-video-modal-frame]');
    var closeVideoModalButtons = document.querySelectorAll('[data-apcs-modal-close]');

    if (openVideoModalBtn && videoModal && videoModalFrame) {
        openVideoModalBtn.addEventListener('click', function () {
            if (!featuredIframe) {
                return;
            }

            var src = featuredIframe.src || '';
            if (src.indexOf('autoplay=1') === -1) {
                src += (src.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
            }

            videoModalFrame.src = src;
            videoModal.classList.add('is-open');
            videoModal.setAttribute('aria-hidden', 'false');
        });
    }

    closeVideoModalButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!videoModal || !videoModalFrame) {
                return;
            }

            videoModal.classList.remove('is-open');
            videoModal.setAttribute('aria-hidden', 'true');
            videoModalFrame.src = '';
        });
    });

    var galleryTrack = document.querySelector('[data-showcase-gallery-track]');
    var gallerySlides = galleryTrack ? Array.prototype.slice.call(galleryTrack.querySelectorAll('.apcs-gallery-slide')) : [];
    var galleryTabs = Array.prototype.slice.call(document.querySelectorAll('[data-apcs-gallery-filters] [data-gallery-filter]'));
    var galleryFilteredSlides = gallerySlides.slice();
    var galleryIndex = 0;

    var getVisibleCount = function () {
        if (window.matchMedia('(max-width: 767px)').matches) {
            return 1;
        }
        if (window.matchMedia('(max-width: 1024px)').matches) {
            return 2;
        }
        return 3;
    };

    var renderGallery = function () {
        if (!galleryTrack) {
            return;
        }

        var visibleCount = getVisibleCount();
        var step = 100 / visibleCount;
        galleryTrack.style.transform = 'translateX(-' + (galleryIndex * step) + '%)';
    };

    if (galleryTrack && gallerySlides.length > 0) {
        galleryTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var filter = (tab.getAttribute('data-gallery-filter') || 'all').toLowerCase();

                galleryTabs.forEach(function (item) {
                    item.classList.remove('is-active');
                });
                tab.classList.add('is-active');

                galleryFilteredSlides = [];
                gallerySlides.forEach(function (slide) {
                    var cat = (slide.getAttribute('data-gallery-category') || 'all').toLowerCase();
                    var show = filter === 'all' || cat === filter;
                    slide.style.display = show ? '' : 'none';
                    if (show) {
                        galleryFilteredSlides.push(slide);
                    }
                });

                galleryIndex = 0;
                renderGallery();
            });
        });

        setInterval(function () {
            if (galleryFilteredSlides.length <= getVisibleCount()) {
                return;
            }

            galleryIndex = (galleryIndex + 1) % galleryFilteredSlides.length;
            renderGallery();
        }, 4000);

        window.addEventListener('resize', renderGallery);
        renderGallery();
    }

    var galleryModal = document.getElementById('apcsGalleryModal');
    var galleryModalImage = document.querySelector('[data-apcs-gallery-modal-image]');
    var galleryModalCaption = document.querySelector('[data-apcs-gallery-modal-caption]');
    var galleryPrev = document.querySelector('[data-apcs-gallery-prev]');
    var galleryNext = document.querySelector('[data-apcs-gallery-next]');
    var galleryClose = document.querySelectorAll('[data-apcs-gallery-close]');
    var galleryActiveIndex = 0;

    var openGallery = function (idx) {
        if (!galleryModal || !galleryModalImage || !gallerySlides.length) {
            return;
        }

        galleryActiveIndex = idx;
        var slide = gallerySlides[galleryActiveIndex];
        galleryModalImage.src = slide.getAttribute('data-gallery-src') || '';
        galleryModalCaption.textContent = slide.getAttribute('data-gallery-title') || 'Gallery image';
        galleryModal.classList.add('is-open');
        galleryModal.setAttribute('aria-hidden', 'false');
    };

    gallerySlides.forEach(function (slide, idx) {
        slide.addEventListener('click', function () {
            openGallery(idx);
        });
    });

    if (galleryPrev) {
        galleryPrev.addEventListener('click', function () {
            if (!gallerySlides.length) {
                return;
            }
            galleryActiveIndex = (galleryActiveIndex - 1 + gallerySlides.length) % gallerySlides.length;
            openGallery(galleryActiveIndex);
        });
    }

    if (galleryNext) {
        galleryNext.addEventListener('click', function () {
            if (!gallerySlides.length) {
                return;
            }
            galleryActiveIndex = (galleryActiveIndex + 1) % gallerySlides.length;
            openGallery(galleryActiveIndex);
        });
    }

    galleryClose.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!galleryModal || !galleryModalImage) {
                return;
            }
            galleryModal.classList.remove('is-open');
            galleryModal.setAttribute('aria-hidden', 'true');
            galleryModalImage.src = '';
        });
    });

    var newsScroller = document.querySelector('[data-apcs-news-scroll]');
    var newsItems = Array.prototype.slice.call(document.querySelectorAll('[data-apcs-news-open]'));
    var newsModal = document.getElementById('apcsNewsModal');
    var newsModalTitle = document.querySelector('[data-apcs-news-modal-title]');
    var newsModalDate = document.querySelector('[data-apcs-news-modal-date]');
    var newsModalText = document.querySelector('[data-apcs-news-modal-text]');
    var newsModalLink = document.querySelector('[data-apcs-news-modal-link]');
    var newsClose = document.querySelectorAll('[data-apcs-news-close]');

    if (newsScroller) {
        var scrollTick = 0;
        var paused = false;

        newsScroller.addEventListener('mouseenter', function () {
            paused = true;
        });
        newsScroller.addEventListener('mouseleave', function () {
            paused = false;
        });

        setInterval(function () {
            if (paused || newsItems.length <= 1) {
                return;
            }

            scrollTick += 1;
            newsScroller.scrollTo({
                top: scrollTick * 74,
                behavior: 'smooth'
            });

            if (newsScroller.scrollTop + newsScroller.clientHeight >= newsScroller.scrollHeight - 5) {
                scrollTick = 0;
                newsScroller.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }, 2400);
    }

    newsItems.forEach(function (item) {
        item.addEventListener('click', function () {
            if (!newsModal) {
                return;
            }

            if (newsModalTitle) {
                newsModalTitle.textContent = item.getAttribute('data-news-title') || 'Notice';
            }
            if (newsModalDate) {
                newsModalDate.textContent = item.getAttribute('data-news-date') || '';
            }
            if (newsModalText) {
                newsModalText.textContent = item.getAttribute('data-news-text') || '';
            }
            if (newsModalLink) {
                newsModalLink.href = item.getAttribute('data-news-link') || '#';
            }

            newsModal.classList.add('is-open');
            newsModal.setAttribute('aria-hidden', 'false');
        });
    });

    newsClose.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!newsModal) {
                return;
            }

            newsModal.classList.remove('is-open');
            newsModal.setAttribute('aria-hidden', 'true');
        });
    });
});
