# Linking panel and navigation update

Modified files:

- `dashboard.php`: adds authenticated, CSRF-protected JSON panel responses.
  Intermediate success, Back and validation failures no longer create global
  success notifications. Successful Step 2 alone sets the final success message
  and refreshes the account list. Standard POSTs still work without JavaScript,
  redirecting intermediate responses to the panel anchor.
- `includes/account-linking.php`: adds a pending-challenge summary by joining only
  the authenticated user's server-side challenge to its verified master account.
  Returns trimmed `AcctCode`, optional Friendly Label, registered consumer name
  and address. Internal `AcctNo`, meter serials and expected amounts are excluded.
- `includes/account-linking-form.php` (new): shared escaped template for initial
  HTML and JSON updates. Displays progress, summary, heading/instructions, month,
  amount, ten-minute expiry information, Verify and Link, and Back. Direct access
  returns 404. The identifier uses the same trimmed `master.AcctCode` as the
  existing account card formatter, preserving stored hyphens.
- `assets/js/account-linking.js`: enhances forms with same-origin Fetch and
  replaces only the panel. Focuses the heading with `preventScroll`, then scrolls
  the panel into view without animation. Validation errors receive focus instead.
  Maintains duplicate-submission protection and browser history restoration.
  Network/session failures show an inline refresh instruction without retrying.
- `tests/account-linking-mysql-test.php`: summary isolation, escaping, privacy,
  navigation-target and premature-success checks.
- `tests/account-linking-navigation-test.js` (new): in-place Step 1, errors, Back,
  expiry and final-success navigation.
- This report.

The schema, 600-second challenge lifetime, allowed months, exact decimal matching,
duplicate-bill policy, rate limits, CSRF/authentication and transactional single-use
linking remain unchanged. No operational PALECO records, SMTP settings, unlink
behavior or email functionality were modified.

Executed checks:

- Isolated MySQL suite: **141 assertions passed**, including concurrent workers.
- Linking navigation and submission-control JavaScript suites: passed.
- Existing linking inputs/configuration and unlink confirmation tests: passed.
- Dashboard smoke, billing summary, SOA and PALECO read-only contract: passed.
- PHP lint, JavaScript syntax and whitespace checks: passed.

Manual browser checks remain for mobile/desktop appearance, keyboard focus and
actual scrolling. Sign in, open account settings and verify an authorized test
account. Confirm the formatted account code/name/address, wrong-amount feedback,
Back, ten-minute expiry and final successful linking. Step 1 and Back should be
JSON POSTs without document reloads; only final success navigates to refresh the
linked-account list. No real consumer link was created by the fixture suite.
