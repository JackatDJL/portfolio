# CV export and print

The web CV and the LuaLaTeX export read the same Statamic globals, Experience, Education, Project, Publication, profile, and milestone entries. `App\Support\CvViewModel` normalizes those fields for the LaTeX template.

## Browser print

`Ctrl+P` and `Cmd+P` on a CV page open that profile's canonical PDF. The browser's PDF viewer handles printing. The HTML print stylesheet remains a fallback for browser print commands that bypass the shortcut.

## Canonical PDF

- Public: `GET /cv/pdf`
- Profile: `GET /cv/{profile}/pdf`
- Authorized private data: open `/cv/{profile}#cv=TOKEN` first. The browser posts the fragment token to `/cv/token-exchange`, removes it from the address bar, and receives a scoped session. The PDF route uses that session without another token in its URL.

The route runs LuaLaTeX with `resources/views/latex/cv.blade.php`. It writes `.tex` and auxiliary files only to a permission-restricted temporary directory, moves out the finished PDF, then deletes the temporary source and auxiliary files. The template receives resolved values, never ciphertext.

Deployment needs LuaLaTeX plus the LaTeX packages `fontspec`, `xcolor`, `graphicx`, `hyperref`, `tikz`, `array`, and `paracol`. The repository includes the Fira Sans Regular and Bold font files used by the website and PDF, so the build does not fall back to Computer Modern. Set `CV_LUALATEX_BINARY` when `lualatex` is outside the service PATH. Set `CV_CANONICAL_BASE_URL` only when the public site moves away from `https://jack.djl.foundation`.

The canonical PDF is separate from browser HTML print. Pressing `Ctrl+P` opens the generated LuaLaTeX PDF.
