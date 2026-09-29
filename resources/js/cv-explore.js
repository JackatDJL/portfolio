import { gsap } from 'gsap';

const reduceMotionQuery = '(prefers-reduced-motion: reduce)';
const desktopPointerQuery = '(min-width: 48rem) and (pointer: fine)';
const waveformPattern = [3, 2, 7, 2, 13, 3, 5, 2, 9, 2, 4, 15, 3, 6, 2, 11, 3, 2, 8, 3, 5, 2, 16, 4, 7, 2, 10, 3, 2, 6, 13, 2];
const wheelThreshold = 72;
const wheelCooldown = 380;
const swipeThreshold = 48;

const parseChronology = value => {
    const match = /^(\d{4})(?:-(\d{1,2}))?$/.exec((value || '').trim());
    if (!match) return null;
    const year = Number(match[1]);
    const month = match[2] ? Number(match[2]) : null;
    if (month !== null && (month < 1 || month > 12)) return null;

    // Place year- and month-precision dates in the middle of their known period.
    const timestamp = month === null
        ? Date.UTC(year, 6, 1, 12)
        : Date.UTC(year, month - 1, 15, 12);
    return { year, month, timestamp };
};

const todayTimestamp = () => {
    const today = new Date();
    return Date.UTC(today.getFullYear(), today.getMonth(), today.getDate(), 12);
};

