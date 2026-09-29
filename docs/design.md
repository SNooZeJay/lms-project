# IT Learning Hub design system

## 1. Document status

This document defines the approved visual and interaction direction.

It is the source of truth for:

- Design personality
- Color and typography
- Layout
- Navigation
- Reusable interface components
- Responsive behavior
- Accessibility
- Page states
- Light and dark themes

Product requirements remain in `plan.md`.

Technical implementation remains in `architecture.md`.

## 2. Design read

Reading this as: a practical academic web application for BSIT Students, Instructors, and Administrators, with a calm institutional voice, dial **ENERGY 1 / RHYTHM 2 / MOTION 2**.

The motion dial was raised from 1 for the scroll reveal in section 23. That is a
departure from this dial rather than an accident, it was asked for, and the
reason and the boundaries are recorded in section 23 so the two places agree.

The interface should feel:

- Academic
- Modern
- Friendly
- Technical
- Organized
- Easy to navigate
- Appropriate for a student project

The application should not feel like:

- A commercial course marketplace
- A social media product
- A gaming interface
- A hacker-themed website
- A generic AI-generated template

### Structural reference

Adminator, the dashboard reference kept in `FOR_UI/`, is the structural
reference for this interface. Its split authentication shell, its narrow single
form, its icon led inputs, and its top row pairing a way back with a link to the
other form are the patterns to follow.

What that means in practice:

- **Structure is taken from Adminator.** The layout, the split shell, the shape of
  the controls, and the arrangement of a form.
- **The palette is not.** Adminator's own dark teal, and its blue to purple aside
  gradient, are not adopted. The two core colours, one accent, and the neutral
  surfaces defined in this document are the palette, so every page stays one
  system rather than two.
- **Its content is not.** The sample testimonial, the "2026 preview" eyebrow, the
  location footer, the free trial line, and the social sign in buttons are not
  copied. A fictional quote and a claim about software that does not exist here
  would be untrue, and buttons for sign in methods this application does not
  offer would not work.
- **No code or asset is imported from it.** `FOR_UI` is a read-only reference.
  Every page here is written as Blade against the tokens in this document.

Adminator is a commercial template, so lifting its markup or its branding into a
student project is not something to do quietly. Reference the design, write the
code.

## 3. Design goals

The design must help users:

- Find courses quickly
- Understand current progress
- Complete required actions
- Read lesson content comfortably
- Distinguish status without relying on color
- Use the system on mobile, tablet, and desktop
- Recover from errors without technical confusion

Usability, accessibility, consistency, and responsiveness take priority over visual novelty.

### Hard layout rule

No page may be wider than the viewport. The shared header must never force horizontal overflow on a 390 pixel screen, so the brand truncates, the subtitle is hidden on small screens, the row may wrap, and the action buttons shrink on mobile. Any row of controls must wrap or stack instead of pushing content off screen. `overflow-x-hidden` is a safety net, never a fix, because it hides the symptom by cutting content off.

## 4. Visual identity

### Identity motif

The interface uses a structured academic rhythm:

- Numbered Modules
- Ordered Lessons
- Clear progress lines
- Stable page headers
- Consistent content widths
- Visible section boundaries

The motif should support learning, not decoration.

### Palette

Use two core colors, one accent, and neutral surfaces.

#### Light theme

| Token | Value | Purpose |
|---|---|---|
| `background` | `#F8FAFC` | Page background |
| `surface` | `#FFFFFF` | Cards, forms, tables |
| `surface-muted` | `#F1F5F9` | Secondary panels |
| `text` | `#0F172A` | Main text |
| `text-muted` | `#475569` | Supporting text |
| `border` | `#CBD5E1` | Borders and dividers |
| `primary` | `#1D4ED8` | Main actions and active navigation |
| `primary-hover` | `#1E40AF` | Primary hover and pressed state |
| `accent` | `#0F766E` | Small progress and learning accents |

#### Dark theme

| Token | Value | Purpose |
|---|---|---|
| `background` | `#0F172A` | Page background |
| `surface` | `#1E293B` | Cards, forms, tables |
| `surface-muted` | `#334155` | Secondary panels |
| `text` | `#F1F5F9` | Main text |
| `text-muted` | `#CBD5E1` | Supporting text |
| `border` | `#475569` | Borders and dividers |
| `primary` | `#2563EB` | Main actions and active navigation |
| `primary-hover` | `#1D4ED8` | Primary hover and pressed state |
| `accent` | `#5EEAD4` | Small progress and learning accents |

