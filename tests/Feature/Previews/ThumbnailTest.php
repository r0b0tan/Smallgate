<?php

/**
 * Preview thumbnails: taken in the background when a version is published,
 * stored against exactly that version, never blocking the customer when they
 * fail, and protected exactly like the preview they show.
 */

use App\Enums\PreviewStatus;
use App\Enums\PreviewTargetType;
use App\Enums\ThumbnailStatus;
use App\Jobs\GeneratePreviewThumbnail;
use App\Models\Customer;
use App\Models\Preview;
use App\Models\Project;
use App\Services\Previews\PreviewScreenshotter;
use App\Services\Previews\PreviewTargetGuard;
use App\Services\Previews\ThumbnailFailed;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const JPEG_BYTES = "\xFF\xD8\xFF\xE0fake-jpeg";

beforeEach(function () {
    Storage::fake('local');

    // A real, allow-listed directory, so the static target exists.
    $this->root = storage_path('framework/testing/previews');
    @mkdir($this->root.'/gasthaus', 0777, true);
    config(['previews.allowed_roots' => [$this->root]]);
});

afterEach(function () {
    @rmdir($this->root.'/gasthaus');
});

function livePreview(array $attributes = []): Preview
{
    return Preview::factory()->available()->create([
        'target' => storage_path('framework/testing/previews/gasthaus'),
        ...$attributes,
    ]);
}

/** A screenshotter that "takes" a picture without a browser. */
function fakeScreenshotter(?string $failWith = null): void
{
    app()->instance(PreviewScreenshotter::class, new class($failWith) extends PreviewScreenshotter
    {
        public function __construct(private readonly ?string $failWith)
        {
            parent::__construct(app(PreviewTargetGuard::class));
        }

        public function capture(Preview $preview, string $outputPath): void
        {
            if ($this->failWith !== null) {
                throw new ThumbnailFailed($this->failWith);
            }

            file_put_contents($outputPath, JPEG_BYTES);
        }
    });
}

/* ------------------------------------------------------------- triggers */

