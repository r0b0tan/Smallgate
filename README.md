# Smallgate

Ein sehr kleines, selbst gehostetes Kundenportal für **Clickit Digital**.

Kunden melden sich an und sehen ausschließlich ihre eigenen Projekte und die
dazugehörigen Website-Vorschauen. Mehr nicht.

**Bewusst kein Bestandteil:** kein CRM, keine Rechnungen, kein Dokumentenarchiv,
kein Chat, keine Benachrichtigungszentrale. Rechnungen, Dokumente und Absprachen
laufen weiterhin per E-Mail.

## Stack

| Baustein | Auswahl |
|---|---|
| Framework | Laravel 13 |
| PHP | 8.4 (Container) |
| Datenbank | PostgreSQL 17 |
| Frontend | Blade + Tailwind CSS 4, ~20 Zeilen eigenes JavaScript |
| Tests | Pest 5 / PHPUnit 13 gegen echtes PostgreSQL |
| Entwicklung | Docker Compose, Mailpit für E-Mails |

Ein Laravel-Monolith. Keine REST-API, kein SPA-Framework, kein Redis, keine
Microservices, keine externen Dienste.

## Setup

Voraussetzung ist Docker mit Compose. PHP, Composer und Node werden **nicht**
auf dem Host benötigt – alles läuft im Container.

```bash
git clone https://github.com/r0b0tan/Smallgate.git
cd Smallgate

cp .env.example .env

# Container-Image bauen (uid/gid des Hosts, damit Dateirechte stimmen)
docker compose build --build-arg UID=$(id -u) --build-arg GID=$(id -g) app

./sg composer install
./sg artisan key:generate
./sg up

./sg artisan migrate
./sg artisan db:seed          # Demo-Daten, nur außerhalb der Produktion

./sg npm install
./sg npm run build
```

Danach erreichbar:

| Dienst | Adresse |
|---|---|
| Portal | http://localhost:8080 |
| Mailpit (E-Mails) | http://localhost:8025 |
| PostgreSQL | localhost:55432 |

Für die Frontend-Entwicklung mit Hot Reload:

```bash
docker compose --profile dev up vite
```

### Demo-Zugänge

Der Seeder legt folgende Konten an (Passwort aus `SEED_PASSWORD`, standardmäßig
`passwort-nur-fuer-lokale-entwicklung`):

| E-Mail | Rolle |
|---|---|
| `admin@clickit-digital.test` | Administrator |
| `marion@holzmann.test` | Kunde – Holzmann Bau GmbH |
| `sabine@bergblick.test` | Kunde – Hotel Bergblick |
| `joerg@altmann.test` | Kunde eines **deaktivierten** Kunden (kann sich nicht anmelden) |

Der Seeder verweigert die Ausführung, wenn `APP_ENV=production` ist.

## Das Helferskript `./sg`

Dünner Wrapper um `docker compose`, damit alles mit der uid/gid des Hosts läuft:

```bash
./sg up                  # Stack starten
./sg down                # Stack stoppen
./sg artisan <befehl>    # Artisan
./sg composer <befehl>   # Composer
./sg test                # vollständige Testsuite
./sg pint                # Code formatieren
./sg npm <befehl>        # npm
./sg shell               # Shell im App-Container
./sg logs                # Logs folgen
```

## Rollen

**Administrator** – legt Kunden, Projekte und Vorschauen an und bearbeitet sie,
lädt Kundenbenutzer ein, versendet Einladungen erneut, sperrt Konten, sieht
alles.

**Kunde** – meldet sich an, ändert das eigene Passwort, sieht ausschließlich die
eigenen Projekte und deren Vorschauen. Keinerlei Schreibrechte.

Es gibt **keine öffentliche Registrierung**. Konten entstehen ausschließlich
durch eine Einladung eines Administrators.

## Sicherheit

Die getroffenen Entscheidungen im Überblick:

**Passwörter** – Argon2id (OWASP-Parameter: 64 MiB, 4 Iterationen), ausschließlich
über Laravels `Hash`-Fassade. Keine eigene Kryptografie, keine Verschlüsselung
von Passwörtern.

**Anmeldung** – Rate-Limiting pro Kombination aus E-Mail und IP (fünf Versuche je
Minute). Das ist *ein* Zähler pro Paar, nicht je ein eigenes Limit pro E-Mail und
pro IP: Angriffe auf ein Konto sperren kein anderes, verteiltes Raten über viele
Quelladressen wird damit aber nicht verhindert. Die Session-ID wird nach dem
Login neu erzeugt; es gibt eine einzige generische Fehlermeldung für falsches
Passwort, unbekannte Adresse, gesperrtes Konto und deaktivierten Kunden. Auch
„Passwort vergessen“ antwortet immer gleich, unabhängig davon, ob die Adresse
existiert.

