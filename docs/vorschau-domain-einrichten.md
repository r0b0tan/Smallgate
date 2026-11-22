# Vorschau-Domain einrichten

Statische Vorschauen liefert Smallgate auf eigenen Hosts aus, einer pro
Vorschau unterhalb von `PREVIEW_BASE_DOMAIN`, z. B.
`holzmann.example-preview.com`. Warum das so gebaut ist, steht in
[ADR 0003](adr/0003-preview-delivery.md). Diese Seite beschreibt die
einmalige Einrichtung auf dem Server.

Erprobt mit Cloudflare als DNS, einer Domain bei einem anderen Registrar und
nginx samt certbot als Reverse-Proxy auf dem Host. Mit anderen Anbietern gilt
dasselbe, nur die Menüs und das certbot-Plugin heißen anders.

In den Beispielen:

| | |
|---|---|
| Portal | `portal.example.com` |
| Vorschau-Domain | `example-preview.com` |
| Server | `203.0.113.10` |
| `web`-Container | `127.0.0.1:8080` (`APP_PORT`) |

## Überblick

1. Eigene Domain registrieren
2. DNS einrichten
3. Wildcard-Zertifikat holen
4. Reverse-Proxy konfigurieren
5. Smallgate konfigurieren und deployen
6. Prüfen

Zwei Stellen verlangen eine bestimmte Reihenfolge: DNSSEC beim Wechsel des
DNS-Anbieters (Schritt 2) und das Deploy von Smallgate vor dem Freischalten des
Proxy-Blocks (Schritt 4 und 5).

## 1. Eigene Domain

Die Vorschau-Domain ist eine **eigene registrierbare Domain**, nie eine
Subdomain der Portal-Domain. Also `example-preview.com`, nicht
`preview.example.com`.

Ein Entwurf ist fremdes HTML mit eigenem JavaScript. Läge er unter derselben
Domain wie das Portal, wären beide für den Browser dieselbe Website: Das
Portal-Cookie ginge bei Anfragen aus dem Entwurf mit, und ein Entwurf könnte
Cookies für das Portal setzen. Mit einer eigenen Domain ist das
ausgeschlossen.

Die Domain verschickt keine Mails und braucht keine Website unter der Domain
selbst.

## 2. DNS

### Domain zum DNS-Anbieter holen

Liegt die Domain bei einem Registrar und das DNS bei Cloudflare:

1. **DNSSEC beim Registrar ausschalten**, falls es dort aktiv ist. Sonst zeigt
   der DS-Eintrag nach dem Wechsel auf die alten Schlüssel, und validierende
   Resolver lösen die Domain gar nicht mehr auf.
2. In Cloudflare die Zone anlegen:
   - **Keine Einträge importieren.** Eine frisch registrierte Domain hat beim
     Registrar meist Standard-Einträge (Parkseite, MX, SPF für dessen
     Mailserver), die hier nichts zu suchen haben.
   - Die Einstellungen zu KI-Crawlern sind ohne Proxy wirkungslos. Auf „Block“
     stellen schadet nicht. „Bot Preference Sync“ ausschalten: Es würde die
     `robots.txt` verändern.
3. Beim Registrar die beiden Cloudflare-Nameserver eintragen und warten, bis
   die Zone in Cloudflare „Active“ ist.
4. Später, wenn alles läuft: DNSSEC in Cloudflare aktivieren (DNS → Settings)
   und den angezeigten DS-Eintrag beim Registrar hinterlegen.

```bash
dig +short NS example-preview.com    # → die Cloudflare-Nameserver
```

### Einträge

| Typ | Name | Inhalt | Proxy |
|---|---|---|---|
| `A` | `*` | `203.0.113.10` | **DNS only** |
| `AAAA` | `*` | IPv6 des Servers, nur wenn der Proxy dort lauscht | **DNS only** |
| `TXT` | `@` | `v=spf1 -all` | |
| `TXT` | `_dmarc` | `v=DMARC1; p=reject;` | |
| `CAA` | `@` | `0 issue "letsencrypt.org"` | |
| `CAA` | `@` | `0 issuewild "letsencrypt.org"` | |

- **Kein Eintrag für die Domain selbst** (`@` als A/AAAA). Smallgate
  beantwortet nur `<label>.example-preview.com` und verwirft alles andere.
- **Proxy aus („DNS only“, graue Wolke).** Cloudflare schaltet ihn bei neuen
  Einträgen standardmäßig ein. Mit Proxy sähe Cloudflare Entwürfe,
  Sitzungs-Cookies und das Übergabe-Token im Klartext, könnte HTML umschreiben,
  und das Rate-Limit am Token-Tausch sähe nur noch Cloudflare-Adressen. Die
  Hinweise, die Cloudflare dazu anzeigt („Proxying is required …“,
  „Visitors cannot reach …“), gelten hier nicht.
