# CLAUDE.md – Smallgate

Kleines, selbst gehostetes Kundenportal für Clickit Digital. Laravel-Monolith.

## Was das Projekt ausdrücklich NICHT ist

Kein CRM, kein Rechnungsprogramm, kein Dokumentenarchiv, kein Chat, keine
Benachrichtigungszentrale. Rechnungen, Dokumente und Kommunikation laufen per
E-Mail. Features außerhalb des MVP nicht hinzufügen.

## Stack

- Laravel 13, PHP 8.4, PostgreSQL 17
- Blade + Tailwind CSS 4, minimal JavaScript (nur Schließen des Konto-Menüs)
- Pest 5 / PHPUnit 13, Tests gegen echtes PostgreSQL (nicht SQLite)
- Docker Compose, Mailpit. Produktion: `compose.prod.yaml` (Dockerfile-Stages
  `prod` und `web`), nie zusammen mit `compose.yaml`
- Queue: Datenbank-Treiber, Worker-Service `worker` in Compose
- Vorschaubilder: `playwright-core` + Alpine-Chromium, ausschließlich im
  Queue-Worker (`scripts/preview-screenshot.mjs`), nie im Browser des Kunden

Keine REST-API, kein React/Angular/Vue, kein Redis, keine Microservices, keine
externen Dienste, keine CDNs.

## Alles läuft im Container

Der Host hat PHP 8.3 – das Projekt braucht 8.4. **Niemals** `php`, `composer`
oder `artisan` direkt auf dem Host aufrufen. Immer `./sg`:

```bash
./sg artisan <befehl>
./sg composer <befehl>
./sg test
./sg pint
./sg npm <befehl>
```

## Architekturregeln

- Kein Repository-Pattern über Eloquent.
- Keine Interfaces außer an echten Systemgrenzen – aktuell existiert genau
  eines: `App\Contracts\PreviewProvisioner`.
- Einfacher, lesbarer Laravel-Code vor abstrakten Konstruktionen.
- Datenbank-Constraints **zusätzlich** zur Anwendungsvalidierung, nicht statt.
- ULIDs als Primärschlüssel für alles, was in URLs auftaucht.

## Sicherheitsregeln – nicht verhandelbar

- Niemals eigene Kryptografie oder eigenes Passwort-Hashing. Argon2id über
  Laravels `Hash`-Fassade.
- `role`, `customer_id`, `is_active`, `project_id`, `provisioned_at` sind
  **nie** `$fillable`. Immer explizit in Admin-Code zuweisen. Ebenso
  `previews.version`, die `thumbnail_*`-Spalten, die `logo_*`-Spalten von
  `branding`, die `directory*`-Spalten von `projects`, bei Rückmeldungen
  `preview_id`, `user_id`, `preview_version` und sämtliche Spalten von
  `preview_handoffs` und `preview_sessions`.
- Fremde oder unbekannte IDs → **404**, niemals 403. Ein 403 bestätigt die
  Existenz der Ressource.
- Sichtbarkeitsprüfungen laufen über `Project::visibleTo()` bzw.
  `Preview::visibleTo()`, nicht über handgeschriebene where-Klauseln.
- Policies formulieren jede Fähigkeit einzeln. Kein pauschales `Gate::before`
  für Admins – neue Fähigkeiten sollen standardmäßig verweigert werden.
- Anmeldung und Passwort-Reset geben **eine** generische Meldung. Nie
  preisgeben, ob eine E-Mail-Adresse existiert.
- Keine Secrets, Tokens oder personenbezogenen Daten loggen.
- `SESSION_DOMAIN` bleibt leer. Siehe ADR 0001.
- Preview-Ziele nur aus der Allowlist in `config/previews.php`. Änderungen an
  `PreviewTargetGuard` brauchen begleitende Tests.
- Der Screenshot-Browser öffnet nur das geprüfte Ziel: statische Verzeichnisse
  ohne Netzwerk, Upstream-URLs auf die geprüfte öffentliche IP gepinnt, alles
  andere blockiert (Request-Handler, toter Proxy, Resolver-Regeln). Chromiums
  Sandbox bleibt an; der Worker braucht dafür `docker/seccomp/chromium.json`.
  Nie `--no-sandbox` oder `seccomp=unconfined` als Abkürzung. Änderungen an
  `PreviewScreenshotter` oder dem Skript brauchen begleitende Tests.
