# Task 06V — Official PALECO form gap analysis

Date: 2026-10-21. **Authoritative comparison blocked: no official New Connection/membership forms or approved procedures were found in the project or supplied for this task.** No official contents are inferred. Current implementation inventory is complete below; the corresponding official comparisons stop at “unverified.”

## 1. Source inventory and versions

| Source | Version/provenance available | Authority for this comparison |
|---|---|---|
| Official New Electric Service Connection application, all pages/annexes | Not supplied/found; document ID, revision, effective date and approver unknown | Missing; comparison blocked |
| Official membership application and signing pages | Not supplied/found; version unknown | Missing; comparison blocked |
| Approved eligibility, representative and documentary requirements | Not supplied/found; version unknown | Missing; comparison blocked |
| Approved locality/service-area and classification schedules | Not supplied/found; version unknown | Missing; comparison blocked |
| Approved New Connection privacy notice/declarations | Not supplied/found; version unknown | Missing; comparison blocked |
| Approved printing/signing/payment operating procedure | Not supplied/found; version unknown | Missing; comparison blocked |
| `resources/ebills-terms.html`, `resources/ebills-terms-source.md` | Existing eBills content/provenance files | Different service; not evidence for New Connection fields, privacy or eligibility |
| `assets/images/logo.png` | Branding asset, not a form | No requirements authority |
| `app/services/new-connection/applicant-contract.php` | `stage1.v1-draft-20261020` | Current implementation baseline only |
| Existing prototype, synthetic catalogs/notices and Tasks 06R/06U reports | Internal provisional implementation/design | Not official approval evidence |

Search scope: current workspace, including resources, reports, storage and test resources; document/image extensions, form/requirements/privacy filenames and relevant first-party PHP/HTML/text references. Dependency/Git content was excluded from authority searches. Generated test screenshots are synthetic UI evidence, not scanned official forms. The inventory found no candidate official form requiring extraction or document-version verification. Existing Task 05A records the same missing-source issue, but that report is not independent official evidence.

No internet source, operational database, real customer document, external attachment directory or inferred legacy-table field was substituted. No new official documents were attached to this request. This finding concerns the available project sources, not whether PALECO maintains such documents elsewhere.

## 2. Complete current-field comparison

**U** means unverified due to missing authoritative documentation. None can be classified as officially required, officially optional, officially absent, or required by an approved separate procedure. Absence of a source is not evidence that the official form omits a field.

All rows below have official comparison **U** for label, maximum length, requiredness, format and validation. Current rules are documented only to enable later comparison. Text means string/null, trimmed, valid UTF-8, prohibited control characters rejected; empty text becomes null. Maximums are characters unless noted. Requiredness applies to synthetic complete-readiness checks; valid incomplete drafts are permitted.

### Applicant identity and portal contact

| Current field / label | Current type/limit and behavior | Official finding |
|---|---|---|
| `applicant.first_name` / First name | Text 25; required, Unicode text | U |
| `applicant.middle_name` / Middle name | Text 25; optional | U |
| `applicant.last_name` / Last name | Text 25; required | U |
| `applicant.extension_name` / Name extension | Text 25; optional free text | U |
| `applicant.contact_number` / Mobile number | Input text 64; required; supported Philippine local/international forms normalize to 11-digit `09…`, spaces/hyphens stripped | U |
| `applicant.profile_confirmed` / Applicant identity confirmation | Boolean; required true for readiness, UI edits invalidate confirmation | U |
| `applicant.email` / Verified portal email | Server-supplied valid email, max 254 bytes; read-only, not a client-contract input | U; no evidence that account and applicant contact emails must match |

Signed-in `user_id`, display `name`, active `status` and `email_verified_at` are portal authentication context, not evidence of legal applicant identity. Existing eligibility implementation is not an official form requirement. No automatic full-name split occurs. No separate representative model is implemented; whether one is officially necessary is U.

### Residential address

| Current field / label | Current type/limit and behavior | Official finding |
|---|---|---|
| `residential_address.province_code` / Province | Text 32; catalog membership; must match selected municipality, including configured null-province cases | U |
| `residential_address.city_municipality_code` / City / municipality | Text 32; required catalog member | U |
| `residential_address.barangay_code` / Barangay | Text 32; required, belongs to municipality | U |
| `residential_address.street_purok_sitio` / Street / purok / sitio | Text 160; required | U |
| `residential_address.house_lot_unit_building` / House / lot / unit / building | Text 100; optional | U |
| `residential_address.additional_address` / Additional address | Text 160; optional | U |
| `residential_address.postal_code` / Postal code | Text 4; optional, exactly four ASCII digits when supplied, leading zeroes retained | U |

