# Smallgate sicher aufsetzen und betreiben

Diese Anleitung richtet sich an alle, die Smallgate auf einem eigenen Server
für echte Kunden betreiben. Die Befehle für Installation, Update und Backup
stehen im README unter „Running in production“. Hier geht es darum, was dabei
für die Sicherheit zählt und warum.

Annahme: ein Linux-Server mit Docker Compose, auf dem ein Reverse Proxy TLS
terminiert und an `compose.prod.yaml` weiterreicht.

## 1. Server

- Ein aktuelles Linux mit automatischen Sicherheitsupdates. SSH nur mit
  Schlüssel, ohne Passwort-Login und ohne Root-Login.
- Firewall: nach außen nur 22, 80 und 443 offen.
- **Docker umgeht die Host-Firewall** für veröffentlichte Ports (ufw,
  firewalld). Deshalb lauscht der `web`-Container nur auf `127.0.0.1`.
  `WEB_BIND` nicht auf `0.0.0.0` ändern.
- Wer in der Gruppe `docker` ist, ist faktisch root. Die Gruppe klein halten.
- DNS: ein A- bzw. AAAA-Eintrag für den Portal-Host, z. B.
  `portal.example.com`. Ein Wildcard-Eintrag für Vorschauen wird derzeit nicht
  gebraucht (siehe Abschnitt 6).

## 2. Reverse Proxy und TLS

Der Proxy muss den ursprünglichen `Host`-Header weitergeben und
`X-Forwarded-For`, `-Proto`, `-Host` und `-Port` **selbst setzen**. Vom Client
mitgeschickte Werte darf er nicht durchreichen.

Caddy setzt `X-Forwarded-For`, `-Proto` und `-Host` von sich aus, holt das
Zertifikat und leitet auf HTTPS um. Nur den Port muss man ausdrücklich setzen:

```
portal.example.com {
    reverse_proxy 127.0.0.1:8080 {
        header_up X-Forwarded-Port 443
    }
}
```

Mit nginx die Header ausdrücklich überschreiben:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host              $host;
    proxy_set_header X-Forwarded-For   $remote_addr;
    proxy_set_header X-Forwarded-Host  $host;
    proxy_set_header X-Forwarded-Proto https;
    proxy_set_header X-Forwarded-Port  443;
}
```

Smallgate sendet HSTS mit einem Jahr Laufzeit. Bevor echte Nutzer kommen,
sicherstellen, dass HTTPS dauerhaft funktioniert. Ein Zurück auf HTTP ist
danach ein Jahr lang nicht möglich.

## 3. Konfiguration (`.env`)

Die `.env` enthält alle Geheimnisse. Sie gehört nicht ins Git (ist ignoriert)
und ist nur für den Betreiber lesbar:

```bash
chmod 600 .env
```

### Pflichtwerte

| Variable | Wert | Warum |
|---|---|---|
| `APP_KEY` | `base64:` + `openssl rand -base64 32` | Verschlüsselt und signiert Cookies. Wie ein Passwort behandeln; ein Wechsel meldet alle ab. |
| `APP_URL` | `https://portal.example.com` | Basis jedes Links in Einladungs- und Reset-Mails und erster vertrauenswürdiger Host. |
| `PORTAL_HOST` | `portal.example.com` | Nur für diesen Host antwortet nginx, alle anderen werden verworfen. |
| `TRUSTED_PROXIES` | gleicher Wert wie `DOCKER_SUBNET` | Nur aus diesem Netz werden `X-Forwarded-*` geglaubt. **Nie `*`.** |
| `SESSION_SECURE_COOKIE` | `true` | Wird **nicht** erzwungen. Ohne diesen Wert geht das Session-Cookie auch über HTTP. |
| `DB_PASSWORD` | lang und zufällig | Ohne startet der Stack nicht. PostgreSQL übernimmt es nur beim **ersten** Start; spätere Änderungen in `.env` ändern das Passwort in der Datenbank nicht. |
| `LOG_STACK` / `LOG_LEVEL` | `stderr` / `info` | `debug` kann Anfragedaten ins Log schreiben. |
| `MAIL_*` | SMTP-Zugang | Siehe unten. |
| `LEGAL_*`, `CONTACT_EMAIL` | Betreiberangaben | Impressum, Datenschutz, Kontakt. |

