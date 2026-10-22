# Task 06J — XAMPP Localhost Authentication Integration Readiness

Date: **2026-10-21, Asia/Manila**. Inspection, isolated tests and implementation package only. **No actual authentication controller, Google behavior, application configuration or migration was changed. New Connection remains disabled by default.**

**Approved scope:** Change Set A preserves current Google identity decisions and adds only closed New Connection return navigation. Change Set B remains separate and unimplemented. Approval of the experimental Google policy is **not** a prerequisite for Change Set A.

**Recommendation:** ready for review of the navigation-only package below; **not ready for automatic localhost deployment**. Resolve the Apache CSRF status mismatch, scoped HTTP-helper configuration and claim-store operations contract first. No actual controller wiring or claim-store initialization is authorized or performed by this report.

## 1. Localhost architecture findings

Reviewed 06E–06I specifications/evidence and current entry points, controllers, views, JavaScript, session/middleware, feature flag, application URL helper and Apache configuration. Credential configuration was neither imported nor copied. Google callback configuration was inspected for its nonsecret URL only.

| Area | Current finding / consequence |
| --- | --- |
| Existing Apache | Apache 2.4.58 Win64, PHP 8.2.12 module; port 80/443 listeners were already running and remain unchanged. `DocumentRoot` is `C:/xampp/htdocs`, `ServerName localhost:80`, directory overrides allowed. No active custom virtual-host mapping was found in the inspected vhosts file |
| Portal path | Files live in `/mportalv2/`; root `.htaccess` contains local testing flags and artifact-denial rules, not a front-controller rewrite. Do not copy its local account-link testing flags into any deployment package |
| Application URLs | `config/app.php` defaults a direct local request to `http://localhost/mportalv2`; `APP_URL` is process configuration, not dotenv. `app_url()` retains the subdirectory. Configure that canonical base explicitly for future wiring, never from browser Host/return parameters |
| Google callback | Current configured nonsecret callback is `http://localhost/mportalv2/google-callback.php`. Controllers use the configured constant; they do not build it from `app_url()` or Host. Leave client settings, scopes, prompt, callback and provider behavior unchanged in Change Set A |
| Sessions | Strict mode, cookie-only, HttpOnly, SameSite Lax; Secure follows actual webserver HTTPS, not forwarded input. Idle 1,800s, absolute 43,200s. Authentication regenerates ID and replaces the entire pre-auth session |
| Return helper | Closed destination `new_connection.home`, 600-second absolute hint, external one-time claim callback. Ordinary target is relative `dashboard.php`; module/expired targets retain canonical base path. HTTP currently allowed only for loopback canonical hosts with exact environment `isolated-auth-test` |
| Important helper deployment gap | Ordinary `APP_ENV=development` is not sufficient to use this helper over HTTP. Do not silently impersonate the synthetic environment in the actual portal. A narrowly scoped localhost-factory/helper contract needs explicit implementation approval (section 5) |
| Module entry | Feature check precedes session/DB/bootstrap; disabled returns 404. Enabled anonymous requests currently redirect to relative `index.php` without creating return intent. Middleware reloads active/verified portal user and never requires linked electric accounts |
| Identity boundaries | Actual eligibility middleware and `portalActiveUser()` qualify `mco_portal.users`; pointing PDO at a disposable DB alone does not isolate them. Fixtures rewrite only their temporary copies. Production navigation does not need a new identity repository or schema redesign |
| Dashboard | Includes account/eBills/legacy side effects; never use it to initialize module authentication or obtain a user. Successful ordinary authentication can redirect there without executing it during tests |
| UI propagation gaps | Login form action is current URL, but Google click handler hardcodes `google-login.php`; ordinary registration/forgot links carry no hint. Registration posts to `register.php`; OTP form needs its own validated hint field. Login reset-success JS clears the query string, so selected flow must already be server-rendered into fields/links before cleanup |
| Logout | Existing confirmation POST uses `dashboard_csrf`, destroys session/cookie and supports JSON or relative redirect. Preserve this behavior; do not add automatic GET logout or transfer a hint through logout/reset confirmation |

