# Task 06K — Apache Authentication Compatibility and Navigation Acceptance

Date: **2026-10-21, Asia/Manila**. **Isolated Apache acceptance passed: 330 server assertions and 23 Chrome checks.** Actual application/configuration/migration files are unchanged. New Connection remains disabled by default; no navigation item, authentication wiring or landing change was installed.

Change Set A continues to preserve existing Google identity behavior. The experimental Google policy is not called by this Apache fixture and is not part of the proposed navigation release.

## 1. Files and environment

Added under `tests/new-connection/`:

- `apache-navigation-acceptance-test.php`: reuses the 06J current-policy SQL coordinator with checked in-memory transformations.
- `apache-navigation-support.php`: separate Apache setup, status-only temporary patches, localhost helper contract, SQL principal/permissions, recovery and independent-worker tests.
- `apache-navigation-private.php`: private copied-server glue, restricted claim connection, matched navigation links and read-only landing rendering.
- `apache-navigation-browser.js`, `apache-navigation-browser-journeys.js`: reused CDP bootstrap plus actual rendered form/dialog journeys.
- `apache-navigation-results.json`, `apache-navigation-browser-results.json`: safe assertion evidence and stated limits.
- `apache-navigation-desktop.png`, `apache-navigation-mobile.png`, `apache-navigation-mobile-dark.png`: proposed landing/sidebar screenshots.
- This report.

Regressions refreshed `google-policy-claim-results.json`, `google-verification-review-results.json` and `localhost-apache-readiness-results.json`. Earlier reports remain historical snapshots. No dependencies added.

Environment: XAMPP PHP 8.2.12, Apache 2.4.58 Win64/PHP `apache2handler`, local MariaDB 10.4.32, Chrome 155.0.8059.85. Two separately launched Apache instances listen only on IPv4/IPv6 loopback at random ports. Their temporary configuration, copied controllers/helpers/views, sessions, local provider/mail doubles and credentials live outside `htdocs`; only a dedicated public subdirectory is aliased at `/mportalv2/`. Private configuration is not HTTP-accessible. Existing port-80/443 Apache remains running with its original PID/listeners; no actual Apache configuration was edited or reloaded.

The browser URL is `http://localhost:<disposable-port>/mportalv2/`. The **exact** proposed canonical `http://localhost/mportalv2/` is tested separately against the isolated helper. This is not a claim that actual port-80 DB-backed authentication has been wired or accepted.

## 2. Apache CSRF compatibility resolution

Fresh reproduction with the unchanged 06J Apache harness again completed 23 assertions and recorded **two expected compatibility failures**: `http_response_code(419)` produced wire HTTP 500 in the probe rejection and copied logout rejection. Its JSON continues to declare deployment readiness false. This is reproduction evidence, not an acceptance pass for 419.

In 06K **temporary copies only**:

| Actual production source requiring a later compatibility patch | Temporary proposed change / result |
| --- | --- |
| `app/controllers/index.php` | Existing invalid login-CSRF status 419 → 403; same predicate/message. Invalid Apache request returns 403; valid password login succeeds |
| `app/controllers/register.php` | Existing initial CSRF denial 419 → 403; unchanged registration/OTP/resend validation and message. Invalid request 403, valid registration/OTP journey succeeds |
| `app/controllers/forgot-password.php` | Existing invalid-CSRF branch currently renders its rejection with HTTP 200, **not 419**. Add 403 at that existing branch; retain `recovery_log(...csrf_invalid)` and message. Invalid request 403; valid synthetic recovery delivery succeeds |
| `app/controllers/reset-password.php` | Existing reset-CSRF denial 419 → 403. Invalid request tested with an otherwise valid synthetic reset token returns 403; valid one-time reset succeeds |
| `app/controllers/logout.php` | `logoutFailure(419, ...)` → `logoutFailure(403, ...)`; same POST/identity/token checks and message. Invalid token 403, valid confirmation destroys session |

The fixture also maps its own synthetic rejection helper to 403. This helper is not a production file. Neither real Google controller requires a CSRF-status patch; its existing OAuth rejection behavior remains unchanged.

Seven temporary-controller byte comparisons normalize only the proposed status edits back to their previous form and verify equality. Thus token checks, rejection text and logging calls remain intact. A real private recovery log entry with category `csrf_invalid` verifies safe security logging still executes. Log checks reject fixture identity/password/OTP/reset-token leakage. Existing valid/invalid token namespaces and logout method restrictions remain enforced.

