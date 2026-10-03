# ADR 0004 – Entwürfe über Smallgate hochladen

- **Status:** Angenommen
- **Datum:** 2026-10-03
- **Kontext:** Baut auf ADR 0002 (Projektordner) und ADR 0003 (Auslieferung)
  auf

## Kontext

Seit ADR 0003 liefert Smallgate statische Vorschauen selbst aus. Die Dateien
kommen aber weiterhin per SFTP oder rsync auf den Server. Das ist das
„Servergefrickel“, das wegfallen soll: Ein Admin soll einen Entwurf im
Browser hochladen, und Smallgate kümmert sich um den Rest.

Dabei gibt es zwei Gefahren. Ein hochgeladenes Archiv ist **Eingabe von
außen**, die auf das Dateisystem trifft. Zip-Slip, Symlinks und Zip-Bomben
sind bekannte Angriffe genau an dieser Stelle. Außerdem hebt das Hochladen
die Regel aus ADR 0002 auf, dass Smallgate nur Ordner anlegt und sonst nichts
am Dateisystem verändert.

Heute gilt außerdem: Wer per rsync in den Ordner einer freigegebenen Vorschau
schreibt, ändert sie sofort und halbfertig für den Kunden.

## Betrachtete Optionen

- **Ordner-Upload im Browser** (`<input webkitdirectory>`). Er braucht kein
  JavaScript, aber PHP begrenzt die Zahl der Dateien pro Request
  (`max_file_uploads`, Standard 20). Ein Build mit Hunderten von Dateien
  scheitert daran oder braucht eine hohe, globale Grenze. Die relativen Pfade
  kommen nur über `full_path` an, das Laravel nicht durchreicht.
- **Git oder CI-Deployment.** Das braucht Zugangsdaten, Webhooks und einen
  Dienst, der von außen erreichbar ist. Das ist mehr Infrastruktur, nicht
  weniger.
- **ZIP-Upload.** Eine Datei pro Entwurf, so wie Build-Werkzeuge sie ohnehin
  erzeugen. Das Entpacken lässt sich vollständig kontrollieren.

## Entscheidung

**Ein Admin lädt den Entwurf als ZIP hoch. Der Queue-Worker entpackt ihn
defensiv in einen neuen, eigenen Ordner. Die Vorschau zeigt erst dann auf
diesen Ordner, wenn er vollständig und geprüft ist.**

### Ablauf

1. **Hochladen.** Auf der Bearbeitungsseite einer statischen Vorschau gibt es
   das Formular „Entwurf hochladen (ZIP)“. Erlaubt ist das nur Admins, über
   die eigene Fähigkeit `PreviewPolicy::upload`. Smallgate speichert die Datei
   unter einem Zufallsnamen auf der privaten Disk. Den Originalnamen
   verwendet es nie als Pfad. Danach legt es einen Eintrag in
   `preview_uploads` an (Status `pending`) und stellt den Job
   `ExtractPreviewUpload` in die Queue. Pro Vorschau läuft höchstens ein
   Upload gleichzeitig. Das erzwingt ein Unique-Index in der Datenbank.
2. **Entpacken (Worker).** Der Job entpackt in einen versteckten
   Arbeitsordner (`.eingang-<upload-id>`). Den liefert der
   `PreviewFileResolver` nie aus, weil er mit einem Punkt beginnt. Erst wenn
   alles geprüft und geschrieben ist, benennt der Job den Ordner atomar in
   `<upload-id>` um.
3. **Umschalten.** Der Job setzt `previews.target` auf den neuen Ordner. Ist
   die Vorschau freigegeben, zählt das wie „Bereitstellen“: neue Version,
   neue Rückmeldung, neues Vorschaubild. Ist sie noch ein Entwurf, ändert sich
   nur das Ziel. Der Admin kann sie dann vor der Freigabe ansehen (ADR 0003)
   und gibt sie wie bisher frei.
4. **Aufräumen.** Das ZIP wird gelöscht. Von den älteren Uploads dieser
   Vorschau bleiben die zwei letzten erhalten, ältere löscht der Job (siehe
   „Löschen“).

Der Kunde sieht nie einen halb entpackten Stand. Zwischen altem und neuem
Entwurf liegt eine einzige Änderung in der Datenbank.

### Ablageort

`<PROJECT_DIRECTORY_ROOT>/<kunde>/<projekt>/vorschauen/<preview-id>/<upload-id>/`

- Der Projektordner muss angelegt sein (ADR 0002). Fehlt er, bietet die Seite
  zuerst „Ordner anlegen“ an.
