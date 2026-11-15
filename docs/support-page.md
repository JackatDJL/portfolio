# Projektförderung als Werkstattbericht

Richtung A. Der Supporteintrag bleibt ausdrücklich unveröffentlicht, bis die fehlenden authentischen Medien und öffentlichen Projektangaben ergänzt sind. `/support` antwortet für diesen Zustand mit 404. Es gibt keinen öffentlich erreichbaren Preview-Bypass.

Das Blueprint `support` hält Kapitel, Assetauswahl, Bildunterschriften und Bedarfstexte editierbar. Der Entwurf beginnt mit einem nachvollziehbaren Arbeitsstand und zeigt Bedarf erst danach. Er übernimmt weder die geschätzten 360/900/700 Euro aus dem anderen Worktree noch dessen private Sponsoringverwaltung.

| Kapitel | Quelle | Noch erforderlich |
| --- | --- | --- |
| Persönliche Software | `projects/prtop.md` und vorhandener Terminal-Screenshot | Zweckbezogene, belegte Hosting-/Betriebskosten bei künftigen Summen |
| Rosenfold | Lokaler Webstand `db38debd47798aabb941adbcec13768f1a1a48d5`, Ready-Screenshot und geprüfter Ausschnitt aus `packages/core/src/progress-throttle.ts`; vom Nutzer für diese Seite angefordert | Entwicklungsversion, kein erfolgreicher Transfer nachgewiesen. Konkrete Kosten und nächster finanzierter Schritt bleiben offen. |
| AtheBlues / Archiv | [Robotik-AG: Kassel 2024](https://athenetz.de/ag-robotik/rcj_2024_kassel/rcj_2024_kassel.html), technisches Poster; [Hannover 2024](https://athenetz.de/ag-robotik/rcj_2024_hannover/rcj_2024_hannover.html), Team und Busmodell | Dokumentiert OnStage, Motorwechsel, gedruckte Halterungen, Steuerung und berichtete Bluetooth-Probleme. Nicht als heutiger Rescue-Line-Stand dargestellt. |
| AtheBlues / nächster Entwurf | `projects/atheblues.md`: Rescue-Line-Antrieb im Entwurf, Computer Vision geplant | Aktuelle CAD-, Bauteil-, Fertigungs- und Testaufnahmen fehlen weiterhin. Die verlinkte AG berichtet 2026 über andere Teams. |

Der Nutzer hat die AG-Seite als Medienquelle sowie echte Rosenfold-Screenshots, Code und eine programmatisch erzeugte Laptopansicht ausdrücklich angefordert. Das Laptopmotiv ist ein unbenanntes 3D-Modell mit dem unveränderten Ready-Screenshot als Bildschirmtextur. Bildunterschrift und Alttext kennzeichnen es als Rendering. Es ist keine Fotografie vorhandener Hardware. Weder Kopplungscodes noch private Dateiinhalte werden aufgenommen. Der Codeausschnitt wird als zugänglicher, escapeter Text geführt; die Projektlizenz von Rosenfold wird nicht als bereits festgelegt ausgegeben.

Die Originale des technischen Posters und der Teamaufnahme stammen von der angegebenen AG-Seite. Der Bildnachweis nennt Quelle und Jahr, ohne eine Creative-Commons-Lizenz zu behaupten. Im bestehenden Assetfeld bedeutet `rights: cc` lediglich „Jemandes anderen“. Aufnahmen bleiben ausdrücklich datiert. Der Posterlink öffnet das vollständige Originalasset; die Seite verlangt nicht, kleingedruckte Postertexte lesen zu können. Wichtige technische Aussagen stehen zusätzlich als CMS-Text daneben.

`scripts/media/render-rosenfold-laptop.py` erzeugt das dauerhafte Produktmotiv. Beispiel: `blender -b --python scripts/media/render-rosenfold-laptop.py -- --screen public/assets/projects/rosenfold/rosenfold-ready.png --output /tmp/rosenfold-laptop.png`. Blender benötigt eine funktionierende OpenColorIO-Konfiguration. Die lokale Paketinstallation hatte eine inkompatible Profilversion; für den Render wurde eine kompatible lokale Konfiguration außerhalb des Repositorys verwendet. PR-Nachweise und Renderlogs werden nicht eingecheckt.

Persönliche Software, unabhängige Vorhaben und Schulteam bleiben finanziell getrennt. Der interne Schlüssel `company` aus dem Altstand wird nicht als bestehende Firma interpretiert. Für das Schulteam wird Unterstützung ausschließlich über eine tatsächlich zuständige Schule oder Teamorganisation geklärt. Die Seite nimmt kein Geld entgegen und bietet keine Paymentfunktion.

Es gibt noch keine geprüften Beträge. Darum erscheinen keine Summen, Nullwerte oder Fortschrittsbalken. Spätere Budgets brauchen nachvollziehbare Kostenbasis, Zweck, Zeitpunkt, Verwaltung und bestätigte Zuordnung. Bestätigte Geldbeiträge, Zusagen und Sachleistungen dürfen nicht zusammengerechnet werden. Öffentliche Namensnennung setzt eine ausdrückliche Freigabe voraus.

## Option Open Collective

Nur Plan, keine API-Aufrufe oder Anbindung. Zuerst Collective, rechtlichen Empfänger und Zuordnung zu einem Verwaltungskreis verifizieren. Keine automatische Verbindung zwischen persönlichen Projekten und Schulteam.

Ein späterer Laravel-Service ruft eine feste GraphQL-Abfrage serverseitig ab, niemals aus dem Browser und niemals mit Zugangsdaten in Statamic-Inhalten. Zeitlimit, begrenzte Ergebnisanzahl und kontrollierte Pagination begrenzen den Abruf. Ein Cache hält normalisierte öffentliche Summen und den Abrufzeitpunkt. Bei Fehlern bleibt die letzte gültige Antwort mit sichtbarem Alter stehen; ohne gültigen Stand erscheint unbekannt statt null. Backoff verhindert wiederholte Abrufe bei Ausfällen.

Ein eigener Zuordnungsdatensatz verbindet bestätigte Transaktions-IDs eindeutig mit Projekt und Verwaltungskreis. Doppelte Zuordnung wird zurückgewiesen. Vor einer Summenanzeige sind Statussemantik, Währung, Gebühren, Erstattungen, Zeitraum und Umbuchungen festzulegen und mit offiziellen API-Dokumenten abzugleichen. Keine Brutto-/Nettoverwechslung und kein Mischen mehrerer Währungen. Nur tatsächlich bestätigte, abgeglichene Eingänge zählen; Zusagen und Sachleistungen werden separat beschrieben.

Öffentlich erscheinen Zweck, Datenstand und nachvollziehbare Projektsummen. Private Spendernamen, Nachrichten und Zahlungsdetails werden nicht übernommen. Der API-Ausfall darf weder den Seitenaufbau blockieren noch eine neue Spende vortäuschen. Vertragstests mit anonymen Fixtures und fachliche Abstimmung mit dem Empfänger gehen einer Aktivierung voraus.

## Nachweise

`SupportPageTest` prüft Veröffentlichungssperre und Entwurfsdarstellung ohne erfundene Geldziele. `bun run build` prüft die Module. Echte immersive Mediensequenzen, Browsermotion, Accessibility und Performance sind noch offen. Die Kontakt-PR ist eine Abhängigkeit. Kein Merge oder Deployment.
