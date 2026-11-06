/**
 * Admin panel client app (v2)
 * - dark/light theme with localStorage persistence
 * - mobile drawer + desktop rail collapse with persistence
 * - sidebar nav filter (Ctrl/Cmd + K focuses it)
 * - topbar dropdown menus (click-outside + Esc close)
 * - dismissible flash messages
 */
(function () {
    'use strict';

    var RAIL_KEY = 'admin-sidebar-collapsed';

    /*theme is fixed dark — legacy light preference is ignored and cleared */
    try { localStorage.removeItem('admin-theme'); } catch (e) {}
    document.documentElement.setAttribute('data-theme', 'dark');

    /* run after DOM ready so the toggle exists */
    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        /* ---------------- sidebar drawer (mobile) ---------------- */
        var sidebar = document.getElementById('adminSidebar');
        var overlay = document.getElementById('sidebarOverlay');
        function closeDrawer() {
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
        function openDrawer() {
            if (sidebar) sidebar.classList.add('show');
            if (overlay) overlay.classList.add('show');
        }
        window.toggleSidebar = function () {
            if (sidebar && sidebar.classList.contains('show')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        };
        if (overlay) overlay.addEventListener('click', closeDrawer);
        /* close the drawer when tapping a nav link on mobile */
        sidebar.querySelectorAll('.sidebar-nav .nav-link').forEach(function (link) {
            link.addEventListener('click', function () { closeDrawer(); });
        });

        /* ---------------- desktop rail collapse ---------------- */
        var railBtn = document.getElementById('adminRailToggle');
        function applyRail(collapsed) {
            sidebar.classList.toggle('rail', collapsed);
            try { localStorage.setItem(RAIL_KEY, collapsed ? '1' : '0'); } catch (e) {}
            var icon = railBtn ? railBtn.querySelector('i') : null;
            if (icon) icon.className = collapsed
                ? 'ph-bold ph-arrow-u-up-left'
                : 'ph-bold ph-arrow-u-right';
        }
        if (railBtn) {
            var wasCollapsed = String.fromCharCode(0);
            try { wasCollapsed = localStorage.getItem(RAIL_KEY) === '1'; } catch (e) {}
            if (wasCollapsed) applyRail(true);
            railBtn.addEventListener('click', function () {
                applyRail(!sidebar.classList.contains('rail'));
            });
        }

        /* ---------------- nav filter ---------------- */
        var input = document.getElementById('adminNavFilter');
        var links = Array.prototype.slice.call(sidebar.querySelectorAll('.sidebar-nav .nav-link'));
        var sections = Array.prototype.slice.call(sidebar.querySelectorAll('.sidebar-nav .nav-section'));
        if (input) {
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                links.forEach(function (link) {
                    var match = q === '' || link.textContent.toLowerCase().indexOf(q) !== -1;
                    link.classList.toggle('hidden', !match);
                });
                sections.forEach(function (section) {
                    var node = section.nextElementSibling;
                    var anyVisible = false;
                    while (node && node.classList.contains('nav-link')) {
                        if (!node.classList.contains('hidden')) { anyVisible = true; break; }
                        node = node.nextElementSibling;
                    }
                    section.style.display = anyVisible ? '' : 'none';
                });
            });
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    if (sidebar.classList.contains('rail')) applyRail(false);
                    openDrawer();
                    input.focus();
                }
            });
        }

        /* ---------------- dropdowns ---------------- */
        function wireDrop(triggerId) {
            var trigger = document.getElementById(triggerId);
            if (!trigger) return;
            var wrap = trigger.closest('.topbar-dd');
            var menu = wrap ? wrap.querySelector('.admin-dropdown') : null;
            if (!menu) return;
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = menu.classList.contains('show');
                document.querySelectorAll('.admin-dropdown.show').forEach(function (m) {
                    m.classList.remove('show');
                });
                if (!open) menu.classList.add('show');
            });
        }
        wireDrop('adminNotifBtn');
        wireDrop('adminUserBtn');
        document.addEventListener('click', function () {
            document.querySelectorAll('.admin-dropdown.show').forEach(function (m) {
                m.classList.remove('show');
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.admin-dropdown.show').forEach(function (m) {
                    m.classList.remove('show');
                });
                closeDrawer();
            }
        });

        /* ---------------- flash dismiss ---------------- */
        document.querySelectorAll('.flash-msg [data-dismiss], .flash-msg .flash-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var msg = btn.closest('.flash-msg');
                if (msg) msg.remove();
            });
        });
        /* auto-dismiss flashes after 6.5s */
        setTimeout(function () {
            document.querySelectorAll('.flash-msg').forEach(function (msg) {
                msg.style.transition = 'opacity .4s ease';
                msg.style.opacity = '0';
                setTimeout(function () { if (msg.parentNode) msg.remove(); }, 400);
            });
        }, 6500);
    });
})();
