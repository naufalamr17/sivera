# SIVERA

**Smart Integrated Visibility & Event Response Application** is an internal Laravel application for collecting exceptions from other Laravel applications, grouping repeated errors into issues, and managing incident response in one place.

The dashboard shows date-filtered activity, application error rankings, and the most frequent exceptions with their reported messages. Operators can inspect stack traces, update issue status and severity, receive live in-app/browser alerts, and receive one email summary each morning. The bundled reporter package sends exceptions without changing the source application's existing Laravel logging.

This repository implements the MVP described in [PRD_SIVERA.md](PRD_SIVERA.md). It does not provide application performance monitoring, distributed tracing, release tracking, or browser session replay.

**Reporter package:** [naufalamr17/sivera-reporter on Packagist](https://packagist.org/packages/naufalamr17/sivera-reporter) · [Reporter source and guide](packages/sivera-reporter).

## Documentation map

- [Requirements](#requirements) and [local installation](#local-setup)
- [Production installation and upgrades](#production-installation-and-upgrades)
- [Server configuration](#server-configuration)
- [Roles and issue workflow](#access-and-workflow)
- [Reporter installation and connection checks](#install-the-reporter-in-another-laravel-application)
- [Event API and signing](#event-api)
- [Email, browser alerts, and retention](#notifications-and-retention)
- [Dashboard calculations](#dashboard-date-filters-and-rankings)
- [Security and privacy](#security-and-privacy)
- [Project structure](#project-layout), [verification commands](#checks-and-useful-commands), and [troubleshooting](#troubleshooting)

## Requirements

- PHP 8.3 with the extensions required by Laravel and the selected database driver
- Composer
- Node.js 20.19+ within 20.x, or Node.js 22.12+ (the installed Vite 8 requirement), and npm for the frontend build
- MySQL or MariaDB for the default configuration

The application uses Laravel 13, Blade, React 19, shadcn/ui components with Radix primitives, Tailwind CSS 4, and Vite 8. React renders workspace navigation, the notification center, and the dashboard; management pages remain server-rendered Blade. Database-backed cache, sessions, and queues are configured in `.env.example`.

The shared SVG logo at `public/sivera-icon.svg` combines a browser window with an ascending bar chart to represent web application monitoring and analytics. The login page, workspace navigation, favicon, and browser notification icon reuse this asset.

## Local setup

From a new checkout, run these commands in the repository root in PowerShell. Preserve an existing `.env` instead of overwriting it:

```powershell
Copy-Item .env.example .env
composer install
npm ci
```

Create an empty MySQL database named `sivera`, or change `DB_DATABASE` in `.env` to the database you created. Set `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` for your local server. Then run:

```powershell
php artisan key:generate
php artisan migrate
php artisan db:seed --class=UserSeeder
npm run build
php artisan serve
```

Open `http://localhost:8000/login`. For frontend development, run `npm run dev` in a separate terminal instead of rebuilding assets after each change.

`UserSeeder` creates these **local and testing only** accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@sivera.test` | `Sivera123!` |
| Viewer | `viewer@sivera.test` | `Sivera123!` |

Do not use the sample accounts in a deployed environment. `UserSeeder` skips environments other than `local` and `testing`; create the first deployed administrator with `php artisan sivera:create-admin`. That command prompts for a name, a unique email address, and a password of at least 12 characters.

Optional sample applications and issues can be added outside production with `php artisan db:seed --class=SiveraDemoSeeder`.

### Background processes

Run the Laravel scheduler for the morning email summary and daily retention cleanup:

```powershell
php artisan schedule:work
```

The digest runs at **07:00 Asia/Jakarta** by default and covers the previous local calendar day. Configure `SIVERA_DIGEST_TIME` and `SIVERA_TIMEZONE` to change this. Cleanup runs at 00:30 in the application's timezone (UTC by default). In deployment, invoke `php artisan schedule:run` every minute using cron or Windows Task Scheduler. Keep the machine/server running when scheduled work is due.

The daily email command sends synchronously and records delivery status; it does not need a queue worker. A source application using `SIVERA_TRANSPORT=queue` still needs its own `php artisan queue:work`.

## Production installation and upgrades

Serve the Laravel **`public/` directory** through your web server with trusted HTTPS. Keep `.env`, storage, vendor files, and repository metadata outside the public document root. The web server user needs write access to `storage/` and `bootstrap/cache/`. Use a dedicated database account with access to SIVERA's database. Prepare the database, SMTP transport, backups, and scheduled task before enabling ingestion.

Set production values in the server's `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sivera.example.com
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
SESSION_DRIVER=database
MAIL_MAILER=smtp
```

Configure the remaining `DB_*` and `MAIL_*` values for your infrastructure. Set `TRUSTED_PROXIES` to the actual proxy addresses only when TLS terminates at a trusted reverse proxy. In multi-server installations, use the same database/cache, `APP_KEY`, and session configuration on every node; replay protection, rate limits, and digest locks must share state.

For a first installation, install production PHP dependencies, generate `APP_KEY` once, migrate, and create an administrator:

```powershell
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan sivera:create-admin
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Build assets on the server or a compatible build machine and deploy `public/build/`. Node.js is required for building, not for serving a completed build. Do not seed sample accounts in production. Keep the generated `APP_KEY` with your backups: replacing it makes existing encrypted credentials and sessions unreadable.

For an upgrade:

1. Back up the database and existing environment/key. Prepare the new code, Composer dependencies, and built assets.
2. Put **all server nodes** into maintenance mode with `php artisan down`, and pause scheduled/long-running work. Do not mix old and new ingestion code during the event-ID migration.
3. Deploy the prepared code and run the following from the SIVERA server root:

   ```powershell
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan up
   ```

4. Resume the scheduler. Verify `/health`, login, a new signed test event, and notification delivery history. Plan the maintenance window with source applications: reporter delivery failures are fail-safe and are not automatically retried indefinitely.

The event-ID migration backfills `application_id` on receipts and sampled occurrences from their issue, keeps existing event IDs and timestamps, and replaces global uniqueness with `(application_id, event_id)` uniqueness. It supports retrying an interrupted backfill. Allow time for table/index changes on large databases; test the migration with a restored backup before a production upgrade.

Rollback does not delete conflicting events. The migration refuses to restore global uniqueness if different applications already share an event ID. Keep the new schema and code in that case; prefer a forward fix. Do not run `migrate:rollback` as a general deployment recovery command. Restoring an older backup loses records collected since that backup, so coordinate any restore explicitly.

Configure cron or Windows Task Scheduler to invoke `php artisan schedule:run` every minute, using the correct PHP executable, working directory, and service account. Do not run both an unmanaged `schedule:work` process and a separate scheduler unless that duplication is intentional. Check the effective schedules with `php artisan schedule:list`.

## Server configuration

These values belong to the **SIVERA server**. Reporter-specific `SIVERA_URL`, client ID, secret, and transport values belong to each monitored application instead.

| Setting | Default / purpose |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | `local`, `true`, localhost in the example; use production, false, and the public HTTPS URL in deployment |
| `APP_KEY` | Generated once; encrypts application credentials and cookies |
| `DB_*` | MySQL/MariaDB connection; SQLite is used by automated tests |
| `CACHE_STORE` | `database`; shared storage is required across nodes |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | `database`, 120 idle minutes |
| `TRUSTED_PROXIES` | Optional comma-separated trusted proxy addresses |
| `MAIL_*` | Mail transport, credentials, and sender; example `log` transport does not deliver email |
| `SIVERA_TIMEZONE` | `Asia/Jakarta`; dashboard date boundaries and digest timezone |
| `SIVERA_DIGEST_TIME` | `07:00` in `SIVERA_TIMEZONE` |
| `SIVERA_POLL_SECONDS` | 5; constrained to 5–10 seconds |
| `SIVERA_AUTO_REOPEN` | `true`; a new event reopens its resolved issue |
| `SIVERA_RETENTION_YEARS` | 3 calendar years minimum |

After changing environment settings, rebuild cached configuration with `php artisan config:cache` in deployment, or use `php artisan config:clear` locally. Restart long-running processes that need the new configuration. Never commit `.env` or credential secrets.

## Access and workflow

| Area | Admin | Viewer |
| --- | --- | --- |
| Dashboard, applications, issues, stack traces, and history | View | View |
| Own account password | Change | Change |
| Applications and API credentials | Manage | View applications |
| Issue status, severity, and comments | Manage | View |
| Users, notification delivery history, audit logs, and API logs | Manage/view | No access |

Inactive users cannot access authenticated pages. The dashboard refreshes its summary every 5 seconds by default; `SIVERA_POLL_SECONDS` is limited to 5–10 seconds.

Open **Account → Change password** in the sidebar or mobile navigation to update your own password. Supply the current password, a different new password of at least 12 characters, and its confirmation. Changes are hashed, audited without storing password values, and limited to five attempts per minute. The current session ID is regenerated after success and other sessions must sign in again. An administrator resetting a user's password also invalidates that user's existing sessions on their next protected request. No email is sent for this action.

To connect an application:

1. Sign in as an admin and create an application with its slug and environment.
2. Generate an API credential on the application page. Copy its client ID and secret when shown; the secret is not shown again.
3. Install the reporter package in the source Laravel application and set its SIVERA environment values.
4. Run `php artisan sivera:test` in the source application, then inspect the new issue in SIVERA.
5. Use the issue page to review occurrences, status history, and comments. Admins can change severity or move an issue through its status workflow.

The Issues page supports application, severity, status, exception class, route, date, and text filters. SIVERA groups related exceptions by application, exception class, file, line, and route. A repeated `event_id` within the same application is accepted without creating another occurrence, even if the repeated payload names a different issue. Different applications can use the same event ID independently. Resolved issues reopen on a new matching event when `SIVERA_AUTO_REOPEN=true`.

## Install the reporter in another Laravel application

The reporter lives at [`packages/sivera-reporter`](packages/sivera-reporter). It is a separate MIT-licensed Composer package; it is not installed as a dependency of the SIVERA server. Install the public package from [Packagist](https://packagist.org/packages/naufalamr17/sivera-reporter) from the **source application's** root without copying package folders:

```powershell
composer require "naufalamr17/sivera-reporter:^0.1"
php artisan sivera:install
```

The package's [README](packages/sivera-reporter/README.md) covers configuration and delivery checks. If the source application already uses the local path repository, remove it with `composer config --unset repositories.sivera-reporter` before requiring the public release. Existing configuration can stay in place; do not rerun `sivera:install` just to switch sources.

If the earlier local package `naufal/sivera-reporter` is already installed, remove it with `composer remove naufal/sivera-reporter` before installing the public package. Packagist's `naufal` vendor is owned by someone else; the public package uses `naufalamr17/sivera-reporter`.

For local package development, add a path repository to the source application's `composer.json`, with a path relative to that application's root:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../sivera/packages/sivera-reporter"
    }
  ]
}
```

Then, from the source application, run:

```powershell
composer require "naufalamr17/sivera-reporter:@dev"
php artisan sivera:install
```

Laravel discovers the package service provider automatically. Set these values in the **source application's** `.env`:

```dotenv
SIVERA_ENABLED=true
SIVERA_URL=https://sivera.example.com
SIVERA_APP_ID=your-application-slug
SIVERA_CLIENT_ID=cli_your_client_id
SIVERA_SECRET=your_generated_secret
SIVERA_ENVIRONMENT=production
SIVERA_TRANSPORT=http
```

`SIVERA_APP_ID` and `SIVERA_ENVIRONMENT` must match the application registered in SIVERA. `SIVERA_URL` is the server base URL, without `/api/v1/events`, and **must use HTTPS**; the reporter refuses plain HTTP even during local testing. Its default transport sends with a 0.5-second connection timeout and a 2-second total timeout. Set `SIVERA_TRANSPORT=queue` to dispatch through the source application's queue worker. The reporter catches delivery failures so they do not change the source application's exception handling; it does not capture request bodies by default.

Run `php artisan sivera:test` in the source application to send a synthetic exception. The `sivera:install` and `sivera:test` commands belong to the installed reporter package, not the SIVERA server.

### First application setup

1. Publish SIVERA at an HTTPS URL reachable from the source application's server. Verify that `GET /health` returns `{"status":"ok"}`. Apply pending SIVERA migrations with `php artisan migrate` before connecting applications.
2. In SIVERA, sign in as an Admin, open **Applications**, and create an application. For example, use name `My Application`, slug `my-application`, environment `production`, and rate limit `60`. Leave the IP allowlist empty unless you know the source server's outbound IP address.
3. Open the application and generate a credential. Save the client ID and secret securely; the secret is shown only once. Use separate registrations and credentials for separate applications or environments.
4. Install the reporter from the **source application's root directory**, not the SIVERA directory. Use the Packagist commands above. For local package development, either use a shared path repository or copy `packages/sivera-reporter` into the source application's own `packages/sivera-reporter` folder and run:

   ```powershell
   composer config repositories.sivera-reporter path ./packages/sivera-reporter
   composer require "naufalamr17/sivera-reporter:@dev"
   php artisan sivera:install
   ```

5. Set the source application's `.env` using the example above. Set `SIVERA_APP_ID=my-application`, match `SIVERA_ENVIRONMENT` to the registered environment, and use the generated client ID and secret. Keep `SIVERA_TRANSPORT=http` if the source application has no queue worker. Do not commit the `.env` file.
6. Reload configuration and send a test event from the source application:

   ```powershell
   php artisan config:clear
   php artisan sivera:test
   ```

   If the deployed application uses cached configuration, run `php artisan config:cache` after updating its environment. Restart existing long-running queue workers with `php artisan queue:restart` when they need to load the new package or configuration.

7. Open **Issues** in SIVERA and verify the synthetic `SIVERA connection test` issue, application, occurrence, and stack trace. Laravel exceptions are then reported automatically; controller and business-logic changes are not required. Existing Laravel logging continues to operate.

Every active SIVERA user automatically receives the daily email summary. Manage names, addresses, and active state under **Users**; **Notifications** displays the active user recipients and delivery history. WhatsApp delivery has been removed.

If the test fails, check the HTTPS URL and certificate, outbound network access, application slug, registered environment, client ID, secret, and server clock. A `401` indicates an authentication or security check failed; `422` indicates an invalid payload; `429` indicates the configured rate limit. The package manifest allows Laravel 10–13, but installation and delivery must still be checked in each target application.

If `/health` is reachable but the source PHP process reports `cURL error 28`, its default 0.5-second connection timeout may be too short for that network. In the **source application**, set `SIVERA_CONNECT_TIMEOUT=3` and `SIVERA_TIMEOUT=8`, then run `php artisan config:clear` and retry `php artisan sivera:test`. These are optional network-specific overrides; a larger timeout can delay exception reporting when SIVERA is unavailable. A source application using queued reporting can keep that delay in its worker.

## Event API

`POST /api/v1/events` receives JSON from registered applications. The recommended client is the bundled reporter. A valid event contains:

```json
{
  "event_id": "01JZEXAMPLE1234567890ABCDEF",
  "application": "your-application-slug",
  "environment": "production",
  "severity": "error",
  "exception": {
    "class": "RuntimeException",
    "message": "Example failure",
    "file": "app/Services/ExampleService.php",
    "line": 42
  },
  "request": {
    "method": "GET",
    "route": "orders.show",
    "path": "/orders/123"
  },
  "occurred_at": "2026-10-05T03:00:00Z"
}
```

`request`, `severity`, file/line, user ID, and up to 50 stack frames are optional. The request body must be at most 256 KiB. Its headers are `X-SIVERA-App-ID`, `X-SIVERA-Client-ID`, `X-SIVERA-Timestamp`, `X-SIVERA-Nonce`, and `X-SIVERA-Signature`. The signature is `sha256=` followed by the lowercase hex HMAC-SHA256 of these newline-separated values:

```text
POST
/api/v1/events
<Unix timestamp>
<nonce>
<SHA-256 hex digest of the exact raw JSON body>
```

Use the credential secret as the HMAC key. Timestamps must be within five minutes of server time, and a nonce cannot be reused for ten minutes. Active credentials, application status, optional IP allowlists, and per-credential rate limits are also checked. Production API traffic requires HTTPS.

| HTTP status | Meaning |
| --- | --- |
| `202` | Accepted; JSON contains `accepted`, `duplicate`, and `event_id`. Duplicate detection is application-scoped. |
| `401` | Missing/invalid signature, timestamp, replayed nonce, inactive identity, expired credential, disallowed IP, or insecure production API request |
| `413` | Body exceeds 256 KiB |
| `422` | Invalid JSON event shape or application/environment mismatch |
| `429` | Credential limit or the outer limit of 600 ingestion requests per source IP per minute; inspect `Retry-After` |

The outer rate limit runs before signature processing. Multiple applications behind one outbound IP share that outer budget; they retain their separate credential limits. Configure trusted proxies correctly so the server sees the source IP. Each event ID is a 1–64-character alphanumeric/hyphen string. Keep an event's ID stable when retrying delivery, but use a fresh nonce and signature for every HTTP attempt.

## Notifications and retention

Every **active Admin and Viewer** receives the daily summary automatically at **07:00 WIB (Asia/Jakarta)** by default. No separate recipient rule is required. Inactive users and legacy external recipient rules are excluded; duplicate email addresses receive one copy per date. **Notifications** lists the active users and delivery history. User email addresses and account state are managed under **Users**.

The email always covers the previous local calendar day, including days with zero events. It includes event counts, active/critical/resolved/ignored issue counts, top-five ERROR/CRITICAL application and issue rankings, and up to 20 status changes during that day. **RESOLVED and IGNORED are included in the summary and in live status-change notifications.** There are no per-event emails.

A separate **Daily follow-up** section lists issues whose current status is neither RESOLVED nor IGNORED, including older issues without events yesterday. The email displays up to 20 pending issues and links to the **All active statuses** issue filter for the complete paginated list. Each scheduled daily run also creates one in-app follow-up per outstanding issue per local day. Repeated runs cannot duplicate that day's follow-up.

Email uses Laravel's `MAIL_*` settings. Configure `MAIL_MAILER=smtp` (or another real transport), the SMTP credentials, and a valid sender. The `log` and `array` transports do not deliver external email. `MAIL_FROM_ADDRESS` is the sender; the recipients are active user accounts.

### Test email from the terminal

From the **SIVERA server repository**, run:

```powershell
php artisan config:clear
php artisan sivera:test-email
```

The command asks **Recipient email address**. Enter one valid email address; it sends one plain test message immediately through the configured `MAIL_*` transport. No queue worker or scheduler is needed. The address is not saved as a daily summary recipient, and the test contains no issue data.

Interactive entry is required: there is no recipient argument or default recipient, and `--no-interaction` fails without sending. Invalid addresses and direct `log`/`array` transports are rejected. Delivery failures return a nonzero exit code without printing provider credentials. A success message means the configured mailer accepted the message; verify the inbox and spam folder to confirm arrival. Use `MAIL_MAILER=smtp` when checking SMTP directly, since a configured failover mailer may fall back to a logging transport.

### Daily email schedule

```dotenv
SIVERA_TIMEZONE=Asia/Jakarta
SIVERA_DIGEST_TIME=07:00
```

After changing environment values, run `php artisan config:clear` and restart `schedule:work` if already running. Run a digest for a completed date with:

```powershell
php artisan sivera:send-digest --date=2026-10-26
```

Omit `--date` for yesterday in `SIVERA_TIMEZONE`. Successful recipient/date pairs are skipped on repeat runs; failed attempts are recorded and can be retried using the same command. An application lock prevents overlapping runs. SMTP acceptance and the database log are separate operations: a process crash after SMTP acceptance can still cause a retry to duplicate delivery. Use the delivery history to investigate failures. Logs follow the retention policy, so avoid rerunning dates older than retained delivery history.

Apply pending migrations using the [upgrade procedure](#production-installation-and-upgrades). The activity migration backfills receipt and status-history records in chunks without modifying their source data. Delivery logs have a nullable user reference; historical rule-based logs remain available. Manual recipient management routes are retired, and legacy rules no longer control delivery. Old jobs without an active user recipient do not send.

For rollback, the previous code can run with the additive tables/columns left in place. Rolling back the activity migration removes the activity stream; source receipts and status histories remain. Rolling back the user-recipient migration removes its user reference but keeps the legacy rule nullable to preserve delivery history. Do not roll back migrations merely to restart a worker.

The bell appears on all authenticated pages for Admins and Viewers. It polls `GET /notifications/feed` every 5–10 seconds independently of dashboard date filters, follows an activity ID cursor, and catches up in batches of 50. The stream includes received errors, all status changes (including RESOLVED and IGNORED), and daily follow-ups for outstanding issues. It groups repeated activity by issue, retains up to 100 updates, and displays up to 20 issues. Follow-up records for issues now resolved or ignored are not emitted; their status-change alerts still are. Read state is stored per user in that browser, rather than synchronized between devices. First visit loads recent activity without desktop alerts for old events. A burst produces a grouped in-app toast.

Click **Enable browser alerts** in the bell to request permission. Desktop notifications require a supported browser and a secure context (trusted HTTPS or localhost). They work while SIVERA is open; Web Push after closing the site is outside this implementation. Desktop notifications show application and severity or the changed status on the lock screen. If permission is denied or unsupported, in-app alerts continue. See [MDN's Notifications API guide](https://developer.mozilla.org/en-US/docs/Web/API/Notifications_API/Using_the_Notifications_API).

## Dashboard date filters and rankings

The dashboard defaults to the last seven calendar days, including today, in `SIVERA_TIMEZONE`. Apply an inclusive **From / To** range (maximum 366 days). A single day uses hourly chart buckets; longer ranges use daily buckets. Polling preserves the selected dates and updates cards, chart, rankings, and recent issues. The **Last 7 days** link resets the filter; a page left open overnight retains its original selected range until reset or reload.

Counts use **event receipt timestamps**, converted from local midnight boundaries to UTC. They do not use lifetime issue counters or sampled stack-trace details. The Applications card counts all registered applications. Open, critical, and resolved counts include only issues with events received in the selected period and reflect their **current** status/severity. Rankings include ERROR and CRITICAL only. Top errors show reported exception/message and location, which are evidence for investigation rather than an automatic root-cause diagnosis.

Recent issues are paginated at 10 per page. Pagination preserves the selected dates, and live polling stays on the selected page. The top-five rankings remain bounded summaries. Notification recipients and delivery history each have independent 20-row pagination; the other management lists already paginate.

Retention settings are defined in `.env.example` and [`config/sivera.php`](config/sivera.php):

| Data | Default retention |
| --- | ---: |
| Occurrence details / stack traces | 3 calendar years |
| API request logs and notification logs | 3 calendar years |
| Event receipts, audit logs, and issues of every status | 3 calendar years |

Use `SIVERA_RETENTION_YEARS=3` for the shared retention policy. The minimum is three years; larger values extend retention for all historical categories. Legacy `SIVERA_*_RETENTION_DAYS` variables are no longer used, so old 30/90/365-day settings cannot shorten the new policy. After deploying, run `php artisan config:clear` or rebuild the server's configuration cache.

Cleanup still runs **daily** and permanently deletes history **older than three calendar years**; it does not run only once every three years. The cutoff uses calendar-year subtraction with leap-day handling. Issues of **every status, including open issues**, are deleted when `last_seen_at` is older than the cutoff; first-seen age alone does not delete a recurring issue. Deleting an issue also removes its associated occurrences, status history, comments, and per-issue notification logs. Activity notifications follow the same retention period. Accounts, application registrations, credentials, and legacy recipient settings are not removed by history cleanup. A retention notice is displayed on workspace pages.

API request logs contain request metadata, not payloads or credential secrets. Event text is sanitized before storage, and application credential secrets are encrypted at rest. Rotated credentials have a 24-hour grace period unless revoked earlier.

## Security and privacy

- All workspace pages require an active authenticated user. Administrative writes require the Admin role. Viewers intentionally see monitoring data for every registered application; this is not a tenant-isolated customer portal.
- Web forms use Laravel CSRF protection. Password changes rotate the current session ID; password-hash validation rejects other sessions after a password change or administrator reset. Deactivated users are rejected on their next request.
- Ingestion verifies HMAC-SHA256 over the exact body, timestamp, path, and nonce. Nonce reservations and limits use the shared cache. Credentials are encrypted at rest, shown only when generated, checked on every request, and can be revoked immediately. Rotation allows a 24-hour grace period.
- An outer 600-request/IP/minute limit protects signature processing. Requests that reach signature middleware with an invalid signature generate at most **60 database log entries per minute across the service**. Further invalid attempts in that window are not individually persisted. Valid-signature requests retain their logs and credential limits. Use gateway metrics for complete rejected-traffic counts; sampled API logs are not a complete failure ledger.
- Blade and React render diagnostic text as escaped text. SQL filters use bound values. The API accepts bounded event fields, not arbitrary request bodies or stack-frame arguments.
- The **bundled reporter source in this checkout** replaces `QueryException` messages with `Database query failed.` and captures route templates such as `/reset-password/{token}` instead of concrete token-bearing paths. Console and unmatched requests have no captured path. This prevents SQL bindings, driver messages, and URL parameters from entering those fields through the bundled builder.
- Free-form exception messages can still contain sensitive information that pattern redaction does not recognize. Custom API clients must redact before sending. Keep secrets out of exception text, route names, and file names, and restrict dashboard access accordingly. Historical data is not scrubbed automatically by these source changes.

Reporter changes in this repository do **not** update already-installed Packagist releases. Publish a new reporter release separately, then update each monitored application and restart its workers. To validate unreleased reporter changes, use the local path repository instructions above. Never claim a public release contains these changes until that release is published.

## Project layout

```text
app/
  Console/Commands/        # Admin creation, email test, digest, retention cleanup
  Http/Controllers/Api/V1/ # Signed event ingestion controller
  Http/Controllers/        # Dashboard, issues, applications, users, and log pages
  Http/Middleware/         # HTTPS, active-user, and signature checks
  Http/Requests/           # Input validation and administrative authorization
  Jobs/                    # Daily digest delivery (called synchronously)
  Mail/                    # DailyDigestMail
  Models/                  # Users, credentials, issues, receipts, activity, logs
  Providers/               # Admin gate and ingestion ingress limiter
  Services/Sivera/         # Event ingestion, date analytics, digest logic
bootstrap/app.php          # Laravel routing and global middleware
config/                    # Database, cache, session, mail, and SIVERA settings
database/
  factories/               # Test data builders
  migrations/              # Schema changes, backfills, event-ID namespace migration
  seeders/                 # Local users and optional demo data
packages/sivera-reporter/
  config/sivera.php         # Reporter settings for monitored applications
  src/Console/             # sivera:install and sivera:test
  src/Jobs/                # Optional queued event delivery
  src/EventBuilder.php     # Minimal exception payload and privacy filtering
  src/Reporter.php         # HTTPS delivery and HMAC signing
  composer.json            # Public package metadata and auto-discovery
  README.md                # Reporter installation and maintenance guide
public/                    # HTTP front controller, built assets, SIVERA icon
resources/views/          # Blade management pages and email templates
resources/js/             # React workspace/dashboard and shadcn/ui components
resources/css/            # Shared neutral theme and management page styles
routes/                    # Web, event API, and scheduled digest/cleanup routes
storage/                   # Runtime logs, cached views, local private files
tests/Feature/             # API, session, migration, analytics, delivery regressions
tests/Frontend/            # Node notification state tests
.env.example               # Server environment template without secrets
PRD_SIVERA.md              # Product requirements
```

## Checks and useful commands

```powershell
php artisan route:list --except-vendor
php artisan schedule:list
php artisan test --compact
node --test tests/Frontend/notifications.test.mjs
npm run build
```

The tests use SQLite in memory. If the PHP CLI does not load SQLite extensions, enable `pdo_sqlite` and `sqlite3` before testing. On a Windows PHP installation where those extension files exist, the suite can be run with:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest --compact
```

`GET /health` returns `{"status":"ok"}` and Laravel also exposes `GET /up` for health checks. If built frontend assets are missing, run `npm run build`.

The focused security checks cover independent sessions after both password update paths, same event IDs across applications, database uniqueness, historical backfills, rollback refusal, ingress limits, rejected-log budgets, SQL exception privacy, and route-template capture. SQLite tests do not replace a migration rehearsal on a restored MySQL/MariaDB database.

## Troubleshooting

| Symptom | Check / action |
| --- | --- |
| Missing Vite manifest or stale UI | Run `npm ci` and `npm run build`; deploy `public/build/` with the matching code. Use `npm run dev` only for development. |
| `could not find driver` | Enable the PHP extension for the selected database. Test runs need `pdo_sqlite` and `sqlite3`; runtime MySQL needs `pdo_mysql`. Confirm CLI and web PHP installations separately. |
| Login fails or session expires after password update | Verify active state and credentials. Other sessions must sign in after any password reset. Check shared session storage, HTTPS cookies, and configuration cache. |
| Production requests rejected despite HTTPS at proxy | Set only actual proxy addresses in `TRUSTED_PROXIES`; make the proxy send the correct scheme/client metadata. |
| Reporter `401` | Check application slug/environment, active credential, secret, timestamp, IP allowlist, and HTTPS/proxy configuration. Do not reuse a nonce. |
| Reporter `413` / `422` | Reduce payload size or fix the event shape/application identity; inspect the API contract above. |
| Reporter `429` | Respect `Retry-After`. Check credential rate limit and shared outbound-IP ingress budget. |
| Reporter `cURL error 28` | Check connectivity, then consider the documented source-app connection/total timeout overrides. |
| No morning summary | Check `schedule:list`, scheduled PHP executable/working directory, timezone, active user email addresses, real `MAIL_*` transport, and delivery logs. Run the interactive email test separately. |
| Missing failed-request log rows | Invalid-signature logs are capped at 60 per minute globally. Requests rejected by the outer throttle or global HTTPS middleware never reach signature logging. Use ingress metrics for totals. |
| Migration rollback refuses global IDs | Different applications now share an event ID. Keep the application-scoped schema and use a forward fix; do not delete events to force rollback. |

For recovery, preserve the database, `APP_KEY`, and configuration together. Investigate failures with metadata and dummy test events; do not paste real secrets or complete sensitive payloads into tickets.
