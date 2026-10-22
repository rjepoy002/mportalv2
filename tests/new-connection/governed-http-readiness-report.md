# Task 06C — Governed HTTP Fixture and Trusted Staging Context Preparation

Date: 2026-10-20, Asia/Manila. **Synthetic/disposable acceptance only. NO-GO for real applicant collection or actual portal migrations.**

The governed Stage 1 fixture now uses the typed publication registry and v2 review-key protocol. The separate legacy compatibility fixture remains available. Create/save recovery retains exact command bytes in memory, suppresses duplicate clicks and preserves edits made during delayed responses. An internal, disposable-only staging-context preparation adapter demonstrates coherent synthetic identity/schema ownership and configured origin/base-path enforcement. It does not authenticate users or create staging routes.

## 1. Files added and modified

Added:

- `tests/new-connection/governed-http-test.php` — CLI governed-suite entry point.
- `tests/new-connection/governed-http-policy.php` — synthetic publication seeding and authorized harness-only publication changes.
- `tests/new-connection/governed-http-control.php` — private CLI publisher control, copied into the temporary fixture only.
- `tests/new-connection/governed-http-browser-test.js` — governed browser/HTTP acceptance, retaining the compatibility suite separately.
- `tests/new-connection/trusted-staging-context.php` — internal preparation adapter; accepts an existing guarded PDO and protected keyring, with no credential/connection factory or password authentication.
- `tests/new-connection/trusted-staging-context-test.php` — assertions executed inside the disposable coordinator.
- `tests/new-connection/governed-http-browser-results.json` and `governed-http-screenshots/` — recorded acceptance evidence.
- This report.

Modified:

- `tests/new-connection/stage1-http-test.php` — explicitly selected governed mode, governance migration/seed, private key configuration, lineage/isolation checks and context assertions. Default execution retains the legacy compatibility mode.
- `tests/new-connection/stage1-http-router.php` — governed-mode typed registry/keyring selection and published catalog context; compatibility branch retained.
- `tests/new-connection/stage1-http-ui.js` — shared test-only bridge: exact create/save retries, in-flight guards and delayed-response generation checks.
- `tests/new-connection/stage1-http-page.php` — explicit exact create/save retry control.

No production entry point/controller/configuration, authentication helper, workflow/persistence/submission service, database migration definition, operational business logic or compiled asset changed in Task 06C. Earlier tasks' changes already existed in the shared workspace. No dependencies were added. Temporary construction scripts were removed after use.

## 2. Governed HTTP fixture architecture

Run locally with `C:/xampp/php/php.exe tests/new-connection/governed-http-test.php`. Compatibility remains `C:/xampp/php/php.exe tests/new-connection/stage1-http-test.php`.

The coordinator bootstraps loopback MariaDB without selecting an operational database, creates a random `mco_nc_test_<16 lowercase hex>` schema, inserts six synthetic `@example.invalid` identities and applies the four existing migration dependencies. The typed registry explicitly provisions synthetic publisher ID 4 with `publication.manage` in `PUBLICATION` scope. This role assignment exists only in that disposable schema. HTTP applicant authentication never derives publisher permissions from browser input, email domain or Google identity.

Six independently versioned catalogs, four retained synthetic notices, a bundle and initial epoch are published through the existing registry. Private CLI changes use that same authorized service. There is no HTTP publication endpoint. The governed flow does not use the legacy array policy provider.

Two independent PHP CLI-server workers share the disposable database, file-backed fixture sessions and file-locked rate-limit store. Their temporary document root contains an explicit file allowlist, local assets and private fixture configuration. It contains no actual portal DB/mail configuration or dashboard bootstrap. Exact routing prevents access to PHP files, private configuration or harness controls. The source `tests` directory remains outside the intended deployed application surface and is denied by existing access controls; do not deploy the generated fixture server.

Test-only routes are unchanged:

| Route | Method | Purpose |
| --- | --- | --- |
| `/login`, `/app` | GET | Synthetic fixture login/editor |
| `/api/login` | POST | Fixed synthetic fixture login; never a staging authentication mechanism |
| `/api/create` | POST | Idempotent draft creation |
| `/api/draft?reference=…` | GET | Authorized draft resume |
| `/api/save` | POST | Validated incomplete draft with expected versions |
| `/api/review` | POST | Exact saved-snapshot review and server-issued proof |
| `/api/submit` | POST | Specialized atomic Stage 1 submission only |
| `/api/receipt?reference=…` | GET | Original authorized committed submission receipt |
| `/api/validate` | POST | Pure contract validation |

