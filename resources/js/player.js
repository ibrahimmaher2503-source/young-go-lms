// Lesson player controls (YG-GAP-LRN-004): speed (remembered), captions, full screen and keyboard shortcuts.
const SPEEDS = [0.75, 1, 1.25, 1.5, 2];
const STORAGE_KEY = 'yg.player.speed';

function readSpeed() {
    try {
        const stored = Number(window.localStorage.getItem(STORAGE_KEY));
        return SPEEDS.includes(stored) ? stored : 1;
    } catch {
        return 1;
    }
}

function saveSpeed(speed) {
    try {
        window.localStorage.setItem(STORAGE_KEY, String(speed));
    } catch {
        // Storage can be blocked; the speed simply is not remembered.
    }
}

function initPlayer(root) {
    const media = root.querySelector('[data-player-media]');
    if (!media) {
        return;
    }

    const select = root.querySelector('[data-player-speed]');
    const captions = root.querySelector('[data-player-captions]');
    const fullscreen = root.querySelector('[data-player-fullscreen]');
    const stage = root.querySelector('[data-player-stage]');

    const setSpeed = (speed) => {
        media.playbackRate = speed;
        if (select) {
            select.value = String(speed);
        }
        root.dataset.speed = String(speed);
        saveSpeed(speed);
    };
    const stepSpeed = (direction) => {
        const index = SPEEDS.indexOf(Number(select ? select.value : media.playbackRate));
        setSpeed(SPEEDS[Math.min(SPEEDS.length - 1, Math.max(0, (index === -1 ? 1 : index) + direction))]);
    };
    const setCaptions = (on) => {
        Array.from(media.textTracks || []).forEach((track) => {
            track.mode = on ? 'showing' : 'hidden';
        });
        root.dataset.captions = on ? 'on' : 'off';
        if (captions) {
            captions.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
    };
    const seek = (seconds) => {
        const target = media.currentTime + seconds;
        media.currentTime = Math.max(0, Number.isFinite(media.duration) ? Math.min(media.duration, target) : target);
    };
    const toggle = () => {
        if (media.paused) {
            media.play().catch(() => {});
        } else {
            media.pause();
        }
    };
    const toggleFullscreen = () => {
        const target = stage || media;
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (target.requestFullscreen) {
            target.requestFullscreen();
        }
    };

    setSpeed(readSpeed());
    setCaptions(false);

    select?.addEventListener('change', () => setSpeed(Number(select.value)));
    captions?.addEventListener('click', () => setCaptions(root.dataset.captions !== 'on'));
    fullscreen?.addEventListener('click', toggleFullscreen);

    document.addEventListener('keydown', (event) => {
        const target = event.target;
        const typing = target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
        if (typing || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        const onControl = target instanceof HTMLElement && ['BUTTON', 'A', 'VIDEO', 'AUDIO'].includes(target.tagName);
        switch (event.key) {
            case ' ':
                if (onControl) {
                    return; // the focused control handles its own space
                }
                event.preventDefault();
                toggle();
                break;
            case 'k':
                toggle();
                break;
            case 'ArrowLeft':
                seek(-5);
                break;
            case 'ArrowRight':
                seek(5);
                break;
            case 'j':
                seek(-10);
                break;
            case 'l':
                seek(10);
                break;
            case 'f':
                toggleFullscreen();
                break;
            case 'c':
                if (captions) {
                    setCaptions(root.dataset.captions !== 'on');
                }
                break;
            case '>':
                stepSpeed(1);
                break;
            case '<':
                stepSpeed(-1);
                break;
            default:
        }
    });

    // The assistant (P7) reads this to attach a question to the moment the learner paused.
    window.ygPlayer = { currentPosition: () => Math.floor(media.currentTime || 0) };
}

document.querySelectorAll('[data-player]').forEach(initPlayer);

// Progress (T-P4-04): the server decides what counts; the page only reports where the learner is.
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function send(url, body, keepalive = false) {
    return fetch(url, {
        method: 'POST',
        keepalive,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
}

function applyResult(root, result) {
    const percent = document.querySelector('[data-course-percent]');
    if (percent && typeof result.course_percent === 'number') {
        percent.textContent = `${result.course_percent}%`;
        const bar = document.querySelector('[data-course-progress] progress');
        if (bar) {
            bar.value = result.course_percent;
        }
    }
    root.dataset.lessonStatus = result.status;
}

function initProgress(root) {
    const url = root.dataset.progressUrl;
    if (!url) {
        return;
    }

    const media = root.querySelector('[data-player-media]');
    if (media) {
        const resume = Number(root.dataset.resume || 0);
        media.addEventListener('loadedmetadata', () => {
            if (resume > 0 && (!Number.isFinite(media.duration) || resume < media.duration - 5)) {
                media.currentTime = resume;
            }
        }, { once: true });

        const report = (keepalive = false) => {
            const position = Math.floor(media.currentTime || 0);
            send(url, { position }, keepalive).then((response) => (response.ok ? response.json() : null)).then((result) => result && applyResult(root, result)).catch(() => {});
        };
        let timer = null;
        media.addEventListener('play', () => {
            timer = timer || window.setInterval(() => report(), 15000);
        });
        const stop = () => {
            window.clearInterval(timer);
            timer = null;
            report();
        };
        media.addEventListener('pause', stop);
        media.addEventListener('ended', stop);
        window.addEventListener('pagehide', () => report(true));
    }

    const complete = root.querySelector('[data-player-complete]');
    complete?.addEventListener('click', () => {
        send(root.dataset.completeUrl, {})
            .then((response) => (response.ok ? response.json() : null))
            .then((result) => {
                if (result) {
                    applyResult(root, result);
                    complete.remove();
                    window.location.reload();
                }
            })
            .catch(() => {});
    });
}

document.querySelectorAll('[data-player]').forEach(initProgress);

// Watermark (YG-GAP-VID-003): it stays inside the stage, so it is also visible in full screen, and it moves every few seconds.
document.querySelectorAll('[data-watermark]').forEach((mark) => {
    const spots = ['top: .75rem; inset-inline-end: .75rem', 'top: .75rem; inset-inline-start: .75rem', 'bottom: 3.5rem; inset-inline-end: .75rem', 'bottom: 3.5rem; inset-inline-start: .75rem', 'top: 45%; inset-inline-start: 35%'];
    let index = 0;
    const place = () => {
        mark.removeAttribute('style');
        mark.style.cssText = spots[index % spots.length];
        mark.dataset.spot = String(index % spots.length);
        index += 1;
    };
    place();
    window.setInterval(place, 12000);
});
