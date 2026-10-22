# Task 05C — Secure Applicant Draft Persistence Foundation

Date: 2026-10-20 (Asia/Manila). Status: internal implementation complete and tested in disposable local MariaDB schemas only.

## Scope and files

Added:

- `app/services/new-connection/drafts.php` — internal transactional draft creation, retrieval/resume and save service.
- `database/migrations/20261020_new_connection_draft_receipts.php` — separately tested additive receipt-table migration, signature/drift detection and empty rollback.
- `tests/new-connection/draft-test-support.php` — loopback-only database bootstrap, disposable-schema SQL guard and metadata signatures.
- `tests/new-connection/draft-persistence-test.php` — synthetic identities, real database constraints/transactions, independent-process races and failure injection.
- This report.

Modified: `tests/paleco-readonly-contract.php` adds only `new_connection_draft_requests` to its explicit portal-owned table allowlist. Its operational-table restrictions remain intact. The initial static check correctly rejected the unlisted new table; it passes after this scoped update.

Task 04 table definitions/migration/workflow, Task 05B prototype, existing dashboard/authentication/account/billing code, production routes and `config/new-connection.php` were not changed. The persistence service is not referenced by a portal controller or HTTP entry point. No actual portal schema migration, customer-data read, application submission, notification, upload, printing or staff operation was performed. New Connection remains disabled by default.

## Draft service API

`new NewConnectionDrafts(PDO $pdo, string $schema, array $catalog)` receives dependencies explicitly. It does not load database configuration, create connections, use PHP sessions, send HTTP responses or log payloads. Schema names are restricted to the existing ownership boundary or the established random disposable-schema pattern. Exception mode, native prepared statements and strict SQL mode are required; the session timezone is UTC.

| Method | Behavior |
| --- | --- |
| `create(int $authenticatedUser, string $creationKey): array` | Creates an empty canonical Stage 1 draft, current revision, exactly seven stages, safe audit and immutable command receipt in one transaction. Stage 1 is `in_progress`; the others are `not_started`. No linked-account query is made. An exact authorized retry returns the original creation receipt, even after subsequent saves. Retrieve afterward for current versions. |
| `retrieve(int $authenticatedUser, string $reference): array` | Retrieves/resumes the current editable revision after current-user/ownership/status checks. Returns receipt fields, the canonical `snapshot`, and a closed-schema `input` suitable for editing/resending. Server-owned snapshot fields are excluded from `input`; acknowledgments and review confirmation are false. No draft/audit records are written. |
| `save(int $authenticatedUser, string $reference, array $expected, string $idempotencyKey, string $inputJson): array` | Saves a validated incomplete draft and returns its immutable version receipt. Unknown input/expected-token keys are rejected. Exact retries return the original authorized receipt without repeating writes. |

All user IDs must come from authenticated **server** context; this integer parameter is not an authentication credential. Future controllers must derive it from the valid session and existing middleware, never from request JSON. The service rechecks the selected database user's active status and non-null verified-email timestamp under lock. It does not require any electric-service account.

Receipts contain only:

```json
{
  "reference": "32 lowercase hexadecimal characters",
  "application_version": 1,
  "revision_id": 1,
  "revision_no": 1,
  "revision_sha256": "64 lowercase hexadecimal characters"
}
```

`expected` contains exactly `application_version`, `revision_id`, `revision_no`, and `revision_sha256` from the last authorized read/save. Numeric tokens must be JSON/PHP integers. The application lock counter is the monotonic edit version; revision ID/number identify the current revision, and its hash is the revision content-version token. This avoids a duplicate draft table or alteration of Task 04 revisions. Even a same-content successful new save increments the application counter, preventing stale-tab acceptance when hashes happen to match.

Creation/save keys must be independent random 256-bit values encoded as 64 lowercase hexadecimal characters. The shared per-application key namespace must not be reused between create/save commands. Public references remain random 128-bit hexadecimal values. Neither a reference nor an idempotency key grants ownership.

Failures are typed: `NewConnectionDenied`, `NewConnectionConflict`, `NewConnectionDraftInvalid` (field errors), `NewConnectionDraftLimited`, or infrastructure exceptions. Field errors are for private UI responses; do not log raw input or field paths that originated in malformed unknown-key payloads. Missing/foreign applications use the same generic denial. There is no final-submission method.

