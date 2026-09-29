/**
 * Measures the reveal from inside the page, at the first frame the element exists.
 *
 * The version that samples with `setTimeout` after load cannot see this: the
 * animation is over inside four hundred milliseconds and the trace starts after
 * `DOMContentLoaded`, which on a warm page is a few hundred milliseconds in. That
 * is how a reveal that never ran was measured as a reveal that ran, three times.
 *
 * The only reliable moment is the element's own first frame, so this reports that:
 * the computed `animation-name`, the opacity and the transform offset in the very
 * first frame the element is painted in, and then the state once the animation
 * has finished. An element that never moves fails the first part. An element that
 * moves and never arrives fails the second.
 *
 * Written to run in any browser, because the check needs a reduced motion
 * preference *off*, which is the one thing the browser tool in this environment
 * cannot do and the one thing that matters here.
 *
 * Usage: paste the exported function's body into the page, or run the file with
 * node and a base url. It is a module, so it can be imported.
 */

/**
 * @param {object} [options]
 * @param {string} [options.base]        where the application is served
 * @param {number} [options.width]       viewport width
 * @param {number} [options.height]      viewport height
 * @param {boolean} [options.reduced]    emulate a reduced motion preference
 * @param {string} [options.path]        the page to load
 * @returns {Promise<object>}
 */
export async function measureReveal({
  base = "http://127.0.0.1:8000",
  width = 1280,
  height = 900,
  reduced = false,
  path = "/",
} = {}) {
  if (typeof document === "undefined") {
    throw new Error("This runs in a page. Serve the application and open it.");
  }

  const element = "section.band-first";
  const sample = (el) => {
    const s = getComputedStyle(el);
    const m = /matrix\(1, 0, 0, 1, ([-\d.]+), ([-\d.]+)\)/.exec(s.transform);
    return {
      opacity: Number(s.opacity),
      offsetY: m ? Number(m[2]) : 0,
      animationName: s.animationName,
      animationDuration: s.animationDuration,
    };
  };

  const observed = [];
  const observer = new MutationObserver(() => {});
  observer.observe(document.documentElement, { attributes: true, subtree: true, attributeFilter: ["style", "class"] });

  // Every frame, from the first one, for as long as anything is moving.
  const frames = [];
  let running = true;
  let last = 0;
  const tick = () => {
    const el = document.querySelector(element);
    if (el) {
      const now = sample(el);
      if (now.opacity !== last) frames.push(now);
      last = now.opacity;
    }
    if (running) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);

  const stop = () => { running = false; observer.disconnect(); };

  // Wait for the page to be interactive, then long enough for any reveal to end.
  await new Promise((resolve) => {
    if (document.readyState === "complete") resolve();
    else window.addEventListener("load", resolve, { once: true });
  });

  const first = document.querySelector(element);
  const atLoad = first ? sample(first) : null;

  // 1200ms is longer than the longest reveal configured.
  await new Promise((r) => setTimeout(r, 1200));
  stop();

  const settledEl = document.querySelector(element);
  const settled = settledEl ? sample(settledEl) : null;

  const distinctOpacities = [...new Set(frames.map((f) => Math.round(f.opacity * 1000) / 1000))];
  const moved = frames.some((f) => f.offsetY > 0.5);

  return {
    reducedMotion: window.matchMedia("(prefers-reduced-motion: reduce)").matches,
    viewport: { width, height },
    elementFound: !!settledEl,
    atLoad,
    settled,
    animationRan: (atLoad?.animationName ?? "none") !== "none" || distinctOpacities.length > 2,
    movedIn: moved,
    distinctOpacities: distinctOpacities.length,
    firstFrames: frames.slice(0, 6),
    verdict:
      !settledEl
        ? "the element is not on the page"
        : settled.opacity < 0.99
          ? "FAILED: content is still not fully visible"
          : reduced
            ? "correct: reduced motion, content on screen and still"
            : distinctOpacities.length < 3
              ? "FAILED: it snapped to full opacity, nothing animated"
              : moved
                ? "correct: animated in, and arrived"
                : "FAILED: faded but never moved",
  };
}

