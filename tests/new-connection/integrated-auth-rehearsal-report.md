# Task 06I — Integrated Authentication Rehearsal on XAMPP

Date: **2026-10-21, Asia/Manila**. **GO for continued isolated localhost rehearsal; NO-GO for actual controller wiring or applicant collection.** The stricter Google policy remains experimental and unapproved.

The copied authentication system successfully combines the 06F closed return helper, 06H SQL claims, proposed Google policy, actual session regeneration and one disposable identity store. Final acceptance: **426 integrated assertions and six Chrome checks passed**, with applicable regression suites passing separately. No actual portal controller/configuration/migration was changed; New Connection remains disabled by default.

## 1. Files created or modified

Created under `tests/new-connection/`:

- `integrated-auth-rehearsal-test.php`: checked in-memory transformations of the existing 06F coordinator, then execution of its complete journeys plus the new integration cases. Does not edit that coordinator or actual source files.
- `integrated-auth-rehearsal-support.php`: disposable-schema setup, temporary-controller transformations, integrated journeys, HTTP clients and external recovery-gate operator functions.
- `integrated-auth-rehearsal-http.php`: private copied-server glue for SQL claims, current-user revalidation, experimental Google policy and synthetic explicit linking.
- `integrated-auth-rehearsal-client.php`: independent loopback HTTP login client.
- `integrated-auth-rehearsal-gate-worker.php`: independent recovery-gate writer.
- `integrated-auth-rehearsal-browser.js`: reuses the existing 06F Chrome journey with a separate result filename.
- `integrated-auth-rehearsal-results.json`, `integrated-auth-rehearsal-browser-results.json`, `integrated-auth-rehearsal-regression-results.json`: safe result labels and limits.
- This report.

Refreshed by regressions: `google-policy-claim-results.json` and `google-verification-review-results.json`. Older reports remain historical snapshots. No dependencies added. Existing 06F/06H implementation files were reused without modification.

A before/after SHA-256 file-manifest comparison covered **all files in `app`, `config` and `database/migrations`** and matched. This includes dashboard, profile, linked-account/eBills code, real authentication controllers, session/middleware helpers, feature configuration and migration guards. Pre-existing workspace changes were preserved. The temporary manifest was removed after verification.

## 2. Integrated authentication architecture

### Environment and isolation

- Windows localhost, XAMPP PHP **8.2.12**, local MariaDB **10.4.32**, headless Chrome **155.0.8059.85**.
- Two independent PHP `-S` loopback servers run with the XAMPP PHP binary. This is a local XAMPP-runtime rehearsal, **not an Apache virtual-host/rewrite/HTTPS acceptance test**.
- Each integrated run creates one random `mco_nc_test_<16 hex>` schema and a private random temporary tree outside the web project. Controller/session/helper copies, local provider/mail doubles, sessions, rate-limit files and configuration live there. Cleanup removes the schema and temporary tree in `finally`.
- No real DB/mail/Google credential configuration is copied. Test environment propagation is restricted to required OS and disposable-test connection variables. SQL uses the existing local-only schema guard, native prepares, strict SQL mode and UTC.
- The HTTP router only accepts configured loopback hosts/addresses and an allowlist of fixture paths; private files and dashboard bootstrap return 404. It sends private no-store responses and restrictive CSP. Runtime error display/logging is disabled; public infrastructure failures are generic.

The reused coordinator's metadata signature checks nonfixture schema structure before/after; it does not read customer rows or select the actual portal database. The new policy and claim operations use guarded disposable connections. The separate database-foundation regression executes migrations **only** against its own disposable schema with existing guards intact.

### One authoritative synthetic identity store

Copied password, registration, recovery and current Google controllers use fixture `users`, `email_verifications` and `password_resets`. The unchanged 06H policy contract addresses an **updatable `synthetic_users` view of that same `users` table**, mapping `google_id` to `google_subject`. There is no identity mirror or post-auth copy synchronization.

