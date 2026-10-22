# Task 06F — Closed Authentication Return-Intent Helper and Synthetic Integration Tests

Date: **2026-10-21, Asia/Manila**. Status: isolated foundation implemented/tested; **not wired into live authentication**. Task 06E remains a proposed architecture, not a deployment approval. New Connection remains disabled by default. No actual `mco_portal` connection, operational query/write, production migration, live applicant route, customer identity, external email or Google request was used.

## 1. Files created or modified

Created:

- `app/helpers/auth-return-intent.php` — pure, closed `PortalAuthReturnIntent` navigation helper; no controller includes it in the actual application.
- `tests/new-connection/auth-return-intent-test.php` — pure negative, expiry, tab, gate, canonical URL and atomic-claim tests.
- `tests/new-connection/auth-return-integration-test.php` — temporary copied-controller integration against one disposable synthetic identity schema, two HTTP workers and private doubles.
- `tests/new-connection/auth-return-fixture.php` — private temporary router/bootstrap and file-locked claim adapter; not a production/staging route.
- `tests/new-connection/auth-return-google-double.php` — provider classes used only instead of Google SDK in the temporary tree.
- `tests/new-connection/auth-return-browser-test.js` — Chrome storage/network/session check for the JSON-only fixture.
- `tests/new-connection/auth-return-integration-results.json`, `auth-return-browser-results.json` — safe acceptance labels/counts, no query-string secrets or applicant data.
- This report.

Existing generated results/screenshots were refreshed by applicable regression suites. Their earlier reports remain historical snapshots; current generated timings/counts can differ by race branch or host load.

**No existing production source/configuration/migration was modified.** Aggregate SHA-256 content fingerprints of `app`, `config` and `database/migrations`, excluding the newly added helper, match the Task 06E fingerprint. This includes all auth controllers/session helpers, dashboard, profile/account/eBills code, feature configuration and migration bytes/checksums. No dependencies were added.

Inspected actual login/register/OAuth/forgot/reset/logout controllers, session/CSRF helpers, module middleware, `config/app.php`, auth/recovery services, Task 06E specification and existing isolation/regression harnesses. Credential-bearing real DB/Google/mail configuration was not imported or copied.

## 2. Helper contract

`PortalAuthReturnIntent` does not authenticate, authorize module eligibility, validate credentials, modify identity data, invoke a workflow, start a session, emit headers or perform I/O/logging. The caller owns secure server sessions and configuration. Its outcomes are navigation hints, not access capabilities.

| API | Contract |
| --- | --- |
| `__construct(canonicalBase, gates, atomicClaim = null)` | Validate trusted server canonical base. Require HTTPS except direct loopback HTTP with exact `environment=isolated-auth-test`. Reject userinfo, query/fragment, controls, backslashes, percent encoding and unsupported path syntax. Path can be empty or simple slash-separated ASCII segments. Gate enabled only when **both** server `environment_permitted` and `feature_enabled` are boolean `true`; missing/truthy strings fail closed. No request/Host/Forwarded value is read. |
| `begin(&$session, destination = new_connection.home, now = time())` | The only accepted destination is `new_connection.home`. Unknown enums/URLs/paths reject. If either gate is off, return null without creating/changing session intent. Otherwise replace the single active record with a cryptographically random 128-bit/32-hex handle and fixed 600-second lifetime. No sliding extension. |
| `match(session, flow, now)` | Null means ordinary authentication. A supplied malformed/forged/cross-session/expired/future-issued/mismatched handle or invalid stored shape means expired context. Valid record/handle yields closed module enum. No consumption/I/O. |
| `take(&$session, flow, now)` | Match and consume the selected record only when its stored handle matches. A stale tab cannot delete a newer tab's flow. A valid module result **requires** an atomic claim provider; missing provider throws, failed claim returns expired, provider outage propagates without fallback. Claim happens before the caller replaces the session. |
| `target(outcome)` | Ordinary → existing relative `dashboard.php`; module → configured base + `/new-connection.php`; expired → configured base + `/index.php?auth_context=expired`. Reject any other outcome shape/enum. These fixed mappings do not check user eligibility or perform redirects themselves. |

