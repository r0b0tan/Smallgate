<?php

namespace App\Services\Previews;

use RuntimeException;

/**
 * An uploaded draft that was not unpacked (ADR 0004). Carries a reason code
 * for the log and a fixed German message for the administrator -- never a
 * path or a name taken from the archive.
 */
class PreviewArchiveRejected extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function germanMessage(): string
    {
        $limits = (array) config('previews.uploads');
        $mb = fn (string $key) => intdiv((int) ($limits[$key] ?? 0), 1024 * 1024);

        return match ($this->reason) {
            'not_a_zip' => 'Die Datei ist kein lesbares ZIP-Archiv.',
            'empty' => 'Das ZIP-Archiv ist leer.',
            'zip_too_large' => 'Das ZIP-Archiv ist größer als '.$mb('max_zip_bytes').' MB.',
            'unsafe_path' => 'Das Archiv enthält einen unzulässigen Pfad, etwa mit „..“, einem absoluten Pfad oder einem Backslash.',
            'link' => 'Das Archiv enthält einen symbolischen Link. Links werden aus Sicherheitsgründen nicht entpackt.',
            'special_file' => 'Das Archiv enthält einen Eintrag, der weder Datei noch Ordner ist.',
            'conflict' => 'Das Archiv enthält einen Namen doppelt oder zugleich als Datei und als Ordner.',
            'path_too_long' => 'Das Archiv enthält einen Pfad mit mehr als '.($limits['max_path_length'] ?? 0).' Zeichen.',
            'too_deep' => 'Das Archiv ist tiefer als '.($limits['max_depth'] ?? 0).' Ordnerebenen verschachtelt.',
            'too_many_files' => 'Das Archiv enthält mehr als '.number_format((int) ($limits['max_files'] ?? 0), 0, ',', '.').' Dateien.',
            'too_large_unpacked' => 'Entpackt wäre der Entwurf größer als '.$mb('max_extracted_bytes').' MB.',
            'no_index' => 'Im obersten Ordner des Archivs fehlt eine index.html.',
            'no_space' => 'Auf dem Server ist nicht genug Platz frei.',
            'corrupt' => 'Das Archiv ist beschädigt.',
            'no_directory' => 'Der Projektordner ist noch nicht angelegt. Bitte zuerst „Ordner anlegen“.',
            'not_allowed' => 'Der Projektordner liegt außerhalb der freigegebenen Wurzelverzeichnisse (PREVIEW_ALLOWED_ROOTS).',
            'job_failed' => 'Das Entpacken wurde abgebrochen.',
            default => 'Die Dateien konnten nicht geschrieben werden. Darf der Queue-Worker in den Projektordner schreiben?',
        };
    }
}
