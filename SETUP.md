# Setting this project up on a new machine

This is the document for putting IT Learning Hub onto a laptop that has never run
it. It assumes nothing beyond what the project actually needs, and it does not
assume the database, the runtime or the environment file exist.

The short version is step 4. Steps 1 to 3 are software you install once.

---

## 1. What this project is

| | |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 or newer |
| Front end | Blade, Tailwind CSS 4, Vite, plain JavaScript |
| Database | MySQL 8.x |
| Packages | Composer for PHP, npm for the front end |

There is no Node build server requirement beyond `npm run build`, and no external
service is needed to run it. Payments are off by default, so no account with a
payment provider is required to start.

---

## 2. Software to install

Install these first. They are the only things the project cannot install for
itself, and installing a runtime is the one step where a script that guesses will
eventually install the wrong one.

| Software | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer | 8.3, 8.4 and 8.5 all work. The project was built on 8.5. |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org) |
| Node.js | 20 or newer | Only needed to build the front end. |
| MySQL | 8.x | XAMPP's copy is fine. |
| Git | any recent | Only needed to clone. |

### PHP extensions

The project needs these loaded, and a missing one shows up as an error on the
first request rather than at install time:

```
pdo_mysql  mbstring  openssl  tokenizer  xml  ctype  fileinfo  curl  bcmath
```

`gd`, `intl` and `zip` are **not** required. The project runs without all three.

On Windows with XAMPP, open the Apache `php.ini` and remove the leading `;`
from each of those lines. On Linux, install `php8.3-mysql php8.3-mbstring
php8.3-xml php8.3-curl php8.3-bcmath php8.3-intl`.

---

## 3. MySQL before the first run

Create a database and a user. The project will not create the user for you,
because an account with no grants cannot grant anything:

```sql
CREATE DATABASE lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lms_user'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON lms.* TO 'lms_user'@'localhost';
FLUSH PRIVILEGES;
```

`tools/setup.php` will create the database itself if it is missing, but it needs
the user above to already exist and to be able to connect.

Put the same values in `.env` (step 5). Note that XAMPP often runs MySQL on port
**3307** rather than 3306; check what yours is using and set `DB_PORT` to match.

---

## 4. The short version

Once steps 1 to 3 are done:

```bash
git clone <the private repository url>
cd lms-project

composer install
npm install
cp .env.example .env          # Windows: copy .env.example .env
php tools/setup.php
```

`tools/setup.php` then does the rest: it creates `.env` if it is not there,
generates an application key, creates the database if it is missing, runs the
migrations, seeds the catalog and the three demonstration accounts, and builds the
front end.

Then start the server:

```bash
php artisan serve
```

It prints the address. Open it, usually <http://127.0.0.1:8000>.

**`php tools/setup.php` is safe to run more than once.** It checks before every
step, so on a machine that already works it changes nothing: it never overwrites
`.env`, never regenerates an existing `APP_KEY`, and never drops a database.

Useful flags:

| Flag | What it does |
|---|---|
| `--skip-npm` | Does not install or build the front end. |
| `--skip-seed` | Does not add the catalog or the accounts. |
| `--fresh` | Not used for a normal setup. Ask before running anything that drops data. |

---

## 5. The environment file

`.env` is **never committed**. `.env.example` is committed and carries every
variable the project reads, with safe placeholders and no real values.

The ones that matter on a new machine:

| Variable | What to put in it |
|---|---|
| `APP_KEY` | Left blank. `tools/setup.php` generates it. Or run `php artisan key:generate`. |
| `APP_URL` | The address you will browse to, e.g. `http://127.0.0.1:8000` |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | From step 3. |
| `DEV_ACCOUNT_PASSWORD` | The password for the three demonstration accounts. Leave blank and the seeder prints a generated one once. |
| `MAIL_MAILER` | `log` writes mail to `storage/logs/laravel.log` and is the right choice while developing. |
| `PAYMONGO_ENABLED` | `false` unless you have payment keys. The project runs fully without them. |

