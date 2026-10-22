# Task 05A — Stage 1 applicant form and consent design

Prepared 20 October 2026, Asia/Manila. **Proposed design, not an approved collection form or deployed contract.** Only this report is added. No application files, endpoints, database tables/data, migration guards or feature flags are changed. No database access is required for this design task. New Connection remains disabled by default.

## 1. Sources inspected and approval status

Reviewed Task 01 assessment, Task 02 foundation, Task 03 authentication, Task 03A browser acceptance, Task 03B local assets and Task 04 database foundation reports, together with the current workflow/migration definitions, profile service, mobile helper, registration markup and eBills consent resources.

Relevant implementation findings:

- Portal profiles have nullable structured first/last/middle/extension names and contact number. Existing name validation uses 25-character parts, requires first/last names, supports UTF-8 and rejects control characters. Legacy display names must not be split automatically.
- `app/helpers/philippine-mobile.php` accepts local `09…`, `+639…`, `639…` and `9…` forms, with ASCII spaces/hyphens, and stores canonical eleven-digit `09XXXXXXXXX`. `EBillsEnrollment::saveProfile` uses that helper. Registration and generic `portalProfileValues` still have narrower canonical-number behavior; do not change them incidentally.
- Email verification establishes control of the portal email, not proof of an applicant's legal identity, address or ownership of premises. Profile names/mobile are user-provided and not independently verified merely because they are prefilled.
- Registration's Terms of Use / Privacy Policy links currently point to `#`. They are not authoritative privacy-notice sources for this module.
- `resources/ebills-terms.html` and its provenance file provide an existing reviewed-content/hash/version pattern. They cover eBills, not New Connection privacy or membership conditions; do not reuse their wording as new-connection approval.
- Task 04 provides versioned JSON snapshots, submitted-row immutability and online acknowledgment records. It does not provide draft-content saving, snapshot validation, an atomic content-plus-consent submission command or document rendering.

**Missing authoritative sources:** the repository inventory, excluding dependencies/Git/build caches, found no official PALECO membership application or electric service application PDF, document, scanned form or approved field map. The available logo and eBills terms are not substitutes. No current, module-specific approved privacy notice, official new-connection acknowledgment text, approved locality catalog or request/classification catalog was found either. No external form was silently adopted.

Obtain from PALECO: the complete current forms including reverse pages/annexes; document IDs/revisions/effective dates; approval/provenance; staff-only and signature-area annotations; applicable applicant/service categories; and the approved privacy notice/contact/retention terms. Exact paper-field labels, page coordinates and print mappings are blocked until those sources are supplied. All mappings below describe candidate concepts, not asserted official labels.

## 2. Proposed sections, fields and prefill

Initial proposal is a natural-person applicant applying for a new service, without an existing electric account. Organization, representative, joint-applicant, minor and overseas-address cases require separate approved handling; do not make users enter misleading personal/address details to fit this initial design.

Requiredness below means **before submission**, and remains proposed until PALECO approves it. Drafts may be incomplete. `S` = required; `O` = optional; `C` = conditional. Every populated draft field must still meet its type/length/safety constraints.

### Section 1 — Applicant identity

| Snapshot field | Type / maximum | Rule and source |
|---|---|---|
| `applicant.first_name` | string, 25 Unicode characters | S; prefill structured profile value, allow applicant correction and require confirmation. |
| `applicant.middle_name` | string or null, 25 | O; do not force an invented middle name or initial. |
| `applicant.last_name` | string, 25 | S; same source/confirmation rule as first name. |
| `applicant.extension_name` | string or null, 25 | O; free bounded text, not a forced suffix list. |
| `applicant.contact_number` | string, canonical 11 digits | Proposed S; normalize with the existing mobile helper. A reachable-mobile requirement and assisted alternative need policy approval. No new mobile OTP/verification claim. |
| `applicant.email` | string, up to 254 bytes | S; display read-only and load from the current active, email-verified portal user on the server. No client override. |
| `applicant.profile_confirmed` | boolean | S=true at submission, initially false; confirms applicant review, not verified legal identity. |

