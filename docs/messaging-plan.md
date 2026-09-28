# Messaging, Notifications, Announcements, and Automation: implementation plan

Status: **approved 27 September 2026, ready to implement from slice 1.**
Source specification: `docs/Messaging, Notifications, Announcements, and Automation system.md`
Plan date: September 27, 2026
Scope approval: `docs/plan.md` section 17.1
Build order: `docs/development-roadmap.md` section 34.1

**Decisions taken at review.** All five were confirmed rather than assumed.

| Decision | Outcome |
| --- | --- |
| 1, plan.md section 17 | Amended. Notifications, announcements and course-scoped messaging are in scope as a V2 addition, with the boundaries recorded in section 17.1 |
| 2, assignments | Deferred. Not built, and their notification types are not created. See plan section 0.2 |
| 3, delivery channels | In-app only, synchronous, no queue dependency. See plan section 0.3 |
| 4, announcement scheduling | Deferred. Announcements publish on creation. See plan section 0.3 |
| 5, first slice | Slice 1 alone, then stop and review before slice 2 |

**Reference convention.** A bare "section N" means a section of the source
specification, because the whole plan is organised around answering it. A
reference to this document is written "plan section N" or uses its own
subsection numbering, such as "4.4" in plan section 12. `docs/plan.md`,
`docs/deployment.md`, and `AGENTS.md` are always named in full.

---

## 0. Approval gate, settled

**Settled 27 September 2026.** Section 0.1 records the conflict that was found,
section 0.2 the scoping decision, and section 0.3 three further decisions. All
five were confirmed at review and are now recorded in `docs/plan.md` section
17.1. The subsections are kept rather than deleted, because a reader who arrives
without that context needs to know the exclusion list was deliberately changed
rather than overlooked.

### 0.1 This feature contradicted an approved document

`docs/plan.md` section 17, "V1 exclusions", lists as excluded from V1:

| plan.md line | Excluded item | This specification asks for it |
| --- | --- | --- |
| L888 | Assignments | yes, throughout |
| L889 | Assignment submissions | yes |
| L890 | Submission grading | yes, specification sections 11 and 12 |
| L892 | Announcements | yes, specification section 5 |
| L893 | Notifications | yes, specification sections 2, 3, 6, 10, 13 |
| L897 | Direct messaging | yes, specification sections 1, 7, 8 |

`docs/plan.md` L906 states: "New domains require approved requirements and
documentation changes."

`AGENTS.md` states: "Do not invent assignments, grades, submissions,
announcements, notifications, forums, or live classes."

The supplied specification is exactly the approved requirements that L906 asks
for, so the correct reading is that this is a **V2 domain addition** and
`plan.md` section 17 must be amended rather than ignored. This plan therefore read that requirement document as a **V2 domain
addition** and asked for the amendment rather than assuming it.

**Decision 1, taken:** `docs/plan.md` section 17 is amended, and section 17.1
records notifications, announcements and course-scoped messaging as in scope
with their boundaries. Assignments stay excluded. See 0.2.

### 0.2 Assignments are out of scope, and the specification agrees

Sections 2, 10, 11 and 12 of the specification assume an assignment and manual
grading domain. This application has none of it:

- No `assignments`, `submissions`, or `gradebook` tables exist. The complete
  schema is 25 tables and none of them is one of those.
- `plan.md` L888 to L890 exclude all three.
- `AGENTS.md` forbids inventing them.

The specification's own section 16 provides the exit: "Only create types that
actually fit the application's functionality." So the assignment-dependent
requirements are dropped rather than half-built:

| Specification requirement | Disposition |
| --- | --- |
| 2: assignment submission notifications | dropped, no assignment domain |
| 10: assignment events | dropped |
| 11: automated grading of assignments | dropped, quiz grading already exists and is reused |
| 12: instructor grading automation | dropped, no manual grading exists |
| 16: `ASSIGNMENT_SUBMITTED`, `ASSIGNMENT_GRADED` types | dropped |

Grading is **not** dropped. Quizzes are automatically graded already, by
`App\Actions\Quizzes\SubmitQuizAttempt`. That existing path is the source of the
pass, fail and retake notifications, so specification section 11 is satisfied for the domain
that actually exists.

**Decision 2, taken:** assignments are deferred and not built.

### 0.3 Three further decisions that change the shape of the work

**Decision 3, taken: no email.** The specification describes an entirely in-app
system: a notification centre, a messaging UI, and announcements on dashboards.
It never asks for email. Adding mail would be infrastructure the specification
does not require, against `plan.md` security rule 23 and the specification's own
section 14 instruction not to introduce unnecessary infrastructure. Everything
below is a database row read by a page.

**Decision 4, taken: scheduled publishing is deferred.** Specification section 5 lists
"Scheduled maintenance" and "Downtime" as examples of announcement *content*, not
as a requirement to publish later. Announcements publish immediately. A
scheduled-publish feature needs a content state, a publish job, and a scheduler
entry, and it is not asked for. If it is wanted, it is a separate slice and it is
called out in plan section 12 below.

**Decision 5, taken: synchronous writes now, one seam for later.** `docs/deployment.md`
section 9 does schedule `queue:work`, so background work is legitimate
infrastructure here. But the same section states that "a webhook settles a
payment inside the request, so a stopped queue worker" is survivable, and the
project deliberately keeps correctness off the worker. Notification writes
therefore happen in the request, through one service, so that moving to a queue
later is a change in one file rather than a change in twenty.

---

## 1. Assumptions

These are the things I believe to be true and cannot verify from the repository
alone. Correct me before implementation.

1. The V1 exclusion list in `plan.md` is amended, not bypassed. See 0.1.
2. Roles stay exactly three: `student`, `instructor`, `administrator`. No new
   role is introduced, so no multi-role work is needed.
