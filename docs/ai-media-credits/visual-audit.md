# AI media credits visual audit

The media-credit UI extends the existing Asset blueprint (alt, caption, rights, and cc). `eu_ai_disclosure` is the single editable disclosure field and supplies an accessible name; the visible AI disclosure is only the official SVG. Older disclosure values remain readable as a fallback. An empty AI involvement field preserves legacy disclosure behavior; explicitly saved `none` suppresses the icon. AI icon appearance and attribution contrast are configurable per asset. The preview example changes only `credit_appearance` on the AI-CTX image; its source and licence fields remain intact.

The SVGs under public/assets/ai-labels/ are copied unchanged from the [European Commission's official icon package](https://digital-strategy.ec.europa.eu/en/policies/eu-icons-labelling-ai-generated-content).

## Route matrix

| Content type | Local page checked | Audit result |
| --- | --- | --- |
| Homepage portrait and project cards | / | Shared overlay renderer is present on image frames. Missing AI metadata does not render an AI icon. Publication cover cards also use the shared renderer. |
| Project listing | /projekte | 20 cover frames render with the shared overlay slot. Existing source credits remain in-frame. |
| Project hero and gallery | /projekte/erstwaehlerforum-stade | Five image frames checked at desktop and mobile widths. Test-only icon and credit stay in the hero frame; the cover caption stays below the clipped frame. Gallery captions remain below their images. |
| CV portrait | /cv | 390 px and 320 px mobile views checked with browser-only test badges. When labels are present, the rounded frame reserves a footer for them so the portrait stays unobstructed; credit-only mode uses the full footer width. |
| Blog feature and cards | /blog and /blog/warum-ich-projekte-zu-codeberg-verschiebe | Featured images and cover cards use the shared renderer. Card image links and linked source credits have separate hit targets. A temporary test entry exercises the cover card. |
| Experience | /cv/exp/volt-stade-kommunikation | The shared image partial handles optional experience covers and their in-frame labels. The current published entry has no cover. |
| Education fullscreen image | /cv/edu/gymnasium-athenaeum-stade | One full-viewport image checked. Overlay insets follow the content grid; long attribution wraps above the bottom edge and clears the summary. |
| Publication detail and preview | /publikationen/zinsen-und-finanzen | Image previews and homepage publication covers use the shared renderer. The selected entry links to an external document and has no image preview. |

## Responsive and contrast checks

Browser checks used 1280 × 900 desktop, 390 × 844 mobile, and 320 × 844 narrow mobile viewports. Overlay boxes remained inside their media frames, and the mobile project and CV routes had no horizontal overflow. A deliberately long test attribution wrapped inside the education overlay without colliding with its summary; narrow CV portrait labels use the frame footer to avoid covering the photo. Publication and blog card image links remain separate from linked source credits, with no nested anchors. AI SVG files are shown without a surrounding disclosure label or added graphic treatment.

Temporary preview fixtures are explicitly labelled as test data and make no claims about the displayed photos. They exercise official black, white, black-50, and white-50 SVG variants over project, education, portrait, and code-image frames, and show dark and light attribution treatments. The education image's saved attribution remains unchanged. Screenshots are supplied in the linked task conversation and are not committed; this route matrix and the checked-in tests are the durable audit record.

Missing and none metadata, unknown icon values, escaped disclosure text, metadata inheritance, and existing attribution rendering are covered by regression tests.

## Saving and sharing

The overlay is a visible page treatment; it is not baked into the original image and does not permanently label an image saved from the browser. The site currently has no image-download or image-sharing control. Assets with non-none AI involvement, or a legacy disclosure without an explicit `none`, are excluded from image social previews unless a labelled derivative path is added. Originals remain separate and unchanged.
