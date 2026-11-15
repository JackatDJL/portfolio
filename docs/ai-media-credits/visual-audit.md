# AI media credits visual audit

The media-credit UI uses the existing Asset fields (alt, caption, rights, and cc) and adds AI involvement, optional disclosure text, and an appearance choice to that blueprint. It does not change asset sidecars.

The SVGs under public/assets/ai-labels/ are copied unchanged from the [European Commission's official icon package](https://digital-strategy.ec.europa.eu/en/policies/eu-icons-labelling-ai-generated-content).

## Route matrix

| Content type | Local page checked | Audit result |
| --- | --- | --- |
| Homepage portrait and project cards | / | Shared overlay renderer is present on image frames. Missing AI metadata does not render an AI icon. Publication cover cards also use the shared renderer. |
| Project listing | /projekte | 20 cover frames render with the shared overlay slot. Existing source credits remain in-frame. |
| Project hero and gallery | /projekte/erstwaehlerforum-stade | Five image frames checked. Credit overlays stay inside each frame; captions remain below the frame. |
| Project hero, responsive fixture | /projekte/ai-ctx | Desktop and 390 px mobile views checked with browser-only test metadata. The original image and sidecar were not changed. |
| Blog featured image | /blog/warum-ich-projekte-zu-codeberg-verschiebe | The current post has no cover. The featured-image template path now calls the shared media renderer. |
| Experience | /cv/exp/volt-stade-kommunikation | This current page has no media image. Existing Asset/Bard media uses the shared image partial. |
| Education fullscreen image | /cv/edu/gymnasium-athenaeum-stade | One full-viewport image checked. Overlay insets follow the content grid; long attribution wraps above the bottom edge and clears the summary. |
| Publication detail | /publikationen/zinsen-und-finanzen | This entry links to an external document and has no image preview. Homepage publication covers use the shared renderer. |

## Responsive and contrast checks

Browser checks used 1280 × 900 desktop and 390 × 844 mobile viewports. Overlay boxes remained inside their media frames, and the mobile project route had no horizontal overflow. A deliberately long test attribution wrapped inside the right overlay without colliding with the image caption or education summary.

The screenshots attached to the pull request show a browser-only fixture labelled TEST ONLY. It exercises the official black-50 and white-50 icon variants and contrast backplates over project artwork and the education image. It is not a claim about the underlying images, and no content asset or metadata sidecar was edited to create it. The backplates provide contrast independently of the image below.

Missing and none metadata, unknown icon values, escaped disclosure text, metadata inheritance, and existing attribution rendering are covered by regression tests.

## Saving and sharing

The overlay is a visible page treatment; it is not baked into the original image and does not permanently label an image saved from the browser. The site currently has no image-download or image-sharing control. AI-involved assets are excluded from image social previews unless a labelled derivative path is added. Originals remain separate and unchanged.
