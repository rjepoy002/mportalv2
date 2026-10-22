# Task 06R — Stage 1 integration readiness and field approvals

2026-10-21. **Ready for owner review and a separately approved read-only staging milestone; not ready for real collection or submission.** This task changed no application routes, authentication controllers, collection settings or database schemas outside isolated tests. No policy approval is inferred.

## Inspected boundaries

Reviewed the existing applicant contract, prototype/view JavaScript, draft/submission services, fixture adapter, module current-user middleware, application flag and independent preview gate. Reuse contract `stage1.v1-draft-20261020`; no second implementation is proposed.

Important architectural differences from the fixture:

- `newConnectionApplicant()` derives the ID from session, lazily reads current `id,name,email,status,email_verified_at` from hardcoded `mco_portal.users`, and requires active/verified status. It does not query linked accounts. Its hardcoded identity schema cannot safely be pointed at staging merely by selecting a disposable default database.
- The actual application gate accepts only server environment `MCO_NEW_CONNECTION_ENABLED=1`, default off, before session/database startup. Its current enabled controller renders a roadmap, not the Stage 1 editor.
- The independent preview requires explicit development opt-in, loopback server/peer and local Host with no forwarding headers; it permits only GET/HEAD and shares the informational view. It must remain separate from collection authorization.
- The draft service accepts `mco_portal` or a guarded disposable schema; that constructor allowance is not deployment authorization. It requires strict SQL/native prepares and sets UTC. The submission constructor still explicitly requires a disposable schema. Migration guards remain disposable-only. Neither restriction should be removed to make a UI route work.
- The prototype requires `NC_PROTOTYPE_FIXTURE`. Its HTTP bridge, synthetic login, generated configuration and test publication provider are fixture code. They are not deployable actual-portal authentication or approved content providers. Synthetic terminology also remains in contract error messages and submission event reasons.

## Complete field approval matrix

Legend: **P** = provisional, no evidenced PALECO approval. Every row below is P. **E** = applicant-entered (synthetic only today); **S** = server-supplied. **R/S** describes saved HTTP review and final submitted evidence, not just browser controls. Text input is string/null; maximums are Unicode characters unless stated. Common validation: valid UTF-8, trimmed, empty becomes null, prohibited control characters rejected; only requested-service details permit line breaks. Drafts may omit readiness-required data. All requiredness below is synthetic readiness, not approved PALECO policy.

### Portal identity — distinct from legal applicant information

| Field / label | Type / maximum | Requirement and validation | Source | R/S | Approval / exact decision |
|---|---|---|---|---|---|
| Session `user_id` / signed-in owner | Positive integer; actual users column type not re-verified in this task | Required for authorization; current DB row checked; not accepted from body | S | Not shown as applicant input; owner/actor FK retained | P: authorize staging identity mapping and confirm actual ID/FK compatibility before deployment |
| User `name` / account display name | Existing identity value; no Stage 1 max | Shell display only; not split into applicant names | S | Shell only, not legal-name snapshot | P: approve display/prefill and representative rules |
| User `email` → `applicant.email` / verified portal email | String, max 254 bytes in contract context, valid email | Required server context; browser override rejected | S | Yes / snapshot | P: decide whether contact email must equal account email and define change handling |
| User `status` / active eligibility | Existing identity status; no Stage 1 length | Exact `active`; rechecked server-side | S | Not in applicant review/snapshot | P: confirm eligibility and mid-request status-change policy |
| User `email_verified_at` / portal verification | Existing identity timestamp; not Stage 1 input | Nonempty portal verification required; Google claim is not a replacement | S | Not in applicant review/snapshot | P: confirm verification/recovery policy without changing Google behavior |

### Applicant identity

