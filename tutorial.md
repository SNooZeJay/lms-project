# IT Learning Hub Beginner Tutorial

This tutorial explains how to run the IT Learning Hub Laravel project on your Windows computer.

You do not need previous Laravel experience. Follow the sections in order.

## 1. What you can run right now

The current project includes:

- A public home page
- Student registration
- Login and logout
- Password reset
- Email verification
- A user profile page
- A profile name and bio editor
- A forced password change for the first Administrator login
- A local Administrator account
- Light and dark themes
- Role-based landing pages
- Administrator user search and role/status controls
- Read-only role and account-status activity records
- Course database foundation with Instructor ownership and safe defaults
- Instructor Course list, create form, and Course outline
- Instructor Module and Lesson authoring with server-assigned order
- Instructor editing for Course, Module, and Lesson
- Instructor Learning Material metadata for text, code, and link materials
- Instructor publish and unpublish for owned courses
- A public course catalog and public course details pages
- An enrollment database record with four documented states
- Free course enrollment and a student `My courses` page
- Student course and lesson reading for enrolled students
- A lesson progress record with three documented states

The following features are not built yet:

- Marking lessons complete and progress percentages
- Continue learning
- Paid enrollment and checkout
- Reordering, deleting, or archiving curriculum content
- File uploads and material downloads
- Full role-specific business dashboards
- Quizzes
- Certificates
- PayMongo checkout

This is normal. The project is being built one approved phase at a time.

## 2. Important local setup

This project uses:

- PHP 8.3 to 8.5
- Laravel 13
- Composer
- Node.js and npm
- MySQL Community Server on port `3307`

Important:

- XAMPP MariaDB is not used by this project.
- Do not change the database port to `3306`.
- The LMS database is `lms`.
- The automated test database is `lms_test`.
- The MySQL Windows service is usually named `MySQL84-LMS`.

## 3. Open the project folder

Open **PowerShell** or **Command Prompt**.

Change to the project folder:

```powershell
Set-Location 'C:\xampp\htdocs\lms-project'
```

You are in the right folder when this command works:

```powershell
Get-ChildItem
```

You should see files such as:

```text
artisan
composer.json
package.json
README.md
```

## 4. The fast daily startup

Use this section when the project is already installed.

### Step 1: Start MySQL

Check the MySQL service:

```powershell
Get-Service -Name 'MySQL84-LMS'
```

The status should be `Running`.

If it is stopped, start it from Windows Services or run:

```powershell
Start-Service -Name 'MySQL84-LMS'
```

### Step 2: Clear old Laravel cache

Run this from the project folder:

```powershell
php artisan optimize:clear
```

This clears old configuration, route, view, and cache files. It is safe to run.

### Step 3: Start Laravel

```powershell
php artisan serve
```

Keep this PowerShell window open while you use the application.

Open this address in your browser:

```text
http://127.0.0.1:8000
```

To stop Laravel, return to PowerShell and press:

```text
Ctrl + C
```

## 5. First-time installation

Use this section only when you are setting up the project for the first time.

### Step 5.1: Check your tools

Run:

```powershell
php --version
composer --version
node --version
npm --version
```

The project expects PHP 8.3 or newer, Node.js 22.12 or newer, and npm 10 or newer.

If PowerShell says `composer` is not recognized, close PowerShell and open a new one. Then try again.

You can also use the full Composer path:

```powershell
& "$env:APPDATA\Composer\bin\composer.bat" --version
```

### Step 5.2: Install PHP packages

```powershell
composer install
```

This reads `composer.json` and installs the PHP packages listed in `composer.lock`.

### Step 5.3: Create the environment file

Laravel reads local settings from a file named `.env`.

If the file does not exist, create it:

```powershell
Copy-Item .env.example .env
```

If `.env` already exists, do not overwrite it.

Open the file for editing:

```powershell
notepad .env
```

At minimum, check these values:

```dotenv
APP_NAME="IT Learning Hub"
APP_ENV=local
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=lms
DB_USERNAME=lms_user
DB_PASSWORD=your-local-database-password
```

Use the database password for your own computer. Do not paste a real password into Git, chat, screenshots, or documentation.

For the local Administrator, also set:

```dotenv
OWNER_NAME="Jayzee Bautista"
OWNER_EMAIL="bautista.jayzee@ncst.edu.ph"
OWNER_SECRET_PATH='C:\Users\YourWindowsUser\.secrets\lms-owner.json'
```

Replace `YourWindowsUser` with your actual Windows username.

The owner secret path must be outside the project folder.

The local PayMongo public key is also kept in `.env` when the payment phase begins. Never put secret PayMongo keys in `.env.example` or Git.

### Step 5.4: Generate the application key

If `APP_KEY` is empty in `.env`, run:

```powershell
php artisan key:generate
```

This creates the encryption key used by Laravel.

Do not generate a new key every day. Changing `APP_KEY` can log out existing sessions and can make encrypted local data unreadable.

