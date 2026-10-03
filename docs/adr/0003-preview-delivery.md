# ADR 0003 – Auslieferung statischer Vorschauen über Übergabe-Tokens

- **Status:** Angenommen
- **Datum:** 2026-10-03
- **Kontext:** Entscheidet ADR 0001 für Vorschauen vom Typ `static_directory`

## Kontext

ADR 0001 hat drei Wege beschrieben, geschützte Vorschauen auszuliefern, und die
Entscheidung offen gelassen. Seitdem gibt es Projektordner (ADR 0002), und das
Ziel ist klarer: Ein Entwurf soll **nur über Smallgate** erreichbar sein. Wer
die Adresse kennt, aber nicht über das Portal kommt, sieht nichts.

Eine pfadbasierte Auslieferung auf dem Portal-Host (`portal…/inhalt/…`) wurde
geprüft und verworfen. Ohne Sandbox liefe das JavaScript des Entwurfs mit den
Rechten des Portals. Mit `Content-Security-Policy: sandbox` ohne
`allow-same-origin` bekommt das Dokument eine opake Origin. Ein Test mit dem
Chromium aus dem Worker hat gezeigt, was dann passiert: Nur das HTML-Dokument
erhält das Session-Cookie. CSS, JavaScript, Bilder, Fonts und `fetch` laufen
ohne Cookie und damit in die Anmeldung. `localStorage` wirft einen
`SecurityError`. Ein Entwurf braucht also eine eigene Origin.

## Entscheidung

**Option C aus ADR 0001: kurzlebige Übergabe-Tokens, ausgeliefert von
Smallgate selbst auf einem eigenen Preview-Host.**

### Eigene Domain für Vorschauen

Vorschauen liegen unter einer **eigenen registrierbaren Domain**:
`*.clickit-preview.de`, nicht unter `*.preview.clickit-digital.de`. Die Domain
ist registriert.

Der Grund: `portal.clickit-digital.de` und `kunde.preview.clickit-digital.de`
sind für den Browser „same-site“. Das hat zwei Folgen:

- `SameSite=Lax` schützt dann nicht. JavaScript im Entwurf kann Anfragen an das
  Portal stellen, und der Browser schickt das Portal-Cookie mit. Lesen kann es
  die Antworten nicht, aber Schutz vor ungewollten Aktionen bietet dann allein
  der CSRF-Token.
- Ein Entwurf kann Cookies für `.clickit-digital.de` setzen (Cookie-Tossing),
  etwa ein fremdes Session-Cookie für das Portal.

Mit einer eigenen Domain sind Portal und Vorschau „cross-site“. Beides entfällt,
ohne dass Smallgate etwas dafür tun muss. Große Anbieter trennen fremde Inhalte
aus demselben Grund auf eigene Domains.

Verworfen wurde eine Subdomain unter der Portal-Domain mit einem
`__Host-`-Session-Cookie. Das verhindert das Überschreiben, lässt aber den
CSRF-Token als einzige Verteidigung gegen Anfragen aus Entwürfen übrig.

`previews.base_domain` nimmt die gewählte Domain auf. `PreviewHostname`
erzwingt weiterhin genau eine Ebene darunter.

### Ablauf

1. **Portal.** Der Kunde klickt „Vorschau öffnen“ (`portal.previews.show`).
   Smallgate löst die Vorschau wie bisher über `resolvePreview()` auf, prüft
   die neue Fähigkeit `PreviewPolicy::open` und erzeugt ein Übergabe-Token:
   32 Zufallsbytes, gültig **60 Sekunden**, einmal verwendbar, gebunden an
   Benutzer und Vorschau. In der Datenbank liegt nur der SHA-256-Hash.
   Antwort: Weiterleitung auf
   `https://<hostname>/__smallgate/zugang?token=…`.
2. **Tausch.** Der Preview-Host löst das Token ein, mit einem einzigen
   atomaren `UPDATE … SET used_at = now() WHERE token_hash = ? AND used_at IS
   NULL AND expires_at > now() RETURNING …`. Zwei gleichzeitige Aufrufe können
   also nicht beide gelingen. Danach prüft er, dass der Hostname der Anfrage
   zur Vorschau des Tokens passt. Dann legt er eine Preview-Sitzung an und
   setzt das Cookie `__Host-smallgate-preview` (`Secure`, `HttpOnly`,
   `SameSite=Lax`, `Path=/`, ohne `Domain`). Antwort: `303` auf `/`, ohne
   Token, mit `Referrer-Policy: no-referrer`. Der Tausch hat ein eigenes
   Rate-Limit pro IP-Adresse. Scheitert er, zeigt der Preview-Host eine
   Fehlerseite und leitet **nicht** weiter. So kann keine Schleife entstehen.
