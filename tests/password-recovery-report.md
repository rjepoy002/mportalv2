# Forgot-password investigation and fix

Investigated 14 October 2026. No deployment, commit, push, real email send, or write
to the application database was performed. Local database inspection used only
SELECT and SHOW INDEX. Existing working-tree changes were preserved.

## A. Root cause and evidence

**Confirmed local configuration failure:** `config/mail.php:5` and `:6` obtain
SMTP credentials from the PHP process environment. The read-only inspection
(`php tests/password-recovery-inspect.php`) found every SMTP variable unset in
the current CLI environment. No matching SMTP/APP/PASSWORD_RESET Apache SetEnv
entries were found under `C:/xampp/apache/conf`. `.env.example` is documentation;
there is no dotenv loader. `sendEmail()` (`config/mailer.php:8`) therefore returns
false at its missing-configuration guard, before initializing SMTP. Credentials
must be provisioned to the actual PHP service to enable sending.

**Why success and no recent record can occur together:** after finding a user,
`forgot-password.php:82` inserts the hashed token. `:155` calls `sendEmail()`.
If that returns false, `:168` deletes only the new hash/user record. `:187`
returns the same public response for known and unknown accounts and operational
failures. This cleanup explains how a successful insert can leave no recent row.
Lookup, limiter and persistence failures are other possible early exits; the
new correlated logs distinguish them. Tests reproduce the missing-configuration
return and the controller's failure cleanup without network traffic.

**Historical code defects:** the HEAD version ignored `sendEmail()`'s boolean,
always displayed success for a syntactically valid address, and constructed a
`http://localhost:8000/` reset URL. That URL affects link usability, not whether
SMTP accepts mail. HEAD also embedded an SMTP credential. The existing working
tree already moved secrets to environment variables, checked the send result,
corrected URL construction and added several token/rate-limit protections.
Those improvements were retained. The old credential must be revoked/rotated;
this report intentionally does not reproduce it.

**Limits of the finding:** the running Apache process's inherited environment,
production configuration and historical SMTP responses were not inspected.
No claim is made that the historical failure was Gmail authentication, networking,
spam filtering or a database error. Missing SMTP settings are confirmed for the
inspected CLI environment, and explain failure wherever PHP has that environment.
Actual provider acceptance and inbox receipt remain unverified.

## Request and reset paths

The browser submits an ordinary POST to `forgot-password.php` itself. Its JS only
validates the form and prevents duplicate submissions; there is no API, queue or
framework router in this path. Execution is:

1. Load the `mco_portal` PDO connection and PHPMailer autoloader; generate a
   session CSRF token. On POST generate a correlation ID and validate CSRF/email.
2. Validate the configured app URL inside the guarded request block. A malformed
   deployment URL now receives the generic response and a server log rather than
   an uncaught configuration exception.
3. Apply the persistent per-email/per-connection-IP limiter. Email is trimmed;
   the limiter lowercases its key. Lookup uses the database's email comparison,
   rather than assuming email local parts must be rewritten.
4. Execute a parameterized `users` lookup. No status or email-verification gate
   exists in this recovery flow; this behavior is preserved to avoid changing
   account eligibility as part of a mail fix.
5. Generate 32 random bytes, encode as 64 hex characters, store only SHA-256
   with one-hour database-time expiry. Insert uses PDO exception mode/autocommit;
   there is no uncommitted reset-request transaction or asynchronous worker.
6. Build the URL from validated APP_URL, preserving its base path. Send directly
   through authenticated PHPMailer SMTP, default Gmail port 587 with STARTTLS.
   No native mail(), Gmail API or OAuth is used here. Google client dependencies
   belong to the separate login integration.
7. Record actual transport acceptance or a safe failure category; delete only
   the failed new token. Keep earlier valid links. Successful requests clean up
   expired/used records for that user. Return account-independent wording.

`reset-password.php:63` looks up a matching unused, unexpired hash.
`recovery_reset_password()` (`includes/password-recovery.php:53`) atomically claims
the exact token under a transaction, updates `users.password_hash`, and commits.
A failed claim or password update rolls back. Replayed, malformed and expired
tokens cannot update the password. The emailed secret serves as the reset form's
capability; the newly added session CSRF field protects the request-email form.

Read-only local schema inspection confirmed the query columns, `users.email`
uniqueness, and InnoDB engines for both users and password_resets. The reset
table has a primary ID and a nonunique user_id index, without a unique hash index.
No speculative migration was made. Hash collisions are cryptographically
negligible; no production schema or portal records were altered.

## B. Changes implemented in this investigation

