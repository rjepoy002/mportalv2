# Profile Settings mobile-number audit — 2026-10-19

## Inspection and root cause

The public profile endpoint remains `dashboard.php?page=account-settings`, POST action `save_profile`. Its implementation is app/controllers/dashboard.php and calls EBillsEnrollment::saveProfile. Profile markup is app/views/pages/dashboard.php. There was no dedicated profile JavaScript validator before this change. The input imposed maxlength=11, pattern=09[0-9]{9}, and a numeric input mode. Shared PHP portalProfileValues also required exactly 09 followed by nine digits. A +63 value was therefore too long for the input and failed both format checks. Separators also failed validation.

A read-only metadata check confirmed mco_portal.users.contact_number is nullable VARCHAR(12). An aggregate-only check found two profiles: one canonical number and one empty/null contact. No personal numbers were exported or modified. Canonical values require only 11 characters, so no schema change or data migration is necessary.

## Changes

| File | Change |
| --- | --- |
| app/helpers/philippine-mobile.php | Strict string normalization for the four requested mobile formats, ASCII spaces and hyphens; rejects malformed/non-string input with a field-specific exception |
| app/services/ebills-enrollment.php | Profile saving normalizes before shared profile validation and the existing transactional parameterized UPDATE; authentication/CSRF checks still precede validation |
| app/controllers/dashboard.php | Profile contact errors survive the normal redirect for inline rendering; invalid contact does not write; successful saves clear stale contact errors |
| app/views/pages/dashboard.php | Removes the profile contact's restrictive pattern/length; uses type=tel, inputmode=tel, autocomplete=tel; adds format guidance, accessible inline error and dedicated script reference |
| assets/js/profile-settings.js | Matches backend accepted formats; normalizes on blur and submit; input events only clear stale errors without rewriting the value; invalid submit focuses the field and blocks posting |
| tests/profile-mobile-fixtures.json | Shared valid/invalid format fixtures for PHP and JavaScript |
| tests/profile-mobile-test.php | Normalization, transactional profile updates, stored values, invalid input, CSRF, actual controller branch, server error rendering and escaping with PDO doubles |
| tests/profile-mobile-js-test.js | Editing/paste preservation, blur/submit normalization, error recovery, blank optional contact and shared fixture parity |
| tests/profile-mobile-browser-test.js | Real profile markup/JavaScript in Chrome at exact 390px and 768px iframe viewports, synthetic profile only |

09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX and 9XXXXXXXXX all save as 09XXXXXXXXX. Leading/trailing spaces and embedded ASCII spaces/hyphens are accepted. Blank/space-only optional input remains NULL. Hyphen-only input, tabs/newlines, Unicode digits, non-mobile prefixes, wrong lengths, punctuation other than hyphens and array/numeric submissions are rejected. Existing canonical numbers and NULL remain unchanged when saved. No automatic on-load rewrite or bulk update occurs.

Normalization is scoped to saveProfile. Registration and eBills enrollment retain their existing form and shared validation behavior. Names, email, display name, password, linked accounts, consent rules, SQL, transaction boundaries and database schema are unchanged.

## Results

- PHP profile mobile suite: PASS, 135 assertions. Includes all four formats, separators, canonical/NULL persistence, invalid inputs without writes, CSRF rejection, actual profile-controller branch execution, post-redirect error state and HTML escaping. Persistence/controller tests use PDO doubles and do not write to the live database.
- Node profile mobile suite: PASS; same format fixtures as PHP, blur, native-submit event handler, editing/paste, inline validity and correction behavior.
- Chrome: PASS at actual 390px and 768px content viewports, 10 checks each. Real rendered profile form confirms tel keyboard/autofill attributes, no truncating maxlength/pattern, preserved initial value, unchanged text during editing, blur-event normalization, requestSubmit normalization/blocking, inline error/focus, correction and optional blank input. Tests use synthetic data and no network submissions.
- Related regressions: PASS profile-registration (24 assertions), dashboard smoke, eBills terms/render (24 assertions), eBills workflow JS (174 assertions), structure (162 assertions), PALECO SQL read-only contract.
- PHP syntax: PASS for all five changed/new PHP files. JavaScript syntax: PASS for the application script and both new JS tests. git diff --check: PASS.

Physical Android/iOS keyboard appearance and real-device autofill remain manual checks: desktop Chrome narrow viewports verify markup and browser events, not a physical handset keyboard. No live profile POST was performed; update behavior was exercised through the existing service/controller with synthetic persistence. Existing live values were inspected only through aggregate counts. Test commands: `php tests/profile-mobile-test.php`, `node tests/profile-mobile-js-test.js`, `node tests/profile-mobile-browser-test.js` (XAMPP PHP and Chrome defaults; PHP_BINARY/CHROME_BINARY override the browser test executables).
