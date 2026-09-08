/**
 * @file
 * Apply 短闻 day/night preference before paint (header script).
 */
(function () {
  try {
    var key = 'xmt_duanwen_theme';
    var pref = localStorage.getItem(key) || 'system';
    var resolved = pref;
    if (pref === 'system') {
      resolved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
        ? 'night'
        : 'day';
    }
    else if (pref !== 'day' && pref !== 'night') {
      pref = 'system';
      resolved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
        ? 'night'
        : 'day';
    }
    document.documentElement.setAttribute('data-xmt-theme', resolved);
    document.documentElement.setAttribute('data-xmt-theme-pref', pref === 'day' || pref === 'night' ? pref : 'system');
  }
  catch (e) {
    // ignore
  }
})();
