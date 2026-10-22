# Task 06N — Localhost preview acceptance

Date: 2026-10-21. Result: **isolated acceptance passed; authenticated actual port-80 acceptance remains untested**. No owner-attested synthetic identity with demonstrated isolation was supplied for the actual portal identity database. No users were queried or created there to obtain access.

## Environment and coverage

Windows/XAMPP, Apache 2.4.58 with PHP 8.2.12 (`apache2handler` configuration), headless Chrome 155, Node 24. Actual deployment: `http://localhost/mportalv2/`.

The new CLI-only `localhost-preview-apache-test.php` reuses the existing preview/foundation suites, copying the actual route, controller, middleware, navigation, view and session helper into a temporary directory outside htdocs. A separate Apache process listens on a random loopback port. Its replacement PDO accepts exactly the existing current-user SELECT and returns synthetic users; it cannot connect to any database or execute writes. Synthetic session files are private fixture state. The temporary server and tree are removed afterward. This verifies Apache request execution, not MariaDB integration or real password authentication.

`localhost-preview-acceptance-browser.cjs` exports the authorized HTML produced by that Apache test, then serves it through a temporary static PHP server for browser checks. Only local portal assets are allowed; customer/action routes and external resources are blocked. **These screenshots are synthetic browser rendering, not authenticated port-80 screenshots or live browser-to-Apache navigation.** No dashboard was executed.

## Actual port-80 results

Requests did not follow redirects or supply authentication cookies:

| Request | Result |
|---|---|
| Preview GET | 302 to relative `index.php`; private, no-store |
| Real `new-connection.php` GET | 404; no-store, private |
| Preview with public Host | 404 |
| Preview with X-Forwarded-For | 404 |

Recorded in `localhost-preview-acceptance-port80-results.json`. Anonymous requests stop before identity connection. Actual authenticated sidebar clicking, current-user SQL execution, inactive/unverified identities and expiry on port 80 remain blocked by the absence of an authorized isolated account. No real customer account was used.

## Tests performed

| Suite | Result |
|---|---|
| Separate Apache preview acceptance | PASS, 77 assertions |
| Foundation/navigation/default-off isolation | PASS, 47 assertions |
| Authentication security, including synthetic HTTP/TLS session settings | PASS, 42 assertions |
| Session actions HTTP | PASS, 22 assertions |
| Structure/dependencies | PASS, 201 assertions |
| PALECO read-only contract | PASS |
| Browser acceptance | PASS, 13 checks |
| New Apache harness PHP syntax | PASS |

The Apache suite covers eligible active/verified synthetic users with no linked-account state; inactive, suspended, unverified and missing users; anonymous redirects; idle and absolute expiration; GET/HEAD; POST rejection before identity query; private caching; infrastructure failure masking; local/environment/proxy gates; fixed preview navigation and active state; default-off application behavior; and existing logout/session assertions. It inherits the existing security and source-isolation assertions rather than replacing them. No migration, claim store, SMTP, Google or operational database is involved.

The browser checks cover 1440px light/dark, 390px light/dark and 320px light, with no horizontal overflow; mobile sidebar opening; fixed preview href and `aria-current=page`; seven-stage rendering; absence of applicant form/upload controls; keyboard Tab reaching a focus-visible control; and no JavaScript exceptions or blocked customer/external requests. These are targeted checks, not a complete accessibility audit. Dark mode at 320px and screen-reader acceptance were not separately tested. The fixed link destination was inspected and requested by the HTTP suite; a browser click following the authenticated actual Apache route was not performed.

## Content and visual review

The controller defines all seven stages in the requested order: applicant information, documents, billing clearance, PMES, inspection, payment/handwritten signing, installation scheduling. The development notice explicitly says applications are unavailable and the page accepts none. Stage 6 states that staff prepare printed forms and applicants visit to sign by hand and pay offline. Stage 7 explicitly distinguishes scheduling from energization. No approval, payment initiation or application submission control is present.

The four screenshots below were opened and visually inspected. Desktop branding, plug icon, active Preview label, spacing and contrast appear consistent with the portal. Mobile text wraps cleanly and the header/sidebar controls remain usable. The seven stages extend below the viewport and are available by normal page scrolling.

- [Desktop light](localhost-preview-acceptance-desktop-light.png)
- [Desktop dark](localhost-preview-acceptance-desktop-dark.png)
- [Mobile light](localhost-preview-acceptance-mobile-light.png)
- [Mobile dark](localhost-preview-acceptance-mobile-dark.png)

Minor content issue for owner review: the shared paragraph says applicants “will” complete forms and staff “will” print them when applications open. The development banner qualifies this, but consistent “planned” wording would avoid presenting an unapproved future process as a commitment. No live functionality or payment promise is enabled. No production content was changed during acceptance.

## Changes and isolation

Added only this report, `localhost-preview-apache-test.php`, `localhost-preview-acceptance-browser.cjs`, the port-80/browser result JSON files and four screenshot PNGs under `tests/new-connection/`. Existing application, configuration, authentication, Google, dashboard, profile, eBills, account linking, workflow and migration files were not edited by this task. The workspace already contains changes from earlier tasks; Git differences from HEAD must not be attributed entirely to this acceptance run.

Existing Google behavior was preserved by making no controller changes; no live Google journey was exercised. Likewise, unchanged dashboard/eBills/account-linking code and read-only/structure contracts do not constitute full authenticated functional regression acceptance. Real application access still returned 404, and its default-off configuration was verified by the foundation suite. No applicant information was collected, no database connection or migration was performed, and no operational writes occurred. Session creation/destruction was limited to isolated synthetic fixture files.

## Recommendation

Accept the read-only preview provisionally for local development. Before declaring actual localhost authenticated acceptance complete, have the owner designate and attest an isolated synthetic identity/database environment, then perform the sidebar click and expiry/eligibility checks there. Keep applicant collection and the application feature disabled. The next phase should resolve approved content/catalog/privacy decisions and staging identity authorization before proposing any live persistence or submission wiring. This task stops at acceptance.
