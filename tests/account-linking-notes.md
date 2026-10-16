# Two-step PALECO account linking

## Local Continue-button fix — October 16, 2026

The unavailable notice and disabled Continue control were rendered by PHP because
`config/account-linking.php` required an enabled production flag and a nonempty
policy approval reference. Neither was configured. JavaScript only disables
controls during submission and did not cause the initial disabled state.
The migration had also not been applied, so enabling only the flag would still
have failed when accessing the challenge and rate-limit tables.

For this authorized local testing session, the existing migration has now been
applied to **mco_portal only**. No operational tables or existing account links
were changed. The local Apache `.htaccess` sets `MCO_APP_ENV=development` and
`PALECO_LINK_LOCAL_TESTING=1`. That machine-specific file is ignored by Git and
must not be deployed. Apache honors it immediately without a restart.

The local opt-in enables the same shared configuration used by both the dashboard
and linking service only when the environment is local/development/test **and**
both real `SERVER_ADDR` and `REMOTE_ADDR` are loopback (`127.0.0.1` or `::1`).
Host and forwarded headers cannot enable it. An explicit `APP_ENV=production`
overrides the development environment and prevents local activation. Production
still requires `PALECO_LINK_BILLING_ENABLED=1` plus a recorded policy approval.
Neither production setting was enabled by this fix. All verification requirements
and attempt limits remain enforced. Disabled UI controls now say "Linking
unavailable" with an accessible explanation rather than an unusable Continue.

A temporary loopback-only HTTP diagnostic confirmed Apache's actual runtime
configuration, persistent PHP sessions, read access to `master` and `billhistory`,
and the presence of both portal security tables. It was removed afterward.
Step 1 remains an authenticated normal form POST to `dashboard.php`, carrying
`action=add` and the existing CSRF token; it is not an AJAX request. Unauthenticated
dashboard access still returns a login redirect. No real customer account was
linked for testing. Controlled linking tests use the isolated fixture database.

Reverification passed: 114 MySQL assertions, configuration/input tests (including
production, remote-address and spoofed-header rejection), JavaScript submission
tests, dashboard smoke and PALECO read-only checks. To test manually, refresh
`http://localhost/mportalv2/dashboard.php?page=account-settings` while signed in,
then complete both steps with an authorized account and statement. LAN access
does not qualify for the local opt-in. To turn local testing off, remove the
local `PALECO_LINK_LOCAL_TESTING` setting or set it to `0`.

The earlier implementation report below describes the original disabled delivery
state; this section supersedes its statements that the application migration has
not been applied or that local verification remains disabled.

## Files changed

| File | Purpose |
|---|---|
| `dashboard.php` | Routes linking actions and renders the two verification steps |
| `includes/account-linking.php` | Exact amount validation, challenges, throttling and transactional links |
| `config/account-linking.php` (new) | Disabled-by-default policy gate and security settings |
| `assets/js/account-linking.js` (new) | Loading states, duplicate submission protection and browser navigation restoration |
| `migrations/20261016_account_link_verification.sql` (new) | Two portal-owned security tables |
| `.env.example` | Server environment configuration examples |
| `README.md` | Updated writable-table inventory and setup-document link |
| `tests/account-linking-test.php` | Normalization and configuration tests; replaces obsolete baseline expectations |
| `tests/account-linking-mysql-test.php` (new) | Isolated functional/security and concurrent-process tests |
| `tests/account-linking-js-test.js` (new) | Submission-control behavior tests |
| `tests/paleco-readonly-contract.php` | Recognizes the new portal tables, idempotent DDL and upsert syntax |
| `tests/account-linking-notes.md` (new) | Implementation report, deployment and manual testing instructions |

## Verification results — October 16, 2026

- MySQL integration suite: **114 assertions passed**, including two concurrent
  PHP worker processes. The dedicated fixture tables were removed afterward.
- Input and configuration suite: passed.
- JavaScript submission-control suite: passed.
- Dashboard smoke check: passed with the feature disabled and no application
  migration installed.
