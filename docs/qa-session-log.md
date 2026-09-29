# QA session log Ã¢â‚¬â€ adversarial pass

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
| Unicode | `Ã£Ââ€œÃ£â€šâ€œÃ£ÂÂ«Ã£ÂÂ¡Ã£ÂÂ¯`, `Ã™â€¦Ã˜Â±Ã˜Â­Ã˜Â¨Ã˜Â§`, `ÃÅ¸Ã‘â‚¬ÃÂ¸ÃÂ²ÃÂµÃ‘â€š` | Multi-byte length counting, collation |
| Emoji | `Ã°Å¸Ëœâ‚¬Ã°Å¸Å¡â‚¬Ã°Å¸â€Â¥Ã°Å¸â€™Â»Ã°Å¸Å½â€œ` | Multi-byte length, surrogate pairs |
| RTL | `Ã¢â‚¬Â®abcÃ¢â‚¬Â¬` | Visual spoofing, display order |
| Zero-width | `a\u{200B}b`, `a\u{FEFF}b` | Length counted differently from display |
| Long | 100, 160, 161, 500, 1000, 5000, 5001, 10000 chars | The boundary, either side of the rule |
| Type-confused | `123`, `true`, `null`, `[]`, `{}`, `1.5`, `NaN`, `Infinity` | Wrong type where a string is expected |
| Numeric edge | `0`, `-1`, `999999999`, `99999999999999999999` | Integer bounds |
| Id edge | `0`, `-1`, `999999999`, `abc`, `%00`, `1 OR 1=1` | Route binding |

## Observations

Tagged as BUG, QUESTION, IDEA, RISK, or NOTE. Times are the order of discovery.

| # | Tag | Charter | Observation | Evidence |
| --- | --- | --- | --- | --- |
| 1 | NOTE | Ã¢â‚¬â€ | Baseline before the pass: 813 tests, 3814 assertions, Pint clean, composer audit clean, build clean | recorded in `docs/project-audit.md` |
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
| 13 | BUG | C5 | **Fixed: a settled amount was never checked against the amount owed.** `ProcessPayMongoEvent::markPaid` marked the Payment as paid and activated the enrollment without reading `amount` or `currency` from the event, and `PayMongoEventEnvelope` did not carry them at all. `docs/architecture.md` listed "Amount or currency mismatch" as a webhook test case, but no such test existed. A provider delivery of Ã¢â€šÂ±1,000 would unlock a course priced at Ã¢â€šÂ±125,000. Amount and currency are now read from the event and compared before the payment is applied. | `test_an_amount_that_does_not_match_is_not_applied` and the currency companion both failed before the fix; envelope diagnostic confirmed `amountMinor` was `null` |
| 14 | NOTE | C5 | A payment does not issue a certificate, because the course is not finished. A replay test asserting one certificate was failing on correct behaviour. Replaced with a comparison against the count before the replay. | first delivery activated correctly; the certificate assertion was the failure |
| 15 | NOTE | C5 | The webhook endpoint does not check the declared content type, so a correctly signed body labelled `text/plain` is applied. **Not a weakness.** The signature is the boundary and cannot be produced without the secret, and refusing on the declared type would risk dropping real deliveries where an intermediary rewrote the header. Replaced with two tests: an unsigned body is refused whatever the type claims, and a signed one is applied whatever the type claims. | a signed `text/plain` delivery returned 200 and activated the enrollment |
| 16 | NOTE | C5 | An event whose `reference_number` is hostile is still applied, because the payment is identified by the provider checkout id as a fallback. **Not a weakness.** An earlier version of this test asserted the enrollment stayed pending and failed on correct matching. Split into two cases: one where the checkout id is intact and application is expected, and one where both identifiers are hostile and nothing may be applied. | `resolvePayment` matches on `idempotency_key` first, then `provider_checkout_id` or `provider_payment_id` |
| 17 | BUG | Ã¢â‚¬â€ | **Found in my own harness, worth recording because it is easy to ship:** the webhook tests encoded the payload once to sign it and again to send it. `json_encode` with different flags produces different bytes, so a payload containing a slash or a multi-byte character produced a signature that did not match for reasons unrelated to the check under test. Replaced with a `sign()` helper that encodes once and returns both the body and its signature. | 38 failures traced to this, not to the application |
| 18 | NOTE | C3 | Six addresses that looked like malformed input are correctly answered with 200. `//courses` normalises, `/courses#frag` never reaches the server, and `?search[]=a` is an unknown parameter. Requiring a refusal for these asserts the application is *stricter* than it needs to be, which is its own kind of wrong. Split into "unresolvable" and "normalises" sets. | ten cases initially failed as `array contains 200` |
| 19 | BUG | Ã¢â‚¬â€ | **Fixed: the sign in, register, and forgot password pages each had two `h1` elements.** The decorative brand panel rendered its marketing line as a page heading alongside the form's own heading, so a screen reader announced "Learn IT. Build practical skills." before it said what the page was for. `docs/project-audit.md` states "exactly one `h1` per page" as verified; that claim came from a browser measurement which covered the workspace and catalog pages but not the three auth pages, so it had quietly stopped being true. The panel line is now a paragraph; the aside keeps its accessible name because `aria-labelledby` accepts any element. | `/login`, `/register`, `/forgot-password` each reported `h1 count: 2`; `/` and `/courses` reported 1 |
| 20 | RISK | Ã¢â‚¬â€ | The h1 claim in the audit was a browser measurement with no test behind it. `OneHeadingPerPageTest` now asserts it on all 19 public, student, instructor, and administrator pages, so the claim cannot quietly stop being true again. | 13 tests, 44 assertions |
| 21 | NOTE | Ã¢â‚¬â€ | **The first two browser "findings" were measurement artefacts, not defects.** Every signed-in page reported 144px of horizontal overflow and the create-course form reported no fields. The cause was the measurement setup: `PublicHttps` derives the scheme from `APP_URL`, which is `https` because the application is served over a tunnel, so every redirect to the sign in page pointed at an `https` address on a plain-http measurement port. Chrome then failed the TLS handshake and reported `ERR_CONNECTION_REFUSED`, and the probe was measuring Chrome's error page, which has no `overflow-x-hidden` and a fixed 1280px width. Reproduced outside the browser with a raw socket to prove it was the server response and not the probe. | raw response was `302 Found` with `Location: https://127.0.0.1:8011/login` against a plain-http server |
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
## Fifth pass: the site that went unresponsive under refresh

