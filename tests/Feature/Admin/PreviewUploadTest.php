<?php

/**
 * "Entwurf hochladen" on the project page (ADR 0004): who may upload, what
 * the request accepts and stores, and what the page shows meanwhile. The
 * queue is synchronous in tests, so a valid upload is unpacked at once.
 */

use App\Enums\ActivityAction;
use App\Enums\DirectoryStatus;
use App\Enums\PreviewStatus;
use App\Enums\PreviewUploadStatus;
use App\Jobs\ExtractPreviewUpload;
use App\Jobs\GeneratePreviewThumbnail;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\PreviewUpload;
use App\Models\Project;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/smallgate-upload-form-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/holzmann/website', 0777, true);

    config([
        'smallgate.project_directories.root' => $this->root,
        'previews.allowed_roots' => [$this->root],
    ]);

    Storage::fake(config('previews.uploads.disk'));
    Queue::fake([GeneratePreviewThumbnail::class]);

    $this->customer = Customer::factory()->create(['slug' => 'holzmann']);
    $this->project = Project::factory()->for_customer($this->customer)->create(['slug' => 'website']);
    $this->project->directory = 'holzmann/website';
    $this->project->directory_status = DirectoryStatus::Created;
    $this->project->directory_created_at = now();
    $this->project->save();

    $this->admin = $this->admin();
    $this->preview = Preview::factory()->for_project($this->project)->create([
        'name' => 'Entwurf Startseite',
        'hostname' => 'holzmann.'.config('previews.base_domain'),
    ]);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

/**
 * @param  array<string, string>  $files
 */
function draftZip(string $root, array $files = ['index.html' => '<h1>Entwurf</h1>'], string $name = 'kunde-holzmann-entwurf.zip'): UploadedFile
{
    $path = $root.'/'.bin2hex(random_bytes(4)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($files as $file => $content) {
        $zip->addFromString($file, $content);
    }

    $zip->close();

    return new UploadedFile($path, $name, 'application/zip', null, true);
}

/**
 * A real file on disk: Laravel's fake files report a MIME type from their
 * name, while a real upload is judged by its content.
 */
function namedFile(string $root, string $name, string $content): UploadedFile
{
    $path = $root.'/'.bin2hex(random_bytes(4));
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function uploadRoute(Project $project, Preview $preview): string
{
    return route('admin.projects.previews.upload', [$project, $preview]);
}

it('takes a zip, unpacks it and shows the result on the project page', function () {
    $this->actingAs($this->admin)
        ->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root)])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHas('status', 'Der Entwurf wird entpackt.');

    $upload = PreviewUpload::query()->sole();

    expect($upload->status)->toBe(PreviewUploadStatus::Ready)
        ->and($upload->user_id)->toBe($this->admin->id)
        ->and($this->preview->fresh()->target)->toEndWith('/vorschauen/'.$this->preview->id.'/'.$upload->id);

    expect(Activity::query()->where('action', ActivityAction::PreviewUploadStarted)->sole()->actor_id)
        ->toBe($this->admin->id);

    $this->get(route('admin.projects.show', $this->project))
        ->assertOk()
        ->assertSee('Zuletzt hochgeladen am')
        ->assertSee('1 Datei,');
});

it('keeps the archive\'s own name out of storage, database and log', function () {
    Queue::fake([ExtractPreviewUpload::class, GeneratePreviewThumbnail::class]);

    $this->actingAs($this->admin)
        ->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root)]);

    $upload = PreviewUpload::query()->sole();

    expect(Storage::disk(config('previews.uploads.disk'))->allFiles())->toBe([$upload->archivePath()])
        ->and(json_encode(PreviewUpload::query()->first()->toArray()))->not->toContain('kunde-holzmann')
        ->and(json_encode(Activity::query()->get()->toArray()))->not->toContain('kunde-holzmann');

    Queue::assertPushed(ExtractPreviewUpload::class, fn ($job) => $job->uploadId === $upload->id && $job->actorId === $this->admin->id);
});

it('refuses what is not a zip, or too large', function (Closure $file, Closure $configure) {
    $configure();

    $this->actingAs($this->admin)
        ->from(route('admin.projects.show', $this->project))
        ->post(uploadRoute($this->project, $this->preview), ['archiv' => $file($this->root)])
        ->assertRedirect(route('admin.projects.show', $this->project))
        ->assertSessionHasErrors('archiv', errorBag: 'upload_'.$this->preview->id);

    expect(PreviewUpload::query()->count())->toBe(0);
})->with([
    'nothing' => [fn () => null, fn () => null],
    'a text file named .zip' => [fn (string $root) => namedFile($root, 'entwurf.zip', 'kein zip'), fn () => null],
    'a pdf named .zip' => [fn (string $root) => namedFile($root, 'entwurf.zip', "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n1 0 obj\n<<>>\nendobj\n"), fn () => null],
    'too large' => [fn (string $root) => draftZip($root, ['index.html' => random_bytes(4096)]), fn () => config(['previews.uploads.max_zip_bytes' => 1024])],
]);

