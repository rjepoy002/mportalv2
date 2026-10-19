
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= dashboardEscape($nav[$page]) ?>
        | PALECO MCO Portal
    </title>

    <link
        rel="icon"
        href="assets/images/logo.png"
    >

    <script>
        (function () {
            const savedTheme = localStorage.getItem('mco-theme');
            const systemDark = window.matchMedia(
                '(prefers-color-scheme: dark)'
            ).matches;

            document.documentElement.classList.toggle(
                'dark',
                savedTheme === 'dark' || (!savedTheme && systemDark)
            );
        })();
    </script>


    <!--
        Development only.
        Requires internet access for Tailwind CDN.
    -->

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <script>

        tailwind.config = {

            darkMode: 'class',

            theme: {

                extend: {

                    colors: {

                        paleco: {

                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d'

                        }

                    },

                    boxShadow: {

                        card:
                            '0 1px 3px rgba(15,23,42,.06),' +
                            '0 1px 2px rgba(15,23,42,.04)'

                    }

                }

            }

        };

    </script>

    <link rel="stylesheet" href="assets/css/portal-shell.css">
    <script>
        (function () {
            try {
                if (localStorage.getItem('mco.sidebar.collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (error) {}
        })();
    </script>
</head>


<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">


<div class="min-h-screen">


    <?php
    $portalActivePage = $page;
    $portalUserDisplayName = $userDisplayName;
    $portalUserEmail = $userEmail;
    require __DIR__ . '/../partials/portal-sidebar.php';
    ?>
    <main class="portal-main">


        <?php
        $portalPageTitle = $nav[$page];
        require __DIR__ . '/../partials/portal-header.php';
        ?>
        <!-- Content -->

        <div
            class="
                mx-auto
                max-w-screen-2xl
                space-y-6
                p-4
                sm:p-6
                lg:p-8
                dark:[&_.bg-white]:bg-slate-900
                dark:[&_.bg-slate-50]:bg-slate-800/50
                dark:[&_.border-slate-100]:border-slate-800
                dark:[&_.border-slate-200]:border-slate-800
                dark:[&_.border-slate-300]:border-slate-700
                dark:[&_.divide-slate-100]:divide-slate-800
                dark:[&_.divide-slate-200]:divide-slate-800
                dark:[&_.text-slate-900]:text-slate-100
                dark:[&_.text-slate-700]:text-slate-200
                dark:[&_.text-slate-600]:text-slate-300
                dark:[&_.text-slate-500]:text-slate-400
                dark:[&_.text-slate-400]:text-slate-500
                dark:[&_.bg-paleco-50]:bg-paleco-900
                dark:[&_.text-paleco-700]:text-paleco-200
                dark:[&_input]:bg-slate-900
                dark:[&_input]:text-slate-100
                dark:[&_input]:placeholder:text-slate-500
                dark:[&_select]:border-slate-700
                dark:[&_select]:bg-slate-900
                dark:[&_select]:text-slate-100
                dark:[&_.hover\:bg-slate-50:hover]:bg-slate-800/50
            "
        >


            <!-- Flash -->

            <?php if ($flash): ?>

                <div
                    role="status"
                    class="
                        rounded-xl
                        border
                        px-4 py-3
                        text-sm
                        font-medium

                        <?= $flash['type'] === 'error'
                            ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                        ?>
                    "
                >
                    <?= dashboardEscape($flash['message']) ?>
                </div>

            <?php endif; ?>


            <?php if ($selectedAccount): ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-paleco-700 dark:text-paleco-200">Viewing Account</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <h2 class="min-w-0 break-words text-xl font-bold sm:text-2xl"><?= dashboardEscape(dashboardDisplayAccountName($selectedAccount)) ?></h2>
                        <?php if ($selectedAccount['is_primary']): ?><span class="rounded-full bg-paleco-50 px-2.5 py-1 text-xs font-bold text-paleco-700 dark:bg-paleco-900 dark:text-paleco-100">PRIMARY</span><?php endif; ?>
                        <?php if (count($linkedAccounts) > 1): ?>
                            <button type="button" data-switch-account-open class="ml-0 inline-flex items-center gap-2 rounded-xl border border-paleco-200 px-4 py-2 text-sm font-semibold text-paleco-700 transition hover:bg-paleco-50 focus:outline-none focus:ring-4 focus:ring-paleco-500/20 dark:border-paleco-800 dark:text-paleco-200 dark:hover:bg-paleco-900/40 sm:ml-auto" aria-haspopup="dialog" aria-controls="switch-account-dialog">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4"><path d="M7 7h11l-3-3M17 17H6l3 3"/><path d="M18 7a7 7 0 0 0-12-3M6 17a7 7 0 0 0 12 3"/></svg>Switch Account
                            </button>
                        <?php else: ?>
                            <button type="button" disabled aria-describedby="switch-account-unavailable" class="ml-0 cursor-not-allowed rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 dark:border-slate-700 sm:ml-auto">Switch Account</button>
                            <span id="switch-account-unavailable" class="w-full text-xs text-slate-500 dark:text-slate-400">No other linked accounts available.</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300"><?= dashboardEscape(trim((string) ($selectedAccount['AcctCode'] ?? '')) ?: 'Account code unavailable') ?></p>
                    <p class="mt-1 break-words text-sm text-slate-500 dark:text-slate-400"><?= dashboardEscape(dashboardFormatServiceAddress($selectedAccount['Address'] ?? '')) ?></p>
                </section>
            <?php endif; ?>
            <!-- =================================================
                 MY ACCOUNTS
                 ================================================= -->

            <?php if ($page === 'account-settings'): ?>

                <section>

                    <p
                        class="
                            text-xs
                            font-bold
                            uppercase
                            tracking-[.14em]
                            text-paleco-700
                        "
                    >
                        Linked Accounts
                    </p>

                    <h2
                        class="
                            mt-1
                            text-2xl
                            font-bold
                        "
                    >
                        Your linked accounts
                    </h2>

                    <p
                        class="
                            mt-1
                            text-sm
                            text-slate-500
                        "
                    >
                        Existing eBills accounts registered
                        with your email are linked automatically.
                        You can also add another account using
                        its account and meter numbers.
                    </p>

                </section>


                <?php if (!$linkedAccounts): ?>

                    <div
                        class="
                            rounded-2xl
                            border
                            border-dashed
                            border-slate-300
                            bg-white
                            px-6 py-10
                            text-center
                        "
                    >

                        <h3 class="font-semibold">
                            No Linked PALECO Account
                        </h3>

                        <p
                            class="
                                mt-2
                                text-sm
                                text-slate-500
                            "
                        >
                            You currently have no PALECO electric service account linked to your portal profile.
                        </p>
                        <a href="#link-paleco-account" class="mt-4 inline-block rounded-xl bg-paleco-700 px-4 py-2 text-sm font-semibold text-white">Link PALECO Account</a>

                    </div>

                <?php endif; ?>


                <!-- Account cards -->

                <div
                    class="
                        grid gap-5
                        md:grid-cols-2
                        xl:grid-cols-3
                    "
                >

                    <?php foreach ($linkedAccounts as $account): ?>

                        <article
                            class="
                                rounded-2xl
                                border border-slate-200
                                bg-white
                                p-5
                                shadow-card
                            "
                        >

                            <div
                                class="
                                    flex
                                    items-start
                                    justify-between
                                    gap-3
                                "
                            >

                                <div class="min-w-0">

                                    <p
                                        class="
                                            text-xs
                                            font-bold
                                            uppercase
                                            tracking-[.12em]
                                            text-slate-400
                                        "
                                    >
                                        <?= dashboardEscape(
                                            formatConsumerAccountCode($account)
                                        ) ?>
                                    </p>

                                    <h3
                                        class="
                                            mt-1
                                            truncate
                                            text-lg
                                            font-bold
                                        "
                                    >
                                        <?= dashboardEscape(dashboardDisplayAccountName($account)) ?>
                                    </h3>

                                </div>


                                <?php if ($account['is_primary']): ?>

                                    <span
                                        class="
                                            shrink-0
                                            rounded-full
                                            bg-paleco-50
                                            px-2.5 py-1
                                            text-xs
                                            font-bold
                                            text-paleco-700
                                        "
                                    >
                                        PRIMARY
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div
                                class="
                                    mt-4
                                    space-y-1
                                    text-sm
                                "
                            >

                                <p class="font-medium">
                                    <?= dashboardEscape(dashboardFormatConsumerName($account['Name'] ?? '')) ?>
                                </p>

                                <p class="text-slate-500">
                                    <?= dashboardEscape(dashboardFormatServiceAddress($account['Address'] ?? '')) ?>
                                </p>

                            </div>


                            <!-- Actions -->

                            <div
                                class="
                                    mt-5
                                    flex
                                    flex-wrap
                                    gap-2
                                "
                            >

                                <form method="post">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= dashboardEscape($csrf) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="switch"
                                    >

                                    <input
                                        type="hidden"
                                        name="account_token"
                                        value="<?= dashboardEscape($accountSelectionTokens[$account['AcctNo']] ?? '') ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="
                                            rounded-lg
                                            border border-slate-200
                                            px-3 py-2
                                            text-sm
                                            font-semibold
                                            hover:bg-slate-50
                                        "
                                    >
                                        View Account
                                    </button>

                                </form>


                                <?php if (!$account['is_primary']): ?>

                                    <form method="post">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= dashboardEscape($csrf) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="primary"
                                        >

                                        <input
                                            type="hidden"
                                            name="account_token"
                                        value="<?= dashboardEscape($accountSelectionTokens[$account['AcctNo']] ?? '') ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="
                                                rounded-lg
                                                bg-paleco-50
                                                px-3 py-2
                                                text-sm
                                                font-semibold
                                                text-paleco-700
                                                hover:bg-paleco-100
                                            "
                                        >
                                            Make Primary
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>


                            <button type="button" data-unlink-open data-account-token="<?= dashboardEscape($accountSelectionTokens[$account['AcctNo']] ?? '') ?>" data-account-number="<?= dashboardEscape(trim((string) ($account['AcctCode'] ?? '')) ?: 'Account code unavailable') ?>" class="mt-4 rounded-xl border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-500/20 dark:text-red-300" aria-haspopup="dialog" aria-controls="unlink-account-dialog">Unlink Account</button>

                            <?php $enrollmentCode=trim((string)($account['AcctCode']??'')); ?>
                            <div class="mt-3">
                            <?php if($ebillsStatus===null||$enrollmentCode===''): ?>
                                <span class="text-sm text-slate-500">eBills status unavailable</span>
                            <?php elseif(isset($ebillsStatus[$enrollmentCode])): ?>
                                <span class="rounded-lg bg-green-50 px-3 py-2 text-sm font-semibold text-green-700">eBills Enrolled</span>
                            <?php else: ?>
                                <button type="button" data-ebills-open data-ebills-code="<?= dashboardEscape($enrollmentCode) ?>" data-account-token="<?= dashboardEscape($accountSelectionTokens[$account['AcctNo']]??'') ?>" aria-haspopup="dialog" aria-controls="ebills-dialog" class="rounded-xl border border-paleco-300 px-3 py-2 text-sm font-semibold text-paleco-700 dark:text-paleco-200">Register for eBills</button>
                            <?php endif; ?>
                            </div>

                            <!-- Friendly label -->
                            <?php $savedLabel = trim((string) ($account['account_nickname'] ?? '')); ?>
                            <form method="post" class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800" data-friendly-label-form data-saved-label="<?= dashboardEscape($savedLabel) ?>">
                                <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>">
                                <input type="hidden" name="action" value="save_label" data-friendly-label-action>
                                <input type="hidden" name="account_token" value="<?= dashboardEscape($accountSelectionTokens[$account['AcctNo']] ?? '') ?>">
                                <label class="mb-1.5 block text-xs font-semibold text-slate-500 dark:text-slate-400">Friendly label</label>
                                <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                    <div class="relative min-w-0 flex-1">
                                        <input name="label" maxlength="100" value="<?= dashboardEscape($savedLabel) ?>" placeholder="e.g. Home" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-paleco-500 focus:ring-4 focus:ring-paleco-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 <?= $savedLabel !== '' ? 'pr-10' : '' ?>" data-friendly-label-input>
                                        <?php if ($savedLabel !== ''): ?>
                                            <button type="button" data-friendly-label-remove class="absolute inset-y-0 right-0 inline-flex items-center px-3 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-paleco-500 dark:hover:text-slate-200" aria-label="Remove friendly label" title="Remove friendly label">
                                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" data-friendly-label-save class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-500/20 disabled:cursor-not-allowed disabled:opacity-70 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white <?= $savedLabel !== '' ? 'hidden' : '' ?>">Save</button>
                                </div>
                            </form>

                        </article>

                    <?php endforeach; ?>

                </div>


                <div id="remove-friendly-label-modal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" aria-hidden="true">
                    <div data-remove-label-backdrop class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                    <section role="dialog" aria-modal="true" aria-labelledby="remove-friendly-label-title" aria-describedby="remove-friendly-label-description" class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:p-6">
                        <h2 id="remove-friendly-label-title" class="text-xl font-bold">Remove friendly label?</h2>
                        <p id="remove-friendly-label-description" class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Are you sure you want to remove this label? The account will display its original consumer name.</p>
                        <div class="mt-6 flex flex-wrap justify-end gap-3"><button type="button" data-remove-label-cancel class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-paleco-500/10 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button><button type="button" data-remove-label-confirm class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-500/30">Remove Label</button></div>
                    </section>
                </div>
                <!-- Add account -->

                <dialog id="unlink-account-dialog" aria-labelledby="unlink-account-title" aria-describedby="unlink-account-description" class="w-[calc(100%_-_2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 shadow-2xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <h2 id="unlink-account-title" class="text-xl font-bold">Unlink PALECO Account?</h2>
                    <div id="unlink-account-description" class="mt-3 space-y-3 text-sm leading-6">
                        <p>Are you sure you want to unlink PALECO account <strong data-unlink-account-number></strong> from your PALECO MCO Portal profile?</p>
                        <p>This action will remove the account from your portal profile. Your official PALECO account and billing records will remain unchanged. You can link the account again later.</p>
                    </div>
                    <form method="post" class="mt-6 flex flex-wrap justify-end gap-3" data-unlink-form>
                        <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>">
                        <input type="hidden" name="action" value="unlink">
                        <input type="hidden" name="account_token" value="" data-unlink-token>
                        <button type="button" data-unlink-cancel autofocus class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button>
                        <button type="submit" data-unlink-confirm class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-70">Confirm Unlink</button>
                    </form>
                </dialog>

                <div id="link-paleco-account"></div>

                <?php if($ebillsStep3) require __DIR__ . '/../partials/ebills-step3.php'; else require __DIR__ . '/../partials/account-linking-form.php'; ?>


            <!-- =================================================
                 PROFILE
                 ================================================= -->

            <!-- =================================================
                 MY PROFILE
                 ================================================= -->

                <section
                    class="
                        max-w-2xl
                        rounded-2xl
                        border border-slate-200
                        bg-white
                        p-6
                        shadow-card
                    "
                >

                    <p
                        class="
                            text-xs
                            font-bold
                            uppercase
                            tracking-[.14em]
                            text-paleco-700
                        "
                    >
                        My Profile
                    </p>

                    <h2
                        class="
                            mt-2
                            text-2xl
                            font-bold
                        "
                    >
                        <?= dashboardEscape($userDisplayName) ?>
                    </h2>

                    <p
                        class="
                            mt-1
                            text-sm
                            text-slate-500
                        "
                    >
                        <?= dashboardEscape($userEmail) ?>
                    </p>

                </section>

                <section id="profile-settings" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 dark:bg-slate-900 dark:border-slate-700">
                    <h2 class="text-xl font-bold">Profile Settings</h2><p class="mt-2 text-sm">Review and confirm your own name for eBills enrollment. This does not change your current display name or sign-in access.</p>
                    <?php $profileContactError = $_SESSION['profile_contact_error'] ?? null; unset($_SESSION['profile_contact_error']); ?>
                    <form method="post" data-profile-settings class="mt-4 grid gap-3 sm:grid-cols-2">
                        <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>"><input type="hidden" name="action" value="save_profile">
                        <?php foreach(['first_name'=>'First Name','last_name'=>'Last Name','middle_name'=>'Middle Name (optional)','extension_name'=>'Name Extension (optional)','contact_number'=>'Contact Number (optional)'] as $profileKey=>$profileLabel): ?>
                        <label class="text-sm font-semibold"><?= dashboardEscape($profileLabel) ?><input name="<?= dashboardEscape($profileKey) ?>" value="<?= dashboardEscape($profileKey==='contact_number' && $profileContactError ? $profileContactError['value'] : ($portalProfile[$profileKey]??'')) ?>" <?= $profileKey==='contact_number'?'type="tel" inputmode="tel" autocomplete="tel" data-profile-contact aria-describedby="profile-contact-error" aria-invalid="'.($profileContactError?'true':'false').'" placeholder="09XXXXXXXXX or +639XXXXXXXXX"':'maxlength="25"' ?> <?= in_array($profileKey,['first_name','last_name'],true)?'required':'' ?> class="mt-1 block w-full rounded-xl border border-slate-300 p-2 dark:border-slate-600 dark:bg-slate-800">
                        <?php if($profileKey==='contact_number'): ?>
                        <span id="profile-contact-error" class="mt-1 block text-sm font-normal text-red-600" aria-live="polite" <?= $profileContactError?'':'hidden' ?>><?= dashboardEscape($profileContactError['message']??'') ?></span>
                        <?php endif; ?></label>
                        <?php endforeach; ?>
                        <button class="rounded-xl bg-paleco-700 px-4 py-2 font-semibold text-white sm:col-span-2">Save and Confirm Profile</button>
                    </form>
                </section>

                <section class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-card">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-paleco-700">Security &amp; Preferences</p>
                    <h2 class="mt-2 text-xl font-bold">Password and appearance</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <h3 class="font-semibold">Password</h3>
                            <p class="mt-1 text-sm text-slate-500">Request a secure password-reset link for your portal account.</p>
                            <button type="button" data-session-action="reset" aria-haspopup="dialog" aria-controls="session-action-dialog" class="mt-3 inline-block text-sm font-semibold text-paleco-700 hover:text-paleco-800">Reset password →</button>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <h3 class="font-semibold">Appearance</h3>
                            <p class="mt-1 text-sm text-slate-500">Use the theme button in the page header to switch between light and dark mode.</p>
                        </div>
                    </div>
                </section>


            <!-- =================================================
                 DASHBOARD / BILLS / HISTORY
                 ================================================= -->

            <?php else: ?>


                <?php if ($page === 'bills'): ?>
                    <nav
                        class="flex border-b border-slate-200 dark:border-slate-800"
                        aria-label="My Bills sections"
                        role="tablist"
                    >
                        <a
                            href="dashboard.php?page=bills&amp;tab=unpaid"
                            role="tab"
                            aria-selected="<?= $billsTab === 'unpaid' ? 'true' : 'false' ?>"
                            class="border-b-2 px-4 py-3 text-sm font-semibold <?= $billsTab === 'unpaid' ? 'border-paleco-700 text-paleco-700 dark:text-paleco-200' : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>"
                        >
                            Unpaid Bills
                        </a>
                        <a
                            href="dashboard.php?page=bills&amp;tab=history"
                            role="tab"
                            aria-selected="<?= $billsTab === 'history' ? 'true' : 'false' ?>"
                            class="border-b-2 px-4 py-3 text-sm font-semibold <?= $billsTab === 'history' ? 'border-paleco-700 text-paleco-700 dark:text-paleco-200' : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>"
                        >
                            Bill History
                        </a>
                    </nav>
                <?php endif; ?>
                <!-- =================================================
                     SUMMARY
                     ================================================= -->

                <div
                    class="
                        grid gap-4
                        md:grid-cols-3
                    "
                >


                    <!-- Amount due -->

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card">
                        <p class="text-xs font-bold uppercase tracking-[.12em] text-slate-400">Amount Due</p>
                        <?php if ($unpaidBillsError): ?>
                            <h3 class="mt-4 text-2xl font-bold">—</h3>
                            <p class="mt-1 text-sm text-slate-500">Balance is temporarily unavailable.</p>
                        <?php else: ?>
                            <h3 class="mt-4 text-2xl font-bold">
                                ₱<?= number_format((float) $unpaidBillSummary['total_due'], 2) ?>
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                <?= (int) $unpaidBillSummary['unpaid_count'] ?> unpaid bill<?= (int) $unpaidBillSummary['unpaid_count'] === 1 ? '' : 's' ?>
                                <?php if ($unpaidBillSummary['overdue_count']): ?>
                                    · <?= (int) $unpaidBillSummary['overdue_count'] ?> overdue
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <a href="dashboard.php?page=bills" class="mt-3 inline-block text-sm font-semibold text-paleco-700 hover:text-paleco-800">View unpaid bills →</a>
                    </article>




                    <!-- Latest bill -->

                    <article
                        class="
                            rounded-2xl
                            border border-slate-200
                            bg-white
                            p-5
                            shadow-card
                        "
                    >

                        <p
                            class="
                                text-xs
                                font-bold
                                uppercase
                                tracking-[.12em]
                                text-slate-400
                            "
                        >
                            Latest Bill
                        </p>


                        <?php if ($latestBill): ?>

                            <h3
                                class="
                                    mt-4
                                    text-2xl
                                    font-bold
                                "
                            >
                                ₱<?= number_format(
                                    (float) $latestBill['TotalBill'],
                                    2
                                ) ?>
                            </h3>

                            <p
                                class="
                                    mt-1
                                    text-sm
                                    text-slate-500
                                "
                            >
                                <?= dashboardEscape(
                                    formatBillMonth(
                                        $latestBill['BillMonth']
                                    )
                                ) ?>
                            </p>

                        <?php else: ?>

                            <h3
                                class="
                                    mt-4
                                    text-2xl
                                    font-bold
                                "
                            >
                                —
                            </h3>

                            <p
                                class="
                                    mt-1
                                    text-sm
                                    text-slate-500
                                "
                            >
                                No billing history available.
                            </p>

                        <?php endif; ?>

                    </article>


                    <!-- Latest consumption -->

                    <article
                        class="
                            rounded-2xl
                            border border-slate-200
                            bg-white
                            p-5
                            shadow-card
                        "
                    >

                        <p
                            class="
                                text-xs
                                font-bold
                                uppercase
                                tracking-[.12em]
                                text-slate-400
                            "
                        >
                            Latest Consumption
                        </p>


                        <?php if ($latestBill): ?>

                            <h3
                                class="
                                    mt-4
                                    text-2xl
                                    font-bold
                                "
                            >
                                <?= number_format(
                                    (float) $latestBill['KWH'],
                                    0
                                ) ?>

                                <span
                                    class="
                                        text-sm
                                        font-medium
                                        text-slate-400
                                    "
                                >
                                    kWh
                                </span>
                            </h3>

                            <p
                                class="
                                    mt-1
                                    text-sm
                                    text-slate-500
                                "
                            >
                                <?= dashboardEscape(
                                    formatBillMonth(
                                        $latestBill['BillMonth']
                                    )
                                ) ?>
                            </p>

                        <?php else: ?>

                            <h3
                                class="
                                    mt-4
                                    text-2xl
                                    font-bold
                                "
                            >
                                —
                            </h3>

                            <p
                                class="
                                    mt-1
                                    text-sm
                                    text-slate-500
                                "
                            >
                                No consumption history available.
                            </p>

                        <?php endif; ?>

                    </article>

                </div>


                <!-- =================================================
                     CONSUMPTION
                     ================================================= -->

                <?php if (
                    $page === 'dashboard'
                ): ?>

                    <section
                        class="
                            rounded-2xl
                            border border-slate-200
                            bg-white
                            p-5
                            shadow-card
                            sm:p-6
                        "
                    >

                        <p
                            class="
                                text-xs
                                font-bold
                                uppercase
                                tracking-[.14em]
                                text-paleco-700
                            "
                        >
                            12-Month Consumption
                        </p>

                        <h2
                            class="
                                mt-1
                                text-lg
                                font-bold
                            "
                        >
                            Electricity use
                        </h2>


                        <?php if ($consumptionHistory): ?>

                            <div
                                class="
                                    mt-6
                                    h-72
                                    sm:h-80
                                "
                            >
                                <canvas id="consumptionChart"></canvas>
                            </div>

                        <?php else: ?>

                            <div
                                class="
                                    mt-5
                                    rounded-xl
                                    border
                                    border-dashed
                                    border-slate-300
                                    bg-slate-50
                                    px-5 py-10
                                    text-center
                                    text-sm
                                    text-slate-500
                                "
                            >
                                No consumption history available.
                            </div>

                        <?php endif; ?>

                    </section>

                <?php endif; ?>


                <!-- =================================================
                     BILL HISTORY
                     ================================================= -->

                <?php if (
                    $page === 'dashboard'
                    || ($page === 'bills' && $billsTab === 'history')
                ): ?>

                    <section
                        class="
                            overflow-hidden
                            rounded-2xl
                            border border-slate-200
                            bg-white
                            shadow-card
                        "
                    >

                        <div
                            class="
                                border-b
                                border-slate-100
                                p-5
                                sm:p-6
                            "
                        >

                            <p
                                class="
                                    text-xs
                                    font-bold
                                    uppercase
                                    tracking-[.14em]
                                    text-paleco-700
                                "
                            >
                                Billing History
                            </p>

                            <h2
                                class="
                                    mt-1
                                    text-lg
                                    font-bold
                                "
                            >
                                Recent bills
                            </h2>

                        </div>


                        <?php if ($billHistory): ?>

                            <div class="overflow-x-auto">

                                <table
                                    class="
                                        min-w-full
                                        divide-y
                                        divide-slate-200
                                        text-sm
                                    "
                                >

                                    <thead class="bg-slate-50">

                                    <tr
                                        class="
                                            text-left
                                            text-xs
                                            font-semibold
                                            uppercase
                                            tracking-wide
                                            text-slate-500
                                        "
                                    >

                                        <th class="px-5 py-3">
                                            Bill Month
                                        </th>

                                        <th class="px-5 py-3">
                                            Bill No.
                                        </th>

                                        <th class="px-5 py-3">
                                            Reading Date
                                        </th>

                                        <th class="px-5 py-3 text-right">
                                            Previous
                                        </th>

                                        <th class="px-5 py-3 text-right">
                                            Present
                                        </th>

                                        <th class="px-5 py-3 text-right">
                                            kWh
                                        </th>

                                        <th class="px-5 py-3">
                                            Due Date
                                        </th>

                                        <th class="px-5 py-3 text-right">
                                            Total Bill
                                        </th>

                                    </tr>

                                    </thead>


                                    <tbody
                                        class="
                                            divide-y
                                            divide-slate-100
                                            bg-white
                                        "
                                    >

                                    <?php foreach ($billHistory as $bill): ?>

                                        <tr class="hover:bg-slate-50">

                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    font-semibold
                                                "
                                            >
                                                <?= dashboardEscape(
                                                    formatBillMonth(
                                                        $bill['BillMonth']
                                                    )
                                                ) ?>
                                            </td>


                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    text-slate-600
                                                "
                                            >
                                                <?= dashboardEscape(
                                                    (string) $bill['BillNo']
                                                ) ?>
                                            </td>


                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    text-slate-600
                                                "
                                            >
                                                <?= dashboardEscape(
                                                    formatPortalDate(
                                                        $bill['ReadingDate']
                                                    )
                                                ) ?>
                                            </td>


                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    text-right
                                                    text-slate-600
                                                "
                                            >
                                                <?= number_format(
                                                    (float) $bill['PrvRdngKWH'],
                                                    0
                                                ) ?>
                                            </td>


                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    text-right
                                                    text-slate-600
                                                "
                                            >
                                                <?= number_format(
                                                    (float) $bill['PrsRdngKWH'],
                                                    0
                                                ) ?>
                                            </td>


                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    text-right
                                                    font-semibold
                                                "
                                            >
                                                <?= number_format(
                                                    (float) $bill['KWH'],
                                                    0
                                                ) ?>
                                            </td>


                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    text-slate-600
                                                "
                                            >
                                                <?= dashboardEscape(
                                                    formatPortalDate(
                                                        $bill['DueDate']
                                                    )
                                                ) ?>
                                            </td>


                                            <td
                                                class="
                                                    whitespace-nowrap
                                                    px-5 py-4
                                                    text-right
                                                    font-semibold
                                                "
                                            >
                                                ₱<?= number_format(
                                                    (float) $bill['TotalBill'],
                                                    2
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div
                                class="
                                    px-6 py-12
                                    text-center
                                    text-sm
                                    text-slate-500
                                "
                            >
                                No billing history available.
                            </div>

                        <?php endif; ?>

                    </section>

                <?php endif; ?>


                                <!-- STATEMENT OF ACCOUNT -->
                <?php if ($page === 'bills' && $billsTab === 'unpaid'): ?>
                    <?php $soaMonth = trim((string) ($latestBill['BillMonth'] ?? '')); ?>
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.14em] text-paleco-700 dark:text-paleco-300">Statement of Account</p>
                                <h2 class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Latest billing statement</h2>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?= dashboardEscape(formatConsumerAccountCode($selectedAccount)) ?></p>
                                <?php if ($soaMonth !== ''): ?>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Billing month: <?= dashboardEscape(formatBillMonth($soaMonth)) ?></p>
                                <?php else: ?>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Statement of Account is not yet available.</p>
                                <?php endif; ?>
                            </div>
                            <button type="button" data-soa-open data-soa-account-code="<?= dashboardEscape(trim((string) ($selectedAccount['AcctCode'] ?? ''))) ?>" <?= $soaMonth === '' ? 'disabled' : '' ?> class="inline-flex min-h-11 items-center justify-center rounded-xl bg-paleco-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-paleco-800 focus:outline-none focus:ring-2 focus:ring-paleco-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900">View SOA</button>
                        </div>
                    </section>
                <?php endif; ?>
<!-- =================================================
                     MY BILLS
                     ================================================= -->

                <?php if ($page === 'bills' && $billsTab === 'unpaid'): ?>

                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card">
                        <div class="border-b border-slate-100 p-5 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[.14em] text-paleco-700">Outstanding Bills</p>
                                    <h2 class="mt-1 text-lg font-bold">Current balance</h2>
                                    <p class="mt-1 text-sm text-slate-500">
                                        <?= dashboardEscape(formatConsumerAccountCode($selectedAccount)) ?>
                                    </p>
                                </div>
                                <a href="dashboard.php?page=account-settings" class="text-sm font-semibold text-paleco-700 hover:text-paleco-800">
                                    Switch account →
                                </a>
                            </div>

                            <?php if ($isDevelopmentMode): ?>
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200" role="note">
                                    Development data — billing balances may not reflect current PALECO records.
                                </div>
                            <?php endif; ?>

                            <?php if (!$unpaidBillsError): ?>
                                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total amount due</p>
                                        <p class="mt-1 text-2xl font-bold">₱<?= number_format((float) $unpaidBillSummary['total_due'], 2) ?></p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unpaid bills</p>
                                        <p class="mt-1 text-2xl font-bold"><?= (int) $unpaidBillSummary['unpaid_count'] ?></p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Overdue</p>
                                        <p class="mt-1 text-2xl font-bold <?= $unpaidBillSummary['overdue_count'] ? 'text-red-600 dark:text-red-400' : '' ?>">
                                            <?= (int) $unpaidBillSummary['overdue_count'] ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($unpaidBillsError): ?>
                            <div class="px-6 py-12 text-center text-sm text-slate-500">
                                We could not load your outstanding bills right now. Please try again later.
                            </div>
                        <?php elseif ($unpaidBills): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th class="px-5 py-3">Bill month</th>
                                            <th class="px-5 py-3">Due date</th>
                                            <th class="px-5 py-3 text-right">Outstanding</th>
                                            <th class="px-5 py-3 text-right">Surcharge</th>
                                            <th class="px-5 py-3 text-right">Total amount</th>
                                            <th class="px-5 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <?php foreach ($unpaidBills as $bill): ?>
                                            <tr class="hover:bg-slate-50">
                                                <td class="whitespace-nowrap px-5 py-4 font-semibold">
                                                    <?= dashboardEscape(formatBillMonth($bill['bill_month'])) ?>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                                                    <?= dashboardEscape(formatPortalDate($bill['due_date'])) ?>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4 text-right text-slate-600">
                                                    ₱<?= number_format((float) $bill['outstanding_balance'], 2) ?>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4 text-right text-slate-600">
                                                    ₱<?= number_format((float) $bill['surcharge'], 2) ?>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4 text-right font-bold">
                                                    ₱<?= number_format((float) $bill['total_due'], 2) ?>
                                                </td>
                                                <td class="whitespace-nowrap px-5 py-4">
                                                    <?php $billStatus = unpaidBillStatus($bill['due_date']); ?>
                                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold <?= dashboardEscape($billStatus['class']) ?>">
                                                        <?= dashboardEscape($billStatus['label']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="px-6 py-12 text-center">
                                <h3 class="text-base font-semibold">No Unpaid Bills</h3>
                                <p class="mt-2 text-sm text-slate-500">
                                    You currently have no outstanding bills for this account.
                                </p>
                                <p class="mt-4 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    Total Outstanding Balance: ₱0.00
                                </p>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php endif; ?>




                <!-- =================================================
                     SERVICE DETAILS
                     ================================================= -->

                <section
                    class="
                        rounded-2xl
                        border border-slate-200
                        bg-white
                        p-5
                        shadow-card
                        sm:p-6
                    "
                >

                    <p
                        class="
                            text-xs
                            font-bold
                            uppercase
                            tracking-[.14em]
                            text-paleco-700
                        "
                    >
                        Service Details
                    </p>

                    <h2
                        class="
                            mt-1
                            text-lg
                            font-bold
                        "
                    >
                        Account information
                    </h2>


                    <dl
                        class="
                            mt-5
                            grid
                            gap-x-8
                            gap-y-5
                            sm:grid-cols-2
                            xl:grid-cols-3
                        "
                    >

                        <div>

                            <dt
                                class="
                                    text-xs
                                    font-medium
                                    text-slate-400
                                "
                            >
                                Account Code
                            </dt>

                            <dd
                                class="
                                    mt-1
                                    text-sm
                                    font-semibold
                                "
                            >
                                <?= dashboardEscape(
                                    formatConsumerAccountCode($selectedAccount)
                                ) ?>
                            </dd>

                        </div>


                        <div>

                            <dt
                                class="
                                    text-xs
                                    font-medium
                                    text-slate-400
                                "
                            >
                                Account Holder
                            </dt>

                            <dd
                                class="
                                    mt-1
                                    text-sm
                                    font-semibold
                                "
                            >
                                <?= dashboardEscape(dashboardFormatConsumerName($selectedAccount['Name'] ?? '')) ?>
                            </dd>

                        </div>


                        <div>

                            <dt
                                class="
                                    text-xs
                                    font-medium
                                    text-slate-400
                                "
                            >
                                Status
                            </dt>

                            <dd
                                class="
                                    mt-1
                                    text-sm
                                    font-semibold
                                "
                            >
                                <?= dashboardEscape(dashboardFormatAccountStatus($selectedAccount['Status'] ?? '')) ?>
                            </dd>

                        </div>


                        <div class="sm:col-span-2">

                            <dt
                                class="
                                    text-xs
                                    font-medium
                                    text-slate-400
                                "
                            >
                                Service Address
                            </dt>

                            <dd
                                class="
                                    mt-1
                                    text-sm
                                    font-semibold
                                "
                            >
                                <?= dashboardEscape(dashboardFormatServiceAddress($selectedAccount['Address'] ?? '')) ?>
                            </dd>

                        </div>

                    </dl>

                </section>


            <?php endif; ?>


            <!-- Footer -->

            <footer
                class="
                    border-t
                    border-slate-200
                    pt-5
                    text-center
                    text-xs
                    text-slate-400
                "
            >
                PALECO MCO Portal ·
                Local XAMPP Development Environment
            </footer>

        </div>

    <?php if ($selectedAccount && count($linkedAccounts) > 1): ?>
        <div id="switch-account-modal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" aria-hidden="true">
            <div data-switch-account-backdrop class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
            <section id="switch-account-dialog" role="dialog" aria-modal="true" aria-labelledby="switch-account-title" aria-describedby="switch-account-description" class="relative z-10 flex max-h-[calc(100dvh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-5 dark:border-slate-800 sm:p-6">
                    <div class="min-w-0"><h2 id="switch-account-title" class="text-xl font-bold">Switch Account</h2><p id="switch-account-description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">Select an account to view its dashboard.</p></div>
                    <button type="button" data-switch-account-close class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-paleco-500 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Close account selector">×</button>
                </div>
                <div class="min-h-0 overflow-y-auto p-3 sm:p-4">
                    <div class="space-y-2" role="list">
                        <?php foreach ($linkedAccounts as $account): ?>
                            <?php $isCurrent = (string) $account['AcctNo'] === (string) $selectedAccount['AcctNo']; ?>
                            <form method="post" action="dashboard.php" class="contents" data-switch-account-form>
                                <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>"><input type="hidden" name="action" value="switch"><input type="hidden" name="return_page" value="<?= dashboardEscape($page) ?>"><input type="hidden" name="account_token" value="<?= dashboardEscape($accountSelectionTokens[$account['AcctNo']] ?? '') ?>">
                                <button type="submit" <?= $isCurrent ? 'disabled aria-current="true"' : '' ?> class="w-full rounded-xl border p-4 text-left transition focus:outline-none focus:ring-4 focus:ring-paleco-500/20 <?= $isCurrent ? 'cursor-default border-paleco-500 bg-paleco-50 dark:border-paleco-700 dark:bg-paleco-900/40' : 'border-slate-200 hover:border-paleco-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-paleco-700 dark:hover:bg-slate-800/70' ?>">
                                    <div class="flex flex-wrap items-center gap-2"><span class="min-w-0 break-words font-semibold"><?= dashboardEscape(dashboardDisplayAccountName($account)) ?></span><?php if ($account['is_primary']): ?><span class="rounded-full bg-paleco-100 px-2 py-0.5 text-xs font-bold text-paleco-700 dark:bg-paleco-900 dark:text-paleco-100">PRIMARY</span><?php endif; ?><?php if ($isCurrent): ?><span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-200">CURRENTLY VIEWING</span><?php endif; ?></div>
                                    <p class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300"><?= dashboardEscape(trim((string) ($account['AcctCode'] ?? '')) ?: 'Account code unavailable') ?></p><p class="mt-1 break-words text-sm text-slate-500 dark:text-slate-400"><?= dashboardEscape(dashboardFormatServiceAddress($account['Address'] ?? '')) ?></p>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p data-switch-account-loading class="hidden border-t border-slate-100 px-5 py-3 text-sm font-medium text-paleco-700 dark:border-slate-800 dark:text-paleco-200">Switching account…</p>
            </section>
        </div>
    <?php endif; ?>
    </main>

</div>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script src="assets/js/profile-settings.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/portal-shell.js"></script>
<?php require __DIR__ . '/../partials/ebills-ui.php'; ?>
<script src="assets/js/account-linking.js"></script>
<script src="assets/js/account-unlinking.js"></script>

<script>

<?php if (
    $consumptionHistory
    && (
        $page === 'dashboard'
    )
): ?>

/* =========================================================
   CONSUMPTION CHART
   ========================================================= */

(function () {

    const canvas =
        document.getElementById(
            'consumptionChart'
        );

    if (!canvas) {
        return;
    }


    const labels =
        <?= json_encode(
            $chartLabels,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?>;


    const values =
        <?= json_encode(
            $chartValues,
            JSON_NUMERIC_CHECK
        ) ?>;


    new Chart(
        canvas,
        {

            type: 'line',

            data: {

                labels: labels,

                datasets: [

                    {

                        label: 'kWh',

                        data: values,

                        borderColor:
                            '#15803d',

                        backgroundColor:
                            'rgba(21, 128, 61, 0.08)',

                        borderWidth: 2,

                        pointRadius: 3,

                        pointHoverRadius: 5,

                        fill: true,

                        tension: 0.3

                    }

                ]

            },


            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {

                    mode: 'index',

                    intersect: false

                },


                plugins: {

                    legend: {

                        display: false

                    },

                    tooltip: {

                        callbacks: {

                            label:
                                function (context) {

                                    return (
                                        Number(
                                            context.parsed.y
                                        ).toLocaleString()
                                        + ' kWh'
                                    );
                                }

                        }

                    }

                },


                scales: {

                    x: {

                        grid: {

                            display: false

                        }

                    },


                    y: {

                        beginAtZero: true,

                        ticks: {

                            callback:
                                function (value) {

                                    return (
                                        Number(value)
                                        .toLocaleString()
                                        + ' kWh'
                                    );
                                }

                        }

                    }

                }

            }

        }
    );

})();

<?php endif; ?>

</script>


<script>
(() => {
    const modal = document.getElementById('switch-account-modal');
    if (!modal) return;
    const dialog = document.getElementById('switch-account-dialog');
    const openers = document.querySelectorAll('[data-switch-account-open]');
    const closeButton = modal.querySelector('[data-switch-account-close]');
    const backdrop = modal.querySelector('[data-switch-account-backdrop]');
    const loading = modal.querySelector('[data-switch-account-loading]');
    let opener = null;
    const focusables = () => Array.from(dialog.querySelectorAll('button:not([disabled]), [href], input:not([disabled])'));
    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('overflow-hidden'); opener?.focus(); };
    const open = (button) => { opener = button; modal.classList.remove('hidden'); modal.classList.add('flex'); modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('overflow-hidden'); focusables()[0]?.focus(); };
    openers.forEach((button) => button.addEventListener('click', () => open(button)));
    closeButton?.addEventListener('click', close); backdrop?.addEventListener('click', close);
    document.addEventListener('keydown', (event) => { if (modal.classList.contains('hidden')) return; if (event.key === 'Escape') { event.preventDefault(); close(); } if (event.key === 'Tab') { const items = focusables(); if (!items.length) return; const first = items[0]; const last = items[items.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); } } });
    modal.querySelectorAll('[data-switch-account-form]').forEach((form) => form.addEventListener('submit', (event) => { const button = form.querySelector('button'); if (button.disabled) { event.preventDefault(); return; } modal.querySelectorAll('button').forEach((item) => { item.disabled = true; }); loading?.classList.remove('hidden'); }));
})();
</script><script>
(() => {
    const modal = document.getElementById('remove-friendly-label-modal');
    if (!modal) return;
    const cancel = modal.querySelector('[data-remove-label-cancel]');
    const confirm = modal.querySelector('[data-remove-label-confirm]');
    const backdrop = modal.querySelector('[data-remove-label-backdrop]');
    let activeForm = null;
    let opener = null;
    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('overflow-hidden'); opener?.focus(); activeForm = null; };
    const open = (button) => { opener = button; activeForm = button.closest('[data-friendly-label-form]'); modal.classList.remove('hidden'); modal.classList.add('flex'); modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('overflow-hidden'); cancel?.focus(); };
    document.querySelectorAll('[data-friendly-label-form]').forEach((form) => {
        const input = form.querySelector('[data-friendly-label-input]');
        const save = form.querySelector('[data-friendly-label-save]');
        const remove = form.querySelector('[data-friendly-label-remove]');
        const action = form.querySelector('[data-friendly-label-action]');
        const saved = form.dataset.savedLabel || '';
        const updateSave = () => save?.classList.toggle('hidden', input.value === saved);
        input?.addEventListener('input', updateSave);
        remove?.addEventListener('click', () => open(remove));
        form.addEventListener('submit', (event) => { const removing = action?.value === 'remove_label'; const submitter = event.submitter || save; if ((!submitter || submitter.classList.contains('hidden')) && !removing) { event.preventDefault(); return; } form.querySelectorAll('button').forEach((control) => { control.disabled = true; }); if (input) input.readOnly = true; if (!removing) submitter.textContent = 'Saving…'; });
    });
    cancel?.addEventListener('click', close); backdrop?.addEventListener('click', close);
    confirm?.addEventListener('click', () => { if (!activeForm) return; const input = activeForm.querySelector('[data-friendly-label-input]'); const action = activeForm.querySelector('[data-friendly-label-action]'); input.value = ''; action.value = 'remove_label'; confirm.disabled = true; cancel.disabled = true; confirm.textContent = 'Removing…'; activeForm.requestSubmit(); });
    document.addEventListener('keydown', (event) => { if (modal.classList.contains('hidden')) return; if (event.key === 'Escape') { event.preventDefault(); close(); } if (event.key === 'Tab') { const controls = Array.from(modal.querySelectorAll('button:not([disabled])')); const first = controls[0]; const last = controls[controls.length - 1]; if (!first) return; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); } } });
})();
</script><div id="soa-modal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="soa-modal-title" aria-hidden="true">
    <div data-soa-backdrop class="absolute inset-0 bg-slate-950/60"></div>
    <div class="relative z-10 flex max-h-[calc(100vh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
        <div class="flex items-start justify-between border-b border-slate-200 p-5 dark:border-slate-800"><div><h2 id="soa-modal-title" class="text-lg font-bold text-slate-900 dark:text-white">Statement of Account</h2><p data-soa-status class="mt-1 text-sm text-slate-500 dark:text-slate-400">Preparing your statement…</p></div><button type="button" data-soa-close class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-paleco-500 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close Statement of Account">&#215;</button></div>
        <div class="min-h-0 flex-1 bg-slate-100 p-3 dark:bg-slate-950"><iframe data-soa-frame title="Statement of Account PDF preview" class="hidden h-[65vh] min-h-80 w-full rounded-lg border-0 bg-white" referrerpolicy="no-referrer"></iframe></div>
        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 p-4 sm:flex-row sm:justify-end dark:border-slate-800"><button type="button" data-soa-close class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Close</button><a data-soa-download class="hidden min-h-11 rounded-xl bg-paleco-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-paleco-800" target="_blank" rel="noopener noreferrer">Open PDF</a></div>
    </div>
