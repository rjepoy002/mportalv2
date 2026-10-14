'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { pageState, initialSelection, embedUrl } = require('../assets/js/consumer-education.js');
for (const [count, pages, end] of [[0, 0, 0], [3, 1, 3], [6, 1, 6], [12, 2, 6], [61, 11, 6]]) {
    const first = pageState(count, 1);
    assert.equal(first.totalPages, pages);
    assert.equal(first.end, end);
    const last = pageState(count, 999);
    assert.equal(last.currentPage, pages || 1);
    assert.equal(last.end, count);
}
assert.equal(pageState(3, 5).currentPage, 1);
assert.equal(initialSelection([{ id: 'other' }, { id: 'hRx-hV4XoJM' }]).id, 'other');
assert.equal(initialSelection([{ id: 'other' }]).id, 'other');
assert.equal(initialSelection([]), null);
assert.throws(() => embedUrl('bad', 'https://portal.example.com'));
const url = new URL(embedUrl('hRx-hV4XoJM', 'https://portal.example.com'));
assert.equal(url.pathname, '/embed/hRx-hV4XoJM');
assert.equal(url.searchParams.get('list'), 'PLQWmQ9rNn-E8');
assert.equal(url.searchParams.get('autoplay'), '1');

class Element {
    constructor(tag = 'div') {
        this.tag = tag; this.children = []; this.dataset = {}; this.events = {}; this.attributes = {};
        this.hidden = false; this.textContent = ''; this.style = {}; this.classes = new Set();
        this.classList = { toggle: (name, enabled) => enabled ? this.classes.add(name) : this.classes.delete(name) };
    }
    appendChild(child) { this.children.push(child); return child; }
    append(...children) { children.forEach(child => this.appendChild(child)); }
    replaceChildren(...children) { this.children = children; }
    setAttribute(name, value) { this.attributes[name] = value; }
    addEventListener(name, handler) { (this.events[name] ||= []).push(handler); }
    fire(name, target = this) { (this.events[name] || []).forEach(handler => handler({ target })); }
    querySelector(tag) { return this.children.find(child => child.tag === tag) || this.children.map(child => child.querySelector(tag)).find(Boolean) || null; }
    querySelectorAll() { return this.children.filter(child => child.dataset.videoId); }
    closest() { return this.dataset.videoId ? this : null; }
    contains(child) { return this.children.includes(child); }
}
const names = ['videoPlayer', 'videoGrid', 'videoRange', 'videoPagination', 'previousVideoPage', 'nextVideoPage', 'videoPageNumbers', 'selectedVideoTitle', 'selectedVideoYouTube', 'videoPlayerError', 'playSelectedVideo'];
const elements = Object.fromEntries(names.map(name => [name, new Element()]));
const initialId = 'hRx-hV4XoJM';
elements.videoPlayer.dataset = { videoId: initialId, videoTitle: 'Change of Name' };
elements.selectedVideoTitle.textContent = 'Change of Name';
elements.playSelectedVideo.tag = 'button';
elements.playSelectedVideo.appendChild(new Element('img'));
elements.videoPlayer.appendChild(elements.playSelectedVideo);
for (let i = 0; i < 13; i++) {
    const button = new Element('button');
    const id = i === 0 ? initialId : `v${String(i).padStart(10, '0')}`;
    button.dataset = { videoId: id, videoTitle: `Video ${i}`, videoThumbnail: `https://i.ytimg.com/vi/${id}/hqdefault.jpg` };
    button.appendChild(new Element('img'));
    elements.videoGrid.appendChild(button);
}
let observer;
let destroyed = 0;
let playerEvents;
let iframeCount = 0;
const document = { getElementById: id => elements[id], head: new Element('head'),
    createElement: tag => { if (tag === 'iframe') iframeCount++; return new Element(tag); } };
const window = { location: { origin: 'https://portal.example.com' }, YT: { Player: function (iframe, options) {
    playerEvents = options.events;
    this.destroy = () => { destroyed++; };
    options.events.onReady();
} } };
vm.runInNewContext(fs.readFileSync('assets/js/consumer-education.js', 'utf8'), {
    document, window, URL, URLSearchParams, Promise,
    setTimeout: () => 1, clearTimeout: () => {},
    MutationObserver: class { constructor(callback) { observer = callback; } observe() {} }
});
(async function () {
    assert.equal(iframeCount, 0, 'No iframe or autoplay on page load');
    assert.equal(elements.videoGrid.children[0].attributes['aria-pressed'], 'true');
    assert.equal(elements.videoRange.textContent, 'Showing 1\u20136 of 13 videos');
    assert.equal(elements.previousVideoPage.disabled, true);
    elements.nextVideoPage.fire('click');
    assert.equal(elements.videoRange.textContent, 'Showing 7\u201312 of 13 videos');
    elements.nextVideoPage.fire('click');
    assert.equal(elements.videoRange.textContent, 'Showing 13\u201313 of 13 videos');
    assert.equal(elements.nextVideoPage.disabled, true);
    elements.previousVideoPage.fire('click');
    assert.equal(elements.nextVideoPage.disabled, false);
    elements.videoPageNumbers.children[0].fire('click');
    assert.equal(elements.previousVideoPage.disabled, true);
    elements.playSelectedVideo.fire('click');
    await Promise.resolve();
    assert.equal(iframeCount, 1);
    assert.equal(new URL(elements.videoPlayer.querySelector('iframe').src).pathname, `/embed/${initialId}`);
    elements.playSelectedVideo.fire('click');
    assert.equal(iframeCount, 1, 'Repeated play does not duplicate iframe');
    playerEvents.onError({ data: 101 });
    assert.equal(elements.videoPlayerError.hidden, false);
    assert.ok(elements.selectedVideoYouTube, 'Direct YouTube link stays available');
    const second = elements.videoGrid.children[1];
    elements.videoGrid.fire('click', second);
    assert.equal(destroyed, 1, 'Selecting another card destroys previous YouTube instance');
    assert.equal(elements.videoPlayer.querySelector('iframe'), null);
    assert.equal(elements.selectedVideoTitle.textContent, 'Video 1');
    assert.equal(elements.selectedVideoYouTube.href, `https://www.youtube.com/watch?v=${second.dataset.videoId}`);
    assert.equal(second.attributes['aria-pressed'], 'true');
    assert.equal(elements.videoGrid.children[0].attributes['aria-pressed'], 'false');
    assert.equal(elements.videoPlayerError.hidden, true);
    assert.equal(elements.videoPlayer.querySelector('img').src, second.dataset.videoThumbnail);
    elements.videoPlayer.children[0].fire('click');
    await Promise.resolve();
    assert.equal(iframeCount, 2);
    assert.equal(new URL(elements.videoPlayer.querySelector('iframe').src).pathname, `/embed/${second.dataset.videoId}`);
    elements.nextVideoPage.fire('click');
    elements.videoGrid.children = elements.videoGrid.children.slice(0, 3);
    observer();
    assert.equal(elements.videoRange.textContent, 'Showing 1\u20133 of 3 videos');
    assert.equal(elements.videoPagination.hidden, true);
    elements.videoGrid.children = [];
    observer();
    assert.equal(elements.videoRange.textContent, 'No videos available.');
    assert.equal(elements.nextVideoPage.disabled, true);
    console.log('Education player selection, iframe lifecycle, error fallback and pagination tests passed (DOM doubles).');
})().catch(error => { console.error(error); process.exitCode = 1; });
