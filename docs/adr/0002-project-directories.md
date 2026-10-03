# ADR 0002 – Projektordner per Knopfdruck

- **Status:** Angenommen
- **Datum:** 2026-10-03
- **Kontext:** Smallgate MVP, ergänzt ADR 0001

## Kontext

Zu einem neuen Projekt gehört in der Regel ein Ordner auf dem Server, in den
später die Vorschaudateien gelegt werden. Bisher musste ihn jemand von Hand
anlegen. ADR 0001 legt fest, dass Smallgate keinen Serverzustand verändert.
Diese ADR erlaubt davon genau eine, eng begrenzte Ausnahme.

## Entscheidung

Auf der Projektseite gibt es den Knopf „Ordner anlegen“. Er legt
`<kunden-kürzel>/<projekt-kürzel>` unterhalb von `PROJECT_DIRECTORY_ROOT` an
(Standard: `storage/app/previews`).

- **Ohne erhöhte Rechte.** Kein `sudo`, kein setuid-Skript, kein Docker-Socket,
  kein Shell-Kommando. Der Queue-Worker ruft `mkdir` selbst auf. Das
  Wurzelverzeichnis muss bereits existieren und für ihn beschreibbar sein.
  Smallgate legt es nie selbst an.
- **Kein frei wählbarer Pfad.** Der Name entsteht ausschließlich aus den beiden
  Kürzeln, die die Datenbank auf `[a-z0-9-]` beschränkt. Er wird beim ersten
  Klick in `projects.directory` festgeschrieben und ändert sich danach nicht
  mehr, auch nicht bei einer Umbenennung des Projekts. Ein `CHECK` erzwingt
  genau zwei Ebenen.
- **Im Queue-Worker, nicht im Request** (`App\Jobs\CreateProjectDirectory`).
  Der Job legt Ebene für Ebene an, nicht rekursiv. Symbolische Links weist er
  ab. Danach prüft er mit `realpath()`, dass der Ordner im Wurzelverzeichnis
  liegt. Ein bereits vorhandener Ordner gleichen Namens gilt als Erfolg.
- **Nur anlegen.** Kein Löschen, Umbenennen, `chown` oder `chmod`, keine
  vhost-Konfiguration, kein Reload.
- **Idempotent.** Ein zweiter Klick auf einen angelegten Ordner ändert nichts.
  Der Status (`pending`, `created`, `failed`) wird atomar gesetzt, sodass ein
  Doppelklick nur einen Job erzeugt.
- **Nachvollziehbar.** Erfolg und Fehlschlag landen im Aktivitätsprotokoll.
  Ins Log gelangen nur die Projekt-ID und ein Fehlercode, kein Pfad.

Liegt das Wurzelverzeichnis innerhalb von `PREVIEW_ALLOWED_ROOTS`, wird der
angelegte Ordner beim Anlegen der nächsten Vorschau als Ziel vorgeschlagen.
Er läuft dabei wie jede Eingabe durch `PreviewTargetGuard`.

## Betrieb

Soll der Ordner außerhalb des `storage`-Volumes liegen, etwa unter
`/srv/previews`, wohin per SFTP oder rsync deployt wird: das Verzeichnis per
Bind-Mount in `app` und `worker` einhängen, `PROJECT_DIRECTORY_ROOT` darauf
setzen und es einer gemeinsamen Gruppe mit gesetztem setgid-Bit geben. Dann
gehören neue Ordner automatisch dieser Gruppe. Der Worker braucht dafür keine
weiteren Rechte.

Liegt der Ordner auf einem **anderen Server**, reicht diese Lösung nicht. Das
ist eine eigene Entscheidung, etwa SSH mit Forced Command, und gehört in eine
eigene ADR.

## Konsequenzen

- Smallgate verändert jetzt Dateien außerhalb des Projektverzeichnisses, aber
  nur durch Anlegen und nur unterhalb eines vom Betreiber bestimmten Wurzel-
  verzeichnisses.
- Der `NullPreviewProvisioner` bleibt unverändert. Er verändert weiterhin
  keine Serverdateien.
- Die Fortschrittsanzeige fragt die Projektseite selbst erneut ab. Eine
  Status-API gibt es nicht. Ohne JavaScript lädt ein Link die Seite neu.
