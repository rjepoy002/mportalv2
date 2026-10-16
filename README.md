# MCO Portal

## Database ownership boundary

The PALECO operational tables, including `master`, `member`, `billhistory`,
`arledger`, and all other consumer or billing tables, are read-only to this
application. Account verification and billing display may use `SELECT` queries
against them. Never run `INSERT`, `UPDATE`, `DELETE`, or schema changes against
those tables from portal code or portal migrations.

Approved exception: eBills enrollment may INSERT a new `palecxzp_pal_db.ebills`
record using only its nine approved enrollment columns. No update, delete,
replacement, schema change or attachment/OTP access is permitted. All other
operational tables remain read-only.

Portal-owned state belongs in dedicated portal tables. Current writable tables
are `users`, `email_verifications`, `password_resets`, `paleco_accounts`,
`account_link_challenges`, `account_link_limits`, and `account_notifications`.
Account links, friendly labels, and primary-account choices live in
`paleco_accounts`; they must not be copied back to `master`.

Run `php tests/paleco-readonly-contract.php` when changing database queries.
In deployments, grant the portal's database credentials only `SELECT` on PALECO
operational tables and the required write privileges on portal-owned tables.

Account-linking setup, approval requirements, migration, test results and local
verification steps are documented in [tests/account-linking-notes.md](tests/account-linking-notes.md).