### Mail

Nothing to do to get the project running. With `MAIL_MAILER=log` a password reset
is written to the log rather than sent, which is enough to test the flow.

To send real mail, on a Gmail account create an **App password**
(Google Account, Security, 2-Step Verification, App passwords) and set:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your.address@gmail.com
MAIL_PASSWORD=the sixteen character app password
MAIL_FROM_ADDRESS=your.address@gmail.com
```

Put it in your own `.env`. **Never in `.env.example` and never in a commit.**

### Payments

Off by default. With `PAYMONGO_ENABLED=false` the catalog shows free courses and
paid courses are not purchasable, which is the honest state rather than a
checkout that pretends to work. To enable it you need test keys from PayMongo in
`PAYMONGO_SECRET_KEY` and `PAYMONGO_WEBHOOK_SECRET`. Keep `PAYMONGO_LIVEMODE=false`
until you mean it.

---

## 6. The demonstration accounts

`php artisan db:seed` creates three, and they are the fastest way to show each
role:

| Role | Email |
|---|---|
| Administrator | `admin@lms.test` |
| Instructor | `instructor@lms.test` |
| Student | `student@lms.test` |

The password is `DEV_ACCOUNT_PASSWORD` from your `.env`. If that was blank when
you seeded, one was generated and printed at that moment; set the variable to
keep it, or run:

```bash
php artisan db:seed --class=DemoAccountsSeeder
```

Seeding is idempotent. Running it again does not duplicate anything and does not
reset an existing account's password.

The seeder also creates the course catalog: five published courses, thirty six
modules, sixty two lessons, fifty two learning materials and seventeen quizzes.
That is what makes the project demonstrable without anyone having to write
content by hand.

---

## 7. Everyday commands

```bash
php artisan serve              # development server
npm run dev                    # front end with hot reload, optional
npm run build                  # build the front end for serving
php artisan test               # the whole test suite
./vendor/bin/pint --test       # code style
composer audit                 # dependency advisories
php artisan route:list         # every route
```

---

## 8. If something goes wrong

**"Class not found" or the page is blank.** The dependencies are not installed.
Run `composer install`.

**The page loads with no styling.** The front end is not built. Run
`npm install` then `npm run build`.

**"SQLSTATE ... Access denied for user".** The credentials in `.env` do not match
a user in MySQL. Check step 3 and that `DB_PORT` is the port your MySQL is
actually on.

**"No application encryption key".** `php artisan key:generate`.

**Sign in says the credentials are wrong and you are sure they are right.** The
account may still be flagged for a forced password change. Sign in, or set it
directly:

```bash
php artisan tinker
>>> App\Models\User::where('email','student@lms.test')->first()->profile->update(['must_change_password' => false]);
```

**The demonstration data is missing.** `php artisan db:seed --force`. It only
adds.

**Port 8000 is in use.** `php artisan serve --port=8001`.

**A test fails on migration.** On some MySQL installations `migrate:fresh`
deadlocks when two runs happen close together, and the half-dropped schema makes
the next run fail on a missing foreign key. Wait a few seconds and run it again;
if it keeps happening, drop and recreate the test database:

```bash
php tools/rebuild-the-test-database.php
```

---

## 9. What is not in this repository, and why

| Not committed | Why |
|---|---|
| `.env` and every `.env.*` | Holds real credentials. Only `.env.example` is committed. |
| `vendor/`, `node_modules/` | Generated by `composer install` and `npm install`. |
| `public/build/` | Generated by `npm run build`. |
| `storage/app/private/` | Uploaded learning materials, which belong to a user. |
| `storage/logs/` | Can contain personal data from error messages. |
| `database/*.sqlite`, dumps | A database file can contain real people's data. |

Everything the project needs to be rebuilt is present, and the demonstration data
is recreated by a seeder rather than carried as a database file.