</div>
<script>
(() => {
    const modal = document.getElementById('soa-modal'), opener = document.querySelector('[data-soa-open]'); if (!modal || !opener) return;
    const status = modal.querySelector('[data-soa-status]'), frame = modal.querySelector('[data-soa-frame]'), download = modal.querySelector('[data-soa-download]'), expectedAccountCode = opener.dataset.soaAccountCode || '', csrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let trigger = null, requestId = 0;
    const close = () => { requestId++; frame.src = 'about:blank'; frame.classList.add('hidden'); download.classList.add('hidden'); modal.classList.add('hidden'); modal.classList.remove('flex'); modal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('overflow-hidden'); trigger?.focus(); };
    const open = async () => { trigger = opener; const id = ++requestId; status.textContent = 'Preparing your statement…'; frame.src = 'about:blank'; frame.classList.add('hidden'); download.classList.add('hidden'); modal.classList.remove('hidden'); modal.classList.add('flex'); modal.setAttribute('aria-hidden', 'false'); document.body.classList.add('overflow-hidden'); modal.querySelector('[data-soa-close]')?.focus(); try { const form = new FormData(); form.append('csrf_token', csrf); const response = await fetch('soa.php', {method: 'POST', body: form, credentials: 'same-origin', cache: 'no-store'}); const payload = await response.json(); if (id !== requestId) return; if (!response.ok || !payload.ok || !payload.url || payload.account_code !== expectedAccountCode) throw new Error(payload.message || 'Statement of Account is currently unavailable.'); frame.src = payload.url; frame.classList.remove('hidden'); download.href = payload.url; download.classList.remove('hidden'); status.textContent = 'Your statement is ready. If the preview is blocked, use Open PDF.'; } catch (error) { if (id === requestId) status.textContent = error.message || 'Statement of Account is currently unavailable.'; } };
    opener.addEventListener('click', open); modal.querySelectorAll('[data-soa-close]').forEach((button) => button.addEventListener('click', close)); modal.querySelector('[data-soa-backdrop]')?.addEventListener('click', close); document.addEventListener('keydown', (event) => { if (!modal.classList.contains('hidden') && event.key === 'Escape') { event.preventDefault(); close(); } });
})();
</script></body>
</html>
