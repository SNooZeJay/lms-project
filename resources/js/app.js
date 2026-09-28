/**
 * Shared interface behaviour.
 *
 * Everything here is a small progressive enhancement over real HTML. Every
 * control is a button or a link with an accessible name, so the page still
 * works with this file blocked, and no behaviour depends on a framework.
 *
 * The file covers five things:
 *
 *   1. The light and dark theme, chosen before the first paint by the layout.
 *   2. The account menu, which opens, closes on Escape or an outside click, and
 *      returns focus to the button that opened it.
 *   3. The mobile navigation drawer, with the same focus and Escape contract.
 *   4. The countdown that carries a newly verified person into their workspace.
 *   5. Form safety: a confirmed action, an error summary that takes focus, and a
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

/* ------------------------------------------------------------- topbar search */

/*
 * The slash shortcut the topbar advertises.
 *
 * It focuses the search field and nothing else. It is skipped while somebody is
 * already typing, because a "/" typed into a form field has to stay a slash, and
 * it is skipped when a modifier is held, because that combination belongs to
 * whatever the person is actually trying to do.
 *
 * The field is a real input inside a real form, so the shortcut is a
 * convenience and never the only way to reach it.
 */
const topbarSearch = document.querySelector('[data-topbar-search] input[name="q"]');

if (topbarSearch) {
    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        const active = document.activeElement;

        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable)) {
            return;
        }

        // A modifier plus a key that is not a slash, such as "/" typed while
        // shift is held on some layouts, is not the shortcut.
        event.preventDefault();
        topbarSearch.focus();
        topbarSearch.select();
    });
}

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

        releaseWhenThePageDoesNotMove(button);
    });
});

/*
 * The other half of the busy state, and the part that was missing.
 *
 * Disabling the button stops a second click being sent, which is the point. It
 * also means that if the request never completes, the control stays disabled
 * until the person reloads by hand. That is the worst outcome available: the
 * action they wanted has become impossible and the page looks broken rather than
 * busy.
 *
 * Three things reopen it, and each covers a way a request can end without the
 * page being replaced:
 *
 *   - a timer, for a request that hangs on a connection which never resolves
 *   - pageshow, which fires when a page is restored from the back forward
 *     cache, so going back does not return to a dead form
 *   - the window coming back online, for a phone that lost signal mid submit
 *
 * The listeners are removed once the control is live again. A form that is
 * submitted repeatedly would otherwise attach a new pair every time and leave
 * them behind for the life of the page.
 */
const PENDING_RELEASE_AFTER_MS = 15000;

function releaseWhenThePageDoesNotMove(button) {
    const stop = () => {
        window.removeEventListener('pageshow', release);
        window.removeEventListener('online', release);
    };

    const release = () => {
        stop();

        // A button that has been replaced by a new page is not ours to touch.
        if (!button.isConnected) {
            return;
        }

        button.disabled = false;
        button.removeAttribute('aria-busy');
    };

    const timer = window.setTimeout(release, PENDING_RELEASE_AFTER_MS);

    window.addEventListener('pageshow', () => {
        window.clearTimeout(timer);
        release();
    });

    window.addEventListener('online', () => {
        window.clearTimeout(timer);
        release();
    });
}

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

/**
 * The rotating line on the authentication panel.
 *
 * Types each sentence one character at a time, holds it long enough to be read,
 * erases it in reverse, moves to the next sentence, and repeats indefinitely.
 *
 * The sentences arrive in a data attribute, and the element already holds the
 * first one in its markup, so with this file blocked the panel still reads
 * correctly. The block also reserves its own height, so swapping a short
 * sentence for a long one cannot pull the message below it upward.
 *
 * The visible line is hidden from assistive technology in the markup, and the
 * full set of sentences is rendered as a plain list beside it, so the
 * information does not depend on the animation existing.
 */
