# Approved profile schema and eBills enrollment implementation

Historical implementation report: the modal, editable enrollment fields and
consent versioning were subsequently updated. See
[eBills flow reordering report](ebills-flow-reordering-report.md) for the current
three-screen terms-first behavior and latest verification results.

## Migration results

Applied to local XAMPP `mco_portal.users`:

| Nullable column | Verified type |
|---|---|
| first_name | varchar(25) |
| last_name | varchar(25) |
| middle_name | varchar(25) |
| extension_name | varchar(25) |
| contact_number | varchar(12) |

Before migration the current schema had none of these fields. A completed
`mysqldump --single-transaction --routines --triggers` portal backup was created
outside the web root, at:

`$env:TEMP\paleco-portal-before-ebills-20261016-154753.sql`

The CLI runner verifies the completed backup belongs to mco_portal, checks all
existing column definitions before executing additive DDL, and skips matching
columns. Its original-column data fingerprint matched before/after; the eBills
CREATE TABLE definition also matched. Re-running the migration passed without
duplicate-column errors. Both existing users retain NULL in all five new fields.
No existing full names or other user data were backfilled/overwritten.

The earlier contact-only draft is marked superseded and no longer contains an
executable 11-character ALTER. The approved storage width is 12; accepted mobile
values remain exactly 11 digits in canonical 09XXXXXXXXX format.

Migration runner:

```powershell
& C:\xampp\php\php.exe migrations/20261016_ebills_profile.php <completed-mco_portal-backup.sql>
```

Manual rollback procedure is prepared in
`migrations/20261016_ebills_profile_rollback.sql` and was **not executed**. Stop
writes, retain the original backup, back up/export CURRENT structured profile
data, restore the prior application release, then review the five-column DROP.
That rollback loses new structured-field values, so never run it automatically.
No eBills row/schema is changed by migration or rollback.

## Completed behavior

- Profile Settings adds the four name parts and the same contact_number field
  used by enrollment. Save and Confirm validates required first/last names,
  optional middle/extension, 25-character limits and optional canonical mobile.
  Existing users' `name` stays unchanged. Google/legacy users complete these
  fields only if they choose to enroll; no names are inferred or split.
- New registration collects structured names and optional contact. It generates
  the existing full display name as First Middle Last Extension and preserves
  the existing email-verification/registration flow. In-flight older registrations
  remain compatible with nullable fields. Existing sign-in code is unchanged.
- Linked Account cards batch-check exact mapped master.AcctCode against eBills
  AccCode, showing eBills Enrolled or Register for eBills. Lookup errors display
  status unavailable and never imply unenrolled. Existing enrollment recipients
  are never displayed. Status matching uses database collation through master,
  avoiding case/format assumptions about legacy keys.
- Linking Step 1/2 verification and 600-second expiry are unchanged. On committed
  success the panel shows optional Step 3 without a document reload or duplicate
  global success notification. It identifies the actual newly linked account.
  Enrolled accounts show Finish; unenrolled accounts offer Enroll and Skip for
  Now. Failure to look up status still leaves the account linked and offers Finish.
  Closing the browser, skipping or enrollment failure cannot undo the link.
- One reusable native dialog serves cards and Step 3. It shows server-verified
  account code/holder (identification only), saved portal names/email read-only,
  prefilled editable contact and an unchecked mandatory consent checkbox.
  Missing/unsupported saved profile data directs the user to Profile Settings.
  Client/server validation and busy controls prevent accidental double submission.
- Enrollment rereads the authenticated user's saved profile and active association
  under user/link row locks. Request-supplied names, email and account codes are
  ignored. Only mapped `master.AcctCode` supplies the enrollment key.
- A transaction inserts the nine approved eBills columns, updates only that user's
  profile contact, and inserts the consent audit. A primary-key collision becomes
  already enrolled with no overwrite. Required-write/audit failures roll everything
  back. Profile changes later never update an existing enrollment.
- The success message says future statements will be delivered through PALECO's
  billing system; it never claims an SOA email was already sent. The portal does
  not generate PDFs, send SOAs, add enrollment emails or change existing senders.

## Consent and operational boundaries

Verified `activity_logs` is an existing InnoDB portal table with durable user/action,
description and timestamp fields. Enrollment records user_id, ebills_enrolled,
actual account code, consent version `ebills-2026-10-16-v1`, the exact consent
wording and an ISO timestamp. Names, emails, contact numbers, IPs and user agents
are not copied into the consent description. This audit commits atomically with
the enrollment/contact update; it is not merely a PHP log message.

