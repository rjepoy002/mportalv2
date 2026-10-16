# PALECO account unlinking

Changes are limited to `dashboard.php`, the new
`includes/account-unlinking.php` and `assets/js/account-unlinking.js`, the extended
`tests/account-linking-mysql-test.php`, the new `tests/account-unlinking-js-test.js`
and this report. No database migration is required.

The dashboard already stores each user/account association in
`mco_portal.paleco_accounts`, with an active/archived status and a unique
`(user_id, AcctNo)` key. Unlinking uses that existing archived state, keeps the
portal profile and other users' associations active, and never writes operational
PALECO tables. A remaining active account is promoted if no primary remains.

Each account card opens a native accessible confirmation dialog with the requested
wording, the same trimmed `master.AcctCode` displayed by the authenticated user's
linked-account card (including its stored hyphens),
Cancel and Confirm Unlink. The number is HTML-escaped in its data attribute and
inserted into the dialog with `textContent`. Internal `paleco_accounts.AcctNo`
remains unchanged for backend selection and unlinking and is not used as a
display fallback. Native dialog behavior
provides keyboard focus containment and Escape handling. Cancel does not submit.
Only Confirm submits a normal POST containing the existing CSRF token and opaque
account selection token. Buttons disable during submission and restore on browser
history navigation. No password, OTP or email action is added.

The server requires the authenticated user, valid CSRF and an owned association.
User-row locking serializes unlinking with linking and primary-account changes.
Database failures roll back. Repeated valid unlinks are harmless; expired selection
tokens show a refresh notice. Successful unlinking clears stale session selection,
invalidates tokens and any pending challenge for that association, and redirects
to refreshed account settings. The final-account state shows the requested
"No Linked PALECO Account" message and Link PALECO Account action.

Legacy eBills synchronization previously reactivated archived associations on
every dashboard request. It now skips existing archived associations, preserving
the explicit unlink choice. Importing missing legacy associations remains intact.
Manual relinking uses the existing two-step account/meter and bill verification.
No eBills enrollment, subscription, delivery or email settings were modified.

Billing and SOA helpers already enforce active portal ownership on each request;
archiving removes access through those helpers. Dashboard billing history is
loaded only for an active account selected from the user's refreshed links.
There is no separate eBills enrollment/access endpoint in this repository to
change. Official registrations, outstanding balances and source records stay
unchanged. Previously obtained documents are not erased by portal unlinking.

Verification:

- Isolated MySQL suite: 134 assertions passed, including unlinking one/final
  account, foreign-user/authentication/CSRF rejection, shared-account preservation,
  primary promotion, repeat requests, stale challenge removal, denied billing/SOA
  access, verified relinking, unchanged billing fixtures and database rollback.
- Confirmation JavaScript tests: passed (open/cancel/confirm, duplicate submission,
  Escape during submission and back/forward restoration).
- Existing linking input, JavaScript, dashboard, billing-summary, SOA and PALECO
  read-only contract checks: passed.
- PHP lint, JavaScript syntax and `git diff --check`: passed.

Manual check: sign in, open Account & Settings, choose Unlink Account on a card,
and verify the full stored account number. Cancel should leave the list unchanged. Confirm
should remove that card and select a remaining account, or show the empty state.
Refresh again to confirm legacy synchronization does not restore the card. Test
relinking with the existing account/meter and statement verification. Browser
visual and keyboard testing remains a manual check; no real consumer link was
removed by the automated suite. `config/mail.php` was not modified.
