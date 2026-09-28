# QA session log — adversarial pass

Date: September 27, 2026
Target: `IT Learning Hub` (BSIT Academic LMS), Laravel 13, Blade, MySQL.

Method adapted from three references, used as thinking tools rather than as code
to install:

- Session-Based Test Management and the HICCUPS / FEW HICCUPS heuristics, with
  a written charter per session and a debrief per session.
- Risk-based scope: effort follows impact, not uniformity.
- Property-based generation with shrinking, illegal state transitions,
  idempotency of repeated actions, and boundary value analysis.

## Scope analysis

### In scope

Everything a signed-in person or a guest can reach, plus the two boundaries
outside the application (Git, the web server). The application is server-rendered
Blade with no client framework and no `fetch()` or AJAX, so the areas below do
not exist and are not tested rather than being reported as untested:

| Area | Status |
| --- | --- |
| Public HTTP API | None. Every endpoint is a browser route. Direct request testing is therefore form testing. |
| Client-side re-render / optimistic UI | None. There is no client state layer. |
| WebSocket or realtime | None. |
| File upload | One surface: Learning Material. |
| Payments | PayMongo checkout plus a verified webhook. Covered by existing tests; re-checked for replay and duplicate delivery. |

### Out of scope

Live provider calls with real money, production infrastructure, and the hosted
checkout page the provider renders, which the API cannot change.

## Risk ranking

| Rank | Area | Why it ranks here | Depth |
| --- | --- | --- | --- |
| 1 | Enrollment and lesson progress | Money has been taken; a wrong state here is visible to a paying student and is not self-correcting | Deep: illegal transitions, repeat, concurrency |
| 2 | Quiz grading and attempts | Server-side score, one attempt limit, retried submit | Deep: repeat submit, tamper, exhaustion |
| 3 | Certificate issuance and revocation | Legal-looking artefact, one active per enrollment, revocation | Deep: illegal transitions, duplicate issue |
| 4 | Authentication and session | Account takeover | Medium: expiry, stale page, concurrent tab |
| 5 | Role and account administration | Privilege escalation, suspending the last admin | Medium: stale page, repeat, tamper |
| 6 | Instructor curriculum authoring | Positions, ordering, ownership, slugs | Medium: repeat save, reorder, long content |
| 7 | Webhook handling | Unauthenticated surface | Medium: replay, duplicate, malformed |
| 8 | Profile and catalog search | Low impact, no state change | Boundary and hostile input only |
| 9 | Presentation | Decorative | Layout under long content only |

## Charters

| ID | Charter | Heuristics |
| --- | --- | --- |
| C1 | Explore every validated text field with hostile input classes to discover crashes, lost input, and validation that accepts nonsense | Boundary, Comparable, Product, Usability |
| C2 | Explore state-changing actions with repeat, concurrency, and illegal transitions to discover duplicates, lost updates, and impossible states | History, Product, Standards |
| C3 | Explore direct request construction with wrong ids, wrong roles, extra fields, and malformed payloads to discover authorization and validation gaps | Product, Standards, Security |
| C4 | Explore search and catalog with hostile query strings to discover injection, layout failure, and unbounded cost | Boundary, Usability, Performance |
| 5 | Explore the webhook with replay, duplicate, malformed, and unsigned payloads to discover state change without authority | History, Standards, Security |

Charter 5 is written with an Arabic digit to keep it distinct in this table; it
is the webhook session and belongs to the same rank as charter 2.

## Hostile input classes

Reused across every field so a class is defined once and applied everywhere.

