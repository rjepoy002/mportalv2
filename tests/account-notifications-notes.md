# PALECO account link/unlink notifications

## Official email branding update

All active customer-facing email templates now use **PALECO MCO Portal**. Account
notification bodies, header, security notice and shared automated footer were
updated, along with the forgot-password subject, HTML/plain bodies and sign-off.
Registration's alternate expanded portal name was standardized too; its subjects,
plain-text versions and sign-offs already used the official name. The diagnostic
test-email template also already used it and needed no change.

The footer follows the latest explicit requested wording, superseding the earlier
footer discussion below:

> This is an automated notification from PALECO MCO Portal. Please do not reply to this email, as this mailbox is not monitored.

The security notice, linked intro/service sentence and unlinked profile wording
match the latest supplied copy. Account details, codes, Philippine timestamps,
URLs and mail behavior are unchanged. Lowercase `mportalv2` in functional URLs is
intentionally preserved; no routes, namespaces, directories, database objects or
configuration keys were renamed. Web-page branding outside email templates was
not changed.

Files modified for this update: `includes/account-notifications.php`,
`includes/account-email-footer.php`, `forgot-password.php`, `register.php`,
`tests/account-notification-template-test.php`, `tests/account-linking-mysql-test.php`,
`tests/password-reset-test.php`, and this report.

Verification passed: **37 template assertions**, **165 isolated MySQL assertions**,
the real mailer wrapper with fake transports, and the password-recovery controller
regression suite with PDO/mail doubles. HTML/plain output checks cover exact
branding/copy, account-code privacy, timestamp conversion, security/footer text,
safe recovery URLs and escaping. Recovery checks also verify the newly branded
subject, intact reset links/tokens, rate limits and failure behavior. A source scan
found no customer-visible legacy branding in the active email template blocks.
PHP syntax and whitespace checks passed. No real emails were sent by tests. No
migration, SMTP change or new deployment setting is required.

## Updated subjects, security notice and automated footer

The current template uses the requested exact subjects and headings:

| Event | Subject | Heading |
|---|---|---|
| Link | PALECO Account Successfully Linked | Account Linked Successfully |
| Unlink | PALECO Account Unlinked | Account Unlinked Successfully |

Both messages use "Hello," and present the account code, saved display name,
actual Asia/Manila event timestamp and Account Linked/Account Unlinked activity.
The event-specific introductory and service-access text follows the supplied copy.

The Security Notice advises immediate password reset through Forgot Password
and review of linked accounts. Its HTML link uses `app_url('forgot-password.php')`
from the existing `config/app.php` validation, with an escaped URL. Production
requires the configured HTTPS `APP_URL`; no Host-derived URL or reset token is
created. Plain text contains the same notice and the full recovery-request URL.
URL/configuration errors are caught as notification delivery failures after commit,
so they do not undo a successful account operation.

`includes/account-email-footer.php` is a new shared footer for these two account
emails only. Password-reset and other email footers remain unchanged. The user
clarified that PALECO staff access this sending mailbox but it is not an inquiry
channel. Accordingly, the final footer accurately says:

> This is an automated notification from PALECO MPortalV2. Please do not reply to this email, as this mailbox is used for sending notifications and does not handle inquiries.

It is headed "Automated Email Notification" in both HTML and plain text, separately
from Security Notice. The earlier requested "mailbox is not monitored" claim is
intentionally not used following that clarification.

Files changed for this wording update: `includes/account-notifications.php`,
`includes/account-email-footer.php` (new),
`tests/account-notification-template-test.php` (new),
`tests/account-linking-mysql-test.php`, and this report.

Reverification: 34 template assertions, 165 isolated MySQL assertions, and the
existing fake-transport mailer suite passed. Template checks include both copies,
subjects/headings, account-code privacy, timestamp conversion, safe recovery URLs,
HTML escaping and rejection of malformed public base URLs. Existing dashboard,
billing/SOA, linking/unlinking UI and read-only checks also passed. No real email
was sent by tests. No new migration or SMTP change is required; supply `APP_URL`
in the real PHP/Apache environment on production, as required by the existing
application configuration (editing `.env.example` alone does not configure PHP).

## Changed files

- `includes/account-notifications.php` (new): transaction-scoped event recording,
  branded HTML/plain-text templates, and post-commit at-most-once dispatch through
  the established `config/mailer.php::sendEmail()` transport.
- `includes/account-linking.php`: records only successful permanent transitions
  and dispatches after commit; no notification for Step 1, failed verification,
  duplicate active links or replayed challenges.
- `includes/account-unlinking.php`: records the successful active-to-archived
  transition and dispatches after commit. Repeated unlinks do not create events.
