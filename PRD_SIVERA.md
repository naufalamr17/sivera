# PRD — SIVERA

> **Smart Integrated Visibility & Event Response Application**  
> **Focus:** Centralized Application Error Monitoring & Incident Response  
> **Document:** Product Requirements Document (PRD)  
> **Target implementation:** Laravel + MySQL/MariaDB on the existing Azure Cloud Server / cPanel  
> **Status:** MVP; notification and dashboard scope revised 2026-10-27
> **Implementation language:** PHP / Laravel  
> **Target consumers:** Internal Laravel applications  
> **Note:** Release/version tracking is outside the MVP scope.

---

## 1. Product Summary

| Name | Full name | Focus |
|---|---|---|
| **SIVERA** | **Smart Integrated Visibility & Event Response Application** | **Centralized Application Error Monitoring & Incident Response** |

SIVERA is an internal application that centrally receives, groups, monitors, and handles errors from multiple internal applications.

SIVERA must automatically receive exceptions through a secure API, group identical errors into one issue, display stack traces and troubleshooting metadata, and provide an error response workflow without requiring users to open production server logs directly.

Source applications should require as few changes as possible.

---

# 2. Background

Several internal applications currently run on the same server, and each has its own log, for example:

```text
storage/logs/laravel.log
```

Checking errors still depends on server/cPanel access and manual log inspection.

Problems to solve:

- Errors may be discovered late.
- Monitoring is scattered across applications.
- Users need server/cPanel access for troubleshooting.
- Identical errors can recur without being grouped.
- There is no centralized issue history.
- There is no error response status workflow.
- There are no centralized notifications.
- There is no standard secure ingestion mechanism across applications.

SIVERA must centralize error monitoring without replacing existing Laravel logging.

---

# 3. Product Goals

SIVERA must:

1. Automatically receive exceptions from multiple Laravel applications.
2. Require minimal changes to source applications.
3. Preserve source application availability when SIVERA is down.
4. Use a secure ingestion API.
5. Group identical errors into one issue.
6. Store the occurrence count, first seen time, and last seen time.
7. Display stack traces relevant to troubleshooting.
8. Provide an issue management workflow.
9. Provide a centralized dashboard.
10. Send notifications for important issues.
11. Protect credentials and sensitive data.
12. Run efficiently on the existing Azure server.

---

# 4. Non-Goals / Out of Scope MVP

The following features are **outside the MVP scope**:

- Release version tracking.
- Git commit tracking.
- Deployment tracking.
- Performance APM.
- Distributed tracing.
- Frontend session replay.
- JavaScript browser monitoring.
- CPU monitoring.
- RAM monitoring.
- Infrastructure monitoring.
- Full log aggregation.
- Database query profiling.
- AI root-cause analysis.
- Automatic code fixing.
- Automatic deployment.

MVP focus:

```text
Exception
   ↓
Secure Ingestion
   ↓
Centralized Issue
   ↓
Monitoring
   ↓
Response
   ↓
Resolution
```

---

# 5. User Roles

SIVERA has only **two roles**.

## 5.1 Admin

Admins have full operational access to SIVERA.

Admins can:

- Sign in to the dashboard.
- View all applications.
- View all issues.
- View stack traces.
- View occurrences.
- View permitted request metadata.
- Create applications.
- Activate or deactivate applications.
- Generate application credentials.
- Rotate credentials.
- Revoke credentials.
- Change issue severity.
- Change issue status.
- Add notes or comments.
- Resolve issues.
- Reopen issues.
- Ignore issues.
- Manage notification rules.
- Configure email notifications.
- View API request logs.
- View audit logs.
- Configure retention policies.
- Manage Viewer users.
- Manage other Admin users.

## 5.2 Viewer

Viewers have **read-only** access to monitoring and administration data. They may change their own account password after verifying the current password.

Viewers can:

- Sign in.
- View the dashboard.
- View the application list.
- View issues.
- View severity.
- View issue status.
- View occurrence counts.
- View first seen time.
- View last seen time.
- View issue details.
- View stack traces.
- View status history.
- View comments and history.

Viewers cannot:

- Create applications.
- Change credentials.
- Change status.
- Change severity.
- Resolve issues.
- Reopen issues.
- Ignore issues.
- Add comments.
- Change notifications.
- Manage users.
- Change system configuration.

---

# 6. High-Level Architecture

```text
┌───────────────────────────────┐
│ Application A                 │
│ Application B                 │
│ Application C                 │
│ Application D                 │
└──────────────┬────────────────┘
               │
               │ naufalamr17/sivera-reporter
               │
               │ HTTPS
               │ HMAC-SHA256
               ▼
┌──────────────────────────────────────────────┐
│                  SIVERA                      │
│                                              │
│  Secure Ingestion API                        │
│        │                                     │
│        ▼                                     │
│  Authentication / Validation                 │
│        │                                     │
│        ▼                                     │
│  Sanitization / Redaction                    │
│        │                                     │
│        ▼                                     │
│  Fingerprinting                              │
│        │                                     │
│        ▼                                     │
│  Issue + Occurrence                          │
│        │                                     │
│        ├─────────────► Notification          │
│        │                  │                  │
│        │              Daily email + in-app         │
│        ▼                                     │
│  Admin / Viewer Dashboard                    │
└──────────────────────────────────────────────┘
```

---

# 6.1 Near-Real-Time Dashboard Requirement

The SIVERA MVP uses **polling every 5–10 seconds**, with a default of **5 seconds**.

SIVERA does not use hot reload for production live updates.

Hot reload / HMR is used only for frontend development, for example through:

```bash
npm run dev
```

Near-real-time updates in production work as follows:

```text
Application Error
       │
       ▼
SIVERA Secure API
       │
       ▼
Database
       │
       ▼
Browser Polling
every 5 seconds
       │
       ▼
Dashboard updated
without a full-page reload
```