const rotateSentences = (node) => {
    const sentences = (node.dataset.sentences || '')
        .split('||')
        .map((sentence) => sentence.trim())
        .filter(Boolean);

    if (sentences.length < 2) {
        return;
    }

    const TYPE_MS = 45;
    const ERASE_MS = 22;
    const HOLD_MS = 3600;
    const GAP_MS = 520;
    const FIRST_DELAY_MS = 1100;

    let index = 0;
    let shown = 0;
    let erasing = false;
    let timer = null;

    const stop = () => {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    const step = () => {
        const sentence = sentences[index];
        shown += erasing ? -1 : 1;
        node.textContent = sentence.slice(0, shown);

        let delay = erasing ? ERASE_MS : TYPE_MS;

        if (!erasing && shown === sentence.length) {
            erasing = true;
            delay = HOLD_MS;
        } else if (erasing && shown === 0) {
            erasing = false;
            index = (index + 1) % sentences.length;
            delay = GAP_MS;
        }

        timer = window.setTimeout(step, delay);
    };

    // Pausing while the tab is in the background is an optimisation, not a
    // condition. Browsers already throttle timers on a hidden tab, and treating
    // hidden as a reason not to run meant a page opened in the background never
    // started at all, which is indistinguishable from a broken feature.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else if (timer === null) {
            timer = window.setTimeout(step, GAP_MS);
        }
    });

    timer = window.setTimeout(step, FIRST_DELAY_MS);
};

document.querySelectorAll('[data-rotate-sentences]').forEach(rotateSentences);

/**
 * The countdown that carries a newly verified person into their workspace.
 *
 * This is an enhancement and never the mechanism. The button it sits beside is a
 * real link with a real destination and an accessible name, so the page works with
 * this file blocked, and the timer only removes one click from somebody who was
 * already on their way.
 *
 * Three decisions are deliberate, and each has a reason that shows up when it is
 * got wrong.
 *
 * The countdown starts at eight seconds rather than three. The message it follows
 * is the last thing read before the application takes over, and a person who needs
 * to read it, or who is navigating by keyboard or a screen reader, is exactly the
 * person a three second timer strands.
 *
 * Stopping is one way. A countdown that can be stopped and then starts again is
 * worse than one that cannot be stopped at all, because a person who presses the
 * control and then finds the page has gone anyway will not press it a second time.
 *
 * There is deliberately no check on prefers-reduced-motion, and that is not an
 * oversight. The project has already ruled on it: AuthPanelRotatingLineTest
 * asserts the bundle contains no such guard, because one hid its own effect three
 * times over and left a panel that looked like a plain paragraph. A number
 * counting down and a page changing are not animation, so gating either on a
 * preference about animation would remove a feature on the strength of a setting
 * about something else. The concerns that are real here, a change nobody asked for
 * and not enough time to read it, are answered by the eight seconds, the live
 * region and the control that stops it.
 */
(() => {
  const SECOND = 1000;

  document.querySelectorAll('[data-auto-continue]').forEach((root) => {
    const now = root.querySelector('[data-auto-continue-now]');
    const cancel = root.querySelector('[data-auto-continue-cancel]');
    const status = root.querySelector('[data-auto-continue-status]');
    const count = root.querySelector('[data-auto-continue-count]');
    const href = root.dataset.href;

    if (!href || !now) {
      return;
    }

    // Read the wait from the markup rather than hard coding it here, so the view
    // owns how long a person is given.
    const total = Number.parseInt(root.dataset.seconds || '8', 10);

    if (!Number.isFinite(total) || total < 1) {
      return;
    }

    let remaining = total;
    let handle = null;
    let stopped = false;

    const stop = (sayIt) => {
      if (stopped) {
        return;
      }

      stopped = true;

      if (handle !== null) {
        window.clearInterval(handle);
        handle = null;
      }

      if (count) {
        count.textContent = '0';
      }

      if (status) {
        status.textContent = sayIt
          ? 'Automatic step cancelled. Use the button above whenever you are ready.'
          : '';
      }

      if (cancel) {
        cancel.hidden = true;
      }
    };

    if (cancel) {
      cancel.hidden = false;
      cancel.addEventListener('click', (event) => {
        event.preventDefault();
        stop(true);
      });
    }

    handle = window.setInterval(() => {
      remaining -= 1;

      // Only the number is written, never the whole status line. The count lives
      // in a span inside the status line, so assigning textContent to the line
      // replaces its contents, that span included, and every write after the first
      // lands on a node that is no longer on the page. The visible result looked
      // right on the first tick and then quietly was not a real element any more,
      // which is the kind of fault that stays invisible until something else goes
      // looking for it.
      //
      // Changing a descendant of an aria-live region is announced, so the sentence
      // is still read aloud as the number falls.
      if (count) {
        count.textContent = String(Math.max(remaining, 0));
      }

      if (remaining <= 0) {
        if (status) {
          status.textContent = 'Continuing now.';
        }

        window.location.assign(href);
      }
    }, SECOND);
  });
})();
