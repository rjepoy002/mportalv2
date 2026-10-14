# Password recovery configuration and verification

Configure environment variables in the PHP/Apache service, then restart that service.
`.env.example` documents variables; PHP does not load it automatically. For XAMPP,
the default APP_URL is `http://localhost/mportalv2`. Deployed services must set
`APP_ENV=production` and an explicit HTTPS `APP_URL`, including any base path.
Google OAuth configuration is independent and unchanged.
Without APP_ENV, only CLI or direct loopback web requests receive development
defaults; other web requests require APP_URL and HTTPS. Always set APP_ENV explicitly
behind a reverse proxy, whose local connection cannot identify a public deployment.

SMTP settings now use `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`,
`SMTP_PASSWORD`, and `SMTP_FROM_EMAIL`. Rotate the credential previously stored in
the repository, and provision its replacement through the PHP environment. Existing
Git history may still contain the old credential. SMTP failures return the same
public response as successful requests and unknown accounts.

The limiter uses a private file directory outside the web root. All PHP workers
must use the same `PASSWORD_RESET_RATE_DIR` and have read/write access. The default
is `sys_get_temp_dir()/mco-portal-reset-limits`. Protect this directory with service
account permissions/Windows ACLs. It enforces a 60-second email cooldown and ten
attempts per connection IP per 15-minute fixed window. `flock` serializes updates;
storage errors fail closed. Reverse proxies share their connection IP quota because
forwarded headers are deliberately ignored. Multiple application servers require
a shared storage backend with reliable locking, or replacement with a database
limiter. A deployment should not clear the limiter directory on each request or restart.

Replacement requests retain previously valid links until their normal expiry.
Failed delivery removes only the new token. Successful delivery removes only
expired/used records for that user. No database migration is required. Atomic token
claims require transactional tables (InnoDB), as does the existing reset transaction.

Both recovery pages send no-store/no-cache and no-referrer headers. Configure
Apache/reverse-proxy access logs and monitoring to omit/redact query strings on
`reset-password.php`; application headers cannot remove tokens from infrastructure
logs of incoming requests. Application mail/error/throttling logs omit email
addresses, raw tokens, SMTP diagnostics, and reset links.

Forgot-password forms now require a session CSRF field; reload previously cached
forms after deployment. PHP session storage must be writable by the service.
Failures log structured FORGOT_PASSWORD events with a correlation ID, UTC timestamp,
stage/category and exception class. Exception messages are never copied to logs.
Set PASSWORD_RESET_DEBUG=1 temporarily to include successful workflow milestones;
it defaults off. SMTP acceptance is distinct from inbox delivery. Full investigation,
test results, deployment steps and limitations are in `password-recovery-report.md`.

Run from the repository root:

```text
php tests/password-reset-test.php
php tests/password-recovery-concurrency.php
php tests/password-recovery-mailer-test.php
php tests/password-recovery-mysql-test.php
node --check assets/js/reset-password.js
node --check assets/js/forgot-password.js
node tests/password-recovery-js-test.js
php tests/paleco-readonly-contract.php
```

The controller tests use PDO/mail doubles and real file storage; the parallel
limiter test starts independent PHP processes. The MySQL concurrency test is
opt-in: create an empty, dedicated `mportal_password_recovery_test` database and
set `PASSWORD_RESET_TEST_DSN`, `PASSWORD_RESET_TEST_USER`, and
`PASSWORD_RESET_TEST_PASSWORD`. It refuses other database names or existing tables,
creates its own InnoDB fixtures, races two connections against the same token,
and drops only those fixture tables afterward. It never uses `config/database.php`
or touches the portal/PALECO databases. Without that DSN it prints an explicit skip.