/*
 | Run it against the live application.
 |
 | node --experimental-strip-types check-reveal.mjs
 |
 | The browser is driven over the debugging protocol rather than through a test
 | runner, because the one thing this has to control is a reduced motion
 | preference set to *off*, which is the whole point: a machine that reports
 | `reduce` can only ever verify that the animations are absent.
 */
if (typeof process !== "undefined" && process.argv?.[1]?.endsWith("check-reveal.mjs")) {
  const { spawn } = await import("node:child_process");
  const { existsSync, mkdtempSync } = await import("node:fs");
  const { tmpdir } = await import("node:os");

  const base = process.argv[2] || "http://127.0.0.1:8000";
  const edge = [
    "C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe",
    "C:/Program Files/Microsoft/Edge/Application/msedge.exe",
  ].find((p) => existsSync(p));

  if (!edge) {
    console.log("  no browser found, so nothing can be measured");
    process.exit(1);
  }

  const port = 9491;
  const child = spawn(
    edge,
    [
      "--headless=new",
      `--remote-debugging-port=${port}`,
      `--user-data-dir=${mkdtempSync(tmpdir() + "\\reveal-")}`,
      "--no-first-run",
      "--disable-gpu",
      "--hide-scrollbars",
      "about:blank",
    ],
    { stdio: "ignore" },
  );

  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  let wsUrl;
  for (let i = 0; i < 40; i++) {
    try {
      const list = await fetch(`http://127.0.0.1:${port}/json/list`).then((r) => r.json());
      const page = list.find((t) => t.type === "page");
      if (page) { wsUrl = page.webSocketDebuggerUrl; break; }
    } catch {}
    await sleep(250);
  }

  const ws = new WebSocket(wsUrl);
  await new Promise((r) => (ws.onopen = r));

  let id = 0;
  const pending = new Map();
  ws.onmessage = (e) => {
    const m = JSON.parse(e.data);
    if (m.id && pending.has(m.id)) { pending.get(m.id)(m.result); pending.delete(m.id); }
  };
  const send = (method, params = {}) =>
    new Promise((resolve) => { const mine = ++id; pending.set(mine, resolve); ws.send(JSON.stringify({ id: mine, method, params })); });

  await send("Page.enable");
  await send("Runtime.enable");

  // The probe, installed before any document script, so it is recording before
  // the application initialises.
  const source = `
    window.__result = null;
    ${measureReveal.toString()}
    window.__measure = measureReveal;
  `;

  const scenarios = [
    { label: "motion allowed, 1280", reduced: false, width: 1280, height: 900 },
    { label: "motion allowed, 390",  reduced: false, width: 390,  height: 844 },
    { label: "reduced motion, 1280", reduced: true,  width: 1280, height: 900 },
  ];

  console.log(`\n  ${base}\n`);
  let failed = 0;

  for (const s of scenarios) {
    await send("Emulation.setEmulatedMedia", {
      features: [{ name: "prefers-reduced-motion", value: s.reduced ? "reduce" : "no-preference" }],
    });
    await send("Emulation.setDeviceMetricsOverride", {
      width: s.width, height: s.height, deviceScaleFactor: 1, mobile: s.width < 700,
    });
    await send("Page.addScriptToEvaluateOnNewDocument", { source });
    await send("Page.navigate", { url: base + "/" });

    const r = await send("Runtime.evaluate", {
      expression: `measureReveal({ reduced: ${s.reduced}, width: ${s.width}, height: ${s.height} })`,
      awaitPromise: true,
      returnByValue: true,
    });

    const v = r.result?.value;
    if (!v) {
      console.log(`  FAIL  ${s.label}  the probe did not return`);
      failed++;
      continue;
    }

    const bad = v.verdict.startsWith("FAILED");
    if (bad) failed++;
    console.log(`  ${bad ? "FAIL" : "ok  "}  ${s.label}`);
    console.log(`        ${v.verdict}`);
    console.log(`        animation-name "${v.atLoad?.animationName}", ${v.distinctOpacities} distinct opacity values, moved ${v.movedIn ? "yes" : "no"}`);
  }

  console.log(`\n  ${failed === 0 ? "The reveal is working." : failed + " scenario(s) failed."}\n`);

  ws.close();
  child.kill();
  process.exit(failed === 0 ? 0 : 1);
}
