# Task 03B — Local CSS and asset reliability

Completed 20 October 2026 (Asia/Manila). **Local/offline presentation acceptance passed in the isolated Chrome fixtures described below.** The Task 03A CDN styling blocker is resolved. Staging HTTPS, real Google sign-in and complete database-backed workflow acceptance remain separate work.

## 1. Files added and modified

Added build inputs and reproducibility:

- `package.json`, `package-lock.json`: exact development-only asset dependencies and build command.
- `tailwind.config.cjs`: existing Tailwind v3 palette, class-based dark mode, card shadow and PHP/JavaScript content paths.
- `resources/css/portal.css`: build input containing Tailwind base/components/utilities directives.
- `resources/build/copy-vendor-assets.cjs`: copies official local runtime distributions, fonts, licenses and Chart.js source map.

Added committed runtime artifacts:

- `assets/css/portal-utilities.css`: minified, generated production utility stylesheet, **30,703 bytes**.
- `assets/vendor/bootstrap-icons/bootstrap-icons.min.css`, `fonts/bootstrap-icons.woff`, `fonts/bootstrap-icons.woff2`, `LICENSE`.
- `assets/vendor/chart.js/chart.umd.js`, `chart.umd.js.map`, `LICENSE.md`.
- `assets/vendor/tailwindcss/LICENSE`.

Modified application presentation/configuration:

- `app/views/pages/dashboard.php`, `consumer-education.php`, `new-connection.php`: local utility stylesheet replaces runtime Tailwind script/configuration. Dashboard also loads local Chart.js.
- `app/views/pages/index.php`, `register.php`, `reset-password.php`: local Bootstrap Icons stylesheet replaces CDN URL; Google Fonts/preconnect references removed.
- `assets/css/login.css`, `register.css`, `forgot-password.css`, `reset-password.css`: explicit `"Inter", system-ui, -apple-system, "Segoe UI", sans-serif` fallback stack.
- `.gitignore`: excludes development `node_modules/` and `.tmp/` build cache.
- `config/apache-root.htaccess`: denies HTTP access to `node_modules`; equivalent protection added to this checkout's ignored local root `.htaccess` without replacing its existing development/security directives. No CSP relaxation was introduced.

Added/updated verification artifacts:

- Added `tests/new-connection/local-assets-test.cjs` and `dashboard-presentation-fixture.php`.
- Updated `tests/new-connection/browser-fixture.php` and `browser-acceptance-test.js` to copy local vendor resources, block external requests, exercise the actual dashboard template with synthetic data, and record dedicated Task 03B results/screenshots.
- Updated `tests/ebills-layout-browser-test.js` to load the actual generated local CSS and wait for the asynchronous native dialog close event before reopening it. No eBills production logic changed.
- Added `tests/new-connection/local-assets-browser-results.json`, 21 screenshots under `local-assets-screenshots/`, and this report. Task 03A results/screenshots are preserved separately.

The working tree already contained Tasks 01–03A. Those earlier authentication/controller changes were preserved; this task did not modify authentication, account-linking, eBills, billing or profile business logic, the dashboard controller, any schema/data, or the New Connection feature configuration/workflow.

## 2. Local build strategy and scope

Inventory found runtime Tailwind in three utility-based pages: dashboard, consumer education and New Connection. Their inline configurations used the same PALECO palette and class dark-mode convention; dashboard/education also defined the card shadow. The login/registration/reset pages instead use page-specific CSS and Bootstrap Icons. Forgot-password already has its own local stylesheet. There is no benefit in loading utility preflight into those authentication pages, so that separation is retained.

The build pins **Tailwind CSS 3.4.17**, **Bootstrap Icons 1.11.3** (the previous CDN icon version), and **Chart.js 4.5.1**. The prior Chart.js URL was unversioned; it is now a reviewed, fixed local UMD bundle exposing the same `Chart` API used by the dashboard. All three packages are development dependencies, with lockfile integrity metadata. Package installation used `--ignore-scripts`.