Stored session key is `portal_auth_return`. Its closed record has only `purpose=portal.auth.return.v1`, `destination`, `flow_id`, `issued_at`, `expires_at`. Unknown fields, changed lifetime/type or purpose reject. No applicant ID/name/contact/email, application reference, command, review proof, notice hash, password, OTP or CSRF token is part of the record.

Only an authorized fixed module-entry caller should invoke `begin`; browser destination fields never reach it. No actual caller was installed. Environment attestation is the caller's responsibility: a boolean is not independent proof of owner approval. `target()` must not be used as an authentication/eligibility decision or as a substitute for `take()`.

### Atomic claim requirement discovered by independent-worker testing

Session locking alone was insufficient: two workers can open the same pre-auth session inode; one consumes/regenerates/unlinks it while the waiting worker subsequently reads the old contents. An early test observed two module outcomes from that race. The final helper requires an injected atomic claim to close it.

Provider signature: `(sha256 purpose/flow digest, fixed expires_at, server now) -> true only for the first claim`. It stores no raw flow/session/user ID or applicant information. It must be shared across all auth workers/nodes, atomic, bounded, expiry-aware and fail closed. A rejected/failed claim never yields a module outcome. No arbitrary destination or identity is accepted through the provider.

The private fixture implements this with an exclusive file lock, digest→expiry entries, expired-entry cleanup, bounded capacity and checked writes/flush. This proves **same-host** concurrency, not distributed deployment readiness. Actual staging needs an approved durable atomic backend and synchronized clocks; do not deploy fixture files as a cross-node store. Missing claim provider deliberately blocks valid module consumption even if `match()` succeeds.

### Session replacement order

After the existing authentication checks succeed, copied fixture code calls `take()` to capture a small validated outcome into a local variable, then invokes the **unchanged** `portalAuthenticated()` and maps the local outcome. Tests seed old auth/recovery/logout CSRF, OAuth state, synthetic applicant data and a pending command into the pre-auth session and verify that none survives replacement. The new authenticated session regenerates its ID and gets a new CSRF value. The whole prior session is never copied forward.

Ordinary login with no supplied flow keeps dashboard destination, even with a pending module hint in another tab. Supplied invalid/superseded flow uses the fixed expired-context sign-in mapping, avoiding dashboard bootstrap as a navigation recovery mechanism. A captured module outcome maps only to the fixed module entry, whose actual feature gate still denies access when disabled.

## 3. Synthetic identity and integration architecture

Run:

```text
C:/xampp/php/php.exe tests/new-connection/auth-return-intent-test.php
C:/xampp/php/php.exe tests/new-connection/auth-return-integration-test.php
```

Environment: PHP 8.2.12, local MariaDB 10.4.32, two independent PHP CLI-server workers, headless Chrome 155.0.8059.85/Node CDP. No actual staging host or provider credentials.

The coordinator uses the existing guarded loopback test connection with **no default operational database**, creates one random `mco_nc_test_<16 hex>` schema and builds the full **inspected auth contract**: users with unique email/Google ID, password/status/verification/timestamps and structured profile columns; hashed OTP verification records with expiry/resend/attempt state; user-FK password-reset records with unique token hash/expiry/use; plus a separate synthetic ownership probe table. This is complete for the inspected controller queries, **not a claim that it clones every actual portal table or freshly verifies the actual schema**. No New Connection migration is needed or executed by this auth harness.

Every identity is synthetic `@example.invalid`. Status/verification and ownership fixtures live in this single selected schema. The current-user middleware copy is pointed at that same schema by replacing only its hardcoded `mco_portal.users` qualification with `users`. The real middleware's checks run; the actual source and SQL wrapper live-schema rejection remain unchanged. No duplicate/shadow production identity store or operational electric-account tables exist.

The temporary tree explicitly copies approved helpers/services/controllers and replaces DB configuration with a guarded disposable PDO connection. Real prepared queries run against MariaDB; this is a replacement connection boundary rather than a fake always-successful database. Mail is captured only to private synthetic delivery artifacts, with an enforced `.invalid` recipient suffix. Google SDK/config are replaced by local provider classes/constants; redirects to the fake provider are inspected without following them. Private provider counters prove invalid state is rejected before exchange.

