# Task 06L — Actual Localhost Sidebar Implementation

Date: 2026-10-21, Asia/Manila. **Implemented: New Connection / Coming Soon is now present in the actual shared sidebar.** It is disabled and has no destination. New Connection remains disabled; no authentication, collection or database behavior changed.

## 1. Actual files modified

- `app/helpers/portal-navigation.php`: added a permanently unavailable New Connection item immediately after Dashboard, without an `href`; added a plug SVG using the existing stroke/icon wrapper.
- `app/views/partials/portal-sidebar.php`: added a disabled-item branch with visible label/secondary status, `role="link"`, `aria-disabled="true"`, accessible name and tooltip. Existing active-link rendering remains unchanged.

No CSS/JavaScript/dependency changes were needed. Only these two production files changed. Before/after SHA-256 manifests of `app`, `config` and `database/migrations` confirm all other files retain their pre-task bytes, including authentication, Google, dashboard, profile/account/eBills/session code, feature configuration and migration guards.

Deliverable artifacts under this test directory: this report, four sidebar screenshots and `localhost-sidebar-browser-results.json`. Temporary rendering/browser/adapted-test scripts were removed; no new preview route was installed.

## 2. Before and after

Before: Dashboard, My Bills, Account & Settings and Consumer Education were the four sidebar links.

After: New Connection appears after Dashboard with **Coming Soon** below it and a plug icon. The original four links retain their exact labels, targets, ordering relative to one another and active-page behavior. Profile, theme and sign-out controls are unchanged.

The disabled row has no `href`, anchor, click handler, form, tabindex or active-page marker. Clicking does nothing; keyboard Tab skips it. It retains the accessible “New Connection, Coming Soon” name and tooltip when collapsed. Disabled presentation does not rely on JavaScript or a feature-flag value, so enabling an environment variable accidentally would not turn this row into a launch link.

Rendering introduces no identity/account query or linked-electric-account condition. It follows the existing shared sidebar's visibility; existing authentication boundaries are unchanged.

## 3. Screenshots and safe-access limits

Opened and inspected:

- [Desktop light](localhost-sidebar-desktop-light.png)
- [Desktop dark](localhost-sidebar-desktop-dark.png)
- [Mobile light](localhost-sidebar-mobile-light.png)
- [Mobile dark](localhost-sidebar-mobile-dark.png)

These show the **actual modified PHP partials**, rendered directly with synthetic display variables in a temporary sidebar-only frame. CSS, scripts and logo are served from the actual `http://localhost/mportalv2/assets/` resources. No copied/overridden navigation definition is used.

They are **not authenticated screenshots of the live dashboard**. No designated synthetic live-portal session was available, and loading the actual dashboard can invoke account/eBills synchronization or notifications. That route was not accessed. The temporary frame is outside the application and was removed; no customer records, real accounts or actual session files were accessed. Browser interception permitted only the temporary static frame and actual static assets, blocking portal action routes.

The only direct actual application endpoint check was an unauthenticated GET to `http://localhost/mportalv2/new-connection.php`, which returned **404 / Page unavailable**. Its disabled source branch precedes session and database bootstrap.

## 4. Responsive and dark-mode results

Chrome 155.0.8059.85 passed **14 browser assertions**:

- Label/status, disabled semantics and plug SVG present; all four existing route targets unchanged.
- Click does not navigate; keyboard Tab goes from Dashboard to My Bills, skipping the disabled item.
- Item fits at 1440px light/dark, 390px light/dark and 320px light, without item/sidebar or page horizontal overflow.
- Existing mobile open/close behavior works; desktop collapse keeps the unavailable accessible name and fits the 72px sidebar.
- Actual theme control switches light/dark styling. Screenshots show the secondary status readable in both modes.
- No sidebar JavaScript runtime exceptions or portal-action requests during the synthetic rendering check.

This is Chrome emulation and a limited sidebar visual check, not physical-device, full assistive-technology or whole-dashboard business-flow acceptance.

## 5. Regression results

| Check | Result |
| --- | --- |
| PHP syntax on both modified files | PASS |
| Four original navigation targets and disabled metadata contract | PASS, direct PHP rendering checks |
| Sidebar browser checks | PASS, 14 |
| `tests/structure-test.php` | PASS, 194 |
| `tests/new-connection/local-assets-test.cjs` | PASS, 102; existing compiled CSS/icons/resources sufficient |
| `tests/paleco-readonly-contract.php` | PASS |
| Actual disabled endpoint | PASS, HTTP 404 |
| Original `foundation-test.php` | **Stops at its historical source-freeze assertion** requiring `portal-navigation.php` to match Git HEAD. The authorized 06L change necessarily violates that old requirement; this is not reported as a passing original suite |
| Temporary adapted foundation run | PASS, 41. Same functional HTTP/auth/feature/read-only/logout cases; only the obsolete navigation Git-HEAD assertion was replaced with an explicit disabled/no-href/Coming Soon contract. Other source guards remained. Existing test file was not edited |
| Protected source manifest / whitespace check | PASS; only the two authorized production files changed |

The old foundation source-freeze assertion should be revised in a separately scoped test-maintenance change to recognize the authorized navigation update while retaining meaningful regression checks. No production protection was relaxed and no test failure was hidden by changing the actual test file.

Existing account/profile/eBills operations were not executed against live data. Their source and navigation links remain unchanged. No actual database connection, migration, operational write, Google request or email delivery was used by the new sidebar checks; the adapted foundation run uses its existing SELECT-only synthetic PDO fixture.

## 6. Feature remains disabled

`config/new-connection.php` is unchanged: unset/empty/unexpected `MCO_NEW_CONNECTION_ENABLED` values remain false, with explicit `1` required to enable. The actual localhost route returned 404. No applicant endpoint, form, upload, workflow action or submission was enabled. The menu row cannot initiate an application even by mouse or keyboard.

## 7. Recommended next task

**Task 06M — Separately gated, read-only seven-stage localhost preview.** Reuse the existing portal shell and informational roadmap, with a clear development/unavailable banner, physical-signing explanation and no applicant fields/actions. Keep the application feature disabled; approve any preview-only route/configuration separately with strict localhost and active/verified-user restrictions, including zero-account users. Do not combine that preview with authentication-policy changes, claims deployment, migrations or application submission.

Stop after this sidebar implementation. No landing preview or authentication wiring was installed.