### Step 5.5: Run database migrations

```powershell
php artisan migrate
```

Migrations create and update database tables.

This command creates the framework tables and the current identity tables, including:

- `users`
- `password_reset_tokens`
- `profiles`
- `sessions`
- `cache`
- `jobs`

It does not create courses, payments, quizzes, or certificates yet.

Check the migration status:

```powershell
php artisan migrate:status
```

### Step 5.6: Install frontend packages

```powershell
npm install
```

This reads `package.json` and installs the frontend packages from `package-lock.json`.

### Step 5.7: Build frontend assets

```powershell
npm run build
```

Laravel loads the generated files from `public/build`.

If a page has no CSS or JavaScript, run this command again.

### Step 5.8: Create the local Administrator

Run:

```powershell
php artisan owner:bootstrap
```

This command creates the configured Administrator only in the local environment.

The command prints the path of the DPAPI-protected secret file. It does not print the password during normal setup.

The command is safe to run again. If the Administrator already exists, it does not silently change the password or promote another account.

### Step 5.9: Start the application

```powershell
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

## 6. Administrator login

The local Administrator account is:

```text
Name: Jayzee Bautista
Email: bautista.jayzee@ncst.edu.ph
Role: Administrator
```

To display the temporary password on your own local computer, run:

```powershell
php artisan owner:bootstrap --show-password
```

Read the password from your local terminal and do not send it to anyone.

Sign in at:

```text
http://127.0.0.1:8000/login
```

The first login redirects to:

```text
http://127.0.0.1:8000/account/password
```

This is intentional. The temporary password must be changed before the account can open the normal profile page.

After changing the password, you can open:

```text
http://127.0.0.1:8000/account/profile
```

## 7. Try the Phase 3 role pages

After the Administrator password change, Laravel sends the account to:

```text
http://127.0.0.1:8000/admin
```

The Administrator page links to:

```text
http://127.0.0.1:8000/admin/users
http://127.0.0.1:8000/admin/activity
```

On the user-management page, you can:

- Search by name or email
- Filter by role or account status
- Assign Student, Instructor, or Administrator
- Suspend an account
- Reactivate an account

The activity page is read-only. It shows role and account-status changes. It does not show passwords, tokens, IP addresses, or browser metadata.

A user cannot change their own role or status. The final active Administrator is protected from accidental demotion or suspension.

Role-based pages are also available:

```text
/student
/instructor
/admin
```

Each page shows the current signed-in role and account status. Pages for other roles return a safe `403` response.

Phase 4A currently adds the Course database foundation only. Phase 4B adds Module, Lesson, and Learning Material database records. Phase 5A adds Instructor Course pages for browser review. There is still no public `/courses` catalog, enrollment, payment, upload, or curriculum authoring page.

## 8. Review the Instructor Course UI

Start the local server:

```powershell
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

To review the Instructor pages:

1. Sign in as the Administrator.
2. Open `/admin/users`.
3. Promote a test Student account to Instructor.
4. Sign out and sign in as that Instructor.
5. Open `/instructor/courses`.
6. Select **Create course**.
7. Enter a title such as `Browser Review Course`.
8. Keep the type as `Free` and the price as `0`.
9. Submit the form.
10. Review the private draft Course outline.

The outline page now lets you add Modules and Lessons. There is no edit, reorder, upload, or publish control yet.

There is still no public course catalog, enrollment, payment, upload, or download workflow.

## 9. Review the Instructor curriculum authoring

Sign in as the Instructor and open one of your Courses.

1. Open `/instructor/courses`.
2. Select a Course you own.
3. Scroll to **Add module**.
4. Enter a title such as `Module One` and an optional description.
5. Select **Add private module**.
6. Confirm the new Module appears as `Module 1` with the status `Draft`.
7. Expand **Add lesson** inside that Module.
8. Enter a title, summary, lesson content, and estimated minutes.
9. Leave **Required lesson** checked to make the Lesson required.
10. Select **Add private lesson**.
11. Confirm the new Lesson appears as `Lesson 1` with the status `Draft`.
12. Add a second Module and a second Lesson and confirm the order keeps increasing.
13. Add two Lessons with the same title and confirm both are saved.
14. Submit an empty title and confirm the page shows an error and keeps your typed text.

Every Module and Lesson starts as a private draft. Order, status, and Lesson slugs are always set by the server.

## 10. Review the Instructor editing

Sign in as the Instructor and open a Course you own.

1. Open `/instructor/courses` and select a Course.
2. Select **Edit course details**.
3. Change the title, description, category, level, or price.
4. Select **Save course details** and confirm the outline shows the new values.
5. Confirm the course address in the read-only box did not change.
6. Select **Edit module** on a Module, change the title, and save.
7. Confirm the Module kept its position and `Draft` status.
8. Select **Edit lesson** on a Lesson, change the content, minutes, or required state, and save.
9. Confirm the Lesson kept its position, status, and address.
10. Try to set a price above `0` on a `Free` Course and confirm the page shows an error.
11. Submit an empty title and confirm the page shows an error and keeps your typed text.
12. Check each edit page at 390px width.