3. A course keeps exactly one instructor. `plan.md` L901 excludes multiple
   course owners, so conversation participants are derived, not assigned.
4. The existing `CourseCompletionChecker` stays the single source of truth for
   completion and certificate eligibility. No second implementation.
5. `LessonPolicy::viewForStudent` stays the single source of truth for "may
   this student read this content", and a notification fan-out reproduces that
   composite rather than a part of it. Plan section 4.4 records what a probe
   measured about this, including why the obvious half of the rule is not enough.
6. No outbound email, no web push, no SMS.
7. The design direction in `docs/design.md` applies unchanged. The icon override
   already recorded for this project (Lucide, user approved) still applies.
8. A notification is never required for correctness. If the notification write
   fails, the learning action that caused it still stands.

---

## 2. What already exists, and what is greenfield

Recorded so the plan builds on the real code rather than on an assumption about
it.

### Reused, not rebuilt

| Existing | Where | Used for |
| --- | --- | --- |
| `LessonPolicy::viewForStudent` | `app/Policies/LessonPolicy.php` | the composite rule a fan-out must reproduce: active student, published content, active or completed enrollment |
| `StudentCourseAccess::allows()` | `app/Support/StudentCourseAccess.php` | the enrollment half of that composite. Measured at one query per call, and measured to ignore account status, which is why the fan-out uses the policy instead. Plan section 4.4 |
| `CourseCompletionChecker` | `app/Services/Learning/CourseCompletionChecker.php` | specification sections 4 and 11: eligibility, and the human readable `reasons` array for "what remains" |
| `CourseRequirement` | `app/Models/CourseRequirement.php` | specification section 11: "use the actual course configuration" |
| `ProgressCalculator` | `app/Services/ProgressCalculator.php` | specification section 4: recalculation, already batched |
| `Action\Learning\RecordLessonActivity` | `app/Actions/Learning/RecordLessonActivity.php` | the precedent for activity versus state change, and the hook for `LESSON_STARTED` |
| `Action\Learning\MarkLessonComplete` | `app/Actions/Learning/MarkLessonComplete.php` | `LESSON_COMPLETED`, and the completion recheck |
| `Action\Quizzes\StartQuizAttempt` / `SubmitQuizAttempt` | `app/Actions/Quizzes/` | all quiz notifications, and pass, fail and retake |
| `Action\Completion\CompleteCourse` | `app/Actions/Completion/CompleteCourse.php` | `COURSE_COMPLETED` and `CERTIFICATE_AVAILABLE`, both already idempotent |
| `Action\Courses\PublishCourse`, `Create*` curriculum actions | `app/Actions/Courses/` | `COURSE_CONTENT_PUBLISHED` |
| 24 Blade components | `resources/views/components/` | the entire UI vocabulary, including `activity-agenda`, `badge`, `empty-state`, `error-state`, `skeleton`, `page-header`, `form-field` |
| `x-icon` plus `config/icons.php` and `icons:sync` | `resources/icons/` | notification type icons, added through the existing pipeline |
| `x-activity-agenda` | `resources/views/components/activity-agenda.blade.php` | the notification list shape, including its day grouping |
| `Navigation::for()` | `app/Support/Navigation.php` | adding the notification centre and messages to the sidebar |
| `ThrottleWrites` | `app/Http/Middleware/ThrottleWrites.php` | write ceilings on message send and announcement create, for free |
| `jobs`, `job_batches`, `failed_jobs` tables | `database/migrations/` | already provisioned, unused until Decision 4 changes |

### Greenfield

Nothing below exists. Verified: the 25-table schema contains no notification,
message, announcement, or conversation table, and `app/` has no `Events`,
`Listeners`, `Jobs`, `Notifications`, or `Observers` directory. A repository-wide
search for notification, announcement, and conversation returns nothing.

---

## 3. Capability map

Phase 0 of the spec-driven workflow, because this specification bundles several
independently testable capabilities. The map is the gate: review these module
boundaries, the dependency direction, and the build order before any module is
specified in detail.

| Module id | Responsibility | Depends on |
| --- | --- | --- |
| `notification-core` | the type vocabulary, storage, dedup key, read state, and the single write seam | none |
| `domain-events` | event classes, and the dispatch points inside the existing Actions | none |
| `automation` | listeners that turn events into notifications, and the spam rules | `notification-core`, `domain-events` |
| `conversations` | shared thread and message storage, and thread authorization | `notification-core` |
| `course-messaging` | enrollment-scoped student and instructor threads, and their policy | `conversations`, `domain-events` |
| `support-threads` | user and administrator report threads, and their policy | `conversations`, `domain-events` |
| `announcements` | course and platform announcements, and their fan-out | `notification-core`, `automation` |
| `notification-centre` | badge, list, mark read, mark all read | `notification-core` |
| `messaging-ui` | thread list, thread view, composer | `course-messaging`, `support-threads` |
| `dashboard-integration` | surfacing all of the above on the three dashboards | all of the above |

### Why these boundaries

- `notification-core` is separate from `domain-events` because the storage and
  the dedup rule are needed by announcements, by messaging, and by automation
  alike. Folding them together would make the messaging feature depend on the
  curriculum Actions, which it has no reason to.
- `conversations` is separate from `course-messaging` and `support-threads`
  because specification sections 1 and 5 describe the same shape of thing with
  different authorization. One set of tables, two policies. Building them twice
  would be the duplicate system `plan.md` and specification section 20 both
  forbid.
- `notification-centre` is separate from `automation` so the UI can be built and
  tested against seeded rows, with no event pipeline running.
- `dashboard-integration` is last because every module it touches is owned by an
  earlier module. Doing it last keeps the earlier diffs reviewable.

### Build order

```
notification-core
  -> domain-events
       -> automation
       -> course-messaging -> support-threads
  -> announcements
  -> notification-centre
  -> messaging-ui
  -> dashboard-integration
```

