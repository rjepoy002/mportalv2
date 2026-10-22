# Task 06G — Google Verification Security Review and Authentication Wiring Plan

Date: **2026-10-21, Asia/Manila**. Status: **security design and synthetic evidence; policy NOT APPROVED; live wiring NOT IMPLEMENTED**.

The current callback has two different ways to establish portal verification without registration OTP: its Gmail/hosted auto-link UPDATE and its new-user INSERT. It also authenticates an already-bound active user whose portal verification timestamp is NULL, although current portal/module middleware subsequently rejects that user. These are distinct branches and must not be described as one verification rule.

**Recommendation:** separate provider authentication/email claims from portal verification and module eligibility; require explicit portal-controlled proof to link an unbound Google identity, and do not fill portal verification in Google login. Approve the resulting ordinary-Google compatibility changes before implementation. Keep ordinary navigation destinations and existing OAuth/CSRF/session protections intact. An approved shared atomic claim adapter is also required before module return wiring.

## 1. Actual Google callback branch analysis

### Scope and evidence

Inspected actual [Google initialization](../../app/controllers/google-login.php), [callback](../../app/controllers/google-callback.php), [session/identity helpers](../../app/helpers/session-security.php), password/registration/recovery/logout controllers, [portal middleware](../../app/middleware/auth.php), [module middleware](../../app/middleware/new-connection-auth.php), dashboard/account-context bootstrap, [Task 06E](staging-identity-integration-spec.md) and [Task 06F](auth-return-intent-report.md). No real DB, provider, account or credential configuration was executed.

The new review harness runs **byte-identical copies** of the current Google controllers with a no-connection PDO SQL spy and local provider double. The spy records SQL intent, never executes SQL or changes a verification timestamp. Fixed input rows are immutable. A separately constructed fixed result models the current new-user SELECT; no account is created. This permits review of 06F's previously unexecuted verification-write branches without violating this task's no-verification-change restriction.

| Step / condition in current source | Current result and side effects |
| --- | --- |
| Google initialization | Starts secure session, loads SDK/config, requests email/profile scopes, prompts account selection, stores random 32-byte/64-hex OAuth state and server creation time in `oauth_pending`, then redirects to provider URL. A new initiation replaces prior pending state. No portal user or verification write. |
| Callback missing/mismatched/replayed/expired state | `portalOAuthStateValid()` consumes pending state even on failure; requires session state, string equality, nonfuture creation and age <600s. Callback returns 400 before SDK exchange/DB bootstrap. No authenticated outcome is produced by a rejected callback. |
| Missing/nonstring code or present `error` | Initial guard returns 400, after consuming state, before exchange/DB. A later duplicate missing-code check is redundant for normal execution. Empty string code is not explicitly rejected by this initial string check; successful exchange is still required. |
| Configuration/client bootstrap | SDK/config/DB loading and client construction precede the main try/catch. Their failure is not made safe merely by the later generic catch. No real configuration failure was exercised. Future bootstrap must fail generically without leaking credentials or falling back to operational configuration. |
| Token exchange returns `error` | Stops without creating a session. No explicit error HTTP status is set on this branch, so the isolated fixture observes 200. No raw token is logged. Missing/malformed access-token handling otherwise relies on SDK behavior/exceptions; this task does not claim live SDK/provider validation. |
| Provider identity validation | Requires nonempty string ID, string/filter-valid email of at most 255 bytes, and strict boolean `verified_email === true`. False, string `"true"`, integer 1, empty ID or invalid email reject. Then email is lowercased/trimmed and name trimmed. This is a provider-profile check, not portal verification or staff authorization. |
| Portal lookup | `SELECT * FROM users WHERE email = :email LIMIT 1`. It does **not** first resolve an existing binding by stable Google subject. |
| Existing portal user inactive/suspended | Reject before linking or verification writes. Source later checks status again, but for existing users it uses the same in-memory row, not a fresh status lookup. |
| Existing active user with nonempty matching `google_id` | No identity/verification write. Calls `portalAuthenticated()` regardless of whether portal `email_verified_at` is NULL. Adds `logged_in=true`; redirects dashboard. |
| Existing active user with nonempty conflicting `google_id` | Reject. Does not overwrite the binding or merge users. |
| Existing active, unbound user, Gmail address | `empty(google_id)` branch accepts the address suffix, UPDATEs Google ID and `updated_at`, and sets `email_verified_at=COALESCE(email_verified_at,NOW())`. Existing timestamp preserved; NULL becomes verified in an actual successful DB execution. Authenticates and redirects dashboard. No registration OTP used here. |
| Existing active, unbound user, nonempty `hd` | Same UPDATE/verification/authentication as Gmail, even for a non-Gmail email. Current condition checks **nonemptiness** only; it does not compare `hd` to email domain, check a tenant allowlist or validate its type/format. The synthetic whitespace/other-domain cases show source behavior, not that Google would issue an attacker-chosen hosted-domain claim. |
| Existing active, unbound, other mailbox (`hd` empty) | Reject even if portal account is already verified and provider says email is verified. Preserves the existing anti-email-only-linking protection. |
| No portal row, verified provider email | Generates random unknown password/hash, INSERTs user with Google ID, full name, `email_verified_at=NOW()` and `status=active`, for Gmail, hosted **or third-party** email. Fetches inserted user by ID, then status-checks/authenticates/redirects. No registration OTP, structured-name prefill approval or staff assignment is created. |
| New-user result is not active | Final post-insert status check rejects session creation, though the attempted INSERT remains a separate effect. This does not undo committed account writes. |
| DB uniqueness/write/provider exception in try | Generic error log/text, no successful session creation by that path. No explicit failure status is set; fixture observes 200 for caught cases. Constraint failure is simulated, not an assertion that real target constraints were reverified. |

