# Task 06Y — Approved UI improvements and geographic blockers

2026-10-22. **Education and required-field presentation implemented in the existing synthetic prototype. Verified Palawan selection/map integration remains blocked; the UI explicitly retains the synthetic fallback.** No real collection, geographic coordinates or application endpoints were enabled.

## Baseline and scope

The actual checkout contains `stage1.v1-draft-20261020` with no education, spouse or map-pin contract fields. Therefore there was no existing educational free-text field to replace, and no conditional spouse fields to annotate. This differs from the task's assumed starting point. No spouse model or unrelated personal-information fields were invented.

Modified only:

- `app/views/pages/new-connection-prototype.php`: education dropdown/Other specification, required legend and markers, no Optional labels, clearly blocked geography/map presentation.
- `assets/js/new-connection-prototype.js`: education visibility/reset, required ARIA/conditional markers, invalidation of display-only pin state on locality/address-copy changes.
- `assets/css/new-connection-prototype.css`: red/light and pale-red/dark required markers, screen-reader helper, blocked-map layout.
- `tests/new-connection/applicant-prototype-browser-test.js`: new dropdown, conditional, dependency, marker and blocked-map checks.
- This report and regenerated existing prototype/HTTP browser artifacts.

No authentication, Google, account workflows, operational database/configuration, migration, feature gate, service contract or persistence service changed. The read-only renderer was not changed; a regression exposed its assumption that guidance immediately follows the heading, so the required legend was placed inside the existing guidance paragraph rather than introducing another paragraph ahead of it.

## Implemented behavior

