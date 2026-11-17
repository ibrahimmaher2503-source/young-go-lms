// Progressive form submit for pages that post to the JSON endpoints (data-ajax forms).
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const message = (key) => document.body.dataset[key] ?? '';

function show(form, tone, text) {
    const box = form.querySelector('[data-form-status]');
    if (!box) return;
    box.hidden = !text;
    box.textContent = text;
    box.className = 'rounded-lg p-3 break-words ' + (tone === 'success' ? 'bg-success text-on-success' : 'bg-danger text-on-danger');
    box.setAttribute('role', tone === 'success' ? 'status' : 'alert');
    if (text) box.focus();
}

function clearErrors(form) {
    form.querySelectorAll('[data-field-error]').forEach((node) => node.remove());
    form.querySelectorAll('[aria-invalid]').forEach((node) => {
        node.removeAttribute('aria-invalid');
        node.classList.replace('border-danger', 'border-border');
    });
    show(form, 'danger', '');
}

function fieldFor(form, name) {
    const bracket = name.replace(/\.(\w+)/g, '[$1]');
    return form.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(bracket)}"]`);
}

function showErrors(form, errors) {
    let first = null;
    for (const [name, messages] of Object.entries(errors)) {
        const field = fieldFor(form, name);
        if (!field) {
            show(form, 'danger', messages[0]);
            continue;
        }
        field.setAttribute('aria-invalid', 'true');
        field.classList.replace('border-border', 'border-danger');
        const error = document.createElement('p');
        error.id = `${field.id || name}-error`;
        error.dataset.fieldError = '';
        error.className = 'rounded-md bg-danger px-2 py-1 text-sm text-on-danger';
        error.textContent = messages[0];
        (field.closest('[data-field]') ?? field.parentElement).append(error);
        field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        first ??= field;
    }
    first?.focus();
}

async function post(url, form) {
    return fetch(url, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    });
}

async function succeed(form, response) {
    const target = form.dataset.resultTarget && document.getElementById(form.dataset.resultTarget);
    if (target) {
        const body = await response.json();
        const value = target.querySelector('[data-result-value]');
        const list = target.querySelector('[data-result-list]');
        if (value) value.textContent = body.code ?? '';
        if (list) {
            list.replaceChildren(...(body.recovery_codes ?? []).map((code) => {
                const item = document.createElement('li');
                item.textContent = code;
                return item;
            }));
        }
        target.hidden = false;
        (value ?? list)?.focus();
        show(form, 'success', '');
        return;
    }
    if (form.dataset.successUrl || 'reload' in form.dataset) {
        form.dataset.navigating = '1'; // keep the buttons disabled while the page changes
        return form.dataset.successUrl ? window.location.assign(form.dataset.successUrl) : window.location.reload();
    }
    if (form.dataset.reset !== undefined) form.reset();
    show(form, 'success', form.dataset.successMessage ?? '');
}

document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('form[data-ajax]');
    if (!form) return;
    event.preventDefault();
    if (form.dataset.busy) return;
    form.dataset.busy = '1';
    clearErrors(form);
    const buttons = form.querySelectorAll('button[type="submit"]');
    buttons.forEach((button) => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });

    try {
        let response;
        for (const url of form.dataset.chain ? JSON.parse(form.dataset.chain) : [form.action]) {
            response = await post(url, form);
            if (!response.ok) break;
        }

        if (response.ok) await succeed(form, response);
        else if (response.status === 422) {
            const body = await response.json();
            showErrors(form, body.errors ?? {});
        } else if (response.status === 429) show(form, 'danger', message('msgThrottle'));
        else if (response.status === 419) show(form, 'danger', message('msgExpired'));
        else {
            const body = await response.json().catch(() => ({}));
            show(form, 'danger', response.status < 500 && body.message ? body.message : message('msgError'));
        }
    } catch {
        show(form, 'danger', message('msgError'));
    } finally {
        delete form.dataset.busy;
        if (!form.dataset.navigating) {
            buttons.forEach((button) => { button.disabled = false; button.removeAttribute('aria-busy'); });
        }
    }
});

// Copy-to-clipboard for one-time codes (data-copy points at the element whose text is copied).
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    const source = document.querySelector(button.dataset.copy);
    if (!source) return;
    try {
        await navigator.clipboard.writeText(Array.from(source.children, (item) => item.textContent).join('\n'));
        const status = button.parentElement.querySelector('[data-copy-status]');
        if (status) status.textContent = button.dataset.copied ?? '';
    } catch {
        // Clipboard can be blocked; the codes stay visible to copy by hand.
    }
});
