/* Media Showcase JS
   - Handles: playlist click -> swap featured video, tabs, gallery filters
   - Lightweight, no dependencies
*/
(function(){
  "use strict";

  // Helpers
  function qs(sel, root=document) { return root.querySelector(sel); }
  function qsa(sel, root=document) { return Array.from(root.querySelectorAll(sel)); }

  // Lazy loader for images (data-src)
  function initLazy() {
    const imgs = qsa('.media-showcase img[data-src]');
    if (!imgs.length) return;
    const io = new IntersectionObserver((entries, obs) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          const img = e.target;
          img.src = img.dataset.src;
          img.addEventListener('load', () => img.classList.add('loaded'));
          obs.unobserve(img);
        }
      });
    }, {rootMargin: '200px'});
    imgs.forEach(i => io.observe(i));
  }

  // Tabs
  function initTabs(root=document) {
    const tabs = qsa('.ms-tab', root);
    const panels = qsa('.ms-panel', root);
    tabs.forEach(tab => tab.addEventListener('click', () => {
      tabs.forEach(t=>t.classList.remove('is-active'));
      tab.classList.add('is-active');
      const key = tab.dataset.tab;
      panels.forEach(p => p.dataset.panel===key ? p.classList.add('is-open') : p.classList.remove('is-open'));
      initLazy();
    }));
  }

  // Playlist -> update featured iframe and meta
  function initPlaylist(root=document) {
    const iframe = qs('#ms-featured-iframe', root);
    const title = qs('.ms-title', root);
    const date = qs('.ms-date', root);
    if (!iframe) return;
    qsa('.ms-playlist-item', root).forEach(btn => btn.addEventListener('click', function(){
      qsa('.ms-playlist-item', root).forEach(b=>b.classList.remove('is-active'));
      this.classList.add('is-active');
      const vid = this.dataset.videoId;
      const vtitle = this.dataset.videoTitle;
      const vdate = this.dataset.videoDate;
      iframe.src = 'https://www.youtube.com/embed/' + vid + '?rel=0&autoplay=1';
      if (title) title.textContent = vtitle;
      if (date) date.textContent = new Date(vdate).toLocaleDateString();
    }));
  }

  // Gallery filters
  function initGallery(root=document) {
    const filters = qsa('.ms-filter', root);
    const cards = qsa('.ms-gallery-card', root);
    filters.forEach(f => f.addEventListener('click', function(){
      filters.forEach(x=>x.classList.remove('is-active'));
      this.classList.add('is-active');
      const key = this.dataset.filter;
      cards.forEach(c => {
        if (key==='All' || c.dataset.cat===key) c.style.display = '';
        else c.style.display = 'none';
      });
    }));
  }

  // Initialize components inside the showcase
  function initShowcase() {
    const root = qs('.media-showcase');
    if (!root) return;
    initLazy();
    initTabs(root);
    initPlaylist(root);
    initGallery(root);

    // reveal skeleton after first iframe load
    const iframe = qs('#ms-featured-iframe', root);
    const skel = qs('.ms-skeleton.ms-video-skel', root);
    if (iframe && skel) {
      iframe.addEventListener('load', function(){ skel.style.display='none'; });
      // fallback remove after 2s
      setTimeout(()=> skel.style.display='none', 2000);
    }
  }

  // DOM ready
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initShowcase);
  else initShowcase();

})();