Do not update the user's global profile as a side effect of editing this application. Explain that corrections apply to this application; offer a separate existing profile link only when useful. Never truncate a name silently. The 25-character compatibility limit needs PALECO review for real-world longer names; a future wider application limit must not silently write back to narrower profile columns.

If only `users.name` exists for an older/Google-created profile, leave the structured name fields incomplete and ask the applicant to enter them. A mononymous applicant must have an approved assisted/alternative path rather than a fabricated surname.

### Sections 2 and 3 — Residential address and proposed service location

These are separate objects. A residential address may be outside the service area; service eligibility applies to the proposed service location, not the person's current residence.

| Field within each address object | Type / maximum | Rule |
|---|---|---|
| `country_code` | string, 2 | S; `PH` for the proposed initial scope. Non-PH residence handling is a policy blocker, not a reason to invent a PH address. |
| `province_code` | string or null, 32 | C; catalog identifier where applicable. Do not force a province for a city classification that the approved catalog handles without one. |
| `province_name` | string or null, 100 | Server-supplied snapshot label; never trust arbitrary code/name pairs from the browser. |
| `city_municipality_code` | string, 32 | S; selects a city **or** municipality. Opaque catalog code, not a database operational account code. |
| `city_municipality_name` | string, 100 | Required resolved label on a submitted snapshot; server-derived from selected catalog version. |
| `barangay_code` | string, 32 | S; must belong to selected city/municipality in that catalog. |
| `barangay_name` | string, 100 | Required resolved label, server-derived. |
| `street_purok_sitio` | string, 160 | S; support street, purok, sitio or a practical local equivalent; do not require an urban street number. |
| `house_lot_unit_building` | string or null, 100 | O; practical identifier where one exists. |
| `additional_address` | string or null, 160 | O; only location details needed to identify the address. |
| `postal_code` | string or null, 4 | O; exactly four ASCII digits if supplied, preserving leading zeros. Do not infer or guess it. |

Service-location-only fields:

| Field | Type / maximum | Rule |
|---|---|---|
| `service_location.landmark` | string or null, 200 | O by default; required only under a documented location-resolution policy. No household biographies, access passwords or sensitive directions. |
| `service_location.same_as_residential` | boolean | Default false. On explicit selection, copy validated applicable address values into the service object; do not store only a reference to a mutable residential address. Editing the service address afterward clears the link indicator. |

Use linked city/municipality and barangay controls backed by a reviewed local catalog. Parent changes clear invalid dependent selections. Re-read and validate the hierarchy server-side; preserve catalog version and resolved labels in the snapshot. Codes remain strings; do not impose an assumed code length/version from an unavailable source. No online geocoding, forced GPS, maps, photographs or coordinate collection is proposed for Stage 1.

Catalog availability, exact service-area eligibility and the handling of renamed/retired localities must be approved before submission. The operational `nc_arealist` is not automatically authoritative or authorized for reuse. Do not turn missing catalog data into arbitrary unverified free text without an approved exception process.

### Section 4 — Service request

| Snapshot field | Type / maximum | Proposed requiredness / rule |
|---|---|---|
| `service_request.request_kind` | string, fixed `new_service` | S; server-fixed scope, not an invitation to submit reconnection/transfer requests. |
| `service_request.connection_type_code` | string or null, 32 | C; only if an approved applicant-understandable service-type catalog is supplied. Do not ask for phases, capacity or technical configuration that staff must determine. |
| `service_request.premises_classification_code` | string, 32 | Proposed S; values/definitions await approval. Candidate concepts such as residential/commercial must not be presented as PALECO's official taxonomy yet. |
| `service_request.occupancy_code` | string, 32 | Proposed S; applicant's relationship to premises. Candidate owner/tenant/authorized-occupant categories require approval and later evidence rules. |
| `service_request.occupancy_other` | string or null, 100 | C only if an approved `other` option exists; otherwise null. Do not collect owner ID numbers/contact data here. |
| `service_request.requested_service_details` | string or null, 500 | O; short plain-text clarification only if PALECO needs it. Avoid an unrestricted catch-all or technical survey. Remove entirely if it has no defined processing purpose. |