3. **Jede weitere Anfrage.** Der Preview-Host liest das Cookie und sucht die
   Sitzung über den Hash. Die Vorschau sucht er über
   `Preview::visibleTo($user)` und den Hostnamen der Anfrage. **Bei jeder
   Anfrage** prüft er erneut: Sitzung gültig und nicht abgelaufen, Sitzung
   gehört zu dieser Vorschau, `PreviewPolicy::open` erlaubt. Die Policy deckt
   über `ProjectPolicy::view` auch gesperrte Benutzer und deaktivierte Kunden
   ab. Erst dann löst er den Pfad auf und liefert die Datei aus.

**Ohne gültige Sitzung** leitet der Preview-Host auf eine Portal-Route weiter,
die die Vorschau über ihren Hostnamen auflöst (`/portal/vorschauen/oeffnen/<hostname>`).
Ist der Benutzer im Portal angemeldet, stellt das Portal ein neues Token aus,
und er landet ohne Zwischenschritt wieder in der Vorschau. Andernfalls landet
er auf der Anmeldung. So funktionieren auch Lesezeichen und Links aus E-Mails.
Diese Weiterleitung ist für jeden Hostnamen unter der Preview-Domain
**identisch**, egal ob es dort eine Vorschau gibt oder nicht. Ob eine Vorschau
existiert, entscheidet erst das Portal, und zwar mit demselben 404 wie überall.

### Cookie

- **`__Host-`-Präfix.** Alle Vorschauen teilen sich `clickit-preview.de`. Ohne
  Präfix könnte ein Entwurf ein Cookie mit `Domain=.clickit-preview.de` setzen
  und damit das Cookie einer anderen Vorschau überlagern (Cookie-Tossing). Ein
  `__Host-`-Cookie nimmt der Browser nur ohne `Domain` an, und der Server liest
  nur diesen Namen.
- **`SameSite=Lax`, nicht `Strict`.** Geprüft mit dem Chromium aus dem Worker:
  - Mit `Strict` kommt das Cookie nach dem Klick im Portal und dem `303` auf
    `/` nicht mit, auch nicht beim Neuladen. Die Navigation hat im Portal
    begonnen und gilt deshalb als „cross-site“. Der Tausch würde sich im Kreis
    drehen.
  - Zwischen Vorschauen schützt `Strict` nicht. Alle `*.clickit-preview.de`
    sind untereinander „same-site“, also geht das Cookie mit, wenn Vorschau a
    eine Datei von Vorschau b einbindet.
  - Gegen fremde Websites reicht `Lax`. Es lässt nur `GET`-Navigationen auf
    oberster Ebene durch, und der Preview-Host hat keine Aktionen, die sich
    damit auslösen ließen.
- **Abschirmung zwischen Vorschauen** leistet
  `Cross-Origin-Resource-Policy: same-origin` auf jeder Antwort. Ein Entwurf
  kann dann keine Dateien einer anderen Vorschau einbinden, etwa deren
  JavaScript per `<script src>`. Das betrifft vor allem Admins, die Sitzungen
  für viele Vorschauen gleichzeitig haben.

### Berechtigung

`PreviewPolicy::open` ist eine eigene Fähigkeit, kein Umweg über `view`:

- Kunden: `view` erlaubt **und** Status `isVisitable()`.
- Admins: jede Vorschau, auch vor der Freigabe. Damit funktioniert „Öffnen“
  zur Kontrolle im Admin-Bereich über denselben Weg.
- Zieltyp `static_directory`, aktiviert in `previews.target_types`, Ziel von
  `PreviewTargetGuard` erneut bestätigt.

Weil jede Anfrage neu prüft, wirkt ein Entzug sofort: Vorschau deaktiviert,
Benutzer gesperrt, Projekt einem anderen Kunden zugeordnet. Beim Abmelden im
Portal löscht Smallgate zusätzlich alle Preview-Sitzungen des Benutzers.

### Laufzeiten

- Übergabe-Token: 60 Sekunden, einmalig.
- Preview-Sitzung: feste Laufzeit von **8 Stunden**, keine Verlängerung. Ein
  neuer Klick im Portal erzeugt eine neue Sitzung.