`notification-core` and `domain-events` are independent of each other and can be
built in parallel. Everything after that is sequential along the arrow.

---

## 4. Cross-cutting decisions

### 4.1 Dispatch after commit, never inside

An event is dispatched **after** the transaction that caused it commits. If the
transaction rolls back, no notification is written.

This is the single most important rule in the plan. A notification for a payment
that then failed, or a certificate that was revoked in the same transaction,
would be a lie the student sees.

Laravel's `->afterCommit()` on the event dispatch does this. It is not optional
and it is asserted by test.

### 4.2 One write seam

Every notification in the system is written through one method. That method
takes the recipient, the type, and an optional dedup key, and does three things:
resolve the recipient's access, check the dedup key, insert.

Because there is exactly one place that inserts, a later move to a queue is one
file. Because the dedup check lives beside the insert, a new notification type
cannot forget it.

### 4.3 Idempotency is a unique index, not a check

The house pattern is already set. `payment_events` has a unique
`provider_event_id`. `lesson_progress` has a unique
`(enrollment_id, lesson_id)`. `enrollments` has a unique
`(student_id, course_id)`.

So: a `dedup_key` column on `notifications`, with a unique index on
`(user_id, dedup_key)`. MySQL permits many NULLs in a unique index, so a
notification that does not need a dedup key simply leaves it null and is never
suppressed.

This makes idempotency a property of the database rather than of the code path,
which is what specification section 18 asks for and what the QA pass in
`docs/qa-session-log.md` found to be the only reliable form.

### 4.4 Access is resolved by the recipient query, not per recipient

The rule a fan-out must reproduce is the one a student's content page already
applies, and it is a **composite** in `LessonPolicy::viewForStudent`:

1. the account is an active student, by role and by account status,
2. the lesson and its module are published,
3. the student holds an active or completed enrollment in the course, which is
   what `StudentCourseAccess::allows()` checks.

Measured rather than assumed, by `tools/verify-fanout-query.php` over 120
students: `StudentCourseAccess::allows()` costs **one query per student**, so a
fan-out across a course's students is an N+1 that would fail
`DashboardQueryBudgetTest`, which exists precisely to fail per-row queries. One
query is achievable, so the saving is roughly two orders of magnitude.

The same probe produced a correction that changes this design.
`allows()` reads **enrollment status and nothing else**. It checks neither role
nor account status: of the 96 students it allowed, 13 were suspended accounts
holding an active enrollment, and `allows()` said yes to every one of them. A
fan-out written against `allows()` alone would notify suspended accounts, and
the notification link would land on a page that refuses them.

So the batched sibling belongs on the **policy**, not on the support class. It is
one query returning the student ids that satisfy the whole composite for a
course, and the per-student `viewForStudent` stays unchanged for the single
check. A test asserts the two agree on a data set containing every exclusion:
pending payment, cancelled, suspended, wrong role, and unpublished content.

This is the only change to an existing class anywhere in the plan. It is
load-bearing for correctness and for the query budget, so it is its own slice.

### 4.5 Authorized means authorized

A notification is written only for a recipient who would actually be allowed to
open the destination. This is enforced in the write seam, using the same
predicate the destination page uses.

A student who cancels an enrollment between the publish and the fan-out must not
receive a notification whose link returns 403. A suspended account receives
nothing. The two rules together are the answer to specification section 3's
"only notify students who are currently enrolled and authorized" and section 9's
"access course content after their authorization or enrollment has ended".

### 4.6 Conversations are provisioned on first message

Specification section 8 asks for course communication to become available on
enrollment, and then explicitly says: "do not automatically create empty message
records if the architecture can instead establish the communication relationship
when the first message is sent."

So no rows are written on enrollment. Both sides derive the same thread from a
deterministic key, and the first message creates the thread.

The risk is a race: a student and an instructor both message each other for the
first time simultaneously, and two threads appear. The fix is a unique index on
the natural key, and the loser of the race retries against the winner's row. This
is the same shape as the `lesson_progress` race that `RecordLessonActivity` was
rebuilt to survive, and the test asserts it.

### 4.7 Announcement read state is the notification read state

Specification section 5 wants a read or unread state per announcement.
Specification section 20 says do not create a duplicate system for functionality
that already exists.

So there is no `announcement_reads` table. Each student's announcement is a
notification row, and its `read_at` is the read state. One mechanism, one place
to mark read, one index to query.

### 4.8 The activity events are transition events

Specification section 17 is emphatic: opening the same lesson five times must
not produce five notifications. The existing `RecordLessonActivity` already
guarantees the only place a lesson start can happen is the single
`not_started` to `in_progress` update, and that update cannot fire twice for the
same row.

So `LESSON_STARTED` is dispatched **only** when that update reports one affected
row, plus a dedup key on `(enrollment, lesson)`. Repeated views cannot produce a
notification, not because of a counter or a time window, but because there is no
second state change to hang it on. That is the difference between a rule and a
heuristic.

---

## 5. Data model

Five tables. All names are singular and match the existing convention
(`enrollments`, `lesson_progress`, `course_requirements`).

### 5.1 `notifications`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint pk | |
| `user_id` | FK users, cascade | the recipient |
| `type` | string(48) | a `NotificationType` value |
| `title` | string(160) | cleared at display, so it survives a rename |
| `body` | text null | one or two sentences |
| `course_id` | FK courses, null on delete | null for platform-wide items |
| `subject_type` | string null | the thing this is about |
| `subject_id` | bigint null | a lesson, quiz, certificate, conversation or announcement |
| `link` | string null | the destination path, resolved and authorized at send time |
| `dedup_key` | string(120) null | see 4.3 |
| `read_at` | timestamp null | null is unread |
| `created_at`, `updated_at` | timestamp | |

Indexes, and why each exists:

