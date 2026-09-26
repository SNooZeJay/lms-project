/**
 * Shared interface behaviour.
 *
 * Everything here is a small progressive enhancement over real HTML. Every
 * control is a button or a link with an accessible name, so the page still
 * works with this file blocked, and no behaviour depends on a framework.
 *
 * The file covers four things:
 *
 *   1. The light and dark theme, chosen before the first paint by the layout.
 *   2. The account menu, which opens, closes on Escape or an outside click, and
 *      returns focus to the button that opened it.
 *   3. The mobile navigation drawer, with the same focus and Escape contract.
 *   4. Form safety: a confirmed action, an error summary that takes focus, and a
 *      pending state so a slow submission is not a silent one.
 */

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

/**
 * Returns focus to the control that opened a layer, so closing it with the
 * keyboard does not drop the user back at the top of the page.
 */
const returnFocusTo = (trigger) => {
    if (trigger && typeof trigger.focus === 'function') {
        trigger.focus();
    }
};

const focusablesIn = (container) => Array.from(
    container.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
).filter((element) => element.offsetParent !== null || element === document.activeElement);

/* ------------------------------------------------------------------ account menu */

document.querySelectorAll('[data-dropdown]').forEach((root) => {
    const trigger = root.querySelector('[data-dropdown-trigger]');
    const panel = root.querySelector('[data-dropdown-panel]');

    if (!trigger || !panel) {
        return;
    }

    const close = ({ restoreFocus = false } = {}) => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');

        if (restoreFocus) {
            returnFocusTo(trigger);
        }
    };

    const open = () => {
        // Only one menu is open at a time, so two headers cannot both look open.
        document.querySelectorAll('[data-dropdown-panel]').forEach((other) => {
            other.hidden = true;
        });
        document.querySelectorAll('[data-dropdown-trigger]').forEach((other) => {
            other.setAttribute('aria-expanded', 'false');
        });

        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');

        const first = panel.querySelector('[data-dropdown-item]');

        if (first) {
            first.focus();
        }
    };

    trigger.addEventListener('click', () => {
        if (panel.hidden) {
            open();
        } else {
            close({ restoreFocus: true });
        }
    });

    // Keep Tab inside the menu while it is open, and wrap from the last item
    // back to the trigger.
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close({ restoreFocus: true });
            return;
        }

        if (event.key !== 'Tab' || panel.hidden) {
            return;
        }

        const items = focusablesIn(panel);
        const first = items[0];
        const last = items[items.length - 1];

        if (!first) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            trigger.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            trigger.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!panel.hidden && !root.contains(event.target)) {
            close();
        }
    });
});

/* ----------------------------------------------------------------- mobile drawer */

const drawer = document.querySelector('[data-drawer]');

if (drawer) {
    const panel = drawer.querySelector('[data-drawer-panel]');
    const backdrop = drawer.querySelector('[data-drawer-backdrop]');
    const openers = Array.from(document.querySelectorAll('[data-drawer-open]'));
    const opener = openers[0] ?? null;
    const lastFocusedInside = { element: null };

    const setBackgroundInert = (isOpen) => {
        // The header and the page column are marked so a keyboard user cannot
        // tab out of an open drawer into content hidden behind it.
        document.querySelectorAll('[data-workspace-sidebar]').forEach((element) => {
            element.toggleAttribute('inert', isOpen);
        });

        const column = document.querySelector('[data-workspace-column]');

        if (column) {
            column.toggleAttribute('inert', isOpen);
        }
    };

    const setOpen = (isOpen) => {
        if (!panel) {
            return;
        }

        panel.hidden = !isOpen;

        if (backdrop) {
            backdrop.hidden = !isOpen;
        }

        openers.forEach((button) => {
            button.setAttribute('aria-expanded', String(isOpen));
        });

        // The page behind an open drawer must not scroll away on touch.
        document.body.classList.toggle('overflow-hidden', isOpen);
        setBackgroundInert(isOpen);

        if (isOpen) {
            lastFocusedInside.element = document.activeElement;

            const first = panel.querySelector('[data-drawer-close]') ?? focusablesIn(panel)[0];

            if (first) {
                first.focus();
            }
        } else {
            const target = lastFocusedInside.element ?? opener;

            returnFocusTo(target && target.isConnected ? target : opener);
        }
    };

    openers.forEach((button) => {
        button.addEventListener('click', () => setOpen(panel?.hidden ?? true));
    });

    drawer.querySelectorAll('[data-drawer-close]').forEach((button) => {
        button.addEventListener('click', () => setOpen(false));
    });

    if (backdrop) {
        backdrop.addEventListener('click', () => setOpen(false));
    }

    // Following a link inside the drawer should close it, or the next page would
    // render behind an open panel.
    panel?.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && panel && !panel.hidden) {
            event.preventDefault();
            setOpen(false);
        }
    });

    if (panel && !panel.hidden) {
        setOpen(false);
    }
}

/* ------------------------------------------------------------------ form safety */

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const errorSummary = document.querySelector('[data-error-summary]');

if (errorSummary) {
    errorSummary.focus();
}

document.querySelectorAll('form[data-pending]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-pending-button]') ?? form.querySelector('button[type="submit"]');

        if (!button || button.disabled) {
            return;
        }

        // The label keeps its words and gains a busy marker, so a slow save is
        // visible without the control changing size or losing its meaning.
        const original = button.getAttribute('data-pending-label') ?? button.textContent.trim();

        button.setAttribute('data-pending-label', original);
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;

        const busy = button.querySelector('[data-pending-text]');

        if (busy) {
            busy.textContent = original;
        }
    });
});

/* ------------------------------------------------- responsive table disclosure */

document.querySelectorAll('[data-table-toggle]').forEach((toggle) => {
    const targetId = toggle.getAttribute('aria-controls');
    const panel = targetId ? document.getElementById(targetId) : null;

    if (!panel) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isHidden = panel.hasAttribute('hidden');

        if (isHidden) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', '');
        }

        toggle.setAttribute('aria-expanded', String(isHidden));
    });
});

/* ------------------------------------------------------------------- printing */

document.querySelectorAll('[data-print-button]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

/* -------------------------------------------------------------- password reveal */

document.querySelectorAll('[data-password-reveal]').forEach((button) => {
    const field = document.getElementById(button.dataset.passwordReveal);

    if (! field) {
        return;
    }

    const showIcon = button.querySelector('[data-password-icon="show"]');
    const hideIcon = button.querySelector('[data-password-icon="hide"]');
    const status = button.querySelector('[data-password-status]');

    button.addEventListener('click', () => {
        const wasRevealed = field.type === 'text';

        field.type = wasRevealed ? 'password' : 'text';

        // The accessible name changes with the state, because showing and hiding
        // are different actions. A button that keeps saying "show password"
        // after the password is visible leaves a screen reader user with no way
        // to tell what the control will do next.
        button.setAttribute('aria-label', wasRevealed ? 'Show password' : 'Hide password');
        button.setAttribute('aria-pressed', String(! wasRevealed));

        if (showIcon) {
            showIcon.classList.toggle('hidden', ! wasRevealed);
        }

        if (hideIcon) {
            hideIcon.classList.toggle('hidden', wasRevealed);
        }

        if (status) {
            status.textContent = wasRevealed ? 'Password is hidden' : 'Password is shown';
        }

        // Return the caret to the field, so typing continues where it was left
        // instead of jumping to the start of the value.
        field.focus();

        const end = field.value.length;

        if (typeof field.setSelectionRange === 'function' && end > 0) {
            field.setSelectionRange(end, end);
        }
    });
});
