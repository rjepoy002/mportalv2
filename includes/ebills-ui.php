<?php
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){http_response_code(404);return;}
?>
<dialog id="ebills-dialog" aria-labelledby="ebills-title" class="max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-xl overflow-y-auto rounded-2xl bg-white p-6 text-slate-900 shadow-2xl backdrop:bg-slate-950/50 dark:bg-slate-900 dark:text-slate-100">
    <h2 id="ebills-title" tabindex="-1" class="text-xl font-bold">eBills Enrollment</h2>
    <p data-ebills-progress class="mt-2 text-sm" aria-live="polite">Step 1 of 3: Terms and Conditions</p>
    <p data-ebills-status role="status" tabindex="-1" class="mt-3 text-sm"></p>
    <dl data-ebills-summary hidden class="mt-4 space-y-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-600"></dl>
    <form data-ebills-form novalidate class="mt-4 space-y-4">
        <input type="hidden" name="action" value="ebills_enroll">
        <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrf) ?>">
        <input type="hidden" name="account_token" data-ebills-token>
        <input type="hidden" name="terms_token" data-ebills-terms-token>
        <input type="hidden" name="terms_version" data-ebills-version>
        <section data-ebills-screen="1" aria-labelledby="ebills-terms-heading">
            <h3 id="ebills-terms-heading" class="font-bold">Official PALECO e-Billing Terms and Conditions</h3>
            <p id="ebills-scroll-help" class="my-2 text-sm">Read and scroll to the bottom of the terms to enable acceptance.</p>
            <div data-ebills-terms data-version="<?= dashboardEscape(EBillsEnrollment::termsVersion()) ?>" role="region" aria-labelledby="ebills-terms-heading" aria-describedby="ebills-scroll-help" tabindex="0" class="h-[clamp(6rem,calc(90dvh_-_25rem),28rem)] overflow-y-auto rounded-xl border border-slate-300 p-4 text-sm leading-relaxed dark:border-slate-600 sm:h-[clamp(6rem,calc(90dvh_-_22rem),28rem)] [&_li]:mb-4 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-4"><?= EBillsEnrollment::termsHtml() ?></div>
            <label class="mt-3 flex gap-2 text-sm"><input type="checkbox" name="consent" value="1" data-ebills-consent disabled class="mt-1"><?= dashboardEscape(EBillsEnrollment::CONSENT) ?></label>
        </section>
        <fieldset data-ebills-screen="2" hidden class="space-y-3">
            <legend class="font-bold">Enrollment Information</legend>
            <p class="text-sm">Confirm your own details below. These will also be saved to your portal profile.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" data-ebills-names>
            <?php foreach(['first_name'=>'First Name','last_name'=>'Last Name','middle_name'=>'Middle Name','extension_name'=>'Name Extension'] as $field=>$label): ?>
            <label class="block text-sm font-semibold"><?= $label ?><?= in_array($field,['first_name','last_name'],true)?' — required':' — optional' ?><input name="<?= $field ?>" data-ebills-personal maxlength="25" <?= in_array($field,['first_name','last_name'],true)?'required':'' ?> autocomplete="<?= ['first_name'=>'given-name','last_name'=>'family-name','middle_name'=>'additional-name','extension_name'=>'honorific-suffix'][$field] ?>" class="mt-1 block w-full rounded-xl border border-slate-300 p-2 dark:border-slate-600 dark:bg-slate-800"></label>
            <?php endforeach; ?>
            </div>
            <label class="block text-sm font-semibold">Contact Number — required<input name="contact_number" data-ebills-personal required maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" autocomplete="tel-national" placeholder="09XXXXXXXXX" class="mt-1 block w-full rounded-xl border border-slate-300 p-2 dark:border-slate-600 dark:bg-slate-800"></label>
            <label class="block text-sm font-semibold">Registered Email<input data-ebills-email readonly type="email" class="mt-1 block w-full rounded-xl border border-slate-300 bg-slate-50 p-2 dark:border-slate-600 dark:bg-slate-800"></label>
        </fieldset>
        <section data-ebills-screen="3" hidden aria-labelledby="ebills-review-heading">
            <h3 id="ebills-review-heading" class="font-bold">Review and Confirmation</h3>
            <dl data-ebills-review class="mt-3 grid gap-3 text-sm sm:grid-cols-2"></dl>
            <p class="mt-3 text-sm">You accepted the official PALECO e-Billing Terms and Conditions shown in Step 1.</p>
        </section>
        <div class="flex flex-wrap justify-end gap-3">
            <button type="button" data-ebills-cancel autofocus class="rounded-xl border px-4 py-2">Cancel</button>
            <button type="button" data-ebills-back hidden class="rounded-xl border px-4 py-2">Back</button>
            <button type="button" data-ebills-next disabled class="rounded-xl bg-paleco-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Continue</button>
            <button type="submit" data-ebills-confirm hidden disabled class="rounded-xl bg-paleco-700 px-4 py-2 font-semibold text-white disabled:opacity-50">Confirm Enrollment</button>
        </div>
    </form>
</dialog>
<script src="assets/js/ebills-enrollment.js" defer></script>