- `unique (user_id, dedup_key)`: idempotency. Section 4.3.
- `index (user_id, id)`: the notification centre list and its pagination.
- `index (user_id, read_at)`: the unread badge, which counts rather than lists.
- `index (course_id)`: per-course filtering if a course page ever shows its own
  notices.

The badge count is the reason this is a `count(*)` and not a loaded collection.
A student with 4,000 notifications must never load 4,000 rows to draw a badge.

### 5.2 `conversations`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint pk | |
| `kind` | string(16) | `course` or `support` |
| `course_id` | FK courses, cascade | set for `course` kind, null for `support` |
| `subject` | string(160) null | the support report title |
| `requester_id` | FK users, restrict | who opened the thread |
| `status` | string(16) | `open` or `closed` |
| `last_message_at` | timestamp null | the list ordering key, so it does not need a join |
| `created_at`, `updated_at` | timestamp | |

Indexes:

- `thread_key` and `unique (kind, thread_key)`: the deterministic thread key. This
  is what makes 4.6 race-safe.
- `index (requester_id, last_message_at)`: the requester's own list.
- `index (course_id, kind)`: an instructor's per-course list.

**Amendment, 28 September 2026.** This originally read
`unique (kind, course_id, requester_id)`, and it was wrong. A course thread
belongs to a *pair* of people about a course, not to whoever opened it, so when a
student and an instructor both reach out about the same course the two rows
differ in `requester_id`, both pass the index, and the pair ends up with two
threads. The test that pins one thread per pair is what caught it.

`thread_key` is now that pair, written in a fixed order so the string does not
depend on who opened the thread: `course:` followed by the two user ids sorted
numerically. A support thread has no pair, because every raise is a new
conversation, so it gets a fresh UUID and the index never has anything to refuse.
The first attempt keyed support threads on the requester, which collapsed two
separate problems into one thread and buried the first.

`status` exists because specification section 1 asks for trackable support
conversations with a visible status. Course threads stay `open`; only support
threads are closed, and only by an administrator.

### 5.3 `conversation_participants`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint pk | |
| `conversation_id` | FK conversations, cascade | |
| `user_id` | FK users, cascade | |
| `last_read_at` | timestamp null | when this person last looked, for display |
| `last_read_message_id` | bigint null | the read boundary the unread count is measured from |
| `archived_at` | timestamp null | hides a settled thread without deleting it |
| `created_at`, `updated_at` | timestamp | |

Indexes: `unique (conversation_id, user_id)`, `index (user_id, archived_at)` and
`index (conversation_id, last_read_message_id)`.

Unread count per thread is `messages.id > last_read_message_id`, which is one
aggregate over an indexed column and needs no counter column that could drift.

**Amendment, 28 September 2026.** This originally read
`messages.created_at > last_read_at`. It does not work, because every timestamp
column in this database is a plain MySQL `timestamp` with no fractional
precision, so a message posted in the same second as somebody read the thread
compares as not newer and is silently dropped from the unread count. That is
exactly when people are messaging back and forth. `last_read_message_id` replaces
the comparison: a bigint id is monotonic and has no such gap, so the boundary is
exact. `last_read_at` stays because a person wants to know when they last looked,
and that is a question about time rather than about position. Nothing counts
against it.

### 5.4 `conversation_messages`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint pk | |
| `conversation_id` | FK conversations, cascade | |
| `author_id` | FK users, restrict | restrict, not cascade: a deleted account must not erase the record of what was said |
| `body` | text | |
| `client_token` | uuid null | see below |
| `created_at`, `updated_at` | timestamp | |

Indexes: `index (conversation_id, id)` for chronological paging, and
`unique (conversation_id, author_id, client_token)`.

`client_token` is a UUID the composer puts in a hidden field. A double click, a
refresh, or a browser retry sends the same token, and the unique index drops the
second insert. This answers specification section 18's "duplicate messages" at
the source rather than with a check that can be raced.

### 5.5 `announcements`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint pk | |
| `scope` | string(16) | `course` or `platform` |
| `course_id` | FK courses, cascade | set for `course` scope |
| `author_id` | FK users, restrict | |
| `title` | string(160) | |
| `body` | text | |
| `published_at` | timestamp | the shown date and time |

Indexes: `index (scope, course_id, published_at)` for a course page,
`index (published_at)` for the platform list, and
`index (author_id, published_at)` for "my announcements".

No `read_at`, no `archived_at`, no draft state. Section 4.7 covers read state
and Decision 4 covers scheduling. A draft announcement is a future slice.

### 5.6 Enums

`NotificationType` and `ConversationKind` follow the existing enum convention
(`app/Enums/`, backed string values, used with `Rule::enum` in requests and casts
on models).

---

## 6. The notification type vocabulary

Specification section 16 lists 22 type names, of which 21 are unique because
`LESSON_COMPLETED` appears twice. Six of the 21 cannot be produced by this
application, and section 16's own rule, "Only create types that actually fit the
application's functionality", is the authority for dropping them.

