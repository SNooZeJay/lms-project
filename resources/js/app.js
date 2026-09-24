const storageKey = 'lms-theme';
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

const readStoredTheme = () => {
    try {
        const storedTheme = window.localStorage.getItem(storageKey);

        return storedTheme === 'light' || storedTheme === 'dark' ? storedTheme : null;
    } catch {
        return null;
    }
};

const storeTheme = (theme) => {
    try {
        window.localStorage.setItem(storageKey, theme);
    } catch {
        // The selected theme still applies for the current page.
    }
};

const updateThemeControls = (theme) => {
    const nextTheme = theme === 'dark' ? 'light' : 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(theme === 'dark'));
        button.setAttribute('aria-label', `Switch to ${nextTheme} theme`);
    });

    document.querySelectorAll('[data-theme-label]').forEach((label) => {
        label.textContent = nextTheme === 'dark' ? 'Dark' : 'Light';
    });
};

const applyTheme = (theme, persist = true) => {
    const safeTheme = theme === 'dark' ? 'dark' : 'light';

    document.documentElement.classList.toggle('dark', safeTheme === 'dark');
    document.documentElement.dataset.theme = safeTheme;
    updateThemeControls(safeTheme);

    if (persist) {
        storeTheme(safeTheme);
    }
};

const currentTheme = () => (
    document.documentElement.classList.contains('dark') ? 'dark' : 'light'
);

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });
});

applyTheme(currentTheme(), false);

systemTheme.addEventListener('change', (event) => {
    if (readStoredTheme() === null) {
        applyTheme(event.matches ? 'dark' : 'light', false);
    }
});