| Class | Example | What it is meant to break |
| --- | --- | --- |
| Empty | `""`, `" "`, `"\t\n"` | Required-field handling, trimming |
| Random | `asdfghjkl`, `qwerty123` | Nothing in particular; the control case |
| Symbol | `!@#$%^&*()_+-={}[]\|;:"'<>,.?/` | Escaping, header construction, HTML |
| HTML | `<script>`, `"><img onerror>`, `<b>` | Output encoding |
| SQL-shaped | `' OR '1'='1`, `'; DROP TABLE` | Parameter binding |
| Unicode | `こんにちは`, `مرحبا`, `Привет` | Multi-byte length counting, collation |
| Emoji | `😀🚀🔥💻🎓` | Multi-byte length, surrogate pairs |
| RTL | `‮abc‬` | Visual spoofing, display order |
| Zero-width | `a\u{200B}b`, `a\u{FEFF}b` | Length counted differently from display |
| Long | 100, 160, 161, 500, 1000, 5000, 5001, 10000 chars | The boundary, either side of the rule |
| Type-confused | `123`, `true`, `null`, `[]`, `{}`, `1.5`, `NaN`, `Infinity` | Wrong type where a string is expected |
| Numeric edge | `0`, `-1`, `999999999`, `99999999999999999999` | Integer bounds |
| Id edge | `0`, `-1`, `999999999`, `abc`, `%00`, `1 OR 1=1` | Route binding |

## Observations

Tagged as BUG, QUESTION, IDEA, RISK, or NOTE. Times are the order of discovery.

