# BSIT Academic LMS product plan

## 1. Document status

This document defines the approved product scope for V1.

It is the source of truth for:

- User roles
- Required features
- Business rules
- Security requirements
- User experience requirements
- Acceptance criteria

Technical implementation belongs in `architecture.md`.

Visual design belongs in `design.md`.

Product display name: `IT Learning Hub`.

Internal project description: BSIT Academic LMS.

## 2. Product vision

Build a beginner-friendly academic Learning Management System for BSIT students in the Philippines.

The LMS should support a complete learning journey:

```text
Register
→ Sign in
→ Discover a course
→ Enroll
→ Study lessons
→ Complete a quiz
→ Track progress
→ Meet course requirements
→ Receive a certificate
```

The application should feel like an academic learning platform, not an online shopping marketplace.

## 3. Target users

### Student

A Student learns from enrolled courses.

A Student can:

- Register and sign in
- Manage a profile
- Browse published courses
- View course details
- Enroll in free courses
- Start PayMongo checkout for paid courses
- View personal payment history
- Access authorized lessons and materials
- Mark lessons complete
- View progress
- Take quizzes
- View quiz results
- View personal certificates

A Student must never access another Student's private records.

### Instructor

An Instructor creates and manages academic content.

An Instructor can:

- Manage a profile
- Create courses
- Edit owned courses
- Publish or unpublish owned courses
- Manage modules, lessons, and learning materials
- View enrolled Students for owned courses
- View learning progress for owned courses
- Create quizzes and questions
- View assessment results for owned courses
- Configure course completion requirements

Each course has one Instructor owner in V1.

An Instructor cannot manage another Instructor's course.

### Administrator

An Administrator manages system operation.

An Administrator can:

- Manage profiles
- Promote users to Instructor or Administrator
- Suspend or reactivate accounts
- Manage all courses
- Review enrollments
- Review payments
- Review activity logs
- View operational reports
- Manage approved system settings
- Revoke and reissue certificates

An Administrator cannot directly activate a paid enrollment without verified payment evidence.

## 4. Role assignment

Public registration always creates a Student account.

The registration form must not contain a role field.

Instructor and Administrator roles are assigned through a protected Administrator action.

Every role change must record:

- Actor
- Target user
- Previous role
- New role
- Timestamp

V1 does not include:

- Multi-role accounts
- Instructor applications
- Invitation workflows
- Co-instructors
- Course ownership transfer

### Phase 3 role management

An Administrator can manage verified user accounts through protected server actions.

Phase 3 allows:

- Student, Instructor, and Administrator role assignment
- Account suspension and reactivation
- A searchable and filterable Administrator user list
- A read-only activity page for role and status changes
- Minimal authorized landing pages for each role

Phase 3 rules:

- A user cannot change their own role or account status.
- The final active Administrator cannot be demoted or suspended.
- Role changes require a verified target email.
- Suspended accounts cannot sign in or continue using an existing session.
- Role and status changes are written with their activity record in one database transaction.
- No hard-delete, archive, bulk-action, or multi-role workflow is added.

### Initial Administrator bootstrap

The first Administrator is provisioned through a local-only Artisan command for setup and recovery.

The command is not a public registration path and does not create an Owner role.

The command must:

- Use the approved Administrator name and email
- Create the User and Profile in one transaction
- Mark the local demo account as verified and active
- Generate a temporary password with a secure random source
- Store the temporary password with Windows DPAPI outside the repository
- Set `must_change_password` to true
- Refuse to silently promote an existing Student or Instructor
- Never log or commit the temporary password

A User must change the temporary password before opening normal authenticated pages.

## 5. V1 course structure

```text
Course
  └── Module
        └── Lesson
              ├── Learning Material
              └── Assessment reference where applicable
```

A Quiz always belongs to a Course.

A Quiz may also belong to a Module or Lesson when the parent relationships match.

### Course types

- `free`
- `paid`

