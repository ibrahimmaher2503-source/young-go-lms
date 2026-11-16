function selectTab(tab, focus = false) {
    const list = tab.closest('[role="tablist"]');
    for (const item of list.querySelectorAll('[role="tab"]')) {
        const selected = item === tab;
        item.setAttribute('aria-selected', String(selected));
        item.tabIndex = selected ? 0 : -1;
        const panel = document.getElementById(item.getAttribute('aria-controls'));
        if (panel) panel.hidden = !selected;
    }

    if (focus) tab.focus();
}

document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-ui-tabs] [role="tab"], [data-ui-dismiss], [command="show-modal"]');
    if (!target) return;
    if (target.matches('[role="tab"]')) selectTab(target);
    if (target.matches('[data-ui-dismiss]')) target.closest('[role="status"]')?.remove();
    if (target.matches('[command="show-modal"]') && !('commandForElement' in target)) {
        document.getElementById(target.getAttribute('commandfor'))?.showModal();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const tooltip = event.target.closest('[data-ui-tooltip]')?.querySelector('[role="tooltip"]');
        if (tooltip) tooltip.hidden = true;
    }

    const tab = event.target.closest('[data-ui-tabs] [role="tab"]');
    if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    const list = tab.closest('[role="tablist"]');
    const tabs = Array.from(list.querySelectorAll('[role="tab"]'));
    const direction = getComputedStyle(list).direction === 'rtl' ? -1 : 1;
    const offset = event.key === 'ArrowRight' ? direction : -direction;
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (tabs.indexOf(tab) + offset + tabs.length) % tabs.length;
    event.preventDefault();
    selectTab(tabs[index], true);
});

document.addEventListener('dragover', (event) => {
    const input = event.target.closest('[data-ui-file]')?.querySelector('input[type="file"]:enabled');
    if (input && event.dataTransfer?.types.includes('Files')) event.preventDefault();
});

document.addEventListener('drop', (event) => {
    const input = event.target.closest('[data-ui-file]')?.querySelector('input[type="file"]:enabled');
    if (!input || !event.dataTransfer?.files.length) return;
    event.preventDefault();
    const transfer = new DataTransfer();
    for (const file of event.dataTransfer.files) {
        transfer.items.add(file);
        if (!input.multiple) break;
    }
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
});

document.addEventListener('change', (event) => {
    const input = event.target.closest('[data-ui-file] input[type="file"]');
    if (!input) return;
    const names = input.closest('[data-ui-file]').querySelector('[data-ui-file-names]');
    names.textContent = Array.from(input.files, (file) => file.name).join(', ') || names.dataset.empty;
});

function restoreTooltip(event) {
    const container = event.target.closest('[data-ui-tooltip]');
    if (!container || container.contains(event.relatedTarget)) return;
    container.querySelector('[role="tooltip"]').hidden = false;
}

document.addEventListener('focusin', restoreTooltip);
document.addEventListener('pointerover', restoreTooltip);
