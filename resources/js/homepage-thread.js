const ns = 'http://www.w3.org/2000/svg';
const clamp = value => Math.max(0, Math.min(1, value));

// One SVG, with contiguous measured stroke segments. The travelling portion
// shares the wordmark's transform; the established thread has document anchors.
export function homepageThread({ root, svg, path, headings, projects, full }) {
    const travel = document.createElementNS(ns, 'g');
    const body = document.createElementNS(ns, 'g');
    const railPath = document.createElementNS(ns, 'path');
    const sections = document.createElementNS(ns, 'path');
    const bumps = projects.map((_, index) => {
        const p = document.createElementNS(ns, 'path');
        p.dataset.projectBump = index;
        body.append(p);
        return p;
    });
    path.replaceWith(travel);
    travel.append(path);
    body.prepend(railPath);
    svg.append(body, sections);
    let geometry, movingPath, lastWordPosition, projectGeometry;
    const wave = (rail, start, end, marks) => {
        const points = new Set([start, end]);
        for (let y = start; y < end; y += 6) points.add(y);
        marks.forEach(m => points.add(m.y));
        return [...points].filter(y => y >= start && y <= end).sort((a, b) => a - b).map((y, i) => {
            const offset = marks.reduce((sum, m) => sum + (Math.abs(y - m.y) < m.radius ? (1 + Math.cos((y - m.y) / m.radius * Math.PI)) * m.depth / 2 : 0), 0);
            return `${i ? 'L' : 'M'}${rail + offset},${y}`;
        }).join(' ');
    };
    const range = (element, tail, head, total) => {
        element.style.strokeDasharray = `${Math.max(0, head - tail)} ${total + 1}`;
        element.style.strokeDashoffset = `${-tail}`;
        element.style.visibility = head > tail ? 'visible' : 'hidden';
    };
    return {
        measure(size, trigger) {
            const rect = root.getBoundingClientRect();
            const cinemaTop = rect.top + scrollY;
            const end = trigger?.end ?? cinemaTop;
            const rail = size.rail;
            const projectY = headings[0].offsetHeight / 2 + parseFloat(getComputedStyle(root.querySelector('[data-home-project-stage]')).paddingTop);
            const join = innerHeight;
            const documentY = element => element.getBoundingClientRect().top + scrollY;
            const later = headings.slice(1).map(h => ({ y: documentY(h) + h.offsetHeight / 2 - end, radius: 44, depth: innerWidth < 768 ? 9 : 23 }));
            const bottom = documentY(root) + root.offsetHeight - end - 30;
            svg.setAttribute('viewBox', `0 0 ${innerWidth} ${innerHeight}`);
            movingPath = (wordX = 0, wordY = 0) => {
            const first = `M${size.wordWidths[0]},${size.font * .94} Q${size.wordWidths[0] / 2},${size.font * .94 + 7} 0,${size.font * .94}`;
            const connector = ` C-35,${size.font * .94} -35,${size.line + size.font * .94} ${wordX},${size.line + size.font * .94 + wordY}`;
            const second = ` Q${wordX + size.wordWidths[1] / 2},${size.line + size.font * .94 + wordY + 7} ${wordX + size.wordWidths[1]},${size.line + size.font * .94 + wordY}`;
            const localX = (rail - size.dockX) / size.scale;
            const localY = -size.dockY / size.scale;
            const tail = ` C${wordX + size.wordWidths[1] + 65},${size.height + 100} ${localX},${size.height + 160} ${localX},${localY}`;
            path.setAttribute('d', first); const a = path.getTotalLength();
            path.setAttribute('d', first + connector); const ab = path.getTotalLength();
            path.setAttribute('d', first + connector + second); const abc = path.getTotalLength();
            path.setAttribute('d', first + connector + second + tail); const total = path.getTotalLength();
            return { a, ab, abc, total };
            };
            const { a, ab, abc, total } = movingPath();
            lastWordPosition = null;
            if (full) {
                const layer = root.querySelector('[data-home-projects]');
                const layerTransform = layer.style.transform;
                layer.style.transform = 'none';
                const cinema = root.querySelector('[data-home-cinema]').getBoundingClientRect();
                const titleCenters = projects.map(project => {
                    const saved = project.style.transform;
                    const copy = project.querySelector('.home-project__copy');
                    project.style.transform = 'none';
                    copy.style.transform = '';
                    const title = project.querySelector('h3').getBoundingClientRect();
                    project.style.transform = saved;
                    return title.top + title.height / 2 - cinema.top;
                });
                layer.style.transform = layerTransform;
                const marks = [{ y: projectY, radius: 44, depth: 23 }];
                const small = projects.map((project, i) => {
                    const y = titleCenters[0] + i * 48;
                    project.querySelector('.home-project__copy').style.transform = `translateY(${y - titleCenters[i]}px)`;
                    return { y, radius: 20, depth: 11 };
                });
                projectGeometry = { rail, join, marks, small };
                railPath.setAttribute('d', wave(rail, 0, join, [...marks, ...small]));
                small.forEach((m, i) => { bumps[i].setAttribute('d', wave(0, m.y - m.radius, m.y + m.radius, [m])); bumps[i].dataset.rail = rail; });
                sections.setAttribute('d', wave(rail, join, Math.max(join, bottom), later));
            } else {
                const marks = headings.map(h => ({ y: documentY(h) + h.offsetHeight / 2, radius: innerWidth < 768 ? 30 : 44, depth: innerWidth < 768 ? 9 : 23 }));
                projects.forEach(p => { const h = p.querySelector('h3'); marks.push({ y: documentY(h) + h.offsetHeight / 2, radius: 20, depth: 11 }); });
                sections.setAttribute('d', wave(rail, documentY(headings[0]) - 70, documentY(root) + root.offsetHeight - 30, marks));
            }
            geometry = { a, ab, abc, total, end, bodyLength: railPath.getTotalLength() };
        },
        draw({ peel = 0, establish = 0, x = 0, y = 0, scale = 1, active = -1, emphasis = [], wordX = 0, wordY = 0, release = 0 }) {
            if (!geometry) return;
            if (full && (lastWordPosition !== `${wordX},${wordY}`)) {
                Object.assign(geometry, movingPath(wordX, wordY));
                lastWordPosition = `${wordX},${wordY}`;
            }
            const { a, ab, abc, total, end, bodyLength } = geometry;
            travel.setAttribute('transform', `translate(${x} ${y}) scale(${scale})`);
            if (!full) {
                travel.style.visibility = 'visible';
                path.style.visibility = 'visible';
                path.style.strokeDashoffset = '0';
                path.style.strokeDasharray = `${a} ${ab - a} ${abc - ab} ${total + 1}`;
                body.style.visibility = 'hidden';
                sections.setAttribute('transform', `translate(0 ${-scrollY})`);
                return;
            }
            travel.style.visibility = '';
            if (peel === 0) {
                path.style.visibility = 'visible';
                path.style.strokeDashoffset = '0';
                path.style.strokeDasharray = `${a * scale} ${(ab - a) * scale} ${(abc - ab) * scale} ${(total + 1) * scale}`;
            } else {
                // Both endpoints advance. The tail clears every underline and
                // connector point, finally leaving only the vertical thread.
                range(path, total * clamp(peel) * scale, (abc + (total - abc) * clamp(peel * 1.7)) * scale, total * scale);
            }
            body.style.visibility = establish > 0 ? 'visible' : 'hidden';
            range(railPath, 0, bodyLength * establish, bodyLength);
            body.setAttribute('transform', `translate(0 ${release})`);
            railPath.style.opacity = '.65';
            const { rail, join, marks, small } = projectGeometry;
            railPath.setAttribute('d', wave(rail, 0, join, [...marks, ...small.map((m, i) => ({ ...m, depth: m.depth * (1 + (emphasis[i] ?? 0) * .45) }))]));
            bumps.forEach((p, i) => { const amount = emphasis[i] ?? 0; p.style.opacity = establish === 1 ? String(.3 + amount * .7) : '0'; p.setAttribute('transform', `translate(${p.dataset.rail} 0) scale(${1 + amount * .45} 1)`); });
            sections.style.visibility = establish === 1 ? 'visible' : 'hidden';
            sections.setAttribute('transform', `translate(0 ${end - scrollY})`);
        },
        destroy() { projects.forEach(p => { p.querySelector('.home-project__copy').style.transform = ''; }); travel.replaceWith(path); body.remove(); sections.remove(); path.removeAttribute('style'); path.removeAttribute('d'); svg.removeAttribute('viewBox'); },
    };
}
