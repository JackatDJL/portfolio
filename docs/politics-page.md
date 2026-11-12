# Politics and civic engagement page

`/politik` is a personal portfolio page, not an official Volt website. Its career, school representation, event, publication and post records are selected through Statamic entry relations in `content/collections/pages/politik.md`; edit the source entries for biographical facts.

The election-period panel is opt-in. It appears only when `campaign_enabled` is on, both dates are valid, the start is not after the end, and the current date in the application timezone is within the inclusive range. The campaign copy is managed separately from the standard biography and defaults to unpublished.

The shared metadata tag emits `ProfilePage` on `/politik`, with the existing verified `Person` entity as its `mainEntity`. The normal sitemap inclusion rules apply. `llms.txt` is supplementary navigation only and makes no ranking claim. Private CV tokens and application records remain behind their existing route controls.

Run the focused page coverage with `php artisan test --filter=PoliticsPageTest`.
