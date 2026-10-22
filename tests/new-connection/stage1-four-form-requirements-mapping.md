# Task 06W — Four-form requirements mapping

2026-10-22. **All four supplied PNGs exist, opened successfully and were inspected directly, including original-resolution views.** These are owner-supplied PALECO-branded references, not verified current approved templates. This report replaces the missing-source assumption for these four images only; earlier reports remain historical. No contract or application implementation changed.

## 1. Sources, interpretation and traceability

All files are under `resources/new-connection/references/`. F1–F3 are 1700×2200 pixels; F4 is 1700×2800. No printed revision/effective-date/document-control number was identified. Acquisition/version approval remains pending. Hashes identify the inspected bytes, not approval:

| ID / file | Printed heading / identity | SHA-256 |
|---|---|---|
| F1 `New Connection_0001.png` | `MGA KINAKAILANGAN PARA SA SERBISYONG ELEKTRIKAL` | `A92113954FDA775643BD6FA4D63D69608763C8B0283B68CC5E050178BAC64FFE` |
| F2 `New Connection_0002.png` | `PALAWAN ELECTRIC COOPERATIVE`; no separate Consumer and Installation Record title visible | `3C813B25D448A973DEB04B59DAF1362603EEE371C0360043DFE172B2119D94B0` |
| F3 `New Connection_0003.png` | `KWH METER INSTALLATION AGREEMENT` | `B12ABA2F801CA0E93C73608236B5467A935E3575DE6F2BC46E45D1629CA8EFEB` |
| F4 `New Connection_0004.png` | `APPLICATION FOR MEMBERSHIP AND FOR ELECTRIC SERVICE` | `E2A9A10F464BC7F9C515A49B2D2C66E2D23E244C234B06EEBF9C6A88AC38E13F` |

Locations below use each image's printed region and row order; each is one page. Quoted labels transcribe visible text, with line wrapping collapsed, not corrected spelling. Blank spaces/underline lengths are not character limits. No field-level asterisk/optional legend was identified on F2–F4: **printed presence alone does not prove mandatory completion**. F1 is explicitly a requirements checklist; listed conditions are recorded, but applicability/current enforcement still needs approval.

Legend: **A** applicant-provided; **S** staff-entered/verified; **E** externally issued; assignments are proposals unless explicit. **B** printed blank/choice, mandatory versus optional unconfirmed; **L** listed checklist requirement, applicability pending; **C** condition expressly printed. Privacy: **P** personal/contact/location; **H** heightened identity/demographic/financial review; **T** third-party data; **I** internal technical/infrastructure; **Sig** physical signature. “Not implemented” means no corresponding collection/operational feature; existing synthetic generic workflow foundations do not constitute these capabilities.

## 2. F1 — Requirements checklist and Stage 2 categories

### Left column: `Paleco:` (top to bottom)

