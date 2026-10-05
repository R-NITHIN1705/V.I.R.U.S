const MODE_KEY = 'virus-mode';

function applyMode(mode: 'dark' | 'light'): void {
    const light = mode === 'light';
    document.documentElement.dataset.mode = mode;
    document.documentElement.setAttribute('data-theme', light ? 'winter' : 'night');
    document.querySelectorAll<HTMLElement>('[data-mode-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-checked', String(light));
        toggle.setAttribute('aria-label', `Switch to ${light ? 'dark' : 'light'} mode`);
        const label = toggle.querySelector<HTMLElement>('[data-mode-label]');
        const icon = toggle.querySelector<HTMLElement>('[data-mode-icon]');
        if (label) label.textContent = mode === 'light' ? 'Light' : 'Dark';
        if (icon) icon.textContent = light ? '☼' : '☾';
    });
}

let savedMode: 'dark' | 'light' = 'dark';
try {
    savedMode = localStorage.getItem(MODE_KEY) === 'light' ? 'light' : 'dark';
} catch {
    // Keep the page usable when storage is unavailable.
}
applyMode(savedMode);

document.querySelectorAll<HTMLElement>('[data-mode-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const next: 'dark' | 'light' = document.documentElement.dataset.mode === 'light' ? 'dark' : 'light';
        try {
            localStorage.setItem(MODE_KEY, next);
        } catch {
            // Apply the selection for the current page even if it cannot be stored.
        }
        applyMode(next);
    });
});