Only fixture users gain the policy's version/provenance/password-availability fields and case-sensitive Google subject uniqueness. Link proofs reference the actual fixture users. The 06H fixture identity mutex, proof/audit and SQL claim/control tables are reused. These DDL changes are test setup, not deployable portal migrations or migration overrides.

The temporary module middleware differs only in replacing its hardcoded actual schema qualification with fixture `users`. It checks current active/verified status and has no electric-account requirement. No real New Connection endpoint or applicant-data service is exposed.

### Temporary controller hooks

Actual controller source is copied, then narrow checked hooks are inserted for destination capture/redirect, registration-to-login navigation, recovery association and OAuth pending context. Session regeneration remains the existing `portalAuthenticated()` implementation.

An experimental branch in the **temporary** Google callback runs after the existing OAuth state, provider exchange double and strict Google identity validation. It calls the 06H policy, reloads the authoritative user and passes through return capture/session regeneration. The original callback branch remains separately selectable by private operator control, allowing current behavior to be tested without labeling it the proposed policy. Callback URL parameters cannot choose the policy, actor or link proof.

At return capture, fixture glue reloads current account status; password and experimental Google journeys also require portal verification before session issuance. The current Google branch retains its existing verification behavior for comparison. Current module access always rechecks active/verified status.

The return helper creates a **closed navigation hint**, not an authentication credential, workflow command or module permission. Both valid concurrent password requests can authenticate the same user; only one can consume the module-return hint. Authorized module access still depends on fresh identity checks.

## 3. Complete journey acceptance

Command: `C:/xampp/php/php.exe tests/new-connection/integrated-auth-rehearsal-test.php`

The 426 assertions include repeated private-response checks and inherited negative cases; they are not 426 distinct journeys.

| Journey | Observed result |
| --- | --- |
| Password login to New Connection | Closed hint consumed via SQL; session ID regenerated; fixed local module destination |
| Ordinary password login | Dashboard destination preserved; no claim row or claim-adapter call |
| Registration/OTP then login | Actual copied structured validation, OTP attempt/expiry ceiling and resend cooldown operate against synthetic DB; only successful OTP creates verified user; registration does not sign in; explicit password login consumes selected return hint |
| Existing verified Google account | Actual copied OAuth state logic plus local exchange/userinfo double, 06H experimental policy, SQL claim and regenerated session complete module return |
| Explicit Google linking | Independent portal password authentication, fresh password confirmation and CSRF precede state-bound synthetic proof; conditional link/proof consumption/audit transaction commits; module return follows |
| Invalid linking proof prerequisites | Wrong password or invalid CSRF rejected; actor/subject/email/proof are never accepted from browser fields |
| Unverified/inactive identities | Password denies generically; proposed Google denies before session issuance; module gate rejects current-policy unverified session |
| Forgot/reset then login | Local mail sink receives reset only; recovery CSRF and one-use token honored; no implicit login; explicit login follows reset; other browser cannot import return hint |
| Expired session then reauthentication | Actual idle-expiry handling denies module, then explicit login with a fresh hint succeeds |
| Logout | Existing POST/token namespace preserved; logout destroys authentication, pending return hint and command state |
| Zero linked electric accounts | Password, OTP-registered and proposed Google users reach fixture module without account tables |
| Invalid/expired/reused OAuth state | Rejected before provider double exchange; callback query cannot supply return destination |
| Expired/superseded/stale return hint | Fixed expired-context result, no arbitrary URL or silent dashboard fallback |
| Unsaved command replay | Pre-auth personal/command state cleared by unchanged session replacement; no application command handler exists |

The explicit-link proof is deliberately synthetic: the fixed local provider double supplies server-controlled identity evidence for proof issuance, and callback validates its actual pending state/exchange double before using that proof. This does not prove a production provider verification/reauthentication protocol. Real linking needs a reviewed two-phase challenge bound to genuinely validated provider evidence, rather than accepting synthetic booleans or reading fixture provider files.