| Field / label | Type / maximum | Requiredness; validation / normalization | Source | R/S | Approval / exact decision |
|---|---|---|---|---|---|
| `applicant.first_name` / First name | Text 25 | Required; common Unicode text rules, no legal-name proof or alphabet-only rule | E | Yes / snapshot | P: approve necessity, name order and 25-character limit |
| `applicant.middle_name` / Middle name | Text 25 | Optional; common rules | E | Yes / snapshot | P: approve optionality and handling of no middle name |
| `applicant.last_name` / Last name | Text 25 | Required; common rules | E | Yes / snapshot | P: approve naming exceptions and limit |
| `applicant.extension_name` / Name extension | Text 25 | Optional; free text, common rules | E | Yes / snapshot | P: approve accepted suffixes/free text and limit |
| `applicant.contact_number` / Mobile number | Input text 64; canonical 11 digits | Required; existing helper accepts local/+63/63/9 forms, strips spaces/hyphens, emits `09…` | E | Yes / canonical snapshot | P: approve mobile necessity and alternatives for applicants without mobile |
| `applicant.profile_confirmed` / Reviewed application identity | Boolean | Must be true for readiness; edits reset UI confirmation | E | Not listed in saved HTTP summary; retained in snapshot | P: approve purpose and wording; not identity verification |

### Residential address

| Field / label | Type / maximum | Requiredness; validation / normalization | Source | R/S | Approval / exact decision |
|---|---|---|---|---|---|
| `residential_address.province_code` / Province | Text 32 | Conditional on municipality relationship; catalog membership if supplied | E | Resolved name shown / code+name snapshot | P: approve catalog and independent-city/null-province rules |
| `residential_address.city_municipality_code` / City / municipality | Text 32 | Required; catalog member with matching province | E | Name shown / code+name | P: approve municipality source and residence coverage |
| `residential_address.barangay_code` / Barangay | Text 32 | Required; belongs to selected municipality | E | Name shown / code+name | P: approve barangay source and missing-locality handling |
| `residential_address.street_purok_sitio` / Street / purok / sitio | Text 160 | Required; common rules | E | Yes / snapshot | P: approve minimum usable address detail and limit |
| `residential_address.house_lot_unit_building` / House / lot / unit / building | Text 100 | Optional; common rules | E | Yes / snapshot | P: confirm optionality and necessity |
| `residential_address.additional_address` / Additional address | Text 160 | Optional; common rules | E | Yes / snapshot | P: confirm distinct purpose versus street/building |
| `residential_address.postal_code` / Postal code | Text 4 | Optional; four ASCII digits, preserves leading zeroes | E | Yes / snapshot | P: approve optionality and authoritative reference |

### Proposed service location

| Field / label | Type / maximum | Requiredness; validation / normalization | Source | R/S | Approval / exact decision |
|---|---|---|---|---|---|
| `service_location.province_code` / Province | Text 32 | Conditional on municipality; catalog relationship | E or server copy | Name / code+name | P: approve service-area province rules |
| `service_location.city_municipality_code` / City / municipality | Text 32 | Required; catalog relationship | E or server copy | Name / code+name | P: approve eligible municipalities |
| `service_location.barangay_code` / Barangay | Text 32 | Required; relationship and supplied catalog eligibility; eligibility applies to service location, not residence | E or server copy | Name / code+name | P: approve coverage/exception decision authority |
| `service_location.street_purok_sitio` / Street / purok / sitio | Text 160 | Required; common rules | E or server copy | Yes / snapshot | P: approve location sufficiency for inspection |
| `service_location.house_lot_unit_building` / House / lot / unit / building | Text 100 | Optional; common rules | E or server copy | Yes / snapshot | P: approve optionality |
| `service_location.additional_address` / Additional address | Text 160 | Optional; common rules | E or server copy | Yes / snapshot | P: approve necessity and scope |
| `service_location.postal_code` / Postal code | Text 4 | Optional; four ASCII digits | E or server copy | Yes / snapshot | P: approve optionality |
| `service_location.landmark` / Landmark | Text 200 | Optional; not copied from residence | E | Yes / snapshot | P: approve usefulness and privacy guidance |
| `service_location.same_as_residential` / Same as Residential Address | Boolean | Optional; server copies seven residential fields when true; direct service-address editing unchecks UI option | E command, S copying | Yes / snapshot | P: approve copying/edit semantics and confirmation |

### Service request, review and declarations

