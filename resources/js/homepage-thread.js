import { gsap } from 'gsap';

const ns = 'http://www.w3.org/2000/svg';
const clamp = value => Math.max(0, Math.min(1, value));

// One SVG, with contiguous measured stroke segments. The travelling portion
// shares the wordmark's transform; the established thread has document anchors.
export function homepageThread({ root, svg, path, headings, projects, full, reduce = false }) {
    const travel = document.createElementNS(ns, 'g');
    const body = document.createElementNS(ns, 'g');
    const sections = document.createElementNS(ns, 'path');
    path.replaceWith(travel);
    travel.append(path);
    svg.append(body);
    body.append(sections);
    let geometry, movingPath, lastWordPosition;
    const merge = { amount: 0 };
    const mergeTimeline = reduce ? null : gsap.timeline({ paused: true })
        .to(merge, { amount: 1, duration: .4, ease: 'power2.inOut' })
        .to(merge, { amount: 0, duration: .6, ease: 'power2.inOut' });
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
        measure(size) {
            const rail = size.rail;
            // offsetTop follows layout, independent of entrance transforms.
            const documentY = element => {
                let y = 0;
                for (let node = element; node; node = node.offsetParent) y += node.offsetTop;
                return y;
            };
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
            const marks = headings.map(h => ({ y: documentY(h) + h.offsetHeight / 2, radius: innerWidth < 768 ? 30 : 44, depth: innerWidth < 768 ? 9 : 23 }));
            projects.forEach(project => {
                const h = project.querySelector('h3');
                marks.push({ y: documentY(h) + h.offsetHeight / 2, radius: 20, depth: 11 });
            });
            geometry = { a, ab, abc, total, rail, marks,
                start: full ? 0 : documentY(headings[0]) - 70,
                end: documentY(root) + root.offsetHeight - 30 };

        },
        draw({ peel = 0, establish = 0, x = 0, y = 0, scale = 1, wordX = 0, wordY = 0, headingShift = 0 }) {
            if (!geometry) return;
            if (full && (lastWordPosition !== `${wordX},${wordY}`)) {
                Object.assign(geometry, movingPath(wordX, wordY));
                lastWordPosition = `${wordX},${wordY}`;
            }
            const { a, ab, abc, total } = geometry;
            const headingY = geometry.marks[0].y + headingShift;
            let expansion = 0;
            if (headingShift > 0 && !reduce) {
                for (const project of geometry.marks.slice(headings.length)) {
                    const reach = geometry.marks[0].radius + project.radius;
                    const phase = clamp((reach - (project.y - headingY)) / (reach + 100));
                    // Scrub one gentle receive/settle timeline. No overshoot or
                    // queued playback when the visitor changes scroll direction.
                    mergeTimeline.progress(phase);
                    expansion = Math.max(expansion, merge.amount);
                }
                expansion *= innerWidth < 768 ? 1 : 2.5;
            }
            const marks = geometry.marks.map((mark, index) => {
                if (index === 0) return { ...mark, y: headingY, depth: mark.depth + expansion };
                if (index < headings.length || headingShift === 0) return mark;
                // The approaching Project bump joins the major bump continuously.
                // Position alone controls this, so reverse scroll splits it back out.
                const separation = clamp((mark.y - headingY) / (geometry.marks[0].radius + mark.radius));
                const blend = separation * separation * (3 - 2 * separation);
                return { ...mark, depth: mark.depth * blend };
            });
            sections.setAttribute('d', wave(geometry.rail, geometry.start, geometry.end, marks));
            travel.setAttribute('transform', `translate(${x} ${y}) scale(${scale})`);
            if (!full) {
                travel.style.visibility = 'visible';
                path.style.visibility = 'visible';
                path.style.strokeDashoffset = '0';
                path.style.strokeDasharray = `${a} ${ab - a} ${abc - ab} ${total + 1}`;
                body.style.visibility = 'visible';
                body.setAttribute('transform', `translate(0 ${-scrollY})`);
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
            if (establish < 1) range(sections, 0, scrollY + innerHeight * establish, sections.getTotalLength());
            else sections.removeAttribute('style');
            body.setAttribute('transform', `translate(0 ${-scrollY})`);
        },
        destroy() { mergeTimeline?.kill(); travel.replaceWith(path); body.remove(); path.removeAttribute('style'); path.removeAttribute('d'); svg.removeAttribute('viewBox'); },
    };
}
