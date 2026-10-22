# Task 03A — Authentication regression and browser acceptance

Date: 20 October 2026, Asia/Manila. **Disposition: PARTIAL ACCEPTANCE — functional browser checks passed; visual and staging acceptance remain blocked.** Do not interpret this report as approval to enable the module or run database migrations.

## Environment and isolation

- Windows workstation, project `C:\xampp\htdocs\mportalv2`.
- Actual browser exercised: **Google Chrome 155.0.8059.85**, headless, disposable profile, Chrome DevTools Protocol controlled through Node's built-in WebSocket. Microsoft Edge 155.0.4283.62 was discovered but not exercised; Firefox and physical mobile devices were not tested.
- PHP **8.2.12**, Node **v24.20.0**. No Playwright/Puppeteer package was installed or required.
- Application requests used a temporary PHP loopback HTTP server on an ephemeral port. The test tree contains copies of the current controllers, actual form templates, shared shell and assets. PDO, Google SDK and mail transport are synthetic doubles. Limiter and session storage are private temporary directories, removed after testing.
- No designated staging URL, approved staging test-account set or staging HTTPS endpoint was supplied/discovered. Existing credential files were not used as authorization to contact a live identity provider or mail service.
- Login/registration/recovery/OTP/reset/logout/New Connection requests ran through actual copied PHP controllers and browser forms. Test-only persistence models supplied user/verification/reset records; these checks do not prove real SQL/schema correctness or SMTP delivery.
- Browser login redirects to a deliberately minimal, authenticated **fixture destination**, not the real dashboard. The real dashboard controller can perform account synchronization and notification side effects on GET. It was not invoked against existing local/operational data.
- Synthetic registered-user identity has no linked service account. Inactive/unverified/expired identities were designated synthetic session fixtures. Precreated status-test sessions were not used to bypass CSRF, OAuth state or expiry checks; normal login, registration and reset use their actual tokens and validation.
- New Connection was enabled **only inside the temporary test server**. Its feature-off case was also exercised. Project `config/new-connection.php` and its disabled-by-default behavior are unchanged; no production/local portal environment was enabled.
- Browser flags include headless/no-sandbox/disabled GPU and software rasterizer for this restricted, synthetic environment. An initial GPU startup failure was resolved with test-runner flags already used by the existing profile browser fixture. No certificate-error ignore flag, fake stylesheet, authentication bypass or limiter reset was used to obtain passing results.

## Tests performed and outcomes

### Actual Chrome browser — 34 functional assertions passed

Machine-readable results: [browser-results.json](browser-results.json). The assertion count includes DOM/layout-structure checks; it is **not** a count of fully accepted end-to-end production features.

| Area | Browser result | Scope / limitation |
|---|---|---|
| Login | PASS | Actual form renders a CSRF token; correct synthetic credentials sign in through the actual controller. Login page captured at 1440×1000 and 390×844; mobile has no horizontal overflow. |
| Login CSRF | PASS | Browser POST with an incorrect token receives 419. Normal form submission sends the rendered token. |
| Registration and OTP | PASS | Actual registration form, server validation, synthetic mail sink and browser OTP form complete to the registered-success sign-in URL. No real mailbox was contacted. |
| Newly registered applicant without an electric account | PASS | Newly inserted synthetic portal identity signs in and reaches the actual New Connection route/view; all seven stages render. No account links or consumer records exist in that fixture. |
| Logout | PASS | Shared confirmation dialog opens; Cancel preserves the session; confirmation executes the existing CSRF-protected logout; protected access subsequently redirects to sign-in. |
| Forgot password / password reset | PASS | Actual browser forms request recovery, use a reset token captured only by the synthetic mail sink, submit the recovery CSRF token and complete reset; subsequent sign-in with the updated synthetic password succeeds. Real delivery and real database atomicity are outside this browser fixture. |
| Inactive / unverified identities | PASS | Controlled sessions are denied the landing page. Shared current-user middleware is also covered by the 42-assertion HTTP suite below. |
| Expired session | PASS | Controlled idle-expired session loses protected access and returns to sign-in. Idle and absolute expiry are both covered by HTTP fixtures below. |
| New Connection feature gates/content | PASS | Enabled route renders actual view and handwritten-signature/payment guidance; disabled route denies access. Project feature default remains off. |
| Login throttling | PASS | Ten requests for a fresh synthetic email remain below its limit; the eleventh returns 429 in the actual browser. Counters were not cleared or bypassed to pass this check. |
| Local HTTP cookie | PASS | Browser cookie inspection finds HttpOnly, SameSite=Lax and Secure=false on the loopback HTTP server. This is appropriate evidence for local HTTP only. |
| Stage-list responsiveness | PASS, limited | Actual viewport emulation at 1440×1000 and 390×844; local module CSS produces two/one columns respectively, with no horizontal document overflow in either theme state. Full shell appearance fails separately below. |
| Theme controls and mobile navigation | PASS, limited | Shared theme code changes the dark class; mobile sidebar control changes its expanded state. These are DOM/behavior checks, not visual approval: missing utility CSS prevents correct shell presentation. |
| Browser JavaScript runtime exceptions | None observed | No `Runtime.exceptionThrown` events in the captured run. External resource requests did fail; absence of JS exceptions does not imply correct styling. |

