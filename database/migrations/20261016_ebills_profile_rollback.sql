-- MANUAL ROLLBACK ONLY. Not executed by the portal or migration runner.
-- Stop profile/enrollment/registration writes, back up the CURRENT users table
-- and export any new structured profile values before running this destructive
-- rollback. Restore the previous application release first. Other user columns,
-- full names, authentication and enrollment records are preserved.
ALTER TABLE mco_portal.users
    DROP COLUMN first_name,
    DROP COLUMN last_name,
    DROP COLUMN middle_name,
    DROP COLUMN extension_name,
    DROP COLUMN contact_number;
