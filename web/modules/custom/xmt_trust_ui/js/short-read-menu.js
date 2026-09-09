/**
 * @file
 * 顶栏「⋯」菜单：默认关闭；点击切换；点菜单项或外侧后收起。
 */
(function (Drupal) {
  'use strict';

  function bindTopMenu(root) {
    if (!root) return;
    root.querySelectorAll('[data-xmt-menu]').forEach((menu) => {
      if (menu.dataset.xmtMenuBound) return;
      menu.dataset.xmtMenuBound = '1';
      const toggle = menu.querySelector('[data-xmt-menu-toggle]');
      const panel = menu.querySelector('[data-xmt-menu-panel]');
      if (!toggle || !panel) return;

      const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.classList.toggle('is-open', open);
      };

      // 始终从收起状态开始
      setOpen(false);

      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        setOpen(!!panel.hidden);
      });

      document.addEventListener('click', (e) => {
        if (!panel.hidden && !menu.contains(e.target)) {
          setOpen(false);
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) {
          setOpen(false);
        }
      });

      // 任意菜单项（链接或按钮）点击后收起
      panel.addEventListener('click', (e) => {
        if (e.target.closest('[role="menuitem"], a, button')) {
          setTimeout(() => setOpen(false), 0);
        }
      });
    });
  }

  Drupal.behaviors.xmtShortReadMenu = {
    attach: function (context) {
      const roots = context.querySelectorAll
        ? context.querySelectorAll('.xmt-short')
        : [];
      Array.prototype.forEach.call(roots, bindTopMenu);
      document.querySelectorAll('.xmt-short').forEach(bindTopMenu);
    },
  };
})(Drupal);