### Other actual-browser regressions

| Command | Result |
|---|---|
| `node tests/profile-mobile-browser-test.js` | PASS: Chrome executes actual profile markup/JavaScript at exact 390px and 768px iframe widths. Ten checks at each width cover telephone attributes, nontruncation, stored value, typing/paste, blur/submit normalization, invalid-input rejection, focus, correction and optional empty value. Submission is intercepted; no portal/operational write occurs. |
| `node tests/ebills-layout-browser-test.js` | **FAILED / environment blocker**: the fixture fails its initial `Tailwind styles loaded` assertion at all three requested windows. Actual reported viewports are 1424×849, 752×873 and 500×693; this older window-size fixture is not exact 390px mobile emulation. Enrollment-layout, dark-mode and subsequent consent-navigation assertions were not reached and are not claimed to pass. The transport is synthetic and no enrollment write is performed. |

### Supporting HTTP/PHP/JavaScript regression checks

These are useful regressions but are not substitutes for unperformed browser/staging tests.

| Check | Result |
|---|---|
| `php tests/new-connection/auth-security-test.php` (also prerequisite to browser run) | PASS, 42 assertions: CSRF rejection before database/mail, normal registration/OTP, generic errors, regeneration, current active/verified user validation, persistent limits and failure denial, session expiry/strict mode, OAuth state replay/mismatch/expiry, verified-email handling and safer linking. Google responses are doubles. |
| `php tests/password-reset-test.php` | PASS, PDO/mail doubles and private filesystem limiter; invalid reset CSRF does not change password/consume token; existing reset-claim/replay/failure cases pass. |
| `php tests/session-actions-http-test.php` | PASS, 22 assertions, synthetic sessions; logout/reset confirmation destinations and replay behavior preserved. |
| `php tests/profile-mobile-test.php` | PASS, 135 assertions, synthetic PDO; no live profile writes. |
| `php tests/account-linking-test.php` | PASS, input/configuration tests. |
| `php tests/ebills-terms-test.php` | PASS, 24 terms/render assertions. |
| `node tests/session-actions-js-test.js` | PASS, Cancel/Escape/focus/once-only POST/errors/logout and reset destinations. |
| `node tests/account-linking-js-test.js` | PASS, submission-control regression. Not a full browser account-linking transaction. |
| `node tests/ebills-enrollment-js-test.js` | PASS, 174 assertions using JavaScript doubles. Does not override the failed real-browser layout check. |
| `php tests/paleco-readonly-contract.php` | PASS. |
| `php tests/structure-test.php` | PASS, 180 assertions. |
| PHP syntax for new browser fixture and updated security test; Node syntax for browser runner | PASS. |

## Screenshots and visual findings

Captured from the actual isolated Chrome run, containing only synthetic information:

- [Login desktop](screenshots/login-desktop.png) — 1440×1000.
- [Login mobile](screenshots/login-mobile.png) — 390×844.
- [New Connection desktop, light state](screenshots/new-connection-1440-light.png).
- [New Connection desktop, dark state](screenshots/new-connection-1440-dark.png).
- [New Connection mobile, light state](screenshots/new-connection-390-light.png).
- [New Connection mobile, dark state](screenshots/new-connection-390-dark.png).

New Connection captures include the full document, extending beyond viewport height. **Its screenshots are defect/blocker evidence, not accepted designs.** Visual inspection confirms oversized/unpositioned navigation icons and overlapping shell/content where Tailwind utility styling is absent. The local stage list remains present, but the full responsive shell is not usable as designed. Dark/light class changes are recorded; the complete visible palette is not correct without utility styles. The login page's local stylesheet renders its card/branding, although external icons/fonts are unavailable.

Captured browser network failures identify `net::ERR_NETWORK_ACCESS_DENIED` for:

- `https://cdn.tailwindcss.com/`
- `https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css`
- `https://fonts.googleapis.com/css2`

