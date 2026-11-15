---
id: 865da06e-c6c2-4358-af14-1ad29b8cf8d3
blueprint: support
title: Projekte unterstützen
template: support/index
published: false
noindex: true
heading: 'Was der nächste Versuch braucht.'
intro: 'Ich arbeite an offener Software und technischen Projekten. Unterstützung soll an einer konkreten Aufgabe ankommen. Hier zeige ich, woran ich arbeite und welche nächsten Schritte noch offen sind.'
byline: 'Jack Ruder · Software und Robotik'
software_need_heading: 'Ein noch unveröffentlichtes Werkzeug fertigbauen.'
software_owner_label: 'Persönliche Softwareprojekte'
rosenfold_need_heading: 'Unter realen Netzwerkbedingungen prüfen.'
rosenfold_owner_label: 'Unabhängiges Projekt · In Entwicklung'
robot_need_heading: 'Vom Antriebsentwurf zum Aufbau.'
robot_owner_label: 'AtheBlues · Schulteam'
financial_boundary: 'Persönliche Projekte, mögliche geschäftliche Vorhaben und das Schulteam bleiben finanziell getrennt. Diese Seite nimmt keine Zahlungen entgegen.'
contact_label: 'Unterstützung besprechen'
software_title: 'Offene Software endet nicht beim Code.'
software_intro: 'Entwicklung, Dokumentation und verlässlicher Betrieb kosten Zeit. Hosting und technische Infrastruktur gehören zu dieser Arbeit.'
software_project:
  - 4739fabf-5726-4d17-ad72-5a5f574e33cd
software_image: projects/prtop/prtop-live-dashboard.png
software_caption: 'prtop im Entwicklungsstand. Noch kein veröffentlichtes Release.'
software_need: 'prtop ist noch nicht veröffentlicht. Ich möchte das Werkzeug fertigbauen, obwohl ich es selbst derzeit nicht nutze. Dafür geht es zunächst um Entwicklungszeit und Wartung. Bei meinen anderen Softwareprojekten kommen Dokumentation und verlässlicher Betrieb hinzu. Eine Unterstützung bekommt einen konkreten Zweck, bevor ich dafür einen Betrag nenne.'
rosenfold_title: 'Rosenfold. Noch in Entwicklung.'
rosenfold_intro: 'Rosenfold soll Dateien und Text zwischen Geräten austauschen. Ich entwickle die Anwendung als unabhängiges Projekt. Der gezeigte Stand ist eine lokale Entwicklungsversion, kein fertiges Produkt.'
rosenfold_image: projects/rosenfold/rosenfold-laptop-render.png
rosenfold_caption: '3D-Rendering mit einem echten Screenshot der lokalen Rosenfold-Entwicklungsversion vom 15. November 2026.'
rosenfold_screen: projects/rosenfold/rosenfold-ready.png
rosenfold_screen_caption: 'Rosenfold im Zustand Ready, vor der Auswahl einer Datei. Lokale Aufnahme vom 15. November 2026.'
rosenfold_code_title: 'Fortschritt muss bei den echten Bytes bleiben.'
rosenfold_code_intro: 'Viele kleine Fortschrittsereignisse sollen die Oberfläche nicht überlasten. Dieser Ausschnitt bündelt Meldungen zeitlich. Der abschließende Wert kann separat ausgegeben werden. Das ist ein Einblick in die Implementierung, kein Nachweis einer fertigen Übertragung.'
rosenfold_code_source: 'packages/core/src/progress-throttle.ts, Ausschnitt aus db38debd, 15.11.2026. Die Projektlizenz ist noch nicht festgelegt.'
rosenfold_code: |
  report(value: T) {
    pendingValue = value
    const elapsed = now() - lastReportedAt
    if (elapsed >= intervalMs) {
      deliver()
    } else if (timer === null) {
      timer = schedule(deliver, intervalMs - elapsed)
    }
  },
rosenfold_need: 'Im Projektplan stehen noch reale Netzwerkprüfungen und Tests der Mobil-App auf Geräten. Dazu gehören auch Verbindungen über TURN-Server. Hier können Testinfrastruktur, Geräte und Entwicklungszeit helfen. Einen Kostenplan veröffentliche ich, sobald Bedarf und Verwaltung geklärt sind.'
robot_title: 'Am Antrieb weiterarbeiten.'
robot_project:
  - 8e9f1f56-1feb-46a8-9ae5-a79ec9444c81
