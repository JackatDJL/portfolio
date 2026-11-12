<?php

return [
    // Enable only after the account and guardian verification requirements are met.
    'github_sponsors_url' => env('SUPPORT_GITHUB_SPONSORS_URL'),
    'goals' => [
        [
            'key' => 'personal',
            'category' => 'Persönliche Open-Source- und Softwareprojekte',
            'title' => 'Offene Software verlässlich betreiben',
            'summary' => 'Ich entwickle quelloffene Werkzeuge und Anwendungen wie DJL UI, SongRaten und persönliche Softwareprojekte. Unterstützung hilft bei Hosting, Wartung und zugänglicher Dokumentation.',
            'budget' => 360,
            'needs' => ['Hosting und Domains: 120 €', 'Elektronik und Testkomponenten: 90 €', 'Prototyping und Kleinserien: 150 €'],
            'received' => 0,
        ],
        [
            'key' => 'company',
            'category' => 'Rosenfold und künftige unabhängige Vorhaben',
            'title' => 'Infrastruktur und Prototypen',
            'summary' => 'Für unabhängige Vorhaben wie Rosenfold entstehen Kosten für zuverlässige Infrastruktur, Hardwareversuche und die Fertigung funktionsfähiger Prototypen.',
            'budget' => 900,
            'needs' => ['Hosting und Infrastruktur: 240 €', 'Komponenten und Elektronik: 260 €', 'Prototyping und Fertigung: 400 €'],
            'received' => 0,
        ],
        [
            'key' => 'school',
            'category' => 'AtheBlues / Schulrobotik',
            'title' => 'Robotikmaterial für AtheBlues',
            'summary' => 'Elektronik, Ersatzteile und Fertigung helfen beim Aufbau und Testen des Rescue-Line-Roboters. Diese Mittel werden getrennt von persönlichen Projekten verwaltet.',
            'budget' => 700,
            'needs' => ['Sensoren und Elektronik: 220 €', 'Mechanik und Ersatzteile: 180 €', 'Prototyping und Fertigung: 300 €'],
            'received' => 0,
            'separate' => true,
        ],
    ],
];
