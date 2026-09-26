# Project audit

## 1. Audit status

Audit date: September 25, 2026

This document describes the repository as it exists now.

The Laravel foundation, Phase 2 authentication/profile slice, Phase 3 roles and authorization slice, Phase 4A Course foundation, Phase 4B curriculum metadata, Phase 5A Instructor Course Outline UI, Phase 5B curriculum authoring, and Phase 5C content editing are human-approved. Phase 5D Learning Material metadata authoring is implemented and awaiting human browser review. Later LMS business modules remain unbuilt.

## 2. Current repository state

The repository is an implementation-stage IT Learning Hub for a BSIT Academic LMS.

It contains the Laravel 13 foundation, documentation, project support files, and a compiled static dashboard reference.

The foundation is runnable. The product display name is now `IT Learning Hub`. Phase 2 contains the Fortify package, User and Profile records, authentication screens, verified email access, own-profile editing, the forced temporary-password gate, the local Administrator owner, and the completed automated/browser evidence checkpoint. Phase 3 now contains ActivityLog records, role/status Actions, Policies, active-account middleware, role pages, and Administrator user management. Phase 4A now contains the Course table, enums, model, Instructor relationship, factory, and constraint tests. Phase 4B now contains the Module, Lesson, and Learning Material tables, enums, models, factories, and constraint tests. Phase 5A now contains the Instructor Course list, create form, and read-only outline. Phase 5B now contains Instructor Module and Lesson authoring on the outline page. Phase 5C now contains Instructor editing for Course, Module, and Lesson. Phase 5D now contains Instructor Learning Material metadata authoring for text, code, video link, and external link materials. The application does not yet contain the catalog UI, enrollment, file uploads, downloads, delete or archive, publishing, progress, quizzes, certificates, or PayMongo.

## 3. Root contents

| Path | Current state | Boundary |
|---|---|---|
| `.agents/` | Installed project skills and references | Preserve unless explicitly asked |
| `.git/` | Initialized local Git repository on `main` with repository-local identity | Use `bautistajayzee` and the approved email |
| `.opencode/` | Project OpenCode commands and skills | Preserve unless explicitly asked |
| `docs/` | Product, design, architecture, roadmap, and audit documentation | Project truth |
| `FOR_UI/` | Compiled Adminator-style dashboard distribution | Read-only visual reference |
| `app/` | Laravel application layer with public, authentication, account, role, and administration code | Application source |
| `bootstrap/` | Laravel application bootstrap | Application source |
| `config/` | Laravel, Fortify, local owner, and role authorization configuration | Application source |
| `database/` | Framework, Phase 2 identity, Phase 3 activity-log, and Phase 4A Course migrations plus factories and empty business seeder | Application source |
| `public/` | Public document root and compiled local assets | Generated assets are ignored |
| `resources/` | Blade layouts, authentication/account/role/admin views, Tailwind CSS, and theme script | Application source |
| `routes/` | Public, authentication, account, role, admin, and health route configuration | Application source |
| `storage/` | Private local storage skeleton | Local runtime files are ignored |
| `tests/` | Authentication, account, role, admin, owner-command, database, Phase 5A, Phase 5B, Phase 5C, and Phase 5D UI feature tests | Application source |
| `vendor/` | Installed Composer dependencies | Generated and ignored |
| `node_modules/` | Installed npm dependencies | Generated and ignored |
| `AGENTS.md` | OpenCode project instructions | Persistent coding context |
| `opencode.json` | Project OpenCode configuration | OpenCode only |
| `README.md` | Beginner project entry point | Documentation |
| `skills-lock.json` | Installed skill inventory | Generated support metadata |
| `.env.example` | Safe local configuration template | Never add a real password |
| `composer.json` and `composer.lock` | PHP dependency contract | Application source and lock evidence |
| `package.json` and `package-lock.json` | Frontend build contract | Application source and lock evidence |
| `phpunit.xml` | MySQL-backed test configuration | Application source |

## 4. Current application state

The following Phase 1 foundation files exist:

- `artisan`
- `composer.json` and `composer.lock`
- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `storage/`
- `tests/`
- `.env.example`
- `package.json` and `package-lock.json`
- `phpunit.xml`

The following Phase 1, Phase 2, Phase 3, and Phase 4A capabilities exist:

