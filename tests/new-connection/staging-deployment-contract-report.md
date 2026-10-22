# Task 06D — Staging Deployment Contract and Recovery Rehearsal

Assessment date: **2026-10-21, Asia/Manila**. Scope: authorized local disposable databases, synthetic identities, isolated HTTP/browser fixtures, and future staging contracts. **NO-GO for actual staging deployment or real applicant collection.** No actual staging hostname, credentials or approval evidence was supplied.

## 1. Files inspected and modified

Reviewed Task 01–06C reports and implementation evidence; `config/app.php`, the New Connection feature flag/landing controller/middleware, session/CSRF and rate-limit helpers, password login/registration/Google callbacks/reset/logout controllers, password-recovery service/tests, dashboard bootstrap, module contract/workflow/draft/submission/publication/keyring services, all four guarded migrations, trusted-context tests and HTTP/browser fixtures. Dashboard inspection was source-only: its synchronization/notification functions were not executed. Credential-bearing production configuration was not imported into any fixture.

Added:

- `tests/new-connection/staging-recovery-test.php` — disposable privilege, encrypted backup/restore, immutable-evidence and interrupted-migration rehearsal.
- `tests/new-connection/staging-recovery-results.json` — safe assertion/timing/digest evidence, without secrets or applicant payloads.
- `tests/new-connection/staging-recovery-http-test.php` — separate governed recovery-browser suite entry point.
- `tests/new-connection/staging-recovery-browser-test.js`, `staging-recovery-browser-results.json`, `staging-recovery-screenshots/` — acceptance and visual evidence.
- This report.

Modified only test/documentation files:

- `stage1-http-test.php` — selects the separately invoked recovery suite; retains existing compatibility/governed entry points.
- `stage1-http-router.php` — private, authenticated fixture-only owned-application discovery and current published catalog reads.
- `stage1-http-ui.js` / `stage1-http-page.php` — explicit recovery discovery, catalog refresh/correction, and exact command retention after uncertain 5xx responses.
- `governed-http-policy.php` — authorized private CLI synthetic catalog rename/removal cases.
- `staging-readiness-report.md` — dated policy-inventory update pointing to the matrix below; historical findings remain identified as historical.

No application controller, production route, production authentication behavior, feature configuration, migration definition, schema guard or operational business logic changed. No new dependencies. Existing local CSS/icons are reused.

## 2. Staging environment contract

This is a proposed contract, **not an attestation or deployable configuration**. Missing actual values remain unset; synthetic `.invalid` fixture values must not fill them.

