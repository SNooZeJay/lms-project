# Deployment runbook

## 1. Document status

This runbook explains how to put a tested build of the IT Learning Hub online
and how to take it back safely.

It does not name a specific hosting provider. Choose a provider that gives you
PHP 8.3 to 8.5, MySQL 8.x, HTTPS, cron, and shell access. Every step below
works on shared hosting and on a small VPS.

The authoritative pre-flight check is a command in this repository:

```text
php artisan lms:check-production
```

Run it on the server after deploying. It exits with a non-zero code when
something is wrong, so a deployment script can stop on failure.

## 2. Requirements

| Requirement | Value | Why |
|---|---|---|
| PHP | 8.3 to 8.5 | Approved stack |
| Extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl` | Laravel and file MIME detection |
| Database | MySQL 8.x | Approved stack |
| Web server | Nginx or Apache | Serves `public/` |
| HTTPS | Required | Secure cookies and the payment provider |

`fileinfo` is not optional. The upload allow-list detects the real file type
with it, so without it every upload would be rejected.

## 2A. CA certificate bundle

PHP must be able to verify a TLS certificate to reach the payment provider. A
default XAMPP or PHP install often has no CA bundle, and then every outbound
HTTPS call fails with:

```text
cURL error 60: SSL certificate problem: unable to get local issuer certificate
```

Confirm it works:

```bash
php -r "var_dump(ini_get('curl.cainfo'));"
```

If that prints an empty string, install a bundle and point PHP at it. Get a
current Mozilla bundle from <https://curl.se/ca/cacert.pem>, save it outside
the repository, for example `C:\tools\php85\extras\ssl\cacert.pem`, then set
both directives in `php.ini`:

```ini
curl.cainfo = "C:\tools\php85\extras\ssl\cacert.pem"
openssl.cafile = "C:\tools\php85\extras\ssl\cacert.pem"
```

Restart PHP, then confirm again.

**Never** work around this by setting `verify => false` in an HTTP client or by
turning off certificate verification. That removes the only protection against
a machine-in-the-middle on the payment call. Install the bundle instead.

`php artisan lms:check-production` does not test outbound TLS. After
installing the bundle, confirm with one real request:

```bash
php artisan tinker
```

```php
try {
    $r = Illuminate\Support\Facades\Http::withBasicAuth(config('services.paymongo.secret_key'), '')
        ->timeout(20)->get('https://api.paymongo.com/v2/payments');
    echo $r->status().PHP_EOL;   // 200 means TLS and the credential both work
} catch (Throwable $e) {
    echo get_class($e).PHP_EOL; // a ConnectionException means TLS is still broken
}
```

## 3. Server preparation

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

Never run `composer install` without `--no-dev` on a production server.

## 4. Environment configuration

Copy `.env.example` to `.env` and set every value below. Never commit `.env`.

```dotenv
APP_NAME="BSIT Academic LMS"
APP_ENV=production
APP_KEY=                       # php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://your-domain.example
```

Generate the key and copy the output into `.env`:

```bash
php artisan key:generate --show
```

Set the database values to the ones your provider gave you:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lms_production
DB_USERNAME=lms_production
DB_PASSWORD=                # paste a long random password from your provider
```

Set production session and cache values:

```dotenv
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Set `SESSION_SECURE=true`. A false value sends the session cookie over plain
HTTP, which `lms:check-production` treats as a failure.

Set the proxy trust when a load balancer sits in front of the application.
Give the exact addresses of your load balancer and nothing else:

```dotenv
TRUSTED_PROXIES=10.0.0.1,10.0.0.2
```

Set payments only when a paid course is on sale:

```dotenv
PAYMONGO_ENABLED=true
PAYMONGO_SECRET_KEY=       # paste the live secret key from the PayMongo dashboard
PAYMONGO_WEBHOOK_SECRET=   # paste the webhook signing secret from the dashboard
```

Both values are required, and they are not interchangeable.

`PAYMONGO_SECRET_KEY` creates the checkout. `PAYMONGO_WEBHOOK_SECRET` verifies
the event that settles the payment. With the first set and the second missing,
a Student reaches a real checkout page, pays, and then waits forever, because
no enrollment is ever activated. This is the worst possible payment failure:
it looks like a provider problem and it is not.

`php artisan lms:check-production` fails with that explanation, on purpose.

When `PAYMONGO_ENABLED=false`, leave both secret values empty. A half
configured payment setup is a failing check too.

## 5. Database

```bash
php artisan migrate --force
```

Never use `migrate:fresh` on a server that holds real data.

If a fresh server needs its first Administrator, create one by hand in the
Tinker console. The bundled `owner:bootstrap` command refuses to run outside
`local` on purpose, so it cannot be used on a production server.

```bash
php artisan tinker
```

```php
$user = App\Models\User::create([
    'name' => 'Site Administrator',
    'email' => 'admin@example.com',
    'password' => Illuminate\Support\Str::random(32),
    'email_verified_at' => now(),
]);

$user->profile->forceFill([
    'role' => App\Enums\UserRole::Administrator,
    'account_status' => App\Enums\UserAccountStatus::Active,
    'must_change_password' => true,
])->save();
```

Set the password through the sign-in page on first visit. The forced password
change makes sure the generated value is never the one that stays in use.

## 6. Web server

Point the document root at `public/`, never at the project root.

Nginx:

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.example;
    root /var/www/lms-project/public;
    index index.php;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Apache needs `DocumentRoot` pointed at `public/` and `AllowOverride All` so
`.htaccess` applies.

## 7. Storage

Learning material files are private. They are stored on the `local` disk at
`storage/app/private` and are served only through an authorized controller.

Two consequences:

- Do **not** run `php artisan storage:link`. A public symlink would expose
  every uploaded file. `lms:check-production` fails if the link exists.
- The `storage` and `bootstrap/cache` directories must be writable by the web
  server user.

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## 8. Build caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`route:cache` fails when a route uses a closure. This project declares every
route with a controller or an invokable class, so the cache builds cleanly.
Confirm it on the server, because a failure here is a deployment blocker:

```bash
php artisan route:cache
```

## 9. Scheduler and queue

Add these to the server cron. Adjust the paths to your install.

```cron
* * * * * cd /var/www/lms-project && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /var/www/lms-project && php artisan queue:work --tries=3 >> /dev/null 2>&1
```

The queue is used for background work. Nothing in the payment flow depends on
it: a webhook settles a payment inside the request, so a stopped queue worker
can never leave a paid Student locked out.

## 10. Backups

Back up the database every day and keep 14 days:

```bash
mysqldump --single-transaction --routines lms_production \
  | gzip > /var/backups/lms-$(date +%F).sql.gz
