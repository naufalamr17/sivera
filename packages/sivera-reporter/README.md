# SIVERA Reporter

`naufalamr17/sivera-reporter` sends Laravel exceptions to a SIVERA monitoring server. It hooks into Laravel exception reporting and preserves the application's existing logging.

The monitoring dashboard and ingestion API are maintained in the [SIVERA server repository](https://github.com/naufalamr17/sivera).

## Requirements

- A Laravel application. The package allows Laravel 10–13; automated integration tests currently run on Laravel 13.
- PHP 8.1 or later, subject to the installed Laravel version's requirements.
- A reachable SIVERA HTTPS endpoint and a registered application's credentials.

## Installation

After the package has been published on Packagist, run these commands from the application you want to monitor:

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
- Known sensitive assignments in exception text are redacted, including password, token, secret, authorization, cookie, session, and API-key names. Free-form exception messages and URL paths can still contain personal information; do not put secrets in exception messages or URL segments.
- File paths are normalized and stack traces contain at most 50 frames.

## Updates

```shell
composer update naufalamr17/sivera-reporter
```

Do not rerun `sivera:install` on every update because it overwrites the published configuration. Review configuration changes before applying them.

## License

MIT. See [LICENSE](LICENSE).
