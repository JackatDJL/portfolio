# CV: visueller Neuaufbau und Prüfung

Stand: 13. September 2026. Die Änderungen liegen im Checkout `t3code-7ed55c3f`.

1. **Informationsarchitektur.** `/cv` und `/cv/allgemeines-profil` verwenden dieselbe Dokumentansicht mit Jack Ruder, öffentlichem Kontakt, Erfahrung, Bildung, ausgewählten Projekten und Veröffentlichungen. Die bestehenden Profilauswahlen bleiben erhalten.
2. **Komposition.** Fira Sans trägt Name, Rollen und Abschnittstitel. Geist bleibt Leseschrift. Auf Desktop bilden Abschnitt, Organisation/Zeitraum und Inhalt ein kompaktes Raster. Auf Mobile steht der Kontext oberhalb der Rolle. Keine Karten oder dekorative Timeline.
3. **Profiltexte.** „Allgemeines Profil“ und die gesamte neutrale Bewerbungs-/Preset-Erklärung wurden aus dem Default-Eintrag entfernt. Der öffentliche Titel ist „Lebenslauf“, die sichtbare Identität Jack Ruder. Keine neue Profilwerbung.
4. **Experience.** Eigene Kapitelansicht mit Organisation, Zeitraum, Rolle, normalem Zusammenfassungstext, Bard-Inhalt, Themen und Kontext. Bestehende Projektbeziehungen können ihre tatsächlichen Zusammenfassungen, Webseiten und Medien zeigen. Nicht veröffentlichte oder vertrauliche Projekte werden dort ausgefiltert. Öffentliche Quellen sind als redaktionelle Links pflegbar.
5. **Education.** Das Athenaeum-Foto bildet die volle Breite und nahezu das gesamte erste Viewport. Ein abgestufter dunkler Verlauf hält Überschrift und Text lesbar, während das Gebäude sichtbar bleibt. Danach folgt normaler Scroll zu Text, Projekten und Themen.
6. **Galerie.** Ein Renderer für Projekt-Galeriefelder, Bard-Galerien und verknüpfte Experience-Medien. 33 EWF-Bilder bleiben in redaktioneller Reihenfolge in einem horizontalen Streifen. Proportionen und intrinsische Maße bleiben erhalten; kein einheitlicher Zuschnitt. Alle Galerie-Bilder laden lazy.
7. **Galeriebedienung.** Native horizontale Bewegung und Touch-Swipe, Scroll-Snap, beschriftete Buttons, Positionsanzeige, Pfeiltasten und Home/End. Rand-Buttons werden deaktiviert. Reduced Motion schaltet programmgesteuerte weiche Bewegung ab. Ohne JavaScript bleibt die native Scrollfläche nutzbar.
8. **Asset-Felder.** Vorhanden sind `alt`, `caption`, `rights` mit `jack`/`cc` und das freie Attributionsfeld `cc`. Es gibt keine getrennten Felder für Fotograf, Rechteinhaber, Lizenz oder Quell-URL. Diese Angaben werden aus dem bestehenden Text übernommen, nicht erfunden oder neu interpretiert.
9. **Attribution.** `partials/media/attribution` verarbeitet Asset-Metadaten und vorhandene Bard-Caption/Credit-Werte. Es trennt Bildunterschrift und kleine Credit-Zeile, vermeidet identische Doppelungen und verlinkt explizite HTTP(S)-Quellen. Fremdes HTML wird escaped. Bei `rights: jack` wird kein möglicherweise veralteter Fremd-Credit angezeigt.
10. **Abgedeckte Bildausgaben.** Education-Cover, Projekt-Cover, Projekt-Galerie, Experience-Projektmedien sowie gemeinsame Bard-Abbildungen und -Galerien. Blog und Publikationen erhalten denselben Weg über ihre bestehenden Bard-Sets; ihre Layouts und der PDF-Viewer wurden nicht umgebaut. Im aktuellen Inhalt gibt es keine nativen Bard-Image-Nodes außerhalb der Sets. 33 EWF-Alttexte und der Alttext des AtheBlues-Covers wurden nach Bildsichtung ergänzt.
11. **Project-Beziehungen.** Experience wird nicht mehr im öffentlichen Projekt-Rail ausgegeben. Die Relation bleibt im Blueprint und Inhalt bestehen.
12. **Linkpfeile.** CV-Einträge, CV-Relations und öffentliche Experience-Quellen benutzen die vorhandene `open-file`-Komponente. Der Pfeil bleibt mit dem letzten Titelwort zusammen und kann nicht allein in die nächste Zeile rutschen. Andere bereits freigegebene Relations behalten ihre Darstellung.
13. **Druck.** A4, 14 mm Rand, kleine Typografie, kompakte Abstände, keine Navigation/Pfeile und keine Hover-Abhängigkeit. Datensätze werden möglichst nicht getrennt. Der aktuelle Standard-CV passt einschließlich aller ausgewählten Projekte, Zusammenfassungen und Veröffentlichungen auf eine Seite.
14. **Responsive.** Desktop-Raster kollabieren auf Mobile ohne schmale Restspalten. Das Education-Bild behält seine Funktion als Hintergrund. Die Galerie lässt einen Teil des nächsten Bildes erkennen. Eine vorhandene `100vw`-Überbreite des mobilen Projektcovers wurde korrigiert, ohne dessen Gestaltung zu verändern.
15. **Browserprüfung.** Alle zehn CV-/Detail-Testpfade wurden bei 1440 × 1000 und 390 × 844 in Light und Dark geprüft und als Screenshots festgehalten. Native Touch-Eingabe, horizontales Wheel, Keyboard, Galerie-Enden, Reduced Motion, drei unterschiedliche Galeriebreiten und Print wurden separat geprüft. Der A4-PDF-Export wurde gerendert und visuell kontrolliert.
16. **Inhaltsgrenzen.** Die drei Volt-Einträge haben weiterhin nur kurze Texte und Themen, keine kanonischen Projekt-/Publikations-/Medienbeziehungen. Deshalb wurden dort keine zusätzlichen Artefakte ergänzt. In diesem Checkout fehlt die im Auftrag erwähnte CV-Capability-/Privatdaten-Ausgabe vollständig; der CV-Global ist leer. Es wurde keine Ersatz-Sicherheitsarchitektur gebaut und es werden nur bereits öffentliche Kontaktdaten verwendet.
17. **Validierung und Builds.** Die genauen Kommandos und Ergebnisse stehen unten. Die Asset-Validierung bleibt wegen fehlender Rechteangaben rot; das ist eine echte Inhaltslücke.

