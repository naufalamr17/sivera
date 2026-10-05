# SIVERA (On Progress)

**Smart Integrated Visibility & Event Response Application** is an internal Laravel application for collecting exceptions from other Laravel applications, grouping repeated errors into issues, and managing incident response in one place.

The dashboard shows recent activity and issue counts. Operators can inspect occurrences and stack traces, update issue status and severity, and send rule-based email or Qontak alerts. The bundled reporter package sends exceptions without changing the source application's existing Laravel logging.

This repository implements the MVP described in [PRD_SIVERA.md](PRD_SIVERA.md). It does not provide application performance monitoring, distributed tracing, release tracking, or browser session replay.

## Requirements

- PHP 8.3 with the extensions required by Laravel and the selected database driver
- Composer
- Node.js and npm for the Vite frontend build
- MySQL or MariaDB for the default configuration

The application uses Laravel 13, Blade, Tailwind CSS 4, and Vite 8. Database-backed cache, sessions, and queues are configured in `.env.example`.

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

Optional sample applications and issues can be added outside production with `php artisan db:seed --class=SiveraDemoSeeder`. `NotificationRulesSeeder` creates a sample rule addressed to `alerts@example.test`; configure a real target before using notifications.

### Background processes

Start a queue worker to deliver email and Qontak notifications:

```powershell
php artisan queue:work
```

Run the Laravel scheduler so retention cleanup executes daily at 00:30 in the application's timezone (UTC by default). For local development, `php artisan schedule:work` is sufficient. In deployment, invoke `php artisan schedule:run` every minute through cron or Windows Task Scheduler. Cleanup can also be run manually with `php artisan sivera:cleanup`.

## Access and workflow

| Area | Admin | Viewer |
| --- | --- | --- |
| Dashboard, applications, issues, stack traces, and history | View | View |
| Applications and API credentials | Manage | View applications |
| Issue status, severity, and comments | Manage | View |
| Users, notification rules, audit logs, and API logs | Manage/view | No access |

Inactive users cannot access authenticated pages. The dashboard refreshes its summary every 5 seconds by default; `SIVERA_POLL_SECONDS` is limited to 5–10 seconds.

To connect an application:

1. Sign in as an admin and create an application with its slug and environment.
2. Generate an API credential on the application page. Copy its client ID and secret when shown; the secret is not shown again.
3. Install the reporter package in the source Laravel application and set its SIVERA environment values.
4. Run `php artisan sivera:test` in the source application, then inspect the new issue in SIVERA.
5. Use the issue page to review occurrences, status history, and comments. Admins can change severity or move an issue through its status workflow.

The Issues page supports application, severity, status, exception class, route, date, and text filters. SIVERA groups related exceptions by application, exception class, file, line, and route. A repeated `event_id` is accepted without creating another occurrence. Resolved issues reopen on a new matching event when `SIVERA_AUTO_REOPEN=true`.

## Install the reporter in another Laravel application

The reporter lives at [`packages/sivera-reporter`](packages/sivera-reporter). It is a separate Composer package; it is not installed as a dependency of the SIVERA server. Add it to the **source application's** `composer.json` using a path repository, with a path relative to that application's root:

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
composer require "mlp/sivera-reporter:@dev"
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

Admins create email or Qontak rules under **Notifications**. Available triggers are `new_critical`, `becomes_critical`, `reopened`, and `high_frequency`. Rules support severity filtering, a frequency window and threshold, and a per-issue cooldown. Notification attempts are recorded and delivered by the queue worker.

Email uses Laravel's `MAIL_*` settings. `.env.example` defaults to `MAIL_MAILER=log`, so configure a real mail transport to send messages. Qontak uses `QONTAK_BASE_URL`, `QONTAK_TOKEN`, `QONTAK_CHANNEL_ID`, `QONTAK_TEMPLATE_ID`, and `QONTAK_LANG`. The default `QONTAK_LANG=id` is the provider template language code; set it to match the approved template independently of the English dashboard language.

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
  Console/Commands/       # Admin creation and retention cleanup
  Http/Controllers/       # Dashboard, issue, application, user, and log pages
  Http/Middleware/        # HTTPS, active-user, and API signature checks
  Http/Requests/          # Input validation
  Jobs/                   # Queued notification delivery
  Models/                 # Users, applications, issues, logs, and rules
  Services/Sivera/        # Event ingestion and notification logic
config/                   # Application and SIVERA settings
database/
  factories/              # Test data builders
  migrations/             # Schema changes
  seeders/                # Local users and optional demo data
packages/sivera-reporter/ # Composer package installed in source applications
public/                   # Frontend assets and SIVERA icon
resources/views/          # Blade UI and email template
routes/                    # Web, event API, and scheduled cleanup routes
tests/                    # Pest unit and feature tests
PRD_SIVERA.md              # Product requirements
```

## Checks and useful commands

```powershell
php artisan route:list --except-vendor
php artisan schedule:list
php artisan test --compact
```

The tests use SQLite in memory. If the PHP CLI does not load SQLite extensions, enable `pdo_sqlite` and `sqlite3` before testing. On a Windows PHP installation where those extension files exist, the suite can be run with:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest --compact
```

`GET /health` returns `{"status":"ok"}` and Laravel also exposes `GET /up` for health checks. If built frontend assets are missing, run `npm run build`.
