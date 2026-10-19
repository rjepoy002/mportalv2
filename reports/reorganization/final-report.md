# MPortalV2 structure reorganization — 2026-10-19

The structural refactor is implemented locally. All 38 relocated implementations/resources match their original source after normalizing include paths, controller/view separation and line endings. No queries, schema, business rules, branding, CSS, JavaScript assets or user-facing markup were changed. Full live workflow certification remains pending the manual checks below; 100% production functionality cannot be certified from the available checks.

## Original versus new structure

```text
Original                         New
root mixed PHP pages             root PHP compatibility entries
                                 app/controllers/*.php
                                 app/views/pages/*.php (six page templates)
includes/ services + templates   app/services/, app/helpers/, app/middleware/
                                 app/views/partials/
                                 includes/ compatibility includes
config/ configuration + logic   config/ configuration; auth/mailer shims
soa.php                          soa.php shim -> api/soa.php
assets/{css,js,images}/           assets/{css,js,images}/ (unchanged)
migrations/                      database/migrations/
                                 migrations/ CLI runner shim
root recovery .bak/.patch        storage/archive/
tests/                           tests/ (updated paths + structural regression)
reports/                         reports/ (inspection and verification artifacts)
jobs/, resources/, vendor/       jobs/, resources/, vendor/ (retained)
```

The complete file list is [moved-files.md](moved-files.md), with a machine-readable [relocation map](relocation-map.json). [original-inventory.json](original-inventory.json) inventories every original file, including Composer dependencies, and records source dependency references. [inspection.md](inspection.md) records the completed pre-edit inspection and plan. No empty models directory was added: database access remains in the existing services/controllers to avoid changing SQL or transaction boundaries. PHP-dependent inline scripts/styles remain in templates to preserve execution order and exact rendering.

## Updated dependencies

- Root entries now require controllers; controllers render their page views in the same PHP variable scope. Browser URLs and relative asset resolution remain at the original root entries.
- All application filesystem references now point to the relocated services, middleware, helpers and partials using `__DIR__`. Config education loading, CLI notification loading and regression-test includes were updated.
- SOA root entry requires api/soa.php; the existing browser fetch remains soa.php. Dashboard and registration AJAX actions remain in their original request controllers; no new action routing was introduced.
- Migration fixture references now use database/migrations. The original migration PHP command remains a shim. No migration was executed.
- Existing source-inspection tests use tests/source-helpers.php to assemble the controller/view source; their security and behavior assertions remain intact. The standalone browser fixture references relocated templates directly.
- Original includes paths remain available to PHP/CLI. Partial shims use require so repeated template rendering is preserved; service shims use require_once to avoid duplicate declarations.
- Apache denial files protect app, config, includes, database, migrations, storage, tests, reports, vendor and jobs. Terms HTML remains public; its Markdown source is protected. Directory listing, environment/manifests, recovery artifacts and private repository/tool directories are blocked by root rules.

## Unchanged public routes

index.php, register.php, forgot-password.php, reset-password.php, google-login.php, google-callback.php, dashboard.php, consumer-education.php, logout.php, soa.php and test-email.php retain their original public names. Dashboard query routes, fragments, action values, redirects, OTP/resend requests, form actions, asset URLs and external callback configuration remain unchanged. api/soa.php is the relocated SOA handler and applies the same method/session/CSRF controls as soa.php.

Internal include/config/test/job URLs intentionally return 403. They are not public request endpoints. CLI commands continue to work. Existing migration SQL paths have moved; update any external runbooks to database/migrations before deployment.

## Verification

Detailed records: [test-results.json](test-results.json), [http-results.json](http-results.json), [source-equivalence.json](source-equivalence.json).

- 22 automated test passes, 3 isolated MySQL suites skipped, 2 unsuccessful checks described below.
- 112 syntax checks passed: 100 first-party PHP files and all 12 application JavaScript files. Composer vendor files were untouched. Apache configuration syntax passed.
- New structure test passed 161 assertions covering relocated files, compatibility entries, protection rules and literal filesystem dependencies.
- All 38 source-equivalence checks passed. Archived recovery files and SQL retain their contents. Every original first-party file, including ignored Google configuration and the local root .htaccess, is present in the private rollback ZIP.
- 43 Apache HTTP checks: all 19 JS/CSS/image assets returned 200; public forms and terms returned 200; existing authenticated routes retained redirects; logout/SOA GET retained 405; unauthenticated SOA POST was rejected. Sensitive paths returned 403. No PHP warning/fatal/parse error appeared in those responses.
- Apache error log inspection showed the expected access-denial entries from the security checks and no new PHP errors from those requests.

