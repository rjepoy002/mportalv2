<?php

function educationThumbnail(array $thumbnails, string $id): string
{
    foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
        $url = $thumbnails[$size]['url'] ?? '';
        $parts = is_string($url) ? parse_url($url) : false;
        if ($parts && ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), ['i.ytimg.com', 'img.youtube.com'], true)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])) return $url;
    }
    return consumerEducationThumbnailUrl($id);
}

/** Fixed Google endpoint; never log URL, key, response body or exception message. */
function educationApiRequest(string $resource, array $parameters, string $key): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('network_unavailable');
    $parameters['key'] = $key;
    $curl = curl_init('https://www.googleapis.com/youtube/v3/' . $resource . '?' . http_build_query($parameters));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    try {
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($body === false) throw new RuntimeException('network_failure');
        if ($status !== 200) {
            $error = json_decode($body, true);
            $reason = $error['error']['errors'][0]['reason'] ?? '';
            $category = match (true) {
                $status === 429 || in_array($reason, ['quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded'], true) => 'rate_limited',
                $status === 401 || in_array($reason, ['keyInvalid', 'accessNotConfigured', 'ipRefererBlocked'], true) => 'invalid_credentials',
                $status === 404 => 'playlist_unavailable',
                default => 'api_rejected',
            };
            throw new RuntimeException($category);
        }
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            throw new RuntimeException('invalid_api_response');
        }
        return $data;
    } finally { curl_close($curl); }
}

/** Fetch every playlist page, then check public availability in batches of 50. */
function educationFetchPlaylist(string $key, ?callable $transport = null): array
{
    $request = $transport ?? 'educationApiRequest';
    $ordered = [];
    $pageToken = '';
    $seenPages = [];
    $deadline = microtime(true) + 30;
    do {
        if (microtime(true) > $deadline) throw new RuntimeException('sync_timeout');
        $parameters = ['part' => 'snippet,contentDetails,status',
            'playlistId' => consumerEducationPlaylistId(), 'maxResults' => 50];
        if ($pageToken !== '') $parameters['pageToken'] = $pageToken;
        $page = $request('playlistItems', $parameters, $key);
        if (!isset($page['items']) || !is_array($page['items'])) throw new RuntimeException('invalid_api_response');
        foreach ($page['items'] as $item) {
            $id = $item['contentDetails']['videoId'] ?? $item['snippet']['resourceId']['videoId'] ?? '';
            $title = trim((string) ($item['snippet']['title'] ?? ''));
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)
                || $title === '' || in_array(strtolower($title), ['private video', 'deleted video'], true)
                || ($item['status']['privacyStatus'] ?? '') === 'private') continue;
            if (!isset($ordered[$id])) $ordered[$id] = true;
        }
        $pageToken = $page['nextPageToken'] ?? '';
        if (!is_string($pageToken) || ($pageToken !== '' && isset($seenPages[$pageToken]))) {
            throw new RuntimeException('invalid_api_pagination');
        }
        $seenPages[$pageToken] = true;
    } while ($pageToken !== '');

    $available = [];
    foreach (array_chunk(array_keys($ordered), 50) as $ids) {
        if (microtime(true) > $deadline) throw new RuntimeException('sync_timeout');
        $page = $request('videos', ['part' => 'snippet,status', 'id' => implode(',', $ids)], $key);
        if (!isset($page['items']) || !is_array($page['items'])) throw new RuntimeException('invalid_api_response');
        foreach ($page['items'] as $video) {
            $id = $video['id'] ?? '';
            $title = trim((string) ($video['snippet']['title'] ?? ''));
            if (!is_string($id) || !isset($ordered[$id]) || $title === ''
                || !in_array($video['status']['privacyStatus'] ?? '', ['public', 'unlisted'], true)
                || ($video['status']['embeddable'] ?? false) !== true
                || in_array($video['status']['uploadStatus'] ?? '', ['deleted', 'failed', 'rejected'], true)) continue;
            $available[$id] = ['id' => $id, 'title' => $title,
                'thumbnail' => educationThumbnail($video['snippet']['thumbnails'] ?? [], $id)];
        }
    }
    $result = [];
    foreach ($ordered as $id => $_) if (isset($available[$id])) $result[] = $available[$id];
    return $result;
}

function educationLocalVideos(): array
{
    return array_map(fn ($video) => $video + ['thumbnail' => consumerEducationThumbnailUrl($video['id'])], consumerEducationVideos());
}

