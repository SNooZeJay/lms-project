# Laravel folder structure

## 1. Document status

This document defines the target folder structure for the BSIT Academic LMS.

The Laravel 13 foundation has been scaffolded at the repository root. The folders below describe the target structure; later phases add the marked business modules.

The structure follows Laravel conventions. It does not use a separate `frontend/` repository or a separate `backend/` repository.

## 2. Technology boundary

```text
Laravel 13
PHP 8.3 to 8.5
Blade
Tailwind CSS
MySQL
Composer
```

Laravel owns:

- Routes
- Controllers
- Middleware
- Form Requests
- Policies
- Models
- Actions
- Services
- Jobs
- Events
- Blade views
- Database migrations
- Authentication
- File storage
- Tests

A browser sends requests to Laravel. Laravel returns HTML, redirects, validation errors, and authorized file responses.

## 3. Target repository tree

```text
lms-project/
├── app/
│   ├── Actions/
│   │   ├── Account/│   │   │   └── ChangePassword.php
│   │   ├── Authentication/
│   │   │   ├── AssignUserRole.php
│   │   │   └── UpdateAccountStatus.php
│   │   ├── Certificates/
│   │   │   └── ListStudentCertificates.php
│   │   ├── Completion/
│   │   │   ├── CompleteCourse.php
│   │   │   ├── ReissueCertificate.php
│   │   │   └── RevokeCertificate.php
│   │   ├── Courses/
│   │   │   ├── CreateCourse.php
│   │   │   ├── PublishCourse.php
│   │   │   ├── UnpublishCourse.php
│   │   │   ├── UpdateCourse.php
│   │   │   ├── ArchiveContent.php
│   │   │   ├── SetCourseCover.php
│   │   │   └── Curriculum/
│   │   │   │   ├── CreateLearningMaterial.php
│   │   │   │   ├── CreateLesson.php
│   │   │   │   ├── CreateModule.php
│   │   │   │   ├── UpdateLearningMaterial.php
│   │   │   │   ├── UpdateLesson.php
│   │   │   │   ├── UpdateModule.php
│   │   │   │   └── ReorderCurriculum.php
│   │   ├── Announcements/
│   │   │   └── PublishAnnouncement.php
│   │   ├── Enrollment/
│   │   │   └── EnrollStudent.php
│   │   ├── Fortify/
│   │   │   ├── CreateNewUser.php
│   │   │   ├── PasswordValidationRules.php
│   │   │   └── ResetUserPassword.php
│   │   ├── Learning/
│   │   │   ├── MarkLessonComplete.php
│   │   │   └── RecordLessonActivity.php
│   │   ├── Messaging/
│   │   │   ├── PostMessage.php
│   │   │   ├── StartConversation.php
│   │   │   └── ThreadState.php
│   │   ├── Notifications/
│   │   │   ├── MarkAllNotificationsRead.php
│   │   │   ├── MarkNotificationRead.php
│   │   │   └── RecordNotification.php
│   │   ├── Payments/
│   │   │   ├── CreatePayMongoCheckout.php
│   │   │   └── ProcessPayMongoEvent.php
│   │   └── Quizzes/
│   │   │   ├── CreateQuizQuestion.php
│   │   │   ├── ManageQuiz.php
│   │   │   ├── StartQuizAttempt.php
│   │   │   └── SubmitQuizAttempt.php
│   ├── Console/
│   │   └── Commands/
│   │   │   ├── BootstrapOwner.php
│   │   │   ├── CheckProductionReadiness.php
│   │   │   └── SyncLucideIcons.php
│   ├── Contracts/
│   │   ├── LocalSecretStore.php
│   │   └── PayMongoClient.php
│   ├── Events/
│   │   ├── AnnouncementPublished.php
│   │   ├── CertificateReissued.php
│   │   ├── CertificateRevoked.php
│   │   ├── ContentPublished.php
│   │   ├── CourseCompleted.php
│   │   ├── LessonCompleted.php
│   │   ├── LessonStarted.php
│   │   ├── QuizGraded.php
│   │   ├── QuizStarted.php
│   │   └── StudentEnrolled.php
│   ├── Enums/
│   │   ├── ActivityEventType.php
│   │   ├── UserRole.php
│   │   ├── UserAccountStatus.php
│   │   ├── CourseStatus.php
│   │   ├── CourseLevel.php
│   │   ├── CourseType.php
│   │   ├── ContentStatus.php
│   │   ├── LearningMaterialType.php
│   │   ├── EnrollmentStatus.php
│   │   ├── LessonProgressStatus.php
│   │   ├── PaymentStatus.php
│   │   ├── QuizAttemptStatus.php
│   │   ├── CertificateStatus.php
│   │   ├── PaymentEventStatus.php
│   │   ├── QuestionType.php
│   │   ├── QuizStatus.php
│   │   └── NotificationType.php
│   │   ├── ConversationKind.php
│   │   ├── AnnouncementScope.php
│   │   └── ConversationStatus.php
│   ├── Listeners/
│   │   └── Notifications/
│   │       ├── NotifyEnrolledStudentsOfContent.php
│   │       ├── NotifyInstructorOfEnrollment.php
│   │       ├── NotifyInstructorOfLessonActivity.php
│   │       ├── NotifyInstructorOfQuizActivity.php
│   │       ├── NotifyRecipientsOfAnnouncement.php
│   │       ├── NotifyStudentOfCertificateChange.php
│   │       ├── NotifyStudentOfCourseCompletion.php
│   │       └── NotifyStudentOfQuizResult.php
│   ├── Http/

│   │   ├── Controllers/
│   │   │   ├── Account/
│   │   │   │   ├── PasswordController.php
│   │   │   │   └── ProfileController.php
│   │   │   ├── AnnouncementController.php
│   │   │   ├── Admin/
│   │   │   │   ├── ActivityLogController.php
│   │   │   │   ├── UserController.php
│   │   │   │   ├── CertificateController.php
│   │   │   │   └── ReportController.php
│   │   │   ├── Catalog/
│   │   │   │   └── CourseCatalogController.php
│   │   │   ├── Controller.php
│   │   │   ├── HomeController.php
│   │   │   ├── Instructor/
│   │   │   │   ├── CourseController.php
│   │   │   │   ├── CourseCoverController.php
│   │   │   │   ├── CurriculumController.php
│   │   │   │   └── QuizController.php
│   │   │   ├── MaterialDownloadController.php
│   │   │   ├── Notification/
│   │   │   │   ├── NotificationCentreController.php
│   │   │   │   └── NotificationReadController.php
│   │   │   ├── Messaging/
│   │   │   │   ├── ConversationController.php
│   │   │   │   └── SupportRequestController.php
│   │   │   ├── Announcements/
│   │   │   │   └── StoreAnnouncementRequest.php
│   │   │   ├── Role/
│   │   │   │   ├── AdministratorController.php
│   │   │   │   ├── InstructorController.php
│   │   │   │   └── StudentController.php
│   │   │   ├── Student/
│   │   │   │   ├── EnrollmentController.php
│   │   │   │   ├── CertificateController.php
│   │   │   │   ├── PaymentController.php
│   │   │   │   └── QuizController.php
│   │   │   └── Webhooks/
│   │   │   │   └── PayMongoWebhookController.php
│   │   ├── Middleware/
│   │   │   ├── RequirePasswordChange.php
│   │   │   ├── EnsureAccountIsActive.php
│   │   │   ├── ConfineDebugOutput.php
│   │   │   ├── RefuseWhenProjectIsWebReadable.php
│   │   │   ├── SecureSessionCookies.php
│   │   │   ├── SecurityHeaders.php
│   │   │   ├── ForcePublicHttps.php
│   │   │   ├── ThrottleWrites.php
│   │   │   └── EnsureUserHasRole.php
│   │   ├── Requests/
│   │   │   ├── Account/
│   │   │   │   ├── ChangePasswordRequest.php
│   │   │   │   └── UpdateProfileRequest.php
│   │   │   ├── Admin/
│   │   │   │   ├── UpdateAccountStatusRequest.php
│   │   │   │   └── UpdateUserRoleRequest.php
│   │   │   ├── Catalog/
│   │   │   │   └── CourseCatalogRequest.php
│   │   │   ├── Certificates/
│   │   │   │   └── RevokeCertificateRequest.php
│   │   │   ├── Courses/
│   │   │   │   ├── CreateCourseRequest.php
│   │   │   │   ├── CreateLearningMaterialRequest.php
│   │   │   │   ├── CreateLessonRequest.php
│   │   │   │   ├── CreateModuleRequest.php
│   │   │   │   ├── UpdateCourseRequest.php
│   │   │   │   ├── UpdateLearningMaterialRequest.php
│   │   │   │   ├── UpdateLessonRequest.php
│   │   │   │   ├── UpdateModuleRequest.php
│   │   │   │   ├── UpdateCourseCoverRequest.php
│   │   │   │   ├── ReorderLessonsRequest.php
│   │   │   │   └── ReorderModulesRequest.php
│   │   │   ├── Messaging/
│   │   │   │   └── PostMessageRequest.php
│   │   │   └── Quizzes/
│   │   │   │   ├── StoreQuizQuestionRequest.php
│   │   │   │   ├── StoreQuizRequest.php
│   │   │   │   ├── SubmitQuizAttemptRequest.php
│   │   │   │   └── UpdateQuizRequest.php
│   │   └── Responses/
│   │   │   ├── RoleBasedLoginResponse.php
│   │   │   └── SafePasswordResetLinkResponse.php
│   │   │   └── VerifyEmailResponse.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Profile.php
│   │   ├── Course.php
│   │   ├── Module.php
│   │   ├── Lesson.php
│   │   ├── LearningMaterial.php
│   │   ├── Enrollment.php
│   │   ├── Payment.php
│   │   ├── PaymentEvent.php
│   │   ├── LessonProgress.php
│   │   ├── Quiz.php
│   │   ├── QuizQuestion.php
│   │   ├── QuizOption.php
│   │   ├── QuizAttempt.php
│   │   ├── QuizAnswer.php
│   │   ├── Certificate.php
│   │   ├── CourseRequirement.php
│   │   ├── Notification.php
│   │   ├── Announcement.php
│   │   ├── Conversation.php
│   │   ├── ConversationMessage.php
│   │   ├── ConversationParticipant.php
│   │   └── ActivityLog.php
│   ├── Policies/
│   │   ├── ActivityLogPolicy.php
│   │   ├── UserPolicy.php
│   │   ├── CoursePolicy.php
│   │   ├── LessonPolicy.php
│   │   ├── ModulePolicy.php
│   │   ├── EnrollmentPolicy.php
│   │   ├── LearningMaterialPolicy.php
│   │   ├── NotificationPolicy.php
│   │   ├── AnnouncementPolicy.php
│   │   ├── ConversationPolicy.php
│   │   ├── QuizPolicy.php
│   │   ├── CertificatePolicy.php
│   │   └── QuizAttemptPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── FortifyServiceProvider.php
│   ├── Services/
│   │   ├── ProgressCalculator.php
│   │   ├── Certificates/
│   │   │   └── CertificateCodeGenerator.php
│   │   ├── Learning/
│   │   │   └── CourseCompletionChecker.php
│   │   ├── Payments/
│   │   │   ├── PayMongoApiClient.php
│   │   │   ├── CheckoutSession.php
│   │   │   └── PayMongoEventEnvelope.php
│   │   ├── Quizzes/
│   │   │   └── QuizGrader.php
│   │   ├── Reporting/
│   │   │   └── OperationsReport.php
│   │   └── Storage/
│   │   │   ├── CourseCoverStorage.php
│   │   │   └── LearningMaterialStorage.php
│   └── Support/
│   │   ├── CourseCoverCatalog.php
│   │   ├── CoursePrice.php
│   │   ├── RoleBasedDestination.php
│   │   ├── StudentCourseAccess.php
│   │   ├── WindowsDpapiSecretStore.php
│   │   ├── ContinueLearning.php
│   │   ├── StudentPaymentState.php
│   │   ├── MaterialFileRules.php
│   │   ├── Money.php
│   │   ├── Navigation.php
│   │   ├── Position.php
│   │   ├── PublicHttps.php
│   │   ├── PublishedCourses.php
│   │   ├── StatusLabel.php
│   │   └── StudentQuizAccess.php
├── bootstrap/
│   ├── app.php
│   └── providers.php
├── config/
│   ├── auth.php
│   ├── fortify.php
│   ├── owner.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── services.php
│   └── session.php
├── database/
│   ├── factories/
│   │   ├── CourseFactory.php
│   │   ├── LearningMaterialFactory.php
│   │   ├── LessonFactory.php
│   │   ├── ModuleFactory.php
│   │   └── UserFactory.php
│   ├── migrations/
│   └── seeders/
├── public/
│   ├── favicon.ico
│   └── robots.txt
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   └── views/
│       ├── admin/
│       │   ├── activity/
│       │   └── users/
│       ├── account/
│       ├── auth/
│       ├── certificates/
│       ├── components/
│       ├── courses/
│       ├── catalog/
│       ├── dashboard/
│       ├── errors/
│       ├── instructor/
│       │   └── courses/
│       ├── layouts/
│       ├── legal/
│       ├── learning/
│       ├── messaging/
│       ├── notifications/
│       ├── payments/
│       ├── quizzes/
│       ├── public/
│       ├── roles/
│       ├── student/
│       │   ├── courses/
│       │   └── lessons/
├── routes/
│   ├── web.php
│   ├── public.php
│   ├── student.php
│   ├── instructor.php
│   ├── admin.php
│   ├── auth.php
│   ├── webhooks.php
│   └── console.php
├── storage/
│   ├── app/
│   │   └── private/
│   ├── framework/
│   │   ├── cache/
│   │   ├── sessions/
│   │   └── views/
│   └── logs/
├── tests/
│   ├── Feature/
│   │   ├── Account/
│   │   ├── Admin/
│   │   ├── Auth/
│   │   ├── Catalog/
│   │   │   └── CourseCatalogController.php
│   │   ├── Instructor/
│   │   ├── Phase4A/
│   │   ├── Phase4B/
│   │   ├── Phase5A/
│   │   ├── Phase5B/
│   │   ├── Phase5C/
│   │   ├── Phase5D/
│   │   ├── Phase5E/
│   │   ├── Phase5F/
│   │   ├── Phase6A/
│   │   ├── Phase6B/
│   │   ├── Phase6C/
│   │   ├── Phase6D/
│   │   ├── Role/
│   │   ├── Student/
│   │   └── Webhooks/
│   └── Unit/
│       ├── Certificates/
│       ├── legal/
│       ├── learning/
│       ├── messaging/
│       ├── notifications/
│       ├── Payments/
│       ├── Quizzes/
│       ├── public/
│       └── Support/
├── vendor/
├── docs/
│   ├── README.md
│   ├── plan.md
│   ├── design.md
│   ├── architecture.md
│   ├── folder-structure.md
│   ├── technology-choice.md
│   ├── glossary.md
│   ├── development-roadmap.md
│   ├── project-audit.md
│   ├── deployment.md
│   └── defense.md
├── FOR_UI/
│   └── adminator (FOR USER DASHBOARD)/
├── .agents/
├── .opencode/
├── .env.example
├── .gitignore
├── opencode.json
├── skills-lock.json
├── AGENTS.md
├── README.md
├── THIRD_PARTY_NOTICES.md
├── artisan
├── composer.json
├── composer.lock
├── package.json
├── package-lock.json
└── phpunit.xml
```