**Resolution:** HTTP 403 is compatible with the tested Apache module and existing browser rejection handling. This establishes the small proposed compatibility patch; it does not apply it to actual controllers.

## 3. Localhost-development helper contract

The production `PortalAuthReturnIntent` file is unchanged. Only the temporary copy accepts the proposed `localhost-development` scope. Parent tests load an isolated renamed copy to test the exact no-port canonical URL without contacting actual authentication routes.

HTTP requires all of:

- Canonical host exactly `localhost`, path `/mportalv2` with optional trailing slash; no credentials/query/fragment or arbitrary destination.
- Explicit server-supplied development authorization and the exact development scope.
- Direct server/client addresses both in `127.0.0.1` / `::1`.
- Existing environment/feature gates for issuing a hint.

The private server supplies these values from its trusted fixture configuration/request connection, not browser environment parameters or forwarding headers. Alternate canonical hosts, alternate paths, missing authorization, nonloopback addresses and production HTTP scope are rejected. Existing HTTPS closed-target behavior remains unchanged. Only fixed `new_connection.home` is accepted; the 600-second hint/session match, one-time callback and session regeneration semantics remain the existing implementation.

The actual portal must later obtain authorization from explicit private development configuration and validate direct connection addresses in its factory; the fixture's private authorization literal must not be copied as an unconditional production setting. No existing synthetic guard or production HTTPS requirement was removed.

Ordinary no-flow authentication never invokes claim storage. Tests show ordinary password login succeeds when claims are full or the claim connection is killed. The identity connection is separate; this is independence from **claim-store failure**, not independence from the identity DB.

## 4. Apache server and browser journey results

Reproduction command:

```powershell
C:/xampp/php/php.exe tests/new-connection/apache-navigation-acceptance-test.php
```

The coordinator runs real copied controllers against one disposable synthetic identity schema through Apache. Existing 06F/06J JSON contract checks remain, then Chrome exercises actual copied HTML views, local scripts/styles, form posts, OTP fields, links and dialogs. Browser values are synthetic; local mail content is read privately by the test driver, never through a public mail endpoint.

| Journey | Result |
| --- | --- |
| Anonymous New Connection entry | Private fixture creates closed hint, redirects under `/mportalv2/`, and renders matched hidden flow/login links |
| Password login | Actual HTML form returns to the development landing; session regenerated; no account-link dependency |
| Ordinary password login | Actual HTML form redirects relatively to a **synthetic dashboard stand-in**; real dashboard/bootstrap never executed |
| Registration/OTP | Actual HTML registration and OTP forms post to copied controller, synthetic code verified; success returns to login with no authenticated session; explicit login returns to module |
| Forgot/reset | Actual forgot/reset forms and private synthetic mail sink; token remains one-use, reset does not authenticate; explicit login retains selected same-browser module navigation |
| Google initiation/callback | Actual Google button follows fixed server-rendered selected link, copied initialization generates state, local provider route returns locally, original callback identity body completes module return |
| Current Google verification behavior | In addition to existing bound/inactive/unverified/conflict tests, hosted auto-link fills a NULL timestamp and new Google registration creates a verified user **only in the disposable schema**, preserving current behavior |
| Google identity source integrity | Identity branch/SQL body between provider identity and session creation matches actual callback bytes; return hook lies outside that block |
| Active/verified zero-account eligibility | Module middleware resolves the same fixture users; no electric-account table is required |
| Expired/forged/superseded intent | Existing copied-controller cases reject stale context with fixed target; no arbitrary redirect or dashboard adoption |
| Session expiry | Actual helper expiration rejects private module access; existing reauthentication/recovery tests preserved |
| Logout | Actual rendered dialog opens and focuses Cancel; confirmation posts existing token namespace and destroys session; repeated complete browser journeys verify redirect to sign-in |
| CSRF | Invalid login, registration, forgot/reset and logout requests reject with 403; valid journeys continue normally |
| Browser privacy/resources | Private no-store, HttpOnly/Lax local HTTP cookie, no external provider/SMTP/asset requests and no runtime exceptions; no applicant/authentication data in browser storage |

Chrome acceptance passed **23 checks**, including repeated dialog checks; these are not 23 distinct journeys. The **330 server assertions** include repeated response-header checks and negative cases, not 330 separate scenarios. Browser form filling dispatches normal input/change/submit events and uses the actual form submission/navigation behavior, rather than replacing the controllers with JSON authentication doubles.

