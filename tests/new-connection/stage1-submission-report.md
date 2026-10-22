# Task 05D — Atomic Stage 1 Submission Foundation

Date: 2026-10-20 (Asia/Manila). Status: internal synthetic submission foundation implemented and tested. **No production submission is enabled.**

## 1. Files added and modified

Added:

- `app/services/new-connection/submission.php` — disposable-schema-only Stage 1 review/submission service.
- `database/migrations/20261020_new_connection_submission_receipts.php` — separate additive migration, drift signature and empty rollback.
- `tests/new-connection/submission-test-policy.php` — synthetic published test notices and explicit test-only catalog approval. These are not Task 05A placeholder legal notices or PALECO-approved production materials.
- `tests/new-connection/stage1-submission-test.php` — real MariaDB transactions, failure injection and independent-process races.
- This report.

Modified:

- `app/services/new-connection/drafts.php` — shared transaction/identity/query/receipt helpers become protected; `owned()` separates ownership/integrity locking from `locked()` editability. The draft class is extensible so the final submission class can reuse these internals. Existing public draft APIs are preserved. Its provisional 30-successful-writes/minute budget now includes `stage1.submitted` alongside draft creation/save.
- `tests/paleco-readonly-contract.php` — adds only `new_connection_submission_requests` to the explicit portal-owned write allowlist. Operational-table restrictions remain unchanged.

The Task 04 workflow implementation/table definitions, Task 05C receipt migration, authentication/session/CSRF helpers, prototype UI, dashboard, operational account/billing behavior and feature configuration were not changed. No production route calls the service. New Connection remains disabled by default. Both the new service and migration reject `mco_portal`; the service rejects that schema before touching its PDO connection.

## 2. Internal API

```php
$service = new NewConnectionStage1Submission(
    $fixturePdo,
    $disposableSchema,
    $serverOwnedPublicationProvider,
    $privateReviewKey,
    $optionalServerClock
);

$review = $service->review($authenticatedUserId, $reference, $expected);
$receipt = $service->submit(
    $authenticatedUserId, $reference, $expected,
    $idempotencyKey, $review['proof'], $selectionJson
);
```

User identity, publication provider, review key, clock and catalog context are server dependencies, never request-body inputs. The class inherits the existing draft operations and transaction machinery; normal draft persistence continues through `NewConnectionDrafts`. No new public transaction hook or generic status/approval setter was introduced.

`expected` contains exactly the Task 05C token fields: integer `application_version`, `revision_id`, `revision_no`, and hexadecimal `revision_sha256`. A submission reads the locked **saved** draft; it does not accept applicant content mixed into the final command. Applicant changes must first be validated/saved and reviewed again.

`review()` returns the complete read-only candidate snapshot, four published synthetic test notices with server-derived hashes, a review proof, and `expires_in: 300`. It performs no database writes. Completeness checking temporarily supplies confirmation values internally to validate the candidate; this is not stored consent and does not bypass the separate submission selections.

The closed selection command is:

```json
{
  "review_confirmed": true,
  "acknowledgments": {
    "privacy_notice": true,
    "information_accuracy": true,
    "application_verification": true,
    "physical_signing": true
  }
}
```

Every selection must be a JSON boolean `true`. Missing/extra codes, wrong types, malformed JSON, extra root fields and attempts to supply identities/hashes are rejected. Selection JSON and proof are separately capped at 8 KiB. Browser-provided notice versions, timestamps, publication flags and applicant IDs are not accepted.

The receipt contains only `reference`, `application_version`, `revision_id`, `revision_no`, `revision_sha256`, `stage_version`, and `status: submitted`. It contains no applicant values or review proof. Command keys are 256 random bits encoded as 64 lowercase hexadecimal characters. Receipts use a namespace separate from draft commands.

Failures use existing denial/conflict/validation/limit exceptions. Infrastructure failures propagate after rollback; a future HTTP adapter must convert them to generic responses without exposing SQL/bound values. No payload logging exists in this service.

## 3. Review proof and canonical content binding

The proof is base64url canonical JSON authenticated with HMAC-SHA-256 using an injected private server key of at least 32 bytes. Tests generate an ephemeral random key; no real deployment key was added to source, config, logs or reports. All workers in a concurrency fixture share that test key through disposable command files, which are removed afterward.

The authenticated payload binds:

- Purpose/version of this synthetic Stage 1 review protocol.
- Authenticated applicant ID, application ID/public reference and current revision ID/number.
- Application lock counter and saved canonical snapshot hash.
- Applicant contract version.
- Locality/service catalog versions and the complete catalog-content digest.
- All required notice codes, versions and content hashes.
- Server-issued time, five-minute expiration and random nonce.