Educational attainment has the exact twelve owner-specified choices: No Formal Education; Elementary Undergraduate; Elementary Graduate; High School Undergraduate; High School Graduate; Senior High School Graduate; Technical / Vocational Graduate; College Undergraduate; College Graduate; Postgraduate (Master's Degree); Postgraduate (Doctorate); Other. Blank is permitted. Other reveals an enabled specification input; choosing another option clears/disables/hides that input. Neither control is required. The specification's 100-character UI bound is provisional, not an official data requirement.

**Education is UI-only in this change**: the page visibly explains that it is not included in saved drafts, review proofs or submitted v1 snapshots. It has no `data-path` and no browser-storage persistence. It is not represented as database-saved. Persisting education needs a deliberate versioned contract change, server validation, review mapping and compatibility tests; this task did not silently extend an immutable snapshot model. This is a documented limitation, not a completed persistence feature.

Every currently readiness-required applicant field has a red asterisk, including identity/review confirmations and four acknowledgment controls. A consistent `* Required fields` explanation is shown. The word Optional is removed from rendered labels without changing server requiredness. House/building, postal code, landmark, middle name, extension, education and other previously non-required fields remain non-required. `aria-required` follows the existing rules; native HTML required attributes are not added, preserving incomplete draft saves. Conditional category/Other occupancy markers update with applicability. Spouse-specific markers are blocked because spouse fields do not exist in the approved current contract.

## Geographic source verification

Primary pages checked on 2026-10-22:

| Source | Verified evidence | Limitation |
|---|---|---|
| [PSA PSGC barangays/publications](https://psa.gov.ph/classification/psgc/barangays) | Official administrative names/codes and publication releases; page advertises the 30 September 2026 publication | A directory/publication is not geographic polygons or proof of service coverage. No complete release was imported/validated here. |
| [PALECO cooperative background](https://paleco.net/paleco/our-cooperative/) | Lists city/municipal franchise coverage and a table | Narrative list is not an approved versioned barangay/application-eligibility join; no automatic admission decision derived from it. |
| [PALECO coverage](https://paleco.net/paleco/coverage/) | Describes franchise systems and incomplete electrification | Franchise inclusion and actual location eligibility are not equivalent. No per-premises eligibility catalog obtained. |
| [Government-hosted PSA Barangay MapServer](https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer) | Explicitly describes **indicative June 2016 boundaries**, from the 2015 census layer; spatial reference 3857 | Cannot treat this as verified current 2026 boundaries without reconciliation, licensing and owner approval. No coordinates/polygons were imported. |

No general Palawan municipality list, third-party geocoder or geographic centroid was treated as approved PALECO coverage. No invented town/barangay names or coordinates were introduced. The existing `TEST-*` catalog remains explicitly synthetic, rather than relabeling its independent-city/province relationships as real Palawan geography.

## Blocked geographic behavior and safe fallback

- Service province is **not yet internally fixed to a real Palawan code**. Existing synthetic selectors remain unchanged, with an explicit notice that server-fixed Palawan is pending approved catalog integration. This is the authorized fallback, not the requested final geographic implementation.
- Existing synthetic municipality/barangay dependency and server-side relationship checks remain. No verified Palawan dropdown is claimed.
- A required-pin heading is displayed as the planned geographic requirement. The confirmation button is disabled, no map provider is loaded and no actual pin can be selected/confirmed. Municipality/barangay and same-address changes clear any display-state confirmation. This is not server-side geometric validation or an implemented pin contract.
- Existing synthetic v1 submission tests still exercise their existing contract; they do **not** satisfy the future required-pin requirement. The UI explicitly states that limitation. No new submission or real applicant collection is activated.
- Municipality centering/zoom, barangay zoom and incompatible-coordinate rejection remain blocked. No fake map or arbitrary coordinates substitute for verified geometry.

## Required design before geographic implementation

1. Obtain owner-approved, versioned PSGC-to-PALECO barangay eligibility mapping with handling for city/province classifications, exceptions and unavailable service. Decide whether a pin outside an eligible polygon is denied or requires staff review.
2. Obtain approved current boundaries and spatial metadata: source date, CRS, licensing, change history, geometry validity and linkage to the exact PSGC release. Do not treat a centroid or map zoom as a service boundary.
3. Select/approve tile/map provider or offline/self-hosted assets, attribution, allowed caching, keys/restrictions, privacy/network implications, availability and CSP. Do not weaken CSP or introduce a required external CDN to make the map render.
4. In a versioned synthetic contract, derive Palawan internally, reject browser province overrides, validate municipality/barangay/coverage, and validate finite coordinates plus confirmation tied to the selected catalog version and exact point. Reset point confirmation on changed locality or moved point and recheck server-side.
5. Fit municipality bounds, then barangay bounds; require manual exact placement and explicit confirmation, accessible keyboard placement/alternative and error focus. Do not auto-confirm a centroid. Keep house/building, postal code and landmark non-required. Preserve draft incompleteness, proof invalidation and no PII browser storage.

These steps are not implemented or approved deployment changes in this task.

## Tests and review

Local synthetic-only browser checks cover 1440/390/320px light/dark, education choices/Other behavior, missing education acceptance, required/unmarked labels, conditional markers, synthetic barangay dependencies, invalidation after locality change, unavailable confirmation, existing draft/review flows, keyboard/error focus and no horizontal overflow. Pin invalidation tests inject display state only to exercise clearing; they do not claim valid geographic confirmation.

| Suite | Result |
|---|---|
| Prototype browser | PASS 121 checks |
| Existing Stage 1 HTTP/browser | PASS 230 checks; four synthetic submissions, expected stage/consent/event/receipt invariants; nonfixture schema signatures unchanged; disposable schema removed |
| Existing read-only Apache | PASS 100 assertions after required-legend placement correction |
| Pure contract | PASS 42 assertions |
| Foundation | PASS 47 assertions |
| Authentication security | PASS 42 assertions |
| Structure | PASS 207 assertions |
| PALECO read-only contract | PASS |
| Prototype PHP / JavaScript syntax | PASS |

Manual review uses the existing temporary runner; no additional environment was created:

```powershell
& C:\xampp\php\php.exe tests/new-connection/stage1-local-development-runner.php --start
```

Open the printed URL, use `demo-a` / `fixture-only`, inspect education and Other in identity, then service-location fallback. Use synthetic information only; type `q` then Enter to clean up. Education is not restored by draft resume. The existing synthetic submission is a v1 regression demonstration, not map-complete application acceptance.

Screenshots regenerated by the existing suites:

- [Desktop identity/education](applicant-prototype-screenshots/identity-1440-light.png)
- [Mobile dark identity](applicant-prototype-screenshots/identity-390-dark.png)
- [Mobile service-location fallback](applicant-prototype-screenshots/service-location-390.png)

Desktop identity and mobile service-location screenshots were opened and visually inspected. Required marks are visible, optional labels are absent, education remains unmarked, and the unavailable map is explicit. Long fallback explanations increase page height but do not introduce horizontal overflow.

## Remaining decisions and completion status

Completed: education UI, Other condition, required presentation and safe blocked-geography fallback. **Not completed:** persisted education, spouse-field markers, fixed actual Palawan province/verified locality catalog, real map/pin confirmation and required-pin server validation. The latter geographic items are blocked by approved data/provider availability; spouse markers require the missing spouse implementation to be identified/approved, rather than inventing a family-data model.

Next step: owner supplies/approves the versioned eligibility/boundary/provider package and confirms a versioned contract scope for education and pin data. Then implement and test the real synthetic geographic workflow before any collection authorization. No production collection, upload, payment, database migration or submission feature was enabled. Stop here.