Free courses use a zero price.

Paid courses use a positive price in Philippine Peso.

### Course states

- `draft`
- `published`
- `archived`

Only published courses appear in the public catalog.

Instructors can view owned draft and archived courses.

Administrators can view all courses.

### Unpublishing

Unpublishing a course:

- Removes it from the public catalog
- Blocks new enrollment
- Preserves existing active or completed access
- Preserves learning history

An Administrator must perform any later access suspension through a protected and audited action.

### Phase 4A Course foundation

Phase 4A creates the Course data foundation before any catalog or enrollment UI.

Phase 4A includes:

- `courses` table
- `Course` model
- `CourseLevel`, `CourseType`, and `CourseStatus` enums
- Course factory
- Instructor ownership relationship
- Database constraints and indexes
- Migration and model tests

Phase 4A defaults:

- `level` defaults to `beginner`
- `course_type` defaults to `free`
- `price_minor` defaults to `0`
- `currency` defaults to `PHP`
- `status` defaults to `draft`
- `published_at` starts as null

Phase 4A rules:

- A Course must belong to one Instructor User.
- A Course slug must be unique.
- Free Courses must have a zero price.
- Paid Courses must have a positive price in minor units.
- Currency must be `PHP`.
- Level, Course type, and Course status use approved enum values only.
- Instructor, slug, price, currency, status, publication time, and thumbnail path are server-owned fields.
- Phase 4A does not create public catalog pages, enrollment, payment, curriculum, or upload behavior.

## 6. Enrollment

Enrollment is the canonical record for Student access to a Course.

Payment is a separate record.

A Student has one canonical enrollment per Course.

### Enrollment states

| State | Meaning | Grants access |
|---|---|---|
| `pending_payment` | Paid enrollment awaits verified payment | No |
| `active` | Free enrollment or verified paid enrollment | Yes |
| `completed` | Course requirements were verified | Yes |
| `cancelled` | Enrollment was cancelled or revoked | No |

### Free enrollment

1. Student signs in.
2. Student selects a published free Course.
3. Server verifies role, publication, and existing enrollment.
4. Server creates or reuses one active enrollment.
5. Student receives access to authorized learning content.

### Paid enrollment

1. Student signs in.
2. Student selects a published paid Course.
3. Server creates or reuses one `pending_payment` enrollment.
4. Server reads the price from the Course.
5. Server creates a pending payment attempt.
6. Server starts PayMongo checkout.
7. No course access is granted yet.
8. A verified successful PayMongo event marks the payment paid and activates the enrollment in one database transaction.

### Failed or cancelled payment

A failed or cancelled payment attempt leaves the enrollment in `pending_payment` so the Student can retry.

A Student can cancel the enrollment explicitly.

V1 has no automatic payment-expiry enrollment state.

### Refund

A refund changes the payment status to `refunded`.

A refund does not automatically cancel access or remove learning history.

An Administrator may cancel or suspend access through a separate audited action.

## 7. Payments

PayMongo is the selected V1 payment provider.

The PayMongo public test key is configuration for a later payment phase. It is kept in the local ignored `.env` and is not used by authentication or profile features.

All secret payment operations run on the Laravel server.

The browser never receives:

- PayMongo secret keys
- Server-only provider credentials
- Authoritative payment status
- Permission to mark a payment paid

### Payment states

- `pending`
- `paid`
- `failed`
- `cancelled`
- `refunded`

### Payment rules

- Store money as integer minor units.
- Store `PHP` as the currency code.
- Copy the validated Course price into each payment attempt.
- Do not use floating-point values for money.
- Use a unique idempotency key for each checkout attempt.
- Store a unique provider event reference for every processed webhook.
- A repeated webhook must not create duplicate payment, enrollment, or access records.
- A successful browser return page is not proof of payment.
- The verified webhook is authoritative.

PayMongo API methods, event names, supported payment methods, amount units, retries, and signature rules must be checked against current official documentation before payment implementation.