Requirements:

- The dashboard must update data without a full-page reload.
- The default polling interval is 5 seconds.
- The interval can be configured between 5 and 10 seconds.
- Polling retrieves only changed data or the latest summary.
- Do not rerender the whole page on each poll.
- Polling endpoints must be lightweight and use pagination or limits.
- A new CRITICAL issue may trigger an in-app toast or alert on the next poll.
- Polling must not fetch heavy data such as all stack traces or full histories every 5 seconds.
- Backend event ingestion must not depend on UI polling.
- The architecture must permit a future move to WebSocket/Laravel Reverb without changing core ingestion.

Example response time targets:

```text
API receives error       : immediately
Issue saved to database  : immediately
Dashboard displays error : within about 5 seconds at the default interval
```

Suggested polling endpoints:

```http
GET /dashboard/live-summary?from=YYYY-MM-DD&to=YYYY-MM-DD
GET /notifications/feed?after={event_receipt_id}
```

These endpoints are only for the authenticated SIVERA dashboard and must enforce `admin` / `viewer` authorization.

---

# 7. Core Design Principles

## 7.1 Minimal Application Changes

Integration must require as few changes to source applications as possible.

Target integration:

```bash
composer require naufalamr17/sivera-reporter
php artisan sivera:install
```

Then add the required `.env` configuration.

Integration must not require changes to:

- Controller.
- Model.
- Service.
- Repository.
- Business routes.
- Business logic.

The package must use Laravel auto-discovery and the exception reporting hook.

---

## 7.2 Fail-Safe by Default

SIVERA must not become a critical dependency of source applications.

If:

```text
SIVERA DOWN
NETWORK TIMEOUT
DNS ERROR
HTTP 500
HTTP 503
```

The source application must continue its normal behavior.

The reporter must not throw a new exception into the application request.

Principle:

```text
Application Error
      │
      ├── Default Laravel logging continues
      │
      └── SIVERA reporting is best effort
```

---

## 7.3 Secure by Default

The ingestion API must use at least:

```text
HTTPS
+
Application ID
+
Client ID
+
Timestamp
+
Nonce
+
HMAC-SHA256 Signature
+
Rate Limiting
```

Optional hardening:

```text
Source IP Allowlist
```

---

## 7.4 Privacy by Default

By default, SIVERA must not receive:

- Password.
- Authorization header.
- Cookies.
- Session ID.
- API token.
- Client secret.
- Database credential.
- SMTP credential.
- Full request body.

---

# 8. Target Technology

## Backend

```text
Laravel
PHP
MySQL / MariaDB
```

## Frontend

The frontend uses Laravel Blade with React 19 islands, shadcn/ui components backed by Radix, Tailwind CSS 4, and Vite 8. Workspace navigation, the notification center, and dashboard are React components. Management forms and detail pages remain Blade.

Use a neutral, accessible interface: clear typography, restrained borders and shadows, consistent spacing, meaningful severity colors, and responsive tables. Mobile navigation uses an accessible sheet with keyboard/focus handling. Do not add decorative gradients or redundant summary cards.

Authenticated Fetch polling defaults to 5 seconds and is configurable between 5 and 10 seconds:
- `GET /dashboard/live-summary?from=YYYY-MM-DD&to=YYYY-MM-DD` returns date-filtered counters, activity buckets, latest issues, application ranking, and top errors.
- `GET /notifications/feed?after={event_receipt_id}` returns at most 50 events per catch-up batch. Initial load returns the latest 20 events.
- Polling updates React components without reloading the page. Retain the selected date range.
- No stack traces, request bodies, or credentials are included in polling payloads.
- In-app toasts and the bell work independently of email delivery.
- Browser notifications are opt-in, require a secure context and browser support, and work while SIVERA is open. Closed-site Web Push is outside scope.

No WebSocket server, Reverb, or Livewire is required. Compile frontend assets with `npm run build`; production does not require a Node process.

---

## Infrastructure

SIVERA must run on the existing server without requiring:

```text
Kafka
ClickHouse
Elasticsearch
Docker
Kubernetes
Redis Cluster
PostgreSQL Cluster
```

Redis may be used if already available, but is not mandatory.

---

# 9. Application Integration Package

Internal package name:

```text
naufalamr17/sivera-reporter
```

The package is responsible for:

- Hooking into Laravel exception reporting.
- Building the payload.
- Sanitizing data.
- Signing requests.
- Sending events to SIVERA.
- Handling timeouts.
- Handling failures silently.
- Providing a test command.

---

# 10. Reporter Installation

Intended usage:

```bash
composer require naufalamr17/sivera-reporter
```

Then:

```bash
php artisan sivera:install
```

The package uses Laravel package auto-discovery.

No manual changes to application controllers are required.

---

# 11. Application Environment Configuration

Example:

```env
SIVERA_ENABLED=true

SIVERA_URL=https://monitor.example.internal
SIVERA_APP_ID=sibaris
SIVERA_CLIENT_ID=cli_xxxxxxxxx
SIVERA_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

SIVERA_ENVIRONMENT=production

SIVERA_CONNECT_TIMEOUT=0.5
SIVERA_TIMEOUT=2
```

Do not use:

```env
SIVERA_RELEASE=
SIVERA_GIT_COMMIT=
```

because release/version tracking is not required.

---

# 12. Reporter Configuration

File:

```text
config/sivera.php
```

Example:

```php
<?php

return [

    'enabled' => env('SIVERA_ENABLED', true),

    'url' => env('SIVERA_URL'),

    'app_id' => env('SIVERA_APP_ID'),

    'client_id' => env('SIVERA_CLIENT_ID'),

    'secret' => env('SIVERA_SECRET'),

    'environment' => env('SIVERA_ENVIRONMENT', 'production'),

    'connect_timeout' => (float) env('SIVERA_CONNECT_TIMEOUT', 0.5),

    'timeout' => (float) env('SIVERA_TIMEOUT', 2),

    'capture_user' => true,

    'capture_request_body' => false,

];
```