| Contract field | Required authoritative value/evidence | Current status and fail-closed rule |
| --- | --- | --- |
| Authorization | Named environment owner, authorized scope, synthetic-only data declaration, expiry/review date, stop authority and approval evidence | **UNSET/BLOCKED** for actual staging. Task 06D authorizes the local disposable rehearsal only. |
| Host/origin | Owner-approved canonical HTTPS origin, certificate, DNS/network isolation and proxy topology | **UNSET/BLOCKED**. Never derive canonical origin from Host/Forwarded headers. |
| Base path | Approved application-relative base path and callback/return path inventory | **UNSET/BLOCKED**. Existing `APP_URL` must include the intended base path; no query/fragment/userinfo. |
| Environment | Explicit PHP-process environment and independently gated module environment/feature state | Existing `config/app.php` reads `APP_ENV`; `staging` is treated as requiring production-style URL validation. Local Apache `MCO_APP_ENV` is a different setting and must not be mistaken for `APP_ENV`. No dotenv loader is assumed. |
| Feature gate | Explicit owner-approved future rehearsal enablement, checked before session/connection/writes | `MCO_NEW_CONNECTION_ENABLED` remains default-off. Existing landing-only controller is not the Stage 1 HTTP adapter. Preparation adapter separately requires exact environment and boolean synthetic/enabled settings; no production override was added. |
| PHP | Supported deployment runtime, required extensions, release/patch ownership | Local evidence: PHP **8.2.12**, PDO MySQL/OpenSSL/JSON and existing dependencies. This version is evidence, not approval of a staging patch baseline. Actual staging compatibility/maintenance approval remains pending. |
| Database | Exact approved host/port/server version/schema, InnoDB, utf8mb4, `users.id` type, constraint behavior and selected-database assertion | Local evidence: **MariaDB 10.4.32**, BIGINT UNSIGNED synthetic users. Existing guards require MariaDB >=10.4 or MySQL >=8.0.16. Actual staging and MySQL-specific execution are **BLOCKED/unperformed**. |
| Identity ownership | One authoritative staging identity store with matching application FKs, synthetic provenance and controlled active/verified state | Disposable fixture has one coherent store. Actual portal helpers hardcode `mco_portal.users`; there is no approved actual-staging adapter/mapping. Never copy numeric IDs into an unsynchronized second store. |
| Module connection | Dedicated PDO, explicit schema, native prepares, exceptions, utf8mb4, UTC and strict SQL before use | Tested locally. Do not include actual portal DB/mail config or change legacy/global SQL mode. Validate selected schema before any migration/service call. |
| Credentials | Separate migration, runtime and publication secret references, approved grants and secure provisioning | Locally tested with three random disposable DB accounts; all removed. Actual secrets/manager/attestation are **UNSET/BLOCKED**. No secret values belong in the contract/report. |
| Strict SQL/time | Explicit strict SQL session, no zero-date acceptance, UTC `+00:00`, synchronized server clocks | Tested dedicated local sessions; actual host time synchronization **BLOCKED**. Display timestamps in Asia/Manila at presentation boundaries. |
| Mail/provider isolation | Deny live mail and external provider traffic by default, approved sink and synthetic recipients; separate OAuth registration if later authorized | Fixtures contain no mailer/config and block external browser assets. Actual mail sink/provider staging configuration **UNSET/BLOCKED**. Never reuse production mail/Google credentials. |
| Publications/keys | Approved publication provenance/version hashes and separate protected review-key provisioning/rotation | Synthetic registry/keyring tested only. Production policy, publisher appointments and secret operations remain unapproved. No synthetic fallback. |

The existing preparation adapter intentionally accepts only `mco_nc_test_<16 lowercase hex>`, synthetic identities and the exact preparation environment. It rejects actual `mco_portal`. A future actual-staging design needs a separately approved implementation plan; this task does not relax those restrictions.

## 3. Authentication integration design

Reuse the portal's successful authentication and session helpers, **not the dashboard controller**:

1. Authenticate through existing password/verified registration or approved Google workflow. Regenerate the session with `portalAuthenticated()` as today. Session remains the identity authority; browser applicant IDs, email claims or staff flags are ignored.
2. At a future module boundary, check environment/feature first, run `portalSessionStart()`, enforce current active and email-verified status from the same authoritative identity store used by module FKs, then construct the dedicated module connection/service. No account-link or billing prerequisite is permitted.
3. Use `portalAuthCsrf()` / `portalAuthCsrfValid()` for state-changing requests, exact configured origin/Fetch Metadata checks, closed JSON commands, size/rate limits and private no-store responses. Expired authentication requires explicit login and recovery of committed drafts only; never replay a submission automatically.
4. Use a server-controlled allowlisted intent such as the module landing path, retained server-side through login/verification/recovery. Do not accept arbitrary absolute/protocol-relative return URLs, encoded traversal, browser workflow state or an unsaved command as a return intent. Existing preparation `returnPath()` allows only the configured module path; its synthetic assertions pass.
5. Keep OAuth state one-use, session-bound and expiring; keep verified-email and active-user checks. A return intent must not replace OAuth state or weaken account-link collision handling.

| Existing path | Current source behavior | Future staging requirement |
| --- | --- | --- |
| Password login | Successful sign-in redirects `dashboard.php` | Separately approved return-intent handling should go directly to the module for that intent; preserve dashboard default for other users. Not changed here. |
| Registration/OTP | Verification completes then returns to `index.php?registered=1`; registration/resend can send email | Preserve verification; use only a staging mail sink and synthetic recipients. Carry only safe server intent, then require normal login. Actual OTP/browser mail acceptance remains blocked. |
| Google login/callback | State validation, verified Google email, active portal user; success redirects dashboard | Separate staging client/callback registration, exact canonical base path, same safe intent after successful auth. No live provider contacted. |
| Forgot/reset password | Token checks and reset confirmation; successful reset returns to sign-in | Preserve token/session/confirmation behavior; do not append secrets to return URLs or turn password reset into an automatic submission/login. Tested with PDO/mail doubles. |
| Logout | POST/CSRF and existing confirmation flow | Preserve existing behavior. No module command replay after next sign-in. Isolated session-action tests pass. |