Pure black and pure white must not dominate either theme.

### Status colors

Status always includes text and an optional icon.

| Status | Text color | Background |
|---|---|---|
| Success | `#166534` | `#ECFDF5` |
| Pending | `#92400E` | `#FFFBEB` |
| Error | `#991B1B` | `#FEF2F2` |
| Information | `#1E40AF` | `#EFF6FF` |
| Neutral | `#334155` | `#F1F5F9` |

Dark-theme status badges need separate dark-theme values with verified contrast.

### Color rules

- Do not use blue-purple or cyan-purple gradients.
- Do not use glow effects.
- Do not use color as the only status signal.
- Do not place light text over an uncertain image without a readable overlay.
- Use the accent color sparingly.
- Keep the active palette within the approved core colors.

## 5. Typography

### Interface font

Use Inter when bundled locally.

Fallback stack:

```css
font-family: Inter, ui-sans-serif, system-ui, sans-serif;
```

Reason: readable at dashboard and lesson sizes, familiar for technical students, and suitable for long reading sessions.

### Code font

Use a readable monospace stack for code examples and technical identifiers:

```css
font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
```

### Type scale

| Role | Suggested size | Weight |
|---|---:|---:|
| Page title | `2rem` | 700 |
| Section title | `1.5rem` | 650 |
| Card title | `1.125rem` | 650 |
| Body | `1rem` | 400 |
| Supporting text | `0.875rem` | 400 |
| Label | `0.875rem` | 600 |
| Caption | `0.75rem` | 500 |

Use sentence case for headings and controls.

Avoid long uppercase labels and wide letter spacing.

### Readability

- Lesson body text should remain at least 16px.
- Long content should use a readable line length near 65 to 80 characters.
- Code blocks may scroll horizontally on small screens.
- Text must wrap without clipping.

## 6. Spacing, radius, and elevation

Use a consistent spacing scale based on 4px increments:

```text
4, 8, 12, 16, 20, 24, 32, 40, 48, 64
```

### Radius

| Element | Radius |
|---|---:|
| Inputs and buttons | 6px to 8px |
| Cards and panels | 8px to 10px |
| Dialogs | 10px to 12px |
| Badges | Full pill only when needed for compact status |

Do not make every element pill-shaped.

### Elevation

Use shadows only for raised surfaces:

- Dialog
- Dropdown
- Sticky header
- Floating mobile drawer

Normal cards should use borders and subtle surface contrast.

## 7. Application shell

All authenticated roles use one shell.

```text
Desktop
┌─────────────────────────────────────────────┐
│ Sidebar │ Header                            │
│         ├───────────────────────────────────┤
│         │ Page content                      │
│         │                                   │
└─────────────────────────────────────────────┘
```

The shell lives in `resources/views/layouts/app-shell.blade.php`. The public
site uses `resources/views/layouts/app.blade.php`, which is a different
document on purpose: a guest has no workspace, so a guest must not be shown a
sidebar with nothing in it.

### Sidebar

- Persistent on desktop
- Compact rail on supported tablet widths
- Off-canvas drawer on mobile
- Current route visibly highlighted
- Role-based navigation generated from Policies
- Hidden links supplement server authorization and never replace it

The rail is the approved reading of the compact-rail rule. From `lg` to `xl`
the sidebar is 80 pixels wide and shows icons only. Every icon keeps an
accessible name and a tooltip, and the visible text is still in the document
for a screen reader, so the rail hides nothing from a keyboard or a reader.

Navigation is built by `App\Support\Navigation`. Each item names the Policy
ability that guards its destination, and the item is dropped unless the server
already allows it. The Policy is asked with an empty model instance, never with
`null`, because a Policy is resolved from the model it is asked about.

### Brand in the shell

The mark appears in the sidebar, the drawer, the mobile header, the public
header, the public footer, the certificate, and the sign-in brand panel. It is
never enlarged past 64 pixels and never used as page decoration.

### Header

May contain:

- Page context
- Search where useful
- Theme toggle
- User menu

V1 does not include a notification center.

### User menu

- Profile
- Settings
- Logout
- Current role for Instructors and Administrators

### Mobile navigation

- Menu button has an accessible name
- Drawer opens and closes
- Escape closes the drawer
- Focus returns to the menu button
- Background content does not scroll unexpectedly

### Footer

One component, `resources/views/components/footer.blade.php`, used by every
layout. It is a component rather than markup written into each layout because
the two footers had already drifted apart, and drift is what makes a footer look
accidental.

It has two densities, and the difference is deliberate:

| Variant | Where | Contents |
| --- | --- | --- |
| `site` | Public pages | Brand, three link groups, copyright bar |
| `workspace` | Signed in shell | Brand, legal terms, copyright bar |

The workspace footer omits the navigation columns on purpose. Every destination
they hold is already in the sidebar, and repeating it would make the footer a
second, worse navigation. What the workspace still needs is the brand, the legal
terms, and the copyright.

Rules:

- The mark is `sm`, 28 pixels, matching the scale table above.
- Link groups are sized to their own content, not to a share of the page. The
  site publishes a handful of public pages, so stretching a few short labels
  across half the width leaves them floating rather than grouped.
- Section headings use the same style as the navigation groups, so the two read
  as one system.
- Footer links use `.footer-link`. They do not stack `link-quiet` under another
  colour utility, because which of two colour utilities wins is decided by
  stylesheet order rather than by anything visible in the view.
- The copyright is separated by a hairline so it reads as its own band.
- On a coarse pointer a link is at least 44 pixels tall. It grows into the gap
  the list already has, so the rhythm of the column does not change.
- The workspace footer carries `data-print="hide"`, because a certificate and a
  receipt are the only pages meant on paper.
- Registration is never offered in the footer. A guest reaches it from the sign
  in page, and `HomePageTest` pins that rule for the public pages.

The footer has no social links. The product has no social accounts, so a link to
one would be a dead button. The public reference design had them and they were
left behind on purpose.

## 8. Page inventory

### Public pages

- Home
- Course catalog
- Course details
- Login
- Register
- Forgot password
- Reset password
- Unauthorized page
- Not found page

### Student pages

- Dashboard
- My Courses
- Course overview
- Lesson page
- Assessments
- Quiz attempt
- Quiz result
- Payments
- Certificates
- Certificate view
- Profile
- Settings

### Instructor pages

- Dashboard
- Courses
- Create Course
- Edit Course
- Modules
- Lessons
- Materials
- Enrolled Students
- Assessments
- Create Quiz
- Results
- Profile

### Administrator pages

- Dashboard
- Users
- Courses
- Enrollments
- Payments
- Reports
- Activity
- Settings

Every navigation item must point to a built or approved route.

## 9. Public pages

### Home

The page should communicate:

- IT Learning Hub purpose
- Academic course catalog
- Main action for registration or sign in
- Clear links to real pages

Do not use unsupported claims, fake testimonials, or fabricated statistics.

The home page shows working **Sign in** and **Create student account** links for guests. It does not show links to courses, dashboards, or payments before those routes exist.

### Authentication pages

Authentication pages use a focused layout with one clear purpose per page:

- Sign in
- Create student account
- Forgot password
- Reset password
- Email verification notice
- Verification link sent state
- Required password change

The layout is a split panel. A brand panel states what the product is and
shows the real path a student walks, and a form panel holds the single form.
On a small screen the brand panel reduces to a compact header, because the form
must stay the only focus and the product must still be identified.

Every authentication card uses the same `card-accent-edge` surface: a surface, a
four pixel primary rule along the top edge, and elevation. That one treatment
is what makes the sign-in and sign-up pages read as a set.

Every authentication form must include:

- Visible labels
- Correct autocomplete values
- Password manager support
- Paste support
- Inline validation messages
- A keyboard-focusable error summary when validation fails
- Pending feedback after submission
- Safe success or error feedback
- A link back to the previous safe step when useful

Do not show a role selector, payment field, fake account, or unbuilt navigation item.

### Money formatting

Amounts are stored as integer minor units. A view never prints minor units
directly and never divides them into a float. `App\Support\Money` is the only
place a display string is built, and the interface shows the peso form
`₱499.00` given in the Philippine context rules. A free course shows the word
`Free` instead of `₱0.00`.

A stored charge, such as a payment record, is never reported as `Free`. A
payment carries no course type, so `Money::format()` formats it and the course
type only decides the `Free` wording on a course price.

The ISO code is printed beside the amount on the payment page, so the peso
symbol reads as a symbol and `PHP` stays on the record.

### Status wording

Every stored state is written in words by one class, `App\Support\StatusLabel`,
which returns both a sentence and a tone. A view never writes a state string by
hand, so one state can never read two different ways on two pages.

A tone is a colour token, never the whole message. Every badge carries a word,
and the status colour table above is unchanged.

An unrecognised state falls back to sentence case of its own value, so a new
state is still readable instead of printing a raw database value.

### Support classes for the interface