Before/after SHA-256 manifests of every `app`, `config` and `database/migrations` file match. This includes dashboard/profile/account/eBills and all actual auth code. Existing unrelated workspace modifications were preserved. Apache configuration files were read only, not edited or reloaded.

This task added only this report, `localhost-apache-readiness-test.php`, `localhost-auth-navigation-test.php`, `localhost-auth-navigation-browser.js` and their three result JSON files under `tests/new-connection/`. Regression runs refreshed the existing Google review and policy/claim result JSON files. No production files were added or modified.

## 2. Change Set A — implementation plan

### Closed flow and entry

Keep default-off feature and environment gates. On a permitted GET/HEAD New Connection entry that lacks authentication, start a fresh closed return hint **after** the feature/session checks and redirect to the fixed canonical login path with only `auth_flow=<opaque hint>`. Do not accept `return`, destination URLs, applicant data or browser workflow commands. Feature-disabled requests remain 404 before session/DB work.

Do not obtain a DB claim merely to begin a hint. On login/registration/forgot pages, validate a supplied hint against that session with `match()` before including it in server-rendered hidden fields and fixed local links. A forged/expired hint must never become ordinary navigation silently. A superseded tab cannot delete or adopt a newer hint. Ordinary no-flow navigation remains dashboard navigation even when a different tab has a pending module hint.

### Authentication completion

For password login, preserve current credential/status/verification/CSRF/rate-limit checks. After success, consume only the selected hint with `take()`, capture the narrow navigation result, call unchanged `portalAuthenticated()`, then map the result with `target()`. Never copy the entire previous session back after regeneration. No-flow success must not construct/connect to claim storage.

For Google initiation, add only an optional validated hint to server-side `oauth_pending`. On callback, retain the pending selected hint only after the **existing** one-use state validation succeeds. Use that retained context, never callback `auth_flow`/return parameters. Keep the whole current identity lookup, linking predicates, verification writes, account creation, conflict handling and authentication decision unchanged. At the same successful-authentication seam, consume the selected hint and retain the existing session-regeneration operation; ordinary success still redirects dashboard.

**Do not add a portal-verification prerequisite to the Google callback in this package.** A currently accepted bound-unverified Google user may obtain a portal session and then receive module 403, exactly as current eligibility requires. Existing auto-verification may make a user eligible under current rules; navigation must neither suppress nor manufacture that verification. Middleware remains the fresh module authority.

New claim infrastructure failure is a navigation failure: no module success, no automatic retry/submit, no fallback claim cache. Handle that new failure generically without rewriting unrelated existing Google error behavior. If claim acknowledgment is uncertain, require an explicit fresh flow; a committed retry cannot regain module return. A consumed hint controls navigation, not permission to collect applicant data.

### Registration, recovery, expiry and logout

- Registration/OTP retains a validated selected hint through both form posts; OTP success redirects to explicit login, never automatically authenticates. Preserve attempts, resend cooldown, verified-user creation and mail behavior. Resend need not consume or modify return state.
- Forgot-password associates a valid selected hint with the same server session only after recovery CSRF validation. Existing reset mail contains only its existing reset token, never the hint or an applicant reference. Reset completion returns to explicit login; a different browser/token cannot import navigation. Expired hint returns to ordinary sign-in without importing a stale context. No changes to reset token/password/verification logic.
- Session expiration clears old hints with the existing session clearing. Reenter the module to start a fresh flow; do not resurrect it from browser storage or replay a command after login.
- Logout/reset confirmation keeps the current POST/CSRF/dialog and destroys old hints with the session. Do not automatically carry a module hint through that deliberate logout. Applicants can explicitly reenter the module after authentication.
- Module entry continues to require active/verified user on every private request, zero linked accounts permitted; no new application forms, submit handlers or persistence routes.