| # | Tag | Charter | Observation | Evidence |
| --- | --- | --- | --- | --- |
| 1 | NOTE | — | Baseline before the pass: 813 tests, 3814 assertions, Pint clean, composer audit clean, build clean | recorded in `docs/project-audit.md` |
| 2 | BUG | C1 | **Every `max:` rule on course creation was reported unenforced.** A 161-character title, a 5001-character description, and a 101-character category were all accepted. | `tests/Feature/Qa/LengthRuleEnforcementTest.php` failed on all three. |
| 3 | NOTE | C1 | Observation 2 was **not a defect in the application.** The validator fails correctly in isolation, and a browser form POST is refused with a redirect carrying the error in the session. The 302 was being read as acceptance because a refused form POST answers 302 exactly as a successful one does. Split into `LengthRuleEnforcementTest`, which asserts the session rather than the status, and a second case proving the JSON shape answers 422. | diagnostic showed validator FAILS, request 302 to `/`, `session errors: []` on the wrong probe, and the real error present on the correct one |
| 4 | BUG | C1 | A course title of 10,000 characters causes the course page to render at an unusable width. Not a crash: the page renders, but the text overflows its container. | recorded as a layout risk; the value is stored and rendered as given |
| 5 | BUG | C1 | `is_correct` accepts the string `'yes'` and the integer `1`, and both are stored as **true**, so a question can be written with more than one correct option. Every answer then scores as right. | `test_an_answer_key_always_names_exactly_one_option` found 2 correct options stored from `['yes', false]` |
| 6 | NOTE | C1 | Two payload classes turned out to be untestable through a form POST rather than buggy: a PHP object cannot be sent in a form body at all. Sending one as JSON is refused with 422. The earlier 500 came from the test harness building the request, not from the application. | `postJson` with an object price returns 422, no row written |
| 7 | BUG | C1 | Eight stored-payload cases appeared to render as live markup. **Not a defect.** The page contained the payload correctly encoded inside a `meta` tag, and a substring search for `onload=window.__xss` cannot tell an encoded attribute from a live one. Replaced with a DOM check that asserts no element outside the application's own tag set exists. | page body showed `&lt;svg onload=window.__xss=4&gt;` in the meta description |
| 8 | BUG | C1 | **Fixed: a quiz question could be stored with more than one correct option.** `is_correct` was cast with `(bool)`, and PHP treats the string `'yes'`, the string `'on'`, and the integer `1` as true. A tampered or scripted request marking two options produced a question that every answer scored as correct. | `test_a_truthy_marker_cannot_produce_two_correct_options`; `['yes','on']` and `['yes',1]` each stored 2 correct options |
| 9 | RISK | C1 | Course titles and descriptions are stored and rendered at their full declared length. A 10,000-character title renders, so a page can become very wide. No crash and no data loss, but a person can make a course page unpleasant to read. | value stored intact, no max enforced beyond `max:160` being bypassed by this path |
| 10 | NOTE | C2 | **The final-administrator guard in `AssignUserRole` is unreachable.** It requires the target to be an active administrator with the active count at one, but `UserPolicy` already refuses a self-directed role change, so the actor is always a *different* active administrator and the count is at least two. It is defence in depth behind the policy, not a bug, but it cannot be tested directly. The property is now asserted where it is enforced. | diagnostic drove the system to one administrator and printed `demote the only admin -> HTTP 403`; the 403 came from the policy |
| 11 | NOTE | C2 | Three separate attempts to read a flashed error all failed before the assertion worked: the response object's own session, then the global `session()` helper, then the response after it had been sent. The client tracks the session for the request it made, so `assertSessionHasErrors` on the response is the only correct reader. | four readings of the same request disagreed |
| 12 | NOTE | C2 | A fixture for a completion test must mark the lesson `is_required`. The completion rule counts required lessons, so an optional lesson completes nothing and two certificate tests silently skipped. The two skips were replaced with a hard assertion that names the unmet rule. | `No certificate was issued` on two tests; fixed by `is_required => true` plus an explicit completion request |
| 13 | BUG | C5 | **Fixed: a settled amount was never checked against the amount owed.** `ProcessPayMongoEvent::markPaid` marked the Payment as paid and activated the enrollment without reading `amount` or `currency` from the event, and `PayMongoEventEnvelope` did not carry them at all. `docs/architecture.md` listed "Amount or currency mismatch" as a webhook test case, but no such test existed. A provider delivery of ₱1,000 would unlock a course priced at ₱125,000. Amount and currency are now read from the event and compared before the payment is applied. | `test_an_amount_that_does_not_match_is_not_applied` and the currency companion both failed before the fix; envelope diagnostic confirmed `amountMinor` was `null` |
| 14 | NOTE | C5 | A payment does not issue a certificate, because the course is not finished. A replay test asserting one certificate was failing on correct behaviour. Replaced with a comparison against the count before the replay. | first delivery activated correctly; the certificate assertion was the failure |
| 15 | NOTE | C5 | The webhook endpoint does not check the declared content type, so a correctly signed body labelled `text/plain` is applied. **Not a weakness.** The signature is the boundary and cannot be produced without the secret, and refusing on the declared type would risk dropping real deliveries where an intermediary rewrote the header. Replaced with two tests: an unsigned body is refused whatever the type claims, and a signed one is applied whatever the type claims. | a signed `text/plain` delivery returned 200 and activated the enrollment |
| 16 | NOTE | C5 | An event whose `reference_number` is hostile is still applied, because the payment is identified by the provider checkout id as a fallback. **Not a weakness.** An earlier version of this test asserted the enrollment stayed pending and failed on correct matching. Split into two cases: one where the checkout id is intact and application is expected, and one where both identifiers are hostile and nothing may be applied. | `resolvePayment` matches on `idempotency_key` first, then `provider_checkout_id` or `provider_payment_id` |
| 17 | BUG | — | **Found in my own harness, worth recording because it is easy to ship:** the webhook tests encoded the payload once to sign it and again to send it. `json_encode` with different flags produces different bytes, so a payload containing a slash or a multi-byte character produced a signature that did not match for reasons unrelated to the check under test. Replaced with a `sign()` helper that encodes once and returns both the body and its signature. | 38 failures traced to this, not to the application |
| 18 | NOTE | C3 | Six addresses that looked like malformed input are correctly answered with 200. `//courses` normalises, `/courses#frag` never reaches the server, and `?search[]=a` is an unknown parameter. Requiring a refusal for these asserts the application is *stricter* than it needs to be, which is its own kind of wrong. Split into "unresolvable" and "normalises" sets. | ten cases initially failed as `array contains 200` |
| 19 | BUG | — | **Fixed: the sign in, register, and forgot password pages each had two `h1` elements.** The decorative brand panel rendered its marketing line as a page heading alongside the form's own heading, so a screen reader announced "Learn IT. Build practical skills." before it said what the page was for. `docs/project-audit.md` states "exactly one `h1` per page" as verified; that claim came from a browser measurement which covered the workspace and catalog pages but not the three auth pages, so it had quietly stopped being true. The panel line is now a paragraph; the aside keeps its accessible name because `aria-labelledby` accepts any element. | `/login`, `/register`, `/forgot-password` each reported `h1 count: 2`; `/` and `/courses` reported 1 |
| 20 | RISK | — | The h1 claim in the audit was a browser measurement with no test behind it. `OneHeadingPerPageTest` now asserts it on all 19 public, student, instructor, and administrator pages, so the claim cannot quietly stop being true again. | 13 tests, 44 assertions |
| 21 | NOTE | — | **The first two browser "findings" were measurement artefacts, not defects.** Every signed-in page reported 144px of horizontal overflow and the create-course form reported no fields. The cause was the measurement setup: `PublicHttps` derives the scheme from `APP_URL`, which is `https` because the application is served over a tunnel, so every redirect to the sign in page pointed at an `https` address on a plain-http measurement port. Chrome then failed the TLS handshake and reported `ERR_CONNECTION_REFUSED`, and the probe was measuring Chrome's error page, which has no `overflow-x-hidden` and a fixed 1280px width. Reproduced outside the browser with a raw socket to prove it was the server response and not the probe. | raw response was `302 Found` with `Location: https://127.0.0.1:8011/login` against a plain-http server |
| 22 | NOTE | C2 | The final chaos pass, after the measurement environment was corrected, reported **zero bugs** across all three roles: no console errors, no uncaught exceptions, no failed requests, no horizontal overflow at 320, 360, 390, 768 or 1440, no spinner left on screen, no permanently disabled button, and every protected address redirecting a guest away. A 353-character value mixing emoji, Japanese, a right-to-left override and a script tag was typed into a real form, kept in full, and submitted without error. | `bugs: 0, notes: 1, shots: 21` for the instructor run; `0 / 2 / 20` for student and administrator |

