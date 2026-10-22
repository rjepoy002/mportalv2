# Task 03 — Authentication and session hardening

Completed 20 October 2026 (Asia/Manila). New Connection remains disabled by default. No migrations or applicant forms were added. No real customer accounts, operational table writes, Google requests or live email were used during validation.

## Files created and modified

Created:

- `app/helpers/session-security.php`: shared session policy, authentication reset, CSRF, current-user and OAuth validation helpers.
- `app/services/auth-rate-limit.php`: persistent single-server authentication limiter.
- `tests/new-connection/auth-security-test.php`: isolated HTTP controller/session/OAuth fixtures.
- `tests/new-connection/auth-security-report.md`: this report.

Modified existing application files:

- `app/controllers/index.php`: shared session startup, CSRF gate, login limits, generic credential/status errors and safe handled query failures, centralized post-login regeneration.
- `app/controllers/register.php`: shared session startup, CSRF on registration/OTP/resend, persistent limits, scalar input guards and safe logging/error wording.
- `app/controllers/google-login.php`, `google-callback.php`: secure session startup, state issuance/validation, verified-email checks and safer linking/failure handling.
- `app/middleware/auth.php`: expiry and current active/verified identity validation; relative sign-in redirect fixes the hardcoded deployment-directory mismatch.
- `app/controllers/logout.php`: shared startup and matching secure/HttpOnly/Lax cookie deletion; confirmation and POST/CSRF contract preserved.
- `app/services/password-recovery.php`: shared session startup through existing recovery token helper; existing recovery limiter/mail/token logic unchanged.
- `app/controllers/reset-password.php`, `app/views/pages/reset-password.php`: session-bound recovery CSRF on password changes and scalar token/password handling; bearer reset token checks and atomic claim logic preserved.
- `app/views/pages/index.php`, `register.php`: hidden CSRF fields, including the OTP form.
- `assets/js/register.js`: resend AJAX includes the OTP form's CSRF token.
- `api/soa.php`: shared session startup; existing account authorization and SOA logic retained.
- `.gitignore`: excludes runtime authentication limiter files.

Modified prior foundation files:

- `app/controllers/new-connection.php`: shared session startup after its feature/method gates.
- `tests/new-connection/foundation-test.php`: includes the new helper in its fixture; narrows the source-preservation assertions to exclude authentication files intentionally changed in Task 03.

Modified tests:

- `tests/session-actions-http-test.php`: checks that rejected logout requests preserve business session state while permitting shared session timestamps to update.
- `tests/password-reset-test.php`: supplies valid CSRF for normal fixture submissions and tests invalid reset CSRF without consuming the reset token or changing the password.

The dashboard controller, account linking/unlinking, eBills, billing, profile services, mail transport/configuration, and existing navigation were not changed. Task 01/02 artifacts remain historical records; this report supersedes their authentication findings and older assertion counts.

## Session policy

Every application session startup found in active controllers/middleware/services/API now uses the shared helper. It enables strict mode, cookies-only session IDs and disables URL session IDs. Cookies are session-lifetime, host-only, path `/`, HttpOnly, SameSite=Lax, and Secure when the trusted web-server `HTTPS` indicator is `on` or `1`. Existing cookies are reissued with the policy too. Incoming forwarding headers are deliberately not trusted. Lax supports Google's top-level GET callback while CSRF tokens protect state-changing forms.

Server-side limits are 30 minutes idle and 12 hours absolute. At either boundary the helper clears all session contents, including authentication, selected accounts, pending registration, CSRF and OAuth state, and regenerates the ID. Session expiry does not depend on PHP garbage collection. Successful password/Google authentication regenerates the ID, clears old pre-authentication/account/token context and starts fresh lifetime timestamps. Existing sessions without timestamps receive a baseline on first use; they are not retroactively assigned an invented login time.

Protected portal pages now SELECT the current user by ID and require active status plus a nonempty verified-email timestamp on every request. Missing/disabled/unverified users lose authenticated session state and return to sign-in. Query failures deny access. The New Connection module retains its dedicated current-user SELECT and requires no electric account. Disabled New Connection requests still stop before session/database startup.

Logout remains accessible for authenticated users without a database status lookup, so users can terminate a session even during database trouble. The existing POST, confirmation-dialog and CSRF requirements remain. Account and enrollment authorization rules are unchanged.