| Printed item, exact visible wording | Stage / proposed source | Evidence | Current mapping | Privacy / outstanding decision |
|---|---|---|---|---|
| `Application for Membership and Electric Service` | 1 prepare; 6 print/sign; A+S | L | F4 identity/location partially supported; no official print implementation | P/Sig: approve template and whether unsigned online preparation suffices before visit |
| `Kwh Meter Installation Agreement` | 6 staff print/applicant physical sign; A+S | L | No agreement/printing collection | Sig/H: approve agreement revision and ID handling |
| `Direksyon at lokasyon ng lugar` | 1 location narrative; 5 staff validation/sketch; A+S | L | Service address + landmark partial; no sketch | P: decide description versus map/sketch evidence |
| `Inspection Report` | 5 S; evidence verified with documents if needed | L | Stage definition only; no inspection report collection | I/P: distinguish PALECO inspection from government certificate |
| `Pre-Membership Education Seminar sa APEC Conference Room tuwing Huwebes ng 9:00 ng umaga (para sa bagong aplikante)` | 4 S attendance/learning evidence | C: new applicant; printed in-person place/time | Online PMES is planned, not implemented | P: approve replacement/equivalence before promising online PMES; do not publish this schedule as current |
| `Resibo ng binayarang service drop wire kung higit sa 30 metrong haba ang kailangan` | 5 staff determines length; 6 payment/receipt; A presents, S validates | C: required length over 30 metres | No payment/length rules implemented | H/I: approve threshold, payer, receipt category and timing |
| `Dalawang 1x1” na larawan` | 2 document requirement; applicant supplies photos | L: two photos | No uploads/photos implemented | H: decide physical-only versus electronic equivalent, access/retention |
| `Clearance mula sa Billing Section` | 3 internal S-issued clearance | L | Stage only, not billing operations | H: no applicant-created clearance or operational query assumed |
| `Balidong pagkakakilanlan` | 2 A supplies E-issued identity, S verifies | L | Portal verification is not document verification | H: approved ID types, masking, expiry, copy-versus-inspection policy |
| `Bayad para sa pagpapamiyembro, saping-puhunan, deposito para sa isang buwang konsumo, at bayad para sa inspeksiyon at koneksiyon` | 6 offline payments, S verifies | L; multiple payment purposes, no amounts printed here | No payment implementation | H: approve current fee schedule, exceptions, deposit calculation and receipts |
| `Notaryadong pagpapaubaya sa kalidad ng elektrisidad kung higit sa 100 metro ang haba ng service drop wire` | 5 condition determined; 2/6 notarized evidence, A+E+S | C: service drop over 100 metres | None | H/Sig/I: legal review of wording, threshold, validity and execution; do not auto-generate a waiver |

### Right column: `Pamahalaan:`

The heading lists `Office of the Building Official/Municipal Engineering Office, Bureau of Fire Protection & Bureau of Internal Revenue`. It identifies a group of issuing offices, not an item-by-item issuer mapping.

| Printed checklist item | Stage / proposed source | Evidence | Current mapping | Privacy / outstanding decision |
|---|---|---|---|---|
| `Electrical Plan na may pirma at selyo ng Professional Electrical Engineer (P.E.E)` | 2 A submits E professional-issued; 5 S reviews | L; signed/sealed qualification explicit | None | P/I/Sig: approve acceptable professional seal/evidence and technical review |
| `Electrical Permit` | 2 A supplies E government permit | L | None | P/I: confirm issuing authority, validity and project categories |
| `Certificate of Final Electrical Inspection/Completion` | 2 evidence; 5 S verifies E certificate | L | None | P/I: clarify sequencing relative to PALECO inspection |
| `Permit for Temporary Service Connection` | 2 A supplies E permit | L; label refers to temporary service, exact applicability not otherwise stated | No approved temporary-service model | P: owner must define conditional applicability; not universally mandatory by inference |
| `Fire Safety Clearance` | 2 A supplies E clearance | L | None | P/I: confirm applicable premises/classes and currency |
| `Tax Identification Number` | 1 only if approved; 2 evidence/verification if required; E-issued, A supplies | L; number, not expressly a certificate/upload | None | H: do not invent mandatory TIN-card upload; define purpose and alternatives |
| `Litrato ng bahay o establisyimento na pirmado ng taga-Office of the Building Official/Municipal Engineering Office` | 2 A supplies externally endorsed photograph; 5 S verifies | L; endorsement explicit | No photo/signature collection | P/I/Sig: confirm endorsement process and retention/minimization |

### Lower `PAGTANGGAP` acknowledgment area

| Exact label / location | Stage / source | Evidence / mapping | Privacy / decision |
|---|---|---|---|
| `Aplikante:` left line | 2 receipt acknowledgment; A identity, S records | B; current applicant names can compose display after confirmation | P: identify who fills/receives this acknowledgment |
| `Lugar:` right line | 2 S from service location | B; service address partial | P: confirm locality granularity |
| `Tumanggap` bottom first line | 2 S receiver | B; no staff receipt implementation | P/Sig: clarify signature/name requirement |
| `Designasyon` next line | 2 S | B; none | Staff role: approve office/role attribution |
| `Petsa` next line | 2 S timestamp/date | B; generic audit timestamp is not this official field | P: date format/timezone and printed acknowledgment policy |
| `Oras` last line | 2 S time | B; none | P: time format and receiver accountability |