Source blockers remain: actual module/current-user helpers name `mco_portal.users`; actual password/Google redirects route through a dashboard that is localhost-restricted and invokes legacy eBills sync/link notifications/mail. Shell reuse alone does not remove these effects. The fixture's fixed passwords, user selector, private control scripts and clock overrides must never become deployable authentication. No actual auth redirect or middleware was modified.

Synthetic evidence: auth security 42 assertions, password-recovery PDO/mail-double regressions, 22 logout/session-action assertions, trusted-context 26 assertions inside HTTP suites, active/verified/ownership and zero-linked-account browser cases. These do not establish live Google, actual registration email delivery or complete portal staging acceptance.

## 4. Database privilege and migration rehearsal

Command: `C:/xampp/php/php.exe tests/new-connection/staging-recovery-test.php`.

Only random, registered disposable schemas on loopback were created. Bootstrap used test-only credentials without a default selected DB; no actual portal configuration was loaded. Three random accounts scoped to the disposable schemas were created specifically for the authorized privilege rehearsal, then dropped. This changes temporary test-account grants, not PALECO operational tables or existing accounts.

Migration order, unchanged:

1. `20261020_new_connection_foundation.php` / `.sql`.
2. `20261020_new_connection_draft_receipts.php`.
3. `20261020_new_connection_submission_receipts.php`.
4. `20261020_new_connection_governance.php` / `.sql`.

| Disposable identity | Granted/tested boundary |
| --- | --- |
| Migrator | SELECT/INSERT/UPDATE/DELETE/CREATE/ALTER/DROP/INDEX/REFERENCES/TRIGGER on one disposable target only; applies all four migrations without a global administrative grant. No runtime connection uses it. |
| Runtime | SELECT on synthetic users/module tables; INSERT on applications/revisions/stages/stage-events/audit/draft receipts/submission receipts/consents/submission lineage; UPDATE on applications/revisions/stages only. Creates, saves and atomically submits using the real services. DDL, evidence deletion and publication-head updates denied. |
| Publisher | SELECT on synthetic users/module tables; INSERT on immutable registry records and audit; UPDATE on publication head. Scoped DB permission plus explicit active/verified `publication.manage`/`PUBLICATION` assignment is required. Can publish; applicant-row updates denied. |

This is a tested minimal working separation for these queries, **not a final approved grant manifest**. Narrow SELECT by actual query/table/column needs before deployment; define credential issuance, rotation, emergency revocation and publisher separation-of-duties policy. Migration-created trigger DEFINER ownership/privileges need an approved durable staging principal. The synthetic dump retains local definers, which worked on this host; portable definer migration is not approved or solved here.

No actual operational-write attempt was made. The guarded PDO rejects operational names, and a runtime connection to an unrelated disposable schema was denied as the cross-schema operational-boundary stand-in. Runtime and publisher credentials never receive operational grants. Existing operational-schema metadata signatures were compared before/after without querying customer rows.

The rehearsal passed **40 assertions** in the recorded run: native prepared statements/strict UTC sessions; runtime resume/save/create/submit without linked accounts; DB/application publisher separation; source/restore hashes; four migration checksums/signatures; restored acknowledgments/lineage/receipts; immutable deletion/update rejection; restored FK enforcement; actual partial-DDL interruption and schema-drift refusal. [Machine-readable results](staging-recovery-results.json) include exact timings and assertions, with no credentials.

Any future missing CREATE USER/grant/tool privileges are recorded as blocked rather than bypassed. The first exploratory grant run reported a blocked credential step; subsequent complete reruns succeeded. The final evidence has an empty `blocked` list; no restriction was weakened to obtain it.

## 5. Backup, restore and interrupted-migration recovery

Synthetic source contains one incomplete draft and two submitted applications (the second verifies least-privilege runtime submission). Backups include all synthetic users/module data, ledger, FKs and triggers; no live backup was used.