- **Universal SSL ausschalten** (SSL/TLS → Edge Certificates → „Disable
  Universal SSL“). Sonst veröffentlicht Cloudflare neben den eigenen
  CAA-Einträgen zusätzliche für Comodo, DigiCert, Google und SSL.com. Ohne
  Proxy wird es nicht gebraucht.
- SPF und DMARC verhindern Phishing-Mails „von“ der Vorschau-Domain. CAA
  erlaubt nur Let's Encrypt; mit einer anderen Zertifizierungsstelle die
  Einträge anpassen.

```bash
dig +short holzmann.example-preview.com    # → 203.0.113.10, keine Cloudflare-Adresse
dig +short gibtsnicht.example-preview.com  # → 203.0.113.10 (Wildcard)
dig +short example-preview.com             # → leer
dig +short CAA example-preview.com         # → nur die beiden letsencrypt.org-Einträge
```

## 3. Wildcard-Zertifikat

Ein Zertifikat für `*.example-preview.com` gibt es nur über die
DNS-01-Challenge: certbot legt kurz einen TXT-Eintrag `_acme-challenge` an und
braucht dafür API-Zugriff auf das DNS.

**API-Token** in Cloudflare: My Profile → API Tokens → Vorlage „Edit zone DNS“,
Zone Resources nur `example-preview.com`, optional auf die IP des Servers
beschränkt.

Das Token **in einer eigenen SSH-Sitzung** eintragen, nicht über einen Chat
oder eine Agent-Sitzung. Deren Verlauf würde es enthalten.

```bash
sudo apt install python3-certbot-dns-cloudflare
sudo install -d -m 700 /root/.secrets
sudo install -m 600 /dev/null /root/.secrets/cloudflare-preview.ini
sudo nano /root/.secrets/cloudflare-preview.ini
#   dns_cloudflare_api_token = <Token>

sudo certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare-preview.ini \
  --dns-cloudflare-propagation-seconds 30 \
  -d '*.example-preview.com' \
  --deploy-hook 'systemctl reload nginx'

sudo certbot renew --dry-run
sudo certbot certificates    # zeigt, wo das Zertifikat liegt
```

Der `--deploy-hook` sorgt dafür, dass nginx das erneuerte Zertifikat bei jeder
automatischen Verlängerung lädt.

## 4. Reverse-Proxy (nginx auf dem Host)

Drei Dinge: Log-Maskierung, Proxy-Header, `server`-Blöcke.

**Log-Maskierung** im `http`-Kontext, etwa
`/etc/nginx/conf.d/smallgate-logmap.conf`. Sie kürzt die drei Pfade, die ein
Einmal-Token in der URL tragen. Smallgates eigene nginx im Container tut
dasselbe, der Proxy davor sieht die Pfade aber vorher:

```nginx
map $request_uri $smallgate_log_uri {
    ~^/einladung/                /einladung/[entfernt];
    ~^/passwort-zuruecksetzen/   /passwort-zuruecksetzen/[entfernt];
    ~^/__smallgate/zugang        /__smallgate/zugang?[entfernt];
    default                      $request_uri;
}

log_format smallgate '$remote_addr - $remote_user [$time_local] '
                     '"$request_method $smallgate_log_uri $server_protocol" '
                     '$status $body_bytes_sent "$http_user_agent"';
```

**Proxy-Header** überschreiben, nie anhängen, etwa
`/etc/nginx/snippets/smallgate-proxy.conf`:

```nginx
proxy_pass http://127.0.0.1:8080;
proxy_set_header Host              $host;
proxy_set_header X-Forwarded-For   $remote_addr;
proxy_set_header X-Forwarded-Host  $host;
proxy_set_header X-Forwarded-Proto https;
proxy_set_header X-Forwarded-Port  443;
```

**`server`-Blöcke** für Portal, Vorschau-Hosts und die Weiterleitung von
Port 80:

```nginx
server {
    listen 443 ssl;
    server_name portal.example.com;
    ssl_certificate     /etc/letsencrypt/live/portal.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/portal.example.com/privkey.pem;
    access_log /var/log/nginx/smallgate.access.log smallgate;

    # Entwürfe hochladen (ADR 0004). Smallgates eigene nginx lässt 66 MB
    # nur auf der Upload-Route durch und begrenzt alles andere auf 16 MB.
    client_max_body_size 66M;

    location / {
        include snippets/smallgate-proxy.conf;
    }
}

server {
    listen 443 ssl;
    server_name *.example-preview.com;
    ssl_certificate     /etc/letsencrypt/live/example-preview.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example-preview.com/privkey.pem;
    # Hier landet der Übergabe-Link mit Token: unbedingt gekürzt loggen.
    access_log /var/log/nginx/smallgate.access.log smallgate;

    location / {
        include snippets/smallgate-proxy.conf;
    }
}

server {
    listen 80;
    server_name portal.example.com *.example-preview.com;
    return 301 https://$host$request_uri;
}
```

**Keine eigenen Sicherheits-Header am Proxy.** Smallgate setzt HSTS, CSP,
`nosniff` und die übrigen Header selbst, passend für Portal und Vorschau-Hosts.
Eine CSP des Proxys würde jeden Entwurf kaputtmachen, ein zweites HSTS ist
überflüssig. Gemeinsame Header-Snippets anderer Seiten auf demselben Server
deshalb nicht in diese Blöcke einbinden.

Den Block für die Vorschau-Hosts erst nach Schritt 5 aktivieren, dann:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Mit **Caddy** statt nginx ersetzt ein Build mit dem passenden DNS-Modul (für
Cloudflare `caddy-dns/cloudflare`) die Schritte 3 und 4. Caddy setzt die
`X-Forwarded-*`-Header selbst und begrenzt die Request-Größe nicht:

```
*.example-preview.com {
    tls {
        dns cloudflare {env.CF_API_TOKEN}
    }
    reverse_proxy 127.0.0.1:8080 {
        header_up X-Forwarded-Port 443
    }
}
```

## 5. Smallgate

In der `.env`:

```dotenv
PREVIEW_BASE_DOMAIN=example-preview.com
PREVIEW_TARGET_TYPES=           # leer: statische Verzeichnisse und Upstream-URLs
```

`SESSION_DOMAIN` bleibt leer. `DB_QUEUE_RETRY_AFTER` nicht setzen, oder
mindestens 130.

`compose.prod.yaml` reicht `PREVIEW_BASE_DOMAIN` an die nginx im Container
weiter. Sie nimmt danach genau eine Ebene unter der Domain an und verwirft
alles andere. Die Portal-CSP erlaubt `form-action` auf die Vorschau-Hosts,
damit die Anmeldung über einen Vorschau-Link dort landet.

Deployen, mit neuem Image (PHP-Erweiterung `zip` für Uploads) und frisch
gecachter Konfiguration:

```bash
docker compose -f compose.prod.yaml up -d --build --force-recreate
```

Die Projektordner (Standard `storage/app/previews`) muss der `app`-Container
lesen und der `worker` beschreiben können. Im Standard-Volume ist beides der
Fall.

## 6. Prüfen

```bash
# Ohne Sitzung: Weiterleitung ins Portal, gültiges Zertifikat, Header der App
curl -sI https://test.example-preview.com/
#   302 → https://portal.example.com/portal/vorschauen/oeffnen/test.example-preview.com
#   x-content-type-options: nosniff
#   cross-origin-resource-policy: same-origin
#   x-robots-tag: noindex

# Genau ein HSTS-Header, nicht zwei
curl -sI https://test.example-preview.com/ | grep -ci strict-transport-security   # → 1

# Nur GET und HEAD
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://test.example-preview.com/login   # → 403

# Portal-CSP erlaubt den Weg vom Login zur Vorschau
curl -sI https://portal.example.com/login | grep -i content-security-policy
#   … form-action 'self' https://*.example-preview.com

docker compose -f compose.prod.yaml exec worker php -m | grep -x zip
docker compose -f compose.prod.yaml exec app php artisan migrate:status | tail -3

# Keine Tokens im Log
sudo grep -c 'token=[0-9a-f]' /var/log/nginx/smallgate.access.log   # → 0
```

Dann einmal im Browser:

1. Als Kunde im Portal eine freigegebene Vorschau öffnen.
2. Abmelden, den Vorschau-Link direkt aufrufen: Anmeldung erscheint, danach
   landet man wieder in der Vorschau.
3. Ein anderer Kunde bekommt für dieselbe Adresse nach der Anmeldung
   „Seite nicht gefunden“.
4. Als Admin auf einer Projektseite „Ordner anlegen“, dann ein ZIP mit
   `index.html` hochladen (siehe README, „Uploading a draft“).

## Ohne Vorschau-Domain

Wer keine eigene Domain einrichten will, setzt
`PREVIEW_TARGET_TYPES=upstream_url`. Dann bietet Smallgate nur Upstream-URLs
an, und statische Vorschauen und Uploads entfallen.
