# Drei Einstiege, eine Handschrift

Stand: 14. November 2026. Konzeptphase, keine Produktionsänderung. Die Homepage bleibt unverändert. Ziel: mindestens 9/10 im Council für die ausgewählte, später implementierte Richtung. Eine Konzeptbewertung ersetzt keine Browserabnahme.

## Befund

Aktueller Checkout: `27c65d7b1e04effca13504ab717284517b98ae66`, Branch `feat/editorial-entry-pages`. Lokale Vergleichsstände: Politik `2f69501`, Support `c1a0db6`. Diese Seiten sind nicht Teil dieses Ausgangsstands. Integration muss gezielt erfolgen, ohne den privaten Sponsoring-Verwaltungsbereich zu übernehmen.

Die Website nutzt Fira Sans für Überschriften, Geist für Text, Geist Mono für technische Inhalte. Helle Palette: Hintergrund #fbfefa, Text #060f03, Fläche #f3f7f2, Mint #5adbbd, Linien #ccd7ce, Fokus #16836c. Dunkle semantische Tokens existieren. Buttons haben kleine harte Schatten. Die Identität liegt vor allem in Typografie, realen Medien und SVG-Verbindungen.

`homepage.js` choreografiert auf großen Geräten eine gepinnte Bühne: Der Name wandert in den Header, Fotografien ziehen durch die Szene, eine SVG-Linie verbindet Projekte. `homepage-thread.js`, `thread-bump.js` und `cv-thread.js` bieten verwandte Linienlogik. Mobile und reduzierte Bewegung haben eigene Bedingungen. Diese Namensreise und Fotoparade werden auf Unterseiten nicht wiederholt.

Die Politikfassung verwendet hauptsächlich Text und Rollenkarten. Die Supportfassung zeigt drei nahezu identische Budgetspalten. Beide verbergen die eigentliche Geschichte. Supportbeträge 360/900/700 Euro und bestätigte 0 Euro stammen aus Konfiguration, nicht aus hier belegten Angeboten oder Transaktionen. Sie werden nicht als verifizierte Ziele übernommen. Der interne Schlüssel `company` beweist keine Firma.

## A: Vor Ort / An der Werkbank / Im System

Empfehlung. Drei verschiedene Seitenkompositionen, verbunden durch dieselbe präzise grafische Handschrift.

### Politik: fotografische Biografie

Einstieg mit Jack als Person und großem echten Veranstaltungsbild, kurzer Einführung und sichtbarem Hinweis auf die persönliche Website. Danach eine vertikale Zeitlinie mit unterschiedlichen Bildgrößen und ruhigem Text. Schule seit 2019, Aufbau des Erstwählerforums ab 2025, Veranstaltung 2026, Kommunikationsarbeit bei Volt und technische Beiträge als überlappende Kapitel. Keine falsche lineare Karriereleiter. Jahresmarken stammen aus referenzierten Einträgen. Ein Schlusskapitel zeigt belegte Interessen an Musik, Technik und politischer Bildung.

Die Linie führt am Rand der Bilder entlang und verzweigt zu Dokumenten und Projekten. GSAP zeichnet nur den gelesenen Abschnitt nach. Alle Stationen bleiben von Anfang an sichtbar. Kein Pinning. Auf Mobilgeräten steht die Linie links, Bilder und Text folgen der Lesereihenfolge. Das offizielle Profil wird direkt und als externes Parteiprofil kenntlich verlinkt.

### Support: Werkstattbericht

Einstieg mit einem tatsächlichen technischen Problem und authentischem Artefakt. Drei aufeinanderfolgende, unterschiedlich aufgebaute Kapitel statt drei Karten: Softwarebetrieb mit echtem Projektbild und Wartungsbedarf; Rosenfold mit ausdrücklich sichtbarem Entwicklungsstatus und belegtem Arbeitsstand; AtheBlues mit Antrieb, Fertigung und Tests. Jede Sequenz erklärt Problem, vorhandene Arbeit, offenen nächsten Schritt und benötigte Unterstützung. Geld kommt danach.

