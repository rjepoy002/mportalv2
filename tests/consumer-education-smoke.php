<?php

require_once __DIR__ . '/../config/consumer-education.php';
putenv('YOUTUBE_API_KEY');
putenv('YOUTUBE_PLAYLIST_CACHE_DIR=' . __DIR__ . '/education-smoke-no-cache');

$expectedPlaylistUrl = 'https://www.youtube.com/playlist?list=PLQWmQ9rNn-E8';
$expectedEmbedUrl = 'https://www.youtube-nocookie.com/embed/videoseries?list=PLQWmQ9rNn-E8';

if (consumerEducationPlaylistUrl() !== $expectedPlaylistUrl) {
    throw new RuntimeException('Consumer Education playlist URL is incorrect.');
}

if (consumerEducationEmbedUrl() !== $expectedEmbedUrl) {
    throw new RuntimeException('Consumer Education embed URL is incorrect.');
}

if (str_contains(consumerEducationEmbedUrl(), 'autoplay')) {
    throw new RuntimeException('Consumer Education embed URL must not enable autoplay.');
}

$videos = consumerEducationVideos();
if (count($videos) !== 10) {
    throw new RuntimeException('Consumer Education did not expose the verified playlist entries.');
}

$firstVideo = $videos[0] ?? [];
if (($firstVideo['id'] ?? null) !== '6VcVyVUIKPc'
    || ($firstVideo['title'] ?? null) !== 'PALECO Collection Partners') {
    throw new RuntimeException('First verified Consumer Education video is incorrect.');
}

if (consumerEducationThumbnailUrl('6VcVyVUIKPc')
    !== 'https://i.ytimg.com/vi/6VcVyVUIKPc/hqdefault.jpg') {
    throw new RuntimeException('Consumer Education thumbnail URL is incorrect.');
}

session_save_path(sys_get_temp_dir());
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Education Smoke Test';
$_SESSION['user_email'] = 'education@example.test';

ob_start();
require __DIR__ . '/../consumer-education.php';
$html = ob_get_clean();

foreach ([
    'Consumer Education',
    'playSelectedVideo',
    'Available Infomercials',
    'data-videos-per-page="6"',
    'previousVideoPage',
    'nextVideoPage',
    'assets/js/consumer-education.js',
    'PALECO Collection Partners',
    'View Full Playlist on YouTube',
    'id="videoPlayer" class="relative aspect-video overflow-hidden rounded-xl bg-slate-950" data-video-id="6VcVyVUIKPc"',
    'Play PALECO Collection Partners',
    'https://i.ytimg.com/vi/6VcVyVUIKPc/hqdefault.jpg',
] as $expected) {
    if (!str_contains($html, $expected)) {
        throw new RuntimeException('Consumer Education page did not render: ' . $expected);
    }
}

if (str_contains($html, '<iframe')) {
    throw new RuntimeException('Consumer Education iframe was rendered before user interaction.');
}
if (str_contains($html, 'Load Videos') || str_contains($html, 'Ready to load official PALECO videos')) {
    throw new RuntimeException('Obsolete player placeholder remains.');
}

echo "Consumer Education smoke check passed.\n";