Procedure executed:

1. Quiesce the synthetic source after transactions complete; compute a deterministic digest over every table's data.
2. Use local `mysqldump --no-defaults --single-transaction --triggers`, restricted to the exact disposable source. Tool passwords come from test-only process environment, not command-line arguments or copied portal credentials. Child errors are withheld from reports.
3. Encrypt dump bytes using AES-256-GCM, random in-memory key/nonce and authentication tag, retaining ciphertext digest/size only in results. Signing keys are separate from the backup. Short-lived plaintext exists only in the current user's temporary directory during capture/import and is immediately removed; synthetic artifacts are deleted at completion. Actual staging needs verified NTFS ACLs/secret custody, not reliance on PHP `chmod` on Windows.
4. Restore into a different disposable schema with local `mysql --no-defaults`. Standard dump loading restores FK session settings; the post-restore test verifies FK checks are enabled and orphan updates fail.
5. Rerun the unchanged guarded migration chain. It validates all four ledger checksums and schema/trigger signatures. Compare all-table digests and each revision SHA-256; retrieve original receipt and draft through the actual services.
6. Check four consent rows per submitted revision, publication lineage and immutable triggers. Submitted snapshot rewriting and audit mutation are refused, as are evidence deletions.

Recorded timing: encrypted capture **0.322 s**; clean restore/verification **1.347 s**; interrupted-target rebuild/verification **1.694 s**. Exact capture size/hash/timing are in the JSON evidence; these tiny local synthetic results are not an RTO/RPO promise or staging capacity benchmark.

Interruption was injected by a test PDO wrapper that forwards to the guarded disposable PDO and throws on the second governance CREATE TABLE. This leaves a real first table and `applying` ledger row. Rerunning unchanged migrations refuses the incomplete state. Recovery destroys/recreates **only the exact disposable target registered by this harness**, imports the verified synthetic backup and revalidates all signatures/data. No migration source, guard, checksum or ledger state is manually edited. A later added drift column also causes rerun refusal.

This destructive target rebuild is safe here because all identities/data/targets are disposable and explicitly authorized. It is **not a procedure to drop populated staging or production data**. For a real incident: stop collection, preserve target/ledger/log evidence, have the DBA inspect partial DDL, and choose an approved restore or forward repair. Populated immutable history prevents forced rollback. Empty-module rollback remains reverse dependency order under existing checks; no guard bypass or trigger disabling was introduced.

All three disposable schemas, three random test DB accounts, plaintext/ciphertext backups and temporary directory were removed. No reusable backup or encryption key is retained. Actual staging backup custody, separate-host restore, disaster key recovery, permissions, RPO/RTO and interruption recovery remain **BLOCKED pending infrastructure/owner authorization**.

## 6. Shared security infrastructure requirements

