import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const wideMotion = window.matchMedia('(min-width: 64rem) and (hover: hover) and (pointer: fine)');

const threadShapes = {
    person: [48, 31, 72, 37, 52],
    current: [50, 61, 40, 63, 50],
    projects: [50, 23, 77, 23, 50],
    writing: [50, 50, 50, 50, 50],
    publications: [50, 76, 27, 76, 50],
    ending: [52, 38, 64, 34, 48],
};

const makeThreadPath = (shape) => {
    const y = [0, 25, 50, 75, 100];
    const commands = [`M ${shape[0]} ${y[0]}`];
    for (let index = 0; index < y.length - 1; index += 1) {
        const fromY = y[index];
        const toY = y[index + 1];
        commands.push(`C ${shape[index]} ${fromY + 8}, ${shape[index + 1]} ${toY - 8}, ${shape[index + 1]} ${toY}`);
    }
    return commands.join(' ');
};

for (const root of document.querySelectorAll('[data-homepage]')) {
    const opening = root.querySelector('[data-home-opening]');
    const name = root.querySelector('[data-home-name]');
    const personalMedia = root.querySelector('[data-home-person-media]');
    const projects = [...root.querySelectorAll('[data-home-project]')];
    const projectRoot = root.querySelector('[data-home-projects]');
    const writing = root.querySelector('.home-writing');
    const publications = root.querySelector('.home-publications');
    const ending = root.querySelector('.home-now');
    const threadPath = root.querySelector('[data-home-thread-path]');
    let context;

    const drawThread = (shape) => threadPath?.setAttribute('d', makeThreadPath(shape));
    drawThread(threadShapes.person);

    const clear = () => {
        context?.revert();
        context = undefined;
        root.classList.remove('is-home-enhanced');
        drawThread(threadShapes.person);
    };

    const enhance = () => {
        clear();
        if (reduceMotion.matches || !wideMotion.matches || !opening || !name) return;

        root.classList.add('is-home-enhanced');
        context = gsap.context(() => {
            const threadState = { values: [...threadShapes.person], origin: [...threadShapes.person], target: [...threadShapes.person], progress: 1 };
            const moveThread = (target) => {
                gsap.killTweensOf(threadState);
                threadState.origin = [...threadState.values];
                threadState.target = [...threadShapes[target]];
                threadState.progress = 0;
                gsap.to(threadState, {
                    progress: 1,
                    duration: 0.5,
                    ease: 'power2.inOut',
                    onUpdate: () => {
                        threadState.values = threadState.origin.map((value, index) => value + ((threadState.target[index] - value) * threadState.progress));
                        drawThread(threadState.values);
                    },
                });
            };

            gsap.timeline({ defaults: { ease: 'power3.out' } })
                .from(name.children, { yPercent: 24, autoAlpha: 0, stagger: 0.08, duration: 0.8 })
                .from(personalMedia, { y: 36, autoAlpha: 0, duration: 0.72 }, 0.12)
                .from('.home-person__intro, .home-person__index-link', { y: 18, autoAlpha: 0, stagger: 0.06, duration: 0.45 }, 0.38);

            gsap.to(name, {
                xPercent: 7,
                yPercent: -15,
                scale: 0.82,
                ease: 'none',
                scrollTrigger: { trigger: opening, start: 'top top', end: 'bottom top', scrub: true },
            });
            gsap.to(personalMedia, {
                yPercent: 15,
                ease: 'none',
                scrollTrigger: { trigger: opening, start: 'top top', end: 'bottom top', scrub: true },
            });

            const setActiveProject = (activeProject) => projects.forEach((project) => project.classList.toggle('is-active', project === activeProject));
            projects.forEach((project, index) => {
                const artifact = project.querySelector('.home-project__artifact');
                const copy = project.querySelector('.home-project__copy');
                ScrollTrigger.create({
                    trigger: project,
                    start: 'top 60%',
                    end: 'bottom 40%',
                    onEnter: () => setActiveProject(project),
                    onEnterBack: () => setActiveProject(project),
                    onLeaveBack: () => setActiveProject(null),
                });
                if (artifact) gsap.fromTo(artifact, { clipPath: 'inset(7% 5% 9% 5%)', scale: 0.95 }, { clipPath: 'inset(0% 0% 0% 0%)', scale: 1, ease: 'none', scrollTrigger: { trigger: project, start: 'top 78%', end: 'top 34%', scrub: true } });
                if (copy) gsap.from(copy, { x: index % 2 ? 32 : -32, autoAlpha: 0, duration: 0.5, scrollTrigger: { trigger: project, start: 'top 72%', toggleActions: 'play none none reverse' } });
            });

            if (projectRoot && projects.length > 1) ScrollTrigger.create({ trigger: projectRoot, start: 'top top+=92', end: 'bottom bottom-=80', pin: projectRoot.querySelector('.home-section-heading'), pinSpacing: false });

            const threadSections = [
                [opening, 'person'],
                [projectRoot, 'projects'],
                [writing, 'writing'],
                [publications, 'publications'],
                [ending, 'ending'],
            ];
            for (const [section, target] of threadSections) {
                if (!section) continue;
                ScrollTrigger.create({ trigger: section, start: 'top center', end: 'bottom center', onEnter: () => moveThread(target), onEnterBack: () => moveThread(target) });
            }
        }, root);
    };

    enhance();
    wideMotion.addEventListener('change', enhance);
    reduceMotion.addEventListener('change', enhance);
}
