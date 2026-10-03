{{-- Shown on a preview host when a handoff token cannot be redeemed. Stands
     alone on purpose: the preview host has no session and loads nothing from
     the portal. No automatic way back either -- that could loop. --}}
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Link abgelaufen</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 15vh auto; padding: 0 1rem; line-height: 1.5; color: #1e293b; }
        a { color: #0369a1; }
    </style>
</head>
<body>
    <h1>Dieser Link ist nicht mehr gültig</h1>
    <p>Bitte öffnen Sie die Vorschau erneut über das Kundenportal.</p>
    <p><a href="{{ route('portal.dashboard') }}">Zum Kundenportal</a></p>
</body>
</html>