`empty(google_id)` also treats empty string and `"0"` as unbound; proposed binding logic should use an attested valid representation rather than silently reinterpreting legacy values. Do a read-only inventory before any cleanup. No legacy account repair is performed here.

### Exact verification/session truth table

All rows assume valid OAuth/exchange, provider email strictly verified, and active status unless stated. “Fill” describes **current SQL intent on actual successful execution**, not a write performed by these tests.

| Existing portal state | Provider/binding | Current portal verification action | Current session outcome | Current module gate after fresh DB read |
| --- | --- | --- | --- | --- |
| Verified | Bound, subject matches | Preserve timestamp; no UPDATE | Authenticate | Eligible by identity only; no electric account required |
| Unverified | Bound, subject matches | Remains NULL | Authenticate | Reject; module middleware requires verified timestamp |
| Verified | Unbound Gmail / nonempty `hd` | Preserve existing timestamp; attach subject | Authenticate | Eligible by identity after successful link |
| Unverified | Unbound Gmail / nonempty `hd` | **Fill NULL verification timestamp** and attach subject | Authenticate | Would pass identity gate after actual successful UPDATE |
| Verified/unverified | Unbound third-party, empty `hd` | No change | Reject | No module entry from that callback |
| Any | Bound subject conflict | No change | Reject | No new authenticated outcome |
| Any | Suspended/inactive | No change before rejection | Reject | Reject |
| No account | Any supported email domain with verified provider claim | **INSERT active verified user** | Authenticate if inserted/fetched active | Would pass identity gate after actual successful INSERT |
| Any | Unverified/invalid provider email claim | No change | Reject | No new authenticated outcome |

The UPDATE does not reload the existing user row: an originally NULL `$user['email_verified_at']` remains NULL in memory after the UPDATE, but `portalAuthenticated()` stores only ID/name/email/timestamps. Later middleware reads the actual current DB row. Therefore a successful verification-fill UPDATE cannot be dismissed as harmless because the callback's local array is stale.

## 2. Verification and account-linking risk assessment

### Four different authorities

