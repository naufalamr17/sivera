# SIVERA

**Smart Integrated Visibility & Event Response Application** is an internal Laravel application for collecting exceptions from other Laravel applications, grouping repeated errors into issues, and managing incident response in one place.

The dashboard shows date-filtered activity, application error rankings, and the most frequent exceptions with their reported messages. Operators can inspect stack traces, update issue status and severity, receive live in-app/browser alerts, and receive one email summary each morning. The bundled reporter package sends exceptions without changing the source application's existing Laravel logging.

This repository implements the MVP described in [PRD_SIVERA.md](PRD_SIVERA.md). It does not provide application performance monitoring, distributed tracing, release tracking, or browser session replay.

## Requirements

- PHP 8.3 with the extensions required by Laravel and the selected database driver
- Composer
- Node.js and npm for the Vite frontend build
- MySQL or MariaDB for the default configuration

The application uses Laravel 13, Blade, React 19, shadcn/ui components with Radix primitives, Tailwind CSS 4, and Vite 8. React renders workspace navigation, the notification center, and the dashboard; management pages remain server-rendered Blade. Database-backed cache, sessions, and queues are configured in `.env.example`.

The shared SVG logo at `public/sivera-icon.svg` combines a browser window with an ascending bar chart to represent web application monitoring and analytics. The login page, workspace navigation, favicon, and browser notification icon reuse this asset.

## Local setup

Run these commands from the repository root in PowerShell:

```powershell
composer install
npm install
Copy-Item .env.example .env
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

Optional sample applications and issues can be added outside production with `php artisan db:seed --class=SiveraDemoSeeder`. `NotificationRulesSeeder` creates a sample email recipient addressed to `alerts@example.test`; configure a real target before using notifications.

### Background processes

Run the Laravel scheduler for the morning email summary and daily retention cleanup:

```powershell
php artisan schedule:work
```

The digest runs at **07:00 Asia/Jakarta** by default and covers the previous local calendar day. Configure `SIVERA_DIGEST_TIME` and `SIVERA_TIMEZONE` to change this. Cleanup runs at 00:30 in the application's timezone (UTC by default). In deployment, invoke `php artisan schedule:run` every minute using cron or Windows Task Scheduler. Keep the machine/server running when scheduled work is due.

The daily email command sends synchronously and records delivery status; it does not need a queue worker. A source application using `SIVERA_TRANSPORT=queue` still needs its own `php artisan queue:work`.

## Access and workflow

| Area | Admin | Viewer |
| --- | --- | --- |
| Dashboard, applications, issues, stack traces, and history | View | View |
| Own account password | Change | Change |
| Applications and API credentials | Manage | View applications |
| Issue status, severity, and comments | Manage | View |
| Users, email recipients, audit logs, and API logs | Manage/view | No access |

Inactive users cannot access authenticated pages. The dashboard refreshes its summary every 5 seconds by default; `SIVERA_POLL_SECONDS` is limited to 5–10 seconds.

Open **Account → Change password** in the sidebar or mobile navigation to update your own password. Supply the current password, a different new password of at least 12 characters, and its confirmation. Changes are hashed, audited without storing password values, and limited to five attempts per minute. The current session ID is regenerated after success; no email is sent for this action.

To connect an application:

1. Sign in as an admin and create an application with its slug and environment.
2. Generate an API credential on the application page. Copy its client ID and secret when shown; the secret is not shown again.
3. Install the reporter package in the source Laravel application and set its SIVERA environment values.
4. Run `php artisan sivera:test` in the source application, then inspect the new issue in SIVERA.
5. Use the issue page to review occurrences, status history, and comments. Admins can change severity or move an issue through its status workflow.

The Issues page supports application, severity, status, exception class, route, date, and text filters. SIVERA groups related exceptions by application, exception class, file, line, and route. A repeated `event_id` is accepted without creating another occurrence. Resolved issues reopen on a new matching event when `SIVERA_AUTO_REOPEN=true`.

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

Email summary recipients are managed in SIVERA under **Notifications**. Reporter installation does not configure recipients. WhatsApp delivery has been removed.

If the test fails, check the HTTPS URL and certificate, outbound network access, application slug, registered environment, client ID, secret, and server clock. A `401` indicates an authentication or security check failed; `422` indicates an invalid payload; `429` indicates the configured rate limit. The package manifest allows Laravel 10–13, but installation and delivery must still be checked in each target application.

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

Use the credential secret as the HMAC key. Timestamps must be within five minutes of server time, and a nonce cannot be reused for ten minutes. Active credentials, application status, optional IP allowlists, and per-application rate limits are also checked. Successful submissions return HTTP `202` with `accepted`, `duplicate`, and `event_id`; invalid authentication, oversized payloads, invalid data, and rate limits return `401`, `413`, `422`, and `429` respectively. Production API traffic requires HTTPS.

## Notifications and retention

Admins add email recipients under **Notifications**. Each active recipient receives one summary per date at the morning schedule. The summary includes event counts, active/critical issues, top five applications by ERROR/CRITICAL event count, and top five grouped errors with their messages, source locations, and issue links. Days with zero events produce an explicit zero-event summary. There are no per-event emails or frequency/cooldown rules.

Email uses Laravel's `MAIL_*` settings. Configure `MAIL_MAILER=smtp` (or another real transport), the SMTP credentials, and a valid sender. The `log` and `array` transports do not deliver external email. Add recipients separately; `MAIL_FROM_ADDRESS` is the sender, not a recipient.

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

The migration converts existing email rules into daily recipients, deactivates WhatsApp rules, and preserves historical logs. Legacy rule fields remain for historical compatibility and no longer control delivery. Old queued per-event jobs are ignored. Rolling back does not restore the original rule trigger/severity settings; restore a database backup to recover those exact settings.

The bell appears on all authenticated pages for Admins and Viewers. It polls `GET /notifications/feed` every 5–10 seconds independently of dashboard date filters, follows an event ID cursor, and catches up in batches of 50. The list groups repeated activity by issue, retains up to 100 events, and displays up to 20 issues. Read state is stored per user in that browser, rather than synchronized between devices. First visit loads recent activity without desktop alerts for old events. A burst produces a grouped in-app toast.

Click **Enable browser alerts** in the bell to request permission. Desktop notifications require a supported browser and a secure context (trusted HTTPS or localhost). They work while SIVERA is open; Web Push after closing the site is outside this implementation. Notifications show only application and severity on the lock screen. If permission is denied or unsupported, in-app alerts continue. See [MDN's Notifications API guide](https://developer.mozilla.org/en-US/docs/Web/API/Notifications_API/Using_the_Notifications_API).

## Dashboard date filters and rankings

The dashboard defaults to the last seven calendar days, including today, in `SIVERA_TIMEZONE`. Apply an inclusive **From / To** range (maximum 366 days). A single day uses hourly chart buckets; longer ranges use daily buckets. Polling preserves the selected dates and updates cards, chart, rankings, and recent issues. The **Last 7 days** link resets the filter; a page left open overnight retains its original selected range until reset or reload.

Counts use **event receipt timestamps**, converted from local midnight boundaries to UTC. They do not use lifetime issue counters or sampled stack-trace details. The Applications card counts all registered applications. Open, critical, and resolved counts include only issues with events received in the selected period and reflect their **current** status/severity. Rankings include ERROR and CRITICAL only. Top errors show reported exception/message and location, which are evidence for investigation rather than an automatic root-cause diagnosis.

Retention settings are defined in `.env.example` and [`config/sivera.php`](config/sivera.php):

| Data | Default retention |
| --- | ---: |
| Occurrences and API request logs | 30 days |
| Notification logs | 90 days |
| Event receipts, audit logs, and eligible issues | 365 days |

Cleanup removes old issues only when their status is `RESOLVED` or `IGNORED`; open issues remain available. API request logs contain request metadata, not payloads or credential secrets. Event text is sanitized before storage, and application credential secrets are encrypted at rest. Rotated credentials have a 24-hour grace period unless revoked earlier.

## Project layout

```text
app/
  Console/Commands/       # Admin creation, daily digest, retention cleanup
  Http/Controllers/       # Dashboard, issue, application, user, and log pages
  Http/Middleware/        # HTTPS, active-user, and API signature checks
  Http/Requests/          # Input validation
  Jobs/                   # Daily digest delivery (called synchronously)
  Models/                 # Users, applications, issues, logs, and rules
  Services/Sivera/        # Event ingestion, date analytics, daily digest logic
config/                   # Application and SIVERA settings
database/
  factories/              # Test data builders
  migrations/             # Schema changes
  seeders/                # Local users and optional demo data
packages/sivera-reporter/ # Composer package installed in source applications
public/                   # Frontend assets and SIVERA icon
resources/views/          # Blade management pages and email templates
resources/js/             # React workspace/dashboard and shadcn/ui components
resources/css/            # Shared neutral theme and management page styles
routes/                    # Web, event API, and scheduled digest/cleanup routes
tests/                    # Pest tests and Node notification state tests
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
