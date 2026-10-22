# Task 06M — Read-Only Localhost Preview

Date: 2026-10-21, Asia/Manila. **Implemented and locally authorized:** [New Connection preview](http://localhost/mportalv2/new-connection-preview.php). Eligible signed-in users can open it from the sidebar. **The real application remains disabled and returns 404.**

## 1. Actual files changed

Added:

- `new-connection-preview.php`: fixed preview entry point.
- `app/controllers/new-connection-preview.php`: read-only gate, existing session/current-user check and informational stage descriptions.
- `app/helpers/new-connection-preview.php`: shared request/environment availability check; no session or database work.
- `config/new-connection-preview.php`: independent explicit development opt-in.

Modified:

- Local `.htaccess`: added only `SetEnv MCO_NEW_CONNECTION_PREVIEW_ENABLED 1`. Existing `MCO_APP_ENV development` supplies development authorization. This is the existing ignored, machine-local testing file; do not deploy it. The production Apache template is unchanged.
- `app/helpers/portal-navigation.php`: fixed preview link and Preview status when the separate gate passes; otherwise the existing disabled Coming Soon item.
- `app/views/partials/portal-sidebar.php`: render an optional secondary status on an enabled item, preserving existing links/active styling.
- `app/views/pages/new-connection.php`: explicit preview notice selected only by the preview controller. Existing application landing wording remains the default.
- `tests/new-connection/foundation-test.php`: replaced only the obsolete navigation Git-HEAD freeze check with label/plug, fixed preview-or-disabled behavior, four original targets and default-off application checks. Added the two new navigation dependencies to its copied fixture. All other security/isolation/source assertions remain.

Added test harnesses: `localhost-preview-test.php`, `localhost-preview-browser.cjs`; browser evidence JSON, four screenshots and this report. No CSS, package or JavaScript application dependency was needed.

Protected-file hash comparison confirms authentication/session/Google/profile/account/eBills/dashboard code, the actual application controller/flag and all database migrations/workflow services are unchanged. Only the listed preview/navigation/view/config additions and edits occurred in `app`/`config`; the root route and local `.htaccess` change are listed separately.

## 2. Preview gate and route

Availability requires **all** of:

1. Server-side `MCO_NEW_CONNECTION_PREVIEW_ENABLED=1`.
2. Explicit `APP_ENV` of `local`/`development`, falling back to `MCO_APP_ENV` only when APP_ENV is unset/empty. A production APP_ENV cannot be overridden by the existing MCO development setting.
3. Direct `SERVER_ADDR` and `REMOTE_ADDR` both loopback (`127.0.0.1` or `::1`).
4. Host exactly localhost, 127.0.0.1 or [::1], optionally with a port; no public hostname.
5. No Forwarded, X-Forwarded-For or X-Forwarded-Host header. The preview is deliberately unavailable through such proxies.

The default configuration is unavailable unless explicitly opted in under the approved development environment. Browser query parameters cannot choose the environment or enable the preview. The application flag is never consulted to enable it and is never changed by the preview.

The route rejects unavailable contexts with 404 **before session/DB startup** and methods other than GET/HEAD with 405. It then reuses `portalSessionStart()` and the existing `newConnectionApplicant()` resolver. Anonymous users redirect to fixed `index.php`; inactive, suspended, unverified or missing identities receive 403. Current identity is reloaded using the existing users SELECT. No linked-electric-account lookup is performed.

The identity connection is lazy. The route suppresses output from the legacy DB bootstrap and establishes a fail-closed 503 status first, so an existing bootstrap `exit()` cannot print connection details or appear successful. Both thrown failures and a synthetic terminating bootstrap were tested. Session/cookie renewal and the shared logout CSRF token remain ordinary portal session maintenance, not application-record writes.

No dashboard/account bootstrap, Google SDK, mailer, claim adapter, applicant persistence or workflow initialization is loaded. This is not authentication return-navigation deployment: anonymous sign-in still follows existing ordinary login behavior; the user can select New Connection after signing in.

## 3. Content and navigation

The page reuses the actual PALECO shell, local styles/scripts, plug icon, responsive navigation and theme controls. Its active item reads **New Connection / Preview** and points only to `new-connection-preview.php`. If availability is lost, rendering falls back to the disabled, non-navigating **Coming Soon** item. The four existing targets remain unchanged.

Notice:

> Development Preview — New Connection applications are not yet available.

The page explicitly says it describes the planned process and does not accept applications. The seven informational cards are:

1. Applicant Information and Submission.
2. Document Submission and Verification.
3. Billing Clearance.
4. Online PMES.
5. Technical Inspection.
6. Payment and Handwritten Form Signing.
7. Installation Scheduling.

Stage 6 states that PALECO staff prepare printed forms and the applicant visits the office to sign by hand and pay offline. Stage 7 explicitly says scheduling does **not** mean the service has been energized. These are presentation descriptions, not changes to the authoritative workflow service.

No applicant fields, application submission form, upload control, draft-saving action, payment verification or staff action exists. The normal shell's sign-out confirmation form is retained; it is not an application form.

## 4. Authentication, authorization and read-only tests

`C:/xampp/php/php.exe tests/new-connection/localhost-preview-test.php` passed **75 assertions**. It extends the existing copied-route foundation harness with the actual preview controller/helper/view and a SELECT-only synthetic PDO. No real database connection or customer account is used.

Confirmed:

- Eligible synthetic active/verified identity with no linked account reaches the preview, with current identity and escaped output.
- Anonymous redirect without private content; inactive/suspended/unverified/missing identities denied.
- Private no-store; HEAD body empty; POST rejected before an identity query.
- Disabled/unexpected flag, production environment, nonloopback server/client, public/deceptive Host and proxy headers rejected before identity bootstrap.
- Browser environment/enable query parameters ignored.
- Exact seven-stage content, physical signing/offline payment text, non-energization clarification, fixed preview link and active state.
- No applicant controls; DB exceptions remain generic and terminating legacy bootstrap output is suppressed with 503.
- Application entry remains 404 with preview enabled.

The fixture permits only the existing identity SELECT. Any other prepared SQL is rejected; no application/operational write, migration, real email/provider request or claim storage is possible in the harness. Temporary test sessions/counters are synthetic and removed.

### Actual existing Apache checks

Requests were made with redirects disabled, avoiding the real sign-in/dashboard bootstrap:

| Actual localhost request | Result |
| --- | --- |
| Anonymous `new-connection-preview.php` | 302 → `index.php` |
| Preview with public Host | 404 |
| Preview with X-Forwarded-For | 404 |
| `new-connection.php` | 404 |

These confirm local opt-in and actual early denial. **No real authenticated customer session or actual portal DB connection was exercised.** Eligible-user coverage uses the copied route and synthetic current-user results; this is stated separately from actual Apache evidence.

## 5. Screenshots and responsive results

Opened and visually inspected:

- [Desktop light](localhost-preview-desktop-light.png)
- [Desktop dark](localhost-preview-desktop-dark.png)
- [Mobile light](localhost-preview-mobile-light.png)
- [Mobile dark](localhost-preview-mobile-dark.png)

Screenshots show the actual preview view rendered by the copied controller after synthetic authorization, using actual localhost static assets. The `.invalid` email and `<Applicant>` display name are synthetic escaping fixtures. They are **not** screenshots of a live customer account.

Chrome passed **11 browser checks**: seven cards/notice, fixed highlighted Preview link, no applicant controls, desktop 1440px and mobile 390px light/dark layouts, 320px light overflow check, opening mobile navigation with the active item, and no runtime exceptions or customer/application requests. All tested widths had no horizontal overflow. Theme switching uses the existing control; no applicant data is stored in browser storage.

The browser harness exports HTML only to a validated temporary directory outside the portal and permits only its static frame plus actual localhost assets. It removes that HTML, its temporary server and Chrome profile afterward. Early harness failures were corrected and their temporary resources cleaned. No synthetic HTML/session token is left as a public fixture file.

This is limited Chrome emulation and screenshot review, not physical-device/full accessibility certification or a live authenticated Apache customer acceptance test.

## 6. Regression results

| Check | Result |
| --- | --- |
| Updated original `foundation-test.php` | PASS **47**, no historical navigation freeze failure |
| Preview authorization/read-only suite | PASS **75** |
| Preview browser | PASS **11** |
| `auth-security-test.php` | PASS **42**, existing isolated HTTP/server-TLS authentication tests |
| `tests/structure-test.php` | PASS **201** |
| `local-assets-test.cjs` | PASS **102**, no rebuild needed |
| `tests/paleco-readonly-contract.php` | PASS |
| PHP/Node syntax and whitespace | PASS |
| Actual application-disabled check | PASS, 404 |
| Protected-source manifest | Only listed preview/navigation/view/config files differ; authentication/workflow/migrations unchanged |

Older hermetic fixtures that copy a hardcoded navigation dependency list may need the newly introduced preview helper/config added when those fixtures are next run. This report claims only the listed fresh regressions; it does not claim unrelated full business flows were re-exercised.

## 7. Feature-disabled verification and rollback

`config/new-connection.php` and `new-connection.php` are untouched. `MCO_NEW_CONNECTION_ENABLED` remains default-off; actual direct access returned 404. No actual applicant endpoints, claim tables, migration overrides or submission features were enabled.

**Immediate rollback:** remove the single `SetEnv MCO_NEW_CONNECTION_PREVIEW_ENABLED 1` line from the local `.htaccess`, or set it to `0` in the same server configuration. The route then returns 404 before session/DB work and the sidebar renders disabled Coming Soon. No Apache configuration reload or database rollback is required for the per-directory setting.

For full code removal, first disable that setting, then restore the pre-task navigation/sidebar/view files and remove the new preview entry/controller/helper/config as one coordinated change so no include is left dangling. Keep the corrected meaningful foundation checks maintained rather than reinstating the obsolete navigation source freeze. Do not alter existing local account-link flags, Google/auth configuration, portal user records or application flag during rollback.

## 8. Recommended next step

Arrange a narrowly scoped **read-only preview acceptance review** with an already designated synthetic portal account on actual XAMPP: click the sidebar, inspect all seven cards, confirm zero-account eligibility, and approve wording/accessibility. Do not create real applicant records or combine this with authentication policy, return claims or collection deployment. Existing form/privacy/catalog and production-governance approvals remain separate.

Stop after this read-only preview. No applicant collection or final submission implementation follows automatically.