The local provider double never contacts Google. SMTP is replaced with a private sink accepting only synthetic `.invalid` addresses. No real client secret/mail/portal DB configuration is imported. Actual OAuth console/provider acceptance, live email, existing-Apache port-80 authentication and production HTTPS remain unperformed.

### Visual inspection

The following screenshots were opened and inspected, not merely generated:

- [Desktop, 1440px](apache-navigation-desktop.png): sidebar/header positioned correctly, local PALECO branding, plug icon/New Connection entry and two-column roadmap visible; page scrolls for remaining stages.
- [Mobile light, 390px](apache-navigation-mobile.png): collapsed navigation, readable wrapping/banner and single-column content; automated check confirms no horizontal overflow.
- [Mobile dark, 390px](apache-navigation-mobile-dark.png): dark panels/text and development banner readable. Theme switching uses the actual local theme control; its preference is cleared after testing, and no applicant details are stored.

The screenshot review covers this informational landing at these sizes. It does not certify all portal pages, 320px behavior, every keyboard path, full contrast compliance or the actual port-80 deployment. The browser JSON's generic full-visual-acceptance flag remains false; this limited manual review is recorded here.

## 5. SQL claim-store preparation and recovery

### Initialization and least privilege

The harness creates fixture `claim_control` and `return_claims` InnoDB tables in its random `mco_nc_test_<16 hex>` schema, then seeds a generation/control record. It creates an **ephemeral MariaDB test principal**, separate from synthetic portal identities, and grants only:

- SELECT/INSERT/DELETE on fixture claims.
- SELECT/UPDATE on fixture singleton control.

The Apache claim adapter actually connects as that principal. Identity queries still use the separate guarded fixture connection. Attempts by the claim principal to read `users`, create a table or alter the claims table are rejected. Initialization/admin credentials are never copied from actual portal configuration; the generated claim password lives only in private temporary configuration. `DROP USER` removes that principal during cleanup, and the fixture schema is dropped. These GRANT/REVOKE catalog effects are test infrastructure, not modifications to PALECO operational/user records.

The unchanged 06H SQL adapter is reused: own transaction, control-row lock, database UTC/clock checks, bounded expired cleanup, duplicate-before-capacity check, unique digest insert and acknowledged commit. It stores only digest/absolute expiry. No Google-policy method is invoked. Fixture CLI/SAPI guards are adapted **only in private copies** for Apache; existing migration guards remain byte-identical.

### Actual concurrent Apache workers

Two independent Apache instances share the private PHP session directory and claim store. A server-only barrier holds the first password request after validation while the second opens the old session. After release:

- Both reach the existing `portalAuthenticated()` session regeneration.
- Exactly one receives the module destination; the other receives fixed expired context.
- Exactly one digest is durably present in MariaDB.

Both requests may validly authenticate the same synthetic user; the one-time claim limits **navigation**, not identity authentication or module authorization. Current-user middleware remains the module authority. No applicant command exists to replay.

### Capacity, expiry, outage and restoration

| Case | Observed result |
| --- | --- |
| Capacity reached | New selected return fails with generic 503; ordinary password login still reaches dashboard |
| Expired claim | Least-privilege backend independently returns false at expiry; existing helper expiry tests also pass |
| Claim outage | Kills only the adapter's own connection ID; selected flow fails closed, no new authentication from that attempt; ordinary login bypasses claim call |
| Backup/restore quarantine | Snapshot synthetic rows/control, consume a live hint through Apache, retain its old session, close external readiness, restore the internally coherent older rows/quota. Retained old session cannot replay and receives 503 |
| Recovery reopening | With readiness closed, invalidate **all fixture sessions**, attest fresh generation and reopen. Old cookie/token rejects with 403; explicit fresh login flow succeeds |
| Concurrent closure | Shared readiness lock spans claim commit; operator exclusive closure serializes with active consumers. Prior 06I regression evidence covers an independently blocked gate writer; 06K uses this lock during its real SQL restore |

Backup here is a synthetic row/control snapshot restored transactionally in the same disposable schema, **not** a physical backup/PITR/failover rehearsal. The alternative full 600s-plus-approved-skew drain was not elapsed; this run uses actual global fixture-session invalidation. Lost COMMIT acknowledgment is covered by the separately rerun 06H adapter test with post-commit fault injection, not an Apache network-partition test.

### Operational recovery and rollback requirements

Before any real claim deployment, approve a separate dedicated schema/principal, durable authoritative writer, UTC/skew/capacity/cleanup policy and a trusted external readiness authority. Do not create tables in `mco_portal` or remove a disposable migration guard as a shortcut.