- Alle Namen unterhalb des Projektordners sind ULIDs, die Smallgate selbst
  erzeugt. Umbenennungen von Vorschau oder Projekt ändern nichts.
- Der Job legt die Ebenen einzeln an, mit denselben Prüfungen wie
  `CreateProjectDirectory`: keine Symlinks, `realpath()` muss im
  Wurzelverzeichnis bleiben.
- Das Wurzelverzeichnis muss in `PREVIEW_ALLOWED_ROOTS` liegen. Das neue Ziel
  läuft wie jedes andere durch `PreviewTargetGuard`.

### Defensives Entpacken

Der Job nutzt nie `ZipArchive::extractTo()`. Er geht Eintrag für Eintrag vor
und schreibt jede Datei selbst:

- **Pfade.** Abgewiesen werden absolute Pfade, Laufwerksbuchstaben,
  Backslashes, Null-Bytes, `..`- und leere Segmente. Ein einziger solcher
  Eintrag lässt den **ganzen Upload** scheitern. Smallgate repariert nichts
  stillschweigend.
- **Symlinks und Sonderdateien.** Einträge, deren Unix-Attribute keinen
  regulären Ordner bzw. keine reguläre Datei ausweisen, lassen den Upload
  scheitern.
- **Übersprungen** werden versteckte Einträge (`.DS_Store`, `.git/`),
  `__MACOSX/` und alles unter `__smallgate/`. Ausgeliefert würden sie ohnehin
  nicht.
- **Ein gemeinsamer Oberordner** (z. B. alles unter `dist/`) wird entfernt.
  Danach muss eine `index.html` im obersten Ordner liegen, sonst scheitert der
  Upload mit einer klaren Meldung.
- **Grenzen gegen Zip-Bomben**, alle in `config/previews.php`:
  - höchstens 64 MB ZIP,
  - höchstens 256 MB entpackt,
  - höchstens 5.000 Dateien,
  - höchstens 20 Ordnerebenen,
  - Pfadlänge höchstens 255 Zeichen.

  Die Größe zählt der Job **beim Schreiben** mit und bricht beim Überschreiten
  ab. Den Größenangaben im ZIP traut er nicht. Vor dem Start prüft er, ob
  genug Platz frei ist.
- **Schreiben.** Jede Datei wird mit `fopen(…, 'x')` neu angelegt. Es wird
  nichts überschrieben und keinem Link gefolgt. Ordner legt der Job einzeln
  an. Kein `chmod`, kein `chown`: Es gelten die Standardrechte des Workers.
- **Inhalt.** Dateien werden nicht inhaltlich geprüft und nie ausgeführt.
  Auch eine `.php`-Datei ist für den Preview-Host nur eine unbekannte Endung
  und geht als Download hinaus (ADR 0003). nginx führt in diesen Ordnern
  nichts aus.

Scheitert irgendetwas, löscht der Job **seinen eigenen** Arbeitsordner und
das ZIP. Der Upload bekommt den Status `failed` mit einer festen deutschen
Meldung, die keinen Pfad und keinen Dateinamen aus dem Archiv enthält. Die
Vorschau bleibt unverändert.

### Löschen

Damit die Platte nicht vollläuft, löscht Smallgate alte Uploads. Das ist die
zweite, eng begrenzte Ausnahme von ADR 0002:

- Gelöscht werden nur Ordner, die in `preview_uploads` stehen. Ihr Pfad wird
  aus den IDs berechnet und nie aus einer gespeicherten Zeichenkette gelesen.
- Ein Ordner, auf den `previews.target` gerade zeigt, wird nie gelöscht.
- Vor dem Löschen prüft der Job: `realpath()` im Wurzelverzeichnis, kein
  Symlink auf dem Weg dorthin. Beim rekursiven Löschen folgt er keinem Link
  (`lstat`).
- Behalten werden der aktuelle und die zwei vorherigen Uploads. Das reicht,
  um eine ältere Fassung zu vergleichen. Ein Archiv ist es nicht.
- Wird eine Vorschau gelöscht, entfernt der Job ihren Ordner
  `vorschauen/<preview-id>/` auf dieselbe Weise.
- Ordner, die per SFTP oder rsync entstanden sind, rührt Smallgate nie an.

### Datenmodell

Neue Tabelle `preview_uploads` (ULID):

- `preview_id`: Fremdschlüssel. Kein `CASCADE`, die Vorschau räumt ihre
  Ordner vorher selbst auf.