## 3. Change Set B — excluded and unimplemented

Future Google security review may address stable-subject-first lookup, independent portal verification, explicit fresh linking proof, transactional/conditional linking, verification provenance and legacy-user recovery. **None belongs in this navigation package.** Do not import the 06H Google policy into actual controllers, add identity proof tables/fields, alter verification timestamps, change account enrollment or require fresh independent linking in Change Set A.

Preserved security concern: the current callback can fill portal verification on permitted unbound Gmail/hosted linking and creates verified Google-only users. Bound unverified users can receive a session even though module middleware rejects them. Current lookup is email-first, hosted-domain acceptance uses nonemptiness and existing linking is not transactional. These remain separate owner-reviewed concerns; this direction does not label them remediated or approve a new policy.

Previously auto-linked/Google-only/unverified/changed-email users may require recovery under a future stricter policy. No actual user inventory, account counts, identity changes or password repairs were performed. Do not let that future review silently alter current navigation compatibility.

## 4. Apache compatibility evidence

### Actual existing deployment, tightly limited request

The only HTTP request made to the existing portal during the new Apache test was `GET http://localhost/mportalv2/new-connection.php`. It returned **404 / Page unavailable**, confirming the disabled branch. Source inspection establishes that this branch precedes session/database/bootstrap. Actual portal login/registration/recovery URLs were **not requested**, because even a GET would import actual DB configuration. No real account or provider was used.

### Separate Apache fixture

`localhost-apache-readiness-test.php` launches a separate Apache process on a random loopback port, with its configuration/private resources outside `htdocs`. A public-only temporary directory and `/mportalv2/` alias expose copied template/controller wrappers. No private fixture is installed under the actual application; requesting its private path returns 404. No current Apache config/listener is changed. The instance is stopped and temporary resources removed afterward.

**23 assertions completed; two compatibility expectations failed.** Result JSON explicitly records `deployment_ready: false`, rather than reporting Apache readiness passed.

| Check | Apache evidence / limit |
| --- | --- |
| Login and registration | Actual copied templates render as HTML; existing relative registration link and CSRF field present |
| OTP | Presentation branch renders only; verification/mail journey tested separately under disposable PHP `-S`, not Apache |
| Root/subdirectory | Dedicated fixture root works; slash redirect retains `/mportalv2/`; relative CSS resolves. This is not proof of all existing port-80 redirects |
| PHP runtime | Probe confirms `apache2handler`, not PHP built-in server |
| Cookies/session | Strict mode, HTTP HttpOnly/Lax without Secure; actual helper regenerates ID and replaces pre-auth CSRF using a synthetic stand-in user. Probe is not password/database authentication |
| CSRF denial | Invalid probe token reaches rejection branch; **expected 419, wire status 500** |
| Logout | Actual copied controller requires POST, destroys synthetic session and redirects relatively to `index.php`; invalid token rejected with **expected 419, wire status 500** |
| Google initiation | Actual copied controller builds a double-provider redirect; fixture redirect constant retains `/mportalv2/google-callback.php`. Redirect not followed, no live provider call |
| Feature off | Actual copied module controller returns 404 without DB bootstrap; actual existing port-80 gate also returns 404 |
| Private resources | Public alias cannot reach private bootstrap; no DB connection or provider/mail delivery in this Apache suite |

**Compatibility blocker A0:** PHP's `http_response_code(419)` does not produce the expected status under this tested Apache module configuration. CSRF checks still deny the action, but callers see 500. The fixture records the mismatch without weakening the predicate or modifying actual controllers. Obtain owner approval for a small, separately reversible HTTP-status compatibility fix—prefer a standard denial status such as 403 with the same message/CSRF checks, or an explicitly tested supported custom-status mechanism. Apply consistently to relevant authentication handlers and adjust tests only for the approved wire status; do not mask security failures as success.

