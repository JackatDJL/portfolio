# Project hero experiment

Question: can a photographic opening become a compact editorial header during ordinary scrolling, while retaining the existing project document?

Start with `bun run preview -- up hero-prototype`. On the returned app URL, compare:

- `/projekte/erstwaehlerforum-stade?hero=transition`
- `/projekte/erstwaehlerforum-stade?hero=original`

The original remains the default. Requests with a `hero` query parameter bypass static cache writes and file-cache reads so comparison variants cannot populate or read the default response cache. The query experiment is limited to Erstwählerforum Stade. The project blueprint also offers an off-by-default `hero_transition` toggle. `?hero=original` overrides that toggle. Missing covers always use the original. No entry content, cover selection, relationships or canonical URLs change.

The existing Fira Sans display type and theme colors connect the photograph to the reading column. The frame narrows and shortens with a 0.65-second scrub catch-up and sine easing. Title and summary scale together from the left bottom; their unscaled text width stays stable to prevent reflow during the transition. Only the bottom corners round. The project status replaces the redundant project eyebrow; the standalone divider and status row are removed. There is no pin or scroll interception.

The experimental gallery now uses CSS columns with natural image ratios: two columns on desktop and one on mobile. It has no horizontal carousel or hidden slides. Captions, source credits, focal metadata and lazy loading remain intact. Column reading order follows DOM order down each column.

The fixed header wordmark uses the existing hover marker when underlying solid-surface contrast falls below 4.5:1. It samples three points behind the name. Images and gradients, including decorative media excluded from pointer hit testing, receive conservative protection rather than claiming a pixel-level contrast measurement. Activation waits 40 ms, removal 160 ms; a 5.5:1 exit threshold provides hysteresis. Hover and keyboard focus retain the same light green marker and dark text in both themes, without a second animation or size change. Without JavaScript the marker stays visible as a readable fallback.

Reduced motion has a static header and no hero ScrollTrigger. Without JavaScript, the image, heading, summary, credit and article render normally; gallery images remain horizontally scrollable. Font readiness and image load refresh geometry. GSAP matchMedia reverts and rebuilds on breakpoint/motion changes; ScrollTrigger handles viewport resizing. Non-cached page exit removes the experiment's listeners and animation context, while browser back/forward cache restoration refreshes it.

## Local proof

The first iteration's desktop/mobile scroll, resize, reduced-motion and no-JavaScript checks passed. The revision adds focused browser regression tests for marker activation, short crossings, stable release and theme changes. The original dark-hero repro failed before the change and passed afterwards. Build and the 52-test PHP subset / 302 assertions pass. Revised visual checks cover desktop, mobile, dark theme, reduced motion and all four masonry captions/credits. Temporary screenshots remain outside Git.


Performance observations: the experiment is dynamically imported only when its hero exists. Its production chunk is about 1.3 kB before gzip and shares the existing GSAP/ScrollTrigger chunks. Responsive image candidates range from 480 to 1920 pixels; gallery images remain lazy-loaded. Frame dimensions deliberately animate layout during this short interval, so this is not a compositor-only animation. No frame-rate or physical-device performance claim is made. Evaluate on a lower-end phone before broad CMS rollout.

This is an opt-in prototype for review, not a decision to replace every project hero.

## Name glass experiment

Append `&name=island` to try the fixed header name with `liquid-glass-react`. The original name stays plain while any part of the header is visible. Only when the header has left above the viewport does its decorative glass surface appear. The surface fades over 420 ms with easing in both directions. Scrolling back restores the normal name; the React glass remains mounted until the fade-out finishes. Reversing direction cancels pending unmount work. Reduced motion skips the fade. `&name=marker` or omitting the parameter restores the buffered marker experiment. The URL flag is not stored; the homepage's separate large-name animation is outside this experiment.

React, ReactDOM and liquid-glass-react are dynamically imported on the first header exit, only for this flag. The original home link remains the only interactive element and keeps its accessible name. The React root is an aria-hidden, pointer-transparent decorative layer. It unmounts the glass while the header is visible and cleans up on non-cached page exit. Font/label resize redraws its dimensions.

The capsule is whitish in light mode and dark translucent in dark mode, with modest refraction and a soft border. No dot, extra text, expansion or elastic mouse movement is enabled. Reduced motion sets displacement to zero; CSS supplies a readable fallback if the import fails. The library documents only partial displacement support in Safari and Firefox. Tests cover header visibility, lazy mounting, hover stability and returning to the plain name, alongside the default marker tests.

Cover captions render in normal document flow below the hero image, right-aligned. Source credits remain overlaid inside the image at bottom right, so multiline captions and multiline credits cannot overlap. A warm-default-file middleware regression verifies that hero variants bypass cached reads and do not overwrite the ordinary file.
