# ADR 0001 – Auslieferung geschützter Preview-Subdomains

- **Status:** Vorgeschlagen – Entscheidung bewusst offen
- **Datum:** 2026-08-31
- **Kontext:** Smallgate MVP

## Kontext

Kundenvorschauen sollen später unter Adressen wie
`holzmann.preview.clickit-digital.de` erreichbar sein. Vor der Auslieferung muss
immer geprüft werden, ob der anfragende Benutzer Zugriff auf das zugehörige
Projekt hat.

Feststehend ist bereits:

- Ein einmalig konfigurierter Wildcard-DNS-Eintrag `*.preview.clickit-digital.de`
  zeigt auf den Preview-Server. Smallgate erzeugt **keine** DNS-Einträge.
- Die angeforderte Subdomain wird anhand des `Host`-Headers einer Preview
  zugeordnet (`previews.hostname` ist global eindeutig).
- Preview-Ziele stammen ausschließlich aus einer administrativ gepflegten
  Allowlist (`config/previews.php`). Kunden bestimmen niemals Pfade oder
  Upstream-URLs.

**Diese ADR entscheidet noch nicht**, wie die Auslieferung technisch erfolgt.
Sie hält die Optionen und ihre Sicherheitsprobleme fest, damit die Entscheidung
mit den nötigen Informationen und nicht unter Zeitdruck getroffen wird.

## Das eigentliche Problem: Sessions über mehrere Subdomains

Alle drei Optionen scheitern oder gelingen an derselben Frage: **Woher weiß der
Preview-Host, wer der Anfragende ist?** Das Portal läuft auf
`portal.clickit-digital.de`, die Vorschau auf
`kunde.preview.clickit-digital.de`. Das ist eine andere Origin.

Die naheliegende Lösung – das Session-Cookie auf `.clickit-digital.de` zu
setzen – ist die gefährlichste:

- Das Cookie wird dann an **jede** Subdomain gesendet, auch an jede
  Preview-Subdomain. Preview-Inhalte sind aber halbfertige Kundenwebsites,
  teilweise mit fremdem JavaScript, Analytics-Snippets oder Redaktionssystemen.
- Ohne `HttpOnly` wäre das Cookie direkt auslesbar; mit `HttpOnly` kann fremdes
  JavaScript auf einer Preview-Subdomain immer noch beliebige authentifizierte
  Anfragen an das Portal stellen.
- Cookies unterscheiden nicht nach Port oder Schema und lassen sich von einer
  Subdomain aus für die gesamte Parent-Domain **überschreiben**
  (Cookie-Tossing). Eine kompromittierte Preview kann damit Sitzungen im Portal
  manipulieren.
- Subdomain-Isolation ist kein Origin-Boundary, auf das man Autorisierung
  stützen sollte.

**Konsequenz, die bereits jetzt gilt:** Das Session-Cookie von Smallgate wird
niemals auf die Parent-Domain gesetzt. `SESSION_DOMAIN` bleibt leer, das Cookie
gilt ausschließlich für den Portal-Host.

## Betrachtete Optionen

### Option A – Nginx `auth_request`

Der Preview-Vhost fragt vor jeder Auslieferung einen internen Endpunkt des
Portals (`/internal/preview-auth`), der 200 oder 401/403 zurückgibt.

*Vorteile:* wenig eigener Code; Nginx liefert statische Dateien effizient aus;
die Autorisierung bleibt vollständig in der Laravel-Anwendung.

*Sicherheitsprobleme:*
- `auth_request` reicht die Cookies der ursprünglichen Anfrage weiter – das
  funktioniert nur, wenn das Session-Cookie die Preview-Subdomain erreicht.
  Damit ist man beim Parent-Domain-Cookie und dessen oben beschriebenen
  Problemen.
- Der interne Auth-Endpunkt darf niemals von außen erreichbar sein.
- Ein `auth_request` pro Unterressource (Bilder, CSS, JS) erzeugt erhebliche
  Last, oder man cached die Entscheidung und riskiert, dass ein Entzug von
  Rechten verzögert wirkt.

### Option B – Eigenes Gateway in der Anwendung

Ein Laravel-Middleware-Stack nimmt Anfragen an `*.preview.…` entgegen, ordnet
den `Host`-Header einer Preview zu, prüft die Berechtigung und liefert die
Datei aus bzw. proxied an den Upstream.

*Vorteile:* eine einzige Stelle für die Autorisierung; identische Policies wie
im Portal; gut testbar.