### The report, and the first wrong guess

Refresh the public address repeatedly and eventually the interface crashes. Sometimes
the whole site stops answering until the page is reloaded. It was reported as a
possibility of a memory leak, an unhandled exception, a database problem or a race,
and the first two hours were spent ruling those out one at a time.

Every one of them was wrong, and every one of them was ruled out by measuring
rather than by reading:

| Possibility | What was measured |
|---|---|
| Memory leak | 54 MB, flat, over 13 hours and about 6000 requests |
| Handle leak | 181, flat |
| Storage growing per request | Sessions and cache are in the database; no file growth |
| Unhandled exceptions | The live server wrote 0 bytes to the log for the whole session |
| Disk exhaustion | 20.8 GB free, though only 9 percent |
| The application | 1680 tests, green |

The answer was in the process table and needed none of that. The public address was
being served by PHP's built in web server, one process, and that server answers one
request at a time.

The cheap fix was tested before anything was built. `PHP_CLI_SERVER_WORKERS` would
have done it, and PHP refused it in as many words: *"forking is not supported on
this platform"*. Windows has no `fork()`.

The signature, measured rather than assumed: throughput was identical at every
level of concurrency. 23 requests a second at one connection, 23 at two, 23 at
forty eight. Latency rose in a straight line, from 40 ms to 2119 ms. Adding
concurrency bought exactly nothing but waiting, which is what a queue looks like
from the outside.

One page view costs four requests. Ten quick refreshes put forty requests into a
queue draining nine deep. Through a tunnel those multi second answers trip the
tunnel's own timeouts, and what a browser shows is a page that never finished. Stop
and the queue drained, which is why reloading sometimes brought it back.

### Three arrangements, and two that had to be thrown away

The FastCGI one was built first and worked. Setting the script name was the whole
trick, and it took three attempts:

`SetHandler "proxy:fcgi://..."` makes mod_proxy rewrite the request filename to the
proxy URL before the script fixup runs, so PHP was asked to open a file named
`proxy:fcgi://127.0.0.1:9000/C:/xampp/.../index.php`. It answered 404 with a body
of "No input file specified", and the application log said nothing at all. A small
FastCGI listener that prints the parameters it is given is what made it visible; a
`SetEnv SCRIPT_FILENAME` fixed it.

A correct `SCRIPT_FILENAME` was still not enough. php-cgi prefers
`PATH_TRANSLATED` over `SCRIPT_FILENAME` when it chooses a file to run, Apache
sends it, and the value it sends is the URL. `SetEnv PATH_TRANSLATED` was the
missing half.

Then six php-cgi processes behind a balancer: throughput 23 to 84 a second. And
then it collapsed past sixteen concurrent requests, answering 503, with the log
full of *"Got bogus version 0"*. php-cgi answers one request on a connection and
misreads whatever arrives next on it, and Apache reuses connections.
`ProxySet keepalive=Off` is the documented remedy. This Apache build refuses it in
a virtual host, where it is routed to the balancer manager, and in a per member
section, which a balancer member never matches.

That is the point at which the ceiling moved rather than disappeared, and a fix
that moves a ceiling is not a fix. php-cgi was dropped.

What is running now is Apache in front, and a pool of the same `php -S` workers the
project always used, behind Apache's balancer. Apache serves the stylesheet, the
script, the images and the icon from disk, so a page view does not spend a worker
on any of it.

| Measurement | One worker | Pool of six |
|---|---|---|
| Sign in page, throughput | 23 a second | 77 a second |
| Sign in page, at 48 connections, worst case | 2455 ms | 900 ms |
| Sign in page, failures at 48 connections | 0 | 0 |
| Dashboard, throughput | 9 a second | 26 a second |
| Dashboard, at 16 connections | 1756 ms | 596 ms |

### The fault the tests could not see

With the pool in place every page returned 200 and every page arrived with **no
stylesheet**. Apache replaces the `Host` header with the worker's own address
unless told not to, so every asset address the application generated pointed at
`https://127.0.0.1:8101/...`, and the content security policy, doing its job,
refused them.

Nothing in the test suite would have seen this, because the suite runs the
application without a web server in front of it. Nor would
`tools/probe-routes.php`, for the same reason. The browser test that drove the
public address found it, and only because it checked whether a stylesheet had
actually applied rather than whether the page had words on it. That check is now
part of the harness, and "unstyled" counts as a failure alongside blank.

`ProxyPreserveHost On` is the whole fix. The comment in the generated configuration
says so, because the symptom is a rendering fault in a browser and the cause is a
header in a file nobody thinks to look at.

### Two measurements that were wrong before they were right

The first load harness sent `Connection: close` and found nothing, then reported
every failure as "status 0", which cannot tell a refused connection from a timeout
from an empty reply. Both were the harness, not the server. Fixed before any
conclusion was drawn from it.

