<?php
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    return;
}
if (empty($_SESSION['dashboard_csrf'])) $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(32));
?>
<dialog id="session-action-dialog" aria-labelledby="session-action-title" aria-describedby="session-action-description" class="w-[calc(100%_-_2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-slate-900 shadow-2xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
    <h2 id="session-action-title" class="text-xl font-bold"></h2>
    <p id="session-action-description" class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"></p>
    <p data-session-error role="alert" tabindex="-1" class="mt-3 hidden text-sm text-red-700 dark:text-red-300"></p>
    <form method="post" action="logout.php" data-session-form class="mt-6 flex flex-wrap justify-end gap-3">
        <input type="hidden" name="csrf_token" value="<?= portalShellEscape($_SESSION['dashboard_csrf']) ?>">
        <input type="hidden" name="intent" value="" data-session-intent>
        <button type="button" data-session-cancel autofocus class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-paleco-500/20 dark:border-slate-600 dark:hover:bg-slate-800">Cancel</button>
        <button type="submit" data-session-confirm class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-500/20 disabled:opacity-70"></button>
    </form>
</dialog>
<script src="assets/js/session-actions.js" defer></script>