The `app/` subtree lists every file that exists today. The rest of the tree shows the
important files in each area, so a file may be created before it is listed here.

Create folders only when approved work needs them.

## 4. Root directories

### `app/`

Contains Laravel application code.

This directory owns business behavior which is not framework configuration.

### `bootstrap/`

Contains framework bootstrap files and service-provider registration.

Application business logic does not belong here.

### `config/`

Contains framework configuration.

`config/icons.php` maps each icon name the views use to the Lucide drawing
behind it. It is the single place the choice of drawing is made.
`php artisan icons:sync` bakes the result into `resources/icons/lucide.php`.

Secrets come from environment variables. Secret values do not belong in committed config files.

### `database/`

Contains:

- Migrations
- Factories
- Seeders

Migrations are the only source for database schema changes.

### `public/`

Contains the web entry point and safe public assets.

The production web server must use `public/` as its document root.

Protected learning files never belong in `public/`.

The brand mark lives in `public/images/brand/`, because a view needs an address
for it. `public/favicon.png` and `public/images/brand/touch-icon.png` are the
icon copies. `public/images/brand/README.md` records which derivative is used
where.

### `resources/`

Contains source files processed or rendered by Laravel:

- Blade views
- CSS
- Small JavaScript files
- The source brand artwork

The source artwork is the only image in `resources/`. It is never served, and it
is the file to edit when the mark changes. Everything a browser downloads lives
under `public/`.