No existing account number, meter serial, member number, transformer, pole number, meter brand, calculated load, deposit quote, fee amount, installation date or inspection result is requested from a first-time applicant.

### Section 5 — Review

Show the applicant-entered identity, both complete addresses and request details with section-specific Edit links, missing-field summary, approved notices and acknowledgment controls. `review.information_confirmed` is a boolean, initially false, proposed required true on submission. Editing reviewed data resets review/profile confirmation as appropriate and clears pending acknowledgment selections tied to the old content. It never erases previously persisted acknowledgments for an earlier submitted revision.

## 3. Versioned JSON data contract

Proposed contract identifier: **`stage1.v1-draft-20261020`**. The word `draft` denotes an unapproved design, not a publishable production schema. Proposed database `schema_version=1` is distinct from `workflow_version=1`, revision number and notice/form versions. Do not treat placeholder snapshots `{}` created by Task 04 as complete/approved v1 data.

Illustrative incomplete draft; all identity/contact data below are empty or synthetic. No customer data is included:

```json
{
  "contract_version": "stage1.v1-draft-20261020",
  "catalog_versions": {
    "localities": null,
    "service_requests": null
  },
  "applicant": {
    "first_name": null,
    "middle_name": null,
    "last_name": null,
    "extension_name": null,
    "contact_number": null,
    "email": "applicant@example.invalid",
    "profile_confirmed": false
  },
  "residential_address": {
    "country_code": "PH",
    "province_code": null,
    "province_name": null,
    "city_municipality_code": null,
    "city_municipality_name": null,
    "barangay_code": null,
    "barangay_name": null,
    "street_purok_sitio": null,
    "house_lot_unit_building": null,
    "additional_address": null,
    "postal_code": null
  },
  "service_location": {
    "country_code": "PH",
    "province_code": null,
    "province_name": null,
    "city_municipality_code": null,
    "city_municipality_name": null,
    "barangay_code": null,
    "barangay_name": null,
    "street_purok_sitio": null,
    "house_lot_unit_building": null,
    "additional_address": null,
    "postal_code": null,
    "landmark": null,
    "same_as_residential": false
  },
  "service_request": {
    "request_kind": "new_service",
    "connection_type_code": null,
    "premises_classification_code": null,
    "occupancy_code": null,
    "occupancy_other": null,
    "requested_service_details": null
  },
  "review": {
    "information_confirmed": false
  }
}
```

Contract rules:

- Fixed object/key structure; reject unknown keys at every level. Reject numeric/array/object inputs where strings are expected and reject strings such as `"true"` where JSON booleans are expected. Optional absent values normalize to null; persist the complete canonical object, not ambiguous sparse patches.
- Drafts allow null for submission-required fields, but populated fields must be valid. Submitted snapshots require all approved S/C fields and confirmations, resolved approved catalogs and no placeholder contract versions.
- `email`, resolved locality labels, catalog versions, fixed request kind and contract version are authoritative server outputs. Rebuild them rather than merging them from an untrusted snapshot. A changed account email requires current verification and a refreshed review; historical submitted email remains the submitted snapshot value.
- Application ID/reference, owner ID, current revision ID/number, lock version, submission state, workflow/stage status, timestamps, hashes and decisions are row/control metadata outside applicant input. Never accept these through mass assignment into the snapshot.
- Proposed request envelope carries only the public reference, expected application/revision version, command idempotency key and allowed applicant fields. Authentication supplies actor/owner. CSRF is a separate request token and is never stored in snapshots.
- Proposed limits: snapshot at most 32 KiB UTF-8; full JSON request at most 64 KiB. No binary/base64/file fields. Count text limits as Unicode characters except the email byte bound and ASCII-only code/number fields.
- Canonicalization for hashing: fixed schema key order recursively, UTF-8, JSON objects with explicit nulls, deterministic booleans/number representation and a documented serialization algorithm. Hash the exact canonical bytes actually stored. Do not hash PHP's incidental associative-array insertion order or browser formatting. SHA-256 is integrity/version evidence, not encryption or proof of a signature.
- Keep submitted `snapshot_json`, `snapshot_sha256`, schema version and submission timestamps immutable. Corrections create the next draft revision under Task 04 rules; do not edit the prior submitted snapshot or silently replace it with a newer global profile.