| Control | Local evidence | Actual staging gate |
| --- | --- | --- |
| HTTPS/proxy trust | Auth suite checks local HTTP and isolated server-TLS cookie behavior; helper trusts webserver HTTPS state, not client forwarding headers | **BLOCKED:** canonical host/certificate/proxy not supplied. Trusted proxy must strip untrusted forwarding headers and set server TLS state correctly. Verify Secure/HttpOnly/SameSite Lax, redirects/origin/base path; do not weaken cookies for proxy convenience. |
| Shared sessions | Two independent loopback workers use the same private session directory and recognize the same authenticated synthetic sessions; session lock released before DB transactions | File storage is same-host only. Approved atomic shared store, namespace, permissions/encryption, fail-closed outage behavior, TTL/regeneration/expiry across nodes and operational ownership remain **BLOCKED**. |
| Shared rate limits | Fixture uses file locking outside sessions; recovery suite submits concurrent reads through independent workers against the same real limit; existing invalid/read and write/service limits retained | No approved distributed limiter is deployed. Require atomic increments/expiry, stable trusted client identity, bounded safe account/IP keys and failure policy; no cookie-rotation reset. Tests honor the real 61-second window rather than clearing limiter state. |
| Review keys | Existing bounded v2 keyring, fixed previous cutoffs, revocation, unknown key and original-receipt tests retained | Secret manager, cross-node synchronized cutover/withdrawal, access audit and emergency procedure **BLOCKED**. Never recompute a previous verification window on each request. |
| Clock | UTC database sessions tested; proof/session expiry can be injected only by private synthetic harness | Actual NTP/time authority, drift alerting and approved skew tolerance **BLOCKED**. No real host clock changes performed. |
| Caching/errors | Private/no-store on authenticated reads and errors; generic infrastructure responses; CSP/local assets maintained | Verify proxy/CDN/service-worker behavior on actual staging; private API responses must never enter shared caches. |
| Monitoring/redaction | No applicant payload logger added; retained metadata/secret checks and private fixture errors remain safe | Approve structured allowlist: action/result/status, bounded nonsecret version IDs and random correlation ID. Reject bodies, names/contact/address, cookies, Authorization, CSRF/review proofs/command bytes, secret values and full query references. Verify app/proxy/APM/crash redaction with synthetic canaries; actual telemetry integration **BLOCKED**. |
| Internal files | Fixture router denies private config, PHP controls and unknown paths; source internal directories have deny configuration | Actual webserver enforcement **BLOCKED**. Verify `/app`, `/config`, `/database`, `/tests`, `/storage`, hidden/config/backup files, directory listing and PHP handler behavior on the authorized host; don't deploy the test router or private controls. |
| External isolation | Browser requests blocked outside local fixture; no mailer, upload or production connection config copied | Actual firewall/provider/mail sink attestation **BLOCKED**. Require deny-by-default egress and synthetic-only delivery permissions before registration/provider testing. |

No actual infrastructure was deployed or modified. Shared file tests demonstrate same-host atomicity, not distributed infrastructure readiness.

## 7. Browser recovery and regression results

Run `C:/xampp/php/php.exe tests/new-connection/staging-recovery-http-test.php`. This invokes the governed acceptance plus new recovery cases in a temporary two-worker loopback environment. The original `stage1-http-test.php` compatibility entry remains separate. No production endpoint was added.

Changes and expected tested behavior:

- Unknown Create after reload: authenticated GET `/api/applications` returns at most 20 owned opaque references/statuses, no applicant payloads or IDs. Applicant explicitly chooses Resume. It does not reissue Create or choose an ambiguous application automatically. Another user's list cannot reveal those references. Larger lists/pagination and stale replica discovery are not implemented.
- Generic uncertain 5xx: response-stage interception substitutes a generic 503 **after a successful commit** for save/submit. Exact bytes/key are retained in memory for explicit retry; no duplicate consent/history/receipt is created. Retry after a 503 is not a fresh command. Browser storage remains free of applicant data/commands/proofs. Reauthentication still discards pending commands and never submits automatically.
- Delayed draft resume: editing during a delayed saved-draft response preserves newer changes; explicit later resume/save is required. Existing delayed-save generation checks remain.
- Removed selection: server rejects a removed synthetic classification. Explicit GET `/api/catalog` refresh requires the current publication; UI clears the unavailable selection, focuses a correction summary and requires applicant choice/save/review.
- Renamed/fresh catalog: explicit refresh updates labels/choices without a write, clears review/acknowledgments and marks information for save and fresh review. Server publication/version checks remain authoritative; no silent category migration or automatic save/submission occurs.
- Private review includes catalog labels whose canonical digest matches the exact server-issued proof binding. If catalog content changes between review and label retrieval, the fixture returns a conflict instead of mixing versions; review rendering uses those bound labels rather than stale editor labels.
- Existing duplicate-click, exact retry, stale-tab, independent-worker concurrent save/save-versus-submit, session expiry, notice withdrawal/epoch/retirement and key-rotation tests remain in the recovery suite.

Limitations: catalog refresh is explicit, not background polling; selection removal does not implement policy-approved migration of old applications. Cross-reload unknown save/submit uses authorized resume/receipt discovery, not persisted pending commands. This is fixture behavior only; no live applicant discovery or catalog route exists. Original prototype conditional shop/other UI rules are not a policy-approved generalized renderer.