## Debrief

### Coverage

| Charter | Status | Where |
| --- | --- | --- |
| C1 hostile text fields | covered | `HostileFormInputTest` (348), `LengthRuleEnforcementTest` (5) |
| C2 repeat, concurrency, illegal transitions | covered | `RepeatedActionTest` (17) |
| C3 malformed addresses and filters | covered | `MalformedRequestTest` (119) |
| C4 hostile query strings | covered | inside `MalformedRequestTest` |
| C5 webhook as an untrusted surface | covered | `WebhookHostilePayloadTest` (109) |
| Cross-cutting, page heading | covered | `OneHeadingPerPageTest` (13) |
| Browser-only behaviour | covered | `chaos-pass.mjs`, all three roles, 61 screenshots |

### Defects found and fixed

1. A quiz question could be stored with more than one correct option, because
   `is_correct` was cast with `(bool)` and PHP treats `'yes'`, `'on'` and `1` as
   true. Every answer would have scored as correct. `CreateQuizQuestion` now
   treats only an explicit mark as true and refuses a value a form could not
   produce.
2. A settled webhook amount was never compared with the amount owed. The envelope
   did not carry amount or currency at all, and `docs/architecture.md` listed
   "Amount or currency mismatch" as a tested case that did not exist. Both are now
   read and checked before a payment is applied.
3. The three auth pages had two `h1` elements each, against a stated project
   standard of one.

### Correct behaviour that looked like a defect

Five separate cases, each of which would have been reported as a bug if the
oracle had not been checked: refused browser form posts answer 302 rather than
422; a quiz question is matched by checkout id as well as by reference number; a
signed webhook labelled `text/plain` is applied because the signature is the
boundary; a malformed address is refused by throwing before routing; and the
final-administrator guard in `AssignUserRole` is unreachable because `UserPolicy`
already prevents the case it guards.

