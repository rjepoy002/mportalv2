# Task 06T — Manual read-only Stage 1 review

2026-10-21. Temporary isolated Apache review is available. Actual portal collection and customer authentication remain unchanged.

## Startup and review URLs

From `C:\xampp\htdocs\mportalv2`, with local XAMPP MariaDB running:

```powershell
& C:\xampp\php\php.exe tests/new-connection/stage1-manual-review-launcher.php --start
```

The launcher requires `--start`; it is CLI-only. It prints five exact random URLs on a newly allocated `http://127.0.0.1:<port>/` Apache listener:

| Printed URL label | Expected behavior |
|---|---|
| `active` | All five read-only field sections; active/verified synthetic user with no linked accounts |
| `inactive` | HTTP 403 |
| `unverified` | HTTP 403 |
| `expired` | Expired session rejected; fixed relative `index.php` redirect |
| `anonymous` | Fixed relative `index.php` redirect |

Open the printed **active** URL in a browser; use developer responsive tools to review desktop/mobile layouts and the Appearance control for themes. No password or customer login is needed. Private, pre-created fixture sessions are assigned by fixed generated wrappers, not by browser-supplied user IDs. URL names are random per launch; do not publish/share them. They provide fixture session access to local machine users who know them.

Expired/anonymous redirects deliberately do not provide a login page: the isolated server has no customer or synthetic login controller. Its `index.php` destination returns 404. The expired URL cannot restore an authenticated session after expiration; restart the disposable review to recreate test cases. Active session idle/absolute expiry uses the existing helper. No reauthentication or return claim mechanism is deployed.

## Reuse and isolation

Added only `stage1-manual-review-launcher.php`, `stage1-manual-review-browser.cjs`, browser result JSON, four screenshot PNGs and this report under `tests/new-connection/`. No existing application/configuration files changed in this task.

The launcher copies the Task 06S controller/renderer, prototype/contract, independent gate, repository seam and actual session-security helpers. It does not implement another Stage 1 form. The private bootstrap explicitly authorizes development display only and supplies a repository bound to a new guarded `mco_nc_test_<16 hex>` schema. Exactly one users table and three synthetic identities are created. No application or linked-account tables exist. No real portal database/mail configuration is copied.

Generated Apache configuration, identity mapping, session IDs/files, schema context and error log remain in a private directory **outside** the public document root. Public PHP wrappers contain only a require of the private bootstrap; assets are copied from an extension allowlist. Apache denies other directories, directory listing is off, and PHP is enabled only inside the temporary public directory. The database helper uses only existing local disposable-test credentials/environment, never customer configuration. No generated credential/context file is exposed over HTTP.

The server binds only IPv4 loopback. The bootstrap checks exact generated Host and peer, rejects mutation methods before session setup, and invokes the existing independent local display gate. The renderer removes forms, inputs, save/submit buttons, editor context and mutation scripts server-side. No persistence/review/upload/API endpoints are installed. Private responses remain no-store; referrer policy prevents the random review URL being sent as a referrer. No Google/SMTP, dashboard or operational query is involved.

## Shutdown and cleanup

Type **q then Enter** in the launcher console; EOF also stops it. Normal shutdown terminates the separate Apache process, checks that only three synthetic users and one table exist, drops that exact disposable schema and removes the validated system-temp child directory. The launcher prints the exact schema and directory for recovery and confirms `Disposable review schema removed.` Ports/URLs are invalid after shutdown. Automated acceptance leaves no server running.

Do not force-close/kill the console. Power loss or forced termination can bypass PHP cleanup. If interrupted, use the printed identifiers to locate only that fixture's Apache process; verify the schema matches `mco_nc_test_<16 hex>` and the resolved directory is a system-temp child named `mco-readonly-<16 hex>` before removal. Never delete an unverified computed path or drop `mco_portal`. If database connectivity fails during cleanup, preserve the identifiers for later verified cleanup. This launcher has no automatic lifetime limit; stop it explicitly after review.

## Acceptance results

Windows/XAMPP Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32, Chrome 155.

| Test | Result |
|---|---|
| Explicit launcher startup and immediate q shutdown | PASS |
| Live Apache browser/HTTP harness | PASS 20 checks plus cleanup verification: all five session URLs, mutation rejection, private context 404, five sections, no editable form controls, desktop/mobile themes, no horizontal overflow, mobile sidebar, no runtime/external/customer requests |
| Normal cleanup | PASS: child process exit and schema-removal confirmation; launcher verifies identity-only schema before dropping it |
| Existing isolated read-only Apache suite | PASS 97 assertions; attested SQL identities removed |
| Foundation/navigation/default-off | PASS 47 assertions |
| Authentication security | PASS 42 assertions |
| Structure | PASS 207 assertions |
| PALECO read-only contract | PASS |
| Launcher PHP syntax | PASS |
| Actual port-80 display route | 404, still disabled |

The browser harness uses the **actual temporary Apache URLs**, not an exported/static HTML rendering. Desktop 1440px and mobile 390px light/dark plus 320px light were checked. The live browser suite uses active review navigation; negative session cases use HTTP requests to the same Apache instance. Actual authenticated customer port-80 acceptance remains untested. No customer account was used or created.

One harness cleanup-reporting variable was initially scoped incorrectly; it was corrected and the test rerun successfully. No security behavior was changed to make a test pass.

## Screenshots and limitations

Fresh live isolated-Apache screenshots:

- [Desktop light](stage1-manual-review-desktop-light.png)
- [Desktop dark](stage1-manual-review-desktop-dark.png)
- [Mobile light](stage1-manual-review-mobile-light.png)
- [Mobile dark](stage1-manual-review-mobile-dark.png)

Desktop light and mobile dark were opened and visually inspected. All five sections use existing styling and ordinary vertical scrolling. Some inherited wording describes entering synthetic details even though this mode displays placeholders only; the prominent read-only notice takes precedence. Header still says Stage 1 prototype. These are presentation issues for owner review, not collection controls.

Sidebar layout/theme/mobile controls are reviewable. Dashboard, profile, eBills, consumer-education and logout workflows are not installed in the isolated server; their relative links either have no destination or their dialog action is unavailable. They cannot reach actual portal business routes because the origin is the separate temporary port. New Connection retains Coming Soon in this copy. No actual sidebar/navigation was changed. Screen-reader acceptance, 320px dark mode and remote/staging HTTPS are outside this test coverage.

## Approvals and next step

Official fields, forms, catalogs, notices/legal basis, representative rules, retention and physical signing/payment remain pending owner approval. Synthetic acceptance grants no production policy approval. Recommended next step: owner manual review of this display and recorded field/policy decisions, before proposing any editable synthetic staging milestone. No real drafts, migrations, submission endpoints or collection are authorized. Stop here.