### `routes/`

Contains route definitions.

Route groups separate public, Student, Instructor, Administrator, authentication, and webhook URLs.

### `storage/`

Contains framework-generated files and private application files.

Most content in this directory is generated and ignored by Git.

### `tests/`

Contains automated Feature and Unit tests.


`tests/Feature/DatabaseIntegrityTest.php` holds the rules the database cannot enforce.
A foreign key proves a row points at an existing row, not at the right one, and a check
constraint cannot reach another table, so the rules that two columns holding one fact
must agree, that an answer names an option of the question it answers, and that a
notice does not outlive the announcement it announces, are kept by the write path and
pinned here. The copied columns are read over every row, and the arrangement builds a
course with a lesson and a learner first, so a passing assertion is never an empty
table agreeing with itself. It also asserts the relationship chain the plan draws,
read out of `information_schema` rather than out of the models, that the constraints
are live rather than merely declared, and that the tables the plan defers are absent.
`tests/Support/QueryCounter.php` counts the queries a piece of work runs, so a
performance change is judged against a number rather than an impression.

### `tools/`

Contains runnable verification scripts. These are tools rather than tests because
they write to the configured database, hold locks open, and report numbers on
screen instead of asserting them.

- `verify-concurrency.php` proves the position row lock using two separate
  database sessions
