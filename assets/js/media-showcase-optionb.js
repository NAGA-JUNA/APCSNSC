(function () {
  'use strict';

  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  /* ── Tab switching ─────────────────────────────────────────── */
  function initTabs(root) {
    const tabs = $$('.ms-tab', root);
    const panels = $$('.ms-tab-panel', root);
    if (!tabs.length || !panels.length) return;

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const id = tab.dataset.tabId;
        tabs.forEach((btn) => {
          btn.classList.remove('is-active');
          btn.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        panels.forEach((panel) => {
          panel.classList.toggle('is-active', panel.dataset.tabPanel === id);
        });
      });
    });
  }

  /* ── Video modal ───────────────────────────────────────────── */
  let _modal = null;

  function getModal() {
    if (_modal) return _modal;

    _modal = $('#ms-cinematic-modal') || $('#ms-video-modal');

    if (!_modal) {
      _modal = document.createElement('div');
      _modal.id = 'ms-cinematic-modal';
      _modal.className = 'ms-video-modal';
      _modal.setAttribute('aria-hidden', 'true');
      _modal.setAttribute('role', 'dialog');
      _modal.innerHTML = [
        '<div class="ms-modal-backdrop" data-modal-close></div>',
        '<div class="ms-modal-dialog">',
        '  <button class="ms-modal-close" data-modal-close aria-label="Close video" type="button">',
        '    <i class="fa-solid fa-xmark"></i>',
        '  </button>',
        '  <div class="ms-modal-body">',
        '    <iframe class="ms-modal-iframe" id="ms-modal-iframe" src=""',
        '      title="YouTube video player" frameborder="0"',
        '      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"',
        '      allowfullscreen></iframe>',
        '  </div>',
        '</div>'
      ].join('');
      document.body.appendChild(_modal);
    }

    $$('[data-modal-close]', _modal).forEach((el) => {
      el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeModal();
    });

    return _modal;
  }

  function openModal(videoId) {
    if (!videoId) return;
    const modal = getModal();
    const iframe = $('#ms-modal-iframe', modal) || $('iframe', modal);
    if (iframe) {
      iframe.src = 'https://www.youtube.com/embed/' + encodeURIComponent(videoId) + '?autoplay=1&rel=0';
    }
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    const modal = _modal;
    if (!modal) return;
    const iframe = $('#ms-modal-iframe', modal) || $('iframe', modal);
    if (iframe) {
      setTimeout(() => { iframe.src = ''; }, 350);
    }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  /* ── Share bar ─────────────────────────────────────────────── */
  let _toast = null;

  function getToast() {
    if (_toast) return _toast;
    _toast = document.createElement('div');
    _toast.className = 'ms-copy-toast';
    _toast.textContent = 'Link copied!';
    document.body.appendChild(_toast);
    return _toast;
  }

  function showToast() {
    const toast = getToast();
    toast.classList.add('is-visible');
    setTimeout(() => toast.classList.remove('is-visible'), 2200);
  }

  function youtubeUrl(videoId) {
    return 'https://youtu.be/' + encodeURIComponent(videoId);
  }

  function updateShareBar(bar, videoId, title) {
    if (!bar || !videoId) return;
    const url   = youtubeUrl(videoId);
    const text  = encodeURIComponent(title + ' ' + url);
    const eUrl  = encodeURIComponent(url);
    const eTitle = encodeURIComponent(title);

    const map = {
      whatsapp:  'https://wa.me/?text=' + text,
      facebook:  'https://www.facebook.com/sharer/sharer.php?u=' + eUrl,
      twitter:   'https://twitter.com/intent/tweet?text=' + eTitle + '&url=' + eUrl,
      telegram:  'https://t.me/share/url?url=' + eUrl + '&text=' + eTitle,
      instagram: url,
    };

    $$('[data-share]', bar).forEach((btn) => {
      const platform = btn.dataset.share;
      if (platform === 'copy') return;
      if (map[platform]) btn.href = map[platform];
    });

    // Wire copy button (replace to remove old listener)
    const copyBtn = bar.querySelector('[data-share="copy"]');
    if (copyBtn) {
      const fresh = copyBtn.cloneNode(true);
      copyBtn.replaceWith(fresh);
      fresh.addEventListener('click', () => {
        if (navigator.clipboard) {
          navigator.clipboard.writeText(youtubeUrl(videoId)).then(showToast);
        } else {
          const ta = document.createElement('textarea');
          ta.value = youtubeUrl(videoId);
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          showToast();
        }
      });
    }
  }

  function initShareBar(root) {
    $$('[data-share-bar]', root).forEach((bar) => {
      const featured = bar.closest('.ms-featured-card');
      if (!featured) return;
      updateShareBar(bar, featured.dataset.videoId, featured.dataset.videoTitle || '');
    });
  }

  /* ── Video card interactions ───────────────────────────────── */
  function initVideoCards(root) {
    $$('.ms-video-card', root).forEach((card) => {
      if (card.dataset.msInited) return;
      card.dataset.msInited = '1';

      card.addEventListener('click', (e) => {
        if (e.target.closest('a, button')) return;

        /* Featured card → open modal */
        if (card.classList.contains('ms-featured-card')) {
          openModal(card.dataset.videoId);
          return;
        }

        /* Mini card → swap into featured */
        if (card.classList.contains('ms-mini-card')) {
          const layout = card.closest('.ms-video-layout');
          if (!layout) return;
          const featured = layout.querySelector('.ms-featured-card');
          if (!featured) return;

          const newId    = card.dataset.videoId;
          const newTitle = card.dataset.videoTitle || '';
          const newDate  = card.dataset.videoDate  || '';
          const newImg   = card.querySelector('img')?.src || '';

          const fImg   = featured.querySelector('.ms-card-media img');
          const fTitle = featured.querySelector('.ms-card-title');
          const fDate  = featured.querySelector('.ms-card-date');

          fImg.style.opacity   = '0';
          fTitle.style.opacity = '0';
          if (fDate) fDate.style.opacity = '0';

          setTimeout(() => {
            if (fImg)   fImg.src = newImg;
            if (fTitle) fTitle.textContent = newTitle;
            if (fDate)  fDate.innerHTML = '<i class="fa-regular fa-calendar"></i> ' + newDate;
            featured.dataset.videoId    = newId;
            featured.dataset.videoTitle = newTitle;
            featured.dataset.videoDate  = newDate;

            fImg.style.opacity   = '1';
            fTitle.style.opacity = '1';
            if (fDate) fDate.style.opacity = '1';

            // Update share bar to reflect new video
            const bar = featured.querySelector('[data-share-bar]');
            updateShareBar(bar, newId, newTitle);
          }, 180);

          $$('.ms-mini-card', layout).forEach((c) => {
            c.classList.remove('is-active', 'is-playing');
          });
          card.classList.add('is-active', 'is-playing');
          card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    });
  }

  /* ── Playlist scroll buttons ───────────────────────────────── */
  function initPlaylistScroll(root) {
    $$('.ms-playlist', root).forEach((playlist) => {
      const track   = playlist.querySelector('.ms-playlist-track');
      const upBtn   = playlist.querySelector('.scroll-up');
      const downBtn = playlist.querySelector('.scroll-down');
      if (!track || !upBtn || !downBtn) return;

      const STEP = 180;

      function update() {
        const atTop    = track.scrollTop <= 2;
        const atBottom = track.scrollTop + track.clientHeight >= track.scrollHeight - 2;
        upBtn.classList.toggle('is-disabled', atTop);
        downBtn.classList.toggle('is-disabled', atBottom);
      }

      track.addEventListener('scroll', update, { passive: true });
      upBtn.addEventListener('click', () => track.scrollBy({ top: -STEP, behavior: 'smooth' }));
      downBtn.addEventListener('click', () => track.scrollBy({ top: STEP, behavior: 'smooth' }));

      requestAnimationFrame(update);
      setTimeout(update, 300);
    });
  }

  /* ── Bootstrap ─────────────────────────────────────────────── */
  function init() {
    const root = $('#media-showcase-dynamic') || $('#media-showcase-optionb');
    if (!root) return;

    initTabs(root);
    initVideoCards(root);
    initPlaylistScroll(root);
    initShareBar(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();