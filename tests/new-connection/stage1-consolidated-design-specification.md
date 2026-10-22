# Task 06Z — Consolidated Stage 1 design specification

2026-10-22. **Design/documentation only. No implementation or collection authorization.** This specification consolidates the owner's Task 06Z decisions. It supersedes earlier proposed UI/field layouts for future work, not the existing executable v1 contract or immutable records.

## 1. Authority, baseline and release boundaries

Reviewed the attached Task 06Z request; current prototype, `applicant-contract.php`, draft/workflow services and profile-handling source; Task 06Y findings; and all four owner-supplied PNGs directly. The image identities/hashes and detailed field locations remain in [Task 06W](stage1-four-form-requirements-mapping.md). Current official revision/approval of those scans is still unverified. The owner's decisions here establish the requested design, not approval of all paper terms, production privacy processing, current fees or geographic eligibility.

Current contract: `stage1.v1-draft-20261020`. Current prototype has five sections, editable applicant fields, residential address, service classifications and synthetic localities. Education is UI-only; spouse/personal details/pin are absent from persisted v1. No silent extension is permitted.

Proposed next contract identifier: **`stage1.v2-design-20261022`**, a design identifier until separately authorized synthetic implementation. All names/limits below labeled “proposed” are engineering recommendations, not additional owner-approved requirements. No actual portal route, authentication/Google behavior, feature flag, workflow, schema or fee administration changes in this task.

Release approvals remain separate: read-only display; synthetic entry; persistent synthetic staging drafts; real applicant collection; actual submission. Approving this specification is not enabling any of them. Optional sensitive fields are not production-authorized merely because they are non-required.

## 2. Four-section form layout

1. **Applicant and Personal Information:** read-only structured portal identity; Profile Settings correction link; profile confirmation; DOB and derived age; gender/civil status; conditional spouse names.
2. **Additional Personal Information:** non-required education/Other, occupation, income source/amount, dependents, religion, social affiliation and TIN. Production rendering of sensitive fields requires specific processing approval; do not display disabled sensitive inputs as an invitation to collect them.
3. **Proposed Service Location:** server-fixed Palawan context; dependent official municipality/barangay choices and eligibility feedback; street/purok/sitio; building/postal/landmark; accessible map placement/confirmation.
4. **Review and Confirmation:** server-saved snapshot, section Edit links, profile confirmation status, locality/pin verification state and approved versioned notice/acknowledgment selections. Review is not an official printed form, signature, approval or energization status.

Use the existing PALECO shell, local CSS/icons, theme, mobile navigation and focus conventions. Show one `* Required fields` legend; red asterisks and programmatic required status on all readiness-required controls, including conditional spouse names and pin confirmation. Do not put “Optional” in labels/placeholders. Empty non-required values are valid. Draft saves must not be blocked by browser-native complete-form validation.

Back/Continue/section navigation must preserve unsaved in-memory state, announce the current section and focus its heading. An error summary links to and reveals the correct section/control; inline errors use `aria-describedby`/`aria-invalid`. Map errors focus an accessible placement/confirmation control. Prefer collapsed guidance and accessible review groups to lengthy repeated instructions. Session-expiry messages explain that only committed drafts survive; no personal browser-storage persistence or automatic replay after reauthentication.

## 3. Complete proposed inventory and requiredness

“Required” below means necessary for submission readiness, not completeness of every draft. Text is UTF-8 string/null, trimmed, empty→null, safe Unicode, control characters rejected, no alphabet-only personal-name rule. Proposed text limits are characters unless specified. Final source/processing and numeric-limit decisions are listed in section 12.

### 3.1 Server-owned profile identity

Only the currently signed-in profile owner may apply. No alternate applicant, representative selector or separate applicant identity is introduced. The spouse is related personal information, not an applicant principal or authorization delegate. Existing linked electric accounts are **not required**.

| Proposed snapshot path / label | Source/type/limit | Requiredness / presentation |
|---|---|---|
| `identity.first_name` / First name | Server profile string; retain current profile's 25-character compatibility pending owner review | Required profile completeness; read-only |
| `identity.middle_name` / Middle name | Server profile string/null, currently max 25 | Non-required; read-only, missing shown neutrally |
| `identity.last_name` / Last name | Server profile string, currently max 25 | Required profile completeness; read-only |
| `identity.extension_name` / Extension | Server profile string/null, currently max 25 | Non-required; read-only |
| `identity.contact_number` / Contact number | Server profile; existing PH-mobile normalization, canonical 11-digit `09…` | Proposed required profile completeness matching v1; owner should confirm contact alternatives; read-only |
| `identity.email` / Verified portal email | Server profile valid email, max 254 bytes | Required; active portal email verification, read-only |
| `confirmations.profile_confirmed` / Confirm profile information | Applicant boolean | Required true, bound to server profile fingerprint; never accepted as proof of legal identity |
| `identity.owner_user_id` | Authenticated server identity matching application owner; FK-compatible integer | Server-only, not displayed as applicant input |
| `identity.profile_fingerprint` and `identity.snapshot_at` | Server canonical digest and UTC timestamp | Server-only lineage; no raw PII digest in logs/URLs |