| Spec type | Kept | Reason |
| --- | --- | --- |
| `COURSE_ENROLLMENT` | yes | `EnrollStudent` |
| `COURSE_CONTENT_PUBLISHED` | yes | `PublishCourse` and the curriculum create actions |
| `COURSE_ACTIVITY` | **no** | too vague to render an icon or a destination for; it is a category, not an event |
| `LESSON_STARTED` | yes | `RecordLessonActivity` transition |
| `LESSON_COMPLETED` | yes | `MarkLessonComplete` |
| `QUIZ_STARTED` | yes | `StartQuizAttempt` |
| `QUIZ_COMPLETED` | yes | `SubmitQuizAttempt` |
| `QUIZ_PASSED` | yes | `SubmitQuizAttempt` |
| `QUIZ_FAILED` | yes | `SubmitQuizAttempt` |
| `ASSIGNMENT_SUBMITTED` | **no** | no assignment domain, section 0.2 |
| `ASSIGNMENT_GRADED` | **no** | no assignment domain, section 0.2 |
| `MODULE_COMPLETED` | **no** | completion is course-level; there is no module completion state to announce |
| `COURSE_COMPLETED` | yes | `CompleteCourse` |
| `CERTIFICATE_ELIGIBLE` | **merged** | `CompleteCourse` issues the certificate in the same transaction as completion, so "eligible" is never a separately observable state and would be a notification about something that has not happened yet |
| `CERTIFICATE_AVAILABLE` | yes | the merged type, and the one that matches reality |
| `RETAKE_REQUIRED` | yes | a failed quiz with attempts remaining |
| `ANNOUNCEMENT` | yes | course scope. Corrected 28 September 2026: it was listed as platform scope in slice 1, which made the seam refuse to store the notice for a course announcement |
| `NEW_MESSAGE` | **split** | see the amendment below |
| `SUPPORT_REPLY` | yes | an administrator reply on a support thread, and an administrator picking a request up |
| `SYSTEM_MAINTENANCE` | **no** | needs scheduled publishing, Decision 4 |
| `SYSTEM_ANNOUNCEMENT` | yes | platform scope |

Added, because the application can produce them and the specification's list is
explicitly illustrative rather than closed:

| Type | Fires from |
| --- | --- |
| `CERTIFICATE_REVOKED` | `RevokeCertificate`. A revoked certificate is a withdrawal, and the student who earned one is entitled to know. |
| `CERTIFICATE_REISSUED` | `ReissueCertificate`, so the replacement is findable and the old link explains itself. |

Final vocabulary: 15 kept from the specification, 2 added, and `NEW_MESSAGE`
replaced by two, so **18 types**.

**Amendment, 28 September 2026.** `NEW_MESSAGE` became `COURSE_MESSAGE` and
`SUPPORT_MESSAGE`, and `SUPPORT_REPLY` is no longer course scoped. The write seam
checks that a course scoped notice carries a course and a platform one does not,
so that no row can be written that no filter can classify — a rule worth keeping.
But a course thread has a course and a support thread has none, and that check is
a property of the *type*, so a single message type had to be scoped or unscoped
and either way made one kind of thread unwritable. With one type, posting into a
support thread raised `support_message belongs to a course, so a course is
required` from the seam, and the notice a person is waiting for could not be
recorded. The seam was working; the vocabulary was wrong.

---

## 7. The automation matrix

This is the heart of the specification and the part most likely to be got wrong,
so it is written out in full. Every row is a real existing Action.

| Event | Dispatched from | Trigger condition | Recipients | Type | Dedup key |
| --- | --- | --- | --- | --- | --- |
| `StudentEnrolled` | `EnrollStudent` | enrollment created, after commit | the instructor | `COURSE_ENROLLMENT` | `enrollment:{id}` |
| `LessonStarted` | `RecordLessonActivity` | **only** when the not-started update reports one affected row | the instructor | `LESSON_STARTED` | `enrollment:{id}:lesson:{id}:lesson_started` |
| `LessonCompleted` | `MarkLessonComplete` | **only** when the status changed to completed | the instructor | `LESSON_COMPLETED` | `enrollment:{id}:lesson:{id}:lesson_completed` |
| `QuizStarted` | `StartQuizAttempt` | attempt row created | the instructor | `QUIZ_STARTED` | `attempt:{id}:quiz_started` |
| `QuizCompleted` | `SubmitQuizAttempt` | attempt graded, first time only | the instructor | `QUIZ_COMPLETED` | `attempt:{id}:quiz_completed` |
| `QuizPassed` | `SubmitQuizAttempt` | graded and passed | the student | `QUIZ_PASSED` | `attempt:{id}` |
| `QuizFailed` | `SubmitQuizAttempt` | graded and failed | the student | `QUIZ_FAILED` | `attempt:{id}` |
| `RetakeRequired` | `SubmitQuizAttempt` | failed **and** the student's used attempts are below `quizzes.max_attempts` | the student | `RETAKE_REQUIRED` | `attempt:{id}` |
| `CourseCompleted` | `CompleteCourse` | when the action reports `created: true` | the student | `COURSE_COMPLETED` | `enrollment:{id}` |
| `CertificateIssued` | `CompleteCourse` | when the action reports `created: true` | the student | `CERTIFICATE_AVAILABLE` | `certificate:{id}` |
| `CertificateRevoked` | `RevokeCertificate` | status changed to revoked | the student | `CERTIFICATE_REVOKED` | `certificate:{id}:revoked` |
| `CertificateReissued` | `ReissueCertificate` | replacement created | the student | `CERTIFICATE_REISSUED` | `certificate:{id}:reissued` |
| `ContentPublished` | `PublishCourse` | transition to published, which publishes the course and its modules and lessons together | currently enrolled and authorized students | `COURSE_CONTENT_PUBLISHED` | `course:{id}:published` |
| `MessageSent` | conversation message create | always | other participants, and the author for their own unread | `COURSE_MESSAGE` or `SUPPORT_MESSAGE`, chosen by the thread kind | `message:{id}:to:{id}` |
| `SupportReplied` | conversation message create on a support thread | author is an administrator | the requester | `SUPPORT_REPLY` | `message:{id}:user:{id}` |
| `CourseAnnouncementPublished` | announcement create, course scope | always | currently enrolled and authorized students | `ANNOUNCEMENT` | `announcement:{id}:user:{id}` |
| `PlatformAnnouncementPublished` | announcement create, platform scope | always | all active accounts, students and administrators | `SYSTEM_ANNOUNCEMENT` | `announcement:{id}:user:{id}` |

### 7.1 The rows that carry the specification's hardest requirements

**Section 3, "avoid duplicate notifications when the instructor simply edits
existing content."** Handled structurally, not by a rule. The specification's own
distinction between new content, an ordinary edit, and an unpublished item maps
onto three existing Actions: `Create*`, `Update*`, and `ArchiveContent`.