1. **Google identity authentication:** evidence that the provider exchange/userinfo represents a stable Google subject. A callback query or browser profile object is not evidence.
2. **Google email verification claim:** a provider attribute of that authenticated identity. It does not itself approve PALECO portal policy, staff role, electric-account ownership or a New Connection application.
3. **Portal account verification:** current portal `email_verified_at` and active lifecycle status, under the authentication owner's approved verification policy. This is not proof of membership approval or handwritten signature. Current OTP registration and Google paths establish it differently.
4. **New Connection eligibility:** active and portal-verified authoritative user, plus module/environment/publication gates and application ownership. No linked electric account prerequisite and no automatic staff access.

### Findings and priority

| Finding | Security/compatibility implication | Required response |
| --- | --- | --- |
| Google linking/new enrollment fills portal verification without OTP | These paths bypass the **OTP registration route as implemented**, and would bypass a policy requiring independent portal verification. No authoritative decision was supplied showing whether Google-based verification was an intended approved alternative. Do not label that policy settled or silently change it. | Authentication/security owners approve a consistent rule before wiring. Recommend no implicit promotion of portal verification. |
| Bound, locally unverified account receives session | Inconsistent with password sign-in's active+verified requirement. Current `auth.php`/module middleware rejects and clears/denies on fresh check; initial session creation still occurs. | Proposed policy must gate full session issuance, not rely only on eventual dashboard/module rejection. |
| Auto-link based on email plus Gmail/nonempty `hd` | Existing third-party mailbox guard and conflict denial are useful protections, but domain/email attributes alone should not authorize a new portal binding under the recommended stricter policy. | Require explicit fresh portal proof and purpose-bound linking; do not merely remove the existing guard. |
| Subject lookup only after email match | A bound Google subject with changed email may miss its original owner and attempt duplicate enrollment; historical Google-ID uniqueness should reject. The spy models a uniqueness failure; real uniqueness was not tested here. | Resolve stable subject first; changed email requires portal-controlled review/reverification, no silent merge or profile overwrite. |
| Link UPDATE matches only `id` | No compare-and-set NULL-binding/status/verification predicate, transaction or row lock surrounds current select/link/session sequence. Concurrent binding/status changes are not serialized by this code. | Future repository must lock/revalidate and enforce unique subject + conditional link. This is a code-level race exposure, not a demonstrated live-provider exploit. |
| Existing final status recheck uses stale row | A status change between lookup and authentication may not be seen inside the callback. | Revalidate near session issuance; every private request must still revalidate current user. Do not claim perfect instantaneous distributed revocation. |
| Google errors often retain status 200; bootstrap outside catch | Generic text does not guarantee generic infrastructure failures or useful monitoring status. | Explicit reviewed error boundaries/status categories, no exception/profile/token/SQL logging. Ordinary navigation compatibility must be tested separately. |
| Return helper requires external atomic claim | Old PHP session inode can survive regeneration for another waiting worker; session locking alone does not guarantee one use. | Shared authoritative claim adapter, fail-closed outages/durability and capacity policy before wiring. |

No live exploit, Google claim spoofing, actual database drift or approved verification policy is asserted. Current default-off module does not accept real submissions, and this task does not enable it.

## 3. Recommended verification policy — proposed, NOT approved

**Default proposal:** Google login never writes `email_verified_at`; full portal session requires active + portal-verified account under the approved portal verification process. Apply the no-implicit-verification rule prospectively. No automatic email-only linking for any domain. Existing ordinary dashboard destination remains the default after successful approved authentication; stricter eligibility/linking is a deliberate policy change requiring approval, not merely navigation wiring.