robot_image: projects/atheblues/atheblues-workshop-2024.jpg
robot_caption: 'AtheBlues mit Bus und Bühnenbild vor der OnStage-Europameisterschaft 2024. Archivaufnahme, nicht der aktuelle Rescue-Line-Roboter.'
robot_archive_label: 'Werkstattarchiv 2024 · OnStage'
robot_document_help: 'Details aus dem OnStage-Poster von 2024 auswählen.'
robot_document_overview: 'Ganzes Poster'
robot_document_details:
  - label: Steuerung
    description: 'OnStage 2024: Tablet-App und Zustandsmodelle für die Kommunikation.'
    left: 80
    top: 7
    width: 18
    height: 17
    note: control
  - label: Antrieb
    description: 'OnStage 2024: Motoren und gedruckte Halterungen am Bus.'
    left: 45
    top: 66
    width: 11
    height: 9
    note: drive
  - label: Bühnenbericht
    description: 'Ganzes OnStage-Poster von 2024; die Erklärung bezieht sich auf den verlinkten Wettbewerbsbericht.'
    left: 0
    top: 0
    width: 100
    height: 100
    note: test
robot_archive_title: 'Was ein Umbau wirklich bedeutet.'
robot_archive_intro: 'Das technische Poster von AtheBlues dokumentiert die OnStage-Arbeit von 2024. Die Bilder zeigen echte Elektronik und mehrere Bauzustände des Busses. Der heutige Rescue-Line-Entwurf ist ein anderes Vorhaben.'
robot_document: projects/atheblues/atheblues-technical-poster-2024.png
robot_document_caption: 'Technisches Demo-Poster für das deutsche RoboCup-Junior-Finale in Kassel, 18.–21. April 2024. Historischer Entwicklungsnachweis.'
robot_control_title: 'Vom Sensor zur Zustandssteuerung'
robot_control_text: 'Die ersten Verbindungen über Farb- und Abstandssensoren waren fehleranfällig. Das Team plante die Kommunikation als Zustandsmodell und steuerte Bus und Stadt über eine Tablet-App und ESP32.'
robot_drive_title: 'Motoren austauschen. Halterungen fertigen.'
robot_drive_text: 'Die ersten Gleichstrommotoren waren für den schweren Bus zu schwach. Sie wurden ersetzt; Halterungen für die neuen Motoren entstanden im 3D-Druck. Das Poster zeigt die Umbauten und die Elektronik.'
robot_test_title: 'Auf der Bühne zählt der ganze Aufbau.'
robot_test_text: 'Der AG-Bericht aus Kassel beschreibt unterbrochene Bluetooth-Verbindungen während der Auftritte. Auch nach der Arbeit an Code und Mechanik musste das Zusammenspiel unter Wettbewerbsbedingungen funktionieren.'
robot_current_title: 'Rescue Line. Im Gesamtaufbau.'
robot_current_text: 'Grundplatte, Motorhalter, Räder und Elektronikdeck sind als Baugruppe zusammengeführt. Dieser CAD-Stand zeigt den Entwurf; Fertigung und Fahrtests sind damit noch nicht nachgewiesen.'
robot_need: 'Wir arbeiten am Antriebsentwurf. Material, Komponenten und Fertigung sollen seine Umsetzung ermöglichen. Computer Vision ist als weiterer Ansatz geplant. Unterstützung klären wir ausschließlich mit einer dafür zuständigen Schule oder Teamorganisation und verwalten sie getrennt von persönlichen Projekten.'
transparency_title: 'Erst der Zweck. Dann die Summe.'
transparency_intro: 'Konkrete Budgets und bestätigte Beiträge erscheinen hier erst mit nachvollziehbarer Grundlage. Für diese Seite sind noch keine geprüften Beträge veröffentlicht. Fehlende Angaben bedeuten nicht null Euro.'
contact_title: 'Etwas möglich machen.'
contact_intro: 'Hosting, Bauteile, Fertigung oder eine andere Form der Zusammenarbeit? Beschreib kurz, welches Projekt du unterstützen möchtest. Zweck, Rahmen und Verwaltung klären wir vor einer Zusage.'
robot_cad_label: 'CAD-Arbeitsstand'
robot_cad: projects/atheblues/atheblues-rescue-line-cad-20261115.png
robot_cad_caption: 'Gesamtbaugruppe „Roboter gesamt“ in Onshape; Sortiermodul für die Aufnahme ausgeblendet.'
robot_cad_laptop: projects/atheblues/atheblues-cad-laptop-render.png
robot_laptop_toggle: 'Die CAD-Aufnahme auf einem Laptop ansehen'
robot_laptop_caption: 'Programmatische 3D-Laptopansicht mit derselben CAD-Aufnahme. Kein fotografierter Arbeitsplatz.'
robot_archive_toggle: 'Was wir 2024 gelernt haben · OnStage-Archiv'
---