The route sweep reported an identical tally for all three roles, which is possible
and is also what a broken sign in looks like. It was a broken sign in: the cookie
function was being passed as a header value, so no cookies were ever sent and every
authenticated page answered as a guest. Caught by asking each role for a page that
belongs to another role, which is the only way to tell the two explanations apart.

### What is left

C: is at 9 percent free. Not the cause of anything today, and worth watching.

The `ngrok` authtoken was printed in the clear while reading its configuration
file. That was careless and is recorded here rather than quietly dropped. It lives
outside the repository so it was not committed. Rotate it.
## Sixth pass: the features that existed and could not be used

### The report

Ask what is still unfinished. The answer that took longest to find is the one that
looks least like a fault from the outside, because every page renders, every test
passes, and the feature is approved, built, tested and documented.

### How it was found

A crawl of every page each role can reach, 66 pages and 339 distinct form actions,
compared against the write routes the route table declares. The question is not
"does this page work" but "can a person reach the thing this route is for". Four
write routes had no form anywhere:

    ORPHAN  administrator posts a platform announcement
    ORPHAN  instructor posts a course announcement
    ORPHAN  a course thread is started
    ok      anyone opens a support conversation
    ok      a message is sent into a thread
    ok      a learning material is added
    ok      an administrator changes a user
    ok      a quiz question is authored

A route nobody can reach is not a feature. It is a controller with a test, and
every one of these had tests, because the tests posted to the routes directly. The
way a person reaches a feature and the way a test reaches it had drifted apart, and
only one of the two is the product.

### What the gate hid

`@can('createPlatform')` binds no model, so there is no policy to look in and the
answer is always false. Asked directly:

    createPlatform, no model                 false
    createPlatform, Announcement             true
    createCourse, Course model               false
    createCourse, Announcement and Course    true, for the instructor who owns it

Both abilities live on `AnnouncementPolicy`, so the form has to name the model.
`@can('createCourse', $course)` was looking for `CoursePolicy::createCourse`, which
does not exist. Both were measured rather than reasoned about, and both now render.

### Two faults underneath

The course in a thread address was decorative. `startCourseThread` re-derived the
course with `first()` over the pair's shared courses, so a Student in two courses
by one Instructor who asked about the second was handed the first. The action now
takes the course from the request and proves the pair shares that one.

The thread key was the pair alone, so even with the course passed in, a request
about course two returned course one's thread. The class docblock said the
guarantee came from "the unique index on (kind, course_id, requester_id)". There is
no such index. The one that exists is on `(kind, thread_key)`, and `thread_key` did
not say which course. A docblock describing an index that is not there made a real
gap look like a guarantee, which is worse than having written nothing. The course is
in the key now, and no migration is needed because the column and the index
already exist.

### A fault in the tool committed yesterday

`serve-concurrently.php` could not run a second instance. `mod_proxy_balancer` keeps
its membership in a shared memory file inside `DefaultRuntimeDir` and refuses to
start when that file exists, so one shared directory meant a probe instance failed
with `balancer slotmem_create failed` and nothing in the message said why. Found by
using the tool the way a person would. Each port now gets its own runtime
directory, and two instances run side by side.

### The demonstration could not show a certificate, and it was not a fault

Two enrollments had every lesson complete and no certificate existed. The
application's own checker said why: completion also requires passing required
published quizzes, nobody had sat one, and so nobody was eligible. Correct
behaviour, and a demonstration that cannot show the thing it built.

`tools/seed-completion-demo.php` runs the real `StartQuizAttempt`,
`SubmitQuizAttempt` and `CompleteCourse` rather than inserting rows, because
grading, the quiz notices to the Student, the activity notice to the Instructor,
the eligibility re-check and the certificate code are the parts worth having run at
least once. Two students now hold certificates they earned.

### What was ruled out, so the next pass does not repeat it

- Every page a person can reach, 27 pages times 3 roles times 2 widths: no
  unstyled page, no console error, no uncaught exception, no server error, and
  nothing overflowing at 390 pixels.
- The private file path, with a real PDF: uploaded as multipart, downloaded by an
  enrolled Student, 622 bytes out against 622 in, correct MIME, `no-store`,
  refused for an Administrator and for a signed out visitor, and a renamed text
  file refused as a PDF.
- One role asking for another's area: 403 in all six directions.
- A wrong password: refused, and the refusal does not distinguish an unknown
  account from a wrong one.
- Odd addresses, including a null byte, `..`, `abc`, `-1`, `1e5` and a 600
  character path: 404 or 403, and no page leaks a stack trace, a SQLSTATE or a
  framework class name.

### Three harness faults recorded, because each read as an application fault

- A sign in that redirects is not a sign in that worked. A rejected attempt
  redirects to the form too, so a helper that checked only the status reported
  success and every later request came back as a guest. The redirect target is the
  check.
- The application rate limits sign in and returns 429 with `Retry-After`. That is
  right, and a probe that signs in as three roles in a row has to wait the time the
  server asked for rather than measure the wrong thing.
- Laravel redirects back with 302 on a validation failure, exactly as it does on
  success. Three checks reported a refusal as an acceptance until the redirect was
  followed and the page read.

### A caution about the public address

`distinct-perplexed-defog.ngrok-free.dev` is IPv6 only: five AAAA records and no A.
For part of this pass the name resolved through `Resolve-DnsName` and failed
through `getaddrinfo`, which is what Node and .NET use, so half the probes failed
for a while with nothing wrong with the application. `ngrok http 8000` carries no
`--domain`, so the name is a random free one rather than a reserved one. A
demonstration depends on that name resolving on the presenter's network, and
nothing in the repository can make that true.