## Additive database change and compatibility

The new migration uses the existing test-schema guard, verifies Task 04's migration/checksum first, obtains a migration advisory lock, and records its own applying/ready row under `20261020_draft_receipts` in the existing migration ledger. It rejects partial installations, checksum mismatches, unowned existing receipt tables and receipt schema/trigger drift. No Task 04 DDL or checksum was rewritten.

`new_connection_draft_requests` is InnoDB/utf8mb4 with:

| Column/constraint | Purpose |
| --- | --- |
| `application_id`, `actor_user_id` BIGINT UNSIGNED | Composite FK to the existing application/owner unique key, with restricted deletion. |
| `idempotency_key` CHAR(64), binary ASCII | Primary key together with application ID; one durable receipt per command key. |
| `request_sha256` CHAR(64), binary ASCII | Fingerprint of authenticated actor, reference, expected tokens and exact input bytes for saves; a distinct command fingerprint for creation. |
| `receipt_json` LONGTEXT + JSON-validity CHECK | Only the five receipt fields above; no applicant snapshot, names, phone or email. |
| `occurred_at` DATETIME(6) | UTC command timestamp. |
| actor/time index | Supports future receipt inspection/retention planning. |
| BEFORE UPDATE/DELETE triggers | Reject receipt mutation/deletion. |

Existing application/revision/stage/audit relationships and submitted-revision immutability triggers are preserved. The original Task 04 schema signature remains identical after applying and rerunning this additive migration. Its original migration still reruns successfully with the additional ledger row/table.

The service requires the receipt migration before use. Existing Task 04 applications can be retrieved/saved with their correct current editable revision tokens, but reusing a foundation-only initialization key through `create()` without a matching draft-service receipt fails closed; call `retrieve()` with the existing reference instead. No legacy applications were backfilled or modified.

## Transaction, concurrency and recovery design

Save operations own their transaction and refuse nesting. The lock order follows the foundation: current user, owned application, seven stages in stage order, current revision, then command receipt. The current user row serializes writes across one applicant's applications and protects the status check against concurrent deactivation. Application/revision/stage row locks coordinate with workflow operations.

Before new writes, the service requires lifecycle `draft`, Stage 1 `in_progress`, a current Stage 1 revision matching the application, draft submission state, supported workflow/revision versions and an intact snapshot SHA-256. Corrections must first go through the separately authorized workflow operation that creates an editable revision; draft saves do not reopen or transition stages.

An existing receipt is checked only after current active/verified identity, ownership and editability checks. Exact matching retries return its original result before stale-version/rate-limit checks. They remain authorized reads of a prior outcome, not another write. Reusing that key with different bytes or expected tokens raises a conflict. JSON whitespace/key-order changes count as changed request content; retry clients must retain the original command bytes and tokens. A later successful save does not change an earlier receipt. A retry after submission/deactivation is denied rather than returning a receipt without current authorization.

For a new key, all four expected tokens must match. The pure Task 05B validator then accepts valid incomplete data, normalizes the mobile/address content, enforces synthetic catalog relationships, rejects unknown/server-owned keys and supplies server-authoritative email from the freshly read user. Only bounded canonical data is stored. Draft review confirmation is cleared; draft acknowledgment choices are not persisted and no consent rows are created. The canonical JSON and SHA-256 are updated together, then the application counter is incremented conditionally, a safe audit is appended, and the immutable receipt is inserted. Commit makes all of these changes visible together; any exception rolls them all back.

Snapshots are updated only while `submission_state='draft'`. Submitted revisions remain protected by the existing database triggers as well as the service checks. The digest token is not a signature or proof of identity. No PII digest is written to audit metadata; revision content hashes stay in the existing revision and restricted receipt records.

Session expiry has no database deletion hook. Missing/expired identity is denied; a newly authenticated instance can retrieve the last committed draft for the same user. Unsaved client edits are outside this service and are not recoverable automatically. The service writes no browser storage or session applicant fields.

## Security limits and future HTTP boundary

