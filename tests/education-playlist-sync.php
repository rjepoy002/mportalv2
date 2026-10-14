<?php
// Operator check, not an automated test: uses the configured real YouTube API.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/consumer-education.php';
if (trim(getenv('YOUTUBE_API_KEY') ?: '') === '') {
    echo "SKIP live playlist synchronization: YOUTUBE_API_KEY is not configured in this PHP process.\n";
    exit;
}
$playlist = consumerEducationPlaylist();
echo 'Playlist: ' . consumerEducationPlaylistId() . PHP_EOL;
echo 'Source: ' . $playlist['source'] . '; stale: ' . ($playlist['stale'] ? 'yes' : 'no')
    . '; available videos: ' . count($playlist['videos']) . PHP_EOL;
if ($playlist['stale']) exit(1);
if ($playlist['source'] === 'cache') echo "Using the cache; this run did not perform a new API retrieval.\n";
else echo "Live API retrieval completed and cached.\n";