**Amendment, 28 September 2026.** This originally said the dispatch came from
`CreateLesson`, `CreateModule` and `CreateLearningMaterial` and never from the
`Update*` actions. The intent is right and the location is not, and the reason is
worth recording because it is the kind of thing a plan can be confidently wrong
about.

All three Create actions write a **draft**. A draft lesson fails the published
half of `LessonPolicy::viewForStudent`, so a notice about one carries a link that
answers 403. Worse, in this application **no action publishes an individual
lesson at all**: `UpdateLesson` deliberately does not touch `status`, and
`PublishCourse` is the only thing that moves content to published. So dispatching
at creation would announce something permanently invisible, and would never
announce the moment content actually appears.

The dispatch therefore comes from `PublishCourse`, which is where a course, its
modules and its lessons all become visible together, and it fires once because
the key names the course. "An edit is not announced" still holds structurally: the
`Update*` actions have no dispatch and there is nothing to misconfigure. The
event also carries a subject and a status, so a course that later gains a
published subject announces that subject separately under its own key.

**Section 3, "not unenrolled, removed, expired, or unauthorized users."**
Handled in the write seam, which calls the batched access query once per
publication and filters the recipient set before any row is written. A student
whose enrollment was cancelled one millisecond earlier is not in the set.

**Section 17, no spam.** Three separate mechanisms, because one is not enough:
transition-only dispatch for activity, a dedup key for state, and recipient
grouping in the interface so an instructor sees "12 students started Module 2"
rather than 12 rows.

**Section 13 and 18, no duplicates on retry.** The unique index on
`(user_id, dedup_key)`. A retried request re-runs the listener, the insert is
dropped by the database, and the Action that caused it is unaffected because it
already committed.

Note that the quiz rows have a second, already-existing line of defence:
`SubmitQuizAttempt` takes `lockForUpdate()` on the attempt row and refuses a
status that is no longer `in_progress`, so a double submit is already rejected
before any listener runs. The dedup key is therefore a backstop rather than the
primary guard there, and the test asserts both.

**Section 11, "do not hardcode arbitrary passing requirements."** The
`RETAKE_REQUIRED` rule reads `quizzes.max_attempts`, which is stored per quiz
with a range of 1 to 3 and a default of 3, and is already enforced by
`StartQuizAttempt`. Pass or fail comes from the graded answers against the
configured rules, not from a constant in a notification.

### 7.2 The one place this matrix is deliberately incomplete

`MessageSent` notifies "other participants, and the author for their own unread".
In practice the author's own unread is tracked by
`conversation_participants.last_read_message_id`, not by a notification row, so
the author gets no message row. The matrix says what happens; the implementation
notes that the author is excluded from the fan-out, because the composer already
shows the sent message.

---

## 8. Authorization matrix

Answers specification section 9 directly. Every row is server-side. Nothing
here is enforced by a hidden link.

| Action | Student | Instructor | Administrator |
| --- | --- | --- | --- |
| See a course thread | own, in an enrolled course | own, in a course they own | no, unless they raised it |
| See a support thread | own, ones they raised | no | all |
| Send in a course thread | own, in an enrolled course | own, in a course they own | no |
| Send in a support thread | own, ones they raised | no | all |
| Create a course announcement | no | own courses only | no |
| Create a platform announcement | no | no | yes |
| Mark a notification read | own only | own only | own only |
| Mark all read | own only | own only | own only |
| Close a support thread | no | no | yes |

Two consequences worth stating because they are the mistakes this shape invites:

- **An instructor cannot read a support thread.** Administration section 9 says
  "unless explicitly involved", and no instructor is ever a participant in a
  support thread, so the answer is simply no. The rule is the participant table,
  not a special case in a policy.
- **A student cannot reach an announcement for a course they are not in.** An
  announcement is listed only through an enrollment-scoped query, so a
  hand-typed announcement id does not resolve to a page. Where the id is
  guessable, the policy refuses.

New policies: `ConversationPolicy`, `AnnouncementPolicy`. `NotificationPolicy` is
needed only for the mark-read routes, and it is two ownership checks.

---

## 9. UI plan

Built from the existing 23 components. New components are added only where
nothing existing fits, and each one is named with the reason it could not be an
existing component.

### 9.1 Reused as-is

The 24 existing components in `resources/views/components/`.

`x-page-header`, `x-badge`, `x-empty-state`, `x-error-state`, `x-skeleton`,
`x-form-field`, `x-form-errors`, `x-btn`, `x-icon`, `x-breadcrumbs`, `x-note`,
`x-status`, `x-footer`.

`x-activity-agenda` is the model for the notification list, including its
day-grouped shape. Its own comment records why: "a grid implies a future the
data does not contain", and a day-grouped list "is the shape that works on a
phone without shrinking anything". The notification list uses the same
reasoning and the same grouping, computed in the view so the reader's own day
boundary is what groups it.

### 9.2 New components, with the reason each is new

| Component | Why not an existing one |
| --- | --- |
| `x-notification-item` | an existing component cannot express an unread dot, a type icon, a course, and a relative time as one row. `x-activity-agenda` groups rows but does not make them clickable with a destination. |
| `x-unread-dot` | a 8px state indicator, not a badge. A badge with a number is wrong for "there is something unread in this thread". |
| `x-conversation-row` | an avatar-less identity line plus a preview plus a count. Deliberately not a card, because a list of conversations as a card grid is the social-messenger look section 7 forbids. |
| `x-message-row` | own message and other message are laid out differently and must be distinguishable without a colour-only cue. |

### 9.3 Deliberate design decisions, per antislop R-31

- **Flat list, not cards.** A card grid of conversations is the generic
  messenger look. The thread list is a flat list with hairline row separators,
  matching the flat LMS design in `docs/design.md`.
