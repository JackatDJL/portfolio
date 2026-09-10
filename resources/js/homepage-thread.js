// One end-to-end stroke, split only at shared endpoints so the long, permanent
// section geometry need not be rebuilt when the pinned project indicator moves.
export function homepageThread({ root, svg, path, slot, headings, full }) {
    const ns = 'http://www.w3.org/2000/svg';
    const projectPath = document.createElementNS(ns, 'path');
    const sectionsPath = document.createElementNS(ns, 'path');
    svg.append(projectPath, sectionsPath);
    let layout, previous = '';
    const bump = (y, center, radius, depth) => Math.abs(y - center) < radius ? (1 + Math.cos((y - center) / radius * Math.PI)) * depth / 2 : 0;
    const wave = (start, end, bumps) => {
        const ys = new Set([start, end]);
        for (let y = start; y < end; y += 8) ys.add(y);
        for (const b of bumps) if (b.y > start && b.y < end) ys.add(b.y);
        return [...ys].sort((a, b) => a - b).map((y, i) => `${i ? 'L' : 'M'}${(layout.rail + Math.sin((y - layout.join) / 66) * layout.amplitude + bumps.reduce((x, b) => x + bump(y, b.y, b.radius, b.depth), 0)).toFixed(2)},${y.toFixed(2)}`).join(' ');
    };
    function measure(size, trigger) {
        const rootTop = root.getBoundingClientRect().top + scrollY;
        const rect = slot.getBoundingClientRect();
        // The opening pin offset is removed to retain the original document anchor.
        const pinOffset = full ? Math.max(0, Math.min(scrollY - (trigger?.start ?? 0), (trigger?.end ?? 0) - (trigger?.start ?? 0))) : 0;
        const x = rect.left, y = rect.top + scrollY - rootTop - pinOffset + size.font * .94;
        const rail = size.rail, join = y + size.line + 110;
        const positions = headings.map(h => { const r = h.getBoundingClientRect(); return r.top + r.height / 2 + scrollY - rootTop; });
        layout = { rootTop, rail, join, positions, amplitude: innerWidth < 768 ? 2 : 4, depth: innerWidth < 768 ? 9 : 23, split: positions[1] - 100, end: root.scrollHeight - 30 };
        svg.setAttribute('viewBox', `0 0 ${innerWidth} ${root.scrollHeight}`);
        svg.style.height = `${root.scrollHeight}px`;
        const first = `M${x + size.wordWidths[0]},${y} Q${x + size.wordWidths[0] / 2},${y + 7} ${x},${y}`;
        const connector = ` C${x - 35},${y} ${x - 35},${y + size.line} ${x},${y + size.line}`;
        const second = ` Q${x + size.wordWidths[1] / 2},${y + size.line + 7} ${x + size.wordWidths[1]},${y + size.line}`;
        const tail = ` C${x + size.wordWidths[1] + 65},${y + size.line + 90} ${rail},${join - 75} ${rail},${join}`;
        path.setAttribute('d', first); layout.a = path.getTotalLength();
        path.setAttribute('d', first + connector); layout.ab = path.getTotalLength();
        path.setAttribute('d', first + connector + second); layout.abc = path.getTotalLength();
        path.setAttribute('d', first + connector + second + tail); layout.total = path.getTotalLength();
        sectionsPath.setAttribute('d', wave(layout.split, layout.end, positions.slice(1).map(y => ({ y, radius: 44, depth: layout.depth }))));
        layout.sectionsLength = sectionsPath.getTotalLength();
        previous = '';
    }
    function draw(progress, projectY, smallY, smallDepth) {
        if (!layout) return;
        const { a, ab, abc, total } = layout;
        const reveal = Math.min(1, progress);
        path.style.strokeDasharray = `${a + (ab - a) * reveal} ${(ab - a) * (1 - reveal)} ${abc - ab + (total - abc) * reveal} ${(total - abc) * (1 - reveal)}`;
        const y = projectY + scrollY - layout.rootTop;
        const small = smallY + scrollY - layout.rootTop;
        const key = [y, smallDepth < .1 ? 0 : small, smallDepth].map(n => n.toFixed(1)).join(',');
        if (key !== previous) {
            projectPath.setAttribute('d', wave(layout.join, layout.split, [{ y, radius: 44, depth: layout.depth }, { y: small, radius: 30, depth: smallDepth }]));
            layout.projectLength = projectPath.getTotalLength();
            previous = key;
        }
        // Body is revealed as the connecting tail arrives, never substituted for it.
        const bodyReveal = Math.max(0, Math.min(1, progress - 1));
        let remaining = (layout.projectLength + layout.sectionsLength) * bodyReveal;
        for (const [segment, length] of [[projectPath, layout.projectLength], [sectionsPath, layout.sectionsLength]]) {
            segment.style.strokeDasharray = `${length}`;
            segment.style.strokeDashoffset = `${Math.max(0, length - remaining)}`;
            remaining = Math.max(0, remaining - length);
        }
    }
    return { measure, draw, destroy() { projectPath.remove(); sectionsPath.remove(); path.removeAttribute('style'); svg.removeAttribute('style'); } };
}