Close/readiness-lock and drain consumers **before** restore. Restore only with readiness independently closed; a coherent old SQL snapshot cannot prove its own missing claim history. Invalidate every retained/restorable old hint-bearing session or wait a trusted full maximum lifetime plus skew. Rotate generation and reopen only after explicit attestation. Generation rotation alone does not invalidate still-live helper hints.

Rollback navigation first by closing its gate and restoring the captured working files as a bundle. Keep ordinary authentication and current Google identity decisions unchanged. Retain tombstones until all previous hints are invalidated/expired; remove dedicated grants/schema only after verified drain/backup. Never restore portal user/password/verification state to undo navigation, and never reopen from an older claim backup without quarantine.

## 6. Exact proposed production change list

**Nothing in this list is installed by 06K.** Preserve current working versions/hashes before a later authorized implementation; do not reset unrelated work to Git HEAD.

| Change package | Exact files / boundary |
| --- | --- |
| Small compatibility patch | `app/controllers/index.php`, `register.php`, `reset-password.php`, `logout.php`: CSRF status 419 → 403 only. `app/controllers/forgot-password.php`: add 403 to existing invalid-CSRF branch; same check/message/logging. Update relevant regression expected wire statuses; no Google identity patch |
| Development helper scope | `app/helpers/auth-return-intent.php`: later approved narrowly authorized localhost scope only; preserve production HTTPS, fixed target, TTL and atomic callback. Do not deploy the test-renamed class |
| New production boundaries | Add reviewed `app/services/auth-return-navigation.php`, `app/services/auth-return-claims.php`, `config/auth-return.php`; lazy claim initialization, dedicated least-privilege configuration, shared readiness/recovery contract. No test imports or Google-policy/proof issuer classes |
| Password completion | `app/controllers/index.php`: selected closed outcome at successful authentication, unchanged regeneration, fixed mapped redirect; ordinary path independent of claims |
| Registration/OTP navigation | `app/controllers/register.php`, `app/views/pages/register.php`: matched hint in both POST forms and explicit-login success redirect; preserve resend/OTP/mail behavior |
| Login presentation | `app/views/pages/index.php`, `assets/js/login.js`: matched hidden hint and fixed server-rendered Google/register/forgot links; reset-success URL cleanup must not erase already-rendered selected context |
| Google navigation only | `app/controllers/google-login.php`, `google-callback.php`: optional hint in validated pending OAuth state and success seam. **No changes** to registration/linking/verification SQL, conditions, conflict handling, SDK settings or ordinary dashboard behavior |
| Forgot/reset navigation | `app/controllers/forgot-password.php`, `reset-password.php`, `app/views/pages/forgot-password.php`, `reset-password.php`: same-browser bounded association, fixed links and explicit-login redirect. Reset token/mail/password and no-auto-login semantics unchanged |
| Module entry | `app/controllers/new-connection.php`: later approved closed login hint only after feature/session checks; preserve default-off 404 and active/verified zero-account gate. No applicant form endpoints |
| Visible sidebar proposal | `app/helpers/portal-navigation.php`, `app/views/partials/portal-sidebar.php`: one New Connection entry, plug SVG, explicit unavailable rendering while collection feature remains off; do not alter account/eBills navigation |
| Informational landing proposal | `app/views/pages/new-connection.php`: development/unavailable banner and existing roadmap styling; no applicant inputs or action buttons. Keep controller/feature gate authoritative |

No navigation change is required to existing session-security/current-user middleware, actual Google configuration, root wrappers, logout confirmation/dialog JavaScript, password-recovery service, registration/forgot/reset JavaScript, dashboard/profile/account/eBills business logic or module migrations. Changing the logout **CSRF status** is distinct from changing its session/dialog behavior.

For each later patch: capture baseline, apply one independent concern, run copied/actual-target synthetic regressions, keep feature off, and revert that concern's exact files if rejected. Claim storage remains an independently approved deployment operation, not an extension/override of existing module migrations.

## 7. Proposed navigation and landing design

The screenshots are a reviewable proposal generated from private copies of the existing portal shell/view and local assets. They were not installed in actual navigation.

**Smallest safe first visible change:** add **New Connection** with plug icon as a clearly unavailable, non-navigating sidebar item while `MCO_NEW_CONNECTION_ENABLED` is false. The existing sidebar renderer needs an explicit disabled/unavailable branch (`aria-disabled`, visible status text, no actionable href); a label alone must not lead users to the current gated 404 or imply applications are open. Use existing local classes/icons and preserve every existing item. This two-file UI change needs no authentication wiring, claim tables or feature enablement.