| Field / label | Type / maximum | Requiredness; validation / normalization | Source | R/S | Approval / exact decision |
|---|---|---|---|---|---|
| `service_request.premises_classification_code` / Premises classification | Text 32 | Required; catalog member | E | Resolved label / code snapshot | P: approve official classifications and mapping |
| `service_request.occupancy_code` / Occupancy | Text 32 | Required; catalog member | E | Resolved label / code | P: approve official occupancy choices/evidence requirements |
| `service_request.connection_type_code` / Connection category | Text 32 | Required only where catalog supplies applicable categories; incompatible category rejected | E | Label / code | P: approve applicability matrix; example shop rule is not policy |
| `service_request.occupancy_other` / Other occupancy details | Text 100 | Required for configured Other codes; rejected otherwise | E | Yes / snapshot | P: approve Other option and clarification purpose |
| `service_request.requested_service_details` / Requested service clarification | Text 500 | Optional; multiline normalization, common rules | E | Yes / snapshot | P: approve necessity and safe instructions |
| `review.information_confirmed` / Reviewed information | Boolean | Required at submission; saved drafts force false; fresh review required after edits | E | Review control / submitted snapshot true | P: approve wording and separation from signature |
| `acknowledgments.privacy_notice` / Privacy notice | Boolean | Separate true selection required for synthetic submit | E | Separate control / version+hash consent record, excluded from applicant snapshot | P: privacy owner must approve notice, basis and acknowledgment purpose |
| `acknowledgments.information_accuracy` / Information accuracy | Boolean | Separate true selection required | E | Control / consent evidence | P: approve accuracy/correction wording |
| `acknowledgments.application_verification` / Application/document verification | Boolean | Separate true selection required | E | Control / consent evidence | P: approve verification/no-approval explanation |
| `acknowledgments.physical_signing` / Physical printing/signing | Boolean | Separate true selection required | E | Control / consent evidence | P: approve offline printing/signing/payment procedure and wording |

### Server-derived snapshot and protocol fields

These are not extra applicant questions; no browser override is allowed. Derived text limits are not independently constrained by `fields()`; approved provider validation must bound them and preserve the 32 KiB canonical snapshot cap.

| Field / label | Type / maximum | Requirement / validation | Source | R/S | Approval / decision |
|---|---|---|---|---|---|
| `residential_address.country_code` / Country | Fixed string `PH` | Always derived | S | Hidden in HTTP summary / snapshot | P: approve Philippine-only residence assumption |
| `service_location.country_code` / Country | Fixed string `PH` | Always derived | S | Hidden / snapshot | P: approve Philippine-only service coverage |
| `residential_address.province_name` / Province name | Provider string/null; no per-field contract max | Derived from selected code | S | Yes / snapshot | P: approve source, labels and bounds |
| `residential_address.city_municipality_name` / Municipality name | Provider string/null; no per-field max | Derived | S | Yes / snapshot | P: approve source/version |
| `residential_address.barangay_name` / Barangay name | Provider string/null; no per-field max | Derived | S | Yes / snapshot | P: approve source/version |
| `service_location.province_name` / Province name | Provider string/null; no per-field max | Derived | S | Yes / snapshot | P: approve coverage labels/bounds |
| `service_location.city_municipality_name` / Municipality name | Provider string/null; no per-field max | Derived | S | Yes / snapshot | P: approve source/version |
| `service_location.barangay_name` / Barangay name | Provider string/null; no per-field max | Derived | S | Yes / snapshot | P: approve source/version |
| `service_request.request_kind` / New Electric Service Connection | Fixed `new_service` | Always server-owned | S | Static form heading; omitted from HTTP summary / snapshot | P: approve official request naming |
| `contract_version` / Contract version | Fixed version string | `stage1.v1-draft-20261020` | S | Not in visible summary / snapshot | P: approve final version and compatibility policy |
| `catalog_versions.localities` / Locality version | Provider version string; not an input field | Current provider lineage | S | Not visible / snapshot | P: approve version/withdrawal authority |
| `catalog_versions.service_requests` / Classification version | Provider version string; not an input field | Current provider lineage | S | Not visible / snapshot | P: approve version/withdrawal authority |

References, application/revision lock versions, canonical SHA-256, review proof/key ID, notice hashes/versions, idempotency keys, receipt status, timestamps and actor FKs are workflow/protocol metadata, not applicant fields. Browser sends only bounded expected-version tokens, proof, key and selections; server authenticates them and owns statuses/hashes/publication. Snapshot email is the server email listed above. No technical meter/account questions exist.

