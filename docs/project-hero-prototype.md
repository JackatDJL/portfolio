# Project hero experiment

Question: can a photographic opening become a compact editorial header during ordinary scrolling, while retaining the existing project document?

Start with `bun run preview -- up hero-prototype`. On the returned app URL, compare:

- `/projekte/erstwaehlerforum-stade?hero=transition`
- `/projekte/erstwaehlerforum-stade?hero=original`

The original remains the default. The query experiment is limited to Erstwählerforum Stade. The project blueprint also offers an off-by-default `hero_transition` toggle. `?hero=original` overrides that toggle. Missing covers always use the original. No entry content, cover selection, relationships or canonical URLs change.

The existing Fira Sans display type, theme background/text/accent and shared `buildThreadPath` curve connect the photograph to the reading column. The frame narrows and shortens as the heading scales toward its reading size. There is no pin, scroll interception, hidden content or image filter. Mobile uses smaller insets, less title scaling and a shorter scroll interval. The gallery uses a consistent image stage with contained photographs, a visible next-image edge, existing keyboard/button navigation, captions and credits below each image. Asset metadata and focus remain authoritative.

Reduced motion has a static header and no hero ScrollTrigger. Without JavaScript, the image, heading, summary, credit and article render normally; gallery images remain horizontally scrollable. Font readiness and image load refresh geometry. GSAP matchMedia reverts and rebuilds on breakpoint/motion changes; ScrollTrigger handles viewport resizing. Non-cached page exit removes the experiment's listeners and animation context, while browser back/forward cache restoration refreshes it.

## Local proof

At 1440 × 900 the sampled frame shrank from 820 to 647 pixels after 400 pixels of scroll. At 390 × 844 it shrank from 696 to 523 pixels. Both reached gallery image 4/4 with the End key, preserved all four captions/credits, and had no page errors or horizontal document overflow. Resizing across the breakpoint also produced no overflow. Original and experimental article text matched exactly.

Reduced-motion and JavaScript-disabled mobile contexts retained identical frame height before/after scrolling, one heading, one hero credit and four gallery credits. A temporary credit-link fixture verified mouse, touch and keyboard activation, because this project's actual credit is plain text.

`bun run build` and the AssetAttributionTest, DynamicStatamicPagesTest DesignSystemTest and CvNavigationTest subset passed after rebasing, 24 tests / 178 assertions with STATAMIC_PRO_ENABLED=true. Sol review approved the pointer fix and confirmed the template conflict resolution; its stale navigation-test assertion finding was fixed and the full subset rerun. Screenshots are temporary review evidence outside Git.

Performance observations: the experiment is dynamically imported only when its hero exists. Its production chunk is about 1.3 kB before gzip and shares the existing GSAP/ScrollTrigger chunks. Responsive image candidates range from 480 to 1920 pixels; gallery images remain lazy-loaded. Frame dimensions deliberately animate layout during this short interval, so this is not a compositor-only animation. No frame-rate or physical-device performance claim is made. Evaluate on a lower-end phone before broad CMS rollout.

This is an opt-in prototype for review, not a decision to replace every project hero.