There is no delete, archive, reorder, publish, or upload control. Deleting content is on purpose for a later phase.

## 11. Review the Instructor material authoring

Sign in as the Instructor and open a Course with a Lesson.

1. Open `/instructor/courses` and select a Course that has a Lesson.
2. Inside the Lesson, select **Add material**.
3. Choose `Text`, enter a title, and type the material content.
4. Select **Add material** and confirm the new material shows as `Material 1 · Text`.
5. Repeat with `Code` and confirm the content is kept.
6. Repeat with `External link` and a full link such as `https://www.php.net/manual/en/`.
7. Try `Video link` with an empty link. Confirm the page shows an error.
8. Try `Text` with empty content. Confirm the page shows an error.
9. Confirm the type list only offers Text, Code, Video link, and External link.
10. Select **Edit material**, change the title or link, and save.
11. Confirm the material number, position, and uploader stayed the same on the edit page.
12. Submit an empty title and confirm the error summary appears and your text is kept.
13. Check the Lesson area at 390px width.

File uploads are not built yet, so there is no file field and no download button.

## 12. Review the Instructor publishing

Sign in as the Instructor.

1. Open `/instructor/courses`.
2. Create a Course and open it without adding a Module.
3. Select **Publish course**. Confirm the page explains that a Module is missing.
4. Confirm the Course is still `Draft`.
5. Add a Module but no Lesson, then select **Publish course** again. Confirm the message mentions a Lesson.
6. Add a Lesson to that Module.
7. Select **Publish course**. Confirm the status becomes `Published`.
8. Confirm the Module and Lesson statuses also became `Published`.
9. Confirm the outline shows the first published time.
10. Confirm the button changed to **Unpublish course**.
11. Select **Unpublish course**. Confirm the status returns to `Draft` and the content returns to `Draft`.
12. Confirm the first published time is still shown.
13. Open `/instructor/courses` and confirm each row shows the right control.
14. Check the list and outline at 390px width.

Nothing is public yet. The public catalog is the next phase.

## 13. Review the public course catalog

You do not need to sign in for this part. Open a private window so you are not signed in.

1. Open `http://127.0.0.1:8000/courses`.
2. Confirm your published course appears and your draft course does not.
3. Select a course to open its public details page.
4. Confirm the page shows the title, description, objectives, level, type, price, and Instructor name.
5. Confirm the outline lists Module titles and Lesson titles with minutes and `Required` or `Optional`.
6. Confirm the page does not show Lesson content, Lesson summaries, or material links.
7. Go back and use the search box. Confirm the list narrows.
8. Use the category, level, and type filters. Confirm the list narrows.
9. Type a nonsense filter such as `?level=not-a-level` in the address bar. Confirm the page still loads.
10. Search for `' OR 1=1--`. Confirm no error and an empty result message.
11. Confirm a `Free` course shows the word `Free` and a paid course shows a `PHP` price.
12. Sign in as the Instructor, unpublish the course, then open `/courses` again. Confirm the course is gone.
13. Open the draft course address directly. Confirm the page shows `404`.
14. Sign in again, publish the course, and confirm it returns to the catalog.
15. Check the catalog and details page at 390px width.

## 14. Review the enrollment database record

This slice adds no page. It adds a database table, so review it with commands.

1. Open PowerShell in the project folder.
2. Run `php artisan migrate:status` and confirm the enrollment migration shows `Ran`.
3. Run `php artisan db:table enrollments --database=mysql` if the command is available, or use your database tool.
4. Confirm the table has `student_id`, `course_id`, `status`, `activated_at`, `completed_at`, `cancelled_at`, and `last_accessed_at`.
5. Confirm `status` allows only `pending_payment`, `active`, `completed`, and `cancelled`.
6. Run `php artisan test --filter=Phase6A` and confirm the enrollment foundation tests pass.
7. Run `php artisan migrate:rollback --step=1` and confirm the table is removed.
8. Run `php artisan migrate` and confirm the table is created again.

## 15. Review the free enrollment

Use a private window as a Student. You need a published **free** course first.

1. Sign in as the Instructor, open `/instructor/courses`, and confirm at least one free course is published.
2. Sign out. Open the Student account you created earlier and verify the email if needed.
3. Open `/student`. Confirm the **My courses** and **Browse catalog** buttons.
4. Open `/courses` and select the published free course.
5. Confirm the page shows an **Enroll free** button.
6. Select **Enroll free**. Confirm you land on `/student/courses`.
7. Confirm your course appears with the status `Active` and today's date.
8. Select the course in the catalog again. Confirm the button changed to **Enrolled**.
9. Press the enroll button again by reloading the page. Confirm only one record exists.
10. Create a second Student account and confirm that Student does not see your course in **My courses**.
11. Open a paid course while signed in. Confirm it shows that paid enrollment opens later and has no enroll button.
12. Sign in as the Instructor and open a public course page. Confirm no enroll control appears.
13. Ask an Administrator to suspend your Student account, then try to enroll. Confirm you are signed out.
14. Check `/student/courses` and the course page at 390px width.