`APP_ENV=production` und `APP_DEBUG=false` setzt `compose.prod.yaml` selbst.
`.env` kann sie nicht überschreiben.

### Nicht anfassen

- `SESSION_DOMAIN` bleibt leer. Ein Cookie auf der Parent-Domain wäre für jede
  Subdomain lesbar und überschreibbar (ADR 0001).
- `HASH_DRIVER=argon2id` und die `ARGON_*`-Werte nie senken.
- `PREVIEW_THUMBNAIL_SANDBOX=true` lassen (Abschnitt 6).
- `TRUSTED_HOSTS` nur mit genauen Hostnamen füllen, und nur, wenn das Portal
  wirklich unter mehreren Namen erreichbar ist.

### Mail

Einladungen und Passwort-Resets enthalten Einmal-Links und gehen nur
verschlüsselt raus:

- Port 465: `MAIL_SCHEME=smtps`
- Port 587: `MAIL_SCHEME` leer lassen, STARTTLS wird automatisch genutzt,
  sobald der Server es anbietet.

`MAIL_ENCRYPTION` aus älteren Laravel-Versionen hat keine Wirkung mehr. Damit
die Mails ankommen, sollten für die Absenderdomain SPF und DKIM eingerichtet
sein.

## 4. Erster Start

1. Stack bauen und starten, ersten Administrator anlegen:
   `docker compose -f compose.prod.yaml exec app php artisan admin:create`.
   Das Passwort wird verdeckt abgefragt und landet weder in der Shell-History
   noch in der Prozessliste.
2. Prüfen, dass ein fremder Host keine Antwort bekommt:
   `curl -H 'Host: falsch.example' http://127.0.0.1:8080/` endet ohne Antwort.
3. Im Browser anmelden. In den Entwicklerwerkzeugen müssen die Cookies
   `Secure` und `HttpOnly` tragen.
4. Impressum und Datenschutzerklärung (`/impressum`, `/datenschutz`) prüfen
   und rechtlich prüfen lassen.
5. Eine Einladung an eine eigene Adresse schicken und den Link einlösen. Dann
   ist auch die Mail geprüft.

## 5. Zugänge

- **Wenige Administratoren**, jeweils ein eigenes Konto, nie geteilt.
- Mindestens 12 Zeichen werden erzwungen. Für Administratoren lange
  Passphrasen aus einem Passwortmanager verwenden.
- **Smallgate hat keine Zwei-Faktor-Anmeldung.** Wer kann, beschränkt den
  Admin-Bereich am Proxy auf bekannte Adressen, z. B. mit Caddy:

  ```
  portal.example.com {
      @admin_extern {
          path /admin /admin/*
          not remote_ip 203.0.113.0/24
      }
      respond @admin_extern 404
      reverse_proxy 127.0.0.1:8080 {
          header_up X-Forwarded-Port 443
      }
  }
  ```

- Die Anmeldung ist pro Kombination aus E-Mail und IP gedrosselt. Verteiltes
  Raten über viele Adressen bremst das nicht. Gegen solche Angriffe helfen nur
  lange Passwörter.
- Wer ausscheidet, wird sofort gesperrt. Das wirkt bei der nächsten Anfrage,
  nicht erst bei der nächsten Anmeldung. Endet eine Zusammenarbeit, den Kunden
  deaktivieren.

## 6. Vorschauen

- In Produktion sind nur **Upstream-URLs** erlaubt
  (`PREVIEW_TARGET_TYPES=upstream_url`). Statische Verzeichnisse brauchen eine
  Auslieferung, über die ADR 0001 noch nicht entschieden hat.
- In `PREVIEW_ALLOWED_UPSTREAM_HOSTS` nur Hosts eintragen, die man selbst
  kontrolliert.