find /var/backups -name 'lms-*.sql.gz' -mtime +14 -delete
```

Also back up `storage/app/private`. A certificate references a file only by
its database row, but a course learning material is gone if its file is.

Test a restore before you need one. A backup that was never restored is a
rumour.

## 11. Payment provider setup

1. Create a PayMongo account and complete its own onboarding.
2. Copy the live secret key into `PAYMONGO_SECRET_KEY`.
3. Copy the webhook signing secret into `PAYMONGO_WEBHOOK_SECRET`.
4. Add this webhook URL in the PayMongo dashboard:

```text
https://your-domain.example/webhooks/paymongo
```

5. Subscribe to one event. In the dashboard, under **Checkout Session**, tick
   the small box next to `checkout_session.payment.paid`.

   The dashboard shows two checkboxes per group. The large one beside the group
   name subscribes to every event in that group. The small one under it
   subscribes to that single event. Tick the small one.

   Do not tick the **Payment** group. Those events belong to the Payment Intents
   API, which this application does not use, and they carry no checkout session
   id, so they cannot be matched to a Payment row. Every other group on that
   page is a different product: QR and QR Ph are direct payments, Payout and
   Transfer move money out, and Subscription and Workflow are recurring and
   automation features.

6. Place one real test payment and confirm the enrollment becomes `active`.

Step 6 is the only way to prove the credentials and the provider contract. The
automated suite proves the state machine with a fake provider, so only a real
payment proves the secrets.

The signing secret is not the API secret key. It is shown only on the endpoint
page, under Developers → Webhooks → your endpoint, and each endpoint has its own.
The API deliberately does not return it, so it cannot be recovered from a
credential and must be copied from that page.

### The signature header

It is not a bare digest, and this is the single easiest thing to get wrong:

```text
t=1496734173,te=<hex signature>,li=
```

The three parts are a timestamp, the test-mode signature, and the live-mode
signature. Only one of `te` and `li` is ever populated. The signed message is
the timestamp, a period, and the untouched request body:

```text
<t>.<raw body>
```

Hash that with the endpoint's secret using SHA-256 and compare against `te` on a
test deployment or `li` on a live one. Hashing only the body, or comparing the
whole header to a digest, silently rejects every genuine delivery while looking
correct in review.

Do not add a freshness window on the timestamp. PayMongo retries a failed
delivery up to twelve times with backoff, and every retry carries the timestamp
of the original event, so a window would reject the retries that matter.
Deduplicate on the provider event id instead.

### PayMongo names events two ways

The dashboard label and the event type in the payload have differed. Both
spellings of the paid event are handled, so either label works. The events this
application acts on are `checkout_session.payment.paid`, `payment.failed`, and
`payment.refunded`.

There is no cancelled payment event. A Student who closes the checkout page
produces no event at all, so the payment stays `pending` and the enrollment
stays `pending_payment`, which is correct.

PayMongo documents two different envelope layouts for the same endpoint. The
handler reads both. This is worth knowing when a payload is inspected by hand:
`app/Services/Payments/PayMongoEventEnvelope.php` lists the exact position of
every field.

### Getting a public URL on a local machine

The endpoint must be reachable over public HTTPS, so `localhost` does not work.
For a test run, a tunnel is enough:

```bash
php artisan serve
ssh -R 80:localhost:8000 nokey@localhost.run
```

Then set `APP_URL` to the tunnel URL, because the `success_url` and `cancel_url`
sent to the provider are generated from it. Without this the Student is
redirected to `127.0.0.1` after paying.

A tunnel is not a deployment. It only works while the machine is running, so
use it to verify, not to hand in.

The checkout half of this was verified against the real test API on
September 26, 2026, and that call found a request type bug the fake could not
see. Reading the Hosted Checkout documentation then found that the webhook
handler only accepted one of the two documented payload layouts, which would
have rejected every real payment. The webhook half still needs step 5 completed
first, because without the signing secret no event can be verified.

## 12. Verification before going live

```bash
php artisan lms:check-production
```

Every line must read `PASS`. The command explains what each check means and
points back at this document.

Then confirm by hand:

| Check | Expected |
|---|---|
| Visit the site over HTTPS | No browser warning |
| Register a Student | Redirects to the Student dashboard |
| Enroll in a free published course | Enrollment becomes `active` |
| Open a lesson and mark it complete | Percentage increases |
| Take and submit a quiz | Server-graded score appears |
| Claim a certificate | Certificate shows the student and course name |
| Sign in as an Administrator | Reports and certificates open |
| Try another Student's URL directly | Denied |
| Try a private material URL directly | Denied |
| Send a signed webhook replay | Recorded, nothing changes |
| Send a webhook with a wrong signature | Recorded as ignored, nothing changes |
| `APP_DEBUG=false` | A failure shows a plain error page, not a stack trace |

## 13. Rollback

Keep the previous release's code, its `public/build` folder, and the database
schema state available.

To roll back a release:

```bash
php artisan down --render="errors::503" --retry=15
git checkout <previous-tag>
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Migrations are additive in this project, so a previous release keeps working
against a newer schema. A migration that destroys data does not exist here,
which is deliberate. If one is ever added, its `down` method is the rollback
path and it must be reviewed before release.

## 14. Release checklist

```text
php artisan test
./vendor/bin/pint --test
composer audit
npm audit
npm run build
git tag -a v1.0.0 -m "V1 release"
```

On the server, after deploying:

```text
php artisan migrate --force
php artisan lms:check-production
```