- Abgelaufene Tokens und Sitzungen räumt der Tausch selbst mit auf. Ein
  Scheduler ist dafür nicht nötig.

### Dateiauslieferung

Eine eigene Klasse `App\Services\Previews\PreviewFileResolver` ist die einzige
Stelle, die einen Anfragepfad in eine Datei übersetzt:

- Den Pfad einmal dekodieren. Null-Bytes, Backslashes, `..`-Segmente und
  Segmente, die mit `.` beginnen (`.git`, `.env`), abweisen.
- Mit `realpath()` auflösen. Die Datei muss unterhalb von `realpath(target)`
  liegen und eine reguläre Datei sein. Symlinks nach außen fallen dabei weg.
- Ein Verzeichnis liefert seine `index.html`. Fehlt der abschließende Slash,
  folgt eine Weiterleitung, damit relative Links stimmen.
- Der MIME-Typ kommt aus einer festen Endungsliste, nie aus dem Dateiinhalt.
  Unbekannte Endungen gehen als `application/octet-stream` mit
  `Content-Disposition: attachment` hinaus.
- Der Präfix `/__smallgate/` ist reserviert und wird nie aus dem Ordner bedient.

Die Antwort ist eine `BinaryFileResponse` mit Range-Unterstützung und diesen
Headern:

- `X-Content-Type-Options: nosniff`
- `Cross-Origin-Resource-Policy: same-origin`
- `Cache-Control: private, no-cache` mit `Last-Modified` und einem `ETag` aus
  Größe und Änderungszeit, nicht aus einem Hash des Inhalts, sonst müsste jede
  Nachfrage die ganze Datei lesen. Der Browser darf zwischenspeichern, fragt
  aber jedes Mal nach. Jede Nachfrage läuft durch die Prüfung und bekommt bei
  unverändertem Stand nur ein `304`.
- `X-Robots-Tag: noindex`
- `Content-Security-Policy: frame-ancestors 'none'`

Darüber hinaus schränkt Smallgate den Entwurf **nicht** ein. Er läuft wie eine
normale Website, mit Skripten, Modulen, Fonts, `localStorage` und
root-absoluten Pfaden. Die Isolation leistet die eigene Origin.

### Trennung von Portal und Preview-Host

- **Routen.** Die Preview-Routen sind eine eigene Gruppe mit `Route::domain()`
  und eigenem Middleware-Stack: keine Portal-Session, kein CSRF, keine
  Portal-Cookies. Sie werden **vor** den Portal-Routen registriert und decken
  auf dem Preview-Host jeden Pfad und jede Methode ab (`GET`/`HEAD` liefern aus,
  alles andere bekommt `405`). Eine Anfrage an einen Preview-Host erreicht so
  nie eine Portal-Route. Auf dem Preview-Host gibt es keine Anmeldeseite, die
  ein Entwurf nachahmen oder über die er an ein Portal-Cookie kommen könnte.
  Ein Test prüft das auch mit dem kompilierten Matcher, den `route:cache` in
  Produktion verwendet.

  Die Portal-Routen bekommen bewusst **kein** `Route::domain()`. Für Routen mit
  Domain baut Laravel URLs aus der Route-Domain und dem **Port des aktuellen
  Requests** und übergeht `APP_URL`. Ein Host-Header wie `portal…:1234` landete
  dann mit diesem Port in Reset- und Einladungslinks.
- **Nginx.** Ein zweiter `server`-Block nimmt nur Hostnamen mit genau einer
  Ebene unter der Preview-Domain an und reicht alles an `index.php` weiter.
  Aus `public/` wird dort nichts direkt ausgeliefert. Erlaubt sind nur `GET`
  und `HEAD`. Die Log-Map entfernt `token=` aus dem Zugriffslog, so wie heute
  schon bei Einladungs- und Reset-Links.
- **`TRUSTED_HOSTS`** nimmt das Muster der Preview-Domain auf.

### Datenmodell

Zwei neue Tabellen mit ULIDs, Fremdschlüsseln mit `ON DELETE CASCADE` und
`UNIQUE` auf dem Hash:

- `preview_handoffs`: `preview_id`, `user_id`, `token_hash`, `expires_at`,
  `used_at`.
- `preview_sessions`: `preview_id`, `user_id`, `token_hash`, `expires_at`.

Ein `CHECK` erzwingt `expires_at > created_at`. Keine dieser Spalten ist
`$fillable`. `previews.hostname` bleibt für statische Vorschauen Pflicht. Die
bestehenden Constraints passen dazu.