Three small classes own interface decisions, so a view stays declarative:

| Class | Owns |
|---|---|
| `App\Support\Money` | Formatting a stored amount for display |
| `App\Support\StatusLabel` | The sentence and the tone for a stored state |
| `App\Support\Navigation` | The role navigation, filtered by Policy |

None of them authorizes anything. `Navigation` reads a Policy to decide what to
draw; the route middleware and the Policy still decide what may be opened.

### Phase 3 role pages

Phase 3 role pages are intentionally small:

- Student landing page
- Instructor landing page
- Administrator landing page

Each page identifies the signed-in user, current role, and current account status. Links must point only to features already built.

Do not add sample courses, fake progress, payment totals, or unbuilt dashboard cards.

### Administrator user management

The Administrator user list uses a responsive table or stacked mobile layout with:

- Name
- Email
- Role
- Account status
- Created date
- Search by name or email
- Role and status filters
- Pagination

Role and status actions require clear labels and confirmation for suspension, reactivation, and demotion.

The activity page is read-only. It shows actor, target, event, previous value, new value, and timestamp. It must not show passwords, tokens, IP addresses, or browser metadata.

### Phase 4A Course foundation

Phase 4A has no public course page yet. The data rules still guide later Instructor and public course interfaces:

- Course status is visible as `Draft`, `Published`, or `Archived`
- Course type is visible as `Free` or `Paid`
- Prices are formatted from integer minor units and `PHP`
- Level is shown as `Beginner`, `Intermediate`, or `Advanced`
- The server owns the Instructor, slug, price, currency, status, publication time, and thumbnail path
- Course lists must never show private thumbnail paths or server-owned payment data

Do not add course cards, catalog filters, fake courses, or enrollment buttons before the Course data and authorization slices are approved.

### Phase 4B curriculum foundation

Phase 4B has no curriculum page yet. The data rules guide later Instructor and Student interfaces:

- Module and Lesson status is shown as `Draft`, `Published`, or `Archived`
- Ordered positions are positive and unique inside their parent
- Lesson required state is explicit
- Estimated minutes are optional but positive
- Material type is an explicit server-validated enum
- Storage paths are private metadata and never public URLs
- External links require a later allowlist and validation step

Do not add course outline cards, drag-and-drop ordering, file upload controls, or download buttons before the curriculum actions and authorization are approved.

### Phase 5A Instructor Course Outline UI

Phase 5A is the first Course browser experience:

- Course list uses a calm table or stacked mobile cards
- Primary action is **Create course**
- Course status is shown as text, not color alone
- Course price is formatted from integer minor units and `PHP`
- Course outline shows ordered Modules, Lessons, and Learning Material metadata
- Empty states explain the next safe action
- Draft content stays inside the Instructor workspace
- No public enrollment, payment, upload, or download controls appear

### Phase 5B curriculum authoring

Phase 5B adds two safe Instructor mutations to the Course outline:

- **Add module** uses a clear title and description form
- **Add lesson** uses title, summary, content, required state, and estimated minutes
- New records show as `Draft`
- Server ordering is shown as Module and Lesson positions
- Forms are usable on desktop and mobile
- No upload, download, public, or payment controls appear

### Phase 5C content editing

Phase 5C adds three edit forms and Edit links on the Course outline:

- **Edit course** changes title, description, objectives, category, level, type, and price
- **Edit module** changes Module title and description
- **Edit lesson** changes title, summary, content, required state, and estimated minutes
- Each edit page shows the current values before saving
- Each form has a visible `Cancel` link back to the outline
- Failed edits keep the typed text and show the shared error summary
- Read-only facts stay visible: slug, position, status, and price rule help text
- No delete, archive, reorder, publish, or upload control appears

### Phase 5D Learning Material authoring

Phase 5D adds material metadata to each Lesson on the outline:

- **Add material** uses a title, a type select, and a matching content or link field
- Only `Text`, `Code`, `Video link`, and `External link` appear in the type select
- File types are not offered because uploads are not built yet
- Each material row uses the same `Material 1` numbering wording as Module and Lesson, shows its type, and links to its edit page
- Failed submits keep the typed text and reopen the same form
- No upload, download, delete, or publish control appears

### Phase 5E publishing

Phase 5E adds two state controls to the Instructor workspace:

- **Publish course** appears on a `draft` Course
- **Unpublish course** appears on a `published` Course
- Only one of the two controls is shown at a time
- The outline header shows the current status and the first publish time
- A blocked publish explains what is missing, such as a missing Lesson
- No archive, delete, or public preview control appears

