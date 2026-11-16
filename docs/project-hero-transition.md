# Project hero transition

The fullscreen-to-compact hero and masonry gallery are enabled on Erstwählerforum Stade. The CMS `Hero-Übergang` toggle makes the same treatment available to another project with a cover; other projects retain their current layout. `?hero=original` forces the former hero for comparison or rollback. Pages without a cover always retain the compact original header. Entry content, cover selections, relations and canonical URLs are unchanged.

The frame narrows and shortens during ordinary scrolling with a 0.65-second scrub catch-up and sine easing. Title and summary scale together from the left bottom while keeping their text width stable. Only the bottom corners round. There is no pinning or scroll interception. The project status appears in the hero; the old project eyebrow and divider are omitted.

The masonry gallery uses natural image ratios, two columns on desktop and one on mobile. Captions, external source credits, focal metadata and lazy loading remain intact. Column reading order follows the DOM order down each column.

The fixed wordmark gains its existing green marker when sampled background contrast is low while the site header is visible. It samples three points behind the name, treats images and gradients conservatively, waits 40 ms before activation and 160 ms before release, and uses 4.5:1 / 5.5:1 thresholds for hysteresis. The marker remains the no-JavaScript fallback.

After the whole header leaves the viewport, the wordmark becomes a decorative liquid-glass capsule. React, ReactDOM and `liquid-glass-react` load only on the first header exit. The original home link keeps its accessible name and remains the only interactive element. The capsule fades in and out over 420 ms; reversing direction cancels pending unmount work. Reduced motion skips the fade and disables displacement. The light/dark capsule has no dot, extra text, expansion or elastic mouse movement.

Without JavaScript, the hero content and image remain readable in the page and the name remains a normal link with its contrast marker. Reduced motion renders the hero without ScrollTrigger. Font readiness, image loads, resize, breakpoint changes and browser back/forward restoration refresh or rebuild animation geometry; non-cached page exit cleans up listeners and animation context.

The image candidates range from 480 to 1920 pixels; gallery images stay lazy-loaded. Hero dimensions animate during the transition, so this is layout work rather than a compositor-only effect. The liquid-glass chunk is lazy-loaded and was measured at approximately 272 kB raw / 101 kB gzip. Browser proof does not establish performance on lower-end physical phones. Safari and Firefox have partial displacement support; the readable CSS capsule remains as a fallback.

The `?hero=original` comparison bypasses static-cache reads and writes so it cannot share a cached response with the enabled transition. The wordmark glass capsule is on by default and does not require a URL flag.
