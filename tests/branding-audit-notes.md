# User-facing branding audit — October 16, 2026

Completed **17 user-facing text replacements in seven application files**:

| File | Replacements | Visible content |
|---|---:|---|
| `dashboard.php` | 1 | Account unlink confirmation |
| `assets/js/session-actions.js` | 2 | Reset confirmation message and sign-out dialog title |
| `forgot-password.php` | 5 | Page title, logo alt text, brand heading, account description, footer |
| `reset-password.php` | 5 | Page title, logo alt text, brand heading and account/password instructions |
| `index.php` | 2 | Welcome description and sign-in instruction |
| `includes/portal-header.php` | 1 | Dynamic default page title |
| `includes/portal-sidebar.php` | 1 | Shared navigation branding |

Three replacements removed the legacy project-facing product name. Thirteen
standardized shortened portal branding; one standardized the expanded alternate
name. All replacements use the exact text **PALECO MCO Portal**. Existing CSS
capitalization styles were preserved; no component was redesigned.

Also changed `tests/session-actions-js-test.js` to expect the official dialog
title, and added this report. Application edits change only displayed strings
and adjacent whitespace in the welcome sentence.

The case-insensitive audit covered application PHP, shared templates, frontend
JavaScript, HTML, CSS, SVG, JSON, email templates, developer notes and historical
copies/patches. Searches included the project-name spelling with optional spacing,
hyphens/version suffixes, shortened portal branding and the expanded alternate
name. All active source matches were inspected for visibility.

Remaining matches were reviewed and intentionally preserved:

- Application URL paths, OAuth redirect URL and authentication redirect route.
- Cache/rate-limit paths and dedicated test-database names.
- Environment/configuration references, technical comments and developer-facing
  test output/assertions that check for obsolete branding.
- README/developer documentation, including historical wording quoted in reports.
- An inactive pre-recovery patch containing old copy.
- Internal directory identifiers, source-control history and third-party vendor
  implementation were not renamed or edited.

The repeated scan found **no remaining user-facing legacy branding in active
application text**. Email subjects, HTML/plain content, security notices and
automated footers already used the official name and needed no changes in this
task. No separate eBills email-template implementation was found beyond existing
account notifications; SOA access does not add another outbound email template.
No uncertain active text occurrences remain. Visual browser checks are still a
manual deployment check; source scanning does not inspect text baked into images.

Passed verification:

- Session confirmation UI tests: cancellation, confirmation, focus, errors and
  once-only submission with the updated title.
- Session-action HTTP tests: **22 assertions**, using synthetic sessions only.
- Password-recovery controller regressions: PDO/mail doubles, no real email.
- Account-notification templates: **37 assertions**, HTML/plain output and links.
- Mailer wrapper: fake transport, no SMTP network delivery.
- Account-linking MySQL suite: **165 assertions**, including concurrent workers.
- Linking navigation, linking submission and unlink confirmation suites.
- Dashboard and Consumer Education smoke checks; PALECO read-only contract.
- PHP syntax for all six changed PHP application files, JavaScript syntax and
  `git diff --check`.

No account/linking logic, session/authentication behavior, database queries,
account identifiers, routes, configuration keys, SMTP settings, email behavior,
schema or existing developer documentation was changed. No migration or new
deployment setting is needed; refresh the browser to load the updated text.