| Situation | Recommended outcome |
| --- | --- |
| Valid provider, matching existing bound subject, active + portal verified, email consistent | Authenticate after fresh status/binding read; then resolve optional return intent. |
| Existing bound or unbound account with NULL portal verification | No normal `user_id`/`logged_in` session and no timestamp update. Offer an approved portal verification/recovery path; any limited provider context must not be accepted by portal/module middleware. No email is sent by this task. |
| Active verified account, no bound subject | Do not auto-link from login callback. Require explicit linking initiated from authenticated portal context, with approved recent password/portal challenge proof, CSRF, one-use OAuth state, fixed `purpose=link`, server-derived actor and unique-subject checks. |
| Subject already linked to a different portal owner / provider-ID mismatch | Generic conflict; no overwrite, email-based merge, reassignment, automatic verification or revealing other account existence. Support/appeal workflow requires separate approved proof. |
| Bound subject with changed provider email | Resolve stable subject, but require portal-controlled email review/change verification before normal use under this conservative proposal. Never overwrite verified portal email or transfer identity automatically. Cross-owner email collision remains a conflict. |
| No portal identity | Use existing approved portal enrollment/OTP route. No automatic active+verified INSERT from Google. Any Google-first pending enrollment design needs explicit approval/schema/UI scope; do not invent an existing `pending` user status. |
| Suspended/inactive user | Deny regardless of Google verification, portal proof or return hint. Verification does not reactivate accounts. |
| Unverified provider email / invalid subject / failed OAuth evidence | Deny before account lookup/link/authentication. Preserve one-use state and account-binding checks. |

The explicit linking proof must be bound to the same portal actor, OAuth purpose/session, challenge freshness and exact intended subject. A browser-supplied Boolean is not proof. The test model's `freshPortalProof` flag is only a trusted synthetic input to exercise decision separation; it is not a deployable authenticator.

**Recovery-path gap:** existing `register.php` rejects an already-present email and primarily verifies pending registration before user creation. It is not an established verification-recovery route for an existing unverified account. Password reset changes a password, not portal verification. An owner-approved existing-account verification/challenge flow or assisted process must be specified before promising recovery to those users. No such endpoint, challenge delivery or timestamp update is added here; do not bypass the gap through Google auto-linking.

Future binding transaction: resolve subject/owner and selected portal actor using authoritative schema; lock relevant row(s) in deterministic order; verify active/portal verified/current expected binding and explicit server proof; enforce unique Google subject; conditional UPDATE only when still unbound and eligible; require affected-row success; commit; revalidate before session issuance. Do not hold an SQL lock across Google network exchange. Prevent partial application on failures; module continues to recheck status/ownership on each request. No production SQL is applied here.

**Migration/repair of existing timestamps is explicitly excluded.** Existing non-NULL verification may have originated from OTP, historical import or Google; the code does not establish provenance. The model's non-NULL predicate cannot prove historic independent OTP verification. Recommend forward-only behavior with no automatic resets, and require an explicit owner decision on legacy acceptance versus separately scoped provenance/reverification before any stronger eligibility claim. Do not reset, backfill, infer provenance from `google_id`, revoke accounts wholesale or mark them approved without that review. Existing successful Google users may be affected by stricter linking/enrollment; communication/recovery/assisted-service plans are approval gates.

## 4. Exact proposed code changes and integration order

These are future review targets only; none was applied to production code in Task 06G.