Lesson content still does not open after enrolling. That is the next phase.

## 16. Review the student lesson reading

Use the Student account that is already enrolled in a published free course.

1. Open `/student/courses`. Confirm your course card shows **Open course**.
2. Select **Open course**. Confirm you see the course title, description, and Instructor.
3. Confirm the outline lists the published Modules and Lessons with `Required` or `Optional` and minutes.
4. Open a Lesson. Confirm you see the title, summary, and the lesson content.
5. Confirm the **Learning materials** section shows any text, code, and link materials.
6. Open a link material and confirm it opens in a new tab.
7. Copy a lesson address, then sign out and open it. Confirm you are asked to sign in.
8. Sign in as a different Student with no enrollment and open that address. Confirm a `403` page.
9. Sign in as the Instructor and open the same student address. Confirm a `403` page.
10. Sign in as the Student again, open the lesson, then ask the Instructor to unpublish the course.
11. Confirm **My courses** shows the course with the note that it is not published.
12. Confirm **Open course** still works, while the public page returns `404`.
13. Ask the Instructor to publish again and confirm the public page returns.
14. Check the course and lesson pages at 390px width.

Progress tracking is not built yet, so nothing is marked complete. That is the next section.

## 17. Review the lesson progress record

This slice adds no page. It adds a database table, so review it with commands.

1. Open PowerShell in the project folder.
2. Run `php artisan migrate:status` and confirm the lesson progress migration shows `Ran`.
3. Open the `lesson_progress` table in your database tool.
4. Confirm it has `enrollment_id`, `student_id`, `lesson_id`, `status`, `started_at`, `completed_at`, and `last_viewed_at`.
5. Confirm `status` allows only `not_started`, `in_progress`, and `completed`.
6. Run `php artisan test --filter=Phase6D` and confirm the foundation tests pass.
7. Run `php artisan migrate:rollback --step=1`, confirm the table is removed, then run `php artisan migrate` again.

## 18. Create a Student account

Use a separate browser or private window if you want to keep the Administrator session.

1. Open `http://127.0.0.1:8000/register`.
2. Enter a name, email, and password.
3. Confirm the password.
4. Submit the form.
5. Laravel creates a Student account.
6. Laravel asks you to verify the email address.

The registration form has no role selector. Public registration cannot create an Administrator.

## 19. Find email verification and reset links

The local development environment uses Laravel's log mailer.

This means email messages are not sent through Gmail or another mail provider. They are written to the local Laravel log.

Open the recent log lines:

```powershell
Get-Content '.\storage\logs\laravel.log' -Tail 100
```

Search for verification or reset links:

```powershell
Select-String -Path '.\storage\logs\laravel.log' -Pattern 'email/verify|reset-password'
```

Copy the local link into the browser.

The log is local. Do not upload or share `storage/logs/laravel.log` because it can contain private links and account details.

## 20. Run the frontend development server

Use this when you are changing CSS or JavaScript.

Keep Laravel running in the first PowerShell window:

```powershell
php artisan serve
```

Open a second PowerShell window in the project folder and run:

```powershell
Set-Location 'C:\xampp\htdocs\lms-project'
npm run dev
```

Vite watches the frontend files and updates the browser when files change.

For normal work, you can stop Vite with `Ctrl + C`. You can also build the final local assets with:

```powershell
npm run build
```

## 21. Run the automated checks

Run these commands from the project folder.

### Tests

```powershell
php artisan test
```

The tests use the separate `lms_test` database. They do not use the normal `lms` database for test data.

### PHP formatting

```powershell
vendor\bin\pint --test
```

Pint checks the PHP code style without changing files.

### PHP syntax

