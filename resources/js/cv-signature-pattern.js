const ns = 'http://www.w3.org/2000/svg';

export const initSignaturePatterns = () => {
    document.querySelectorAll('[data-signature-example]').forEach(image => {
        image.addEventListener('error', () => {
            image.hidden = true;
            const fallback = image.parentElement.querySelector('[data-signature-example-fallback]');
            if (fallback) fallback.hidden = false;
        }, { once: true });
        if (image.complete && image.naturalWidth === 0) image.dispatchEvent(new Event('error'));
    });
    document.querySelectorAll('[data-signature-pattern]').forEach(svg => {
        if (svg.dataset.initialized) return;
        svg.dataset.initialized = 'true';
        const colors = [...svg.querySelectorAll('path')].map(path => path.getAttribute('stroke'));
        const palette = colors.length ? colors : ['#315CA8', '#8AA7D3'];
        const lineCount = Math.max(4, Math.min(12, Math.round(colors.length / 2)));
        const reduced = matchMedia('(prefers-reduced-motion: reduce)');
        const layers = Array.from({ length: 20 }, () => {
            const group = document.createElementNS(ns, 'g');
            group.setAttribute('fill', 'none');
            group.setAttribute('stroke-width', '2.4');
            group.setAttribute('stroke-linecap', 'round');
            return group;
        });
        const defs = document.createElementNS(ns, 'defs');
        const filter = document.createElementNS(ns, 'filter');
        const filterId = `signature-gamma-${crypto.randomUUID()}`;
        filter.id = filterId;
        const transfer = document.createElementNS(ns, 'feComponentTransfer');
        for (const channel of ['R', 'G', 'B']) {
            const curve = document.createElementNS(ns, `feFunc${channel}`);
            curve.setAttribute('type', 'gamma');
            curve.setAttribute('exponent', '1.15');
            transfer.append(curve);
        }
        filter.append(transfer);
        defs.append(filter);
        svg.replaceChildren(defs, ...layers);
        const render = () => {
            for (const [index, layer] of layers.entries()) {
                const phase = Math.random() * Math.PI * 2;
                const matrix = svg.getScreenCTM();
                const scaleX = matrix ? Math.hypot(matrix.a, matrix.b) || 1 : 1;
                const scaleY = matrix ? Math.hypot(matrix.c, matrix.d) || 1 : 1;
                layer.setAttribute('transform', `translate(${(Math.random() * 20 - 10) / scaleX} ${(Math.random() * 20 - 10) / scaleY})`);
                layer.style.opacity = '.16';
                layer.style.mixBlendMode = ['multiply', 'exclusion', 'difference', 'screen'][index % 4];
                layer.style.filter = `url(#${filterId}) contrast(${.8 + Math.random()}) brightness(${.7 + Math.random() * .6}) hue-rotate(${Math.random() * 30 - 15}deg)`;
                const paths = Array.from({ length: lineCount }, (_, line) => {
                    const path = document.createElementNS(ns, 'path');
                    let d = '';
                    for (let step = 0; step <= 60; step++) {
                        const x = 14 + step / 60 * 692;
                        const angle = step / 60 * Math.PI * 6 + phase + line;
                        const y = 90 + Math.sin(angle) * 42 + Math.sin(angle * 2.15 - line) * 17;
                        d += `${step ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`;
                    }
                    path.setAttribute('d', d);
                    path.setAttribute('vector-effect', 'non-scaling-stroke');
                    path.setAttribute('stroke', palette[(index + line) % palette.length]);
                    return path;
                });
                layer.replaceChildren(...paths);
            }
        };
        render();
        let timer;
        const schedule = () => {
            clearInterval(timer);
            if (!reduced.matches && !document.hidden) timer = setInterval(() => {
                if (svg.isConnected && svg.getBoundingClientRect().height && !svg.closest('[hidden]')) render();
            }, 1200);
        };
        reduced.addEventListener('change', schedule);
        document.addEventListener('visibilitychange', schedule);
        window.addEventListener('beforeprint', () => clearInterval(timer));
        window.addEventListener('afterprint', schedule);
        window.addEventListener('pagehide', () => clearInterval(timer), { once: true });
        schedule();
    });
};

initSignaturePatterns();