Active/verified identity comes from the server session and is rechecked against the same synthetic `users` table used by ownership FKs. Closed command fields reject applicant IDs and workflow/publication/staff fields. All POSTs require session CSRF and the exact allowlisted request origin; method, Fetch Metadata, JSON type and 96 KiB body limits remain enforced. Private responses, including errors, are `private, no-store`. Generic infrastructure errors disclose no PDO/configuration details. Session locks are released before persistence transactions so the workers can contend on actual database locks.

The typed provider shares the submission service's exact PDO/schema. Publication head locking, expected application/revision versions, canonical validation, consent retention, stage history, receipts and submission lineage remain the existing services' authority. The adapter does not duplicate or expose the generic workflow transition method.

## 3. Publication and review-key results

Chrome acceptance runs with external requests blocked. Publication mutations occur only through the private CLI control; applicant UI remains the review/submit path.

| Case | Result/evidence |
| --- | --- |
| Replacement privacy notice | Old review rejected; acknowledgments cleared; fresh review displays `TEST-replaced-http-2` retained text. |
| Withdrawal during review | Submission denied; proof/selections cleared. Explicit republication and fresh review required. |
| Same-bundle/content republication | New epoch invalidates the old proof despite unchanged content. |
| Locality catalog replacement | Independently versioned catalog adds a synthetic city; old proof rejected; explicit save updates snapshot catalog versions before fresh review. |
| Premises classification replacement | Independently versioned catalog adds a synthetic warehouse choice; same stale-proof/save/review behavior. |
| Catalog retirement | Retiring an unused catalog advances the epoch and invalidates pending review. Existing governance regression additionally rejects retiring an active bundle component until withdrawal and rejects reuse of retired components. |
| Historical receipt | Authorized receipt remains available after publication withdrawal and key revocation. |
| Current key | Normal browser submissions use v2 `k1` proofs bound to bundle and epoch. |
| Previous verification key | Authenticated HTTP submission accepts a previously issued `k1` proof under current `k2`, within a fixed issuance cutoff/verification window. |
| Revoked previous key | Uncommitted submission denied; new current-key review succeeds. |
| Unknown previous key ID | Uncommitted submission denied after key removal; fresh current-key review succeeds. |
| Expired proof | Browser submission rejected at the injected server proof clock; new review and fresh selections required. |
| Exact authorized retry after revocation | Returns original committed result without requiring the old signing key. |

Key rotation cases use authenticated HTTP commands with cookies obtained from the browser session; they are not a new key-management screen. The existing governance suite additionally tests exact expiry/cutover boundaries and bounded clock skew. The fixture clock override is private harness state, never an HTTP field.

Two random signing secrets live only in temporary private server configuration/memory. Rotation cutoffs are written once by the harness, not recomputed per request. Browser proofs contain a nonsecret key ID, binding and MAC; no signing key. Snapshot/audit/receipt checks reject retained signing-secret values. Browser storage contains only existing theme/sidebar preferences; no applicant fields, commands, proofs or keys. Application stdout/stderr are discarded by the fixture, and no applicant payload logger was introduced.

Catalog editor options are page-load context. These tests retain existing selected codes while adding new choices; viewing the added choices requires page reload. Automatic catalog refresh and removal/migration of already selected codes remain future UI work. Submission still checks the current published catalog server-side and cannot rely on the old displayed choices.

## 4. Create/save/retry/concurrency results

