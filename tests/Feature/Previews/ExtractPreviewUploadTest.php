<?php

/**
 * Unpacking an uploaded draft and switching the preview to it (ADR 0004),
 * against a real temporary project folder: what goes live and when, what a
 * rejection leaves behind, and which folders may ever be deleted.
 */

use App\Enums\ActivityAction;
use App\Enums\DirectoryStatus;
use App\Enums\PreviewStatus;
use App\Enums\PreviewUploadStatus;
use App\Jobs\ExtractPreviewUpload;
use App\Jobs\GeneratePreviewThumbnail;
use App\Jobs\RemovePreviewUploads;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\PreviewUpload;
use App\Models\Project;
use App\Services\Previews\DirectoryRemover;
use App\Services\Previews\PreviewUploadPruner;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/smallgate-uploads-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/holzmann/website', 0777, true);

    config([
        'smallgate.project_directories.root' => $this->root,
        'previews.allowed_roots' => [$this->root],
    ]);

    Storage::fake(config('previews.uploads.disk'));
    Queue::fake([GeneratePreviewThumbnail::class]);

    $customer = Customer::factory()->create(['slug' => 'holzmann']);
    $this->project = Project::factory()->for_customer($customer)->create(['slug' => 'website']);
    $this->project->directory = 'holzmann/website';
    $this->project->directory_status = DirectoryStatus::Created;
    $this->project->directory_created_at = now();
    $this->project->save();

    $this->admin = $this->admin();
    $this->preview = Preview::factory()->for_project($this->project)->create([
        'hostname' => 'holzmann.'.config('previews.base_domain'),
    ]);
    $this->previewDirectory = $this->root.'/holzmann/website/vorschauen/'.$this->preview->id;
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

/**
 * A pending upload whose ZIP waits on the private disk, as the form leaves it.
 *
 * @param  array<string, string>  $files
 */
function pendingUpload(Preview $preview, array $files = ['index.html' => '<h1>Entwurf</h1>']): PreviewUpload
{
    $upload = new PreviewUpload;
    $upload->preview_id = $preview->id;
    $upload->status = PreviewUploadStatus::Pending;
    $upload->created_at = now();
    $upload->save();

    $path = Storage::disk(config('previews.uploads.disk'))->path($upload->archivePath());
    @mkdir(dirname($path), 0777, true);

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();

    return $upload;
}

function unpackUpload(PreviewUpload $upload, ?string $actorId = null): PreviewUpload
{
    ExtractPreviewUpload::dispatchSync($upload->id, $actorId);

    return $upload->fresh();
}

/* ------------------------------------------------------------ the way in */

it('unpacks a draft into its own folder and points the preview at it', function () {
    $upload = unpackUpload(pendingUpload($this->preview, ['index.html' => 'start', 'css/app.css' => 'body{}']), $this->admin->id);
    $folder = $this->previewDirectory.'/'.$upload->id;

    expect($upload->status)->toBe(PreviewUploadStatus::Ready)
        ->and($upload->file_count)->toBe(2)
        ->and($upload->size_bytes)->toBe(strlen('start') + strlen('body{}'))
        ->and($upload->finished_at)->not->toBeNull()
        ->and($this->preview->fresh()->target)->toBe($folder)
        ->and(file_get_contents($folder.'/css/app.css'))->toBe('body{}')
        // Nothing half-written left over, and the ZIP is gone.
        ->and(glob($this->previewDirectory.'/.eingang-*'))->toBe([])
        ->and(Storage::disk(config('previews.uploads.disk'))->exists($upload->archivePath()))->toBeFalse();

    $activity = Activity::query()->where('action', ActivityAction::PreviewUploaded)->sole();

    expect($activity->actor_id)->toBe($this->admin->id)
        ->and($activity->properties)->toEqual(['files' => 2, 'bytes' => 11]);
});

it('only sets the target of a preview that is not released yet', function () {
    unpackUpload(pendingUpload($this->preview));

    $preview = $this->preview->fresh();

    expect($preview->status)->toBe(PreviewStatus::Draft)
        ->and($preview->version)->toBe(0)
        ->and($preview->provisioned_at)->toBeNull();

    Queue::assertNotPushed(GeneratePreviewThumbnail::class);
});