### Explicit instrumentation, not unchanged-controller deployment

The harness generates **test-only controller copies**:

- Password/Google success points capture an outcome immediately before unchanged session replacement and use the helper's target instead of the copied dashboard redirect.
- Registration completion carries the still-valid selected flow to the fixture sign-in route; no automatic login.
- Google initialization associates selected flow with pending server OAuth state. The copied callback invokes a wrapper that snapshots pending state, calls the unchanged one-use validator, and exposes its flow only after validation succeeds. Callback URL hints are ignored. Flow consumption still occurs only at successful authentication.
- Recovery associates flow only with an explicit, same-browser CSRF-validated forgot request; the copied reset success carries it only while still valid. Reset email/token rows never carry destinations.
- JSON-only diagnostic views expose synthetic form error/session state to tests instead of rendering the actual auth UI.

Existing validation, password checks, actual session helper, OTP/resend/hash checks, Google binding branches, reset atomic claim and logout destruction logic are reused. These copied hooks exercise the **proposed integration**, not an approved production controller change. Live controllers still redirect exactly as before and do not include the new helper.

Temporary routes such as `/begin`, `/login`, `/callback`, `/module`, `/owned` and `/session` exist only on allowlisted loopback test servers. `/module` is a current-user eligibility probe, not a production New Connection editor/endpoint. `/owned` verifies server-derived owner filtering against synthetic records, not Stage 1 persistence. Unknown/private paths and dashboard are denied; the tree contains no dashboard bootstrap or actual mail/DB/Google configuration. Child process environment is allowlisted to OS/test-only settings.

CLI private controls age synthetic expiry/cooldown/intent values and change synthetic user state to exercise transitions. No auth limiter is cleared/disabled, password/OTP/state/CSRF check bypassed, or live configuration imported. Cleanup verifies the exact random schema/temp-root pattern before removing it; all current test servers/schema/private sessions/delivery/provider/browser artifacts are removed.

## 4. Password, registration, Google, recovery and logout results

Final new results: **54 pure-helper assertions**, **247 synthetic integration assertions**, and **6 Chrome storage/network checks**. Recorded integration and browser results are linked below. HTTP assertions include private no-store on responses.

| Area | Verified synthetic behavior |
| --- | --- |
| Password | Correct credentials with valid selected flow map directly to module; ordinary/missing-flow credentials keep dashboard header without executing dashboard. Wrong password and inactive/unverified users retain generic rejection. Invalid login CSRF rejects. Expired/forged/superseded handles cannot map arbitrary targets or silently fall back to dashboard. |
| Session replacement | New session ID and CSRF; old return intent, OAuth/CSRF state, personal-data marker and pending command absent. Idle expiry denies current-user probe. Current active status is reread; deactivation rejects an already-authenticated user. |
| Concurrent tabs | Two actual independent HTTP workers receive the same initial session/flow. At most one reaches module destination with the atomic claim. The other gets expired context or loses old CSRF after regeneration. Ordinary tab does not adopt another tab's hint. Stale handle cannot consume a newer active flow. |
| Identity/ownership | Active verified user with no linked accounts accesses eligibility probe; authenticated user cannot access another fixture owner's record. Signup and Google sessions resolve to the same authoritative synthetic store/FKs. |
| Registration/profile | Required structured identity fields preserved; existing terms requirement preserved. Mail sink receives only synthetic delivery. No verified user is created by return-intent logic. |
| OTP/resend | Actual hashed OTP checked; wrong code increments DB attempts; expired code and exhausted attempt counter reject. Resend cooldown enforced; after aging the synthetic cooldown, real resend creates new hash/expiry/reset attempts. Successful OTP creates active verified user, retains selected sign-in flow and **does not log in**; explicit subsequent password login is required for module return. |
| Google state | Forged/expired/replayed state rejects before provider exchange/destination capture. Return outcome comes only from validated pending state; callback `return`/`auth_flow` hints cannot change it. Ordinary Google flow keeps dashboard header. |
| Google binding/verification | Bound identity match succeeds; binding collision rejects. Inactive portal user or unverified Google email cannot authenticate. Existing third-party unbound mailbox rule rejects without changing Google ID/verification. Already bound but locally unverified portal account cannot enter module, and its timestamp stays NULL. See the blocked unbound Gmail/hosted branch below. |
| Same-browser recovery | Existing forgot/reset controllers issue/use hashed one-time reset token through the sink. Same-browser explicitly selected flow survives only while valid. Reset does not authenticate or replay a command. A later explicit login can consume the valid flow. |
| Expired/cross-browser recovery | Expired hint returns ordinary reset-success sign-in. Another browser uses its own recovery CSRF and cannot import caller-supplied flow. Reset token replay fails; real token claim updates exactly once. Auth CSRF cannot substitute for recovery CSRF. |
| Logout/reset dialogs | Actual logout controller requires POST and `dashboard_csrf`; auth CSRF is rejected. Confirmed logout destroys session/intent/command markers. Existing reset-password/logout dialog code was not modified. Visual confirmation/focus acceptance is not claimed from JSON views; existing session-action regressions are separate. |
| Claim infrastructure failure | Injected claim-store outage returns generic 503 before authentication/session replacement; no module redirect, fallback or authenticated identity. No sensitive exception detail returned. |