---

# 13. Exception Capture

The reporter must integrate with Laravel exception reporting.

Concept:

```text
Throwable
   │
   ▼
Laravel Exception Handler
   │
   ├── existing Laravel log
   │
   └── SIVERA Reporter
             │
             ▼
          SIVERA API
```

Existing logging must remain enabled.

---

# 14. Fail-Safe Reporter Requirements

The reporter must:

- Use short timeouts.
- Avoid excessive blocking.
- Avoid infinite retries.
- Never throw a new exception.
- Never show monitoring failures to end users.
- Never fail a production request because of SIVERA.

Example implementation principle:

```php
try {
    $reporter->send($event);
} catch (\Throwable $reportingException) {
    // Swallow reporter exception.
    // Never break the target application.
}
```

---

# 15. Transport Modes

The MVP must support:

## HTTP Mode

Default:

```env
SIVERA_TRANSPORT=http
```

Use this when the source application has no queue worker.

## Queue Mode

Optional:

```env
SIVERA_TRANSPORT=queue
```

Use this if the source application already has a queue worker.

Do not force source applications to set up a new queue.

---

# 16. API Endpoint

Main endpoint:

```http
POST /api/v1/events
```

Content-Type:

```http
application/json
```

Maximum payload:

```text
256 KB
```

---

# 17. API Authentication

The API must not rely on a simple bearer token alone.

Use:

```text
Application ID
Client ID
Timestamp
Nonce
HMAC-SHA256
```

Request headers:

```http
X-SIVERA-App-ID: sibaris
X-SIVERA-Client-ID: cli_xxxxxxxxx
X-SIVERA-Timestamp: 1791162000
X-SIVERA-Nonce: 0199abcd-xxxx-xxxx-xxxx
X-SIVERA-Signature: sha256=xxxxxxxxxxxxxxxxxxxxxxxx
```

---

# 18. HMAC Signing

Canonical string:

```text
HTTP_METHOD
REQUEST_PATH
TIMESTAMP
NONCE
SHA256(BODY)
```

Example:

```text
POST
/api/v1/events
1791162000
0199abcd-xxxx
bc8c401fd5...
```

Signature:

```text
HMAC-SHA256(
    canonical_string,
    application_secret
)
```

Verification must use constant-time comparison.

PHP example:

```php
hash_equals($expectedSignature, $providedSignature);
```

---

# 19. Replay Attack Protection

A request is valid only if its timestamp falls within the allowed window.

Default:

```text
± 5 minutes
```

Expired request:

```http
401 Unauthorized
```

Nonces must be unique.

The same nonce must not be reused during the validity window.

Example:

```text
Request #1
Nonce ABC
→ ACCEPTED

Request #2
Nonce ABC
→ REJECTED
```

Nonces may be stored temporarily for:

```text
10 minutes
```

---

# 20. Per-Application Credentials

Each application has its own credentials.

Example:

```text
Application:
SIBARIS

App ID:
sibaris

Client ID:
cli_abc123

Secret:
********************************
```

Application A's credentials must not be usable to send events on behalf of application B.

Validation must ensure that:

```text
App ID ↔ Client ID ↔ Secret
```

form a valid combination.

---

# 21. Credential Storage

HMAC secrets must not be stored as plaintext.

Because the server needs the original secret to verify HMAC signatures, use Laravel encryption:

```php
Crypt::encryptString($secret);
```

The database stores the encrypted secret.

Laravel's `APP_KEY` must be protected and must not be exposed in a public repository.

---

# 22. Secret Rotation

Admins can:

```text
Generate New Secret
```

The system may temporarily support:

```text
Current Secret
Previous Secret
```

Grace period default:

```text
24 hours
```

Flow:

```text
Admin generates a new secret
        ↓
Update the application's .env
        ↓
Run connection test
        ↓
Verify that the event arrives
        ↓
Revoke previous secret
```

The new secret is shown in full only once when generated.

---

# 23. Optional IP Allowlist

Each application may have:

```text
allowed_ips
```

Example:

```text
20.xxx.xxx.xxx
```

The IP allowlist provides additional defense.

HMAC remains mandatory.

---

# 24. Rate Limiting

Default per credential:

```text
60 requests / minute
```

Burst allowance:

```text
120 requests / minute
```

The rate limit must be configurable per application.

Return:

```http
429 Too Many Requests
```

The API must send:

```http
Retry-After
```

when applicable.

---

# 25. Payload Structure

Example payload:

```json
{
  "event_id": "01K6J94PBRQ3YTABCDEFGHJK",
  "application": "sibaris",
  "environment": "production",
  "severity": "error",

  "exception": {
    "class": "Illuminate\\Database\\QueryException",
    "message": "SQLSTATE[23000]: Integrity constraint violation",
    "file": "app/Services/InvoiceService.php",
    "line": 187
  },

  "request": {
    "method": "POST",
    "route": "invoice-finance.store",
    "path": "/invoice-finance"
  },

  "user": {
    "id": 182
  },

  "stack_trace": [
    {
      "file": "app/Services/InvoiceService.php",
      "line": 187,
      "function": "saveInvoice",
      "class": "App\\Services\\InvoiceService"
    },
    {
      "file": "app/Http/Controllers/InvoiceController.php",
      "line": 148,
      "function": "store",
      "class": "App\\Http\\Controllers\\InvoiceController"
    }
  ],

  "occurred_at": "2026-10-05T09:32:14+07:00"
}
```

---

# 26. Event ID / Idempotency

Every event must have:

```text
event_id
```

Use:

```text
ULID
```

or:

```text
UUID
```

