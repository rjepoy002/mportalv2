# Task 04 — New Connection database foundation

Completed 20 October 2026 (Asia/Manila). **Foundation implemented and validated in a disposable schema only.** No migration ran against `mco_portal` or an operational schema. No endpoint, applicant form, submission route, staff screen, upload, mail, payment or scheduling UI was added. `MCO_NEW_CONNECTION_ENABLED` remains disabled by default.

## 1. Files and migration boundary

Added:

- `database/migrations/20261020_new_connection_foundation.sql` — reviewed table DDL template, unqualified so the guarded runner can target only its selected disposable schema.
- `database/migrations/20261020_new_connection_foundation.php` — CLI-only migration library, compatibility/ownership checks, migration lock/checksum/schema signature, immutability/revocation triggers and conservative empty-installation rollback.
- `app/services/new-connection/workflow.php` — internal stage definitions/matrix, transactional initialization, scoped authorization, transition/version/idempotency checks, correction/reopening and audit persistence. No existing controller calls it.
- `tests/new-connection/database-foundation-test.php` — real MariaDB disposable-schema integration tests and independent concurrency workers.
- `tests/new-connection/database-foundation-report.md` — this report.

Modified:

- `tests/paleco-readonly-contract.php` — explicitly permits the twelve named portal-owned module tables, recognizes trigger event declarations, and excludes build/dependency caches. Operational writes remain prohibited. No blanket `new_connection_*` wildcard write exception was introduced.

Existing application/controllers/authentication, dashboard, account services, assets, navigation and feature configuration were not modified in Task 04. Prior task changes already present in the working tree were preserved.

The ownership target is **`mco_portal`**, with `new_connection_` names and existing `users` identity. For this task, the migration runner explicitly refuses every database name except `mco_nc_test_` plus sixteen random hexadecimal characters. It has no production override. Running the PHP migration file directly only defines its CLI functions; it does not connect or migrate automatically. A separately approved deployment adapter/change is required before any real portal-schema execution. Operational `nc_*`, `master`, `member`, `billhistory`, `arledger` and other PALECO tables are never migration targets or identity sources.

## 2. Verified compatibility and table relationships

Metadata-only local inspection found **MariaDB 10.4.32** and `mco_portal.users.id = BIGINT(20) UNSIGNED`. The inspected server SQL mode lacks strict mode. No user/customer rows or credentials were printed or inspected.

All applicant/actor/grantor foreign keys therefore use **BIGINT UNSIGNED**. The twelve module tables use **InnoDB, utf8mb4, utf8mb4_unicode_ci**. Public references are random 128-bit values rendered as 32 hexadecimal characters, with a binary ASCII unique index; sequential internal IDs are not public application references.

Snapshots and safe audit metadata use **LONGTEXT with `CHECK(JSON_VALID(...))`**, avoiding platform-specific native JSON storage/default behavior. Stage codes have an enforced 1–7 CHECK. The runner gates MariaDB at 10.4+ and declares MySQL 8.0.16+ as the minimum for enforced CHECK support. **Only MariaDB 10.4.32 was actually exercised; MySQL acceptance remains unperformed.** The tests directly verify invalid JSON/stage range rejection, foreign keys, triggers and transactions on the available engine.

| Table | Data and relationships |
|---|---|
| `new_connection_applications` | Unique opaque reference; applicant FK; creation request key unique per applicant; separate lifecycle; workflow version; current revision and revision number; office code; lock version; UTC timestamps. Composite `(id, applicant_user_id)` supports consent ownership. Current revision uses a composite FK ensuring the revision belongs to this application. |
| `new_connection_application_revisions` | Application/creator FKs; unique application/revision number; schema version; JSON snapshot/hash; draft/submitted state and timestamps. Composite application/revision ID key supports child FKs. Submitted rows reject all UPDATE and DELETE operations. |
| `new_connection_stages` | Application FK, stage code, independent status/version, revision FK, current decision actor/reason/time, update time. Unique application/stage; stage code limited to seven values; DELETE denied. Composite revision FK prevents cross-application evidence references. |
| `new_connection_stage_events` | Application/stage/revision lineage via composite FKs; actor; from/to status; reason; stage version; idempotency key/request fingerprint; UTC occurrence. Application/request key unique. Append-only. |
| `new_connection_audit_events` | Optional application FK; actor FK; action/entity identifiers; safe allowlisted service metadata; UTC occurrence. Append-only. No snapshots, email addresses, tokens, password hashes or arbitrary request bodies are copied into audit metadata. |
| `new_connection_consents` | Application/applicant ownership FK, application/revision FK; acknowledgment code; document version/hash; time. Unique per application/revision/acknowledgment/version. Append-only online acknowledgments, **not handwritten signatures**. |
| `new_connection_roles` | Unique module role code and description; no automatically created roles/grants. |
| `new_connection_permissions` | Unique permission code/description; no automatic grants. |
| `new_connection_role_permissions` | Role/permission pair PK and restrictive FKs. |
| `new_connection_staff_assignments` | User, role, explicit office scope, grantor/time, optional expiry; revocation actor/time/reason. Original assignment facts cannot be changed; assignments can only be revoked and cannot be deleted. |
| `new_connection_staff_assignment_events` | Assignment/actor FKs; grant/revocation events generated by database triggers; reason and occurrence. Append-only. |
| `new_connection_schema_migrations` | Migration version, source checksum, applying/ready state, schema signature and UTC applied time. No relationship to operational migrations. |

