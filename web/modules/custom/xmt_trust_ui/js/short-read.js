/**
 * @file
 * 短闻核心交互（精简恢复版）：主题、已读、稍后、信流标记。
 * 节奏呼吸点见 short-read-rhythm.js；卡片「核先于题」由 Twig 输出。
 */
(function (Drupal) {
  'use strict';

  const READ_KEY = 'xmt_short_read_ids';
  const LATER_KEY = 'xmt_short_later';
  const STREAK_KEY = 'xmt_duanwen_streak';
  const THEME_KEY = 'xmt_duanwen_theme';

  function loadJson(key, fallback) {
    try {
      const raw = localStorage.getItem(key);
      if (!raw) return fallback;
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  function saveJson(key, val) {
    try {
      localStorage.setItem(key, JSON.stringify(val));
    } catch (e) { /* ignore */ }
  }

  function themePref() {
    const raw = localStorage.getItem(THEME_KEY);
    if (raw === 'day' || raw === 'night' || raw === 'system') return raw;
    return 'system';
  }

  function resolveTheme(pref) {
    if (pref === 'day' || pref === 'night') return pref;
    try {
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        return 'night';
      }
    } catch (e) { /* ignore */ }
    return 'day';
  }

  function applyTheme(pref) {
    const nextPref = pref === 'day' || pref === 'night' || pref === 'system' ? pref : 'system';
    const resolved = resolveTheme(nextPref);
    document.documentElement.setAttribute('data-xmt-theme', resolved);
    document.documentElement.setAttribute('data-xmt-theme-pref', nextPref);
    document.querySelectorAll('[data-xmt-theme-label]').forEach((el) => {
      el.textContent = resolved === 'night' ? '黑夜' : '白天';
    });
    document.querySelectorAll('[data-xmt-theme-toggle]').forEach((btn) => {
      btn.setAttribute('aria-pressed', resolved === 'night' ? 'true' : 'false');
      btn.title = resolved === 'night' ? '切换到白天' : '切换到黑夜';
    });
  }

  function cycleTheme() {
    const next = resolveTheme(themePref()) === 'night' ? 'day' : 'night';
    try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* ignore */ }
    applyTheme(next);
  }

  function bindThemeToggle(root) {
    if (!root) return;
    root.querySelectorAll('[data-xmt-theme-toggle]').forEach((btn) => {
      if (btn.dataset.xmtThemeBound) return;
      btn.dataset.xmtThemeBound = '1';
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        cycleTheme();
      });
    });
    applyTheme(themePref());
  }

  function todayKey() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function readIds() {
    const ids = loadJson(READ_KEY, []);
    return Array.isArray(ids) ? ids.map(String) : [];
  }

  function markRead(nid) {
    const id = String(nid);
    if (!id) return;
    const ids = readIds();
    if (!ids.includes(id)) {
      ids.push(id);
      if (ids.length > 500) ids.splice(0, ids.length - 500);
      saveJson(READ_KEY, ids);
    }
    const day = todayKey();
    const data = loadJson(STREAK_KEY, { day: '', nids: [], count: 0 });
    if (data.day !== day) {
      data.day = day;
      data.nids = [];
      data.count = 0;
    }
    if (!data.nids.includes(id)) {
      data.nids.push(id);
      data.count = data.nids.length;
      saveJson(STREAK_KEY, data);
    }
    updateStreakUi(document);
  }

  function updateStreakUi(ctx) {
    const data = loadJson(STREAK_KEY, { day: '', count: 0 });
    const count = data.day === todayKey() ? (data.count || 0) : 0;
    (ctx.querySelectorAll ? ctx.querySelectorAll('[data-xmt-streak]') : []).forEach((el) => {
      if (count > 0) {
        el.hidden = false;
        el.textContent = '今日已读 ' + count + ' 则';
      } else {
        el.hidden = true;
      }
    });
  }

  function laterList() {
    const list = loadJson(LATER_KEY, []);
    return Array.isArray(list) ? list : [];
  }

  function addLater(item) {
    const list = laterList().filter((x) => String(x.nid) !== String(item.nid));
    list.unshift(item);
    saveJson(LATER_KEY, list.slice(0, 80));
  }

  function applyReadState(root) {
    const ids = new Set(readIds());
    root.querySelectorAll('.xmt-short__card[data-nid]').forEach((card) => {
      if (ids.has(String(card.getAttribute('data-nid')))) {
        card.classList.add('is-read');
      }
    });
  }

  function observeImmerse(root) {
    if (root.dataset.immerse !== '1') return;
    if (!('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting || entry.intersectionRatio < 0.5) return;
          const card = entry.target;
          const nid = card.getAttribute('data-nid');
          if (!nid) return;
          markRead(nid);
          card.classList.add('is-read');
          const cards = root.querySelectorAll('.xmt-short__card[data-nid]');
          const idx = Array.prototype.indexOf.call(cards, card);
          const prog = root.querySelector('[data-xmt-progress]');
          if (prog && idx >= 0) prog.textContent = String(idx + 1);
        });
      },
      { threshold: [0.5] }
    );
    root.querySelectorAll('.xmt-short__card[data-nid]').forEach((c) => io.observe(c));
  }

  function bindLater(root) {
    root.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-xmt-later]');
      if (!btn) return;
      e.preventDefault();
      addLater({
        nid: btn.getAttribute('data-nid'),
        title: btn.getAttribute('data-title') || '',
        url: btn.getAttribute('data-url') || '',
      });
      btn.textContent = '已加稍后';
    });

    const openBtn = root.querySelector('[data-xmt-later-open]');
    const panel = root.querySelector('#xmt-short-later');
    const listEl = root.querySelector('[data-xmt-later-list]');
    const closeBtn = root.querySelector('[data-xmt-later-close]');
    if (openBtn && panel && listEl) {
      openBtn.addEventListener('click', (e) => {
        if (openBtn.getAttribute('href') === '/read/later' && !e.metaKey) {
          // allow navigation; also refresh panel if present
        }
        const list = laterList();
        listEl.innerHTML = list
          .map((x) => '<li><a href="' + (x.url || '#') + '">' + (x.title || x.nid) + '</a></li>')
          .join('') || '<li>暂无</li>';
        panel.hidden = false;
      });
    }
    if (closeBtn && panel) {
      closeBtn.addEventListener('click', () => { panel.hidden = true; });
    }
  }

  function bindCopyBrief(root) {
    root.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-xmt-copy-brief]');
      if (!btn) return;
      e.preventDefault();
      const text = btn.getAttribute('data-brief') || '';
      if (navigator.clipboard && text) {
        navigator.clipboard.writeText(text).then(() => {
          btn.textContent = '已复制';
        }).catch(() => {});
      }
    });
  }

  function bindDomains(root) {
    const wrap = root.querySelector('[data-xmt-domains]');
    if (!wrap) return;
    const toggle = wrap.querySelector('[data-xmt-domains-toggle]');
    const panel = wrap.querySelector('[data-xmt-domains-panel]');
    if (!toggle || !panel) return;
    toggle.addEventListener('click', () => {
      const open = panel.hidden;
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      wrap.classList.toggle('is-open', open);
      const more = toggle.querySelector('[data-xmt-domains-label-more]');
      const less = toggle.querySelector('[data-xmt-domains-label-less]');
      if (more) more.hidden = open;
      if (less) less.hidden = !open;
    });
  }

  function bindKeyboard(root) {
    if (root.dataset.xmtKeysBound) return;
    root.dataset.xmtKeysBound = '1';
    document.addEventListener('keydown', (e) => {
      if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
      if (e.key === 'i' || e.key === 'I') {
        const browse = root.querySelector('.xmt-short__seg[href*="mode=browse"]');
        const immerse = root.querySelector('.xmt-short__seg:not([href*="mode=browse"])');
        if (root.dataset.mode === 'immerse' && browse) browse.click();
        else if (immerse) immerse.click();
      }
    });
  }

  Drupal.behaviors.xmtShortRead = {
    attach: function (context) {
      const roots = once('xmt-short-read', '.xmt-short', context);
      roots.forEach((root) => {
        bindThemeToggle(root);
        applyReadState(root);
        observeImmerse(root);
        bindLater(root);
        bindCopyBrief(root);
        bindDomains(root);
        bindKeyboard(root);
        updateStreakUi(root);
      });
      // 首页入口等仅有 theme-root
      once('xmt-short-theme', '[data-xmt-theme-root]', context).forEach((el) => {
        bindThemeToggle(el);
      });
    },
  };

  // once polyfill if core/once not loaded as global
  function once(id, selector, context) {
    const ctx = context && context.querySelectorAll ? context : document;
    const nodes = ctx.querySelectorAll(selector);
    const out = [];
    nodes.forEach((el) => {
      const key = 'data-once-' + id;
      if (el.getAttribute(key)) return;
      el.setAttribute(key, '1');
      out.push(el);
    });
    return out;
  }
})(Drupal);