- **Unread is a weight and a dot, not a colour alone.** Colour-only state fails
  WCAG 1.4.1, so the unread row is bold and carries a dot.
- **No relative timestamps without an absolute one.** Each row carries a
  `datetime` attribute for assistive technology and a `title` for hover, so the
  exact time is always reachable.
- **Type icons come from the existing Lucide pipeline.** Added to
  `config/icons.php` and baked with `icons:sync`. The Lucide override for this
  project is already recorded and is not re-raised.
- **No live chat.** The application is server-rendered with no client framework
  and no `fetch()` anywhere. A messaging UI built as ordinary Blade forms is
  consistent with that and needs no new infrastructure. Polling or websockets are
  explicitly out of scope and recorded in plan section 13.
- **Empty, loading and error states are required** for every list and for the
  composer, per R-27 and specification section 7.

### 9.4 Routes and navigation

Added to `app/Support/Navigation.php` so the sidebar reflects the server's view
of what the account may open, which is the existing mechanism:

- `Notifications` in all three roles, badge count from the unread aggregate.
- `Messages` in all three roles, badge from the per-thread unread aggregate.

`CommunicationController` equivalents, following the existing controller layout:
`NotificationController` (index, read, readAll),
`ConversationController` (index, show, store),
`AnnouncementController` (index, store, destroy for the author).

`Navigation` has an `abilityFor()` map that decides whether a nav item is
offered. The two new items are abilities on the new policies, so the sidebar
cannot show a link the server would refuse. This is the existing mechanism, not
a new one.

---

## 10. Test plan

Follows the charter structure in `docs/qa-session-log.md`, because that file
established what this project considers evidence. Every module ships with tests
before it ships.

### 10.1 Per module

| Module | Tests that must exist before merge |
| --- | --- |
| `notification-core` | dedup key suppresses a repeat; a null dedup key never suppresses; unread count is a `count` and not a load; a recipient with no access receives nothing; a notification for a rolled back transaction does not exist |
| `domain-events` | every dispatch is `afterCommit`; a forced rollback produces no event; the `LessonStarted` transition fires once and not on a repeat view |
| `LessonPolicy` batch | the batched set equals the per-student decision on a data set containing pending payment, cancelled, suspended, wrong role, and unpublished content; one query regardless of student count |
| `automation` | one row of the matrix per type, each asserting recipient, type and dedup key; content edit produces nothing; unpublished content produces nothing; a cancelled enrollment receives nothing at fan-out time |
| `conversations` | duplicate `client_token` produces one message; simultaneous first messages produce one thread; a body is stored escaped, not raw |
| `course-messaging` | a student cannot open another student's thread; a student cannot open an unrelated course's thread; an instructor cannot open a course they do not own; a cancelled enrollment cannot post |
| `support-threads` | a student sees only their own; an instructor is refused; an administrator sees all; only an administrator can close |
| `announcements` | a course announcement reaches only enrolled students; a platform announcement reaches active accounts only; a suspended account receives nothing; read state is the notification's read state |
| `notification-centre` | one heading per page; pagination; mark read affects only the owner; mark all read affects only the owner |
| `messaging-ui` | empty, loading and error states present; no horizontal overflow at 320, 360, 390, 768, 1440; a 500 character message body does not break the thread |
| `dashboard-integration` | each dashboard shows only its own role's items; the badge count matches the list |

### 10.2 Cross-cutting suites

Three new suites, following the existing `tests/Feature/Qa/` convention:

- **`AutomationMatrixTest`** walks every row of plan section 7 as a data provider. One
  row is one test, so a failure names the row.
- **`NotificationFanoutTest`** is the spam and audience suite: one publication to
  many students, verified to reach exactly the authorized set, exactly once, and
  to reach nobody after an enrollment ends.
- **`MessagingAuthorizationTest`** is the IDOR suite: every cell of the plan
  section 8 matrix, signed in as the wrong role and against another account's
  ids.

Plus the two properties that are cheap to get wrong and expensive to discover:

- **`NotificationQueryBudgetTest`** in the style of the existing
  `DashboardQueryBudgetTest`. The unread badge is a bounded number of queries
  regardless of how many notifications exist, and the notification centre list
  does not query per row.
- **`MessagingIdempotencyTest`** sends each message, announcement, and mark-read
  three times and asserts one row each time.

### 10.3 Reuse of the existing QA harness

`tests/Support/HostileInput.php` already exists and is applied to every new text
field: message body, announcement title and body, and the notification copy. The
property is the one already established: no 500, nothing written on a refusal,
and the typed text preserved.

The browser pass in `docs/qa-session-log.md` is re-run against the new pages
before the feature is called done, across all three roles.

---

## 11. Slices

Vertical slices, each independently shippable and independently testable. Each
ends green on the full suite. This is `incremental-implementation` applied to the
build order in plan section 3.