- Validator input is capped at 64 KiB before database work; the existing contract caps canonical snapshots at 32 KiB and bounds individual fields. Early malformed token/size failures do not constitute successful saves.
- A provisional limit of 30 successful create/save operations per applicant per rolling database minute spans all their drafts. It is checked under the applicant lock using the safe audit history. Authorized exact retries bypass this write budget; retrieve remains available while writes are limited.
- This is a **successful-write budget**, not a complete HTTP abuse limiter. Invalid attempts, denied requests and read traffic must also have bounded request/body limits and rate limiting at any future HTTP boundary. No such boundary exists in this task. The current audit actor/time count may need a separately reviewed supporting index/load test before scale; Task 04 indexes were not silently altered.
- Future endpoints must use existing secure session initialization/current-user middleware, enforce CSRF through `portalAuthCsrfValid()`, derive identity solely from authenticated session context, return `Cache-Control: private, no-store` for successes/errors, and return generic safe database-error responses. Add same-origin/content-type/method enforcement, expired-session reauthentication and private conflict recovery. Do not bypass these requirements by exposing this class directly.
- No raw applicant data is interpolated into service SQL or audit metadata. Audit metadata is restricted to application version and revision number; application/actor/entity IDs remain in their intended FK columns. The service has no logger or mailer. Production SQL/error logging must likewise avoid bound values, credentials, request tokens and raw database exceptions in browser responses.
- Least-privilege database grants, backup encryption/access, session-boundary acceptance and approved privacy/retention rules remain deployment requirements. Service checks do not substitute for those controls.

## Isolated verification

Environment: local **MariaDB 10.4.32**, XAMPP PHP CLI, native PDO prepares, strict SQL mode and UTC DB sessions. No portal configuration was included. The administrative connection selected **no default database**; all service/migration persistence used a newly randomized `mco_nc_test_<16 hex>` schema. Synthetic `users` has BIGINT UNSIGNED IDs, active/inactive/unverified variants and `.invalid` emails, with no linked-account table at all.

The SQL wrapper rejects known live/operational identifiers, schema switching, database creation/deletion through the service connection and foreign qualified schemas. Bootstrap creates/drops only the exact validated random fixture name. Nonfixture database definitions are compared using information_schema metadata before/after; no customer/business rows are inspected. Output contains test counts and database version, not credentials, applicant values or tokens. Worker command files contain only synthetic fixtures and are removed. Cleanup runs on success/failure, and successful runs confirmed schema deletion.

| Test | Result |
| --- | --- |
| `php tests/new-connection/draft-persistence-test.php` | **PASS — 149 assertions.** |
| Initial creation, canonical empty revision, exactly seven stages, idempotent create and original create receipt after later save | PASS. |
| Incomplete valid save, normalization, canonical hash and closed-schema resume input | PASS. |
| Unknown/forged fields, wrong types, oversized input, malformed/weak command tokens; no partial writes | PASS. |
| Owner/nonowner, unknown reference, missing/inactive/unverified user; known retry key gives no cross-owner access | PASS. |
| Stale application counter and revision identity/number/hash independently rejected | PASS. |
| Two independent PHP/PDO processes with the same expected versions and different keys | PASS — one commit, one conflict, one version increment. |
| Two independent processes with identical key/payload | PASS — identical receipts, one audit/commit. |
| Exact retry after a later save, changed bytes/tokens with same key | PASS — original receipt returned or conflict, no overwrite. |
| Injected failure on final receipt insertion after snapshot/version/audit writes | PASS — complete transaction rollback. |
| Injected failure creating Stage 4 during initialization | PASS — no application/revision/stage/audit/receipt partial rows. |
| Reauthentication with a fresh service/connection; current deactivation/unverification | PASS — saved draft survives identity loss; denied until authorized again. This models the service boundary, not a new live browser session test. |
| Noneditable lifecycle/stage, unsupported revision version, submitted snapshot/replay; database immutability | PASS. |
| Receipt uniqueness, composite owner FK, JSON CHECK and append-only triggers | PASS. |
| Shared successful-write budget, allowed read while limited | PASS. |
| Safe audit/receipt field allowlists and bound applicant values | PASS. |
| Migration rerun, empty additive rollback, populated rollback refusal, unchanged Task 04 signature, additive drift rejection | PASS. |
| Nonfixture schema metadata comparison and no selected portal database | PASS. |
| `php tests/new-connection/applicant-contract-test.php` | PASS — 42 assertions. |
| `php tests/new-connection/auth-security-test.php` | PASS — 42 assertions, synthetic HTTP/TLS/session fixtures. |
| `php tests/new-connection/foundation-test.php` | PASS — 41 assertions, isolated synthetic/SELECT-only fixture. |
| `php tests/structure-test.php` | PASS — 187 assertions. |
| `php tests/paleco-readonly-contract.php` | PASS. |
| PHP syntax: new service, migration, test/support and modified static guard | PASS. |