## Proposed file-by-file integration map — not implemented

| Proposed file(s) | Exact responsibility and boundary |
|---|---|
| New `config/new-connection-stage1-development.php` and `app/helpers/new-connection-stage1-development.php` | Independent default-off trusted development authorization, exact approved environment/host/loopback checks and named capabilities for display/entry/persistence/submission. Fail closed before session/DB; no browser-selected schema/environment. Do not reuse application or read-only preview enablement. |
| New `new-connection-stage1-development.php`, `app/controllers/new-connection-stage1-development.php` | GET/HEAD display only in the first milestone; 404 when unauthorized, 405 for mutations. Private no-store, generic errors. Do not register any POST/save/submit route in that milestone. |
| `app/helpers/session-security.php` | Reuse current session startup/expiry/regeneration unchanged. Actual login/logout/registration/Google controllers remain outside the change set. No automatic authentication-return claim deployment. |
| `app/middleware/new-connection-auth.php` | Reuse eligibility semantics, but first propose an explicitly trusted identity-repository/connection boundary to remove its hardcoded customer-schema coupling in staging. No client schema or user-ID selection. Preserve lazy connection and current active/verified checks; production behavior needs separate review before any edit. |
| `app/views/pages/new-connection-prototype.php` and prototype CSS/JS | Reuse layout/fields. First milestone needs a reviewed display-only rendering mode: no enabled form controls, fixture bridge, proof/create/save/submit commands or real profile prefill. Keep fixture guard until its replacement authorization is reviewed; do not simply define it globally. Preserve the temporary runner unchanged. |
| New `app/services/new-connection/stage1-development-context.php` | Server-only synthetic snapshot/catalog/notice context for display. No production fallback to `prototype-data.php` or test notices; immutable approved providers required before collection. |
| `app/services/new-connection/applicant-contract.php` | Reuse pure canonicalizer and validation; approved field changes require explicit contract-version decisions. Resolve synthetic-specific messages/catalog assumptions before real use, not silently in this task. |
| Proposed `app/services/new-connection/stage1-connection.php` | Future least-privilege, explicit schema/identity binding after deployment approval. No operational schema access; no actual portal DB bootstrap in fixture. Must preserve native prepares, strict SQL, UTC and error masking. |
| Existing `drafts.php`, `submission.php`, `publication.php`, `review-keys.php` | Future adapters reuse transaction/ownership/locking/proof/receipt logic. Submission and migration disposable guards remain intact until a separately approved, tested deployment design exists. No generic workflow transition may substitute for atomic submit. Publication registry must share the transaction/PDO for withdrawal consistency; keys outside webroot, retained key IDs and rotation required. |
| Future HTTP adapter, path to be approved separately | Closed command schemas, exact Origin/CSRF, POST/content type/body limits, current identity/ownership, shared rate limits, no-store, safe errors. Do not deploy `tests/new-connection/stage1-http-router.php`, synthetic login or CLI status controls. Endpoint authorization is a later milestone. |
| `app/helpers/portal-navigation.php`, `app/views/partials/portal-sidebar.php` | No first-milestone change: preserve current read-only Preview/Coming Soon behavior. Any later Stage 1 link needs its own capability gate and owner approval; never imply collection from preview availability. |
| Existing root application/preview routes and flags | Remain unchanged, real application default-off. No approval to modify `.htaccess`, customer DB configuration or authentication routes. |
| New milestone acceptance tests under `tests/new-connection/` | Actual staging session integration with attested synthetic users; gate/method/IDOR/private response/read-only contracts, no writes, zero linked accounts, current-user changes and portal regression checks. |

## Separate deployment prerequisite matrix

All rows are cumulative only where explicitly appropriate; approval of one is not approval of another. Persistent staging work still uses disposable/authorized synthetic schemas, never actual customer identities.