## Seventh pass: preparing the demonstration accounts

Date: September 29, 2026
Scope: the five accounts the demonstration is built on, and the faults found by
actually using them rather than by reading the code.

### What was asked for

Five accounts with fixed names, addresses, roles and passwords, a clean account
list, and proof through the application's own authentication that each one works
and is kept apart from the others.

### The accounts, and what each one was already carrying

Inspecting first turned out to matter more than expected. Two of the five addresses
already existed and already owned the demonstration:

| Account | Role | Already held |
| --- | --- | --- |
| `jayzeeb65@gmail.com` | Administrator | 2 announcements, a conversation |
| `bautista.jayzee@ncst.edu.ph` | Instructor | all 5 courses, 17 quizzes, 2 announcements |
| `garmino.shanleekian@ncst.edu.ph` | Student | 2 enrollments, 21 lessons, 5 attempts, 2 certificates |
| `lalamonan.joren@ncst.edu.ph` | Student | new |
| `guia.justinejosh@ncst.edu.ph` | Student | new |

Two accounts that were not on the list held data as well, so both deletions were put
to the user before anything was touched. `rutherford.emmanuelle@example.com` had an
enrollment, 4 messages and 3 notices; it is a reserved `example.com` address with a
name that does not match it, so it is seeded test data that could never receive mail
and never be signed into again. Its enrollment was moved to Joren Lalamonan first, so
the course kept a second student and the account list came out at five.
`jaybau16@gmail.com` had no enrollments and was registered for the user an hour
earlier to walk the verification flow; it was removed as superseded.

`tools/prepare-demo-accounts.php` applies the list. It reads the passwords from a JSON
file whose path is given on the command line and refuses any path inside the
repository, so no credential is ever written into a tool or into the history. It
never prints a password, not even to say it set one.

### Three faults the rehearsal caught, none of which the suite could see

**1. The role was reported but never set.** The tool created the five accounts and
printed `as administrator`, `as instructor`, three times `as student`. All five were
students. `Profile` has `bio` as its only fillable field, so passing `role` into
`create()` was dropped without a word and the column took its database default. On
the real database this would have left a demonstration with nobody able to administer
or teach anything, while the tool reported all three roles. The role is now set by
assignment, the way `BootstrapOwner` does it, and read back afterwards so the tool
reports what is stored rather than what it intended.

**2. Half a list was applied before the run stopped.** Checking and writing happened
in the same pass, so a list whose fifth entry asked for the wrong role created the
first four accounts and then stopped. Half a demonstration applied is worse than
none: the accounts exist, the passwords are set, and the person using them is not the
person they were prepared for. The list is now checked in full before anything is
written, and every problem is reported at once.

**3. A hand built row is silently wrong.** `Enrollment` accepts only `student_id`
and `course_id` from a caller, so a test that built one by hand got `pending_payment`
from the column default and a student the notification query excludes. This bit a
test written during this pass, not the application. The factory is the right way to
make one, and the same trap exists on `Course` and on `Profile`, which is the models
protecting server owned fields rather than a fault.

### A real fault: a notice that 500s on a local request

Publishing a course announcement over `http://127.0.0.1:8000` returned HTTP 500 with
`A notification link must stay on this application` raised from inside a notice that
was entirely safe.

`RecordNotification` allows an absolute link only when its host matches
`config('app.url')`. Eleven listeners compose their links with `route()`, which takes
its address from the request in flight, so every one of them refused a legitimate
action whenever the application was reached by a different name than the one it
believes it is published at. It passed every earlier check because every earlier
check went through the ngrok tunnel, where the two hosts happened to agree. The suite
agreed with itself for the same reason: `.env.testing` sets `APP_URL` to
`http://127.0.0.1:8000` and the test client requests exactly that, so the refusal was
unreachable.

The guard now accepts the configured address or the host the request arrived on, and
the scheme is not part of the decision because `toLocalPath` discards it before the
value is stored, so it cannot reach the column either way. A third host is still
refused, and a test asserts that a spoofed `Host` header still yields a path.

### A second real fault: the author could not see what they published

An instructor publishes an announcement to their course. Enrolled students receive it
and can open it. The instructor's own list showed only the two platform
announcements, so they could not read it, and had no list to withdraw it from.

`AnnouncementPolicy::view` has always allowed the author and the instructor who owns
the course. `Announcement::scopeVisibleTo` joined only through an enrollment. The
controller's own comment says the two must be one rule, so this was a query narrower
than the policy it claims to mirror rather than a new feature, and the two claims
were added to the query. `AnnouncementTest` now asserts the list and the policy agree
for every combination of reader and announcement, so a clause added to one side alone
is caught rather than shipped. The administrator's view is unchanged, which is what
was asked for: platform notices only.

### The harness, which was wrong more often than the application

Three rounds of probe failures were each the probe being wrong about the application,
and each is now written into the probe at the point it matters:

- The enrol route is POST only, so asking it for a form returns 405 with nothing in
  it, and posting the token from that page is a 419 rather than a reading.
- A course cannot be told free from paid on the catalog page. It has the same enrol
  form either way, and the difference only appears as a redirect to a checkout.
- A quiz submits to its own `.../submit` page, not to the attempt. A selector looking
  for anything mentioning the attempt finds the read only page and posts to a GET.
- Answers are named after the question id (`answers[8]`), not by position, and a `q`
  field sits beside them.
- A thread's reply posts to the conversation itself, while close, reopen and archive
  post beside it and match the same search.
- A certificate number is four groups and lives on the certificate, not on the list.

