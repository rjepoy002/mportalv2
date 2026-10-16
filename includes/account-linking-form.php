<?php
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    return;
}
?>
                <section id="account-linking-panel"
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
                        Add Account
                    </p>

                    <h2
                        class="
                            mt-1
                            text-xl
                            font-bold
                        "
                    >
                        Link another PALECO account
                    </h2>

                    <p
                        class="
                            mt-1
                            text-sm
                            text-slate-500
                        "
                    >
                        Verify your account and meter, then verify a billing month and amount from your PALECO statement.
                    </p>


                    <?php if ($accountLinkFormError): ?>
                        <div data-link-error tabindex="-1" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300" role="alert">
                            <?= dashboardEscape($accountLinkFormError) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($accountLinkPending): ?>
                    <ol aria-label="Account linking progress" class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        <li class="rounded-xl bg-paleco-50 p-3 font-semibold text-paleco-700 dark:bg-paleco-950 dark:text-paleco-200">Step 1: Account Details — Completed</li>
                        <li aria-current="step" class="rounded-xl border border-paleco-500 p-3 font-semibold">Step 2: Billing Verification — Current</li>
                    </ol>
                    <?php $linkSummary = $accountLinkPending['summary']; ?>
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800" aria-label="Verified PALECO account">
                        <p class="font-bold break-words">Account <?= dashboardEscape($linkSummary['account_number'] ?: 'code unavailable') ?></p>
                        <dl class="mt-2 space-y-2 text-sm break-words">
                            <?php foreach (['description' => 'Account description', 'consumer_name' => 'Registered consumer', 'address' => 'Address'] as $summaryKey => $summaryLabel): ?>
                                <?php if (trim($linkSummary[$summaryKey]) !== ''): ?>
                                    <div><dt class="text-slate-500 dark:text-slate-400"><?= dashboardEscape($summaryLabel) ?></dt><dd><?= dashboardEscape($linkSummary[$summaryKey]) ?></dd></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                    <h3 data-link-step-heading tabindex="-1" class="mt-5 text-lg font-bold">Step 2: Billing Verification</h3>
                    <p class="mt-2 text-sm text-paleco-700 dark:text-paleco-200" role="status">Account found. Please complete billing verification to link this account.</p>
                    <p id="link-billing-help" class="mt-2 text-sm text-slate-500">Select a billing month from your PALECO Statement of Account and enter the exact Total Bill Amount printed on that statement.</p>
                    <form method="post" class="mt-6 grid gap-4 sm:grid-cols-2" data-account-link-form data-loading-text="Verifying account…">
                        <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>">
                        <input type="hidden" name="action" value="verify_link">
                        <input type="hidden" name="challenge_id" value="<?= dashboardEscape($accountLinkPending['id']) ?>">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Billing Month
                            <select name="bill_month" required aria-describedby="link-billing-help" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                <option value="">Select a billing month</option>
                                <?php foreach ($accountLinkPending['months'] as $linkMonth): ?>
                                    <option value="<?= dashboardEscape($linkMonth) ?>"><?= dashboardEscape(palecoLinkMonthLabel($linkMonth)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Total Bill Amount (PHP)
                            <input name="bill_amount" type="text" inputmode="decimal" maxlength="80" autocomplete="off" required placeholder="0.00" aria-describedby="link-billing-help" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        </label>
                        <div class="sm:col-span-2">
                            <p class="mb-3 text-sm text-slate-500">Verification expires 10 minutes after account verification. Use Back to restart if it expires.</p>
                            <button data-link-submit type="submit" class="rounded-xl bg-paleco-700 px-5 py-2.5 text-sm font-semibold text-white focus:outline-none focus:ring-4 focus:ring-paleco-500/30 disabled:opacity-70">Verify and Link Account</button>
                        </div>
                    </form>
                    <form method="post" class="mt-3" data-account-link-form data-loading-text="Restarting verification…">
                        <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>">
                        <input type="hidden" name="action" value="restart_link">
                        <button data-link-submit type="submit" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold disabled:opacity-70">Back</button>
                    </form>
                    <?php else: ?>
                    <ol aria-label="Account linking progress" class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                        <li aria-current="step" class="rounded-xl border border-paleco-500 p-3 font-semibold">Step 1: Account Details — Current</li>
                        <li class="rounded-xl border border-slate-200 p-3 text-slate-500">Step 2: Billing Verification — Pending</li>
                    </ol>
                    <h3 data-link-step-heading tabindex="-1" class="mt-4 text-lg font-bold">Step 1: Account Details</h3>
                    <?php if (!$accountLinker->enabled()): ?>
                        <p id="link-disabled-help" class="mt-2 text-sm text-slate-500" role="status">Online account linking is currently disabled. Continue is unavailable. Please contact PALECO customer support.</p>
                    <?php endif; ?>
                    <form method="post" class="mt-6 grid gap-4 md:grid-cols-3" data-account-link-form data-loading-text="Verifying account…">
                        <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>">
                        <input type="hidden" name="action" value="add">

                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            PALECO Account Code
                            <input name="account_code" maxlength="12" required autocomplete="off" inputmode="text" placeholder="XX-XXXX-XXXX" aria-describedby="account-code-help" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-paleco-500 focus:ring-4 focus:ring-paleco-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <span id="account-code-help" class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">Enter your PALECO account code.</span>
                        </label>

                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Meter Serial Number
                            <input name="meter_number" maxlength="20" required autocomplete="off" placeholder="Enter meter serial number" aria-describedby="meter-number-help" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-paleco-500 focus:ring-4 focus:ring-paleco-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <span id="meter-number-help" class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">Enter the meter serial number registered to your account.</span>
                        </label>

                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Friendly label <span class="font-normal text-slate-400">(optional)</span>
                            <input name="label" maxlength="100" placeholder="e.g. Home" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3 py-2.5 outline-none focus:border-paleco-500 focus:ring-4 focus:ring-paleco-500/10 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        </label>

                        <div class="flex flex-wrap gap-3 md:col-span-3">
                            <button data-link-submit type="submit" <?= !$accountLinker->enabled() ? 'disabled aria-describedby="link-disabled-help"' : '' ?> class="rounded-xl bg-paleco-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-paleco-800 focus:outline-none focus:ring-4 focus:ring-paleco-500/30 disabled:cursor-not-allowed disabled:opacity-70"><?= $accountLinker->enabled() ? 'Continue' : 'Linking unavailable' ?></button>
                            <a href="dashboard.php?page=account-settings" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-paleco-500/10 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
                        </div>
                    </form>
                    <?php endif; ?>
                </section>
