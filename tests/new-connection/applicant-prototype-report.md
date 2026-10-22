# Task 05B — Stage 1 Applicant Form Prototype

Date: 2026-10-20 (Asia/Manila). Status: isolated prototype complete; not authorized for real applicant information.

## Scope and isolation

The five-section page and pure PHP contract follow Task 05A. There is no portal controller or entry point for collecting applicant data. The view returns 404 unless the disposable fixture defines its rendering guard. `new-connection.php` continues to use its existing landing page; `config/new-connection.php` remains disabled unless explicitly enabled through its existing environment flag. No flag was enabled for this task.

The CLI browser runner copies an allowlist of presentation/helpers and local assets into a randomly named temporary directory, starts PHP on a random loopback port, and serves only `/prototype`, `/fixture-api`, and local assets. No database configuration, mailer, workflow service, or business controller is copied or executed. Only synthetic data is supplied. Fixture draft state uses a separate PHP session name and temporary session directory; the server and directory are removed after the test. There are no database connections, operational queries, emails, uploads, migrations or real submission routes in this prototype.

Do not deploy the test router or treat its synthetic persona as portal authentication. The endpoint exists only inside the disposable test process, not the actual portal.

## Files added and modified for Task 05B

Added:

- `app/services/new-connection/applicant-contract.php` — pure contract validator/canonicalizer, no I/O or persistence.
- `app/views/pages/new-connection-prototype.php` — guarded presentation template using the existing sidebar/header and local branding.
- `assets/css/new-connection-prototype.css` — scoped layout, dark mode, error/focus styles and mobile wrapping.
- `assets/js/new-connection-prototype.js` — section navigation, address copying, conditional fields, review, fixture-only validation/save calls, expiry and dirty-state behavior.
- `tests/new-connection/prototype-data.php` — synthetic profile and explicitly synthetic locality/service catalogs.
- `tests/new-connection/applicant-contract-test.php` — pure validation regression tests.
- `tests/new-connection/applicant-prototype-test.php` — temporary HTTP/session fixture and cleanup runner.
- `tests/new-connection/applicant-prototype-browser-test.js` — Chrome CDP browser checks and screenshot capture.
- `tests/new-connection/applicant-prototype-browser-results.json` — recorded browser results.
- `tests/new-connection/applicant-prototype-screenshots/` — 18 synthetic-data screenshots.
- This report.

Regenerated: `assets/css/portal-utilities.css` using the existing local Tailwind build. No new dependencies were added. Other dirty files from earlier tasks were preserved; the dashboard controller, production authentication/account/billing logic, database migrations and feature configuration were not edited for Task 05B.

## Form structure and behavior

| Section | Prototype behavior |
| --- | --- |
| Applicant Identity | Separate first/middle/last/extension names; structured synthetic prefill; applicant confirmation; Philippine mobile normalization through the existing helper; server-context email displayed read-only. No legacy full-name splitting. |
| Residential Address | Province where applicable, city/municipality, barangay, street/purok/sitio, house/lot/unit/building, additional address and optional postal code. Only example localities. |
| Proposed Service Location | Separate address plus optional landmark; Same as Residential Address copies editable fields and is enforced again by the validator. Parent changes clear invalid dependent selections. |
| Service Request | Fixed New Electric Service Connection; explicitly prototype-only premises, occupancy and conditional category choices. Other occupancy requires clarification; example shop classification requires its example connection category. |
| Review and Acknowledgments | Read-only complete field summary with section Edit buttons; separate review confirmation and four initially unchecked draft acknowledgments. No PDF, official form, print, signature or submission action. |

This is a normal page with Back, Continue, direct section buttons and section progress. Continue validates the current section; direct navigation allows reviewing incomplete drafts. Full readiness checks report missing fields/confirmations. Editing data clears confirmations/acknowledgments associated with the previous content. Review content is constructed with DOM text nodes rather than interpolated applicant HTML.