The probe also used to enroll a student on every run, so a second run read a different
application from the first and a failure could not be told apart from a change
somebody else made. It now reads the account's real state first and accepts either
state where the application deliberately offers one of two: a completed lesson
withdraws its control, a sat quiz withdraws its start form, and a course thread that
exists withdraws the form that would have created a duplicate.

### A correction to my own account of what happened

The state agreed for the demonstration was described as "Justine is two lessons and
two attempts in with a paid course waiting at a checkout". By the time it was
agreed she had exactly that. By the time the work finished she had five enrollments
and twelve progress rows, because the workflow probe was run eight more times while
the two faults were being found, and each run enrolls into one more catalog course
and completes one more lesson.

That is the probe doing what an honest probe does, in a way that is wrong to repeat:
a verification that walks a student through enrolling, completing a lesson and
sitting a quiz is the only way to prove those screens work, and it is also the
wrong thing to run eight times before a presentation. Two lessons came out of it.

The first is that a probe which mutates demonstration data needs a way back, the
way `cleanup-probe-announcements.php` already existed for announcements. The
workflow probe now withdraws what it publishes for the same reason, and
`restore-the-agreed-demo-state.php` puts one named account back to the agreed shape
after a run that went further than intended. It prints every row before removing it,
removes only progress and enrollments belonging to that one account, and does
nothing on a second run.

The second is that a reading which looks wrong should be checked against the schema
before it is acted on. Two progress rows per lesson looked like a missing unique
index, which would have inflated every learner's progress and could have completed a
course early. There is a unique index on `(enrollment_id, lesson_id)` and no
duplicates; the doubling was a display artefact of a join in a throwaway query where
`lp.id` and `l.id` collided on the name `id`. The measurement that settled it was
one that counted distinct lessons rather than rows.

### Where it ended

- Accounts and role separation: 31 of 31 checks, all five signing in, landing on the
  right workspace, and refused the other two.
- Workflows: 38 of 38, including a real enrollment, a lesson completed, a quiz sat
  and graded at 100%, a message sent into a thread the instructor can see, and a
  certificate opened by its holder.
- Suite: 1713 tests, 7567 assertions. Pint 391 files. `composer audit` clean. 106
  routes.
- No password appears in any tracked file, and the account file is outside the
  repository.

### The state the demonstration now walks in with

Kept as it is, since each account carries one story, and counted rather than
described: Joren is enrolled in one course with nothing started. Justine holds two
free enrollments with one completed lesson in each, two quiz attempts, and one paid
enrollment waiting at a checkout. Garmino has 21 completed lessons, 5 attempts, 2
courses finished and 2 certificates.

The workflow probe mutates that state, so it is run before this shape is restored
rather than after.

## Eighth pass: the database audit

Date: September 29, 2026
Scope: the schema, the data in it, and whether either of them is sound. Asked for as
a normalization and integrity audit, with the instruction not to normalize blindly
and to fix rather than report.

### What was read, and what was not

The live schema through `SHOW CREATE TABLE` rather than the migration files, because
the migrations are what the schema was meant to be. 23 application tables and 8
framework tables. `assignments`, `submissions` and `grades` are named in the brief
and do not exist: the plan defers them, and a test now says so, so a half built one
cannot arrive as a stray migration.

MySQL 8.4.11, so the `CHECK` constraints in the schema are enforced rather than
parsed and ignored. That matters: three of them, including the one that keeps a free
course from carrying a price, would have been decoration on an older server.

### Fifty seven checks, and three rows that should not have existed

A foreign key proves a row points at an existing row. It does not prove it points at
the right one, and most of the rules that matter here are the second kind. Every check
asked a question the schema cannot answer, over every row, and named the rows that
failed rather than counting them.

Three findings, all found by reading the data rather than the code:

- Two lesson progress rows read as finished with no date beside them.
- Two profile rows belonged to accounts that no longer existed.
- Three notices pointed at an announcement that had been withdrawn.

### Two of the three were application faults, and both passed the suite

**A lesson finished through the update path kept no completion date.**
`MarkLessonComplete` leaves `completed_at` out of the columns an upsert may overwrite,
so a second press cannot move the moment the learner finished. That is right, and it
had a second effect nobody measured: a row that already existed took the update path,
which does not write the column at all. Opening a lesson is what a learner does before
marking it finished, so the ordinary journey produced a lesson recorded as finished at
no time. Every test in `LessonProgressTest` pressed the button straight after
enrolling and so never took that path. Fixed by adding the column to the overwrite
list exactly when the row is moving onto completed, with three tests: the date is
written on the visit-first path, a second press does not move it, and the path that
already worked still does.

**Withdrawing an announcement left the notices that announced it behind.**
`notifications.subject_type` and `subject_id` point at an announcement, a quiz, a
certificate and a conversation, so the reference is polymorphic and no foreign key
can hold it. Nothing cascades. Deleting the announcement alone left every student who
had been told holding a notice whose link answers 404. The withdraw action now
removes the notices in the same transaction, keyed on the subject so one withdrawal
cannot take another's notices, with a test for each half.

**The two orphan profiles were not a gap in the schema.** `profiles.user_id` is a
foreign key with a cascade and it is enforced, proven by asking the server to refuse
the insert. Those rows cannot have been written while it was in place, so they came
from a load with the checks turned off. `tools/repair-impossible-rows.php` removed
them, along with the three stale notices and the two missing dates, taking the date
from `last_viewed_at` because that is the closest evidence the row still holds. It
reports every row before it writes and does nothing on a second run.

### Four faults in the audit itself, which are worth more than the findings

A trustworthy instrument was needed before any finding could be believed, and the
first version was not one.