- Laravel application bootstrap
- Named public Home route and thin controller
- Blade application layout
- Tailwind CSS build
- Light and dark theme preference
- Health route
- Safe 401, 403, 404, 419, 429, 500, and 503 views
- MySQL local and test connections
- Framework-only sessions, cache, and jobs migrations
- PHPUnit feature tests
- Composer and npm quality checks
- Laravel Fortify package and restricted feature configuration
- `users`, `password_reset_tokens`, and `profiles` identity migrations
- User and Profile models with Student registration
- Student-only registration action and email verification contract
- Login, logout, safe password reset, and email verification screens
- Own name and bio profile editing
- Forced temporary-password change middleware and page
- Local Administrator owner command with Windows DPAPI storage
- Login throttling and suspended-account blocking
- ActivityLog migration and model
- Role and account-status Actions with transaction and audit records
- UserPolicy and ActivityLogPolicy
- Role and active-account middleware
- Role-based login and password-change redirects
- Minimal Student, Instructor, and Administrator pages
- Administrator user search, filters, pagination, role forms, and status forms
- Read-only Administrator activity page
- `courses` table with Instructor ownership, unique slug, catalog indexes, and free/paid price checks
- `CourseLevel`, `CourseType`, and `CourseStatus` enums
- `Course` model, Instructor relationship, and `CourseFactory`
- `modules` and `lessons` curriculum tables with ordered parent-scoped constraints
- `ContentStatus` enum
- `Module` and `Lesson` models with Course → Module → Lesson relationships
- `ModuleFactory` and `LessonFactory`
- `learning_materials` metadata table with Lesson and uploader relationships
- `LearningMaterialType` enum
- `LearningMaterial` model and `LearningMaterialFactory`
- `CoursePolicy` with active-Instructor ownership checks
- Instructor Course list, create form, and read-only outline routes
- Server-generated unique Course slugs and private draft creation

The following capabilities are not implemented yet:

- Course management UI
- Enrollment
- Learning materials
- Progress
- Quizzes
- Certificates
- PayMongo
- Private file storage
- Deployment

### Approved Phase 2 specification

- Product display name: `IT Learning Hub`
- Laravel Fortify authentication with Blade views
- User and Profile identity records
- Student-only public registration
- Local Administrator owner: `Jayzee Bautista`
- Local-only owner bootstrap command
- DPAPI-protected temporary password
- Forced first-login password change
- Public email verification through Laravel's log mailer
- Own-profile editing for display name and bio
- PayMongo public test key stored only in the local ignored `.env`
- No payment package, route, table, checkout, or webhook

### Approved Phase 3 specification

- Reuse the existing UserRole and UserAccountStatus enums
- Add Administrator user search, role filters, status filters, and pagination
- Allow verified-target role assignment and account suspension/reactivation
- Reject self-role and self-status changes
- Protect the final active Administrator
- Store role/status changes and ActivityLog records in one transaction
- Add read-only Administrator activity records
- Add minimal authorized Student, Instructor, and Administrator pages
- Redirect login and forced-password-change completion by role
- Block suspended sessions on the next request
- No deletion, archive, bulk actions, multi-role accounts, or business modules

Phase 3 and Phase 4A code are human-approved. Phase 4B curriculum and material metadata is implemented and awaiting human review.

### Approved Phase 4A specification

- Add only the `courses` table in the first database slice
- Reuse the existing User identity for Instructor ownership
- Add `CourseLevel`, `CourseType`, and `CourseStatus` enums
- Default level to `beginner`, type to `free`, price to `0`, currency to `PHP`, and status to `draft`
- Enforce unique slugs, valid enum values, PHP currency, and free/paid price rules
- Keep slug, price, currency, status, publication time, and thumbnail path server-owned
- Add Course model, User ownership relationship, factory, and constraint tests
- No catalog UI, curriculum, enrollment, payments, uploads, or sample seeders

### Approved Phase 4B specification

- Add `modules`, `lessons`, and `learning_materials` tables
- Add `ContentStatus` and `LearningMaterialType` enums
- Reuse the existing Course and User identities
- Enforce positive, parent-scoped ordering and Lesson slug uniqueness
- Default Module and Lesson status to `draft`
- Default Lessons to required
- Keep storage metadata server-owned
- Do not add curriculum routes, uploads, private downloads, URL fetching, enrollment, payment, or sample seeders

### Approved Phase 5A specification

- Add Instructor Course list, create form, and read-only owned Course outline
- Add CoursePolicy ownership checks
- Create new Courses as private drafts
- Generate unique slugs on the server
- Display Course status, level, type, PHP price, and curriculum metadata
- Reject privileged fields
- No public catalog, enrollment, payment, upload, download, or curriculum mutation

