# Task 06Q — Stage 1 owner review

2026-10-21. Ready for synthetic owner review only. This document records implemented behavior and pending decisions; it grants no policy approval or collection authorization.

## Usability changes made

Modified only `app/views/pages/new-connection-prototype.php` and `assets/js/new-connection-prototype.js`, plus this report and regenerated HTTP browser results/screenshots.

- Shortened the development guidance and removed duplicate warnings while retaining the prominent synthetic-only notice.
- Shortened repeated labels to “required,” with a nearby explanation that these are unapproved test requirements. Optional field labels remain explicit; conditional category/occupancy labels now explain when required.
- Shortened the applicant/account identity explanation; applicant information can differ from the signed-in identity and prefill requires confirmation.
- Explained that same-address copying stays active for residential changes and switches off when the service address is edited. Existing client/server copying rules are unchanged.
- Kept the compact scrolling mobile section row and scrolls it to the current section when navigation changes. Keyboard navigation and error-summary/field focus are retained.
- Retained the four native collapsible review groups, initially expanded, with Edit actions. Validation messages and server rules were deliberately preserved.

No form, contract, persistence, proof, consent or submission service was duplicated or changed. Contract remains `stage1.v1-draft-20261020`.

## Complete provisional field checklist — implemented

Required means required for synthetic submission readiness, not for an incomplete valid draft. Text fields accept strings/null; confirmations are booleans. Lengths below are existing character limits, not approved policy.

| Section | Fields and current rules |
|---|---|
| Applicant | `first_name`, `last_name`: required, 25 each. `middle_name`, `extension_name`: optional, 25 each. `contact_number`: required, input up to 64, supported Philippine formats normalized to 11-digit `09…`. `profile_confirmed`: required confirmation. Unicode names supported; no automatic full-name split. |
| Portal identity display | Verified synthetic email, read-only and supplied server-side. It is not editable applicant input or evidence that the account name is the legal applicant name. |
| Residential address | `province_code` (32; must match municipality), `city_municipality_code` (32, required), `barangay_code` (32, required), `street_purok_sitio` (160, required), `house_lot_unit_building` (100, optional), `additional_address` (160, optional), `postal_code` (4, optional; exactly four ASCII digits when supplied). Catalog relationships enforced server-side. |
| Service location | All seven residential address fields above, independently validated; plus optional `landmark` (200), and boolean `same_as_residential`. Copying is reapplied server-side when selected. |
| Service request | Server-owned fixed new-connection request kind. `premises_classification_code` and `occupancy_code`: required, 32 each. `connection_type_code`: 32, required only for applicable synthetic category. `occupancy_other`: 100, required when Other is selected. `requested_service_details`: optional, 500. All catalog choices are synthetic examples. |
| Review | `information_confirmed`: required. Read-only saved snapshot with Edit links and four collapsible groups. A changed draft requires save and fresh proof/review. |
| Separate acknowledgments | `privacy_notice`, `information_accuracy`, `application_verification`, `physical_signing`: four distinct required selections for synthetic submission. Not handwritten signatures or approved electronic signatures. Test notice versions/content supplied by server, not applicant input. |

Unknown keys, incorrect JSON types, malformed/oversized content, invalid catalog relationships and server-owned overrides are rejected. Drafts may be incomplete but must be valid. Acknowledgments are not restored as accepted draft consent. Submitted revisions remain immutable; stale saves conflict and exact idempotent retries return their original authorized outcome.

No meter serial, existing electric account number, installation technical details, document uploads or official PDF is collected/generated.

## Owner decisions awaiting authorization

Please record owner, decision, date and supporting approved source for each item; all remain **pending**:

- [ ] Membership/service application owner: confirm authoritative forms and map necessary fields; approve identity lengths, requiredness and optional omissions.
- [ ] Applicant eligibility owner: define legal applicant versus representative/account-holder rules and permitted prefill; decide Google-only/unverified recovery separately from this UI.
- [ ] Service-area owner: approve locality catalog, independent-city/province treatment, postal conventions and versioning. Synthetic localities must never become production fallbacks.
- [ ] Connection operations owner: approve premises, occupancy, conditional categories and requested-details purpose; review same-address behavior.
- [ ] Privacy/legal owner: approve purpose/lawful basis, recipients, rights/contact details, retention/deletion and backup treatment; determine whether each acknowledgment is necessary. Synthetic notices are not official policy.
- [ ] Operations/legal owner: approve printed forms, physical signing and offline payment wording; no current application/payment promise is authorized.
- [ ] Review staff owner: approve correction/reopening, office assignment, rejection and audit retention rules.
- [ ] Product/accessibility owner: review mobile section discovery, address editing, error recovery and expanded/collapsed review with keyboard and screen reader.

Suggested owner exercise: start the temporary runner using the command in the Task 06P report; use synthetic data to save an incomplete draft, try an invalid mobile, copy/edit addresses, select conditional choices, review/edit/save again, and submit with four test acknowledgments. Record observations rather than approving policy by checking fixture boxes. Stop with `q` then Enter.

## Screenshots and visual findings

Fresh 1440/390/320px screenshots were captured by the HTTP suite. Opened and visually inspected:

- [Desktop light](stage1-http-screenshots/identity-1440-light.png)
- [Mobile dark](stage1-http-screenshots/identity-390-dark.png)

The shortened introduction and labels reduce wrapping. Mobile section navigation remains horizontally scrollable within the page, with no page overflow. Long review content remains available through native expandable groups. Screen-reader acceptance is still outstanding; automated keyboard checks do not establish complete accessibility compliance.

## Fresh regression results

| Suite | Result |
|---|---|
| Stage 1 HTTP/browser | PASS 230 checks; 1440/390/320px, light/dark, keyboard/error focus, review collapse/reopen, draft/review/submission and security journeys |
| Database HTTP invariants | PASS: four synthetic submissions, exactly seven stages each, four consents/one event/one receipt per submission; nonfixture schema signatures unchanged |
| Draft persistence | PASS 149 assertions |
| Atomic submission | PASS 97 assertions |
| Pure contract | PASS 42 assertions |
| Foundation/navigation/default-off | PASS 47 assertions |
| Structure | PASS 201 assertions |
| Read-only contract | PASS |
| Manual runner isolation/lifecycle | PASS: loopback startup, private-file denial, foreign Host denial, anonymous denial, shutdown and schema removal |
| Modified PHP/JavaScript syntax | PASS |

All disposable schemas were removed. The HTTP suite retains ownership, status/verification, CSRF/Origin, expiry, proof invalidation, concurrency, idempotency and private-response tests. No actual customer records, operational queries, Google/SMTP, dashboard, account linking or eBills journeys were accessed. No actual portal configuration, authentication controller, migration guard or application feature flag changed. This is synthetic fixture acceptance, not authenticated actual-portal acceptance.

## Proposed first controlled portal integration — not implemented

1. Obtain the owner decisions above and an attested synthetic identity/database staging environment; do not create test identities in the customer database.
2. Prepare an owner-reviewed change map for a separate default-off staging entry/controller and configuration, authenticating through actual session/current-user helpers while permitting zero linked accounts. Keep the read-only preview route separate and existing Google behavior unchanged.
3. Route that entry only to the existing Stage 1 view/contract and authorized services after the disposable-only service guard/deployment design has separately passed review. Do not remove those guards as a UI shortcut or deploy the fixture router/login/provider.
4. Supply approved versioned catalog/notice providers, least-privilege storage, HTTPS/proxy settings, shared rate limiting and reviewed key/backup recovery before authorizing persistence. Retain exact-origin, CSRF, ownership, version/proof and retry protections.
5. Run actual staging-session, IDOR, lifecycle and existing portal regressions using synthetic identities; approve enabling collection as a distinct later action. No production collection or submission integration is authorized by this report.

Next recommended task: owner review with recorded decisions and usability observations, followed by an explicit staging integration proposal. Stop after this refinement; the temporary fixture and actual read-only preview remain separate.