| File | Problem and change | Side effects |
| --- | --- | --- |
| `includes/recovery-log.php` | New structured logger with correlation ID, event, UTC timestamp, failure category and exception class. Raw exception messages are omitted. | Normal milestone logging requires PASSWORD_RESET_DEBUG=1; failures always log through PHP error_log. |
| `config/mailer.php` | Added correlated missing/invalid configuration, authentication, exception, rejection and acceptance events; reject invalid encryption/ports before sending. Added optional fake-mailer factory for tests. | Existing five-argument callers remain compatible. SMTP debug stays off, timeout stays 15 seconds, certificate verification remains enabled. |
| `forgot-password.php` | Added milestone/stage diagnostics and session CSRF validation, guarded app-configuration loading, string-safe email handling, and wording that does not promise a send happened. | Old cached forms must reload for a CSRF field; PHP sessions must work. Account-existence response remains generic. |
| `includes/password-recovery.php` | Load safe logger and add session CSRF helper; retain existing limiter and atomic reset helper. | Uses existing PHP session storage. No new tables. |
| `.env.example` | Document PASSWORD_RESET_DEBUG=0. | This file does not configure PHP. |
| `tests/password-reset-test.php` | Extend controller doubles for CSRF, insert failure, invalid APP_URL, correlation/event fields and exception privacy. Use private temporary test-session storage. | No real DB writes or mail. |
| `tests/password-recovery-mailer-test.php` | Fake PHPMailer authentication failure, rejection and acceptance; invalid config; debug-off and log privacy assertions. | No SMTP connections. |
| `tests/password-recovery-inspect.php` | CLI-only read-only schema/dependency/config-presence diagnostic; no credentials or account/token records printed. | Reads the existing local database using its existing connection settings; exits nonzero if unavailable. |
| `tests/password-recovery-notes.md`, `tests/password-recovery-report.md` | Updated operator notes and this technical report. | Documentation only. |

Pre-existing changes in config/app.php, config/mail.php, reset-password.php,
the two recovery JS files, .gitignore, and other recovery tests were preserved.
Their working-tree behavior is included in validation, but they were not all
authored during this investigation. Enrollment, billing, consumer-account and
Google login implementation files were not changed.

## C. Configuration requirements

Set variables in the PHP/Apache service environment, then restart the service.
Keep service configuration and credentials outside version control and the web
root. Copying `.env.example` to `.env` alone has no effect.

| Variable | Requirement |
| --- | --- |
| APP_ENV | Explicit `production` on a deployed service. |
| APP_URL | Absolute HTTPS public app URL, including base path; e.g. `https://portal.example.com/mportalv2`. Local development may use `http://localhost/mportalv2`. |
| SMTP_HOST | Actual provider hostname; current default `smtp.gmail.com`. |
| SMTP_PORT | 587 for STARTTLS, or provider's documented implicit TLS port (commonly 465). |
| SMTP_ENCRYPTION | `tls` for STARTTLS or `ssl` for implicit TLS. No plaintext option. |
| SMTP_USERNAME | Authenticated provider account. |
| SMTP_PASSWORD | Fresh provider-issued SMTP secret, never committed. |
| SMTP_FROM_EMAIL | Provider-authorized sender; defaults to SMTP_USERNAME. |
| PASSWORD_RESET_RATE_DIR | Shared private directory writable by all PHP workers, outside web root; otherwise PHP temporary directory is used. |
| PASSWORD_RESET_DEBUG | Default 0. Set 1 temporarily for milestone diagnostics, then return to 0. |