**Host-Header und generierte Links** – Passwort-Reset- und Einladungsmails
enthalten absolute URLs. Damit ein gefälschter `Host`-Header kein gültiges Token
auf eine fremde Domain schicken kann, greifen zwei Schichten: Anfragen mit einem
nicht konfigurierten `Host` werden abgewiesen (`TRUSTED_HOSTS`, außerhalb von
`local`; Subdomains gelten *nicht* als vertrauenswürdig), und jede generierte URL
wird fest an `APP_URL` gebunden – auch in Queue-Workern und Konsolenbefehlen, wo
es gar keine Anfrage gibt. `X-Forwarded-*` wird nur von den in `TRUSTED_PROXIES`
genannten Proxys akzeptiert; ohne Eintrag werden die Header ignoriert.

Der vorgeschaltete Webserver sollte unbekannte Hosts zusätzlich selbst abweisen
(bei nginx ein `default_server` mit `return 444`). Die mitgelieferte
nginx-Konfiguration ist eine Entwicklungskonfiguration und tut das bewusst nicht.

**Sitzungen** – Datenbank-Treiber, `HttpOnly`, `SameSite=lax`, `Secure` in
Produktion. Nach Passwortänderung und -Reset werden alle anderen Sitzungen
gelöscht und Remember-Me-Tokens verworfen. Ein gesperrter Benutzer oder ein
deaktivierter Kunde verliert den Zugriff bei der **nächsten Anfrage**, nicht
erst beim nächsten Login.

**Autorisierung** – Policies ohne pauschales `Gate::before`; jede Fähigkeit wird
einzeln formuliert, Standard ist Verweigern. Kundendaten werden über den Scope
`Project::visibleTo()` eingegrenzt – eine fremde oder unbekannte ID liefert
deshalb **404**, niemals 403, und ist damit von außen ununterscheidbar.

**Mass Assignment** – `role`, `customer_id`, `is_active`, `project_id` und
`provisioned_at` sind nirgends `$fillable`. Das Invitation-Modell ist vollständig
`#[Guarded]`. Zusätzlich erzwingen PostgreSQL-CHECK-Constraints, dass ein
Kundenbenutzer immer einen Kunden hat und ein Administrator nie einen.

**Einladungen** – 256 Bit CSPRNG-Token, in der Datenbank nur als SHA-256-Hash.
Zeitlich begrenzt, einmalig verwendbar, Wiederversand macht den alten Link sofort
ungültig. Die Einlösung läuft in einer Transaktion mit `lockForUpdate`, sodass
zwei gleichzeitige Einlösungen nicht beide ein Konto erzeugen.

**IDs** – ULIDs als Primärschlüssel für alle öffentlich sichtbaren Ressourcen.
Fortlaufende Ganzzahlen wären zähl- und aufzählbar.

**Datenschutz** – nur technisch notwendige Cookies. Kein Tracking, keine
Analytics, keine externen JavaScript-Dienste, keine CDNs. Schriftarten werden als
npm-Pakete lokal gebündelt. Das Portal ist mit `noindex` von Suchmaschinen
ausgeschlossen. Es werden keine Tokens, Secrets oder personenbezogenen Daten
protokolliert.

**Vorschau-Ziele** – Pfade und Upstream-URLs stammen ausschließlich aus einer
Allowlist in `config/previews.php`. Path Traversal wird lexikalisch aufgelöst;
existierende Pfade werden zusätzlich per `realpath()` gegen Symlinks geprüft.
Upstream-URLs müssen HTTPS sein und einen erlaubten Host ohne IP-Literal,
Zugangsdaten oder abweichenden Port verwenden. Kunden sehen ein Ziel nie und
können es nie beeinflussen.

Diese Prüfungen sind eine Eingabevalidierung, keine vollständige SSRF-Abwehr:
Hostnamen werden nicht aufgelöst, private oder Loopback-Adressen hinter einem
erlaubten Namen nicht erkannt, Redirects und DNS-Rebinding nicht behandelt.
Ebenso schützt `realpath()` nicht gegen Symlinks, die erst nach der Prüfung
entstehen (TOCTOU), und nicht gegen noch nicht existierende Ziele. Solange nur
der `NullPreviewProvisioner` existiert, wird keine Verbindung aufgebaut und
keine Datei ausgeliefert – vor einer echten Auslieferung müssen beide Prüfungen
am tatsächlichen I/O-Punkt wiederholt und vervollständigt werden.

## Vorschauen

Für das MVP sind Vorschauen **nur ein geschützter Eintrag im Portal**. Es gibt
noch keine Subdomain-Auslieferung und keinen Proxy.