### Phase 6B free enrollment

Phase 6B adds the first student-owned records and pages:

- The public Course page shows one clear state: `Sign in to enroll`, `Enroll free`, `Enrolled`, or a paid-course note
- A guest never sees enrollment details
- **My courses** lists only the signed-in Student's enrollments with status, enrolled date, and Instructor name
- The empty state links to the catalog
- Each enrollment card links back to the public Course page
- A refused enrollment shows the shared error summary
- No lesson content, progress, payment, or cancel control appears

### Phase 6C lesson access

Phase 6C completes the Student reading flow:

- **My courses** leads to a Course page, then to a Lesson page
- The Course page lists published Modules and Lessons with `Required` or `Optional` and minutes
- The Lesson page shows title, summary, content, then Learning Materials below it
- Blank lines in lesson content become separate paragraphs
- Text and code materials render in a readable block
- Link materials show the full address and open in a new tab
- A note explains that file downloads and progress tracking are not built yet
- An unpublished course explains that the enrollment is kept
- No `Mark as complete` control appears

### Phase 6E lesson progress

Phase 6E adds progress that is always visible and always honest:

- The Lesson page shows a `Mark as complete` button, or a `Completed` badge once done
- Completing a Lesson returns to the same Lesson with a short confirmation
- The Student course page shows `Required`, `Optional`, and `Completed` markers per Lesson
- The Student course page and `My courses` show one completion percentage per course
- A percentage of `0%` is shown when no required published Lessons exist, never an empty box
- While a Course is unpublished, the percentage is hidden and a note explains why
- No percentage is ever accepted from the browser

### Phase 13 dashboards and reports

- Each dashboard opens with a row of real counts, one per concern, in a four-column band that wraps to two columns on mobile
- Counts come from a single report service, so a number can never disagree with itself across pages
- A label says exactly what is counted, in words, and never uses an unexplained abbreviation
- Every dashboard has an empty state that names the next action instead of showing a blank grid
- The Instructor dashboard lists only owned courses, so another instructor's work is never visible
- The Administrator report is a real table with a screen-reader caption, and it scrolls horizontally inside its own container rather than widening the page
- An amount is formatted as a currency and minor units are never printed raw

### Phase 11 and 12 payments

- A pending enrollment shows a Pay button with the exact amount, never a generic "Buy"
- The return page says plainly that it does not confirm payment by itself, so a Student is never misled while waiting
- Payment state is written in words: Waiting for payment confirmation, Payment confirmed, Payment did not go through
- The reference number is shown in monospace so a Student can quote it in support
- The amount is always stated as coming from the course record, so a tampered browser value is visibly irrelevant

### Phase 10 certificates

- The Student course page shows a Certificate panel with either the certificate, a claim button, or the exact list of what is still missing
- The certificate list states lessons and quizzes as `x of y`, so nothing is a mystery
- A certificate uses a double-ruled frame and a monospace code, and never claims to be an accredited document
- A revoked certificate states the reason and the date in words
- The certificate page says plainly that it is visible only while the Student is enrolled

### Phase 9 quizzes

- The Student course page lists each Quiz with `Not attempted`, `Not passed`, or `Passed`
- A Quiz page shows the passing score, attempts used, and every question with its options
- A Student never sees the correct answer or any explanation before submitting
- The result page labels the correct answer and the Student's own choice in text, not by color alone
- The score is stated as a percentage plus points earned out of points available
- Attempts used are always visible, so a blocked Student knows why

### Course catalog

- Search by course title
- Filter by category and level
- Filter by free or paid type
- Clear empty and error states
- A guest can browse without an account
- Cards show title, category, level, type, price, module count, and Instructor name
- A `Free` course shows the word `Free` instead of `PHP 0.00`
- The details page shows the outline structure, never Lesson content or material links
- A `Not open yet` note replaces an enroll button
- Filters use visible labels and keep a `Clear filters` link
- A draft Course has no public page at all
- Pagination when required

### Course details

Recommended order:

1. Course title and summary
2. Learning objectives
3. Level, format, and price
4. Course content outline
5. Completion requirements
6. Instructor
7. Enrollment action

The enrollment action must reflect real enrollment and payment state.

## 10. Student dashboard

The primary focal point is **Continue Learning**.

Recommended order:

1. Welcome and current role context
2. Continue Learning
3. My Courses
4. Progress
5. Recent Assessments
6. Certificates
7. Recent Activity
8. Payment History

Do not fill empty dashboard areas with fake analytics.

## 11. Instructor dashboard