No real Google callback, provider configuration acceptance, existing-Apache DB-auth journey, HTTPS Secure cookie inspection or visual logout/reset-dialog acceptance was performed. CLI/server-TLS and JSON Chrome evidence below do not fill those gaps.

## 5. Minimal localhost SQL claim-store deployment contract

This is a proposed contract, not initialized infrastructure or executable deployment SQL. Existing migration guards remain untouched.

| Item | Required contract |
| --- | --- |
| Ownership | Portal authentication infrastructure owner; dedicated localhost schema, e.g. **proposed** `portal_auth_return_local`, separate from `mco_portal` and operational schemas. Owner approves final name/backup scope |
| Tables | Proposed `auth_return_claims(digest CHAR(64) ASCII binary PRIMARY KEY, expires BIGINT UTC NOT NULL, INDEX(expires))`; `auth_return_claim_control(id singleton PRIMARY KEY, generation CHAR(32), ready BOOLEAN, used quota count, last_clock BIGINT UTC, not_before BIGINT UTC)`. InnoDB, appropriate checks/strict SQL. No applicant/user/session/token/URL fields or user FK |
| Runtime principal | SELECT/INSERT/DELETE on claims, SELECT/UPDATE on singleton control only; no DDL, user-table, mail or operational privileges. Cleanup uses expired-only bounded deletes. Administrative initialization/recovery principal separate |
| Configuration | Separate server-only credentials; never reuse/copy embedded portal credentials or fall back to its connection on failure. Adapter validates expected dedicated schema, authoritative writer and UTC/strict-mode configuration. No root account in deployed app |
| Initialization | DBA first verifies MariaDB compatibility, creates reviewed dedicated schema/tables with admin principal, seeds unique generation/readiness **closed**, validates constraints/privileges/rollback and synthetic atomic claims, verifies backups and external gate, then explicitly opens readiness. No module migrations or actual initialization in 06J |
| Lazy connection | Ordinary no-flow login never invokes adapter factory/readiness/SQL initialization. Identity DB may still be required for ordinary login; claim-store outage must not become identity-DB outage through shared bootstrap |
| Atomic semantics | Own transaction; lock control; trusted DB UTC and bounded skew/regression checks; readiness/generation/count integrity; bounded expired cleanup; duplicate check before capacity; unique insert/quota update; acknowledged durable commit only returns true. Duplicate/expired false; errors/uncertain commit throw. No upsert/TTL refresh/live eviction |
| Lifetime | Existing helper 600-second absolute expiry, no renewal on retry. Backend independently enforces expiry, approved skew bounds and quota. Capacity/skew/cleanup values from 06H/06I are test values, not approved operational defaults |
| Failure | Generic navigation failure, no automatic replay and no dashboard substitution for a selected failed context; explicit fresh-flow UX. No filesystem/cache/replica fallback. Keep ordinary authentication independent |
| Durability | Single authoritative durable writer, flush-on-commit policy and clock health. Least-privilege tests, backup encryption/access and monitoring required; test commit visibility is not power-loss certification |
| External readiness | Separate trusted control outside restored claim data. Serialize gate closure with in-flight consumers; gate acknowledgment means old consumers drained before restore. SQL readiness alone cannot detect coherent old snapshots |
| Recovery | Close gate, stop/drain consumers, restore/test. Missing tombstone plus matching older quota is otherwise undetectable. Invalidate **all** old hint-bearing sessions/restore copies or wait full maximum lifetime plus approved skew from a trusted closure point before reopening with fresh generation. Generation rotation alone does not invalidate existing helper hints |
| Cleanup/monitoring | Bounded expired rows, quota reconciliation fails closed; never delete live tombstones to free capacity. Aggregate outage/capacity/clock/cleanup metrics; no raw flow, digest, session, OAuth code/token or user data in ordinary diagnostics |
| Rollback | Close module navigation/feature gate; restore earlier navigation files as one reviewed set; ordinary auth remains available. Retain claim tombstones until every old hint is invalidated or expired. Do not restore an older claim snapshot and reopen without quarantine. Remove dedicated schema/grants only after drain/backup verification; no portal-user rollback or schema changes |