it('queues a thumbnail for the new version when a preview is provisioned', function () {
    Queue::fake();
    $admin = $this->admin();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->create([
        'hostname' => 'gasthaus.'.config('previews.base_domain'),
        'target' => $this->root.'/gasthaus',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.provision', [$project, $preview]))
        ->assertSessionHas('status');

    $preview->refresh();

    expect($preview->status)->toBe(PreviewStatus::Available)
        ->and($preview->version)->toBe(1)
        ->and($preview->thumbnail_status)->toBe(ThumbnailStatus::Pending);

    Queue::assertPushed(GeneratePreviewThumbnail::class,
        fn ($job) => $job->previewId === $preview->id && $job->version === 1);

    // Re-provisioning publishes the next version and asks for a new picture.
    $this->actingAs($admin)->post(route('admin.projects.previews.provision', [$project, $preview]));

    expect($preview->fresh()->version)->toBe(2);
    Queue::assertPushed(GeneratePreviewThumbnail::class, fn ($job) => $job->version === 2);
});

it('queues nothing when provisioning fails', function () {
    Queue::fake();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->create();   // no hostname, no target

    $this->actingAs($this->admin())
        ->post(route('admin.projects.previews.provision', [$project, $preview]))
        ->assertSessionHas('error');

    expect($preview->fresh()->version)->toBe(0);
    Queue::assertNothingPushed();
});

it('does not take screenshots on page views', function () {
    Queue::fake();
    Process::fake();
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $preview = livePreview(['project_id' => Project::factory()->for_customer($customer)->create()->id]);

    $this->actingAs($user)->get(route('portal.dashboard'))->assertOk();
    $this->actingAs($user)->get(route('portal.projects.show', $preview->project_id))->assertOk();

    Queue::assertNothingPushed();
    Process::assertNothingRan();
});

it('lets an administrator regenerate the thumbnail of a live preview only', function () {
    Queue::fake();
    $admin = $this->admin();
    $preview = livePreview();
    $draft = Preview::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.thumbnail.regenerate', [$preview->project_id, $preview]))
        ->assertSessionHas('status');

    Queue::assertPushed(GeneratePreviewThumbnail::class, fn ($job) => $job->previewId === $preview->id);

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.thumbnail.regenerate', [$draft->project_id, $draft]))
        ->assertSessionHas('error');

    Queue::assertPushed(GeneratePreviewThumbnail::class, 1);

    // Customers do not even see that the action exists.
    $this->flushSession();
    $this->actingAs($this->customerUser())
        ->post(route('admin.projects.previews.thumbnail.regenerate', [$preview->project_id, $preview]))
        ->assertNotFound();
});

/* ----------------------------------------------------- version pinning */

it('stores the picture against the version it shows and replaces the old one', function () {
    fakeScreenshotter();
    $preview = livePreview(['version' => 2]);
    $provisionedAt = $preview->updated_at;

    Storage::disk('local')->put("preview-thumbnails/{$preview->id}/v1-old.jpg", JPEG_BYTES);

    (new GeneratePreviewThumbnail($preview->id, 2))->handle(app(PreviewScreenshotter::class));

    $preview->refresh();

    expect($preview->thumbnail_status)->toBe(ThumbnailStatus::Ready)
        ->and($preview->thumbnail_version)->toBe(2)
        ->and($preview->hasCurrentThumbnail())->toBeTrue()
        ->and(Storage::disk('local')->exists($preview->thumbnail_path))->toBeTrue()
        ->and(Storage::disk('local')->files("preview-thumbnails/{$preview->id}"))->toBe([$preview->thumbnail_path])
        // A thumbnail is not a configuration change.
        ->and($preview->updated_at->equalTo($provisionedAt))->toBeTrue()
        ->and($preview->needsProvisioning())->toBeFalse();
});

it('skips a job whose version has been superseded', function () {
    Process::fake();
    $preview = livePreview(['version' => 3]);

    (new GeneratePreviewThumbnail($preview->id, 2))->handle(app(PreviewScreenshotter::class));

    Process::assertNothingRan();
    expect($preview->fresh()->thumbnail_path)->toBeNull();
});

it('discards a picture when a new version went live while it was taken', function () {
    $preview = livePreview();

    app()->instance(PreviewScreenshotter::class, new class extends PreviewScreenshotter
    {
        public function __construct() {}

        public function capture(Preview $preview, string $outputPath): void
        {
            file_put_contents($outputPath, JPEG_BYTES);
            // The administrator re-provisions in the meantime.
            Preview::query()->whereKey($preview->id)->toBase()->update(['version' => 2]);
        }
    });

    (new GeneratePreviewThumbnail($preview->id, 1))->handle(app(PreviewScreenshotter::class));

    expect($preview->fresh()->thumbnail_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('treats a thumbnail of an older version as stale', function () {
    $preview = livePreview(['version' => 2]);
    $preview->forceFill([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_version' => 1,
        'thumbnail_path' => 'preview-thumbnails/x/v1.jpg',
    ])->save();

    expect($preview->hasCurrentThumbnail())->toBeFalse()
        ->and($preview->hasStaleThumbnail())->toBeTrue();
});

/* --------------------------------------------------------- failure */

it('marks a failure, logs no target, and leaves the preview usable', function () {
    fakeScreenshotter('timeout');
    Log::spy();

    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();
    $preview = livePreview(['project_id' => $project->id, 'name' => 'Entwurf 2']);

    (new GeneratePreviewThumbnail($preview->id, 1))->handle(app(PreviewScreenshotter::class));

    expect($preview->fresh()->thumbnail_status)->toBe(ThumbnailStatus::Failed);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($preview) {
        return $context === ['preview_id' => $preview->id, 'version' => 1, 'reason' => 'timeout']
            && ! str_contains(json_encode($context), (string) $preview->target);
    })->once();

    // The card falls back to a placeholder; the draft is still one click away.
    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Website-Entwurf')
        ->assertSee('Entwurf ansehen')
        ->assertDontSee(route('portal.previews.thumbnail', [$project, $preview]), escape: false);

    $this->actingAs($user)->get(route('portal.previews.show', [$project, $preview]))
        ->assertRedirectContains('https://'.$preview->hostname.'/__smallgate/zugang?token=');
});

it('marks the thumbnail failed when the worker kills the job', function () {
    $preview = livePreview();
    $preview->forceFill(['thumbnail_status' => ThumbnailStatus::Pending])->save();

    (new GeneratePreviewThumbnail($preview->id, 1))->failed(new RuntimeException('timed out'));

    expect($preview->fresh()->thumbnail_status)->toBe(ThumbnailStatus::Failed);
});

/* ---------------------------------------------- what the browser may open */

it('never starts the browser for a target outside the allowlist', function () {
    Process::fake();
    $preview = livePreview();
    $preview->forceFill(['target' => '/etc'])->saveQuietly();

    expect(fn () => app(PreviewScreenshotter::class)->capture($preview, '/tmp/x.jpg'))
        ->toThrow(ThumbnailFailed::class, 'target_rejected');

    $preview->forceFill(['target' => $this->root.'/gasthaus/../../../../etc'])->saveQuietly();

    expect(fn () => app(PreviewScreenshotter::class)->capture($preview, '/tmp/x.jpg'))
        ->toThrow(ThumbnailFailed::class, 'target_rejected');

    Process::assertNothingRan();
});

it('refuses an allow-listed upstream host that resolves to a private address', function (string $address) {
    Process::fake();
    $preview = livePreview([
        'target_type' => PreviewTargetType::UpstreamUrl,
        'target' => 'https://staging.clickit-digital.test/entwurf',
    ]);

    $screenshotter = new class(app(PreviewTargetGuard::class), $address) extends PreviewScreenshotter
    {
        public function __construct($guard, private readonly string $address)
        {
            parent::__construct($guard);
        }

        protected function resolve(string $host): array
        {
            return [$this->address];
        }
    };

    expect(fn () => $screenshotter->capture($preview, '/tmp/x.jpg'))
        ->toThrow(ThumbnailFailed::class, 'address_not_public');

    Process::assertNothingRan();
})->with(['127.0.0.1', '10.0.0.5', '169.254.169.254', '192.168.1.10', '100.64.0.1']);

it('pins an upstream target to the checked address and passes it on stdin', function () {
    $output = storage_path('framework/testing/upstream.jpg');

    Process::fake(function (PendingProcess $process) {
        file_put_contents(json_decode($process->input, true)['output'], JPEG_BYTES);

        return Process::result('{"ok":true}');
    });

    $preview = livePreview([
        'target_type' => PreviewTargetType::UpstreamUrl,
        'target' => 'https://staging.clickit-digital.test/entwurf',
    ]);

    $screenshotter = new class(app(PreviewTargetGuard::class)) extends PreviewScreenshotter
    {
        protected function resolve(string $host): array
        {
            return ['93.184.215.14'];
        }
    };

    $screenshotter->capture($preview, $output);

    Process::assertRan(function (PendingProcess $process) {
        $spec = json_decode($process->input, true);

        return $spec['mode'] === 'upstream'
            && $spec['host'] === 'staging.clickit-digital.test'
            && $spec['address'] === '93.184.215.14'
            && $spec['viewport'] === ['width' => 1440, 'height' => 900]
            // Chromium's own sandbox is on unless explicitly switched off.
            && $spec['sandbox'] === true
            // The target travels on stdin, not on the command line.
            && ! str_contains(implode(' ', (array) $process->command), 'staging.clickit-digital.test');
    });

    @unlink($output);
});

it('starts the browser without the worker\'s secrets', function () {
    putenv('SMALLGATE_TEST_SECRET=nicht-fuer-den-browser');

    Process::fake(fn () => Process::result(output: '{"ok":false,"error":"timeout"}', exitCode: 1));

    try {
        app(PreviewScreenshotter::class)->capture(livePreview(), '/tmp/x.jpg');
    } catch (ThumbnailFailed) {
        // Only the command line matters here.
    } finally {
        putenv('SMALLGATE_TEST_SECRET');
    }

    Process::assertRan(function (PendingProcess $process) {
        $command = (array) $process->command;
        $node = array_search((string) config('previews.thumbnails.node'), $command, true);
        $inherited = array_slice($command, 2, $node - 2);

        return array_slice($command, 0, 2) === ['env', '-i']
            // Nothing but the allowlisted names is handed on ...
            && collect($inherited)->every(fn (string $pair) => in_array(
                Str::before($pair, '='), ['PATH', 'HOME', 'TMPDIR', 'LANG', 'TZ'], true,
            ))
            // ... and neither a secret of the worker nor the app key.
            && ! str_contains(implode(' ', $command), 'nicht-fuer-den-browser')
            && ! str_contains(implode(' ', $command), (string) config('app.key'))
            && $process->environment === [];
    });
});

it('passes on only the script\'s own error code', function () {
    Process::fake(fn () => Process::result(
        output: '{"ok":false,"error":"timeout"}',
        errorOutput: 'page.goto: Timeout at https://secret.example/path?token=abc',
        exitCode: 1,
    ));

    expect(fn () => app(PreviewScreenshotter::class)->capture(livePreview(), '/tmp/x.jpg'))
        ->toThrow(fn (ThumbnailFailed $e) => expect($e->getMessage())->toBe('timeout'));
});

it('rejects output that is not a JPEG', function () {
    $output = storage_path('framework/testing/not-a-jpeg.jpg');

    Process::fake(function (PendingProcess $process) {
        file_put_contents(json_decode($process->input, true)['output'], '<svg/>');

        return Process::result('{"ok":true}');
    });

    expect(fn () => app(PreviewScreenshotter::class)->capture(livePreview(), $output))
        ->toThrow(ThumbnailFailed::class, 'no_image');

    @unlink($output);
});

/* ----------------------------------------------------------- access */

it('serves the current thumbnail to the customer it belongs to', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();
    $preview = livePreview(['project_id' => $project->id]);
    $preview->forceFill([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_version' => 1,
        'thumbnail_path' => "preview-thumbnails/{$preview->id}/v1.jpg",
        'thumbnail_generated_at' => now(),
    ])->save();
    Storage::disk('local')->put($preview->thumbnail_path, JPEG_BYTES);

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertSee(route('portal.previews.thumbnail', [$project, $preview]), escape: false);

    $response = $this->actingAs($user)->get(route('portal.previews.thumbnail', [$project, $preview]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->streamedContent())->toBe(JPEG_BYTES);
});

it('protects thumbnails like the preview itself', function () {
    $mine = Customer::factory()->create();
    $user = $this->customerUser($mine);
    $myProject = Project::factory()->for_customer($mine)->create();

    $foreign = livePreview();
    $foreign->forceFill([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_version' => 1,
        'thumbnail_path' => "preview-thumbnails/{$foreign->id}/v1.jpg",
    ])->save();
    Storage::disk('local')->put($foreign->thumbnail_path, JPEG_BYTES);

    $this->actingAs($user)
        ->get(route('portal.previews.thumbnail', [$foreign->project_id, $foreign]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('portal.previews.thumbnail', [$myProject, $foreign]))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('admin.projects.previews.thumbnail', [$foreign->project_id, $foreign]))
        ->assertNotFound();

    // The storage path itself is not a way in either.
    expect($this->actingAs($user)->get('/storage/'.$foreign->thumbnail_path)->status())->toBeIn([403, 404]);

    auth()->logout();
    $this->flushSession();

    $this->get(route('portal.previews.thumbnail', [$foreign->project_id, $foreign]))
        ->assertRedirect(route('login'));

});

it('never serves a stale or unreleased thumbnail to the customer', function () {
    $customer = Customer::factory()->create();
    $user = $this->customerUser($customer);
    $project = Project::factory()->for_customer($customer)->create();

    $stale = livePreview(['project_id' => $project->id, 'version' => 2]);
    $stale->forceFill([
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_version' => 1,
        'thumbnail_path' => "preview-thumbnails/{$stale->id}/v1.jpg",
    ])->save();
    Storage::disk('local')->put($stale->thumbnail_path, JPEG_BYTES);

    $disabled = livePreview(['project_id' => $project->id]);
    $disabled->forceFill([
        'status' => PreviewStatus::Disabled,
        'thumbnail_status' => ThumbnailStatus::Ready,
        'thumbnail_version' => 1,
        'thumbnail_path' => "preview-thumbnails/{$disabled->id}/v1.jpg",
    ])->save();
    Storage::disk('local')->put($disabled->thumbnail_path, JPEG_BYTES);

    $this->actingAs($user)->get(route('portal.previews.thumbnail', [$project, $stale]))->assertNotFound();
    $this->actingAs($user)->get(route('portal.previews.thumbnail', [$project, $disabled]))->assertNotFound();

    // The administrator still sees the stale one, marked as such.
    $this->flushSession();
    $admin = $this->admin();
    $this->actingAs($admin)
        ->get(route('admin.projects.previews.thumbnail', [$project, $stale]))
        ->assertOk();
    $this->actingAs($admin)
        ->get(route('admin.projects.show', $project))
        ->assertSee('Veraltet (Version 1)');
});

it('removes the pictures together with the preview', function () {
    $preview = livePreview();
    Storage::disk('local')->put("preview-thumbnails/{$preview->id}/v1.jpg", JPEG_BYTES);

    $preview->delete();

    expect(Storage::disk('local')->exists("preview-thumbnails/{$preview->id}"))->toBeFalse();
});