Signature comparisons are constant time. Verification enforces the current binding, valid lifetime and server clock. A newer application counter invalidates an old proof even when a same-content save leaves its content hash unchanged. Replaced catalog versions/content or notice versions/content invalidate the proof; an unpublished notice prevents a new submission. A proof cannot move to another applicant/application. Altered signatures and incorrectly expected/stored snapshot hashes are rejected.

The saved draft has `review.information_confirmed: false`. Review/submission first reconstruct and fully validate its closed input with current server email/catalog, then verify that the candidate with this review flag reset still hashes to the saved draft. The submitted canonical snapshot changes this flag to true only after the separate review selection succeeds, so its final stored hash can differ from the draft hash. All applicant content remains exactly the validated saved content. A current-user email/catalog change that would change the candidate requires another save/review rather than silently replacing reviewed content.

A proof is not secret authorization, a signature, or a CSRF token. Current user/ownership is rechecked independently. No raw proof is stored in snapshots, events, audits or receipts; only a digest of the complete submission command binds it to the durable retry receipt. There is no browser-storage or PHP-session applicant persistence added here.

## 4. Synthetic notice publication and acknowledgment evidence

The provider is read again inside each new review/submission transaction. It must explicitly identify `environment: synthetic_test`, mark the catalog approved for tests, and return exactly four required notices. Each notice must be published, test-only, have a bounded `TEST-...` version and bounded UTF-8 content. The service derives the SHA-256 from those actual published bytes rather than accepting a supplied digest.

The fixture wording is intentionally a synthetic automated-test statement, not an approved privacy notice or official application text. The underlying Task 05B contract remains a draft/prototype contract. These test gates do not authorize actual applicant collection, legally effective consent, or production submission.

Each acknowledgment is inserted into the existing `new_connection_consents` table with application/current revision IDs, authenticated applicant ID, code, provider version, derived content hash and database-generated UTC timestamp. Existing unique constraints, owner/revision foreign keys and append-only triggers apply. The four online acknowledgments remain distinct from future physical handwritten signatures; no signature image, document signing or printing is implemented.

**Publication limitation:** this internal test provider is trusted server configuration, not a production publication registry. It supplies a consistent policy snapshot for each command, and replacement between review and submission is tested. Multi-server publication withdrawal racing *during* a transaction still requires a transactional/version-locked publication registry or equivalent coordinated deployment policy before production use. No production publication table or approval workflow was invented.

## 5. Atomic transaction and workflow integration

The submission class reuses the draft service's transaction ownership and lock order: active/verified user, owned application, seven stages in stage order, current revision, then command receipt. Nested transactions are refused by the inherited transaction helper. User status, application ownership, supported workflow/revision versions and snapshot integrity are checked under these locks.

For a new command:

1. Require editable lifecycle `draft`, current draft revision and Stage 1 `in_progress`, with matching revision reference.
2. Match all four expected version/identity/hash tokens and apply the shared successful-write budget.
3. Obtain current published test policy, validate complete canonical content, validate the review proof, and require all explicit selections.
4. Reuse `NewConnectionWorkflow::TRANSITIONS` for the only supported action, Stage 1 `in_progress → submitted`. Stage 1 has no earlier-stage prerequisite. Downstream stages must still be `not_started`; this command grants no later-stage approval and never starts staff processing.
5. Insert exactly four immutable consent rows.
6. Store the validated canonical snapshot/hash and freeze the revision with server submission time.
7. Update Stage 1 with its conditional expected lock counter, and append its immutable transition event using the authenticated actor, fixed safe reason and command fingerprint.
8. Set application lifecycle/submission time and conditionally increment its expected lock counter.
9. Append a safe `stage1.submitted` audit and the immutable command receipt.
10. Commit. Any failure rolls back every row/counter/history change.

No call to `NewConnectionWorkflow::transition()` is made after a separately committed save or consent insertion. That status-only foundation remains internal and unchanged; a future submission HTTP adapter must call this validated atomic path, never expose the generic workflow method to applicants.

The final service offers no target-stage/status argument, staff permission shortcut, downstream approval or reopen method. Correction/reopening remains the existing separate authorized workflow operation. Submitted snapshots remain protected by the original database update/delete triggers as well as editability checks.

### Durable retry behavior

The fingerprint binds action, authenticated actor, application reference, normalized expected-token keys, **exact proof bytes** and **exact selection JSON bytes**. Clients must retain the original command for retry; generating a new proof or reformatting JSON with the same key is different content and conflicts.

An existing receipt is returned only after current active/verified identity, ownership and current application/revision integrity checks. Matching retries return the original committed outcome even though Stage 1 is now submitted or the proof has expired. They also remain readable after later notice withdrawal; no new submission or consent is performed. Constructor initialization consequently does not require notices to remain published merely to read an authorized prior receipt. New commands still require current publication and an unexpired proof. Inactive/unverified applicants and other applicants cannot use known keys/proofs to read previous outcomes.