Recommended order:

1. Teaching summary
2. Create Course
3. My Courses
4. Student progress requiring attention
5. Recent assessment results
6. Recent activity

Counts must come from authorized database queries.

## 12. Administrator dashboard

Recommended order:

1. Operational summary
2. Recent Students
3. Recent Enrollments
4. Recent Payments
5. Course status
6. Recent system activity
7. Reports

Keep the dashboard operational and readable.

## 13. Reusable components

### Base components

- Button
- Link button
- Input
- Textarea
- Select
- Checkbox
- Radio group
- Label
- Form error
- Badge
- Alert
- Card
- Modal
- Dropdown
- Tabs
- Accordion
- Tooltip
- Skeleton
- Pagination
- Empty state
- Error state
- Confirmation dialog
- Status badge

### Layout components

- App shell
- Sidebar
- Header
- User menu
- Theme toggle
- Breadcrumbs
- Mobile navigation

### LMS components

- Course card
- Course header
- Course outline
- Module list
- Enrollment card
- Lesson sidebar
- Lesson navigation
- Learning material list
- Progress bar
- Continue Learning card
- Quiz form
- Quiz result
- Payment status
- Payment history table
- Certificate card
- Certificate view

## 14. Forms

Every form must include:

- Visible label
- Required indicator when needed
- Input constraints
- Server validation feedback
- Safe error summary for long forms
- Pending state
- Success feedback
- Keyboard access

Placeholders may show examples but must not replace labels.

Dangerous actions require a confirmation step.

### Authentication form rules

- Use `autocomplete="name"`, `autocomplete="email"`, `autocomplete="current-password"`, and `autocomplete="new-password"` where appropriate.
- Never block paste into password fields.
- Use `type="email"` for email input.
- Keep the submit label specific, such as **Sign in** or **Create student account**.
- Move focus to the error summary after a failed submission.
- Keep errors associated with their fields and announce them to assistive technology.
- Show a pending state without removing the entered email or name unnecessarily.
- Never reveal whether an email exists on a login or password-reset response.

## 15. Tables

Tables must support:

- Semantic headings
- Responsive overflow or stacked layout
- Search where useful
- Filters where useful
- Pagination
- Loading state
- Empty state
- Error state
- Status text
- Keyboard-accessible row actions

Do not compress an Administrator table into unreadable mobile cards without a clear layout.

## 16. Progress indicators

Progress must represent real persisted data.

- Use text with every visual percentage.
- Use a consistent progress component.
- Do not mix Quiz scores into Lesson progress.
- Do not show animated decorative progress.
- Preserve meaning in screen readers.

## 17. Quiz interface

Recommended flow:

```text
Quiz instructions
→ Question
→ Selected option
→ Next question
→ Review answers
→ Submit
→ Result
```

Requirements:

- One clear question focus
- Large option targets
- Keyboard selection
- Visible selected state
- No correct-answer feedback before submission
- Pending state during submission
- Safe retry message after failure
- Result explanations after submission

## 18. Payment interface

The payment page must feel academic and trustworthy.

Show:

- Course
- Validated amount
- Philippine Peso format
- Payment status
- Pending explanation
- Retry action when allowed
- Return or cancel action

Do not show secret values, raw provider errors, or permanent transaction payloads.

## 19. Certificate design

Certificates should look formal and academic.

Include:

- Student name
- Course name
- Completion date
- Certificate identifier
- Revoked status when applicable
- Print action

Printing uses a dedicated print stylesheet.

V1 has no public verification page.

## 20. Theme behavior

The application has two themes:

- Light
- Dark

Rules:

- Theme choice persists in browser storage
- The initial page uses the stored theme before painting when practical
- System preference is the first-use fallback
- Both themes pass contrast checks
- Theme choice never changes authorization or business state

Do not add a theme dropdown or theme editor.

## 21. Responsive behavior

### Desktop

- Full sidebar
- Multi-column content where useful
- Tables remain readable

### Tablet

- Compact sidebar or collapsible navigation
- Reduced content columns
- Touch targets remain at least 44px

### Mobile

- Sidebar becomes a drawer
- Cards stack in one column
- Forms use full-width controls
- Tables scroll or stack based on information
- Lesson content remains the main focus
- Quiz options remain easy to tap
- No horizontal page overflow

## 22. Accessibility

