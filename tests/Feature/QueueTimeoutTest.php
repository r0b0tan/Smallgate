<?php

/**
 * A job still running when its queue's retry_after runs out is handed to a
 * second worker as well. With tries = 1 that worker marks it failed at once
 * -- failed() runs, a thumbnail shows "Fehlgeschlagen" -- while the first one
 * is still at work. Laravel's rule against it: every timeout, the worker's and
 * each job's own, stays below retry_after.
 *
 * With the single worker both compose files start, this cannot happen yet.
 * These tests keep it that way once someone runs a second one.
 */

use App\Jobs\GeneratePreviewThumbnail;

/** Seconds between a job's timeout and retry_after, for the worker to clean up. */
const QUEUE_TIMEOUT_MARGIN = 10;

function retryAfter(): int
{
    return (int) config('queue.connections.database.retry_after');
}

it('keeps the worker timeout below retry_after', function (string $file) {
    // Jobs without a timeout of their own -- the queued mails -- get this one.
    preg_match('/queue:work\b.*--timeout=(\d+)/', file_get_contents(base_path($file)), $match);

    expect($match)->not->toBeEmpty()
        ->and((int) $match[1])->toBeLessThanOrEqual(retryAfter() - QUEUE_TIMEOUT_MARGIN);
})->with(['compose.yaml', 'compose.prod.yaml']);

it('keeps every job timeout below retry_after', function (string $class) {
    $timeout = (new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? null;

    expect($timeout)->not->toBeNull()
        ->and($timeout)->toBeLessThanOrEqual(retryAfter() - QUEUE_TIMEOUT_MARGIN);
})->with(fn () => array_map(
    fn (string $file) => 'App\\Jobs\\'.basename($file, '.php'),
    glob(__DIR__.'/../../app/Jobs/*.php'),
));

it('gives the screenshot browser less time than the thumbnail job has', function () {
    // PreviewScreenshotter allows the browser its timeout plus 15 seconds.
    $browser = (int) config('previews.thumbnails.timeout_seconds') + 15;
    $job = (new ReflectionClass(GeneratePreviewThumbnail::class))->getDefaultProperties()['timeout'];

    expect($browser)->toBeLessThan($job);
});