it('publishes a new version of a live preview, like "Bereitstellen"', function () {
    $this->preview->status = PreviewStatus::Available;
    $this->preview->target = $this->root.'/holzmann/website';
    $this->preview->version = 3;
    $this->preview->provisioned_at = now()->subDay();
    $this->preview->save();

    $this->travel(1)->minute();
    $upload = unpackUpload(pendingUpload($this->preview), $this->admin->id);
    $preview = $this->preview->fresh();

    expect($preview->status)->toBe(PreviewStatus::Available)
        ->and($preview->version)->toBe(4)
        ->and($preview->target)->toBe($this->previewDirectory.'/'.$upload->id)
        ->and($preview->provisioned_at->timestamp)->toBe(now()->timestamp)
        ->and($preview->needsProvisioning())->toBeFalse();

    Queue::assertPushed(GeneratePreviewThumbnail::class, fn ($job) => $job->previewId === $preview->id && $job->version === 4);

    expect(Activity::query()->where('action', ActivityAction::PreviewProvisioned)->sole()->properties)
        ->toBe(['version' => 4]);
});

it('runs a pending upload once and leaves every other one alone', function (PreviewUploadStatus $status) {
    $upload = pendingUpload($this->preview);
    $upload->status = $status;
    $upload->size_bytes = 0;
    $upload->file_count = 0;
    $upload->error = 'x';
    $upload->finished_at = now();
    $upload->save();

    unpackUpload($upload);

    expect($upload->fresh()->status)->toBe($status)
        ->and($this->preview->fresh()->target)->toBeNull()
        ->and(file_exists($this->previewDirectory.'/'.$upload->id))->toBeFalse();
})->with([PreviewUploadStatus::Extracting, PreviewUploadStatus::Ready, PreviewUploadStatus::Failed]);

/* ------------------------------------------------------------- rejections */

it('rejects a bad archive and changes nothing', function () {
    $this->preview->target = $this->root.'/holzmann/website';
    $this->preview->save();

    $upload = unpackUpload(pendingUpload($this->preview, ['index.html' => 'x', '../../ausbruch.txt' => 'x']), $this->admin->id);

    expect($upload->status)->toBe(PreviewUploadStatus::Failed)
        ->and($upload->error)->toContain('unzulässigen Pfad')
        ->and($upload->finished_at)->not->toBeNull()
        ->and($this->preview->fresh()->target)->toBe($this->root.'/holzmann/website')
        ->and(file_exists($this->previewDirectory.'/'.$upload->id))->toBeFalse()
        ->and(glob($this->previewDirectory.'/.eingang-*'))->toBe([])
        ->and(file_exists($this->root.'/holzmann/ausbruch.txt'))->toBeFalse()
        ->and(Storage::disk(config('previews.uploads.disk'))->exists($upload->archivePath()))->toBeFalse();

    expect(Activity::query()->where('action', ActivityAction::PreviewUploadFailed)->sole()->actor_id)->toBe($this->admin->id);
});

it('needs the project folder first', function () {
    $this->project->directory_status = DirectoryStatus::Pending;
    $this->project->save();

    $upload = unpackUpload(pendingUpload($this->preview));

    expect($upload->status)->toBe(PreviewUploadStatus::Failed)
        ->and($upload->error)->toContain('Ordner anlegen');
});

it('refuses a project folder outside the allowed roots', function () {
    config(['previews.allowed_roots' => ['/srv/anderswo']]);

    $upload = unpackUpload(pendingUpload($this->preview));

    expect($upload->status)->toBe(PreviewUploadStatus::Failed)
        ->and($upload->error)->toContain('PREVIEW_ALLOWED_ROOTS')
        ->and(file_exists($this->previewDirectory))->toBeFalse();
});

it('refuses to unpack through a symlink in the project folder', function () {
    mkdir($this->root.'/woanders');
    symlink($this->root.'/woanders', $this->root.'/holzmann/website/vorschauen');

    $upload = unpackUpload(pendingUpload($this->preview));

    expect($upload->status)->toBe(PreviewUploadStatus::Failed)
        ->and(scandir($this->root.'/woanders'))->toBe(['.', '..']);
});

it('cleans up after a job that died halfway', function () {
    $upload = pendingUpload($this->preview);
    $upload->status = PreviewUploadStatus::Extracting;
    $upload->save();

    $staging = $this->previewDirectory.'/.eingang-'.$upload->id;
    mkdir($staging, 0777, true);
    file_put_contents($staging.'/index.html', 'halb');

    (new ExtractPreviewUpload($upload->id))->failed(new RuntimeException('timeout'));

    expect($upload->fresh()->status)->toBe(PreviewUploadStatus::Failed)
        ->and($upload->fresh()->error)->toBe('Das Entpacken wurde abgebrochen.')
        ->and(file_exists($staging))->toBeFalse();
});