### Local HTTP helper scope

Propose one small approved helper/factory adjustment: add an explicit `localhost-auth` development scope alongside the existing synthetic scope. The pure helper still requires a loopback canonical host for HTTP; its server-only factory must also require direct loopback server/client addresses, an explicit nonproduction environment and navigation opt-in. No client header may select the scope; never trust forwarded headers or Host. Nonlocal/HTTPS rules stay unchanged. Tests must reject HTTP for nonlocal hosts, nonloopback requests, production scope and malformed bases, and retain `/mportalv2`.

This is a proposed source change, **not implemented**. Do not repurpose `isolated-auth-test` as a deployment shortcut, remove the 06H disposable guards or deploy its test-only class directly. Implement a separately reviewed production adapter using the tested claim contract; no Google-policy classes or fixture proof issuer belong in it.

## 6. Exact proposed file changes and rollback

The following is the complete intended production touch list for Change Set A. Optional A0 status compatibility is identified separately; no file is changed by this package. Rows distinguish newly added files from modifications. Rollback restores captured pre-change bytes, not Git HEAD, because this workspace already contains other work.

| File | Current → proposed navigation-only change | Security/dependencies | Required regression | Rollback |
| --- | --- | --- | --- | --- |
| **Add** `app/services/auth-return-navigation.php` | No factory → lazy closed helper/config/SQL callback boundary, validated selected-flow view model and fixed URLs | Trusted local scope, no browser destinations/commands, no eager claim DB | Ordinary outage, bad bases, stale tabs, generic errors | Remove imports then added file |
| **Add** `app/services/auth-return-claims.php` | No deployable adapter → dedicated SQL claim contract above | No test imports/Google policy; external gate, bounded quota/clock/commit | Independent processes, outage, uncertainty, restore, privilege checks | Close gate; remove adapter after dependents, retain tombstones |
| **Add** `config/auth-return.php` | No runtime claim contract → fail-closed server configuration for base, environment, dedicated storage, readiness/capacity | No credentials in source/output; ordinary path must not load it | Missing/bad config; no-flow independence | Remove dependent imports, revoke unused grants after drain |
| `app/helpers/auth-return-intent.php` | Current synthetic-only HTTP exception → approved narrowly scoped localhost contract, otherwise same destinations/TTL/claim semantics | Requires factory loopback attestation; no broad HTTP bypass | Existing 54 tests plus localhost scope/base-path negatives | Restore exact helper bytes after disabling scope |
| `app/controllers/new-connection.php` | Anonymous redirect loses context → begin closed hint after gate/session, redirect fixed login | No write/claim just to begin; keep lazy identity/middleware and 403; no editor endpoints | Off 404 before DB; direct access, expiry, zero accounts | Restore entry redirect; remain default off |
| `app/controllers/index.php` | Successful password redirects dashboard → capture selected result then existing regeneration and fixed target | No change password/status/verification/limiter; selected failure handled generically | Password, CSRF, ordinary/module, outage, real regeneration race | Restore narrow success/redirect hook; A0 status separately reversible |
| `app/views/pages/index.php` | Unqualified links/form fields → escaped server-validated opaque hidden hint, fixed Google/register/forgot links | Never raw return URL; no account/applicant details | Real form POST/link clicks, reset-success cleanup, stale tab | Restore template; ordinary links retained |
| `assets/js/login.js` | Hardcoded Google route and reset query cleanup → use fixed server-rendered Google link; flow already in validated hidden fields before cleanup | Do not copy arbitrary query values into destination; preserve ordinary UX | Browser Google click/query cleanup/no-flow navigation | Restore script with template/controller bundle |
| `app/controllers/register.php` | OTP success returns plain login → validated selected flow on explicit-login redirect | Preserve all registration/OTP/mail behavior; no auto-login | Real registration/OTP/resend/expiry, explicit login | Restore success redirect/context preparation |
| `app/views/pages/register.php` | Registration/OTP POST can lose flow → escaped validated hidden hint in both forms and fixed login link | No applicant payload in hint; resend still cannot claim | Form POST, OTP UI, resend cooldown and stale context | Restore template; no user data/schema changes |
| `app/controllers/google-login.php` | Pending OAuth state only → optional validated navigation hint alongside existing state | Do not change random state, TTL, SDK config, linking/enrollment | State one-use/expiry, ordinary dashboard, callback forged hint | Remove only navigation metadata addition |
| `app/controllers/google-callback.php` | Existing successful Google auth/dashboard → capture state-bound optional navigation result around existing regeneration | **Leave identity SQL/conditions/verification/conflicts unchanged**; callback URL cannot select hint | Current branch review, SQL navigation, ordinary Google, invalid state, module eligibility, outage | Restore only state-context/success navigation hooks; no account repair |
| `app/controllers/forgot-password.php` | Recovery with no selected context → same-session association after valid recovery CSRF | Reset mail unchanged; no token destination/applicant state | Request genericness, email sink, no flow in reset mail | Remove association; do not change reset records |
| `app/views/pages/forgot-password.php` | Login link/form loses context → validated hint field and fixed selected-login link | Only same-session match; no account existence leak | Form/error/success and expired hint | Restore template |
| `app/controllers/reset-password.php` | Success plain login → explicit selected-login redirect only if session-associated hint remains valid | No authentication/verification/token semantics changes | One-use token, cross-browser reset, stale hint, CSRF | Restore success redirect; no password reversal |
| `app/views/pages/reset-password.php` | Unqualified login/forgot links → server-validated selected links for same-session association | Never import flow from reset token/query/other browser | Valid/invalid/expired/success screens and fixed links | Restore template |