Evidence links: [recovery browser results](staging-recovery-browser-results.json), [database recovery results](staging-recovery-results.json), [compatibility browser results](stage1-http-browser-results.json). Browser environment: headless **Chrome 155.0.8059.85**, Node/CDP, two independent PHP workers; external browser resources blocked. Screenshots include all three widths/light-dark modes, conflict/session dialogs and recovery review/confirmation. Opened and visually inspected: [desktop dark recovery review](staging-recovery-screenshots/recovery-review-1440.png), [320px dark identity](staging-recovery-screenshots/identity-320-dark.png), and [desktop recovered confirmation](staging-recovery-screenshots/recovered-submit-1440.png). Layout, sidebar/header, wrapping, focus indication and synthetic-only wording were intact in these images. Programmatic checks cover all five sections at 1440/390/320px in both themes, no horizontal overflow, keyboard/error focus, local resources and no JavaScript exceptions.

Regression inventory for this task:

| Test | Result |
| --- | --- |
| Disposable privilege/restore rehearsal | PASS, 40 assertions; no final blocked items in the local run |
| Recovery browser suite | PASS, **353 HTTP/browser checks** and **26 trusted-context assertions**; eight synthetic submissions, seven stages/four consents/one event/one receipt and lineage each; disposable cleanup verified; actual staging browsers remain blocked |
| Original compatibility browser suite | PASS, 227 HTTP/browser checks; four synthetic submissions; disposable cleanup verified |
| Authentication security | PASS, 42 assertions; local HTTP and isolated server-TLS fixtures |
| Password recovery | PASS with PDO/mail doubles and real file limiter; no email delivery |
| Logout/session actions | PASS, 22 assertions with synthetic sessions |
| Structure/dependencies | PASS, 194 assertions |
| PALECO read-only contract | PASS |
| Applicant contract | PASS, 42 assertions |
| Module foundation | PASS, 41 assertions |
| Publication governance | PASS, 107 assertions with independent-PDO concurrency |
| Draft persistence | PASS, 149 assertions |
| Atomic submission | PASS, 99 assertions in this run (race-dependent branches) |
| Database foundation | PASS, 68 assertions |
| PHP/Node syntax | PASS on added/modified executable fixture files |

The real portal dashboard/account-linking/eBills/profile and actual Google/OTP delivery were not exercised. Their actual staging regressions are **BLOCKED**, not passed. No real accounts/customer emails were used.

Early browser attempts caught malformed fixture bootstrap JSON introduced during editing and a navigation-wait assertion that dereferenced an element before the new document loaded. Both were corrected and the complete final suite passed. No authentication, rate limit or migration protection was relaxed to make a test pass. The final result contains empty exception/external-request/missing-resource/cache-failure lists and 16 screenshots.

## 8. Updated policy approval matrix

This updates the Task 06A inventory, not PALECO policy itself. **All rows remain UNVERIFIED.** Owners below are proposed accountable functions; no named authority or approval is invented. Evidence must identify the authoritative artifact, exact version/content hash, approving person/function, scope, date/effectivity and supersession/withdrawal status.

