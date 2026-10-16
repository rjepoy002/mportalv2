# Reset and Sign Out confirmations

Modified files:

- `dashboard.php`: replaces the Security & Preferences reset link with a dialog
  trigger. Recovery remains the existing `forgot-password.php` workflow.
- `includes/portal-sidebar.php`: replaces the direct global logout link with a
  dialog trigger and includes the shared dialog on dashboard and education pages.
- `includes/session-action-dialogs.php` (new): native accessible dialog using
  existing rounded-panel, danger-button, light/dark and mobile layout conventions.
  Includes a session-bound dashboard CSRF token. Direct access returns 404.
- `assets/js/session-actions.js` (new): exact requested wording, Cancel/Escape,
  focus restoration, once-only confirmed POST, inline failure messages, fixed
  destination validation, and page-history refresh after session termination.
- `assets/js/portal-shell.js`: Escape leaves the background sidebar alone while
  a native dialog is open, preserving dialog focus/cancellation behavior.
- `logout.php`: retains session clearing, cookie expiry, destruction and login
  redirect, adding authenticated POST/CSRF validation. The previous endpoint had
  no CSRF check and signed out on GET. GET now returns 405 without session changes.
  Confirmed reset uses the same teardown before opening `forgot-password.php`.
  Enhanced submissions get JSON; standard POSTs retain normal redirects.
- `tests/session-actions-js-test.js` (new): dialog behavior and request tests.
- `tests/session-actions-http-test.php` (new): isolated HTTP/session tests.
- This report.

Only confirm submits. Cancel or idle Escape performs no request and does not
change authentication/session data. The dialog initially focuses Cancel, native
dialog behavior traps focus, and closing restores focus to the triggering button.
An in-flight request disables both buttons and blocks duplicate submissions.
Failures remain inline and re-enable controls without automatic retries. Server
redirect targets are restricted to `index.php` and `forgot-password.php`.

The existing recovery form, reset tokens, mailer, CSRF, rate limits and password
update logic were not modified. No database schema, mail settings, account
linking/unlinking or eBills/email functionality changed.

Executed results:

- Session confirmation JavaScript suite: passed for both actions, exact titles
  and button labels, Cancel, Escape, focus, one request, errors and destinations.
- Isolated HTTP suite: **22 assertions passed**. Tests seed synthetic sessions in
  a temporary directory and run a separate loopback PHP server. They verify GET
  rejection without session modification, CSRF/intent rejection without session
  modification, JSON and standard redirects for both actions, destroyed session
  files, expired cookies and rejection of replayed authenticated requests. No
  database, reset email or real consumer session is used.
- Dashboard and Consumer Education smoke checks: passed.
- PHP lint, JavaScript syntax, PALECO read-only contract and whitespace checks:
  passed.

Manual visual/accessibility checks remain in the browser: in both light and dark
themes, open each action on desktop/mobile, verify exact messages, tab containment,
Cancel/Escape and focus restoration. Confirm Sign Out returns to login; Confirm
Reset returns to the existing recovery form after signing out. Use browser network
tools to verify no request on Cancel and one POST on confirmation. A rejected CSRF
request should show an inline error while retaining the signed-in session.
