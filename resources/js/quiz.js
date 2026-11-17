// Quiz attempt page: shows one question at a time, saves answers as they change, and counts down the time the server gave.
// The browser decides nothing: the server owns the deadline, grades the attempt, and closes it even if this page is gone.
const form = document.querySelector('[data-quiz-form]');

if (form) {
    const questions = [...form.querySelectorAll('[data-question]')];
    const navButtons = [...form.querySelectorAll('[data-goto]')];
    const label = form.querySelector('[data-progress-label]');
    const fill = form.querySelector('[data-progressfill]');
    const bar = form.querySelector('[data-progressbar]');
    const state = form.querySelector('[data-save-state]');
    const confirmBox = form.querySelector('[data-finish-confirm]');
    const total = questions.length;
    let current = 1;
    let timer = null;
    let dirty = false;
    let closed = false;

    const answered = (question) => {
        const inputs = [...question.querySelectorAll('[data-answer-input]')];
        return inputs.some((input) => (input.type === 'radio' || input.type === 'checkbox' ? input.checked : input.value.trim() !== ''));
    };

    const refresh = () => {
        questions.forEach((question, index) => {
            question.hidden = index + 1 !== current;
        });
        navButtons.forEach((button, index) => {
            button.setAttribute('aria-current', index + 1 === current ? 'true' : 'false');
            button.dataset.answered = answered(questions[index]) ? 'true' : 'false';
        });
        const text = label.textContent.replace(/\d+(\D+)\d+/, `${current}$1${total}`);
        label.textContent = text;
        fill.style.width = `${Math.round((current / total) * 100)}%`;
        bar.setAttribute('aria-valuenow', String(current));
        form.querySelector('[data-prev]').disabled = current === 1;
        form.querySelector('[data-next]').disabled = current === total;
    };

    const go = (number) => {
        current = Math.min(total, Math.max(1, number));
        confirmBox.classList.add('hidden');
        refresh();
        questions[current - 1].querySelector('legend')?.scrollIntoView({ block: 'nearest' });
    };

    const payload = () => new FormData(form);

    const save = async () => {
        if (closed || !dirty) {
            return;
        }
        dirty = false;
        state.textContent = form.dataset.textSaving;
        try {
            const body = payload();
            const response = await fetch(form.dataset.saveUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (response.status === 409) {
                const data = await response.json();
                if (data.closed) {
                    closed = true;
                    window.location.reload(); // the server closed the attempt: show the result
                    return;
                }
            }
            state.textContent = response.ok ? form.dataset.textSaved : form.dataset.textSaveFailed;
            if (!response.ok) {
                dirty = true;
            }
        } catch {
            state.textContent = form.dataset.textSaveFailed;
            dirty = true;
        }
    };

    let debounce = null;
    form.addEventListener('input', () => {
        dirty = true;
        refresh();
        window.clearTimeout(debounce);
        debounce = window.setTimeout(save, 700);
    });
    form.addEventListener('change', () => {
        dirty = true;
        refresh();
        window.clearTimeout(debounce);
        debounce = window.setTimeout(save, 300);
    });

    navButtons.forEach((button) => button.addEventListener('click', () => go(Number(button.dataset.goto))));
    form.querySelector('[data-prev]').addEventListener('click', () => go(current - 1));
    form.querySelector('[data-next]').addEventListener('click', () => go(current + 1));

    form.querySelector('[data-finish]').addEventListener('click', () => {
        const missing = questions.filter((question) => !answered(question)).length;
        const text = form.querySelector('[data-finish-text]');
        text.textContent = missing > 0 ? text.dataset.unanswered?.replace(':count', String(missing)) ?? text.textContent : text.textContent;
        confirmBox.classList.remove('hidden');
        confirmBox.querySelector('[data-submit]').focus();
    });
    form.querySelector('[data-keep-working]').addEventListener('click', () => {
        confirmBox.classList.add('hidden');
        form.querySelector('[data-finish]').focus();
    });
    form.addEventListener('submit', () => {
        closed = true; // the submit carries every answer itself
    });

    const clock = form.querySelector('[data-quiz-timer]');
    if (clock) {
        const text = clock.querySelector('[data-timer-text]');
        const announce = clock.querySelector('[data-timer-announce]');
        // Count from the server's remaining seconds on this device's own clock, so a wrong device time changes nothing.
        const endsAt = performance.now() + Number(clock.dataset.remaining) * 1000;
        let warned = false;
        const pad = (n) => String(n).padStart(2, '0');
        const tick = () => {
            const left = Math.max(0, Math.round((endsAt - performance.now()) / 1000));
            const h = Math.floor(left / 3600);
            const m = Math.floor((left % 3600) / 60);
            text.textContent = h > 0 ? `${h}:${pad(m)}:${pad(left % 60)}` : `${pad(m)}:${pad(left % 60)}`;
            if (left <= 60 && !warned) {
                warned = true;
                announce.textContent = form.dataset.textMinuteLeft;
            }
            if (left <= 0) {
                window.clearInterval(timer);
                closed = true;
                announce.textContent = form.dataset.textTimeUp;
                form.requestSubmit(); // the server decides; late answers are ignored there
            }
        };
        timer = window.setInterval(tick, 1000);
        tick();
    }

    refresh();
}
