/* TokLens front-end. No inline handlers, no innerHTML with remote data (XSS-safe). */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const root = document.documentElement;
  const lang = root.lang || 'en';
  let T = {};
  try { T = JSON.parse($('#i18n')?.textContent || '{}'); } catch (_) { /* ignore */ }

  /** Tiny DOM builder: el('a', {href, class}, child, 'text') — text is always textContent. */
  function el(tag, attrs = {}, ...children) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v == null || v === false) continue;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
      else n.setAttribute(k, v === true ? '' : String(v));
    }
    for (const c of children.flat()) {
      if (c == null || c === false) continue;
      n.append(c instanceof Node ? c : document.createTextNode(String(c)));
    }
    return n;
  }
  function icon(name, cls = '') {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', ('ic ' + cls).trim());
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#i-' + name);
    svg.append(use);
    return svg;
  }
  /** Only allow our own relative proxy URLs into src/href. */
  const safe = (u) => (typeof u === 'string' && /^\/(media|stream)\?/.test(u) ? u : null);
  const safeExt = (u) => (typeof u === 'string' && /^https:\/\/www\.tiktok\.com\//.test(u) ? u : null);

  const nf = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });
  const compact = (n) => (typeof n === 'number' ? nf.format(n) : '');
  const dur = (s) => (s > 0 ? `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}` : '');

  function toast(msg) {
    const box = $('.toasts');
    if (!box) return;
    const t = el('div', { class: 'toast glass', text: msg });
    box.append(t);
    setTimeout(() => t.remove(), 2800);
  }

  // ---------------------------------------------------------------- theme
  $('[data-theme-toggle]')?.addEventListener('click', () => {
    const next = root.dataset.theme === 'light' ? 'dark' : 'light';
    const apply = () => { root.dataset.theme = next; try { localStorage.setItem('theme', next); } catch (_) {} };
    if (document.startViewTransition && !matchMedia('(prefers-reduced-motion: reduce)').matches) document.startViewTransition(apply);
    else apply();
  });

  // ---------------------------------------------------------------- liquid glass refraction (Chromium only)
  const brands = navigator.userAgentData?.brands?.map((b) => b.brand).join(' ') || '';
  const desktop = !matchMedia('(pointer: coarse)').matches && innerWidth > 900;
  if (desktop && /Chromium|Google Chrome|Microsoft Edge/.test(brands) && !matchMedia('(prefers-reduced-transparency: reduce)').matches) {
    root.classList.add('lg-refract');
  }

  // pointer-following light on .lit surfaces
  if (desktop) document.addEventListener('pointermove', (e) => {
    const t = e.target instanceof Element ? e.target.closest('.lit') : null;
    if (!t) return;
    const r = t.getBoundingClientRect();
    t.style.setProperty('--mx', `${e.clientX - r.left}px`);
    t.style.setProperty('--my', `${e.clientY - r.top}px`);
  }, { passive: true });

  // ---------------------------------------------------------------- search forms
  $$('form[data-search]').forEach((f) => {
    const input = f.querySelector('input');
    input?.addEventListener('paste', () => {
      setTimeout(() => {
        // Turn a pasted profile link into a clean username on username forms.
        if (input.name !== 'url') {
          const m = input.value.match(/tiktok\.com\/@([A-Za-z0-9._]{2,24})/i);
          if (m && !/\/(video|photo)\//.test(input.value)) input.value = m[1];
        }
      }, 0);
    });
    f.addEventListener('submit', () => {
      const b = f.querySelector('button[type=submit]');
      if (b) { b.setAttribute('aria-busy', 'true'); b.querySelector('.btn-label')?.replaceChildren(T.loading || '…'); }
    });
  });
  // reset busy state on back/forward cache restore
  window.addEventListener('pageshow', (e) => { if (e.persisted) $$('[aria-busy="true"]').forEach((b) => b.removeAttribute('aria-busy')); });

  // ---------------------------------------------------------------- copy / share / reload
  document.addEventListener('click', async (e) => {
    const c = e.target.closest?.('[data-copy]');
    if (c) {
      try { await navigator.clipboard.writeText(c.dataset.copy); toast(T.copied || 'Copied'); } catch (_) {}
      return;
    }
    const s = e.target.closest?.('[data-share]');
    if (s) {
      const data = { title: s.dataset.title || document.title, url: location.href };
      if (navigator.share) { try { await navigator.share(data); } catch (_) {} }
      else { try { await navigator.clipboard.writeText(location.href); toast(T.copied || 'Copied'); } catch (_) {} }
      return;
    }
    if (e.target.closest?.('[data-reload]')) { e.preventDefault(); location.reload(); }
  });

  // ---------------------------------------------------------------- local time for timestamps
  try {
    // Only localise if the browser actually supports this language (e.g. Sorani often isn't).
    if (!Intl.DateTimeFormat.supportedLocalesOf([lang]).length) throw new Error('unsupported');
    const df = new Intl.DateTimeFormat(lang, { dateStyle: 'long', timeStyle: 'short' });
    $$('[data-ts]').forEach((t) => {
      const ts = Number(t.dataset.ts);
      if (ts > 0) { t.title = t.textContent; t.textContent = df.format(new Date(ts * 1000)); }
    });
  } catch (_) { /* keep server UTC text */ }

  // ---------------------------------------------------------------- account age: instant calc for numeric IDs
  const ageInput = $('.page-age form[data-search] input');
  const ageLive = $('[data-age-live]');
  if (ageInput && ageLive && typeof BigInt === 'function') {
    const calc = () => {
      const v = ageInput.value.trim();
      if (!/^\d{15,19}$/.test(v)) { ageLive.hidden = true; return; }
      const ts = Number(BigInt(v) >> 32n);
      if (ts < 1388534400 || ts > Date.now() / 1000 + 86400) { ageLive.hidden = false; ageLive.textContent = T.ageOld || ''; return; }
      ageLive.hidden = false;
      ageLive.textContent = new Intl.DateTimeFormat(lang, { dateStyle: 'full', timeStyle: 'short' }).format(new Date(ts * 1000));
    };
    ageInput.addEventListener('input', calc);
    calc();
  }

  // ---------------------------------------------------------------- player dialog
  const dlg = $('#player');
  function openPlayer(v) {
    if (!dlg) return;
    $('.modal-title', dlg).textContent = v.desc || (v.author?.uniqueId ? '@' + v.author.uniqueId : '');
    const media = $('.modal-media', dlg);
    media.replaceChildren();
    if (Array.isArray(v.images) && v.images.length) {
      const strip = el('div', { class: 'photo-strip' });
      v.images.forEach((img, i) => {
        const src = safe(img.src);
        if (src) strip.append(el('a', { class: 'photo', href: safe(img.dl) || src, download: true }, el('img', { src, alt: `${T.photo || 'Photo'} ${i + 1}`, loading: 'lazy' })));
      });
      media.append(strip);
    } else if (safe(v.stream)) {
      media.append(el('video', { src: safe(v.stream), poster: safe(v.cover), controls: true, playsinline: true, autoplay: true, preload: 'metadata' }));
    } else if (safe(v.cover)) {
      media.append(el('img', { src: safe(v.cover), alt: '' }));
    }
    const meta = $('.modal-meta', dlg);
    meta.replaceChildren(
      ...[['plays', 'eye', T.views], ['likes', 'heart', T.likes], ['comments', 'comment', T.comments], ['shares', 'share', T.shares]]
        .filter(([k]) => typeof v[k] === 'number')
        .map(([k, ic, label]) => el('span', { title: label }, icon(ic), compact(v[k])))
    );
    if (v.created) meta.append(el('span', {}, icon('calendar'), new Intl.DateTimeFormat(lang, { dateStyle: 'medium' }).format(new Date(v.created * 1000))));
    const actions = $('.modal-actions', dlg);
    actions.replaceChildren();
    const labels = { hd: T.hd, nowm: T.nowm, wm: T.wm, mp3: T.mp3 };
    let first = true;
    for (const k of ['hd', 'nowm', 'wm', 'mp3']) {
      const href = safe(v.dl?.[k]);
      if (!href) continue;
      actions.append(el('a', { class: 'btn ' + (first ? 'btn-primary' : 'glass lit'), href, download: true }, icon(k === 'mp3' ? 'music' : 'download'), labels[k] || k));
      first = false;
    }
    const ext = safeExt(v.url);
    if (ext) actions.append(el('a', { class: 'btn glass lit', href: ext, target: '_blank', rel: 'nofollow noopener noreferrer' }, icon('external'), T.openTiktok || 'TikTok'));
    dlg.showModal();
  }
  dlg?.addEventListener('close', () => { $('video', dlg)?.pause(); $('.modal-media', dlg).replaceChildren(); });
  dlg?.addEventListener('click', (e) => { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });

  // server-rendered cards (story viewer page)
  document.addEventListener('click', (e) => {
    const card = e.target.closest?.('[data-video]');
    if (!card) return;
    try { openPlayer(JSON.parse(card.dataset.video)); } catch (_) {}
  });

  // ---------------------------------------------------------------- video card
  function videoCard(v) {
    const card = el('button', { class: 'vcard', type: 'button', 'aria-label': v.desc || 'video' });
    const cover = safe(v.cover);
    card.append(cover ? el('img', { src: cover, alt: '', loading: 'lazy', decoding: 'async', referrerpolicy: 'no-referrer' }) : el('span', { class: 'cover-fallback' }, icon('video')));
    if (v.pinned) card.append(el('span', { class: 'vcard-badge pin' }, icon('pin'), T.pinned || 'Pinned'));
    else if (v.images?.length) card.append(el('span', { class: 'vcard-badge' }, icon('image'), String(v.images.length)));
    if (v.duration > 0 && !v.images?.length) card.append(el('span', { class: 'vcard-badge vcard-dur', text: dur(v.duration) }));
    card.append(el('span', { class: 'vcard-play' }, icon('play')));
    card.append(el('span', { class: 'vcard-meta' },
      v.desc ? el('span', { class: 'vcard-desc', text: v.desc }) : null,
      el('span', { class: 'vcard-stats' },
        typeof v.plays === 'number' ? el('span', {}, icon('eye'), compact(v.plays)) : null,
        typeof v.likes === 'number' ? el('span', {}, icon('heart'), compact(v.likes)) : null,
        typeof v.comments === 'number' ? el('span', {}, icon('comment'), compact(v.comments)) : null)));
    card.addEventListener('click', () => openPlayer(v));
    return card;
  }

  // ---------------------------------------------------------------- feeds (videos / stories)
  async function api(path, params, token) {
    const url = path + '?' + new URLSearchParams({ ...params, t: token }).toString();
    const res = await fetch(url, { headers: { 'X-Token': token, Accept: 'application/json' }, credentials: 'same-origin' });
    if (res.status === 429) throw new Error(T.rate || 'rate');
    if (!res.ok) {
      let code = '';
      try { code = (await res.json()).error || ''; } catch (_) {}
      throw new Error(`${T.error || 'error'} (${res.status}${code ? ' · ' + code : ''})`);
    }
    return res.json();
  }

  function skeletons(n) {
    return Array.from({ length: n }, () => el('div', { class: 'vcard skeleton', 'aria-hidden': 'true' }));
  }

  function setupFeed(panel) {
    const kind = panel.dataset.feed;
    const grid = el('div', { class: 'vgrid', 'aria-live': 'polite' });
    const more = el('div', { class: 'feed-more' });
    panel.append(grid, more);
    let cursor = '0';
    let loading = false;
    let done = false;
    let started = false;
    let paused = false; // set after an error so infinite scroll doesn't hammer the server

    const empty = (msg) => grid.replaceChildren(el('p', { class: 'feed-empty glass muted', text: msg }));

    async function load() {
      if (loading || done) return;
      paused = false;
      loading = true;
      started = true;
      const sk = skeletons(kind === 'stories' ? 4 : 8);
      grid.append(...sk);
      more.replaceChildren();
      try {
        const params = kind === 'stories' ? { u: panel.dataset.user, id: panel.dataset.uid || '' } : { u: panel.dataset.user, cursor };
        const data = await api(kind === 'stories' ? '/api/stories' : '/api/videos', params, panel.dataset.token);
        sk.forEach((s) => s.remove());
        const items = Array.isArray(data.items) ? data.items : [];
        items.forEach((v) => grid.append(videoCard(v)));
        if (kind === 'videos') {
          cursor = String(data.cursor || '0');
          done = !data.hasMore;
          const region = $('[data-region]');
          if (data.region && region && region.querySelector('.muted')) {
            region.replaceChildren(document.createTextNode(String(data.region).slice(0, 4) + ' '), el('span', { class: 'muted small', text: `(${T.fromVideos || ''})` }));
          }
        } else {
          done = true;
        }
        if (!grid.children.length) empty(kind === 'stories' ? (data.available === false ? T.storiesUnavailable : T.noStories) : T.noVideos);
        if (!done) more.append(el('button', { class: 'btn glass lit', type: 'button', onclick: load }, T.loadMore || 'More'));
      } catch (err) {
        sk.forEach((s) => s.remove());
        paused = true;
        more.replaceChildren(el('p', { class: 'muted small', text: err.message }), el('button', { class: 'btn glass lit', type: 'button', onclick: load }, T.retry || 'Retry'));
      } finally {
        loading = false;
      }
    }

    // infinite scroll for videos
    if (kind === 'videos' && 'IntersectionObserver' in window) {
      new IntersectionObserver((entries) => {
        if (entries.some((x) => x.isIntersecting) && started && !done && !paused) load();
      }, { rootMargin: '600px' }).observe(more);
    }
    return { load, get started() { return started; } };
  }

  const feeds = {};
  $$('[data-feed]').forEach((p) => { feeds[p.dataset.feed] = setupFeed(p); });
  feeds.videos?.load();

  // ---------------------------------------------------------------- tabs (ARIA tabs pattern)
  const tabs = $$('[role="tab"]');
  function selectTab(tab) {
    tabs.forEach((t) => {
      const on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      const panel = document.getElementById(t.getAttribute('aria-controls'));
      if (panel) panel.hidden = !on;
    });
    const kind = document.getElementById(tab.getAttribute('aria-controls'))?.dataset.feed;
    if (kind && feeds[kind] && !feeds[kind].started) feeds[kind].load();
  }
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => selectTab(t));
    t.addEventListener('keydown', (e) => {
      let next = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (root.dir === 'rtl') next = -next;
      if (next) {
        e.preventDefault();
        const n = tabs[(i + next + tabs.length) % tabs.length];
        n.focus();
        selectTab(n);
      }
    });
  });

  // ---------------------------------------------------------------- stat count-up animation
  if (!matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        const target = Number(en.target.dataset.count);
        const final = en.target.textContent;
        if (!(target > 0)) return;
        const t0 = performance.now();
        const step = (now) => {
          const p = Math.min(1, (now - t0) / 1100);
          const eased = 1 - Math.pow(1 - p, 3);
          en.target.textContent = p < 1 ? compact(Math.round(target * eased)) : final;
          if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      });
    }, { threshold: .4 });
    $$('[data-count]').forEach((n) => io.observe(n));
  }

  // ---------------------------------------------------------------- PWA
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
  }
})();