This report is the proposed contract specification; no runtime JSON-schema validator, draft API or approved schema file is being installed by Task 05A.

## 4. Validation and error behavior

Server validation is authoritative; browser validation provides early feedback only.

| Area | Proposed server rules / example error |
|---|---|
| Names and single-line text | Validate UTF-8, trim surrounding whitespace, reject C0/DEL control characters, preserve legitimate letters, diacritics, spaces, apostrophes and hyphens. Do not use an ASCII-only name whitelist, title-case transformation or silent truncation. “Enter your first name, using no more than 25 characters.” |
| Mobile | Use `portalNormalizeMobileNumber`, then require a nonnull canonical value if approved policy makes contact mandatory. Preserve the user's editable input until blur/save; show normalized review value. Accept existing helper formats; reject tabs/newlines, Unicode digits, malformed prefixes, arrays and numbers. “Enter a Philippine mobile number, for example 09XXXXXXXXX or +639XXXXXXXXX.” Syntax does not verify ownership/reachability. |
| Email | Load from current active verified user; validate syntax and proposed 254-byte maximum. Do not lowercase an entire local part as an identity-matching shortcut or replace a snapshot with a client value. If unusable, direct to an approved account-support/change-verification path; do not collect an unverified override. |
| Address hierarchy | Validate catalog version/code membership and barangay-parent relationship. Derive display labels. “Choose a barangay within the selected city or municipality.” Service-area eligibility is separate from residential address validation. |
| Address details | Require a usable street/purok/sitio description under approved policy, permit absent house numbers, and apply bounded plain-text rules. Never infer that an address is verified or premises ownership is established from entry alone. |
| Postal code | Optional string of exactly four ASCII digits. No numeric conversion or guessed default. |
| Request catalogs | Allow only codes in the published version; no invented enum values. Apply conditional rules for occupancy_other/details only when approved. “Select a premises classification.” Catalog absence blocks submission rather than accepting arbitrary categories. |
| Longer clarification text | Plain text only; normalize CRLF to LF, allow line feeds, reject other control characters and enforce the 500-character bound. Escape on every output; do not treat it as HTML/Markdown or execute it in a form/PDF template. |
| Draft completeness | Save only structurally valid, bounded incomplete data. Malformed submitted values return field errors and leave the prior saved snapshot intact; do not report a partial save as “Saved.” |
| Final submission | Revalidate the entire canonical snapshot, current owner/status, revision/version, required notices and policy/catalog publication in one command. A client review flag or progress bar is never enough. |

Proposed response behavior for later APIs: 401/403 for authentication/authorization, 419 for CSRF, 409 for stale revision/version or idempotency conflict, 422 with stable field paths for validation, 429 for bounded draft/submission abuse limits, and generic 503 for unavailable infrastructure. Avoid distinguishing another user's reference from a missing application. Validation messages must not echo raw SQL, tokens, stack traces or unescaped input.

## 5. Notices and versioned acknowledgments

**All wording here is a review draft, not official/legal approval.** Privacy owners must approve the processing purpose/basis, scope, recipients, retention, rights/contact details and mandatory/optional treatment before collection begins. An acknowledgment of reading a notice is not a substitute for an approved processing basis or a blanket consent waiver.