```powershell
Get-ChildItem app,bootstrap,config,database,routes,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

### Dependency security

```powershell
composer audit
npm audit
```

### Frontend production build

```powershell
npm run build
```

### Route list

```powershell
php artisan route:list
```

This shows all registered URLs and their controller or action.

### All Laravel caches

```powershell
php artisan optimize:clear
```

## 22. Useful commands

| Command | What it does |
|---|---|
| `php artisan about` | Shows Laravel application information |
| `php artisan route:list` | Shows application routes |
| `php artisan migrate` | Applies database migrations |
| `php artisan migrate:status` | Shows migration status |
| `php artisan optimize:clear` | Clears Laravel caches |
| `php artisan test` | Runs automated tests |
| `php artisan serve` | Starts the local web server |
| `php artisan owner:bootstrap` | Creates or checks the local Administrator |
| `npm run dev` | Starts the Vite development server |
| `npm run build` | Builds frontend assets |

## 23. Troubleshooting

### `composer` is not recognized

Close the current terminal and open a new one. Then run:

```powershell
composer --version
```

If it still fails, use:

```powershell
& "$env:APPDATA\Composer\bin\composer.bat" --version
```

Use the full Composer path for the other Composer commands when needed.

### `php artisan serve` says the port is already in use

Use another port:

```powershell
php artisan serve --port=8001
```

Then update `APP_URL` in `.env`:

```dotenv
APP_URL=http://127.0.0.1:8001
```

Clear the cached configuration:

```powershell
php artisan optimize:clear
```

### Database connection refused

Check the service:

```powershell
Get-Service -Name 'MySQL84-LMS'
```

Check `.env`:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=lms
DB_USERNAME=lms_user
DB_PASSWORD=your-local-database-password
```

Do not use port `3306` for this project. That port belongs to the separate XAMPP MariaDB setup.

### `database not found`

The local setup expects these databases to exist:

```text
lms
lms_test
```

Ask the person who prepared the local MySQL setup to create them. The application user `lms_user` must have permission to use both databases.

### CSS or JavaScript is missing

Run:

```powershell
npm install
npm run build
php artisan optimize:clear
```

Then refresh the browser with a hard refresh:

```text
Ctrl + F5
```

### Email verification says to check the email

The local app does not use a real mail server during development.

Open the local log and search for the verification link:

```powershell
Select-String -Path '.\storage\logs\laravel.log' -Pattern 'email/verify'
```

### Owner command says the environment is not local

Check `.env`:

```dotenv
APP_ENV=local
```

Then run:

```powershell
php artisan optimize:clear
php artisan owner:bootstrap
```

The owner bootstrap command is intentionally blocked outside the local environment.

### Owner command says the secret file already exists

Do not delete the file blindly. First try:

```powershell
php artisan owner:bootstrap --show-password
```

If you no longer need the local Administrator, ask before removing the protected file or changing the account.

## 24. Project folder guide

These are the folders you will use most often:

| Folder | Purpose |
|---|---|
| `app/` | PHP application code |
| `config/` | Laravel configuration |
| `database/` | Migrations, factories, and seeders |
| `resources/views/` | Blade HTML templates |
| `resources/css/` | Tailwind CSS entry file |
| `resources/js/` | Browser JavaScript |
| `routes/` | URL definitions |
| `tests/` | Automated tests |
| `storage/` | Logs, sessions, cache, and local runtime files |
| `public/` | Public web files and generated frontend assets |
| `docs/` | Project requirements and architecture |

Do not edit files in `vendor/`, `node_modules/`, or `public/build/` by hand. They contain installed or generated files.

`FOR_UI/adminator (FOR USER DASHBOARD)` is a read-only visual reference. It is not the application source code.

## 25. Security rules

Keep these rules in mind:

- Never commit `.env`.
- Never share `DB_PASSWORD`, `APP_KEY`, or secret PayMongo keys.
- Never paste real passwords into documentation or screenshots.
- Keep the owner secret path outside the project.
- Use `php artisan owner:bootstrap --show-password` only on your local computer.
- Keep `MAIL_MAILER=log` for local demonstrations.
- Do not use a real payment secret until the payment architecture phase is approved.

## 26. Recommended beginner order

When you are learning the project, use this order:

1. Run the project using this tutorial.
2. Open the home page and authentication pages.
3. Sign in as the local Administrator.
4. Change the temporary password.
5. Edit the profile name and bio.
6. Register a Student account.
7. Read `docs/plan.md` for requirements.
8. Read `docs/architecture.md` for the technical design.
9. Review the Instructor Course pages as an Instructor.
10. Add one Module and one Lesson to your own Course.
11. Edit the Course, the Module, and the Lesson.
12. Add a text material and a link material to a Lesson.
13. Publish the Course, then unpublish it.
14. Open `/courses` in a private window and browse the published Course.
15. Run `php artisan migrate:status` and confirm the enrollment migration ran.
16. Enroll in a published free course as a Student and check `/student/courses`.
17. Open an enrolled lesson and read its content and materials.
18. Run `php artisan migrate:status` and confirm the lesson progress migration ran.
19. Mark a Lesson complete and watch the percentage change.
20. Read the relevant test before changing a feature.
21. Run `php artisan test` before and after your change.

You do not need to understand the whole Laravel framework before running the application. Start with the commands in this tutorial, then inspect one small feature at a time.

## 27. Review the lesson progress interface

Use the Student account that is already enrolled in a published free course. You need at least two Lessons for the percentages to be interesting.