Chrome performs six JSON-fixture acceptance checks: authenticated zero-account module access, private no-store, empty local/session storage, local HTTP HttpOnly/Lax cookie, zero external requests and zero runtime exceptions. It does **not** assert visual layouts, real confirmation-dialog rendering or production Secure cookies. No screenshot acceptance is claimed.

## 4. Concurrency, failures and recovery

### Actual session-regeneration race

Two independent HTTP clients send the same flow, credentials and original session to two independent PHP servers. A private checkpoint holds the first request after password validation while it owns the session lock; the second arrives at session initialization before the first is released.

Both requests then reach actual session regeneration and return HTTP 302. Exactly one location is the module; the other is the fixed expired-context location. MariaDB contains **one** digest for that flow. This closes 06H's remaining gap: the SQL adapter is now exercised with the real concurrent session-regeneration behavior, rather than only a manually copied session array. Private pending state/commands are not preserved across authentication.

### Failure and status behavior

| Case | Result and limits |
| --- | --- |
| Claim outage | Fixture kills only the claim adapter's own MariaDB connection; password and proposed Google module returns fail generically before new session issuance |
| Ordinary login during claim outage | Password and proposed Google ordinary login still return dashboard and create no claim rows; identity DB remains available |
| Unknown commit outcome | Hook throws immediately after actual SQL commit; HTTP 503, no new authenticated session; retry cannot repeat module return; explicitly started fresh flow succeeds |
| Status change after password lookup | Separate DB connection revokes status while request is paused; fresh check rejects before session creation |
| Expired/reused/stale hints | Existing helper/HTTP cases pass; no URL/command fallback |
| Database constraints/parallel linking | Separate 06H suite passes unique-subject, concurrent same/different-account linking, rollback and status-change tests |

The unknown outcome is **post-commit acknowledgment fault injection**, not a real network partition during COMMIT. Status is freshly rechecked before return consumption/session creation, but the identity decision, claim commit and session write are not one transaction. Revocation after that check remains possible; current-user middleware must reject subsequent access. A claim failure after an explicitly authorized link can leave the binding committed while module return fails; these separate operations must not be presented as an all-or-nothing distributed transaction.

### Externally controlled recovery

The rehearsal's external control file is **outside the SQL schema**, so restoring SQL does not restore readiness. Claim consumers hold a shared file lock through SQL commit; operator changes acquire an exclusive lock. Tests demonstrate the operator writer blocks behind an in-flight claim, closes only after it finishes, and blocks all subsequent claims once closure is acknowledged. A claim already in flight before closure may complete normally; restoration must wait for acknowledged closure.

Recovery scenario:

1. Snapshot claim rows/control before consuming a fresh hint, retain a synthetic old session and successfully consume the hint.
2. Close external readiness and restore the older, internally consistent SQL snapshot—losing that consumed digest and restoring its old quota consistently.
3. Confirm the original hint is **still within its lifetime**. Attempt from the retained old session receives HTTP 503 without new authentication: external quarantine prevents replay even though SQL row-count checking alone cannot detect the loss.
4. Premature readiness with a future external recovery deadline still denies claims.
5. Instead of waiting 602 seconds, close readiness, invalidate **all** fixture session files while requests are quiescent, attest a fresh generation and reopen. The old session now fails CSRF/strict-session recovery; a manually initiated new flow succeeds.

This is an actual local SQL-history restoration and actual fixture-session invalidation, not a claim that generation rotation alone invalidates old hints. The full drain interval was **not** elapsed. Restoring both external state and SQL coherently, skipping operator quarantine, undiscovered failover history loss, backup/session restoration, multi-host locking and power-loss durability remain outside this rehearsal. No database can detect all coherent rollback of its own history. A production external readiness authority must survive independent restore, serialize gate closure with consumers across all workers/hosts, and enforce reliable global hint invalidation or a full approved lifetime/skew drain.