| Code / placeholder version | Proposed acknowledgment wording |
|---|---|
| `privacy_notice` / `PENDING-PRIVACY-01` | “I have read the New Connection privacy notice and understand how the information in this application will be used for the purposes described in that notice.” |
| `information_accuracy` / `PENDING-ACCURACY-01` | “I have reviewed the information in this application and confirm that it is accurate to the best of my knowledge. I understand that I may need to correct information identified during verification.” |
| `application_verification` / `PENDING-PROCESS-01` | “I understand that submitting this application does not mean it has been approved. PALECO personnel will review the application and required supporting documents before it can proceed.” |
| `physical_signing` / `PENDING-PHYSICAL-SIGNING-01` | “I understand that I will complete and preview application information online, and PALECO personnel will print the official forms for my handwritten signatures during the office payment visit. Checking this box is an online acknowledgment, not my handwritten signature.” |

The privacy notice must be visible before the first future server-side draft collection, not introduced only on the final review page. A proposal is to require an explicit Continue after presenting the approved notice before enabling draft persistence. Whether that step needs a separately retained acknowledgment is a policy decision; do not conflate a notice-view event with consent. If a separate pre-draft acknowledgment is required, specify its code/lifecycle separately before implementation.

For the final review, present four separately labeled, unchecked controls with links to accessible local versions of their complete notice text. Proposed requiredness needs approval; do not bundle optional marketing, eBills enrollment or unrelated processing into required application acknowledgments. Do not copy the existing registration `#` links. Do not make scrolling alone proof of understanding or precheck a box based on a previous application.

Notice publication metadata should include code, approved version, exact text hash, approval status/authority, effective time, language and retained content artifact. `PENDING-*` identifiers must never pass the server's published-notice check or authorize submission. Hashes must be calculated from real approved local content; none are invented here. If required text/version changes during a draft, require review and fresh selections before submitting; leave earlier immutable records untouched.

Pending checkbox selections are transient until the relevant review/submission transaction succeeds. Persist applicant/application/revision, acknowledgment code, approved document version/hash and server UTC acknowledgment time in `new_connection_consents`, together with the immutable submitted snapshot. Do not accept server timestamps, notice hashes or applicant IDs supplied by the browser. Retain the exact approved text so an older hash remains interpretable.

No drawn signature, typed-signature representation, biometric, scanned ID or claim of an approved electronic signature is proposed. Physical signature/date/witness/staff-certification areas remain blank in later official prefilled forms. Signature evidence belongs to a later stage and record type.

## 6. Official application and membership form mapping

**Neither authoritative form is available.** The following is an implementation-preparation matrix of concepts to verify against both supplied forms later. It is not a replica, official wording, mandatory-field declaration or page-coordinate map.

| Candidate concept | Proposed source/responsibility | Mapping state |
|---|---|---|
| Applicant name | Confirmed Stage 1 structured name; format only after inspecting each official form's name boxes/order. | Candidate for both forms; exact labels/layout unconfirmed. |
| Contact and email | Confirmed application mobile; server-sourced verified portal email snapshot. | Map only if the official form includes these fields and PALECO approves their use. |
| Current residential address | `residential_address` snapshot, separately formatted. | Candidate membership/contact-address concept; must not silently fill a service-site box. |
| Proposed supply/service address | `service_location` snapshot. | Candidate electric-service application concept; exact fields unconfirmed. |
| Premises use, occupancy and request category | Approved service-request codes mapped through an explicit form-version adapter. | PALECO must define mapping; do not invent official checkbox options. |
| Application/reference and receipt dates | Portal reference where PALECO authorizes it; official receiving/reference numbers entered by staff. | Do not replace an official number automatically with the portal reference. |
| Membership number, member classification/approval, resolution or certification | PALECO personnel under applicable policy. | No operational `member` record creation or name-based auto-match in Stage 1. |
| Account/meter assignment, supply configuration, measured/calculated load, pole/transformer details, inspection findings | Technical/installation personnel after relevant assessment. | Not applicant-required fields; do not fabricate zero/unknown values on an official form. |
| Charges/deposits, official receipt, payment confirmation, staff approvals and installation date | Authorized billing/cashier/inspection/scheduling personnel in later stages. | Do not infer from Stage 1 or collect unverified payment claims here. |
| Applicant/spouse/co-applicant/witness signatures, thumbmarks, signing dates and staff attestation signatures | Handwritten at the appropriate physical visit; staff completes designated attestations. | Leave every such area blank in applicant preview/prefill. Which areas actually exist must be confirmed from the forms. |
| Birth date/place, civil status, spouse/dependent details, nationality, occupation/income, TIN/government ID, ownership-document details | No Stage 1 collection by default. | Examples of sensitive/unconfirmed legacy concepts requiring purpose/necessity review, not claims these appear on the missing forms. Add only under approved requirements, with justified access/retention. |