- `verify-large-dataset.php` reports the query count per page with a few hundred
  courses present
- `verify-cleanup.php` removes the rows those two created
- `probe-mailer.php` sends a real password reset through the configured mailer
  and reports the transport, so "mail is switched on" is measured rather than
  assumed. The reset token is never printed
- `swap-account-emails.php` exchanges two accounts' addresses without tripping
  the unique index, parking both rows on addresses nobody can own first. It
  refuses rather than guesses when an address has no account
- `inspect-schema.php` reports which tables a database has without writing to
  it, for answering "is the schema still there" without making it worse
- `probe-routes.php` walks every readable route as a guest and as each role and
  reports the status and query cost of each, so authorization and performance are
  measured rather than assumed
- `probe-one-route.php` prints the reason behind one route's status
- `probe-message-page-queries.php` prints every conversation query the message list
  page runs, with the count. `TopbarMessagingCostTest` pins how many are allowed
  and asks that anything beyond the documented set be justified; justifying it by
  eye is a guess, so this prints the statements instead
- `why-404.php` shows the body a status code would have thrown away, because a
  404 from the router, from model binding and from the controller differ
- `repair-enrollment-activations.php` fills in a missing `activated_at` on an
  enrollment that is already live. A live enrollment always has one: the real
  flows set the status and the date together, so a row with a status and no date
  can only come from somewhere that wrote the status on its own, and every panel
  that reads real dates then reads that learner as having never started. It only
  ever fills a date that is absent, takes it from the learner's own earliest
  lesson progress, and changes nothing on a second run. `--dry` reports without
  writing
