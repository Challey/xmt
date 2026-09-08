(function (Drupal) {
  'use strict';

  const READ_KEY = 'xmt_short_read_ids';
  const LATER_KEY = 'xmt_short_later';
  const STREAK_KEY = 'xmt_duanwen_streak';
  const THEME_KEY = 'xmt_duanwen_theme';

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
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

  function bumpStreak(nid) {
    const day = todayKey();
    const data = loadJson(STREAK_KEY, { day: '', nids: [], count: 0 });
    if (data.day !== day) {
      data.day = day;
      data.nids = [];
      data.count = 0;
    }
    const id = String(nid);
    if (id && !data.nids.includes(id)) {
      data.nids.push(id);
      data.count = data.nids.length;
      saveJson(STREAK_KEY, data);
    }
    return data.count;
  }

  function renderStreak(root) {
    const el = root.querySelector('[data-xmt-streak]');
    if (!el) return;
    const data = loadJson(STREAK_KEY, { day: '', count: 0 });
    const count = data.day === todayKey() ? (data.count || 0) : 0;
    if (count < 1) {
      el.hidden = true;
      return;
    }
    el.hidden = false;
    el.textContent = '今日节奏 · 已读 ' + count + ' 条';
  }

  function linkHasLabel(a, labels) {
    const t = (a.textContent || '').trim();
    return labels.some((l) => t.indexOf(l) !== -1);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function escapeAttr(s) {
    return escapeHtml(s).replace(/'/g, '&#39;');
  }

  function loadJson(key, fallback) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
      return fallback;
    }
  }
  function saveJson(key, val) {
    try {
      localStorage.setItem(key, JSON.stringify(val));
    } catch (e) {
      // ignore quota
    }
  }

  function readIds() {
    return loadJson(READ_KEY, []);
  }
  function markRead(nid) {
    const ids = readIds();
    const id = String(nid);
    if (!ids.includes(id)) {
      ids.push(id);
      if (ids.length > 400) ids.splice(0, ids.length - 400);
      saveJson(READ_KEY, ids);
      scheduleProgressSync();
    }
    bumpStreak(nid);
    document.querySelectorAll('.xmt-short').forEach((root) => renderStreak(root));
  }

  function unmarkRead(nid) {
    const id = String(nid);
    const next = readIds().filter((x) => x !== id);
    saveJson(READ_KEY, next);
    syncProgressToServer(true);
  }

  function focusedCard(root, container) {
    if (!container) return null;
    const cards = Array.from(container.querySelectorAll('.xmt-short__card[data-nid]')).filter((c) => !c.hidden);
    if (!cards.length) return null;
    if (root.dataset.immerse === '1') {
      const mid = container.scrollTop + container.clientHeight * 0.35;
      let cur = cards[0];
      cards.forEach((c) => { if (c.offsetTop <= mid) cur = c; });
      return cur;
    }
    const y = window.scrollY || window.pageYOffset;
    let idx = cards.findIndex((c) => c.offsetTop + c.offsetHeight * 0.4 > y + 80);
    if (idx < 0) idx = cards.length - 1;
    return cards[idx] || null;
  }

  function applyUnmarkToCard(root, card) {
    if (!card) return;
    const nid = card.getAttribute('data-nid');
    if (!nid) return;
    unmarkRead(nid);
    card.classList.remove('is-read');
    applyUnreadFilter(root);
  }

  let progressTimer = null;
  function scheduleProgressSync() {
    const settings = (window.drupalSettings && window.drupalSettings.xmtShortRead) || {};
    if (!settings.uid || settings.uid < 1 || !settings.progressUrl) return;
    if (progressTimer) clearTimeout(progressTimer);
    progressTimer = setTimeout(() => syncProgressToServer(false), 800);
  }

  function syncProgressToServer(replace) {
    const settings = (window.drupalSettings && window.drupalSettings.xmtShortRead) || {};
    if (!settings.uid || settings.uid < 1 || !settings.progressUrl || !settings.csrfToken) return Promise.resolve();
    return fetch(settings.progressUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': settings.csrfToken,
      },
      body: JSON.stringify({
        ids: readIds(),
        mode: replace ? 'replace' : 'merge',
      }),
    }).catch(() => {});
  }

  function pullProgressFromServer() {
    const settings = (window.drupalSettings && window.drupalSettings.xmtShortRead) || {};
    if (!settings.uid || settings.uid < 1 || !settings.progressUrl) return Promise.resolve();
    return fetch(settings.progressUrl, { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (!data || !Array.isArray(data.ids)) return;
        const local = readIds();
        const merged = Array.from(new Set([].concat(data.ids.map(String), local.map(String))));
        if (merged.length > 500) merged.splice(500);
        saveJson(READ_KEY, merged);
        const serverSet = new Set(data.ids.map(String));
        const localOnly = local.filter((id) => !serverSet.has(String(id)));
        if (localOnly.length) {
          return syncProgressToServer(false);
        }
      })
      .catch(() => {});
  }

  function laterList() {
    return loadJson(LATER_KEY, []);
  }
  function addLater(item) {
    const list = laterList().filter((x) => String(x.nid) !== String(item.nid));
    list.unshift(item);
    saveJson(LATER_KEY, list.slice(0, 50));
    scheduleLaterSync();
  }
  function removeLater(nid) {
    saveJson(LATER_KEY, laterList().filter((x) => String(x.nid) !== String(nid)));
    scheduleLaterSync();
  }
  function clearLater() {
    saveJson(LATER_KEY, []);
    scheduleLaterSync();
  }

  let laterTimer = null;
  function scheduleLaterSync() {
    const settings = (window.drupalSettings && window.drupalSettings.xmtShortRead) || {};
    if (!settings.uid || settings.uid < 1 || !settings.laterSyncUrl) return;
    if (laterTimer) clearTimeout(laterTimer);
    laterTimer = setTimeout(() => syncLaterToServer(false), 800);
  }

  function syncLaterToServer(replace) {
    const settings = (window.drupalSettings && window.drupalSettings.xmtShortRead) || {};
    if (!settings.uid || settings.uid < 1 || !settings.laterSyncUrl || !settings.csrfToken) return Promise.resolve();
    return fetch(settings.laterSyncUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': settings.csrfToken,
      },
      body: JSON.stringify({
        items: laterList(),
        mode: replace ? 'replace' : 'merge',
      }),
    }).catch(() => {});
  }

  function pullLaterFromServer() {
    const settings = (window.drupalSettings && window.drupalSettings.xmtShortRead) || {};
    if (!settings.uid || settings.uid < 1 || !settings.laterSyncUrl) return Promise.resolve();
    return fetch(settings.laterSyncUrl, { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => {
        if (!data || !Array.isArray(data.items)) return;
        const local = laterList();
        const byNid = {};
        data.items.concat(local).forEach((x) => {
          if (x && x.nid) byNid[String(x.nid)] = {
            nid: String(x.nid),
            title: x.title || '短闻',
            url: x.url || '#',
          };
        });
        const merged = Object.values(byNid).slice(0, 80);
        saveJson(LATER_KEY, merged);
        const serverNids = new Set((data.items || []).map((x) => String(x.nid)));
        if (local.some((x) => !serverNids.has(String(x.nid)))) {
          return syncLaterToServer(false);
        }
      })
      .catch(() => {});
  }

  function cardHtml(card, mode) {
    const pointsArr = card.keypoints || [];
    const points = (mode === 'immerse' ? pointsArr : pointsArr.slice(0, 2))
      .map((p) => `<li>${escapeHtml(p)}</li>`)
      .join('');
    const sourceLabel = card.source_name || card.publisher || '';
    const source = sourceLabel
      ? (card.source_feed_url
        ? `<a class="xmt-short__source" href="${escapeAttr(card.source_feed_url)}">${escapeHtml(sourceLabel)}</a>`
        : `<span class="xmt-short__source">${escapeHtml(sourceLabel)}</span>`)
      : '';
    const domain = card.domain_label
      ? `<span class="xmt-short__domain-tag">${escapeHtml(card.domain_label)}</span>`
      : '';
    const official = card.source_url
      ? `<a class="xmt-short__btn xmt-short__btn--ghost" href="${escapeAttr(card.source_url)}" target="_blank" rel="noopener noreferrer">原文</a>`
      : '';
    const shareImg = card.share_image_url
      ? `<a class="xmt-short__btn xmt-short__btn--ghost" href="${escapeAttr(card.share_image_url)}" target="_blank" rel="noopener noreferrer">分享图</a>`
      : '';
    const primary = mode === 'browse'
      ? `<a class="xmt-short__btn xmt-short__btn--primary" href="${escapeAttr(card.immerse_url)}">沉浸</a>
         <a class="xmt-short__btn xmt-short__btn--ghost" href="${escapeAttr(card.detail_url)}">详情</a>`
      : `<a class="xmt-short__btn xmt-short__btn--primary" href="${escapeAttr(card.detail_url)}">详情</a>`;
    return `
<article class="xmt-short__card xmt-short__card--${mode}" data-nid="${card.nid}" id="short-${card.nid}" data-title="${escapeAttr(card.title)}" data-detail="${escapeAttr(card.detail_url)}">
  <div class="xmt-short__card-inner">
    <div class="xmt-short__meta">
      <span class="xmt-trust-badge ${escapeAttr(card.badge_class)}">${escapeHtml(card.badge_label)}</span>
      ${source}
      ${domain}
      <span class="xmt-short__time">约 ${card.reading_seconds || 10} 秒</span>
    </div>
    <h2 class="xmt-short__title">${escapeHtml(card.title)}</h2>
    <p class="xmt-short__brief">${escapeHtml(card.brief || '')}</p>
    ${points ? `<ul class="xmt-short__points${mode === 'browse' ? ' xmt-short__points--compact' : ''}">${points}</ul>` : ''}
    <p class="xmt-short__prov">${escapeHtml(card.provenance_note || 'RSS 官方收录 · 可信分层展示')}</p>
    <div class="xmt-short__actions">
      ${primary}
      ${official}
      ${shareImg}
      <button type="button" class="xmt-short__btn xmt-short__btn--ghost" data-xmt-copy-short data-url="${escapeAttr(card.detail_url || '')}">复制短链</button>
      ${card.brief ? `<button type="button" class="xmt-short__btn xmt-short__btn--ghost" data-xmt-copy-brief data-brief="${escapeAttr(card.brief)}">复制摘要</button>` : ''}
      <button type="button" class="xmt-short__btn xmt-short__btn--ghost" data-xmt-later data-nid="${card.nid}" data-title="${escapeAttr(card.title)}" data-url="${escapeAttr(card.detail_url)}">稍后再看</button>
    </div>
  </div>
</article>`;
  }

  function applyReadState(root) {
    const ids = readIds();
    root.querySelectorAll('.xmt-short__card[data-nid]').forEach((el) => {
      if (ids.includes(String(el.getAttribute('data-nid')))) {
        el.classList.add('is-read');
      }
    });
    applyUnreadFilter(root);
  }

  function applyUnreadFilter(root) {
    const on = root.dataset.hideRead === '1';
    const cards = root.querySelectorAll('.xmt-short__card[data-nid]');
    let visible = 0;
    cards.forEach((el) => {
      const hide = on && el.classList.contains('is-read');
      el.hidden = hide;
      if (!hide) visible += 1;
    });
    const unreadN = Array.from(cards).filter((el) => !el.classList.contains('is-read')).length;
    const readN = cards.length - unreadN;
    root.querySelectorAll('[data-xmt-unread-toggle]').forEach((btn) => {
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      const base = on ? '显示已读' : '隐藏已读';
      btn.textContent = cards.length ? `${base}（未读 ${unreadN}）` : base;
      btn.classList.toggle('is-active', on);
    });
    root.querySelectorAll('[data-xmt-clear-read]').forEach((btn) => {
      btn.hidden = readN < 1;
    });
    const empty = root.querySelector('[data-xmt-hide-read-empty]');
    if (empty) {
      const allHidden = on && cards.length > 0 && visible === 0;
      empty.hidden = !allHidden;
      const rail = root.querySelector('#xmt-short-rail');
      const list = root.querySelector('#xmt-short-list');
      if (rail) rail.hidden = allHidden;
      if (list) list.hidden = allHidden;
      const hint = root.querySelector('.xmt-short__hint');
      if (hint) hint.hidden = allHidden;
      const prog = root.querySelector('.xmt-short__progress');
      if (prog) prog.hidden = allHidden;
    }
  }

  function clearAllRead(root) {
    saveJson(READ_KEY, []);
    syncProgressToServer(true);
    root.querySelectorAll('.xmt-short__card.is-read').forEach((el) => {
      el.classList.remove('is-read');
      el.hidden = false;
    });
    applyUnreadFilter(root);
  }

  function bindUnreadToggle(root) {
    const btns = root.querySelectorAll('[data-xmt-unread-toggle]');
    if (!btns.length && !root.querySelector('[data-xmt-clear-read]')) return;
    try {
      if (localStorage.getItem('xmt_short_hide_read') === '1') {
        root.dataset.hideRead = '1';
      }
    } catch (e) { /* ignore */ }
    btns.forEach((btn) => {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', () => {
        const next = root.dataset.hideRead === '1' ? '0' : '1';
        root.dataset.hideRead = next;
        try { localStorage.setItem('xmt_short_hide_read', next); } catch (e) { /* ignore */ }
        applyUnreadFilter(root);
      });
    });
    root.querySelectorAll('[data-xmt-clear-read]').forEach((btn) => {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', () => {
        if (!window.confirm('清空本机已读记录？登录账号也会同步清空。')) return;
        clearAllRead(root);
      });
    });
    applyUnreadFilter(root);
  }

  function bindLater(root) {
    const openBtn = root.querySelector('[data-xmt-later-open]');
    const panel = root.querySelector('#xmt-short-later');
    const listEl = root.querySelector('[data-xmt-later-list]');
    const closeBtn = root.querySelector('[data-xmt-later-close]');

    function renderLater() {
      const list = laterList();
      if (openBtn) {
        openBtn.hidden = false;
        openBtn.textContent = list.length ? `稍后再看(${list.length})` : '稍后再看';
      }
      if (!listEl) return;
      listEl.innerHTML = list.map((x) => `<li><a href="${escapeAttr(x.url)}">${escapeHtml(x.title)}</a></li>`).join('')
        || '<li>暂无</li>';
    }

    root.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-xmt-later]');
      if (!btn) return;
      e.preventDefault();
      addLater({
        nid: btn.getAttribute('data-nid'),
        title: btn.getAttribute('data-title') || '短闻',
        url: btn.getAttribute('data-url') || '#',
      });
      renderLater();
      if (openBtn) openBtn.hidden = false;
    });

    if (openBtn && panel && openBtn.tagName === 'BUTTON') {
      openBtn.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        renderLater();
      });
    }
    if (closeBtn && panel) {
      closeBtn.addEventListener('click', () => { panel.hidden = true; });
    }
    renderLater();
  }

  function bindLaterPage(root) {
    const listEl = root.querySelector('[data-xmt-later-page-list]');
    const emptyEl = root.querySelector('[data-xmt-later-empty]');
    const clearBtn = root.querySelector('[data-xmt-later-clear]');
    if (!listEl) return;

    function render() {
      const list = laterList();
      if (clearBtn) clearBtn.hidden = list.length === 0;
      if (emptyEl) emptyEl.hidden = list.length > 0;
      listEl.innerHTML = list.map((x) => `
        <li class="xmt-short-later-page__item">
          <a class="xmt-short-later-page__link" href="${escapeAttr(x.url)}">${escapeHtml(x.title)}</a>
          <button type="button" class="xmt-short__btn xmt-short__btn--ghost" data-xmt-later-remove data-nid="${escapeAttr(x.nid)}">移除</button>
        </li>`).join('');
    }

    root.addEventListener('click', (e) => {
      const rm = e.target.closest('[data-xmt-later-remove]');
      if (rm) {
        e.preventDefault();
        removeLater(rm.getAttribute('data-nid'));
        render();
        return;
      }
      if (e.target.closest('[data-xmt-later-clear]')) {
        e.preventDefault();
        if (!window.confirm('清空稍后再看列表？')) return;
        clearLater();
        render();
      }
    });
    if (!root.dataset.xmtLaterKeys) {
      root.dataset.xmtLaterKeys = '1';
      let idx = 0;
      document.addEventListener('keydown', (e) => {
        if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
        if ((e.key === 'c' || e.key === 'C') && !e.metaKey && !e.ctrlKey) {
          const btn = root.querySelector('[data-xmt-later-clear]:not([hidden])');
          if (btn) { e.preventDefault(); btn.click(); }
          return;
        }
        if ((e.key === 'p' || e.key === 'P') && !e.metaKey && !e.ctrlKey) {
          const btn = root.querySelector('[data-xmt-copy-share]');
          if (btn) { e.preventDefault(); btn.click(); }
          return;
        }
        if ((e.key === 'f' || e.key === 'F' || e.key === '/') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          window.location.href = '/trusted/search';
          return;
        }
        const items = Array.from(root.querySelectorAll('.xmt-short-later-page__item'));
        if (!items.length) return;
        if (e.key === 'j' || e.key === 'ArrowDown') {
          e.preventDefault();
          idx = Math.min(items.length - 1, idx + 1);
          items.forEach((el, i) => el.classList.toggle('is-focus', i === idx));
          items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (e.key === 'k' || e.key === 'ArrowUp') {
          e.preventDefault();
          idx = Math.max(0, idx - 1);
          items.forEach((el, i) => el.classList.toggle('is-focus', i === idx));
          items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (e.key === 'Enter') {
          const link = items[idx] && items[idx].querySelector('.xmt-short-later-page__link');
          if (link) { e.preventDefault(); window.location.href = link.getAttribute('href'); }
        }
        if ((e.key === 'x' || e.key === 'X' || e.key === 'Delete' || e.key === 'Backspace') && !e.metaKey && !e.ctrlKey) {
          const item = items[idx];
          const rm = item && item.querySelector('[data-xmt-later-remove]');
          if (rm) {
            e.preventDefault();
            rm.click();
            const nextItems = Array.from(root.querySelectorAll('.xmt-short-later-page__item'));
            idx = Math.min(idx, Math.max(0, nextItems.length - 1));
            if (nextItems[idx]) {
              nextItems.forEach((el, i) => el.classList.toggle('is-focus', i === idx));
            }
          }
        }
        if ((e.key === 't' || e.key === 'T') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const today = root.querySelector('a[href*="/read/today"]');
          window.location.href = today ? today.getAttribute('href') : '/read/today';
        }
        if ((e.key === 'i' || e.key === 'I') && !e.metaKey && !e.ctrlKey) {
          const imm = Array.from(root.querySelectorAll('a')).find((a) => (a.textContent || '').indexOf('沉浸') !== -1);
          if (imm && imm.getAttribute('href')) {
            e.preventDefault();
            window.location.href = imm.getAttribute('href');
          }
        }
        if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
          const btn = root.querySelector('[data-xmt-copy-rss]');
          if (btn) { e.preventDefault(); btn.click(); }
        }
        if ((e.key === 'g' || e.key === 'G') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const om = root.querySelector('a[href*="/official-media"]');
          window.location.href = om ? om.getAttribute('href') : '/official-media';
        }
      });
    }
    render();
  }

  function bindTodayShortcuts(root) {
    if (root.dataset.xmtTodayKeys) return;
    root.dataset.xmtTodayKeys = '1';
    let idx = 0;
    document.addEventListener('keydown', (e) => {
      if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
      const items = Array.from(root.querySelectorAll('.xmt-short-today__item'));
      if ((e.key === 'c' || e.key === 'C') && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-share]');
        if (btn) { e.preventDefault(); btn.click(); }
        return;
      }
      if ((e.key === 'l' || e.key === 'L') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        window.location.href = '/read/later';
        return;
      }
      if ((e.key === 'i' || e.key === 'I') && !e.metaKey && !e.ctrlKey) {
        const imm = Array.from(root.querySelectorAll('a.xmt-short__mode-btn')).find((a) => (a.textContent || '').indexOf('沉浸') !== -1);
        if (imm) { e.preventDefault(); window.location.href = imm.getAttribute('href'); }
        return;
      }
      if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-rss]');
        if (btn) { e.preventDefault(); btn.click(); }
        return;
      }
      if ((e.key === 'p' || e.key === 'P') && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-share]');
        if (btn) { e.preventDefault(); btn.click(); }
        return;
      }
      if ((e.key === 'f' || e.key === 'F' || e.key === '/') && !e.metaKey && !e.ctrlKey) {
        const search = Array.from(root.querySelectorAll('a.xmt-short__mode-btn')).find((a) => (a.textContent || '').indexOf('搜索') !== -1);
        if (search) { e.preventDefault(); window.location.href = search.getAttribute('href'); }
        return;
      }
      if ((e.key === 'g' || e.key === 'G') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        const om = root.querySelector('a[href*="/official-media"]');
        window.location.href = om ? om.getAttribute('href') : '/official-media';
        return;
      }
      if (!items.length) return;
      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault();
        idx = Math.min(items.length - 1, idx + 1);
        items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        items[idx].classList.add('is-focus');
        items.forEach((el, i) => { if (i !== idx) el.classList.remove('is-focus'); });
      }
      if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault();
        idx = Math.max(0, idx - 1);
        items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        items[idx].classList.add('is-focus');
        items.forEach((el, i) => { if (i !== idx) el.classList.remove('is-focus'); });
      }
      if (e.key === 'Enter' || e.key === 'e' || e.key === 'E') {
        const link = items[idx] && items[idx].querySelector('.xmt-short-today__link');
        if (link) { e.preventDefault(); window.location.href = link.getAttribute('href'); }
      }
      if ((e.key === 'a' || e.key === 'A') && !e.metaKey && !e.ctrlKey) {
        const btn = items[idx] && items[idx].querySelector('[data-xmt-later]');
        if (btn) { e.preventDefault(); btn.click(); }
      }
      if ((e.key === 'b' || e.key === 'B') && !e.metaKey && !e.ctrlKey) {
        const brief = items[idx] && items[idx].querySelector('.xmt-short-today__brief');
        if (brief) {
          e.preventDefault();
          copyText((brief.textContent || '').trim());
        }
      }
    });
  }

  function setupInfinite(root, container, mode) {
    let loading = false;
    let after = 0;
    const cards = container.querySelectorAll('.xmt-short__card');
    if (cards.length) {
      after = cards[cards.length - 1].getAttribute('data-nid');
    }

    async function loadMore() {
      if (loading || !root.dataset.api) return;
      loading = true;
      try {
        const url = new URL(root.dataset.api, window.location.origin);
        url.searchParams.set('after', after || '0');
        url.searchParams.set('mode', mode);
        const res = await fetch(url.toString(), { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.items || !data.items.length) return;
        container.insertAdjacentHTML('beforeend', data.items.map((c) => cardHtml(c, mode)).join(''));
        after = data.next_after || after;
        applyReadState(root);
        const total = root.querySelector('[data-xmt-total]');
        if (total) {
          total.textContent = String(container.querySelectorAll('.xmt-short__card').length);
        }
      } catch (e) {
        // ignore
      } finally {
        loading = false;
      }
    }

    if (mode === 'immerse') {
      container.addEventListener('scroll', () => {
        const nearEnd = container.scrollTop + container.clientHeight > container.scrollHeight - container.clientHeight * 1.2;
        if (nearEnd) loadMore();
        updateProgress(root, container);
        markVisibleRead(container);
      }, { passive: true });

      container.addEventListener('keydown', (e) => {
        const h = container.clientHeight;
        if (e.key === 'ArrowDown' || e.key === 'j' || e.key === 'PageDown') {
          e.preventDefault();
          if (root.dataset.hideRead === '1') {
            const cards = Array.from(container.querySelectorAll('.xmt-short__card:not(.is-read)'));
            const mid = container.scrollTop + h * 0.35;
            const next = cards.find((c) => c.offsetTop > mid + 8);
            if (next) next.scrollIntoView({ behavior: 'smooth', block: 'start' });
            else container.scrollBy({ top: h, behavior: 'smooth' });
          }
          else {
            container.scrollBy({ top: h, behavior: 'smooth' });
          }
        }
        if (e.key === 'ArrowUp' || e.key === 'k' || e.key === 'PageUp') {
          e.preventDefault();
          if (root.dataset.hideRead === '1') {
            const cards = Array.from(container.querySelectorAll('.xmt-short__card:not(.is-read)'));
            const mid = container.scrollTop + h * 0.35;
            let prev = null;
            for (let i = cards.length - 1; i >= 0; i -= 1) {
              if (cards[i].offsetTop < mid - 8) { prev = cards[i]; break; }
            }
            if (prev) prev.scrollIntoView({ behavior: 'smooth', block: 'start' });
            else container.scrollBy({ top: -h, behavior: 'smooth' });
          }
          else {
            container.scrollBy({ top: -h, behavior: 'smooth' });
          }
        }
        if ((e.key === 'u' || e.key === 'U') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          applyUnmarkToCard(root, focusedCard(root, container));
        }
        if ((e.key === 'm' || e.key === 'M') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (card) {
            markRead(card.getAttribute('data-nid'));
            card.classList.add('is-read');
            applyUnreadFilter(root);
          }
        }
        if ((e.key === 'h' || e.key === 'H') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-unread-toggle]');
          if (btn) btn.click();
        }
        if ((e.key === 'c' || e.key === 'C') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-clear-read]:not([hidden])');
          if (btn) btn.click();
        }
        if ((e.key === 'l' || e.key === 'L') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const later = root.querySelector('[data-xmt-later-open]');
          if (later) {
            if (later.tagName === 'A' && later.getAttribute('href')) {
              window.location.href = later.getAttribute('href');
            }
            else later.click();
          }
        }
        if ((e.key === 'o' || e.key === 'O') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (!card) return;
          const src = Array.from(card.querySelectorAll('a[href][target="_blank"]'))
            .find((a) => (a.textContent || '').trim() === '原文');
          if (src) window.open(src.getAttribute('href'), '_blank', 'noopener');
        }
        if ((e.key === 'b' || e.key === 'B') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (!card) return;
          const btn = card.querySelector('[data-xmt-copy-brief]');
          if (btn) btn.click();
          else {
            const brief = card.querySelector('.xmt-short__brief');
            if (brief) copyText((brief.textContent || '').trim());
          }
        }
        if ((e.key === 't' || e.key === 'T') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const today = root.querySelector('a[href*="/read/today"]');
          window.location.href = today ? today.getAttribute('href') : '/read/today';
        }
        if ((e.key === 'i' || e.key === 'I') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          if (root.dataset.mode === 'immerse') {
            const browse = root.querySelector('a.xmt-short__mode-btn[href]:not([href*="mode=immerse"])');
            // Prefer explicit 速览 link text.
            const links = root.querySelectorAll('.xmt-short__top-actions a.xmt-short__mode-btn');
            let dest = null;
            links.forEach((a) => {
              if ((a.textContent || '').indexOf('扫读') !== -1) dest = a.getAttribute('href');
            });
            if (dest) window.location.href = dest;
            else if (browse) window.location.href = browse.getAttribute('href');
          }
          else {
            const imm = root.querySelector('a[href*="mode=immerse"]');
            if (imm) window.location.href = imm.getAttribute('href');
          }
        }
        if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-copy-rss]');
          if (btn) btn.click();
        }
        if ((e.key === 'p' || e.key === 'P') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-copy-share]');
          if (btn) btn.click();
        }
        if ((e.key === 'a' || e.key === 'A') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          const btn = card && card.querySelector('[data-xmt-later]');
          if (btn) btn.click();
        }
        if ((e.key === 'e' || e.key === 'E') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (!card) return;
          let detail = card.getAttribute('data-detail');
          if (!detail) {
            const links = card.querySelectorAll('a[href*="/read/"]');
            for (const a of links) {
              if ((a.textContent || '').trim() === '详情') { detail = a.getAttribute('href'); break; }
            }
          }
          if (detail) window.location.href = detail;
        }
        if ((e.key === 'f' || e.key === 'F' || e.key === '/') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const search = Array.from(root.querySelectorAll('a.xmt-short__mode-btn, a[href*="/trusted/search"]'))
            .find((a) => (a.getAttribute('href') || '').indexOf('/trusted/search') !== -1 || (a.textContent || '').indexOf('搜索') !== -1);
          if (search) window.location.href = search.getAttribute('href');
        }
        if ((e.key === 'g' || e.key === 'G') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const om = root.querySelector('a[href*="/official-media"]');
          window.location.href = om ? om.getAttribute('href') : '/official-media';
        }
      });
    }
    else {
      const sentinel = root.querySelector('#xmt-short-sentinel');
      if (sentinel && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
          if (entries.some((en) => en.isIntersecting)) loadMore();
        }, { rootMargin: '400px' });
        io.observe(sentinel);
      }
      else {
        window.addEventListener('scroll', () => {
          if (window.innerHeight + window.scrollY > document.body.offsetHeight - 800) loadMore();
        }, { passive: true });
      }
      document.addEventListener('keydown', (e) => {
        if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
        const hideOn = root.dataset.hideRead === '1';
        const cards = Array.from(container.querySelectorAll('.xmt-short__card')).filter((c) => !c.hidden && (!hideOn || !c.classList.contains('is-read')));
        if ((e.key === 'u' || e.key === 'U') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          applyUnmarkToCard(root, focusedCard(root, container));
          return;
        }
        if ((e.key === 'm' || e.key === 'M') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (card) {
            markRead(card.getAttribute('data-nid'));
            card.classList.add('is-read');
            applyUnreadFilter(root);
          }
          return;
        }
        if ((e.key === 'h' || e.key === 'H') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-unread-toggle]');
          if (btn) btn.click();
          return;
        }
        if ((e.key === 'c' || e.key === 'C') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-clear-read]:not([hidden])');
          if (btn) btn.click();
          return;
        }
        if ((e.key === 'l' || e.key === 'L') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const later = root.querySelector('[data-xmt-later-open]');
          if (later) {
            if (later.tagName === 'A' && later.getAttribute('href')) {
              window.location.href = later.getAttribute('href');
            }
            else later.click();
          }
          return;
        }
        if ((e.key === 'o' || e.key === 'O') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (!card) return;
          const src = Array.from(card.querySelectorAll('a[href][target="_blank"]'))
            .find((a) => (a.textContent || '').trim() === '原文');
          if (src) window.open(src.getAttribute('href'), '_blank', 'noopener');
          return;
        }
        if ((e.key === 'b' || e.key === 'B') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (!card) return;
          const btn = card.querySelector('[data-xmt-copy-brief]');
          if (btn) btn.click();
          else {
            const brief = card.querySelector('.xmt-short__brief');
            if (brief) copyText((brief.textContent || '').trim());
          }
          return;
        }
        if ((e.key === 't' || e.key === 'T') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const today = root.querySelector('a[href*="/read/today"]');
          window.location.href = today ? today.getAttribute('href') : '/read/today';
          return;
        }
        if ((e.key === 'i' || e.key === 'I') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          if (root.dataset.mode === 'immerse') {
            const links = root.querySelectorAll('.xmt-short__top-actions a.xmt-short__mode-btn');
            let dest = null;
            links.forEach((a) => {
              if ((a.textContent || '').indexOf('扫读') !== -1) dest = a.getAttribute('href');
            });
            if (dest) window.location.href = dest;
          }
          else {
            const imm = root.querySelector('a[href*="mode=immerse"]');
            if (imm) window.location.href = imm.getAttribute('href');
          }
          return;
        }
        if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-copy-rss]');
          if (btn) btn.click();
          return;
        }
        if ((e.key === 'e' || e.key === 'E') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          if (!card) return;
          let detail = card.getAttribute('data-detail');
          if (!detail) {
            const links = card.querySelectorAll('a[href*="/read/"]');
            for (const a of links) {
              if ((a.textContent || '').trim() === '详情') { detail = a.getAttribute('href'); break; }
            }
          }
          if (detail) window.location.href = detail;
          return;
        }
        if ((e.key === 'p' || e.key === 'P') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const btn = root.querySelector('[data-xmt-copy-share]');
          if (btn) btn.click();
          return;
        }
        if ((e.key === 'a' || e.key === 'A') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const card = focusedCard(root, container);
          const btn = card && card.querySelector('[data-xmt-later]');
          if (btn) btn.click();
          return;
        }
        if ((e.key === 'f' || e.key === 'F' || e.key === '/') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const search = Array.from(root.querySelectorAll('a.xmt-short__mode-btn, a[href*="/trusted/search"]'))
            .find((a) => (a.getAttribute('href') || '').indexOf('/trusted/search') !== -1 || (a.textContent || '').indexOf('搜索') !== -1);
          if (search) window.location.href = search.getAttribute('href');
          return;
        }
        if ((e.key === 'g' || e.key === 'G') && !e.metaKey && !e.ctrlKey) {
          e.preventDefault();
          const om = root.querySelector('a[href*="/official-media"]');
          window.location.href = om ? om.getAttribute('href') : '/official-media';
          return;
        }
        if (!cards.length) return;
        const y = window.scrollY || window.pageYOffset;
        let idx = cards.findIndex((c) => c.offsetTop + c.offsetHeight * 0.4 > y + 80);
        if (idx < 0) idx = cards.length - 1;
        if (e.key === 'j' || e.key === 'ArrowDown') {
          e.preventDefault();
          const next = cards[Math.min(cards.length - 1, idx + (cards[idx] && cards[idx].offsetTop <= y + 40 ? 1 : 0))];
          if (next) next.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        if (e.key === 'k' || e.key === 'ArrowUp') {
          e.preventDefault();
          const prev = cards[Math.max(0, idx - 1)];
          if (prev) prev.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
      container.addEventListener('click', (e) => {
        const card = e.target.closest('.xmt-short__card[data-nid]');
        if (card) markRead(card.getAttribute('data-nid'));
      });
    }

    return { loadMore };
  }

  function updateProgress(root, rail) {
    const cards = [...rail.querySelectorAll('.xmt-short__card')];
    if (!cards.length) return;
    const mid = rail.scrollTop + rail.clientHeight / 2;
    let idx = 0;
    cards.forEach((c, i) => {
      if (c.offsetTop <= mid) idx = i;
    });
    const cur = root.querySelector('[data-xmt-progress]');
    const total = root.querySelector('[data-xmt-total]');
    if (cur) cur.textContent = String(idx + 1);
    if (total) total.textContent = String(cards.length);
  }

  function markVisibleRead(rail) {
    const mid = rail.scrollTop + rail.clientHeight * 0.35;
    rail.querySelectorAll('.xmt-short__card').forEach((c) => {
      if (c.offsetTop <= mid) {
        markRead(c.getAttribute('data-nid'));
        c.classList.add('is-read');
      }
    });
  }

  function focusCard(root, rail) {
    const focus = root.dataset.focus;
    if (!focus || focus === '0') return;
    const el = root.querySelector(`#short-${focus}`);
    if (el && rail) {
      el.scrollIntoView({ block: 'start' });
      markRead(focus);
    }
    else if (el) {
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  }

  function isWeChat() {
    return /MicroMessenger/i.test(navigator.userAgent || '');
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        resolve();
      } catch (e) {
        reject(e);
      } finally {
        document.body.removeChild(ta);
      }
    });
  }

  function updateSyncStatus(root) {
    const el = root.querySelector('[data-xmt-sync-status]');
    if (!el) return;
    const settings = (window.drupalSettings && window.drupalSettings.xmtShortRead) || {};
    el.hidden = false;
    if (settings.uid && settings.uid > 0) {
      el.textContent = '已登录：已读 / 稍后再看会同步到账号';
      el.classList.add('is-on');
      el.classList.remove('is-off');
    }
    else {
      el.textContent = '未登录：进度仅保存在本机浏览器';
      el.classList.add('is-off');
      el.classList.remove('is-on');
    }
  }

  function bindDetailShortcuts(root) {
    if (!root.classList.contains('xmt-short-detail') || root.dataset.xmtKeys) return;
    root.dataset.xmtKeys = '1';
    document.addEventListener('keydown', (e) => {
      if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
      const key = e.key.toLowerCase();
      if (key === 'j' || e.key === 'ArrowDown') {
        const next = root.querySelector('.xmt-short-detail__pager-link--next');
        if (next) { e.preventDefault(); next.click(); }
      }
      else if (key === 'k' || e.key === 'ArrowUp') {
        const prev = root.querySelector('.xmt-short-detail__pager-link--prev');
        if (prev) { e.preventDefault(); prev.click(); }
      }
      else if (key === 'c' && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-share]');
        if (btn) { e.preventDefault(); btn.click(); }
      }
      else if (key === 'y' && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-short]');
        if (btn) { e.preventDefault(); btn.click(); }
      }
      else if (key === 's' && !e.metaKey && !e.ctrlKey) {
        const src = root.querySelector('a[href*="source="]');
        if (src) { e.preventDefault(); window.location.href = src.getAttribute('href'); }
      }
      else if (key === 'u' && !e.metaKey && !e.ctrlKey) {
        const nid = root.getAttribute('data-nid') || (root.querySelector('[data-nid]') && root.querySelector('[data-nid]').getAttribute('data-nid'));
        if (nid) {
          e.preventDefault();
          unmarkRead(nid);
        }
      }
      else if (key === 'm' && !e.metaKey && !e.ctrlKey) {
        const nid = root.getAttribute('data-nid') || (root.querySelector('[data-nid]') && root.querySelector('[data-nid]').getAttribute('data-nid'));
        if (nid) {
          e.preventDefault();
          markRead(nid);
        }
      }
      else if (key === 'l' && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-later]');
        if (btn) { e.preventDefault(); btn.click(); }
      }
      else if (key === 'o' && !e.metaKey && !e.ctrlKey) {
        const links = root.querySelectorAll('a[href][target="_blank"]');
        for (const a of links) {
          const t = (a.textContent || '').trim();
          if (t === '原文' || t.indexOf('官方原文') !== -1) {
            e.preventDefault();
            window.open(a.getAttribute('href'), '_blank', 'noopener');
            break;
          }
        }
      }
      else if (key === 'b' && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-brief]');
        if (btn) { e.preventDefault(); btn.click(); }
        else {
          const brief = root.querySelector('.xmt-short-detail__brief p');
          if (brief) {
            e.preventDefault();
            copyText((brief.textContent || '').trim());
          }
        }
      }
      else if (key === 't' && !e.metaKey && !e.ctrlKey) {
        const today = root.querySelector('a[href*="/read/today"]');
        e.preventDefault();
        window.location.href = today ? today.getAttribute('href') : '/read/today';
      }
      else if (key === 'i' && !e.metaKey && !e.ctrlKey) {
        const imm = root.querySelector('a[href*="mode=immerse"]');
        if (imm) { e.preventDefault(); window.location.href = imm.getAttribute('href'); }
      }
      else if (key === 'r' && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-rss]');
        if (btn) { e.preventDefault(); btn.click(); }
      }
      else if ((key === 'f' || e.key === '/') && !e.metaKey && !e.ctrlKey) {
        const search = root.querySelector('a[href*="/trusted/search"]');
        if (search) { e.preventDefault(); window.location.href = search.getAttribute('href'); }
      }
      else if (key === 'g' && !e.metaKey && !e.ctrlKey) {
        const om = root.querySelector('a[href*="/official-media"]');
        e.preventDefault();
        window.location.href = om ? om.getAttribute('href') : '/official-media';
      }
    });
  }

  function bindOfficialMediaShortcuts(root) {
    if (root.dataset.xmtOfficialKeys) return;
    root.dataset.xmtOfficialKeys = '1';
    let idx = 0;
    document.addEventListener('keydown', (e) => {
      if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
      if ((e.key === 't' || e.key === 'T') && !e.metaKey && !e.ctrlKey) {
        const today = root.querySelector('a[href*="/read/today"]');
        if (today) { e.preventDefault(); window.location.href = today.getAttribute('href'); }
        return;
      }
      if ((e.key === 'l' || e.key === 'L') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        window.location.href = '/read/later';
        return;
      }
      if ((e.key === 'f' || e.key === 'F' || e.key === '/') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        window.location.href = '/trusted/search';
        return;
      }
      if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-rss]');
        if (btn) { e.preventDefault(); btn.click(); }
        return;
      }
      const items = Array.from(root.querySelectorAll('.xmt-official-media__item'));
      if (!items.length) return;
      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault();
        idx = Math.min(items.length - 1, idx + 1);
        items.forEach((el, i) => el.classList.toggle('is-focus', i === idx));
        items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault();
        idx = Math.max(0, idx - 1);
        items.forEach((el, i) => el.classList.toggle('is-focus', i === idx));
        items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      if (e.key === 'Enter' || e.key === 'e' || e.key === 'E') {
        const link = items[idx] && items[idx].querySelector('.post-title a');
        if (link) { e.preventDefault(); window.location.href = link.getAttribute('href'); }
      }
      if ((e.key === 'i' || e.key === 'I') && !e.metaKey && !e.ctrlKey) {
        const imm = items[idx] && items[idx].querySelector('a[href*="mode=immerse"]');
        if (imm) { e.preventDefault(); window.location.href = imm.getAttribute('href'); }
      }
    });
  }

  function bindSearchShortcuts(root) {
    if (root.dataset.xmtSearchKeys) return;
    root.dataset.xmtSearchKeys = '1';
    let idx = 0;
    document.addEventListener('keydown', (e) => {
      if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
      if ((e.key === 't' || e.key === 'T') && !e.metaKey && !e.ctrlKey) {
        const today = root.querySelector('a[href*="/read/today"]');
        if (today) { e.preventDefault(); window.location.href = today.getAttribute('href'); }
        return;
      }
      if ((e.key === 'l' || e.key === 'L') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        window.location.href = '/read/later';
        return;
      }
      if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
        const btn = root.querySelector('[data-xmt-copy-rss]');
        if (btn) { e.preventDefault(); btn.click(); }
        return;
      }
      if ((e.key === 'p' || e.key === 'P') && !e.metaKey && !e.ctrlKey) {
        const share = root.querySelector('[data-xmt-copy-share]');
        if (share) { e.preventDefault(); share.click(); }
        return;
      }
      if ((e.key === 'g' || e.key === 'G') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        const om = root.querySelector('a[href*="/official-media"]');
        window.location.href = om ? om.getAttribute('href') : '/official-media';
        return;
      }
      if ((e.key === 'f' || e.key === 'F' || e.key === '/') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        window.location.href = '/trusted/search';
        return;
      }
      const items = Array.from(root.querySelectorAll('.xmt-search__item'));
      if (!items.length) return;
      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault();
        idx = Math.min(items.length - 1, idx + 1);
        items.forEach((el, i) => el.classList.toggle('is-focus', i === idx));
        items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault();
        idx = Math.max(0, idx - 1);
        items.forEach((el, i) => el.classList.toggle('is-focus', i === idx));
        items[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      if (e.key === 'Enter' || e.key === 'e' || e.key === 'E') {
        const link = items[idx] && items[idx].querySelector('.xmt-search__title a, a.xmt-short__btn--primary');
        if (link) { e.preventDefault(); window.location.href = link.getAttribute('href'); }
      }
      if ((e.key === 'i' || e.key === 'I') && !e.metaKey && !e.ctrlKey) {
        const imm = items[idx] && Array.from(items[idx].querySelectorAll('a')).find((a) => (a.textContent || '').indexOf('沉浸') !== -1);
        if (imm) { e.preventDefault(); window.location.href = imm.getAttribute('href'); }
      }
    });
  }

  function bindShareAndWeChat(root) {
    const settings = (Drupal.settings && Drupal.settings.xmtShortRead)
      || (window.drupalSettings && window.drupalSettings.xmtShortRead)
      || {};
    const tip = root.querySelector('[data-xmt-wechat-tip]') || document.querySelector('[data-xmt-wechat-tip]');
    if (tip && isWeChat()) {
      tip.hidden = false;
    }

    root.addEventListener('click', (e) => {
      const briefBtn = e.target.closest('[data-xmt-copy-brief]');
      if (briefBtn) {
        e.preventDefault();
        const text = briefBtn.getAttribute('data-brief') || '';
        copyText(text).then(() => {
          const prev = briefBtn.textContent;
          briefBtn.textContent = '已复制摘要';
          setTimeout(() => { briefBtn.textContent = prev; }, 1600);
        }).catch(() => {
          window.prompt('复制摘要', text);
        });
        return;
      }
      const rssBtn = e.target.closest('[data-xmt-copy-rss]');
      if (rssBtn) {
        e.preventDefault();
        const href = rssBtn.getAttribute('data-rss') || '';
        const abs = href
          ? (href.indexOf('http') === 0 ? href : (window.location.origin + href))
          : (window.location.origin + '/read/rss.xml');
        copyText(abs).then(() => {
          const prev = rssBtn.textContent;
          rssBtn.textContent = '已复制 RSS';
          setTimeout(() => { rssBtn.textContent = prev; }, 1600);
        }).catch(() => {
          window.prompt('复制 RSS', abs);
        });
        return;
      }
      const shortBtn = e.target.closest('[data-xmt-copy-short]');
      if (shortBtn) {
        e.preventDefault();
        const url = shortBtn.getAttribute('data-url') || settings.shareUrl || window.location.href;
        copyText(url).then(() => {
          const prev = shortBtn.textContent;
          shortBtn.textContent = '已复制短链';
          setTimeout(() => { shortBtn.textContent = prev; }, 1600);
        }).catch(() => {
          window.prompt('复制短链', url);
        });
        return;
      }
      const btn = e.target.closest('[data-xmt-copy-share]');
      if (!btn) return;
      e.preventDefault();
      const url = btn.getAttribute('data-url') || settings.shareUrl || window.location.href;
      copyText(url).then(() => {
        const prev = btn.textContent;
        btn.textContent = '已复制';
        setTimeout(() => { btn.textContent = prev; }, 1600);
      }).catch(() => {
        window.prompt('复制链接', url);
      });
    });
  }

  function bindWechatHelp(root) {
    const input = root.querySelector('[data-xmt-copy-url]');
    const btn = root.querySelector('[data-xmt-copy-btn]');
    const ok = root.querySelector('[data-xmt-copy-ok]');
    if (!input || !btn) return;
    btn.addEventListener('click', () => {
      copyText(input.value).then(() => {
        if (ok) {
          ok.hidden = false;
          setTimeout(() => { ok.hidden = true; }, 1600);
        }
      }).catch(() => {
        input.select();
      });
    });
    if (isWeChat() && input) {
      input.focus();
      input.select();
    }
  }

  function bindDomainRail(root) {
    const wrap = root.querySelector('[data-xmt-domains]');
    if (!wrap || wrap.dataset.xmtDomainsBound) {
      return;
    }
    wrap.dataset.xmtDomainsBound = '1';
    const btn = wrap.querySelector('[data-xmt-domains-toggle]');
    const panel = wrap.querySelector('[data-xmt-domains-panel]');
    const labelMore = wrap.querySelector('[data-xmt-domains-label-more]');
    const labelLess = wrap.querySelector('[data-xmt-domains-label-less]');
    if (!btn || !panel) {
      return;
    }

    const KEY = 'xmt_duanwen_domains_open';
    const apply = (open) => {
      wrap.classList.toggle('is-open', open);
      panel.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (labelMore) {
        labelMore.hidden = open;
      }
      if (labelLess) {
        labelLess.hidden = !open;
      }
      try {
        sessionStorage.setItem(KEY, open ? '1' : '0');
      }
      catch (e) {
        // ignore
      }
    };

    let open = wrap.hasAttribute('data-open');
    try {
      const saved = sessionStorage.getItem(KEY);
      if (saved === '1') {
        open = true;
      }
      if (saved === '0') {
        open = false;
      }
    }
    catch (e) {
      // ignore
    }
    apply(open);

    btn.addEventListener('click', () => {
      apply(panel.hidden);
    });
  }

  Drupal.behaviors.xmtShortRead = {
    attach(context) {
      const home = context.querySelector?.('.xmt-short-home') || document.querySelector('.xmt-short-home');
      if (home && !home.dataset.xmtBound) {
        home.dataset.xmtBound = '1';
        bindShareAndWeChat(home);
        bindThemeToggle(home);
      }

      const today = context.querySelector?.('.xmt-short--today') || document.querySelector('.xmt-short--today');
      if (today && !today.dataset.xmtBound) {
        today.dataset.xmtBound = '1';
        bindShareAndWeChat(today);
        bindLater(today);
        bindTodayShortcuts(today);
        bindThemeToggle(today);
        pullLaterFromServer();
      }

      const searchPage = context.querySelector?.('.xmt-search') || document.querySelector('.xmt-search');
      if (searchPage && !searchPage.dataset.xmtBound) {
        searchPage.dataset.xmtBound = '1';
        bindShareAndWeChat(searchPage);
        bindSearchShortcuts(searchPage);
      }

      const official = context.querySelector?.('.xmt-official-media') || document.querySelector('.xmt-official-media');
      if (official && !official.dataset.xmtBound) {
        official.dataset.xmtBound = '1';
        bindShareAndWeChat(official);
        bindOfficialMediaShortcuts(official);
      }

      const publishers = context.querySelector?.('.xmt-publisher-directory') || document.querySelector('.xmt-publisher-directory');
      if (publishers && !publishers.dataset.xmtBound) {
        publishers.dataset.xmtBound = '1';
        bindShareAndWeChat(publishers);
      }

      const laterPage = context.querySelector?.('[data-xmt-later-page]') || document.querySelector('[data-xmt-later-page]');
      if (laterPage && !laterPage.dataset.xmtBound) {
        laterPage.dataset.xmtBound = '1';
        bindShareAndWeChat(laterPage);
        pullLaterFromServer().then(() => bindLaterPage(laterPage));
      }

      const help = context.querySelector?.('[data-xmt-wechat-help]') || document.querySelector('[data-xmt-wechat-help]');
      if (help && !help.dataset.xmtBound) {
        help.dataset.xmtBound = '1';
        bindWechatHelp(help);
      }

      // Detail page later button
      const detail = context.querySelector?.('.xmt-short-detail') || document.querySelector('.xmt-short-detail');
      if (detail && !detail.dataset.xmtBound) {
        detail.dataset.xmtBound = '1';
        bindLater(detail);
        bindShareAndWeChat(detail);
        bindDetailShortcuts(detail);
        bindThemeToggle(detail);
        pullLaterFromServer();
      }

      const root = context.querySelector?.('.xmt-short') || onceRoot(context);
      if (!root || root.dataset.xmtBound) return;
      root.dataset.xmtBound = '1';

      const mode = root.dataset.mode || 'browse';
      if (mode === 'immerse') {
        document.body.classList.add('xmt-mode-immerse');
      }

      bindLater(root);
      bindShareAndWeChat(root);
      bindUnreadToggle(root);
      bindDomainRail(root);
      bindThemeToggle(root);
      updateSyncStatus(root);
      renderStreak(root);
      pullLaterFromServer();
      pullProgressFromServer().then(() => {
        applyReadState(root);
        renderStreak(root);
      });
      applyReadState(root);

      const rail = root.querySelector('#xmt-short-rail');
      const list = root.querySelector('#xmt-short-list');
      const container = mode === 'immerse' ? rail : list;
      if (!container) return;

      setupInfinite(root, container, mode);
      if (mode === 'immerse' && rail) {
        focusCard(root, rail);
        updateProgress(root, rail);
        rail.focus({ preventScroll: true });
      }
      else {
        focusCard(root, null);
      }
    },
  };

  function onceRoot(context) {
    if (context.nodeType === 1 && context.classList?.contains('xmt-short')) return context;
    return document.querySelector('.xmt-short');
  }
})(Drupal);