## 8. Learning materials

A Learning Material can be:

- Text or Markdown
- Image
- PDF
- Approved office document
- Code example
- Approved external video link
- Approved external resource link

V1 uses private Laravel Storage disks for uploaded files.

Uploaded file paths must be generated by the server.

A file path alone never grants access.

The server checks role, course ownership, enrollment, and resource relationships before serving a private file.

V1 does not include direct video uploads or public protected-file storage.

Exact file-size limits remain deferred until upload testing defines safe values.

## 9. Lesson access and progress

A Student can open any authorized published Lesson.

V1 does not require the previous Lesson to be completed before opening the next Lesson.

A Student needs an `active` or `completed` enrollment.

The server records:

- `not_started`
- `in_progress`
- `completed`

Opening a Lesson records recent activity but does not mark completion.

The Student must run a valid **Mark as Complete** action.

### Progress calculation

The server calculates progress from persisted records.

Course percentage uses completed required published Lessons divided by total required published Lessons.

Quiz completion and scores appear separately.

A browser cannot submit an authoritative progress percentage.

## 10. Course completion

Course requirements are configurable by the owning Instructor or an Administrator.

The approved default checks:

- Student has an active enrollment
- Required published Lessons are complete when required
- Required published Quizzes have passed attempts when required
- Server recalculates every value from database records

After all requirements pass:

1. Server marks the enrollment `completed`.
2. Server records `completed_at`.
3. Server may issue a certificate through an idempotent action.

Later Course or requirement edits do not silently revoke an existing completion or certificate.

An explicit Administrator action is required for revocation.

## 11. Quizzes

V1 supports multiple-choice questions.

Each question has:

- Prompt
- Position
- Point value
- Options
- One correct option
- Optional explanation shown after submission

Correct-answer data must never appear in a Student response before submission.

### Attempt policy

- Maximum attempts: three
- Timer: none in V1
- Failed attempt: retried while attempts remain
- Passed Quiz: no new attempt by default
- Administrator reset: allowed only through an audited support action
- Explanations: shown only after submission

The server calculates score, pass state, and points.

A browser cannot submit an authoritative score or pass result.

## 12. Certificates

A certificate is issued after verified Course completion.

A certificate contains:

- Student
- Course
- Enrollment
- Student name snapshot
- Course title snapshot
- Completion date
- Unique certificate code
- Issued timestamp
- Issuing actor or system marker
- Status

Certificate states:

- `issued`
- `revoked`

Certificate access is authenticated.

The certificate owner and authorized Administrators can view relevant records.

V1 does not include public certificate verification.

An Administrator can revoke a certificate with a reason and timestamp.

A reissue creates a new record linked to the revoked certificate and requires a fresh eligibility check.

Final institution wording remains deferred.

## 13. Dashboards and reports

All three roles use one shared application shell.

Only navigation and content change by role.

### Student dashboard

The Student dashboard focuses on learning:

- Welcome
- Continue Learning
- My Courses
- Course Progress
- Recent Assessments
- Recent Activity
- Certificates
- Payment History

### Instructor dashboard

The Instructor dashboard focuses on teaching:

- My Courses
- Enrollment counts
- Recent Student activity
- Student progress
- Assessment results
- Course management actions

### Administrator dashboard

The Administrator dashboard focuses on system operation:

- User counts
- Course counts
- Enrollment counts
- Payment summary
- Recent activity
- Operational reports

V1 reports include real counts, tables, statuses, and simple course-level progress.

V1 does not include advanced business intelligence, predictive analytics, or fabricated sample metrics.

## 14. User experience requirements

The interface must support:

- Desktop
- Tablet
- Mobile

Every major data page must handle:

- Loading
- Empty
- Error
- Success

Forms must include:

- Visible labels
- Validation
- Safe error messages
- Pending feedback
- Keyboard access
- Visible focus states