- Billing-summary fixture check: passed.
- SOA service suite: passed.
- PALECO read-only contract: passed.
- PHP lint for every changed/new PHP file: passed.
- JavaScript syntax check and `git diff --check`: passed.

No failures remained in the relevant checks run. This was not a run of every
unrelated repository test. The baseline source-assertion and session-directory
issues are diagnosed separately below. No production/business approval was
recorded and the feature remains disabled. The migration was exercised only in
the dedicated test database, not installed in `mco_portal`.

## Implementation and policy

`dashboard.php` retains the account code, meter serial and optional Friendly Label
form. Its existing authenticated session and dashboard CSRF token protect every
linking action. `includes/account-linking.php` verifies the master mapping before
creating a ten-minute database challenge. Step 1 never writes an account link.

The next form shows up to three distinct valid `YYYYMM` periods in descending
order, including gaps. Only records with nonnegative, non-null `TotalBill` are
eligible. Fewer periods are supported; no usable history blocks online linking.

Step 2 accepts an exact match to **any** `billhistory.TotalBill` for the challenged
account and selected allowed period. This intentionally accepts historical bill
versions, potentially including cancelled/superseded versions whose status is
not represented locally. It does not identify the current official bill.

PHP validates and normalizes the entered currency into a canonical decimal
string, with at most 17 integer digits and two fractional digits. Comparison
uses MySQL DECIMAL equality. No float conversion or rounding occurs. Expected
amounts, transaction identifiers and revision metadata are never retrieved for
the pre-linking UI or included in its responses.

Challenges store the verified account number, normalized account code, meter,
label, permitted months, random identifier, timestamps and failure count. The
browser receives only the random identifier, month options and ordinary CSRF
token. Client account identifiers in Step 2 are ignored. Master identity is
rechecked immediately before the amount query. Back and new Step 1 submissions
invalidate previous challenges; success, expiry and five failures consume them.

Portal-user row locks serialize challenge changes and link creation. Persistent
rate rows serialize guesses across users sharing an account. The existing
`UNIQUE(user_id, AcctNo)` constraint prevents duplicate links. Different users
may independently link the same account. Existing links, label editing, account
navigation, legacy synchronization, billing display and mail settings remain
unchanged. An archived link can be restored only after both verification steps.

## Migration and activation

The migration is **provided, not automatically applied to `mco_portal`**:

`migrations/20261016_account_link_verification.sql`

It creates only `mco_portal.account_link_challenges` and
`mco_portal.account_link_limits`. It adds no operational-table indexes or
constraints and does not change existing links. Both new tables use InnoDB.
The challenge foreign key requires the existing `users.id` BIGINT UNSIGNED key.

Apply the script with the local mysql client or phpMyAdmin, then grant the
application CRUD permissions on these two portal tables. Keep SELECT-only access
to operational tables. If the feature is enabled without the migration, linking
fails closed with a generic support message.

The feature defaults to disabled in **all** environments. Before production
activation, obtain PALECO approval that any historically issued SOA amount is
acceptable for account-linking verification. Record the approval externally and
configure both variables in the real PHP/Apache environment:

```apache
SetEnv PALECO_LINK_BILLING_ENABLED 1
SetEnv PALECO_LINK_POLICY_APPROVAL "APPROVED-TICKET-OR-DOCUMENT-REFERENCE"
```

Restart Apache after configuration changes. `.env.example` is documentation;
this application has no dotenv loader. A nonempty reference records the operator's
assertion of approval; the application cannot verify the external document.
Set `PALECO_LINK_BILLING_ENABLED=0` to disable both verification stages immediately.
There is no account/meter-only fallback or browser override. Existing links remain
usable while the feature is disabled. Unexpired challenges remain in storage;
they still require the enabled policy and a correct amount before linking.

Settings in `config/account-linking.php` default to:

