for (const gallery of document.querySelectorAll('[data-media-gallery]')) {
    const track = gallery.querySelector('.media-gallery__track');
    const items = [...track.children];
    const previous = gallery.querySelector('[data-gallery-previous]');
    const next = gallery.querySelector('[data-gallery-next]');
    const position = gallery.querySelector('[data-gallery-position]');
    if (!items.length) continue;
    gallery.querySelector('.media-gallery__controls').hidden = false;
    const offsets = () => items.map(item => item.getBoundingClientRect().left - track.getBoundingClientRect().left + track.scrollLeft);
    const current = () => {
        if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 2) return items.length - 1;
        const distances = offsets().map(left => Math.abs(left - track.scrollLeft));
        return distances.indexOf(Math.min(...distances));
    };
    const update = () => {
        previous.disabled = track.scrollLeft < 2;
        next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
        position.textContent = `${current() + 1} / ${items.length}`;
    };
    const go = (index) => track.scrollTo({
        left: offsets()[Math.max(0, Math.min(items.length - 1, index))],
        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    });
    const previousIndex = () => offsets().findLastIndex(left => left < track.scrollLeft - 2);
    const nextIndex = () => offsets().findIndex(left => left > track.scrollLeft + 2);
    previous.addEventListener('click', () => go(previousIndex()));
    next.addEventListener('click', () => go(nextIndex() < 0 ? items.length - 1 : nextIndex()));
    track.addEventListener('keydown', (event) => {
        if (event.target !== track || event.altKey || event.ctrlKey || event.metaKey) return;
        const targets = { ArrowLeft: previousIndex(), ArrowRight: nextIndex() < 0 ? items.length - 1 : nextIndex(), Home: 0, End: items.length - 1 };
        if (!(event.key in targets)) return;
        event.preventDefault();
        go(targets[event.key]);
    });
    track.addEventListener('scroll', update, { passive: true });
    new ResizeObserver(update).observe(track);
    update();
}