Evidence: [synthetic integration results](auth-return-integration-results.json), [browser results](auth-return-browser-results.json). The browser logs in using same-origin requests and real CSRF against this synthetic harness, verifies zero localStorage/sessionStorage entries, private no-store, local HTTP HttpOnly/Lax cookie attributes and no external requests/runtime exceptions. These are storage/network assertions, **not visual acceptance of real portal forms/dialogs or actual staging HTTPS**.

## 5. Negative security and privacy results

Covered: arbitrary URL/destination enums, protocol-relative inputs, CRLF, traversal/double encoding, invalid canonical configuration, unknown/forged/cross-session handles, missing/reused/mismatched flows, timestamp boundary/future issue/altered lifetime, malformed stored record/unknown fields, OAuth failure/expiry/replay, login-tab interference, disabled environment/feature, atomic-claim rejection/outage and current status/ownership changes.

The helper reads no request globals and logs nothing. Private claim state contains only purpose/flow digests and fixed expiry. Auth request logs go to the null device; existing explicit recovery/Google diagnostics are captured separately and scanned for identity, passwords, OTP and reset-token leakage. Private mail/provider/session artifacts are test inputs, not retained telemetry, and are deleted.

An early evidence-label version retained synthetic query strings; labels were changed to **path only** and results regenerated. Final evidence has an explicit assertion against identity/query-secret patterns. No real tokens/customer data were involved, and raw synthetic reset/OAuth/flow values are not retained in the final result JSON.

Other exploratory failures: registration fixture omitted its existing terms field; a multi-worker test exposed the old-session-inode race; a first two-server cleanup only stopped the last worker. The fixture input, atomic claim and cleanup were corrected. The leftover test worker was identified precisely, stopped, and its verified empty temporary directory removed; no stale failed fixture remains from that case. No production protection was relaxed to make acceptance pass.

## 6. Existing regression results rerun for Task 06F

| Suite | Result |
| --- | --- |
| `auth-return-intent-test.php` | PASS, **54** assertions |
| `auth-return-integration-test.php` | PASS, **247** assertions; guarded real MariaDB, two HTTP workers, private doubles |
| `auth-return-browser-test.js` through coordinator | PASS, **6** checks, Chrome 155.0.8059.85; storage/network only |
| `auth-security-test.php` | PASS, **42** assertions; unchanged copied actual controllers and isolated HTTP/server-TLS helpers |
| `tests/password-reset-test.php` | PASS; existing PDO/mail doubles and real file limiter |
| `tests/session-actions-http-test.php` | PASS, **22** assertions; existing logout/session behavior |
| `tests/structure-test.php` | PASS, **194** assertions |
| `tests/paleco-readonly-contract.php` | PASS |
| `applicant-contract-test.php` | PASS, **42** assertions |
| `foundation-test.php` | PASS, **41** assertions; default-off/access contracts |
| `publication-governance-test.php` | PASS, **107** assertions with independent-PDO concurrency |
| `draft-persistence-test.php` | PASS, **149** assertions |
| `stage1-submission-test.php` | PASS, **97** assertions in this run (race-dependent branches) |
| `database-foundation-test.php` | PASS, **68** assertions |
| `staging-recovery-test.php` | PASS, **40** assertions; disposable privilege/backup/restore/interruption rehearsal |
| `stage1-http-test.php` compatibility | PASS, **227** browser/HTTP checks; four synthetic submissions |
| `staging-recovery-http-test.php` governed superset | PASS, **353** browser/HTTP checks and **26** trusted-context assertions; eight synthetic submissions; private schema removed |
| `applicant-prototype-test.php` | PASS, **97** browser checks; pure/synthetic state |
| `local-assets-test.cjs` | PASS, **102** checks; local CSS/icons |
| PHP lint / Node syntax | PASS for added executable PHP/JS files |