### Proposed service location

| Current field / label | Current type/limit and behavior | Official finding |
|---|---|---|
| `service_location.province_code` / Province | Text 32; municipality relationship | U |
| `service_location.city_municipality_code` / City / municipality | Text 32; required catalog member | U |
| `service_location.barangay_code` / Barangay | Text 32; required, municipality relationship; coverage checked if provided in catalog | U |
| `service_location.street_purok_sitio` / Street / purok / sitio | Text 160; required | U |
| `service_location.house_lot_unit_building` / House / lot / unit / building | Text 100; optional | U |
| `service_location.additional_address` / Additional address | Text 160; optional | U |
| `service_location.postal_code` / Postal code | Text 4; optional four ASCII digits | U |
| `service_location.landmark` / Landmark | Text 200; optional | U |
| `service_location.same_as_residential` / Same as residential address | Boolean; optional; server copies seven residential fields when selected; client direct service-address editing turns copying off | U |

Residency and proposed service-area eligibility are separate in the implementation. Synthetic catalog coverage is not official eligibility evidence.

### Service request, review and acknowledgments

| Current field / label | Current type/limit and behavior | Official finding |
|---|---|---|
| `service_request.premises_classification_code` / Premises classification | Text 32; required example-catalog member | U |
| `service_request.occupancy_code` / Occupancy | Text 32; required example-catalog member | U |
| `service_request.connection_type_code` / Conditional connection category | Text 32; required only for applicable configured premises categories; mismatches rejected | U |
| `service_request.occupancy_other` / Other occupancy details | Text 100; required for configured Other codes, rejected otherwise | U |
| `service_request.requested_service_details` / Requested service clarification | Text 500; optional, normalized multiline text | U |
| `review.information_confirmed` / Information review confirmation | Boolean; required for synthetic submission; saved drafts force false, fresh review after edits | U |
| `acknowledgments.privacy_notice` / Privacy notice acknowledgment | Separate boolean required true for synthetic submission | U |
| `acknowledgments.information_accuracy` / Accuracy declaration | Separate boolean required true | U |
| `acknowledgments.application_verification` / Application/document verification | Separate boolean required true | U |
| `acknowledgments.physical_signing` / Printing, handwritten signing and payment | Separate boolean required true | U |

Acknowledgments are excluded from the applicant snapshot and recorded separately against server-published synthetic notice versions/hashes on synthetic submit. Their existence does not prove official wording, consent necessity, legal effect or lawful processing basis.

### Server-derived fields and metadata

| Current derived fields | Current behavior | Official finding |
|---|---|---|
| `residential_address.country_code`, `service_location.country_code` | Fixed `PH` | U: Philippine-only assumptions unconfirmed by official sources |
| `residential_address.province_name`, `residential_address.city_municipality_name`, `residential_address.barangay_name` | Server-resolved catalog labels/null; no separate per-field max in contract | U for each |
| `service_location.province_name`, `service_location.city_municipality_name`, `service_location.barangay_name` | Server-resolved catalog labels/null; no separate per-field max | U for each |
| `service_request.request_kind` | Fixed server-owned `new_service`; displayed as New Electric Service Connection | U: official naming/classification unknown |
| `contract_version` | Fixed current draft contract version | Technical implementation metadata, not asserted official form content |
| `catalog_versions.localities`, `catalog_versions.service_requests` | Server-owned version lineage | Technical metadata; authoritative catalog sources unverified |

Public reference, owner/actor IDs, lock/revision versions, SHA-256, timestamps, proof/key IDs and request receipts are technical security/workflow metadata, not applicant questions. They are not additions justified by a paper form. Total input/canonical limits are 64/32 KiB; these are implementation safeguards, not official document limits. No official discrepancy can be asserted without a source counterpart.

## 3. Applicant eligibility and supporting documents