Printed contact block: `048 433-2001`, `09178644443`, `newconnections.paleco@gmail.com`. These are reference-sheet contact details, not applicant fields; current contact-channel approval is unverified. No calls or email were sent.

**Proposed Stage 2 taxonomy:** applicant photos/valid-ID evidence/location information; professional signed electrical plan; government permit/final-inspection certificate/temporary permit/fire clearance; externally endorsed premises photo; conditional notarized document. Link internally issued clearance and PALECO inspection as staff evidence rather than applicant uploads. Keep PALECO templates separate from government-issued documents. Payment receipts belong primarily to Stage 6, with length-based conditions decided in Stage 5. No upload category is implemented by this report.

## 3. F2 — Consumer/installation record inventory

All labels below are **B**: printed blanks/options do not establish universal mandatory completion. Sourced data may be prepared from approved applicant information, but technical measurements/equipment assignments must remain staff-only.

| Exact label / location | Proposed stage/source | Current mapping | Privacy / PALECO decision |
|---|---|---|---|
| `CONSUMER NAME:` top row | 1 A confirmed → S print | Applicant structured names supported | P: legal name composition and print order |
| `SPOUSE NAME:` second row | 1 only if approved A; S print | Missing | T/H: necessity, applicability and spouse rights |
| `ADDRESS:` third row | 5 S from confirmed service location | Service address supported; meaning not explicit | P: decide residence versus installation address |
| `HOUSE STRUCTURE:` choices `Concrete`, `Semi-Concrete`, `Light Materials` | 5 S inspection | No construction field; premises classification is not equivalent | P/I: approve classification/verification, not applicant self-certification |
| `NEAREST ACTIVE MEMBER: a.` | 5 S lookup/field verification | None | T/P: avoid applicant collecting neighbors' account data |
| `b.` second nearest-member line | 5 S | None | T/P: define actual identifiers needed and access restrictions |
| `LENGTH OF SERVICE DROP:` / `Meters` mid-left | 5 S measured | None | I: define measurement and relation to F1 thresholds |
| `SIZE:` mid-right above sketch | 5 S | None | I: unit/object not specified; do not assume wire gauge or transformer rating |
| `METER SERIAL NUMBER:` | Installation execution, staff; after scheduling | None | I: never Stage 1 input |
| `SERVICE X’FORMER ID:` | 5 planning / execution S | None | I: printed apostrophe shape may render as `'`; approve equipment identifier source |
| `METER LOCATION:` | 5 S planned location; execution verifies | Landmark only partial context, not equivalent | P/I: distinguish approved meter placement from general address |
| `TAPPING POLE:` | 5/execution S | None | I: approved infrastructure source |
| `METER BRAND:` | Execution S | None | I: asset verification |
| `CALIB. SEAL (indicate the color):` | Execution S | None | I: approved seal data/verification |
| `COVER SEAL (indicate the color):` | Execution S | None | I: approved seal data/verification |
| `DATE TAPPING:` | Execution S | None | I/P: actual completion date, not scheduling date |
| `TEAM LEADER:` and continuation line | Execution S | None | P: staff attribution; unlabeled continuation must not become invented second field |
| `Received by:` / `Print Name & Signature` lower-left | Execution handover, recipient role unresolved | None | Sig/P: could be consumer/representative or staff; owner must identify, not presume |
| `Date:` below receipt | Execution handover | None | P: handover date versus tapping date |
| `SKETCH OF LOCATION` lower-right box | 5 S; applicant directions may inform | No sketch | P/I: approved location-sketch content, no forced GPS/neighbor identity collection |

