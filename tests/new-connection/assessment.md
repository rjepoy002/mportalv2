# Online New Electric Service Connection: assessment and implementation plan

Assessment date: 20 October 2026 (Asia/Manila). Status: assessment only; implementation requires approval.

## 1. Scope, method, and evidence limits

Inspected root entry points, `app/` controllers/middleware/services/views/helpers, compatibility `includes/`, `api/`, configuration, Composer manifest, JavaScript/CSS assets, Apache restrictions, migration resources, and existing test/report inventories. No AGENTS.md was found. `rg` was unavailable; PowerShell file discovery and source searches were used.

Connected through the existing database configuration and queried only `information_schema` table, column, index, and foreign-key metadata. Both inspection sessions used `START TRANSACTION READ ONLY` followed by rollback. No business rows, customer records, password values, or notification contents were queried. No application endpoints, jobs, migration scripts, or database-dependent tests were executed: some have write/email side effects. No existing application files, schema, or data were changed. This report is the only assessment artifact added.

Findings describe the available local checkout and local database, not a verified production deployment. Database grants, web PHP session configuration, TLS/proxy configuration, backups, antivirus availability, and operational ownership require deployment verification. Configuration values are deliberately omitted. Historical reports are context, not schema authority: for example, `tests/ebills-enrollment-inspection.md` predates structured profile fields now confirmed locally.

## 2. Existing system architecture

This is a conventional PHP application with explicit includes and procedural controllers, not a framework router. Root PHP files preserve public URLs and delegate to `app/controllers/`; templates live in `app/views/pages/` and `app/views/partials/`. `includes/` and some `config/` files are compatibility wrappers. New implementations should live under `app/`, avoiding duplicate logic in wrappers.

`config/database.php` creates one PDO MySQL connection defaulting to `mco_portal`, with exceptions, associative fetches, UTF-8, and native prepared statements. Services use schema-qualified reads against `palecxzp_pal_db`. The configuration contains embedded database settings; production secret management and least-privilege grants must be addressed separately without copying values into documentation.

Composer dependencies include PHPMailer and Google API Client. UI uses local CSS/vanilla JavaScript, shared portal shell partials, Tailwind via CDN, Bootstrap icon references in existing UI code, and Chart.js on the dashboard. This makes internet/CDN availability a deployment dependency. There is no existing general workflow engine, secure supporting-document upload service, staff administration module, randomized assessment engine, or new-connection form renderer in the inspected active source.

The dashboard is unusually coupled: it includes account services, performs legacy eBills account synchronization, handles many POST actions, reads profile/link/billing data, then renders a large template. Merely loading it can insert account links and notification events and attempt email delivery. Its controller also explicitly restricts hosts to localhost. Reusing the dashboard controller as a module bootstrap would introduce unrelated reads/writes and deployment constraints.

### Authentication, sessions, and account management

- `app/controllers/register.php`: structured profile validation, password hashing, emailed six-digit OTP with hashed storage, expiry, resend cooldown and attempt checks; creates an active email-verified user. Electric account code and meter details are not prerequisites.
- `app/controllers/index.php`: password verification, active-status and email-verification checks, session ID regeneration; stores `user_id`, `user_email`, `user_name`, then redirects to dashboard.
- `google-login.php` / `google-callback.php` controllers: Google OAuth, lookup/link by email, active-user check, session regeneration. Google login does not provision staff privileges.
- `app/middleware/auth.php`: starts session and checks only presence of `user_id`. It does not recheck active status, email verification, or roles. Its unauthenticated redirect uses `/mco-portal-v2/index.php`, differing from this workspace's `/mportalv2` base.
- Account linking verifies existing service ownership through challenge/billing information; unlinking archives the portal relationship. Friendly labels, primary account selection, profile editing, eBills enrollment, billing history/unpaid summary, SOA requests, consumer education, and password recovery already exist.
- `SelectedAcctNo` and opaque account-selection tokens control existing electric-account views; these must never become new-application authorization credentials.
- Logout is POST-only with CSRF and session destruction. Recovery has its own CSRF/token mechanisms. No active portal staff-role or permission system was found, and none exists in the inspected portal schema.

### Confirmed local schema

All eleven `mco_portal` tables inspected are InnoDB:

| Table | Confirmed structure / implication |
|---|---|
| `users` | `id` BIGINT UNSIGNED PK; unique `email` and nullable unique `google_id`; name/password hash; nullable verification timestamp; active/suspended/inactive status; timestamps; nullable first/last/middle/extension names VARCHAR(25), contact VARCHAR(12). No role column. |
| `paleco_accounts` | User FK; account number; label; primary flag; active/archived status; verification/timestamps; unique `(user_id, AcctNo)`. This is a service link, not an application identity. |
| `email_verifications` | Email, OTP hash, expiry, resend time, attempts, verification time; indexed email/expiry. |
| `password_resets` | User FK, token hash, expiry, used/created timestamps. |
| `account_link_challenges` | User PK/FK, unique challenge, account verification metadata and expiry/failure count. |
| `account_link_limits` | Hashed scope PK, attempts, window and block timestamps. |
| `account_notifications` | Event PK, link/unlink event enum, recipient/account snapshot, pending/sending/accepted/failed state. No application FK. |
| `notifications` | User FK, nullable service-link FK, type/title/message, read flag/timestamps. No application FK or delivery queue. |
| `activity_logs` | Nullable user FK, action/description, IP/user agent and timestamp. No application/stage relationship or structured transition history. |
| `master`, `member` | Consumer/member shaped tables also exist locally; existence does not establish ownership or permission to write. |

Operational metadata confirms `master`, `member`, `billhistory`, `arledger`, `ebills`, plus existing `nc_applications`, `nc_arealist`, `nc_list`, `nc_users`, and `paleco_users` among other tables. Targeted inspection found:

- `nc_applications`: only `id` INT and `user_id` INT, both required. It cannot be presumed to implement this workflow or reference portal users.
- `nc_arealist`: municipality/barangay and nullable coordinates. Suitability, maintenance, and completeness remain unknown.
- `nc_list`: agency, title, description, creation/submission metadata, status; no inspected relationship proving that it is an application requirements catalog.
- `nc_users`: staff-like designation/department/type/status and a short password column. Password encoding was not inspected and must not be inferred from column width.
- `paleco_users`: a separate operational identity table with department/status/password/reset metadata. No verified identity mapping to portal `users` exists.

No active references to these `nc_*` tables were found in the searched module source. Table names alone do not establish a supported integration API. Keep them untouched pending an owner-approved contract.

The README ownership boundary is controlling: operational tables are read-only, except the explicitly approved nine-column INSERT for existing eBills enrollment. That exception does not authorize new-connection writes. Portal-owned state belongs in dedicated portal tables.

## 3. Reusable components and reuse boundaries

| Component | Reuse recommendation |
|---|---|
| `app/views/partials/portal-header.php`, `portal-sidebar.php`; `app/helpers/portal-navigation.php` | Reuse shell/branding/navigation presentation after supplying module context. Add navigation only in an approved later change; education also has a controller-local navigation list. |
| `assets/css/portal-shell.css`, `dashboard.css`, `assets/js/portal-shell.js`, `theme.js` | Match palette, responsive sidebar, focus styles, dark mode and reduced motion. Keep new selectors scoped; do not load account/billing scripts wholesale. |
| Session-action dialogs and `assets/js/session-actions.js` | Reuse sign-out confirmation and accessibility patterns; preserve existing token expectations. |
| Account/eBills dialogs and JavaScript | Reference focus management, scroll containment, confirmation and AJAX error patterns; they are feature-specific, not a generic modal API. |
| `dashboardEscape`, `portalShellEscape` | Existing HTML escaping patterns. New rendering must escape for its output context, including JSON, URLs and print HTML. |
| `portalProfileValues`, Philippine mobile helper, profile JavaScript | Reuse reviewed normalization rules where applicable; server validation remains authoritative. Snapshot applicant-confirmed identity at submission; never split legacy full names automatically. |
| `sendEmail`, email footer, `config/app.php` | Reuse transport/template conventions and validated base URLs. Mail configuration currently embeds SMTP settings despite transport comments describing environment configuration; resolve secret configuration before production rollout. |
| Account notification queue and CLI job | Reuse transaction-then-dispatch design, not its link/unlink schema or master join. Existing dispatch is at-most-once attempt, including ambiguous failures; new delivery/retry semantics need explicit design. |
| Consumer education playlist/player | Reuse approved media presentation/caching where appropriate. Viewing videos is not PMES certification; questions, scoring, attempts and completion must be separate server-owned records. |
| SOA API/modal | Reference authenticated request and preview presentation only. Billing SOA generation is unrelated to official new-connection forms. |
| Existing CLI/source/browser tests | Reuse conventions and readonly ownership guard after reviewing test side effects. Run mutating tests only in an isolated test database. |