/* ---------------------------------------------------------- old uploads */

it('keeps the newest uploads and removes the older ones it made itself', function () {
    // Put there by hand, e.g. with rsync: never touched.
    mkdir($this->previewDirectory.'/von-hand', 0777, true);

    $uploads = [];

    foreach (range(1, 4) as $round) {
        $this->travel(1)->minute();
        $uploads[] = unpackUpload(pendingUpload($this->preview));
    }

    expect($uploads[0]->fresh()->status)->toBe(PreviewUploadStatus::Removed)
        ->and(file_exists($this->previewDirectory.'/'.$uploads[0]->id))->toBeFalse();

    foreach (array_slice($uploads, 1) as $kept) {
        expect($kept->fresh()->status)->toBe(PreviewUploadStatus::Ready)
            ->and(is_dir($this->previewDirectory.'/'.$kept->id))->toBeTrue();
    }

    expect(is_dir($this->previewDirectory.'/von-hand'))->toBeTrue()
        ->and($this->preview->fresh()->target)->toBe($this->previewDirectory.'/'.$uploads[3]->id);
});

it('never removes the folder the preview currently shows', function () {
    $this->travel(1)->minute();
    $older = unpackUpload(pendingUpload($this->preview));
    $this->travel(1)->minute();
    $newer = unpackUpload(pendingUpload($this->preview));

    // An administrator pointed the preview back at the older one by hand.
    $this->preview->target = $this->previewDirectory.'/'.$older->id;
    $this->preview->save();

    config(['previews.uploads.keep' => 1]);
    app(PreviewUploadPruner::class)->prune($this->preview->fresh());

    expect($older->fresh()->status)->toBe(PreviewUploadStatus::Ready)
        ->and(is_dir($this->previewDirectory.'/'.$older->id))->toBeTrue()
        ->and($newer->fresh()->status)->toBe(PreviewUploadStatus::Ready);
});

/* ------------------------------------------------------ deleting a preview */

it('removes the unpacked drafts of a deleted preview, and only those', function () {
    $upload = unpackUpload(pendingUpload($this->preview));
    mkdir($this->previewDirectory.'/von-hand');

    $this->actingAs($this->admin)
        ->delete(route('admin.projects.previews.destroy', [$this->project, $this->preview]))
        ->assertRedirect(route('admin.projects.show', $this->project));

    expect(Preview::query()->find($this->preview->id))->toBeNull()
        ->and(PreviewUpload::query()->count())->toBe(0)
        ->and(file_exists($this->previewDirectory.'/'.$upload->id))->toBeFalse()
        // Not empty, so the preview's own folder stays as well.
        ->and(is_dir($this->previewDirectory.'/von-hand'))->toBeTrue();
});

it('removes the preview folder itself once nothing else is in it', function () {
    unpackUpload(pendingUpload($this->preview));

    $this->actingAs($this->admin)->delete(route('admin.projects.previews.destroy', [$this->project, $this->preview]));

    expect(file_exists($this->previewDirectory))->toBeFalse()
        ->and(is_dir($this->root.'/holzmann/website/vorschauen'))->toBeTrue();
});

it('does not delete a preview while a draft is being unpacked', function () {
    $upload = pendingUpload($this->preview);

    $this->actingAs($this->admin)
        ->delete(route('admin.projects.previews.destroy', [$this->project, $this->preview]))
        ->assertSessionHas('error');

    expect($this->preview->fresh())->not->toBeNull()
        ->and($upload->fresh()->status)->toBe(PreviewUploadStatus::Pending);
});

it('skips anything in the removal job that is not an upload id', function () {
    $upload = unpackUpload(pendingUpload($this->preview));
    file_put_contents($this->root.'/holzmann/website/wichtig.txt', 'bleibt');

    (new RemovePreviewUploads($this->project->id, $this->preview->id, ['..', '../../website', $upload->id]))
        ->handle(app(DirectoryRemover::class));

    expect(file_exists($this->previewDirectory.'/'.$upload->id))->toBeFalse()
        ->and(file_get_contents($this->root.'/holzmann/website/wichtig.txt'))->toBe('bleibt');
});