**Scope conflict:** Stage 7 is Installation Scheduling, the final online stage. Actual tapping, installed meter/seals and handover occur after scheduling; do not mark them completed when Stage 7 is scheduled, or add an eighth stage without approval. Preserve separate future internal/operational completion records and an explicit scheduling-versus-energization distinction.

## 4. F3 — KWH Meter Installation Agreement

### Fillable locations

| Exact printed label / location | Proposed stage/source | Evidence / current mapping | Privacy / PALECO decision |
|---|---|---|---|
| Opening `I,` followed by name blank | 1 A name → 6 S prefill | B; names supported | P: legal agreement party and name format |
| `of legal age, and a resident of` followed by address blank | 1 approved residence → 6 S prefill | Printed legal-age statement, no numeric age blank here; residence supported | P/H: approve age eligibility/verification, do not infer numerical threshold or require DOB automatically |
| `IN WITNESS WHEREOF` / `this` blank / `day of` blank | 6 signing date, S/applicant at visit | B; none | P: date components/year formatting not labeled separately |
| Fixed execution place `Barangay Tiniguiban, Puerto Princesa City, Palawan` | 6 offline signing | Printed fixed place; no applicant field | P: approve use for other offices; never silently predeclare signing occurred there |
| `CONFORME:` / `Member Consumer - Owner` / `(Signature Over Printed Name)` | 6 applicant physical signature | Signature line explicit; no signature feature | Sig: keep blank for physical signing; authorized representative/spouse not specified here |
| `ID No.` | 2 verification / 6 S records approved identity evidence | B; missing | H: ID necessity, masking and allowed document types |
| `Issuer` | 2/6 E issuer, S verifies | B; missing | H: issuer/verification policy |
| `Valid Until` | 2/6 E expiry, S verifies | B; missing | H: IDs without expiry/expired-ID exceptions |
| `BRAND` | Installation execution S | B; none | I: meter/asset data, not Stage 1 |
| `MAKE` | Execution S | B; none | I: distinction from brand unclear; approve controlled source |
| `TYPE` | Execution S | B; none | I: meter type, not request category |
| `AMPERES` | Execution S | B; none | I: technical unit printed, numeric limits not specified |
| `SERIAL NO.` | Execution S | B; none | I: asset serial, not existing applicant account |
| `PALECO NO.` | Execution S | B; none | I: meaning unconfirmed; do not map to portal user or existing electric account number |
| `INITIAL READING` | Execution S | B; none | I: initial meter reading, unit/precision not specified |

### Printed obligations (numbered paragraphs; summarized, not approved online declarations)

| Location | Reference content | Allocation / current gap / decision |
|---|---|---|
| F3 ¶1 | PALECO supplies/installs meter and chooses location for staff safety/convenience | 5 placement, later execution; no implementation; approve current terms |
| ¶2 | Member safeguards equipment against loss, damage and tampering | 4 education / 6 physical agreement; no approved lesson/notice |
| ¶3 | Reporting and replacement responsibility for negligent loss/damage | 4/6; legal review of current wording, not an automatically checked checkbox |
| ¶4 | Meter tampering and penalties; printed reference to R.A. 7832 | 4/6; printed legal reference only, no legal validity/current-law assessment made |
| ¶5 | Premises owner/regular MCO requires tenant separate connection when necessary | 1 eligibility question for policy owners; 6 agreement | 

The last clause does not establish that all new applicants must already hold an electric account; it creates an applicability ambiguity for first-time applicants, tenants and representatives. No existing-account gate should be added from it. No separate staff signature line is clearly printed on F3; do not invent one.

## 5. F4 — Membership and electric service application inventory

All field rows below are **B** unless stated. No maximum lengths, structured formats, demographic options or field-level requiredness legend are printed. Proposed stage allocation is not approval to collect.

