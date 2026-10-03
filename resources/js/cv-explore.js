import { gsap } from 'gsap';

const reduceMotionQuery = '(prefers-reduced-motion: reduce)';
const desktopPointerQuery = '(min-width: 48rem) and (pointer: fine)';
const wheelThreshold = 72;
const wheelIdleDelay = 220;
const swipeThreshold = 48;
const clusterDistance = 18;
const clusterSpread = 6;

const utcTimestamp = (year, monthIndex, day) => {
    const date = new Date(0);
    date.setUTCHours(12, 0, 0, 0);
    date.setUTCFullYear(year, monthIndex, day);
    return date.getTime();
};

export const parseChronology = value => {
    const match = /^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/.exec((value || '').trim());
    if (!match) return null;

    const year = Number(match[1]);
    const month = match[2] ? Number(match[2]) : null;
    const day = match[3] ? Number(match[3]) : null;
    if (month !== null && (month < 1 || month > 12)) return null;

    if (day !== null) {
        const check = new Date(utcTimestamp(year, month - 1, day));
        if (check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day) return null;
    }

    // Year and month precision use the middle of the known period for geometry.
    const timestamp = day !== null
        ? utcTimestamp(year, month - 1, day)
        : month !== null
            ? utcTimestamp(year, month - 1, 15)
            : utcTimestamp(year, 6, 1);

    return { year, month, day, timestamp };
};

const todayTimestamp = () => {
    const today = new Date();
    return utcTimestamp(today.getFullYear(), today.getMonth(), today.getDate());
};

