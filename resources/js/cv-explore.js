import { gsap } from 'gsap';

const reduceMotionQuery = '(prefers-reduced-motion: reduce)';
const desktopPointerQuery = '(min-width: 48rem) and (pointer: fine)';
const wheelThreshold = 72;
const wheelIdleDelay = 220;
const swipeThreshold = 48;
const strokeCount = 96;

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

const padIndex = value => String(value).padStart(2, '0');

export const initCvExplore = () => {
    const root = document.querySelector('[data-cv-explore]');
    if (!root) return;

    const open = root.querySelector('[data-cv-explore-open]');
    const close = root.querySelector('[data-cv-explore-close]');
    const teaser = root.querySelector('.cv-explore__teaser');
    const previewWave = root.querySelector('[data-cv-explore-preview-wave]');
    const content = root.querySelector('[data-cv-explore-content]');
    const stage = root.querySelector('[data-cv-timeline-stage]');
    const waveform = root.querySelector('[data-cv-timeline-track]');
    const bars = root.querySelector('[data-cv-timeline-bars]');
    const needle = root.querySelector('[data-cv-timeline-needle]');
    const years = root.querySelector('[data-cv-timeline-years]');
    const startLabel = root.querySelector('[data-cv-timeline-start]');
    const todayLabel = root.querySelector('[data-cv-timeline-today]');
    const detail = root.querySelector('[data-cv-active-detail]');
    const currentIndexLabel = root.querySelector('[data-cv-timeline-current]');
    const totalIndexLabel = root.querySelector('[data-cv-timeline-total]');
    const sources = [...root.querySelectorAll('template[data-cv-milestone]')]
        .map((template, order) => ({
            template,
            date: parseChronology(template.dataset.date),
            order,
        }))
        .filter(item => item.date)
        .map(item => ({
            ...item,
            dateLabel: item.template.content.querySelector('.cv-milestone__date')?.textContent.trim() || '',
            title: item.template.content.querySelector('h3')?.textContent.trim() || '',
        }))
        .sort((a, b) => a.date.timestamp - b.date.timestamp || a.order - b.order);

    if (!open || !close || !teaser || !previewWave || !content || !stage || !waveform || !bars || !needle || !years || !startLabel || !todayLabel || !detail || !currentIndexLabel || !totalIndexLabel || !sources.length) return;

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
    let activeIndex = Math.min(4, sources.length - 1);
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
    let hoveredMilestone = null;
    const waveEnvelope = { center: 0 };

    const motionReduced = () => matchMedia(reduceMotionQuery).matches;
    const normalizedPosition = timestamp => {
        const range = Math.max(1, endTimestamp - startTimestamp);
        return Math.max(0, Math.min(1, (timestamp - startTimestamp) / range));
    };
    const positionFor = timestamp => normalizedPosition(timestamp) * 100;
    const selectedText = index => `${sources[index].dateLabel}: ${sources[index].title}`;
    waveEnvelope.center = positionFor(sources[activeIndex].date.timestamp);

    const buildPreview = () => {
        const fragment = document.createDocumentFragment();
        for (let index = 0; index < 36; index += 1) {
            const line = document.createElement('i');
            line.style.height = `${4 + ((Math.sin(index * 1.7) + 1) * 5)}px`;
            fragment.append(line);
        }
        previewWave.replaceChildren(fragment);
    };

    const drawWaveform = () => {
        bars.querySelectorAll('i').forEach((bar, index) => {
            const position = (index / (strokeCount - 1)) * 100;
            const base = 2
                + ((Math.sin(index * 2.31) + 1) / 2) * 5
                + ((Math.sin(index * .63 + 1.5) + 1) / 2) * 3;
            const activeEnvelope = Math.max(0, 1 - Math.abs(position - waveEnvelope.center) / (motionReduced() ? 6.5 : 8.5));
            const pointEnvelope = sources.reduce((amount, milestone, milestoneIndex) => {
                if (milestoneIndex === activeIndex) return amount;
                const pointDistance = Math.abs(position - positionFor(milestone.date.timestamp));
                const eventLift = Math.max(0, 1 - pointDistance / 1.15) * 23;
                const hoveredLift = milestoneIndex === hoveredMilestone
                    ? Math.max(0, 1 - pointDistance / 1.7) * 8
                    : 0;
                return amount + eventLift + hoveredLift;
            }, 0);
            const isPoint = sources.some((milestone, milestoneIndex) => milestoneIndex !== activeIndex
                && Math.abs(position - positionFor(milestone.date.timestamp)) < 1.25);
            const isHovered = hoveredMilestone !== null
                && Math.abs(position - positionFor(sources[hoveredMilestone].date.timestamp)) < 1.8;
            const height = Math.max(3, base + activeEnvelope * 42 + pointEnvelope);
            bar.classList.toggle('is-milestone', isPoint);
            bar.classList.toggle('is-hovered', isHovered);
            bar.style.height = `${height}px`;
            bar.style.opacity = String(Math.min(1, .48 + activeEnvelope * .52 + pointEnvelope / 48));
        });
        needle.style.left = `${waveEnvelope.center}%`;
    };

    const moveWaveTo = (timestamp, animate) => {
        const target = positionFor(timestamp);
        gsap.killTweensOf(waveEnvelope);
        if (motionReduced() || !animate) {
            waveEnvelope.center = target;
            drawWaveform();
            return;
        }

        const distance = Math.abs(target - waveEnvelope.center);
        gsap.to(waveEnvelope, {
            center: target,
            duration: Math.min(.58, .16 + distance * .0055),
            ease: 'power2.inOut',
            onUpdate: drawWaveform,
        });
        drawWaveform();
    };

    const updateSelection = (index, { animate = true, focus = false, force = false } = {}) => {
        if (index < 0 || index >= sources.length) return;
        if (focus) stage.focus({ preventScroll: true });
        if (index === activeIndex && !force) return;

        activeIndex = index;
        const selected = sources[index];
        stage.setAttribute('aria-valuemax', String(sources.length));
        stage.setAttribute('aria-valuenow', String(index + 1));
        stage.setAttribute('aria-valuetext', selectedText(index));
        currentIndexLabel.textContent = padIndex(index + 1);
        totalIndexLabel.textContent = padIndex(sources.length);
        moveWaveTo(selected.date.timestamp, animate);
        detail.replaceChildren(selected.template.content.cloneNode(true));

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
        const firstLabelYear = Math.max(startYear + 1, earliestYear);
        const labels = new Set();
        let lastX = 0;

        for (let year = firstLabelYear; year <= endYear; year += 1) {
            if ((year - firstLabelYear) % step !== 0) continue;
            const x = normalizedPosition(utcTimestamp(year, 0, 1)) * width;
            if (x - lastX < minimumGap || width - x < 48) continue;
            labels.add(year);
            lastX = x;
        }
        return labels;
    };

    const createYearLabel = year => {
        const marker = document.createElement('span');
        marker.className = 'cv-timeline__year';
        marker.dataset.year = String(year);
        marker.style.left = `${normalizedPosition(utcTimestamp(year, 0, 1)) * 100}%`;
        if (year === startYear) marker.dataset.align = 'start';
        marker.textContent = String(year);
        return marker;
    };

    const buildTimeline = () => {
        if (disposed || content.hidden || stage.clientWidth === 0) return;
        const width = stage.clientWidth;
        const day = todayTimestamp();
        const year = new Date().getFullYear();
        const buildKey = `${width}:${day}`;
        if (buildKey === lastBuildKey) return;

        endYear = year;
        endTimestamp = day;
        startLabel.textContent = String(startYear);
        todayLabel.textContent = `Heute · ${endYear}`;
        years.replaceChildren(...[...labelYearsForWidth(width)].map(createYearLabel));

        const fragment = document.createDocumentFragment();
        for (let index = 0; index < strokeCount; index += 1) {
            const bar = document.createElement('i');
            bar.dataset.waveIndex = String(index);
            bar.style.left = `${(index / (strokeCount - 1)) * 100}%`;
            fragment.append(bar);
        }
        bars.replaceChildren(fragment);
        updateSelection(activeIndex, { animate: false, force: true });
        lastBuildKey = buildKey;
    };

    const scheduleBuild = () => {
        if (content.hidden) return;
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(buildTimeline);
    };

    const selectNearest = clientX => {
        const rect = waveform.getBoundingClientRect();
        const progress = Math.max(0, Math.min(1, (clientX - rect.left) / Math.max(1, rect.width)));
        let nearest = 0;
        let distance = Infinity;
        sources.forEach((milestone, index) => {
            const nextDistance = Math.abs(normalizedPosition(milestone.date.timestamp) - progress);
            if (nextDistance < distance) {
                distance = nextDistance;
                nearest = index;
            }
        });
        updateSelection(nearest, { focus: true });
    };

    const handleKey = event => {
        let next = activeIndex;
        if (event.key === 'ArrowLeft') next = Math.max(0, activeIndex - 1);
        else if (event.key === 'ArrowRight') next = Math.min(sources.length - 1, activeIndex + 1);
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = sources.length - 1;
        else return;
        event.preventDefault();
        updateSelection(next);
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
        if (motionReduced() || !matchMedia(desktopPointerQuery).matches || event.ctrlKey) return;
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
        if (wheelDirection !== direction) wheelAccumulator = 0;
        wheelDirection = direction;
        wheelAccumulator += Math.min(Math.abs(delta), wheelThreshold);
        if (wheelAccumulator >= wheelThreshold) {
            wheelAccumulator = 0;
            wheelLatched = true;
            updateSelection(next);
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
            updateSelection(next);
        }
    };

    const handlePointerMove = event => {
        const rect = waveform.getBoundingClientRect();
        const progress = (event.clientX - rect.left) / Math.max(1, rect.width);
        let nearest = 0;
        let distance = Infinity;
        sources.forEach((milestone, index) => {
            const nextDistance = Math.abs(normalizedPosition(milestone.date.timestamp) - progress);
            if (nextDistance < distance) {
                distance = nextDistance;
                nearest = index;
            }
        });
        const nextHover = distance * 100 < 3.2 ? nearest : null;
        if (nextHover === hoveredMilestone) return;
        hoveredMilestone = nextHover;
        drawWaveform();
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
        const expandedHeight = root.getBoundingClientRect().height;
        root.style.height = `${closedHeight}px`;
        root.style.overflow = 'hidden';
        open.setAttribute('aria-expanded', 'true');
        stage.focus({ preventScroll: true });
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
        const closedHeight = teaser.getBoundingClientRect().height + Number.parseFloat(getComputedStyle(root).borderBottomWidth || '0');
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

    buildPreview();
    open.addEventListener('click', openTimeline, { signal: eventsController.signal });
    close.addEventListener('click', closeTimeline, { signal: eventsController.signal });
    stage.addEventListener('keydown', handleKey, { signal: eventsController.signal });
    stage.addEventListener('click', event => {
        if (performance.now() < suppressClickUntil) {
            event.preventDefault();
            return;
        }
        if (event.target.closest('button, a')) return;
        selectNearest(event.clientX);
    }, { signal: eventsController.signal });
    stage.addEventListener('pointermove', handlePointerMove, { signal: eventsController.signal });
    stage.addEventListener('pointerleave', () => {
        if (hoveredMilestone === null) return;
        hoveredMilestone = null;
        drawWaveform();
    }, { signal: eventsController.signal });
    stage.addEventListener('pointerdown', handlePointerDown, { passive: true, signal: eventsController.signal });
    stage.addEventListener('pointerup', handlePointerUp, { passive: true, signal: eventsController.signal });
    stage.addEventListener('pointercancel', () => { touchStart = null; }, { passive: true, signal: eventsController.signal });
    content.addEventListener('wheel', handleWheel, { passive: false, signal: eventsController.signal });
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