- `seed-dashboard-demo.php` builds the demonstration catalog, progress, quizzes
  and results. It uses the factory states rather than overriding `status`,
  because an override replaces the state that sets `activated_at` with it
- `seed-topbar-demo.php` builds the conversations and notifications the topbar
  panel reads, so the panel is exercised with something in it
- `rebuild-the-test-database.php` drops and recreates the test database and
  migrates it. It exists because `migrate:fresh` reported "Dropping all tables
  DONE" and then failed on a table that had survived the drop, and a later run
  deadlocked on the drop statement and left a half dropped schema that made every
  following run fail on a foreign key pointing at a table that was no longer
  there. Recreating the database in one statement does not walk foreign keys. It
  refuses to run unless the name matches the database `phpunit.xml` declares,
  because `.env` names the demonstration database and a script that drops
  databases being pointed at the wrong one is the failure mode worth designing
  against
- `check-reveal.mjs` measures the opening band's entrance in a real browser with
  a reduced motion preference forced **off**. It is in this project because the
  machine it was written on reports that preference as set, and can therefore only
  ever verify that the animations are absent. It samples computed opacity from the
  first frame the element is painted in, which is the only moment a reveal lasting
  a few hundred milliseconds can be observed at all: three earlier attempts
  sampled after load and reported a working animation as broken, and one replayed
  the reveal by hand and reported a broken one as working
