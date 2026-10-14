<?php
require_once dirname(__DIR__) . '/config/consumer-education.php';
function educationAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$directory = __DIR__ . '/education-fixtures-' . bin2hex(random_bytes(5));
mkdir($directory, 0700);
$variables = ['YOUTUBE_API_KEY', 'YOUTUBE_PLAYLIST_CACHE_DIR'];
$original = [];
foreach ($variables as $variable) $original[$variable] = getenv($variable);
$originalLog = ini_set('error_log', $directory . '/events.log');
putenv('YOUTUBE_PLAYLIST_CACHE_DIR=' . $directory);
$ids = array_map(fn ($i) => 'v' . str_pad((string) $i, 10, '0', STR_PAD_LEFT), range(0, 60));
$calls = [];
$transport = function ($resource, $parameters, $key) use ($ids, &$calls) {
    $calls[] = [$resource, $parameters];
    educationAssert($key === 'sentinel-private-api-key', 'Key stays in server transport');
    if ($resource === 'playlistItems') {
        educationAssert($parameters['playlistId'] === 'PLQWmQ9rNn-E8' && $parameters['maxResults'] === 50, 'Exact playlist and API page size');
        $second = isset($parameters['pageToken']);
        if ($second) educationAssert($parameters['pageToken'] === 'page-two', 'Follow nextPageToken');
        $subset = $second ? array_merge([$ids[0]], array_slice($ids, 50)) : array_slice($ids, 0, 50);
        $items = array_map(fn ($id) => ['contentDetails' => ['videoId' => $id], 'snippet' => ['title' => 'Playlist ' . $id]], $subset);
        if ($second) {
            $items[] = ['contentDetails' => ['videoId' => 'Private0000'], 'snippet' => ['title' => 'Private video']];
            $items[] = ['contentDetails' => ['videoId' => 'Deleted0000'], 'snippet' => ['title' => 'Deleted video']];
            $items[] = ['contentDetails' => ['videoId' => 'bad'], 'snippet' => ['title' => 'Invalid']];
        }
        return ['items' => $items] + ($second ? [] : ['nextPageToken' => 'page-two']);
    }
    $requested = explode(',', $parameters['id']);
    educationAssert(count($requested) <= 50, 'Availability requests batched by 50');
    // Google may return a different order. Preserve the playlist order, not this order.
    return ['items' => array_map(fn ($id) => ['id' => $id,
        'snippet' => ['title' => 'Actual ' . $id, 'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg']]],
        'status' => ['privacyStatus' => 'public', 'embeddable' => true, 'uploadStatus' => 'processed']], array_reverse($requested))];
};
try {
    putenv('YOUTUBE_API_KEY');
    $fallback = consumerEducationPlaylist(fn () => throw new RuntimeException('Must not request without key'));
    educationAssert($fallback['source'] === 'local' && $fallback['stale'] && count($fallback['videos']) === 10, 'Retain all existing local entries without a key');
    educationAssert(consumerEducationInitialVideo($fallback['videos'])['id'] === '6VcVyVUIKPc', 'First listed video selected without reordering gallery');
    putenv('YOUTUBE_API_KEY=sentinel-private-api-key');
    $now = 1000000;
    $result = consumerEducationPlaylist($transport, $now);
    educationAssert($result['source'] === 'api' && !$result['stale'] && count($result['videos']) === 61, 'Retrieve more than 50 unique videos');
    educationAssert(array_column($result['videos'], 'id') === $ids, 'Preserve playlist order and remove duplicates');
    educationAssert(count($calls) === 4, 'Two playlist pages and two availability batches');
    educationAssert($result['videos'][0]['title'] === 'Actual ' . $ids[0], 'Use actual API title');
    educationAssert(consumerEducationInitialVideo($result['videos'])['id'] === $ids[0], 'Missing initial video selects first available');
    $cached = consumerEducationPlaylist(fn () => throw new RuntimeException('Fresh cache should not request'), $now + 100);
    educationAssert(!$cached['stale'] && $cached['videos'] === $result['videos'], 'Six-hour fresh cache');
    $failures = 0;
    $failure = function () use (&$failures) { $failures++; throw new RuntimeException('sentinel-private-api-key https://secret.example'); };
    $stale = consumerEducationPlaylist($failure, $now + 21601);
    educationAssert($stale['stale'] && $stale['videos'] === $result['videos'], 'Keep last successful playlist during API failure');
    consumerEducationPlaylist($failure, $now + 21602);
    educationAssert($failures === 1, 'Backoff avoids quota storms');
    educationAssert(!str_contains(file_get_contents($directory . '/events.log'), 'sentinel-private-api-key'), 'No key in logs');
    educationAssert(!str_contains(file_get_contents($directory . '/paleco-playlist.json'), 'sentinel-private-api-key'), 'No key in cache');
    $attemptTime = $now + 22202;
    foreach (['invalid_credentials', 'rate_limited', 'playlist_unavailable', 'network_failure'] as $category) {
        $failed = consumerEducationPlaylist(function () use ($category) { throw new RuntimeException($category); }, $attemptTime);
        educationAssert($failed['stale'] && $failed['videos'] === $result['videos'], 'Preserve cache during ' . $category);
        educationAssert(str_contains(file_get_contents($directory . '/events.log'), 'category=' . $category), 'Safe operational failure category');
        $attemptTime += 601;
    }
    $empty = consumerEducationPlaylist(fn () => ['items' => []], $attemptTime);
    educationAssert($empty['videos'] === [] && !$empty['stale'], 'Successful empty playlist is cached as empty');
    educationAssert(consumerEducationInitialVideo([])['id'] === 'hRx-hV4XoJM', 'Initial remains accessible when gallery empty');
    $filtered = educationFetchPlaylist('fake', function ($resource) {
        if ($resource === 'playlistItems') return ['items' => array_map(fn ($id) => ['contentDetails' => ['videoId' => $id], 'snippet' => ['title' => 'Entry']], ['Available00', 'NoEmbed0000', 'Private0000', 'Missing0000'])];
        return ['items' => [
            ['id' => 'Available00', 'snippet' => ['title' => 'Available', 'thumbnails' => ['high' => ['url' => 'https://evil.example/track']]], 'status' => ['privacyStatus' => 'public', 'embeddable' => true]],
            ['id' => 'NoEmbed0000', 'snippet' => ['title' => 'Blocked'], 'status' => ['privacyStatus' => 'public', 'embeddable' => false]],
            ['id' => 'Private0000', 'snippet' => ['title' => 'Private'], 'status' => ['privacyStatus' => 'private', 'embeddable' => true]],
        ]];
    });
    educationAssert(count($filtered) === 1 && $filtered[0]['id'] === 'Available00', 'Filter private, missing and non-embeddable videos');
    educationAssert($filtered[0]['thumbnail'] === consumerEducationThumbnailUrl('Available00'), 'Reject unrelated thumbnail hosts');
    $repeated = false;
    try { educationFetchPlaylist('fake', fn () => ['items' => [], 'nextPageToken' => 'repeat']); }
    catch (RuntimeException $e) { $repeated = true; }
    educationAssert($repeated, 'Repeated API page token fails safely');
    $malformed = false;
    try { educationFetchPlaylist('fake', fn () => ['unexpected' => []]); }
    catch (RuntimeException $e) { $malformed = true; }
    educationAssert($malformed, 'Malformed API response is not cached as success');
    file_put_contents($directory . '/paleco-playlist.json', 'broken');
    putenv('YOUTUBE_API_KEY');
    educationAssert(consumerEducationPlaylist()['source'] === 'local', 'Corrupt cache falls back safely');
    echo "Education playlist API pagination, availability, cache, fallback and privacy tests passed (fake transport).\n";
} finally {
    foreach ($original as $variable => $value) $value === false ? putenv($variable) : putenv($variable . '=' . $value);
    ini_set('error_log', $originalLog);
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