| Exact label / location, top-to-bottom below terms | Stage/source | Current Stage 1 mapping | Privacy / outstanding decision |
|---|---|---|---|
| `APPLICANT:` / `First Name` | 1 A → 6 S print | `applicant.first_name` | P: approve requiredness and 25-character limit |
| `Middle Name` under applicant | 1 A | `applicant.middle_name` | P: optionality/absence convention |
| `Last Name` under applicant | 1 A | `applicant.last_name` | P: naming exceptions and length |
| `Signature` under applicant | 6 physical A | Not collected | Sig: preserve blank until office signing |
| `Spouse:` / `First Name` | 1 only if justified A | Missing | T/H: applicability, purpose, rights and representative authority |
| `Middle Name` under spouse | 1 only if approved A | Missing | T/H: no automatic spouse-profile inference |
| `Last Name` under spouse | 1 only if approved A | Missing | T/H: approved need/limits |
| `Signature` under spouse | 6 physical spouse if applicable | Not collected | Sig/T: when is spouse signature necessary? |
| `Future Address:` / `Street/Purok` | 1 A | `service_location.street_purok_sitio` partial label equivalence | P: confirm service address meaning; Sitio extension is prototype choice |
| `Barangay` | 1 A | `service_location.barangay_code`, resolved name | P: approve catalog |
| `City/Municipality` | 1 A | `service_location.city_municipality_code`, resolved name | P: approve hierarchy/service-area rules |
| `Date of Birth:` | 1 only if approved A | Missing | H: necessity, format, verification and retention |
| `Age:` | 1 only if approved, preferably derived if DOB authorized | Missing | H: avoid inconsistent duplicate stored age; as-of date unresolved |
| `Gender:` | 1 only if justified A | Missing | H: purpose/options/non-disclosure policy not printed |
| `Civil Status:` | 1 only if justified A | Missing | H/T: applicability to spouse fields; do not infer from name |
| `Highest Educational Attainment:` | Defer pending minimization | Missing | H: no demonstrated service necessity |
| `Occupation:` | Defer pending purpose approval | Missing | H: employment profiling/minimization |
| `Source of Income` | Defer pending purpose approval | Missing | H: financial information |
| `Annual Income: Php` | Defer pending purpose approval | Missing | H: sensitive financial profile; no numeric range/requiredness printed |
| `Number of Dependents:` | Defer pending purpose approval | Missing | H/T: household information; no dependent identities requested on scan |
| `Religion:` | Do not propose online collection without privacy/legal decision | Missing | H: especially sensitive belief information, necessity unproven |
| `Social Affiliation:` | Do not propose online collection without privacy/legal decision | Missing | H/T: may reveal sensitive associations; no categories printed |
| `Email Address:` | 1 A/contact policy decision | Server-supplied `applicant.email`, partial fit | P: paper does not require equality with portal email or prove verification |
| `Contact No.:` | 1 A | `applicant.contact_number`, partial fit | P: paper says contact number, not mobile-only; approve landline/alternate handling |
| `Tax Identification Number (TIN):` | 1 only if approved / 2 verification | Missing | H: government identifier; number does not imply card scan |
| `Paleco Membership Number:` | Staff/membership assignment; phase needs owner | Missing | H/internal ID: do not demand existing number from first-time applicant |
| `Date Paid:` | 6 cashier S | Not implemented | H: official payment record, not applicant assertion |
| `Amount: Php` | 6 cashier S | Not implemented | H: current fee/total definition; future DECIMAL, not float |
| `O.R. No.:` | 6 cashier S | Not implemented | H: receipt validation, no online receipt generation implied |
| `Share Capital Paid: Php` | 6 cashier/membership S | Not implemented | H: distinguish subscription from actual payments; future DECIMAL |
| `Teller` under line | 6 S | Not implemented | P/Sig: determine printed name/signature requirement; label alone does not specify |
| `1 x 1 ID Picture` box, lower right | 2 A photo / 6 physical form attachment | No photo field/upload | H: portrait, not necessarily a scan of an ID card; reconcile two photos in F1 versus one box |
| `BOARD ACTION: Application for Membership approved / disapproved under Board Resolution No.` | Staff/board membership decision, timing unresolved | No board approval model | P/internal: separate membership decision from Stage 1 submission and service approval |
| `Dated` following Board Resolution | Board/staff S | Missing | Internal: resolution date, not application date |