- `server-router.php` is the router that lets PHP's built in web server serve this
  application. Without one, `php -S` hands a request for a stylesheet to Laravel,
  the application answers with its 404 page, and the browser is handed HTML where
  it asked for CSS, so the page renders unstyled. It lives in the repository so
  there is one copy of the rule and it survives a reboot
- `repair-impossible-rows.php` repairs the rows an audit of the live database found to
  be impossible: a lesson progress row marked finished with no date beside it, a
  profile row belonging to an account that no longer exists, and a notice pointing at
  an announcement that was withdrawn. It reports every row before it writes, never
  overwrites a good row, and does nothing on a second run, because a repair whose
  first run writes is a repair nobody will run against a copy first. A lesson's missing
  date is taken from `last_viewed_at`, the closest evidence the row still holds, rather
  than invented from `created_at`
- `serve-concurrently.php` serves the application from a pool of workers behind
- `cleanup-probe-announcements.php` removes the duplicate announcements the form
  probe leaves behind, and the notices that were generated with them. Posting a real
  announcement is the only honest way to prove a publish form works and the wrong way
  to leave a demonstration, and three probe runs left three identical notices that read
  as somebody having pressed publish three times by accident. Only titles the probe
  writes are touched, and one of each is kept
- `seed-completion-demo.php` runs the real quiz and completion Actions so the
  demonstration can show a graded result and an earned certificate. Completion needs
  every required lesson finished and every required quiz passed, and with nobody having
  sat a quiz no Student was eligible, so the workflow existed, worked and had nothing to
  show. It seeds past nothing: `StartQuizAttempt`, `SubmitQuizAttempt` and
  `CompleteCourse` are the application's own, so grading, the quiz notices to the
  Student, the activity notice to the Instructor, the eligibility re-check and the
  certificate code all genuinely run. Safe to run twice: a quiz already passed is left
  alone and completion returns the existing certificate rather than issuing a second
  Apache, which is how the public address is reached. `start` writes the Apache
  configuration, starts the workers and the front door, and proves the sign in
  page renders before reporting success; `stop`, `status` and `check` are the
  other commands. It is needed because PHP's built in web server answers one
  request at a time and `PHP_CLI_SERVER_WORKERS`, which would change that, needs
  `fork()`, which Windows does not have

### `vendor/`

Contains Composer dependencies.

Never edit vendor files manually. Never commit `vendor/`.

### `docs/`

Contains project truth and planning documents.

## 5. Actions

Actions represent one approved business operation.

Examples:

```text
EnrollStudent
ActivatePaidEnrollment
MarkLessonComplete
SubmitQuizAttempt
CompleteCourse
IssueCertificate
```

An Action may use a database transaction.

Actions should have one clear responsibility and a predictable result.

## 6. Services

Services contain reusable technical capabilities.

Examples:

- PayMongo communication
- Progress calculation
- Course completion checks
- Quiz grading
- Certificate code generation
- Protected file storage

Do not create a generic `Services/` dumping ground. Each Service must have a clear technical owner.

## 7. Controllers

Controllers are grouped by access area:

- `Public/`
- `Auth/`
- `Student/`
- `Instructor/`
- `Admin/`
- `Webhook/`

A controller should:

1. Receive a request
2. Use a Form Request
3. Use a Policy
4. Call an Action or Service
5. Return a response

Large workflows belong in Actions.

## 8. Form Requests

Form Requests live under `Http/Requests/` and are grouped by domain.