### Unexplored

- Live provider calls with real money. The checkout page PayMongo renders cannot
  be changed through the API, which is already recorded in `defense.md`.
- Cross-browser rendering. Only Chromium was driven. No Firefox or WebKit
  engine was available on this machine.
- Screen reader output. The heading structure was checked in the markup, not read
  aloud by an actual assistive technology.
- Long-running and multi-process behaviour under real concurrency. The two
  database session proof and the position lock tests from the earlier pass cover
  locking, but not sustained load from many clients at once.

## Second pass: every page, every role, driven rather than read

### Charters

Two questions, asked of the running application instead of the source.

1. Does every page render, for every role, without a server error, and does it
   stay inside its viewport on a phone?
2. Does every control do what it appears to do, and does every identifier and
   label on a page actually point at one thing?

A static reading of a controller cannot answer either. A Policy three calls away
inside an Action reads exactly like a missing one, and a card that looks clickable
reads exactly like one that is. Both were settled by sending real requests and
asking a real browser.

### What was measured

`tools/probe-routes.php` walked all 48 readable routes as a guest and as each of
the three roles, 192 requests, reporting the status and the query cost of each
one. A browser sweep then opened every page those requests resolved to, at 1440
and at 390 pixels, as all four roles, collecting console errors, failed
subresources, horizontal overflow, dead controls, unlabelled fields, nameless
controls, images with no `alt`, repeated identifiers, unsafe `target=_blank`, and
undersized tap targets.

The first two runs of that sweep are why this section is worth reading. Both
reported several hundred defects that did not exist, and both were wrong in a way
that would have sent somebody to change working code.

### Defects found and fixed

1. **`/user/confirm-password` was a 500 for every signed-in person.** The route is
   registered as soon as any Fortify view is enabled, but the response it returns
   is an interface bound only by `confirmPasswordView`, which nothing had called,
   so the container was asked to build an interface. The page is now written and
   registered, with tests covering the render, the guest redirect, a correct
   password, and a wrong one that confirms nothing.

2. **The Instructor course outline repeated thirty element identifiers.** The form
   component derived its identifier from the field name alone, and that page
   renders an add form for every module, lesson, material and quiz, so
   `field-title` appeared nineteen times. A `label for` matches the *first* element
   with that identifier, so eighteen fields were announced with the wrong name and
   any script looking one up edited the wrong row. The component now takes a scope
   prefix, which a caller cannot supply without also keeping the field name, and
   `UniqueElementIdTest` pins the property across every workspace.

3. **A course card was clickable only on its title row.** The card puts a stretched
   link on the title, and where that overlay lands is decided by the nearest
   positioned ancestor. The card is `relative`, and so was the row inside it
   holding the title and the price badge, so the nearer one won. Measured at 390
   pixels the overlay was 316 by 26 on a card 358 by 314, leaving the description,
   the counts, the level and the instructor name activating nothing. Pressing the
   card body, its button and its title now all reach the course page, confirmed by
   clicking each one.

4. **Twelve curriculum write routes had no cross-Instructor coverage.** Course
   editing is covered by `ContentEditingTest` and publication by
   `CoursePublishingTest`, but every store, archive, restore and reorder route had
   no second-Instructor case. `CrossInstructorCurriculumTest` now covers them and
   asserts the rows did not change.

### A test that had pinned a defect as expected behaviour

`CourseCardHitAreaTest` asserted that **two** elements inside the card are
positioned, naming the title row and the button wrapper, so that the button is not
swallowed by the title's hit area. The intent was sound and the button does not
need it: a positioned element paints above a non-positioned one that comes later
in the document, and the button already comes later. That second positioning
context was precisely what truncated the overlay in defect 3 above, so the test
was holding the fault in place. It now expects one, and a second test states the
general rule, that nothing between the card and its title link may be positioned.