| Flow / files | Proposed implementation map and checks |
| --- | --- |
| Policy seam: NEW `app/services/google-identity-policy.php` | Implement only the approved decisions, separate provider evidence/portal verification/link purpose/eligibility. Do not deploy the test decision model verbatim; it omits real token/proof/repository validation. |
| Identity repository: NEW `app/services/portal-identity.php` | One authoritative store; subject-first/actor lookup, current-user eligibility, transactional conditional linking and uniqueness handling. No operational account lookup, email-based owner merge or invented staff grants. |
| Claim seam: NEW `app/services/auth-return-claim-store.php` and approved backend adapter | Interface in section 5, independent of dashboard/workflow. Use protected config and least-privilege shared storage; no production fallback to fixture files. |
| Context/config: NEW `app/services/auth-return-context.php`, explicitly scoped configuration | Build helper/claim adapter only for selected module flow after auth eligibility checks; canonical URL/environment/feature attestation and safe errors. **Ordinary no-flow login must not depend on module claim-store availability or missing module config.** Never infer origin/schema from Host or browser fields. |
| Password: `app/controllers/index.php`, `app/views/pages/index.php` | Preserve password/status/verification checks, dummy hash, auth CSRF and rate limiter. Match explicit session flow; after successful authentication, atomically `take()` before unchanged `portalAuthenticated()` clears the session. Capture only small closed outcome; map to fixed module/sign-in target. No-flow keeps existing `dashboard.php` behavior. |
| Registration/OTP: `app/controllers/register.php`, `app/views/pages/register.php` | Preserve fields/terms, OTP hashing, expiry/attempts/resend and no automatic login. Keep valid selected flow separate from registration data and return it only to sign-in. Do not consume it at OTP completion or promote verification because a hint exists. Full duplicate/OTP-transaction correctness review remains separate if needed. |
| Google init: `app/controllers/google-login.php` | Preserve session-bound random state, expiry, scopes and account-selection behavior. Bind selected return context only server-side to that pending OAuth record. Distinguish approved login vs explicit-link purpose; no callback-supplied purpose/actor/destination. Add approved shared abuse limits without bypassing state checks. |
| Google callback: `app/controllers/google-callback.php` | Consume/validate state before exchange; capture associated context only after validation, not arbitrary callback fields. Validate provider evidence, then approved policy/repository checks. Remove implicit verification writes **only after policy approval**, and do not replace them with weaker auto-linking. Capture/take selected navigation only after auth/link decision; regenerate session without preserving pre-auth data. Preserve ordinary dashboard destination and generic conflict handling. Apply safe error boundary to bootstrap as well as exchange; retain verified provider/binding protections. |
| Recovery: `app/controllers/forgot-password.php`, `app/controllers/reset-password.php`, corresponding views | Same-browser flow must have been explicitly associated through local CSRF-protected recovery initiation. Preserve hash/one-time reset claim/password rules and no automatic login. No destination/flow in reset email/token rows. Cross-browser/expired intent returns normal sign-in; no flow adoption from unrelated tab or callback URL. |
| Session expiry: module caller + unchanged `app/helpers/session-security.php` contracts | Existing idle/absolute expiry and regeneration remain. Expired module requests return private 401/419 as appropriate, discard pending commands/proofs/acknowledgments, and offer explicit fixed module-entry reauth. Never auto-submit after reauthentication. Do not restore whole old session just to preserve a hint. |
| Logout/reset confirmation: `app/controllers/logout.php`, `app/views/partials/session-action-dialogs.php`, `assets/js/session-actions.js` | Preserve POST, `dashboard_csrf`, distinct token namespaces, intent enum, confirmation/cancellation/focus, destruction and existing sign-in/forgot destination. Logout intentionally clears return intent; do not preserve it through session destruction. Shell can initialize dialog token without dashboard bootstrap. |
| New Connection: `app/controllers/new-connection.php`, `app/middleware/new-connection-auth.php` | Gate environment/feature before new session/connection/writes; fixed module entry establishes a selected hint only when permitted. Use active/verified same-store user resolver, no linked electric account or dashboard bootstrap. Any hint success is navigation only; current-user/publication/ownership checks remain. No applicant endpoint/form/persistence integration in this auth wiring change. |
| Tests: dedicated actual-controller/staging suites | Keep current-behavior evidence separate from approved future-policy tests. Add shared-store durability/failover, subject/email conflicts, binding CAS/status races, real auth/registration/OAuth/recovery/root/subpath return and ordinary portal regressions using synthetic data only. |

No-flow means ordinary behavior, not “whatever pending module hint happens to be in the session.” Supplied invalid/expired/mismatched hint goes to the fixed expired-context sign-in notice, not arbitrary URL or silent dashboard recovery. Already captured module destination still reaches the fixed module entry, where disabling its flag denies access; a disabled gate before consumption yields expired context. No mode may override feature/eligibility protections.

Implementation order: approve verification/linking policy and compatibility effects → approve authoritative identity/claim infrastructure contract → implement provider/repository decisions in synthetic tests → review minimal controller wiring → authorize isolated staging acceptance. Actual applicant routes, migrations and real collection are separate later approvals. Do not modify existing migration definitions/checksums/guards or dashboard controller for this task.

