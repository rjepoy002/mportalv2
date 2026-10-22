# Task 06H — Isolated Google Identity Policy and Atomic Return Claims

Date: 2026-10-21, Asia/Manila. **Isolated implementation and synthetic acceptance completed; production policy and deployment NOT approved.**

The proposed policy never creates a portal account or changes portal verification. Explicit linking requires separate, fresh portal proof. The SQL claim adapter permits one committed consumer across independent PHP processes and rejects replay, unavailable storage and uncertain outcomes. Neither implementation is connected to portal controllers. New Connection remains disabled by default.

## 1. Files added and modified

Added, all under `tests/new-connection/`:

- `isolated-identity-claim.php`: CLI-only synthetic evidence, Google policy/repository, read-only compatibility inventory, SQL claims and fixture-only DDL.
- `google-policy-claim-worker.php`: independent PHP/PDO linking and claim worker with local file barriers.
- `google-policy-claim-test.php`: one random disposable MariaDB schema, synthetic identities, concurrency/failure assertions and cleanup.
- `google-policy-claim-results.json`: safe assertion labels, MariaDB version, durability setting and explicit simulation limits.
- This report.

Refreshed generated regression evidence: `auth-claim-contract-results.json`, `auth-return-integration-results.json`, `auth-return-browser-results.json`, `google-verification-review-results.json`. Existing reports remain historical evidence. No dependencies added.

**No production source, route, configuration or migration changed.** A before/after SHA-256 manifest comparison covered every file in `app`, `config` and `database/migrations`, including previously untracked foundations. The temporary manifest was removed after comparison. The workspace already contained earlier-task modifications; this task did not revert or overwrite them.

Reviewed the 06E identity specification, 06F return helper/report, 06G wiring plan and policy/claim models, actual Google callback, session helpers, module middleware and existing isolated auth harnesses. Real credential configuration was not imported or copied. No actual portal or operational schema was selected or queried by the new suite; no migration runner was invoked.

## 2. Isolated policy implementation

`SyntheticProviderEvidence` distinguishes validated OAuth state, validated exchange and provider-verified email. These are **synthetic server evidence**, not a production token verifier or values acceptable from browser input. Real integration still needs the actual state/exchange/provider validation boundary. The service neither creates a PHP authentication session nor grants staff access.

| Condition | Proposed laboratory result |
| --- | --- |
| Invalid state, exchange or provider email verification | Deny provider authentication |
| Existing bound active portal-verified user, matching email | Authenticate decision; identity eligible for New Connection |
| Bound or unbound user without portal verification | Require portal verification; leave timestamp unchanged |
| Inactive/suspended user | Deny, including after a concurrent status change |
| Stable subject with changed provider email | Resolve existing owner; require portal email review; no duplicate account |
| Subject owner and email owner conflict | Reject; never overwrite or merge accounts |
| Existing unbound verified user, any provider domain | Require explicit portal-controlled linking proof |
| No portal user, including Gmail/hosted/third-party mailbox | Require portal enrollment; no automatic account or verification creation |
| Fresh authorized link | Conditional binding update; preserve portal verification/passwords |

Provider verification, portal verification and module eligibility remain separate. A Google claim alone cannot fill `email_verified_at`. A successful identity decision is not application authorization, account linking to electric service, or staff authorization. No linked-electric-account table exists in this fixture.

### Explicit linking proof

The fixture issuer requires synthetic independent portal reauthentication and CSRF evidence, current active/verified identity and validated provider evidence. It issues a random one-time token with a fixed 120-second laboratory lifetime. Only its digest is stored, bound to actor, provider subject, provider email and synthetic session digests. The link transaction checks all bindings and current account status, consumes the proof and records a fixed safe event atomically.

The boolean proof-issuance inputs are laboratory evidence only. They must **not** become HTTP parameters in future integration. A production issuer must derive them from approved server-side reauthentication, session and OAuth/CSRF checks. This task introduces no production proof endpoint or account-link UI.

## 3. Synthetic repository architecture

The harness creates exactly one random `mco_nc_test_<16 hex>` schema per run on local MariaDB, using the existing disposable connection helper. No actual portal config or mail credentials are used. Every service constructor checks that selected schema pattern; connections use UTC, native prepares and strict SQL mode. The guarded PDO rejects operational identifiers/schema switching. Fixture SQL is InnoDB; user records are synthetic.

