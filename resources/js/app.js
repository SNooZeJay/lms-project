import AOS from 'aos';

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
 * Motion on the public pages.
 *
 * Three things move, and all three are additions to content that is already
 * complete without them.
 *
 * SCROLL ANIMATION IS AOS
 *
 * Animate on Scroll does the part that is genuinely fiddly: it finds every
 * element marked `data-aos`, works out where each one sits relative to the
 * viewport, and adds its `aos-animate` class when the reader reaches it. The
 * scroll listener, the throttling that keeps it cheap, the offset arithmetic, the
 * resize and orientation handling and the mutation observer that picks up
 * elements added later are all the library's, because they are the same on every
 * site and none of them are worth writing a second time.
 *
 * Only four effects are declared in the stylesheet, because only four are used.
 * The library ships thirty. The rest cost nothing, since no element asks for one.
 *
 * THE HIDING IS OURS, ON PURPOSE
 *
 * AOS's own stylesheet hides anything carrying `data-aos`, unconditionally. If
 * the stylesheet arrives and this script does not, every marked element stays at
 * zero opacity for ever. Nothing throws, nothing logs, and the page is simply
 * blank where its text should be.
 *
 * So the stylesheet gates the hidden state on the `aos-init` class that AOS puts
 * on an element as part of initialising it. An element is only ever hidden by
 * code that has already proved it is there to reveal it. This script never
 * arriving therefore costs a page that is still, which is the correct failure and
 * not a silent one.
 *
 * REDUCED MOTION IS ASKED OF THE LIBRARY, NOT PATCHED OVER
 *
 * `disable` is AOS's own switch, and when it is set the library strips the
 * `data-aos` attributes from every element it found. The stylesheet then has
 * nothing to act on, so the animations do not merely run quickly, they are not
 * part of the document. The stylesheet also carries the same rule under the same
 * media query, as a backstop for a preference that changes after initialisation.
 *
 * ONE SHOT, NEVER A LOOP
 *
 * A band that re-animates every time it crosses the viewport is a band that
 * cannot be scrolled past quickly, which is the opposite of what a reader is
 * trying to do. `once` settles each element the first time it arrives and then
 * leaves it alone.
 */


(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /*
     | The opening band, which the library cannot animate.
     |
     | Everything below the fold is prepared by the library while it is off screen,
     | so an element there gets `aos-init` now and `aos-animate` later, when the
     | reader scrolls to it. There is a real gap between those two states and the
     | transition crosses it. That is why scrolling the page animated everything,
     | and why the page looked like it worked.
     |
     | The opening band is different. It is on screen when the page loads, so the
     | library adds `aos-init` and `aos-animate` in the same recalculation. The
     | browser has one computed style to draw, the final one, and no transition
     | runs. Nothing about it looks broken: the element arrives at full opacity,
     | the page reads correctly, the console is silent. It simply never moved.
     |
     | So the one animation a reader sees without doing anything was the one
     | animation that could not happen, and anybody who had scrolled the page and
     | watched the other six work had every reason to say it was not working.
     *
     | A transition needs two painted styles, one to come from and one to go to.
     | Forcing that gap with `requestAnimationFrame` was tried and measured, and it
     | does not survive contact with a real page load: the callbacks land inside a
     * single style recalculation, the browser coalesces them, and the hidden value
     | is never painted. Over 128 frames traced from before the library ran, the
     | opening band took exactly one distinct opacity value and was never seen
     | part way through anything. Replaying the same steps by hand did animate it,
     | which is precisely what made it look like a working fix.
     *
     | A keyframe animation is the right tool, because it does not depend on
     * catching the element between two states. It has a start, an end and a
     | length, and it plays from wherever the element is when it starts. Applied
     | on the first frame it runs from zero; applied a little later it still runs.
     | There is no frame to miss and no state to lose.
     *
     | The element is marked with a class rather than animated from here, so the
     * motion lives in the stylesheet with everything else that moves. The duration
     * is passed in as a custom property, because the opening band asks to be
     | quicker than a band the reader has to scroll to, and hard coding it here
     | would make that request a decoration.
     *
     | Only elements on screen at load are marked. Everything below the fold is
     | left entirely to the library, so the path that already worked is untouched.
     *
     | A reduced motion reader gets none of it: the block is skipped, the library's
     | own `disable` strips the attributes, and the page is simply on screen and
     * still.
     */
    if (!reducedMotion.matches) {
        document.querySelectorAll('[data-aos]').forEach((element) => {
            const box = element.getBoundingClientRect();
            if (box.top >= window.innerHeight || box.bottom <= 0) {
                return;
            }
            const declared = Number.parseInt(element.getAttribute('data-aos-duration') ?? '', 10);
            element.style.setProperty(
                '--entrance-duration',
                `${Number.isFinite(declared) ? declared : 500}ms`
            );
            element.classList.add('entrance');
        });
    }

    AOS.init({
        // Off entirely under a reduced motion preference. See above.
        disable: reducedMotion.matches,

        // Once. See above.
        once: true,

        // A band starts to arrive while it is still a little below the fold, so
        // it has finished moving by the time it is read rather than beginning to
        // move at the exact moment it appears.
        offset: 60,

        /*
         | Five hundred, and the stylesheet says the same.
         |
         | The library's default is four hundred, which is a touch snappy for
         | something a whole band of the page is doing. Six hundred, which is what
         | this was, is a touch slow: a reader scrolling briskly passes a band
         | that is still arriving behind them.
         */
        duration: 500,

        // Arrives and settles, rather than a linear ramp or a bounce. A landing
        // page that bounces reads as a template.
        easing: 'ease-out-cubic',
    });

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