## Eingearbeitete Zeitangaben und Ergänzungen

| Experience | Beginn / Darstellung | Grundlage |
| --- | --- | --- |
| Volt Stade Kommunikation | Februar 2026 | Nutzerangabe; Monatsgenauigkeit, kein behaupteter genauer Tag |
| Volt Europa | 12. Juni 2026 | Nutzerangabe |
| Volt Deutschland IT | 21. Juli 2026 | Nutzerangabe zum Eintritt in die IT-Gruppe |
| Erstwählerforum Organisation | 25. Oktober 2025 | Nutzerangabe zum letzten Samstag der Herbstferien, abgeglichen mit dem offiziellen Ferienkalender |
| Hackclub Stade | Januar 2025; Agreement am 18. Januar | Nutzerangabe; AG-Idee, Ablehnung und heutige zeitliche Grenze ergänzt |
| Robotik AG · Athenaeum Stade | Seit etwa dem Schuljahr 2021/22 | Unsicherheit der Nutzerangabe bleibt sichtbar; kein fiktives Startdatum |

Die neuen optionalen Experience-Felder `started_at_precision` und `period_label` unterscheiden einen bekannten Monat von einem genauen Datum bzw. einem ungefähr bekannten Schuljahr. `links` enthält öffentliche Quellen. Die bestehenden URLs und IDs bleiben erhalten.

Für Robotik sind 2023, 2024, die eigenen Projekte 2025/2026 und das aktuelle Vorhaben für Saison 2027 getrennt beschrieben. Geschwindigkeit, Hotplug-Fähigkeit und Computer Vision bleiben Ziele des laufenden Projekts, keine behaupteten Ergebnisse.

Quellen: [Ferienübersicht des niedersächsischen Kultusministeriums](https://www.mk.niedersachsen.de/download/190063/Uebersicht_Ferien_2024_25_bis_2029_30.pdf), [Schulbericht RoboCup Hamburg 2023](https://athenetz.de/ag-robotik/rcj_2023_hamburg/rcj_2023_hamburg.html), [Schulbericht RoboCup Kassel 2024](https://athenetz.de/ag-robotik/rcj_2024_kassel/rcj_2024_kassel.html).

## Prüfungen

- `php artisan test`: 12 Tests, 30 Assertions bestanden.
- `bun run test:cv`: 8 Tests bestanden; 40 Seiten-/Viewport-/Theme-Kombinationen plus Interaktions- und Druckprüfungen.
- `bun run test:browser`: 1 Test bestanden.
- `bunx playwright test --config playwright.homepage.config.js`: 8 Tests bestanden.
- `bun run build`: Vite-Produktionsbuild erfolgreich.
- `php scripts/validate-content.php`: alle 31 Einträge bestanden. 43 von 50 Assets ohne das erforderliche `rights`-Feld. Keine Lizenzen oder Eigentümer ergänzt. Der vollständige Bericht liegt in `storage/app/content-validation.json`.
- `APP_URL=http://127.0.0.1:8010 php artisan statamic:ssg:generate`: 37 Seiten erfolgreich generiert. Die lokale Basisadresse dient dem statischen Browser-Testserver.
- `vendor/bin/pint --test ...` und `git diff --check`: bestanden.

Screenshots und der A4-Export liegen unter `storage/app/cv-qa/`. `bun run test:cv` erstellt sie erneut und startet bei Bedarf den lokalen PHP-Server auf Port 8091. Vorher `bun run build` ausführen. Frontend-Fixture-Tests sollten separat laufen, da deren Vite-Server die temporäre Datei `public/hot` verwendet.