| Setting | Default |
|---|---|
| Challenge lifetime | 600 seconds |
| Failures per challenge | 5 |
| Billing failures per user and per account | 5 per 900 seconds |
| Step 1 submissions per user and per normalized account code | 20 per 900 seconds |
| Cooldown after a rate limit is exhausted | 900 seconds from exhaustion |

Challenge replacement and PHP session resets do not clear database rate limits.
Successful links do not reset shared counters. These conservative limits can
temporarily block other users verifying the same account. Fixed audit events
include portal user IDs, without amounts, meter serials, account codes or tokens.
Protect the PHP server log accordingly. Old rate rows are reused. Expired
challenges cannot be used, but inactive users' expired rows remain in storage.
Deployments should schedule retention for expired challenges and stale rate rows
outside the online request path, while preserving active cooldowns. No cleanup
job is installed in this phase.

## Tests

Run from `C:\xampp\htdocs\mportalv2` in PowerShell:

```powershell
& C:\xampp\php\php.exe tests/account-linking-test.php
node tests/account-linking-js-test.js
& C:\xampp\php\php.exe tests/paleco-readonly-contract.php
& C:\xampp\php\php.exe tests/dashboard-smoke.php
& C:\xampp\php\php.exe tests/billing-summary-test.php
& C:\xampp\php\php.exe tests/soa-service-test.php
```

The MySQL suite is explicitly isolated from application data. Create an empty
dedicated database (or use the empty database left by a previous successful run):

```powershell
& C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS mportal_account_linking_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
$env:ACCOUNT_LINK_TEST_DSN = 'mysql:host=127.0.0.1;dbname=mportal_account_linking_test;charset=utf8mb4'
& C:\xampp\php\php.exe tests/account-linking-mysql-test.php
```

Optional credentials: `ACCOUNT_LINK_TEST_USER`, `ACCOUNT_LINK_TEST_PASSWORD`.
The suite refuses any other database name or a nonempty fixture database. A test
PDO subclass redirects **both** portal and operational query prefixes to that
database. Fixtures contain synthetic data only; the migration is exercised there.
All fixture tables are removed in `finally`; the empty test database remains.
Two independent PHP worker processes test simultaneous challenge submissions.

The previous account-linking suite asserted a development-only disabled throttle
and a cross-user linking ban. Those expectations conflict with this task and were
replaced with input/configuration checks plus executable MySQL behavior tests.
Its old source assertion also depended on an exact newline sequence and failed
on the checked-out CRLF file. The old `session_start()` used XAMPP's unwritable
default `C:\xampp\tmp` in this sandbox. The pure helper suite needs no session,
so that unnecessary call was removed. Production session settings were not changed.

## Manual XAMPP checks

1. With the default flag, sign in and open
   `http://localhost/mportalv2/dashboard.php?page=account-settings`. Existing
   accounts and labels should work; new linking displays an unavailable notice.
   A forged POST cannot bypass the disabled policy.
2. After the approval and migration steps above, use an approved test account.
   Enter its PALECO **Account Code**, matching meter serial and a Friendly Label.
   Continue should show Step 2 without adding a linked account.
3. Confirm that month options are distinct, descending, and limited to three.
   No amounts or transaction/revision information should appear in HTML or the
   network responses. No verification data is stored in browser local storage.
4. Enter a wrong amount, then the exact printed total for one displayed period.
   Failure must be generic. Success should refresh the account list and retain
   the label. A second portal user may independently verify the same account.
5. Check Back, challenge expiry after ten minutes, browser back navigation and
   double clicks. A successful challenge must not work again. Five failures must
   require a cooldown even after restarting Step 1 or signing in again.
6. Check an account with one/two periods and one without eligible history. Missing
   history must never allow linking. Use the synthetic suite for controlled
   duplicate versions, malformed data and concurrency; do not edit real bills.
7. Recheck billing history, SOA access, account switching, Friendly Label edits
   and any existing unlink mechanism. No new unlink or eBills workflow is added.

No PALECO operational data was modified by implementation or automated tests.
Browser visual/keyboard testing is a manual deployment check; the automated suite
checks rendered dashboard markup and submission-control behavior.
