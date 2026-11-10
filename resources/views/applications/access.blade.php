<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Private Bewerbung</title>
    <style>body{font:1rem/1.6 system-ui,sans-serif;color:#202624;background:#fafbf9;margin:0}main{max-width:38rem;margin:12vh auto;padding:2rem}h1{font-size:1.8rem;line-height:1.2}a{color:#235c4c;text-underline-offset:.2em}</style>
    <script src="/application-access.js" defer></script>
</head>
<body data-application-id="{{ $id }}">
<main>
    <h1>Private Bewerbung</h1>
    <p role="status" data-application-access-status>Zum Öffnen wird ein gültiger Zugriffslink benötigt.</p>
    <a href="{{ url('/applications/'.$id.'/pdf') }}" @if(!$authorized) hidden @endif data-application-pdf>Vollständige Bewerbung als PDF öffnen</a>
</main>
</body>
</html>