- Vorschaubilder liegen auf der privaten Disk und werden nur über autorisierte
  Routen ausgeliefert – Kunden nur das Bild der aktuellen Version.
- Projektordner („Ordner anlegen“) nur über `CreateProjectDirectory` im
  Queue-Worker: Name nur aus den Kürzeln, nur anlegen, nur unterhalb von
  `PROJECT_DIRECTORY_ROOT`, keine Symlinks, keine erhöhten Rechte. Siehe
  ADR 0002. Änderungen daran brauchen begleitende Tests.
- Statische Vorschauen liefert Smallgate selbst auf eigenen Preview-Hosts aus
  (ADR 0003):
  - `PREVIEW_BASE_DOMAIN` ist eine eigene registrierbare Domain, nie eine
    Subdomain der Portal-Domain.
  - Die Portal-Session verlässt nie den Portal-Host. Zugang nur über das
    einmalige Übergabe-Token (`PreviewAccess`), Sitzungs-Cookie nur
    `__Host-`, `Secure`, `HttpOnly`, `SameSite=Lax` – nicht `Strict`, das
    bricht den Tausch (ADR 0003, „Cookie“).
  - Tokens nur als SHA-256-Hash speichern, nie loggen. nginx kürzt
    `/__smallgate/zugang` im Access-Log.
  - Jede Anfrage auf dem Preview-Host prüft Sitzung, `Preview::visibleTo()`
    und `PreviewPolicy::open` neu. Kein Cachen der Entscheidung.
  - Dateien nur über `PreviewFileResolver`. Kein zweites Dekodieren des
    Pfads, MIME-Typ nur aus der Endungsliste.
  - Auf dem Preview-Host erreicht keine Anfrage eine Portal-Route: Die
    Preview-Routen werden zuerst registriert und decken jeden Pfad und jede
    Methode ab. Portal-Routen bekommen **kein** `Route::domain()` (bricht
    `APP_URL`-gebundene Links, siehe ADR 0003). Ohne `web`-Middleware auf dem
    Preview-Host – keine Session, kein CSRF, keine Portal-Cookies.
  - Weiterleitungen auf dem Preview-Host nur mit relativer `Location`:
    `redirect()`/`url()` erzeugen Portal-URLs.
  - Änderungen an `PreviewAccess`, `PreviewHostController`,
    `PreviewFileResolver`, `routes/preview.php` oder den Preview-Blöcken der
    nginx-Konfiguration brauchen begleitende Tests.
- Logos im Erscheinungsbild: nur PNG/WebP, nie SVG. Auslieferung nur über
  `BrandingAssetController` mit gespeichertem MIME-Typ, `nosniff` und
  Sandbox-CSP. Farben gelangen nur als validierter Hex-Wert ins Stylesheet.

## Preview-Provisioning

Der `NullPreviewProvisioner` ist die einzige Implementierung. Er darf **keine**
Dateien außerhalb des Projektverzeichnisses verändern (die einzige Ausnahme in
Smallgate sind Projektordner, ADR 0002, und die gehören nicht hierher) und **keine** Kommandos
mit erhöhten Rechten ausführen. Für die Auslieferung braucht er auch nichts
davon: Statische Vorschauen liefert Smallgate selbst aus, sobald sie verfügbar
sind, siehe `docs/adr/0003-preview-delivery.md`. ADR 0001 hält die verworfenen
Optionen fest. Hochladen von Entwürfen ist nicht entschieden und bekommt eine
eigene ADR.

## Kundenzuordnung

Ein Benutzer gehört zu genau einem Kunden (`users.customer_id`). Jede
Sichtbarkeitsprüfung geht durch `User::accessibleCustomerIds()`. Wenn später
Mehrfachzuordnung nötig wird: Schema ändern und **diese eine Methode** anpassen,
nicht alle Abfragen umschreiben.

## Sprache

- Benutzeroberfläche, Validierungsmeldungen und Dokumentation: Deutsch.
- Code, Kommentare, Klassen- und Methodennamen, Testbeschreibungen: Englisch.
- Routen-URLs sind deutsch (`/kunden`, `/projekte`), Routennamen englisch
  (`admin.customers.index`).

## Nach jeder Änderung

```bash
./sg pint && ./sg test
```
