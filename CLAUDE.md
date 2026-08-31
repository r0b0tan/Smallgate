# CLAUDE.md – Smallgate

Kleines, selbst gehostetes Kundenportal für Clickit Digital. Laravel-Monolith.

## Was das Projekt ausdrücklich NICHT ist

Kein CRM, kein Rechnungsprogramm, kein Dokumentenarchiv, kein Chat, keine
Benachrichtigungszentrale. Rechnungen, Dokumente und Kommunikation laufen per
E-Mail. Features außerhalb des MVP nicht hinzufügen.

## Stack

- Laravel 13, PHP 8.4, PostgreSQL 17
- Blade + Tailwind CSS 4, minimal JavaScript (nur Mobile-Nav-Toggle)
- Pest 5 / PHPUnit 13, Tests gegen echtes PostgreSQL (nicht SQLite)
- Docker Compose, Mailpit

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
  **nie** `$fillable`. Immer explizit in Admin-Code zuweisen.
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

## Preview-Provisioning

Der `NullPreviewProvisioner` ist die einzige Implementierung. Er darf **keine**
Dateien außerhalb des Projektverzeichnisses verändern und **keine** Kommandos
mit erhöhten Rechten ausführen. Die echte Subdomain-Auslieferung ist eine eigene
Phase – die Architekturentscheidung ist bewusst noch offen, siehe
`docs/adr/0001-preview-subdomain-architecture.md`.

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