/** A cache contains only public normalized metadata; validate it before rendering. */
function educationReadCache(string $file): ?array
{
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data) || ($data['playlist_id'] ?? '') !== consumerEducationPlaylistId()
        || !is_int($data['fetched_at'] ?? null) || !is_array($data['videos'] ?? null)) return null;
    $seen = [];
    foreach ($data['videos'] as $video) {
        if (!is_array($video) || !is_string($video['id'] ?? null)
            || !preg_match('/^[A-Za-z0-9_-]{11}$/', $video['id']) || isset($seen[$video['id']])
            || !is_string($video['title'] ?? null) || trim($video['title']) === '') return null;
        $seen[$video['id']] = true;
    }
    $data['videos'] = array_map(function ($video) {
        $video['thumbnail'] = educationThumbnail(['high' => ['url' => $video['thumbnail'] ?? '']], $video['id']);
        return array_intersect_key($video, array_flip(['id', 'title', 'thumbnail']));
    }, $data['videos']);
    return $data;
}

/** Six-hour lazy refresh; nonblocking lock prevents duplicate refreshes across workers. */
function consumerEducationPlaylist(?callable $transport = null, ?int $now = null): array
{
    $now ??= time();
    $key = trim(getenv('YOUTUBE_API_KEY') ?: '');
    $directory = getenv('YOUTUBE_PLAYLIST_CACHE_DIR') ?: sys_get_temp_dir() . '/mportal-education';
    $file = $directory . '/paleco-playlist.json';
    $cache = educationReadCache($file);
    $fallback = function () use (&$cache) {
        return ['videos' => $cache ? $cache['videos'] : educationLocalVideos(),
            'source' => $cache ? 'cache' : 'local', 'stale' => true];
    };
    if ($key === '') return $fallback();
    if ($cache && $cache['fetched_at'] > $now - 21600) {
        return ['videos' => $cache['videos'], 'source' => 'cache', 'stale' => false];
    }
    $lock = null;
    try {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('cache_unavailable');
        }
        $lock = @fopen($directory . '/refresh.lock', 'c+');
        if (!$lock) throw new RuntimeException('cache_unavailable');
        if (!flock($lock, LOCK_EX | LOCK_NB)) return $fallback();
        $cache = educationReadCache($file);
        if ($cache && $cache['fetched_at'] > $now - 21600) {
            return ['videos' => $cache['videos'], 'source' => 'cache', 'stale' => false];
        }
        rewind($lock);
        $lastAttempt = (int) stream_get_contents($lock);
        // Back off after failures instead of consuming quota on every page view.
        if ($lastAttempt > $now - 600) return $fallback();
        rewind($lock);
        if (!ftruncate($lock, 0) || fwrite($lock, (string) $now) === false || !fflush($lock)) {
            throw new RuntimeException('cache_unavailable');
        }
        $videos = educationFetchPlaylist($key, $transport);
        $payload = ['playlist_id' => consumerEducationPlaylistId(), 'fetched_at' => $now, 'videos' => $videos];
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        try {
            if (file_put_contents($temporary, json_encode($payload, JSON_THROW_ON_ERROR), LOCK_EX) === false
                || !rename($temporary, $file)) throw new RuntimeException('cache_write_failed');
        } finally { if (is_file($temporary)) unlink($temporary); }
        return ['videos' => $videos, 'source' => 'api', 'stale' => false];
    } catch (Throwable $e) {
        // Never copy transport exceptions: they may include the API key/request URL.
        $categories = ['network_unavailable', 'network_failure', 'rate_limited', 'invalid_credentials',
            'playlist_unavailable', 'api_rejected', 'invalid_api_response', 'invalid_api_pagination',
            'sync_timeout', 'cache_unavailable', 'cache_write_failed'];
        $category = in_array($e->getMessage(), $categories, true) ? $e->getMessage() : 'sync_failure';
        error_log('[CONSUMER_EDUCATION] playlist_sync_failed category=' . $category . ' exception_type=' . get_class($e));
        return $fallback();
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

function consumerEducationInitialVideo(array $videos): array
{
    if ($videos) return $videos[0];
    foreach (educationLocalVideos() as $video) if ($video['id'] === PALECO_CONSUMER_EDUCATION_INITIAL_VIDEO_ID) return $video;
    throw new RuntimeException('Initial Consumer Education video is not configured.');
}