- Two aggregate checks answered with one row per table carrying a count, and counting
  rows reported six findings when the answer was six zeroes. Filtering them on a
  column that the other fifty five checks do not have turned a finding into silence,
  which is worse than the mistake it replaced, so the filter is scoped to the
  aggregates.
- A check demanded that every conversation have two members. A support thread has
  one until an administrator joins it through `/admin/support/{conversation}/join`,
  which is the design. The check was wrong and a healthy unclaimed request was being
  reported as a fault.
- The index audit compared a composite's leading column against every single column
  index in the schema and reported 49 redundancies, which is nonsense:
  `announcements.author_id` does not have an index called
  `conversation_messages_author_id_foreign`. Scoped to the same table and the same
  column, the answer is none.
- The plan measurement ran a query inside the loop that built its own test data, 3,600
  of them, and reused one variable for both a count and the array of rows, so the loop
  never ended. Both are the mistake the application's own query budget tests exist to
  catch, made in a tool rather than in the product.

### Normalization: what was deliberately left alone

Six columns are each determined by the enrollment they name, which is a transitive
dependency and a breach of third normal form taken literally:
`lesson_progress.student_id`, `quiz_attempts.student_id`, `certificates.student_id`,
`certificates.course_id`, `payments.student_id` and `payments.course_id`.

They stay. Every learner facing screen filters on one of them directly, and removing
them would turn the hottest queries in the product into a join to enrollments on every
row of every page, in exchange for removing a possibility the write path already
prevents. That is a bad trade, so the duplication is kept and the reason is written
down. The mechanism that makes it safe is `DatabaseIntegrityTest`, which reads every
row for all twelve rules of this shape and builds a course with a lesson and a learner
first, so a passing assertion is never an empty table agreeing with itself.

The same applies to the derived figures that are deliberately stored: the grade on an
attempt, the pass flag, the last message time on a conversation, and the name and
title a certificate keeps. Each is checked against the thing it was computed from,
and each is a copy taken once and not rewritten.

### Indexes: measured, and none added

The structural answer was that all 46 foreign keys have an index leading on their own
column, all 26 named access paths filter on a column that leads an index, and no
index repeats another. Then the same queries were run against a synthetic catalog of
4,000 courses, written inside a transaction that is rolled back: 13 of 14 query shapes
use an index.

The one that does not is an instructor's own course list, because the index is on
`instructor_id` and the list is ordered by the date it was last touched. The synthetic
data gave one instructor all 4,000 courses, which is not a shape this application
sees; the real one owns five and the page is fifteen long. An index was not added,
because the evidence does not ask for one, and "avoid adding indexes everywhere without
a reason" is easier to honour when the reason is measured rather than imagined.

### Where it ended

- Fifty seven integrity checks, none with rows to look at. Thirty five index checks,
  none uncovered.
- 1745 tests, 7567 assertions, up 32. Pint 393 files. `composer audit` clean. 106
  routes. Migrations reproduce from nothing, which the passing suite proves because
  every test builds its database from them.
- The live workflows again, after all of it: 31 of 31 on the accounts and the roles,
  41 of 41 on the learning path, the announcement lifecycle, messaging, notices,
  certificates and role separation.
- The demonstration state restored afterwards, because the workflow probe enrolls and
  completes as it goes.


## Ninth pass: the public interface, rebuilt against a system

This pass was asked for twice over. The first request was for a polished home
page and a new About page. The second was a complaint that the About page looked
generated, that the spacing was wrong, that the animations were missing, and that
a credit badge on every course cover looked cheap. All three complaints were
correct and all three were checked against a rendered page before anything was
changed.

### What was measured rather than assumed

A screenshot of the home page showed a page with no styling on it. The obvious
reading is a broken stylesheet. The actual cause took three steps to find:

`APP_URL` is the public tunnel, so `PublicHttps::isEnabled()` was true, so
`AppServiceProvider` called `URL::forceScheme('https')` on every request. A request
to the plain-http local port was therefore handed https URLs, and the page asked
the browser for `https://127.0.0.1:8000/build/assets/app-….css`. That port
serves http. The browser cannot verify a certificate for a loopback address, so it
discarded the stylesheet, and `document.styleSheets.length` was **0**.

The same document, with the stylesheet address corrected from https to http, was
measured again in the same browser:

| | stylesheets loaded | cover box | ratio |
|---|---|---|---|
| as served | 0 | 1224 × 1246 | 0.98 |
| same page, http | 1 | 390 × 219 | 1.78 |

Nothing at the HTTP level could have found this. The page answered 200, the asset
answered 200, and the refusal happened in the browser afterwards. A test made with
an HTTP client does not enforce the content security policy, so every check in the
suite passed while the page had no design system on it.

### Four faults the suite had passed

1. **The content security policy refused every course cover.**
   `img-src` was `'self' data:`, which is right for everything else and wrong for a
   cover chosen from the photograph catalog, which is served from
   `images.unsplash.com`. All five covers were blocked. The earlier evidence for
   this feature was twenty HTTP 200s from a server-side `fetch`, which is exactly
   the check that cannot see a browser policy.

2. **The cover fallback was an `onerror` attribute the policy forbids.**
   `script-src` is `'self'` plus a per request nonce, with no `unsafe-inline`, so
   the browser discarded the attribute. The fallback was absent on exactly the
   pages that needed it. The handler moved into the bundle, delegated and
   captured, because an `error` event on an element does not bubble.

3. **The home page and the footer sent every signed-in reader to a student route.**
   `student.courses.index` sits inside the group guarded by `role:student`, so an
   instructor and an administrator were each offered a link to a page that answers
   403, on the landing page. Both now ask `RoleBasedDestination::learningFor()`,
   so there is one answer rather than two that drifted.