There are fifteen module triggers protecting submitted snapshots, stage/history/consent/assignment retention and assignment grant/revocation history. All relationships use restrictive deletion; there is no cascading removal of approved history. Monetary/payment tables are not introduced. Future amounts must use appropriate **DECIMAL** columns, not floating point.

The current-revision FK is added after both applications and revisions exist, resolving their circular relationship. Empty rollback removes that FK before dropping child tables. No foreign-key-check disabling is used.

All module write timestamps use `UTC_TIMESTAMP(6)`; the service sets its connection timezone to UTC. Future UI/report rendering must convert explicitly to **Asia/Manila**. No display UI was added here. The service requires strict SQL mode, refusing a nonstrict connection. Disposable connections enable strict mode only for their own sessions; existing portal configuration/global SQL mode was not changed. Before future wiring, provision a module connection/session with strict mode rather than silently altering unrelated legacy connection behavior.

## 3. Workflow state model

`NewConnectionWorkflow::STAGES` defines exactly:

1. Applicant Information and Submission.
2. Document Submission and Verification.
3. Billing Clearance.
4. Online PMES.
5. Technical Inspection.
6. Payment and Handwritten Form Signing.
7. Installation Scheduling.

`initialize(actor, creationKey)` is an **internal foundation operation**, not a draft-saving/submission endpoint. It revalidates the active verified identity, locks its user row, creates an application, an empty versioned draft snapshot and exactly seven stages, assigns the current revision, writes initialization audit and commits them together. Stage 1 starts `in_progress`; 2–7 start `not_started`. A failure halfway through rolls back application, revision, stage and audit writes. Repeating the same actor/creation key returns the existing reference. No applicant information fields or arbitrary snapshot-writing API were implemented.

The service owns this seven-row initialization invariant. Database uniqueness/range/deletion constraints prevent duplicate/eighth/deleted stage rows, but SQL alone cannot require seven child rows while the initial transaction is still constructing them. Runtime credentials and all future callers must use the service, not bypass it with direct application/stage INSERTs.

| Current stage status | Ordinary next statuses |
|---|---|
| `not_started` | `in_progress` |
| `in_progress` | `submitted` |
| `submitted` | `under_review` |
| `under_review` | `approved`, `needs_action`, `on_hold`, `rejected` |
| `needs_action` | `in_progress` |
| `on_hold` | `under_review` |
| `approved` / `rejected` | No ordinary transition; explicit authorized reopening only. |

Each stage retains its own status and version. All earlier stages must be approved before a later stage advances. A progress indicator is never consulted. Applicant owners can submit their own stage-1 draft or begin their own requested stage-1 correction; they cannot approve, review, reject, reopen approved decisions or change another applicant's state. Other transitions require scoped stage-management permission. Even stage submission through this library is not exposed by any route.

Application lifecycle is separate: `draft`, `submitted`, `processing`, `scheduled`, `rejected`, `withdrawn`. Initialization/correction is draft; stage-1 submission is submitted; review/processing is processing; rejection records rejected; stage-7 approval yields scheduled. Withdrawn is reserved for later policy/API work; no withdrawal method exists. Scheduling does not mean installation/energization occurred.

On stage-1 submission the current snapshot is frozen. A requested correction or authorized stage-1 reopening creates a new draft revision copied from the old snapshot, never overwriting the submitted revision. Reopening another approved/rejected stage retains the application revision. Authorized reopening requires both that stage's management permission and `workflow.reopen`, a reason and the expected current version. All downstream statuses are reset to `not_started`, their current decision metadata is cleared, versions increment, and each invalidation is appended as a new history event. Old approvals and their revision references remain intact.

The foundation validates **status prerequisites and authorization**, not actual document/billing/PMES/inspection/payment/scheduling evidence. Its fully synthetic seven-stage test does not perform those operations. Stage-specific evidence validators must be added before any future approval/submission endpoint is enabled. The library must not become a generic staff-controlled bypass around those future checks.

### Concurrency and duplicate requests

