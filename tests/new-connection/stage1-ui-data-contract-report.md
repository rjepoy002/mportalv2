# Task 06O — Stage 1 UI and data contract preparation

Date: 2026-10-21. **Isolated development preparation complete. Real applicant collection remains disabled.**

## Reused implementation and changes

The existing Task 05B prototype and Task 05E HTTP fixture already implement the requested five-section development form. They are reused, not replaced with a second model. Task 05C draft persistence and Task 05D specialized atomic submission remain the persistence authority.

Files changed for this task:

- `app/views/pages/new-connection.php`: replaces guaranteed future online-form/printing wording with planned, approval-dependent language; explicitly accepts no applications or payments. Seven stages and layout are unchanged.
- `app/views/pages/new-connection-prototype.php`: adds provisional requiredness guidance and explicitly separates signed-in portal identity from applicant information. A display name is not proof of the legal applicant name; synthetic profile details require confirmation.
- `tests/new-connection/applicant-prototype-test.php` and `stage1-http-test.php`: copy the preview helper/config dependencies now required by shared navigation into their temporary fixture trees. No gate, controller, database or authentication behavior is weakened.
- This report and regenerated existing prototype/HTTP browser result JSON and screenshot artifacts under `tests/new-connection/`.

Unchanged reused components: `app/services/new-connection/applicant-contract.php`, `drafts.php`, `submission.php`, the workflow service, `assets/js/new-connection-prototype.js`, prototype CSS, `stage1-http-router.php`, `stage1-http-page.php`, `stage1-http-ui.js`, fixture login and synthetic notice/catalog providers. No new route, migration or contract version is introduced.

## Fields and contract

Contract: `stage1.v1-draft-20261020`; canonical field/type/length authority is `NewConnectionApplicantContract::fields()`, with server context supplying email, catalog versions, country and fixed request kind.

| Section | Existing provisional fields and validation |
|---|---|
| Applicant identity | Separate first/middle/last/extension names, mobile, identity confirmation; read-only verified synthetic portal email. First/last/mobile required for readiness; middle/extension optional. Unicode names up to 25 characters; supported Philippine mobile formats normalize through the existing helper to `09…`. No full-name splitting. |
| Residential address | Province, municipality, barangay, street/purok/sitio, building/unit, additional address, postal code. Municipality/barangay/street required for readiness; province must match municipality. Postal code optional, exactly four ASCII digits when supplied. |
| Proposed service location | Same structured address plus landmark and same-as-residential option. Relationship checks and copying are repeated server-side. Locality choices remain labeled synthetic. |
| Service request | Fixed new electric service request; provisional premises, occupancy and conditional category/detail fields. Requiredness follows existing conditional rules, not invented PALECO policy. No meter serial or existing electric account is requested. |
| Review and declarations | Read-only saved snapshot, Edit links, separate review confirmation and four unchecked test acknowledgments. Changed content requires save, fresh review and fresh selections. |

Validation rejects malformed JSON, unknown keys, incorrect types, invalid UTF-8/control characters, oversized input and invalid catalog relationships. Input limit is 64 KiB; canonical snapshot limit 32 KiB; HTTP envelope limit 96 KiB. Draft mode accepts valid incomplete information. Readiness additionally requires the existing mandatory and conditional fields, confirmations and acknowledgments. It is synthetic readiness, not production eligibility or official approval.

Versioned snapshots use deterministic JSON and SHA-256 hashes. Acknowledgment controls are distinct from snapshot applicant content and are not physical or approved electronic signatures. Submitted revisions remain immutable. No browser-controlled user IDs, workflow states, staff permissions or publication hashes are accepted.

## Working development behavior

Use the existing CLI coordinator `C:/xampp/php/php.exe tests/new-connection/stage1-http-test.php` for an automated, short-lived demonstration. It creates a random guarded disposable MariaDB schema and two loopback fixture servers, runs synthetic browser journeys, then removes them. It does not install a persistent localhost route. The separate prototype coordinator exercises UI-only disposable session draft behavior; it must not be confused with database persistence.

The HTTP fixture uses the authenticated synthetic session, current active/verified checks, ownership checks, CSRF, exact Origin, private no-store responses and strict command allowlists. A random `mco_nc_test_<16 hex>` schema holds synthetic users and `new_connection_*` records. Actual portal database/mail configuration and business controllers are not copied. Existing migration and service isolation guards remain intact.

Create initializes exactly seven stages. Save locks/checks application and revision versions, validates/canonicalizes incomplete data, updates snapshot/hash and lock version, and records safe audit metadata transactionally. Identical idempotency retries return the original result; changed payloads conflict. Stale versions cannot overwrite decisions or submitted revisions.

Review uses the saved snapshot and server-issued proof. Editing or changing/withdrawing test notices invalidates review and selections. Only the specialized atomic submission service commits the submitted revision, consent evidence, stage event and original receipt. Reauthentication resumes committed drafts without replaying unsaved commands. Synthetic submission never means approval or energization.

## Fresh tests

Environment: local XAMPP PHP 8.2.12, MariaDB 10.4.32, headless Chrome 155.0.8059.85. No live Google/SMTP calls or actual customer journeys.