Fixture-only tables:

| Table | Purpose and constraints |
| --- | --- |
| `synthetic_users` | BIGINT UNSIGNED IDs; unique email and case-sensitive unique nullable Google subject; status, verification timestamp, explicit synthetic password-availability/provenance flags and binding version |
| `identity_mutex` | One conservative transaction lock serializes policy operations |
| `link_proofs` | Digest primary key; actor FK; subject/email/session digests; absolute expiry and consumption flag |
| `identity_events` | Safe actor ID and fixed `explicit_google_link` action only |
| `claim_control` | Single generation/readiness/quota/clock/recovery gate row |
| `return_claims` | Unique digest and indexed absolute expiry only |

These definitions are **not production migrations** and are not additions to the Task 04 migration chain. No existing migration/guard changed. They are deliberately separate from operational identity and application tables.

Policy operations lock the mutex, then resolve stable subject, email candidate and explicit actor with current locking reads. Binding uses `google_subject IS NULL`, active status, non-NULL verification and expected version predicates, requiring one affected row. Unique subject remains a database backstop. Proof consumption and safe audit insert share the transaction; injected failure rolls all three back. No policy SQL updates verification or passwords. The lab mutex trades throughput for simple lock ordering; production scalability/locking design needs review.

## 4. Google linking and verification acceptance

`C:/xampp/php/php.exe tests/new-connection/google-policy-claim-test.php` passed **346 assertions** on MariaDB **10.4.32**. This includes 72 behavioral/process/configuration assertions and 274 per-statement isolation checks; the count is not 346 distinct authentication scenarios.

Confirmed:

- Stable-subject authentication, changed-email review, conflicting email/subject ownership and domain-independent enrollment/link policy.
- Invalid synthetic OAuth/state/provider-verification evidence; inactive, suspended, bound-unverified and unbound-unverified rejection.
- Missing fresh reauthentication/CSRF evidence; wrong actor, session or subject; expired and consumed proof rejection.
- Conditional authorized link; unique-subject constraint rejection; injected post-link failure leaves no binding, consumed proof or audit record.
- Two independent PDO workers linking the same account create exactly one binding event. Different accounts racing for the same subject produce one link and one binding conflict.
- Status updated in a competing transaction before worker lock acquisition is observed by both waiting authentication workers. Decisions never rely on a stale pre-proof account copy.
- Every synthetic verification timestamp is byte-equivalent before/after policy decisions. The compatibility inventory leaves all user rows unchanged.

Current production behavior is tested separately by the existing 06G review suite. Its implicit verification branches remain **current behavior**, not the proposed policy above. No claim of a live Google exchange, real identity token validation or owner-approved policy is made.

## 5. Existing-user compatibility findings

The inventory reads only fixture rows and returns aggregate counts, never identities. No real account inventory was run. `password_available` and binding provenance are explicit synthetic facts; neither can safely be inferred from a production password hash or Google binding alone.

| Cohort | Risk if proposed policy is approved and wired |
| --- | --- |
| Google-only active verified, matching stable binding/email | Continues under the proposed policy; Google email changes require recovery/review |
| Previously auto-linked, currently verified | Existing timestamp is preserved; this task does not reinterpret its provenance, revoke binding or mass-reset verification |
| Previously bound but unverified | Ordinary Google authentication would now stop before session issuance; present callback can issue a session that downstream middleware rejects |
| Unbound existing account accustomed to automatic linking | Must complete explicit independent portal proof; email/domain possession alone no longer links |
| New Google-only enrollment | Automatic creation stops; approved portal enrollment/verification path required |
| Google email now differs from portal email | Stable owner is found, but login is held for approved email-review/recovery; no email-only reassignment |
| Conflicting Google binding | No account merge, unlink or transfer; independently verified support process required |
| No usable password login and unverified/changed-email identity | Could lose the last usable sign-in path; resolve recovery before rollout |

The current callback generates an unknown random password for new Google users. A nonempty hash therefore proves no usable password option. Existing OTP registration does not establish a recovery path for already-created unverified accounts; password reset does not confer portal verification. Owner-approved recovery and a minimized, access-controlled read-only inventory are prerequisites. Do not alter real passwords, bindings or verification timestamps to make tests pass.