## 4. Applicant access and staff authorization

Use the existing portal identity for applicants, including people with no PALECO service account. Offer a New Connection entry from sign-in/registration and the authenticated shell in later approved work. Preserve a validated internal return destination through login/registration so applicants reach the module rather than the dashboard's no-account redirect. Never permit arbitrary redirect URLs.

Recommended applicant entry: a new thin root `new-connection.php` wrapper to an independent module controller. It requires an active, email-verified portal user and module CSRF, but no linked account, meter number, member code, or `SelectedAcctNo`. Existing users may apply for another service too. Each application belongs to `users.id`; account linking remains optional and separate. Do not fabricate consumer records or auto-link services based on name/email matches.

Recommended staff entry: a separate root `new-connection-admin.php` wrapper with dedicated staff views and middleware. Reuse portal identity only with explicit provisioned staff assignments, or integrate approved staff SSO later. Do not trust email domain, a client role field, Google login, operational `type` fields, or an applicant's user profile as proof of staff status.

| Principal / permission | Allowed actions |
|---|---|
| Applicant owner | Create/read own applications; edit own drafts or explicitly returned fields; upload own requested documents; view released decisions/previews; take own eligible PMES attempts. No approvals, clearance, print-master, payment verification or schedule mutation. |
| Intake verifier | Review assigned/scoped submitted applications and documents; request corrections; approve intake. |
| Billing clearance officer | Record internal clearance decision/reference; sensitive debt details remain staff-only. |
| PMES administrator | Publish versioned learning/assessment rules and question bank; authorized exceptions require reasons/audit. |
| Technical inspector | Record assigned inspection visits/findings/reinspection and technical decision. |
| Payment/forms verifier | Print approved snapshots; verify offline receipt and required handwritten signatures independently. |
| Installation scheduler | Schedule/reschedule only after all prerequisite approvals. |
| Module administrator / auditor | Administrator provisions scoped roles/configuration; auditor reads authorized history. Neither receives unlogged bypass rights. |

Authorize every request and download server-side using owner or current staff permission plus office/assignment scope. Recheck active user and current staff assignments on protected requests; do not rely solely on cached session roles. Deny by default; use consistent not-found/forbidden behavior to resist enumeration. Staff MFA, shorter idle/absolute lifetimes, reauthentication for role changes and sensitive actions, and role revocation are rollout prerequisites. Separate public-facing decision text from private internal notes.

## 5. Proposed seven-stage workflow

The following grouping/order is a proposal for PALECO approval, not a claim about current policy. Draft preparation is within stage 1. Staff printing is a stage-6 preparation action before/during the payment visit, not an eighth stage. Installation scheduling is the final online stage; physical energization remains an external operational process.

| Stage | Completion evidence / responsible party |
|---|---|
| 1. Application preparation and submission | Applicant saves draft, completes validated online form, acknowledges current notices, sees read-only official previews, submits an immutable revision. |
| 2. Supporting-document verification | Applicant uploads requirements; intake staff approve current document versions or request corrections. |
| 3. Internal billing clearance | Billing staff record cleared/hold/rejected decision and reference; an applicant with no current account is not automatically cleared or blocked. |
| 4. Online PMES and assessment | Required learning acknowledgments and server-scored randomized assessment meet the attempt's frozen passing requirements. |
| 5. Technical inspection | Assigned staff record visit, findings, required corrections and technical approval; reinspection remains within this stage. |
| 6. Payment visit, printing and signed-form verification | Staff print prefilled approved form versions with blank handwritten signature areas; offline receipt/payment and each mandatory signed form are verified before stage approval. No online payment gateway or electronic-signature substitution. |
| 7. Installation scheduling | Scheduler records installation date/window, responsible team and applicant notice after all required preceding approvals. Scheduled does not mean installed/energized. |