**Inspected, no navigation source modification required:** root entry wrappers, `config/app.php`, `config/google.php`, database/mailer configuration, `app/helpers/session-security.php`, actual auth/module middleware, `app/controllers/logout.php`, `app/views/partials/session-action-dialogs.php`, `assets/js/session-actions.js`, registration/forgot/reset JavaScript, password-recovery service, dashboard/profile/account/eBills and existing module migrations. Existing session replacement/logout destroys hints; preserve it. Actual frontend tests must confirm no overlooked propagation dependency before finalizing a later implementation diff.

**A0 separately proposed affected files:** CSRF status branches in `app/controllers/index.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `logout.php`, plus corresponding auth/session tests (and any additional auth endpoint found by a bounded 419 search). Do not mix this wire-status-only compatibility decision with Google identity logic. Preserve all token predicates/messages and confirmation UI. First reproduce and approve the mapping, then independently test/revert that small patch. No A0 change was applied.

## 7. Fresh test results

All PHP commands use `C:/xampp/php/php.exe`. New evidence files: `localhost-auth-navigation-results.json`, `localhost-auth-navigation-browser-results.json`, `localhost-apache-readiness-results.json`.

| Suite | Result and scope |
| --- | --- |
| `localhost-auth-navigation-test.php` | **PASS 247**; complete reused 06F copied-controller journeys with SQL-backed 06H claims and unchanged current Google identity body. No experimental Google policy method called; only its claim class loaded. One disposable schema removed |
| Navigation Chrome | **PASS 6**, Chrome 155.0.8059.85; JSON fixture access, private no-store, local HTTP cookie, no browser storage/external requests/runtime exceptions; not visual acceptance |
| `localhost-apache-readiness-test.php` | **23 assertions completed, TWO compatibility failures**, both expected CSRF 419 observed 500. Deployment readiness false; zero DB/provider/mail connections; separate resources removed |
| `auth-security-test.php` | PASS 42; password/CSRF/OAuth/session, isolated HTTP and server-TLS fixtures |
| `tests/password-reset-test.php` | PASS; PDO/mail doubles and real isolated limiter |
| `tests/session-actions-http-test.php` | PASS 22; logout/reset action compatibility, not fresh visual-dialog acceptance |
| `auth-return-intent-test.php` | PASS 54 |
| `google-verification-review-test.php` | PASS 206; 36 current callback branches via immutable SQL spy, separate proposed model |
| `google-policy-claim-test.php` | PASS 346; disposable SQL claim failure/concurrency/recovery contracts plus separate experimental identity regression. Running this regression does not put its policy in Change Set A |
| `foundation-test.php` | PASS 41; feature off and active verified zero-account access |
| `database-foundation-test.php` | PASS 68; guarded disposable-only migration/constraint/concurrency checks |
| `tests/structure-test.php` | PASS 194 |
| `tests/paleco-readonly-contract.php` | PASS |
| Syntax | PASS on both new PHP harnesses and JavaScript wrapper |
| Protected sources | SHA-256 manifests match; no actual app/config/migration changes |

Current SQL navigation fixture deliberately does not execute unbound Gmail/hosted verification-write branches; fresh 06G spy evidence covers their current SQL intent, and 06I's earlier integrated synthetic execution remains historical evidence. Do not equate these scopes with actual existing-Apache account acceptance. No real credentials/customer records, actual portal migration, operational data modification, provider/email request or applicant collection occurred.

## 8. Backup, rollback and remaining approvals

Before any later approved change, capture protected file hashes and a recoverable snapshot of the **current working versions**, configuration provenance and owner-approved deployment settings. Store credentials separately with restricted access. A navigation-only release does not need a portal-user schema migration or a restore of user/verification/password data. Never roll back legitimate user registrations/password resets as a navigation rollback.

Before separate claim initialization, verify dedicated-store backup and restoration using disposable synthetic data; close external readiness, drain/invalidate old hints and prove replay denial while retained old contexts are still live. Test rollback after interrupted initialization, quota integrity, least privileges, clock/outage/capacity and ordinary login independence. Do not use an older SQL backup as an unguarded reset of consumed claims.

Remaining decisions **for Change Set A**:

- Approve precise localhost HTTP scope/base, fixed expired-context/error UX, 600s lifetime and subdirectory navigation patch.
- Resolve A0 Apache status behavior and test actual rendered auth links/POSTs against disposable identities on a separate Apache deployment; current live DB-backed routes remain untested.
- Assign claim DBA/operations owner, dedicated schema/grants, capacity/skew/durability and trusted readiness/recovery authority. Local rehearsal file locks do not establish distributed recovery semantics.
- Approve the exact file bundle and server configuration; real Google callback console validation/live test requires a later explicitly authorized synthetic staging exercise, not this task.

**Not prerequisites for navigation:** approval/adoption of stricter Google policy, verification timestamp changes, explicit linking/recovery redesign or stable-subject repository rollout. Those remain Change Set B. All applicant-collection/privacy/form/catalog approvals also remain separate; this package does not enable collection.

## 9. Recommended next task and go/no-go

**Task 06K — Navigation-only Apache Fixture Acceptance and Approved Adapter Preparation.** Preserve current Google behavior. First resolve the scoped HTTP contract and owner-selected CSRF wire status in temporary copies, run actual rendered login/register/OTP/recovery/link-click journeys on isolated Apache with disposable users and local doubles, and finalize dedicated claim initialization/readiness/restore/rollback evidence. Keep actual portal controllers and feature default unchanged until a separate explicit wiring approval.

**GO** for this narrowly scoped preparation and review. **NO-GO** for automatic actual-controller integration, claim-table creation in `mco_portal`, enabling New Connection or applicant collection. The unresolved blockers are concrete localhost/deployment issues; no experimental Google policy approval is being required or implied.

Stop after this readiness package. No production migration override or live endpoint is included.