Phase 5A code is human-approved. Phase 5B curriculum authoring is approved and starting.

### Approved Phase 5B specification

- Add Instructor-owned Module and Lesson creation forms
- Assign Module and Lesson positions on the server
- Generate unique Lesson slugs inside a Module
- Create draft curriculum content only
- Enforce ModulePolicy and LessonPolicy ownership
- No public catalog, enrollment, payment, upload, or download behavior

Phase 5B Module and Lesson authoring is human-approved.

### Approved Phase 5C specification

- Add Instructor-owned edit forms for Course, Module, and Lesson
- Keep owner, parent, position, status, currency, and slugs server-owned
- Keep the free and paid price rules identical for create and update
- Reject privileged fields on every update
- No delete, archive, reorder, publish, upload, enrollment, or payment behavior

Phase 5C Course, Module, and Lesson editing is human-approved.

### Approved Phase 5D specification

- Add Instructor-owned Learning Material metadata forms
- Support text, code, video link, and external link materials only
- Reject image, PDF, and document types until uploads exist
- Require content for text and code materials and a valid link for link materials
- Keep parent, uploader, position, and storage metadata server-owned
- No upload field, download route, delete, publish, enrollment, or payment behavior

Phase 5D Learning Material metadata authoring is implemented and awaiting human browser review.

### Deferred: delete and archive

Delete is deliberately deferred. Hard delete can destroy progress, grade, and payment history that does not exist yet. The planned safe default is status-based archiving in a later approved phase.

### Local environment check

Checked on September 25, 2026:

| Tool | Result | Readiness |
|---|---|---|
| PHP CLI | 8.5.8 | Ready for Laravel 13 |
| Required PHP extensions | curl, DOM, Fileinfo, Mbstring, OpenSSL, PDO, pdo_mysql, Tokenizer, XML | Ready |
| Composer | 2.10.3, official installer signature verified, diagnostics pass | Ready |
| Composer path | `C:\Users\Administrator\AppData\Roaming\Composer\bin` | Added to the current user PATH |
| Node.js | 24.18.0 | Ready |
| npm | 11.18.0 | Ready |
| MySQL Community Server | 8.4.11 LTS, official archive checksum and executable signature verified | Ready |
| MySQL service | `MySQL84-LMS`, automatic startup, `127.0.0.1:3307` | Ready |
| LMS databases | `lms` and `lms_test` | Ready |
| LMS database user | `lms_user` with database-scoped privileges | Ready |
| Credential storage | DPAPI-encrypted file under `C:\Users\Administrator\.secrets\lms-mysql.json` | Ready |
| XAMPP database | MariaDB 10.4.32 remains on port 3306 | Preserved and not used by the LMS |

Phase 1 environment prerequisites, the Laravel foundation, and the human-approved Phase 2, Phase 3, Phase 4A, Phase 4B, Phase 5A, Phase 5B, and Phase 5C slices are ready. Phase 5D Learning Material metadata authoring is implemented and awaiting browser review.

## 5. Documentation state

The documentation now uses one approved target architecture:

```text
Laravel 13
PHP 8.3 to 8.5
Blade
Tailwind CSS
MySQL 8.x
Laravel authentication
Laravel Policies and Gates
Laravel Events, Listeners, and Jobs
Laravel Storage
PayMongo
Composer
```

### Source of truth

| File | Purpose |
|---|---|
| `docs/plan.md` | Product scope and security rules |
| `docs/design.md` | Visual and interaction direction |
| `docs/architecture.md` | Laravel architecture and data design |
| `docs/folder-structure.md` | Target Laravel folder tree |
| `docs/development-roadmap.md` | Incremental build order |
| `docs/technology-choice.md` | Laravel decision and SIA1 rationale |
| `docs/glossary.md` | Beginner definitions |
| `docs/project-audit.md` | Current repository facts |

The earlier Next.js and Supabase planning direction was superseded by the approved Laravel and MySQL decision.

Before Phase 1, no application code was created when the documentation direction changed. The approved Laravel foundation is now implemented and documented separately from the LMS business scope.

## 6. `FOR_UI` audit

`FOR_UI/adminator (FOR USER DASHBOARD)` contains a compiled static dashboard distribution.

Observed characteristics:

- HTML pages
- Compiled CSS
- Compiled JavaScript bundles
- Static images and icon fonts
- Generic dashboard, table, form, calendar, chart, map, email, and authentication demonstrations
- Light and dark theme demonstration
- Responsive sidebar and mobile drawer demonstration