Create seven independent stage rows per application, with a unique application/stage key. Suggested statuses: `not_started`, `in_progress`, `submitted`, `under_review`, `needs_action`, `approved`, `rejected`, `on_hold`; applicability and transitions vary by stage. Overall application lifecycle (`draft`, `submitted`, `processing`, `scheduled`, `withdrawn`, `rejected`) is separate; a current-stage indicator is derived convenience only.

Use an explicit server-side transition matrix and prerequisite checks. Independent status tracking does not mean unrestricted advancement. Record actor, reason, timestamp, revision, and evidence for every transition. Handle correction loops and authorized reopening without erasing prior decisions. A material revision must invalidate affected downstream approvals under an approved rule set. Lock application/stage rows and use version checks so concurrent staff approvals, autosaves and duplicate submissions cannot overwrite decisions. Idempotency keys prevent duplicate transition/notification/payment-verification effects.

## 6. Proposed module file structure

All paths below are proposals; none have been created by this assessment.

```text
new-connection.php                       # applicant wrapper
new-connection-admin.php                 # staff wrapper
api/new-connection.php                   # allowlisted module actions/downloads
app/controllers/new-connection/
  applicant.php, staff.php, actions.php, documents.php, print.php
app/middleware/new-connection-auth.php
app/services/new-connection/
  applications.php, workflow.php, authorization.php, documents.php
  clearance.php, pmes.php, inspections.php, forms.php
  payment-verification.php, scheduling.php, notifications.php, audit.php
app/views/pages/new-connection/           # applicant and staff templates
app/views/partials/new-connection/        # stage tracker, forms, previews/dialogs
app/helpers/new-connection-validation.php
assets/js/new-connection/                # module-scoped applicant/staff behavior
assets/css/new-connection.css
config/new-connection.php                # nonsecret limits and feature switch
resources/new-connection/                # approved templates, version manifest
database/migrations/                    # reviewed portal-only migrations later
jobs/send-new-connection-notifications.php
tests/new-connection/                    # assessment now; isolated tests later
```

Prefer private document storage outside Apache's document root, supplied through deployment configuration. A module subdirectory under existing HTTP-denied `storage/` is only a fallback with verified Apache restrictions and executable-file denial; do not assume `.htaccess` works on every host. Templates/resources remain internal and are served through authorized controllers where necessary. No new routing framework is required.

## 7. Proposed database design and relationships

These are logical design recommendations, not executable DDL. Create dedicated `mco_portal.new_connection_*` tables after approval. Do not reuse the operational `nc_*` namespace. Use BIGINT UNSIGNED IDs/FKs matching portal users, InnoDB, utf8mb4, explicit constraints, UTC timestamps displayed in Asia/Manila, and DECIMAL for money. Retention must preserve decision evidence; avoid cascading deletion of approved applications/history.

