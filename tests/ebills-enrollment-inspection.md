# eBills enrollment: inspected prerequisites and review decisions

Status: inspection completed; enrollment application changes and writes are
blocked pending a verified profile-name source/schema decision. No migration was
executed and no eBills enrollment was created, updated or deleted.

## Confirmed architecture

- `dashboard.php`: Linked Accounts cards, profile display, POST/CSRF handling,
  primary selection and legacy eBills synchronization.
- `includes/account-linking.php`: two-step verified linking, server challenges,
  match-any-record billing amounts and ten-minute expiry. Preserve these rules.
- `includes/account-linking-form.php`, `assets/js/account-linking.js`: shared
  linking view and in-place navigation; optional Step 3 would extend the existing
  committed-success transition, without rolling back a completed link.
- `includes/account-unlinking.php`, `assets/js/account-unlinking.js`: user-scoped
  archive and confirmation; enrollment must not be removed when unlinking.
- `includes/account-context.php`, `config/auth.php`: authenticated sessions,
  opaque linked-account tokens, output escaping and dashboard CSRF token.
- `config/database.php`: one PDO connection whose default schema is `mco_portal`;
  qualified operational queries access `palecxzp_pal_db` on the same server.
- `register.php` and `google-callback.php`: store a single user `name`; no
  existing structured-name/profile editor was found. Dashboard My Profile is
  currently display-only.
- Existing account-notification and recovery mail transports stay unchanged.
  SOA generation/sending remains outside this implementation.

## Actual profile schema: blocking identity mismatch

`mco_portal.users` contains:

| Column | Type |
|---|---|
| id | bigint unsigned, primary key |
| email | varchar(255), unique, required |
| google_id | varchar(255), nullable, unique |
| name | varchar(150), required |
| password_hash | varchar(255), required |
| email_verified_at | datetime, nullable |
| status | enum(active, suspended, inactive) |
| created_at / updated_at | datetime |

There are **no** first-name, last-name, middle-name, extension, contact, mobile or
phone columns. The supplied task requires first and last names taken from approved
profile values. A full `name` cannot be safely split into those fields: compound
surnames, multiple given names and extensions are ambiguous. Using account-holder
names, client-supplied read-only values or inferred name parts would violate the
identity-source rule. Changing only contact storage cannot resolve this.

### Concrete proposed profile design for approval

Either identify an existing authoritative user-profile source with structured
names, or approve user-confirmed structured fields on `mco_portal.users`:

| Proposed field | Proposed type | Enrollment requirement |
|---|---|---|
| first_name | varchar(25), nullable | Required before enrollment |
| last_name | varchar(25), nullable | Required before enrollment |
| middle_name | varchar(25), nullable | Optional |
| extension_name | varchar(25), nullable | Optional |
| contact_number | varchar(11), nullable | Required canonical 09XXXXXXXXX |

These are **proposals, not existing/approved mappings**. No name-field migration
has been created or executed. Keep `users.name` and current login/registration
semantics unchanged. Existing users would explicitly complete/confirm profile name
parts before enrollment; never automatically split their current full name.
The enrollment modal would display those saved values read-only and reread them
server-side on submission.

Per the task, the contact-only script has been prepared at
`migrations/20261016_ebills_profile_contact_DRAFT.sql`, **not executed**. It adds
only a nullable 11-character column, preserving existing users and workflows.
The task explicitly requires review before execution. Approving this script alone
would still leave first/last-name enrollment mappings unresolved.

## eBills schema and account mapping

`palecxzp_pal_db.ebills` is InnoDB, utf8/utf8_general_ci. Its existing primary key
is `AccCode VARCHAR(12)`. Approved data columns match the task: two nullable
datetimes; nullable FirstName/LastName/MiddleName/ExtensionName varchar(25);
Email varchar(50); ContactNumber varchar(12). No structural changes are needed.
Excluded attachment/OTP fields were inspected only as schema metadata; no excluded
field values were queried and none will be selected/changed by enrollment code.

The verified mapping is `paleco_accounts.AcctNo -> master.AcctNo`, followed by
`master.AcctCode -> ebills.AccCode`, as used by legacy synchronization. Account
codes must remain exactly as mapped, with no substitution of internal AcctNo.
An aggregate-only check found 4,205 current enrollments, 4,204 in the expected
hyphenated account-code format and one in another format. No identifiers or
enrollment personal details were exposed. Do not normalize, repair or overwrite
legacy rows as part of this task; check the actual mapped key and rely on its
primary key to prevent concurrent duplicate insertion.

Additional validation requirements derived from the schema:

- Portal email capacity (255) exceeds eBills Email capacity (50). Reject an
  overlength registered address clearly; never truncate it or use a different
  billing address. No email-field schema change is authorized.
- Enforce each name's 25-character maximum and the legacy utf8 character-set
  capacity before insertion; do not silently truncate or substitute characters.
- An enrollment-status failure must be displayed as unavailable, not unenrolled.
- The sole intended operational write is an explicit-column INSERT into ebills.
  Existing README/read-only guard conventions must be updated for precisely this
  newly authorized exception; other operational tables remain read-only.

## Transaction and consent findings

`users`, `paleco_accounts` and `ebills` all use InnoDB through the same PDO
connection. Cross-schema atomic transactions are supported. Lock the authenticated
user and active association, reread profile values, validate consent/mobile,
recheck enrollment and perform the approved INSERT plus profile contact update
in one transaction. Handle a competing primary-key insert as already enrolled,
without updating any existing enrollment or leaking its email address.

An existing `mco_portal.activity_logs` table provides a persistent audit mechanism:
user_id, action varchar(100), description text, optional IP/user-agent, created_at.
It has an InnoDB foreign key to users. There is no current code writing enrollment
consent there. It can record the enrollment action, actual account code, the task's
supplied consent wording/version and timestamp in the same transaction, without
creating an audit table. This reuses the available mechanism as authorized. No
consent has been collected or claimed as durably recorded during this inspection.

Registration's Terms/Privacy links currently use `href="#"`; they are not usable
policy documents. No real policy route was identified. Do not invent a policy
or represent those placeholders as a published notice.

## Approval/clarification needed

1. Identify the authoritative structured profile-name source, or approve the
   concrete nullable structured fields and user-confirmed profile editor above.
2. Review/approve the contact migration before it is executed; no automatic
   migration or profile backfill will be performed.
3. Optional: supply a genuine privacy URL if available. Existing activity_logs can
   be reused for versioned consent without additional table approval.

These decisions are required before secure enrollment insertion can be completed.
No production consent or enrollment workflow should be enabled using guessed names.

## Checks

Read-only schema/index/engine inspection and source inspection completed. The
existing PALECO read-only contract check passes with the draft migration present.
Functional eBills enrollment tests were not run because enrollment has not been
implemented/enabled pending the required profile decision. Existing linking,
unlinking, SOA, email and authentication code is untouched by this inspection.
