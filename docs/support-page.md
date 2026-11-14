# Projektförderung als Werkstattbericht

Richtung A. Der Supporteintrag bleibt ausdrücklich unveröffentlicht, bis die fehlenden authentischen Medien und öffentlichen Projektangaben ergänzt sind. `/support` antwortet für diesen Zustand mit 404. Es gibt keinen öffentlich erreichbaren Preview-Bypass.

Das Blueprint `support` hält Kapitel, Assetauswahl, Bildunterschriften und Bedarfstexte editierbar. Der Entwurf beginnt mit einem nachvollziehbaren Arbeitsstand und zeigt Bedarf erst danach. Er übernimmt weder die geschätzten 360/900/700 Euro aus dem anderen Worktree noch dessen private Sponsoringverwaltung.

| Kapitel | Quelle | Noch erforderlich |
| --- | --- | --- |
| Persönliche Software | `projects/prtop.md` und vorhandener Terminal-Screenshot | Zweckbezogene, belegte Hosting-/Betriebskosten bei künftigen Summen |
| Rosenfold | Nutzerauftrag: ausdrücklich in Entwicklung | Öffentliche Projektbeschreibung, freigegebene aktuelle Screenshots, belegter nächster Arbeitsschritt |
| AtheBlues | `projects/atheblues.md`: Rescue-Line-Antrieb im Entwurf, Computer Vision geplant | Aktuelle CAD-, Bauteil-, Fertigungs- und Testaufnahmen mit Kontext und Rechten. Keine alten OnStage-Bilder als aktueller Rescue-Line-Baufortschritt. |

Die sichtbaren Medienhinweise sind redaktionelle Entwurfsmarkierungen, keine fertige öffentliche Gestaltung. Fehlversuche oder Herstellungsschritte werden nicht erfunden. Bereits lokale Rosenfold-Screenshots sind Kandidaten, keine automatische Veröffentlichungserlaubnis und kein Beweis heutiger Produktfunktion.

Persönliche Software, unabhängige Vorhaben und Schulteam bleiben finanziell getrennt. Der interne Schlüssel `company` aus dem Altstand wird nicht als bestehende Firma interpretiert. Für das Schulteam wird Unterstützung ausschließlich über eine tatsächlich zuständige Schule oder Teamorganisation geklärt. Die Seite nimmt kein Geld entgegen und bietet keine Paymentfunktion.

Es gibt noch keine geprüften Beträge. Darum erscheinen keine Summen, Nullwerte oder Fortschrittsbalken. Spätere Budgets brauchen nachvollziehbare Kostenbasis, Zweck, Zeitpunkt, Verwaltung und bestätigte Zuordnung. Bestätigte Geldbeiträge, Zusagen und Sachleistungen dürfen nicht zusammengerechnet werden. Öffentliche Namensnennung setzt eine ausdrückliche Freigabe voraus.

## Option Open Collective

Nur Plan, keine API-Aufrufe oder Anbindung. Zuerst Collective, rechtlichen Empfänger und Zuordnung zu einem Verwaltungskreis verifizieren. Keine automatische Verbindung zwischen persönlichen Projekten und Schulteam.

Ein späterer Laravel-Service ruft eine feste GraphQL-Abfrage serverseitig ab, niemals aus dem Browser und niemals mit Zugangsdaten in Statamic-Inhalten. Zeitlimit, begrenzte Ergebnisanzahl und kontrollierte Pagination begrenzen den Abruf. Ein Cache hält normalisierte öffentliche Summen und den Abrufzeitpunkt. Bei Fehlern bleibt die letzte gültige Antwort mit sichtbarem Alter stehen; ohne gültigen Stand erscheint unbekannt statt null. Backoff verhindert wiederholte Abrufe bei Ausfällen.

Ein eigener Zuordnungsdatensatz verbindet bestätigte Transaktions-IDs eindeutig mit Projekt und Verwaltungskreis. Doppelte Zuordnung wird zurückgewiesen. Vor einer Summenanzeige sind Statussemantik, Währung, Gebühren, Erstattungen, Zeitraum und Umbuchungen festzulegen und mit offiziellen API-Dokumenten abzugleichen. Keine Brutto-/Nettoverwechslung und kein Mischen mehrerer Währungen. Nur tatsächlich bestätigte, abgeglichene Eingänge zählen; Zusagen und Sachleistungen werden separat beschrieben.

Öffentlich erscheinen Zweck, Datenstand und nachvollziehbare Projektsummen. Private Spendernamen, Nachrichten und Zahlungsdetails werden nicht übernommen. Der API-Ausfall darf weder den Seitenaufbau blockieren noch eine neue Spende vortäuschen. Vertragstests mit anonymen Fixtures und fachliche Abstimmung mit dem Empfänger gehen einer Aktivierung voraus.

## Nachweise

`SupportPageTest` prüft Veröffentlichungssperre und Entwurfsdarstellung ohne erfundene Geldziele. `bun run build` prüft die Module. Echte immersive Mediensequenzen, Browsermotion, Accessibility und Performance sind noch offen. Die Kontakt-PR ist eine Abhängigkeit. Kein Merge oder Deployment.
