# eBills enrollment flow reordering — 2026-10-16

## Implemented

Both Linked Accounts and the optional enrollment step after successful account
linking open the same three-screen native dialog:

1. Official complete terms, compact selected account code/name/address card,
   initially unchecked and disabled acceptance checkbox, Cancel and Continue.
   Scrolling the keyboard-accessible terms region to its bottom unlocks the
   checkbox; Continue requires an explicit check.
2. Editable first/last name (required), middle/extension (optional), required
   Philippine mobile number, and read-only registered portal email. Values are
   prefilled only from the authenticated portal profile. Account holder data is
   displayed once in the account card, never used to populate personal fields.
3. Review of entered personal information and registered email, the shared
   selected-account card, and confirmation of terms acceptance. Back preserves
   edits without saving; Confirm Enrollment performs the only enrollment write.

The dialog keeps focus within the existing native modal, focuses its heading on
step changes, restores the opener on close, handles Escape/Cancel, preserves
errors and entered values, and prevents duplicate submissions while requests
are pending. Existing light/dark styling and a bounded responsive modal remain.

## Official terms

Full visible terms were retrieved directly from PALECO's existing page:
https://paleco.net/paleco/mco_portal_login/mco_portal_main/mco_portal_ebills_terms/

The reviewed local snapshot contains all ten visible numbered clauses and both
surrounding paragraphs, with unchanged wording. No invented legal text is used.
See `resources/ebills-terms-source.md` for exact snapshot hash, provenance and
update procedure. The official page has no published document version, so the
consent version is the SHA-256 of the exact displayed local resource. Pending
acceptance becomes invalid if that document changes. Deployment must include
the resource file; portal requests do not depend on the external site.

## Server-side consent and transaction

Opening enrollment creates an unpredictable, session-held, 30-minute challenge
bound to the authenticated user, selected linked account, and current terms
hash. A CSRF-protected acceptance request validates that binding and explicit
acceptance before advancing. Direct enrollment without this accepted state, or
with an expired/wrong-account/wrong-user/stale-version challenge, fails. The
scroll gate is a browser usability requirement; it does not claim to prove that
a person has read or understood the document.

Final confirmation rechecks CSRF/authentication, accepted current terms, current
active linkage and existing AccCode. The service validates reviewed name fields
and mobile, and reloads the registered email from the locked authenticated user
row. It inserts only the nine approved eBills columns, updates that user's five
structured profile fields, and inserts `activity_logs` consent evidence using
one PDO connection and one cross-schema InnoDB transaction. Consent logs include
user reference, AccCode, exact acceptance wording, document hash/source,
acceptance time and successful enrollment time, without names/email/mobile.

Required-write failures roll back enrollment, profile updates and successful
consent evidence together. Pre-confirmation acceptance is session state only;
cancelled/failed actions produce no successful consent log. Existing enrollment
and duplicate-key paths never overwrite eBills or save profile edits. Repeated
successful submissions safely return already enrolled without another audit.

No schema/migration, existing eBills update, excluded Attachment/OTP access,
SOA generation, email sending or SMTP configuration change was made by this task.
Account linking, unlinking and optional post-link enrollment entry remain intact.

## Files changed for this task

- `dashboard.php`: acceptance action and explicit enrollment-created result.
- `includes/ebills-enrollment.php`: terms/version loading, bounded session
  challenges, acceptance validation, account address, editable-name validation
  and transactional profile/consent changes.
- `includes/ebills-ui.php`: three-screen responsive modal.
- `assets/js/ebills-enrollment.js`: scrolling, acceptance, navigation, review,
  safe text rendering, busy/error handling and status/profile refresh.
- `resources/ebills-terms.html`: complete official visible terms snapshot.
- `resources/ebills-terms-source.md`: provenance, hash and maintenance procedure.
- `tests/ebills-enrollment-mysql-test.php`: expanded synthetic integration tests.
- `tests/ebills-enrollment-js-test.js`: three-screen navigation tests.
- `tests/ebills-terms-test.php`: official snapshot and rendered-modal checks.
- `tests/ebills-enrollment-report.md`: historical implementation note.
- `tests/ebills-flow-reordering-report.md`: this report.

Other pre-existing worktree changes belong to preceding implementation tasks.

## Verification results

- Official terms/render tests: **21 assertions passed**.
- eBills isolated MySQL tests: **61 assertions passed**, including missing saved
  names, edited names saved to the correct profile, server email authority,
  direct consent bypass, expiry/version/user/account mismatch, CSRF, duplicate
  immutability, rollback and simultaneous enrollment by two users.
- Modal JavaScript tests: **157 assertions passed**, including both entry points,
  initial screen/reset, scroll gate, Back/Cancel, validation, review edits,
  acceptance/enrollment failures, duplicate submission and badge updates.
- Profile/registration tests: **24 assertions passed**.
- Account linking/unlinking MySQL regression: **170 assertions passed**.
- Linking input/configuration, submission/navigation JS, unlink dialog JS,
  password recovery PHP/JS, notification templates (**37 assertions**) and fake
  transport, session action HTTP (**22 assertions**) and JS, dashboard/education
  smoke tests, billing and SOA fixtures: **passed**.
- PHP syntax, JavaScript syntax, PALECO read-only contract and whitespace checks:
  **passed**.

Database mutations in automated enrollment/linking tests use dedicated isolated
fixture databases, removed afterward. No real enrollment, profile edit or email
was created by these tests. The operational eBills count remains 4,205 and real
`ebills_enrolled` consent-log count remains zero.

## Remaining manual verification

No browser automation dependency is installed. Manually verify desktop/mobile
layout and actual native dialog keyboard/scroll behavior in both themes. The
automated DOM/render checks cover styling hooks and navigation behavior but do
not substitute for visual browser verification. No application migration or
mail configuration step is required; deploy the PHP/JS files together with the
reviewed terms resource. Hard-refresh already open pages after deployment so
their displayed terms and server version agree.
