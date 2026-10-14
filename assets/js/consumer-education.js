(function () {
    'use strict';
    const PALECO_PLAYLIST_ID = 'PLQWmQ9rNn-E8';
    const VIDEOS_PER_PAGE = 6;

    function pageState(count, page) {
        const totalPages = Math.ceil(count / VIDEOS_PER_PAGE);
        const currentPage = Math.max(1, Math.min(Number(page) || 1, totalPages || 1));
        return { totalPages, currentPage, start: (currentPage - 1) * VIDEOS_PER_PAGE,
            end: Math.min(currentPage * VIDEOS_PER_PAGE, count) };
    }
    function initialSelection(videos) {
        return videos[0] || null;
    }
    function embedUrl(id, origin) {
        if (!/^[A-Za-z0-9_-]{11}$/.test(id)) throw new Error('Invalid video ID');
        const url = new URL(`https://www.youtube-nocookie.com/embed/${id}`);
        url.search = new URLSearchParams({ autoplay: '1', rel: '0', enablejsapi: '1',
            origin, list: PALECO_PLAYLIST_ID }).toString();
        return url.toString();
    }
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { pageState, initialSelection, embedUrl };
    }
    if (typeof document === 'undefined') return;

    const player = document.getElementById('videoPlayer');
    const grid = document.getElementById('videoGrid');
    if (!player || !grid) return;
    const range = document.getElementById('videoRange');
    const pagination = document.getElementById('videoPagination');
    const previous = document.getElementById('previousVideoPage');
    const next = document.getElementById('nextVideoPage');
    const numbers = document.getElementById('videoPageNumbers');
    const title = document.getElementById('selectedVideoTitle');
    const watchLink = document.getElementById('selectedVideoYouTube');
    const error = document.getElementById('videoPlayerError');
    let currentPage = 1;
    let selectedId = player.dataset.videoId;
    let generation = 0;
    let youtubePlayer = null;
    let readyTimer;
    let apiPromise;

    function buttons() { return Array.from(grid.querySelectorAll('.education-video')); }
    function showError() {
        error.textContent = 'This video could not be played here. Open on YouTube or select another video.';
        error.hidden = false;
    }
    function thumbnailFallback(img, id) {
        img.addEventListener('error', () => {
            if (!img.dataset.fallback && /^[A-Za-z0-9_-]{11}$/.test(id)) {
                img.dataset.fallback = 'true';
                img.src = `https://i.ytimg.com/vi/${id}/hqdefault.jpg`;
            } else {
                img.style.visibility = 'hidden';
            }
        });
    }
    function renderPagination() {
        const cards = buttons();
        const state = pageState(cards.length, currentPage);
        currentPage = state.currentPage;
        cards.forEach((card, index) => { card.hidden = index < state.start || index >= state.end; });
        range.textContent = cards.length ? `Showing ${state.start + 1}\u2013${state.end} of ${cards.length} videos` : 'No videos available.';
        previous.disabled = currentPage === 1;
        next.disabled = !state.totalPages || currentPage === state.totalPages;
        pagination.hidden = state.totalPages <= 1;
        numbers.replaceChildren();
        for (let page = 1; page <= state.totalPages; page++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(page);
            button.className = page === currentPage
                ? 'rounded-lg bg-paleco-700 px-3 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-4 focus:ring-paleco-500/30'
                : 'rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-4 focus:ring-paleco-500/20 dark:border-slate-700';
            button.setAttribute('aria-label', `Go to infomercial page ${page}`);
            if (page === currentPage) button.setAttribute('aria-current', 'page');
            button.addEventListener('click', () => { currentPage = page; renderPagination(); });
            numbers.appendChild(button);
        }
    }
    function markSelection() {
        buttons().forEach(button => {
            const selected = button.dataset.videoId === selectedId;
            button.setAttribute('aria-pressed', String(selected));
            ['ring-2', 'ring-paleco-500', 'border-paleco-500'].forEach(cls => button.classList.toggle(cls, selected));
        });
    }
    function clearPlayer() {
        generation++;
        clearTimeout(readyTimer);
        if (youtubePlayer) { youtubePlayer.destroy(); youtubePlayer = null; }
        error.hidden = true;
    }
    function youtubeApi() {
        if (window.YT?.Player) return Promise.resolve(window.YT);
        if (apiPromise) return apiPromise;
        apiPromise = new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(new Error('Player unavailable')), 15000);
            const prior = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                clearTimeout(timeout);
                resolve(window.YT);
                if (typeof prior === 'function') prior();
            };
            const script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            script.onerror = () => { clearTimeout(timeout); reject(new Error('Player unavailable')); };
            document.head.appendChild(script);
        });
        return apiPromise;
    }
    function playVideo() {
        if (player.querySelector('iframe')) return;
        clearPlayer();
        const currentGeneration = generation;
        const iframe = document.createElement('iframe');
        iframe.src = embedUrl(selectedId, window.location.origin);
        iframe.title = title.textContent;
        iframe.className = 'h-full w-full rounded-xl';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.allowFullscreen = true;
        iframe.addEventListener('error', () => { if (generation === currentGeneration) showError(); });
        player.replaceChildren(iframe);
        readyTimer = setTimeout(() => { if (generation === currentGeneration) showError(); }, 20000);
        youtubeApi().then(YT => {
            if (generation !== currentGeneration) return;
            youtubePlayer = new YT.Player(iframe, { events: {
                onReady: () => { if (generation === currentGeneration) { clearTimeout(readyTimer); error.hidden = true; } },
                onError: () => { if (generation === currentGeneration) { clearTimeout(readyTimer); showError(); } }
            } });
        }).catch(() => { if (generation === currentGeneration) showError(); });
    }
    function selectVideo(button) {
        if (button.dataset.videoId === selectedId && player.querySelector('iframe')) return;
        clearPlayer();
        selectedId = button.dataset.videoId;
        title.textContent = button.dataset.videoTitle;
        watchLink.href = `https://www.youtube.com/watch?v=${selectedId}`;
        const preview = document.createElement('button');
        preview.type = 'button';
        preview.className = 'group relative block h-full w-full focus:outline-none focus:ring-4 focus:ring-inset focus:ring-paleco-500';
        preview.setAttribute('aria-label', `Play ${title.textContent}`);
        const img = document.createElement('img');
        img.src = button.dataset.videoThumbnail;
        img.alt = '';
        img.className = 'h-full w-full object-cover';
        thumbnailFallback(img, selectedId);
        const overlay = document.createElement('span');
        overlay.className = 'absolute inset-0 flex items-center justify-center bg-black/25';
        const label = document.createElement('span');
        label.className = 'rounded-full bg-paleco-700 px-7 py-4 text-lg font-bold text-white shadow-lg group-hover:bg-paleco-800';
        label.textContent = '\u25b6 Play';
        overlay.appendChild(label);
        preview.append(img, overlay);
        preview.addEventListener('click', playVideo);
        player.replaceChildren(preview);
        markSelection();
        // Keep card selection lightweight; playback begins with an explicit Play click.
    }
    document.getElementById('playSelectedVideo')?.addEventListener('click', playVideo);
    thumbnailFallback(player.querySelector('img'), selectedId);
    buttons().forEach(button => thumbnailFallback(button.querySelector('img'), button.dataset.videoId));
    grid.addEventListener('click', event => {
        const button = event.target.closest('.education-video');
        if (button && grid.contains(button)) selectVideo(button);
    });
    previous.addEventListener('click', () => { currentPage--; renderPagination(); });
    next.addEventListener('click', () => { currentPage++; renderPagination(); });
    // Recalculate counts and clamp the page if a later refresh replaces the gallery.
    new MutationObserver(() => { markSelection(); renderPagination(); }).observe(grid, { childList: true });
    markSelection();
    renderPagination();
})();