| Proposed table suffix | Key fields and relationships |
|---|---|
| `applications` | PK id; unique opaque public reference; applicant user FK; overall status; workflow version; current revision FK; lock version; created/submitted/updated timestamps. Optional external service reference only after verified handoff. |
| `application_revisions` | Application FK; unique `(application_id, revision_no)`; structured applicant/site/service data or validated versioned payload; schema version, snapshot hash, submitted/frozen timestamp. Submission snapshots are immutable. |
| `stages` | Application FK; stage code 1–7; unique `(application_id, stage_code)`; status, revision/evidence reference, assignee, opened/decided timestamps, decision actor and version. Exactly seven rows created transactionally. |
| `stage_events` | Stage/application FKs; from/to status, actor user FK, revision, reason, event time, unique idempotency reference. Append-only transition history. |
| `requirement_versions` | Versioned requirement code, service category, required/conditional rules, effective dates; freeze applicable set for each submitted revision. |
| `documents` | Application/revision/requirement-version FKs; document version, opaque storage key, original name (escaped), detected MIME, bytes, checksum, uploader, scan status, review status and timestamps. Immutable file versions. |
| `document_reviews` | Document FK, reviewer FK, decision, applicant-visible reason and separate internal note, time. Preserve prior reviews on replacement. |
| `clearances` | Application/revision FK, billing actor, cleared/hold/rejected outcome, restricted reference/notes, decision/expiry times. No automatic operational updates. |
| `pmes_versions` | Published immutable content/rules version; pass threshold, question count, attempt/cooldown/time rules, publication actor/time. |
| `pmes_questions`, `pmes_options` | Versioned questions, topics, choices and protected answer keys; options FK to question. Published question versions immutable. |
| `pmes_progress` | Application/user/content-version relationship; required unit acknowledgments/completion evidence. Does not alone grant a pass. |
| `pmes_attempts` | Application/user/rules-version FKs; attempt number; randomized seed or selected set reference, started/expires/submitted times, score/max score, frozen threshold and pass result. |
| `pmes_attempt_questions`, `pmes_answers` | Attempt FK, selected question-version FK, frozen question/choice order; unique attempt/question and answer constraints; submitted choices and awarded marks. No answer keys in applicant responses. |
| `inspections` | Application/revision FK; inspector/team, visit schedule, checklist version/findings/outcome, correction and reinspection references, recorded/approved timestamps. Multiple visits supported. |
| `form_templates` | Official form code/version, effective dates, approved artifact location/hash, field mapping and approving authority. |
| `form_instances`, `form_print_events` | Application/revision/template FKs; immutable prefilled snapshot/hash; print instance, staff actor, time, copy/reprint reason. New revisions cannot silently alter previously printed forms. |
| `payment_verifications` | Application FK, offline receipt reference, amount/currency/date, verifying actor/time/outcome and evidence FK. Duplicate/reversal controls follow approved receipt rules. |
| `signed_form_verifications` | Form-instance FK, mandatory signature checklist, verified/missing/rejected result, staff actor/time, restricted scan FK if approved. Separate from payment decision. |
| `installation_schedules`, `schedule_events` | Application FK, team, start/end window, current state/version; history of scheduling/rescheduling/cancellation with reason and actor. One current confirmed slot per application. |
| `roles`, `permissions`, `role_permissions`, `staff_assignments` | Explicit module RBAC; assignment user/role/office scope, grantor, validity/revocation timestamps. No changes required to existing users role semantics. |
| `notification_outbox`, `notification_deliveries` | Application/event/user references; channel, template version, deduplication key, delivery state/attempts/next attempt and safe failure category. Commit event with business transition; send after commit. |
| `audit_events` | Application/entity/action, actor or system identity, safe before/after summary, reason/request ID/time. Include sensitive reads/downloads, print, configuration and role changes; exclude document bodies, credentials and answer keys. |
| `consents` | Application/revision/user FKs, notice/form version hashes, acknowledgment type and timestamp; distinguishes online acknowledgment from later handwritten signatures. |

Core cardinalities: user 1:N applications; application 1:N revisions, exactly seven stages, and N documents/attempts/visits/form instances/events; document 1:N reviews; PMES version 1:N question versions/attempts; form template 1:N form instances; application 1:N schedule history. Child evidence must belong to the same application/revision as its decision, enforced with composite keys where practical and service checks.

Index applicant/status/update time; stage/status/assignee; application/event time; document/application/requirement/version; attempts/application/version; schedule/team/window; outbox/state/next-attempt. Add unique public reference, stage key, revision number, event deduplication key, and form version keys. Capacity/overlap scheduling needs transactional team/day allocation rather than a simple uniqueness constraint on timestamps. Exact lengths, check constraints and JSON strategy require confirmed database-version compatibility before DDL.

The existing generic `notifications` and `activity_logs` may later receive summary pointers through an adapter, but their present schemas do not replace application-linked delivery/audit records. Do not expand the link/unlink enum in `account_notifications` or make new notifications depend on an existing electric account.

## 8. Security assessment and required controls

Positive patterns include PDO bound parameters, owner-scoped account queries, transaction locks, password/OTP hashing, random CSRF tokens with constant-time checks, escaped output, internal-directory HTTP denial, POST-only logout, and no-store responses on several sensitive endpoints. These are useful patterns, not proof of uniform protection.

Gaps requiring planned work:

1. Authentication middleware trusts session presence without current user/role checks. Login and registration handlers reviewed lack a common CSRF check and login throttling. OAuth handlers do not explicitly generate/validate state or check Google's verified-email flag before email-based account linking. Resolve these identity risks before relying on those paths for sensitive applicant/staff access.
2. No application-level consistent secure/HttpOnly/SameSite/strict-mode session setup was found. CLI defaults inspected have secure/strict disabled and HttpOnly/SameSite unset; actual web settings are unverified. Define HTTPS cookie policy, idle/absolute expiry and revocation. Login regeneration already exists and should be preserved.
3. SMTP configuration embeds sensitive settings, and database settings are also embedded. Move secrets to deployment configuration through a separately approved change and review rotation with the credential owner. Do not publish values. Least-privilege DB grants are unverified.
4. There is no reusable secure upload pipeline in inspected active PHP. Require allowed extensions and detected MIME/signature checks, size/count limits and PHP upload-error checks, random storage names, quarantine/antivirus, no executable content, and private storage. Reject active formats such as HTML/SVG unless a separately approved sanitization policy exists. Use authenticated controlled streaming with safe filenames, `nosniff`, private/no-store caching, and download/preview authorization on every request. Never trust path or MIME supplied by the client.
5. Every state-changing module action, including autosave, upload, attempt submission, print-record creation and staff decisions, needs method checks, CSRF, input bounds, owner/permission checks and concurrency handling. GET previews must have no workflow side effects. JavaScript checks supplement server validation.
6. Official previews must be generated from approved template versions and application snapshots, with blank signature spaces and a preview label. Browser read-only presentation cannot prevent screenshots or copying. Staff-authorized print routes produce the approved signing copies, record print/reprint events, and must not expose arbitrary paths or remote URLs to renderers.
7. Randomize PMES question/option selection server-side, persist each attempt's selection/order/rules, score on the server, conceal correct answers, enforce eligibility/limits/timeouts, and prevent concurrent attempts/replayed submissions. Threshold changes affect future attempts, not completed results.
8. Commit stage decisions, audit and notification events atomically. Do not roll back an approval because email failed. SMTP acceptance is not delivery; define bounded retries and ambiguous-send reconciliation without claiming exactly-once email.
9. Existing raw exception logging in some controllers may include operational detail. New logging should use safe categories/correlation IDs. Restrict audit/document/clearance access, retention and backups. A generic activity table is not inherently tamper-resistant; restrict update/delete grants and plan protected archival.

## 9. Integration conflicts, dependencies and safeguards

- Dashboard's no-account redirect blocks account-independent pages added through its current page switch. Use a separate controller; preserve account-link and eBills behavior.
- Dashboard GET synchronization can create links/send email. Never invoke it as a module helper or use HTTP smoke checks casually during assessment.
- Existing operational `nc_*` tables create ownership/naming and possible duplicate-system conflicts. Obtain their owner's purpose, lifecycle and migration/handoff policy. Do not infer foreign-key identity compatibility from `user_id` names.
- Local and operational `master`/`member` coexist; an older helper uses unqualified table names while active dashboard uses qualified operational names. New code must explicitly qualify data sources and never join applicant identity by inferred names.
- Existing account notifications assume a service link and link/unlink events. New applicants have neither; isolate delivery records and templates.
- Existing billing summary requires an authorized linked account and cannot serve as internal applicant clearance. Staff clearance rules need a separate authorized process, especially for first-time applicants.
- Consumer education completion currently provides no PMES examination evidence. Do not award certification based on a player event alone.
- Current profile fields are nullable, and Google signup can create users without structured names. Require applicant confirmation; preserve submitted snapshots independently of subsequent profile edits.
- Multiple navigation definitions, relative links, the authentication redirect mismatch and localhost dashboard restriction need deliberate base-path/deployment tests. Do not remove existing local safeguards incidentally.
- Private storage/scanning, approved official forms and field maps, staff identity provisioning, SMTP reliability, scheduled jobs, timezone policy, and database grants are external prerequisites. No approved PMES question bank or official new-connection documents were supplied or verified in this assessment.
- Installation booking must not create a consumer, assign a meter or mark energization in operational tables. Any eventual back-office handoff needs a separately approved interface and reconciliation process.

## 10. Recommended implementation sequence and validation

