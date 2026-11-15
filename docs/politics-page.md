# Persönliche politische Biografie

Die Seite `/politik` ist eine eigenständige persönliche Biografie. Sie nutzt Fira Sans, Geist, semantische Themefarben und eine Kapitelverbindung aus der bestehenden Portfolio-Gestaltung. Die Homepage-Dateien und ihre Scrollchoreografie bleiben unverändert. Nur das gemeinsame CMS-Menü erhält Politik und Kontakt.

Inhalte werden über das Blueprint `politik` bearbeitet. Einleitung, Kapitelüberschriften, Bilder und Bildunterschriften liegen in `content/collections/pages/politik.md`. Schulstationen, Erstwählerforum, Volt-Mitarbeit und Jugendchor werden als vorhandene Einträge referenziert. Veröffentlichte Quelltexte bleiben dort editierbar; unveröffentlichte Referenzen werden nicht ausgegeben. Zeitangaben, technische Motivation und Kontaktabschluss sind ebenfalls CMS-Felder. Ein zusätzlicher Abschnitt zu Mobilität, digitaler Bildung, demokratischer Beteiligung und Europa paraphrasiert die belegten persönlichen Interessen aus dem offiziellen Profil. Die Seite hat keine Wahlkampf-Funktion und behauptet weder Kandidatur noch Mandat.

| Abschnitt | Quelle |
| --- | --- |
| Schule | `cv_milestones/schuelervertretung.md`, `stellvertretender-schuelersprecher.md`, `schulvorstand.md` |
| Erstwählerforum | `projects/erstwaehlerforum-stade.md`, vorhandene Projektgalerie |
| Kommunikation | `experience/volt-stade-kommunikation.md`, vorhandenes Foto `photos/volt-av2-20260520.jpg` |
| Technik | `experience/volt-europa-technische-mitarbeit.md` |
| Persönliches | öffentliche Kurzbiografie, `cv_milestones/jugendchor-neukloster.md`, vorhandenes Porträt |
| Offizielles Profil | `https://voltdeutschland.org/niedersachsen/menschen/jack-ruder`, live geprüft am 14.11.2026 |

Der widersprüchliche Beginn der Kommunikationsrolle, lokal Februar und auf der Parteiseite März, wird nicht stillschweigend vereinheitlicht. Die Seite verwendet hierfür keine Monatsangabe. Sie trennt persönliche Darstellung und offiziellen Parteiauftritt unmittelbar im Einstieg und beim externen Profillink.

Vier Fotografien verwenden den bestehenden Glide-Renderer mit Alttext, Abmessungen, responsiven Quellen und Attribution. Nur das Einstiegsbild ist eager/priorisiert. Es werden keine neuen Bildinhalte erzeugt. Die Auswahl verwendet bereits öffentliche Portfolio-Assets; eine spätere andere Bildauswahl braucht entsprechende Herkunftsangaben.

Die datierte Geschichte bleibt als normales Dokument lesbar. Kapitelanker funktionieren ohne JavaScript. Die GSAP-Datei wird nur auf dieser Route geladen und zeichnet ausschließlich die SVG-Verbindung, ohne Inhalte zu verstecken oder Scrollen zu sperren. Bei reduzierter Bewegung bleibt die Linie statisch; ohne JavaScript markiert eine einfache CSS-Linie den Verlauf. Layoutmessung erfolgt beim Aufbau und bei Größenänderung, nicht pro Scrollframe.

Diese PR hängt von der Kontakt-PR ab, weil der Abschluss nach `/kontakt?thema=politik` führt. Keine Zahlungsfunktion, keine private CV-Verknüpfung, kein Merge oder Deployment.

Prüfen: `php artisan test --filter=PoliticsPageTest`, `bun run build`. Die fokussierten Tests prüfen reale Textquellen sowie vier Bilder mit Alternativtext, Abmessungen und genau einer Priorisierung. Sie ersetzen keine interaktive Browser-, Kontrast-, Motion- oder Performanceabnahme.