4. **The live database had never had the cover migration applied.** The test
   database had it, because the suite migrates it on every run. The demonstration
   database did not, and every write to a cover failed with
   `Unknown column 'cover_disk'`. A test can only ever prove a thing about the
   database it runs against.

Each of these is now pinned by a test: `ContentSecurityPolicyTest` reads the
origins out of `CourseCoverCatalog` rather than a list written beside the policy,
`IconNameTest` reads every icon a template asks for against `config/icons.php`, and
`PublicContentTest` keeps build vocabulary off the public pages.

### The design system, decided in one place

The complaints about spacing were not really about spacing. The vertical rhythm
was written out band by band and had already drifted by a step in three places,
and the home page and the About page each invented a second scale of their own.
So it now lives in four named classes and nothing else chooses it:

- `.shell` — the page frame, one definition, used by every public page
- `.band` — the step between two sections, 40/48/56 pixels
- `.band-head` — the heading block: eyebrow, heading, lead
- `.measure` — 68 characters, because a wide screen will otherwise give a
  paragraph a hundred and thirty

The step went down from 128 pixels between two desktop bands to 112. Six bands at
the old value meant more space than content.

### The hero stopped being a dashboard

The opening held a panel of four tinted boxes: free courses, paid courses, lessons,
quizzes. That is the shape an analytics screen uses to report a system, and on a
page about a library of courses it read as though the site were showing a reader
its own internals. The four figures are the same four, still counted from the
database, on one ruled line beneath the headline at the weight of ordinary text.

The figures also turned out to be lying. The panel read `$freeCourses->count()`,
which is the number of cards drawn and therefore capped at three. Eight free
courses would have printed as three. It read correctly only because this repository
holds two, so the cap was never reached. `PublishedCourses::totals()` now counts
the whole published catalog, and it was verified by publishing nine more free
courses inside a rolled back transaction and asking the panel whether it agreed.

### A short row of cards, filled

With three columns and two courses, a third of the row was empty, and an
unexplained gap in the middle of a page is the most noticeable thing on it. The
first attempt capped the grid so two cards would not stretch, which removed one gap
and created a larger one to its right.

The row is now three columns with the third cell carrying a link to the full list,
centred, with the same icon square the other cards use. A group of one is never
filled, because a catalog holding one free course is a small catalog and padding it
out would be the page inventing a shape the data does not have.

### Animate on Scroll

Added, at 2.3.4, with the four effects the site uses and the thirty it does not
declared as nothing.

AOS's own stylesheet hides anything carrying `data-aos`, unconditionally. If that
stylesheet arrives and the script does not, every marked element stays at zero
opacity for ever: nothing throws, nothing logs, and the page has no text on it. So
the hidden state here is gated on the `aos-init` class AOS itself adds to an
element while initialising. An element is only ever hidden by code that has already
proved it is there to reveal it.

`ScrollAnimationTest` pins that, and pins the four places the wiring can be wrong.
It exists because the machine this was written on reports
`prefers-reduced-motion: reduce`, so every browser pass exercised the disabled path
and the animated path was asserted rather than observed. That is stated in the
test rather than left implied.

### The About page, written for the wrong audience

It ended with a table naming the framework, the language, the database and the
build tool. Every row was true and every row was irrelevant: a reader who opened
"About" to find out what a certificate is got a list of packages. The page is now
about the learning, from what a course is to what has to happen before a
certificate is issued. `PublicContentTest` keeps it that way, and it was proved by
putting the words back and watching the test fail.

The seven identical two column bands are gone. Each band is shaped to what it has
to say: an opening, a three part chain, the student's six steps, the instructor's
four, the four conditions for finishing, and a finish.

### The closing band stopped being a different component

It was a full width panel in the deep brand blue with white buttons on it, and the
only dark surface on a page of light ones. It is a border and a tinted ground now,
like every other panel, with the heading in the primary colour as the one thing
that marks it as a conclusion.

### The credit badge came off the cards

A dark "Photo: Unsplash" label sat over the bottom left of every cover. Six covers
in a grid meant six identical black labels over six different photographs, none at
the same contrast against its background, competing with the picture they were
printed on. It reads as a watermark nobody asked for.

The name and the address are still columns on the course and the credit is still
reachable, on the course's own page, which is where somebody is actually reading
about the photograph rather than scanning a list of twelve.

### What a real viewport found

The first responsive pass constrained the page with a `max-width` and reported zero
overflows. The document still reports the window's own width, so that measurement
was of nothing. Re-run through headless Edge at 390, 820, 1280 and 1600 pixels:

- **No horizontal overflow at any width.** `scrollWidth` equals `clientWidth` on
  every page, on the home page, About, the catalog and sign in.
- **No broken images.** Every cover loaded, which is what the policy fix bought.
- **No console errors** on any of the sixteen page and width combinations.
- **Four icon buttons** were 36 to 42 pixels wide rather than 44. All four clear
  the 24 pixel minimum in WCAG 2.5.8, so this is polish rather than a violation,
  and they are now square.

The card titles the same pass listed as 21 pixel tap targets are false positives:
the title carries a stretched overlay that covers the card, and a measurement of
the anchor cannot see a pseudo element.

### A panel of two, and a label that lied

Two cards with titles wrapping onto different numbers of lines had their buttons
at two different heights in the same row. `mt-auto` with a `pt-5` floor puts the
button on the bottom edge of every card while keeping the space above it in a card
that is exactly filled.

The credit line inside the card became one quiet sentence: subject, modules,
lessons, level, separated by rules rather than by four small icons. The instructor's
name came off it. Four icons in a row read as a toolbar and made the card look like
a panel of controls; it is now the four facts a student compares courses on.