## Betrieb

- **DNS.** Ein Wildcard-Eintrag `*.clickit-preview.de` zeigt auf den Server.
  Smallgate legt weiterhin keine DNS-Einträge an.
- **TLS.** Ein Wildcard-Zertifikat für `*.clickit-preview.de` am Reverse-Proxy,
  per DNS-01-Challenge. Der Proxy reicht den `Host`-Header unverändert an den
  `web`-Container weiter.
- **Volumes.** Die Projektordner müssen im `app`-Container lesbar sein (nur
  lesend). Der `worker` braucht sie weiterhin schreibend für ADR 0002.
- **Produktion.** `compose.prod.yaml` bietet `static_directory` wieder an.
- **Entwicklung.** Der Mock-Host ohne Zugriffsschutz entfällt. Der
  `preview`-Container (`docker/nginx/preview.conf.template`) leitet
  `*.preview.localhost` an die Anwendung weiter, mit demselben Ablauf wie in
  Produktion.

## Nicht entschieden

- **Upstream-Vorschauen** (`upstream_url`) bleiben, wie sie sind: Das Portal
  leitet auf die fremde URL weiter, und Smallgate schützt sie nicht.
- **Hochladen von Entwürfen** über Smallgate ist nicht Teil dieser ADR. Es
  setzt auf diese Auslieferung auf und bekommt eine eigene ADR. Absehbar sind:
  ZIP-Upload nur für Admins, Entpacken im Queue-Worker, Abwehr von Zip-Slip,
  Symlinks und Zip-Bomben, jede Version in einen neuen Unterordner mit
  anschließender Umschaltung.
- **Einbettung im Portal** (iframe mit Kopfzeile und „Änderung melden“) ist
  später möglich. Vorerst öffnet die Vorschau als eigene Seite.

## Konsequenzen

- ADR 0001 ist für statische Vorschauen entschieden.
- Smallgate hat eine zweite Sitzungsart. Sie ist an genau eine Vorschau und
  genau einen Host gebunden und wird bei jeder Anfrage gegen die Policy geprüft.
- Jede Datei eines Entwurfs geht durch PHP und die Datenbank. Bei der Größe
  einer Agentur ist das unproblematisch. Wird es eng, übernimmt Nginx per
  `X-Accel-Redirect` das Ausliefern, nachdem Laravel geprüft und den Pfad
  aufgelöst hat.
- Der Betrieb braucht für `clickit-preview.de` Wildcard-DNS und ein
  Wildcard-Zertifikat.
- `CLAUDE.md` bekommt neue Sicherheitsregeln:
  - Kein Portal-Code auf dem Preview-Host.
  - Tokens nur gehasht speichern und nie loggen.
  - Dateien nur über `PreviewFileResolver`.
  - Änderungen am Tausch, an der Sitzungsprüfung oder am Resolver brauchen
    begleitende Tests.

## Umsetzung

1. Migrationen, Modelle, `PreviewPolicy::open`. *(erledigt)*
2. `PreviewFileResolver` mit Unit-Tests: `..`, `%2e%2e`, `%00`, Backslash,
   Dotfiles, Symlink nach außen, Verzeichnis ohne `index.html`, MIME-Liste.
   *(erledigt)*
3. Preview-Routen, Middleware und Tausch, mit Feature-Tests: *(erledigt)*
   - Token zweimal eingelöst, abgelaufen oder auf einem anderen Host.
   - Fremder Kunde, deaktivierte Vorschau, gesperrter Benutzer, Abmeldung.
   - Ohne Sitzung dieselbe Weiterleitung für unbekannte und vorhandene Hosts.
   - Header auf jeder Antwort, `304` bei unverändertem Stand.
   - Portal-Routen auf dem Preview-Host liefern 404.
4. Im Portal: `showPreview()` erzeugt das Token, statt direkt
   weiterzuleiten. Dazu kommt die Route `/portal/vorschauen/oeffnen/<hostname>`.
   Der Admin-Link führt weiter direkt auf den Preview-Host und kommt über diese
   Route mit Token zurück. *(erledigt, zusammen mit Schritt 3)*
5. Nginx (Entwicklung und Produktion), `compose.prod.yaml`, `TRUSTED_HOSTS`,
   README unter „Betrieb“. *(erledigt)*
6. `CLAUDE.md` anpassen. *(erledigt)*