Vorbereitet ist:

- Ein Wildcard-DNS-Eintrag `*.preview.clickit-digital.de` genügt – Smallgate
  erzeugt keine DNS-Einträge.
- `previews.hostname` ist global eindeutig, sodass ein `Host`-Header später
  genau einer Vorschau zugeordnet werden kann.
- Das Interface `App\Contracts\PreviewProvisioner` markiert die Systemgrenze.
- Die einzige Implementierung `NullPreviewProvisioner` verändert **keine**
  Serverdateien und führt **keine** privilegierten Kommandos aus.

Die Architekturentscheidung für die echte Auslieferung ist bewusst noch offen –
inklusive der Sicherheitsprobleme von Session-Cookies über mehrere Subdomains:
[docs/adr/0001-preview-subdomain-architecture.md](docs/adr/0001-preview-subdomain-architecture.md).

## Tests

```bash
./sg npm run build   # einmalig nötig: die Views binden das Vite-Manifest ein
./sg test
```

Die Tests laufen gegen eine echte PostgreSQL-Datenbank (`smallgate_test`, wird
vom `db`-Container beim ersten Start automatisch angelegt) und **nicht** gegen
SQLite – die CHECK-Constraints und Regex-Operatoren des Schemas gehören
ausdrücklich mit zum Testumfang.

Abgedeckt sind unter anderem: keine öffentliche Registrierung, Anlegen von
Kunden, Einladungsablauf inklusive Einmalverwendung und Ablauf, Mandantentrennung,
404 statt 403 bei fremden IDs, gesperrte Benutzer und deaktivierte Kunden,
Login-Rate-Limiting, Sitzungsentzug bei Passwortänderung, Mass-Assignment-Schutz
sowie Path-Traversal- und SSRF-Abwehr bei Vorschau-Zielen.

## Konfiguration

Alle sensiblen Werte kommen aus Umgebungsvariablen. `.env.example` enthält keine
echten Secrets und ist durchgehend kommentiert.

Vor dem Produktivbetrieb zwingend setzen:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<php artisan key:generate>
APP_URL=https://portal.example.de
SESSION_SECURE_COOKIE=true
LOG_LEVEL=info

# Nur nötig, wenn das Portal unter weiteren Namen erreichbar ist.
TRUSTED_HOSTS=
# Adressen oder CIDR-Bereiche des Reverse Proxy, der TLS terminiert.
TRUSTED_PROXIES=
```

`APP_URL` ist nicht kosmetisch: Es ist die kanonische Basis für jeden
generierten Link und gleichzeitig der erste vertrauenswürdige Host. Ohne
`TRUSTED_PROXIES` sieht die Anwendung hinter einem TLS-terminierenden Proxy
`http` statt `https`.

`SESSION_DOMAIN` bleibt leer. Das Session-Cookie darf niemals auf die
Parent-Domain gesetzt werden – die Begründung steht in ADR 0001.

Impressum und Datenschutzerklärung sind Platzhalterseiten und werden über
`LEGAL_*` konfiguriert. Der endgültige Text ist vor dem Livegang rechtlich zu
prüfen.

## Projektstruktur

```
app/
├── Contracts/          PreviewProvisioner – die einzige echte Systemgrenze
├── Enums/              UserRole, ProjectStatus, PreviewStatus, PreviewTargetType
├── Http/
│   ├── Controllers/    Auth, Admin, Portal, Profile, Legal
│   ├── Middleware/     EnsureUserIsAdmin, EnsureAccountIsActive
│   └── Requests/       serverseitige Validierung
├── Models/             User, Customer, Project, Preview, Invitation
├── Notifications/      Einladung, Passwort-Reset
├── Policies/           explizit, ohne pauschales Gate::before
├── Rules/              PreviewHostname, AllowedPreviewTarget
└── Services/
    ├── InvitationService.php
    └── Previews/       NullPreviewProvisioner, PreviewTargetGuard
docker/                 PHP-Image, nginx, PostgreSQL-Init
docs/adr/               Architekturentscheidungen
```

Keine Repository-Pattern über Eloquent. Keine Interfaces außer an der
tatsächlichen Systemgrenze. Keine vorweggenommene Multi-Tenancy-Plattform.

### Eine Anmerkung zur Kundenzuordnung

Ein Benutzer gehört im MVP zu genau einem Kunden – abgebildet als
`users.customer_id`. Jede Sichtbarkeitsprüfung läuft über
`User::accessibleCustomerIds()`. Eine spätere Mehrfachzuordnung erfordert damit
eine Schemaänderung und die Anpassung **dieser einen Methode**, nicht das
Umschreiben aller Abfragen.
