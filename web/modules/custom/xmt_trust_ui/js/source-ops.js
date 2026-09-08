(function (Drupal) {
  'use strict';
  const KEY = 'xmt_source_ops_filters';

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
      try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        resolve();
      }
      catch (e) {
        reject(e);
      }
    });
  }

  Drupal.behaviors.xmtSourceOpsRemember = {
    attach(context) {
      const form = context.querySelector?.('.xmt-source-ops-search') || document.querySelector('.xmt-source-ops-search');
      if (form && !form.dataset.xmtBound) {
        form.dataset.xmtBound = '1';

        try {
          const saved = JSON.parse(localStorage.getItem(KEY) || '{}');
          if (saved && !window.location.search) {
            const params = new URLSearchParams();
            ['group', 'trust', 'status', 'q'].forEach((k) => {
              if (saved[k]) params.set(k, saved[k]);
            });
            const qs = params.toString();
            if (qs) {
              window.location.replace(window.location.pathname + '?' + qs);
              return;
            }
          }
        } catch (e) {
          // ignore
        }

        const params = new URLSearchParams(window.location.search);
        const state = {
          group: params.get('group') || '',
          trust: params.get('trust') || '',
          status: params.get('status') || '',
          q: params.get('q') || '',
        };
        try {
          localStorage.setItem(KEY, JSON.stringify(state));
        } catch (e) {
          // ignore
        }
      }

      const root = context.querySelector?.('.xmt-source-ops-table-wrap') || document.querySelector('.xmt-source-ops-table-wrap') || document;
      if (root.dataset && root.dataset.xmtCopyBound) return;
      if (root.dataset) root.dataset.xmtCopyBound = '1';
      (root.addEventListener ? root : document).addEventListener('click', (e) => {
        const feedBtn = e.target.closest && e.target.closest('[data-xmt-copy-feed]');
        if (feedBtn) {
          e.preventDefault();
          const url = feedBtn.getAttribute('data-xmt-copy-feed') || '';
          if (!url) return;
          copyText(url).then(() => {
            const prev = feedBtn.textContent;
            feedBtn.textContent = '已复制';
            setTimeout(() => { feedBtn.textContent = prev; }, 1400);
          }).catch(() => {
            window.prompt('复制 Feed URL', url);
          });
          return;
        }
        const shortBtn = e.target.closest && e.target.closest('[data-xmt-copy-short]');
        if (!shortBtn) return;
        e.preventDefault();
        const url = shortBtn.getAttribute('data-xmt-copy-short') || '';
        if (!url) return;
        copyText(url).then(() => {
          const prev = shortBtn.textContent;
          shortBtn.textContent = '已复制短链';
          setTimeout(() => { shortBtn.textContent = prev; }, 1400);
        }).catch(() => {
          window.prompt('复制短链', url);
        });
      });
    },
  };
})(Drupal);