- `user_id`: wer hochgeladen hat, `SET NULL` beim Löschen des Benutzers.
- `status`: `pending`, `extracting`, `ready`, `failed`, `removed`, per
  `CHECK` begrenzt.
- `size_bytes`, `file_count`, `error`, `created_at`, `finished_at`.
- Partieller Unique-Index auf `preview_id` für die Status `pending` und
  `extracting`.

Keine dieser Spalten ist `$fillable`. Der Originalname des ZIPs wird nicht
gespeichert.

### Berechtigung und Protokoll

- `PreviewPolicy::upload`: nur Admins, nur für statische Vorschauen. Kein
  `Gate::before`.
- Das Aktivitätsprotokoll hält fest: Upload gestartet, abgeschlossen (mit
  Größe und Dateizahl), gescheitert. Ins Log gelangen nur IDs und ein
  Fehlercode, nie Pfade oder Dateinamen aus dem Archiv.
- Der Fortschritt wird angezeigt wie beim Anlegen des Ordners (ADR 0002): Die
  Seite fragt selbst erneut ab, ohne JavaScript lädt ein Link sie neu.

### Größengrenzen auf dem Weg dorthin

Heute gelten in Entwicklung und Produktion 16 MB (`docker/php/php.ini`, in
beiden Stages, und `client_max_body_size` in nginx).

- **nginx:** `client_max_body_size` bleibt bei 16 MB. Nur die Upload-Route
  bekommt einen eigenen `location`-Block mit 66 MB (64 MB ZIP plus
  Formularfelder). nginx liest den Request-Body in dem Block, der ihn an PHP
  übergibt. Deshalb braucht die Upload-Route einen eigenen
  `fastcgi_pass`-Block. Ein `try_files` auf `/index.php` würde wieder die
  16 MB des PHP-Blocks anwenden.
- **PHP:** `upload_max_filesize` (64 MB) und `post_max_size` (66 MB) gelten
  global, weil PHP den Request liest, bevor Laravel routet. Dass nur die
  Upload-Route so große Requests überhaupt erreichen, stellt nginx sicher.
- **Image:** Die PHP-Erweiterung `zip` kommt ins Image, in beiden Stages
  (`dev` und `prod`).

## Nicht entschieden

- **Upload durch Kunden.** Ausgeschlossen. Kunden laden nichts hoch.
- **Rückkehr zu einer älteren Fassung per Knopf.** Die Ordner wären da, aber
  die Oberfläche dafür gehört nicht ins MVP.
- **Upstream-Vorschauen.** Nicht betroffen.

## Konsequenzen

- Smallgate schreibt und löscht Dateien unterhalb von
  `PROJECT_DIRECTORY_ROOT`, aber nur in Ordnern, die es selbst angelegt und
  in der Datenbank verzeichnet hat. ADR 0002 bekommt einen Verweis darauf.
- Per rsync gepflegte Vorschauen funktionieren unverändert weiter. Beide Wege
  lassen sich mischen: Ein Upload setzt nur das Ziel um.
- Der Worker braucht Schreibrechte im Projektordner, wie für ADR 0002 schon
  heute. Der `app`-Container braucht dort nur Leserechte.
- `CLAUDE.md` bekommt die Regeln: Entpacken nur über den Job, nie
  `extractTo()`, Löschen nur verzeichneter Ordner, Änderungen nur mit
  begleitenden Tests.

## Umsetzung

1. `zip`-Erweiterung im Image, Grenzen in `config/previews.php`, PHP- und
   nginx-Limits. *(erledigt)*
2. Migration `preview_uploads`, Modell, `PreviewPolicy::upload`. *(erledigt)*
3. Entpacker als eigene Klasse mit Unit-Tests. Echte ZIPs, im Test erzeugt:
   - Zip-Slip (`../`, absolut, Backslash, Laufwerksbuchstabe), Symlink-Eintrag,
   - Zip-Bombe (Größe und Dateizahl), fehlende `index.html`, Oberordner,
   - versteckte und `__MACOSX`-Einträge.
4. Job `ExtractPreviewUpload` mit Umschalten, Versionierung und Aufräumen.
   Feature-Tests: gleichzeitiger zweiter Upload, Fehler lässt die Vorschau
   unverändert, Löschen nur verzeichneter Ordner, aktuelles Ziel nie gelöscht.
5. Formular und Statusanzeige im Admin-Bereich, Aktivitätsprotokoll.
6. README, `docs/sicherer-betrieb.md`, ADR 0002 und `CLAUDE.md`.
