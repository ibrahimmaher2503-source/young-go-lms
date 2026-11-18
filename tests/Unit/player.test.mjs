import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

// T-P4-04, T-P4-07, YG-GAP-VID-003: exercise the shared player against a simulated Vimeo frame.
test('Vimeo reports playback, resumes and pauses through the player stage', async () => {
    const posts = [];
    const reports = [];
    const frame = new EventTarget();
    frame.contentWindow = { postMessage: (message, origin) => posts.push([message.method, message.value, origin]) };
    const fullscreen = new EventTarget();
    let fullscreenRequested = false;
    const controls = {
        '[data-player-vimeo]': frame,
        '[data-player-fullscreen]': fullscreen,
        '[data-player-stage]': {
            requestFullscreen: async () => { fullscreenRequested = true; },
        },
    };
    const root = { dataset: { progressUrl: '/progress', resume: '21' }, querySelector: (key) => controls[key] };
    const window = new EventTarget();
    const document = {
        fullscreenEnabled: true,
        querySelector: () => null,
        querySelectorAll: (key) => key === '[data-player]' ? [root] : [],
    };
    let reportProgress;
    window.clearInterval = () => { reportProgress = undefined; };
    window.setInterval = (callback) => {
        reportProgress = callback;
        return 1;
    };
    const fetch = async (url, request) => {
        reports.push([url, JSON.parse(request.body)]);
        return { ok: false };
    };
    runInNewContext(await readFile(new URL('../../resources/js/player.js', import.meta.url), 'utf8'), {
        window, document, fetch, EventTarget, Event,
    });

    const playerMessage = (data) => {
        const event = new Event('message');
        Object.assign(event, { source: frame.contentWindow, origin: 'https://player.vimeo.com', data });
        window.dispatchEvent(event);
    };
    window.dispatchEvent(new Event('pagehide'));
    assert.deepEqual(reports, []);
    playerMessage({ method: 'ping' });
    playerMessage({ method: 'getDuration', value: 120 });
    assert.ok(posts.some(([method, value, origin]) =>
        method === 'setCurrentTime' && value === 21 && origin === 'https://player.vimeo.com'));
    playerMessage({ event: 'play', data: { seconds: 21 } });
    playerMessage({ event: 'timeupdate', data: { seconds: 31.9 } });
    assert.equal(window.ygPlayer.currentPosition(), 31);
    reportProgress();
    let pausedAt;
    window.ygPlayer.onPause(() => { pausedAt = window.ygPlayer.currentPosition(); });
    window.ygPlayer.pause();
    assert.equal(posts.at(-1)[0], 'pause');
    playerMessage({ event: 'pause', data: { seconds: 32 } });
    assert.equal(pausedAt, 32);
    assert.deepEqual(reports, [['/progress', { position: 31 }], ['/progress', { position: 32 }]]);
    assert.equal(reportProgress, undefined);
    fullscreen.dispatchEvent(new Event('click'));
    assert.ok(fullscreenRequested);
});