## 5. Atomic claim infrastructure specification

### Adapter contract compatible with Task 06F

```text
claim(digest: 64 lowercase hex, expiresAt: integer UTC epoch,
      requestNow: integer UTC epoch) -> bool

true  = first authoritative durable claim committed and acknowledged
false = already claimed OR expired; never permission to redirect to module
throw = unavailable, uncertain commit, invalid clock/input, capacity or corruption
```

Digest is the helper's SHA-256 of purpose/version + random flow; no user/session ID, email, OAuth state, CSRF, provider token or applicant fields. Caller cannot supply destination/proof/identity via this adapter. A module claim is attempted only after credentials and approved eligibility checks, immediately before `portalAuthenticated()`. Ordinary login does not call it. `PortalAuthReturnIntent::take()` propagates missing-provider/outage failures; wiring must return a generic failure without module success/dashboard fallback.

### Recommended deployable reference design, not provisioned

Use an owner-approved **shared single-authority transactional durable SQL store**, with a unique digest key, absolute expiry and a scoped quota row. This can be dedicated authentication infrastructure; exact host/schema/ownership is **REQUIRES OWNER INPUT**. It is not an operational billing table, not an identity shadow, and not part of current New Connection migrations. Any future DDL needs separate review/authorization; none is created here.

Reference operation, on a dedicated connection/transaction:

1. Validate input shape and caller/environment namespace; obtain trusted storage UTC time and reject unapproved application/storage clock divergence. Require a future deadline within the helper's fixed 600s protocol bound plus approved clock tolerance. The deadline may never be refreshed by a retry.
2. Lock the environment/purpose quota row; perform bounded expired-marker cleanup/accounting under that same serialization policy. Check duplicate digest before allocating new capacity. Storage independently rejects deadlines already expired even if a lagging application clock still accepts its session record.
3. If digest already present, return false; never UPSERT/extend/replace it. If capacity unavailable, throw/fail closed. Otherwise INSERT unique digest + absolute expiry and increment accounting transactionally.
4. Commit and return true only after authoritative durable acknowledgment. A uniqueness race resolves as already claimed. No process-local success flag, shared-session lock, replica read or cache miss substitutes for this commit.
5. On timeout/unknown commit, throw; do not return true, delete the marker or retry as a fresh key automatically. The first claim may have committed. Explicit user restart creates a new flow; it never replays an application submission.

This serialized reference prioritizes correctness over throughput. Benchmark only after quota partitioning/locking/durability are approved; do not remove serialization to conceal capacity pressure. A more scalable backend must prove equivalent atomicity and no-loss behavior, including failover and expiry, before substituting it. A best-effort cache, evictable marker, asynchronous lost write, local filesystem across nodes or fail-open outage is insufficient.

### Operational requirements

