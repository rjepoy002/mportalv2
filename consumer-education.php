<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/consumer-education.php';

function educationEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userDisplayName = trim((string) ($_SESSION['user_name'] ?? ''));
$userEmail = trim((string) ($_SESSION['user_email'] ?? ''));

if ($userDisplayName === '') {
    $userDisplayName = $userEmail !== '' ? $userEmail : 'Member';
}

try {
    $playlistUrl = consumerEducationPlaylistUrl();
    $embedUrl = consumerEducationEmbedUrl();
    $playlist = consumerEducationPlaylist();
    $videos = $playlist['videos'];
    $initialVideo = consumerEducationInitialVideo($videos);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Consumer Education is temporarily unavailable.');
}

$navigation = [
    ['href' => 'dashboard.php?page=dashboard', 'label' => 'Dashboard'],
    ['href' => 'dashboard.php?page=bills', 'label' => 'My Bills'],
    ['href' => 'dashboard.php?page=account-settings', 'label' => 'Account & Settings'],
    ['href' => 'consumer-education.php', 'label' => 'Consumer Education'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consumer Education | PALECO MCO Portal</title>
    <link rel="icon" href="assets/images/logo.png">

    <script>
        (function () {
            const savedTheme = localStorage.getItem('mco-theme');
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle(
                'dark',
                savedTheme === 'dark' || (!savedTheme && systemDark)
            );
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        paleco: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0',
                            500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                            800: '#166534', 900: '#14532d'
                        }
                    },
                    boxShadow: {
                        card: '0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04)'
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="assets/css/portal-shell.css">
    <style>
        #videoPagination[hidden], #videoPlayerError[hidden], #videoGrid .education-video[hidden] { display: none; }
    </style>
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
    $portalActivePage = 'consumer-education';
    $portalUserDisplayName = $userDisplayName;
    $portalUserEmail = $userEmail;
    require __DIR__ . '/includes/portal-sidebar.php';
    ?>
    <main class="portal-main">
        <?php
        $portalPageTitle = 'Consumer Education';
        require __DIR__ . '/includes/portal-header.php';
        ?>

        <div class="mx-auto max-w-screen-xl space-y-6 p-4 sm:p-6 lg:p-8">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[.14em] text-paleco-700 dark:text-paleco-200">PALECO Resources</p>
                <h2 class="mt-1 text-2xl font-bold">Consumer Education</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Watch official PALECO infomercials, consumer advisories, and educational videos.</p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 p-5 dark:border-slate-800 sm:p-6">
                    <h2 class="text-lg font-bold">Official PALECO playlist</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Videos are loaded only when you choose to play them.</p>
                </div>

                <div class="p-5 sm:p-6">
                    <div id="videoPlayer" class="relative aspect-video overflow-hidden rounded-xl bg-slate-950" data-video-id="<?= educationEscape($initialVideo['id']) ?>" data-video-title="<?= educationEscape($initialVideo['title']) ?>">
                        <button id="playSelectedVideo" type="button" class="group relative block h-full w-full focus:outline-none focus:ring-4 focus:ring-inset focus:ring-paleco-500" aria-label="Play <?= educationEscape($initialVideo['title']) ?>">
                            <img src="<?= educationEscape($initialVideo['thumbnail']) ?>" alt="" class="h-full w-full object-cover" fetchpriority="high">
                            <span class="absolute inset-0 flex items-center justify-center bg-black/25">
                                <span class="rounded-full bg-paleco-700 px-7 py-4 text-lg font-bold text-white shadow-lg group-hover:bg-paleco-800">&#9654; Play</span>
                            </span>
                        </button>
                    </div>
                    <p id="selectedVideoTitle" class="mt-3 font-semibold" aria-live="polite"><?= educationEscape($initialVideo['title']) ?></p>
                    <p id="videoPlayerError" class="mt-2 text-sm text-slate-500 dark:text-slate-400" role="status" hidden></p>
                    <a id="selectedVideoYouTube" href="https://www.youtube.com/watch?v=<?= educationEscape($initialVideo['id']) ?>" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-sm font-semibold text-paleco-700 dark:text-paleco-200">Open on YouTube &#8599;</a>
                    <?php if ($playlist['stale']): ?>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400" role="status">Showing saved PALECO videos. The playlist may not be current; use the full playlist link below for the latest videos.</p>
                    <?php endif; ?>

                    <section class="mt-8" aria-labelledby="available-infomercials-heading">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.14em] text-paleco-700 dark:text-paleco-200">PALECO Videos</p>
                                <h3 id="available-infomercials-heading" class="mt-1 text-lg font-bold">Available Infomercials</h3>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Select a video to load it in the player above.</p>
                        </div>

                        <p id="videoRange" class="mt-4 text-sm text-slate-500 dark:text-slate-400" role="status" aria-live="polite"></p>

                        <div
                            id="videoGrid"
                            class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                            data-videos-per-page="6"
                        >
                            <?php if (!$videos): ?>
                                <p class="sm:col-span-2 lg:col-span-3 rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                    No infomercials are available at this time.
                                </p>
                            <?php endif; ?>

                            <?php foreach ($videos as $video): ?>
                                <?php
                                $videoId = (string) $video['id'];
                                $videoTitle = (string) $video['title'];
                                ?>
                                <button
                                    type="button"
                                    class="education-video group overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-sm transition hover:border-paleco-500 hover:shadow-card focus:outline-none focus:ring-4 focus:ring-paleco-500/20 dark:border-slate-700 dark:bg-slate-900"
                                    data-video-url="<?= educationEscape(consumerEducationVideoEmbedUrl($videoId)) ?>"
                                    data-video-title="<?= educationEscape($videoTitle) ?>"
                                    data-video-id="<?= educationEscape($videoId) ?>"
                                    data-video-thumbnail="<?= educationEscape($video['thumbnail']) ?>"
                                    aria-pressed="<?= $videoId === $initialVideo['id'] ? 'true' : 'false' ?>"
                                >
                                    <img
                                        src="<?= educationEscape($video['thumbnail']) ?>"
                                        alt=""
                                        loading="lazy"
                                        class="aspect-video w-full object-cover"
                                    >
                                    <span class="block p-4 text-sm font-semibold group-hover:text-paleco-700 dark:group-hover:text-paleco-200">
                                        <?= educationEscape($videoTitle) ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <nav
                            id="videoPagination"
                            class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            aria-label="Infomercial pages"
                            hidden
                        >
                            <div class="flex items-center gap-2">
                                <button id="previousVideoPage" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Previous infomercial page">Previous</button>
                                <button id="nextVideoPage" type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Next infomercial page">Next</button>
                            </div>
                            <div id="videoPageNumbers" class="flex flex-wrap gap-2" aria-label="Infomercial page numbers"></div>
                        </nav>
                    </section>
                    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800/50">
                        <p class="font-semibold">Having trouble viewing the player?</p>
                        <p class="mt-1 text-slate-500 dark:text-slate-400">Your network or browser may block embedded YouTube content. Open the playlist directly instead.</p>
                        <a href="<?= educationEscape($playlistUrl) ?>" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block font-semibold text-paleco-700 hover:text-paleco-800 dark:text-paleco-200">View Full Playlist on YouTube ↗</a>
                    </div>
                    <noscript>
                        <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Video playback requires JavaScript. Please use the Watch on YouTube link above.</p>
                    </noscript>
                </div>
            </section>

            <footer class="border-t border-slate-200 pt-5 text-center text-xs text-slate-400 dark:border-slate-800">PALECO MCO Portal · Local XAMPP Development Environment</footer>
        </div>
    </main>
</div>

<script src="assets/js/theme.js"></script>
<script src="assets/js/portal-shell.js"></script>
<script src="assets/js/consumer-education.js"></script>
</body>
</html>