The contact requiredness recommendation does not authorize changing Profile Settings or verification policy. Missing structured first/last names or unusable contact blocks readiness and directs the owner to Profile Settings; saving other incomplete draft sections remains allowed where authorized. Never guess/split legacy `users.name`. If the existing profile workflow cannot safely serve a zero-linked-account user, record a separate integration blocker rather than bypassing identity completeness or loading account synchronization from Stage 1.

### 3.2 Required personal information and conditional spouse

| Proposed path / label | Type / proposed bound | Requiredness / rule |
|---|---|---|
| `personal.date_of_birth` / Date of birth | Strict date-only `YYYY-MM-DD`, 10 ASCII characters | Required; real Gregorian date, not future relative to server Asia/Manila date |
| `personal.age_years` / Age | Server-derived nonnegative integer, read-only | Derived, never accepted as browser input; calculation reference date retained |
| `personal.age_as_of` | Server date-only, server-only | Recalculate at current review/submission; retain submission value in immutable snapshot |
| `personal.gender` / Gender | Enum code | Required: `male` → Male; `female` → Female; `prefer_not_to_say` → Prefer not to say |
| `personal.civil_status` / Civil status | Enum code | Required: `single` → Single; `married` → Married; `widowed` → Widowed; `separated` → Separated |
| `spouse.first_name` / Spouse first name | Unicode text/null, proposed 25 | Required only when Married |
| `spouse.middle_name` / Spouse middle name | Unicode text/null, proposed 25 | Non-required, applicable only when Married |
| `spouse.last_name` / Spouse last name | Unicode text/null, proposed 25 | Required only when Married |

No extra gender/civil-status choices, spouse signature upload or numeric minimum legal age are inferred. F3 says legal age; the threshold/verification policy still needs confirmation before eligibility enforcement. Date parsing must reject rollover dates. Derive age as completed birthdays on the specified Asia/Manila date; leap-day birthday treatment (recommended March 1 in non-leap years) remains an explicit owner rule to settle and test. Do not store client-calculated age or calculate by dividing days by 365.

When leaving Married, explain that spouse draft details will be cleared, then clear all spouse fields in memory and canonical next saved snapshot. Server canonicalization removes stale spouse data when status is not Married, returns the cleared canonical input and invalidates proof/selections. Never store hidden spouse content as active applicant data. Leaving Married must not alter earlier immutable submitted revisions. Unsaved spouse drafts must not reappear from localStorage, browser autofill or stale responses; switching back starts blank. Missing status cannot retain spouse details as applicable data.

### 3.3 Additional personal information — all non-required

| Proposed path / label | Type / proposed bound and validation | Condition/privacy gate |
|---|---|---|
| `additional.education_code` / Highest educational attainment | Enum/null; twelve choices below | Non-required; no selection valid |
| `additional.education_other` / Specify educational attainment | Text/null, proposed 100 | Visible only for Other; non-required in this design because owner makes all additional fields non-required; clear when choice changes |
| `additional.occupation` / Occupation | Text/null, proposed 100 | Purpose, retention and production authorization needed |
| `additional.income_source` / Source of income | Text/null, proposed 160 | Financial profiling review; no new categorical assumptions |
| `additional.annual_income_php` / Annual income | Decimal-string/null, proposed nonnegative `DECIMAL(12,2)` compatible, max `9999999999.99` | Blank differs from zero; no floats/exponent/currency symbols in canonical payload; bound is proposed, not a fee or eligibility rule |
| `additional.dependent_count` / Number of dependents | Integer/null, proposed 0–999 | Reject boolean, negative, fractional/exponent representations; bound needs owner confirmation; do not collect dependent identities |
| `additional.religion` / Religion | Text/null, proposed 100 | Especially sensitive purpose/legal-basis review; omission must not block service |
| `additional.social_affiliation` / Social affiliation | Text/null, proposed 160 | Potential sensitive associations/third-party references; omission valid |
| `additional.tin` / TIN | String/null, proposed max 32 | Preserve supplied leading zeroes; safe ASCII digits/separators, trim only until an approved canonical format is defined; no government verification claim, card upload or mandatory rule |

Proposed stable education codes, with owner-approved displayed labels:

| Code | Label |
|---|---|
| `none` | No Formal Education |
| `elementary_undergraduate` | Elementary Undergraduate |
| `elementary_graduate` | Elementary Graduate |
| `high_school_undergraduate` | High School Undergraduate |
| `high_school_graduate` | High School Graduate |
| `senior_high_school_graduate` | Senior High School Graduate |
| `technical_vocational_graduate` | Technical / Vocational Graduate |
| `college_undergraduate` | College Undergraduate |
| `college_graduate` | College Graduate |
| `masters` | Postgraduate (Master's Degree) |
| `doctorate` | Postgraduate (Doctorate) |
| `other` | Other |

Codes are proposed contract identifiers, not official administrative categories. Education Other remains non-required unless the owner later explicitly changes that rule. Inapplicable Other text is removed server-side and from the UI. Persisted v2 review must include the chosen label and applicable specification; unlike 06Y's UI-only control, it cannot claim to save while omitting this data.

### 3.4 Proposed service location

| Proposed path / label | Type / bound | Requiredness / source |
|---|---|---|
| `service_location.province_context` / Province | Server constant `Palawan` | Fixed/read-only; not a browser field; not proof all Palawan is eligible |
| `service_location.province_psgc` | Server official identifier where applicable | Do not invent a code or mis-parent an independent city; nullable/derived per official hierarchy |
| `service_location.city_municipality_psgc` / City / Municipality | Official stable string identifier; proposed max 32 | Required; published locality catalog and approved eligibility join |
| `service_location.barangay_psgc` / Barangay | Official stable string identifier; proposed max 32 | Required; child of selected city/municipality |
| `service_location.street_purok_sitio` / Street / Purok / Sitio | Text/null, retain 160 | Required |
| `service_location.house_lot_building` / House / Lot / Building Number | Text/null, retain 100 | Non-required; no numeric-only restriction |
| `service_location.postal_code` / Postal Code | String/null, four ASCII digits | Non-required, leading zeroes retained |
| `service_location.landmark_directions` / Landmark and directions | Text/null, proposed 200; plain multiline if approved | Non-required; no unrelated neighbor identity request |
| `service_location.pin.latitude` | Finite JSON number/null, WGS84 degrees, −90…90 | Required at readiness; never fabricate/default to zero |
| `service_location.pin.longitude` | Finite JSON number/null, −180…180 | Required at readiness; preserve sufficient source precision, no silent rounding across boundaries |
| `service_location.pin.confirmed` / Confirm exact location | Applicant boolean, corroborated server-side | Required true tied to exact point + locality/catalog/geometry/eligibility versions |
| `service_location.pin.confirmed_at`, `validated_at` | Server UTC times | Server-owned evidence, not trusted browser time |
| `service_location.catalog_version`, `geometry_version`, `eligibility_version` | Server manifest identifiers/digests | Published trusted context, not arbitrary browser versions |
| Resolved locality labels / canonical point fingerprint | Server-derived | Review/print lineage; browser labels do not become authority |

Province context Palawan is not a request to rewrite official PSGC hierarchy. Include Puerto Princesa only under an approved geographic/coverage mapping that respects its actual classification. No invented administrative parent code. Remove the residential block, same-as-residential flag, separate additional-address question and premises/occupancy/request classification questions from final v2 Stage 1. Fixed new-service kind may remain server metadata; do not expose request classifications through another label.

### 3.5 Review/confirmation metadata

Retain server reference, contract/revision/application lock versions, immutable snapshot hash, review nonce/proof, proof-key ID, publication epochs and consent lineage. Client commands carry only closed, bounded expected-version tokens, a random idempotency key, applicant-editable content where applicable, and permitted confirmation selections. No owner ID, role, status, publication hash, age, profile name/contact/email override or schema choice comes from browser input.

`confirmations.information_confirmed` is required after saved-snapshot review. Four acknowledgment topics from v1 are candidate notice/publication mappings, not approved text; exact wording, legal purpose and whether each is required remain privacy/process release blockers. No applicant/spouse signature is created by a checkbox. Conditional physical spouse signing belongs to Stage 6 policy, not Stage 1 electronic signature collection.

## 4. Profile-linking, freshness and immutable identity

Source inspection shows registration stores structured first/last/middle/extension/contact fields and current Profile Settings calls `saveProfile()` through the existing enrollment service. That service has a structured-profile SELECT and updates users; it is coupled to existing portal code. Do not call the dashboard controller or enrollment operations from Stage 1. Do not assume a profile version column exists; none was established by this documentation task.

Future server profile repository reads only the authenticated owner using a trusted connection boundary and active/verified checks. All corrections occur through Profile Settings, with a fixed safe return route and unsaved-change warning; no name/contact editing in Stage 1. Missing fields show an action to complete profile, never split `users.name`.

Fingerprint the canonical identity field set (owner, structured names/contact/email and relevant verification state) server-side. Confirmation is bound to that fingerprint plus application/revision context. On draft open/resume, save, review and submit, compare against current profile; do not trust a session name or client digest. If changed, clear profile/information confirmation, current proof and acknowledgment selections; return a clear refresh-required response without overwriting newer edits. A profile refresh/save explicitly creates the new draft identity snapshot. No silent overwrite of a submitted identity.

At atomic submission re-read/lock current user/profile alongside application/revision using a documented lock order compatible with the existing services. A simultaneous profile update must either precede the checked snapshot or cause conflict/review refresh. Test independent connections; hashing only at browser render is insufficient. Do not add a customer-schema version column in this phase; synthetic profile versions or canonical fingerprints can prove the design first. Failed/uncertain commits use existing authorized receipt recovery, never blind command replay.

Submitted snapshots retain the identity, age-as-of, locality labels/versions, pin and published notices acknowledged at submission. Later profile changes affect future drafts and eligibility, not historical content. Corrections to submitted information require a separately authorized new revision/event; staff must not impersonate the profile owner or mutate signed history.

## 5. Validation, draft and conditional behavior

Preserve closed-object validation, strict types/depth/UTF-8, prepared queries, 64 KiB input and 32 KiB canonical caps unless a separately tested limit change is approved. Reject unknown v1/removed keys in v2 commands rather than accepting ignored legacy fields. Server-owned keys are not accepted just because they appear in snapshots. Canonical server output can contain enriched identity/age/labels/lineage; command input cannot override them.

Draft validation has two independent results: syntactic/domain validity and submission completeness. Missing DOB/gender/status/spouse/location/pin/confirmations may be saved as null/false in a valid incomplete draft. Malformed dates, invalid types, oversized values, incompatible locality codes and nonfinite/out-of-range coordinates are invalid even for drafts. Geographic uncertainty is explicit `unverified`, not `eligible=true`.

If map tiles are down, preserve/save other valid draft sections and any previously valid pin with its provenance; never delete it merely because tiles fail. Unconfirmed or stale-version pins cannot pass final readiness. If authoritative geometry/eligibility verification is unavailable, permit an incomplete unverified draft, but deny review-proof generation for submission and actual submission until server verification succeeds. A missing provider does not waive the required pin.

Civil-status/education changes must canonicalize inapplicable details away. Every substantive saved change, profile refresh, point movement, relevant catalog/notice change and expired proof requires fresh review and acknowledgment selections. A tile style refresh alone need not invalidate a verified point if geometry/eligibility/point are unchanged. Birthday changes affecting age-as-of require refreshed review where a previously issued proof spans the calculation date; retain the receipt's original date for exact committed retries.

Use existing create/retrieve/save/version/receipt contracts and specialized atomic submit architecture, extended via explicit version dispatch. Preserve ownership, active/verified status, exactly seven initialized stages, per-application/revision locks, optimistic expected versions, distinct idempotency namespaces, safe audit metadata and immutable submitted revisions. A legacy v1 synthetic success must never be labeled v2/map-complete readiness.

## 6. Geography and Leaflet architecture

### Approved data prerequisites

Three separately versioned inputs are required: official PSGC names/relationships; current approved boundary geometries; PALECO application eligibility/service coverage. The [PSA PSGC directory](https://psa.gov.ph/classification/psgc/barangays) is the administrative reference, not a utility serviceability decision. The [PALECO coverage page](https://paleco.net/paleco/coverage/) is context, not a machine-approved per-point admission catalog. Government-hosted [PSA barangay geometry service](https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer) identifies its boundaries as indicative June 2016 data; do not silently combine them with a newer PSGC release as current approved boundaries.

Manifests must record publisher/custodian approval, release/effective dates, source hashes/licenses, CRS/transform, stable identifiers, valid multipolygons/holes, geometry resolution and eligibility exceptions. Handle renamed/split/merged localities, islands, coastal points, overlapping/edge cases and independent cities. A version change can invalidate review/pin confirmation. No centroid is an applicant point or proof of coverage.

### Client map

Vendor a pinned stable Leaflet build and license locally; no CDN request-time dependency. Leaflet is the interface, not a boundary/eligibility authority. Use GeoJSON overlays, bounds fitting, marker movement and accessible controls supported by the [official API reference](https://leafletjs.com/reference.html). On municipality selection clear incompatible barangay, load/fit verified municipal bounds; on barangay selection fit that geometry with a bounded maximum zoom. Do not place a point automatically. User explicitly places/adjusts and confirms the exact point; moving it clears confirmation. For simplicity invalidate confirmation on every locality change, even if coordinates might remain compatible, and require reconfirmation.

Support keyboard-operable placement/adjustment and a text-coordinate alternative with the same locality/geometry validation; do not invent a center coordinate when no geometry is available. Provide a non-map address/draft path, explanatory unavailable state and staff-assisted process only if separately approved. An accessible fallback cannot bypass the final required confirmed point. Browser geolocation is not automatic; any future use needs explicit user action and separate privacy review.

Server receives only bounded point/locality candidates and revalidates coordinate ranges/finite values, selected official hierarchy, geometry membership, current eligibility/version and confirmation binding. Define a documented boundary-edge tolerance and staff-review policy before enabling; no arbitrary buffer or “nearest barangay” correction. Treat view zoom as presentation metadata, not evidence. Retain WGS84 point provenance and server validation timestamps.

### Tiles, attribution, privacy and deployment

Use OSM-derived data with a **separately approved** production tile provider or self-hosted service. OSM's public standard tiles are not an SLA-backed default: the [official tile policy](https://operations.osmfoundation.org/policies/tiles/) requires attribution, cache compliance and browser Referer, and prohibits bulk prefetch. Do not use public standard tiles for offline-test scraping or silently weaken existing no-referrer protections. Choose a compatible provider/deployment design before production; do not resolve the conflict by exposing applicant references in URLs/referrers.

Keep visible attribution and appropriate licensing links according to [OSM copyright/licensing guidance](https://www.openstreetmap.org/copyright), with additional boundary/provider attribution. Separate tile caching from private no-store applicant HTML/JSON. Map assets/requests must not carry applicant names, IDs, references, notices or draft payloads. Tile coordinates/IP can reveal viewed locations: assess processor/recipient disclosures, retention, network metadata and vendor terms. No reverse-geocoding/address search service is assumed approved.

Production prerequisites: HTTPS, restricted provider keys/origins, CSP allowlist without broad weakening, rate/quota/cost review, outage behavior, tile-cache policy, accessible keyboard acceptance and versioned server geometry validation. Synthetic offline tests use self-hosted deterministic non-real test geometry/tiles or blocked-provider tests, clearly identified as test data. Never present synthetic names/codes/coordinates as Palawan evidence.

## 7. Exactly three application roles

| Action / scope | Applicant | Administrator | PALECO Personnel |
|---|---|---|---|
| View own applications | Own only | All within module administration authorization | Assigned applications or explicitly granted queue |
| Edit applicant draft / confirm identity / submit Stage 1 | Own, active/verified, permitted lifecycle only | No applicant impersonation; authorized correction workflow only | No applicant impersonation; authorized correction workflow only |
| Staff stage actions | None | Oversight plus explicit action permissions and audit | Explicit permission + assignment/authorized queue + stage/lifecycle scope |
| View sensitive details | Own supplied data | Least-privilege purpose-based access, audited where required | Only necessary fields for assigned task; redact unrelated sensitive data |
| Manage personnel permissions/queues | None | Explicit provisioning/revocation authority | None |
| Manage approved versioned fee schedules | None | Future permission/approval workflow, not implemented here | Only if separately authorized action policy; not an automatic role grant |
| Delete/overwrite submitted history | None | None | None |

Module roles are **Applicant**, **Administrator**, **PALECO Personnel** only. Cashier/inspector/PMES verifier/fee administrator are not separate application roles. Task permissions may be granular (e.g. inspection decision, payment verification) within PALECO Personnel/Administrator; assignment, permission and current identity are all enforced server-side on every read/mutation. Email domain, Google identity, operational `nc_users`, menu visibility and knowledge of references do not grant staff access.

Explicit bootstrap/provisioning and revocation history are required; no self-upgrade and no automatic migration of earlier experimental roles into broad access. Preserve deny-by-default and audit permission changes without raw applicant values. Existing experimental role seeds/schema are not evidence that these three-role assignments are provisioned. Map them only in separately approved disposable fixtures first.

## 8. Seven-stage workflow and official-form boundaries

The owner's future labels are:

1. Applicant Information and Submission.
2. Document Submission and Verification.
3. Billing Clearance.
4. Online PMES or Verification of Existing PMES Certificate.
5. Technical Inspection.
6. Offline Payment and Handwritten Form Signing.
7. Installation Scheduling.

Current code's Stage 4/6 labels are shorter; this document does not change them. Preserve stable stage IDs, independent statuses, lifecycle separation and historical events; future label/workflow-version changes require explicit tests. Stage 4 needs a separately approved certificate acceptance/validity/verification policy; possessing a file alone is not completion. It also resolves the design direction beyond F1's printed in-person seminar, without claiming the scan itself approves online equivalence.

Stage 7 is the final online stage and **does not mean installed or energized**. Meter/transformer/seal/initial-reading/tapping/completion fields in F2/F3 remain staff execution records, never applicant Stage 1 inputs. F4 board membership approval is not online Stage 1 submission approval; timing remains an owner process decision.

F4 names/contact/Future Address and personal/demographic fields inform this proposed design. F3 residence blank conflicts with removal of Stage 1 residential address: obtain an approved later-office capture/source or approved template revision; do not quietly restore a residential block. Spouse physical signature applicability, membership number, fees, receipts, teller and board fields remain offline/staff decisions. All official signature lines remain blank for physical signing during the payment visit.

Printed membership/share-capital amounts are provisional historical references only. Administrators will eventually manage approved, immutable/versioned fee schedules with approval/effective dates; do not implement that feature or hardcode the scan's values now. Fee versions must not silently change amounts already reviewed/paid. This document creates no payment flow.

## 9. Field-by-field contract gap analysis

“Prototype” means the actual existing synthetic editor, not the future design. None of these rows authorizes modifying v1 in place.

| Existing v1 field(s) | Existing prototype | Owner's future design / required version change | Pending privacy/policy |
|---|---|---|---|
| `applicant.first_name` | Editable structured prefill | Server-owned `identity.first_name`, read-only | Profile completeness/limits/legacy corrections |
| `applicant.middle_name` | Editable, non-required | Server-owned `identity.middle_name`, read-only | Null/no-middle-name convention |
| `applicant.last_name` | Editable | Server-owned `identity.last_name`, read-only | Profile limits/naming exceptions |
| `applicant.extension_name` | Editable, non-required | Server-owned `identity.extension_name`, read-only | Profile availability |
| `applicant.contact_number` | Editable/mobile normalized | Server-owned read-only identity contact | Requiredness/alternate formats pending confirmation |
| Enriched `applicant.email` | Server email/read-only | Server-owned `identity.email` | Verified current profile; no separate applicant email |
| `applicant.profile_confirmed` | Unbound boolean | Fingerprint-bound `confirmations.profile_confirmed` | New freshness/atomic race contract |
| No DOB/age | Absent | Add personal DOB + server age/as-of | Age/legal eligibility and leap-day policy |
| No gender | Absent | Add exact three-choice enum | Processing purpose/notice |
| No civil status | Absent | Add exact four-choice enum | Processing purpose/spouse rules |
| No spouse first name | Absent | Add, Married-required | Third-party processing/notice |
| No spouse middle name | Absent | Add, Married applicable/non-required | Third-party minimization |
| No spouse last name | Absent | Add, Married-required | Third-party processing/notice |
| No education code | 06Y UI-only dropdown | Persist versioned code in v2/review | Non-required processing authorization |
| No education Other | UI-only, conditional, 100 UI bound | Persist applicable non-required specification | Confirm max and non-required Other rule |
| No occupation | Absent | Add non-required | Purpose/retention approval |
| No income source | Absent | Add non-required | Financial minimization |
| No annual income | Absent | Add non-required decimal-string | Bounds/processing purpose |
| No dependents | Absent | Add non-required integer | Household minimization |
| No religion | Absent | Non-required design, production privacy-gated | Sensitive-information authorization; omit until approved |
| No social affiliation | Absent | Non-required design, production privacy-gated | Sensitive association purpose/basis |
| No TIN | Absent | Non-required string | Official format/purpose; no identity upload implied |
| `residential_address.province_code` | Editable synthetic select | Remove from v2 | F3 later source/template resolution |
| `residential_address.city_municipality_code` | Synthetic select | Remove | Same |
| `residential_address.barangay_code` | Synthetic dependency | Remove | Same |
| `residential_address.street_purok_sitio` | Required for v1 readiness | Remove | Same |
| `residential_address.house_lot_unit_building` | Non-required | Remove | No carry-over into service address by assumption |
| `residential_address.additional_address` | Non-required | Remove | Minimize new snapshots |
| `residential_address.postal_code` | Non-required | Remove | Preserve only old immutable evidence |
| `service_location.province_code` | Editable synthetic select | Replace with server Palawan context/official derived hierarchy | PSGC/independent-city mapping |
| `service_location.city_municipality_code` | `TEST-*` options | Replace with official PSGC + approved eligibility | Versioned approved source missing |
| `service_location.barangay_code` | Synthetic dependency | Replace with official child PSGC | Boundary/eligibility join missing |
| `service_location.street_purok_sitio` | Required | Retain in v2, required | Confirm max; no unrequested new address questions |
| `service_location.house_lot_unit_building` | Non-required | Rename to `house_lot_building`, explicit conversion | Max compatibility |
| `service_location.additional_address` | Non-required | Remove; no new separate question | Do not concatenate into another field silently |
| `service_location.postal_code` | Non-required four-digit string | Retain | Approved reference if provided |
| `service_location.landmark` | Non-required | Rename `landmark_directions`, explicit mapping | Newline/length decision |
| `service_location.same_as_residential` | Copy checkbox | Remove | No automatic copy in v2 conversion |
| No pin/confirmed/version fields | Disabled 06Y placeholder; no coordinate collection | Add point, explicit confirmation, server validation/version metadata | Geometry/provider/privacy blockers |
| `service_request.connection_type_code` | Conditional example | Remove | Later-stage classification policy, no substitute question |
| `service_request.premises_classification_code` | Required example | Remove | F2 house materials remain staff inspection |
| `service_request.occupancy_code` | Required example | Remove | Later staff/terms decisions if necessary |
| `service_request.occupancy_other` | Conditional text | Remove | Do not preserve hidden active content |
| `service_request.requested_service_details` | Non-required | Remove from four-section design | No replacement free text introduced |
| Server `service_request.request_kind` | Fixed new-service context | Retain only server technical metadata if needed | No editable service-type classification |
| `review.information_confirmed` | Required boolean | `confirmations.information_confirmed`, bound saved snapshot | Approved wording |
| Four `acknowledgments.*` | Synthetic separate controls | Versioned selection envelope/consent evidence, no silent text adoption | Approved text, lawful purposes and requiredness |
| Resolved address labels/country/catalog versions/contract version | Enriched v1 snapshot | Remove residence metadata; rebuild service/profile/geometry lineage in v2 | Bounds/version/provenance rules |

No separate applicant/representative identity, photograph, ID attachment, technical meter fields or payment questions are added by this specification.

## 10. Synthetic-only version transition strategy

1. Freeze v1 contract/fixtures as a compatibility baseline. Implement v2 as an explicit version-dispatched contract within the existing service architecture; no duplicate workflow engine or unversioned field merge.
2. Keep submission's disposable-schema guard and migration guards. Use only new random `mco_nc_test_<16 hex>` schemas and synthetic users/profiles. No customer schema migration or migration override.
3. Distinguish JSON contract version from the database revision/schema-version integer. Define their mapping explicitly; do not assume incrementing one changes validation dispatch. Confirm snapshot limits and column compatibility before proposing any additive test migration.
4. New synthetic v2 applications get v2 snapshots. Keep existing v1 draft readable using its v1 validator. Conversion is an explicit owner action on an editable draft with expected lock/revision/hash checks, a new revision/lineage event and deterministic version-specific idempotency. Never change the version label on the same saved content.
5. Rehydrate identity exclusively from current synthetic owner profile. Do not reuse editable v1 applicant names as authoritative profile data. Copy only approved service-address fields with explicit mapping and validation; do not infer official PSGC from `TEST-*` names, copy a residential address, concatenate discarded text, or fabricate a point.
6. New required DOB/gender/status/spouse/pin values remain missing; derive only trusted metadata. Reset profile/review/pin confirmation, proof and acknowledgments. Education from the old UI was not persisted and cannot be recovered by guessing. Additional sensitive values start absent.
7. Preserve prior revisions as history subject to approved retention, with restricted access; do not expose removed sensitive fields in the active v2 editor or arbitrary audit JSON. Do not convert submitted v1 snapshots in place; v1 receipts retain their original authorization/hash/version behavior.
8. Catalog/version changes and schema restore must fail closed for stale proofs. Extend exact-retry tests across mixed v1/v2 deployments. Any conversion failure rolls back the new revision and version changes atomically.

## 11. Privacy and security requirements

DOB, gender/civil status, spouse, religion, affiliation, income, dependents, TIN and exact home pin require documented purpose, minimized scope, approved basis, recipients/rights notice, retention/deletion and backup handling. Non-required fields must never influence readiness/scoring/eligibility covertly. Do not equate profile confirmation, Google sign-in, online acknowledgments or a map pin with physical identity verification/signature.

For real deployment, optional sensitive collection stays unavailable until specifically approved. Synthetic tests may use obviously fabricated data, not real staff/customer identities. No production grants arise from the three-role design. Protect exact location and third-party spouse data through field-level purpose-based access, safe audit metadata and private no-store responses. No PII in query strings, localStorage/sessionStorage, provider requests or exception logs.

Retain CSRF/exact Origin/method/content-type/body checks, server identity/ownership, current-user validation, session expiry/regeneration, generic infrastructure errors, shared rate limits and explicit receipt recovery. No ordinary login dependency on claim storage and no authentication policy change. Applicant data does not authorize reading operational PALECO tables. Existing eBills/linking workflows remain unchanged.

## 12. Remaining owner decisions and release blockers

| Item | Status / decision needed |
|---|---|
| Four sections, self-only profile identity, no linked-account prerequisite | Owner design decision recorded; not yet implemented |
| Required personal enums/DOB and Married spouse names | Owner design decision recorded; production purpose/privacy/verification still required |
| Additional fields non-required | Design decision only; sensitive processing not automatically authorized |
| Contact/profile limits and legacy completeness | Confirm mobile-required assumption, approved bounds and safe zero-account Profile Settings path |
| Age rules | Approve eligibility threshold/evidence, acceptable historical dates and leap-day rule; no guessed threshold |
| Spouse/signing | Approve third-party notice/authority and Stage 6 spouse-signature applicability; spouse is not alternate applicant |
| Optional field limits/formats | Approve proposed bounds/TIN normalization/income precision; Other specification stays non-required unless changed explicitly |
| Removal of residence/classifications | Owner design recorded; resolve F3 resident-of blank and later technical/document classification needs without restoring Stage 1 fields silently |
| PSGC, geometry and PALECO eligibility | Approved versioned join, independent-city handling, boundary-edge tolerance and exceptions missing |
| Leaflet/tiles | Interface/provider direction only; stable build, hosting/license/privacy/CSP/cache/offline/availability approval pending |
| Three-role provisioning | Named administrators, explicit personnel queues/action permissions, revocation and segregation-of-duties policy pending |
| Notices/declarations | Exact approved texts/versions, purposes, retention, mandatory selections and publication withdrawal pending |
| PMES certificate pathway | Approve acceptable existing certificate, verification/expiry and equivalence policy |
| Printing/fees/board action | Current forms/terms, approved fee version process and membership acceptance timing pending; no fee feature in scope |

## 13. Proposed implementation phases — separate approvals required

1. **Specification review:** approve disputed engineering limits/age/Other/profile rules and privacy gates; attest current forms. No code.
2. **Pure synthetic v2 contract:** closed commands, server context, conditional canonicalization, date/decimal/pin validation and deterministic conversion; add no production route/schema override.
3. **Synthetic four-section UI:** reuse current prototype/shell with explicit contract-version rendering, read-only profile, profile-change simulation, required indicators and non-persisted browser state only. Do not change real Profile Settings or Google.
4. **Isolated geography:** approved-version import pipeline plus deterministic synthetic geometry/offline tests, Leaflet local assets, accessible point confirmation and provider-outage drafts. Real geographic availability must not be inferred from synthetic test success.
5. **Disposable persistence/review/submission rehearsal:** extend existing services with version dispatch, profile/point/publication race handling, new revision conversion and mixed-version receipts. Restrict all tests to disposable schemas.
6. **Owner acceptance and staging proposal:** use existing temporary runner/Apache architecture; attest synthetic identity mapping, roles, least privilege and recovery. Only later propose actual session integration, collection and submission as distinct authorizations. No implementation begins from this documentation task.

## 14. Acceptance criteria and test plan

| Area | Required future evidence |
|---|---|
| Field/layout fidelity | Exactly four sections; all listed owner fields; no residential/same-address/premises/occupancy/representative questions; red required marks and one legend; no Optional label text |
| Identity | Owner-only IDs from session, active/verified and zero-linked eligible; read-only structured profile; no full-name split; incomplete profile directs to settings; forged profile/age/owner fields rejected |
| Profile races | Profile edits before/after save/review/submit invalidate confirmation/proof; independent concurrent profile update/submit has one authoritative identity snapshot; submitted identity remains unchanged |
| Dates/personal | Strict invalid/future dates rejected; age matches server reference date, leap/year/timezone boundaries; exact enum values; no unintended minimum-age rule |
| Spouse | Married requires first/last, middle empty accepted; other statuses clear hidden spouse content server/client; stale responses cannot restore it; no spouse identity authority |
| Additional fields | All empty passes completeness; twelve education options and non-required Other specification; Other clearing; decimal precision/range and integer types; sensitive fields absent in unauthorized collection configurations |
| Geography | Trusted fixed Palawan context with correct official hierarchy; dependent verified PSGC; invalid/mismatched codes/version rejected; coverage separated from geography; no `TEST-*` accepted as real |
| Map | Fit verified bounds without auto point; move/locality change clears confirmation; holes/edges/islands/invalid coordinates/NaN/Infinity and stale manifests tested; server rejects forged confirmation; keyboard/text placement accepted only with same rules |
| Outage | Tiles/geometry/eligibility failures allow valid incomplete drafts but never bypass required verified pin for submission; no external requests in offline fixture acceptance |
| Draft/conversion | Invalid fields no partial write; explicit v1→v2 revision conversion; no automatic fill of new personal values/pin; removed keys rejected; old submitted snapshots/receipts immutable |
| Concurrency/retry | Stale versions conflict; save-vs-submit/profile/catalog withdrawal races; exact retries return original authorized receipt; changed payload/key misuse denied; injected rollback and uncertain-commit recovery |
| Roles | Exactly three role names; applicant own-only; admin explicit personnel management; personnel assigned/authorized queues; IDOR, revoked grants and sensitive-field scoping denied server-side |
| Workflow | Exactly seven stages and independent status/event history; Stage 4 online/existing-certificate path per approved policy; Stage 7 scheduling never installation/energization completion |
| Browser | 1440/390/320px light/dark, keyboard/error focus, mobile navigation, Other/spouse visibility, review groups, no page overflow, accessible map fallback, no runtime/missing assets or PII storage |
| Regression/isolation | Existing authentication/Google state/session, foundation/navigation/read-only, pure contract, draft/submission, structure and read-only operational guards; actual feature flags off; no customer/operational DB/mail access |

Test plan only: no suite was run or represented as passing in this documentation task. No runtime/acceptance screenshots were required. Only this specification/report was created; source/form inspection and official map-policy research were read-only. No production database, authentication, route, gate, workflow, payment, upload, actual draft or submission changed. **Stop after specification; wait for separate implementation approval.**
