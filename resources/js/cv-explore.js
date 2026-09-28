import { gsap } from 'gsap';

const reduceMotionQuery = '(prefers-reduced-motion: reduce)';
const desktopPointerQuery = '(min-width: 48rem) and (pointer: fine)';
const tickPattern = [5, 5, 9, 4, 6, 14, 5, 8, 4, 6, 5, 11, 4, 7, 5, 16];

const parseChronology = value => {
    const match = /^(\d{4})(?:-(\d{1,2}))?$/.exec((value || '').trim());
    if (!match) return null;
    const year = Number(match[1]);
    const month = Number(match[2] || 1);
    if (month < 1 || month > 12) return null;
    return { year, month, timestamp: Date.UTC(year, month - 1, 1) };
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
    const detail = root.querySelector('[data-cv-active-detail]');
    const sources = [...root.querySelectorAll('template[data-cv-milestone]')]
        .map((template, order) => ({
            template,
            date: parseChronology(template.dataset.date),
            order,
        }))
        .filter(item => item.date)
        .sort((a, b) => a.date.timestamp - b.date.timestamp || a.order - b.order);
    if (!open || !close || !content || !stage || !track || !sources.length) return;

    const eventMarkers = [];
    const eventsController = new AbortController();
    const resizeObserver = new ResizeObserver(() => scheduleBuild());
    let activeIndex = -1;
    let positions = [];
    let resizeFrame = 0;
    let disposed = false;

    const motionReduced = () => matchMedia(reduceMotionQuery).matches;
    const monthNumber = date => date.year * 12 + date.month;

    const positionForDate = timestamp => {
        if (timestamp <= sources[0].date.timestamp) return positions[0];
        for (let index = 0; index < sources.length - 1; index += 1) {
            const startDate = sources[index].date.timestamp;
            const endDate = sources[index + 1].date.timestamp;
            if (timestamp <= endDate) {
                const span = endDate - startDate;
                const progress = span > 0 ? (timestamp - startDate) / span : 0;
                return positions[index] + (positions[index + 1] - positions[index]) * progress;
            }
        }
        return positions.at(-1);
    };

    const setActive = (index, animate = true) => {
        if (index < 0 || index >= sources.length || index === activeIndex) return;
        activeIndex = index;
        eventMarkers.forEach((marker, markerIndex) => {
            const selected = markerIndex === index;
            marker.toggleAttribute('data-active', selected);
            marker.setAttribute('aria-pressed', String(selected));
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
            duration: .18,
            ease: 'power2.out',
            clearProps: 'opacity,transform,visibility',
        });
    };

    const updateFromScroll = () => {
        const focus = stage.scrollLeft + stage.clientWidth / 2;
        let nearest = 0;
        let distance = Infinity;
        positions.forEach((position, index) => {
            const nextDistance = Math.abs(position - focus);
            if (nextDistance < distance) {
                nearest = index;
                distance = nextDistance;
            }
        });
        setActive(nearest);
    };

    const createYearMarker = (year, position) => {
        const marker = document.createElement('span');
        marker.className = 'cv-timeline__year';
        marker.dataset.year = String(year);
        marker.style.left = position + 'px';
        marker.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.className = 'cv-timeline__year-label';
        label.textContent = String(year);
        marker.append(label);
        return marker;
    };

    const buildTrack = () => {
        if (disposed || content.hidden || stage.clientWidth === 0) return;
        const width = stage.clientWidth;
        const sideInset = width / 2;
        positions = [sideInset];

        for (let index = 1; index < sources.length; index += 1) {
            const monthsApart = Math.max(0, monthNumber(sources[index].date) - monthNumber(sources[index - 1].date));
            const extraSpacing = Math.min(108, monthsApart * 2.5);
            positions.push(positions[index - 1] + 176 + extraSpacing);
        }

        const trackWidth = positions.at(-1) + sideInset;
        track.style.width = trackWidth + 'px';
        ticks.replaceChildren();
        years.replaceChildren();
        events.replaceChildren();
        eventMarkers.length = 0;

        const tickFragment = document.createDocumentFragment();
        let tickIndex = 0;
        for (let x = 0; x <= trackWidth; x += 14) {
            const tick = document.createElement('i');
            tick.className = 'cv-timeline__tick';
            tick.style.left = x + 'px';
            tick.style.setProperty('--tick-height', tickPattern[tickIndex % tickPattern.length] + 'px');
            tickFragment.append(tick);
            tickIndex += 1;
        }
        ticks.append(tickFragment);

        const firstYear = sources[0].date.year;
        const lastYear = sources.at(-1).date.year;
        const yearFragment = document.createDocumentFragment();
        for (let year = firstYear; year <= lastYear; year += 1) {
            const date = year === firstYear ? sources[0].date.timestamp : Date.UTC(year, 0, 1);
            yearFragment.append(createYearMarker(year, positionForDate(date)));
        }
        years.append(yearFragment);

        const eventFragment = document.createDocumentFragment();
        sources.forEach((source, index) => {
            const article = source.template.content.querySelector('.cv-milestone');
            const dateLabel = article.querySelector('.cv-milestone__date')?.textContent.trim();
            const title = article.querySelector('h3')?.textContent.trim();
            const marker = document.createElement('button');
            marker.type = 'button';
            marker.className = 'cv-timeline__event';
            marker.dataset.cvTimelineEvent = String(index);
            marker.style.left = positions[index] + 'px';
            marker.setAttribute('aria-label', [dateLabel, title].filter(Boolean).join(': '));
            marker.setAttribute('aria-pressed', String(index === activeIndex));
            if (index === activeIndex) marker.setAttribute('data-active', '');
            const visibleMark = document.createElement('span');
            visibleMark.setAttribute('aria-hidden', 'true');
            marker.append(visibleMark);
            eventFragment.append(marker);
            eventMarkers.push(marker);
        });
        events.append(eventFragment);

        if (activeIndex < 0) setActive(0, false);
        const targetPosition = positions[Math.max(0, activeIndex)];
        stage.scrollLeft = Math.max(0, targetPosition - width / 2);
        updateFromScroll();
    };

    const scheduleBuild = () => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(buildTrack);
    };

    const centerOn = (index, focus = false) => {
        const marker = eventMarkers[index];
        if (!marker) return;
        setActive(index);
        stage.scrollTo({
            left: Math.max(0, positions[index] - stage.clientWidth / 2),
            behavior: motionReduced() ? 'auto' : 'smooth',
        });
        if (focus) marker.focus({ preventScroll: true });
    };

    const handleTimelineKey = event => {
        let next = activeIndex;
        if (event.key === 'ArrowLeft') next = Math.max(0, activeIndex - 1);
        else if (event.key === 'ArrowRight') next = Math.min(sources.length - 1, activeIndex + 1);
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = sources.length - 1;
        else return;
        event.preventDefault();
        centerOn(next, true);
    };

    const handleWheel = event => {
        if (motionReduced() || !matchMedia(desktopPointerQuery).matches || event.ctrlKey || event.deltaY === 0) return;
        const multiplier = event.deltaMode === WheelEvent.DOM_DELTA_LINE
            ? 16
            : event.deltaMode === WheelEvent.DOM_DELTA_PAGE ? stage.clientWidth : 1;
        const movement = event.deltaY * multiplier;
        const maximum = stage.scrollWidth - stage.clientWidth;
        const canMove = movement > 0 ? stage.scrollLeft < maximum - 1 : stage.scrollLeft > 1;
        if (!canMove) return;
        event.preventDefault();
        stage.scrollLeft = Math.max(0, Math.min(maximum, stage.scrollLeft + movement));
    };

    const clearRootSize = () => {
        gsap.set(root, { clearProps: 'height,overflow' });
    };

    const openTimeline = () => {
        if (disposed || !content.hidden) return;
        gsap.killTweensOf(root);
        const closedHeight = root.getBoundingClientRect().height;
        teaser.hidden = true;
        content.hidden = false;
        buildTrack();
        const expandedHeight = root.scrollHeight;
        root.style.height = closedHeight + 'px';
        root.style.overflow = 'hidden';
        open.setAttribute('aria-expanded', 'true');
        stage.focus({ preventScroll: true });
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
        root.style.height = expandedHeight + 'px';
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
    stage.addEventListener('keydown', handleTimelineKey, { signal: eventsController.signal });
    stage.addEventListener('scroll', updateFromScroll, { passive: true, signal: eventsController.signal });
    stage.addEventListener('wheel', handleWheel, { passive: false, signal: eventsController.signal });
    events.addEventListener('click', event => {
        const marker = event.target.closest('[data-cv-timeline-event]');
        if (marker) centerOn(Number(marker.dataset.cvTimelineEvent));
    }, { signal: eventsController.signal });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !content.hidden && root.contains(document.activeElement)) closeTimeline();
    }, { signal: eventsController.signal });

    resizeObserver.observe(stage);
    window.addEventListener('pagehide', () => {
        disposed = true;
        resizeObserver.disconnect();
        cancelAnimationFrame(resizeFrame);
        eventsController.abort();
        gsap.killTweensOf(root);
        gsap.killTweensOf(detail);
    }, { once: true });
};