This is evidence of restrictions in **this execution environment**, not proof those services or staging are down. No fake CDN response, downloaded replacement stylesheet, permissive certificate flag or application styling change was introduced to mask the failure.

## Blocked / unperformed acceptance

| Requirement | Status and reason |
|---|---|
| Full visual desktop/mobile shell and visible light/dark approval | BLOCKED by denied external assets. Local grid/overflow checks alone are insufficient. |
| Actual staging HTTPS cookie Secure attribute, browser transport and redirects | UNPERFORMED: no designated HTTPS staging endpoint/certificate/proxy configuration. Existing HTTP tests simulate a trusted server HTTPS indicator and pass; that is not actual TLS acceptance. |
| Real Google provider sign-in, registered callback URI and tenant claims | UNPERFORMED: no designated staging provider configuration/test account. Only isolated OAuth state/identity fixtures pass. No real Google login was attempted. |
| Real email receipt/delivery/bounce behavior | UNPERFORMED deliberately: mail was captured by a synthetic sink. No customer or external mailbox received test email. |
| Full dashboard/billing/account-linking/profile/eBills database-backed workflows | UNPERFORMED end-to-end: no disposable representative portal/operational test database/account set was provided. Loading the actual dashboard against existing data can synchronize account links and send notifications. Component/browser and PDO/JS regressions above pass, but do not establish whole-dashboard or successful account-link/enrollment acceptance. |
| eBills real-browser consent/modal layout | FAILED at environment stylesheet prerequisite; later assertions not run. |
| Mobile device engines / Edge / Safari / Firefox | UNPERFORMED. Chrome mobile viewport emulation is not a physical-device test. |
| Registration-resend cooldown interaction in browser | UNPERFORMED as a timed UI workflow. Registration/OTP normal flow and resend CSRF source wiring/HTTP rejection are covered; no browser cooldown was bypassed. |

## Deployment configuration issues and recommendations

1. Restore access to approved external assets in the acceptance environment, or separately approve a locally built/hosted asset strategy. Rerun full shell/eBills visual checks in both themes. No styling/product fix is included in this verification task.
2. Supply a designated HTTPS staging endpoint, noncustomer test accounts for active/inactive/unverified/no-account states, a disposable representative database, isolated mail sink and designated Google test configuration. Do not reuse production consumer data or real customer mail recipients. Explicitly disable or route automatic account-sync notifications into the sink before full-dashboard acceptance.
3. Verify real TLS termination, trusted proxy HTTPS signaling, HTTP→HTTPS redirects and actual browser Secure/HttpOnly/Lax cookies. Do not trust arbitrary forwarded headers or relax CSRF/state/limits to make acceptance pass.
4. Verify writable private session/limiter storage, thresholds under shared IP/NAT use, server/proxy query-string redaction for OAuth/reset URLs, production error-display settings and deployment-secret provisioning described in Task 03. None was validated on staging here.
5. Confirm the Google first-link restriction for nonauthoritative third-party email with designated accounts. Keep blocked first-link cases on the approved password/recovery path until a separately approved explicit linking flow exists.
6. Before approving migrations, close the visual and staging blockers, run full dashboard/account/profile/eBills acceptance with isolated data, and approve outstanding workflow/security policies. These passing synthetic checks do not authorize migration or applicant-data collection.

## Verification artifacts and reproduction

Task 03A changed **test tooling/artifacts only**:

- Added `tests/new-connection/browser-fixture.php`, `browser-acceptance-test.js`, `browser-results.json`, six screenshot PNGs and this report.
- Updated `tests/new-connection/auth-security-test.php` with an explicit opt-in browser hook.
- Updated `tests/ebills-layout-browser-test.js` only with isolated headless startup flags to get beyond this workstation's GPU failure. Its stylesheet failure remains visible.

The working tree already contained Task 01–03 changes; they were preserved. No application/configuration code was modified in Task 03A, no database schema/data was changed, and no migration/new feature was implemented.

Reproduce isolated browser verification from PowerShell:

```powershell
$env:MCO_AUTH_BROWSER_ACCEPTANCE = '1'
& C:/xampp/php/php.exe tests/new-connection/auth-security-test.php
Remove-Item Env:MCO_AUTH_BROWSER_ACCEPTANCE
```

The opt-in affects the test harness only. The fixture runner copies the application and enables New Connection in its own server; it never sets the live portal's feature environment. A zero test exit means the functional assertions completed, **not** that the separate visual/staging blockers are resolved: inspect `browser-results.json` and this report.

Verification stops here. Overall browser/staging acceptance remains incomplete.