- Each transition starts a transaction, revalidates actor state, locks the application/stages, checks current assignment scope/expiry/revocation, and requires the expected stage version.
- A version-qualified UPDATE must affect exactly one row. Stale competing decisions fail with `NewConnectionConflict`.
- A 256-bit command key is unique within an application; its fingerprint binds actor, application, stage, target, expected version, reason and reopen flag. Exact authorized replays return the prior outcome; reuse with changed input is rejected. Replay does not reapply state/audit effects and does not restore revoked staff access.
- Transition, invalidation, immutable history, lifecycle/version changes and safe audit commit atomically. Deadlocks/timeouts/errors roll back and propagate; future callers must safely reload/retry rather than silently overwrite or invent success.

## 4. Staff authorization foundation

Default is deny. Migration creates **no roles, permissions or staff assignments**. Test grants are explicit synthetic SQL in the disposable schema only. No email domain, Google identity, account link, operational `nc_users` record or applicant status grants staff access.

Internal permission vocabulary is `stage.1.manage` through `stage.7.manage`, plus `workflow.reopen`. An effective assignment requires a current active, email-verified user; the exact requested permission; its application's office or an explicitly provisioned `*` scope; a grant already in effect; no revocation; and no expiry. Permissions are checked on each command and on replays. Provisioning a role with stage permissions does not imply reopen permission.

Applications currently start with server-owned office code `UNASSIGNED`. There is no applicant-supplied office parameter or office reassignment API. Offices, assignment routing, wildcard authority and separation of duties require PALECO policy before staff UI/provisioning work. Office codes deliberately do not assume an existing office table or operational department ID mapping.

Assignment rows preserve original user/role/scope/grant facts. Revocation writes an immutable event automatically; deleting or widening the original assignment is rejected. Multiple explicitly provisioned assignments may exist, so revoking access requires reviewing all effective grants. Role/permission administration and its change-audit/provisioning procedure are not exposed in this task. Future runtime DB credentials should have SELECT-only access to authorization catalogs/assignments and users; a separately controlled provisioner manages grants.

Actor IDs supplied to the internal service must come from the authenticated server context, never a client form field. New routes will also need the existing session/CSRF boundary. Database triggers enforce retention within the application security boundary; they cannot prevent a privileged database administrator from dropping triggers or altering tables. Runtime grants must prohibit that authority and UPDATE/DELETE on append-only history.

## 5. Validation results

Executed `php tests/new-connection/database-foundation-test.php`: **PASS — 68 assertions** on the actual local MariaDB engine.

The test creates a random, validated disposable schema; its `users` table contains synthetic identities only and matches the verified unsigned bigint key. All fixture SQL passes through a wrapper that refuses live-schema references and database switching. Only the harness's explicit validated CREATE/DROP of its own test schema uses the original bootstrap connection. Independent concurrency workers use the same guarded schema rule. No production/operational data fallback exists if CREATE DATABASE is unavailable.

Verified:

- Initial migration, safe repeat, repeat after workflow data and complete empty rollback/reapply.
- Twelve InnoDB/utf8mb4 tables, fifteen expected triggers, and stable schema signature.
- No staff provisioning by migration.
- Live-schema migration guard rejects the live connection before DDL.
- Exactly seven transactional stage rows; opaque reference format; duplicate references/stages rejected.
- Applicant FK, 1–7 range CHECK, JSON validity, composite cross-application revision FKs and consent ownership/uniqueness.
- Inactive/unverified identity denial; other applicant mutation/owner approval denial; matrix/prerequisite enforcement.
- Submission/correction idempotency and conflicting-key rejection.
- Frozen submitted revisions reject UPDATE/DELETE; stage, audit, consent and assignment histories reject mutation/deletion; parent deletion cannot erase history.
- A real **two-process concurrent approval race**: exactly one command commits and one returns a version conflict; exactly one approval event is written.
- Corrections/reopen create a new draft revision where required; all downstream approvals are invalidated without deleting old events.
- Explicit office scope, grant start, expiry, revocation, current disabled-user checks and separate reopen permission.
- Full synthetic status-only path through all seven stages; final application lifecycle becomes scheduled independently of stage status.
- Injected stage-4 initialization failure rolls back application/revision/stage/audit counts.
- Populated rollback and schema-drift rerun refuse destructive repair.
- Before/after metadata fingerprints of existing `mco_portal` and operational schemas match. No business rows were queried to create these fingerprints; test actions sent no DML to those schemas. This is not a claim about unrelated concurrent external activity.

The disposable schema is dropped in a guarded `finally` cleanup after tests, including the synthetic rows/history. That cleanup is specific to the explicitly created test database, not the application rollback policy.

Other checks:

