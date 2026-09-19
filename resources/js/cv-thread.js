const ns = 'http://www.w3.org/2000/svg';

const drawThread = (root) => {
    const records = [...root.querySelectorAll(':scope > .cv-record')];
    if (!records.length) return;

    const svg = document.createElementNS(ns, 'svg');
    const path = document.createElementNS(ns, 'path');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    svg.append(path);
    root.prepend(svg);

    const render = () => {
        const frame = root.getBoundingClientRect();
        const height = Math.max(1, root.clientHeight);
        const compact = window.innerWidth < 768;
        const rail = compact ? 7 : 12;
        const depth = compact ? 7 : 10;
        const radius = compact ? 11 : 14;
        const marks = records.map((record) => {
            const anchor = record.querySelector('h3') || record;
            const box = anchor.getBoundingClientRect();
            return Math.max(radius, Math.min(height - radius, box.top - frame.top + box.height / 2));
        }).sort((a, b) => a - b);

        let cursor = 0;
        const commands = [`M ${rail} 0`];
        for (const y of marks) {
            const start = Math.max(cursor, y - radius);
            const end = Math.min(height, y + radius);
            const shoulder = radius * .66;
            commands.push(`V ${start}`);
            commands.push(`C ${rail} ${y - shoulder}, ${rail + depth} ${y - shoulder}, ${rail + depth} ${y}`);
            commands.push(`C ${rail + depth} ${y + shoulder}, ${rail} ${y + shoulder}, ${rail} ${end}`);
            cursor = end;
        }
        commands.push(`V ${height}`);
        svg.setAttribute('viewBox', `0 0 ${rail + depth + 2} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        path.setAttribute('d', commands.join(' '));
    };

    const schedule = () => requestAnimationFrame(render);
    const observer = new ResizeObserver(schedule);
    observer.observe(root);
    records.forEach((record) => observer.observe(record));
    window.addEventListener('resize', schedule, { passive: true });
    document.fonts?.ready.then(schedule);
    schedule();
};

document.querySelectorAll('[data-cv-thread]').forEach(drawThread);