Reproduce with `C:/xampp/php/php.exe tests/new-connection/draft-persistence-test.php`. Defaults target local loopback MariaDB with the usual local test administrator; environment overrides are `MCO_NC_TEST_HOST`, `MCO_NC_TEST_PORT`, `MCO_NC_TEST_USER`, `MCO_NC_TEST_PASSWORD`. Only loopback hosts are accepted. Supply secrets through protected environment configuration, not command text or reports. The account needs disposable CREATE/DROP DATABASE and fixture DDL/DML/trigger privileges; production runtime must not have those privileges. Unavailable database/privileges are reported BLOCKED with no live fallback.

No browser/UI change was made, so screenshots/browser acceptance were not rerun or claimed for Task 05C. Actual staging HTTPS, MySQL 8, production deployment grants, production migration/backup restoration, sustained load and approved catalogs/notices remain untested.

## Backup, rollback and compatibility consequences

This migration library intentionally refuses the actual portal database. A future deployment adapter and migration execution require separate authorization; do not remove the guard to deploy casually.

1. Keep module writers/routes disabled. Before a future approved deployment, take a protected, server-compatible transactional backup including triggers and verify restoration in an isolated nonmail environment. Use administrator-owned credential configuration and encrypted/access-controlled storage. No live backup containing customer data was created here.
2. Apply Task 04 first and this additive migration second. DDL implicitly commits in MariaDB: the applying/ready ledger and signatures detect incomplete execution; a failed partial migration requires manual inspection/restore or a forward fix. Do not forge a ready ledger row.
3. With writers stopped and **no receipt rows**, `newConnectionDraftRollback(PDO)` drops only the receipt table (and its triggers) and its own ledger row. The empty rollback was tested and preserves the original foundation signature.
4. Roll back this additive migration **before** attempting Task 04's empty foundation rollback: the receipt composite FK otherwise prevents dropping its parent application table. The original foundation rollback does not know about this later table.
5. If any receipt exists, automatic additive rollback refuses. Preserve receipts, drafts, audit/history and FKs; roll back code/disable routes and plan a reviewed restore or forward fix. Do not drop populated tables, disable immutability triggers, or restore the entire portal over unrelated changes just to roll back this service.
6. Receipt retention must be decided with draft/audit retention. Append-only triggers and restricted FKs intentionally prevent casual deletion and can conflict with a future purge policy. An approved privileged retention/hold process must be designed separately.

## Remaining approvals and final Stage 1 submission recommendation

The Task 05A/05B catalog, classifications, required fields/name exceptions, privacy notice, acknowledgment publication/versioning, retention periods and official form sources remain unapproved. A draft is already personal-data collection; real persistence must not be enabled merely because this internal test passes. The successful-write budget, key lifetime, multiple-draft policy and retention of obsolete receipts also need operational approval. No retention duration or background cleanup job was invented.

Before final submission, approve those policy inputs and the authentication/HTTP integration, migration and restore plan. Then implement a **separate atomic submission command** that locks the same records, checks expected versions and approved catalog/notice versions, validates complete applicant input and review binding, records versioned approved acknowledgments separately, freezes the revision, appends the Stage 1 transition/audit, updates lifecycle/version and stores an immutable submission receipt together. Add races between save/submission, duplicate-submission and failure-injection tests in disposable schemas.

Do not call the existing status-only `NewConnectionWorkflow::transition()` after a separately committed save: its independent transaction could freeze content without the required consent/evidence. Deliberately design/test the shared transaction boundary before exposing submission. Pending prototype acknowledgments and `ready: true` do not authorize a real submission or physical/electronic signature.

Task 05C stops at internal draft persistence and isolated testing. No production draft routes, final submission, staff review, document uploads, PMES, payment, printing or feature activation were added.