The interface must meet WCAG AA contrast guidance.

Status must never rely on color alone.

The interface uses simple English.

HyperUI is an approved Tailwind CSS reference for forms, content sections, authentication layouts, empty states, and responsive page patterns when the local component set lacks a clear solution.

Reference: <https://github.com/markmead/hyperui>

HyperUI uses the MIT License. No package is required. If markup or code is copied, preserve the required copyright and license notice in the project documentation.

Adapted HyperUI patterns must use the LMS academic identity, accessible states, approved content, and responsive behavior.

## 15. Philippine context

- Interface language: English
- Currency: Philippine Peso
- Example format: `₱499.00`
- Payment provider: PayMongo
- Course examples: BSIT and related academic subjects

Dollar pricing must not appear in the application.

## 16. Security requirements

The application must:

- Hash passwords through Laravel authentication
- Regenerate sessions after login
- Generate temporary bootstrap passwords with a secure random source
- Store temporary bootstrap passwords with Windows DPAPI outside the repository
- Require a password change before normal authenticated access when the profile flag is set
- Protect forms with CSRF tokens
- Use secure session cookies
- Rate-limit login and sensitive endpoints
- Validate every request
- Escape Blade output by default
- Use Policies for protected resources
- Check authorization inside controllers, actions, jobs, and webhook handlers
- Use database transactions for protected state changes
- Use generated private file paths
- Validate file type, size, and safe filename
- Allow external resource links only through approved HTTPS rules
- Never fetch a user-supplied URL on the server without an explicit host policy
- Verify webhook authenticity with the provider signature instead of browser CSRF protection
- Make payment handling idempotent
- Keep secrets in environment variables
- Keep `APP_DEBUG=false` in production
- Log important business and administrative actions
- Never log passwords, session tokens, PayMongo secrets, raw sensitive payment data, or answer keys
- Never expose stack traces or database errors to normal users

UI visibility is not authorization.

A hidden link or JavaScript check never protects a route or record.

## 17. V1 exclusions

V1 does not include:

- Assignments
- Assignment submissions
- Submission grading
- Gradebook
- Announcements
- Notifications
- Forums
- Discussions
- Live classes
- Direct messaging
- Public certificate verification
- Public dashboards
- Direct video uploads
- Multiple course owners
- Ownership transfer
- Multi-role accounts
- Advanced analytics

New domains require approved requirements and documentation changes.

## 18. Acceptance criteria

V1 succeeds when the following workflows pass automated and manual verification:

### Authentication

- Public registration creates a Student
- Login and logout work
- Invalid credentials fail safely
- Password reset works
- Email verification works for public registrations
- Suspended accounts cannot sign in
- The initial Administrator is provisioned through a local-only command
- A temporary Administrator password must change after first sign-in
- Passwords require at least 12 characters and confirmation
- A User can view and edit only their own name and bio
- Email and role cannot be changed through the profile form

### Authorization

- Students cannot access Administrator or Instructor actions
- A Student cannot read another Student's private data
- An Instructor cannot edit another Instructor's Course
- An Administrator can search and filter verified users
- An Administrator can assign one approved role to another user
- An Administrator can suspend and reactivate an account
- A user cannot change their own role or account status
- The final active Administrator cannot be demoted or suspended
- Every role and status change creates an activity record
- Role and status changes succeed or fail in one transaction
- Every protected action checks a Policy
- Role-specific pages contain no fabricated business data

### Courses and enrollment

- Published Course catalog works
- Draft Courses stay private
- Free enrollment creates one active enrollment
- Duplicate enrollment requests do not create duplicates
- Paid enrollment remains pending before verified payment
- Unpublishing blocks new enrollment and preserves existing access

### Learning

- Authorized Lesson access works
- Unauthorized Lesson access fails
- Mark as Complete updates persisted progress
- Progress is calculated from database records
- Private file access requires authorization

### Quizzes

