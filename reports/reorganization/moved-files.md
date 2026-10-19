# Moved implementations and resources

Original paths ending in PHP remain as compatibility shims. Six controllers additionally render app/views/pages/<same filename>.

| Original | Implementation/resource destination |
| --- | --- |
| includes/account-context.php | app/middleware/account-context.php |
| includes/account-email-footer.php | app/helpers/account-email-footer.php |
| includes/account-linking-form.php | app/views/partials/account-linking-form.php |
| includes/account-linking.php | app/services/account-linking.php |
| includes/account-notifications.php | app/services/account-notifications.php |
| includes/account-unlinking.php | app/services/account-unlinking.php |
| includes/billing-service.php | app/services/billing-service.php |
| includes/ebills-enrollment.php | app/services/ebills-enrollment.php |
| includes/ebills-step3.php | app/views/partials/ebills-step3.php |
| includes/ebills-ui.php | app/views/partials/ebills-ui.php |
| includes/education-playlist.php | app/services/education-playlist.php |
| includes/password-recovery.php | app/services/password-recovery.php |
| includes/portal-header.php | app/views/partials/portal-header.php |
| includes/portal-navigation.php | app/helpers/portal-navigation.php |
| includes/portal-sidebar.php | app/views/partials/portal-sidebar.php |
| includes/recovery-log.php | app/helpers/recovery-log.php |
| includes/session-action-dialogs.php | app/views/partials/session-action-dialogs.php |
| includes/soa-service.php | app/services/soa-service.php |
| config/auth.php | app/middleware/auth.php |
| config/mailer.php | app/services/mailer.php |
| index.php | app/controllers/index.php |
| register.php | app/controllers/register.php |
| dashboard.php | app/controllers/dashboard.php |
| forgot-password.php | app/controllers/forgot-password.php |
| reset-password.php | app/controllers/reset-password.php |
| consumer-education.php | app/controllers/consumer-education.php |
| google-login.php | app/controllers/google-login.php |
| google-callback.php | app/controllers/google-callback.php |
| logout.php | app/controllers/logout.php |
| test-email.php | app/controllers/test-email.php |
| soa.php | api/soa.php |
| migrations/20261016_account_link_verification.sql | database/migrations/20261016_account_link_verification.sql |
| migrations/20261016_account_notifications.sql | database/migrations/20261016_account_notifications.sql |
| migrations/20261016_ebills_profile.php | database/migrations/20261016_ebills_profile.php |
| migrations/20261016_ebills_profile_contact_DRAFT.sql | database/migrations/20261016_ebills_profile_contact_DRAFT.sql |
| migrations/20261016_ebills_profile_rollback.sql | database/migrations/20261016_ebills_profile_rollback.sql |
| dashboard.php.pre-recovery-20261009-133818.patch | storage/archive/dashboard.php.pre-recovery-20261009-133818.patch |
| dashboard.php.pre-recovery-20261009-133829.bak | storage/archive/dashboard.php.pre-recovery-20261009-133829.bak |