| # | Slice | Ships | Gate |
| --- | --- | --- | --- |
| 1 | `notification-core` | the table, the enum, the write seam, the dedup key, mark read | dedup and access tests green; query budget green |
| 2 | `domain-events` | event classes and the `afterCommit` dispatch points, emitting nothing yet | rollback test green; no behaviour change for any user |
| 3 | `automation`, lessons | `LESSON_STARTED`, `LESSON_COMPLETED` and the completion recheck | matrix rows green; repeat view produces nothing |
| 4 | `automation`, quizzes | `QUIZ_STARTED`, `QUIZ_COMPLETED`, `QUIZ_PASSED`, `QUIZ_FAILED`, `RETAKE_REQUIRED` | matrix rows green; retake rule read from stored configuration, not hardcoded |
| 5 | `automation`, certificates and enrollment | `COURSE_ENROLLMENT`, `COURSE_COMPLETED`, `CERTIFICATE_AVAILABLE`, `CERTIFICATE_REVOKED`, `CERTIFICATE_REISSUED` | matrix rows green; idempotent under repeat |
| 6 | batched authorization on `LessonPolicy` | one query returning the student ids satisfying the whole composite for a course | the per-student `viewForStudent` is unchanged and a test proves the two agree on every exclusion; budget test green |
| 7 | `automation`, content fan-out | `COURSE_CONTENT_PUBLISHED` for lessons, modules, materials, course publish | edit produces nothing; unauthorized recipients receive nothing |
| 8 | `conversations` | the three tables, the policies, message create with the client token | IDOR and idempotency suites green |
| 9 | `course-messaging` | deterministic thread, both directions, policy wiring | authorization matrix green |
| 10 | `support-threads` | the report flow, admin reply, close | authorization matrix green |
| 11 | `announcements` | course and platform create, list, fan-out | audience tests green |
| 12 | `notification-centre` | the page, the badge, mark read, mark all read | one heading per page; no dead controls; responsive green |
| 13 | `messaging-ui` | thread list, thread view, composer, states | responsive green; all three states present |
| 14 | `dashboard-integration` | the three dashboards and the two navigation items | each role sees only its own |

Fourteen slices. Slices 1 and 2 are prerequisites for everything. Slices 3 to 5
are independent of each other and can run in parallel by different pairs. Slice
6 must land before slice 7. Slices 8 to 11 can be parallel once 1 and 2 are in.

Slices 1 through 7 deliver the automation with no user-facing notification
surface, which is deliberate: the events are correct and quiet before anything
is rendered, so a mistake in a listener is not also a visible mistake.

---

## 12. Risks

| Risk | Likelihood | Impact | Response |
| --- | --- | --- | --- |
| Fan-out writes a row per student and a large course makes publishing slow | medium | medium | the batched access query in section 4.4; a notification query budget; if a real course exceeds a few hundred students the seam in 4.2 moves to the queue with no other change |
| A listener throws and breaks the learning action | low | high | the dispatch is `afterCommit` and the listener is wrapped, so a notification failure never rolls back or fails a completed quiz. Asserted by test |
| An event is dispatched inside a transaction by a later contributor | medium | high | the rule is in section 4.1, and a test asserts no event class is dispatched without `afterCommit` |
| A student is notified about content they cannot open | low | high | section 4.5, the same predicate the destination uses; asserted per matrix row |
| Notification growth | high | low | a row per event is small; a retention policy is a future slice and is named in section 13 |
| Duplicate notifications after a retry | medium | medium | the unique index, not a check. Section 4.3 |
| An instructor's dashboard becomes unreadable because 12 students started one lesson | high | medium | recipient grouping in the interface, decided in the UI slice, not by suppressing the notification |
| Announcement fan-out to all active accounts on a large system | medium | medium | the same seam; platform announcements are the only case that touches every account |

---

## 13. Explicitly out of scope

Named so a later change does not quietly widen the slice.

- Assignments, submissions, and manual grading. Section 0.2.
- Email, SMS, and web push. Decision 3.
- Scheduled or drafted announcement publishing. Decision 4.
- Live chat, websockets, and polling. The application is server-rendered with no
  client data layer, and specification section 14 says not to introduce
  infrastructure the existing application does not need.
- Attachments on messages. A file is a Learning Material, and that surface
  already has its own private-disk delivery. Reusing it inside a conversation
  would need its own authorization story and is a separate decision.
- Notification retention and cleanup policy.
- A notification preference or mute setting per user. Specification section 17
  asks the system not to be noisy, which the matrix addresses by construction
  rather than by letting a user turn things off.
- Threads with more than two participants. Section 1 of the specification
  describes pairwise communication only, and a group thread changes the
  authorization model from one predicate to an access list.

---

## 14. Open questions

Answered ones are recorded in plan section 1. These are the ones I could not settle
from the repository.

1. **Should a student be able to start a conversation with an instructor before enrolling?** The specification scopes course communication to enrolled students, so this plan answers no. If a prospective student needs to ask a question, that is an argument for letting them open a support thread instead, which slice 10 provides.
2. **Should an instructor be able to delete a message?** The plan says no, and keeps edit and delete out of scope, because a communication record that a participant can erase is weaker as a record. If a moderation need appears, delete belongs on the administrator side and needs an audit entry, which is a different feature from this one.
3. **How long should a support thread stay open?** `status` exists so it can be closed, but the plan sets no automatic closing. A scheduled sweep is a scheduler entry and is not asked for.
4. **Does the notification centre need pagination, or is a capped recent list enough?** The plan assumes pagination because the badge count is unbounded. A capped list of the last 50 with a "view all" would be simpler and would need the index in 5.1 either way.

---

## 15. Verification checklist

Before this feature is called done:

- [ ] `php artisan test` passes, with the four new suites and the matrix suite included
- [ ] `php vendor/bin/pint --test` passes
- [ ] `composer audit` passes
- [ ] `npm run build` passes
- [ ] `php artisan icons:sync --check` reports in sync
- [ ] `NotificationQueryBudgetTest` green, badge and list both bounded
- [ ] Every row of the plan section 7 matrix has a test, and the matrix is quoted in the test's data provider so a new type cannot be added without a row
- [ ] The plan section 8 matrix has a test per cell
- [ ] `HostileInput` applied to every new text field, with the no-crash and no-partial-write properties
- [ ] The browser chaos pass re-run for all three roles, reporting zero bugs
- [ ] One heading per page on every new page
- [ ] No horizontal overflow at 320, 360, 390, 768, 1440
- [ ] `docs/plan.md` section 17 amended, and `docs/development-roadmap.md` given the slice list as the implementation order
- [ ] `docs/architecture.md` given the event and notification sections, matching the format of the existing sections
- [ ] `docs/folder-structure.md` lists every new `app/` file, which the existing `ProductionReadinessTest` enforces