| Capability | Mandatory evidence and authorization | Current disposition |
|---|---|---|
| 1. Read-only Stage 1 display | Owner-approved default-off design; trusted local/staging gate; attested isolated actual-session identity source; active/verified checks without linked-account requirement; GET/HEAD only; synthetic/redacted display; no writable controls, profile collection or service writes; no-store and denial regression | Proposed first controlled milestone; current read-only seven-stage preview is a different page |
| 2. Synthetic Stage 1 entry | Explicit synthetic-entry authorization, isolated users/server/schema, prominent warning, existing pure contract, no real prefill/browser PII storage; no production routes or mail | Available in temporary runner only; no actual-portal integration authorized |
| 3. Persistent staging drafts | Explicit persistence approval; disposable/authorized synthetic schema and approved migration path; least privilege, identity ownership mapping, strict SQL/UTC, backup/recovery, concurrency/idempotency/rate controls, reauthentication recovery | Proven in disposable tests; not an approved persistent staging deployment |
| 4. Real applicant collection | Explicit collection authorization; field/form/legal-representative approvals; lawful basis/privacy/retention; approved catalogs/notices; security/accessibility review, actual HTTPS/proxy/session acceptance, monitoring and recovery, authorized schema deployment | NO-GO; neither synthetic acceptance nor draft availability grants this |
| 5. Actual application submission | Separate submission approval; all collection prerequisites plus immutable approved notice/catalog lineage, atomic withdrawal consistency, managed review keys, exact saved-snapshot proof binding, four approved selections, concurrency/retry receipts, correction/office policy, approved guarded deployment design | NO-GO; existing submission service intentionally disposable-only |

## Security, rollback and owner decisions

Never conflate portal display identity with legal applicant identity or automatically split legacy names. No Google verification-policy change is in scope. Current eligibility requires portal verification regardless of linked accounts. A future real-session integration must re-read identity state at access and transaction boundaries, not trust stale session display values.

Future HTTP boundaries must retain server-derived owner IDs, CSRF and same-origin checks, limits, private/no-store responses, no PII in logs/audits, no browser applicant storage, immutable submitted revisions, application/revision expected versions and safe explicit retry. Review must display the saved snapshot bound to proof. Any draft/publication change requires fresh review/selections. No automatic command replay on reauthentication.

Rollback for the proposed first read-only milestone: disable its separate gate, remove its entry/controller/context and retain existing preview/navigation/application behavior. No migration or record deletion should be needed. For later persistence, disabling access must preserve records/audit/receipts; destructive schema rollback is not an acceptable response after data exists. Backup/restore quarantine and proof/key/claim replay implications need deployment rehearsal before that milestone. Existing disposable migration guards must not be weakened.

Pending owners/evidence: application operations approves official forms, required fields and representative rules; service-area/technical operations approves locality/classification coverage; privacy/legal approves purposes/basis/notices/retention; connection/billing operations approves offline printing/signing/payment and correction flow; security/IT approves identity mapping, infrastructure keys, shared limits, HTTPS, least privilege, schema compatibility and recovery. Named individual owners, signed approvals and authoritative sources have not been supplied. Synthetic content is not an official fallback.

## Fresh regression evidence

Local PHP 8.2.12, MariaDB 10.4.32, Chrome 155. Synthetic data only:

| Suite | Result |
|---|---|
| Stage 1 HTTP/browser | PASS 230 checks; four synthetic submissions; seven stages each; four consent records/one event/one request receipt each; nonfixture schema signatures unchanged |
| Draft persistence | PASS 149 assertions; independent-process races |
| Atomic submission | PASS 97 assertions; transactions/concurrency |
| Pure contract | PASS 42 assertions |
| Foundation/navigation/default-off | PASS 47 assertions |
| Separate Apache preview/navigation | PASS 77 assertions |
| Authentication security | PASS 42 assertions |
| Structure | PASS 201 assertions |
| PALECO read-only contract | PASS |

All created disposable schemas were removed. HTTP suite regenerated its existing result JSON/screenshots; no application code/settings were edited by this task. Schema-signature isolation checks inspect metadata, not customer rows. No actual portal migration, real Google/email request, customer identity access or operational write occurred. These tests do not prove authenticated actual-port-80 or production readiness.

## Recommended first controlled milestone

Approve and implement **read-only Stage 1 display with synthetic content in an attested isolated portal-session environment**, using the proposed separate gate and no mutation routes. First resolve the hardcoded identity-schema boundary; do not obtain a real customer session to work around it. Keep the existing preview and temporary interactive runner separate. Authorize synthetic entry, staging persistence, real collection and actual submission only through their own gates and evidence. This task stops at analysis/reporting.