Save Draft explicitly says it saves only a disposable fixture draft. Reload restores the last fixture save during that test session; acknowledgment selections and review confirmation are not restored. Unsaved changes trigger the browser warning after user interaction. The ten-minute demonstration expiry disables editing/saving and the fixture server rejects further requests; reloading can recover only the last saved fixture state. No applicant information is written to localStorage/sessionStorage; existing shell theme/sidebar preferences are the only browser-storage keys observed.

## Validation contract

Version: `stage1.v1-draft-20261020`. Input sections are `applicant`, `residential_address`, `service_location`, `service_request`, `review`, and `acknowledgments`. The closed field/type/length specification is returned by `NewConnectionApplicantContract::fields()`; `emptyInput()` supplies nullable strings and false booleans. This is a versioned draft implementation of Task 05A, not a final approved production schema.

- JSON must be an object; section values must be objects. Unknown fields/sections, server-owned fields, wrong JSON types, malformed JSON/UTF-8, prohibited control characters and oversized values fail validation.
- Maximum validator input is 64 KiB; canonical snapshot maximum is 32 KiB. Fixture command envelopes are capped separately at 96 KiB. Strings are trimmed, empty text becomes null, and permitted multiline line endings are normalized.
- Name parts allow Unicode text up to 25 characters, following existing profile compatibility. Required readiness fields are first/last name, valid mobile, both cities/barangays/street details, premises and occupancy, conditional details, profile/review confirmation and all four draft acknowledgments. Optional middle/extension names, building details, landmark, postal code and clarification remain optional.
- Mobile uses the existing Philippine helper: supported local/international formats become 11-digit `09...` values. Postal codes, when supplied, are exactly four ASCII digits and remain strings to preserve leading zeroes.
- Synthetic catalog membership and province/city/barangay relationships are validated server-side. Same-address copying is repeated server-side. Conditional categories cannot be supplied for unrelated premises/occupancy choices.
- Email, country, resolved locality labels, catalog versions, contract version and fixed request kind come from server context; browser overrides are rejected.
- Draft mode accepts valid incomplete data and reports `ready: false`. Complete-check mode also returns missing required fields. `ready: true` means only the synthetic demonstration is complete; results are marked `approval: prototype_only`. It authorizes no real application or approved consent.
- Canonical snapshots recursively sort keys, encode UTF-8 JSON deterministically and include a SHA-256 digest. Checkbox acknowledgments are excluded from snapshots. No revision is persisted and this code does not implement submitted-revision storage or immutability; production integration must enforce that separately.

The four acknowledgment texts are copied from Task 05A draft wording and visibly marked PENDING / draft only: privacy, accuracy, application/document verification, and physical printing/signing during the payment visit. No approved privacy notice exists in this fixture. Checking a control is not a handwritten or approved electronic signature.

## Verification and screenshots

Environment: Windows/XAMPP PHP CLI and temporary PHP HTTP server; Chrome **155.0.8059.85** headless via CDP. Tests used only synthetic applicant data at **1440 × 1000**, **390 × 1000** and **320 × 1000** CSS viewport sizes. External page requests were intercepted and blocked; none were attempted. No remote fonts, CSS or icon requests were required.

| Check | Result |
| --- | --- |
| `php tests/new-connection/applicant-contract-test.php` | PASS — 42 assertions, including malformed/type/unknown-key/oversize rejection, Unicode boundaries, phone formats, postal format, catalog relationships, conditional requirements and canonicalization. |
| `php tests/new-connection/applicant-prototype-test.php` | PASS — 97 browser checks; temporary state removed. |
| `php tests/structure-test.php` | PASS — 184 assertions. |
| `php tests/paleco-readonly-contract.php` | PASS. |
| `node tests/new-connection/local-assets-test.cjs` | PASS — 102 checks; local icon/font/CSS resources found. |
| `php tests/new-connection/foundation-test.php` | PASS — 41 assertions in its isolated synthetic, SELECT-only fixture. |
| PHP syntax, all five added PHP files; Node syntax, both added JavaScript files | PASS. |
| Existing `npm.cmd run build:assets` | PASS; nonfatal Browserslist freshness warning. Production page requests require no Node build. |
| `git diff --check` | PASS; existing line-ending conversion warnings only. |