1. Open `/student/courses`. Confirm the card shows a **Progress** value such as `0%`.
2. Note the percentage. Write it down.
3. Open the course. Confirm the course page shows **Progress: 0%** and a line such as `0 of 2 required published lessons completed`.
4. Confirm the outline lists the Lessons with `Required` or `Optional`, and no `Completed` badge yet.
5. Open the first Lesson.
6. Confirm the page has a **Your progress** heading and a **Mark as complete** button.
7. Reload the Lesson page. Confirm the button is still there. Opening a Lesson never completes it.
8. Select **Mark as complete**.
9. Confirm you return to the same Lesson and a green message says `Lesson marked as complete.`
10. Confirm the **Completed** badge shows the completion date and the **Mark as complete** button is gone.
11. Select **Mark as complete** again from a direct form post. Confirm nothing breaks and the original completion date stays.
12. Open the course page. Confirm the percentage increased and the finished Lesson now has a `Completed` badge.
13. Complete the second required Lesson. Confirm the percentage reaches `100%`.
14. Confirm **My courses** shows the same percentage.
15. Copy a lesson address and its `complete` address. Sign out and open each one. Confirm you are asked to sign in.
16. Sign in as a different Student with no enrollment. Open the lesson. Confirm a `403` page.
17. Sign out. Sign in as the Instructor. Open the same lesson address. Confirm a `403` page.
18. Sign in as the Student again, then ask the Instructor to unpublish the course.
19. Confirm the course page says **Progress is hidden** and explains that completed lessons are kept.
20. Confirm **My courses** shows `Hidden` in the Progress row.
21. Ask the Instructor to publish again. Confirm the percentage returns without losing any completion.
22. Open the `lesson_progress` table in your database tool. Confirm one row per Lesson you opened, with the correct `status`, `started_at`, `completed_at`, and `last_viewed_at`.
23. Check the course page and the Lesson page at 390px width. Confirm nothing is cut off.

The percentage is only a count. It comes from the `lesson_progress` table, so it never comes from the browser.

## 28. Review curriculum reorder, archive, and private files

Use the Instructor account that owns a course with at least two Modules and at least two Lessons in one Module.

1. Sign in as the Instructor and open the course outline.
2. Confirm a **Reorder modules** section exists with one position field per Module.
3. Change the first Module to the highest number and the last Module to the lowest. Select **Save module order**.
4. Confirm the outline order changed and each Module now shows `Module 1`, `Module 2`, and so on with no gaps.
5. Open **Reorder lessons** inside a Module. Swap two Lessons and save.
6. Confirm the Student course page shows the new Lesson order.
7. Enter the same position twice and save. Confirm a plain error appears and the old order is unchanged.
8. Archive one Module. Confirm it says **Archived**, its Lessons disappear from the Student course page, and a **Restore module** button appears.
9. Confirm the Student cannot open a Lesson inside the archived Module.
10. Restore the Module. Confirm it comes back as `Draft` and the Instructor must publish again before a Student sees it.
11. Attach a PDF to a Lesson using the **Add material** form. Confirm a material row appears with a type and a size.
12. Open the file link in the address bar directly. Confirm the bytes never come back.
13. Try to upload `payload.exe`. Confirm a plain error and nothing saved.
14. Sign in as an enrolled Student and open the Lesson. Confirm a **Download** button appears and the file downloads.
15. Copy a download address and open it while signed out. Confirm you are asked to sign in.

The stored path never contains the filename you uploaded, and no public route serves it.

## 29. Review Continue Learning

Use a Student who has opened at least one Lesson.

1. Sign in as the Student and open the dashboard.
2. Confirm a **Continue learning** panel names the most recently opened Lesson, its Module, its Course, and how long ago it was opened.
3. Select **Resume**. Confirm the same Lesson opens.
4. Open a different Lesson in another course. Return to the dashboard. Confirm the panel now points at that Lesson.
5. Ask the Instructor to unpublish that course. Return to the dashboard. Confirm the panel disappears and shows **No lessons opened yet**.
6. Confirm you can still open the Lesson itself while the enrollment is active.
7. Sign in as a brand new Student. Confirm the dashboard shows **No lessons opened yet** and offers **Open My courses**.
8. Sign in as an Instructor and an Administrator. Confirm neither dashboard shows the Student panel.

The panel never points at a Lesson you cannot open. The rule is re-checked every time the page loads.

## 30. Review quizzes

Use an Instructor who owns a published course, and a Student enrolled in it.

1. Sign in as the Instructor. On the outline, open **Add quiz** and create a quiz with a passing score and a maximum attempt count.
2. Open **Questions and options** and add a question with two options, marking exactly one correct.
3. Try to mark two options correct. Confirm a plain error and nothing saved.
4. Select **Publish quiz**. Confirm the quiz says `Published`.
5. Sign in as the Student. Open the course page. Confirm the quiz says `Not attempted`.
6. Open the quiz. Confirm the question and both options are visible, and confirm the page does **not** show which answer is correct.
7. Select **Start attempt 1**. Confirm you land on the answer form.
8. Answer the question correctly and submit. Confirm the result says `Passed` with a percentage and shows the correct answer.
9. Open the quiz again. Confirm it says you already passed and offers no new attempt.
10. Ask the Instructor to archive the quiz. Confirm the Student can no longer open it, and that earlier attempts are kept.
11. Sign in as a different Student with no enrollment and open the quiz address. Confirm you are refused.
12. In the browser console, try to post a submission with `passed: true` by hand. Confirm the request is rejected and the attempt state does not change.