| Check | Result |
|---|---|
| New service, migration library and database test PHP syntax | PASS. |
| `tests/new-connection/foundation-test.php` | PASS — 41 assertions; no-account applicant and feature gate intact. |
| `tests/new-connection/auth-security-test.php` | PASS — 42 assertions, synthetic identities/mail/OAuth. |
| `tests/structure-test.php` | PASS — 181 assertions. |
| `tests/paleco-readonly-contract.php` | PASS, operational writes still prohibited. |
| `git diff --check` | PASS; only configured line-ending normalization notices. |

No browser test was needed for this internal-only change. MySQL 8, production grants, actual staging deployment, backup restoration and PALECO policy/evidence validation were **not** tested and are not claimed to pass.

## 6. Backup, rollback and migration failure procedure

No live backup was created because that would copy sensitive existing records unnecessarily. The following is a **future deployment procedure**, not an action executed in this task:

1. Obtain approval for the production/staging migration adapter and scope. Keep the feature off and freeze new-module writers during schema work. Confirm the server version, strict-mode session policy, users key/engine and least-privilege grants. Use a privileged migration identity distinct from the runtime identity.
2. Use the server-matching `mariadb-dump`/`mysqldump` with `--single-transaction --routines --triggers --databases mco_portal` and an administrator-owned credential option file outside the web root. Never put a password in a command, report or repository. Store the backup outside the web root with restricted permissions/encryption, checksum it and record provenance. Avoid concurrent DDL while capturing it.
3. Restore that backup into an isolated, nonmail/nonjob environment and verify table definitions, data consistency, FKs and triggers. Do not regard a dump file's existence as proof of recoverability. Protect/erase restored sensitive material under the approved retention procedure.
4. Review migration checksum and table list. DDL implicitly commits on the tested server; **the whole migration is not transactionally reversible**. An advisory lock prevents concurrent copies of this migration; the applying/ready ledger and schema fingerprint detect incomplete/drifted installations. Applied reruns make no schema changes; partial/checksum/drift failures stop for manual recovery and never auto-ALTER existing tables.
5. For an **empty disposable installation**, `newConnectionRollback(PDO)` verifies the test-schema boundary and absence of rows in every module data/catalog/history table, drops the circular current-revision FK, then drops only module tables in FK-safe reverse order. This path was tested. The fixture `users` table is retained until the entire disposable schema is cleaned up by the harness.
6. If applications, consents, grants or any history exist, automated rollback refuses. Disable module entry points and preserve the additive tables/data. Review a protected module backup/export and a controlled restore/forward-fix plan with the owner; do not delete approved history, disable FK checks or drop populated tables merely to roll back code. A full portal restore can overwrite unrelated portal changes and needs a separate recovery decision/maintenance window.
7. An interrupted/partial DDL installation requires inspection against the manifest and a verified backup. Do not edit the ledger to pretend success. In disposable tests, drop/recreate only that explicitly named test database. Production recovery remains separately approved because this runner cannot target production.

## 7. Policy decisions and remaining blockers

- Approve the exact stage matrix, rejection/withdrawal/reopening authority, whether a correction invalidates every downstream decision, and lifetime/expiry rules. Current implementation takes the conservative all-downstream-invalidated approach.
- Define stage-specific evidence requirements before wiring staff decisions: document verification, billing clearance, PMES rules/results, inspection findings, offline receipt and handwritten-signature evidence, installation-slot reservation. None exists yet.
- Confirm office routing for new applications, supported office codes, wildcard assignment authority and reassignment history. Current `UNASSIGNED` is a controlled placeholder.
- Approve explicit role provisioning/revocation, separation of duties, grant start/expiry, wildcard scopes, role-permission change auditing and emergency access. No staff UI or provisioning endpoint exists.
- Approve applicant snapshot schema versions/fields, acknowledgment versions and privacy/retention policy. Empty JSON snapshots here are placeholders, not an approved application-data contract. Handwritten signatures remain separate future records.
- Provision a strict-mode UTC module DB session and appropriate grants before using the service. The existing local portal SQL-mode configuration was intentionally not altered.
- Production migration/backup/restore acceptance and MySQL compatibility remain unperformed; use a separately approved adapter, do not remove the test-schema guard casually.
- Remaining Task 03A real staging HTTPS/Google/database-backed acceptance still applies. No application-data collection is authorized by this report.
- No notification outbox, uploads, assessment, technical/payment/scheduling operational tables or monetary fields are included. Those require subsequent explicit tasks.

## 8. Recommendation for Task 05

Prepare the approved applicant-data/consent contract, stage evidence interfaces, office/role provisioning policy and controlled staging migration/restore acceptance plan. Validate those in a disposable staging copy with explicit permissions and strict SQL mode before adding applicant endpoints. Keep production migration and applicant-form activation as separate approval gates. Do not use the status-only foundation as evidence that substantive clearance/payment/PMES requirements have been implemented.

Task 04 stops here; the feature remains off and no applicant forms/submission endpoints have begun.