- Smallgate schützt den **Weg** zur Vorschau, nicht die Vorschau selbst. Wer
  die URL kennt, kann sie öffnen. Vertrauliche Entwürfe brauchen einen eigenen
  Schutz auf dem Staging-Server. Dann kann der Worker allerdings kein
  Vorschaubild mehr aufnehmen und die Karte zeigt den Platzhalter.
- Der Worker öffnet Kundenseiten in Chromium. Dessen Sandbox und das
  Seccomp-Profil `docker/seccomp/chromium.json` sind die Grenze zwischen einer
  fremden Webseite und dem Container. **Nie** `--no-sandbox`,
  `PREVIEW_THUMBNAIL_SANDBOX=false` oder `seccomp=unconfined` als Abkürzung
  verwenden. Läuft die Sandbox nicht, nimmt Smallgate lieber kein Bild auf.

## 7. Backups

- `scripts/backup.sh` täglich per Cron laufen lassen. Es sichert Datenbank und
  `storage/app` bei laufendem Betrieb.
- Die Dateien enthalten personenbezogene Daten. Verschlüsselt außer Haus
  kopieren (z. B. mit `age` oder `gpg`) und alte Stände löschen. Beides macht
  das Skript nicht selbst.
- Die **`.env` sichert das Skript nicht.** Sie getrennt und sicher
  aufbewahren, z. B. im Passwortmanager. Nicht neben den Backups.
- Eine Wiederherstellung einmal wirklich durchspielen (README, „Backup and
  restore“), bevor man sich darauf verlässt.

## 8. Updates

Vor jedem Update ein Backup. Dann:

```bash
git pull
docker compose -f compose.prod.yaml build --pull
docker compose -f compose.prod.yaml pull db
docker compose -f compose.prod.yaml up -d
```

`--pull` holt aktuelle Basis-Images (PHP, nginx, Node) mit deren
Sicherheitsupdates. Ohne `--pull` bleibt der alte Stand im Cache. PostgreSQL
bekommt so Minor-Updates. Ein Wechsel der Hauptversion (17 → 18) braucht dagegen
Dump und Restore.

PHP-Abhängigkeiten auf bekannte Lücken prüfen:
`./sg composer audit` in einer Entwicklungsumgebung.

## 9. Laufender Betrieb

- **Logs:** `docker compose -f compose.prod.yaml logs`. Smallgate schreibt
  keine Passwörter, Tokens oder personenbezogenen Daten ins Log.
- **Protokoll:** Unter „Protokoll“ im Admin-Bereich stehen Anmeldungen (auch
  fehlgeschlagene) und alle Änderungen. Gelegentlich auf Auffälliges
  durchsehen. Einträge werden nach `ACTIVITY_RETENTION_DAYS` (Standard 90)
  gelöscht.
- **Erreichbarkeit:** `https://portal.example.com/up` antwortet mit 200,
  solange die Anwendung läuft. Das eignet sich für ein Uptime-Monitoring.
- **Speicherplatz:** Vorschaubilder sammeln sich im Volume `storage`.

## Checkliste vor dem Go-live

- [ ] SSH nur mit Schlüssel, Firewall offen nur für 22/80/443
- [ ] `WEB_BIND=127.0.0.1`, Reverse Proxy mit TLS, setzt `X-Forwarded-*` selbst
- [ ] `.env` mit `chmod 600`, eigener `APP_KEY`, langes `DB_PASSWORD`
- [ ] `APP_URL` mit https, `PORTAL_HOST` gesetzt
- [ ] `TRUSTED_PROXIES` = `DOCKER_SUBNET`, nicht `*`
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN` leer
- [ ] `LOG_LEVEL=info`, Mail über TLS
- [ ] Fremder Host bekommt keine Antwort, Cookies sind `Secure` und `HttpOnly`
- [ ] Administrator mit `admin:create` angelegt, Einladung einmal durchgespielt
- [ ] Impressum und Datenschutz rechtlich geprüft
- [ ] Backup per Cron, verschlüsselt außer Haus, Wiederherstellung getestet
- [ ] `.env` getrennt gesichert
- [ ] Optional: Admin-Bereich am Proxy auf bekannte Adressen beschränkt