Useful reference areas:

- Authenticated dashboard shell
- Sidebar and header proportions
- Table layout
- Form layout
- Loading and empty-state examples
- Responsive navigation behavior
- Theme token approach

Problems:

- Files are compiled and difficult to maintain
- Business behavior is not real LMS behavior
- Authentication pages are demonstrations
- Data and analytics are not real records
- Branding and assets belong to the reference project
- Source maps and maintainable component code are not present

Rules:

- Never modify `FOR_UI`
- Never import its bundles as application logic
- Never copy its branding or assets without license review
- Use it only as a visual and interaction reference

## 7. Project support state

### `.agents/`

Contains installed skills and references for planning, security, testing, quality, UI, incremental work, and context engineering.

These files are reusable guidance. They are not product documentation.

### `.opencode/`

Contains the project-local `define-core-domains` skill and command.

Do not install additional Sauron components.

Do not rewrite installed skill files without explicit approval.

### `skills-lock.json`

Records installed skill sources and hashes.

Treat it as generated metadata.

### `opencode.json`

Configures OpenCode formatting, LSP support, and watcher exclusions for this repository.

It does not configure Laravel.

## 8. External reference repository audit

Reference:

```text
https://github.com/aliameenco-creator/lms-project-ali-amin-ai-web-development
```

Reference value:

- Course and curriculum concepts
- Student course-player concepts
- Instructor authoring concepts
- Administrator dashboard concepts
- High-level `src/` organization for a Next.js project

Rejected implementation areas:

- Public registration accepts role input
- Service-role registration route
- Browser-only role checks
- Automatic demo login
- Local-storage business data
- Manual payment approval
- RLS migration
- Duplicate schema files
- Mock identities
- Branding and assets
- Documentation and dependency inconsistencies

The repository does not include a clear license file. Code must not be copied without permission.

The final folder structure follows Laravel conventions and does not copy the reference `src/` tree.

## 9. Security posture

Phase 1 has the default Laravel web middleware, Blade escaping, CSRF protection, secure session cookie settings, local-only secrets, and a safe error view.

Phase 2 now includes these controls:

- Laravel Fortify authentication
- CSRF-protected registration, login, logout, reset, and verification flows
- Password hashing and 12-character password validation
- Login throttling
- Account-status checks
- Forced temporary-password change
- Own-profile authorization
- DPAPI-protected local bootstrap secret

Phase 3 now includes these controls:

- Role and active-account middleware
- UserPolicy checks for Administrator account management
- Self-change and last-Administrator safeguards
- Role/status ActivityLog records
- One transaction for each account change and audit record
- Read-only Administrator activity page

These later controls remain unimplemented until their approved phases:

- Policies and Gates for LMS resources
- Form Request validation for business forms
- Database constraints and transactions for later LMS workflows
- Private file disks
- Safe upload validation
- PayMongo signature verification
- Idempotent webhook processing
- Sanitized activity records for later domains

`APP_DEBUG=true` is limited to the local `.env`. Production must use `APP_DEBUG=false`.

The browser theme preference uses browser storage for display preference only. It does not store roles, prices, payment state, scores, completion, or ownership.

## 10. Quality posture

Phase 1, Phase 2, Phase 3, and Phase 4A quality checks exist:

- `php artisan test` with MySQL-backed feature tests
- `vendor/bin/pint --test`
- `composer audit`
- `npm audit`
- `npm run build`
- `php artisan route:list`
- Local MySQL migrations
- Blade, route, and configuration cache checks