### Ten printed terms above fields (source evidence, not current fee/legal advice)

| F4 paragraph | Printed topic / requirement | Stage and owner decision |
|---|---|---|
| 1 | Membership fee One Hundred `(PhP100)` pesos, conditional on acceptance constituting membership fee | 6; confirm current schedule and acceptance/refund policy before any amount is configured |
| 2 | Minimum 12 shares, `(PhP 100)` per share, `(Php 1,200)` total; initial at least three shares, remainder through monthly electric bills; certificate after full payment of 12 shares | 6/membership accounting; explicit printed monetary obligations, **not verified current pricing**; no changes to existing eBills/billing authorized |
| 3 | Purchase of power for specified premises under Amended By-Laws | 6 terms; obtain referenced by-laws/version |
| 4 | Wiring per PALECO specifications/Philippine Electrical Code | 2 plan/permits and 5 inspection; current standards/reference confirmation required |
| 5 | Member liability limited to subscribed capital and private-property protection described | 6 agreement; legal review, not demographic-input justification |
| 6 | Rights/easements, facilities/property, access and printed waiver language citing penal-code articles | 6 physical agreement; high-priority legal/owner review, not a substitute privacy consent |
| 7 | Disconnection for nonpayment/policy violations/tampering; R.A. 7832 reference | 4 education / 6 agreement; current policy/legal review, not online eligibility decision |
| 8 | Undertaking for unpaid bills/occupant use and deposit insufficiency | 6 agreement; clarify owners/tenants/applicant responsibility |
| 9 | Supply/damage liability exclusion wording | 6 agreement; legal review; do not convert to unchecked mandatory online waiver automatically |
| 10 | Acceptance constitutes agreement; printed one-year service term from availability and continuation while needed | Membership/service acceptance, not Stage 1 online receipt; approve who accepts and when |

No complete privacy notice, retention policy or approved electronic-signature authorization is present in these images.

## 6. Reconciliation with `stage1.v1-draft-20261020`

| Current contract field/group | Form 4 evidence | Gap / recommendation |
|---|---|---|
| `applicant.first_name`, `middle_name`, `last_name` | Direct labeled counterparts | Retain candidates; current 25-character limits and requiredness are **not** proven by blanks |
| `applicant.extension_name` | No explicit extension field | Keep provisional pending owner need/print composition; not automatically remove |
| `applicant.contact_number` | `Contact No.` | Existing normalization is mobile-only; decide other contact formats before changing |
| Server `applicant.email` | `Email Address` | Paper permits a contact field; does not establish server-owned portal-email equivalence |
| `applicant.profile_confirmed` | No printed confirmation checkbox | Technical UX confirmation, not official paper signature/identity proof |
| All seven `residential_address` inputs (province, municipality, barangay, street/purok/sitio, building, additional address, postal code) | No separately labeled residential-address block on F4 | F3 has residence; F2 ADDRESS is ambiguous. Approve necessity and residence/service distinction rather than remove or require based on F4 alone |
| `service_location.city_municipality_code`, `barangay_code`, `street_purok_sitio` | Future Address's three components | Supported concepts, but code catalog/eligibility and Sitio wording require approval |
| `service_location.province_code`, `house_lot_unit_building`, `additional_address`, `postal_code` | Not explicitly labeled on F4 | Optional/location hierarchy choices remain provisional; no printed lengths/formats |
| `service_location.landmark` | Absent F4; related to F1 directions/F2 sketch | Purpose supported in broader location workflow, online field optionality/limits still owner decision |
| `service_location.same_as_residential` | No checkbox | UX convenience, not official statement; preserve no-signature semantics |
| `service_request.connection_type_code`, `premises_classification_code`, `occupancy_code`, `occupancy_other`, `requested_service_details` | No counterparts with these labels/options | All remain provisional. F2 construction materials are a different concept. F1 temporary permit/F3 tenant clause do not approve example catalogs |
| `review.information_confirmed` | No checkbox | Review mechanism, not applicant/spouse signature |
| Four `acknowledgments.*` | No equivalent four online selections | Keep test-only; approved notice/terms version mapping is missing; signatures remain offline |
| Server country/resolved locality labels/catalog/contract versions | No equivalent printed metadata fields | Technical enrichment/lineage; source and assumptions need approval, not applicant questions |