Eine SVG-Leiterbahn verbindet bei Software den Ablauf. Beim Roboter folgt eine eigene Linie dem dokumentierten Fertigungsablauf. Desktop darf ein Bild während einer kurzen Textsequenz sticky halten, ohne Scrollzwang oder künstliche Leerstrecke. Mobile bleibt vollständig im Dokumentfluss. CAD, Bauteile, Fehlversuche und Fertigungsbilder sind derzeit im öffentlichen Assetbestand nicht vorhanden: Diese Kapitel bleiben bis zu echten freigegebenen Medien als redaktioneller Entwurf, ohne Stockbilder oder erfundene Testfotos.

Bestätigte Budgets erhalten Zweck, Quelle, Stand und Verwaltungskreis. Ohne Belege erscheinen keine Summen oder Fortschrittsbalken. Persönliche Projekte, mögliche geschäftliche Vorhaben und Schulteam bleiben getrennt. Keine Bezahlfunktion. Sponsoringanfragen führen nach `/kontakt?thema=sponsoring`.

### Leistungen: technische Fallstudien

Ein großer echter Anwendungsausschnitt eröffnet eine konkrete technische Fragestellung. Danach abwechselnd Screenshot, kleines Ablaufdiagramm und lesbare Fallbeschreibung. Printgate ist als öffentlich indexierbares frühes Experiment ein belastbarer Startpunkt. Das Portfolio selbst ist ebenfalls noindex und benötigt vor Verwendung als verlinkte Fallstudie eine ausdrückliche redaktionelle Entscheidung. Keine Kundenlogos oder Erfolgsmessungen ohne Nachweis. Private Bewerbungsdaten, Signaturen und CV-Zugänge bleiben ausgeschlossen. Authentifizierung wird nur anhand eines dafür freigegebenen öffentlichen Falls gezeigt; DJL ID ist noindex und deshalb nicht automatisch geeignet.

SVG markiert Eingabe, Verarbeitung und Ergebnis. GSAP kann die zugehörige Verbindung beim Lesen betonen, ohne Inhalte umzuschalten oder zu verdecken. Ein eigener Abschnitt beschreibt AI-Assistenz als Arbeitsmethode: Anforderungen prüfen, Vorschläge lesen, unabhängig testen, Sicherheitsgrenzen kontrollieren und menschliche Verantwortung. Diese Aussagen werden vor Veröffentlichung am tatsächlichen Arbeitsprozess bestätigt.

## B: Das offene Archiv

Politik wird ein datiertes Foto- und Dokumentenarchiv mit einer durchgehenden lesbaren Biografie und einem kleinen Jahresregister. Das erste Bild ist nicht herohaft, sondern Teil eines asymmetrischen Kontaktbogens. Auswahl und Bildunterschriften tragen die Geschichte. Die SVG-Linie wirkt als Randnotiz zwischen Dokument und Ereignis.

Support wird ein öffentliches Entwicklungsjournal. Ein großer aktueller Werkstatteintrag eröffnet die Seite, darunter chronologische Bau- und Betriebsberichte mit ihren belegten Bedürfnissen. Finanzinformationen sind eine nachgelagerte, getrennte Transparenzansicht. Leistungen wird ein technisches Dossier: wenige ausführliche Fälle mit reproduzierbarem Ausgangspunkt, Architektur und ehrlicher Statuszeile.

Bewegung: Ein Dokument wird beim Eintritt sanft ausgerichtet, eine Verbindung zur zugehörigen Notiz gezeichnet. Kein gestapeltes Kartenkarussell, keine versteckten Archivseiten. Mobil bleibt es ein lesbares Journal. Stärke: dokumentarische Glaubwürdigkeit. Risiko: neue Besucher müssen stärker redaktionell geführt werden; leere Aktualität darf nicht mit fiktiven Einträgen gefüllt werden.

## C: Eine Frage führt weiter