Tailwind scans root PHP entry points, all `app/**/*.php` (including status strings/controllers/helpers), compatibility includes, API PHP and `assets/js/**/*.js`. This includes classes in conditional PHP strings, AJAX partials and JavaScript-created UI. Existing classes are expressed as complete tokens; no blanket safelist is needed. Future utility additions must remain full literals or be explicitly safelisted and rebuilt; concatenating class fragments cannot be inferred by the compiler. See [Tailwind v3 content configuration](https://v3.tailwindcss.com/docs/content-configuration).

The generated stylesheet retains v3 preflight only on the same utility pages that previously received it, and loads before `portal-shell.css` / module-specific CSS. No global utility stylesheet was added to authentication pages. PALECO color values, breakpoints, spacing utilities, arbitrary selectors/values, dark variants, focus styles and card shadow are preserved in the shared build configuration. This avoids a v4/design migration and avoids conflicts with existing page CSS.

Bootstrap Icons uses its original CSS classes and glyphs, with relative local WOFF2/WOFF files; icon names/meanings and production HTML/JavaScript icon markup were not substituted. Licenses are retained. This follows the [official Bootstrap Icons installation approach](https://icons.getbootstrap.com/usage/).

Google font requests have been removed. If Inter is installed locally it may be used; otherwise system fonts render immediately. Slight font metrics differences from a successfully downloaded Inter font are an intentional allowed fallback, not a promise of pixel-identical external-font rendering.

Chart.js is local too, so loading the dashboard does not require a chart CDN. Its source map is copied locally to avoid a dangling development-tools reference. The default build produced the same stylesheet hash on repeat builds:

```text
SHA-256 portal-utilities.css
de5990940252be825581a009067ec7fa91adab210caf72b7917bce62922227e4
```

### Build and deployment

On a developer/CI machine:

```powershell
npm.cmd ci --include=dev --ignore-scripts --cache .tmp/npm-cache
npm.cmd run build:assets
node tests/new-connection/local-assets-test.cjs
```

On other shells use `npm` in place of `npm.cmd`. PowerShell's script policy prevented running `npm.ps1`; the normal `npm.cmd` entry point was used without changing execution policy. The initial restricted network request was denied; an approved tool escalation downloaded the pinned build dependencies. Production/browser rendering does not download npm packages.

Deploy the updated templates, generated `assets/css/portal-utilities.css`, full `assets/vendor/` runtime tree, existing page assets and applicable Apache deny rule. **Node.js/npm are not required on the PHP production server during requests, and PHP never runs the build.** Do not deploy `node_modules/`, `.tmp/`, or developer caches. Rebuild assets when template/JavaScript classes change and invalidate stale deployment caches as needed. Keep normal stylesheet/font/script MIME types and existing security policy; these resources are same-origin.

## 3. Browser tests and results

Environment: Windows, PHP 8.2.12, Node v24.20.0, actual headless **Chrome 155.0.8059.85**, disposable profile. No new browser package was installed. All page traffic targets the copied loopback PHP fixture. DevTools request interception explicitly allows only that fixture's origin and blocks every other origin; zero external asset failures means the tested pages did not attempt external resources, not that blocking was disabled.

The fixture copies actual current PHP templates, JavaScript and generated assets. Synthetic PDO/mail/identity data prevent customer access or real mail. The dashboard presentation fixture invokes only the actual view and pure display helpers; it does **not** invoke dashboard synchronization/actions. eBills details/terms responses are synthetic, require the existing CSRF token, and carry the actual terms version; final enrollment is not submitted. Profile forms are displayed but not saved to live storage. No operational PALECO table or schema was modified.

| Verification | Result |
|---|---|
| Offline local-assets browser suite | **PASS — 88 assertions**, zero styling blockers, missing assets, external resource requests or JavaScript runtime exceptions. See [machine-readable results](local-assets-browser-results.json). |
| Login and icons | Actual login form and local Bootstrap Icons font load; desktop/mobile rendering, valid login, CSRF rejection and local HTTP cookie behavior pass. |
| Registration / recovery / reset / logout | Existing browser workflows pass through actual forms/controllers with synthetic persistence/mail. Registration/OTP, reset/new-password login, logout confirmation/Cancel and subsequent denial pass. |
| Dashboard shell and Chart.js | Actual view rendered with synthetic account/chart data; local Chart.js instantiates a chart. Fixed sidebar, sticky header and mobile chart overflow checks pass. Controller/synchronization not run. |
| Profile and eBills presentation | 1440px, 390px and **320px** viewports, both themes: no document overflow; dialog fits, terms scroll, initial focus is inside, personal fields use two/one columns as appropriate, Cancel returns focus. Terms are read through the normal scroll/consent path. No final enrollment action. |
| New Connection | 1440px and 390px, both themes: seven stages, appropriate column count, no horizontal overflow, correct computed light/dark background colors, working navigation/session dialogs. No-account and disabled-feature cases still pass. |
| `node tests/ebills-layout-browser-test.js` | PASS: 40/38/38 assertions at actual legacy window viewports 1424×849, 752×873 and 500×693. Exact narrow 390/320 coverage comes from the newer DevTools suite above. |
| `node tests/profile-mobile-browser-test.js` | PASS: ten profile interaction checks at each exact 390px and 768px iframe width. |
| `node tests/new-connection/local-assets-test.cjs` | PASS — 92 read-only artifact checks: local references, font files, 18 used icon glyph names, utility selectors including responsive/dark/arbitrary classes, font stacks, lockfile and disabled-by-default feature contract. |
| Auth security / foundation PHP suites | PASS — 42 / 41 assertions, respectively; isolated data only. |
| Password reset / session-action HTTP suites | PASS; session HTTP suite has 22 assertions. |
| Profile / linking / terms PHP regressions | PASS — 135 profile assertions, linking input/configuration checks, 24 eBills terms/render assertions. |
| eBills / linking / session-action JavaScript regressions | PASS — 174 eBills modal assertions plus linking submission and session confirmation checks. |
| Structure / operational read-only contract | PASS — 180 structure assertions; read-only contract passes. |
| Changed PHP template/fixture syntax, browser-runner syntax and whitespace checks | PASS. |

One early standalone eBills test run reopened a modal before its prior native close event finished, leaving synthetic test state stale. Waiting for that event in the test fixed the race; no eBills application handler or security rule was changed. Test-fixture initialization issues were corrected before the final results above; failed intermediate runs are not presented as passing application acceptance.

Reproduce the offline suite:

```powershell
$env:MCO_AUTH_BROWSER_ACCEPTANCE = '1'
$env:MCO_LOCAL_ASSETS_ACCEPTANCE = '1'
& C:/xampp/php/php.exe tests/new-connection/auth-security-test.php
Remove-Item Env:MCO_AUTH_BROWSER_ACCEPTANCE, Env:MCO_LOCAL_ASSETS_ACCEPTANCE
```

These are test-harness switches, not production feature flags. The test server enables New Connection in its temporary environment only; the project default remains disabled.

## 4. Screenshots inspected

Actual screenshots were opened and visually inspected, not inferred solely from assertions. Representative corrected layouts:

- [Dashboard desktop with local chart](local-assets-screenshots/dashboard-desktop.png) and [mobile dashboard](local-assets-screenshots/dashboard-mobile.png).
- [New Connection desktop dark](local-assets-screenshots/new-connection-1440-dark.png) and [mobile light](local-assets-screenshots/new-connection-390-light.png).
- [Profile mobile dark](local-assets-screenshots/profile-390-dark.png).
- [eBills desktop light](local-assets-screenshots/ebills-1440-light.png), [390px light](local-assets-screenshots/ebills-390-light.png) and [320px dark](local-assets-screenshots/ebills-320-dark.png).
- [Login mobile with local icons](local-assets-screenshots/login-mobile.png) and [registration mobile](local-assets-screenshots/registration-mobile.png).

Inspection confirms the previous oversized/unpositioned sidebar icons and shell/content overlap are resolved. The header/sidebar align correctly, New Connection cards wrap into one mobile column/two desktop columns, dark colors are visible, profile fields fit and the eBills scroll area/buttons fit even at 320px. Other screenshots in the same directory preserve additional width/theme combinations. Authentication pages retain their existing light-theme design; no new authentication-page dark mode was introduced. New Connection screenshots are full-document captures; other captures are viewport-sized.

## 5. Remaining issues and limits

- Real staging HTTPS/proxy cookies, real Google provider sign-in, SMTP delivery and complete database-backed linking/eBills/billing workflows remain unperformed. Local fixture success does not close those Task 03A staging requirements or authorize migrations.
- Consumer Education's CSS/template dependencies were migrated and source-scanned, but its live playlist/video behavior was not browser-tested here. Existing YouTube/media/thumbnail services and other intentional external data services remain unchanged; this work removes critical CSS/icon/chart/font CDN dependencies, not the need for connectivity to watch remote videos or authenticate with Google.
- Visual acceptance covers the inspected Chrome synthetic fixtures and measured widths. Edge/Firefox/Safari, physical devices, arbitrary browser zoom and every possible production data length were not tested.
- The build emits a nonfatal Browserslist database freshness warning. Review browser-data/compiler updates through a controlled dependency update; no runtime network fallback or automatic update was added.
- Include generated assets and fonts in deployment. Excluding them, forgetting to rebuild after class changes, or wrong server MIME/cache rules can reintroduce failures. Apache deny rules were updated but actual staging Apache/Nginx behavior was not exercised by the PHP fixture server.
- System-font fallback is reliable offline but can differ slightly from remote Inter. The pinned local Chart.js version replaces a previously unpinned CDN URL; its existing global API and synthetic rendering passed, not every possible billing chart dataset.

## 6. Functionality and stop boundary

No authentication, account-linking, eBills, profile or billing production business logic changed in Task 03B. Existing icon meanings, navigation links, form actions/CSRF fields, modal scripts, eligibility rules and security settings remain intact; the compatibility tests above pass within their stated limits. No CSP was weakened, database operation executed against real portal/operational data, email sent to a real recipient, new staff/applicant feature added, or New Connection submission enabled.

Recommendation: deploy these prebuilt assets to the designated staging environment, verify asset MIME/cache handling and repeat the remaining provider/HTTPS/database-backed acceptance there. Stop before application migrations/forms; those still require separate approval.