**Missing from prototype but printed on F4:** spouse name parts/signature; applicant physical signature; DOB, age, gender, civil status, education, occupation, income source/amount, dependents, religion, affiliation, TIN, membership number, ID photo, paid date/amount/receipt/share capital/teller, board decision/resolution/date. Missing does not mean “add to Stage 1.” Separate minimized applicant information, third-party data, documentary evidence, offline signatures, staff financial records and board decisions.

No printed limits justify widening/narrowing current input lengths. Adding approved spouse/demographic/TIN/contact-email fields or changing requiredness/normalization would need an explicit new contract version and compatibility tests. Staff/payment/installation fields belong in separately approved later-stage models, not the Stage 1 snapshot by default. Amounts should eventually use DECIMAL; no payment model or migration is proposed for execution now.

## 7. Seven-stage allocation and sequencing issues

| Existing stage | Reference-based allocation | Decision/gap |
|---|---|---|
| 1 Applicant Information and Submission | Approved subset of F4 identity/contact/Future Address; F3 residence if necessary | Confirm representative/spouse/eligibility rules and minimized fields; online submission is not board acceptance |
| 2 Document Submission and Verification | F1 approved applicant/external evidence; identity/photo/TIN handling; intake acknowledgment | Conditional checklist and government issue/validity rules; no upload implementation |
| 3 Billing Clearance | F1 Billing Section clearance | Internal staff-issued, not applicant-entered billing result |
| 4 Online PMES | F1 PMES evidence plus approved learning content | **Conflict:** printed in-person Thursday session versus planned online assessment; substitution/equivalence/passing requirements not established |
| 5 Technical Inspection | F1 PALECO report, government completion evidence; F2 sketch/materials/drop length/pole/location | Some documents depend on inspection and cannot all be rigid prerequisites before Stage 5; approve conditional sequencing/corrections within existing stages |
| 6 Payment and Handwritten Form Signing | F3/F4 staff print; applicant/spouse physical signatures where applicable; F1 fees/conditional receipts; ID verification | Current fee schedule, signing venue/roles, waiver and payment verification pending; leave signatures blank online |
| 7 Installation Scheduling | Staff schedules once approved prerequisites complete | F2 tapping/meter/seals and F3 installed-asset readings are **later execution records**, not proof that scheduled service is energized |

Board membership approval timing is unresolved and must not be silently inserted as automatic Stage 1 approval. Do not add an eighth stage or alter independent stage status authority from a paper-field inference.

## 8. Privacy/data-minimization review

- **Religion/social affiliation:** no necessity or approved processing basis established; especially high-priority omission/defer decision. Do not enable online collection merely to reproduce paper.
- **Income/source, occupation, education, dependents, gender, civil status, DOB/age:** identify exact service/membership purpose, minimize duplicates, define optionality, access and retention. No categories/defaults are established by the scans.
- **Spouse/nearest active members:** third-party information; define notice/authority and avoid neighbors' account/identity disclosure. No spouse signature image or third-party account lookup exposed to applicants.
- **TIN, ID number/issuer/expiry, valid ID and portrait:** fraud/identity risks; approve types, inspect-versus-copy, masking, storage/access and deletion. A `1 x 1 ID Picture` is not evidence for mandatory ID-card scanning.
- **Addresses/sketch/house photo and electrical plan:** reveal home/location/infrastructure and possibly neighbors; constrain detail and staff access, avoid unnecessary GPS/public display.
- **Payment, share capital, receipts, membership number:** restricted financial/identity metadata, staff verified; do not log raw values or assume old printed amounts remain current.
- **Signatures, staff attribution and legal terms:** physical signing is distinct from online acknowledgments. No legal validity/current-law conclusion is made about printed waivers/disconnection/liability clauses. Obtain approved current terms; a privacy checkbox is not their legal basis or execution.