export const initCvExplore = () => {
    const root = document.querySelector('[data-cv-explore]');
    if (!root) return;

    const open = root.querySelector('[data-cv-explore-open]');
    const close = root.querySelector('[data-cv-explore-close]');
    const teaser = root.querySelector('.cv-explore__teaser');
    const content = root.querySelector('[data-cv-explore-content]');
    const stage = root.querySelector('[data-cv-timeline-stage]');
    const ticks = root.querySelector('[data-cv-timeline-ticks]');
    const years = root.querySelector('[data-cv-timeline-years]');
    const events = root.querySelector('[data-cv-timeline-events]');
    const present = root.querySelector('[data-cv-timeline-present]');
    const detail = root.querySelector('[data-cv-active-detail]');
    const sources = [...root.querySelectorAll('template[data-cv-milestone]')]
        .map((template, order) => ({
            template,
            date: parseChronology(template.dataset.date),
            order,
        }))
        .filter(item => item.date)
        .sort((a, b) => a.date.timestamp - b.date.timestamp || a.order - b.order);

    if (!open || !close || !content || !stage || !ticks || !years || !events || !present || !detail || !sources.length) return;

    const eventMarkers = [];
    const eventsController = new AbortController();
    const resizeObserver = new ResizeObserver(() => scheduleBuild());
    const earliestYear = sources[0].date.year;
    const configuredStartYear = Number.parseInt(root.dataset.startYear || '', 10);
    const currentYear = new Date().getFullYear();
    const startYear = Number.isInteger(configuredStartYear)
        && configuredStartYear <= currentYear
        && configuredStartYear <= earliestYear
        ? configuredStartYear
        : earliestYear;
    const startTimestamp = utcTimestamp(startYear, 0, 1);
    let activeIndex = -1;
    let endTimestamp = todayTimestamp();
    let endYear = currentYear;
    let resizeFrame = 0;
    let lastBuildKey = '';
    let disposed = false;
    let wheelDirection = 0;
    let wheelAccumulator = 0;
    let wheelLatched = false;
    let wheelResetTimer = 0;
    let touchStart = null;
    let suppressClickUntil = 0;

    const motionReduced = () => matchMedia(reduceMotionQuery).matches;
    const normalizedPosition = timestamp => {
        const range = Math.max(1, endTimestamp - startTimestamp);
        return Math.max(0, Math.min(1, (timestamp - startTimestamp) / range));
    };

    const setActive = (index, { animate = true, focus = false } = {}) => {
        if (index < 0 || index >= sources.length) return;
        if (focus) eventMarkers[index]?.focus({ preventScroll: true });
        if (index === activeIndex) return;

        activeIndex = index;
        eventMarkers.forEach((marker, markerIndex) => {
            const selected = markerIndex === index;
            marker.toggleAttribute('data-active', selected);
            marker.setAttribute('aria-pressed', String(selected));
            marker.tabIndex = selected ? 0 : -1;
        });
        detail.replaceChildren(sources[index].template.content.cloneNode(true));

        if (motionReduced() || !animate) {
            gsap.set(detail, { clearProps: 'opacity,transform,visibility' });
            return;
        }
        gsap.killTweensOf(detail);
        gsap.fromTo(detail, { autoAlpha: 0, x: 5 }, {
            autoAlpha: 1,
            x: 0,
            duration: .16,
            ease: 'power2.out',
            clearProps: 'opacity,transform,visibility',
        });
    };

    const labelYearsForWidth = width => {
        const yearSpan = Math.max(1, endYear - startYear);
        const minimumGap = width < 420 ? 76 : width < 800 ? 66 : 50;
        const step = Math.max(1, Math.ceil(minimumGap * yearSpan / width));
        const labels = new Set([startYear]);
        let lastX = 0;

        for (let year = startYear + 1; year <= endYear; year += 1) {
            if ((year - startYear) % step !== 0) continue;
            const x = normalizedPosition(utcTimestamp(year, 0, 1)) * width;
            if (x - lastX < minimumGap || width - x < 48) continue;
            labels.add(year);
            lastX = x;
        }

        return labels;
    };

    const createYearMarker = (year, position, showLabel, isStart) => {
        const marker = document.createElement('span');
        marker.className = 'cv-timeline__year';
        marker.dataset.year = String(year);
        marker.style.left = `${position * 100}%`;
        marker.setAttribute('aria-hidden', 'true');
        if (isStart) marker.dataset.start = '';

        if (showLabel) {
            const label = document.createElement('span');
            label.className = 'cv-timeline__year-label';
            label.dataset.cvYearLabel = '';
            label.textContent = String(year);
            if (isStart) label.dataset.align = 'start';
            marker.append(label);
        }
        return marker;
    };

    const pruneOverlappingYearLabels = trackWidth => {
        const labels = [...years.querySelectorAll('[data-cv-year-label]')];
        const startLabel = labels.find(label => label.dataset.align === 'start');
        const presentLabel = present.querySelector('span');
        if (!startLabel || !presentLabel) return;

        const trackBox = root.querySelector('[data-cv-timeline-track]').getBoundingClientRect();
        let previous = startLabel.getBoundingClientRect();
        const presentBox = presentLabel.getBoundingClientRect();
        for (const label of labels.filter(candidate => candidate !== startLabel)) {
            const box = label.getBoundingClientRect();
            if (box.left < previous.right + 8 || box.right + 8 > presentBox.left || box.left < trackBox.left || box.right > trackBox.left + trackWidth) {
                label.remove();
                continue;
            }
            previous = box;
        }
    };

    const clusterEvents = (positions, width) => {
        const groups = [];
        for (let index = 0; index < positions.length; index += 1) {
            const x = positions[index] * width;
            const previous = groups.at(-1);
            const previousX = previous ? positions[previous.end] * width : null;
            if (previous && x - previousX < clusterDistance) previous.end = index;
            else groups.push({ start: index, end: index });
        }

        return groups.map(group => {
            const previousX = group.start === 0 ? 0 : positions[group.start - 1] * width;
            const nextX = group.end === positions.length - 1 ? width : positions[group.end + 1] * width;
            const firstX = positions[group.start] * width;
            const lastX = positions[group.end] * width;
            const left = group.start === 0 ? 0 : (previousX + firstX) / 2;
            const right = group.end === positions.length - 1 ? width : (lastX + nextX) / 2;
            const count = group.end - group.start + 1;
            const anchorIndex = group.start + Math.floor((count - 1) / 2);

            return { ...group, left, right, count, anchorIndex };
        });
    };

    const buildTimeline = () => {
        if (disposed || content.hidden || stage.clientWidth === 0) return;
        const width = stage.clientWidth;
        const day = todayTimestamp();
        const year = new Date().getFullYear();
        const buildKey = `${width}:${day}`;
        if (buildKey === lastBuildKey) return;

        const restoreFocus = events.contains(document.activeElement);
        endYear = year;
        endTimestamp = day;
        const track = root.querySelector('[data-cv-timeline-track]');
        track.dataset.startTimestamp = String(startTimestamp);
        track.dataset.endTimestamp = String(endTimestamp);
        track.dataset.startYear = String(startYear);
        track.dataset.endYear = String(endYear);
        root.dataset.timelineEndYear = String(endYear);
        stage.setAttribute('aria-label', `Lebenszeitachse von ${startYear} bis heute`);

        const positions = sources.map(source => normalizedPosition(source.date.timestamp));
        ticks.replaceChildren();
        years.replaceChildren();
        events.replaceChildren();
        eventMarkers.length = 0;

        const tickFragment = document.createDocumentFragment();
        const spacing = 12;
        const tickCount = Math.max(12, Math.floor(width / spacing));
        for (let index = 0; index < tickCount; index += 1) {
            const tick = document.createElement('i');
            tick.className = 'cv-timeline__tick';
            tick.style.left = `${((index + .5) / tickCount) * 100}%`;
            tickFragment.append(tick);
        }
        ticks.append(tickFragment);

        const yearLabels = labelYearsForWidth(width);
        const yearFragment = document.createDocumentFragment();
        for (let calendarYear = startYear; calendarYear <= endYear; calendarYear += 1) {
            const position = normalizedPosition(utcTimestamp(calendarYear, 0, 1));
            yearFragment.append(createYearMarker(calendarYear, position, yearLabels.has(calendarYear), calendarYear === startYear));
        }
        years.append(yearFragment);
        pruneOverlappingYearLabels(width);

        present.dataset.timestamp = String(endTimestamp);
        present.setAttribute('aria-label', 'Heute');

        const clusters = clusterEvents(positions, width);
        const eventFragment = document.createDocumentFragment();
        clusters.forEach(cluster => {
            const clusterWidth = Math.max(0, cluster.right - cluster.left);
            for (let index = cluster.start; index <= cluster.end; index += 1) {
                const source = sources[index];
                const article = source.template.content.querySelector('.cv-milestone');
                const dateLabel = article.querySelector('.cv-milestone__date')?.textContent.trim();
                const title = article.querySelector('h3')?.textContent.trim();
                const localIndex = index - cluster.start;
                const cellLeft = cluster.left + (clusterWidth * localIndex / cluster.count);
                const cellRight = cluster.left + (clusterWidth * (localIndex + 1) / cluster.count);
                const anchorX = positions[cluster.anchorIndex] * width;
                const spreadIndex = index - cluster.anchorIndex;
                const spreadSteps = Math.max(1, cluster.count - 1);
                const visualOffset = cluster.count === 1 ? 0 : spreadIndex * (clusterSpread / spreadSteps);
                const visualX = Math.max(0, Math.min(width, anchorX + visualOffset));
                const marker = document.createElement('button');
                marker.type = 'button';
                marker.className = 'cv-timeline__event';
                marker.dataset.cvTimelineEvent = String(index);
                marker.dataset.position = positions[index].toFixed(8);
                marker.dataset.timestamp = String(source.date.timestamp);
                marker.dataset.date = source.template.dataset.date;
                marker.dataset.clusterAnchor = positions[cluster.anchorIndex].toFixed(8);
                marker.style.left = `${cellLeft}px`;
                marker.style.width = `${cellRight - cellLeft}px`;
                marker.style.setProperty('--event-x', `${visualX - cellLeft}px`);
                marker.setAttribute('aria-label', [dateLabel, title].filter(Boolean).join(': '));
                marker.setAttribute('aria-pressed', String(index === activeIndex));
                marker.tabIndex = index === activeIndex ? 0 : -1;
                marker.setAttribute('aria-controls', 'cv-active-milestone');
                marker.append(document.createElement('span'));
                eventFragment.append(marker);
                eventMarkers.push(marker);
            }
        });
        events.append(eventFragment);

        if (activeIndex < 0) setActive(0, { animate: false });
        else {
            eventMarkers.forEach((marker, index) => {
                marker.toggleAttribute('data-active', index === activeIndex);
                marker.setAttribute('aria-pressed', String(index === activeIndex));
                marker.tabIndex = index === activeIndex ? 0 : -1;
            });
        }

        lastBuildKey = buildKey;
        if (restoreFocus) eventMarkers[activeIndex]?.focus({ preventScroll: true });
    };

    const scheduleBuild = () => {
        if (content.hidden) return;
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(buildTimeline);
    };

    const activate = (index, focus = false) => setActive(index, { focus });

    const handleTimelineKey = event => {
        const marker = event.target.closest?.('[data-cv-timeline-event]');
        if (!marker) return;
        let next = activeIndex;
        if (event.key === 'ArrowLeft') next = Math.max(0, activeIndex - 1);
        else if (event.key === 'ArrowRight') next = Math.min(sources.length - 1, activeIndex + 1);
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = sources.length - 1;
        else return;
        event.preventDefault();
        activate(next, true);
    };

    const resetWheelGesture = () => {
        clearTimeout(wheelResetTimer);
        wheelResetTimer = window.setTimeout(() => {
            wheelAccumulator = 0;
            wheelDirection = 0;
            wheelLatched = false;
        }, wheelIdleDelay);
    };

    const handleWheel = event => {
        if (!matchMedia(desktopPointerQuery).matches || event.ctrlKey) return;
        const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
        if (!delta) return;
        const direction = delta > 0 ? 1 : -1;
        const next = activeIndex + direction;
        if (next < 0 || next >= sources.length) {
            wheelAccumulator = 0;
            wheelDirection = 0;
            wheelLatched = false;
            return;
        }

        event.preventDefault();
        resetWheelGesture();
        if (wheelLatched) return;
        if (wheelDirection !== direction) {
            wheelAccumulator = 0;
        }
        wheelDirection = direction;

        wheelAccumulator += Math.min(Math.abs(delta), wheelThreshold);
        if (wheelAccumulator >= wheelThreshold) {
            wheelAccumulator = 0;
            wheelLatched = true;
            activate(next);
        }
    };

    const handlePointerDown = event => {
        if (event.pointerType !== 'touch') return;
        touchStart = { id: event.pointerId, x: event.clientX, y: event.clientY };
    };

    const handlePointerUp = event => {
        if (!touchStart || event.pointerId !== touchStart.id) return;
        const deltaX = event.clientX - touchStart.x;
        const deltaY = event.clientY - touchStart.y;
        touchStart = null;
        if (Math.abs(deltaX) < swipeThreshold || Math.abs(deltaX) <= Math.abs(deltaY) * 1.25) return;

        const next = activeIndex + (deltaX < 0 ? 1 : -1);
        if (next >= 0 && next < sources.length) {
            suppressClickUntil = performance.now() + 450;
            activate(next);
        }
    };

    const clearRootSize = () => gsap.set(root, { clearProps: 'height,overflow' });

    const openTimeline = () => {
        if (disposed || !content.hidden) return;
        gsap.killTweensOf(root);
        const closedHeight = root.getBoundingClientRect().height;
        teaser.hidden = true;
        content.hidden = false;
        lastBuildKey = '';
        buildTimeline();
        const expandedHeight = root.scrollHeight;
        root.style.height = `${closedHeight}px`;
        root.style.overflow = 'hidden';
        open.setAttribute('aria-expanded', 'true');
        eventMarkers[activeIndex]?.focus({ preventScroll: true });
        if (motionReduced()) {
            clearRootSize();
            return;
        }
        requestAnimationFrame(() => gsap.to(root, {
            height: expandedHeight,
            duration: .22,
            ease: 'power2.out',
            onComplete: clearRootSize,
        }));
    };

    const closeTimeline = () => {
        if (disposed || content.hidden) return;
        gsap.killTweensOf(root);
        gsap.killTweensOf(content);
        const expandedHeight = root.getBoundingClientRect().height;
        teaser.hidden = false;
        const closedHeight = teaser.getBoundingClientRect().height;
        teaser.hidden = true;
        root.style.height = `${expandedHeight}px`;
        root.style.overflow = 'hidden';
        open.setAttribute('aria-expanded', 'false');

        const finish = () => {
            content.hidden = true;
            teaser.hidden = false;
            gsap.set(content, { clearProps: 'opacity,transform,visibility' });
            clearRootSize();
            open.focus({ preventScroll: true });
        };
        if (motionReduced()) {
            finish();
            return;
        }
        gsap.to(content, { opacity: 0, duration: .14, ease: 'power1.out' });
        gsap.to(root, {
            height: closedHeight,
            duration: .2,
            ease: 'power2.inOut',
            onComplete: finish,
        });
    };

    open.addEventListener('click', openTimeline, { signal: eventsController.signal });
    close.addEventListener('click', closeTimeline, { signal: eventsController.signal });
    events.addEventListener('keydown', handleTimelineKey, { signal: eventsController.signal });
    events.addEventListener('click', event => {
        if (performance.now() < suppressClickUntil) {
            event.preventDefault();
            return;
        }
        const marker = event.target.closest('[data-cv-timeline-event]');
        if (marker) activate(Number(marker.dataset.cvTimelineEvent));
    }, { signal: eventsController.signal });
    stage.addEventListener('wheel', handleWheel, { passive: false, signal: eventsController.signal });
    stage.addEventListener('pointerdown', handlePointerDown, { passive: true, signal: eventsController.signal });
    stage.addEventListener('pointerup', handlePointerUp, { passive: true, signal: eventsController.signal });
    stage.addEventListener('pointercancel', () => { touchStart = null; }, { passive: true, signal: eventsController.signal });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !content.hidden && root.contains(document.activeElement)) closeTimeline();
    }, { signal: eventsController.signal });

    resizeObserver.observe(stage);
    window.addEventListener('pagehide', event => {
        if (event.persisted) return;
        disposed = true;
        resizeObserver.disconnect();
        cancelAnimationFrame(resizeFrame);
        clearTimeout(wheelResetTimer);
        eventsController.abort();
        gsap.killTweensOf(root);
        gsap.killTweensOf(detail);
    }, { signal: eventsController.signal });
};