Grading happens on the server only. The browser never sends a score.

## 31. Review completion and certificates

Use a Student who has finished every required Lesson in an enrolled course.

1. Open the course page. Confirm the **Certificate** panel either offers **Claim certificate** or lists exactly what is still missing.
2. While a required Lesson is incomplete, confirm the panel names that requirement in words.
3. Complete everything. Confirm the panel offers **Claim certificate**.
4. Select it. Confirm the certificate page shows your name, the course name, the issue date, a code such as `ITH-XXXX-XXXX-XXXX-XXXX`, and the status `Valid`.
5. Confirm the page states that it is only visible to you while you are enrolled.
6. Sign out. Open the certificate address. Confirm you are asked to sign in.
7. Sign in as another Student and open the same address. Confirm you are refused.
8. Sign in as the Administrator, open **Manage certificates**, revoke the certificate with a reason, then reissue it.
9. Confirm the replacement is linked to the revoked one and has a different code.
10. Add a new required Lesson, then try to reissue. Confirm it is refused until the requirement is met again.

Revoking keeps the record. Nothing is deleted.

## 32. Review payments

Payments need real PayMongo test-mode credentials. Until then, review the flow without paying.

1. Set `PAYMONGO_ENABLED=true`, `PAYMONGO_SECRET_KEY`, and `PAYMONGO_WEBHOOK_SECRET` in your local `.env`, then restart the server.
2. Enroll in a published paid course. Confirm the enrollment state is `pending_payment` and the course is not open.
3. Confirm **My courses** shows a **Pay** button with the exact amount and currency.
4. Select it. Confirm the return page says **Waiting for payment confirmation** and states plainly that the page does not confirm payment by itself.
5. Complete a test-mode payment on the provider page.
6. Confirm the provider sends a webhook, the payment becomes `paid`, and the enrollment becomes `active`.
7. Replay that webhook by resending it. Confirm the record count does not change and the enrollment stays `active`.
8. Change one character in the webhook body but keep the old signature. Confirm the event is recorded as ignored and nothing changes.
9. Check the `payments` table. Confirm no secret value is stored in any column.
10. Run `php artisan lms:check-production`. Confirm it refuses to pass while payments are disabled with keys present, or enabled with keys missing.

## 33. Review dashboards and reports

1. Sign in as a Student. Confirm the four counts match the database: courses enrolled, lessons completed, quizzes passed, and certificates earned.
2. Sign in as an Instructor. Confirm the counts cover only your own courses, and that another Instructor's course never appears.
3. Confirm the Instructor dashboard lists your courses with an enrollment count and an **Open outline** link.
4. Sign in as the Administrator. Confirm the counts cover the whole system.
5. Open **View reports**. Confirm one row per enrollment with a student, course, state, quizzes passed, and amount paid.
6. Confirm a row with no paid payment says **Not paid** rather than a zero amount.
7. Sign in as a Student and a non-Administrator Instructor and open the report address. Confirm you are refused.
8. Widen the browser to 390px and open the report. Confirm the table scrolls inside its own area and the page does not widen.

## 34. Review the production pre-flight check

1. Run `php artisan lms:check-production` on your local machine.
2. Confirm it lists each check with `PASS` or `FAIL` and exits with a failure code on a development server.
3. Confirm the failing checks are named in plain words and the command points at `docs/deployment.md`.
4. Confirm no secret value is printed anywhere in the output.
5. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`. Confirm each succeeds.
6. Open the site again. Confirm it still loads with the caches in place.
7. Run `php artisan optimize:clear` so local `.env` changes take effect again.

On a real server, every line must read `PASS` before you announce the release.

## 35. Share your site over the internet for free

Everything so far runs only on your own computer. Other people cannot see it.
This section makes your site reachable from anywhere in the world, for free.

It works by running a small program called **cloudflared** on your computer. That
program opens an outgoing connection to Cloudflare's servers and asks them to
forward visitors to your local address. Cloudflare then gives you a public web
address.

**Names used in this section:**

- **A tunnel** is that forwarding connection. It runs no code of its own.
- **Your computer is the server.** If you turn your computer off, the site goes
  off too. This is not hosting. It is a window.
- **A public address** (also called a URL) is the web link visitors type, such as
  `https://example.trycloudflare.com`.

### Step 35.1: Install cloudflared

Download this file and save it as `cloudflared.exe`:

```
https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe
```

Put it in a folder you can find again. This tutorial uses
`C:\Users\YourName\AppData\Local\cloudflared\cloudflared.exe`.