## 6. Atomic claim adapter

`IsolatedSqlReturnClaims::claim(string $digest, int $expires, int $requestNow): bool` implements the existing return helper callback shape. `true` means acknowledged committed first claim; `false` means already consumed or expired; unavailable/uncertain/invalid conditions throw a generic runtime failure. No automatic fallback, lease extension or retry that returns navigation success occurs.

The adapter starts its own transaction, locks `claim_control`, reads database UTC time, verifies readiness/trusted generation/recovery deadline and request/database clock bounds, and rejects persisted clock regression. It checks actual row count against quota, removes at most a configured expired batch, updates quota, checks duplication **before capacity**, inserts the unique digest, increments quota and commits. Only acknowledged completion returns true. There is no replica read or session/file cache fallback. Callers must not wrap this operation in another transaction.

Laboratory defaults: 32 retained claims, 2-second maximum skew, 8-row cleanup batch and helper TTL at most 600 seconds plus configured skew. These are test values, not approved production capacity/time policy. Never evict unexpired claims when full. Cleanup and quota changes are transactional; an interrupted cleanup rolls back. The table stores no user IDs, email, raw flow, session, Google token or return URL. Identity audit metadata likewise contains no raw applicant details.

`innodb_flush_log_at_trx_commit=1` was checked. Independent new connections observe committed decisions. Physical power-loss, disk/controller guarantees, replication failover and backup-storage durability were **not** tested. Shared deployment requires a single authoritative durable writer, least-privilege DML, trusted UTC/clock synchronization, capacity monitoring and an explicit restore quarantine procedure.

### Restore and missing-history safety

Deleting a claim while retaining its quota causes a fail-closed mismatch. This cannot detect restoration of a *coherent older copy* of both rows and control data. A database cannot independently prove that it has not lost its own history.

Restores/failovers with uncertain history must therefore close the externally controlled readiness gate, invalidate old hints or drain the entire maximum flow lifetime plus approved skew, verify recovery, then attest a fresh generation and allow explicit new flows. Generation rotation **alone** does not invalidate a still-live hint submitted to a new adapter: helper records do not contain the SQL generation. The application must not promote readiness automatically from a successful SQL connection or consistent row count. The test's recovery control UPDATEs are synthetic operator actions, not a production recovery tool or bypass.

## 7. Concurrency, durability and failures

| Test | Result / evidence limit |
| --- | --- |
| Same flow, independent PHP/PDO workers | Exactly one true and one false; real local MariaDB concurrency |
| Pre-regeneration copied session | First helper take succeeds; copied context receives expired on replay |
| Independent connection retry with changed expiry | False; original expiry unchanged |
| Actual database disconnection | Killed only the fixture's own connection ID; claim fails closed |
| Unknown commit outcome | Injected exception immediately after actual SQL commit; no success returned; retry rejects committed digest |
| Failure before commit | Injected exception; rollback leaves digest consumable |
| Expiration, excessive TTL, skew, backwards database clock | Rejected; clock regression simulated with stored future high-water mark |
| Capacity exhaustion | New digest denied; no live tombstone evicted; existing digest denied before capacity |
| Interrupted/bounded cleanup | Injected interruption rolls back; subsequent two-row batch removes exactly two of five expired fixture rows |
| Missing claim row | Counter mismatch denies consumption |
| Coherent restore readiness gate / drain | Closed gate and future recovery deadline deny consumption |
| Drain expiry and fresh generation | Helper expiry boundary simulated; explicit fresh flow succeeds after simulated operator attestation; no real 602-second wait |
| Ordinary authentication during outage | No-flow helper returns ordinary without invoking disconnected claim adapter |

Lost-ack testing models an ambiguous commit using a post-commit hook, not a real network partition during COMMIT. Actual crash during cleanup, cross-host clock skew, replication restoration and simultaneous PHP session regeneration with the SQL adapter were not reproduced. The existing 06F copied-controller suite separately exercises real session regeneration using its file claim adapter. No SQL claim adapter is wired into those copied controllers or production controllers. The SQL test verifies durable replay prevention using a copied old session and independent workers.