- Answer keys remain hidden before submission
- Three-attempt limit works under repeated requests
- Failed attempts can be retried
- Passing blocks new attempts by default
- Scores and pass state are calculated on the server

### Certificates

- Eligible completion creates one certificate
- Ineligible completion creates none
- Repeated issuance does not create duplicates
- Revocation and reissue remain audited

### Payments

- Amount comes from the database
- Invalid or repeated webhook events do not grant duplicate access
- Browser redirects never activate paid enrollment
- Failed and cancelled payments retain safe retry behavior

### User experience

- Light and dark themes work
- Mobile navigation works
- Lesson and Quiz workflows work on mobile
- Keyboard and focus behavior pass
- Loading, empty, error, and success states work

## 19. Development lifecycle

Every feature and project phase must follow this cycle:

```text
Data gathering
→ Create or update ERD and flowcharts
→ Develop
→ Build
→ Test
→ Find bugs
→ Fix
→ Test again
```

### 19.1 Data gathering

Before implementation, collect:

- Approved user need
- Roles and permissions
- Input fields and file types
- Business rules
- Data relationships
- External service behavior
- Error and empty states
- Security constraints
- Acceptance checks

### 19.2 ERD and flowcharts

Create or update:

- Entity Relationship Diagram when tables or relationships change
- User flow when page navigation changes
- Process flowchart when business logic changes
- Sequence diagram when integrations or transactions change
- Input, process, and output list for the feature

No implementation begins while required diagrams remain missing.

### 19.3 Develop, build, test, fix, and retest

1. Develop the smallest approved slice.
2. Build the application.
3. Run focused and full tests.
4. Record bugs and failed checks.
5. Fix each confirmed bug.
6. Rebuild.
7. Test again.
8. Repeat until checks pass.
9. Request human review before advancing.

### 19.4 Input, process, and output

Document every feature as:

```text
Input → Process → Output
```

**Input**

- Form fields
- Route parameters
- Uploaded files
- Authentication session
- External API data
- Signed webhook data

**Process**

- Validation
- Authentication
- Authorization
- Business rules
- Database transactions
- File authorization
- External service calls
- Audit logging

**Output**

- Rendered page
- Redirect
- Validation message
- Authorized file response
- Safe JSON response
- Webhook acknowledgement
- Database record
- Sanitized activity record

A phase is not ready for the next phase until the build, tests, fixes, retests, and human review are complete.

## 20. Academic defense evidence

The team should be able to explain:

- Why Laravel was selected
- How MVC and layered architecture work
- How routes, middleware, controllers, Form Requests, Policies, Actions, Services, and models interact
- How MySQL relationships support LMS workflows
- How authentication differs from authorization
- How enrollment is separate from payment
- Why PayMongo webhooks are required
- How payment events remain idempotent
- How progress and completion are calculated
- How quiz answer keys remain protected
- How certificates are issued and revoked
- How private files receive authorized access
- How testing proves role and payment boundaries
- How the application is deployed

Every major technology must have a reason the team can explain.

## 21. Deferred decisions

The following details are deferred with safe defaults:

| Decision | Safe default |
|---|---|
| Final institution name | Do not display an unverified institution name |
| Exact file-size limits | Reject uploads until approved limits are configured |
| PayMongo methods and events | Check current official documentation before implementation |
| Production hosting | Use local development until deployment is planned |
| Queue driver | Use the simplest configured driver during early development |
| Public certificate verification | Keep certificate access authenticated |
| Payment expiry | Keep enrollment pending until explicit cancellation or verified payment |
| Ownership transfer | Keep one Instructor owner |

## 22. Change control

Changes to product scope require:

1. A written requirement
2. Security and data impact review
3. Update to `plan.md`
4. Update to `architecture.md` when technical behavior changes
5. Update to `design.md` when user behavior changes
6. User approval
7. A new test plan

Do not add a feature only because a reference repository contains it.
