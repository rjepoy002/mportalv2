# Task 06U — Official requirements review

2026-10-21. Presentation cleanup only. **Every field/policy decision below remains pending; no official approval evidence was supplied.** This document uses the [Task 06R complete field matrix](stage1-integration-readiness-report.md), not synthetic catalogs or declarations as authority.

## Presentation corrections

Changed `app/helpers/new-connection-stage1-display.php` only for application presentation. Explicit DOM rules scoped to the read-only render now:

- Set browser title, header and main heading to **Stage 1 — Applicant Information**.
- Replace section instructions with neutral provisional descriptions; remove interactive hints and first-person declaration wording.
- Mark every field label provisional and show confirmation/declaration topics as approval pending.
- Show the conditional category/Other field structure for review, without controls.
- Preserve all five sections, prominent read-only/development notice, local theme/sidebar assets and server-side removal of forms, controls, editor scripts and action buttons.

No broad string replacement was added. The interactive prototype view/JavaScript, contract, draft/proof/submission services, session helpers and feature gates are unchanged. No new environment or launcher was created; Tasks 06S/06T are reused.

Updated `tests/new-connection/stage1-readonly-test-support.php` with assertions for naming, neutral language and provisional labels. Existing test result JSON and screenshots were regenerated. HTML assertions decode entities before testing rendered punctuation; this corrected an initial raw-HTML test failure without changing eligibility or isolation controls.

## Actionable PALECO approval checklist

For each row, the reviewer should record: **named approver, approved/changed/rejected decision, date, exact document/version/page or signed decision reference**. Suggested owner functions below are not claims that an individual has accepted responsibility. A missing official source is a blocker, not an approval.

| Decision | Current/proposed behavior for review | Owner / status | Supporting official source |
|---|---|---|---|
| ☐ Applicant names | Existing separate first/last required and middle/extension optional, 25 characters each; no full-name splitting. Decide legal-name exceptions, length and requiredness before collection. | Membership/service applications owner — **pending** | Approved membership/service forms and field instructions needed; none attested here |
| ☐ Contact mobile | Existing Philippine formats normalize to `09…`; required for synthetic readiness. Decide necessity, alternate contact and applicants without mobile. | Applicant services + privacy owner — **pending** | Approved contact/notification requirements needed |
| ☐ Residential address | Existing municipality/barangay/street required; province relationship enforced; building/additional address/postal optional. Decide legitimate purpose, requiredness and residence outside service area. | Membership owner + privacy owner — **pending** | Approved form/address requirements and locality source/version needed |
| ☐ Proposed service location | Existing separate address plus optional landmark and same-as-residential copying. Decide minimum inspection-location detail, missing-locality handling, coverage and independent-city rules. | Technical/service-area owner — **pending** | Authorized service-area catalog and location instructions needed; synthetic examples are not evidence |
| ☐ Premises classification | Existing example catalog requires one premises code; categories apply conditionally. Approve exact official labels/codes and applicability; do not adopt example shop rules automatically. | Connection/technical operations — **pending** | Approved classification schedule and applicability matrix needed |
| ☐ Occupancy | Existing example choices, required occupancy and conditional Other clarification. Decide official choices and whether evidence is collected in a later document stage. | Membership/connection operations — **pending** | Approved occupancy requirements/form instructions needed |
| ☐ Applicant versus representative | Current legal applicant fields are separate from signed-in display identity; no representative model exists. Decide who may apply for another person, authorization evidence and account ownership. Any new fields require an explicit contract change. | Membership + legal/security owners — **pending** | Approved representation/authorization policy needed |
| ☐ Portal versus applicant email | Existing snapshot email is server-supplied verified portal email, read-only. Decide whether a separate applicant contact email is necessary and how it would be verified/updated; do not silently add it. | Authentication + applicant services + privacy — **pending** | Approved contact and verification policy needed |
| ☐ Required/optional/conditional fields | Review every row in Task 06R; accept/change/remove purpose and limit for each. Valid incomplete drafts are supported synthetically; readiness is distinct from official eligibility. | Application process owner + privacy — **pending** | Signed field matrix referencing authoritative forms/purpose needed |
| ☐ Privacy notice | No approved notice exists in this environment. Approve purpose/lawful basis, recipients, rights/contact details, retention/deletion/backups and exact notice version before real collection. Do not assume acknowledgment supplies a lawful basis. | PALECO privacy/legal owner — **pending** | Approved versioned privacy notice and processing decision needed |
| ☐ Review and declarations | Existing four separate synthetic topics: privacy, accuracy, application/document verification, physical signing. Approve necessity, exact text and withdrawal/version rules; selections are not signatures. | Application process + privacy/legal — **pending** | Approved declaration wording/version and publication decision needed |
| ☐ Offline printing/signing/payment | Proposed staff-prepared forms, handwritten signatures at an office visit and offline payment. Decide forms, print responsibility, visit timing, payment sequence and signed-form verification. No online payment or printing is enabled. | Connection operations + billing/cashier + legal — **pending** | Approved forms and signed operating procedure needed |