For Gmail password authentication, provision an app password where account policy
allows it; Google requires 2-Step Verification and revokes app passwords after an
account-password change. See [Google's app-password instructions](https://support.google.com/mail/answer/185833?hl=en).
Google login OAuth credentials do not authenticate this SMTP transport. Ensure
outbound provider port connectivity, trusted CA certificates, and an authorized
From address. Custom-domain senders also need the provider's domain authorization
and DNS configuration. Do not disable TLS verification or enable raw SMTP debug
in production. [PHPMailer documentation](https://github.com/PHPMailer/PHPMailer)
describes its SMTP transport and boolean send result.

## D. Validation results

Passed:

- PHP syntax checks for all application/test PHP files outside vendor.
- `php tests/password-reset-test.php`: known/unknown accounts, valid request,
  insert failure, mail failure cleanup, generic response, CSRF, invalid app URL,
  token hashing, expiry, invalid/reused tokens, transactional rollback, stale
  claim, limiter behavior and sensitive-log exclusion.
- `php tests/password-recovery-mailer-test.php`: missing config, invalid sender,
  invalid encryption, fake authentication failure, fake rejection, fake provider
  acceptance, disabled debug milestones and log privacy; no network.
- `php tests/password-recovery-concurrency.php`: eight competing email workers
  and sixteen competing IP workers.
- `php tests/paleco-readonly-contract.php`: ownership boundary intact.
- Recovery JavaScript syntax checks and `tests/password-recovery-js-test.js`:
  63 assertions. Standalone Node was absent from PATH; used installed VS Code's
  embedded Node in ELECTRON_RUN_AS_NODE mode. Electron emitted crashpad permission
  warnings, but the checks executed successfully.
- Billing summary, SOA service, consumer education and dashboard tests.
- `git diff --check`.
- Read-only schema and dependency inspection: PHP 8.2.12; PHPMailer v7.1.1 and
  google/apiclient v2.19.4 match composer.lock; OpenSSL, PDO MySQL, mbstring and
  curl are loaded; Composer autoload/platform check succeeds.

Skipped/failed/limited:

- MySQL token concurrency test explicitly skipped because PASSWORD_RESET_TEST_DSN
  is unset. The dedicated test schema was not created and the portal DB was never
  used as a substitute. PDO-double transaction tests passed, but do not prove
  concurrency against a live MySQL engine.
- `tests/account-linking-test.php` fails its existing line-39 exact source-string
  assertion, "Cross-user duplicate account protection is missing." dashboard.php
  and the test were not changed. The run also encountered sandbox session-path
  warnings. This unrelated failure is not a claimed pass or a mail-fix regression.
- Composer CLI was unavailable; a full `composer validate`, platform audit and
  vulnerability audit were not performed. Installed versions and autoload were
  checked directly instead.
- No authenticated SMTP test, actual email or production test was performed.

## E. Deployment, manual verification and rollback

1. Review the complete working-tree diff, including changes that predate this
   investigation. Save the current deployment files and secure service settings
   as the rollback snapshot. Do not deploy tests or reports publicly.
2. Revoke the credential previously embedded in repository history, provision a
   fresh credential and sender authorization, and set the variables above in
   the real PHP service. Never restore the exposed credential on rollback.
3. Deploy the reviewed recovery PHP/JS files, configuration and helper includes
   together. Keep the locked vendor dependencies. No schema migration is needed.
4. Verify PHP session storage and the private limiter directory permissions.
   Restart PHP/Apache after environment changes. Use production display_errors=Off,
   log_errors=On and a protected error-log destination.
5. Run the listed tests in staging. For real MySQL concurrency, first provision
   an empty dedicated `mportal_password_recovery_test` schema, then set
   PASSWORD_RESET_TEST_DSN/USER/PASSWORD and run the opt-in test. Its fixtures
   are isolated from portal and PALECO databases.
6. Temporarily enable PASSWORD_RESET_DEBUG=1. With an authorized test account,
   open the real forgot-password page in a browser and submit once. Correlate
   request_received, validation, lookup, persistence and transport events by ID.
   Look for email_transport_accepted; a generic browser response is insufficient.
   Do not paste raw tokens, reset URLs or message bodies into logs/tickets.
7. Verify receipt in that mailbox, including spam; verify the link uses the public
   HTTPS base path. Reset the test account password, confirm login, and confirm
   the same link cannot be reused. Check invalid/expired-link behavior in staging.
8. Submit an unknown email and confirm the same public message. Repeated requests
   should be throttled without invalidating an earlier valid link. Test provider
   failure only in staging; verify failure logs and failed-token cleanup.
9. Disable PASSWORD_RESET_DEBUG again, protect/rotate operational logs, and redact
   query strings on reset-password requests in Apache/proxy/monitoring logs.
10. If rollback is required, restore the saved reviewed files as a coherent set,
    retain rotated secrets in secure configuration, restart the service and run
    the safety/smoke checks. There are no schema changes to reverse. Existing
    unexpired records remain compatible. Avoid reverting to HEAD's embedded
    credential or localhost URL. No commit/push/deployment was done here.

## F. Remaining risks

SMTP acceptance cannot guarantee inbox delivery. Provider bounces, quotas, spam
filtering and sender/domain policies need provider-side investigation if acceptance
is logged but receipt fails. A timeout may occur after provider acceptance; deleting
that new token can leave a delayed email with an unusable link. Resolving ambiguous
delivery requires a durable delivery/outbox design, beyond this targeted fix.

The file limiter is suitable for one server with reliable locking. Multiple app
servers need shared reliable storage or a database limiter; reverse proxies share
the observed connection IP quota. The global limiter state file can grow with many
distinct requests. Account-enumeration content protection is retained, but SMTP
timing can still distinguish paths; constant-time/asynchronous delivery was not
introduced. Password reset currently consumes the used token while preserving
other valid links until their one-hour expiry, matching the existing architecture.

Database bootstrap failure still exits with the existing generic connection error
before controller diagnostics. Reset-token lookup also uses existing PDO handling.
These shared/bootstrap concerns were not broadened into an unrelated authentication
rewrite. Missing operational configuration must be corrected before mail can work;
the code changes alone do not provision a provider account.