*Sicherheitsprobleme:*
- Auch hier muss die Anfrage authentifiziert sein – dasselbe Cookie-Problem.
- Statische Auslieferung durch PHP ist deutlich langsamer und speicherhungriger.
- Ein selbst gebauter Proxy ist eine SSRF- und Request-Smuggling-Angriffsfläche:
  Header-Weitergabe, Redirect-Folgen, `Host`-Header-Behandlung und Timeouts
  müssen alle korrekt sein.
- Path Traversal muss bei jeder Dateiauslieferung erneut abgewehrt werden.

### Option C – Kurzlebige Übergabe-Tokens

Das Portal erzeugt beim Klick auf „Vorschau öffnen“ ein signiertes, kurzlebiges
Token und leitet auf
`https://kunde.preview.…/__enter?token=…` weiter. Der Preview-Host tauscht das
Token gegen ein **eigenes**, auf genau diese Subdomain begrenztes Cookie.

*Vorteile:* Das Portal-Session-Cookie verlässt niemals den Portal-Host. Jede
Preview-Subdomain bekommt ein eigenes, isoliertes Cookie – eine kompromittierte
Preview gefährdet weder das Portal noch andere Previews. Das ist die einzige
Option, die das Kernproblem tatsächlich löst statt es zu verschieben.

*Sicherheitsprobleme:*
- Tokens in URLs landen in Server-Logs, `Referer`-Headern und im Browserverlauf.
  Sie müssen daher sehr kurz gültig (Sekunden), einmalig verwendbar und sofort
  gegen ein Cookie eingetauscht werden, gefolgt von einem Redirect ohne Token.
- Der Token-Tausch braucht eigenes Rate-Limiting.
- Es entsteht eine zweite Sitzungsart mit eigener Lebensdauer; Entzug von
  Rechten im Portal muss auf die Preview-Sitzung durchschlagen (kurze Laufzeit
  plus serverseitige Prüfung).
- Mehr bewegliche Teile als bei A und B.

## Entscheidung

**Für das MVP: keine dieser Optionen.** Vorschauen werden als geschützter
Eintrag bzw. geschützter Link innerhalb des Portals dargestellt. Es gibt keinen
Proxy und keine Subdomain-Auslieferung.

Umgesetzt ist stattdessen:

- Das Interface `App\Contracts\PreviewProvisioner` markiert die Systemgrenze.
- Die einzige Implementierung `NullPreviewProvisioner` verändert **keine**
  Serverdateien, führt **keine** Shell-Kommandos aus und nutzt **kein** `sudo`.
  Sie protokolliert die Absicht und validiert das Ziel erneut.
- `App\Services\Previews\PreviewTargetGuard` setzt die Allowlists bereits
  vollständig durch – inklusive Path-Traversal-Normalisierung und Abweisung von
  IP-Literalen, Zugangsdaten, abweichenden Ports und nicht freigegebenen Hosts.
  Formularvalidierung und Provisioner nutzen denselben Code, können also nicht
  auseinanderlaufen.
- `App\Rules\PreviewHostname` erzwingt genau eine Subdomain unterhalb der
  konfigurierten Basisdomain.

## Konsequenzen

- Die Vorschau-Auslieferung ist eine eigene Projektphase mit eigenem
  Sicherheitsreview.
- Bis dahin gibt es keinen Weg, über Smallgate Serverzustand zu verändern.
- `SESSION_DOMAIN` bleibt leer. Wird diese Entscheidung später revidiert, ist
  das ein sicherheitsrelevanter Eingriff und keine Konfigurationskleinigkeit.
- Die Datenbank hält Hostname und Ziel bereits vor, sodass die spätere Phase
  keine Migration der Bestandsdaten benötigt.

## Nächste Schritte für die Provisioning-Phase

1. Entscheidung zwischen A, B und C – aus heutiger Sicht ist **Option C** der
   aussichtsreichste Kandidat, weil nur sie die Cookie-Isolation löst; die
   Entscheidung wird aber bewusst nicht hier vorweggenommen.
2. TLS für `*.preview.clickit-digital.de` klären (Wildcard-Zertifikat via
   DNS-01-Challenge).
3. Umgang mit fremdem JavaScript in Vorschauinhalten festlegen (CSP, `sandbox`).
4. Verhalten bei Rechteentzug während laufender Preview-Sitzung definieren.
5. Rate-Limiting und Logging für den Preview-Host festlegen – ohne Tokens oder
   personenbezogene Daten zu protokollieren.
