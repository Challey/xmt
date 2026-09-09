/**
 * @file
 * 节奏信流（Rhythm Feed）：像短视频一样可连读，但用「呼吸点」与「今日节奏」
 * 替代无限多巴胺环。不强制中断，只给清晰、体面的停顿机会。
 */
(function (Drupal) {
  'use strict';

  const SESSION_KEY = 'xmt_duanwen_session';
  const STREAK_KEY = 'xmt_duanwen_streak';
  /** 每读完几则出现一次呼吸点（软提示，可继续）。 */
  const BREATH_EVERY = 7;
  /** 当日软目标：达到后提示「今日够了」，仍可继续。 */
  const DAY_SOFT_GOAL = 18;
  /** 单次会话软时长（分钟）。 */
  const SESSION_SOFT_MIN = 12;

  function todayKey() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

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
    } catch (e) { /* quota */ }
  }

  function sessionState() {
    const day = todayKey();
    let s = loadJson(SESSION_KEY, null);
    if (!s || s.day !== day) {
      s = { day: day, sessionStart: Date.now(), sessionReads: 0, lastBreathAt: 0, dayGoalShown: false };
    }
    return s;
  }

  function persistSession(s) {
    saveJson(SESSION_KEY, s);
  }

  function dayCount() {
    const data = loadJson(STREAK_KEY, { day: '', count: 0 });
    if (data.day !== todayKey()) return 0;
    return data.count || (data.nids && data.nids.length) || 0;
  }

  function ensureOverlay(root) {
    let el = root.querySelector('[data-xmt-rhythm]');
    if (el) return el;
    el = document.createElement('div');
    el.className = 'xmt-short__rhythm';
    el.setAttribute('data-xmt-rhythm', '1');
    el.hidden = true;
    el.innerHTML =
      '<div class="xmt-short__rhythm-card" role="dialog" aria-modal="true" aria-labelledby="xmt-rhythm-title">' +
      '<p class="xmt-short__rhythm-kicker">节奏</p>' +
      '<h3 class="xmt-short__rhythm-title" id="xmt-rhythm-title"></h3>' +
      '<p class="xmt-short__rhythm-body"></p>' +
      '<div class="xmt-short__rhythm-actions">' +
      '<button type="button" class="xmt-short__btn xmt-short__btn--primary" data-xmt-rhythm-continue>继续读</button>' +
      '<a class="xmt-short__btn xmt-short__btn--ghost" data-xmt-rhythm-today href="/read/today">看今日</a>' +
      '<button type="button" class="xmt-short__btn xmt-short__btn--ghost" data-xmt-rhythm-done>先这样</button>' +
      '</div></div>';
    root.appendChild(el);

    el.querySelector('[data-xmt-rhythm-continue]').addEventListener('click', function () {
      hideRhythm(root);
    });
    el.querySelector('[data-xmt-rhythm-done]').addEventListener('click', function () {
      hideRhythm(root);
      // 体面离开：回今日，而不是空白墙
      window.location.href = '/read/today';
    });
    return el;
  }

  function showRhythm(root, kind, title, body) {
    const el = ensureOverlay(root);
    el.dataset.kind = kind;
    el.querySelector('.xmt-short__rhythm-title').textContent = title;
    el.querySelector('.xmt-short__rhythm-body').textContent = body;
    el.hidden = false;
    el.classList.add('is-open');
    document.documentElement.classList.add('xmt-rhythm-open');
  }

  function hideRhythm(root) {
    const el = root.querySelector('[data-xmt-rhythm]');
    if (!el) return;
    el.hidden = true;
    el.classList.remove('is-open');
    document.documentElement.classList.remove('xmt-rhythm-open');
  }

  function maybeBreath(root) {
    if (root.dataset.immerse !== '1') return;
    if (document.documentElement.classList.contains('xmt-rhythm-open')) return;

    const s = sessionState();
    s.sessionReads = (s.sessionReads || 0) + 1;
    persistSession(s);

    const count = dayCount();
    const elapsedMin = (Date.now() - (s.sessionStart || Date.now())) / 60000;

    // 今日软目标：只提示一次
    if (!s.dayGoalShown && count >= DAY_SOFT_GOAL) {
      s.dayGoalShown = true;
      persistSession(s);
      showRhythm(
        root,
        'day',
        '今日节奏差不多了',
        '已读 ' + count + ' 则可信短闻。信息可以停在「够用」，不必追完。继续读也可以，你说了算。'
      );
      return;
    }

    // 会话时长软提示
    if (elapsedMin >= SESSION_SOFT_MIN && s.sessionReads % BREATH_EVERY === 0) {
      showRhythm(
        root,
        'time',
        '已经读了约 ' + Math.round(elapsedMin) + ' 分钟',
        '短闻设计成可连读，也设计成可停。歇口气，或去「今日」收个尾。'
      );
      return;
    }

    // 固定呼吸点：每 BREATH_EVERY 则
    if (s.sessionReads > 0 && s.sessionReads % BREATH_EVERY === 0 && s.sessionReads !== s.lastBreathAt) {
      s.lastBreathAt = s.sessionReads;
      persistSession(s);
      showRhythm(
        root,
        'breath',
        '呼吸点 · 已连读 ' + s.sessionReads + ' 则',
        '不是打断你，只是留一个停的台阶。上滑继续，或点「先这样」。'
      );
    }
  }

  function updatePulseMeter(root) {
    let meter = root.querySelector('[data-xmt-pulse-meter]');
    if (!meter) return;
    const count = dayCount();
    const s = sessionState();
    const goal = DAY_SOFT_GOAL;
    const pct = Math.min(100, Math.round((count / goal) * 100));
    meter.style.setProperty('--xmt-pulse', pct + '%');
    meter.setAttribute('aria-valuenow', String(count));
    meter.setAttribute('aria-valuemax', String(goal));
    const label = meter.querySelector('[data-xmt-pulse-label]');
    if (label) {
      label.textContent = count > 0 ? ('今日 ' + count + ' / ' + goal) : '今日节奏';
    }
    // 会话读数写进 streak 旁的小字
    const sess = root.querySelector('[data-xmt-session-reads]');
    if (sess) {
      sess.textContent = s.sessionReads > 0 ? ('本段 ' + s.sessionReads) : '';
      sess.hidden = s.sessionReads < 1;
    }
  }

  function bindReadObserver(root) {
    if (root.dataset.xmtRhythmBound) return;
    root.dataset.xmtRhythmBound = '1';

    // 监听卡片进入视口并标记已读时的节奏（与主脚本 mark 配合）
    const cards = function () {
      return Array.prototype.slice.call(root.querySelectorAll('.xmt-short__card[data-nid]'));
    };

    if (!('IntersectionObserver' in window)) return;

    const seen = new Set();
    const io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting || entry.intersectionRatio < 0.55) return;
          const card = entry.target;
          const nid = card.getAttribute('data-nid');
          if (!nid || seen.has(nid)) return;
          // 等主脚本 mark 之后再计节奏：短延迟
          setTimeout(function () {
            if (card.classList.contains('is-read') || root.dataset.immerse === '1') {
              if (seen.has(nid)) return;
              seen.add(nid);
              maybeBreath(root);
              updatePulseMeter(root);
            }
          }, 400);
        });
      },
      { threshold: [0.55] }
    );

    cards().forEach(function (c) {
      io.observe(c);
    });

    // 动态加载的卡片
    const list = root.querySelector('#xmt-short-rail, #xmt-short-list');
    if (list && 'MutationObserver' in window) {
      const mo = new MutationObserver(function () {
        cards().forEach(function (c) {
          if (!c.dataset.xmtRhythmObs) {
            c.dataset.xmtRhythmObs = '1';
            io.observe(c);
          }
        });
      });
      mo.observe(list, { childList: true, subtree: true });
    }

    // 手动「够了」
    root.querySelectorAll('[data-xmt-rhythm-stop]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        showRhythm(
          root,
          'stop',
          '先这样也可以',
          '短闻不靠「再看一条」留你。今日已读 ' + dayCount() + ' 则，去「今日」收个尾，或稍后再来。'
        );
      });
    });

    updatePulseMeter(root);
    // 新开会话刷新起点（同一天保留 dayGoalShown）
    const s = sessionState();
    if (!s.sessionStart || Date.now() - s.sessionStart > 30 * 60 * 1000) {
      s.sessionStart = Date.now();
      s.sessionReads = 0;
      s.lastBreathAt = 0;
      persistSession(s);
    }
  }

  Drupal.behaviors.xmtShortReadRhythm = {
    attach: function (context) {
      const roots = context.querySelectorAll
        ? context.querySelectorAll('.xmt-short[data-xmt-theme-root], .xmt-short')
        : [];
      const list = roots.length ? roots : [];
      Array.prototype.forEach.call(list, function (root) {
        if (root.nodeType !== 1) return;
        bindReadObserver(root);
        bindReadObserver(root); // idempotent
        updatePulseMeter(root);
      });
      // once() 兼容：直接扫 document
      document.querySelectorAll('.xmt-short').forEach(function (root) {
        bindReadObserver(root);
        updatePulseMeter(root);
      });
    },
  };
})(Drupal);
