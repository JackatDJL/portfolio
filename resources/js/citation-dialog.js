import { gsap } from 'gsap';

const preferenceKey = 'jack-publication-citation-style';

const partsForName = (name) => {
    const words = name.trim().split(/\s+/).filter(Boolean);
    return { family: words.pop() || '', given: words.join(' ') };
};

const initials = (given) => given.split(/\s+/).filter(Boolean).map((part) => `${part[0]}.`).join(' ');

const formatAuthors = (authors, style) => {
    const names = authors.map(partsForName);
    if (style === 'mla') return names.map(({ family, given }, index) => index === 0 ? `${family}, ${given}` : `${given} ${family}`).join(', ');
    if (style === 'vancouver') return names.map(({ family, given }) => `${family} ${initials(given).replaceAll(' ', '')}`).join(', ');
    if (style === 'ieee') return names.map(({ family, given }) => `${initials(given)} ${family}`).join(', ');
    if (style === 'chicago') return names.map(({ family, given }, index) => index === 0 ? `${family}, ${given}` : `${given} ${family}`).join(', ');
    const formatted = names.map(({ family, given }) => `${family}, ${initials(given)}`);
    return formatted.length > 1 ? `${formatted.slice(0, -1).join(', ')}, & ${formatted.at(-1)}` : formatted[0];
};

const buildCitation = ({ authors, title, year, publisher, doi }, style) => {
    const authorText = formatAuthors(authors, style);
    const doiUrl = doi ? `https://doi.org/${doi}` : '';
    const source = publisher || 'Ohne Verlag';
    switch (style) {
    case 'harvard': return `${authorText} (${year}) ${title}. ${source}.${doiUrl ? ` ${doiUrl}` : ''}`;
    case 'mla': return `${authorText}. "${title}." ${source}, ${year}.${doiUrl ? ` ${doiUrl}` : ''}`;
    case 'vancouver': return `${authorText}. ${title}. ${source}; ${year}.${doiUrl ? ` Available from: ${doiUrl}` : ''}`;
    case 'chicago': return `${authorText}. ${year}. "${title}." ${source}.${doiUrl ? ` ${doiUrl}` : ''}`;
    case 'ieee': return `${authorText}, "${title}," ${source}, ${year}.${doiUrl ? ` [Online]. Available: ${doiUrl}` : ''}`;
    default: return `${authorText} (${year}). ${title}. ${source}.${doiUrl ? ` ${doiUrl}` : ''}`;
    }
};

const copyText = async (value, target) => {
    try {
        await navigator.clipboard.writeText(value);
        return true;
    } catch {
        const range = document.createRange();
        range.selectNodeContents(target);
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(range);
        const copied = document.execCommand('copy');
        selection?.removeAllRanges();
        return copied;
    }
};

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

for (const dialog of document.querySelectorAll('[data-citation-dialog]')) {
    if (!(dialog instanceof HTMLDialogElement)) continue;
    const select = dialog.querySelector('[data-citation-select]');
    const trigger = dialog.querySelector('[data-citation-style]');
    const value = dialog.querySelector('[data-citation-style-value]');
    const options = dialog.querySelector('[data-citation-options]');
    const output = dialog.querySelector('[data-citation-output]');
    const copy = dialog.querySelector('[data-citation-copy]');
    if (!(select instanceof HTMLElement) || !(trigger instanceof HTMLButtonElement) || !(value instanceof HTMLElement)
        || !(options instanceof HTMLElement) || !(output instanceof HTMLElement) || !(copy instanceof HTMLButtonElement)) continue;

    const optionButtons = [...options.querySelectorAll('[data-citation-option]')].filter((option) => option instanceof HTMLButtonElement);
    const citation = {
        authors: [...dialog.querySelectorAll('[data-citation-author]')].map((author) => author.textContent || '').filter(Boolean),
        title: dialog.dataset.citationTitle || '', year: dialog.dataset.citationYear || '',
        publisher: dialog.dataset.citationPublisher || '', doi: dialog.dataset.citationDoi || '',
    };
    let style = window.localStorage.getItem(preferenceKey) || 'apa';
    if (!optionButtons.some((option) => option.dataset.citationOption === style)) style = 'apa';
    let isOpen = false;

    const render = () => {
        value.textContent = optionButtons.find((option) => option.dataset.citationOption === style)?.textContent || style;
        output.textContent = buildCitation(citation, style);
        optionButtons.forEach((option) => option.setAttribute('aria-selected', String(option.dataset.citationOption === style)));
    };
    const closeOptions = (returnFocus = false) => {
        if (!isOpen) return;
        isOpen = false;
        trigger.setAttribute('aria-expanded', 'false');
        select.classList.remove('is-open');
        const finish = () => { options.hidden = true; if (returnFocus) trigger.focus(); };
        if (prefersReducedMotion()) finish();
        else gsap.to(options, { autoAlpha: 0, y: -4, duration: 0.14, ease: 'power1.in', onComplete: finish });
    };
    const openOptions = () => {
        if (isOpen) return;
        isOpen = true;
        options.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        select.classList.add('is-open');
        if (prefersReducedMotion()) gsap.set(options, { autoAlpha: 1, y: 0 });
        else gsap.fromTo(options, { autoAlpha: 0, y: -4 }, { autoAlpha: 1, y: 0, duration: 0.18, ease: 'power2.out' });
    };
    const choose = (nextStyle) => {
        style = nextStyle;
        window.localStorage.setItem(preferenceKey, style);
        render();
        closeOptions(true);
    };

    render();
    trigger.addEventListener('click', () => { if (isOpen) closeOptions(); else openOptions(); });
    trigger.addEventListener('keydown', (event) => {
        if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
        event.preventDefault();
        openOptions();
        const active = optionButtons.find((option) => option.dataset.citationOption === style);
        active?.focus();
    });
    optionButtons.forEach((option, index) => {
        option.addEventListener('click', () => choose(option.dataset.citationOption || 'apa'));
        option.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { event.preventDefault(); closeOptions(true); }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                optionButtons[(index + (event.key === 'ArrowDown' ? 1 : -1) + optionButtons.length) % optionButtons.length]?.focus();
            }
        });
    });
    document.addEventListener('pointerdown', (event) => { if (isOpen && event.target instanceof Node && !select.contains(event.target)) closeOptions(); });
    dialog.addEventListener('cancel', (event) => { if (isOpen) { event.preventDefault(); closeOptions(true); } });
    copy.addEventListener('click', async () => {
        if (await copyText(output.textContent || '', output)) {
            copy.textContent = 'Kopiert';
            window.setTimeout(() => { copy.textContent = 'Zitation kopieren'; }, 1600);
        } else output.focus();
    });
}

for (const trigger of document.querySelectorAll('[data-citation-open]')) {
    if (!(trigger instanceof HTMLButtonElement)) continue;
    trigger.addEventListener('click', () => {
        const dialog = document.getElementById(trigger.dataset.citationOpen || '');
        if (dialog instanceof HTMLDialogElement && !dialog.open) dialog.showModal();
    });
}