Check it worked. Open PowerShell and run:

```powershell
& "$env:LOCALAPPDATA\cloudflared\cloudflared.exe" --version
```

You should see a version number. An example, not your real output: `cloudflared
version 2026.9.3`.

### Step 35.2: Start everything

Three things must be running at the same time. Each one needs its own PowerShell
window.

1. **MySQL.** Start it from the XAMPP Control Panel as in section 4.
2. **Laravel.**

   ```powershell
   cd C:\xampp\htdocs\lms-project
   php artisan serve
   ```

3. **The tunnel.**

   ```powershell
   & "$env:LOCALAPPDATA\cloudflared\cloudflared.exe" tunnel --url http://127.0.0.1:8000 --no-autoupdate
   ```

Wait about twenty seconds. Cloudflare prints a line like this. The exact address
will be different for you:

```
https://some-words-here.trycloudflare.com
```

**That address is your public site.** Anyone who opens it sees your LMS.

### Step 35.3: Check it works

Open the printed address in your browser. You should see the IT Learning Hub home
page.

Then check it from a device that is **not** on your Wi-Fi, because your own
computer can often reach itself in ways other devices cannot. The easiest test
is your phone with mobile data turned on and Wi-Fi turned off.

If it loads there, other people can reach it too.

### What the quick tunnel does not do

- **The address changes every time you restart it.** If you stop and start
  cloudflared, you get a different address. Send people the new one.
- **Your computer must stay on and awake.** See section 37.
- It is free, needs no account, and has no time limit.

## 36. Get an address that never changes

The address from section 35 changes each restart, which is inconvenient when
you are sharing a link. A **named tunnel** gives you one fixed address, such as
`https://lms.yourdomain.com`.

**The tunnel itself is free**, on Cloudflare's free plan, with no trial and no
expiry. The only cost is a **domain name** if you do not already own one. A
domain is the web address of a website, such as `yourdomain.com`, and costs
around ten dollars a year.

So:

- If you **already own a domain**, this costs nothing.
- If you **do not own one**, keep using the free quick tunnel from section 35.
  It is genuinely good enough for a demonstration.

### If you own a domain

**Step 1. Add the domain to Cloudflare, free.**

In your Cloudflare account choose **Add a site**, enter your domain, and follow
the instructions. Cloudflare will give you two nameserver addresses. At the
company where you bought the domain, replace the existing nameservers with
those two. DNS propagation, which is the process of the change spreading around
the internet, takes up to a day, often much less.

**Step 2. Install cloudflared as a Windows service.**

A service is a program Windows starts automatically at boot. This step means the
tunnel is already running after a restart, so you do not have to open a window
by hand. In PowerShell, run as Administrator:

```powershell
& "$env:LOCALAPPDATA\cloudflared\cloudflared.exe" service install <the-token-Cloudflare-gave-you>
```

**Step 3. Point a name at the tunnel.**

In the Cloudflare dashboard, open **Zero Trust**, then **Networks**, then
**Tunnels**, and create a tunnel. Cloudflare walks you through each step. When
it asks for a public hostname, enter the subdomain you want, such as `lms`, and
your domain, then set the service to `http://127.0.0.1:8000`.

**Step 4. Test it.**

Open `https://lms.yourdomain.com`. Confirm the home page loads, then confirm
`/login` works. Also confirm your security headers are still present; see
section 25.

**Important:** if you close your computer, the named tunnel address stops
working, because your computer is still the server. A named tunnel gives you a
permanent address, not permanent hosting.

## 37. Presentation checklist

Run through this the day before you demonstrate. The most common failure is not
a broken site. It is a computer that fell asleep.

1. **Stop sleep.** Windows Settings, System, Power, then set Screen and sleep to
   **Never**, both on battery and when plugged in.
2. **Stop lid sleep.** Control Panel, Power Options, Choose what closing the lid
   does, set it to **Do nothing**.
3. **Plug in the charger.**
4. **Start the three things** from section 35.2: MySQL, Laravel, tunnel.
5. **Test from your phone on mobile data**, not your own Wi-Fi.
6. **Send the address to yourself** so you can paste it from your phone if the
   laptop network fails.
7. **Have a backup plan.** Know your campus Wi-Fi password, and keep your phone
   hotspot ready. A dropped connection during a demonstration is the one problem
   no amount of software quality prevents.
8. **Check the accounts first.** Sign in as the Administrator, an Instructor,
   and a Student before you begin. That way you know each one works.

### When the site is down during a demonstration

Check these in order. The answer is almost always the first one.

1. Is the PowerShell window running `php artisan serve` still open?
2. Is the PowerShell window running cloudflared still open?
3. Is MySQL running in the XAMPP Control Panel?
4. Has the computer slept?
5. Has the public address changed? The quick tunnel address changes on restart,
   so a restart with a new address is the usual explanation.

