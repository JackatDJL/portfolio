// A bounded recycled window per lane. Editorial count changes the source pool,
// never the scroll distance or the number of visible slots.
export function homepageStream(container, sources, gsap) {
    if (!sources.length) return { draw() {}, destroy() {} };
    const slots = [];
    for (let lane = 0; lane < 2; lane++) for (let slot = -1; slot < 5; slot++) {
        const node = sources[0].cloneNode(true);
        node.dataset.streamClone = '';
        node.setAttribute('aria-hidden', 'true');
        node.style.top = lane ? '52%' : '15%';
        container.append(node);
        slots.push({ node, lane, slot, index: -1 });
    }
    return {
        draw(progress) {
            const stride = Math.min(560, Math.max(288, innerWidth * .34)) + 32;
            const distance = progress * innerWidth * 2.5;
            const step = Math.floor(distance / stride), fraction = distance % stride;
            const entry = Math.max(0, 1 - progress / .3) * innerWidth * 1.6;
            const exit = Math.max(0, (progress - .7) / .3) * innerWidth * 1.6;
            for (const item of slots) {
                const index = ((step + item.slot + 2) * 2 + item.lane) % sources.length;
                if (index !== item.index) {
                    const image = sources[index].querySelector('img').cloneNode(true);
                    image.alt = ''; image.loading = 'eager';
                    item.node.replaceChildren(image); item.index = index;
                }
                const base = item.slot * stride - fraction;
                gsap.set(item.node, { x: item.lane ? innerWidth - base - stride - entry + exit : base + entry - exit });
            }
        },
        destroy() { slots.forEach(({ node }) => node.remove()); },
    };
}
