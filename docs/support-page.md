# Support aus der Root-Inszenierung

Die aktuelle Seite folgt `docs/entry-pages-editorial.md`: Name/Porträt, Fotostrom, schwebende Projektberichte, editierbare Unterstützungs-Widgets und großer Abschluss mit sechs einzeichnenden Pfeilen. Die Root-Module für Linie und Fotostrom werden unverändert wiederverwendet. Der Eintrag bleibt im Commit `published: false` und `noindex: true`; lokale Browseraufnahmen schalten nur die Arbeitskopie frei. Kein öffentlicher Preview-Bypass.

| Inhalt | Quelle / Grenze |
| --- | --- |
| Rosenfold | Echte lokale Ready-Aufnahme von db38debd,15.11.2026. Kein Nachweis einer fertigen Dateiübertragung. Netzwerk-/Geräteprüfungen sind der belegte nächste Schritt. |
| Projektwoche | Öffentlicher Eintrag `projects/projektwoche-nachhaltige-webentwicklung.md`: Workshop2025, eigene Webprojekte und eingesetzter Rust-Paketmanager. Keine laufende AG und kein erfundenes Release. |
| Robotik | Vom Nutzer freigegebene Onshape-Gesamtbaugruppe „Roboter gesamt“, Aufnahme15.11.2026, Sortiermodul/Mate-Markierungen ausgeblendet. Keine Geometriebearbeitung oder Veröffentlichung privater CAD-Dateien/URLs. Kein Fertigungs-/Fahrtestnachweis. |
| Robotikbedarf | Vom Nutzer bestätigter Eigenbau, austauschbare Halterung, gewünschter Test, CNC-/SLS- und Rechenbedarf. Teamunterstützung bleibt getrennt und verweist auf www.atheblues.de/donate; keine Aussage über unabhängig geprüften Empfänger/Steuerstatus. |
| Finanzielle Unterstützung | Keine geprüften Summen, Fortschrittsbalken, erfundenen Nullstände, Unterstützer oder Zahlungen. Die Widgets nennen konkrete Arten der Hilfe, keine Preise. |

Die zuvor verwendeten OnStage-Archivbilder und Laptop-Renderings bleiben im Assetbestand, dominieren aber nicht mehr den aktuellen Seitenentwurf. Rechte und Herkunft der vorhandenen Produktassets sind in ihren Metadaten dokumentiert; eine pauschale Creative-Commons-Lizenz fremder Robotikmedien wird nicht behauptet.

## Option Open Collective

Nur Plan, keine Integration oder API-Aufrufe. Zuerst Collective, rechtlichen Empfänger und Verwaltungskreis verifizieren. Ein späterer Laravel-Service ruft eine feste GraphQL-Abfrage serverseitig ab; keine Zugangsdaten im Browser oder CMS. Zeitlimit und kontrollierte Pagination begrenzen den Abruf. Cache normalisiert öffentliche Summen mit Datenstand. Fehler zeigen einen alten Stand mit sichtbarem Alter, fehlende Daten bleiben unbekannt statt null.

Bestätigte Transaktions-IDs werden eindeutig Projekt und Verwaltungskreis zugeordnet. Statussemantik, Währung, Gebühren, Erstattungen, Zeiträume und Umbuchungen sind vor Summenanzeige abzugleichen. Zusagen und Sachleistungen werden separat geführt, Schule/Team nicht mit persönlichen Projekten vermischt. Private Spendernamen, Nachrichten und Zahlungsdetails bleiben ausgeschlossen. Anonyme Vertragstests und fachliche Abstimmung gehen jeder Aktivierung voraus.