### Two weaknesses in the new tests, found in the tests rather than the application

Both were found by removing the thing under test and watching the test pass, which
is the only way to tell a guard from a decoration.

- The cross-account test originally accepted a redirect as a refusal. A successful
  write answers with a redirect too. With both authorization layers removed from
  `CreateModule`, the test passed. It now requires 403 or 404, and fails.
- The reorder payload named a position that did not exist, so validation turned it
  away. Validation answers with a redirect, which is the same answer a permitted
  write gives, so the case passed without ever reaching the Policy. The fixture
  now builds a payload the validator accepts, which genuinely swaps two modules.

An earlier draft of the cross-account test was deleted rather than kept. It also
re-tested role changes, certificate revocation, announcement publishing and thread
privacy, and each of those already has a test that asserts both the refusal and
that the row did not change. A second, weaker copy of a property that is already
pinned is worse than no copy.

### Correct behaviour that looked like a defect

Four, each of which would have been reported as a bug had the oracle not been
checked first.

- **Almost three hundred undersized tap targets.** The stylesheet grows its targets
  inside `@media (pointer: coarse)`, which is the right place, because a mouse does
  not need a 44 pixel target. The probe had set a mobile viewport without emulating
  touch, so the media query never matched and every target measured 20 pixels on a
  page that serves 44 to a real finger. Settled by asking the browser what
  `matchMedia('(pointer: coarse)')` reported under the emulation.
- **A 21 pixel course card link.** The first hit test asked whether the element at
  a point was contained by the card, which is true of the whole card and therefore
  of nothing, and it reported all four corners as the link while the overlay
  covered only the header row. That misreading is what defect 3 was then confirmed
  from, using a test that asks whether the topmost element at a point *is* the
  link.
- **A 404 for `/favicon.ico`.** The layout declares and serves `favicon.png`, and it
  is present. The 404 came from `/user/confirmed-password-status`, which returns
  JSON and so carries no icon link, and which only the probe navigated to.
- **Material downloads answering 404 for everybody.** All 51 materials in the
  development database are text or link materials with no stored file, and the
  controller refuses a material that has nothing to download. The rule working,
  not the rule broken.

### Still unexplored

- **The upload, store and download path has never run against a real file.** No
  material in the development database has a stored file, so the private disk, the
  generated path, the stored MIME type and the authorised download are exercised
  only by tests that create their own bytes. Worth one end-to-end pass with a real
  upload before release.
- Dark mode was measured at both widths for contrast and layout, but not driven
  through the theme toggle, and no automated contrast measurement was run across
  every component.
- Keyboard traversal was checked for the skip link and focus rings in the markup.
  No automated Tab-order walk was done and no dialog was opened and closed with
  Escape.

### A dead read that cost eleven queries on every report visit

The report controller passed `forAdministrator()` to the view as `stats`. The view
never rendered it. Eleven count queries were issued on every visit of the page
and the result was discarded.

Nothing failed and nothing looked wrong. The page was correct, every test passed,
and the only symptom was a report that was a third more expensive than it had any
reason to be. It was found by counting queries per page rather than by reading
the controller, which is the only way a variable that is passed and never used
shows up at all.

The net effect on that page is worth recording on its own, because it is the
opposite of what adding features usually does:

| | queries |
|---|---|
| before | 22 |
| after adding a count strip and a course progress table | 32 |
| after removing the dead read | 21 |

More to look at, and cheaper, because eleven queries were paying for nothing.
The budget is now pinned with a ceiling rather than an exact number, so a query
saved elsewhere does not fail the test and a whole unused read cannot return. It
was verified by putting the dead read back, which reported 29 against the
ceiling of 24.

## Third pass: the reporting gaps the plan approves

### Two guards that had been overtaken

