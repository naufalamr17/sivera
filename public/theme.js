(() => {
    const root = document.documentElement;
    let theme = "cyberpunk";
    try {
        const stored = localStorage.getItem("sivera.theme");
        if (stored === "light" || stored === "cyberpunk") theme = stored;
    } catch {
        /* Storage can be disabled. */
    }
    root.dataset.theme = theme;

    document.addEventListener("click", (event) => {
        if (!event.target.closest("[data-theme-toggle]")) return;
        const next = root.dataset.theme === "cyberpunk" ? "light" : "cyberpunk";
        root.dataset.theme = next;
        try {
            localStorage.setItem("sivera.theme", next);
        } catch {
            /* The theme still works for this page without storage. */
        }
    });
})();