Each request class owns validation and request-level authorization for one form or endpoint.

Sensitive fields must never be accepted from ordinary forms.

## 9. Policies

Policies own resource authorization.

Use a Policy for every protected model action.

Policies must be tested as anonymous, Student, Instructor, Administrator, cross-user, and cross-course cases.

## 10. Models and enums

Models represent database records.

Enums represent approved finite states.

Use casts on Models for dates, decimals, booleans, and enums.

Keep business workflows out of Models when an Action or Service gives a clearer owner.

## 11. Blade views

Views are grouped by user area and shared components.

```text
resources/views/
├── components/          Shared interface components
│   ├── app/             Layout components: nav and user menu
│   └── *.blade.php      Base interface components
├── layouts/             app, app-shell, and auth
├── public/              Home page
├── auth/                Sign in, register, recovery, verification
├── account/             Own profile and password
├── roles/               The three role dashboards
├── catalog/             Public course catalog and course page
├── student/             Courses, lessons, quizzes, certificates, payments
├── instructor/          Course authoring and the course outline
├── admin/               Users, activity, certificates, reports
└── errors/              Public error pages
```

Two layouts are used, and the split is deliberate:

| Layout | Used by | Reason |
|---|---|---|
| `layouts/app` | Public pages | A guest has no workspace, so a guest sees no sidebar |
| `layouts/app-shell` | Every signed in page | One shell, one sidebar, one header for all three roles |
| `layouts/auth` | Authentication pages | Wraps `layouts/app` with the split brand panel |

A page that needs a second or third copy of a repeated row, such as a table row
and its mobile card, uses a local partial beside the page rather than a
component, because it is not reused elsewhere.

Blade views render safe data and collect input.

They never contain the only authorization check for an action.

Most reusable UI belongs in `resources/views/components/` as Blade components. Use a PHP class under `app/View/Components/` only when a component needs injected services or substantial behavior.

## 12. Tailwind and JavaScript

`resources/css/app.css` imports Tailwind and approved global styles.

`resources/js/app.js` contains only small shared interactions which require JavaScript.

Do not add React, Vue, Redux, or a second frontend application for V1.

Alpine.js may be added later only when a small interaction cannot be handled with Blade, CSS, or normal HTML.

## 13. Routes

Recommended route files:

| File | Responsibility |
|---|---|
| `web.php` | Main route loader and shared middleware |
| `public.php` | Catalog and public pages |
| `auth.php` | Registration, login, logout, and password flows |
| `student.php` | Student route group |
| `instructor.php` | Instructor route group |
| `admin.php` | Administrator route group |
| `webhooks.php` | External provider callbacks |
| `console.php` | Artisan commands and scheduling |

Route files define URLs and middleware. Business logic remains in Actions and Services.

## 14. Database files

```text
database/migrations/
database/factories/
database/seeders/
```

### Migrations

Each migration changes one related schema concern.

Do not edit an old migration after it has been applied in a shared environment. Create a new migration.

### Factories

Factories create valid model records for tests.

### Seeders

Seeders create documented development or demonstration records.

Seed data must never be presented as real user, payment, enrollment, or institution data.

## 15. Tests

### Feature tests

Feature tests cover complete HTTP workflows:

- Registration
- Login
- Role middleware
- Policies
- Enrollment
- Lesson completion
- Quiz submission
- Certificate issuance
- Private file download
- Payment webhooks

### Unit tests

Unit tests cover focused rules:

- Progress calculation
- Quiz scoring
- Completion checks
- Money formatting
- Certificate code generation

## 16. Private files

Development protected files use a private disk such as:

```text
storage/app/private/courses/
storage/app/private/learning-materials/
storage/app/private/profile-avatars/
storage/app/private/certificates/
```

Production may use an S3-compatible private disk.

The database stores the disk name and generated path.

The web server must not expose private disk files directly.

## 17. Domain mapping

