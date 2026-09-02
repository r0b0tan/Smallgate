<?php

/**
 * Preview targets and hostnames as they behave through the real admin forms,
 * plus the provisioner boundary.
 */

use App\Contracts\PreviewProvisioner;
use App\Enums\PreviewStatus;
use App\Models\Preview;
use App\Models\Project;
use App\Services\Previews\NullPreviewProvisioner;
use Illuminate\Database\QueryException;

it('refuses a preview target outside the allowed roots', function (string $target) {
    $admin = $this->admin();
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.projects.previews.create', $project))
        ->post(route('admin.projects.previews.store', $project), [
            'name' => 'Vorschau',
            'slug' => 'vorschau',
            'hostname' => 'test.'.config('previews.base_domain'),
            'target_type' => 'static_directory',
            'target' => $target,
            'status' => 'available',
        ])
        ->assertSessionHasErrors('target');

    expect(Preview::count())->toBe(0);
})->with([
    '/etc/passwd',
    '/srv/previews/../../etc',
    '/var/www/html/.env',
    '/srv/previews-other/leak',
]);

it('refuses an upstream target that is not allow-listed', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.projects.previews.create', $project))
        ->post(route('admin.projects.previews.store', $project), [
            'name' => 'Vorschau',
            'slug' => 'vorschau',
            'hostname' => 'test.'.config('previews.base_domain'),
            'target_type' => 'upstream_url',
            'target' => 'https://169.254.169.254/latest/meta-data/',
            'status' => 'available',
        ])
        ->assertSessionHasErrors('target');

    expect(Preview::count())->toBe(0);
});

it('refuses a hostname outside the preview base domain', function (string $hostname) {
    $admin = $this->admin();
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.projects.previews.create', $project))
        ->post(route('admin.projects.previews.store', $project), [
            'name' => 'Vorschau',
            'slug' => 'vorschau',
            'hostname' => $hostname,
            'target_type' => 'static_directory',
            'target' => '/srv/previews/test',
            'status' => 'available',
        ])
        ->assertSessionHasErrors('hostname');

    expect(Preview::count())->toBe(0);
})->with([
    'foreign domain' => 'kunde.example.com',
    'the base domain itself' => 'preview.clickit-digital.test',
    'two labels deep' => 'a.b.preview.clickit-digital.test',
    'underscore' => 'kein_unterstrich.preview.clickit-digital.test',
    'trailing hyphen' => 'kunde-.preview.clickit-digital.test',
]);

it('refuses a duplicate preview hostname', function () {
    $admin = $this->admin();
    $existing = Preview::factory()->available()->create();
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.projects.previews.create', $project))
        ->post(route('admin.projects.previews.store', $project), [
            'name' => 'Kollision',
            'slug' => 'kollision',
            'hostname' => $existing->hostname,
            'target_type' => 'static_directory',
            'target' => '/srv/previews/kollision',
            'status' => 'available',
        ])
        ->assertSessionHasErrors('hostname');
});

it('allows a draft preview without hostname or target', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();

    $this->actingAs($admin)->post(route('admin.projects.previews.store', $project), [
        'name' => 'Noch offen',
        'slug' => 'noch-offen',
        'hostname' => '',
        'target_type' => 'static_directory',
        'target' => '',
        'status' => 'draft',
    ])->assertRedirect();

    $preview = Preview::sole();
    expect($preview->status)->toBe(PreviewStatus::Draft)
        ->and($preview->hostname)->toBeNull()
        ->and($preview->target)->toBeNull();
});

it('does not offer a url for a preview that is not available', function () {
    $preview = Preview::factory()->available()->create();

    expect($preview->url())->toBe('https://'.$preview->hostname);

    $preview->forceFill(['status' => PreviewStatus::Disabled])->save();
    expect($preview->fresh()->url())->toBeNull();
});

it('offers administrators a host url in any status while the customer url stays gated', function () {
    $preview = Preview::factory()->available()->create();

    expect($preview->hostUrl())->toBe('https://'.$preview->hostname)
        ->and($preview->url())->toBe('https://'.$preview->hostname);

    // A draft is reachable for the administrator who has to check it, but is
    // never offered to the customer.
    $preview->forceFill(['status' => PreviewStatus::Draft])->save();
    $preview = $preview->fresh();

    expect($preview->hostUrl())->toBe('https://'.$preview->hostname)
        ->and($preview->url())->toBeNull();

    $preview->forceFill(['hostname' => null])->save();
    expect($preview->fresh()->hostUrl())->toBeNull();
});

/* ------------------------------------------------------------- provisioner */

it('binds the null provisioner in the mvp', function () {
    expect(app(PreviewProvisioner::class))->toBeInstanceOf(NullPreviewProvisioner::class);
});

it('marks a valid preview available without touching the server', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create([
        'status' => PreviewStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.provision', [$project, $preview]))
        ->assertRedirect(route('admin.projects.previews.index', $project))
        ->assertSessionHas('status');

    $preview->refresh();
    expect($preview->status)->toBe(PreviewStatus::Available)
        ->and($preview->provisioned_at)->not->toBeNull();
});

it('fails provisioning rather than trusting a bad target', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();

    // A row that somehow got a target outside the allowlist -- the provisioner
    // re-validates instead of assuming the form did its job.
    $preview = Preview::factory()->for_project($project)->available()->create();
    $preview->forceFill(['target' => '/etc/passwd'])->save();

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.provision', [$project, $preview]))
        ->assertSessionHas('error');

    $preview->refresh();
    expect($preview->status)->toBe(PreviewStatus::Failed)
        ->and($preview->provisioned_at)->toBeNull();
});

it('rejects a preview of another project through the nested route', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $otherPreview = Preview::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.projects.previews.edit', [$project, $otherPreview]))
        ->assertNotFound();

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.provision', [$project, $otherPreview]))
        ->assertNotFound();
});

/* --------------------------------------------------- database backstop */

it('refuses an available preview without a target at the database level', function () {
    $project = Project::factory()->create();

    expect(fn () => DB::table('previews')->insert([
        'id' => (string) Str::ulid(),
        'project_id' => $project->id,
        'name' => 'Kaputt',
        'slug' => 'kaputt',
        'hostname' => null,
        'target_type' => 'static_directory',
        'target' => null,
        'status' => 'available',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('refuses an unknown preview status at the database level', function () {
    $project = Project::factory()->create();

    expect(fn () => DB::table('previews')->insert([
        'id' => (string) Str::ulid(),
        'project_id' => $project->id,
        'name' => 'Kaputt',
        'slug' => 'kaputt',
        'target_type' => 'static_directory',
        'status' => 'irgendwas',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