The first full foundation run passed 7 tests and 26 assertions. After the Fortify dependency and identity slice, the suite passed 9 tests and 45 assertions. The completed Phase 2 authentication and profile slice passes 28 tests and 141 assertions, including a real Windows DPAPI round trip. The Phase 3 role and authorization slice passed 41 tests and 195 assertions. The Phase 4A Course foundation slice passed 55 tests and 235 assertions. The Phase 4B curriculum and material foundation slice passed 80 tests and 308 assertions. The Phase 5A Instructor Course Outline UI slice passed 93 tests and 359 assertions. The Phase 5B Module and Lesson authoring slice passed 104 tests and 406 assertions. The Phase 5C content editing slice passed 120 tests and 499 assertions. The Phase 5D Learning Material authoring slice passes 136 tests and 572 assertions. A first Phase 5D run exposed dropped controller imports that returned HTTP 500; the controller was rewritten in one pass and retested. Two earlier guards that blocked the material store route were updated to block the future upload route instead, because material metadata authoring is now approved. A first Phase 5C run exposed controller imports that were dropped while the methods were added, which returned HTTP 500; the imports were restored and retested. The free and paid price rules moved into `App\Support\CoursePrice` so create and update cannot drift apart. A first Phase 5B run exposed a missing policy import that returned HTTP 500 instead of creating a Module, and the import was fixed and retested. A first Phase 5B form check also showed that a failed lesson form lost its typed text, so the failed form now keeps its input and reopens. The first dependency pass found a missing PHP Fileinfo extension, which was enabled and retested. The Administrator user list now marks unverified accounts and explains that role changes unlock after email verification; the server-side rejection remains enforced. The shared authenticated header now exposes a visible Sign out form; the missing Administrator logout control was reproduced and fixed. A later cached-config run exposed a test database selection defect, which was fixed and retested. A first-run Blade check exposed an unconditional Vite manifest dependency, which was fixed and retested. A local `.env` owner-name value needed quoting and was fixed before the Phase 2 tests passed. Fortify’s default unknown-email reset response initially exposed account state, so a safe generic response was added and retested. The password-change middleware initially allowed the GET route but not the POST route, and the fix was retested. The owner test initially reused the real local secret path, so it now uses a unique temporary path. Registration normalization initially assumed missing fields were present, so missing-field validation now returns safe errors. Edge fallback review confirmed responsive auth layout, theme persistence, and keyboard-safe controls. The Phase 3 Administrator table initially caused mobile page overflow; stacked mobile cards fixed it and the 390px browser check was rerun. The first Phase 4A factory test exposed an array value in a text column; the factory now joins sentences before insertion. The Phase 5A Course list initially used an unsupported nested `withCount` relation; the controller now uses a supported Module count and the browser page passes.

CI and production deployment checks are not implemented yet.

## 11. Main risks

| Risk | Impact | Required response |
|---|---|---|
| Framework learning curve | Delayed implementation | Follow the beginner learning path and build vertical slices |
| Client-side authorization | Data exposure | Use Policies and server checks |
| Payment replay | Duplicate enrollment or access | Unique events, transactions, and idempotency |
| Unsafe uploads | Private file exposure | Private disks, generated paths, and validation |
| Large documentation | Confusion | Use the documentation map and one source of truth per concern |
| Scope growth | Delayed V1 | Enforce the V1 exclusion list |
| Reference copying | Security and license problems | Use concepts only and require clear permission |
| Payment assumptions | Broken integration | Check current official PayMongo documentation |
| Hidden business rules | Inconsistent code | Keep actions small and covered by tests |
| Unverified completion | Weak defense | Record tests, diagrams, and real screenshots |

## 12. Current approved decisions

- Laravel 13 with PHP 8.3 to 8.5
- Product display name: `IT Learning Hub`
- Blade and Tailwind CSS
- MySQL 8.x
- Laravel Fortify authentication
- Policies and Gates
- MySQL migrations and transactions
- Laravel private Storage
- PayMongo server integration
- One course owner
- Four enrollment states
- Three Quiz Attempts with no timer
- Automatic authenticated certificates
- Free and paid enrollment in V1
- Operational reports
- Private files and external video links
- No undocumented domains

## 13. Deferred decisions

- Hosting provider
- Managed database provider
- Production object storage
- Production queue driver
- Exact file-size limits
- Final institution name
- Certificate wording
- PayMongo methods, event names, retries, and amount-unit contract
- Payment expiry policy beyond explicit cancellation
- Course ownership transfer

Each deferred item has a safe planning default in `plan.md` and `architecture.md`.

## 14. Next approved milestone

The next milestone is human browser review of the implemented Phase 5D Learning Material metadata authoring.

Phase 5B, Phase 5C, and Phase 5D automated, build, and audit evidence is recorded. The desktop browser connector was unavailable in this session, so Edge fallback checks are used.

File uploads, downloads, delete or archive, publishing, public catalog, enrollment, and payments remain separate approved boundaries.

## 15. Audit conclusion

The repository now has a runnable Laravel 13 foundation, human-approved Phase 2, Phase 3, Phase 4A, Phase 4B, Phase 5A, Phase 5B, and Phase 5C slices, and a tested Phase 5D Learning Material metadata increment for IT Learning Hub.

The safe next step is human browser review of Phase 5D material authoring. Uploads, delete, public catalog, and payment work remain later phases.