The database must have a unique constraint on:

```text
event_id
```

Duplicate requests must not create duplicate occurrences.

A duplicate still returns:

```http
202 Accepted
```

Example:

```json
{
  "accepted": true,
  "duplicate": true,
  "event_id": "01K6..."
}
```

---

# 27. Sensitive Data Redaction

The reporter must sanitize data before sending the payload.

At a minimum, redact keys containing:

```text
password
passwd
password_confirmation
current_password
new_password

authorization
bearer
access_token
refresh_token
token

api_key
apikey

secret
client_secret

cookie
set-cookie
session

database_password
smtp_password
```

Wildcard matching:

```text
*password*
*secret*
*token*
*authorization*
*cookie*
```

Values must become:

```text
[REDACTED]
```

---

# 28. Request Body Policy

Default:

```text
capture_request_body = false
```

The reporter must not use:

```php
$request->all()
```

as the default payload.

Request metadata that may be sent:

```text
HTTP method
named route
path
selected non-sensitive query metadata
```

Sending the full body may only be a future opt-in feature with an explicit allowlist.

---

# 29. User Data Policy

Default user context:

```json
{
  "user": {
    "id": 182
  }
}
```

The MVP does not need to send automatically:

- Full name.
- Email.
- Phone number.
- National identity number (NIK).
- Address.

Purpose:

```text
Data minimization
```

---

# 30. Stack Trace Policy

Stack traces may be stored.

Before storage:

- Normalize path.
- Avoid displaying absolute filesystem paths unless necessary.
- Remove arguments that may contain credentials.
- Prioritize application frames.

Example:

Do not display:

```text
/home/account/public_html/sibaris/app/Services/InvoiceService.php
```

Display only:

```text
app/Services/InvoiceService.php
```

---

# 31. Error Fingerprinting

Fingerprinting goal:

```text
100 identical errors
=
1 issue + 100 occurrences
```

Fingerprint MVP:

```text
application_id
+
exception_class
+
normalized_file
+
line
+
route
```

Hash:

```text
SHA-256
```

Example input:

```text
sibaris
Illuminate\Database\QueryException
app/Services/InvoiceService.php
187
invoice-finance.store
```

Do not use the full message in the fingerprint because its value may change even when the root cause is the same.

---

# 32. Issue Lifecycle

Status:

```text
NEW
ACKNOWLEDGED
INVESTIGATING
RESOLVED
IGNORED
REOPENED
```

Normal flow:

```text
NEW
 ↓
ACKNOWLEDGED
 ↓
INVESTIGATING
 ↓
RESOLVED
```

If a `RESOLVED` issue receives a new occurrence:

```text
RESOLVED
 ↓
REOPENED
```

Automatic reopening must be configurable.

---

# 33. Severity

Severity MVP:

```text
INFO
WARNING
ERROR
CRITICAL
```

Default mapping:

```text
Unhandled exception → ERROR
```

Admins can change severity.

Severity is supplied on initial ingestion and can be changed by an Admin. Daily email does not escalate severity.

---

# 34. Issue Data Model

Table:

```text
issues
```

Suggested fields:

```text
id
uuid
application_id
fingerprint

severity
status

exception_class
message

file
line

route
method

first_seen_at
last_seen_at
occurrence_count

acknowledged_at
investigating_at
resolved_at
ignored_at
reopened_at

created_at
updated_at
```

Do not include:

```text
release
version
git_commit
deployment_id
```

---

# 35. Occurrence Data Model

Table:

```text
issue_occurrences
```

Fields:

```text
id
issue_id
event_id

stack_trace_json
request_metadata_json
user_reference

source_ip

occurred_at
created_at
```

`event_id` must be unique.

---

# 36. Applications Data Model

Table:

```text
applications
```

Fields:

```text
id
uuid

name
slug
description

environment

is_active

rate_limit_per_minute

created_at
updated_at
```

---

# 37. Application Credentials Data Model

Table:

```text
application_credentials
```

Fields:

```text
id
application_id

client_id
encrypted_secret

valid_from
expires_at

last_used_at
last_ip

is_active

created_at
revoked_at
```

---

# 38. Users Data Model

Table:

```text
users
```

Minimum fields:

```text
id
name
email
password
role

is_active

last_login_at

created_at
updated_at
```

Allowed roles:

```text
admin
viewer
```

Use an enum or an equivalent constrained value.

---

# 39. Issue History

Table:

```text
issue_status_histories
```

Fields:

```text
id
issue_id

from_status
to_status

changed_by

created_at
```

---

# 40. Issue Comments

Only Admins may add comments.

Table:

```text
issue_comments
```

Fields:

```text
id
issue_id
user_id
comment
created_at
updated_at
```

Viewers may only read them.

---

# 41. Audit Logs

Table:

```text
audit_logs
```

Minimum audit events:

```text
Admin login
Admin create application
Admin disable application
Admin generate credential
Admin rotate credential
Admin revoke credential
Admin change issue status
Admin change severity
Admin add comment
Admin change notification rule
Admin create/update/deactivate user
```

Fields:

```text
id
user_id
action
subject_type
subject_id
metadata_json
ip_address
created_at
```

Do not store secrets in audit metadata.

---

# 42. API Request Security Logs

Table:

```text
api_request_logs
```

Fields:

```text
id

application_id
client_id

source_ip

http_status
signature_valid

request_size

received_at
```

Do not store:

```text
secret
signature
full payload
authorization
```

---

# 43. Dashboard

The dashboard displays:
- Events received in the selected date range.
- Open and critical issues active during the selected period, using their current status/severity.
- Total registered applications, regardless of date range.
- Resolved issues active during the period, shown in the chart footer.
- Hourly activity for one day; daily activity for longer ranges.
- Top five applications by ERROR/CRITICAL event count, with distinct issue counts.
- Top five grouped errors by period event count: exception class, stored message, application, severity, source location, route, and issue link.
- Recent issues with activity in the selected period, paginated at ten per page. Preserve the selected dates and page during polling.