Politik beginnt mit einer belegten Situation aus dem Erstwählerforum und führt rückblickend zur Schülervertretung, dann in die heutige Arbeit und persönliche Interessen. Eine visuelle Zeitlinie bleibt vorhanden, die Dramaturgie ist thematisch statt rein chronologisch.

Support beginnt mit einer echten technischen Hürde. Große Nahaufnahme und kurze Textfolge zeigen Ursache und den nächsten Versuch. Danach wechseln Bildpaare zwischen Software und Material. Leistungen beginnt mit einem konkreten Ablauf, etwa PDF bis Druckauftrag, und erklärt Entscheidungen an diesem Beispiel. Andere Fälle folgen als eigenständige Kapitel.

Bewegung: Ein einziges kurzes Desktop-Storyboard pro Seite verbindet zwei bis drei reale Zustände. Die SVG-Linie wechselt zwischen Frage, Beleg und nächstem Schritt. Keine Vollbild-Scrollsperre; auf schmalen und niedrigen Displays sowie bei reduzierter Bewegung stehen alle Zustände untereinander. Stärke: stärkster unmittelbarer Einstieg. Risiko: größte Abhängigkeit von passenden Medien, teuerste Umsetzung und größte Gefahr einer Homepage-Kopie. Kein Storyboard wird gebaut, bevor echte Vorher/Nachher-Medien vorliegen.

## Inhalt und Quellen

| Inhalt | Quelle | Verwendung / Grenze |
| --- | --- | --- |
| Farben, Schrift, Fokus, Themes | resources/css/jack-site-theme.css | Vorhandene Tokens, keine neue Markenpalette |
| Homepage und Motion | resources/views/home.antlers.html; resources/js/homepage*.js | Gestaltungsanalyse, keine Änderungen |
| Bildrenderer | resources/views/partials/media/image.antlers.html | Glide, Größen, Fokus, Attribution wiederverwenden |
| Persönliche Einleitung und Fotos | content/globals/jacks-portfolio/home.yaml | Nur öffentliche freigegebene Inhalte; keine globale Galerie duplizieren |
| Navigation und Identität | content/trees/navigation/main.yaml; resources/views/partials/header.antlers.html; content/globals/jacks-portfolio/site.yaml | Neue Einstiege im CMS-Menü; Header nicht mit vier zusätzlichen Links überladen |
| Schule und Engagement | content/collections/cv_milestones/{schuelervertretung,stellvertretender-schuelersprecher,schulvorstand}.md | Referenzen statt zweite Faktensammlung |
| Erstwählerforum | content/collections/projects/erstwaehlerforum-stade.md; public/assets/projects/erstwaehlerforum-stade | Echte Galerie vorhanden; Credits und Bildfreigabe vor Auswahl prüfen |
| Volt | content/collections/cv_milestones/volt-*.md; content/collections/experience/volt-*.md | Persönliche Arbeit; kein Mandat ableiten |
| Offizielles Profil | https://voltdeutschland.org/niedersachsen/menschen/jack-ruder | Am 14.11.2026 live abgerufen, Name und Kommunikationsrolle bestätigt; Datumswiderspruch siehe unten |
| Robotik | content/collections/projects/atheblues.md | Rescue Line im Antriebsentwurf; Computer Vision geplant, nicht implementiert |
| AtheBlues-Medien | public/assets/projects/atheblues/atheblues-teammark.webp | Nur Teammarke gefunden; kein CAD-/Fertigungsnachweis |
| Software und Leistungen | content/collections/projects/{personal-website,printgate,djl-ui,songraten}.md | Status und öffentliche Sichtbarkeit beachten |
| Rosenfold | Kein öffentlicher Projekteindruck in dieser Collection gefunden | CMS-Entwurf und freigegebene Medien erforderlich; ausdrücklich in Entwicklung |
| Supportaltstand | anderer Worktree: config/support.php; resources/views/support/index.antlers.html | Keine Übernahme unbelegter Zahlen, privater Daten oder Empfängerannahmen |

## Statamic und Kontakt