| Topic | Current implementation distinction | Official evidence / conclusion |
|---|---|---|
| Applicant versus authorized representative | Account identity separate from applicant names; no implemented representative fields | Missing; authorized applicant categories/evidence unknown |
| Identity verification | Active verified portal email checked; this is not legal identity verification | Missing; accepted identity documents, inspection and exceptions unknown |
| Contact information | Mobile plus verified portal email | Missing; official contact requirements/alternatives unknown |
| Service address | Separate structured address and optional landmark | Missing; minimum location detail and coverage rules unknown |
| Required supporting documents | No upload/collection in this read-only milestone | Missing; cannot list required IDs, ownership documents, certificates or other evidence |
| Existing PALECO electric account | Current module permits zero linked accounts | Missing; no official evidence establishes or excludes an account prerequisite |

No Google authentication or verification-policy change is recommended from this missing evidence. Portal email verification must not be described as official applicant identity verification.

## 4. Privacy and declaration gaps

All four synthetic topics lack an approved source counterpart. Obtain the current approved privacy notice, purposes/lawful basis decision, rights/contact information, retention and disclosure provisions; approved accuracy/verification declarations; and the physical signing/payment procedure with signature areas annotated. No checkbox can be equated with a handwritten signature, approved electronic signature or lawful basis merely because it appears in the prototype.

The eBills document does not close these gaps. Do not copy its acceptance wording or presume its scope includes New Connection. Do not infer retention, mandatory consent, payment amounts/timing or printed-form responsibilities from the synthetic workflow.

## 5. Recommendation classification and contract impact

| Classification | Recommendation |
|---|---|
| Supported by official evidence | **No application-field/policy change can currently be placed here.** No authoritative sources were available. |
| Pending owner decision | Obtain and attest the sources below; map exact page/field/requirement references; approve necessity/requiredness, naming, limits, representative/email rules, coverage, declarations and offline procedures. |
| Unsupported — do not implement | Treat example catalogs/declarations as official; add guessed mandatory supporting documents or sensitive legacy fields; require an existing account based on assumptions; alter legal name/email/representative rules; claim checkbox signature/legal basis; change Google policy; enable collection to “match” an unavailable form. |

**No contract change is evidenced or authorized.** Keep `stage1.v1-draft-20261020` unchanged. If later evidence supports adding/removing/renaming fields, changing JSON types, normalization, limits, requiredness or conditional rules, explicitly version the contract with draft/review/submitted-snapshot compatibility tests; do not silently reinterpret existing revisions. A separate contact email or representative model would require such review/versioning.

New approved catalog/notice contents need their own immutable versions/hashes and publication decisions. They do not automatically require a schema-contract version if field semantics remain compatible, but changed eligibility/conditional semantics must be assessed for contract compatibility and always invalidate affected reviews as designed. Presentation-only label corrections may avoid a contract change when semantics and persisted data are identical; exact official labels remain unavailable today.

## 6. Missing documents and owner actions

Request these from the project owner, with named custodian/approver, document ID, revision, effective date, all pages/annexes and evidence of current approval:

1. Membership application and electric service application originals; annotate applicant/staff/inspection-only fields and physical signature areas.
2. Official eligibility/representative and documentary-requirement procedures, including applicant categories, exceptions and existing-account rules.
3. Approved address/service-area catalog and premises/occupancy/classification applicability schedules with custodians/versioning.
4. Approved New Connection privacy notice, processing/retention decision and declaration texts.
5. Approved staff printing, office visit, handwritten signing, payment and signed-document verification procedure.

Operations/membership, technical/service-area, billing/cashier, privacy/legal and authentication/security owner functions should validate their respective documents. Named owners and approvals have not been supplied; this is a request inventory, not evidence of organizational assignment.

## 7. Recommended sequence and task boundary

First obtain/attest the sources. Then compare each row above to exact document/page references, distinguishing mandatory instructions from merely present paper blanks; record omitted fields separately from fields required by another approved procedure. Reconcile conflicting versions with the owner. Complete data-minimization/privacy review before approving a versioned change proposal. Only afterward implement approved contract/catalog/notice changes in isolated fixtures and rerun acceptance; actual portal collection/submission remains a separate authorization.

Only this Markdown report was created. No application code, database query/migration, authentication request, browser/customer journey, feature flag or applicant collection setting was changed or exercised. No regression suite was needed for this documentation-only inventory. Task 06V stops here; official comparison remains blocked until sources are supplied.
