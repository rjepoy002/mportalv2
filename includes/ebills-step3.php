<?php
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){http_response_code(404);return;}
if (!empty($ebillsStep3)): ?>
<section id="account-linking-panel" class="rounded-2xl border bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
    <ol aria-label="Account linking progress" class="grid gap-2 text-sm sm:grid-cols-3"><li>Step 1: Account Details — Completed</li><li>Step 2: Billing Verification — Completed</li><li aria-current="step">Step 3: eBills Enrollment — Optional</li></ol>
    <h3 data-link-step-heading tabindex="-1" class="mt-4 text-lg font-bold">Account linked. eBills Enrollment (Optional)</h3>
    <p class="mt-2 font-semibold">Account <?= dashboardEscape($ebillsStep3['code']) ?></p>
    <?php if ($ebillsStep3['enrolled'] === true): ?>
        <p class="mt-3">This PALECO account is already enrolled in eBills.</p>
    <?php elseif ($ebillsStep3['enrolled'] === false): ?>
        <p class="mt-3">Would you like to enroll this PALECO account in eBills?</p><p class="mt-2 text-sm">Receive monthly Statements of Account through your registered email address. Your account is already linked; enrollment is optional.</p>
        <button type="button" data-ebills-open data-ebills-code="<?= dashboardEscape($ebillsStep3['code']) ?>" data-account-token="<?= dashboardEscape($ebillsStep3['token']) ?>" class="mt-3 rounded-xl bg-paleco-700 px-4 py-2 font-semibold text-white">Enroll in eBills</button>
    <?php else: ?><p class="mt-3">eBills enrollment status is temporarily unavailable. Your account remains linked. Try enrollment from Linked Accounts later.</p><?php endif; ?>
    <form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>"><input type="hidden" name="action" value="finish_ebills"><button class="rounded-xl border px-4 py-2"><?= $ebillsStep3['enrolled'] === false ? 'Skip for Now' : 'Finish' ?></button></form>
</section>
<?php endif; ?>
