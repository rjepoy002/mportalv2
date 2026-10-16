# eBills modal layout refinements — 2026-10-16

## Changes

- `includes/ebills-ui.php`: account summary initially hidden; external source
  link removed; complete official terms retained unchanged. Terms height is
  viewport-constrained, up to 28rem (448px), reserving space for headings,
  consent and buttons. Four editable name fields now use one column below
  640px and two columns from 640px, with contact/email below the grid.
- `assets/js/ebills-enrollment.js`: show the shared account card only on Steps
  2 and 3; hide it again when returning to Step 1. Account details are rendered
  once, so the account name is not duplicated in the review.
- `tests/ebills-enrollment-js-test.js`: verify summary visibility when opening,
  navigating forward, reviewing and returning to terms.
- `tests/ebills-terms-test.php`: verify hidden initial summary, removed external
  link, responsive grid and unchanged official terms snapshot.
- `tests/ebills-layout-browser-test.js`: real headless Chrome checks with only
  synthetic account/profile data, mocked requests and temporary files.
- `tests/ebills-modal-layout-report.md`: this report.

No service/controller, verification, consent audit, schema, mail or existing
enrollment-record changes. Terms resource content and SHA-256 remain unchanged.
Profile prefilling, editing and synchronization use the existing implementation.

## Results

- Modal JavaScript: **174 assertions passed**.
- Terms/render checks: **24 assertions passed**, including all ten official
  clauses and the original exact snapshot hash.
- Isolated eBills MySQL integration: **61 assertions passed**, including
  server-side consent validation, ownership, duplicates, profile synchronization,
  transactional rollback and concurrent enrollment.
- PHP syntax, JavaScript syntax, PALECO operational write contract and whitespace
  checks passed.
- Actual headless Chrome rendering in both light and dark themes passed at CSS
  viewports **1424 × 905**, **752 × 929**, and **500 × 749**: **116 checks** total.
  Checks include account-card visibility, complete scrollable terms, maximum
  height and enlarged desktop height, consent and Continue within the viewport,
  scroll/acceptance gating, actual two/one-column input positions, profile
  prefilling, read-only email, review identification, no repeated account name,
  theme colors and cancellation.

Chrome clamps the requested 390px headless window to a 500px CSS viewport on this
machine; the mobile check therefore covers a 500px narrow viewport. Physical
device behavior and narrower phone widths remain manual checks. No claim is
made of testing on physical devices. The runner requires installed Chrome,
PHP and access to the project's existing Tailwind CDN; it uses no added package.

Run: `node tests/ebills-layout-browser-test.js`. Optional `CHROME_BINARY` and
`PHP_BINARY` environment variables override the local XAMPP/Chrome paths.
No real authenticated user session, customer enrollment or mail delivery is used.
