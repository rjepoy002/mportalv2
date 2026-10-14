# Consumer Education playlist update

## Cause and implementation

The gallery previously read exactly ten hardcoded entries from
`PALECO_CONSUMER_EDUCATION_VIDEOS` in `config/consumer-education.php`. Pagination
operated on those ten buttons; there was no backend API retrieval. The main player
was independently initialized as a "Load Videos" placeholder. The playlist ID
was already correct, so it was retained exactly: `PLQWmQ9rNn-E8`.

`educationFetchPlaylist()` in `includes/education-playlist.php` now retrieves every
playlistItems page, following nextPageToken with maxResults=50. It removes duplicate
IDs and private/deleted placeholders, then checks videos in batches of 50 for
public/unlisted availability and embeddability. Missing/deleted/private and explicitly
non-embeddable entries are omitted. Video metadata comes from Google's response,
while the gallery retains the playlist's ordering. There is no ten-video or fixed
total-count limit. A repeated pagination token or malformed response is a failure,
not a partial successful synchronization. This follows the documented
[playlistItems pagination](https://developers.google.com/youtube/v3/docs/playlistItems/list)
and [videos lookup](https://developers.google.com/youtube/v3/docs/videos/list).

`consumerEducationPlaylist()` refreshes a private file cache after six hours on the
next page request. This is request-driven synchronization, not a scheduled job.
A nonblocking file lock prevents concurrent refreshes; failed attempts back off
for ten minutes. Only complete normalized successful results, including genuinely
empty results, replace the cache using a temporary file and rename. Failures retain
the last success. Without a key or usable cache, the existing ten local entries are
retained as required; their titles were preserved rather than invented or scraped.
Saved/fallback data displays a notice that the playlist may not be current.

The server renders the first listed video as an immediately visible thumbnail and
Play button, matching the user's updated preference. In the saved collection this
is `6VcVyVUIKPc` ("PALECO Collection Partners"); synchronized data uses its first
available entry. The earlier explicitly requested `hRx-hV4XoJM` selection was
replaced because it was last in the saved collection. The gallery order is preserved.
With an empty gallery the configured `hRx-hV4XoJM` preview and official links
remain accessible, but empty pagination is hidden. The chosen ID appears immediately
without waiting for JavaScript. No iframe or audio autoplay occurs on initial load.

Play creates one YouTube privacy-enhanced iframe for the selected video, with the
exact official playlist ID. The iframe API is loaded only after Play to detect
embedding errors. Selecting a card stops/removes the old player and shows the new
thumbnail; Play starts that selection. Repeated clicks do not duplicate players.
Error/timeout states provide an "Open on YouTube" link and leave other cards usable.
The [YouTube iframe API](https://developers.google.com/youtube/iframe_api_reference)
supplies playback error events; iframe errors alone are insufficient for cross-origin
embedding restrictions. Missing thumbnails fall back to YouTube's high-resolution
thumbnail, then hide the broken image while preserving the card/Play interaction.

The existing navy/dark theme, green controls, PALECO branding, navigation, rounded
cards, and responsive one/two/three-column grid remain. Pagination shows six entries
per page, accurate ranges/page numbers, boundary-disabled Previous/Next controls,
and recalculates/clamps if the gallery's children change. The player retains 16:9.

## Files changed

| File | Change |
| --- | --- |
| `config/consumer-education.php` | Exact initial-video constant and playlist service include; preserved local entries and URL helpers. |
| `includes/education-playlist.php` | New server-side API transport, complete retrieval, metadata validation, availability filtering, cache, backoff and fallback selection. |
| `consumer-education.php` | Use synchronized/fallback data, render initial preview, currentness notice, actual thumbnails, selected title/direct watch link, and external controller. |
| `assets/js/consumer-education.js` | New player lifecycle/error handling, selection, thumbnail fallback and six-card pagination controller. |
| `.env.example` | YouTube key and private cache-directory documentation. |
| `tests/consumer-education-smoke.php` | Update expectations for immediate preview and removal of the old placeholder. |
| `tests/education-playlist-test.php` | Fake transport tests for API pagination, normalization, filtering, cache/fallback and privacy. |
| `tests/education-player-test.js` | Pagination boundary/count cases and player lifecycle/selection tests with DOM/API doubles. |
| `tests/education-playlist-sync.php` | CLI-only operator check that explicitly distinguishes a real retrieval, cache use, and missing-key skip. |
| `tests/consumer-education-notes.md` | This implementation/configuration/validation guide. |

No new dependencies, database changes or changes to billing, enrollment, account
settings, Google login or password recovery were introduced. The API key is only
read by PHP, never serialized to HTML, frontend code, normalized cache or logs.
Transport exception messages and raw API responses are not logged. Failure categories
are allowlisted; TLS verification is enabled and redirects are not followed.

## Configuration and deployment

1. Enable YouTube Data API v3 in a Google Cloud project and create a suitable key.
   See [Google's setup instructions](https://developers.google.com/youtube/v3/getting-started).
   Restrict it to the YouTube Data API and the server's outbound IP where practical,
   following [Google's key guidance](https://docs.cloud.google.com/docs/authentication/api-keys-best-practices).
   Google login OAuth configuration is unrelated and unchanged.
2. Set `YOUTUBE_API_KEY` in the actual PHP/Apache environment. `.env.example` is
   documentation; copying it to `.env` does not load variables. Keep actual keys
   outside Git and publicly served files. Restart Apache/PHP after service-environment
   changes. PHP needs cURL, trusted CA certificates and outbound HTTPS to Google.
3. Set `YOUTUBE_PLAYLIST_CACHE_DIR` to a private directory outside the web root,
   writable by the PHP service account. Otherwise the default is
   `sys_get_temp_dir()/mportal-education`. Protect it with service permissions or
   Windows ACLs. All workers on the server should use the same directory. Multiple
   servers can use separate caches or shared storage with reliable file locking.
4. Deploy the PHP service include, config, page and JavaScript together. There is
   no schema migration or dependency install. Keep tests out of the public deployment.
5. Open Consumer Education as an authenticated user. A cold configured cache
   triggers retrieval automatically; subsequent requests use it for six hours.
   On refresh failure inspect `[CONSUMER_EDUCATION] playlist_sync_failed` in the
   private PHP log. No API key is needed for local fallback or iframe playback.
6. Optional operator check: `php tests/education-playlist-sync.php`. It must run
   with the same key/cache environment as the service. `Source: api; stale: no`
   verifies new retrieval; `Source: cache` only verifies cache use. A skip is not
   successful synchronization. This operator command may make real API requests;
   unit tests do not.
7. No cache clearing is required for normal updates. To force a refresh, stop
   concurrent refreshes and remove only `paleco-playlist.json` and `refresh.lock`
   from the configured private cache directory, then reload with a valid key.
   Clearing the JSON loses the stale fallback, so retain a backup if needed.
   Reload the browser after deploying the new controller; a hard refresh may be
   needed if its static files are aggressively cached. To roll back, restore the
   previous config/page as a coherent set; the new helper/JS/cache become unused.

## Validation and limits

Passed on the local workspace:

- PHP syntax checks for every changed PHP file.
- `php tests/education-playlist-test.php`: 61 videos over two playlist pages,
  two availability batches, duplicate elimination, order/actual-title preservation,
  private/deleted/missing/non-embeddable filtering, thumbnail-host validation,
  fresh cache, stale retention, retry backoff, simulated credential/rate/network/
  playlist errors, empty/corrupt cache and sensitive-data exclusion.
- `node tests/education-player-test.js`: initial selection, missing initial ID,
  fewer than six, exactly six/twelve, 13/61/zero videos, page navigation/clamping,
  no initial iframe, correct embed IDs, repeated Play, previous-player destruction,
  selected card/thumbnail/title/watch-link changes, and embedding-error fallback.
- JavaScript syntax checks for controller and JS test. Standalone Node is absent
  from PATH; checks used VS Code's embedded Node via ELECTRON_RUN_AS_NODE. It emitted
  a sandbox crashpad warning, but the tests completed successfully.
- Consumer Education and dashboard smoke tests, billing summary fixtures, SOA
  service tests, `php tests/paleco-readonly-contract.php`, and `git diff --check`.

**Live synchronization was not verified:** YOUTUBE_API_KEY is unset in the current
process. The operator check explicitly skipped. The web tool also could not fetch
the supplied YouTube page; no page scraping or substituted playlist was used. The
61-video result is a test fixture, not a claim about the live playlist's count.
The current live playlist's availability, titles, count and embed behavior require
verification after configuration. No deployment or commit was performed.

No browser automation capability was available, so rendered mobile appearance,
real iframe playback, blocked networks/ad blockers and visual thumbnail quality
still need manual checks. Open at desktop and approximately 375px mobile width;
confirm preview visibility/no audio, Play, selecting another card while playing,
correct count/page boundaries, keyboard focus, and direct YouTube links. Compare
gallery ordering/count against the official playlist after a confirmed API retrieval.
Temporarily deny API access after warming the cache and verify the saved notice/data.

API calls have bounded per-request timeouts and a synchronization deadline. Very
large/slow playlists fail safely rather than publishing an incomplete cache. Regional,
age or browser embedding restrictions may not be identifiable from API status fields;
the player error/direct-link fallback covers those cases where YouTube reports them.
Platform policies or removed videos can change after caching. Without an API key,
automatic discovery of additional videos is unavailable and the fallback remains
the original ten entries, with the full official playlist linked prominently.
