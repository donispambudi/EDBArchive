/**
 * app.js — Admin Starter Layout
 * Plain JavaScript — No jQuery / No Alpine / No Bootstrap
 */

(function () {
    'use strict';

    /* ============================================================
       HELPERS
       ============================================================ */
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
    const isMobile = () => window.innerWidth <= 768;

    /* ============================================================
       ELEMENTS
       ============================================================ */
    const wrapper        = $('#admin-wrapper');
    const hamburgerBtn   = $('#hamburger-btn');
    const sidebarOverlay = $('#sidebar-overlay');

    /* ============================================================
       SIDEBAR TOGGLE (Desktop collapse ↔ Mobile off-canvas)
       ============================================================ */

    function getSidebarState() {
        return localStorage.getItem('sidebarCollapsed') === 'true';
    }

    function saveSidebarState(collapsed) {
        localStorage.setItem('sidebarCollapsed', collapsed);
    }

    /**
     * Apply the correct visual state based on viewport.
     * Desktop: collapsed adds .sidebar-collapsed (mini 64px icon mode).
     * Mobile:  .sidebar-collapsed is REMOVED — sidebar is always full-width
     *          and toggled via .mobile-open.
     */
    function applySidebarState() {
        if (isMobile()) {
            // On mobile, collapsed state is meaningless — always full-width overlay
            wrapper.classList.remove('sidebar-collapsed');
            // Keep hamburger UX (X icon only when panel is open)
            const isOpen = wrapper.classList.contains('mobile-open');
            if (hamburgerBtn) hamburgerBtn.classList.toggle('active', isOpen);
        } else {
            // On desktop, apply persisted collapsed state
            wrapper.classList.remove('mobile-open');
            const collapsed = getSidebarState();
            wrapper.classList.toggle('sidebar-collapsed', collapsed);
            if (hamburgerBtn) hamburgerBtn.classList.toggle('active', collapsed);
        }
    }

    function toggleSidebar() {
        if (isMobile()) {
            const isOpen = wrapper.classList.toggle('mobile-open');
            if (hamburgerBtn) hamburgerBtn.classList.toggle('active', isOpen);
        } else {
            const collapsed = wrapper.classList.toggle('sidebar-collapsed');
            if (hamburgerBtn) hamburgerBtn.classList.toggle('active', collapsed);
            saveSidebarState(collapsed);
        }
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', toggleSidebar);
    }

    // Close mobile sidebar when clicking the overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            wrapper.classList.remove('mobile-open');
            if (hamburgerBtn) hamburgerBtn.classList.remove('active');
        });
    }

    // Re-apply on resize (debounced) — handles orientation change / window resize
    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applySidebarState, 120);
    });

    applySidebarState();

    /* ============================================================
       SUBMENU ACCORDION
       ============================================================ */

    /**
     * Toggle a submenu open/closed.
     * @param {HTMLButtonElement} btn   - The parent nav button
     * @param {HTMLElement}       panel - The .submenu div
     * @param {boolean}           [forceOpen] - Optional: force a specific state
     */
    function toggleSubmenu(btn, panel, forceOpen) {
        const willOpen = (forceOpen !== undefined) ? forceOpen : (btn.getAttribute('aria-expanded') !== 'true');

        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        panel.classList.toggle('submenu-open', willOpen);
        btn.classList.toggle('parent-active', willOpen);
    }

    // Wire up all submenu parent buttons
    $$('[data-submenu]').forEach(function (btn) {
        const targetId = btn.dataset.submenu;
        const panel    = document.getElementById(targetId);
        if (!panel) return;

        btn.addEventListener('click', function () {
            toggleSubmenu(btn, panel);
        });
    });

    // On page load: auto-expand the submenu whose child is currently active
    $$('.submenu').forEach(function (panel) {
        const activeChild = panel.querySelector('.submenu-item.active');
        if (activeChild) {
            const btn = $('[data-submenu="' + panel.id + '"]');
            if (btn) toggleSubmenu(btn, panel, true);
        }
    });

    /* ============================================================
       AVATAR DROPDOWN
       ============================================================ */
    const avatarBtn    = $('#avatar-btn');
    const dropdownMenu = $('#dropdown-menu');

    function openDropdown() {
        if (!dropdownMenu) return;
        dropdownMenu.classList.add('open');
        if (avatarBtn) {
            avatarBtn.classList.add('open');
            avatarBtn.setAttribute('aria-expanded', 'true');
        }
    }

    function closeDropdown() {
        if (!dropdownMenu) return;
        dropdownMenu.classList.remove('open');
        if (avatarBtn) {
            avatarBtn.classList.remove('open');
            avatarBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleDropdown(e) {
        e.stopPropagation();
        dropdownMenu && dropdownMenu.classList.contains('open') ? closeDropdown() : openDropdown();
    }

    if (avatarBtn) {
        avatarBtn.addEventListener('click', toggleDropdown);
    }

    document.addEventListener('click', function (e) {
        if (!dropdownMenu) return;
        if (!dropdownMenu.classList.contains('open')) return;
        if (!dropdownMenu.contains(e.target) && e.target !== avatarBtn) {
            closeDropdown();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDropdown();
            // Also close mobile sidebar
            if (isMobile() && wrapper.classList.contains('mobile-open')) {
                wrapper.classList.remove('mobile-open');
                if (hamburgerBtn) hamburgerBtn.classList.remove('active');
            }
        }
    });

    /* ============================================================
       DATETIME CLOCK — Server seed → JS tick every second
       ============================================================ */
    const timeEl = $('#js-time');
    const dateEl = $('#js-date');

    const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const DAY_NAMES   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    function pad2(n) { return String(n).padStart(2, '0'); }
    function formatTime(d) { return pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds()); }
    function formatDate(d) {
        return DAY_NAMES[d.getDay()] + ', ' + pad2(d.getDate()) + ' ' + MONTH_NAMES[d.getMonth()] + ' ' + d.getFullYear();
    }

    if (timeEl || dateEl) {
        // data-server-ts is Carbon->valueOf() (milliseconds)
        const serverTs = parseInt(timeEl ? timeEl.dataset.serverTs : 0, 10) || Date.now();
        const drift    = serverTs - Date.now();  // ms difference between server and client

        function tick() {
            const now = new Date(Date.now() + drift);
            if (timeEl) timeEl.textContent = formatTime(now);
            if (dateEl) dateEl.textContent = formatDate(now);
        }

        tick();
        setInterval(tick, 1000);
    }

    /* ============================================================
       AUTO-DISMISS ALERTS
       ============================================================ */
    $$('[data-auto-dismiss]').forEach(function (el) {
        const delay = parseInt(el.dataset.autoDismiss, 10) || 5000;
        setTimeout(function () {
            el.style.transition = 'opacity .4s, margin .4s';
            el.style.opacity    = '0';
            el.style.marginBottom = '0';
            setTimeout(function () { el.remove(); }, 420);
        }, delay);
    });

})();
