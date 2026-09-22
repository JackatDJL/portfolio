# Media rendering audit

The audit covered homepage photos and project artwork, project covers and galleries, Bard figures and galleries, publication previews, the CV portrait, Experience relations, Education hero media, and relation components.

The canonical legal metadata renderer remains `partials/media/attribution`. `partials/media/image` now provides the shared figure/image path for CV, Education, Project, and Bard hero/figure use. Galleries remain on the established horizontal renderer and now provide responsive image sources.

Fixed paths:

- CV portrait now uses the Asset alt text, intrinsic dimensions, responsive sources, eager loading, and the shared renderer.
- Education and Project heroes now use the shared renderer with attribution.
- Publication previews no longer invent the title as alt text and now expose attribution.
- Project index captions use `caption`, not `alt`, and expose attribution when present.
- Bard's native image button was removed from the two shared fieldsets. Authors use the Figure or Gallery sets, which preserve alt, caption, rights, and CC fields.
- Gallery images now include responsive `srcset` and `sizes`.

The homepage's two selected personal photos still have empty author metadata. They remain empty rather than receiving invented alt or rights text. Existing content validation continues to report the broader missing-rights backlog; the renderer does not infer ownership or a licence.