Prepared landing:

- Existing logo, green palette, responsive sidebar/header, light/dark mode and local CSS/scripts.
- Clear **“Development preview — applications are unavailable”** notice, no submission/upload/payment buttons and no personal-information fields.
- Seven informational stage cards: Applicant Information and Submission; Document Submission and Verification; Billing Clearance; Online PMES; Technical Inspection; Payment and Handwritten Form Signing; Installation Scheduling.
- Explicit online preparation/read-only preview concept and PALECO printing for physical handwritten signing at the offline payment visit.
- No progress percentages, approvals, installation promises, draft identifiers or application records.

The private enabled fixture links to this preview for acceptance. **Do not turn on the actual collection feature merely to show it.** If a clickable actual-localhost preview is requested later while that feature remains off, separately approve a strictly local, active/verified read-only preview boundary/flag; keep the existing application entry default-off and expose no applicant endpoints. That additional route is not the minimum first UI change and is not created here.

## 8. Regression results and limits

| Suite | Fresh result |
| --- | --- |
| Apache copied-controller/SQL suite | PASS **330** |
| Rendered Chrome journeys | PASS **23**, zero external requests/runtime exceptions |
| Original 06J Apache reproduction | **23 completed; two 419/500 compatibility failures reproduced**, intentionally not claimed ready |
| `auth-security-test.php` | PASS **42**, isolated HTTP/server-TLS fixtures |
| `tests/password-reset-test.php` | PASS, PDO/mail doubles and real isolated limiter |
| `tests/session-actions-http-test.php` | PASS **22** |
| `auth-return-intent-test.php` | PASS **54**, production helper unchanged |
| `google-verification-review-test.php` | PASS **206**, 36 current callback cases plus separate proposed model |
| `google-policy-claim-test.php` | PASS **346**, disposable adapter and separate experimental-policy regression; experimental policy remains excluded from this integration |
| `foundation-test.php` | PASS **41**, default-off and zero-account eligibility |
| `database-foundation-test.php` | PASS **68**, guarded disposable schema only |
| `tests/structure-test.php` | PASS **194** |
| `tests/paleco-readonly-contract.php` | PASS |
| Syntax | PASS on three new PHP files, browser wrapper and injected journey body |
| Protected source comparison | Every `app`, `config`, `database/migrations` file matches pre-task SHA-256 manifest |

All final servers, temporary schemas, generated credentials/mail/provider/session artifacts and ephemeral SQL principals were removed. The actual Apache listener PID on 80/443 is unchanged. No actual portal/customer connection, operational SQL write or real Google/SMTP request occurred; reused isolation checks read schema metadata only.

During development, a strict-types fixture bootstrap error occurred before normal cleanup. Its one owned temporary schema/principal/tree was explicitly removed before proceeding; final successful runs complete automatic cleanup. Browser-driver quoting/interception issues were corrected in test code; no CSRF, rate limiter, OAuth state or migration protection was bypassed to pass. Final evidence supersedes those development attempts.

Remaining limits: physical durability/replication, distributed readiness locking, actual dedicated-store deployment, actual port-80 authentication, real Google callback console validation, HTTPS proxy/cookie acceptance, 320px and full accessibility/visual regression across other portal pages. SQL adapter source is still test-only; a production adapter needs a separate reviewed deployment boundary.

## 9. Recommendation and remaining approvals

**GO for the first separately approved visible-localhost UI change:** the disabled/unavailable New Connection sidebar item described above, with the prepared landing design retained for review. Keep the feature off. This is the smallest visible result and does not depend on adopting experimental Google policy or wiring real authentication.

Before any later navigation-only authentication deployment, approve the five-file status patch, narrowly scoped local helper/factory, fixed failure UX, least-privilege store initialization, capacity/skew/cleanup and external recovery ownership. Then authorize the actual file bundle and repeat synthetic acceptance on the intended Apache deployment. Current Google behavior must remain unchanged; Change Set B is separate and is not a gate for Change Set A.

Suggested next task: **06L — Localhost New Connection unavailable navigation indicator**, limited to the two UI files with default-off assertions, ordinary navigation regression and screenshots. No actual authentication/controller/schema change or collection enablement should accompany that first visible step.

Stop here. No live wiring, feature enablement, migrations, uploads, staff workflow or applicant submission follows this acceptance task.