| Domain | Primary location |
|---|---|
| Authentication | `routes/auth.php`, `app/Http/Controllers/Auth/`, Laravel Auth config |
| Roles and accounts | `app/Models/Profile.php`, `app/Enums/`, `app/Actions/Authentication/` |
| Courses | `app/Models/Course.php`, Course controllers, requests, Policies, Actions |
| Modules and Lessons | Related Models, instructor controllers, requests, Policies |
| Learning materials | `LearningMaterial` Model, Storage Service, download controller |
| Enrollment | `Enrollment` Model, student controllers, requests, Policies, Actions |
| Payments | `Payment` Model, PayMongo Service, checkout and webhook controllers |
| Progress | `LessonProgress` Model, Progress Service, completion Actions |
| Quizzes | Quiz Models, instructor and student controllers, grading Actions |
| Certificates | `Certificate` Model, certificate Actions, Policies, printable views |
| Administration | Admin controllers, requests, Policies, activity Models |
| Reports | Read-only admin controllers, queries, views |
| Dashboards | Role-specific controllers and Blade views |
| Database schema | `database/migrations/` |
| Tests | `tests/Feature/`, `tests/Unit/` |

## 18. Current support and reference directories

### `FOR_UI/`

Read-only visual reference.

Do not modify, import, rename, or delete files.

### `.agents/`

Installed project skills and references.

Do not rewrite them as application documentation.

### `.opencode/`

Project-local OpenCode commands and the `define-core-domains` skill.

Do not install extra Sauron components. Do not modify installed files without explicit approval.

### `skills-lock.json`

Inventory of installed project skills.

Treat it as generated support metadata. Do not edit it manually.

### `opencode.json`

Project OpenCode configuration.

Keep separate from Laravel application configuration.

## 19. Generated and ignored paths

The following paths are generated or local-only:

```text
/vendor/
/node_modules/
/storage/logs/
/storage/framework/cache/
/storage/framework/sessions/
/storage/framework/views/
/public/build/
/public/hot/
/.env
/.env.backup
/.phpunit.result.cache
```

Use `.env.example` for safe variable names.

The current local database baseline should use:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=lms
DB_USERNAME=lms_user
DB_PASSWORD=
```

Never place the real password in `.env.example`.

Never commit:

- `.env`
- Database credentials
- PayMongo secrets
- Access tokens
- Real payment payloads
- Real user data

## 20. Naming rules

### PHP classes

Use PascalCase:

```text
CreateCourse
EnrollmentPolicy
PayMongoClient
```

### Blade files

Use lowercase kebab-case:

```text
course-card.blade.php
learning-material-list.blade.php
student-dashboard.blade.php
```

### Routes

Use clear names:

```text
student.dashboard
student.courses.show
instructor.courses.edit
admin.users.index
webhooks.paymongo
```

### Database tables

Use plural snake_case:

```text
courses
lesson_progress
quiz_attempts
payment_events
```

## 21. File-placement rules

- Put a route in the access group for its URL.
- Put a controller in the matching access folder.
- Put a Form Request beside its domain under `Http/Requests/`.
- Put a resource Policy in `app/Policies/`.
- Put one business operation in `app/Actions/`.
- Put reusable technical work in a named Service.
- Put a database change in a migration.
- Put a Blade page or partial in the matching views folder.
- Put reusable UI in a Blade component.
- Put critical business tests in Feature tests.
- Put focused rule tests in Unit tests.
- Do not create `utils/`, `helpers/`, `common/`, or `misc/` dumping grounds.
- Do not create folders for deferred LMS domains.

## 22. Reference repository rule

The external repository may inform folder and UI concepts.

Do not copy its `src/` tree into Laravel.

Do not copy application code, schema, authentication, client-side role checks, local-storage data, payment approval, branding, or assets.

HyperUI may supply suitable Tailwind CSS markup under its MIT License. When code or markup is copied, record the required copyright and license notice in `THIRD_PARTY_NOTICES.md`.

Laravel's standard folders remain the target structure.

## 23. Structure acceptance check

The folder blueprint is ready when a developer can:

- Place a new route without guessing
- Place a controller and Form Request consistently
- Identify the correct Policy
- Choose between an Action and a Service
- Add a database change through a migration
- Find reusable Blade UI
- Find the correct test suite
- Keep protected files outside `public/`
- Avoid creating code inside `FOR_UI`
- Avoid adding unapproved domains
- Explain the request path from browser to database and back