- `dashboard.php`: newly imported legacy eBills associations also record link
  events in their existing transaction and dispatch after commit. Existing or
  archived associations are skipped; no subscription/email-delivery rules change.
- `migrations/20261016_account_notifications.sql` (new): portal-owned event table.
- `jobs/send-account-notifications.php` (new): CLI recovery for pending events.
- `tests/account-linking-mysql-test.php`: extended isolated behavioral tests;
  all test transports are fake, including concurrent worker processes.
- `tests/account-notification-mailer-test.php` (new): exercises real `sendEmail`
  and PHPMailer setup with fake transport outcomes, without network access.
- `tests/paleco-readonly-contract.php`, `README.md`: writable-table inventory.
- This report.

## Recipient and content

Recipients are selected from `mco_portal.users.email/name` using the authenticated
user ID inside the association transaction. No recipient comes from request input,
session email, `master` billing contacts or eBills registrations. An unusable
registered address records a failed notification without preventing linking.

Content preserves the same trimmed `master.AcctCode` (including hyphens) and
Friendly Label or uppercase consumer-name fallback used by linked-account cards.
Missing codes use the UI's unavailable text, never `AcctNo`. All dynamic HTML is
escaped. Messages contain the event, Asia/Manila timestamp, and advice to reset
the portal password and review linked accounts if the action was unauthorized.
No passwords, tokens, meter serials, amounts or internal
account identifiers appear. The internal event correlation ID is not email content.

The template uses the established simple inline HTML/UTF-8/plain-text conventions,
with PALECO branding and a green header. `config/mail.php`, credentials, SMTP setup,
password recovery and its templates were not modified.

## Transactions, delivery and idempotency

`account_notifications` is inserted in the same transaction as the actual link
transition, so rollback removes the event. Existing user-row locks and association
state checks prevent request retries from creating another event. Relinking after
a genuine unlink is a new transition and correctly generates another notification.

After commit, an atomic `pending -> sending` update claims the event. Only the
worker winning that update invokes `sendEmail`; repeated dispatches do nothing.
The event becomes `accepted` only when that transport returns true; this means
transport acceptance, not guaranteed inbox delivery. False returns, invalid
registered addresses and exceptions become `failed`. A state-write failure is
logged without changing the committed account result. UI success messages describe
only linking/unlinking and never claim an email was sent.

Logs contain fixed status/event correlation fields; no addresses, codes, content,
SMTP responses, credentials or exception text are logged. Existing mailer logging
also suppresses private transport diagnostics.

SMTP has no transactional exactly-once guarantee. A crash after claiming or an
ambiguous acceptance can leave an event `sending`. Failed/sending events are never
automatically retried, avoiding duplicate notifications. Operators should inspect
transport records before any deliberate recovery. Pending events after a crash
between commit and dispatch can be processed with:

```powershell
& C:\xampp\php\php.exe jobs/send-account-notifications.php
```

The CLI job handles up to 100 pending events per run and can be scheduled by the
deployment operator. No scheduler was installed and no real email was sent during
testing. Delivery occurs synchronously after commit in normal web requests and
may add the existing mailer's transport timeout to response latency.

## Database/deployment

The migration was applied to the local XAMPP `mco_portal` database. Apply it to
other portal deployments before deploying these controller changes. It creates
only `mco_portal.account_notifications`; no existing account associations or
operational PALECO data are changed, and no historical events are backfilled.
Event rows contain recipient/contact snapshots: restrict access and establish
retention appropriate to deployment policy. Transactional event-storage failures
roll back the database action; post-commit delivery failures never do.

## Executed tests

- Isolated MySQL suite: **165 assertions passed**, including success, failed and
  cancelled verification, repeated links/unlinks, shared-account recipients,
  concurrent submissions, pre/post-commit behavior, rollback removal, exact UI
  code/label display, HTML escaping, Asia/Manila conversion, invalid registered
  address, rejected/throwing transports, secure logs and dispatch idempotency.
- Real `sendEmail()` wrapper with fake PHPMailer transports: passed for linked
  and unlinked HTML/plain bodies, correct registered recipient, acceptance,
  rejection, exceptions and private-log suppression; no network/email delivery.
- Dashboard, input/configuration, navigation, linking/unlinking submission controls,
  billing summary, SOA and read-only contract checks: passed.
- PHP/JavaScript syntax and whitespace checks: passed.

Manual delivery verification should use an approved test portal user and its own
registered mailbox. Complete both linking steps, confirm one correctly addressed
message, unlink and confirm one corresponding message. Verify failed verification,
Cancel and repeated requests do not send. No live consumer action or live SMTP
delivery was performed by the automated suites.