## 5. Current versus proposed Google policy

Both branches run in isolated copies; verification changes below affect **only synthetic rows**.

| Synthetic situation | Current copied callback | Experimental 06H branch |
| --- | --- | --- |
| Bound active verified, matching subject/email | Authenticates | Authenticates after policy/current-user checks |
| Bound active unverified | Creates ordinary authenticated session; module denies | Denies before session issuance, preserves NULL verification |
| Existing unbound, unverified hosted identity | Automatically binds and fills verification timestamp | Requires portal verification; does not bind or fill timestamp |
| New provider-verified identity | Creates active verified Google-only user with unknown random password/hash | Requires separate portal enrollment; creates no user |
| Conflicting subject/email | Existing protections reject | Stable-subject-first resolution rejects conflict without reassignment |
| Provider email changed for existing subject | Existing email-first lookup can miss original owner (covered by 06G separate review) | Integrated experimental test locates subject owner and requires email review |
| Explicit linking | No approved explicit link protocol in current controller | Synthetic independent proof authorizes transactional conditional binding |

The tests establish no real account counts. Unverified bound users, previously automatic-link users, new Google-only applicants and changed-email users can lose their current sign-in path under stricter policy. A nonempty password hash does not prove a user-known password: the current callback generates one randomly. Previously verified auto-linked accounts are not mass-reset or reclassified by this experiment. Approve verification provenance, explicit enrollment/linking and recovery/support processes before adoption; existing registration OTP does not itself recover an already-created unverified account.

## 6. Regression and isolation results

All commands used local XAMPP PHP and synthetic/disposable fixtures. Final evidence is in the three integrated result JSON files.

| Suite | Result |
| --- | --- |
| Integrated copied-controller HTTP rehearsal | PASS, 426 assertions; complete inherited 06F journeys plus SQL/policy/concurrency/recovery cases |
| Integrated Chrome fixture | PASS, 6; no external requests/storage/runtime exceptions |
| `auth-security-test.php` | PASS, 42; password/CSRF/OAuth/session and server-TLS fixtures |
| `tests/password-reset-test.php` | PASS; PDO/mail doubles and real isolated limiter |
| `tests/session-actions-http-test.php` | PASS, 22 |
| `auth-return-intent-test.php` | PASS, 54 |
| `google-verification-review-test.php` | PASS, 206; 36 current callback cases and separate proposed model |
| `google-policy-claim-test.php` | PASS, 346; independent real MariaDB policy/claim tests |
| `foundation-test.php` | PASS, 41; feature off and verified zero-account eligibility |
| `database-foundation-test.php` | PASS, 68; guarded disposable migrations, constraints, triggers and concurrency |
| `tests/structure-test.php` | PASS, 194 |
| `tests/paleco-readonly-contract.php` | PASS |
| Syntax | PASS on five new PHP files and the JavaScript wrapper |
| Protected-source manifests | PASS; actual app/config/migration bytes unchanged |
| Cleanup | PASS; disposable schema and temporary servers/config/session/provider/mail artifacts removed |

No rate limiter was disabled or reset to pass a journey; separate synthetic recovery/gate identities keep independent scenarios within the existing per-account limits. No CSRF, state or migration guard was bypassed. Production dashboard/profile/account/eBills source integrity was checked; their real business actions were **not executed**, avoiding synchronization/email/operational effects. Actual Apache HTTPS, Google SDK/provider communication, SMTP, visual dialogs and full applicant workflows remain untested here.

## 7. Remaining technical and policy approvals