- Semantic HTML
- Keyboard access for every control
- Visible focus indicator
- Color contrast meets WCAG AA
- Status uses text and color
- Forms have labels and error association
- Modals trap focus and close with Escape
- Images need useful alternative text
- Decorative images use empty alternative text
- Motion respects reduced-motion preferences
- Touch targets meet 44px minimum
- Language is set to English
- Page titles describe the current page

## 23. Motion

Motion is limited to:

- Theme transition
- Sidebar and drawer movement
- Dropdown and modal appearance
- Button pending feedback
- Small hover transitions
- **One restrained entrance per section, as it is scrolled to**

Do not use:

- Animated backgrounds
- Floating decorative objects
- Constant motion
- Large entrance sequences
- Per element motion inside a section
- Motion on anything the reader may still be trying to click

### The scroll reveal, and why it is here

A section that settles in as it is scrolled to was added on request and it is a
departure from the dial in section 2, which is recorded here rather than left for
the next person to find as a contradiction.

What is allowed is narrower than it sounds:

- One animation per section, not per element. A page that fades each heading,
  each paragraph and each icon separately is unreadable while it happens.
- Fade and a small rise, so the page is showing where it is rather than
  performing.
- The animation runs once. A band that replays every time it crosses the viewport
  is a band that cannot be scrolled past quickly.
- It is off for a reader who has asked for reduced motion, and it is off for that
  reader by the library's own switch rather than by running faster, so the motion
  is not in the document at all.
- It is hidden by code that has already proved it will reveal the element. A
  stylesheet that hides a marked element unconditionally leaves the page blank
  when the script does not run, and nothing throws or logs when that happens.

`ScrollAnimationTest` pins those five, because the machine this was built on
reports a reduced motion preference and so every browser pass exercised the
disabled path. The animated path was asserted rather than observed, and that is
stated in the test rather than left implied.



### Behaviour hooks

A behaviour that needs JavaScript is attached to a `data-` attribute and
handled in `resources/js/app.js`. The attribute names the behaviour, so a view
never carries an inline handler and never depends on a framework.

| Attribute | Behaviour |
|---|---|
| `data-theme-toggle` | Switches the light and dark theme |
| `data-dropdown`, `-trigger`, `-panel`, `-item` | Account menu open, close, and focus |
| `data-drawer`, `-open`, `-close`, `-panel`, `-backdrop` | Mobile navigation drawer |
| `data-print` and `data-print-button` | Print stylesheet and print action |
| `data-pending`, `data-pending-button`, `data-pending-label` | Submit pending state |
| `data-confirm` | Confirmation before a dangerous action |
| `data-error-summary` | Focus target after a rejected form |
| `data-table-toggle` | Show or hide a responsive table block |
| `data-form-context` | The authoring form that must reopen after a rejection |
| `data-brand-mark` | The brand mark image |

The drawer and the account menu share one focus contract: Escape closes the
open layer, focus returns to the control that opened it, Tab stays inside an
open layer, and a click outside closes it. The page behind an open drawer is
made inert and does not scroll.

### Content security policy

The security headers set a strict policy with no inline styles and no inline
scripts. Every behaviour therefore lives in `resources/js/app.js`, which the
policy allows as a same-origin file, and the two small inline scripts, the
theme bootstrap and the payment poll, carry the per-request nonce the headers
publish.

One consequence is worth keeping: no view may use an inline `style` attribute.
A progress bar is therefore a native `progress` element with a fixed set of CSS
widths, not a div with a painted length.

## 24. Content rules

- Use simple English.
- Prefer specific labels such as **Continue Learning** and **My Courses**.
- Avoid buzzwords and inflated claims.
- Never use sample names or metrics as real data.
- Mark examples clearly during development.
- Show safe validation and error messages.
- Do not expose technical stack traces to users.

## 25. Reference policy

The following references may inform patterns:

- Adminator for authenticated dashboard structure, used read-only
- Sailboat UI for basic form and control patterns, used after license review
- HyperUI for Tailwind CSS forms, content sections, authentication layouts, empty states, and responsive patterns

HyperUI reference: <https://github.com/markmead/hyperui>

HyperUI uses the MIT License and requires no package. It may be used when the approved local component set lacks a clear solution.

Rules:

- Do not copy branding or distinctive visual identity.
- Do not copy unrelated communication, map, or commerce pages.
- Adapt patterns to academic LMS content, design tokens, accessibility, and responsive behavior.
- Add loading, empty, error, success, keyboard, and focus behavior required by the LMS.
- Preserve the HyperUI MIT copyright and license notice when code or markup is copied.
- Check licenses before copying other code or assets.
- Keep `FOR_UI/adminator (FOR USER DASHBOARD)` read-only.