| Area | Required behavior / owner gate |
| --- | --- |
| Capacity | Global/per-environment/per-purpose caps, bounded request/key size, authenticated-attempt abuse controls and bounded cleanup. Values **REQUIRES OWNER INPUT**; mock capacity 2 is only a test value. Never delete unexpired claims to admit new requests. |
| Clock | UTC synchronized application/storage clocks, absolute deadline, approved skew bound, rollback/leap detection and readiness gate. Values **REQUIRES OWNER INPUT**. Mock skew 0 and 2 seconds are test settings, not approved policy. No sliding TTL or accepting arbitrary caller time as backend authority. |
| Availability | Protected connection credentials, bounded timeouts, primary authority, durable commit/failover contract and health monitoring. On error/corruption/capacity/clock uncertainty, module flow fails closed; ordinary no-flow auth remains independent. No fallback to another empty namespace/store. |
| Session regeneration | Shared store independent of PHP session file/inode; one digest can succeed once across copied pre-regeneration session snapshots and workers. Capture only closed outcome, then regenerate/replace session. Do not restore old tokens/commands. |
| Least privilege | Dedicated adapter principal may read/insert claim rows and update scoped quota accounting; cleanup principal deletes expired rows only under approved procedure. No user verification/profile writes, operational access, DDL, GRANT or secret dump rights in runtime. |
| Retention/cleanup | Expire only after protocol/backend deadline rules make the hint unusable. Bounded cleanup must preserve quota consistency; cleanup failure may reduce capacity but must not reopen claims. Hashes/expiry are infrastructure metadata, not a reason to invent applicant retention policy. |
| Restore/failover | Losing consumed markers while old session hints remain valid reopens replay. After uncertain marker loss/restore, keep module-flow consumption off until all pre-recovery flows are invalidated or their 600s validity plus approved clock allowance has drained under an approved procedure. Namespace rotation alone without invalidating old session hints is **not enough**. Ordinary authenticated-session/revocation recovery needs its own approved plan. |
| Monitoring | Aggregate claimed/already/expired/capacity/unavailable/clock/latency counts and bounded safe error categories. No raw flow, full digest as metric label, cookies, OAuth/CSRF/reset tokens, provider subject/email/profile or exception/SQL payloads in logs. Review proxy/APM/crash logs too. |

Storage technology, target/grants, durable trigger/DB ownership if applicable, capacity, timeout, clock/failover/restore plan and on-call responsibility remain unapproved. This interface does not authorize provisioning infrastructure or installing a production claim table.

## 6. Synthetic tests and verification limits

### Files added

- `tests/new-connection/google-verification-review-test.php` — source-copy current-branch harness with immutable SQL spy and separate proposed tests.
- `tests/new-connection/google-verification-review-fixture.php` — temporary loopback routing/shutdown summaries; no deployed endpoint.
- `tests/new-connection/google-policy-proposal.php` — **unapproved test-only** pure identity/pipeline decision model, not application auth code.
- `tests/new-connection/auth-claim-contract-test.php` — in-memory shared-claim contract mock.
- `google-verification-review-results.json`, `auth-claim-contract-results.json` — sanitized evidence.
- This plan.

No existing application/helper/controller/configuration/migration was modified. Before/after content fingerprints cover `app`, `config` and `database/migrations`; current callback copy's SHA-256 also matches its source. All temporary server/session/private fixture/spy artifacts are removed. Actual Google/DB/mail configuration is never copied or loaded.

### Results

| Test | Result / scope |
| --- | --- |
| `google-verification-review-test.php` | PASS: **206 assertions**, **36 current-controller cases**; separate pure proposed decision and OAuth-evidence pipeline checks. No real DB connection, SQL execution or account verification change. |
| `auth-claim-contract-test.php` | PASS: **20 assertions**, in-memory contract only: first claim, shared/fork replay, expiry, capacity, outage, lost commit acknowledgment, explicit fresh-flow recovery, skew/clock regression, digest-only metadata and ordinary-login independence. |
| `auth-return-intent-test.php` | PASS: **54** existing pure synthetic-session assertions. |
| `auth-security-test.php` | PASS: **42** existing isolated controller/PDO/provider-double and HTTP/server-TLS assertions; no actual portal database. |
| `tests/structure-test.php` | PASS: **194** dependency/structure assertions. |
| `tests/paleco-readonly-contract.php` | PASS. |
| Added PHP lint | PASS. |

Current cases cover bound verified/unverified, Gmail/hosted/third-party unbound variants, conflicts, inactive/suspended, strict provider verification types, missing ID/invalid email, new enrollment for each domain class, post-insert inactive result, simulated write/uniqueness failure, changed-email duplicate scenario, token failure, missing/expired/mismatched/replayed state, missing/array code and cancellation. Mock malformed `hd` illustrates the local condition only.