Different-key duplicate submissions fail editability checks. The database also enforces one receipt per application/revision. Save-versus-submit races lock the same records: either save increments the counter and makes submission stale, or submission freezes the revision and save is denied. No outcome can leave saved content with mismatched consent/lifecycle history.

## 6. Separate additive receipt migration

Migration: `database/migrations/20261020_new_connection_submission_receipts.php`.

Adds only **`new_connection_submission_requests`**, using InnoDB and utf8mb4:

| Structure | Purpose |
| --- | --- |
| `application_id`, `actor_user_id`, `revision_id` BIGINT UNSIGNED | Existing application-owner composite FK and application-revision composite FK, both with restricted deletion. |
| `(application_id, idempotency_key)` primary key | One command receipt per application/key. |
| `(application_id, revision_id)` unique key | One successful final-submission outcome per revision. |
| `request_sha256` binary ASCII CHAR(64) | Digest of the bound request; raw proof/payload is not stored. |
| `receipt_json` LONGTEXT with JSON-validity CHECK | Metadata-only original outcome. |
| `occurred_at` DATETIME(6) | Server-generated UTC timestamp. |
| `nc6_receipt_update/delete` triggers | Reject receipt update/deletion. |

There are no changes to Task 04 or Task 05C table definitions. Apply order is foundation → draft receipts → submission receipts. The new migration verifies earlier migration signatures, obtains an advisory lock, records its own `20261020_submission_receipts` applying/ready ledger row/checksum, and fingerprints its DDL/triggers. Reruns preserve data; changed/partial/unowned/drifted installations fail closed. The existing migration library refuses all non-disposable schemas, including the actual portal database.

Rollback procedure:

1. Stop all module writers and retain the feature-off state. A future approved deployment requires a protected, server-compatible backup including triggers and a tested isolated restore before migration work; no live customer-data backup was taken here.
2. For an empty matching installation, `newConnectionSubmissionRollback(PDO)` obtains the migration advisory lock, verifies its signature and zero receipts, then drops only this table/triggers and its ledger row. Earlier foundation signatures remain unchanged.
3. Reverse dependency order is submission receipts → draft receipts → foundation. The new revision/application FKs would otherwise prevent parent-table removal.
4. With any submission receipts, rollback refuses. Preserve consent/revision/history and receipts; disable routes/roll back code and plan a reviewed restore or forward fix. Do not disable immutability triggers or delete approved evidence to undo a deployment.
5. MariaDB DDL implicitly commits. An interrupted `applying` migration is not automatically repaired or marked ready. Inspect/recover against a verified backup; disposable tests can discard their exact random schema. Retention/deletion of immutable receipts/consents requires a separately approved privileged policy and hold process.

## 7. Verification results

Environment: Windows/XAMPP PHP CLI, local **MariaDB 10.4.32**, native prepares, strict SQL mode and UTC sessions. Only random `mco_nc_test_<16 hex>` databases and synthetic identities/notices/catalogs were used. No portal configuration was included, and the bootstrap connection had no selected database. The shared test wrapper rejects live/operational identifiers and foreign schema references. Nonfixture schema definitions were compared through information_schema metadata, without reading customer rows. Temporary schema/worker files were removed after runs, including failure paths.

