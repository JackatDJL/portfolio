# Kontaktformular

`/kontakt` verbindet Projektanfragen, Sponsoring, Politik und allgemeinen Kontakt in einem Formular. Einleitung und Datenschutzhinweis liegen in der Statamic-Seite `content/collections/pages/kontakt.md` mit dem Blueprint `contact`.

Der Versand ist standardmäßig aus. Vor Aktivierung müssen Empfänger, Transport, Absender, Datenschutzinformationen und Aufbewahrung geprüft sein. Anschließend ausschließlich serverseitig konfigurieren:

```dotenv
CONTACT_MAIL_ENABLED=true
CONTACT_MAIL_RECIPIENT=verified-recipient@example.org
CONTACT_MAILER=smtp
```

Der benannte Laravel-Mailer muss als echter Transport konfiguriert sein. `log`, `array` und zusammengesetzte Fallback-Transporte werden absichtlich nicht akzeptiert. Der Absender stammt aus `mail.from`, die Antwortadresse aus der validierten Formulareingabe. Thema und Betreff stammen aus einer festen Liste. Keine automatische Kopie, keine Anhänge und kein Zahlungsfluss.

CSRF bleibt aktiv. POST ist auf fünf Versuche pro Minute begrenzt. Ein verschlüsselter, an die Formularsession gebundener Zeitnachweis und ein Honeypot ergänzen die Prüfung. Ein Formular muss mindestens zwei Sekunden und höchstens zwei Stunden alt sein. Ungültige Eingaben werden direkt im Response gerendert, nicht als Old Input in der Session gespeichert. Nur die erfolgreiche Zustellbestätigung wird als boolescher Wert für einen Redirect geflasht. Mailfehler werden mit einer allgemeinen Fehlermeldung beantwortet; Transportexceptions werden nicht mit Nutzerdaten geloggt. Die Route antwortet mit `private, no-store` und ohne Referrerweitergabe.

Betriebsgrenzen: Webserver, APM und Mailprovider können eigene Protokollierung besitzen. Vor Aktivierung ist deren Konfiguration auf Requestbody-/Adressprotokollierung und Aufbewahrung zu prüfen. Ein fehlender oder ausgefallener Transport darf keine erfolgreiche Zustellung vortäuschen. Die Tests verwenden Mail-Fakes, keine echten Nachrichten.

Prüfen mit `php artisan test --filter=ContactPageTest` und `bun run build`. Visuelle und Accessibility-Abnahme steht separat aus.
