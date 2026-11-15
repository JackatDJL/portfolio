# Project hero experiment

Question: can a photographic opening become a compact editorial header during ordinary scrolling, while retaining the existing project document?

Start with `bun run preview -- up hero-prototype`. On the returned app URL, compare:

- `/projekte/erstwaehlerforum-stade?hero=transition`
- `/projekte/erstwaehlerforum-stade?hero=original`

The original remains the default. Requests with a `hero` query parameter bypass static caching so comparison variants cannot populate or read the default response cache. The query experiment is limited to Erstwählerforum Stade. The project blueprint also offers an off-by-default `hero_transition` toggle. `?hero=original` overrides that toggle. Missing covers always use the original. No entry content, cover selection, relationships or canonical URLs change.

The existing Fira Sans display type and theme colors connect the photograph to the reading column. The frame narrows and shortens with a 0.65-second scrub catch-up and sine easing. Title and summary scale together from the left bottom; their unscaled text width stays stable to prevent reflow during the transition. Only the bottom corners round. The project status replaces the redundant project eyebrow; the standalone divider and status row are removed. There is no pin or scroll interception.

The experimental gallery now uses CSS columns with natural image ratios: two columns on desktop and one on mobile. It has no horizontal carousel or hidden slides. Captions, source credits, focal metadata and lazy loading remain intact. Column reading order follows DOM order down each column.

The fixed header wordmark uses the existing hover marker when underlying solid-surface contrast falls below 4.5:1. It samples three points behind the name. Images and gradients, including decorative media excluded from pointer hit testing, receive conservative protection rather than claiming a pixel-level contrast measurement. Activation waits 40 ms, removal 160 ms; a 5.5:1 exit threshold provides hysteresis. Hover and keyboard focus retain the same light green marker and dark text in both themes, without a second animation or size change. Without JavaScript the marker stays visible as a readable fallback.

Reduced motion has a static header and no hero ScrollTrigger. Without JavaScript, the image, heading, summary, credit and article render normally; gallery images remain horizontally scrollable. Font readiness and image load refresh geometry. GSAP matchMedia reverts and rebuilds on breakpoint/motion changes; ScrollTrigger handles viewport resizing. Non-cached page exit removes the experiment's listeners and animation context, while browser back/forward cache restoration refreshes it.

## Local proof

The first iteration's desktop/mobile scroll, resize, reduced-motion and no-JavaScript checks passed. The revision adds focused browser regression tests for marker activation, short crossings, stable release and theme changes. The original dark-hero repro failed before the change and passed afterwards. Build and the 25-test PHP subset / 181 assertions pass. Revised visual checks cover desktop, mobile, dark theme, reduced motion and all four masonry captions/credits. Temporary screenshots remain outside Git.


Performance observations: the experiment is dynamically imported only when its hero exists. Its production chunk is about 1.3 kB before gzip and shares the existing GSAP/ScrollTrigger chunks. Responsive image candidates range from 480 to 1920 pixels; gallery images remain lazy-loaded. Frame dimensions deliberately animate layout during this short interval, so this is not a compositor-only animation. No frame-rate or physical-device performance claim is made. Evaluate on a lower-end phone before broad CMS rollout.

This is an opt-in prototype for review, not a decision to replace every project hero.

## Name island experiment

Append `&name=island` to the hero comparison URL to try a compact capsule around the fixed header wordmark. `&name=marker` or omitting the parameter restores the established marker. The flag is URL-only and never stored. The homepage's separate large-name animation is outside this experiment.

The capsule keeps Fira Sans and the site green, adds a small hard shadow, and expands a short home-link hint on desktop hover or keyboard focus. It remains one home link with its original accessible name. On mobile the hint stays hidden so the capsule cannot collide with the menu. Over difficult media the capsule changes to light green with dark text using the same contrast buffer; sampling uses the stable name label, not the expanding capsule. Reduced motion disables the shape transition. Without JavaScript the existing wordmark and readable marker fallback remain.