Use unsampled event receipts and receipt timestamps, not lifetime occurrence counters, for period counts. Reported messages explain the observed failure; they do not prove the root cause. Current severity/status can change historical summaries.

---

# 44. Dashboard Filters

Dashboard:
- Inclusive From/To dates, defaulting to the last seven calendar days including today in `SIVERA_TIMEZONE` (Asia/Jakarta by default).
- Maximum range: 366 calendar days.
- Convert local midnight boundaries to UTC and use an exclusive next-day upper bound.
- Validate dates and reject reversed or invalid ranges on both HTML and polling endpoints.
- Polling retains dates; the Last 7 days link resets them to the default weekly range.

The issue list separately supports application, severity, status, exception type, date range, route, and text search (exception class, message, file, route, issue ID). Its date filter applies to issue last-seen time.

---

# 45. Issue Detail Screen

Minimum UI:

```text
Issue ID

Application
Environment
Severity
Status

Exception Class
Message

File
Line
Route
HTTP Method

First Seen
Last Seen
Occurrence Count

Stack Trace

Occurrence History
Status History
Comments
```

Admins have these actions:

```text
Acknowledge
Set Investigating
Resolve
Ignore
Reopen
Change Severity
Add Comment
```

Viewers have no write actions.

---

# 46. Application Management Screen

Admins can:

```text
Create Application
Edit Application
Enable Application
Disable Application

Generate Credential
Rotate Secret
Revoke Credential

Configure IP Allowlist
Configure Rate Limit
```

The full secret may only be displayed when first generated or rotated.

After the page is closed:

```text
the secret cannot be retrieved again
```

The admin must rotate the secret if it is lost.

---

# 47. Notification

The current notification channels are daily email, live in-app activity, and optional desktop browser notifications. WhatsApp delivery is removed.

Email is pulled by the Laravel scheduler once each morning at `SIVERA_DIGEST_TIME`, default 07:00 in `SIVERA_TIMEZONE=Asia/Jakarta`. It covers the previous local calendar day. There are no event-triggered emails. Admins manage recipient names, email addresses, and active state under Notifications. Mail transport credentials remain in server environment settings.

# 48. Digest Delivery and Deduplication

Run `php artisan sivera:send-digest` for yesterday, or `--date=YYYY-MM-DD` for a completed local day. The command sends synchronously, needs the scheduler but no mail queue worker, and uses a lock against concurrent runs. Each recipient/date pair has one delivery log, shared across duplicate recipient rules.

Successful pairs are skipped when rerun. Failed sends remain visible and may be retried with the same command. A failure for one recipient must not prevent other recipients from receiving their summaries. A zero-event day still sends a clear summary. SMTP acceptance and database updates cannot be atomic: a crash between them can duplicate delivery on retry.

# 49. Daily Email Content

The HTML and text summary includes:
- Local summary date and timezone.
- Total received events, active issues, and critical issues.
- Top five applications by ERROR/CRITICAL events.
- Top five errors with exception, stored message, period count, source location, route, and authenticated issue link.
- A link to the dashboard for the same date.
- A note that severity/status reflect current values.

`MAIL_FROM_ADDRESS` is the sender. Recipients must be configured separately. A log/array mailer does not deliver real email.

# 50. Live In-App and Browser Alerts

Admin and Viewer receive activity through authenticated polling on every workspace page. Use receipt IDs as cursors so an unchanged critical count cannot hide a new issue. Catch up in batches of 50; do not skip overflowing batches.

Retain up to 100 recent events and display up to 20 grouped issues in the bell. A new batch triggers one grouped toast. Read state is per user/browser in local storage. On the first visit, show recent activity without generating desktop notifications for historical events.

Request browser permission only after clicking Enable browser alerts. Use a trusted secure context and handle denied/unsupported states without disabling in-app alerts. Desktop notifications show only application and severity, rather than detailed error messages on the lock screen. Notifications require an open SIVERA page; closed-site Web Push is not included.

# 51. Notification Data Model

`notification_rules` stores daily email recipients: id, name, trigger (`daily_digest`), channel (`email`), target, is_active, timestamps. Legacy trigger/threshold/cooldown/target-name fields are retained for compatibility and no longer control delivery.

`notification_logs` stores id, nullable issue_id (historical per-issue records), notification_rule_id, channel, status, response_code (historical), sent_at, created_at, digest_date, digest_recipient. A unique constraint covers digest_date + normalized digest_recipient.

The migration converts existing email rules into daily recipients, disables WhatsApp rules, and preserves historical logs. Old queued per-event jobs do not send. Rollback keeps issue_id nullable for historical digests and does not recreate exact original trigger/severity settings; restore a backup if those values are needed.

The live activity feed reuses `event_receipts` and issue/application relationships; it does not need email rules or a separate notifications table. Do not store provider credentials in logs.

---

# 52. Health Endpoint

Endpoint:

```http
GET /health
```

Response:

```json
{
  "status": "ok"
}
```

The endpoint must not expose:

```text
PHP version
Laravel version
DB engine
hostname
filesystem path
APP_ENV details
credentials
```

---

# 53. Reporter Test Command

Command:

```bash
php artisan sivera:test
```

Expected output:

```text
SIVERA Reporter

Application:
SIBARIS

Endpoint:
https://monitor.example.internal

Connection:
OK

Authentication:
OK

Signature:
OK

Event Delivery:
OK

Result:
Successfully connected to SIVERA.
```

The command must send a synthetic test event.

---

# 54. API Response Contract

## Accepted

```http
202 Accepted
```

```json
{
  "accepted": true,
  "event_id": "01K6..."
}
```

## Duplicate