The operational write exception is narrowly restricted to a new explicit-column
INSERT into `palecxzp_pal_db.ebills`. Attachment/OTP values are neither read nor
written by the application; no UPDATE, DELETE, REPLACE, upsert, index or schema
change is introduced for eBills. The read-only contract enforces the exact allowed
INSERT columns; all other operational tables remain read-only. Deployment DB
privileges should mirror that boundary and allow required portal activity_logs
INSERT/profile writes. Unlinking still never removes an enrollment.

## Files modified/added

- `dashboard.php`: profile actions/view, batched enrollment statuses, secure modal
  endpoints, optional Step 3 and shared modal integration.
- `register.php`, `assets/js/register.js`: structured registration fields, display
  name generation and preserved client validation target/error message.
- `includes/ebills-enrollment.php` (new): profile and enrollment service, validation,
  atomic writes, authorization, status lookup, consent and optional-step model.
- `includes/ebills-ui.php`, `includes/ebills-step3.php` (new): shared escaped views.
- `assets/js/ebills-enrollment.js` (new): accessible modal lifecycle and enrollment.
- `includes/account-linking.php`: exposes only the successfully linked internal
  account to the server controller for Step 3; verification rules unchanged.
- `assets/js/account-linking.js`: preserves in-place transition when optional step
  is returned instead of performing the old final document reload.
- `migrations/20261016_ebills_profile.php` (new), superseded contact draft, and
  `migrations/20261016_ebills_profile_rollback.sql` (new): reviewed schema tooling.
- `tests/paleco-readonly-contract.php`, `README.md`: document/enforce only the
  authorized operational INSERT exception and portal consent storage.
- `tests/ebills-enrollment-mysql-test.php`, `tests/ebills-enrollment-js-test.js`,
  `tests/profile-registration-test.php` (new), existing linking integration and
  navigation tests: new and regression verification.
- This report. The earlier inspection report is retained as historical context;
  its schema blockers are resolved by this approval and implementation.

## Test results

- eBills isolated MySQL suite: **46 assertions passed**, including profile sources,
  unauthorized/CSRF/consent/mobile rejection, required-name gating, exact code,
  profile/contact consistency, immutable existing records, excluded-column defaults,
  dates, consent durability/privacy, rollback and two-user concurrent enrollment.
- Profile/registration suite: **24 assertions passed**, including Unicode name
  limits, missing/invalid fields, GET rendering and old validation compatibility.
- Existing linking suite: **170 assertions passed**, including concurrent workers,
  unchanged amount/duplicate verification, notifications, unlinking, relinking and
  committed-account selection for optional enrollment.
- Modal JavaScript: passed for both entry points, saved profile/contact, consent,
  cancellation, double submission, existing/missing/unavailable statuses and badges.
- Linking navigation: passed for Step 1, errors, Back, expiry and in-place Step 3.
- Dashboard smoke passes with the existing nullable/incomplete real profiles.
  Existing users can reach the authenticated portal without completing names;
  sign-in/password verification code was not modified.
- Password-recovery regression, mailer/template, session confirmation, billing,
  SOA, education, read-only contract, syntax and whitespace checks passed.

Test writes use dedicated `mportal_ebills_test` and `mportal_account_linking_test`
databases; fixture tables are removed afterward. No real enrollment, profile edit,
registration or customer email was created by automated tests.

Run the new DB suite after creating an EMPTY dedicated database:

```powershell
& C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS mportal_ebills_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
$env:EBILLS_TEST_DSN='mysql:host=127.0.0.1;dbname=mportal_ebills_test;charset=utf8mb4'
& C:\xampp\php\php.exe tests/ebills-enrollment-mysql-test.php
& C:\xampp\php\php.exe tests/profile-registration-test.php
node tests/ebills-enrollment-js-test.js
```

## Manual checks / outstanding limitations

Browser visual/keyboard checks in both themes and mobile/desktop remain manual.
Sign in as an existing user with missing structured fields: normal dashboard,
billing and linked-account actions must still work. Complete Profile Settings,
then open Register for eBills, verify your saved profile, enter contact/consent,
and submit only an approved real test account. Verify existing enrollment shows
no other recipient details. Check Cancel, Skip, refresh and duplicate submissions.

The existing registration Terms/Privacy links are placeholders, so no invented
privacy-policy link was added. Supply a real published route if one should be
linked from consent. Legacy eBills Email is only 50 characters and names use utf8
rather than utf8mb4; unsupported saved email/name values are rejected clearly,
never truncated or replaced. No enrollment-edit/cancel or contact synchronization
to existing enrollment is implemented. Backups contain private data: keep the
verified backup in protected storage outside the web root and retain it according
to PALECO policy; the OS temp directory is not a long-term backup archive.
