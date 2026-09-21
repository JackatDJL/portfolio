import { buildThreadPath, TOC_BUMP } from './thread-bump.js';

const ns = 'http://www.w3.org/2000/svg';

const drawThread = (root) => {
    const records = [...root.querySelectorAll(':scope > .cv-record, :scope > .cv-milestone')];
    if (!records.length) return;

    const svg = document.createElementNS(ns, 'svg');
    const path = document.createElementNS(ns, 'path');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    svg.append(path);
    root.prepend(svg);

    for (const record of records.filter(record => record.classList.contains('cv-record'))) {
        const printSvg = document.createElementNS(ns, 'svg');
        const printPath = document.createElementNS(ns, 'path');
        printSvg.classList.add('cv-record__print-thread');
        printSvg.setAttribute('viewBox', '0 0 20 100');
        printSvg.setAttribute('preserveAspectRatio', 'none');
        printSvg.setAttribute('aria-hidden', 'true');
        printPath.setAttribute('d', buildThreadPath({ rail: 2, height: 100, marks: [{ y: 50 }] }));
        printSvg.append(printPath);
        record.prepend(printSvg);
    }

    const render = () => {
        const frame = root.getBoundingClientRect();
        const height = Math.max(1, root.clientHeight);
        const marks = records.map(record => {
            const anchor = record.querySelector('h3') || record;
            const box = anchor.getBoundingClientRect();
            const y = box.top - frame.top + box.height / 2;
            return { y: Math.max(TOC_BUMP.halfHeight, Math.min(height - TOC_BUMP.halfHeight, y)) };
        });

        svg.setAttribute('viewBox', `0 0 20 ${height}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        path.setAttribute('d', buildThreadPath({ rail: 2, height, marks }));
    };

    let frame;
    const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(render);
    };
    const observer = new ResizeObserver(schedule);
    observer.observe(root);
    records.forEach(record => observer.observe(record));
    window.addEventListener('resize', schedule, { passive: true });
    document.fonts?.ready.then(schedule);
    schedule();
};

document.querySelectorAll('[data-cv-thread]').forEach(drawThread);