## 26. Visual assets

### Approved mark

The IT Learning Hub mark is approved. The source artwork is
`resources/it-lms-logo-only.png`. The served derivatives live in
`public/images/brand/`, and `public/images/brand/README.md` is the authority on
which derivative is used where.

The mark is a graduation cap over a cube, in the same blue as `primary`. The
source file is never served, because the mark is displayed between 28 and 56
pixels and the original is 800 pixels at 919 kilobytes. The files under
`public/images/brand/` are the same artwork resampled to the sizes the
interface actually asks for, so a page does not download a large image to draw
a small one.

The mark is a mark, not a lockup. The product name beside it is live text, not
part of the artwork, so it takes the colour of the current theme, stays readable
in both themes, and can be read and searched by a screen reader.

Rules for the mark:

- Display it between 28 and 56 pixels. Never stretch it and never use it as a
  background.
- Keep the mark's own proportions. The height is the widest dimension.
- Never edit a derivative by hand. Change the source and regenerate.
- The mark carries an empty alternative text when the name sits beside it, so a
  screen reader announces the name once and not twice.
- The mark is not restyled, recoloured, or given a drop shadow.

Where the mark appears:

| Place | Component size |
|---|---|
| Sidebar and drawer | `sm`, 28 pixels |
| Public header and mobile header | `sm`, 28 pixels |
| Public footer and account summary | `sm`, 28 pixels |
| Authentication brand panel | `lg`, 44 pixels |
| Certificate | `sm`, 28 pixels |
| Favicon and touch icon | `public/favicon.png`, `public/images/brand/touch-icon.png` |

The component chooses the derivative that matches the size it draws, so a small
mark is never shipped as a large file.

### Still not approved

No avatar, photograph, illustration, course thumbnail, or institutional mark is
approved.

During development:

- Use the product name as text.
- Use labeled placeholders for missing assets.
- Do not create realistic people or fake institutions.
- Do not present placeholder content as final.

## 27. Definition of done

The design is ready when:

- Public and authenticated pages share a coherent system
- Student, Instructor, and Administrator navigation works
- Light and dark themes pass review
- All data pages include loading, empty, error, and success states
- Forms and tables work on mobile
- Keyboard focus is visible
- Status remains understandable without color
- Course, Lesson, Quiz, payment, and certificate workflows are complete
- Philippine Peso formatting is consistent
- No dead navigation or fake data remains
- No copied branding or unlicensed assets remains

## 28. Interface component set

Reusable interface pieces live in `resources/views/components/`. A view uses
these instead of writing a long class list, so a token change reaches every page
at once.

### Base components

| Component | Purpose |
|---|---|
| `x-btn` | Button or link button, with a variant and a size |
| `x-badge` | A short status or category chip |
| `x-status` | A badge whose words and tone come from `StatusLabel` |
| `x-note` | A tinted, left-ruled message panel |
| `x-icon` | A 24 unit stroke icon on one grid |
| `x-logo` | The brand lockup, mark plus product name |
| `x-theme-toggle` | The light and dark control |
| `x-amount` | A money amount, never raw minor units |
| `x-progress` | A progress bar that always prints its number |
| `x-form-errors` | The keyboard-focusable error summary |
| `x-form-field` | Label, control, hint, and error for one input |
| `x-error-state` | A safe error page body |
| `x-breadcrumbs` | The trail of where the current page sits |
| `x-page-header` | Eyebrow, title, description, and actions |
| `x-stat` | One counted number, with a label that says what is counted |
| `x-empty-state` | A named empty state that offers the next action |
| `x-card` family | `card`, `card-muted`, `card-raised`, `card-accent-edge` |

### Layout components

| Component | Purpose |
|---|---|
| `x-app.nav` | Role navigation, grouped and Policy-filtered |
| `x-app.user-menu` | The account menu with role, profile, and sign out |

### Local partials

| Partial | Purpose |
|---|---|
| `admin/users/user-card` | One account as a stacked mobile card |
| `instructor/courses/course-card` | One owned course as a stacked mobile card |

### Rules for new interface pieces

- A new button, badge, note, or field goes into `app.css` and is used through
  these components. Do not add a new colour in a Blade file.
- A new reusable element is a Blade component with a `@props` block and a short
  comment explaining what it is for.
- A component that needs an injected service or real behaviour becomes a class
  under `app/View/Components/`.
- A page that needs a behaviour that is not HTML uses a `data-` attribute and a
  few lines in `resources/js/app.js`. No framework, and no new dependency.
