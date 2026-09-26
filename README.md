# IT Learning Hub

A beginner-friendly academic Learning Management System for a BSIT project in the Philippines.

## Current status

Phase 1 of the Laravel foundation is complete. Phase 2 authentication, profiles, Phase 3 roles and authorization, Phase 4A Course foundation, Phase 4B curriculum metadata, Phase 5A Instructor Course Outline UI, Phase 5B curriculum authoring, Phase 5C content editing, Phase 5D Learning Material metadata authoring, and Phase 5E Course publishing are human-approved. Phase 5F public Course catalog is implemented and awaiting browser review.

The repository currently contains:

- Product and architecture documentation
- A Laravel 13 application foundation
- Blade and Tailwind CSS foundation views
- MySQL migrations for framework infrastructure and Phase 2 identity tables
- Automated feature tests and quality commands
- Project-local agent skills and OpenCode configuration
- A compiled Adminator dashboard under `FOR_UI/`

Course management UI, enrollment, learning materials, progress, quizzes, certificates, private uploads, and PayMongo are not implemented yet. The Course database foundation is implemented in Phase 4A.

## Approved technology stack

| Layer | Choice |
|---|---|
| Language | PHP 8.3 to 8.5 |
| Framework | Laravel 13 |
| UI | Blade templates |
| Styling | Tailwind CSS |
| Database | MySQL 8.x |
| Authentication | Laravel Fortify and Laravel authentication |
| Authorization | Policies and Gates |
| Background work | Laravel Jobs, Events, and Listeners |
| File storage | Laravel Storage with private files by default |
| Payments | PayMongo integration on the server |
| Dependencies | Composer |
| Tests | The test framework supplied by the chosen Laravel starter kit |
| Version control | Git and GitHub |

PHP compatibility will be recorded in `composer.json`. Package versions will be pinned in `composer.lock`. The selected MySQL version will be recorded in the local and deployment documentation.

## Product goal

The LMS supports this complete academic workflow:

```text
Register
→ Sign in
→ Browse courses
→ Enroll
→ Study lessons
→ Complete quizzes
→ Track progress
→ Complete course requirements
→ Receive a certificate
```

Instructors manage academic content. Administrators manage users, courses, enrollments, payments, reports, and system activity.

## New to Laravel?

Start with the beginner-friendly [tutorial.md](tutorial.md) for setup, startup, Administrator login, testing, and troubleshooting.

## Documentation map

Read these files in order:

1. [`docs/README.md`](docs/README.md): documentation map and reading order
2. [`docs/glossary.md`](docs/glossary.md): beginner-friendly technical terms
3. [`docs/technology-choice.md`](docs/technology-choice.md): Laravel decision and SIA1 fit
4. [`docs/plan.md`](docs/plan.md): product requirements and scope
5. [`docs/design.md`](docs/design.md): visual and interaction direction
6. [`docs/architecture.md`](docs/architecture.md): Laravel architecture and data design
7. [`docs/folder-structure.md`](docs/folder-structure.md): Laravel folder structure
8. [`docs/development-roadmap.md`](docs/development-roadmap.md): step-by-step build order
9. [`docs/project-audit.md`](docs/project-audit.md): current repository state

## Important boundaries

- `docs/plan.md` is the source of truth for product requirements and security rules.
- `docs/design.md` is the source of truth for visual and interaction decisions.
- `docs/architecture.md` is the source of truth for technical architecture and database design.
- `docs/project-audit.md` describes the repository as it exists now.
- `FOR_UI/adminator (FOR USER DASHBOARD)` is a read-only visual reference.
- `.agents/`, `.opencode/`, and `skills-lock.json` are project support files. They must not be rewritten as application documentation.
- The external Next.js repository is a reference for selected concepts only. Its authentication, authorization, payment, and database code must not be copied.
- HyperUI is an approved MIT-licensed Tailwind CSS reference for suitable forms, content sections, empty states, and responsive patterns.

## Required delivery lifecycle

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

Every feature must also document:

```text
Input → Process → Output
```

A phase cannot advance until the build, tests, fixes, retests, and human review are complete.

## V1 exclusions

V1 does not include:

- Assignments
- Submission grading
- Gradebook
- Announcements
- Notifications
- Forums
- Live classes
- Public certificate verification
- Public course dashboards
- Direct video uploads
- Public storage for protected learning materials

New domains require approved requirements before they enter the plan or architecture.

## Beginner learning order

Before implementing the full LMS, learn these topics in order:

1. PHP functions, arrays, classes, and Composer
2. Laravel routing and controllers
3. Blade templates and layouts
4. Form requests and validation
5. Authentication and middleware
6. Policies and authorization
7. Eloquent models and migrations
8. Services and actions
9. Filesystem and external APIs
10. Tests and deployment

Build one working vertical slice at a time. Do not begin with payment processing.

## Local prerequisites

The current environment is ready:

- PHP 8.5.8
- Composer 2.10.3
- Node.js 24.18.0
- npm 11.18.0
- MySQL Community Server 8.4.11 LTS
- MySQL service `MySQL84-LMS` on `127.0.0.1:3307`
- Dedicated `lms` and `lms_test` databases
- DPAPI-encrypted local database credentials

XAMPP MariaDB remains available on port 3306 and is not used by the LMS.

Open a new terminal before running `composer` so Windows loads the updated user PATH.

## Run the foundation

Use a terminal from the repository root:

```text
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000` in a browser. The local application uses the dedicated MySQL service on port `3307`.

Run the checks with:

```text
php artisan test
vendor/bin/pint --test
composer audit
npm audit
npm run build
```

## Phase 5F checkpoint

Phase 2 authentication, profiles, Phase 3 roles and authorization, Phase 4A Course foundation, Phase 4B curriculum metadata, Phase 5A Instructor Course Outline UI, Phase 5B curriculum authoring, Phase 5C content editing, Phase 5D Learning Material metadata authoring, and Phase 5E Course publishing are human-approved. The Phase 5F slice adds:

- A public catalog at `/courses` and public details at `/courses/{slug}`
- Published Courses only, with a `404` for a draft or archived slug
- Search by title and filters for category, level, and free or paid type
- Public outline structure with no Lesson content and no material data
- Instructor display name only, never an email address
- A Courses link in the shared header and on the home page
- No enrollment, payment, progress, upload, download, delete, or archive behavior

Delete and archiving stay deferred on purpose. A later phase will use status-based archiving so student progress and payment history stay intact.

The Phase 5F browser review checkpoint is open.

The local Administrator is provisioned with:

```text
php artisan owner:bootstrap
```

Run it without `--show-password` to keep the generated password in the external DPAPI file. When needed, run `php artisan owner:bootstrap --show-password` locally. The account is:

```text
Name: Jayzee Bautista
Email: bautista.jayzee@ncst.edu.ph
Role: Administrator
```

The first sign-in requires a password change. The provided PayMongo public test key remains local configuration only. Catalog UI, enrollment, quizzes, certificates, uploads, and payments remain later phases.