1. Approve the seven-stage grouping, ownership boundaries, permission matrix, and initial policy decisions below. Confirm ownership of existing operational `nc_*` tables and authoritative official forms.
2. Implement the isolated, disabled-by-default module shell described below. Verify existing routes remain unchanged, feature-off behavior, unauthenticated access, and the no-linked-account applicant path.
3. Address authentication/session/OAuth/secret prerequisites in narrowly reviewed changes; establish staff provisioning and authorization tests before staff access.
4. Prepare portal-only schema migrations and rollback/backup procedure for review. Validate on a disposable database, including FK compatibility, seven-stage initialization, unique constraints, concurrency and ownership denial. Never point mutating tests at operational schemas.
5. Implement applicant drafts, validation, revision submission, notices and read-only previews. Verify owner isolation, XSS/CSRF, lost-update prevention, and immutable submitted data.
6. Implement quarantined uploads and verification; test MIME spoofing, executable/polyglot rejection, oversized uploads, path traversal, unauthorized retrieval, replacement and scan failure.
7. Implement staff queues, transitions, internal clearance and audit/outbox. Test unauthorized cross-office actions, correction/reopening rules, duplicate decisions and failed email.
8. Implement versioned PMES content, randomized attempts and configurable pass rules. Test scoring on threshold boundaries, replay/timeout/concurrent attempts and answer-key nondisclosure.
9. Implement inspections, approved print snapshots, offline receipt verification and signed-form checklists. Verify reprints, obsolete revisions, missing signatures, rejected receipts and correction history.
10. Implement scheduling as the final online stage; test prerequisite enforcement, capacity conflicts, rescheduling and notifications. Complete regression checks for registration/login/recovery, linking/unlinking, primary selection, eBills, SOA, billing and education in an isolated environment. Include mobile/accessibility and base-path checks.
11. Obtain staff UAT and deployment readiness approval; enable gradually with backup/restore, private storage and job monitoring verified. Roll back by disabling entry points, preserving application evidence, rather than deleting workflow tables or uploaded documents.

## 11. Unresolved PALECO policy decisions

- Exact stage names/order, parallel activities, rejection/withdrawal/reopening authority, approval expiry, and which corrections invalidate later stages.
- Eligible applicant types, representative/co-applicant handling, minors, organizations, multiple sites/applications and duplicate detection/appeal policy.
- Required fields/documents by service category, acceptable file types/limits, original-document checks, and applicant correction deadlines.
- Authoritative form versions, approved preview labels/field maps, printing authority, reprint rules, mandatory signatures/witnesses and whether signed scans may be retained.
- Clearance criteria for applicants without accounts, identity matching safeguards, outstanding-balance escalation and what outcome text may be disclosed online.
- PMES approved curriculum/question bank, accessibility/language accommodations, question mix/count, pass score, timing, retry limits/cooldowns, certificate validity, exemptions and proctoring expectations.
- Inspection scope/checklist, fees, staff/team allocation, rescheduling and reinspection rules.
- Offline fee schedule, receipt authority/uniqueness/reversal rules, partial payments, who verifies payment versus signatures, and separation of duties.
- Installation capacity/calendar rules, applicant choice versus staff assignment, notices/service targets, and exact boundary with actual connection/energization.
- Staff identity source/SSO, MFA, offices/scopes, role grant/removal authority and audit access.
- Privacy notices, lawful retention periods, rejected/withdrawn draft expiry, scan/backup retention, data export/deletion and incident response ownership.
- Notification channels, consent/preferences, delivery retry/escalation policy, and ownership of existing operational new-connection tables and any later integration.

## 12. First safe implementation change — recommendation

After approval, add a disabled-by-default, account-independent New Connection landing shell using a new thin root wrapper, a dedicated controller/view and a module-specific authorization boundary. It should show approved introductory information only, accept no applications/uploads, make no database writes, and avoid including the dashboard controller or account synchronization. Keep existing navigation unchanged until the shell and authenticated no-linked-account access have been reviewed. Use existing portal branding and server-side identity checks without creating operational consumer records.

Acceptance criteria: feature disabled by default; direct feature-off requests denied; enabled requests require a current active verified user; staff routes remain unavailable without explicit provisioned authorization; no account link required; no side effects; existing entry points unchanged. Address the identified authentication prerequisites before a sensitive-data pilot. Prepare any schema changes as a separate reviewed step.

Assessment stops here. No feature implementation or migration is authorized by this report.