- Response-stage Chrome interception drops successful create/save responses **after commit**. Retry uses the same original command bytes, key, CSRF and expected versions. Browser request capture confirms identical create/save retry bytes. Double clicks produce one initial request and one explicit retry, not two initial commands.
- A separate in-memory pending create/save command blocks other mutation kinds and review until resolved. Pending commands are not written to browser storage. Failed validation/conflict/auth responses clear the failed command; network uncertainty retains it for explicit retry.
- A delayed save response cannot clear the dirty flag or replace newer edits. The saved receipt advances, while newer editor changes remain unsaved. Identity changes still require profile reconfirmation.
- Resume has an in-flight/generation guard so a late draft response cannot overwrite newer edits. The acceptance delay test directly exercises save; exhaustive delayed-resume scheduling remains additional coverage.
- Independent-worker concurrent saves yield one success and one conflict. Stale tab editing shows a focused error dialog and requires explicit resume/review.
- Save-versus-submit yields one serialized winner. If save wins, a fresh review is required before submission. Different-key duplicate submissions cannot create another revision/consent/stage event/receipt.
- Lost submission response uses the existing exact submission retry. Receipt retrieval/reload does not issue another submission.
- Session expiry requires explicit reauthentication and recovers only the last committed draft. Unsaved changes are excluded and submissions are never automatically replayed after login.
- Limits remain unchanged. The governed suite intentionally waits 61 real seconds after repeated invalid-proof tests; it neither resets nor bypasses rate-limit state to continue acceptance. Dedicated invalid-request/read-limit assertions still verify 429 behavior.

Reload after an unknown create outcome loses its in-memory command key; cross-reload discovery/reconciliation of such drafts is not implemented. A generic 5xx response may need an explicit outcome-reconciliation policy before deployment; current UI preserves exact retry on network failures. Neither limitation authorizes automatic submission replay.

## 5. Trusted staging-context preparation

`NewConnectionTrustedStagingContext` is an internal design executable under tests, not deployable staging middleware. It accepts a preconstructed guarded PDO, server-only configuration and `NewConnectionReviewKeys`. It opens no connection, accepts no password and contains no fixed fixture login. Its gate requires all of:

- Environment exactly `isolated-staging-preparation`.
- Explicit boolean `enabled=true` and `synthetic_only=true`; truthy strings fail.
- A disposable schema name matching the existing restriction, equal to the PDO's selected database.
- Native prepared statements, exception mode and strict SQL, using existing service requirements; UTC is set on the dedicated connection.
- Explicit HTTPS origin without credentials/path/query/fragment and a simple allowlisted base path. Host/forwarded headers cannot choose it.
- Existing v2 keyring, retained only as a private object.

The adapter resolves only session `user_id`, checks current active/verified status and requires a synthetic `@example.invalid` identity from the same selected schema. Session-supplied email is ignored. There is no linked-account lookup: eligible zero-account synthetic users succeed. `boundary()` compares the configured origin and uses `portalAuthCsrfValid`; `returnPath()` permits only the configured module path, replacing foreign/protocol-relative/unapproved destinations with that safe path. `service()` constructs the typed registry and specialized submission service on the same connection.

The future caller must run `portalSessionStart()` before resolving identity, enforce expiry, initialize its CSRF token, apply HTTP method/body/Fetch Metadata/rate/cache controls and use only the real server session. The context object does not create a session or turn an arbitrary client array into trusted authentication. Existing auth regression tests exercise expiry, regeneration and local HTTP/server-TLS cookie behavior. Context assertions cover missing/inactive/unverified/unknown identities, forged session email, disabled/wrong environment, schema mismatch, real-schema rejection, origin/base-path rejection, CSRF/origin rejection, safe return paths, strict/UTC state and specialized service construction.

Actual portal auth currently has hardcoded schema lookups and dashboard/post-login behavior described in [06A](staging-readiness-report.md). Do not paste synthetic numeric IDs into another database's ownership records. A later approved staging integration needs one coherent identity store or a separately specified, verified identity mapping with matching FKs. The fixture's `/api/login`, fixed passwords, clock/error controls, loopback host selection, test publisher grants and private control scripts must never be copied into staging. The context adapter deliberately rejects actual `mco_portal`; no migration/service guard was weakened and no production override exists.

## 6. Browser evidence and security/isolation checks

Environment: Windows/XAMPP PHP CLI-server, MariaDB 10.4.32, Node/CDP, headless Chrome 155.0.8059.85; two independent loopback HTTP workers. Actual staging HTTPS, reverse proxies and live OAuth were not contacted.

At 1440, 390 and 320px, both light/dark modes and all five sections were checked for horizontal overflow. Acceptance covers keyboard focus, validation summary/link focus, focused native error dialogs, saved-content review, unchecked acknowledgment requirements, edit invalidation, retry controls, session recovery and submitted-state wording explicitly distinct from application approval. No JS runtime exceptions, missing local resources, external requests or missing private-cache headers were observed in the recorded passing run.

Screenshots were opened and visually inspected, including desktop light identity, narrow dark review and mobile submission. Representative evidence:

- [Desktop identity, light](governed-http-screenshots/identity-1440-light.png)
- [Desktop identity, dark](governed-http-screenshots/identity-1440-dark.png)
- [Mobile identity, light](governed-http-screenshots/identity-390-light.png)
- [Narrow mobile review, dark](governed-http-screenshots/review-320.png)
- [Desktop conflict dialog](governed-http-screenshots/conflict-1440.png)
- [Mobile expired session](governed-http-screenshots/session-expired-390.png)
- [Mobile synthetic submission](governed-http-screenshots/submitted-390.png)

The coordinator checks exactly seven stages per application, four consents/one stage event/one submission receipt per submitted revision and one immutable submission-publication association per submission. Retained records are scanned for signing-secret leakage and audit metadata for synthetic personal-field leakage. Nonfixture schema metadata signatures are compared before/after; no customer rows are queried. SQL wrappers reject operational schema/table access. Cleanup drops only the exact randomly named disposable schema and removes the validated temporary directory. No email subsystem, upload, operational query, real applicant identity or production route participates.

## 7. Regression results

| Command/test | Result |
| --- | --- |
| `governed-http-test.php` | PASS: 269 HTTP/browser checks; 26 trusted-context assertions; seven synthetic applications/submissions, seven stages each and retained publication lineage; disposable schema removed. |
| `stage1-http-test.php` compatibility | PASS: 227 HTTP/browser checks; four synthetic applications/submissions; cleanup verified. |
| `publication-governance-test.php` | PASS: 107 assertions; independent-PDO/barrier-confirmed concurrency. |
| `draft-persistence-test.php` | PASS: 149 assertions. |
| `stage1-submission-test.php` | PASS: 97 assertions in this run. |
| `database-foundation-test.php` | PASS: 68 assertions. |
| `foundation-test.php` | PASS: 41 assertions. |
| `auth-security-test.php` | PASS: 42 assertions, isolated HTTP/server-TLS fixtures. |
| `applicant-contract-test.php` | PASS: 42 assertions. |
| `tests/structure-test.php` | PASS: 194 assertions. |
| `tests/paleco-readonly-contract.php` | PASS. |
| PHP lint / Node syntax | PASS for the added/modified PHP files and changed/new browser/bridge JS. |

Detailed browser results: [governed](governed-http-browser-results.json), [compatibility](stage1-http-browser-results.json). Early failing runs exposed synthetic test-input mistakes, the deliberately reached rate limit, and a context-test metadata query refused by the existing SQL guard. The inputs/query were corrected and the suite honors the actual rate window; no guard was relaxed. Only a fully passing final run constitutes acceptance.

Existing dashboard/account-linking/eBills/profile production controllers were not executed or modified. Their complete actual-staging browser regressions remain pending; isolated Stage 1/structure/read-only checks do not prove those live workflows. No live credentials, customer mail or provider OAuth were tested.

## 8. Read-only deployment preparation and remaining blockers

These are procedures for an authorized environment owner, not actions executed in Task 06C. No staging migration or live credential test was performed.