```http
202 Accepted
```

```json
{
  "accepted": true,
  "duplicate": true,
  "event_id": "01K6..."
}
```

## Unauthorized

```http
401 Unauthorized
```

```json
{
  "error": "Unauthorized"
}
```

## Invalid Payload

```http
422 Unprocessable Entity
```

```json
{
  "error": "Invalid payload"
}
```

## Rate Limit

```http
429 Too Many Requests
```

```json
{
  "error": "Too many requests"
}
```

## Payload Too Large

```http
413 Payload Too Large
```

---

# 55. API Error Information Leakage

The API must not return internal details such as:

```text
Client exists but secret mismatched
Database table name
SQL query
Stack trace
Filesystem path
Encryption failure
```

For authentication failures, return only:

```json
{
  "error": "Unauthorized"
}
```

---

# 56. Dashboard Authentication Security

The dashboard must use:

- HTTPS.
- Laravel CSRF protection.
- Secure cookies.
- HTTP-only cookies.
- SameSite cookies.
- Login throttling.
- Session expiration.
- Password hashing Laravel default.
- Authorization middleware per role.

Admin routes must enforce role authorization.

Viewer monitoring routes must be read-only. The authenticated own-account password form is the only account-management write action available to Viewers.

---

# 57. Authorization Matrix

| Feature | Admin | Viewer |
|---|---:|---:|
| Change own password | ✅ | ✅ |
| Dashboard | ✅ | ✅ |
| View Applications | ✅ | ✅ |
| Create Application | ✅ | ❌ |
| Update Application | ✅ | ❌ |
| Disable Application | ✅ | ❌ |
| View Issues | ✅ | ✅ |
| View Stack Trace | ✅ | ✅ |
| Change Status | ✅ | ❌ |
| Change Severity | ✅ | ❌ |
| Resolve / Reopen | ✅ | ❌ |
| Add Comment | ✅ | ❌ |
| View Comments | ✅ | ✅ |
| Generate Credentials | ✅ | ❌ |
| Rotate Credentials | ✅ | ❌ |
| Notification Settings | ✅ | ❌ |
| View Audit Log | ✅ | ❌ |
| User Management | ✅ | ❌ |

---

# 58. Data Retention

Historical monitoring data uses a shared retention policy of three calendar years:

| Data | Retention |
|---|---:|
| Detailed Occurrences | 3 years |
| Stack Trace | 3 years |
| API Security Logs | 3 years |
| Notification Logs | 3 years |
| Event Receipts | 3 years |
| Issues of every status | 3 years since last event |
| Audit Logs | 3 years |

Configure `SIVERA_RETENTION_YEARS=3`; the minimum is three years. Legacy per-category `SIVERA_*_RETENTION_DAYS` settings no longer apply. Use calendar-year subtraction without leap-day overflow, rather than a fixed 1,095-day approximation.

Daily cleanup permanently deletes records older than the cutoff. Open issues are included when their last event is older than the cutoff. Deleting an issue cascades to its occurrences, comments, status history, and per-issue notification logs. Accounts, applications, credentials, and email recipient settings are not historical monitoring data and are not purged.

Display the retention policy on authenticated workspace pages. Paginate all main record lists, including recent dashboard issues, notification recipients, and delivery history. Recipient and delivery pagination must be independent and preserve each other's query parameters.

---

# 59. Database Growth Protection

SIVERA must not store unlimited occurrence details.

Sampling example:

```text
Occurrence 1–100
→ store details

Occurrence 101–1,000
→ store 1 detailed occurrence per 10 events

Occurrence >1,000
→ store 1 detailed occurrence per 100 events
```

However:

```text
occurrence_count
```

must still increase for every accepted event.

---

# 60. Cleanup Scheduler

Laravel Scheduler must run cleanup.

Command:

```bash
php artisan sivera:cleanup
```

Schedule:

```text
00:30 daily cleanup in the application timezone (UTC by default)
```

---

# 61. Performance Requirements

Target SIVERA MVP:

```text
API ingestion p95 < 500 ms
```

under normal server conditions.

Reporter target app timeout:

```text
Connect timeout: 500 ms
Request timeout: 2 sec
```

Dashboard pagination mandatory.

Do not load all occurrences in a single request.

---

# 62. Indexing Requirements

Database index minimum:

## issues

```text
application_id
fingerprint UNIQUE per application context
status
severity
last_seen_at
created_at
```

## issue_occurrences

```text
issue_id
event_id UNIQUE
occurred_at
```

## application_credentials

```text
client_id UNIQUE
application_id
is_active
```

## api_request_logs

```text
application_id
received_at
http_status
```

---

# 63. Suggested Database Constraints

Use foreign keys when appropriate for the existing architecture.

Use:

```text
unique(event_id)
unique(client_id)
```

The fingerprint may use:

```text
unique(application_id, fingerprint)
```

when grouping is scoped per application.

---

# 64. Security Requirements Checklist

The SIVERA MVP meets the minimum security baseline if:

- [ ] HTTPS mandatory.
- [ ] HMAC-SHA256 implemented.
- [ ] Timestamp verification.
- [ ] Nonce replay protection.
- [ ] Constant-time signature comparison.
- [ ] Per-application credentials.
- [ ] Credentials can be revoked.
- [ ] Credentials can be rotated.
- [ ] Secret encrypted at rest.
- [ ] Rate limiting.
- [ ] Payload size limit.
- [ ] Sensitive data redaction.
- [ ] Request body disabled by default.
- [ ] Login throttling.
- [ ] CSRF protection.
- [ ] RBAC Admin/Viewer.
- [ ] Audit logging.
- [ ] API error responses do not leak internals.

---

# 65. Core Routes — Web

Suggested routes:

```text
/login
/logout

/dashboard

/applications
/applications/{application}

/issues
/issues/{issue}

/audit-logs

/settings/notifications
/settings/users
```

