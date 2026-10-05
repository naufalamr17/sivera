document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('site-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');

    if (!toggle || !sidebar || !backdrop) return;

    const mobile = window.matchMedia('(max-width: 760px)');
    const update = () => {
        const expanded = mobile.matches
            ? document.documentElement.classList.contains('sidebar-mobile-open')
            : !document.documentElement.classList.contains('sidebar-collapsed');
        const label = mobile.matches
            ? (expanded ? 'Close navigation' : 'Open navigation')
            : (expanded ? 'Collapse sidebar' : 'Expand sidebar');

        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        sidebar.inert = mobile.matches && !expanded;
        sidebar.setAttribute('aria-hidden', String(mobile.matches && !expanded));
    };

    toggle.addEventListener('click', () => {
        let opened = false;
        if (mobile.matches) {
            opened = document.documentElement.classList.toggle('sidebar-mobile-open');
        } else {
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            try { localStorage.setItem('sivera.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (_) {}
        }
        update();
        if (opened) sidebar.querySelector('a')?.focus();
    });
    backdrop.addEventListener('click', () => {
        document.documentElement.classList.remove('sidebar-mobile-open');
        update();
        toggle.focus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches && document.documentElement.classList.contains('sidebar-mobile-open')) {
            document.documentElement.classList.remove('sidebar-mobile-open');
            update();
            toggle.focus();
        }
    });
    mobile.addEventListener('change', () => {
        document.documentElement.classList.remove('sidebar-mobile-open');
        update();
    });
    update();
});