## 9. Uncertainty register

No entire checklist item or labeled field was unreadable at original resolution. Specific interpretation/typographic uncertainties remain:

1. F2 has no visible separate record title; the user description is not a printed heading. `SERVICE X’FORMER ID` uses an apostrophe-like scan mark; no operational identifier format is established.
2. F2 `SIZE` lacks a stated object/unit; the unlabeled line below TEAM LEADER is not evidence of an additional role. Received-by identity/role is unspecified.
3. F3 signing blanks do not specify a separate year field/format. `MAKE`, `PALECO NO.` and technical reading precision need definitions. Fixed execution place may not suit other offices.
4. F4 Date of Birth label is slightly degraded at its upper edge but readable; do not infer a date format or numerical age rule. No required/optional legend, input limits or demographic choices are printed.
5. F1 temporary permit applicability is suggested by its name, not a printed universal condition; the two explicit length thresholds are different conditions and should not be conflated. PMES schedule, fees, contact details and legal wording require currency approval.
6. No backs, additional pages, current by-laws, fee orders, approved privacy notice or document-control provenance accompanied the four files. Completeness beyond these images is unverified.

## 10. Prioritized owner decisions and implementation sequence

| Priority / proposed owner | Decision and evidence required |
|---|---|
| P0 Process/document custodian | Attest current approval/revisions, all pages/annexes, source originals and scope of each form; label replaced/obsolete versions |
| P0 Privacy/legal + membership | Decide field-by-field need/omission for sensitive demographics, spouse and TIN; approve purpose/basis/retention/notice and representative rules before any collection |
| P0 Operations + legal | Confirm applicant legal-age/tenant/owner/first-time-member eligibility; membership-number assignment, board approval timing and signing-party roles |
| P0 PMES owner | Approve online versus in-person equivalence, content, assessment/passing rules and attendance evidence; do not adopt Thursday schedule as current |
| P1 Technical + permitting liaison | Approve required/conditional document matrix and sequence, temporary-service cases, construction categories, measured drop thresholds, photo endorsement and notarized-document policy |
| P1 Billing/cashier/membership | Approve current fees/share capital/deposit calculations and payment timing/receipts, distinguishing F4 printed amounts from current pricing |
| P1 Technical/installation | Define F2 SIZE/received-by and F3 equipment identifiers; allocate actual installation completion outside scheduling; approve staff-only source/access |
| P1 Product + data owners | Approve contact email versus account email, non-mobile contacts, name limits, residence/service catalogs and field-to-print composition |
| P2 Staff/print owner | Approve staff printing/prefill, blank signature areas, spouse conditionality, office/date handling and signed-form verification |

First attest forms and decide minimization/eligibility. Next approve a reviewed field/document/terms matrix with exact source references and named owners. Then propose versioned contract/catalog/notice changes and separate later-stage models; do not silently change `stage1.v1-draft-20261020`. Validate only in existing synthetic fixtures after explicit implementation approval. Actual data collection, uploads, PMES, billing operations, printing, payment and scheduling remain separate implementation authorizations.

**Task boundary:** only this report was added. Source image hashes/file availability and direct visual inspection were verified; existing contract/stage definitions were read. No application code, contract, schema, data, authentication/Google, feature flag or collection endpoint was changed. No database/browser application journey or regression suite was needed for this source-based documentation task. Stop after mapping.
