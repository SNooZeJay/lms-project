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

The following features are not built yet:

- Public course catalog
- Enrollment
- Reordering, publishing, deleting, or archiving curriculum content
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
4. Select **Add material** and confirm the new material shows as `Material 1`.
5. Repeat with `Code` and confirm the content is kept.
6. Repeat with `External link` and a full link such as `https://www.php.net/manual/en/`.
7. Try `Video link` with an empty link. Confirm the page shows an error.
8. Try `Text` with empty content. Confirm the page shows an error.
9. Confirm the type list only offers Text, Code, Video link, and External link.
10. Select **Edit material**, change the title or link, and save.
11. Confirm the position and uploader stayed the same on the edit page.
12. Submit an empty title and confirm the error summary appears and your text is kept.
13. Check the Lesson area at 390px width.

File uploads are not built yet, so there is no file field and no download button.

## 12. Create a Student account

Use a separate browser or private window if you want to keep the Administrator session.

1. Open `http://127.0.0.1:8000/register`.
2. Enter a name, email, and password.
3. Confirm the password.
4. Submit the form.
5. Laravel creates a Student account.
6. Laravel asks you to verify the email address.

The registration form has no role selector. Public registration cannot create an Administrator.

## 13. Find email verification and reset links

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

## 14. Run the frontend development server

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

## 15. Run the automated checks

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

## 16. Useful commands

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

## 17. Troubleshooting

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

## 18. Project folder guide

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

## 19. Security rules

Keep these rules in mind:

- Never commit `.env`.
- Never share `DB_PASSWORD`, `APP_KEY`, or secret PayMongo keys.
- Never paste real passwords into documentation or screenshots.
- Keep the owner secret path outside the project.
- Use `php artisan owner:bootstrap --show-password` only on your local computer.
- Keep `MAIL_MAILER=log` for local demonstrations.
- Do not use a real payment secret until the payment architecture phase is approved.

## 20. Recommended beginner order

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
13. Read the relevant test before changing a feature.
14. Run `php artisan test` before and after your change.

You do not need to understand the whole Laravel framework before running the application. Start with the commands in this tutorial, then inspect one small feature at a time.
