# CV export and print

The web CV and the LuaLaTeX export read the same Statamic globals, Experience, Education, Project, Publication, profile, and milestone entries. `App\Support\CvViewModel` normalizes those fields for the LaTeX template.

## Browser print

`Ctrl+P` and `Cmd+P` use `resources/css/cv.css`. The print stylesheet fixes the page to A4, switches the document to a white print palette, removes site chrome and the interactive timeline, and keeps records together where practical. Browser-added date, URL, title, and page-number headers belong to the print dialog and cannot be removed reliably with page CSS. Disable them in the browser's print settings when needed.

## Canonical PDF

- Public: `GET /cv/pdf`
- Profile: `GET /cv/{profile}/pdf`
- Authorized private data: pass an existing profile-scoped capability as `?token=...`

The route runs LuaLaTeX with `resources/views/latex/cv.blade.php`. It writes `.tex` and auxiliary files only to a permission-restricted temporary directory, moves out the finished PDF, then deletes the temporary source and auxiliary files. The template receives resolved values, never ciphertext.

Deployment needs LuaLaTeX plus the LaTeX packages `fontspec`, `xcolor`, `graphicx`, `hyperref`, `tikz`, `array`, and `paracol`. The repository includes the Fira Sans Regular and Bold font files used by the website and PDF, so the build does not fall back to Computer Modern. Set `CV_LUALATEX_BINARY` when `lualatex` is outside the service PATH. Set `CV_CANONICAL_BASE_URL` only when the public site moves away from `https://jack.djl.foundation`.

The canonical PDF is separate from browser print. Pressing `Ctrl+P` never invokes LuaLaTeX.