Admin-only:

```text
/applications/create
/applications/{application}/edit

/applications/{application}/credentials
/applications/{application}/credentials/rotate
/applications/{application}/credentials/revoke

/issues/{issue}/status
/issues/{issue}/severity
/issues/{issue}/comments

/settings/*
```

---

# 66. Core Routes — API

```text
POST /api/v1/events
GET  /health
```

A public CRUD API for the dashboard is not required in the MVP unless the frontend needs it.

---

# 67. Suggested Laravel Middleware

API stack:

```text
EnsureHttps
ValidateSiveraHeaders
ResolveApplicationCredential
VerifyTimestamp
VerifyNonce
VerifyHmacSignature
RateLimitApplication
LimitPayloadSize
```

Then:

```text
EventPayloadRequest
      ↓
EventIngestionService
```

---

# 68. Suggested Service Classes

Core backend:

```text
App\Services\Sivera\EventIngestionService
App\Services\Sivera\FingerprintService
App\Services\Sivera\SanitizationService
App\Services\Sivera\IssueService
App\Services\Sivera\NotificationService
App\Services\Sivera\CredentialService
```

Security:

```text
App\Services\Security\HmacService
App\Services\Security\NonceService
```

Avoid substantial business logic in controllers.

---

# 69. Suggested Controllers

```text
Api\V1\EventController

DashboardController
ApplicationController
ApplicationCredentialController
IssueController
IssueCommentController
NotificationSettingController
UserController
AuditLogController
```

Controllers must remain thin.

---

# 70. Reporter Package Suggested Structure

```text
naufalamr17/sivera-reporter
│
├── src/
│   ├── SiveraServiceProvider.php
│   ├── Reporter.php
│   ├── EventBuilder.php
│   ├── Sanitizer.php
│   ├── HmacSigner.php
│   ├── Transports/
│   │   ├── HttpTransport.php
│   │   └── QueueTransport.php
│   └── Console/
│       └── TestConnectionCommand.php
│
├── config/
│   └── sivera.php
│
├── tests/
│
└── composer.json
```

---

# 71. Testing Requirements

## Unit Tests

Minimal:

```text
HMAC signing
HMAC verification
Timestamp expiration
Nonce duplicate detection
Payload sanitization
Fingerprint generation
Issue grouping
Credential encryption
Role authorization
```

## Feature Tests

Minimal:

```text
Valid event accepted
Invalid signature rejected
Expired timestamp rejected
Duplicate nonce rejected
Unknown client rejected
Disabled application rejected
Rate limited request rejected
Payload too large rejected
Duplicate event ID is idempotent
Viewer cannot mutate issue
Admin can mutate issue
```

---

# 72. Reporter Tests

Package tests:

```text
Exception generates payload
Sensitive data is redacted
Reporter timeout does not throw
HTTP failure does not throw
HMAC signature format correct
Test command works
Disabled reporter sends nothing
```

---

# 73. Acceptance Criteria — Integration

SIVERA meets the integration requirements if:

1. Laravel applications can be integrated through the Composer package.
2. No Controller changes are required.
3. No business logic changes are required.
4. Exceptions are sent automatically.
5. Existing Laravel logging continues to work.
6. If SIVERA is down, the target application continues to operate as before.

---

# 74. Acceptance Criteria — Security

Valid if:

1. Requests without a signature are rejected.
2. Requests with an invalid signature are rejected.
3. Requests with an expired timestamp are rejected.
4. Requests that reuse a nonce are rejected.
5. Requests using disabled credentials are rejected.
6. Requests from disabled applications are rejected.
7. Payloads above the configured limit are rejected.
8. Rate limiting works as expected.
9. Secrets do not appear in API responses.
10. Sensitive values are not stored.

---

# 75. Acceptance Criteria — Grouping

If 100 events have the same fingerprint:

```text
Issues:
1

Occurrence Count:
100
```

Not:

```text
Issues:
100
```

---

# 76. Acceptance Criteria — Roles

## Admin

Admins can:

```text
Create application
Manage credentials
Change issue status
Resolve issue
Add comment
Manage notification
Manage user
```

## Viewer

Viewers can only:

```text
View dashboard
View issue
View occurrence
View stack trace
View history
```

Viewers can also change their own password through **Account → Change password**, using the current password and a confirmed new password of at least 12 characters. This action must not permit changing another account, role, or active state. Limit attempts to five per minute and audit success without storing passwords.

Monitoring and administration write requests from a Viewer must return:

```http
403 Forbidden
```

---

# 77. Acceptance Criteria — Notification

1. Fifty occurrences of one critical issue remain one issue and fifty receipt records.
2. No per-event email or WhatsApp delivery occurs.
3. One daily summary is sent per active email recipient for the previous local day, including zero-event days.
4. Repeating a completed date skips successful deliveries; failed deliveries are logged and retryable.
5. Disabled recipients and legacy WhatsApp jobs do not send.
6. A burst exceeding 50 events is fetched through successive cursor batches without loss.
7. Admin and Viewer receive live bell/toast updates without full-page reloads.
8. Browser alerts require an explicit click and permission; denial leaves in-app activity working.
9. Receipt-date filtering and local day boundaries apply consistently to email and dashboard analytics.

---

# 77.1 Acceptance Criteria — Near-Real-Time Dashboard

The near-real-time dashboard is considered valid if:

1. Default polling runs every 5 seconds.
2. The interval can be configured between 5 and 10 seconds.
3. The dashboard does not perform a full-page reload.
4. Issue counters can update without a manual refresh.
5. New issues appear no later than the next polling cycle.
6. Critical issues can trigger an in-app alert or toast.
7. Polling endpoints do not fetch full stack traces for all issues.
8. Viewers can receive live updates while remaining read-only.
9. If polling fails, the dashboard remains usable and retries on the next interval.
10. Browser polling failures do not affect API ingestion.

