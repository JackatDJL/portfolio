import { gsap } from 'gsap';

const preferenceKey = 'jack-publication-citation-style';

export const partsForName = (name) => {
    const words = name.trim().split(/\s+/).filter(Boolean);
    return { family: words.pop() || '', given: words.join(' ') };
};

export const initials = (given) => given.split(/\s+/).filter(Boolean).map((part) => `${part[0]}.`).join(' ');

const formatAuthors = (authors, style) => {
    const names = authors.map(partsForName);
    if (style === 'mla') {
        const first = names[0];
        return `${first.family}, ${initials(first.given)}${names.length > 1 ? ', et al.' : ''}`;
    }
    if (style === 'vancouver') return names.map(({ family, given }) => `${family} ${initials(given).replaceAll(' ', '').replaceAll('.', '')}`).join(', ');
    if (style === 'ieee') {
        const formatted = names.map(({ family, given }) => `${initials(given)} ${family}`);
        return formatted.length > 1 ? `${formatted.slice(0, -1).join(', ')}, and ${formatted.at(-1)}` : formatted[0];
    }
    if (style === 'harvard') {
        const formatted = names.map(({ family, given }) => `${family}, ${initials(given)}`);
        return formatted.length > 1 ? `${formatted.slice(0, -1).join(', ')} and ${formatted.at(-1)}` : formatted[0];
    }
    if (style === 'chicago') {
        const formatted = names.map(({ family, given }, index) => index === 0 ? `${family}, ${initials(given)}` : `${initials(given)} ${family}`);
        return formatted.length > 1 ? `${formatted.slice(0, -1).join(', ')}, and ${formatted.at(-1)}` : formatted[0];
    }
    const formatted = names.map(({ family, given }) => `${family}, ${initials(given)}`);
    return formatted.length > 1 ? `${formatted.slice(0, -1).join(', ')}, & ${formatted.at(-1)}` : formatted[0];
};

const formatDate = (date, style) => {
    if (!date) return '';
    const parsed = new Date(`${date}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return '';
    const monthName = parsed.toLocaleDateString('en-GB', { month: 'long' });
    const month = style === 'mla' && !['May', 'June', 'July'].includes(monthName)
        ? `${monthName.slice(0, 3)}.` : monthName;
    const day = parsed.getDate();
    if (style === 'mla') return `${day} ${month} ${parsed.getFullYear()}`;
    if (style === 'ieee') return `${monthName.slice(0, 3)}. ${day}, ${parsed.getFullYear()}`;
    return `${day} ${month} ${parsed.getFullYear()}`;
};

export const buildCitation = ({ authors, title, year, date, publisher, doi, type, version }, style) => {
    const authorText = formatAuthors(authors, style);
    const doiUrl = doi ? `https://doi.org/${doi}` : '';
    const source = publisher || '';
    const versionText = version ? `Version ${version}` : '';
    const graphicText = type === 'poster' ? ' [Graphic]' : '';
    switch (style) {
    case 'harvard': return `${authorText} (${year}) ${type === 'poster' ? `${title}.` : `'${title}'.`}${source ? ` ${source}.` : ''}${doiUrl ? ` Available at: ${doiUrl}.` : ''}`;
    case 'mla': return `${authorText.endsWith('.') ? authorText : `${authorText}.`} '${title}.'${versionText ? ` ${versionText},` : ''} ${source}${date ? `, ${formatDate(date, style)}` : ''}${doiUrl ? `, ${doiUrl}` : ''}.`;
    case 'vancouver': return `1.${authorText}. ${title}. ${source}; ${year}.${doiUrl ? ` doi:${doi}` : ''}`;
    case 'chicago': return `${authorText.endsWith('.') ? authorText : `${authorText}.`} ${type === 'poster' ? `${title}.` : `'${title}.'`}${versionText ? ` ${versionText}.` : ''}${type === 'preprint' ? ' Preprint.' : ''} ${source}${date ? `, ${formatDate(date, 'chicago')}` : ''}.${doiUrl ? ` ${doiUrl}` : ''}`;
    case 'ieee': return `[1]${authorText}, ${type === 'poster' ? title : `'${title}'`},${date && type !== 'poster' ? ` ${formatDate(date, 'ieee')},` : ''}${source ? ` ${source},` : ''} ${year}.${doiUrl ? ` doi: ${doi}.` : ''}`;
    default: return `${authorText} (${year}). ${title}${versionText ? ` (${versionText})` : ''}${graphicText}. ${source ? `${source}.` : ''}${doiUrl ? ` ${doiUrl}` : ''}`;
    }
};

if (typeof document !== 'undefined') {

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
    const panel = dialog.querySelector('.citation-dialog__panel');
    const close = dialog.querySelector('[data-citation-close]');
    if (!(select instanceof HTMLElement) || !(trigger instanceof HTMLButtonElement) || !(value instanceof HTMLElement)
        || !(options instanceof HTMLElement) || !(output instanceof HTMLElement) || !(copy instanceof HTMLButtonElement)
        || !(panel instanceof HTMLElement) || !(close instanceof HTMLButtonElement)) continue;

    const optionButtons = [...options.querySelectorAll('[data-citation-option]')].filter((option) => option instanceof HTMLButtonElement);
    const citation = {
        authors: [...dialog.querySelectorAll('[data-citation-author]')].map((author) => author.textContent || '').filter(Boolean),
        title: dialog.dataset.citationTitle || '', year: dialog.dataset.citationYear || '',
        date: dialog.dataset.citationDate || '', type: dialog.dataset.citationType || '',
        version: dialog.dataset.citationVersion || '',
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
    const closeDialog = () => {
        if (!dialog.open || dialog.dataset.state === 'closing') return;
        dialog.dataset.state = 'closing';
        closeOptions();
        const finish = () => {
            dialog.close();
            delete dialog.dataset.state;
            gsap.set(dialog, { clearProps: 'opacity,visibility,transform' });
        };
        if (prefersReducedMotion()) finish();
        else gsap.to(dialog, { autoAlpha: 0, scale: 0.7, duration: 0.2, ease: 'power1.in', onComplete: finish });
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
    close.addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        if (isOpen) closeOptions(true);
        else closeDialog();
    });
    copy.addEventListener('click', async () => {
        if (await copyText(output.textContent || '', output)) {
            closeDialog();
        } else output.focus();
    });
}

for (const trigger of document.querySelectorAll('[data-citation-open]')) {
    if (!(trigger instanceof HTMLButtonElement)) continue;
    trigger.addEventListener('click', () => {
        const dialog = document.getElementById(trigger.dataset.citationOpen || '');
        if (!(dialog instanceof HTMLDialogElement) || dialog.open) return;

        dialog.showModal();
        if (prefersReducedMotion()) {
            gsap.set(dialog, { autoAlpha: 1, x: 0, y: 0, scale: 1 });
            return;
        }
        gsap.fromTo(dialog,
            { autoAlpha: 0, y: 36, scale: 0.7 },
            { autoAlpha: 1, y: 0, scale: 1, duration: 0.32, ease: 'power2.out', clearProps: 'opacity,visibility,transform' },
        );
    });
}
}
