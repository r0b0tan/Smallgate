<?php

/**
 * A preview type the operator has switched off in config('previews.target_types')
 * -- for example static directories on an installation without a preview
 * domain. It must not be offered, accepted, provisioned or linked to.
 */

use App\Enums\PreviewStatus;
use App\Models\Preview;
use App\Models\Project;

beforeEach(function () {
    config(['previews.target_types' => ['upstream_url']]);
});

it('offers only the enabled types in the admin form', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin())
        ->get(route('admin.projects.previews.create', $project))
        ->assertOk()
        ->assertSee('value="upstream_url"', false)
        ->assertDontSee('value="static_directory"', false);
});

it('refuses a switched-off type from the form', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin())
        ->from(route('admin.projects.previews.create', $project))
        ->post(route('admin.projects.previews.store', $project), [
            'name' => 'Vorschau',
            'slug' => 'vorschau',
            'hostname' => 'test.'.config('previews.base_domain'),
            'target_type' => 'static_directory',
            'target' => '/srv/previews/test',
        ])
        ->assertSessionHasErrors('target_type');

    expect(Preview::count())->toBe(0);
});

it('still accepts an enabled type', function () {
    $project = Project::factory()->create();

    $this->actingAs($this->admin())
        ->post(route('admin.projects.previews.store', $project), [
            'name' => 'Vorschau',
            'slug' => 'vorschau',
            'target_type' => 'upstream_url',
            'target' => 'https://staging.clickit-digital.test/holzmann',
        ])
        ->assertSessionHasNoErrors();

    expect(Preview::count())->toBe(1);
});

it('refuses to provision an existing preview of a switched-off type', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create([
        'status' => PreviewStatus::Draft,
    ]);

    $this->actingAs($this->admin())
        ->post(route('admin.projects.previews.provision', [$project, $preview]))
        ->assertSessionHas('error');

    expect($preview->refresh()->status)->not->toBe(PreviewStatus::Available);
});

it('does not send a customer to a live preview of a switched-off type', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->available()->create();
    $customer = $this->customerUser($project->customer);

    expect($preview->url())->toBeNull()
        ->and($preview->openUrl())->toBeNull();

    $this->actingAs($customer)
        ->get(route('portal.previews.show', [$project, $preview]))
        ->assertOk()
        ->assertSee('derzeit nicht erreichbar');
});

it('keeps sending a customer to a live preview of an enabled type', function () {
    $project = Project::factory()->create();
    $preview = Preview::factory()->for_project($project)->upstream()->create();

    $this->actingAs($this->customerUser($project->customer))
        ->get(route('portal.previews.show', [$project, $preview]))
        ->assertRedirect('https://staging.clickit-digital.test/zimmerei-holzmann');
});

it('leaves the preview types to .env in the production stack', function () {
    // Static directories are served in production since ADR 0003; the stack
    // no longer narrows the types behind the operator's back.
    expect(file_get_contents(base_path('compose.prod.yaml')))
        ->not->toContain('PREVIEW_TARGET_TYPES');
});