## Remaining decisions and release boundaries

Also unresolved: draft/submitted retention, correction/rejection/reopening, office/staff ownership, approved catalog publication and withdrawal, proof-key rotation, staging identity attestation and HTTPS/backup recovery. Existing Google behavior is outside this field review and unchanged.

Approve display, synthetic entry, staging persistence, real collection and actual submission separately, as specified in Task 06R. Neither this checklist nor checking synthetic acknowledgments authorizes any later capability. No real customer information should be entered in the review environment.

## Screenshots

Updated screenshots from the **existing Task 06T live temporary Apache environment**:

- [Desktop light](stage1-manual-review-desktop-light.png)
- [Desktop dark](stage1-manual-review-desktop-dark.png)
- [Mobile light](stage1-manual-review-mobile-light.png)
- [Mobile dark](stage1-manual-review-mobile-dark.png)

Desktop light and mobile dark were opened and visually inspected: corrected headings, neutral instructions, provisional labels, no editable controls, consistent portal styling. Five sections remain available by vertical scrolling. Additional Task 06S exported-render screenshots were also regenerated by its existing browser suite. No actual authenticated customer port-80 screenshots were taken.

## Fresh regression results

| Suite | Result |
|---|---|
| Read-only isolated Apache | PASS 100 assertions, including new presentation checks and existing authorization/method/gate/isolation checks |
| Existing read-only browser | PASS 11 checks |
| Existing manual live Apache/browser | PASS 20 checks plus cleanup verification |
| Interactive Stage 1 HTTP/browser | PASS 230 checks; four synthetic submissions with seven stages each and expected consent/event/receipt counts; nonfixture schema signatures unchanged; schema removed |
| UI-only interactive prototype | PASS 97 checks |
| Draft persistence | PASS 149 assertions |
| Atomic synthetic submission | PASS 99 assertions |
| Foundation/navigation/default-off | PASS 47 assertions |
| Authentication security | PASS 42 assertions |
| Structure | PASS 207 assertions |
| PALECO read-only contract | PASS |

Tests used local XAMPP/PHP/MariaDB and synthetic disposable identities only. Apache/browser checks retain desktop/mobile light/dark and keyboard/theme/sidebar behavior; interactive tests independently cover keyboard/error focus, draft/review and synthetic submit. No security checks were removed. Created disposable schemas and temporary servers were cleaned up. Actual application/display flags, customer identities, authentication/Google controllers, operational data, migrations and business workflows were not modified. No new applicant collection endpoint exists.

## Next step after owner approval

Record signed field decisions and official source versions first. Then propose an explicit versioned contract/catalog/notice update reflecting only approved changes, with compatibility tests and a synthetic owner acceptance session in the existing temporary runner. Any trusted staging identity bootstrap or editable/persistent portal integration remains a separate reviewed task. Keep real collection, drafts and submission disabled. Stop after this presentation and requirements review.