For each real form, a future mapping manifest must record source/provenance, form ID/version/hash, page/field identifiers, required/conditional use, source snapshot path, formatting/overflow rules, staff-only/inspection/signature exclusions and reviewer approval. Never guess locations, shrink text until unreadable, omit long-name suffixes or place a service address into a membership-address field without confirmation.

Until approved forms arrive, the UI concept may provide a **read-only application summary** marked as a portal summary. It must not label an invented layout “Official PALECO Form.” Official-preview and print actions remain unavailable. Even an approved later preview must identify its revision and show blank physical signature areas; read-only rendering cannot prevent screenshots or copying.

## 7. Responsive experience and session recovery

Proposed structure, matching the local-assets portal shell:

```text
PALECO sidebar / mobile menu       Stage 1: Applicant information
Section 1 of 5 — Applicant identity        Saved / Unsaved status
[Identity] [Residence] [Service location] [Request] [Review]

Section heading + short purpose + required-field guidance
Labeled fields, inline guidance and errors

Back                 Save draft                    Continue
Review: section summaries + Edit links + approved acknowledgments
        Read-only summary / official preview only when approved
        Submit application (future; not implemented or enabled)
```

- Desktop uses a bounded content area with two columns only for related short fields; mobile uses one column. Address text/landmarks span full width. Stage progress remains distinct from the five within-Stage-1 sections and from approval status.
- Use existing local `portal-utilities.css`, `portal-shell.css`, module-scoped classes, PALECO colors, rounded cards, dark palette and visible keyboard focus. Rebuild utilities for any new literal PHP/JavaScript classes in a later implementation.
- Use real labels, fieldsets/legends where useful, logical heading/order, `aria-describedby` and `aria-invalid`, error summary links, and focus on the first invalid field/summary after server errors. Never use color alone, placeholders as labels or disabled controls as the only explanation of a blocker.
- `autocomplete=given-name`, `additional-name`, `family-name`, `tel` and appropriate address tokens; mobile telephone keyboard and no maxlength that truncates `+63` before normalization. Email read-only has an explanation/support link. Do not force numeric keyboards for name/address text.
- Save draft is separate from Continue and final Submit. Saving incomplete valid data is allowed; show “Saved at …” only after a successful versioned server response, displayed in Asia/Manila. Pending/failure/unsaved states must be explicit. Back/Edit preserves local edits within the current session; warn before navigation when changes have not been saved.
- Proposed autosave, if approved, is debounced and serialized; blur/save rather than every keypress. It must not overwrite a newer tab or keep a session alive forever in the background. An explicit Save remains available.
- Avoid a large wizard modal for the whole application. Use normal page sections for keyboard/mobile accessibility. A read-only preview may use an accessible dialog with bounded scrolling, Escape/Close and return focus, or a dedicated authorized view; neither should trap unsaved state invisibly.

Current session policy is 30 minutes idle / 12 hours absolute. A future editor should warn from server-provided expiry metadata, offer explicit reauthentication/Save while valid, and not simulate permanent activity. After expiry, stop writes, hide sensitive content, return to sign-in, then reload the **server-saved** draft under the same owner authorization. Refresh CSRF and versions after login. Do not replay an unsaved submission automatically.