| Module | Automated evidence | Remaining verification |
| --- | --- | --- |
| Login and registration | Public GET renders; profile/registration field checks pass; login/register JS syntax and asset loads pass | Real login, invalid credentials, OTP delivery/resend, registration completion |
| Password reset | PHP token/expiry/CSRF tests, file-limiter concurrency and 63 JavaScript assertions pass | Real email delivery and end-to-end token use; MySQL concurrency suite requires dedicated DSN |
| Mail infrastructure | Notification fake-transport test passes; recovery mailer assertion fails identically before/after | Existing recovery test expects a missing-SMTP configuration diagnostic differing under local config; no production configuration was changed |
| Account linking | Input/config tests and submission/navigation JS tests pass | Dedicated fixture-DB integration suite skipped; live verification/OTP and linking require a test account |
| Account unlinking | Confirmation JS tests pass; service source equivalent | DB transaction/notification workflow covered by skipped linking integration suite |
| Account notifications | Template tests pass 37 assertions; real mailer infrastructure with fake transport passes | Real SMTP delivery and notification CLI job processing |
| eBills and terms | Official terms/render tests pass 24 assertions; JS workflow passes 174 assertions | Dedicated fixture DB suite skipped; live enrollment/duplicate/consent checks pending |
| Billing history and selected account | Read-only dashboard render and billing-summary fixtures pass | Authenticated history/account switching against representative records |
| Statements/SOA | Service transport/URL allowlist/security tests pass; HTTP method/auth rejection preserved | Authenticated upstream PDF preview/download |
| Consumer Education | Page smoke, fake API/cache/fallback/privacy suite and player lifecycle/pagination JS tests pass | Browser YouTube playback and live playlist synchronization |
| Profile and friendly labels | Profile validation and registration tests pass; dashboard rendering/source assertions pass | Authenticated save/remove labels and profile update |
| Logout/reset confirmation | HTTP suite passes 22 assertions with synthetic sessions; JS Cancel/Escape/focus/POST/redirect checks pass | Visual browser confirmation |
| Google OAuth | Source equivalent, PHP syntax passes, public route shims preserved | Real OAuth login/callback; not invoked because it starts external authorization |
| Diagnostic email endpoint | Source equivalent and PHP syntax passes | test-email.php deliberately not requested because it sends real email |
| Operational DB protection | PALECO read-only SQL contract passes | Deployment database privileges remain an operational responsibility |

The real Chrome layout check was attempted but failed before producing results: Chrome reported GPU process failure/access denied. No interactive browser console inspection or visual certification was possible. The seven Node behavior suites passed using DOM doubles. This browser environment failure is separate from the pre-existing recovery-mailer assertion. No live registration, email, enrollment, migrations, OAuth, notification jobs or playlist sync was triggered by verification.

## Deployment and rollback

The local root .htaccess is ignored by Git and contains this machine's testing flags. Its existing directives were preserved and security rules appended. **For deployment, merge the tracked config/apache-root.htaccess rules into the deployed root .htaccess; do not copy local testing flags.** Deploy the per-directory .htaccess files, including vendor/.htaccess, and require Apache AllowOverride support. The inspected XAMPP installation has AllowOverride All and rewrite enabled; the running Apache access checks confirm the rules take effect. A PHP development server does not enforce .htaccess and should not be exposed as a production server.

Private rollback snapshot: `storage/reorganization/original.zip` (Git ignored, HTTP 403). It contains every original first-party file, including ignored credentials; retain it privately and never publish or attach it. Composer vendor contents were not copied because they were untouched. To roll back locally, stop requests/jobs, extract this ZIP over the project root with overwrite enabled, and restart normal service. This restores original implementations, SQL paths, archives and the original root .htaccess. New protected app/api/database files may remain as unused artifacts; keep them protected until removing them separately after review. The original Git revision is `a42ca8dfa4469264b540387b8afa79632a4aad52`, but Git alone does not preserve ignored local configuration. Do not use a blanket git reset/clean as a credential-preserving rollback.

Before production rollout, run the three isolated MySQL suites against their explicitly named empty fixture databases, resolve the existing mailer-test/config discrepancy separately, and complete the manual workflow checks above in a staging environment with approved test accounts. The existing localhost-only dashboard restriction and hard-coded `/mco-portal-v2/index.php` authentication redirect were intentionally preserved; the latter differs from this workspace's /mportalv2 path. Changing either would be a separate behavior change requiring review.
