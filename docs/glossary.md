# Beginner glossary

Use this file when a technical term appears in the project documentation.

## Architecture

**Architecture**

The plan for how system parts connect and communicate.

**Client**

The browser used by a Student, Instructor, or Administrator.

**Server**

The Laravel application which receives requests, checks permissions, and returns responses.

**Monolith**

One application containing the pages, business logic, database access, and server endpoints. Laravel uses a monolithic architecture.

**API**

A defined way for one software system to request data or actions from another system. PayMongo is an external API.

**Webhook**

An HTTP request sent by an external provider when an event occurs. PayMongo can call the LMS after a payment event.

## PHP and Laravel

**PHP**

The server-side programming language used by the LMS.

**Composer**

The PHP dependency manager. It records installed packages in `composer.json` and `composer.lock`.

**Laravel**

The PHP framework used for routing, validation, authentication, database access, queues, storage, and security.

**Artisan**

The Laravel command-line tool. Commands begin with `php artisan`.

**Route**

A URL and the controller method responsible for handling it.

**Controller**

A class which receives a request, coordinates work, and returns a response. Controllers should remain small.

**Middleware**

Code which runs before or after a request. Authentication, role checks, throttling, and request logging use middleware.

**Form Request**

A Laravel class which validates and authorizes one type of form request.

**Service**

A class for reusable technical or external-service work, such as PayMongo communication.

**Action**

A class for one business operation, such as activating a paid enrollment or issuing a certificate.

**Provider**

A Laravel service provider which connects framework configuration and application services.

**Job**

A PHP class for work which can run in the background through a queue.

**Queue**

A system for deferring work, such as sending a receipt after a successful payment.

**Event**

A record of something important happening inside the application.

**Listener**

A class which reacts to an event.

## User interface

**Blade**

Laravel's server-rendered HTML template format. Blade files use `.blade.php`.

**View**

A Blade template used to build a page.

**Layout**

A shared page shell containing the header, navigation, theme control, and main content area.

**Blade component**

A reusable UI piece created with an X Blade component.

**Tailwind CSS**

A utility-first CSS framework used to build responsive layouts and reusable visual styles.

**Alpine.js**

A small optional JavaScript library for local UI interactions. Use it only when Blade and normal HTML are insufficient.

## Database

**MySQL**

The relational database used to store LMS records.

**Table**

A named collection of related records, such as `courses` or `enrollments`.

**Row**

One record in a table.

**Column**

One field in a table, such as `status` or `created_at`.

**Primary key**

A unique identifier for each record.

**Foreign key**

A database link between two tables.

**Relationship**

A connection between tables. One Student can have many enrollments.

**Eloquent**

Laravel's PHP model system for reading and writing database records.

**Model**

A PHP class representing one database table, such as `Course` or `Enrollment`.

**Migration**

A version-controlled PHP file which creates or changes a database table.

**Seeder**

A development or test class which inserts known records into the database.

**Query builder**

Laravel's helper for building SQL queries.

**Transaction**

A group of database operations which succeed together or roll back together.

**Index**

A database structure which improves selected query speeds.

**Constraint**

A database rule which protects valid data, such as a unique enrollment per Student and Course.

## Security

**Authentication**

The process of confirming who the user is. Laravel Fortify provides the Phase 2 authentication routes and server flows.

**Authorization**

The process of deciding what an authenticated user may do.

**Policy**

A Laravel class containing authorization rules for one model, such as `CoursePolicy`.

**Gate**

A Laravel authorization rule for an action or a simple global check.

**CSRF protection**

Protection against unauthorized requests created from another site. Laravel includes CSRF protection in web forms.

**Mass assignment protection**

A Laravel model feature which limits writable fields. Sensitive fields such as `role` must also be protected in the request and service layer.

**Hashing**

A one-way transformation used to protect passwords. Laravel includes password hashing tools.

**Encryption**

A reversible transformation used for sensitive data when a system must decrypt it.

**Least privilege**

Giving a user or process only the access required for its task.

**Private file**

A file served only after an authorized server request. A private file must not have a permanent public URL.

**Temporary URL**

A short-lived authorized link for a private file.

## LMS domains

**Profile**

The application identity linked to a Laravel User. A Profile stores the role, account status, bio, and temporary-password change state.

**Role**

The stored account type: `student`, `instructor`, or `administrator`.

**Role middleware**

Middleware that checks the signed-in user's role before a protected route runs. The server still repeats authorization inside the controller or action.

**Last active Administrator**

The final account with both the Administrator role and active status. Phase 3 prevents demoting or suspending this account.

**Activity log**

A read-only record of an approved administrative action. Phase 3 records role and account-status changes without passwords, tokens, IP addresses, or browser metadata.

**Initial Administrator**

The first local Administrator account created by the owner bootstrap command. The project does not add a separate Owner role.

**Temporary password**

A generated local bootstrap password protected by Windows DPAPI. The account must replace it before normal authenticated access.

**Course**

The academic learning offering owned by one Instructor.

**Course type**

The stored Course price category: `free` or `paid`. Free Courses use zero minor units; paid Courses use a positive amount.

**Course status**

The stored Course visibility state: `draft`, `published`, or `archived`. Only published Courses may enter the public catalog in a later phase.

**Content status**

The stored Module or Lesson state: `draft`, `published`, or `archived`. Phase 4B stores the state but does not expose curriculum publicly.

**Learning Material**

A Lesson resource described by metadata. Phase 4B stores text, link, or private-file metadata but does not upload or serve files.

**Minor units**

Integer money storage such as `100` for PHP 1.00. Phase 4A stores Course prices as `price_minor` with the `PHP` currency code.

**Module**

An ordered section inside a Course.

**Lesson**

An ordered learning activity inside a Module.

**Learning material**

A protected file, image, text resource, code sample, or approved external link attached to a Lesson.

**Enrollment**

The record which grants or denies a Student's access to a Course.

**Payment**

A separate record for one payment attempt linked to an Enrollment.

**Progress**

Persisted evidence of which Lessons a Student started or completed.

**Assessment**

A Quiz linked to a Course and optionally to a Module or Lesson.

**Attempt**

One Student's submission of one Quiz.

**Certificate**

A server-issued record created after verified Course completion.

**Activity log**

A sanitized record of an important administrative or business action.

## Payment

**PayMongo**

The external Philippine payment service selected for V1.

**Checkout**

The server-created payment request sent to PayMongo.

**Payment amount**

The exact course amount copied to a payment attempt as integer minor units, such as `49900` for `₱499.00`.

**Idempotency**

The ability to process the same payment event more than once without creating duplicate payments, enrollments, or access.

**Webhook signature**

A provider-generated value used to prove a webhook came from the expected sender and was not modified.

## Development

**Test**

Automated code which checks expected behavior.

**Feature test**

A Laravel test which sends requests through routes, middleware, controllers, and the database.

**Unit test**

A focused test for one class or business function.

**Factory**

A test helper which creates model records with valid test data.

**Fake**

A test replacement for an external dependency such as a payment client or file disk.

**Deployment**

Moving a tested application from local development to a hosted environment.

**Environment variable**

A configuration value kept outside source code, such as a database password or PayMongo secret.

**Production**

The live environment used by real users. `APP_DEBUG` must be `false`.
