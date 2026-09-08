import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const wideMotion = window.matchMedia('(min-width: 64rem) and (hover: hover) and (pointer: fine)');

for (const root of document.querySelectorAll('[data-homepage]')) {
    const opening = root.querySelector('[data-home-opening]');
    const name = root.querySelector('[data-home-name]');
    const openingArtifact = root.querySelector('[data-opening-artifact]');
    const projects = [...root.querySelectorAll('[data-home-project]')];
    const projectRoot = root.querySelector('[data-home-projects]');
    let context;

    const clear = () => {
        context?.revert();
        context = undefined;
        root.classList.remove('is-home-enhanced');
    };

    const enhance = () => {
        clear();
        if (reduceMotion.matches || !wideMotion.matches) return;
        root.classList.add('is-home-enhanced');
        context = gsap.context(() => {
            gsap.timeline({ defaults: { ease: 'power3.out' } })
                .from(name.children, { yPercent: 22, autoAlpha: 0, stagger: 0.08, duration: 0.8 })
                .from(openingArtifact, { y: 44, rotation: -3, autoAlpha: 0, duration: 0.8 }, 0.1);
            gsap.to(name, { xPercent: 8, yPercent: -20, scale: 0.78, ease: 'none', scrollTrigger: { trigger: opening, start: 'top top', end: 'bottom top', scrub: true } });
            gsap.to(openingArtifact, { yPercent: 24, rotation: 3, ease: 'none', scrollTrigger: { trigger: opening, start: 'top top', end: 'bottom top', scrub: true } });

            const setActiveProject = (activeProject) => projects.forEach((project) => project.classList.toggle('is-active', project === activeProject));
            projects.forEach((project, index) => {
                const artifact = project.querySelector('.home-project__artifact');
                const copy = project.querySelector('.home-project__copy');
                ScrollTrigger.create({ trigger: project, start: 'top 60%', end: 'bottom 40%', onEnter: () => setActiveProject(project), onEnterBack: () => setActiveProject(project), onLeaveBack: () => setActiveProject(null) });
                gsap.fromTo(artifact, { clipPath: 'inset(8% 6% 10% 6%)', scale: 0.94 }, { clipPath: 'inset(0% 0% 0% 0%)', scale: 1, ease: 'none', scrollTrigger: { trigger: project, start: 'top 78%', end: 'top 34%', scrub: true } });
                gsap.from(copy, { x: index % 2 ? 36 : -36, autoAlpha: 0, duration: 0.55, scrollTrigger: { trigger: project, start: 'top 72%', toggleActions: 'play none none reverse' } });
            });
            if (projectRoot && projects.length > 1) ScrollTrigger.create({ trigger: projectRoot, start: 'top top+=92', end: 'bottom bottom-=80', pin: projectRoot.querySelector('.home-section-heading'), pinSpacing: false });
        }, root);
    };

    enhance();
    wideMotion.addEventListener('change', enhance);
    reduceMotion.addEventListener('change', enhance);
}