SQL spy fields `would_link`, `would_fill_portal_verification`, `would_insert_verified_user` describe **attempted SQL intent**, not committed changes; `write_failed` distinguishes simulated constraint failure. No SQL statement executes. The input portal row remains fixed even after an attempted UPDATE, accurately exposing the callback's stale local-row behavior. New-user SELECT returns a separately declared fixed fixture result. These tests do not verify target constraints, transactions, SDK transport/token authenticity, real Google claims or production concurrency.

The proposed model is separately named and never inserted into the current callback. Its identity decision assumes already trusted provider evidence; its pipeline model explicitly denies failed state/exchange before considering identity. `authenticate` in the proposal is a decision label only—no real session or user write. It additionally tests explicit portal-proof linking, no verification promotion despite proof, cross-owner binding conflict, changed-email review and unknown-purpose rejection. It is not a production proof verifier or complete binding transaction.

Results: [current/proposed review](google-verification-review-results.json), [claim contract mock](auth-claim-contract-results.json). No real migrations or real/disposable account verification updates were needed. Real-DB write/constraint tests, distributed atomicity/durability/failover, actual Google/HTTPS staging, visual dialogs and full portal regressions remain **NOT PERFORMED / pending authorized later work**. Historical Task 06F multi-worker claim tests remain separate evidence, not newly performed infrastructure acceptance.

## 7. Remaining approvals and go/no-go

| Decision | Required evidence / proposed accountable approver | Current status |
| --- | --- | --- |
| Portal verification meaning and Google promotion | Signed rule for OTP vs Google-derived verification, new enrollment, existing unverified/bound users, historic timestamp provenance and any recovery exceptions; authentication + security owners, with privacy/process review where needed | **REQUIRES OWNER APPROVAL**; recommendation is not approved |
| Explicit linking and conflicts | Purpose-bound fresh proof standard, same-actor/subject checks, changed-email handling, unique constraints, transactional CAS and safe support/appeal process; authentication/security + DBA | **REQUIRES OWNER APPROVAL** |
| Existing-user compatibility | Impact inventory (read-only first), migration/communication/assisted recovery and ordinary Google/login regressions; portal application owner + authentication/security | **REQUIRES OWNER APPROVAL**; no repairs/state resets authorized |
| Atomic claim infrastructure | Exact shared target/technology, durability/failover/capacity/clock/outage/restore/grant/monitoring ownership; infrastructure + DBA + security/authentication owners | **REQUIRES OWNER INPUT/APPROVAL**; no service/table provisioned |
| Return wiring | Closed destination/selected-flow/600s behavior, callback capture ordering, independent ordinary-auth path, generic failures, canonical URL/base path and scoped changed-file map; application/authentication + security owners | **REQUIRES OWNER APPROVAL**; helper remains unwired |
| Staging scope/identity isolation | Named host/schema, one coherent synthetic identity store, mail/provider suppression, least privileges and actual TLS/shared-store attestation; environment owner + DBA + security/project sponsor | **REQUIRES OWNER INPUT/APPROVAL** |
| PALECO New Connection policy | Official forms/fields/privacy/retention/notices/catalogs/staff/signatures and eventual collection scope from Tasks 06A–06F; designated PALECO policy owners | **UNVERIFIED**; no synthetic recommendation constitutes approval |

**GO:** review this plan and repeat memory-only synthetic tests. **NO-GO:** change live Google verification/linking behavior, wire production auth returns, provision claim infrastructure, migrate actual portal, enable applicant routes or collect real data. Feature configuration remains unchanged/default-off; all existing schema guards/checksums remain intact.

## 8. Recommended next task

**Task 06H — Owner-Approved Google Identity Policy and Atomic Claim Adapter Implementation**, only after the decisions above are supplied and explicit implementation scope is approved. Implement the accepted pure policy/repository and claim interface in isolated fixtures first, including subject/email conflicts, verification denial, CAS/status races, commit uncertainty and shared-store durability. Then seek separately scoped minimal auth wiring with ordinary-user/UI/registration/recovery regressions. Keep New Connection applicant endpoints, operational data, actual migrations and collection enablement outside that change.

Stop after this security analysis, synthetic evidence and wiring plan. No production policy was silently changed.