---

# 78. MVP Development Phases

## Phase 1 — Foundation

Build:

```text
Authentication
RBAC Admin / Viewer
Application management
Credential management
Database schema
Basic dashboard
```

---

## Phase 2 — Secure Ingestion

Build:

```text
POST /api/v1/events

HMAC
Timestamp
Nonce
Rate limit
Payload limit
Validation
API security log
```

---

## Phase 3 — Error Processing

Build:

```text
Sanitization
Fingerprinting
Issue grouping
Occurrences
Issue detail
Stack trace
Status history
```

---

## Phase 4 — Reporter Package

Build:

```text
naufalamr17/sivera-reporter

Laravel exception hook
Event builder
Sanitizer
HMAC signer
HTTP transport
Queue transport optional
Test command
Fail-safe behavior
```

---

## Phase 5 — Operational Workflow

Build:

```text
Admin status management
Severity management
Comments
Resolved
Reopened
Ignored
Viewer read-only access
```

---

## Phase 6 — Notification

Build:

```text
Daily email digest
Email recipients
Live in-app notifications
Optional desktop browser alerts
Per-date delivery logs and deduplication
```

---

## Phase 7 — Hardening

Build:

```text
Retention
Sampling
Cleanup scheduler
Audit logs
Secret rotation
IP allowlist
Security testing
Performance testing
```

---

# 79. Definition of Done

The MVP is considered complete when:

- [ ] Admin can sign in.
- [ ] Viewer can sign in.
- [ ] Viewer access is fully read-only.
- [ ] Admin can create applications.
- [ ] Admin can generate credentials.
- [ ] Application credentials are stored and handled securely.
- [ ] The reporter package can be installed in Laravel applications.
- [ ] Exceptions are sent automatically.
- [ ] HMAC verification is enabled.
- [ ] Replay protection is enabled.
- [ ] Rate limiting is enabled.
- [ ] Sensitive-data filtering is enabled.
- [ ] Duplicate event idempotent.
- [ ] Error grouping works correctly.
- [ ] The issue dashboard is available.
- [ ] Stack traces are available.
- [ ] The status workflow works correctly.
- [ ] Resolved issues can reopen.
- [ ] Daily email summaries work through the configured transport and scheduler.
- [ ] Live in-app notifications work for Admins and Viewers.
- [ ] Browser alerts work after permission on supported secure browsers.
- [ ] Recipient/date deduplication and delivery failure handling work.
- [ ] Dashboard date filters, application rankings, and top errors are verified.
- [ ] Audit logs are available.
- [ ] Retention cleanup runs successfully.
- [ ] SIVERA failures do not cause target applications to fail.
- [ ] The dashboard performs near-real-time polling every 5 seconds by default.
- [ ] Polling updates components without a full-page reload.
- [ ] Viewers receive live updates while remaining read-only.

---

# 80. Codex Implementation Instructions

Use this PRD as the source of truth.

Implementation must follow these priorities:

```text
1. Security
2. Fail-safe integration
3. Minimal modification on target applications
4. Correct issue grouping
5. Data privacy
6. Maintainability
7. UI
```

Implementation rules:

1. Do not add release/version tracking.
2. The only roles are `admin` and `viewer`.
3. Do not add a developer role.
4. Do not allow Viewers to mutate monitoring or administration data. Permit authenticated changes to their own password only.
5. Do not use the full request body by default.
6. Do not store secrets in plaintext.
7. Do not make SIVERA a hard dependency of target applications.
8. Do not change default Laravel logging in target applications.
9. Use Service classes for business logic.
10. Use Form Requests for validation.
11. Use Policies/Gates or middleware for authorization.
12. Use database transactions for ingestion operations that require consistency.
13. Use idempotency based on `event_id`.
14. Use application-scoped fingerprints.
15. Add automated tests for security-critical behavior.
16. Avoid heavy dependencies when the functionality can be implemented with native Laravel features.
17. All migrations must be reversible.
18. All example secrets in the repository must be dummy values.
19. `.env` must not be committed to the repository.
20. API error responses must not expose internal implementation details.
21. The frontend uses Blade management pages, React workspace/dashboard components, shadcn/ui with Radix, and authenticated Fetch polling.
22. The default dashboard polling interval is 5 seconds and must be configurable between 5 and 10 seconds.
23. Livewire may be used, but it is not a required dependency.
24. Do not use WebSocket/Laravel Reverb in the MVP unless the requirement changes.
25. Polling should update only dashboard components that need live data; do not perform full-page reloads.

---

# 81. Recommended First Implementation Milestone

Codex must start with the following milestone:

```text
MILESTONE 1

- Create Laravel project structure.
- Setup authentication.
- Implement roles admin/viewer.
- Create application management.
- Create application credentials.
- Implement encrypted secrets.
- Implement POST /api/v1/events.
- Implement HMAC validation.
- Implement timestamp validation.
- Implement nonce replay protection.
- Implement payload validation.
- Implement issue fingerprinting.
- Implement issue grouping.
- Implement occurrence storage.
- Create issue list.
- Create issue detail.
- Add automated tests.
```

Daily email and live activity notifications depend on secure ingestion and stable issue grouping; do not add WhatsApp delivery.

---

# 82. Final Product Statement

**SIVERA — Smart Integrated Visibility & Event Response Application** is an internal centralized application error monitoring system designed to provide visibility into application exceptions and support response workflows in a secure, lightweight, and centralized manner.

SIVERA's core principles:

```text
Secure
Minimal Integration
Fail-Safe
Centralized
Read-Only Viewer
Admin Controlled
Privacy Conscious
Lightweight
```

SIVERA does not replace Laravel logging.

SIVERA complements existing logging with centralized monitoring and response management.
