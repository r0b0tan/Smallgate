<?php

use App\Enums\PreviewStatus;
use App\Enums\ThumbnailStatus;
use App\Jobs\GeneratePreviewThumbnail;
use App\Models\Preview;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Queue thumbnails for every available preview whose current version has
 * none yet -- after the first deployment of thumbnails, or after the browser
 * was missing. --all also redoes the ones that exist.
 */
Artisan::command('previews:thumbnails {--all : Auch vorhandene Vorschaubilder neu erstellen}', function () {
    if (! config('previews.thumbnails.enabled')) {
        $this->error('Vorschaubilder sind in der Konfiguration abgeschaltet.');

        return 1;
    }

    $count = 0;

    Preview::query()
        ->where('status', PreviewStatus::Available)
        ->where('version', '>=', 1)
        ->each(function (Preview $preview) use (&$count) {
            if (! $this->option('all') && $preview->hasCurrentThumbnail()) {
                return;
            }

            // Query builder on purpose: thumbnail state must not move updated_at.
            Preview::query()->whereKey($preview->id)->toBase()
                ->update(['thumbnail_status' => ThumbnailStatus::Pending->value]);

            GeneratePreviewThumbnail::for($preview);
            $count++;
        });

    $this->info("{$count} Vorschaubild(er) in die Warteschlange gestellt.");

    return 0;
})->purpose('Vorschaubilder für verfügbare Vorschauen erzeugen');
