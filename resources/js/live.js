// Live session card: a progressive enhancement only. The server decides who may join and when (GetJoinLink);
// this reveals the join form when the window opens and keeps a readable countdown while the page stays open.
function format(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const two = (n) => String(n).padStart(2, '0');

    return h > 0 ? `${h}:${two(m)}:${two(s)}` : `${two(m)}:${two(s)}`;
}

function start(card) {
    if (card.dataset.liveState !== 'upcoming') {
        return;
    }
    const opensAt = Date.parse(card.dataset.opensAt);
    const label = card.querySelector('[data-live-countdown]');
    const form = card.querySelector('[data-join-form]');
    const disabled = card.querySelector('[data-join-disabled]');
    if (Number.isNaN(opensAt) || !label) {
        return;
    }

    const tick = () => {
        const left = Math.round((opensAt - Date.now()) / 1000);
        if (left <= 0) {
            label.textContent = card.dataset.textJoinable;
            form?.classList.remove('hidden');
            disabled?.classList.add('hidden');
            window.clearInterval(timer);

            return;
        }
        label.textContent = `${card.dataset.textStartsIn} ${format(left)}`;
    };
    const timer = window.setInterval(tick, 1000);
    tick();
}

document.querySelectorAll('[data-live-card]').forEach(start);
