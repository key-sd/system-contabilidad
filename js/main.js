document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openBtn = document.getElementById('open-sidebar-btn');
    const closeBtn = document.getElementById('close-sidebar-btn');

    function toggleSidebar(show) {
        if (!sidebar || !overlay) return;
        if (show) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', () => toggleSidebar(true));
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => toggleSidebar(false));
    }

    if (overlay) {
        overlay.addEventListener('click', () => toggleSidebar(false));
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay && !overlay.classList.contains('hidden')) {
            toggleSidebar(false);
        }
    });
});