| Suite | Result |
|---|---|
| Pure applicant contract | PASS 42 assertions |
| Prototype browser/UI isolation | PASS 97 checks |
| Stage 1 HTTP/browser integration | PASS 227 checks; rerun after final UI wording |
| HTTP database invariants | PASS: four synthetic applications/submissions; seven stages each; four consents, one transition event and one request receipt per submitted application; nonfixture schema signatures unchanged |
| Draft persistence | PASS 149 assertions, including independent-process races |
| Atomic Stage 1 submission | PASS 97 assertions, including transactions/concurrency |
| Foundation/navigation/default-off | PASS 47 assertions |
| Authentication security | PASS 42 assertions |
| Structure/dependencies | PASS 201 assertions |
| Separate Apache preview regression | PASS 77 assertions |
| PALECO read-only contract | PASS |
| Modified view PHP syntax | PASS |

All created disposable schemas were removed. Tests cover incomplete drafts, invalid data without partial writes, stale/concurrent saves, cross-applicant denial, inactive/unverified users, session recovery, exact retries, changed-payload rejection, consent requirements, proof expiry and publication changes, duplicate submissions, rollback, receipt recovery and save-versus-submit races. Browser checks cover 1440/390/320px light and dark modes, keyboard/error focus, section navigation, copying, normalization, review, dialogs, no horizontal overflow, no applicant browser storage, no external/missing resources and no JavaScript exceptions. Existing theme/sidebar preferences are the only permitted browser storage.

No actual customer database migration, user change, eBills/account workflow or operational write occurred. Actual Google/authentication controllers, return claims and application feature configuration were not changed. Tests use synthetic SQL identities rather than real portal session integration; no claim of authenticated production acceptance is made.

## Screenshots and UX findings

Fresh screenshots are in `stage1-http-screenshots/` and the existing prototype screenshot directory. These three were opened and visually inspected:

- [Desktop identity, light](stage1-http-screenshots/identity-1440-light.png)
- [Mobile identity, dark](stage1-http-screenshots/identity-390-dark.png)
- [Narrow mobile saved review](stage1-http-screenshots/review-320.png)

Labels, synthetic-only banners, focus outline and read-only email are visible; fields wrap without horizontal overflow. Mobile section navigation and the complete review produce a long vertical page. Before wider usability acceptance, consider a compact section navigation and collapsible review groups while retaining keyboard access and a complete accessible summary. No official PDF or application approval is shown. This targeted inspection is not a full screen-reader accessibility audit.

## Privacy questions and blockers

Owner approval remains required for legal applicant versus portal representative rules; authoritative forms and field necessity; address/service-area catalogs; premises/occupancy classifications; privacy purposes and lawful basis; recipients/processors and rights/contact wording; draft/submitted retention, deletion and backup handling; notice publication/version withdrawal; correction/reopening rules; office assignment; and offline payment/printing/signature procedures. Fixture notices are automated-test publications, never approved PALECO policy. Acknowledgments do not establish a lawful processing basis or signature approval by themselves.

Technical blockers before controlled real integration include an attested isolated identity environment, approved publication/catalog providers, key storage/rotation, shared rate limiting, least-privilege database access, HTTPS/proxy acceptance, backup recovery and an approved migration deployment contract. The synthetic provider and local root test connection are not deployment defaults.

## Exact proposed future file plan — not implemented

1. `config/new-connection-preview.php`, `app/helpers/new-connection-preview.php`, `new-connection-preview.php`, `app/controllers/new-connection-preview.php`: retain the independent read-only preview; do not turn it into a collection endpoint.
2. `config/new-connection.php`: retain default-off application behavior. Propose a separate explicit controlled-development authorization only after owner review; never infer it from browser input or preview access.
3. Proposed `tests/new-connection/stage1-local-development-runner.php`: start a manually inspectable, bounded-lifetime private fixture with generated disposable schema, synthetic-only identities and cleanup; reuse existing router/page/services rather than install actual portal routes.
4. `tests/new-connection/stage1-http-router.php`: retain synthetic authentication and closed routes; any future environment changes require isolation regression tests. Do not copy it into production.
5. `app/views/pages/new-connection-prototype.php`, `assets/js/new-connection-prototype.js`, `assets/css/new-connection-prototype.css`: refine mobile navigation and required/conditional field presentation against the same versioned contract; preserve fixture-only rendering guard.
6. `app/services/new-connection/applicant-contract.php`: change only after approved field/catalog decisions; introduce explicit version compatibility if the contract changes. `drafts.php` and `submission.php` remain authoritative and guarded until a separately approved deployment design exists.
7. Proposed `tests/new-connection/stage1-development-privacy-test.php`: add approved-policy boundary tests when actual notices/catalogs and representative rules are specified. Extend existing contract/HTTP/browser suites instead of inventing another form model.
8. No authentication, Google, dashboard, profile, account-linking, eBills or operational migration file is included in this proposed UI work.

Recommended next task: a bounded-lifetime, owner-reviewable synthetic localhost development runner and mobile UX refinement, followed by explicit policy/identity approval gates. Do not activate real applicant persistence or submission. Task 06O stops here.