## Tenth pass: loading the approved design direction, and finding nothing to fix in it

This pass started from an instruction to always use `skills-lock.json`. The lock
file records eleven skills, two of which govern interface work, and the project
has its own approved direction in `docs/design.md`, which is 1193 lines and says
it is the source of truth for the visual and interaction direction.

None of it had been read before the home page and the About page were built.

### What that cost

`docs/design.md` section 22 sets two requirements as hard, and section 23 sets
motion as a limit. Two of the three turned out to be already met, and the third
was a direct conflict with an explicit request.

### The two that were already met, after being measured wrong twice

**Touch targets at 44 pixels.** Section 22 says 44. WCAG 2.5.8, which is AA, only
asks for 24, and the first pass over the pages reported a dozen controls at 19 to
42 pixels and called the rest false positives without checking which. Two things
were wrong with that.

The first was treating WCAG's 24 as the bar. This project chose 44 in its own
document, which is WCAG 2.5.5 and AAA, so 24 is a pass against WCAG and a failure
against this project.

The second was the measurement. Every footer link reads as 20 pixels tall because
the rule that grows it to 44 is inside `@media (pointer: coarse)` and a headless
browser reports a fine pointer. Emulating touch on the same pages gives **zero**
undersized targets on all four pages at both 390 and 1440 pixels. The twelve
failures were the harness, and so were most of the remaining set: inline links in
a sentence, which WCAG exempts, and a card title, whose hit area is a stretched
pseudo element a measurement of the anchor cannot see.

**Colour contrast.** The same pass reported two failures on the sign in page: the
subheading at 3.69 to 1 and the copyright at 3.83 to 1, both against `#2563EB`.

Both were the harness again, twice over. It read `background-color` and walked up
the tree, and the auth panel paints a linear gradient over a flat fallback, so
every sample came back as the fallback and neither element had been measured
against the gradient. It also could not parse the colour, which is written in
`oklab()` by the Tailwind build, so the foreground was read as three channels of
`0.999994, 0.00004, 0.00002` and treated as near black.

Measured against what is actually painted at the text's own position, both sit
below the gradient's 82 percent stop and therefore on the deep end: **11 to 1 and
12 to 1**.

The panel's own comment recorded 4.2 and 4.8. Those are the ratios against the
flat fallback, and the comment was careful enough to say so. It was still a
different claim from the one a reader would take from it, so the measured values
are now written down beside it.

### The one that is a real conflict

Section 2 sets the dial at **MOTION 1**, and section 23 limits motion to theme,
drawer, dropdown, pending feedback and small hover transitions, and says not to
use large entrance sequences or decorative scroll effects.

The scroll reveal is both of those things. It was asked for, so it stays, and
section 2 now reads MOTION 2 with the departure named in the place that sets the
dial, and section 23 lists the reveal as allowed with five boundaries: one
animation per section rather than per element, a fade and a small rise, once only,
off under a reduced motion preference by the library's own switch rather than by
running faster, and hidden only by code that has already proved it will reveal
the element.

Recording the override in the document is what stops the two places disagreeing
quietly. The alternative, which is what happened for as long as the reveal
existed, is that the next person to follow the document deletes the animation.

### The spacing scale, which was genuinely wrong

Section 6 gives 4, 8, 12, 16, 20, 24, 32, 40, 48, 64. The layout classes were
using 56 and 80.

Both were chosen by measurement, which is the test: the desktop gap looked too
wide at 64 and too tight at 48, so it became 56. A closing band wanted more room
than a middle one, so it became 80. A scale that is adjusted to taste is not a
scale, and the first value measured against it is the one the next person copies.

The desktop step is 64 now, which is eight pixels more than the 56 it was. That
is five bands, forty pixels, on a page over four thousand five hundred tall.

`SpacingScaleTest` reads the scale out of the document rather than restating it,
so changing the scale is a change to the document and the test follows. It is
proved by putting 56 back and watching it fail. It is not in `HomePageRhythmTest`
because it needs no database, and a `RefreshDatabase` class made a check about
arithmetic in a stylesheet fail on a migration deadlock, which is a real failure
hiding behind an unrelated one.

### The click-through, which had been claimed and not done

"Every public page was reviewed" had been said on the strength of screenshots. A
screenshot cannot click anything.

Sixty destinations are now walked: every visible control on the home page, About,
the catalog, sign in, create account, forgot password, the terms and the privacy
notice, including the five course pages and both filtered catalog views. **Zero
problems.** Every destination answers a non-error status, has its own `h1`, and
logs no console error and no exception on the way.

The first version of the walk reported two 404s that were its own: the legal pages
are `/terms` and `/privacy`, and Laravel's `route()` emits absolute URLs, so every
same-origin link was being classified as external and skipped. Sixty controls, two
navigated. A walk that checks a fifth of what it claims to check and reports the
result as a pass is worse than no walk, and it was the same class of mistake as
the contrast harness: a measurement that could not see the thing it was measuring.

### What the gate says

- 1801 tests, 8079 assertions. Pint 408 files. `composer audit` clean. 108 routes.
- The suite was run from a rebuilt test database, and the reason is recorded in
  `tools/rebuild-the-test-database.php`: `migrate:fresh` reported "Dropping all
  tables DONE" and then failed on a table that had survived the drop, and a later
  run deadlocked on the drop statement and left a half dropped schema that made
  every following run fail on a foreign key pointing at a table that was no longer
  there. Six failures in one run were that, not code.
- The demonstration database was checked after every rebuild that could have
  touched it, and held 5 published courses and 62 lessons throughout.
