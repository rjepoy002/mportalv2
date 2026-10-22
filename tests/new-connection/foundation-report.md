# New Connection foundation — Task 02

Completed: 20 October 2026, Asia/Manila. Feature remains disabled by default. No migrations, applicant forms, uploads, staff routes, payment processing or PDF printing were implemented.

## Files

Created for this task:

| File | Purpose |
|---|---|
| `new-connection.php` | Thin isolated applicant route. |
| `config/new-connection.php` | Strict server-side feature flag. |
| `app/controllers/new-connection.php` | Feature/method gates, session, current identity check and seven-stage introductory model. |
| `app/middleware/new-connection-auth.php` | Module-only current-user SELECT, active-status and verified-email checks. |
| `app/views/pages/new-connection.php` | Branded informational landing page using existing sidebar/header/session-action partials. |
| `assets/css/new-connection.css` | Scoped responsive stage grid, numbered stages and skip link. |
| `tests/new-connection/foundation-test.php` | Isolated HTTP integration test using copied production route files and synthetic PDO/users/sessions. |
| `tests/new-connection/foundation-report.md` | This handoff and validation record. |

Modified existing application files: **none**. `assessment.md` from Task 01 remains unchanged. Existing navigation, login, logout, dashboard, account linking, profile, eBills and email source files remain unchanged.

## Behavior and structure

The entry point delegates to a dedicated controller, not the dashboard. Unset/empty/unrecognized feature values deny access with HTTP 404 before session startup, database configuration or shell rendering. Enable only in the PHP process environment with `MCO_NEW_CONNECTION_ENABLED=1`; a query parameter or cookie cannot enable it. There is no dotenv loader, and CLI environment changes do not automatically affect Apache. No configuration was enabled by this task.

When enabled, GET/HEAD requests start the existing portal session and require a positive user ID. Unauthenticated requests redirect to the existing relative `index.php` URL. A single parameterized SELECT of `mco_portal.users` confirms that the current user exists, is active and has a verification timestamp. Inactive, suspended, missing and unverified identities receive HTTP 403. Current name/email are read from that row rather than trusting stale session profile values. Handled failures produce a generic 503 without exception details. The existing database bootstrap itself still terminates on connection failure; that inherited generic error behavior was not modified.

There are no account-table queries, linked-account prerequisites or `SelectedAcctNo` dependency. This route never invokes dashboard synchronization, account-linking, billing, PMES media APIs or email services. Only the existing shared shell's session CSRF token may be created, for the existing logout dialog; there are no business-database writes. POST and other unsupported methods return 405 before session/database startup, with `Allow: GET, HEAD`. Private responses use no-store caching, frame denial and MIME-sniffing protection.

The page introduces application preparation/submission, document verification, internal billing clearance, online PMES, inspection, payment/signature verification and installation scheduling. It explicitly states applications are not open and provides no application action controls. It explains future online form completion/previews and PALECO staff printing for handwritten signatures during the offline payment visit. These are planned stages, not asserted approvals or an active application.

Existing PALECO branding/sidebar/header, mobile navigation script, theme script and logout dialog are reused. No link to this route was added to the existing navigation. New styling is scoped to the module, with a single-column stage list on narrow screens and two columns at 768px and above, text wrapping and a keyboard skip link. Utility styling uses the portal's existing Tailwind CDN pattern; no package or runtime dependency was added. The shared shell remains dependent on CDN availability.

Future forms, evidence, clearance, PMES, inspections, payment and scheduling should be separate services/views behind this boundary, following the assessment. The current stage list is display content, not a workflow engine. Do not repurpose it as persisted status or authorization logic. Future staff access requires explicit role provisioning and separate authorization before any route is exposed.

## Validation results

Commands run with local PHP 8.2 and Node:

| Check | Result |
|---|---|
| `php tests/new-connection/foundation-test.php` | PASS — 44 assertions; isolated HTTP server, copied actual route/controller/middleware/view and shared shell, synthetic users/sessions, strict SELECT-only PDO fixture. |
| `php -l` on all six new PHP files, including the test | PASS. |
| `php tests/structure-test.php` | PASS — 168 assertions. |
| `php tests/paleco-readonly-contract.php` | PASS. |
| `php tests/session-actions-http-test.php` | PASS — 22 assertions, synthetic sessions only. |
| `node tests/session-actions-js-test.js` | PASS — cancellation, Escape, focus, once-only POST, errors and logout/reset destinations. |
| Git diff / working-tree review | No existing tracked files modified; only the new module files/test/report added alongside the prior assessment. |

Foundation HTTP assertions cover strict feature-off values and absence of session/database startup, unauthenticated redirect without DB access, inactive/suspended/unverified/missing-user denial, safe handled database failure, successful rendering for an active verified user without account state, seven stages, informational text, current-user output escaping, shell/theme/viewport hooks, absence of application controls, no-store headers, HEAD, POST denial without queries, and successful real shared-shell logout followed by denied access with the destroyed session. The fixture allows only the exact identity SELECT and includes no mailer, dashboard or account services. It does not connect to any real database or send messages. Temporary fixture files are removed afterward.

Tests also confirm selected existing login/registration/logout/dashboard/navigation/account/eBills/mailer sources have no Git diff. This proves source preservation, not a full end-to-end password-login transaction.

### Limitations and screenshots

Playwright is not installed/resolvable and no browser automation tool is available in this session. No screenshots were produced. Mobile/desktop grid rules, viewport metadata and shared responsive/theme hooks were inspected, but actual layout, focus, CDN loading and dark-mode appearance still need browser verification. No visual result is claimed.

Live password login was not executed because it would require real user credentials and may lead into dashboard synchronization. Live enabled-route authentication was not tested against customer/user rows. The identity query and response behavior were exercised through the isolated PDO fixture. Existing logout was tested end-to-end with synthetic sessions. No migrations, real account routes with side effects, operational writes or email jobs were run.

## Remaining security dependencies

- Existing login/registration CSRF and abuse protection, OAuth state and verified-email handling, secure session settings/lifetimes/revocation, and production secret configuration remain the assessment's prerequisites. This foundation does not alter those shared behaviors.
- Configure HTTPS, secure/HttpOnly/SameSite session cookies, production error-display policy and least-privilege database credentials before a sensitive-data pilot. Shared bootstrap failure handling remains inherited.
- Validate layouts and logout in a real browser at mobile/desktop widths, both themes, and the deployed base path. The existing login destination remains dashboard; no new post-login return flow was added. Users can reach the isolated route directly after login when explicitly enabled.
- No staff route or role management exists here. Staff authorization, secure private upload storage/scanning, official form approval, PMES policy/versioning, immutable revisions, audit/outbox and concurrency controls remain future work.
- Existing operational `nc_*` ownership and all unresolved PALECO policies in the assessment still require confirmation. No operational integration permission is implied by this foundation.

## Recommended next task

First complete browser acceptance and resolve the shared identity/session prerequisites in a separately approved authentication-hardening task, preserving current account behavior. Then approve the stage/policy matrix and prepare portal-owned schema design/migrations for review and disposable-database validation. Keep this module disabled until readiness is confirmed; do not enable forms or apply migrations as part of this foundation.