Do not persist applicant details or tokens to localStorage/IndexedDB/sessionStorage by default. Unsaved in-memory changes can be lost on expiry/reload and the UI must say so; the latest successful server draft is the recovery point. Any retained same-tab in-memory edit buffer must be discarded on logout/account switch and must not be revealed to another identity. A future offline-recovery feature requires an explicit shared-device threat model and separate approval. The existing login currently redirects to dashboard: returning safely to the editor needs a separately implemented, allowlisted internal return destination; it is not already available.

## 8. Draft saving and submission architecture

This is a **future design**, not implemented endpoints:

1. Feature/session/owner gates precede module reads/writes. Applicants need no service account, meter details or `SelectedAcctNo`. Accept actor identity only from the server session; use public references only as locators, never authorization tokens.
2. Load structured profile prefill server-side. Explicit applicant confirmation creates an application-specific copy. Never overwrite a saved draft automatically when the global profile changes; show the difference and ask the applicant to review.
3. A draft-save command checks CSRF, ownership, active/verified status, Stage 1 editable status, current revision, schema/version, request size and expected application lock version. Lock the application/revision; validate/canonicalize only allowed fields; update only the current draft snapshot/hash and increment application version in one transaction. Reject stale saves with 409, preserve both the last saved version and the user's unsaved values for a deliberate reload/reapply. Do not silently merge competing edits.
4. Saving drafts does not advance any stage, create consent/signature evidence, modify a user's profile, write to operational tables, send notifications or create technical/payment records. Draft request deduplication needs a defined replay contract; the existing application creation key is not an autosave idempotency mechanism.
5. Before Submit, create a server-owned review token bound to owner, application, revision, lock version, snapshot hash, contract/catalog versions and notice versions. Editing content or publishing replacement required notices invalidates it. This token is separate from CSRF, cannot grant approval, and must not be logged or persisted in applicant JSON.
6. The final command locks the same records and revalidates the full snapshot, required acknowledgments, publication status and review token. Atomically store the validated canonical content/hash, immutable consent rows, Stage 1 submitted event, lifecycle/version update and safe audit event. If any write/validation fails, roll back everything. There must be no interval in which a submitted revision lacks its required acknowledgments.
7. Task 04's `transition()` owns its own transaction and freezes the existing draft; it cannot simply be called after a separately committed content/consent save. A future atomic submission command/internal transaction boundary must be deliberately refactored and regression-tested. Do not nest independent transactions, weaken submitted-row triggers or call a generic status transition from a browser to bypass content/consent checks.
8. Repeated final commands with the same idempotency key and exact bound payload must return the original authorized outcome without duplicate consent/events; changed payloads conflict. Existing Task 04 stage version/fingerprint logic must be preserved. Draft-save retries require equivalent protection, probably a separate command receipt/design not present in the current schema. Any additional schema change needs separate approval.
9. Staff-requested corrections use the foundation's new-revision workflow. Restrict edits to approved correction scope when specified, revalidate/review/reacknowledge for the new revision and retain all earlier snapshots/decisions. No delete-and-reinsert of submitted history.

Current migration runners still reject the actual portal schema, and no module tables exist there from Task 04. This design does not authorize changing that guard. Draft-content versioning can use the existing application lock plus revision identity; do not add a duplicate draft table or silently widen profile columns without an approved reason.

## 9. Privacy, security and retention risks

