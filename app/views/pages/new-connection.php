<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Connection | PALECO MCO Portal</title>
    <link rel="icon" href="assets/images/logo.png">
    <script>
        (() => {
            try {
                const theme = localStorage.getItem('mco-theme');
                document.documentElement.classList.toggle('dark', theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches));
                document.documentElement.classList.toggle('sidebar-collapsed', localStorage.getItem('mco.sidebar.collapsed') === 'true');
            } catch (error) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/css/portal-utilities.css">

    <link rel="stylesheet" href="assets/css/portal-shell.css">
    <link rel="stylesheet" href="assets/css/new-connection.css">
    <script src="assets/js/portal-shell.js" defer></script>
    <script src="assets/js/theme.js" defer></script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<a href="#new-connection-content" class="nc-skip">Skip to content</a>
<div class="min-h-screen">
    <?php require __DIR__ . '/../partials/portal-sidebar.php'; ?>
    <main class="portal-main" id="new-connection-content" tabindex="-1">
        <?php require __DIR__ . '/../partials/portal-header.php'; ?>
        <div class="nc-content mx-auto max-w-screen-xl space-y-6 p-4 sm:p-6 lg:p-8">
            <section class="nc-panel rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="nc-introduction">
                <p class="nc-eyebrow text-xs font-bold uppercase tracking-widest text-paleco-700 dark:text-paleco-200">PALECO MCO Portal</p>
                <h2 id="nc-introduction" class="mt-2 text-2xl font-bold sm:text-3xl">Online New Electric Service Connection</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">Learn about the planned application process for a new electric service connection. You do not need an existing PALECO electric service account to access this page.</p>
                <p class="nc-notice mt-5 rounded-xl bg-paleco-50 p-4 text-sm leading-6 text-paleco-900 dark:bg-paleco-900 dark:text-paleco-100"><?php if ($previewMode ?? false): ?><strong>Development Preview — New Connection applications are not yet available.</strong> This page describes the planned process and does not accept applications.<?php else: ?><strong>Applications are not open on this page yet.</strong> This is an introduction to the planned process. Application forms and document uploads are planned, subject to approval.<?php endif; ?></p>
            </section>
            <section class="nc-panel rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="nc-signatures">
                <h2 id="nc-signatures" class="text-lg font-bold">Online preparation, handwritten signatures</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The planned process includes completing and previewing forms online. Subject to PALECO approval, personnel would prepare printed forms for handwritten signatures during an office visit, with payment handled offline. These procedures remain subject to confirmation; this page accepts no applications or payments.</p>
            </section>
            <section aria-labelledby="nc-stages">
                <h2 id="nc-stages" class="text-xl font-bold">The seven application stages</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">An overview of the planned process. These stages do not represent a submitted application or an approval.</p>
                <ol class="nc-stages mt-5" aria-label="Planned application stages">
                    <?php foreach ($stages as $index => [$title, $description]): ?>
                        <li class="nc-panel rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <span class="nc-number" aria-hidden="true"><?= $index + 1 ?></span>
                            <div>
                                <h3 class="font-bold"><span class="sr-only">Stage <?= $index + 1 ?>: </span><?= portalShellEscape($title) ?></h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"><?= portalShellEscape($description) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>
    </main>
</div>
</body>
</html>