Pro Seite ein eigenes Blueprint mit Einleitung, kuratierten Kapitelblöcken, referenzierten Projekten/Meilensteinen, Assetauswahl, Alttext, Bildunterschrift und Attribution. Keine Faktenduplikate in JavaScript. Leere oder nicht veröffentlichte Referenzen entfallen sauber; die Erzählung darf dadurch keine falschen Zeitübergänge bekommen. Leistungen wird zunächst als unabhängiger Entwurf geplant.

`/kontakt` erhält ein einzelnes Formular. Sichtbare Auswahl: Projektanfrage, Sponsoring, Politik, allgemeiner Kontakt. Querywerte werden ausschließlich gegen diese Liste aufgelöst. Sichtbare Labels, verständliche Pflichtfeldfehler, Fehlerübersicht mit Fokus, Längenlimits und serverseitige Validierung. CSRF, Rate Limit, Honeypot und Zeitprüfung ohne zusätzliche Trackingdienste. Keine Anhänge. Keine Nachrichtentexte in Logs, Analytics, URLs oder persistentem Fehlerzustand. Keine automatische Kopie an Absender. Mail wird nur mit explizit aktiviertem Versand und konfiguriertem Empfänger/Transport versendet. Ohne Konfiguration ist der Versand deaktiviert und wird nicht als erfolgreich quittiert. Mailadressen werden validiert; Header stammen aus fester Konfiguration. Datenschutztext und Aufbewahrung werden vor Aktivierung bestätigt.

## Option Open Collective

Nur Planung, kein API-Aufruf und keine Integration in dieser Phase. Später ausschließlich serverseitiger GraphQL-Abruf für einen verifizierten Collective und Empfänger. Feste Queries, begrenzte Laufzeit, Cache mit Standanzeige, Backoff und letzte gültige Antwort. Getrennte Projektzuordnung und Verwaltungskreise; keine doppelte Zuordnung einer Transaktion. Nur tatsächlich bestätigte, abgeglichene Zahlungseingänge nach definierter Behandlung von Gebühren, Erstattungen, Währung und Zeitraum. Fehlende Daten heißen unbekannt, nicht 0 Euro. Keine Veröffentlichung privater Spendernamen ohne Freigabe. Genaue Query und Statussemantik vor Implementierung gegen offizielle Dokumentation prüfen.

## Umsetzung und Abnahme nach Richtungswahl

1. PR Kontakt: gemeinsames Formular, standardmäßig ausgeschalteter Versand, Validierungs- und Datenschutztests. Verlinkung aus den Einstiegen.
2. PR Politik: selektive Übernahme der öffentlichen Quellen und Blueprint-Struktur, fotografische Biografie, Motion und Quellenmapping.
3. PR Support: öffentliche Erzählung und bestätigte Finanzdaten, Medienentwürfe bei Quellenlücken. Keine private Buchhaltung, keine Paymentfunktion.
4. Leistungen: separater Plan und CMS-Entwurf. Veröffentlichung erst nach Freigabe der Fallstudien; vollständiger Build nur nach Bestätigung dieses Umfangs.

Keine PR wird gemergt oder deployt. Bestehende Featurebranches werden nicht pauschal gemergt. Abhängigkeiten zwischen Kontakt und CTA-Links werden vor PR-Eröffnung festgelegt, sodass kein veröffentlichbarer Stand tote Links enthält.

Evidenz je implementierter Seite: Desktop 1440×900 und Mobile 390×844 sowie 320px-Reflow; vollständige Screenshots, kurzer Scrollfilm und reduzierte-Bewegung-Ansicht. Zusätzlich Tastaturablauf, sichtbarer Fokus, Überschriften, Alttexte, Kontrast in beiden Themes, 200%-Zoom, JS-aus und automatisierter Accessibilityscan. Keine verdeckten Textinhalte ohne JS. SVG rein dekorativ und für Assistenztechnik ausgeblendet.