| Item | Proposed accountable PALECO function | Required authoritative document and approval/version evidence | Verified status | Gate effect |
| --- | --- | --- | --- | --- |
| Official membership/service forms | Membership/customer service and document controller | Authoritative originals/all pages, IDs/revisions/effectivity, provenance, mapping and print/signature annotations | UNVERIFIED; no authoritative form supplied | Blocks official preview/printing claims and approved form mapping. Pure synthetic staging can use clearly labeled examples only. |
| Fields/requiredness | New-service process owner with engineering/privacy review | Signed field contract and exceptions: names, contact/address, category conditions, requiredness, long-name/assisted-service handling | UNVERIFIED; draft contract only | Blocks real collection including drafts. Synthetic rehearsal can test mechanics, not endorse requirements. |
| Privacy notice/lawful basis | Designated privacy officer/function and authorized legal/process approver | Approved purpose/basis assessment, notice before first save, recipients/rights/contact/retention/security responsibilities | UNVERIFIED | Blocks real collection. An online checkbox is not evidence that a lawful basis has been approved. |
| Acknowledgment wording | Privacy/legal and process owners | Exact text/code/version/hash, language/effectivity, publication/withdrawal approval and relation to notices | UNVERIFIED; TEST publications only | Blocks real submission; no acknowledgment may be represented as a handwritten/electronic signature. |
| Locality/service eligibility | Service-area/engineering data custodian | Authorized catalog/hierarchy/license, eligibility map, owner, versioning, removed/renamed-code rules | UNVERIFIED; synthetic only; no `nc_arealist` authorization | Blocks real eligibility/address choices. Synthetic staging must not silently substitute examples as official. |
| Premises/occupancy/service classes | Engineering/new-service/membership process owners | Approved codes/labels/conditions, assisted/unclassified cases and catalog transition decisions | UNVERIFIED | Blocks approved applicant service contract. Tested refresh/removal mechanics do not approve classifications. |
| Draft retention | Records/privacy/process owners | Approved abandonment/recovery/delete/hold schedule including backup, audit/receipt treatment and lawful initial collection | UNVERIFIED; no period invented | Blocks real draft storage and purge scheduling. Disposable synthetic cleanup is separate test authorization. |
| Submitted/rejected/withdrawn retention | Records/privacy/legal and process owners | Evidence-category schedules, holds, export/access/destruction authority and backup expiry | UNVERIFIED | Blocks real evidence lifecycle; immutable triggers cannot be bypassed to implement an unspecified deletion policy. |
| Offices/staff/publisher roles | Service-operations management and access/security owner | Office routing/UNASSIGNED handling, explicit appointment/grant/revoke/expiry, duties/approval separation and access-review records | UNVERIFIED; synthetic grants only | Blocks real intake/publisher operations. Actual staging requires named environment/access owners even with synthetic users. |
| Corrections/rejections/reopening | New-service process owner and authorized review/appeal functions | Approved decision/evidence matrix, applicant correction scope, appeal/reopen authority and downstream/notice invalidation rules | UNVERIFIED | Blocks real decision/reopen endpoints. No such screen implemented here. |
| Print/physical signing | Office/payment/membership owner and document controller | Approved template mapping, blank signature areas, payment-visit sequence, verification/custody and print authorization | UNVERIFIED | Blocks official printing and later payment/signature operations. Not needed to claim a synthetic Stage 1 test. |
| Pilot/rollout/staging scope | Project sponsor/process owner with privacy, security and DBA sign-off | Named host/synthetic users, permitted actions, success/stop criteria, incident owner, any downstream deferral and explicit deployment authorization | UNVERIFIED for actual staging/pilot | Blocks actual staging deployment and all real intake. This task's local disposable scope is the only executed rehearsal authorization. |

No retention duration, official form wording or legal-basis conclusion was created. Approval can be absent from this repository yet exist elsewhere; obtain and verify it rather than assuming either approval or rejection.

## 9. Go/no-go assessment

**GO:** repeat the authorized loopback/disposable synthetic privilege, restore and HTTP recovery suites with their guards and cleanup. The tested local recovery mechanics preserve evidence and do not require live operational data.

**NO-GO for actual staging deployment:** actual owner/hostname/origin/base path/schema/identity topology, secret/grant/trigger-definer contract, TLS/proxy/shared-store controls, provider/mail isolation, monitoring and separate-host recovery evidence are missing. Existing migrations/submission/publication services still reject actual portal deployment targets; no deployable adapter was created.

**NO-GO for real applicants:** every applicable policy approval remains unverified, production auth/return paths and full portal regressions remain unfinished, and no production endpoint is authorized. Passing synthetic tests does not approve actual policy, deployment or data collection.

Default New Connection remains disabled; no actual `mco_portal` migration, operational data modification, real applicant collection, staff UI, upload, PMES, payment, printing or scheduling was performed.

## 10. Recommended next task

**Task 06E — Approved Staging Identity/Authentication Adapter Specification and Target Attestation.** Obtain the named environment/policy/access owners and actual authoritative contract values first. Specify how real portal auth, safe return intents, coherent synthetic identity FKs, dedicated strict/UTC connections and durable trigger ownership will operate on that approved target. Validate mail/provider suppression, shared controls/redaction, separate-host encrypted restore and full portal regressions with synthetic data. Any implementation should be separately scoped; do not turn fixture logins/routes into staging code or remove disposable migration guards to reach a target.

Stop after this contract/rehearsal. No actual deployment or intake enablement is implied.