- Collect only the approved identity/contact, current residence, service location and request details. Do not copy sensitive operational member fields merely because columns exist. Avoid free-text fields that invite unrelated personal information.
- Drafts already contain personal information: privacy notice, authorized processing, access controls, private caching and retention apply before submission. “Not submitted” is not equivalent to “not collected.”
- No generic event/error/access log may contain snapshots, name/address/mobile/email values, CSRF/review/reset tokens or request bodies. Audit safe action/version/field-path identifiers rather than field values. Infrastructure request logging and analytics must follow the same boundary.
- Revalidate owner and current user for saves, reads, summaries and previews. Staff access must use explicit module permissions/scopes once approved; no email-domain or operational identity shortcut. Use prepared statements, output-context escaping and plain-text templates; a readonly form does not make user content trusted.
- Version/hash catalogs, notices and templates locally. Do not fetch unreviewed HTML from a third-party site during applicant requests. Prevent CSRF, cross-user reference enumeration, stale-tab overwrites, repeated-submit effects and request-size/rate abuse.
- Do not hide consent in a checkbox bundle or imply a guarantee of approval/installation. Online acknowledgment and the office handwritten signature remain different records/events.
- Retention periods are **not approved**. PALECO must set separate schedules for abandoned drafts, submitted/rejected/withdrawn applications, acknowledgments, audits, backup copies and legal/administrative holds. Minimize obsolete draft copies; preserve required submitted evidence and apply authorized deletion/retention consistently with restrictive FKs and immutable-history triggers. Do not invent an automatic purge job or a fixed statutory period in Task 05A.
- Existing append-only triggers can conflict with a future approved deletion policy. Define an audited, privileged retention procedure and hold checks separately; do not disable triggers in applicant request paths.
- Stage 1 makes no claim to verify property title, applicant legal capacity, mobile ownership, technical feasibility or billing clearance. Such claims would exceed the inspected evidence and current scope.

## 10. Proposed implementation files and Task 05B

All paths below are proposals only; none are created by this design:

| Proposed file/resource | Responsibility |
|---|---|
| `resources/new-connection/contracts/stage1-v1.schema.json` | Approved closed-object schema and separate draft/submission rules. |
| `resources/new-connection/notices/` plus manifest | Approved local text, version/hash, effective time, language, provenance and publication state. |
| `resources/new-connection/catalogs/` | Reviewed locality and request classifications, stable versioned offline data. |
| `resources/new-connection/forms/` and mapping manifest | Authoritative approved originals and reviewed field adapters; absent until supplied. |
| `app/services/new-connection/applicant-contract.php` | Canonicalization, validation and server-sourced profile/catalog enrichment. |
| `app/services/new-connection/drafts.php`, `submission.php` | Owner/version-checked persistence and atomic content/consent/stage submission after approval. |
| `app/services/new-connection/notices.php` | Publication checks and revision-bound acknowledgment validation. |
| `app/views/partials/new-connection/stage1-*.php` | Identity, residence, service location, request and review sections. |
| `assets/js/new-connection-stage1.js`, scoped additions to module CSS | Accessible navigation, validation, save status/conflicts and expiry warnings; no authoritative workflow rules. |
| Isolated fixtures under `tests/new-connection/` | Contract/normalization, ownership/CSRF, concurrent saves, transactional rollback/idempotency, notice changes, browser/accessibility tests. |

Recommended Task 05B: first obtain/approve official forms, notice wording, data limits/requiredness, initial applicant scope and locality/request catalogs. Then implement the pure contract validator/canonicalizer and an isolated responsive editor/review **fixture** with synthetic data and no live persistence. Test names with diacritics/compound surnames and overlength handling; all supported mobile formats; rural/no-house-number addresses; city/barangay mismatch; optional fields; unsupported applicant/address paths; stale tabs; changed profile/notice versions; expiry without PII browser storage; and visibly separate online acknowledgments/physical signatures.

Approve authenticated draft/submission endpoints and any schema/provisioning changes separately after the atomic content-plus-consent design, privacy/retention policy, staging security requirements and original-form mappings are reviewed. Do not make a missing official source look complete with an invented PDF or guessed field labels.

## 11. Verification and stop boundary

This was a source/report/resource inspection and design review, not a functional implementation test. Field paths, JSON example and database transaction constraints were checked against the current source. No browser claim, approved-form mapping claim or runtime validation pass is made for this proposed editor. No database connection, test migration, application/customer read, email or applicant-facing request was performed for this design task.

Only `tests/new-connection/applicant-form-design-report.md` was added. Existing application code, database schemas/data, feature configuration and prior artifacts remain unchanged. Task 05A stops here.