it('needs the project folder first', function () {
    $this->project->directory_status = null;
    $this->project->directory = null;
    $this->project->directory_created_at = null;
    $this->project->save();

    $this->actingAs($this->admin)
        ->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root)])
        ->assertSessionHas('error');

    expect(PreviewUpload::query()->count())->toBe(0);

    $this->get(route('admin.projects.show', $this->project))
        ->assertSee('Zum Hochladen zuerst oben den Projektordner anlegen.')
        ->assertDontSee(uploadRoute($this->project, $this->preview), escape: false);
});

it('waits for a folder that is being created and then offers the form', function () {
    $this->project->directory_status = DirectoryStatus::Pending;
    $this->project->directory_created_at = null;
    $this->project->save();

    // Found in the browser: without polling, the form only appeared after a
    // manual reload once the folder was there.
    $html = $this->actingAs($this->admin)
        ->get(route('admin.projects.show', $this->project))
        ->assertSee('Der Projektordner wird gerade angelegt.')
        ->getContent();

    expect($html)->toMatch('/id="preview-upload-'.$this->preview->id.'"[^>]*\sdata-poll\s+data-poll-reload[\s>]/');
});

it('shows every time on the project page in the display time zone', function () {
    // Found in the browser: "hochgeladen 22:33" above "bereitgestellt 20:33".
    config(['smallgate.display_timezone' => 'Europe/Berlin']);
    $this->travelTo(Carbon::parse('2026-10-03 20:33:00', 'UTC'));

    $this->actingAs($this->admin)->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root)]);
    $this->preview->refresh();
    $this->preview->status = PreviewStatus::Available;
    $this->preview->provisioned_at = now();
    $this->preview->save();

    $this->get(route('admin.projects.show', $this->project))
        ->assertSee('Zuletzt hochgeladen am 03.10.2026 22:33')
        ->assertSee('Zuletzt bereitgestellt 03.10.2026 22:33')
        ->assertDontSee('20:33');
});

it('takes one upload per preview at a time', function () {
    // Queued, not run: the first upload stays pending.
    Queue::fake([ExtractPreviewUpload::class, GeneratePreviewThumbnail::class]);

    $this->actingAs($this->admin)->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root)]);

    $this->actingAs($this->admin)
        ->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root)])
        ->assertSessionHas('error', 'Für diese Vorschau wird gerade ein Entwurf entpackt.');

    expect(PreviewUpload::query()->count())->toBe(1);
    Queue::assertPushed(ExtractPreviewUpload::class, 1);
});

it('lets only administrators upload, and only for static previews of the project', function () {
    $upstream = Preview::factory()->for_project($this->project)->upstream()->create();
    $foreign = Preview::factory()->create();

    $this->actingAs($this->customerUser($this->customer))
        ->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root)])
        ->assertNotFound();

    $this->flushSession();

    $this->actingAs($this->admin)
        ->post(uploadRoute($this->project, $upstream), ['archiv' => draftZip($this->root)])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(uploadRoute($this->project, $foreign), ['archiv' => draftZip($this->root)])
        ->assertNotFound();

    expect(PreviewUpload::query()->count())->toBe(0);
});

/* ------------------------------------------------------------ the page */

it('offers the form on static previews only', function () {
    $upstream = Preview::factory()->for_project($this->project)->upstream()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.projects.show', $this->project))
        ->assertSee(uploadRoute($this->project, $this->preview), escape: false)
        ->assertDontSee(uploadRoute($this->project, $upstream), escape: false)
        ->assertSee('enctype="multipart/form-data"', escape: false);
});

it('tells whether the customer will see the upload at once', function (PreviewStatus $status, string $hint) {
    $this->preview->status = $status;
    $this->preview->target = $this->root.'/holzmann/website';
    $this->preview->save();

    $this->actingAs($this->admin)
        ->get(route('admin.projects.show', $this->project))
        ->assertSee($hint);
})->with([
    'live' => [PreviewStatus::Available, 'Der Kunde sieht den neuen Stand sofort als neue Version.'],
    'draft' => [PreviewStatus::Draft, 'Für den Kunden freigeben wie gewohnt mit „Bereitstellen“.'],
]);

it('shows the progress while unpacking and refreshes itself', function () {
    $upload = new PreviewUpload;
    $upload->preview_id = $this->preview->id;
    $upload->status = PreviewUploadStatus::Extracting;
    $upload->created_at = now();
    $upload->save();

    $this->actingAs($this->admin)
        ->get(route('admin.projects.show', $this->project))
        ->assertSee('Entwurf wird entpackt')
        ->assertSee('id="preview-upload-'.$this->preview->id.'"', escape: false)
        ->assertSee('data-poll', escape: false)
        ->assertDontSee(uploadRoute($this->project, $this->preview), escape: false);
});

it('shows why the last upload was rejected', function () {
    $this->actingAs($this->admin)
        ->post(uploadRoute($this->project, $this->preview), ['archiv' => draftZip($this->root, ['seite.html' => 'x'])]);

    expect(PreviewUpload::query()->sole()->status)->toBe(PreviewUploadStatus::Failed);

    $this->get(route('admin.projects.show', $this->project))
        ->assertSee('Im obersten Ordner des Archivs fehlt eine index.html.')
        ->assertSee(uploadRoute($this->project, $this->preview), escape: false);
});