| Area | Required evidence before a separately approved staging rehearsal |
| --- | --- |
| Host/database attestation | Named owner signs the canonical staging origin/base path, host/network isolation, DB server/version/port, exact selected schema, synthetic identity provenance and mail-delivery suppression. Record configuration fingerprints without secret values. Verify `SELECT DATABASE()`, server compatibility, `users.id` type/engine/collation and no operational cross-schema access using read-only attestation. |
| Least privilege | Separate migration identity from runtime and publisher identities. Derive a table-by-table grant manifest from actual queries: runtime user reads current identity/catalog/history and uses only needed module DML; no operational privileges, DDL, GRANT, FILE or global administration. Immutable events/consents/receipts must remain append-only; publisher writes only explicitly needed registry records. Test negative grants in disposable rehearsal, not with real credentials. |
| HTTPS/proxy | Configure a canonical HTTPS origin/base path and trusted proxy boundary outside client headers. Existing helper bases Secure cookies on trusted server HTTPS state; verify the webserver supplies it correctly. Test Secure/HttpOnly/SameSite, redirects, CSRF origins and external return-URL rejection on actual staging TLS. Do not trust arbitrary forwarded headers or disable Secure to accommodate proxy mistakes. |
| Sessions/rate limits | Document shared session backend, cookie namespace/path, idle/absolute expiry and regeneration across nodes. Replace single-host fixture files with approved atomic shared limits keyed independently of session rotation. Verify simultaneous workers, expiry/TTL, outage behavior and no sensitive identifiers in storage keys/logs. No shared backend is delivered here. |
| Key provisioning | Approved secret manager/file permissions, distinct per-environment keys, synchronized current key ID, immutable previous issuance/verification cutoffs, emergency revocation, bounded skew, secure clock synchronization and restart/deploy behavior. Never commit/export secrets or recompute cutoffs per request. Verify exact committed receipts survive key removal. |
| Monitoring/redaction | Allowlist route/result/status/correlation ID and safe version metadata. Exclude bodies, names, email/mobile/address, cookies, Authorization/CSRF/review proofs, query references and DB/mail credentials. Review reverse-proxy/access/APM/crash logs as well as app logs. Demonstrate redaction with synthetic canaries and generic infrastructure errors. Fixture stdout suppression is not production monitoring readiness. |
| Backup/restore | DBA-approved encrypted consistent backup with triggers/constraints/ledger; capture checksums/signatures and restore into a separate isolated schema/host. Verify FK/trigger enforcement, seven stages, immutable revisions, consent/bundle lineage and receipt recovery; test key/config recovery separately. Record RPO/RTO, restore ownership, access and destruction evidence. This rehearsal remains unperformed. |
| Migration/drift/recovery | Order: foundation → draft receipts → submission receipts → governance. Preserve original PHP/SQL bytes/checksums and migration ledger `applying/ready` state. Preflight InnoDB, utf8mb4, actual MySQL/MariaDB constraint support, native prepares, strict mode and UTC. Capture schema signatures before/after; interrupted/nontransactional DDL or checksum/signature mismatch requires stop and inspected restore/reconciliation, never a blind rerun/ledger edit. Existing disposable guards stay intact; actual staging-schema migration authorization/tooling is separate work. |
| Rollback | Empty-module rollback only, in reverse dependency order, with existing guards and row checks. Once drafts/submissions/publications/history exist, do not drop immutable evidence or disable triggers to force rollback. Disable collection, preserve evidence and restore a verified backup or apply an approved forward repair. No populated destructive rollback was performed. |
| Portal auth return paths | Inventory password login, registration/OTP, forgot/reset, Google state/callback and logout paths under canonical base path. Preserve account verification and existing dialogs. Accept only server-allowlisted return destinations; do not carry unsaved submit commands through login/OAuth. Avoid dashboard bootstrap's sync/mail side effects. Actual OAuth credential/callback registration and end-to-end portal-return testing remain blocked pending authorized staging infrastructure. |

All PALECO approvals listed in [06A's policy matrix](staging-readiness-report.md) remain unverified: authoritative forms/field requiredness, privacy/lawful basis, acknowledgment wording, service-area/locality/classification catalogs, retention/holds, staff scope, correction/rejection/reopening and physical printing/signing. Synthetic publications and `TEST-EVIDENCE` never establish those approvals.

Other blockers: real coherent portal identity/session integration; actual staging TLS/grants/shared stores; approved publication/key operational ownership; dynamic catalog-refresh/removal UX; generic 5xx/unknown-outcome recovery; cross-reload unknown-create discovery; full portal/browser/provider regression; backup/restore and interrupted-migration rehearsal. The global publication-head lock's throughput is not benchmarked. Default New Connection enablement remains unchanged and off when its environment flag is unset.

## 9. Go/no-go and recommended next task

**GO:** repeat the existing loopback synthetic/disposable governed acceptance harness.

**NO-GO:** real data collection, enabling the module, live applicant/publisher endpoints, actual `mco_portal` migrations or promotion of fixture login/configuration/approvals.

Recommend **Task 06D — Approved Staging Deployment Contract and Recovery Rehearsal**: obtain named policy/infrastructure evidence; specify the actual coherent staging identity/auth return-path and dedicated strict/UTC connection contracts; rehearse least-privilege grants, shared controls, key provisioning, restore and interrupted-migration recovery in an explicitly authorized synthetic environment. Address remaining catalog and unknown-outcome recovery UX there before any live route proposal. Do not remove existing disposable guards as a shortcut. Actual deployment/migration and any applicant collection require separate explicit authorization after policy and infrastructure gates pass.

Task 06C stops at isolated integration/acceptance and staging-context preparation. No downstream application feature was implemented.
