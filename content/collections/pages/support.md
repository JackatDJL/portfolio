---
id: 865da06e-c6c2-4358-af14-1ad29b8cf8d3
blueprint: support
title: Projekte unterstützen
template: support/index
published: false
noindex: true
heading: 'Ich bin Jack. Ich entwickle eigene Software.'
intro: 'Ich bin Jack Ruder aus Stade. Ich entwickle Webanwendungen und offene Werkzeuge. Mit Rosenfold arbeite ich daran, Dateien und Text zwischen Geräten auszutauschen. Hier zeige ich, was schon entstanden ist, was noch fehlt und wo Unterstützung meine Arbeit weiterbringen kann.'
byline: 'Jack Ruder · Software und Robotik'
software_need_heading: 'Ein noch unveröffentlichtes Werkzeug fertigbauen.'
software_owner_label: 'Persönliche Softwareprojekte'
rosenfold_need_heading: 'Unter realen Netzwerkbedingungen prüfen.'
rosenfold_owner_label: 'Unabhängiges Projekt · In Entwicklung'
robot_need_heading: 'Die Motorhalterung muss auf den Prüfstand.'
robot_owner_label: 'AtheBlues · Schulteam'
financial_boundary: 'Persönliche Projekte, mögliche geschäftliche Vorhaben und das Schulteam bleiben finanziell getrennt. Diese Seite nimmt keine Zahlungen entgegen.'
contact_label: 'Unterstützung besprechen'
software_title: 'Auch kleine Werkzeuge brauchen Pflege.'
software_intro: 'Neben Rosenfold entwickle ich offene Werkzeuge wie prtop: eine Terminaloberfläche für Pull Requests aus verschiedenen Git-Plattformen. Die Oberfläche ist gebaut; ein veröffentlichtes Release gibt es noch nicht.'
software_project:
  - 4739fabf-5726-4d17-ad72-5a5f574e33cd
software_image: projects/prtop/prtop-live-dashboard.png
software_caption: 'prtop im Entwicklungsstand. Noch kein veröffentlichtes Release.'
software_need: 'Aus einem Entwicklungsstand wird erst durch Wartung, Dokumentation und verlässlichen Betrieb ein nutzbares Werkzeug. Dafür brauche ich Zeit und Infrastruktur. Welche Aufgabe eine Unterstützung ermöglicht, klären wir am konkreten Projekt.'
rosenfold_title: 'Rosenfold. Noch in Entwicklung.'
rosenfold_intro: 'Rosenfold soll Dateien und Text zwischen Geräten austauschen. Oberfläche und Übertragungslogik sind in Entwicklung. Der echte Screenshot zeigt die lokale Anwendung vor der Dateiauswahl. Die nächsten Hürden liegen bei realen Netzwerkbedingungen und der Mobil-App auf Geräten.'
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
robot_title: 'Robotik im Team. Von Grund auf gebaut.'
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
robot_current_text: 'Bei Rescue Line soll ein Roboter selbstständig einer schwarzen Linie folgen und Hindernisse bewältigen. Zur Rettungsaufgabe gehören auch das Aufnehmen und Sortieren simulierter Opfer. Wir entwickeln und bauen unseren Roboter von Grund auf selbst. Mechanik, Elektronik und Software gehören zusammen; wir programmieren keinen fertigen Bausatz. Die schnell austauschbare Motorhalterung ist entworfen. An Kugelsorter, Kamera und Ballfänger arbeiten wir noch. Dieser CAD-Stand zeigt den Entwurf; Fertigung und Fahrtests sind damit noch nicht nachgewiesen.'
robot_need: 'Als Nächstes wollen wir die Motorhalterungen fertigen und am realen Aufbau testen. Noch fehlt uns ein physischer Roboter, an dem wir die Software entwickeln können. Dafür suchen wir Unterstützung bei Fertigung und Rechenhardware.'
robot_requirements:
  - title: 'Grundplatte und Motorhalterungen fertigen'
    text: 'Die Grundplatte soll CNC-gefertigt werden. Für die Motorhalterungen suchen wir einen SLS-Druckpartner oder die Finanzierung der Fertigung. Unsere FDM-Halterungen sind für den geplanten Einsatz nicht stabil genug. Der Test soll zeigen, wie sich die austauschbare Konstruktion im Aufbau bewährt.'
  - title: 'Bildverarbeitung auf dem Roboter ermöglichen'
    text: 'Für uns steht in der AG derzeit nur ein Raspberry Pi 4 mit 4 GB RAM zur Verfügung. Für unsere geplante Bildverarbeitung reicht das nicht aus. Wir brauchen passende Rechenhardware und einen KI-Beschleuniger für das Visionmodell.'
  - title: 'Modelle trainieren und Software entwickeln'
    text: 'Das Visionmodell braucht Rechenleistung für das Training. Wir nutzen außerdem KI-gestützte Entwicklungswerkzeuge, für deren Betrieb Kosten entstehen. Sachleistungen und Zugang zu geeigneter Hardware können ebenso helfen wie finanzielle Unterstützung.'
robot_team_boundary: 'AtheBlues ist ein eigenständiges Team. Sponsoring und Teamfinanzen laufen getrennt von meinen persönlichen Softwareprojekten. Wer die Robotik unterstützen möchte, findet die zuständige Anlaufstelle auf der Teamwebsite.'
transparency_title: 'Erst der Zweck. Dann die Summe.'
transparency_intro: 'Konkrete Budgets und bestätigte Beiträge erscheinen hier erst mit nachvollziehbarer Grundlage. Für diese Seite sind noch keine geprüften Beträge veröffentlicht. Fehlende Angaben bedeuten nicht null Euro.'
contact_title: 'Meine Entwicklung unterstützen.'
contact_intro: 'Du möchtest Rosenfold oder eines meiner Softwareprojekte weiterbringen? Schreib mir, wobei du helfen kannst: mit Infrastruktur, Testgeräten oder finanzieller Unterstützung für einen konkreten Entwicklungsschritt.'
robot_cad_label: 'CAD-Arbeitsstand'
robot_cad: projects/atheblues/atheblues-rescue-line-cad-20261115.png
robot_cad_caption: 'Gesamtbaugruppe „Roboter gesamt“ in Onshape; Sortiermodul für die Aufnahme ausgeblendet.'
robot_cad_laptop: projects/atheblues/atheblues-cad-laptop-render.png
robot_laptop_toggle: 'Die CAD-Aufnahme auf einem Laptop ansehen'
robot_laptop_caption: 'Programmatische 3D-Laptopansicht mit derselben CAD-Aufnahme. Kein fotografierter Arbeitsplatz.'
robot_archive_toggle: 'Was wir 2024 gelernt haben · OnStage-Archiv'
opening_image: photos/jack-20260614.jpg
opening_caption: 'Jack Ruder.'
robot_brief: 'Mit einem Teamkollegen entwickle ich einen Rescue-Line-Roboter von Grund auf. Die schnell austauschbare Motorhalterung ist entworfen; als Nächstes wollen wir sie fertigen und am realen Aufbau testen. Dafür braucht das Team CNC- und SLS-Fertigung sowie passende Rechenhardware. Kugelsorter, Kamera und Ballfänger sind noch in Arbeit.'
robot_details_label: 'Entwicklungsstand und konkrete Team-Bedarfe'
robot_team_url: 'https://www.atheblues.de/donate'
robot_team_label: 'AtheBlues als Team unterstützen'
---
