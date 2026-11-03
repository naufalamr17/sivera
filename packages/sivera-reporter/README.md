# SIVERA Reporter

`naufalamr17/sivera-reporter` sends Laravel exceptions to a SIVERA monitoring server. It hooks into Laravel exception reporting and preserves the application's existing logging.

The monitoring dashboard and ingestion API are maintained in the [SIVERA server repository](https://github.com/naufalamr17/sivera).

Public package: [naufalamr17/sivera-reporter on Packagist](https://packagist.org/packages/naufalamr17/sivera-reporter). The server's [main guide](https://github.com/naufalamr17/sivera#readme) covers installation, roles, API signing, operations, and project structure.

## Requirements

- A Laravel application. The package allows Laravel 10–13; automated integration tests currently run on Laravel 13.
- PHP 8.1 or later, subject to the installed Laravel version's requirements.
- A reachable SIVERA HTTPS endpoint and a registered application's credentials.

## Installation

Run these commands from the application you want to monitor:

```shell
composer require naufalamr17/sivera-reporter:^0.1
php artisan sivera:install
```

Laravel discovers the service provider automatically. The install command publishes `config/sivera.php`; it overwrites that file if it already exists. Run the install command on first installation and preserve any existing configuration before running it again.

If the application previously used the local path repository from the SIVERA setup guide, remove that repository before requiring the public release:

```shell
composer config --unset repositories.sivera-reporter
composer require naufalamr17/sivera-reporter:^0.1
```

An existing `config/sivera.php` and its `.env` settings can stay in place; do not rerun the install command just to switch package sources.

If the application already requires the earlier local package name `naufal/sivera-reporter`, remove that package with `composer remove naufal/sivera-reporter` before installing `naufalamr17/sivera-reporter`. The earlier vendor name is owned by someone else on Packagist and is not this package's public name.

Register the application in SIVERA and generate a credential. Add the following to the monitored application's `.env`:

```dotenv
SIVERA_ENABLED=true
SIVERA_URL=https://sivera.example.com
SIVERA_APP_ID=my-application
SIVERA_CLIENT_ID=your-generated-client-id
SIVERA_SECRET=your-generated-secret
SIVERA_ENVIRONMENT=production
SIVERA_TRANSPORT=http
```

Use the application slug as `SIVERA_APP_ID`. The environment must match the environment registered in SIVERA. Use a separate registration and credential for each application or environment. Keep credentials out of source control.

`SIVERA_URL` is the SIVERA server's base URL, without `/api/v1/events`. It must use HTTPS, including during local testing. The monitored application itself can use local HTTP.

Reload configuration and send a synthetic exception:

```shell
php artisan config:clear
php artisan sivera:test
```

Open SIVERA's Issues page and check for `SIVERA connection test`. Exceptions are then reported automatically; no controller or business-logic changes are required. If the application caches configuration, run `php artisan config:cache` after changing `.env`. Restart existing long-running workers so they load the new configuration.

## Transport and failure handling

HTTP transport is the default and requires no queue worker. It uses a 0.5-second connection timeout and a 2-second request timeout. Override these defaults with `SIVERA_CONNECT_TIMEOUT` and `SIVERA_TIMEOUT`.

Set `SIVERA_TRANSPORT=queue` only when the monitored application already has a queue worker. Delivery jobs make one attempt. Reporting and delivery failures are caught so they do not replace the application's original exception.

## Privacy and security

- Requests use HMAC-SHA256 signatures, timestamps, and fresh nonces.
- Request bodies, headers, cookies, session data, and stack-frame arguments are not collected.
- User context contains only the authenticated user ID when `capture_user` is enabled.
- Database `QueryException` messages become `Database query failed.`; SQL bindings and database driver messages are not sent by the event builder.
- HTTP paths use route templates, such as `/reset-password/{token}`, instead of actual URL parameter values. Unmatched requests and console events have no captured path.
- Known sensitive assignments in other exception text are redacted, including password, token, secret, authorization, cookie, session, and API-key names. Arbitrary free-form text may still contain secrets or personal information. Do not put secrets in exception messages, route names, or file names. Callers using `Reporter::send()` directly must supply a safe payload themselves.
- File paths are normalized and stack traces contain at most 50 frames.

The SQL-message and route-template protections require v0.1.2 or later. Updating package source does not update installed applications or scrub historical events. Update the package in each monitored application and restart existing long-running workers. Use the server guide's local path repository instructions to test unreleased changes.

## Configuration reference

| Setting | Default / meaning |
| --- | --- |
| `SIVERA_ENABLED` | `true`; disable outbound reports with `false` |
| `SIVERA_URL` | Required HTTPS base URL without `/api/v1/events` |
| `SIVERA_APP_ID` | Registered application slug |
| `SIVERA_CLIENT_ID`, `SIVERA_SECRET` | Generated credential identity and signing secret |
| `SIVERA_ENVIRONMENT` | `production`; must match the registered application |
| `SIVERA_TRANSPORT` | `http`; use `queue` only with an existing queue worker |
| `SIVERA_CONNECT_TIMEOUT`, `SIVERA_TIMEOUT` | 0.5 and 2 seconds; larger values can delay exception handling |
| `capture_user` in `config/sivera.php` | `true`; set false to omit the user reference |

Request-body capture is not implemented; changing `capture_request_body` does not enable it. Each job has one attempt. Delivery failures do not interrupt the monitored application and are not a durable outage buffer.

## Delivery and maintenance

- Run `php artisan sivera:test` after installation, credential changes, or upgrades. Inspect SIVERA for the synthetic issue, not just terminal success.
- For `401`, check the HTTPS endpoint, clock, application/environment, credential status, secret, and allowlist. For `429`, respect `Retry-After`; the server also applies a shared source-IP ingress limit.
- Keep the same event ID when retrying a custom event, but generate a fresh nonce/signature per HTTP request. IDs are deduplicated within the registered application.
- During rotation, deploy the replacement client ID/secret to the source application, reload configuration, and test before revoking the previous credential. The server normally permits a 24-hour rotation grace period.
- After changing environment values, rebuild cached configuration where used and restart existing queue workers. Do not print secrets or copy real exception data into connection diagnostics.

## Package structure

```text
config/sivera.php          # Published reporter configuration
src/SiveraServiceProvider.php # Auto-discovery and exception-reporting hook
src/EventBuilder.php      # Minimal event, privacy filtering, normalized frames
src/Reporter.php          # HTTP delivery, HMAC signatures, safe diagnostics
src/Jobs/SendEventJob.php  # Optional queue delivery, one attempt
src/Console/InstallCommand.php # Configuration publishing
src/Console/TestConnectionCommand.php # Synthetic delivery check
composer.json             # Requirements and Composer/Laravel integration
LICENSE                   # MIT license
```

The SIVERA repository's `tests/Feature/ReporterPackageTest.php` covers this bundled source. Compatibility with every allowed Laravel major must be checked in its target application.

## Updates

```shell
composer update naufalamr17/sivera-reporter
```

Do not rerun `sivera:install` on every update because it overwrites the published configuration. Review configuration changes before applying them.

## License

MIT. See [LICENSE](LICENSE).
