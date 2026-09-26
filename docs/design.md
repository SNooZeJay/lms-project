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

Reading this as: a practical academic web application for BSIT Students, Instructors, and Administrators, with a calm institutional voice, dial **ENERGY 1 / RHYTHM 2 / MOTION 1**.

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
- A copied Adminator dashboard
- A generic AI-generated template

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

### Sidebar

- Persistent on desktop
- Compact rail on supported tablet widths
- Off-canvas drawer on mobile
- Current route visibly highlighted
- Role-based navigation generated from Policies
- Hidden links supplement server authorization and never replace it

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

Do not use:

- Animated backgrounds
- Floating decorative objects
- Constant motion
- Large entrance sequences
- Decorative scroll effects

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

No logo, avatar, photograph, illustration, or brand mark is approved yet.

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
- No copied branding or unlicensed assets remain
