// Finite trajectories: source count only selects photographs. It never changes
// slot count, distance, velocity, or the master timeline's duration.
export function homepageStream(container, sources, gsap) {
    const slots = [];
    if (!sources.length) return { build() {}, destroy() {} };
    for (let lane = 0; lane < 2; lane++) {
        for (let index = 0; index < 4; index++) {
            const node = sources[(index * 2 + lane + 1) % sources.length].cloneNode(true);
            node.dataset.streamClone = '';
            node.setAttribute('aria-hidden', 'true');
            const image = node.querySelector('img');
            image.alt = '';
            image.loading = 'eager';
            container.append(node);
            slots.push({ node, lane, index });
        }
    }
    return {
        build(timeline, labels) {
            const duration = labels['project-1'] - labels['gallery-in'];
            for (const { node, lane, index } of slots) {
                const direction = lane ? 1 : -1;
                // Every slot keeps its image for the entire journey. There are
                // no recycled nodes popping back into view during the exit.
                const start = () => lane
                    ? -innerWidth * (.42 + index * .48)
                    : innerWidth * (1.03 + index * .48);
                gsap.set(node, { top: lane ? '54%' : '15%', autoAlpha: 1 });
                timeline.fromTo(node, { x: start }, {
                    x: () => start() + direction * innerWidth * 2.95,
                    duration, ease: 'none', immediateRender: true,
                }, 'gallery-in');
            }
        },
        destroy() { slots.forEach(({ node }) => node.remove()); },
    };
}