Browser checks cover all five sections in light/dark mode at every width, horizontal overflow, Back/Continue/direct navigation, read-only email, Unicode prefill, keyboard Tab focus, error-summary and error-link focus, mobile normalization, address copying, conditional fields, review/Edit behavior, unchecked acknowledgments, failed/passed synthetic readiness, reset-on-edit, fixture draft recovery, unsaved-change dialogs, expiration, HTTP 401 for expired requests, HTTP 419 for incorrect CSRF, browser-storage keys, missing resources and runtime exceptions. No horizontal overflow, missing assets, external requests or JavaScript runtime exceptions were observed.

Actual screenshots were opened and visually inspected, including desktop identity, narrow dark identity/request, mobile residential address, desktop service location and the complete desktop review. Focus scrolling uses a header offset to keep targeted controls/headings visible. Full-page screenshots are captured from scroll position zero to avoid sticky-header capture artifacts.

- [Desktop identity — light](applicant-prototype-screenshots/identity-1440-light.png)
- [Desktop identity — dark](applicant-prototype-screenshots/identity-1440-dark.png)
- [390px identity — light](applicant-prototype-screenshots/identity-390-light.png)
- [320px identity — dark](applicant-prototype-screenshots/identity-320-dark.png)
- [390px residential address](applicant-prototype-screenshots/residential-390.png)
- [Desktop service location](applicant-prototype-screenshots/service-location-1440.png)
- [320px service request](applicant-prototype-screenshots/request-320.png)
- [Desktop complete review](applicant-prototype-screenshots/review-1440.png)
- [390px review](applicant-prototype-screenshots/review-390.png)

All 18 captures and individual assertion names are indexed in [browser results](applicant-prototype-browser-results.json). Reproduce with `C:/xampp/php/php.exe tests/new-connection/applicant-prototype-test.php` and Node/Chrome at the runner's documented executable paths (Chrome may be overridden with `CHROME_BINARY`).

Limitations: Chrome emulation is not physical-device, Safari/Firefox or assistive-technology acceptance. No live login, staff permissions, operational account linking, eBills, actual database persistence, approved notices or official document previews were exercised. Shared navigation is reused visually, but fixture destinations outside its allowlist return 404. Existing production feature behavior is protected by isolation and contract tests; this report does not claim a new end-to-end production acceptance run. The fixture expiry/reload behavior is a demonstration, not a production recovery or retention policy.

## Unapproved requirements and next persistence task

PALECO must approve authoritative locality/service-area catalogs, premises/occupancy/category rules, requiredness and name-length exceptions, privacy notice and acknowledgment versions, retention/deletion policy and official form originals/mapping. The synthetic catalog must never become a production fallback, and the prototype-only readiness result must never authorize submission.

For a separately approved persistence task: first finalize these policy inputs and server-owned context mapping; integrate verified active-user authentication and ownership checks without requiring linked electric accounts; keep CSRF and enforce payload/rate limits; add authenticated private no-store responses and safe logs; implement transactional portal-owned draft storage with expected-revision concurrency checks, deterministic validation/hashes and duplicate-request protection. Restore drafts only after reauthentication/ownership checks; do not store applicant data in browser storage. Persist versioned approved acknowledgments separately only at the approved submission boundary. Preserve submitted revisions and append-only history, and never overwrite a submitted snapshot to correct it. Add disposable-database tests for authorization, races, rollback, replay and immutable revision behavior before enabling any real collection.

Task 05B stops here. No production persistence, forms route activation, migration execution, official printing or application submission was introduced. New Connection remains disabled by default.