export const initCvExplore = () => {
    const root = document.querySelector('[data-cv-explore]');
    if (!root) return;

    const open = root.querySelector('[data-cv-explore-open]');
    const close = root.querySelector('[data-cv-explore-close]');
    const teaser = root.querySelector('.cv-explore__teaser');
    const content = root.querySelector('[data-cv-explore-content]');
    const stage = root.querySelector('[data-cv-timeline-stage]');
    const track = root.querySelector('[data-cv-timeline-track]');
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

    if (!open || !close || !content || !stage || !track || !ticks || !years || !events || !present || !detail || !sources.length) return;

    const eventMarkers = [];
    const eventsController = new AbortController();
    const resizeObserver = new ResizeObserver(() => scheduleBuild());
    const earliestYear = sources[0].date.year;
    const configuredStartYear = Number.parseInt(root.dataset.startYear || '', 10);
    const startYear = Number.isInteger(configuredStartYear) && configuredStartYear <= new Date().getFullYear()
        ? configuredStartYear
        : earliestYear;
    const startTimestamp = Date.UTC(startYear, 0, 1, 12);
    let activeIndex = -1;
    let positions = [];
    let endTimestamp = todayTimestamp();
    let endYear = new Date().getFullYear();
    let resizeFrame = 0;
    let disposed = false;
    let wheelDirection = 0;
    let wheelAccumulator = 0;
    let wheelLockedUntil = 0;
    let wheelResetTimer = 0;
    let touchStart = null;
    let suppressClickUntil = 0;

    const motionReduced = () => matchMedia(reduceMotionQuery).matches;
    const normalizedPosition = timestamp => {
        const range = Math.max(1, endTimestamp - startTimestamp);
        return Math.max(0, Math.min(1, (timestamp - startTimestamp) / range));
    };

    const setActive = (index, { animate = true, focus = false } = {}) => {
        if (index < 0 || index >= sources.length || index === activeIndex) return;
        activeIndex = index;
        eventMarkers.forEach((marker, markerIndex) => {
            const selected = markerIndex === index;
            marker.toggleAttribute('data-active', selected);
            marker.setAttribute('aria-pressed', String(selected));
            marker.tabIndex = selected ? 0 : -1;
        });
        detail.replaceChildren(sources[index].template.content.cloneNode(true));

        if (focus) eventMarkers[index]?.focus({ preventScroll: true });
        if (motionReduced() || !animate) {
            gsap.set(detail, { clearProps: 'opacity,transform,visibility' });
            return;
        }
        gsap.killTweensOf(detail);
        gsap.fromTo(detail, { autoAlpha: 0, x: 6 }, {
            autoAlpha: 1,
            x: 0,
            duration: .18,
            ease: 'power2.out',
            clearProps: 'opacity,transform,visibility',
        });
    };

    const labelsForWidth = width => {
        const yearSpan = Math.max(1, endYear - startYear);
        const pixelsPerYear = width / yearSpan;
        const yearStep = Math.max(1, Math.ceil(48 / pixelsPerYear));
        const selected = new Set();
        for (let year = startYear; year < endYear; year += yearStep) selected.add(year);

        const currentYearX = normalizedPosition(Date.UTC(endYear, 0, 1, 12)) * width;
        const previousYear = [...selected].at(-1);
        const previousYearX = previousYear === undefined
            ? -Infinity
            : normalizedPosition(Date.UTC(previousYear, 0, 1, 12)) * width;
        if (width - currentYearX >= 42 && currentYearX - previousYearX >= 48) selected.add(endYear);
        return selected;
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

    const laneForPosition = (position, width, placed) => {
        const x = position * width;
        const nearbyLanes = placed
            .filter(item => Math.abs(item.x - x) < 38)
            .map(item => item.lane);
        const laneOrder = [1, 0, 2];
        return laneOrder.find(lane => !nearbyLanes.includes(lane)) ?? laneOrder[placed.length % laneOrder.length];
    };

    const buildTimeline = () => {
        if (disposed || content.hidden || stage.clientWidth === 0) return;
        const width = stage.clientWidth;
        const height = stage.clientHeight;
        const today = new Date();
        endYear = today.getFullYear();
        endTimestamp = todayTimestamp();
        root.dataset.timelineEndYear = String(endYear);
        positions = sources.map(source => normalizedPosition(source.date.timestamp));

        track.dataset.startYear = String(startYear);
        track.dataset.endYear = String(endYear);
        track.dataset.startTimestamp = String(startTimestamp);
        track.dataset.endTimestamp = String(endTimestamp);
        track.style.width = `${width}px`;

        ticks.replaceChildren();
        years.replaceChildren();
        events.replaceChildren();
        eventMarkers.length = 0;

        const tickFragment = document.createDocumentFragment();
        const tickCount = Math.max(24, Math.floor(width / 7.4));
        for (let index = 0; index < tickCount; index += 1) {
            const tick = document.createElement('i');
            const position = (index + .5) / tickCount;
            tick.className = 'cv-timeline__tick';
            tick.style.left = `${position * 100}%`;
            tick.style.setProperty('--tick-height', `${waveformPattern[index % waveformPattern.length]}px`);
            tickFragment.append(tick);
        }
        ticks.append(tickFragment);

        const yearLabels = labelsForWidth(width);
        const yearFragment = document.createDocumentFragment();
        for (let year = startYear; year <= endYear; year += 1) {
            const boundary = Date.UTC(year, 0, 1, 12);
            const position = normalizedPosition(boundary);
            yearFragment.append(createYearMarker(year, position, yearLabels.has(year), year === startYear));
        }
        years.append(yearFragment);

        present.dataset.timestamp = String(endTimestamp);
        const eventFragment = document.createDocumentFragment();
        const placed = [];
        sources.forEach((source, index) => {
            const article = source.template.content.querySelector('.cv-milestone');
            const dateLabel = article.querySelector('.cv-milestone__date')?.textContent.trim();
            const title = article.querySelector('h3')?.textContent.trim();
            const position = positions[index];
            const lane = laneForPosition(position, width, placed);
            const marker = document.createElement('button');
            marker.type = 'button';
            marker.className = 'cv-timeline__event';
            marker.dataset.cvTimelineEvent = String(index);
            marker.dataset.position = position.toFixed(6);
            marker.dataset.timestamp = String(source.date.timestamp);
            marker.dataset.lane = String(lane);
            marker.style.left = `${position * 100}%`;
            marker.style.top = `${Math.round(height / 2 - 12 + (lane - 1) * 24)}px`;
            marker.setAttribute('aria-label', [dateLabel, title].filter(Boolean).join(': '));
            marker.setAttribute('aria-pressed', String(index === activeIndex));
            marker.tabIndex = index === activeIndex ? 0 : -1;
            if (source.template.dataset.featured === 'true') marker.dataset.featured = '';
            if (index === activeIndex) marker.dataset.active = '';
            marker.append(document.createElement('span'));
            eventFragment.append(marker);
            eventMarkers.push(marker);
            placed.push({ x: position * width, lane });
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
    };

    const scheduleBuild = () => {
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

    const handleWheel = event => {
        if (motionReduced() || !matchMedia(desktopPointerQuery).matches || event.ctrlKey) return;
        const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
        if (!delta) return;
        const direction = delta > 0 ? 1 : -1;
        const next = activeIndex + direction;
        if (next < 0 || next >= sources.length) {
            wheelAccumulator = 0;
            wheelDirection = 0;
            return;
        }

        event.preventDefault();
        if (wheelDirection !== direction) wheelAccumulator = 0;
        wheelDirection = direction;
        if (performance.now() >= wheelLockedUntil) wheelAccumulator += Math.min(Math.abs(delta), wheelThreshold);
        clearTimeout(wheelResetTimer);
        wheelResetTimer = window.setTimeout(() => {
            wheelAccumulator = 0;
            wheelDirection = 0;
        }, 240);

        if (wheelAccumulator >= wheelThreshold && performance.now() >= wheelLockedUntil) {
            wheelAccumulator = 0;
            wheelLockedUntil = performance.now() + wheelCooldown;
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
            duration: .24,
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
        gsap.to(content, { opacity: 0, duration: .16, ease: 'power1.out' });
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
