# Task 06P — Interactive temporary Stage 1 preview

Date: 2026-10-21. Complete for synthetic local development; real collection remains disabled.

## Start and use

From `C:\xampp\htdocs\mportalv2`, with local MariaDB running:

```powershell
& C:\xampp\php\php.exe tests/new-connection/stage1-local-development-runner.php --start
```

The `--start` argument is mandatory; omission exits before setup. The CLI-only runner prints `http://127.0.0.1:<random-port>/login`, the exact disposable schema and temporary resource directory. Open that printed URL manually. Use `demo-a` / `fixture-only`; `other` / `fixture-only` is a separate synthetic owner. `demo-b`, `demo-c`, `inactive` and `unverified` are also fixed test identities. These are disposable fixture credentials, not portal accounts. No browser is launched automatically.

Create a test application, edit the five sections, save an incomplete valid draft, resume, complete the synthetic requirements, request review, select the four separate test acknowledgments and submit. Review is the saved snapshot, not unsaved form state. Invalid values produce field errors; stale versions conflict. The confirmation explicitly represents only a disposable test submission.

Every start gets new random ports, schema, session state and review keys. Data does not survive shutdown. No permanent Stage 1 route or link is installed in the actual portal.

## Shutdown and cleanup

Type **q then Enter** in the runner console. Closing stdin/EOF also stops it. The coordinator terminates both loopback servers, drops only its regex-validated random disposable schema and removes its validated temporary tree. It prints `Disposable HTTP schema removed.` Normal setup failures use the same finally cleanup. The runner is not left running after automated acceptance.

Do not use Task Manager termination, console close or power loss as a cleanup method: PHP finally cannot guarantee cleanup after forced termination. If interrupted, preserve the printed exact schema/directory, stop only the corresponding fixture processes, and have a developer verify those resources match `mco_nc_test_<16 hex>` and a system-temp child `mco-http-<16 hex>` before deleting them. Never select or drop `mco_portal`, and never delete a computed or unverified directory. If database cleanup fails during an outage, retain those exact identifiers for later cleanup. There is no automatic age-based database deletion, and no idle timeout for the runner itself; it lasts until explicitly stopped. Session expiry remains enforced independently.

## Files and mobile refinements

Added `stage1-local-development-runner.php`, CLI-only, and `stage1-local-development-runner-test.cjs`, plus this report.

Modified:

- `stage1-http-test.php`: explicit manual mode reuses the entire existing setup, server, migration and cleanup path. Automated acceptance remains the default; only its browser invocation/minimum submitted-count expectation is skipped during manual review. Database isolation and per-application invariants still run on graceful exit.
- `app/views/pages/new-connection-prototype.php`: prominent exact development warning and field-level provisional required indicators. Existing optional labels, conditional rules and identity distinction remain.
- `assets/css/new-connection-prototype.css`: compact horizontally scrollable mobile section row within the page width; conditional fields have a visible inset; native review summaries have keyboard-sized targets.
- `assets/js/new-connection-prototype.js` and `tests/new-connection/stage1-http-ui.js`: existing review groups use native `details`/`summary`, initially expanded, with Edit controls inside. Browser users may collapse them without altering content, proofs or consent state.
- `stage1-http-browser-test.js`: verifies all four review groups collapse/reopen at each viewport. Existing result JSON/screenshots regenerated.

No contract change: `stage1.v1-draft-20261020` and server validation remain authoritative. No second editor, persistence service or submission model was created.

## Acceptance results

Windows/XAMPP PHP 8.2.12, MariaDB 10.4.32, Chrome 155 headless. All test data synthetic.

| Check | Result |
|---|---|
| Explicit manual runner startup/shutdown | PASS: printed loopback URL, synthetic login HTML, private/config and source routes 404, anonymous editor 401, raw foreign Host 403, schema removal, listening server unavailable after exit |
| Missing `--start` | Expected refusal before setup |
| HTTP/browser suite | PASS 230 checks; four synthetic submissions, seven stages each, four consents/one event/one receipt; nonfixture schema signatures unchanged; schema removed |
| UI-only prototype suite | PASS 97 checks |
| Pure contract | PASS 42 assertions |
| Draft persistence | PASS 149 assertions |
| Atomic submission | PASS 99 assertions |
| Foundation/default-off/navigation | PASS 47 assertions |
| Authentication security | PASS 42 assertions |
| Structure | PASS 201 assertions |
| Read-only contract | PASS |
| Runner/coordinator syntax | PASS |

The full HTTP suite exercises the same router/UI/services copied by manual mode: synthetic login, draft recovery, invalid fields, active/verified eligibility, ownership denial, expired sessions, proof expiry, review refresh, concurrency, exact retries, changed-payload rejection, rate limiting and atomic submission. Manual-mode smoke testing covers lifecycle and access boundaries; full browser journeys run through the automated coordinator, not a manually operated browser session.

Browsers were tested at 1440, 390 and 320 CSS pixels in light/dark modes, including keyboard/error focus, no page horizontal overflow, section navigation, four acknowledgment controls and review collapsibility. No external/missing resources, JavaScript exceptions or applicant browser-storage persistence were reported. Native summary behavior is used; comprehensive screen-reader testing remains outstanding.

Initial smoke-test expectations incorrectly assumed anonymous access redirected and foreign Host used 404. Tests were corrected to match existing 401/403 denials without changing router behavior. Raw Node HTTP was used for the Host check because fetch did not transmit the intended Host override reliably.

## Screenshots

Opened and visually inspected fresh synthetic screenshots:

- [Desktop identity](stage1-http-screenshots/identity-1440-light.png)
- [Mobile dark identity](stage1-http-screenshots/identity-390-dark.png)
- [320px saved review](stage1-http-screenshots/review-320.png)

Mobile navigation occupies one scrolling row; required labels wrap, with no page overflow. Review groups are initially expanded for completeness, so the page can still be long until collapsed. The top provisional guidance also remains lengthy; future usability review can shorten it without changing requirements.

## Isolation and security

The runner reuses the guarded Task 05E fixture: no default database is selected by bootstrap; database names must match the random disposable pattern; service PDO guards reject operational identifiers and cross-schema queries. No actual portal configuration or mail credentials are copied. The local test connection is supplied through existing `MCO_NC_TEST_*` environment settings, with existing local-XAMPP test defaults; these are not production deployment credentials.

Both listeners bind `127.0.0.1` only; the router additionally checks loopback peer and exact generated Host, validates Origin/CSRF and methods/types/body limits, and serves only its allowlisted routes/assets. Private fixture files are never served. Session identity, current verification/status, ownership, optimistic versions, idempotency, review proofs, four test acknowledgments and transactional submission remain unchanged. Responses remain private/no-store. The fixture is intentionally accessible to other local machine users; do not forward its port or use it on an untrusted shared development machine.

No actual customer records, Google/SMTP, eBills/account workflow, claim infrastructure or operational database was accessed. No actual authentication, dashboard, profile, linking, eBills, feature flag or production migration guard was changed. The separate read-only preview remains intact; foundation tests verify the real application remains default-off. This task does not claim authenticated port-80 acceptance.

## Approvals and next step

Official forms, legal applicant/representative rules, field requiredness, locality/service catalogs, privacy basis/text, retention, declaration wording, correction rules and offline procedures remain unapproved. Synthetic publications confer no PALECO approval.

Recommended next step: owner-guided manual review using this temporary runner, recording usability and policy decisions before any controlled identity/persistence deployment proposal. Keep the real application disabled. Stop here; no live collection or submission is activated.