`CourseFoundationTest` and `StudentLessonAccessTest` both asserted that
`instructor.courses.students` must not exist. They were right to, when the route
was out of scope. `plan.md` L785 now lists "Student progress" among the things
the Instructor workspace focuses on, the route is built, and the guards were
retired with the reason written down rather than deleted, so the record of why
they were ever there survives. The neighbouring assertion in each file, for a
Student facing progress index that is still not approved, was left in place.

A guard is not a wish. Leaving one in place would have made the suite fail on
purpose; deleting one silently would have removed the reason.

### One lesson repeated, and this time noticed

Four times across these passes a background full-suite run was left going while a
filtered run was started in the foreground. Both processes use one test database,
and the result is either "table definition has changed, please retry transaction"
or a sheet of `table not found` failures. Four times the output looked like a
regression and was not. Twice a sabotaged file was left with a parse error and
the test "passed" against a file that could not load, which is a guard that
measures nothing.

Neither mistake is in the product. Both are in how the checks were run, and both
are recorded here rather than left as a footnote, because the second one is the
kind of error that makes a test suite look trustworthy when it is not.

## Fourth pass: the reference, and a chart that encoded nothing

### The bar chart drew every bar at full width

`style-src 'self'` forbids the `style` attribute. The bar fill set its width as
`style="width: 42%"`, the browser threw the declaration away, and the fill fell
back to its natural width. Every bar in every chart on all three dashboards drew
at one hundred percent, whatever its value. The browser logged eight CSP
violations on the Administrator dashboard and rendered the page anyway.

The `progress` component in the same directory already avoided this, and says
why in its own comment. The rule had been applied to one component and not to
the other. Both now use the same arrangement: a generated class sets a custom
property, and CSS reads it.

### Underneath it, a second fault

A row with no maximum was scaled against its own value, so every non-zero bar
was full width even with the inline style working. Two learners and one learner
drew as two identical bars. A bar chart whose bars are all the same length is
decoration, and it is worse than no chart because it looks like a reading.

### How it was found, and how it nearly was not

By opening the dashboards and measuring rendered pixel widths, after noticing
that three states holding nothing looked like three states holding everything.
The class names said `width: 0%` and the measurement said 100%, and the
disagreement between those two is what identified the cause.

There was already a test for "showing a full bar for a zero", and it was the
reason this went unnoticed for so long. It covered a system with **no data at
all**, where a chart of four zeros is replaced by an empty state. On such a
system the bug is invisible. The test asserted the anticipated symptom on the
one input where the symptom could not occur, and the suite was green the whole
time.

The replacements are written the other way round: one test forbids the inline
style, which covers the mechanism on any input, and one asserts that a zero row
and a full row differ, which covers the symptom.

### Four other defects in the same pass

- The Student's activity feed had no lesson source, though its docblock listed
  "a lesson finished". A learner with 21 completed lessons was told nothing had
  happened. Found by reading the rendered page against its own numbers.
- `tools/seed-dashboard-demo.php` overrode `status` on the factory, which
  replaces the state that sets `activated_at` alongside it, so it built rows the
  application cannot produce. The cause of the empty feed, and of an enrollment
  list sorted by a column that was null.
- A Continue Learning card badged a lesson "Completed" over a button reading
  "Resume this lesson". The service was left alone, because a deliberate existing
  test establishes that the most recently opened lesson is offered regardless of
  completion. Only the wording and badge colour were wrong.
- Dashboard cards were stretched to the tallest card in their row, leaving about
  230 pixels of empty panel under two Student cards.

### A habit worth recording

Three times in this pass a background suite was left running while files it reads
were edited, and once while a view was renamed mid-run. Compiled Blade caches go
inconsistent and the output is a sheet of failures that reads exactly like a
regression. All three were self-inflicted and all three were re-run clean.

The rule is simple and was still broken three times: nothing that the suite reads
gets touched while the suite runs. It is recorded here because the failure mode is
convincing enough to send somebody hunting a bug that does not exist.
