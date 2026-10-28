# SIVERA development guidance

Read and follow [AGENTS.md](AGENTS.md) before editing this project. Laravel Boost is already installed; do not reinstall PHP or dependencies as a bootstrap step.

Current stack: PHP 8.3, Laravel 13, MySQL/MariaDB, Blade management pages, React 19 workspace/dashboard, shadcn/ui components with Radix, Tailwind CSS 4, Vite 8. Confirm installed versions before using package APIs.

Notification scope: daily email digest at 07:00 Asia/Jakarta by default; live in-app and optional browser alerts use authenticated polling. WhatsApp delivery is removed. All active users receive yesterday's summary; manual recipient rules are retired. All status changes, including RESOLVED/IGNORED, remain notified. Daily follow-ups exclude only those two statuses and include older outstanding issues. Apply activity/user-recipient migrations before running the new code. See [README.md](README.md) and [PRD_SIVERA.md](PRD_SIVERA.md).

Verify PHP changes with the relevant Pest tests and `php vendor/bin/pint --dirty --format agent`. For frontend changes run `npm run build` and `node --test tests/Frontend/notifications.test.mjs`. Windows CLI tests may require loading `pdo_sqlite` and `sqlite3` explicitly, as documented in README.

Keep changes focused. Do not expose credentials or send test emails to real recipients without an explicit request. A passing fake-mail test does not prove SMTP delivery or business acceptance.
