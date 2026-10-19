# Inspection and relocation plan — 2026-10-19

Inspection completed before application edits. See `original-inventory.json` for all 36,890 files (including 36,770 Composer files), sizes, and 182 dependency-reference groups. No AGENTS.md files were found. Starting Git revision: `a42ca8dfa4469264b540387b8afa79632a4aad52`; working tree initially clean.

## Current architecture

Root PHP pages combine request handling, SQL, session state and templates. `includes/` holds feature services and shared templates. Assets already occupy `assets/{js,css,images}` and remain there. `config/` contains database, SMTP, OAuth, account-linking, SOA and education configuration. Google configuration is ignored by Git and must be included in the private rollback snapshot. Composer supplies PHPMailer and Google API clients; vendor files will not move.

Public entries: index.php, register.php, forgot-password.php, reset-password.php, google-login.php, google-callback.php, dashboard.php, consumer-education.php, logout.php, soa.php and test-email.php. Dashboard query routes and POST actions handle profile, linking, unlinking, primary account, labels, billing, and eBills. Registration includes OTP/resend actions. JavaScript submits to dashboard.php, register.php, logout.php and soa.php. Empty form actions post to the current URL. All these browser paths will remain unchanged.

Authentication starts sessions in config/auth.php and redirects to the existing hard-coded `/mco-portal-v2/index.php`. Dashboard also enforces a localhost-only restriction. These existing behaviors are out of scope. Account context depends on auth and database configuration. Linking/unlinking depend on account notifications; notifications depend on mailer, app URL and email-footer helpers. Password recovery depends on safe logging, atomic filesystem limits, mailer and app URL. Billing and SOA use account context; SOA uses configured upstream PDF hosts. Education configuration loads playlist services with filesystem cache/fake-transport support. eBills loads the official resources/ebills-terms.html. Session/CSRF controls remain verbatim.

Runtime caches/rate limits use existing environment overrides or system temporary paths and will remain there. Migration runner and notification job are explicit CLI tools. SQL is not executed during this refactor. Operational PALECO tables remain subject to the existing read-only contract and eBills exception.

XAMPP: PHP 8.2.12, Apache document root C:/xampp/htdocs, rewrite module enabled, AllowOverride All for htdocs. Root .htaccess is ignored by Git and contains local testing SetEnv directives; preserve them. No active virtual-host definitions found in the inspected vhost file. No npm package/test runner; tests are standalone PHP and Node scripts. Browser layout test uses a locally installed browser. Existing recovery .bak/.patch and billing-investigation reports contain private supporting material.

## Relocation map (planned)

| Original | Destination |
| --- | --- |
| Root page implementations | app/controllers/<same name>.php, with original root compatibility entries |
| First top-level HTML body in index, register, dashboard, forgot/reset password and consumer education | app/views/pages/<same name>.php |
| soa.php implementation | api/soa.php, root soa.php retained |
| includes/account-context.php | app/middleware/account-context.php |
| config/auth.php | app/middleware/auth.php, old include shim retained |
| config/mailer.php | app/services/mailer.php, old include shim retained |
| includes/{account-linking,account-unlinking,account-notifications,billing-service,ebills-enrollment,education-playlist,password-recovery,soa-service}.php | app/services/<same name>.php |
| includes/{recovery-log,account-email-footer,portal-navigation}.php | app/helpers/<same name>.php |
| Remaining includes templates | app/views/partials/<same name>.php |
| migrations/* | database/migrations/*; old CLI PHP runner shim retained |
| dashboard recovery .bak/.patch | storage/archive/<same name> |

Old includes paths will remain as require_once shims for CLI/test/deployment compatibility. Static SQL consumers and migration references will point to database/migrations. No models directory is warranted: extracting SQL from services would broaden this structural change and risk query behavior. Inline scripts containing PHP interpolation remain in templates to preserve script ordering and rendering. Existing external CDNs remain unchanged.

## Baseline

Standalone tests: 14 PHP passes, 7 Node passes, 3 MySQL suites skipped (dedicated fixture DSNs absent), 1 existing PHP failure: password-recovery-mailer-test.php expects a missing-SMTP diagnostic that differs under the current configuration. Do not change production mail configuration to satisfy this assertion. Database migrations, live email sending, OAuth callbacks and live playlist synchronization are excluded from test execution. Read-only dashboard and education render smoke checks pass.

## Incremental safeguards

Save a private rollback ZIP of all first-party originals before implementation changes. Protect app, config, includes, database, storage, tests, vendor, reports and jobs with Apache denial; protect terms source but keep the public terms HTML available. Disable directory indexes and block root environment, manifest and recovery artifacts. Preserve existing local .htaccess directives. Move services/partials first, then controllers/views and endpoint, then migrations/archive; run syntax and regression checks after each stage. No framework, schema, query, branding, session, CSRF, markup or CSS redesign is planned. If runtime behavior changes are needed, stop for approval.