- Authentication/security owners: proposed Google verification separation, stable-subject/email-change rules, enrollment, explicit linking, anti-takeover conflict handling and legacy-user recovery.
- Provider boundary: approved validation and fresh independent reauthentication protocol, proof TTL, one-time consumption and session-binding behavior; no laboratory flags/provider files in deployment.
- Identity integration: approved current-user resolver, removal of hardcoded actual-schema assumptions from any future isolated staging implementation, revocation checks and safe bootstrap errors.
- Claim infrastructure: dedicated least-privilege principal/schema, durable authoritative writer, capacity, UTC/skew policy, retention/cleanup and aggregate safe monitoring. Fixture SQL DDL is not a deployment migration.
- Recovery: independent trusted readiness authority, shared lock/serialization equivalent across deployment workers, externally attested restoration, full global invalidation or real lifetime drain. Local file locking is not a distributed deployment solution.
- HTTP/session deployment: actual Apache routing/base path, trusted HTTPS/proxy configuration, Secure cookie inspection, shared rate limiting and owner-approved errors/reauthentication UX.
- Retain all New Connection policy/publication/privacy/catalog/form gates. This task approves none of them and enables no collection.

## 8. Exact production-controller proposal for owner review

**This is a proposed change map, not authorization or implemented production behavior.**

| Proposed file/boundary | Exact intended change after approval |
| --- | --- |
| Dedicated auth return service/factory and private server configuration | Instantiate claim storage lazily only when a validated module flow is consumed; approved canonical base, feature/environment gates, least-privilege connection and external readiness contract; no fixture imports |
| `app/controllers/index.php` | After credential/status/verification checks, refresh current user; consume only the closed server-session hint; capture narrow result, regenerate session using existing helper, redirect via fixed helper target. Preserve no-flow dashboard behavior, CSRF and limiter |
| `app/controllers/register.php` | Preserve registration/OTP and explicit subsequent login; retain only valid bounded selected navigation context on success; no automatic sign-in or applicant state |
| `app/controllers/google-login.php` | Bind optional validated return hint and approved explicit-link context to server-side OAuth pending state; do not accept arbitrary return URLs/actor IDs |
| `app/controllers/google-callback.php` | Preserve one-use OAuth state before exchange; validate provider evidence; use approved stable-subject policy/repository and explicit link proof; never implicitly set portal verification under the proposed policy; reload current identity, consume selected hint, regenerate session and use fixed target. Make infrastructure failure responses generic and appropriately non-200 |
| Forgot/reset controllers and recovery service | Preserve token/CSRF/limiter behavior; same-browser bounded navigation association only; reset does not authenticate, verify portal email or import a destination from mail/token/browser fields |
| Logout/session-expiry handling | Clear pending return/link/recovery context; preserve POST confirmation, token namespace and regeneration; require explicit new flow after expiry/failure; never replay submitted application commands |
| New Connection entry/current-user middleware | Keep feature disabled by default; when separately approved, use dedicated active/verified resolver without linked-electric-account requirement, closed sign-in navigation and private no-store |
| Schema/deployment boundary | Propose separate reviewed identity-proof/claim storage DDL and rollback/recovery plan; do not modify existing guarded module migrations or add a live override |

Do not change `portalAuthenticated()` to retain the whole pre-auth session. Keep ordinary login usable if claim storage alone is down. Google policy rollout must be separately approved from return-navigation wiring because it changes who can authenticate, not merely where a successful login goes.

## 9. Go/no-go recommendation

**GO:** continue controlled localhost/disposable integration and prepare owner review using this evidence. The components now work together, including the real session-regeneration race and externally quarantined SQL-history loss.

**NO-GO:** actual portal-controller wiring, actual schema migration, public applicant routes or real New Connection collection. Require recorded Google/recovery policy decisions, deployment-grade claim/readiness/proof infrastructure and actual Apache/HTTPS staging acceptance first.

Recommended next task: **06J — Owner-approved authentication change specification and deployment-adapter contract**, resolving the approvals above and defining production storage/recovery evidence before authorizing any actual controller edits. Stop here; no automatic live wiring follows this rehearsal.