## 8. Regression results

All commands used `C:/xampp/php/php.exe`; no real Google or email requests:

| Suite | Result |
| --- | --- |
| New policy/SQL claim suite | PASS, 346 assertions; disposable MariaDB schema dropped in `finally` |
| Syntax on all three new PHP files | PASS |
| `auth-security-test.php` | PASS, 42; password login, CSRF, OAuth state/provider checks and HTTP/server-TLS session fixtures |
| `tests/password-reset-test.php` | PASS; PDO/mail doubles and real isolated limiter |
| `tests/session-actions-http-test.php` | PASS, 22; logout/reset confirmation/session actions |
| `auth-return-intent-test.php` | PASS, 54; closed destinations and synthetic sessions |
| `auth-return-integration-test.php` | PASS, 247; copied authentication controllers, registration/OTP, recovery, session renewal and disposable identities |
| Return integration Chrome checks | PASS, 6; Chrome 155.0.8059.85, zero-account module fixture, no browser storage/external requests/runtime exceptions; no visual acceptance claim |
| `google-verification-review-test.php` | PASS, 206; 36 current callback cases, separate proposed model |
| `auth-claim-contract-test.php` | PASS, 20; preexisting in-memory contract model |
| `foundation-test.php` | PASS, 41; feature-off gate and active verified zero-account access, SELECT-only double |
| `tests/structure-test.php` | PASS, 194 |
| `tests/paleco-readonly-contract.php` | PASS |
| Protected file manifest comparison | PASS; application/configuration/migration bytes unchanged |

The reused integration harness consults read-only schema metadata for its isolation signature; no customer records or actual portal connection are used. The new suite does not query operational metadata. No applicant endpoints, migrations, notices, uploads or emails were activated. Browser screenshot/visual acceptance and actual staging HTTPS/provider testing were not requested or performed for these unwired services.

## 9. Outstanding policy and infrastructure approvals

- Authentication/security owners must approve no implicit Google portal verification, explicit linking, new enrollment behavior and changed-email/conflict recovery.
- Approve legacy verification provenance handling and a usable recovery path for Google-only/unverified/no-password users before rollout. No actual inventory counts are established here.
- Specify the production independent reauthentication proof mechanism, TTL, storage/consumption limits and audit retention. Laboratory booleans/classes are not deployable evidence issuers.
- Select claim storage ownership, dedicated schema, least-privilege account, authoritative writer and durability/backup guarantees. These fixture table names/DDL are not approved migrations.
- Approve capacity, cleanup scheduling, skew thresholds, clock health checks, fail-closed user messages, aggregate monitoring and incident procedures. Do not log digests/raw flows or identity/provider secrets in routine telemetry.
- Require external restore/failover attestation, full lifetime drain or reliable hint invalidation, generation distribution and a safe manual fresh-flow UX. Silent coherent history rollback remains undetectable without external controls.
- Retain the separate 06A–06G notice/catalog/privacy/form approvals and staging gates. This task supplies no PALECO policy approval or production readiness certification.

## 10. Exact proposed next controller-integration task

**Task 06I — Approved Authentication Policy and Return-Intent Wiring in a Disposable Controller Fixture.** First obtain recorded owner decisions for verification/recovery and the claim deployment/recovery contract. Then use a disposable copied-controller tree to replace synthetic proof booleans with validated server evidence and wire the SQL callback through the existing return helper. Keep real controllers untouched until that fixture passes.

Cover password login; registration/OTP returning to login; Google state/exchange and stable-subject lookup; explicit independent link authorization; recovery/reset without implicit sign-in or verification; expiry/logout clearing; zero-account eligibility; stale tabs/session regeneration; and claim outage/unknown outcome. Consume only the closed destination hint, capture its narrow authorized outcome around session regeneration, recheck current identity, never replay browser commands, and preserve ordinary dashboard navigation without constructing a claim-store dependency for no-flow authentication. Test the actual concurrent session-regeneration race with the SQL adapter in that fixture, and rehearse externally gated restore recovery.

Do not authorize actual controller changes, production migrations, real Google/mail traffic, New Connection enabling or applicant collection through this recommendation. A separate approved production-wiring task and staging acceptance remain necessary.