Performance: Build und getrennte Lighthouse-Läufe für Desktop und gedrosseltes Mobile, drei kalte Läufe mit Median und Range. Ziel LCP ≤2,5s, CLS ≤0,1 und Lighthouse Performance ≥90 unter dokumentiertem lokalen Profil, keine behaupteten Felddaten. Ein priorisiertes responsives Einstiegsbild; übrige Medien lazy mit Abmessungen. Zielbudget pro Route ≤1 MB initiale Übertragung, ≤250 KB komprimiertes JS insgesamt, ≤200 KB Einstiegsbild. Budgets werden nach Baseline gemessen, nicht durch Entfernung wichtiger Inhalte erzwungen. Kein Scroll-Handler mit Layoutmessung je Frame. Routenbezogene Motionmodule und GSAP matchMedia mit Cleanup.

Council bewertet Quellenwahrheit, eigenständige Einstiege, Zugehörigkeit zum Portfolio, Unterschiede der Seiten, mobile Lesbarkeit, Motion und technische Machbarkeit. ≥9/10 pro vollständiger Prüfung ist das Ziel, ohne Score-Erfindung. Ausstehende Medien und Browserbelege bleiben ausdrücklich offen.


## Konkretisierung nach Council Runde 1

Die interaktive Datei `entry-pages-concepts.html` enthält für jede Richtung drei Kompositionsskizzen mit Einstieg, Mittelteil und Abschluss. Das sind bewusst noch keine fertigen Designs oder Implementierungsbelege. Die Bildlücken sind darin sichtbar, statt durch generische Illustrationen kaschiert.

A unterscheidet Personenporträt und Randzeitlinie, bildfüllenden Werkstattbericht und technischen Querschnitt. B unterscheidet Kontaktbogen mit Jahresregister, aktuelles Werkstattjournal und Dossier mit Randnavigation. C unterscheidet einen fotografischen Szeneneinstieg, eine sequenzielle Problemuntersuchung und einen durchgehenden Anwendungsablauf. Diese Unterschiede müssen ohne Bewegung bestehen bleiben.

Die gemeinsame SVG-Grammatik verwendet abgerundete Enden, einen 2–3px-Strich, semantische Akzentfarbe und dieselben weichen Bézierkurven wie die vorhandene Website. Keine Leiterplatten-Dekoration ohne tatsächliche technische Beziehung. Knoten markieren nur belegte Stationen oder Übergänge.

Sticky-Medien sind nur oberhalb 64rem Breite und 48rem Höhe zulässig; maximale Bildhöhe `min(60svh, 36rem)`, keine feste Scrollstrecke und kein Pin. Bei 200%-Zoom, kleinerem Viewport oder reduzierter Bewegung entfällt Sticky vollständig.

Support ohne neue Medien bleibt ein redaktioneller Entwurf. Ein öffentlicher Ersatz aus drei Textblöcken würde die ursprüngliche Aufgabe verfehlen. Die vorhandene Printgate-Aufnahme zeigt ausschließlich den Softwareteil, niemals Robotik oder Rosenfold. Fehlversuche, Herstellung und konkrete Beschaffungen werden erst mit echten freigegebenen Belegen ergänzt.

Die Kontakt-PR wird zuerst vorbereitet. Darauf basieren die Politik- und Support-PRs, damit die CTAs keine nicht vorhandene Route voraussetzen. Einzeln reviewbar bedeutet nicht unabhängig deploybar; die Abhängigkeit steht in jeder PR.

Liveprüfung des offiziellen Profils am 14.11.2026: https://voltdeutschland.org/niedersachsen/menschen/jack-ruder bestätigt Name, Rolle und technische Mitarbeit. Der Text nennt die Wahl zum Communications Lead Anfang März; der lokale Meilenstein nennt Februar 2026. Bis zur Klärung wird keine präzise Monatsangabe dafür verwendet und kein Quelleneintrag stillschweigend geändert.

Die bestehende `noindex`-Entscheidung von personal-website, DJL UI, SongRaten und DJL ID wird nicht automatisch aufgehoben. Für Leistungen werden nur ausdrücklich freigegebene Fälle ausgewählt. AI-Methoden sind geplante, noch zu bestätigende Aussagen.

## Council Runde 1