Session policy follows the [PHP session security guidance](https://www.php.net/manual/en/session.security.ini.php). Actual TLS termination and production PHP settings still require deployment verification.

## CSRF and abuse protection

Login, registration, OTP verification and resend require a random session-bound token with constant-time comparison and scalar input checks. Missing/malformed/incorrect tokens receive 419 before database/bootstrap/mail activity in login/registration. Tokens are inserted into existing forms; resend AJAX sends the same token. Forgot-password retains its existing recovery CSRF. Password reset now includes that session's recovery CSRF in addition to the secret, expiring, single-use reset link token. Invalid reset CSRF leaves the password/reset token unchanged. Logout retains its existing dashboard token.

The new limiter uses an exclusive file lock, a persistent 15-minute window, lowercase/trimmed email keys and normalized direct-connection IP keys. Keys are hashed; raw email/IP values are not stored. It counts all attempts, including successful ones, and persists across browser sessions/PHP workers on the same host:

| Action | Per IP / 15 minutes | Per normalized email / 15 minutes |
|---|---:|---:|
| Password login | 50 | 10 |
| Registration / resend | 20 | 5 |
| OTP verification | 60 | 15 |

The existing OTP record's five-wrong-attempt limit, expiry and resend cooldown still apply. Separate verification limits avoid using up that allowance merely by requesting the first code. Limiter storage/capacity failures deny attempts with a generic 429 response. Default storage is under HTTP-denied `storage/auth-rate-limits/`; `MCO_AUTH_RATE_DIR` can point to an administrator-provisioned private directory. No database tables were introduced. Existing password-recovery throttling is unchanged.

Password login now uses the same error for nonexistent users, incorrect passwords, inactive/suspended status and unverified users. A dummy bcrypt verification runs for unknown identities. Registration no longer explicitly says that an email already exists; it gives a generic unsuccessful-registration message and directs users toward sign-in/recovery. This is not a claim of constant-time or fully indistinguishable registration behavior: successful registration still displays OTP progression. Making all signup outcomes indistinguishable requires a separate product/email-abuse design.

## OAuth changes and compatibility

Google authorization generates a cryptographically random state stored in the session with its creation time. Callback validation is constant-time, one-use and limited to ten minutes. Missing/mismatched/expired/cancelled callbacks are rejected before Google SDK/database loading. Opening a second Google sign-in replaces the first pending state; users must retry stale tabs. Callback responses use no-referrer policy.

Provider userinfo must contain a nonempty identity, valid email and strictly true `verified_email`. This is checked before portal identity queries or writes. Existing disabled accounts are rejected before linking changes; Google subject mismatches give a generic failure. Authentication success still regenerates the session and redirects to dashboard. Exception details, codes, tokens, recipient data and credentials are not included in new application logs.

One intentional compatibility restriction: automatic linking to an existing unlinked portal account is permitted only when Google is authoritative for that email (Gmail or verified hosted-domain userinfo). A verified third-party mailbox alone does not authorize automatic linking to an existing account. Already-linked third-party Google identities continue to sign in. Users in the blocked first-link case should use their existing portal credentials/recovery; a future explicit reauthenticated linking flow requires separate approval. No insecure linking fallback was added.

These checks follow Google's guidance on [state and email authority](https://developers.google.com/identity/openid-connect/openid-connect). Real provider redirects, tenant claims and production callback registration must still be verified in staging with designated noncustomer accounts.

## Validation results

| Check | Result |
|---|---|
| PHP syntax for 19 changed/new application/test PHP files | PASS; reset controller rechecked after scalar guards. |
| `php tests/new-connection/auth-security-test.php` | PASS — 42 assertions. |
| `php tests/new-connection/foundation-test.php` | PASS — 41 assertions; synthetic user without linked accounts still allowed; feature-off gate remains intact. |
| `php tests/password-reset-test.php` | PASS — PDO/mail doubles, existing real filesystem recovery limiter fixture; includes reset CSRF rejection. |
| `php tests/session-actions-http-test.php` | PASS — 22 assertions, synthetic sessions. |
| `php tests/structure-test.php` | PASS — 180 assertions. |
| `php tests/paleco-readonly-contract.php` | PASS. |
| `php tests/profile-mobile-test.php` | PASS — 135 assertions, PDO doubles, no live writes. |
| `php tests/account-linking-test.php` | PASS — pure input/configuration tests. |
| `php tests/ebills-terms-test.php` | PASS — 24 assertions. |
| `node tests/session-actions-js-test.js` | PASS — cancel/Escape/focus/once-only POST/errors/logout and reset destinations. |
| `node tests/account-linking-js-test.js` | PASS. |
| `node tests/ebills-enrollment-js-test.js` | PASS — 174 assertions. |
| `node --check assets/js/register.js`; `git diff --check` | PASS; Git reports only configured LF/CRLF normalization notices. |

The new HTTP suite copies actual controllers/helpers into a temporary tree with synthetic PDO, Google SDK/userinfo and mail transport. It tests login/registration CSRF rejection before DB/mail, valid registration and OTP completion, generic sign-in errors, login regeneration, current-user status/verification denial and success, persistent cross-session throttling, limiter failure denial, hashed limiter state, idle/absolute expiry, rejection of unknown session IDs, HttpOnly/Lax cookies, Secure behavior under a server-controlled HTTPS indicator, and refusal to trust forwarded-proto spoofing. OAuth checks include state rejection before external activity, replay/expiry/mismatch, unverified email denial before queries and absence of authenticated session, successful verified sign-in/regeneration, and no linking writes for inactive/untrusted first-link users. Temporary fixture state is removed.

HTTPS tests simulate the web server's trusted TLS indicator over a loopback HTTP test server. They validate emitted cookie attributes, not a real TLS handshake, browser cookie transport, reverse proxy or actual Google redirect. No browser automation/screenshots were available. Live credential configuration and SMTP were not exercised. Dashboard smoke tests that read real user rows and may run synchronization were deliberately not used.

## Remaining deployment requirements and limits

1. Terminate TLS correctly and make PHP see the true HTTPS state. If a trusted proxy terminates TLS, configure the origin/web server to set HTTPS safely after validating the proxy; do not enable client-controlled forwarding-header trust. Redirect public HTTP to HTTPS and review HSTS at deployment. Actual browser behavior needs staging validation.
2. Provision durable, private, writable limiter storage and monitor capacity/429s. The file lock is a single-host design, not a distributed limit. Multiple application hosts require a shared atomic limiter approved before rollout; do not silently bypass limits. Review baseline thresholds for shared-office/NAT usage and account-targeted denial-of-service risk.
3. Confirm private session storage and consistent settings across all PHP workers. Review 30-minute idle/12-hour absolute policy with PALECO and communicate that long-idle forms require reload/re-authentication. Immediate logout of all devices after password change is not implemented; without a session-version/credential-revocation design, other sessions remain until expiry or user-status change. That is a separate authorization/session lifecycle decision.
4. Configure production `display_errors=Off`, protected error logs, least-privilege DB grants and credential storage/rotation. Existing embedded database/SMTP/Google configuration was not read into reports, changed or migrated. Its ownership and secret provisioning need a deployment-approved change.
5. Web-server/proxy access logs can independently record OAuth codes/state or reset tokens in query strings. Redact/exclude sensitive query strings for callback/reset routes; application logging changes cannot enforce infrastructure logging policy.
6. Validate registered Google redirect URI, TLS and verified-domain behavior in staging. Explicit first-time linking for nonauthoritative third-party mailboxes needs a separately approved reauthenticated flow. Staff identity/MFA/roles are still future work.
7. Registration retains its existing email-verification storage model and OTP progression. Uniform signup outcomes, stronger distributed bot controls, concurrent OTP lifecycle transactions and fully verified recovery delivery policy remain separate work; no new schema was assumed or modified.
8. Existing DB bootstrap terminates with a generic message on initial connection failure; centralized bootstrap error/status handling is still inherited. No claim is made that every legacy endpoint has identical failure presentation.

## Recommended next phase

Run browser/staging acceptance for local HTTP and deployed HTTPS, Google sign-in, expiry prompts, registration/recovery and account/eBills regressions; confirm session/limiter/logging/secret configuration and the Google linking restriction with the deployment owner. Then obtain approval for the New Connection workflow/policy matrix and portal-owned schema design. Keep the feature disabled and defer all application migrations/forms until separately authorized.

Task 03 stops here.
