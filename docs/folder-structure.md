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
│   │   ├── Account/
│   │   │   └── ChangePassword.php
│   │   ├── Fortify/
│   │   │   ├── CreateNewUser.php
│   │   │   ├── PasswordValidationRules.php
│   │   │   └── ResetUserPassword.php
│   │   ├── Authentication/
│   │   │   ├── AssignUserRole.php
│   │   │   └── UpdateAccountStatus.php
│   │   ├── Certificates/
│   │   │   ├── IssueCertificate.php
│   │   │   ├── RevokeCertificate.php
│   │   │   └── ReissueCertificate.php
│   │   ├── Courses/
│   │   │   ├── CreateCourse.php
│   │   │   ├── PublishCourse.php
│   │   │   └── UnpublishCourse.php
│   │   ├── Enrollment/
│   │   │   ├── EnrollStudent.php
│   │   │   ├── ActivatePaidEnrollment.php
│   │   │   └── CancelEnrollment.php
│   │   ├── Learning/
│   │   │   ├── MarkLessonComplete.php
│   │   │   └── CompleteCourse.php
│   │   ├── Payments/
│   │   │   ├── CreatePayMongoCheckout.php
│   │   │   ├── ProcessPayMongoEvent.php
│   │   │   └── RefundPayment.php
│   │   └── Quizzes/
│   │       ├── StartQuizAttempt.php
│   │       ├── SubmitQuizAttempt.php
│   │       └── GradeQuizAttempt.php
│   ├── Console/
│   │   └── Commands/
│   │       └── BootstrapOwner.php
│   ├── Contracts/
│   │   └── LocalSecretStore.php
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
│   │   ├── PaymentStatus.php
│   │   ├── LessonProgressStatus.php
│   │   ├── QuizAttemptStatus.php
│   │   └── CertificateStatus.php
│   ├── Events/
│   │   ├── EnrollmentActivated.php
│   │   ├── PaymentPaid.php
│   │   ├── QuizPassed.php
│   │   ├── CourseCompleted.php
│   │   ├── CertificateIssued.php
│   │   └── RoleChanged.php
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── RequirePasswordChange.php
│   │   │   ├── EnsureAccountIsActive.php
│   │   │   └── EnsureUserHasRole.php
│   │   ├── Controllers/
│   │   │   ├── Account/
│   │   │   │   ├── PasswordController.php
│   │   │   │   └── ProfileController.php
│   │   │   ├── Admin/
│   │   │   │   ├── ActivityLogController.php
│   │   │   │   └── UserController.php
│   │   │   ├── Auth/
│   │   │   ├── Instructor/
│   │   │   ├── Public/
│   │   │   ├── Role/
│   │   │   │   ├── AdministratorController.php
│   │   │   │   ├── InstructorController.php
│   │   │   │   └── StudentController.php
│   │   │   ├── Student/
│   │   │   └── Webhook/
│   │   │       └── PayMongoWebhookController.php
│   │   ├── Requests/
│   │   │   ├── Account/
│   │   │   │   ├── ChangePasswordRequest.php
│   │   │   │   └── UpdateProfileRequest.php
│   │   │   ├── Admin/
│   │   │   │   ├── UpdateAccountStatusRequest.php
│   │   │   │   └── UpdateUserRoleRequest.php
│   │   │   ├── Auth/
│   │   │   ├── Courses/
│   │   │   │   └── CreateCourseRequest.php
│   │   │   ├── Enrollment/
│   │   │   ├── Learning/
│   │   │   ├── Payments/
│   │   │   └── Quizzes/
│   │   ├── Responses/
│   │   │   ├── RoleBasedLoginResponse.php
│   │   │   └── SafePasswordResetLinkResponse.php
│   ├── Jobs/
│   │   ├── SendPaymentReceipt.php
│   │   └── RemoveOrphanedMaterial.php
│   ├── Listeners/
│   │   ├── SendEnrollmentReceipt.php
│   │   └── RecordRoleChangeActivity.php
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
│   │   ├── ActivityLog.php
│   │   └── SystemSetting.php
│   ├── Policies/
│   │   ├── ActivityLogPolicy.php
│   │   ├── UserPolicy.php
│   │   ├── CoursePolicy.php
│   │   ├── EnrollmentPolicy.php
│   │   ├── LearningMaterialPolicy.php
│   │   ├── PaymentPolicy.php
│   │   ├── QuizPolicy.php
│   │   └── CertificatePolicy.php
│   ├── Services/
│   │   ├── Certificates/
│   │   │   └── CertificateCodeGenerator.php
│   │   ├── Learning/
│   │   │   ├── ProgressCalculator.php
│   │   │   └── CourseCompletionChecker.php
│   │   ├── Payments/
│   │   │   └── PayMongoClient.php
│   │   ├── Quizzes/
│   │   │   └── QuizGrader.php
│   │   └── Storage/
│   │       └── LearningMaterialStorage.php
│   ├── Support/
│   │   ├── RoleBasedDestination.php
│   │   └── WindowsDpapiSecretStore.php
│   ├── View/Components/
│   │   ├── Alert.php
│   │   ├── Button.php
│   │   ├── EmptyState.php
│   │   ├── FormField.php
│   │   ├── Pagination.php
│   │   ├── ProgressBar.php
│   │   └── StatusBadge.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── FortifyServiceProvider.php
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
│       ├── dashboard/
│       ├── errors/
│       ├── instructor/
│       │   └── courses/
│       ├── layouts/
│       ├── learning/
│       ├── payments/
│       ├── quizzes/
│       ├── roles/
│       └── student/
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
│   │   ├── Instructor/
│   │   ├── Phase4A/
│   │   ├── Phase4B/
│   │   ├── Phase5A/
│   │   ├── Role/
│   │   ├── Student/
│   │   └── Webhooks/
│   └── Unit/
│       ├── Certificates/
│       ├── Learning/
│       ├── Payments/
│       ├── Quizzes/
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
│   └── project-audit.md
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

The tree shows important examples. It does not require every listed file to exist immediately.

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

### `resources/`

Contains source files processed or rendered by Laravel:

- Blade views
- CSS
- Small JavaScript files

### `routes/`

Contains route definitions.

Route groups separate public, Student, Instructor, Administrator, authentication, and webhook URLs.

### `storage/`

Contains framework-generated files and private application files.

Most content in this directory is generated and ignored by Git.

### `tests/`

Contains automated Feature and Unit tests.

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
├── components/
├── layouts/
├── auth/
├── courses/
├── dashboard/
├── student/
├── instructor/
├── admin/
├── learning/
├── quizzes/
├── payments/
├── certificates/
└── errors/
```

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