Konzeptbewertung des Textplans, vor der visuellen Konkretisierung. Keine Implementierungsabnahme.

| Delegate | A | B | C |
| --- | --- | --- | --- |
| GPT 6.1 Sol, high | 8,6/10 | 8,1/10 | 7,7/10 |
| Gemini 4 Hydrogen, high | 8,5/10 | 7,6/10 | 6,9/10 |
| Mistral Medium 3.5, high | no score | no score | no score |

Mistral scheiterte an HTTP 402 `billing_vibe_budget_exhausted`. Claude war deaktiviert. Es liegt kein vollständiges Drei-Provider-Council vor. Beide erhaltenen Reviews empfehlen A. Das Ziel 9,0 ist noch nicht erreicht; eine erneute Bewertung muss die überarbeiteten visuellen Entwürfe prüfen.


## Entscheidung und Council Runde 2

Der Nutzer hat am 14.11.2026 Richtung A ausgewählt: fotografische Biografie, Werkstattbericht und technische Fallstudien. Dies ist die Richtungswahl für die Umsetzung, keine visuelle Abnahme und kein erreichter 9,0-Score.

| Delegate | A Vollständigkeit / Ausführung | B Vollständigkeit / Ausführung | C Vollständigkeit / Ausführung |
| --- | --- | --- | --- |
| GPT 6.1 Sol | 8,7 / 7,5 | 8,2 / 7,1 | 7,7 / 6,7 |
| Gemini 4 Hydrogen | 8,6 / 7,4 | 8,0 / 7,2 | 7,1 / 6,2 |

Gemini vergab zusätzlich Gesamtwerte A 8,3, B 7,8 und C 6,8. GPT vergab keinen Gesamtwert. Die Medienlücken und ungleiche Ausarbeitung der drei Richtungen bleiben Abzüge. Beide empfehlen A. Mistral bleibt wegen Budgetfehler ohne Ergebnis, kein dritter Provider wurde ersetzt.

Korrekturpass nach Runde 2: Geist und Geist Mono eingebettet, dunkle Ansicht ergänzt, Jahreszahlen auf Mobilgeräten aus der zu schmalen Spalte gelöst, C-Überschrift aus dem hellen Foto genommen, vollständige Software-Screenshots statt Beschnitt, persönliche Schlusssequenz und offizieller Profillink bei A ergänzt. A beginnt nun mit der Veranstaltung. Support verwendet prtop statt derselben Printgate-Aufnahme wie Leistungen. SVG-Verbindungen erscheinen innerhalb der Kapitelkompositionen. Diese Korrekturen wurden noch nicht erneut bewertet.

Weitere öffentliche Quellen: `content/collections/projects/prtop.md` ist aktiv und indexierbar, mit echten Screenshots. Kein Release; der Eintrag erklärt, dass Jack es derzeit selbst nicht nutzt. `ai-ctx.md` ist indexierbar, aber archiviert und unvollständig. Beide sind keine Kundenreferenzen. Die bisherige Quellenauswahl war zu eng.

Zusätzliche lokale Medienrecherche: `/home/jack/dev/Rosenfold/docs/evidence/ux-repair-screenshots/` enthält echte Entwicklungsscreenshots. Eine Aufnahme wurde angesehen; Aktualität und öffentliche Freigabe bleiben zu prüfen. Kein Transfererfolg wird aus einem Ready-Screenshot abgeleitet. `/home/jack/dev/AtheBlues` enthält Quellcode und archivierte KiCad-Projekte, aber im gezielten Dateiscan keine freigegebenen aktuellen Werkstattfotos. Archivmaterial darf nicht als Rescue-Line-Stand ausgegeben werden.

Visuelle Referenzprüfung versucht: T3 konnte die MOUTHWASH-Fallstudie öffnen, aber zweimal keine Screenshotframes liefern. Der alternative Browserzugang meldete einen nicht verfügbaren Browserhost. Deshalb weiterhin keine eigene visuelle Referenzinspektion behauptet. Der geöffnete Referenztab wurde geschlossen.
