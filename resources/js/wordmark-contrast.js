// Solid surfaces use WCAG luminance. Images/gradients are treated conservatively:
// their painted pixels cannot be inferred reliably from computed CSS alone.
export function initWordmarkContrast() {
    const wordmark = document.querySelector('.site-header__wordmark');
    if (!wordmark) return;
    wordmark.dataset.contrastMarker = 'false';
    let frame = 0;
    let timer = 0;
    let marked = false;
    let pending = false;
    const rgb = (value) => value.match(/[\d.]+/g)?.map(Number);
    const luminance = (color) => color.slice(0, 3).map(value => {
        const channel = value / 255;
        return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
    }).reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
    const contrast = (a, b) => (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
    const needsMarker = () => {
        const rect = wordmark.getBoundingClientRect();
        const text = luminance(rgb(getComputedStyle(document.body).color));
        // Sample both ends and centre so a section boundary cannot hide half the name.
        return [.1, .5, .9].some(position => {
            const stack = document.elementsFromPoint(rect.left + rect.width * position, rect.top + rect.height / 2);
            for (const element of stack) {
                if (element.closest('.site-header, .menu-dialog')) continue;
                const style = getComputedStyle(element);
                if (element.matches('img, video, canvas') || style.backgroundImage !== 'none') return true;
                const color = rgb(style.backgroundColor);
                if (!color || (color.length === 4 && color[3] < 1)) continue;
                return contrast(text, luminance(color)) < (marked ? 5.5 : 4.5);
            }
            return false;
        });
    };
    const check = () => {
        frame = 0;
        const next = needsMarker();
        if (next === pending) return;
        pending = next;
        clearTimeout(timer);
        if (next === marked) return;
        timer = window.setTimeout(() => {
            marked = pending;
            wordmark.dataset.contrastMarker = String(marked);
        }, next ? 100 : 300);
    };
    const schedule = () => { if (!frame) frame = requestAnimationFrame(check); };
    const observer = new MutationObserver(schedule);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    window.addEventListener('pageshow', schedule);
    document.addEventListener('load', schedule, true);
    document.fonts?.ready.then(schedule);
    schedule();
    window.addEventListener('pagehide', event => {
        if (event.persisted) return;
        cancelAnimationFrame(frame);
        clearTimeout(timer);
        observer.disconnect();
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        window.removeEventListener('pageshow', schedule);
        document.removeEventListener('load', schedule, true);
    });
}