Existing guarded suites create/migrate only their random disposable schemas; none migrates actual `mco_portal`. The 353-check superset retains governed notice/catalog/key/concurrency acceptance, including the real rate-window wait. Ordinary dashboard/account-linking/eBills/profile production source is unchanged; these isolated checks do not claim full live operational-route browser acceptance. No live mail/provider or dashboard bootstrap was executed by the new auth harness.

## 7. Blocked controller integration and remaining limitations

1. **Actual wiring deliberately absent.** Task 06E decisions are not approved. Password/register/Google/recovery/logout controllers, live module routes, configuration and migration guards stay unchanged. Temporary redirect/context hooks are not deployable code.
2. **Existing Google verification policy conflict:** callback code can populate `email_verified_at` when linking an otherwise unverified existing Gmail/hosted identity. Executing that branch in the proposed module flow would violate this task's no-automatic-verification requirement. That specific branch is **NOT EXECUTED/BLOCKED**, not reported passed. No fake provider outcome or controller patch pretends to resolve it. Actual account-binding/verification policy and a scoped security fix need owner approval. Bound-unverified and third-party-unbound rejection cases were tested separately.
3. **Distributed claim/session infrastructure absent.** The new callback seam is mandatory for module consumption; same-host file claims are test-only. Approve a shared atomic backend, fixed expiry/clock policy, capacity/outage behavior and operations before wiring. Do not rely solely on session locks across regeneration.
4. **No real auth UI or staging TLS/provider acceptance.** JSON fixture views do not prove confirmation dialog visuals, real registration/Google callback configuration, reverse proxies or shared-store failover. Existing auth/session/local asset regressions are evidence at their stated scope only.
5. **Identity schema target pending.** The synthetic auth schema satisfies inspected queries, not a fresh authoritative schema inventory. Actual identity ownership/selected schema, lifecycle, least privileges, durable trigger definer and backup/security-state recovery need owner attestation. Current service/migration guards remain disposable-only.

## 8. Decisions requiring owner approval

- Task 06E technical decisions T1/T2/T3/T7: authoritative identity/schema, closed selected-flow/expired-context behavior, 600s fixed lifetime, synthetic staging scope and verification compatibility.
- Atomic claim provider and shared-session/rate-limit infrastructure, canonical origin/base path, failure policy, clock synchronization and secret/telemetry operations. No real hostname or credentials are invented.
- Explicit disposition of unbound Google Gmail/hosted accounts with NULL portal verification; preserve legitimate binding protections and deny module access until the approved eligibility requirements are met.
- Scope/files for any eventual real-controller changes, synthetic mail/provider isolation and ordinary-user regression acceptance.
- All PALECO form/field/privacy/retention/notice/catalog/staff/signature policy gates in [06E](staging-identity-integration-spec.md) remain unverified. This navigation helper does not approve any applicant-data collection.

## 9. Recommended next task

**Task 06G — Approved Authentication Wiring Plan and Google Verification Gate Resolution.** Obtain the named technical/security decisions first. Specify the shared atomic claim adapter and the exact Google verification/binding disposition, then authorize only narrowly scoped auth-return wiring with full synthetic controller/UI regression. Keep applicant editor/HTTP persistence, migrations and real collection out of that change. Actual deployment remains a separate owner-attested gate.

**Stop here.** The helper is internal and unwired, New Connection is default-off, and no live authentication redirect, database schema, operational data or policy approval changed.
