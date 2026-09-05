# Internal design-system lab

Open `/design-system` with `APP_ENV=local`. Run `bun install --frozen-lockfile`, then `bun run dev` or `bun run build` alongside the Laravel server.

The route returns 404 outside `local` and `testing`, even if enabled. Set `DESIGN_SYSTEM_ENABLED=false` to disable it locally too. Refresh Laravel's cached configuration after changing environment settings. Responses include `X-Robots-Tag: noindex, nofollow, noarchive` and `Cache-Control: private, no-store`; the document also has a robots meta tag. Do not expose a development server publicly.

## Isolation and source of truth

`resources/css/jack-site-theme.css` is the supplied theme, with small accessibility corrections. It is also the shared home for the approved visual primitives: controls, surfaces, chips, semantic links, layout widths, project metadata, focus treatment and Bard/prose output. Small semantic markup primitives live in `resources/views/partials/components/`; the lab and public shell consume those same partials. `resources/css/lab.css` imports the theme and the locally bundled Fontsource fonts. The lab has a separate Antlers layout/template and Vite entry. It renders static HTML representative of the existing Bard formats, including tables; it does not render or mutate a real Bard field.

Fira Sans bundles normal weights 400, 500, 700 and 800. Geist bundles variable normal and italic; Geist Mono bundles variable normal. Vite emits the fonts under `public/build/assets`; no third-party font requests are needed. Font licenses are included in the installed Fontsource packages.

## Theme behavior

Native radio inputs provide System / Hell / Dunkel. Explicit choices set `data-theme` on the root and persist under `jack-theme`. System removes both the attribute and storage key, letting the theme's media query follow OS changes live. A small head script restores the preference before styles paint. Invalid values are ignored; blocked storage does not prevent switching. The preference is shared by the lab and public shell.

## Theme corrections and remaining observations

- Light focus changed from `#21a88b` to `#16836c`: the original was only 2.94:1 against background and 2.57:1 against surface-strong. The replacement exceeds 3:1 against all three light surfaces.
- Disabled buttons no longer move or acquire hover colors. They retain readable muted text without opacity reduction.
- Prose links explicitly underline, rather than relying on a browser default removed by Tailwind's reset.
- Reduced motion also suppresses button/card transforms.
- The first element in a prose block no longer carries a section gap (`margin-top: 0` via `:first-child`). Bard content that opens with a heading used to push the whole prose block down through margin collapse.
- Font-family tokens match Fontsource's registered `Geist Variable` and `Geist Mono Variable` family names.
- The supplied `dark:` variant only matches explicit dark mode, while system mode changes semantic variables through a media query. The lab therefore uses semantic tokens throughout and needs no `dark:` utilities.
- Existing primitives contain some literal sizes alongside theme tokens, and `shadow-soft` has a fixed light-oriented color. These have been preserved; this lab adds no new spacing or radius scale.

## Reference checks

Run `bun run build`, `php artisan test`, `vendor/bin/pint --dirty --test`, and `git diff --check`.

For visual review, check 320px, 390px, 768px and 1440px widths in both themes. Verify the grid has one/two/three columns, long German text wraps, and code/table overflow stays local. Tab through the skip link, radio group, links and buttons; use arrow keys in the radio group. Reload explicit themes, return to System, change the OS preference, and replay the animation with reduced motion enabled. The permanent focus specimen is labeled; the other controls demonstrate real keyboard focus.