| Test | Result |
| --- | --- |
| `php tests/new-connection/stage1-submission-test.php` | **PASS — 96 assertions.** |
| Complete canonical submission, lifecycle/revision freeze, server notice hashes/timestamps | PASS — four consents, one Stage 1 event, one submission receipt. |
| Missing applicant fields; invalid locality relationship; unapproved/replaced catalog | PASS — reject without submission writes. |
| Missing/unchecked/wrong-type/malformed acknowledgments; extra client identity/hash fields; oversized commands | PASS. |
| Unpublished notice, changed notice version or same-version content replacement | PASS — new submission denied/proof invalidated. |
| Forged/expired/oversized proof, foreign valid proof, wrong expected/stored hash, stale application/revision tokens | PASS. |
| Same-content save invalidates old proof through lock version | PASS. |
| Cross-applicant, missing, inactive and unverified identity on review/submission | PASS. |
| Exact retry after success/expiry and fresh-service replay after notice withdrawal | PASS — original authorized receipt, no duplicated records. |
| Changed payload with same key; different-key duplicate submission; inactive/unverified receipt replay | PASS. |
| Two independent PHP/PDO processes submitting different commands | PASS — one commit and one conflict; no duplicated evidence/history. |
| Two independent processes retrying the same command | PASS — identical committed result and four consent rows total. |
| Concurrent save/submission | PASS — one serialized winner with consistent revision/lifecycle/version. Both save-first and submit-first sequences also verified separately. |
| Failure after consent insertion | PASS — injected revision-update failure rolled back all consent/other changes. |
| Failure after revision freeze | PASS — injected stage-update failure rolled back freeze, snapshot and consent changes. |
| Failure immediately before receipt insertion | PASS — injected failure rolled back snapshot, freeze, consent, stage event, lifecycle/version and audit together. |
| Full application/revision/stage/event/audit/consent/draft-receipt/submission-receipt state comparison on rollback | PASS. |
| Submitted revision update, history/consent/receipt deletion, receipt update and duplicate receipt | PASS — database constraints/triggers reject them. |
| Downstream-state integrity check | PASS — no approval/prerequisite bypass. |
| Audit/receipt metadata allowlists and no raw proof in submitted snapshot | PASS. |
| Migration rerun, empty rollback, populated rollback refusal, old signatures preserved, additive drift detection | PASS. |
| Nonfixture schema signatures/no selected portal database | PASS. |
| `php tests/new-connection/draft-persistence-test.php` | PASS — **149 assertions**, including real two-process save races. |
| `php tests/new-connection/applicant-contract-test.php` | PASS — 42 assertions. |
| `php tests/new-connection/auth-security-test.php` | PASS — 42 assertions in isolated synthetic HTTP/TLS fixtures. |
| `php tests/new-connection/foundation-test.php` | PASS — 41 assertions in an isolated SELECT-only fixture. |
| `php tests/structure-test.php` | PASS — 189 assertions. |
| `php tests/paleco-readonly-contract.php` | PASS. |
| PHP syntax of new/changed service, migration, policy and submission test | PASS. |

Reproduce with `C:/xampp/php/php.exe tests/new-connection/stage1-submission-test.php`. It uses the Task 05C loopback-only `MCO_NC_TEST_HOST/PORT/USER/PASSWORD` environment convention and needs disposable-schema DDL/trigger/DML privileges. Credentials are not printed or placed in command arguments. Missing infrastructure is reported BLOCKED, with no actual-portal fallback. Production runtime must not inherit this test administrator's DDL privileges.

No browser/screenshots, physical devices, live Google/email, actual portal session-to-submission integration, staging HTTPS, MySQL 8, production grants, sustained load or backup restoration were tested for this internal-only task. Existing UI/prototype remained disconnected. Metadata signatures verify schema preservation; the absence of operational DML is enforced by connection isolation/SQL guards rather than by reading sensitive production row contents.

## 8. Remaining policy and deployment blockers

- Official privacy/acknowledgment wording, lawful processing authorization before real draft collection, publication/version ownership, authoritative locality/service catalogs, request classifications, required fields/name exceptions and official form originals remain unapproved. Synthetic `TEST-` publication is not production approval.
- Review-key provisioning/rotation/shared-node availability, clock synchronization, transactional publication locking/withdrawal policy and review display binding need deployment design and acceptance. No key was installed in production.
- Receipt/consent/draft/audit retention, legal holds, authorized purge procedures, backup/restore and duplicate-draft policies remain undecided. Append-only triggers intentionally prevent casual deletion.
- The shared service budget covers successful writes only. Future endpoints must also rate-limit invalid/read/proof requests, cap raw HTTP bodies, handle timeouts and avoid bound-parameter/request logging. Audit count indexing/load acceptance remains a separate reviewed dependency.
- No production migration adapter exists. The new service explicitly rejects `mco_portal`; supporting production requires a separately approved change after policy and deployment acceptance, not silently relaxing the disposable guard.
- The original status-only workflow command must remain internal. Staff evidence workflows and correction/reopening approval policies are still separate future work.

## 9. Recommended next HTTP integration task

First approve the real catalogs/notices and publication/retention/deployment inputs. Then build an **isolated HTTP adapter fixture** for authenticated save → private review → explicit submission, retaining the disposable schema restriction. Derive the actor from secure session middleware, enforce existing CSRF, same-origin/method/content-type controls, strict command schemas, private `no-store` headers for successes/errors and pre-service abuse limits. Never accept actor IDs, workflow targets, publication state or notice hashes from browser JSON.

Test session expiry/reauthentication, CSRF rejection, changed notices during review, stale tabs, refresh/network retries retaining original command bytes, races and generic private error responses through that boundary. Use the specialized atomic submission command; do not join independently committed consent/save/workflow operations. Only after those checks and a separately approved migration/restore deployment plan should any live routing or actual applicant submission be considered.

Task 05D stops here. No production endpoints, actual portal migration, document uploads, staff review, PMES, payment, printing or feature activation were introduced.
