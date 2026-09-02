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
        ])
        ->assertSessionHasErrors('hostname');
});

it('completes the subdomain with the configured base domain', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();

    // The form only asks for the label -- the base domain cannot be mistyped
    // because it is never typed.
    $this->actingAs($admin)->post(route('admin.projects.previews.store', $project), [
        'name' => 'Stand KW12',
        'slug' => 'kw12',
        'hostname' => 'holzmann',
        'target_type' => 'static_directory',
        'target' => '/srv/previews/holzmann',
    ])->assertRedirect();

    expect(Preview::sole()->hostname)->toBe('holzmann.'.config('previews.base_domain'));
});

it('never takes the status from the form', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();

    // Releasing a preview is an action, not a form field. A status smuggled
    // into the request must not make it visible to the customer.
    $this->actingAs($admin)->post(route('admin.projects.previews.store', $project), [
        'name' => 'Geschmuggelt',
        'slug' => 'geschmuggelt',
        'hostname' => 'geschmuggelt',
        'target_type' => 'static_directory',
        'target' => '/srv/previews/geschmuggelt',
        'status' => 'available',
    ])->assertRedirect();

    $preview = Preview::sole();
    expect($preview->status)->toBe(PreviewStatus::Draft)
        ->and($preview->url())->toBeNull();

    $this->actingAs($admin)->patch(route('admin.projects.previews.update', [$project, $preview]), [
        'name' => 'Geschmuggelt',
        'slug' => 'geschmuggelt',
        'hostname' => 'geschmuggelt',
        'target_type' => 'static_directory',
        'target' => '/srv/previews/geschmuggelt',
        'status' => 'available',
    ])->assertRedirect();

    expect($preview->fresh()->status)->toBe(PreviewStatus::Draft);
});

it('requires hostname and target once a preview is live', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create();

    // A draft may be incomplete; something the customer is being offered may not.
    $this->actingAs($admin)
        ->from(route('admin.projects.previews.edit', [$project, $preview]))
        ->patch(route('admin.projects.previews.update', [$project, $preview]), [
            'name' => $preview->name,
            'slug' => $preview->slug,
            'hostname' => '',
            'target_type' => 'static_directory',
            'target' => '',
        ])
        ->assertSessionHasErrors(['hostname', 'target']);
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

/* ------------------------------------------------------------------- the ui */

it('manages previews on the project page itself', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create(['name' => 'Stand KW12']);

    // One list, and every action on it -- there is no second preview page to
    // wonder about.
    $this->actingAs($admin)
        ->get(route('admin.projects.show', $project))
        ->assertOk()
        ->assertSee('Stand KW12')
        ->assertSee(route('admin.projects.previews.provision', [$project, $preview]), escape: false)
        ->assertSee(route('admin.projects.previews.disable', [$project, $preview]), escape: false)
        ->assertSee(route('admin.projects.previews.edit', [$project, $preview]), escape: false)
        ->assertSee(route('admin.projects.previews.destroy', [$project, $preview]), escape: false);
});

it('asks only for the subdomain label in the form', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create();

    $label = Str::before($preview->hostname, '.'.config('previews.base_domain'));

    $this->actingAs($admin)
        ->get(route('admin.projects.previews.edit', [$project, $preview]))
        ->assertOk()
        // The label goes in the input, the base domain sits beside it as fixed
        // text -- so it can neither be mistyped nor deleted.
        ->assertSee('value="'.$label.'"', escape: false)
        ->assertSee('.'.config('previews.base_domain'))
        // And the status is not something the form offers to change.
        ->assertDontSee('name="status"', escape: false);
});

it('puts open previews on the administrator dashboard', function () {
    $admin = $this->admin();
    $draft = Preview::factory()->create(['name' => 'Wartet auf Freigabe']);
    $live = Preview::factory()->available()->create(['name' => 'Laeuft schon']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Wartet auf Freigabe')
        ->assertDontSee('Laeuft schon');

    expect($live->needsProvisioning())->toBeFalse();
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
        ->assertRedirect(route('admin.projects.show', $project))
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

it('takes a preview off the portal again without touching the server', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create();

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.disable', [$project, $preview]))
        ->assertRedirect(route('admin.projects.show', $project))
        ->assertSessionHas('status');

    $preview->refresh();
    expect($preview->status)->toBe(PreviewStatus::Disabled)
        ->and($preview->url())->toBeNull()
        // Administrators can still reach it to check what the customer had.
        ->and($preview->hostUrl())->not->toBeNull();
});

it('flags a preview that was edited after it went live', function () {
    $admin = $this->admin();
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create([
        'status' => PreviewStatus::Draft,
    ]);

    $this->actingAs($admin)->post(route('admin.projects.previews.provision', [$project, $preview]));
    expect($preview->fresh()->needsProvisioning())->toBeFalse();

    $this->travel(1)->minutes();

    $this->actingAs($admin)->patch(route('admin.projects.previews.update', [$project, $preview]), [
        'name' => 'Anderer Name',
        'slug' => $preview->slug,
        'hostname' => $preview->hostname,
        'target_type' => 'static_directory',
        'target' => $preview->target,
    ])->assertRedirect();

    // What is configured is no longer what was last provisioned.
    expect($preview->fresh()->needsProvisioning())->toBeTrue();
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

    $this->actingAs($admin)
        ->post(route('admin.projects.previews.disable', [$project, $otherPreview]))
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
